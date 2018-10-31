<?php

namespace PlusB\PbSocial\Adapter;

use PlusB\PbSocial\Domain\Model\Feed;
use PlusB\PbSocial\Domain\Model\Item;
use Tumblr\API\Client;

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

class TumblrAdapter extends SocialMediaAdapter
{

    const TYPE = 'tumblr';


    public $isValid = false, $validationMessage = "";
    private $apiId, $apiSecret, $token, $tokenSecret, $options;

    /**
     * @param mixed $apiId
     */
    public function setApiId($apiId)
    {
        $this->apiId = $apiId;
    }

    /**
     * @param mixed $apiSecret
     */
    public function setApiSecret($apiSecret)
    {
        $this->apiSecret = $apiSecret;
    }

    /**
     * @param mixed $token
     */
    public function setToken($token)
    {
        $this->token = $token;
    }

    /**
     * @param mixed $tokenSecret
     */
    public function setTokenSecret($tokenSecret)
    {
        $this->tokenSecret = $tokenSecret;
    }

    /**
     * @param mixed $options
     */
    public function setOptions($options)
    {
        $this->options = $options;
    }

    private $api;

    public function __construct($apiId, $apiSecret, $token, $tokenSecret, $itemRepository, $options)
    {
        parent::__construct($itemRepository);

        /* validation - interrupt instanciating if invalid */
        if($this->validateAdapterSettings(
                array(
                    'apiId' => $apiId,
                    'apiSecret' => $apiSecret,
                    'token' => $token,
                    'tokenSecret' => $tokenSecret,
                    'options' => $options
                )) === false)
        {return $this;}
        /* validated */


        $this->api =  new Client($this->apiId, $this->apiSecret);
        $this->api->setToken($this->token, $this->tokenSecret);
    }

    /**
     * validates constructor input parameters in an individual way just for the adapter
     *
     * @param $parameter
     * @return bool
     */
    public function validateAdapterSettings($parameter)
    {
        $this->setApiId($parameter['apiId']);
        $this->setApiSecret($parameter['apiSecret']);
        $this->setToken($parameter['token']);
        $this->setTokenSecret($parameter['tokenSecret']);
        $this->setOptions($parameter['options']);

        if (empty($this->apiId) || empty($this->apiSecret) ||  empty($this->token)||  empty($this->tokenSecret)) {
            $this->validationMessage = self::TYPE . ' credentials not set';
        } elseif (empty($this->options->tumblrBlogNames) ) {
            $this->validationMessage = self::TYPE . ' - no blog names for search term defined';
        } else {
            $this->isValid = true;
        }

        return $this->isValid;
    }


    public function getResultFromApi()
    {
        $options = $this->options;
        $result = array();
        /**
         * todo: (AM) "$options->refreshTimeInMin * 60) < time()" locks it to a certain cache lifetime - users want to bee free, so... change!
         * todo: try to get rid of duplicate code
         */
        // search for users
        foreach (explode(',', $options->tumblrBlogNames) as $blogName) {
            $feeds = $this->itemRepository->findByTypeAndCacheIdentifier(self::TYPE, $blogName);
            if ($feeds && $feeds->count() > 0) {
                $feed = $feeds->getFirst();
                if ($options->devMod || ($feed->getDate()->getTimestamp() + $options->refreshTimeInMin * 60) < time()) {
                    try {
                        $posts = $this->getPosts($blogName, $options);
                        $feed->setDate(new \DateTime('now'));
                        $feed->setResult($posts);
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
                $feed->setCacheIdentifier($blogName);

                $posts = $this->getPosts($blogName, $options);
                $feed->setResult($posts);

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
            foreach ($result as $tblr_feed) {
                $rawFeeds[self::TYPE . '_' . $tblr_feed->getCacheIdentifier() . '_raw'] = $tblr_feed->getResult();
                foreach ($tblr_feed->getResult()->posts as $rawFeed) {
                    if ($options->onlyWithPicture && empty($rawFeed->photos[0]->original_size->url)) {
                        continue;
                    }
                    $feed = new Feed(self::TYPE, $rawFeed);
                    $feed->setId($rawFeed->id);
                    $text = '';
                    if ($rawFeed->caption) {
                        $text = $rawFeed->caption;
                    } elseif ($rawFeed->body) {
                        $text = $rawFeed->body;
                    } elseif ($rawFeed->description) {
                        $text = $rawFeed->description;
                    } elseif ($rawFeed->text) {
                        $text = $rawFeed->text;
                    } elseif ($rawFeed->summary) {
                        $text = $rawFeed->summary;
                    }
                    $feed->setText($this->trim_text(strip_tags($text), $options->textTrimLength, true));
                    if ($rawFeed->photos[0]->original_size->url) {
                        $feed->setImage($rawFeed->photos[0]->original_size->url);
                    } elseif ($rawFeed->thumbnail_url) {
                        $feed->setImage($rawFeed->thumbnail_url);
                    }
                    $feed->setLink($rawFeed->post_url);
                    $feed->setTimeStampTicks($rawFeed->timestamp);
                    $feedItems[] = $feed;
                }
            }
        }

        return array('rawFeeds' => $rawFeeds, 'feedItems' => $feedItems);
    }

    public function getPosts($blogName, $options)
    {
        $posts = '';

        if ($blogName == 'MYDASHBOARD') {
            if ($options->tumblrShowOnlyImages) {
                $posts = (json_encode($this->api->getDashboardPosts(array('limit' => $options->feedRequestLimit, 'type' => 'photo'))));
            } else {
                $posts = (json_encode($this->api->getDashboardPosts(array('limit' => $options->feedRequestLimit))));
            }
        } else {
            if ($options->tumblrHashtag !== '') {
                $options->tumblrHashtag = trim($options->tumblrHashtag);
                $options->tumblrHashtag = ltrim($options->tumblrHashtag, '#'); //strip hastags
                if ($options->tumblrShowOnlyImages) {
                    $posts = (json_encode($this->api->getBlogPosts($blogName, array('limit' => $options->feedRequestLimit, 'type' => 'photo', 'tag' => $options->tumblrHashtag, 'filter' => 'text'))));
                } else {
                    $posts = (json_encode($this->api->getBlogPosts($blogName, array('limit' => $options->feedRequestLimit, 'tag' => $options->tumblrHashtag, 'filter' => 'text'))));
                }
            } else {
                if ($options->tumblrShowOnlyImages) {
                    $posts = (json_encode($this->api->getBlogPosts($blogName, array('limit' => $options->feedRequestLimit, 'type' => 'photo', 'filter' => 'text'))));
                } else {
                    $posts = (json_encode($this->api->getBlogPosts($blogName, array('limit' => $options->feedRequestLimit, 'filter' => 'text'))));
                }
            }
        }

        return $posts;
    }
}
