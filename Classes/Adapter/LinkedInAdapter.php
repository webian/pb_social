<?php

namespace PlusB\PbSocial\Adapter;

$extensionPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('pb_social') . 'Resources/Private/Libs/';
require_once $extensionPath . 'linkedin/src/Client.php'; # Include provider library
// ... please, add composer autoloader first
include_once $extensionPath . 'linkedin' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use LinkedIn\AccessToken;
use LinkedIn\Client;
use PlusB\PbSocial\Domain\Model\Credential;
use PlusB\PbSocial\Domain\Model\Feed;
use PlusB\PbSocial\Domain\Model\Item;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2018 Ramon Mohi <rm@plusb.de>, plus B
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

class LinkedInAdapter extends SocialMediaAdapter
{

    const TYPE = 'linkedin';
    const EXTKEY = 'pb_social';
    const linkedin_company_post_uri = "https://www.linkedin.com/feed/update/urn:li:activity:";

    public $isValid = false, $validationMessage = "";
    private $apiKey, $apiSecret, $apiCallback, $token, $options;

    /**
     * @param mixed $apiKey
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * @param mixed $apiSecret
     */
    public function setApiSecret($apiSecret)
    {
        $this->apiSecret = $apiSecret;
    }

    /**
     * @param mixed $apiCallback
     */
    public function setApiCallback($apiCallback)
    {
        $this->apiCallback = $apiCallback;
    }

    /**
     * @param mixed $token
     */
    public function setToken($token)
    {
        $this->token = $token;
    }

    /**
     * @param mixed $options
     */
    public function setOptions($options)
    {
        $this->options = $options;
    }

    private $api;

    /**
     * credentialRepository
     *
     * @var \PlusB\PbSocial\Domain\Repository\CredentialRepository
     * @inject
     */
    protected $credentialRepository;

    public function __construct($apiKey, $apiSecret, $apiCallback, $token, $itemRepository, $credentialRepository, $options)
    {
        parent::__construct($itemRepository);
        /**
         * todo: quick fix - but we'd better add a layer for adapter in between, here after "return $this" instance is not completed but existing (AM)
         */
        /* validation - interrupt instanciating if invalid */
        if($this->validateAdapterSettings(
                array(
                    'apiKey' => $apiKey,
                    'apiSecret' => $apiSecret,
                    'apiCallback' => $apiCallback,
                    'token' => $token,
                    'options' => $options
                )) === false)
        {return $this;}
        /* validated */

        $this->api =  new Client($this->apiKey,$this->apiSecret);

        $this->credentialRepository = $credentialRepository;

        // get access token from database
        $this->setAccessToken($this->token, $this->apiKey);
    }

    /**
     * validates constructor input parameters in an individual way just for the adapter
     *
     * @param $parameter
     * @return bool
     */
    public function validateAdapterSettings($parameter)
    {
        $this->setApiKey($parameter['apiKey']);
        $this->setApiSecret($parameter['apiSecret']);
        $this->setApiCallback($parameter['apiCallback']);
        $this->setToken($parameter['token']);
        $this->setOptions($parameter['options']);

        if (empty($this->apiKey) || empty($this->apiSecret) ||  empty($this->token) ||  empty($this->apiCallback)) {
            $this->validationMessage = self::TYPE . ' credentials not set';
        } elseif (empty($this->options->companyIds)) {
            $this->validationMessage = self::TYPE . ' no search term defined';
        } else {
            $this->isValid = true;
        }

        return $this->isValid;
    }

