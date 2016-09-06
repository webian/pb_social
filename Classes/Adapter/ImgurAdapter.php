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

class ImgurAdapter extends SocialMediaAdapter
{

    const TYPE = 'imgur';

    private $api;

    public function __construct($apiId, $apiSecret, $itemRepository)
    {
        parent::__construct($itemRepository);

        $this->api =  new \Imgur($apiId, $apiSecret);

        //TODO: Implement OAuth authentication (to get a user's images etc)
    }

    public function getResultFromApi($options)
    {
        $result = array();

        // search for users
        foreach (explode(',', $options->imgSearchUsers) as $searchId) {
            $searchId = trim($searchId);
            $feeds = $this->itemRepository->findByTypeAndCacheIdentifier(self::TYPE, $searchId);
            if ($feeds && $feeds->count() > 0) {
                $feed = $feeds->getFirst();

                if ($options->devMod || ($feed->getDate()->getTimestamp() + $options->refreshTimeInMin * 60) < time()) {
                    try {
                        $posts = json_encode($this->api->account($searchId)->images());
                        $feed->setDate(new \DateTime('now'));
                        $feed->setResult($posts);
                        $this->itemRepository->updateFeed($feed);
                    } catch (\Exception $e) {
                        $this->logger->warning(self::TYPE . ' feeds can\'t be updated', array('data' => $e->getMessage())); //TODO => handle FacebookApiException
                    }
                }
                $result[] = $feed;
                continue;
            }

            try {
                $posts = json_encode($this->api->account($searchId)->images($page = 0));
                $feed = new Item(self::TYPE);
                $feed->setCacheIdentifier($searchId);
                $feed->setResult($posts);

                // save to DB and return current feed
                $this->itemRepository->saveFeed($feed);
                $result[] = $feed;
            } catch (\Exception $e) {
                $this->logger->warning('initial load for ' . self::TYPE . ' feeds failed', array('data' => $e->getMessage())); //TODO => handle FacebookApiException
            }
        }

        // search for tags
        foreach (explode(',', $options->imgSearchTags) as $searchId) {
            $searchId = trim($searchId);
            $feeds = $this->itemRepository->findByTypeAndCacheIdentifier(self::TYPE, $searchId);
            if ($feeds && $feeds->count() > 0) {
                $feed = $feeds->getFirst();

                if ($options->devMod || ($feed->getDate()->getTimestamp() + $options->refreshTimeInMin * 60) < time()) {
                    try {
                        $posts = json_encode($this->api->gallery()->search($searchId));
                        $feed->setDate(new \DateTime('now'));
                        $feed->setResult($posts);
                        $this->itemRepository->updateFeed($feed);
                    } catch (\Exception $e) {
                        $this->logger->warning(self::TYPE . ' feeds can\'t be updated', array('data' => $e->getMessage())); //TODO => handle FacebookApiException
                    }
                }
                $result[] = $feed;
                continue;
            }

            try {
                $posts = json_encode($this->api->gallery()->search($searchId));
                $feed = new Item(self::TYPE);
                $feed->setCacheIdentifier($searchId);
                $feed->setResult($posts);

                // save to DB and return current feed
                $this->itemRepository->saveFeed($feed);
                $result[] = $feed;
            } catch (\Exception $e) {
                $this->logger->warning('initial load for ' . self::TYPE . ' feeds failed', array('data' => $e->getMessage())); //TODO => handle FacebookApiException
            }
        }

        return $this->getFeedItemsFromApiRequest($result, $options);
    }

    public function getFeedItemsFromApiRequest($result, $options)
    {
        $rawFeeds = array();
        $feedItems = array();

        $endingArray = array('.gif', '.jpg', '.png');
        if (!empty($result)) {
            foreach ($result as $im_feed) {
                $rawFeeds[self::TYPE . '_' . $im_feed->getCacheIdentifier() . '_raw'] = $im_feed->getResult();
                $i = 0;
                foreach ($im_feed->getResult()->data as $rawFeed) {
                    if (is_object($rawFeed) && ($i < $options->feedRequestLimit)) {
                        if ($this->check_end($rawFeed->link, $endingArray)) {
                            $i++;
                            $feed = new Feed(self::TYPE, $rawFeed);
                            $feed->setId($rawFeed->id);
                            $feed->setImage($rawFeed->link);
                            $feed->setText($this->trim_text($rawFeed->title, $options->textTrimLength, true));
                            $feed->setLink('http://imgur.com/gallery/' . $rawFeed->id);
                            $feed->setTimeStampTicks($rawFeed->datetime);
                            $feedItems[] = $feed;
                        }
                    }
                }
            }
        }

        return array('rawFeeds' => $rawFeeds, 'feedItems' => $feedItems);
    }
}
