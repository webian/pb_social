<?php

namespace PlusB\PbSocial\Adapter;

$extensionPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('pb_social') . 'Resources/Private/Libs/';
require $extensionPath . 'facebook/src/Facebook/autoload.php';

use Facebook\Facebook;
use PlusB\PbSocial\Domain\Model\Feed;
use PlusB\PbSocial\Domain\Model\Item;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;


/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2016 Ramon Mohi <rm@plusb.de>, plus B
 *  (c) 2018 Arend Maubach <am@plusb.de>, plusB
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
class FacebookAdapter extends SocialMediaAdapter
{

    const TYPE = 'facebook';

    const api_url = 'https://graph.facebook.com', api_version = "v3.2";

    private $api;

    private $access_token;

    public $isValid = false, $validationMessage = "";
    private $apiId, $apiSecret, $options;

    /**
     * @param mixed $apiId
     */
    public function setApiId($apiId)
    {
        $this->apiId = $apiId;
    }

    /**
     * @param mixed $apiSecret
     */
    public function setApiSecret($apiSecret)
    {
        $this->apiSecret = $apiSecret;
    }

    /**
     * @param mixed $options
     */
    public function setOptions($options)
    {
        $this->options = $options;
    }

    public function __construct($apiId, $apiSecret, $itemRepository, $options,
        $ttContentUid,
        $ttContentPid,
        $cacheIdentifier)
    {
        parent::__construct($itemRepository, $cacheIdentifier, $ttContentUid,
            $ttContentPid);

        /* validation - interrupt instanciating if invalid */
        if($this->validateAdapterSettings(
            array(
                'apiId' => $apiId,
                'apiSecret' => $apiSecret,
                'options' => $options
            )) === false)
        {
            throw new \Exception( self::TYPE . ' ' . $this->validationMessage );
        }

        $this->api = new Facebook(['app_id' => $this->apiId,'app_secret' => $this->apiSecret,'default_graph_version' => self::api_version]);

        $this->access_token =  $this->api->getApp()->getAccessToken();
        $this->api->setDefaultAccessToken($this->access_token);
    }

    /**
     * validates constructor input parameters in an individual way just for the adapter
     *
     * @param $parameter
     * @return bool
     */
    public function validateAdapterSettings($parameter)
    {
        $this->setApiId($parameter['apiId']);
        $this->setApiSecret($parameter['apiSecret']);
        $this->setOptions($parameter['options']);

        if (empty($this->apiId) || empty($this->apiSecret)) {
            $this->validationMessage = 'credentials not set: ' . (empty($this->apiId)?'apiId ':''). (empty($this->apiSecret)?'apiSecret ':'');
        } elseif (empty($this->options->settings['facebookSearchIds'])) {
            $this->validationMessage = 'no search term defined ("Facebook search IDs" in flexform settings) ';
        } else {
            $this->isValid = true;
        }

        return $this->isValid;
    }

    public function getResultFromApi()
    {
        $options = $this->options;
        $result = array();
        $feed = null;

        $facebookSearchIds = $options->settings['facebookSearchIds'];
        if (empty($facebookSearchIds)) {
            $this->logAdapterWarning('- no search term defined', 1558435713);
            return null;
        }

        foreach (explode(',', $facebookSearchIds) as $searchId) {
            $searchId = trim($searchId);
            $posts = null;

            $feeds = $this->itemRepository->findByTypeAndCacheIdentifier(self::TYPE, $this->cacheIdentifier);

            try {
                $posts = $this->getPosts($searchId, $options->feedRequestLimit, $options->settings['facebookEdge']);
            }
            catch (\Exception $e) {
                throw new \Exception( $e->getMessage() );
            }

            if ($feeds && $feeds->count() > 0) {
                $feed = $feeds->getFirst();
                /**
                 * todo: (AM) "$options->refreshTimeInMin * 60) < time()" locks it to a certain cache lifetime - users want to be free, so... change by conf
                 */
                if ($options->devMod || ($feed->getDate()->getTimestamp() + $options->refreshTimeInMin * 60) < time()) {

                    //update feed
                    if ($posts !== null) {
                        $feed->setDate(new \DateTime('now'));
                        $feed->setResult($posts);
                        $this->itemRepository->updateFeed($feed);
                    }

                }
                $result[] = $feed;

                //after having updated, roll over in foreach
                continue;
            }

            //insert new feed
            if ($posts !== null) {
                $feed = new Item(self::TYPE);
                $feed->setCacheIdentifier($this->cacheIdentifier);
                $feed->setResult($posts);
                // save to DB and return current feed
                $this->itemRepository->saveFeed($feed);
                $result[] = $feed;
            }
        }

        return $this->getFeedItemsFromApiRequest($result, $options);
    }

