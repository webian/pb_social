<?php

namespace PlusB\PbSocial\Adapter;

$extensionPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('pb_social') . 'Resources/Private/Libs/';
require $extensionPath . 'twitteroauth/autoload.php';
use Abraham\TwitterOAuth\TwitterOAuth;
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

class TwitterAdapter extends SocialMediaAdapter
{

    const TYPE = 'twitter';

    private $api;

    private $api_url = 'search/tweets';

    public function __construct($consumerKey, $consumerSecret, $accessToken, $accessTokenSecret, $itemRepository)
    {
        parent::__construct($itemRepository);

        $this->api =  new TwitterOAuth($consumerKey, $consumerSecret, $accessToken, $accessTokenSecret);
    }

    public function getResultFromApi($options)
    {
        $result = array();

        // because of the amount of data twitter is sending, the database can only carry 20 tweets.
        // 20 Tweets = ~86000 Character
        $apiParameters = array();
//        if ($options->feedRequestLimit > 20) {
//            $options->feedRequestLimit = 20;
//        }
        if ($options->twitterLanguage) {
            $apiParameters['lang'] = $options->twitterLanguage;
        }
        if ($options->twitterGeoCode) {
            $apiParameters['geocode'] = $options->twitterGeoCode;
        }

        if ($options->twitterSearchFieldValues) {
            foreach (explode(',', $options->twitterSearchFieldValues) as $searchValue) {
                $searchValue = trim($searchValue);
                $feeds = $this->itemRepository->findByTypeAndCacheIdentifier(self::TYPE, $searchValue);

                $tweets = $this->getPosts($apiParameters, $options, $searchValue);

                if ($feeds && $feeds->count() > 0) {
                    $feed = $feeds->getFirst();
                    if ($options->devMod || ($feed->getDate()->getTimestamp() + $options->refreshTimeInMin * 60) < time()) {
                        try {
                            $feed->setDate(new \DateTime('now'));
                            $feed->setResult($tweets);
                            $this->itemRepository->updateFeed($feed);
                        } catch (\Exception $e) {
                            $this->logger->error(self::TYPE . ' feeds can\'t be updated', array('data' => $e->getMessage()));
                        }
                    }
                    $result[] = $feed;
                    continue;
                }

                try {
                    $feed = new Item(self::TYPE);
                    $feed->setCacheIdentifier($searchValue);
                    $feed->setResult($tweets);

                    // save to DB and return current feed
                    $this->itemRepository->saveFeed($feed);
                    $result[] = $feed;
                } catch (\Exception $e) {
                    $this->logger->error('initial load for ' . self::TYPE . ' feeds failed', array('data' => $e->getMessage()));
                }
            }
        }

        if ($options->twitterProfilePosts) {
            foreach (explode(',', $options->twitterProfilePosts) as $searchValue) {
                $searchValue = trim($searchValue);
                $feeds = $this->itemRepository->findByTypeAndCacheIdentifier(self::TYPE, $searchValue);

                //https://dev.twitter.com/rest/reference/get/search/tweets
                //include_entities=false => The entities node will be disincluded when set to false.

                $tweets = $this->getPosts($apiParameters, $options, $searchValue);

                if ($feeds && $feeds->count() > 0) {
                    $feed = $feeds->getFirst();
                    if ($options->devMod || ($feed->getDate()->getTimestamp() + $options->refreshTimeInMin * 60) < time()) {
                        try {
                            $feed->setDate(new \DateTime('now'));
                            $feed->setResult($tweets);
                            $this->itemRepository->updateFeed($feed);
                        } catch (\Exception $e) {
                            $this->logger->error(self::TYPE . ' feeds can\'t be updated', array('data' => $e->getMessage()));
                        }
                    }
                    $result[] = $feed;
                    continue;
                }

                try {
                    $feed = new Item(self::TYPE);
                    $feed->setCacheIdentifier($searchValue);
                    $feed->setResult($tweets);

                    // save to DB and return current feed
                    $this->itemRepository->saveFeed($feed);
                    $result[] = $feed;
                } catch (\Exception $e) {
                    $this->logger->error('initial load for ' . self::TYPE . ' feeds failed', array('data' => $e->getMessage()));
                }
            }
        }

        return $this->getFeedItemsFromApiRequest($result, $options);
    }

    public function getFeedItemsFromApiRequest($result, $options)
    {
        $rawFeeds = array();
        $feedItems = array();

        $placeholder = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath('pb_social') . 'Resources/Public/Icons/Placeholder/twitter.jpg';

        if (!empty($result)) {
            foreach ($result as $twt_feed) {
                if (empty($twt_feed->getResult()->statuses)) {
                    error_log('empty status @TwitterAdapter');
                    break;
                }
                $rawFeeds[self::TYPE . '_' . $twt_feed->getCacheIdentifier() . '_raw'] = $twt_feed->getResult();
                foreach ($twt_feed->getResult()->statuses as $rawFeed) {
                    if ($options->twitterShowOnlyImages && null == $rawFeed->entities->media) {
                        continue;
                    }
                    $feed = new Feed($twt_feed->getType(), $rawFeed);
                    $feed->setId($rawFeed->id);
                    $feed->setText($this->trim_text($rawFeed->text, $options->textTrimLength, true));
//                    $feed->setImage($placeholder);
                    if ($rawFeed->entities->media[0]->type == 'photo') {
                        $feed->setImage($rawFeed->entities->media[0]->media_url);
                    }
                    if ($rawFeed->entities->media[0]->url) {
                        $feed->setLink($rawFeed->entities->media[0]->url);
                    } elseif ($rawFeed->entities->urls[0]->expanded_url) {
                        $feed->setLink($rawFeed->entities->urls[0]->expanded_url);
                    } else {
                        $feed->setLink('https://twitter.com/' . $rawFeed->user->screen_name . '/status/' . $rawFeed->id_str);
                    }
                    $dateTime = new \DateTime($rawFeed->created_at);
                    $feed->setTimeStampTicks($dateTime->getTimestamp());
                    $feedItems[] = $feed;
                }
            }
        }

        return array('rawFeeds' => $rawFeeds, 'feedItems' => $feedItems);
    }

    public function getPosts($apiParameters, $options, $searchValue)
    {
        $requestParameters = $apiParameters;
        $include_entities = false;

        if ($options->twitterHideRetweets) {
            $searchValue = $searchValue . ' -filter:retweets';
            $include_entities = true;
        }
        if ($options->twitterShowOnlyImages) {
            $searchValue = $searchValue . ' filter:images';
            $include_entities = true;
        }
        if ($include_entities) {
            $requestParameters['include_entities'] = 'true';
        }

        $requestParameters['q'] = $searchValue;
        $requestParameters['count'] = $options->feedRequestLimit;
        $tweets = json_encode($this->api->get($this->api_url, $requestParameters));

        return $tweets;
    }
}
