<?php

namespace PlusB\PbSocial\Adapter;

$extensionPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('pb_social') . 'Resources/Private/Libs/';
require_once $extensionPath . 'pinterest/autoload.php';
use DirkGroenen\Pinterest;
use PlusB\PbSocial\Domain\Model\Credential;
use PlusB\PbSocial\Domain\Model\Feed;
use PlusB\PbSocial\Domain\Model\Item;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2016 Ramon Mohi <rm@plusb.de>, plusB
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

class PinterestAdapter extends SocialMediaAdapter
{

    const TYPE = 'pinterest';

    private $api;

    private $credentialRepository;

    public $isValid = false, $validationMessage = "";
    private $appId, $appSecret, $accessCode, $options;

    /**
     * @param mixed $appId
     */
    public function setAppId($appId)
    {
        $this->appId = $appId;
    }

    /**
     * @param mixed $appSecret
     */
    public function setAppSecret($appSecret)
    {
        $this->appSecret = $appSecret;
    }

    /**
     * @param mixed $accessCode
     */
    public function setAccessCode($accessCode)
    {
        $this->accessCode = $accessCode;
    }

    /**
     * @param mixed $options
     */
    public function setOptions($options)
    {
        $this->options = $options;
    }

    public function __construct($appId, $appSecret, $accessCode, $itemRepository, $credentialRepository, $options)
    {
        parent::__construct($itemRepository);
        /**
         * todo: quickfix - but we better add a layer for adapter inbetween, here after "return $this" intance is not completet but existend (AM)
         */
        /* validation - interrupt instanciating if invalid */
        if($this->validateAdapterSettings(
                array(
                    'appId' => $appId,
                    'appSecret' => $appSecret,
                    'accessCode' => $accessCode,
                    'options' => $options
                )) === false)
        {return $this;}
        /* validated */

        $this->api = new Pinterest\Pinterest($this->appId, $this->appSecret);

        $this->credentialRepository = $credentialRepository;

        $code = $this->extractCode($this->accessCode);

        $this->getAccessToken($code);
    }


    /**
     * validates constructor input parameters in an individual way just for the adapter
     *
     * @param $parameter
     * @return bool
     */
    public function validateAdapterSettings($parameter)
    {
        $this->setAppId($parameter['appId']);
        $this->setAppSecret($parameter['appSecret']);
        $this->setAccessCode($parameter['accessCode']);
        $this->setOptions($parameter['options']);

        if (empty($this->appId) || empty($this->appSecret) ||  empty($this->accessCode)) {
            $this->validationMessage = self::TYPE . ' credentials not set';
        } elseif (empty($this->options->pinterest_username) || empty($this->options->pinterest_username)) {
            $this->validationMessage = self::TYPE . ' username or boardname not defined';
        } else {
            $this->isValid = true;
        }

        return $this->isValid;
    }

    public function getResultFromApi()
    {
        $options = $this->options;
        $result = array();

        $boardname = $options->pinterest_username . '/' . $options->pinterest_boardname;
        /*
        * todo: duplicate cache writing, must be erazed here - $searchId is invalid cache identifier OptionService:getCacheIdentifierElementsArray returns valid one (AM)
        */
        foreach (explode(',', $options->username) as $searchId) {
            $searchId = trim($searchId);
            $feeds = $this->itemRepository->findByTypeAndCacheIdentifier(self::TYPE, $searchId);

            if ($feeds && $feeds->count() > 0) {
                $feed = $feeds->getFirst();

                if ($options->devMod || ($feed->getDate()->getTimestamp() + $options->refreshTimeInMin * 60) < time()) {
                    try {
                        $feed->setDate(new \DateTime('now'));
                        $feed->setResult($this->getPosts($boardname));
                        $this->itemRepository->updateFeed($feed);
                    } catch (\Exception $e) {
                        $this->logError("feeds can't be updated - " . $e->getMessage());
                    }
                }
                $result[] = $feed;
                continue;
            }

            try {
                $feed = new Item(self::TYPE);
                $feed->setCacheIdentifier($searchId);
                $feed->setResult($this->getPosts($boardname));

                // save to DB and return current feed
                $this->itemRepository->saveFeed($feed);
                $result[] = $feed;
            } catch (\Exception $e) {
                $this->logError('initial load for feed failed - ' . $e->getMessage());
            }
        }

        return $this->getFeedItemsFromApiRequest($result, $options);
    }

