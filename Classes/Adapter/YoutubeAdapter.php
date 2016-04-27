<?php

namespace PlusB\PbSocial\Adapter;
use PlusB\PbSocial\Domain\Model\Feed;
use PlusB\PbSocial\Domain\Model\Item;
use TYPO3\CMS\Core\FormProtection\Exception;

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

class YoutubeAdapter extends SocialMediaAdapter {

    const TYPE = 'youtube';

    const YT_LINK = 'https://www.youtube.com/watch?v=';

    const YT_SEARCH = 'https://www.googleapis.com/youtube/v3/search?q=';

    private $appKey;

    public function __construct($appKey, $itemRepository){

        parent::__construct($itemRepository);

        $this->appKey = $appKey;

        //todo: use google client

    }

    public function getResultFromApi($options){

        $result = array();

        $fields = array(
            'key' => $this->appKey,
            'maxResults' => $options->feedRequestLimit,
            'part' => 'snippet'
        );

        if($options->youtubeType != '') $fields['type'] = $options->youtubeType;
        if($options->youtubeLanguage != '') $fields['relevanceLanguage'] = $options->youtubeLanguage;
        if($options->youtubeOrder != 'relevance') $fields['order'] = $options->youtubeOrder;

        foreach (explode(',', $options->youtubeSearch) as $searchString) {
            $searchString = trim($searchString);
            $feeds = $this->itemRepository->findByTypeAndCacheIdentifier(self::TYPE, $searchString);
            if ($feeds && $feeds->count() > 0) {
                $feed = $feeds->getFirst();
                if ($options->devMod || ($feed->getDate()->getTimestamp() + $options->refreshTimeInMin * 60) < time()) {
                    try {
                        $feed->setDate(new \DateTime('now'));
                        $feed->setResult($this->getPosts($searchString, $fields));
                        $this->itemRepository->update($feed);
                        $result[] = $feed;
                    } catch (\Exception $e) {
                        $this->logger->error(self::TYPE . ' feeds cant be updated', array('data' => $e->getMessage()));
                    }
                }
                continue;
            }

            try {
                $feed = new Item(self::TYPE);
                $feed->setCacheIdentifier($searchString);
                $feed->setResult($this->getPosts($searchString, $fields));
                // save to DB and return current feed
                $this->itemRepository->saveFeed($feed);
                $result[] = $feed;

            } catch (\Exception $e) {
                error_log('catched ' . $e->getMessage());
                $this->logger->warning('initial load for ' . self::TYPE . ' feeds failed. Please check the log file typo3temp/log/typo3.log for further information.');
            }
        }

        return $this->getFeedItemsFromApiRequest($result, $options);
    }

    function getFeedItemsFromApiRequest($result, $options) {

        $rawFeeds = array();
        $feedItems = array();

        if (!empty($result)) {
            foreach ($result as $yt_feed) {
                $rawFeeds[self::TYPE . '_' . $yt_feed->getCacheIdentifier() . '_raw'] = $yt_feed->getResult();
                error_log(json_encode($yt_feed->getResult()));
                foreach ($yt_feed->getResult()->items as $rawFeed) {
//                    if ($options->onlyWithPicture && empty($rawFeed->object->attachments[0]->image->url)) {
//                        continue;
//                    }
                    $feed = new Feed(self::TYPE, $rawFeed);
                    error_log(json_encode($rawFeed));
                    $feed->setId($rawFeed->id['videoId']);
                    $feed->setText($this->trim_text($rawFeed->snippet['title'], $options->textTrimLength, true));
                    $feed->setImage($rawFeed->snippet['default']->url);
                    $feed->setLink(self::YT_LINK . $rawFeed->id['videoId']);
                    $d = new \DateTime($rawFeed->snippet['publishedAt']);
                    $feed->setTimeStampTicks($d->getTimestamp());
                    $feedItems[] = $feed;
                }
            }
        }

        return array('rawFeeds' => $rawFeeds, 'feedItems' => $feedItems);
    }

    /**
     * @param $searchString
     * @param $fields
     * @return mixed
     * @throws \Exception
     */
    function getPosts($searchString, $fields){

        $headers = array('Content-Type: application/json');
        $url = self::YT_SEARCH . $searchString . '&' . http_build_query($fields);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $curl_response = json_decode(curl_exec($ch));

        // check if google error object is set => throw exception with json response
        if (property_exists($curl_response, 'error')) {
            $err = $curl_response->error->errors[0]->message;
            $this->logger->warning($err);
            throw new \Exception($err);
        }

        return $curl_response->items;
    }
}