<?php

namespace PlusB\PbSocial\Adapter;
use PlusB\PbSocial\Domain\Model\Item;
use PlusB\PbSocial\Domain\Model\Feed;

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

class FacebookAdapter extends SocialMediaAdapter {

    const TYPE = 'facebook';

    private $api;

    public function __construct($apiId, $apiSecret, $itemRepository){

        parent::__construct($itemRepository);

        $this->api = new \Facebook(array('appId' => $apiId, 'secret' => $apiSecret));

    }

    public function getResultFromApi($options){

        $result = array();

        $facebookSearchIds = $options->settings['facebookSearchIds'];
        if (empty($facebookSearchIds)) {
            $this->logger->warning($options->type . ' - no search term defined');
            return null;
        }

        foreach (explode(',', $facebookSearchIds) as $searchId) {
            $searchId = trim($searchId);
            $feeds = $this->itemRepository->findByTypeAndCacheIdentifier($options->type, $searchId);

            // /links? /statuses? /tagged?
            $url = '/' . $searchId . '/posts?filter=app_2392950137&limit=' . $options->feedRequestLimit;

            if ($feeds && $feeds->count() > 0) {
                $feed = $feeds->getFirst();

                if ($options->devMod || ($feed->getDate()->getTimestamp() + $options->refreshTimeInMin * 60) < time()) {
                    try {
                        $feed->setDate(new \DateTime('now'));
                        $feed->setResult(json_encode($this->api->api($url)));
                        $this->itemRepository->update($feed);
                    } catch (\FacebookApiException $e) {
                        $this->logger->warning($options->type . ' feeds can\'t be updated', array('data' => $e->getMessage())); //TODO => handle FacebookApiException
                    }
                }
                $result[] = $feed;
                continue;
            }

            try {
                $feed = new Item($options->type);
                $feed->setCacheIdentifier($searchId);
                $feed->setResult(json_encode($this->api->api($url)));

                // save to DB and return current feed
                $this->itemRepository->saveFeed($feed);
                $result[] = $feed;

            } catch (\FacebookApiException $e) {
                $this->logger->warning('initial load for ' . $options->type . ' feeds failed', array('data' => $e->getMessage())); //TODO => handle FacebookApiException
            }
        }

        return $this->getFeedItemsFromApiRequest($result, $options);
    }

    function getFeedItemsFromApiRequest($result, $options){

        $rawFeeds = array();
        $feedItems = array();

        //this can probably go in SocialMediaAdapter
        if (!empty($result)) {
            foreach ($result as $fb_feed) {
                $rawFeeds[self::TYPE . '_' . $fb_feed->getCacheIdentifier() . '_raw'] = $fb_feed->getResult();
                foreach ($fb_feed->getResult()->data as $rawFeed) {
                    if ($options->onlyWithPicture && empty($rawFeed->picture)) {
                        continue;
                    }
                    $feed = new Feed(self::TYPE , $rawFeed);
                    $feed->setId($rawFeed->id);
                    $feed->setText($this->trim_text($rawFeed->message, $options->textTrimLength, true));
                    $feed->setImage(urldecode($rawFeed->picture));
                    $feed->setLink($rawFeed->link);
                    $d = new \DateTime($rawFeed->created_time);
                    $feed->setTimeStampTicks($d->getTimestamp());

                    $feedItems[] = $feed;
                }
            }
        }

        return array('rawFeeds' => $rawFeeds, 'feedItems' => $feedItems);

    }
}