    public function getFeedItemsFromApiRequest($result, $options)
    {
        $rawFeeds = array();
        $feedItems = array();

        if (!empty($result)) {
            foreach ($result as $pin_feed) {
                $rawFeeds[self::TYPE . '_' . $pin_feed->getCacheIdentifier() . '_raw'] = $pin_feed->getResult();
                $i = 0;
                foreach ($pin_feed->getResult()->data as $pin) {
                    if ($pin->image && ($i < $options->feedRequestLimit)) {
                        $i++;
                        $feed = new Feed(self::TYPE, $pin);
                        $feed->setText($this->trim_text($pin->note, $options->textTrimLength, true));
                        $feed->setImage($pin->image->original->url);
                        $link = $pin->link ? $pin->link : $pin->url;
                        $feed->setLink($link);
                        $d = new \DateTime($pin->created_at);
                        $feed->setTimeStampTicks($d->getTimestamp());
                        $feedItems[] = $feed;
                    }
                }
            }
        }

        return array('rawFeeds' => $rawFeeds, 'feedItems' => $feedItems);
    }

    public function getPosts($boardname)
    {
        $fields = array(
            'fields' => 'id,link,counts,note,created_at,image[small],url'
        );

        return json_encode($this->api->pins->fromBoard($boardname, $fields));
    }

    private function getAccessToken($code)
    {
        $apiKey = $this->appId;

        # get access token from database #
        $credentials = $this->credentialRepository->findByTypeAndAppId(self::TYPE, $apiKey);

        if ($credentials->count() > 1) {
            foreach ($credentials as $c) {
                if ($c->getAccessToken != '') {
                    $credential = $c;
                } else {
                    $this->credentialRepository->remove($c);
                }
            }
        } else {
            $credential = $credentials->getFirst();
        }

        if (!isset($credential) || !$credential->isValid()) {
            # validate code to get access token #
            $token = $this->api->auth->getOAuthToken($code);
            $access_token = $token->access_token;
            if ($access_token) {
                if (isset($credential)) {
                    $credential->setAccessToken($access_token);
                    $this->credentialRepository->update($credential);
                } else {
                    # create new credential #
                    $credential = new Credential(self::TYPE, $apiKey);
                    $credential->setAccessToken($access_token);
                    $this->credentialRepository->saveCredential($credential);
                }
            } else {
                $this->logError('access code expired. Please provide new code in pb_social extension configuration.');
                return null;
            }
        }

        $this->api->auth->setOAuthToken($credential->getAccessToken());

        //testrequest
        try {
            $this->api->request->get('me');
        } catch (\Exception $e) {
            $this->credentialRepository->deleteCredential($credential);
            $this->logError('exception: ' . $e->getMessage());
            $this->logWarning(': Please provide new access code');
        }
    }

    /** Converts url-encoded code
     * @param $accessCode
     * @return string
     */
    public function extractCode($accessCode)
    {
        $accessCode = urldecode($accessCode);

        if (strpos($accessCode, '&state=')) {
            $accessCode = explode('&state=', $accessCode)[0];
        }

        if (strpos($accessCode, 'code=') > -1) {
            $parts = explode('code=', $accessCode);
            $code = strpos($parts[0], 'http') > -1 || $parts[0] == '' ? $parts[1] : $parts[0];
        } elseif (strpos($accessCode, '=') == 0) {
            $code = ltrim($accessCode, '=');
        } else {
            $code = $accessCode;
        }

        return $code;
    }
}
