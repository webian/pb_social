<?php

namespace PlusB\PbSocial\Adapter;

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

class GooglePlusAdapter extends SocialMediaAdapter
{

    const TYPE = 'googleplus';

    const API_URL = 'https://www.googleapis.com/plus/v1/people/';

    private $appKey;

    private $api;

    public function __construct($appKey, $itemRepository)
    {
        parent::__construct($itemRepository);

        $this->appKey = $appKey;

        //todo: use google client
//        $client = new \Google_Client();
//        $client->setDeveloperKey($appKey);
//        $this->api = new \Google_Service_Plus($client);
//        $getResults = $this->api->activities->listActivities($searchId, 'public', array('maxResults' => $options->feedRequestLimit));
    }

    public function getResultFromApi($options)
    {
        $result = array();

        $googlePlusSearchIds = $options->settings['googleSearchIds'];

        foreach (explode(',', $googlePlusSearchIds) as $searchId) {
            $searchId = trim($searchId);
            $feeds = $this->itemRepository->findByTypeAndCacheIdentifier(self::TYPE, $searchId);

            if ($feeds && $feeds->count() > 0) {
                $feed = $feeds->getFirst();
                if ($options->devMod || ($feed->getDate()->getTimestamp() + $options->refreshTimeInMin * 60) < time()) {
                    try {
                        $posts = $this->getPosts($searchId, $options->feedRequestLimit);
                        $feed->setDate(new \DateTime('now'));
                        $feed->setResult($posts);
                        $this->itemRepository->updateFeed($feed);
                    } catch (\Exception $e) {
                        $this->logger->error(self::TYPE . ' feeds cant be updated', array('data' => $e->getMessage()));
                    }
                }
                $result[] = $feed;
                continue;
            }

            try {
                $posts = $this->getPosts($searchId, $options->feedRequestLimit);
                $feed = new Item(self::TYPE);
                $feed->setCacheIdentifier($searchId);
                $feed->setResult($posts);
                // save to DB and return current feed
                $this->itemRepository->saveFeed($feed);
                $result[] = $feed;
            } catch (\Exception $e) {
                $this->logger->error('initial load for ' . self::TYPE . ' feeds failed', array('data' => $e->getMessage()));
            }
        }

        return $this->getFeedItemsFromApiRequest($result, $options);
    }

    public function getFeedItemsFromApiRequest($result, $options)
    {
        $rawFeeds = array();
        $feedItems = array();

        $placeholder = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('pb_social') . 'Resources/Public/Icons/Placeholder/gplus.jpg';

        if (!empty($result)) {
            foreach ($result as $gp_feed) {
                $rawFeeds[self::TYPE . '_' . $gp_feed->getCacheIdentifier() . '_raw'] = $gp_feed->getResult();
                foreach ($gp_feed->getResult()->items as $rawFeed) {
                    if ($options->onlyWithPicture && empty($rawFeed->object->attachments[0]->image->url)) {
                        continue;
                    }
                    $feed = new Feed(self::TYPE, $rawFeed);
                    $feed->setId($rawFeed->id);
                    $feed->setText($this->trim_text($rawFeed->title, $options->textTrimLength, true));
//                    $img = property_exists($rawFeed, 'picture') ? urldecode($rawFeed->picture) : $placeholder;
                    $feed->setImage($rawFeed->object->attachments[0]->image->url);

                    // only for type photo
                    if ($rawFeed->object->attachments[0]->objectType == 'photo' && $rawFeed->object->attachments[0]->fullImage->url != '') {
                        $feed->setImage($rawFeed->object->attachments[0]->fullImage->url);
                    }

                    // only if no title is set but somehow the video is labeled
                    if ($rawFeed->title == '' && $rawFeed->object->attachments[0]->displayName != '') {
                        $feed->setText($this->trim_text($rawFeed->object->attachments[0]->displayName, $options->textTrimLength, true));
                    }

                    $feed->setLink($rawFeed->url);
                    $d = new \DateTime($rawFeed->updated);
                    $feed->setTimeStampTicks($d->getTimestamp());
                    $feedItems[] = $feed;
                }
            }
        }

        return array('rawFeeds' => $rawFeeds, 'feedItems' => $feedItems);
    }

    public function getPosts($searchId, $limit)
    {
        $headers = array('Content-Type: application/json');
        $fields = array('key' => $this->appKey, 'format' => 'json', 'ip' => $_SERVER['REMOTE_ADDR']);

        $url = self::API_URL . $searchId . '/activities/public?maxResults=' . $limit . '&' . http_build_query($fields);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $curl_response = curl_exec($ch);

        // check if google error object is set => throw exception with json response
        if (property_exists(json_decode($curl_response), 'error')) {
            throw new \Exception($curl_response);
        }

        return $curl_response;
    }
}