    public function getResultFromApi()
    {
        $options = $this->options;
        $result = array();

        # set filters
        $filters = (@$options->settings['linkedinFilterChoice'] != '')?'&'.$options->settings['linkedinFilterChoice']:'';

        # get company updates
        # additional filters for job postings, new products and status updates may be applied
        foreach (explode(',', $options->companyIds) as $searchId) {

            $searchId = trim($searchId);
            /*
            * todo: duplicate cache writing, must be erazed here - $searchId is invalid cache identifier OptionService:getCacheIdentifierElementsArray returns valid one (AM)
            */
            if ($searchId != ""){
                $feeds = $this->itemRepository->findByTypeAndCacheIdentifier(self::TYPE, $searchId);

                if ($feeds && $feeds->count() > 0) {
                    $feed = $feeds->getFirst();
                    /**
                     * todo: (AM) "$options->refreshTimeInMin * 60) < time()" locks it to a certain cache lifetime - users want to bee free, so... change!
                     * todo: try to get rid of duplicate code
                     */
                    if ($options->devMod || ($feed->getDate()->getTimestamp() + $options->refreshTimeInMin * 60) < time()) {
                        try {
                            # api call
                            $companyUpdates = $this->api->get('companies/' . $searchId .'/updates?format=json' . $filters); # filters is empty ("") if no filters are applied..
                            $feed->setDate(new \DateTime('now'));
                            $feed->setResult(json_encode($companyUpdates));
                            $this->itemRepository->updateFeed($feed);
                        } catch (\Exception $e) {
                            $this->logAdapterError("feeds cannot be updated  - " . $e->getMessage(), 1558435552);
                            continue;
                        }
                    }
                    $result[] = $feed;
                    continue;
                }

                try {



                    # api call
                    $companyUpdates = $this->api->get('companies/' . $searchId .'/updates?format=json' . $filters);
                    $feed = new Item(self::TYPE);
                    $feed->setCacheIdentifier($searchId);
                    $feed->setResult(json_encode($companyUpdates));

                    // save to DB and return current feed
                    $this->itemRepository->saveFeed($feed);
                    $result[] = $feed;
                } catch (\Exception $e) {
                    $this->logAdapterError("get_updates failed - " . $e->getMessage(), 1558435546);
                    throw $e;
                }
            }
        }

        return $this->getFeedItemsFromApiRequest($result, $options);
    }

    public function getFeedItemsFromApiRequest($result, $options)
    {
        $rawFeeds = array();
        $feedItems = array();

        if (!empty($result)) {
            foreach ($result as $linkedin_feed) {
                $rawFeeds[self::TYPE . '_' . $linkedin_feed->getCacheIdentifier() . '_raw'] = $linkedin_feed->getResult();
                $i = 0;
                if (is_array($linkedin_feed->getResult()->values)) {
                    foreach ($linkedin_feed->getResult()->values as $rawFeed) {
                        if ($i < $options->feedRequestLimit)
                        {
                            $feed = new Feed(self::TYPE, $rawFeed);
                            $feed->setId($rawFeed->timestamp);
                            $feed->setText($this->trim_text($rawFeed->updateContent->companyStatusUpdate->share->comment, $options->textTrimLength, true));
                            $feed->setImage($rawFeed->updateContent->companyStatusUpdate->share->content->thumbnailUrl);
                            $link = self::linkedin_company_post_uri . array_reverse(explode('-', $rawFeed->updateKey))[0];
                            $feed->setLink($link);
                            $feed->setTimeStampTicks($rawFeed->timestamp);
                            $feedItems[] = $feed;
                            $i++;
                        }
                    }
                }
            }
        }

        return array('rawFeeds' => $rawFeeds, 'feedItems' => $feedItems);
    }

    private function setAccessToken($token, $apiKey)
    {
        if (empty($token))
        {
            $this->logAdapterError('Access token empty.', 1558435558);
            return null;
        }
        if (empty($apiKey))
        {
            $this->logAdapterError('Client ID empty.', 1558435560);
            return null;
        }
        # generate AccessToken class
        try
        {
            $access_token = new AccessToken();
            $access_token->setToken($token);
        }
        catch (\Exception $e)
        {
            $this->logAdapterError('failed to setup AccessToken - ' . $e->getMessage(), 1558435565);
            return null;
        }
        # get access token from database #
        $credentials = $this->credentialRepository->findByTypeAndAppId(self::TYPE, $apiKey);

        if ($credentials->count() > 1)
        {
            foreach ($credentials as $c)
            {
                if ($c->getAccessToken != '')
                {
                    $credential = $c;
                } else {
                    $this->credentialRepository->remove($c);
                }
            }
        }
        else {
            $credential = $credentials->getFirst();
        }

//        if (!empty($this->api->getAccessTokenExpiration()) && $this->api->getAccessTokenExpiration() < strtotime('tomorrow'))
//        {
//            # api doc says you can reuse the old access code.. maybe I misinterpreted something? we'll give it a shot
//            # https://developer.linkedin.com/docs/oauth2
//            # todo: renew LinkedIn access token when $accessToken->getExpiresAt() < strtotime('tomorrow')
//        }

        if (!isset($credential) || !$credential->isValid())
        {
            if (isset($credential))
            {
                $credential->setAccessToken($token);
                $this->credentialRepository->update($credential);
            }
            else {
                # create new credential #
                $credential = new Credential(self::TYPE, $apiKey);
                $credential->setAccessToken($token);
                $this->credentialRepository->saveCredential($credential);
            }
        }

        $this->api->setAccessToken($access_token);

        return $credential->getAccessToken();
    }
}
