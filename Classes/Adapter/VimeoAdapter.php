<?php

namespace PlusB\PbSocial\Adapter;

$extensionPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('pb_social') . 'Resources/Private/Libs/';
require $extensionPath . 'vimeo/autoload.php';
use PlusB\PbSocial\Domain\Model\Feed;
use PlusB\PbSocial\Domain\Model\Item;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2016 Ramon Mohi <rm@plusb.de>, plus B
 *  (c) 2018 Arend Maubach <am@plusb.de>, plus B
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

class VimeoAdapter extends SocialMediaAdapter
{

    const TYPE = 'vimeo';

    const VIMEO_LINK = 'https://player.vimeo.com';

    public $isValid = false, $validationMessage = "";
    private $clientIdentifier, $clientSecret, $accessToken, $options;

    /**
     * @param mixed $clientIdentifier
     */
    public function setClientIdentifier($clientIdentifier)
    {
        $this->clientIdentifier = $clientIdentifier;
    }

    /**
     * @param mixed $clientSecret
     */
    public function setClientSecret($clientSecret)
    {
        $this->clientSecret = $clientSecret;
    }

    /**
     * @param mixed $accessToken
     */
    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;
    }

    /**
     * @param mixed $options
     */
    public function setOptions($options)
    {
        $this->options = $options;
    }



    public function __construct(
        $clientIdentifier,
        $clientSecret,
        $accessToken,
        $itemRepository,
        $options,
        $ttContentUid,
        $ttContentPid,
        $cacheIdentifier
    )
    {
        parent::__construct($itemRepository, $cacheIdentifier, $ttContentUid, $ttContentPid);


        /* validation - interrupt instanciating if invalid */
        if($this->validateAdapterSettings(
                array(
                    'clientIdentifier' => $clientIdentifier,
                    'clientSecret' => $clientSecret,
                    'accessToken' => $accessToken,
                    'options' => $options
                )) === false)
        {
            throw new \Exception( self::TYPE . ' ' . $this->validationMessage, 1558521262);
        }
        /* validated */

        $this->api = new \Vimeo\Vimeo($clientIdentifier, $clientSecret, $accessToken);
    }

    /**
     * validates constructor input parameters in an individual way just for the adapter
     *
     * @param $parameter
     * @return bool
     */
    public function validateAdapterSettings($parameter)
    {
        $this->setClientIdentifier($parameter['clientIdentifier']);
        $this->setClientSecret($parameter['clientSecret']);
        $this->setAccessToken($parameter['accessToken']);
        $this->setOptions($parameter['options']);

        if (empty($this->clientIdentifier) || empty($this->clientSecret) || empty($this->accessToken)) {
            $this->validationMessage = self::TYPE . ' credentials not set';
        } elseif (empty($this->options->vimeoChannel)) {
            $this->validationMessage = self::TYPE . ' no channel defined';
        } else {
            $this->isValid = true;
        }

        return $this->isValid;
    }

    public function getResultFromApi()
    {
        $options = $this->options;
        $result = array();

        $fields = array(
            // 'key' => $this->appKey,
            // 'per_page' => $options->feedRequestLimit,
            // 'part' => 'snippet'
        );

        $searchTerms = explode(',', $options->settings['vimeoChannel']);

        foreach ($searchTerms as $searchString) {

            $searchString = trim($searchString);
            $feeds = $this->itemRepository->findByTypeAndCacheIdentifier(self::TYPE, $this->cacheIdentifier);
            if ($feeds && $feeds->count() > 0) {
                $feed = $feeds->getFirst();
                /**
                 * todo: (AM) "$options->refreshTimeInMin * 60) < time()" locks it to a certain cache lifetime - users want to bee free, so... change!
                 * todo: try to get rid of duplicate code
                 */
                if ($options->devMod || ($feed->getDate()->getTimestamp() + $options->refreshTimeInMin * 60) < time()) {
                    try {
                        $feed->setDate(new \DateTime('now'));
                        $feed->setResult($this->getPosts($searchString, $fields, $options));
                        $this->itemRepository->updateFeed($feed);
                        $result[] = $feed;
                    } catch (\Exception $e) {
                        throw new \Exception("feeds can't be updated. " . $e->getMessage(), 1558435657);
                    }
                }
                continue;
            }

            try {
                $feed = new Item(self::TYPE);
                $feed->setCacheIdentifier($searchString);
                $feed->setResult($this->getPosts($searchString, $fields, $options));
                // save to DB and return current feed
                $this->itemRepository->saveFeed($feed);
                $result[] = $feed;
            } catch (\Exception $e) {
                throw new \Exception('initial load for feed failed' . $e->getMessage(), 1558435662);
            }
        }

        return $this->getFeedItemsFromApiRequest($result, $options);
    }

    public function getFeedItemsFromApiRequest($result, $options)
    {
        $rawFeeds = array();
        $feedItems = array();

        if (!empty($result)) {
            foreach ($result as $vimeo_feed) {
                /**
                 * todo: invalid cache identifier
                 */
                $rawFeeds[self::TYPE . '_' . $vimeo_feed->getCacheIdentifier() . '_raw'] = $vimeo_feed->getResult();
                foreach ($vimeo_feed->getResult()->body->data as $rawFeed) {
                    $feed = new Feed(self::TYPE, $rawFeed);
                    $feed->setId($rawFeed->link);
                    $feed->setText($this->trim_text($rawFeed->name, $options->textTrimLength, true));
                    $feed->setImage($rawFeed->pictures->sizes[5]->link);
                    $feed->setLink(self::VIMEO_LINK . $rawFeed->link);
                    $d = new \DateTime($rawFeed->created_time);

                    $feed->setTimeStampTicks($d->getTimestamp());
                    $feedItems[] = $feed;
                }
            }
        }

        return array('rawFeeds' => $rawFeeds, 'feedItems' => $feedItems);
    }

    public function getPosts($searchString, $fields, $options)
    {
        if ($searchString == 'me') {
            $url = '/me/videos';
        } else {
            $url = '/channels/' . $searchString . '/videos';
        }

        $response = $this->api->request($url, array('per_page' => $options->feedRequestLimit), 'GET');
        return json_encode($response);
    }
}
