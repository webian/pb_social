<?php

namespace PlusB\PbSocial\Adapter;

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

class YoutubeAdapter extends SocialMediaAdapter
{

    const TYPE = 'youtube';

    const YT_LINK = 'https://www.youtube.com/watch?v=';

    const YT_SEARCH = 'https://www.googleapis.com/youtube/v3/search?q=';

    // get items from playlist api call
    const YT_SEARCH_PLAYLIST = 'https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&playlistId=';

    // get items from channel api call
    const YT_SEARCH_CHANNEL = 'https://www.googleapis.com/youtube/v3/search?channelId=';

    public $isValid = false, $validationMessage = "";
    private $appKey, $options;

    /**
     * @param mixed $appKey
     */
    public function setAppKey($appKey)
    {
        $this->appKey = $appKey;
    }

    /**
     * @param mixed $options
     */
    public function setOptions($options)
    {
        $this->options = $options;
    }



    public function __construct(
        $appKey,
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
                    'appKey' => $appKey,
                    'options' => $options
                )) === false)
        {
            throw new \Exception( self::TYPE . ' ' . $this->validationMessage, 1558521003);
        }
        /* validated */
    }

    /**
     * validates constructor input parameters in an individual way just for the adapter
     *
     * @param $parameter
     * @return bool
     */
    public function validateAdapterSettings($parameter)
    {
        $this->setAppKey($parameter['appKey']);
        $this->setOptions($parameter['options']);

        if (empty($this->appKey)) {
            $this->validationMessage = self::TYPE . ' credentials not set';
        } elseif (empty($this->options->youtubeSearch)  && empty($this->options->youtubePlaylist) && empty($this->options->youtubeChannel) ) {
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

        $fields = array(
            'key' => $this->appKey,
            'maxResults' => $options->feedRequestLimit,
            'part' => 'snippet'
        );

        if ($options->youtubeType != '') {
            $fields['type'] = $options->youtubeType;
        }
        if ($options->youtubeLanguage != '') {
            $fields['relevanceLanguage'] = $options->youtubeLanguage;
        }
        if ($options->youtubeOrder != 'relevance') {
            $fields['order'] = $options->youtubeOrder;
        }

        $searchTerms = explode(',', $options->youtubeSearch);
        if ($options->youtubePlaylist) {
            $searchTerms = explode(',', $options->youtubePlaylist);
        }
        if ($options->youtubeChannel) {
            $searchTerms = explode(',', $options->youtubeChannel);
        }

        foreach ($searchTerms as $searchString) {
            $searchString = trim(urlencode($searchString));
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
                        throw new \Exception("feeds can't be updated. " . $e->getMessage(), 1558435668);
                    }
                }
                continue;
            }

            try {
                $feed = new Item(self::TYPE);
                $feed->setCacheIdentifier($this->cacheIdentifier);
                $feed->setResult($this->getPosts($searchString, $fields, $options));
                // save to DB and return current feed
                $this->itemRepository->saveFeed($feed);
                $result[] = $feed;
            } catch (\Exception $e) {
                throw new \Exception('initial load for feed failed' . $e->getMessage(), 1558435673);
            }
        }

        return $this->getFeedItemsFromApiRequest($result, $options);
    }

    public function getFeedItemsFromApiRequest($result, $options)
    {
        $rawFeeds = array();
        $feedItems = array();

        if (!empty($result)) {
            foreach ($result as $yt_feed) {
                $rawFeeds[self::TYPE . '_' . $yt_feed->getCacheIdentifier() . '_raw'] = $yt_feed->getResult();
                foreach ($yt_feed->getResult()->items as $rawFeed) {
                    $feed = new Feed(self::TYPE, $rawFeed);
                    if ($options->youtubePlaylist) {
                        $id = $rawFeed->snippet->resourceId->videoId;
                    } else {
                        $id = $rawFeed->id->videoId;
                    }
                    $feed->setId($id);
                    $feed->setText($this->trim_text($rawFeed->snippet->title, $options->textTrimLength, true));
                    $feed->setImage($rawFeed->snippet->thumbnails->standard->url);
                    $feed->setLink(self::YT_LINK . $id);
                    $d = new \DateTime($rawFeed->snippet->publishedAt);
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
    public function getPosts($searchString, $fields, $options)
    {
        $headers = array('Content-Type: application/json');

        // use different api call for channel
        if ($options->youtubeChannel) {
            $url = self::YT_SEARCH_CHANNEL . $searchString . '&' . http_build_query($fields);
        } // use different api call for playlist
        else if ($options->youtubePlaylist) {
            $url = self::YT_SEARCH_PLAYLIST . $searchString . '&' . http_build_query($fields);
        } else {
            $url = self::YT_SEARCH . $searchString . '&' . http_build_query($fields);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $curl_response = curl_exec($ch);

        if (property_exists(json_decode($curl_response), 'error')) {
            throw new \Exception($curl_response, 1558521095);
        }

        return $curl_response;
    }
}
