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

class PinterestAdapter extends SocialMediaAdapter {

    const TYPE = 'pinterest';

    private $username;
    private $boardname;

    public function __construct($username, $boardname, $itemRepository){

        parent::__construct($itemRepository);

        $this->username =  $username;
        $this->boardname = $boardname;

    }

    public function getResultFromApi($options){

        $result = array();

        foreach (explode(',', $this->username) as $searchId) {
            $searchId = trim($searchId);
            $feeds = $this->itemRepository->findByTypeAndCacheIdentifier(self::TYPE, $searchId);

            if ($feeds && $feeds->count() > 0) {
                $feed = $feeds->getFirst();

                if ($options->devMod || ($feed->getDate()->getTimestamp() + $options->refreshTimeInMin * 60) < time()) {
                    try {
                        $feed->setDate(new \DateTime('now'));
                        $feed->setResult($this->itemRepository->curl_download('http://api.pinterest.com/v3/pidgets/boards/$this->username/$this->boardname/pins/'));
                        $this->itemRepository->update($feed);
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
                $feed->setResult($this->itemRepository->curl_download('http://api.pinterest.com/v3/pidgets/boards/$this->username/$this->boardname/pins/'));

                // save to DB and return current feed
                $this->itemRepository->saveFeed($feed);
                $result[] = $feed;

            } catch (\FacebookApiException $e) {
                $this->logger->warning('initial load for ' . self::TYPE . ' feeds failed', array('data' => $e->getMessage())); //TODO => handle FacebookApiException
            }
        }

        return $this->getFeedItemsFromApiRequest($result, $options);

    }

    function getFeedItemsFromApiRequest($result, $options)
    {

        $rawFeeds = array();
        $feedItems = array();

        if (!empty($result)) {
            foreach ($result as $pin_feed) {
                $rawFeeds[self::TYPE . '_' . $pin_feed->getCacheIdentifier() . '_raw'] = $pin_feed->getResult();
                foreach ($pin_feed->getResult()->data as $rawFeed) {
                    $i = 0;
                    foreach ($rawFeed as $pin) {
                        if ($pin->images && ($i < $options->feedRequestLimit)) {
                            $i++;
                            $feed = new Feed(self::TYPE, $pin);
                            $feed->setText($this->trim_text($pin->description, $options->textTrimLength, true));
                            $image = (array)$pin->images;
                            $feed->setImage($image['237x']->url);
                            $link = $pin->link ? $pin->link : $pin->pinner->profile_url . $this->boardname;
                            $feed->setLink($link);
                            $feed->setTimeStampTicks($rawFeed->created_time);
                            $feedItems[] = $feed;
                        }
                    }
                }
            }
        }

        return array('rawFeeds' => $rawFeeds, 'feedItems' => $feedItems);
    }
}