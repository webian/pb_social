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

class InstagramAdapter extends SocialMediaAdapter {

    const TYPE = 'instagram';

    private $api;

    public function __construct($apiId, $itemRepository){

        parent::__construct($itemRepository);

        $this->api =  new \Instagram($apiId);

    }

    public function getResultFromApi($options){

        $result = array();

        foreach (explode(',', $options->instagramSearchIds) as $searchId) {
            $searchId = trim($searchId);
            $feeds = $this->itemRepository->findByTypeAndCacheIdentifier(self::TYPE, $searchId);
            if ($feeds && $feeds->count() > 0) {
                $feed = $feeds->getFirst();
                if ($options->devMod || ($feed->getDate()->getTimestamp() + $options->refreshTimeInMin * 60) < time()) {
                    try {
                        $feed->setDate(new \DateTime('now'));
                        $feed->setResult(json_encode($this->api->getUserMedia($searchId, $options->feedRequestLimit)));
                        $this->itemRepository->update($feed);
                    } catch (\Exception $e) {
                        $this->logger->error(self::TYPE . ' feeds cant be updated', array('data' => $e->getMessage()));
                    }
                }
                $result[] = $feed;
                continue;
            }

            try {
                $feed = new Item(self::TYPE);
                $feed->setCacheIdentifier($searchId);
                $feed->setResult(json_encode($this->api->getUserMedia($searchId, $options->feedRequestLimit)));

                // save to DB and return current feed
                $this->itemRepository->saveFeed($feed);
                $result[] = $feed;

            } catch (\Exception $e) {
                $this->logger->error('initial load for ' . self::TYPE . ' feeds failed', array('data' => $e->getMessage()));
            }
        }

        foreach (explode(',', $options->instagramHashTags) as $searchId) {
            $searchId = trim($searchId);
            $feeds = $this->itemRepository->findByTypeAndCacheIdentifier(self::TYPE, $searchId);

            if ($feeds && $feeds->count() > 0) {
                $feed = $feeds->getFirst();
                if ($options->devMod || ($feed->getDate()->getTimestamp() + $options->refreshTimeInMin * 60) < time()) {
                    try {
                        $feed->setDate(new \DateTime('now'));
                        $feed->setResult(json_encode($this->api->getTagMedia($searchId, $options->feedRequestLimit)));
                        $this->itemRepository->update($feed);
                    } catch (\Exception $e) {
                        $this->logger->error(self::TYPE . ' feeds cant be updated', array('data' => $e->getMessage()));
                    }
                }
                $result[] = $feed;
                continue;
            }

            try {
                $feed = new Item(self::TYPE);
                $feed->setCacheIdentifier($searchId);
                $feed->setResult(json_encode($this->api->getTagMedia($searchId, $options->feedRequestLimit)));

                // save to DB and return current feed
                $this->itemRepository->saveFeed($feed);
                $result[] = $feed;

            } catch (\Exception $e) {
                $this->logger->error('initial load for ' . self::TYPE . ' feeds failed', array('data' => $e->getMessage()));
            }
        }

        return $this->getFeedItemsFromApiRequest($result, $options);

    }

    function getFeedItemsFromApiRequest($result, $options) {

        $rawFeeds = array();
        $feedItems = array();

        if (!empty($result)) {
            foreach ($result as $ig_feed) {
                $rawFeeds[self::TYPE . '_' . $ig_feed->getCacheIdentifier() . '_raw'] = $ig_feed->getResult();
                if(is_array($ig_feed->getResult()->data)){
                    foreach ($ig_feed->getResult()->data as $rawFeed) {
                        if ($options->onlyWithPicture && empty($rawFeed->images->standard_resolution->url)) {
                            continue;
                        }
                        $feed = new Feed(self::TYPE, $rawFeed);
                        $feed->setId($rawFeed->id);
                        $feed->setText($this->trim_text($rawFeed->caption->text, $options->textTrimLength, true));
                        $feed->setImage($rawFeed->images->standard_resolution->url);
                        $feed->setLink($rawFeed->link);
                        $feed->setTimeStampTicks($rawFeed->created_time);
                        $feedItems[] = $feed;
                    }
                }
            }
        }

        return array('rawFeeds' => $rawFeeds, 'feedItems' => $feedItems);
    }
}