    public function getFeedItemsFromApiRequest($result, $options)
    {
        $rawFeeds = array();
        $feedItems = array();

        //this can probably go in SocialMediaAdapter
        if (!empty($result)) {
            foreach ($result as $fb_feed) {
                $rawFeeds[self::TYPE . '_' . $fb_feed->getCacheIdentifier() . '_raw'] = $fb_feed->getResult();
                foreach ($fb_feed->getResult()->data as $rawFeed) {
                    if ($options->onlyWithPicture && (empty($rawFeed->picture) || empty($rawFeed->full_picture))) {
                        continue;
                    }
                    $feed = new Feed(self::TYPE, $rawFeed);
                    $feed->setId($rawFeed->id);
                    $feed->setText($this->trim_text($rawFeed->message, $options->textTrimLength, true));
                    if (property_exists($rawFeed, 'picture')) {
                        $feed->setImage(urldecode($rawFeed->picture));
                    }

                    // ouput link to facebook post instead of article
                    if ($options->settings['facebookLinktopost']) {
                        $feed->setLink('https://facebook.com/' . $rawFeed->id);
                    } else {
                        $feed->setLink($rawFeed->link);
                    }
                    $d = new \DateTime($rawFeed->created_time);
                    $feed->setTimeStampTicks($d->getTimestamp());

                    $feedItems[] = $feed;
                }
            }
        }

        return array('rawFeeds' => $rawFeeds, 'feedItems' => $feedItems);
    }

    /** Make API request via Facebook sdk function
     *
     * @param string $searchId
     * @param int $limit
     * @return string
     */
    public function getPosts($searchId, $limit, $edge)
    {
        //endpoint
            switch ($edge){
                case 'feed': $request = 'feed'; break;
                case 'posts': $request = 'posts'; break;
                default: $request = 'feed';
            }

        $endpoint = '/' . $searchId . '/' . $request;

        //params
            //set default parameter list in case s.b messes up with TypoScript
            $faceBookRequestParameter =
                'picture,
               
                created_time,
                full_picture';

            //overwritten by Typoscript
            if(isset($this->options->settings['facebook']['requestParameterList']) && is_string($this->options->settings['facebook']['requestParameterList'])){
                $faceBookRequestParameter =  $this->options->settings['facebook']['requestParameterList'];
            }

            //always prepending id, link and message
            $faceBookRequestParameter = 'id,link,message,' . $faceBookRequestParameter;

        $params = [
            'fields' => $faceBookRequestParameter,
            'limit' => $limit
        ];

        try {
            /** @var \Facebook\FacebookResponse $resp */
            $resp = $this->api->sendRequest(
                'GET',
                $endpoint,
                $params
            );

        } catch (\Facebook\Exceptions\FacebookSDKException $e) {
            throw new \Exception( '1558011840 ' . $e->getMessage() );
        }

        if (empty(json_decode($resp->getBody())->data) || json_encode($resp->getBody()->data) == null) {
            throw new \Exception( '1558011842 no posts found for ' . $searchId );
        }

        return $resp->getBody();
    }
}
