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

    private $appId;

    public function __construct($appId, $appSecret, $accessCode, $itemRepository, $credentialRepository)
    {
        parent::__construct($itemRepository);

        $this->appId = $appId;

        $this->api = new Pinterest\Pinterest($appId, $appSecret);

        $this->credentialRepository = $credentialRepository;

        $code = $this->extractCode($accessCode);

        $this->getAccessToken($code);
    }

    public function getResultFromApi($options)
    {
        $result = array();

        $boardname = $options->pinterest_username . '/' . $options->pinterest_boardname;

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
                    } catch (\FacebookApiException $e) {
                        $this->logger->warning(self::TYPE . ' feeds can\'t be updated', array('data' => $e->getMessage())); //TODO => handle FacebookApiException
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
            } catch (\FacebookApiException $e) {
                $this->logger->warning('initial load for ' . self::TYPE . ' feeds failed', array('data' => $e->getMessage())); //TODO => handle FacebookApiException
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
                error_log('-------- need new code ---------');
                $this->logger->error(self::TYPE . ' access code expired. Please provide new code in pb_social extension configuration.', array('data' => self::TYPE . ' access code invalid. Provide new code in pb_social extension configuration.'));
                return null;
            }
        }

        $this->api->auth->setOAuthToken($credential->getAccessToken());

        //testrequest
        try {
            $this->api->request->get('/me');
        } catch (Pinterest\Exceptions\PinterestException $e) {
            $this->credentialRepository->deleteCredential($credential);
            $this->logger->warning(self::TYPE . ' exception - ' . $e->getMessage());
            $this->logger->info('Please provide new ' . self::TYPE . ' access code');
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
