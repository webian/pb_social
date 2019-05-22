<?php

namespace PlusB\PbSocial\Adapter;

$extensionPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('pb_social') . 'Resources/Private/Libs/';
require $extensionPath . 'instagram/src/Instagram.php';


use MetzWeb\Instagram\Instagram;
use PlusB\PbSocial\Domain\Model\Credential;
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

class InstagramAdapter extends SocialMediaAdapter
{

    const TYPE = 'instagram';

    public $isValid = false, $validationMessage = "";
    private $apiKey, $apiSecret, $apiCallback, $code, $token, $options;

    /**
     * @param mixed $apiKey
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * @param mixed $apiSecret
     */
    public function setApiSecret($apiSecret)
    {
        $this->apiSecret = $apiSecret;
    }

    /**
     * @param mixed $apiCallback
     */
    public function setApiCallback($apiCallback)
    {
        $this->apiCallback = $apiCallback;
    }

    /**
     * @param mixed $code
     */
    public function setCode($code)
    {
        $this->code = $code;
    }

    /**
     * @param mixed $token
     */
    public function setToken($token)
    {
        $this->token = $token;
    }

    /**
     * @param mixed $options
     */
    public function setOptions($options)
    {
        $this->options = $options;
    }
    private $api;

    /**
     * InstagramAdapter constructor.
     * @param $apiKey
     * @param $apiSecret
     * @param $apiCallback
     * @param $code
     * @param $token
     * @param $itemRepository
     * @param $options
     * @param $ttContentUid
     * @param $ttContentPid
     * @param $cacheIdentifier
     * @throws \MetzWeb\Instagram\InstagramException
     */
    public function __construct(
        $apiKey,
        $apiSecret,
        $apiCallback,
        $code,
        $token,
        $itemRepository,
        $options,
        $ttContentUid,
        $ttContentPid,
        $cacheIdentifier)
    {
        parent::__construct($itemRepository, $cacheIdentifier, $ttContentUid, $ttContentPid);

        /* validation - interrupt instanciating if invalid */
        if($this->validateAdapterSettings(
                array(
                    'apiKey' => $apiKey,
                    'apiSecret' => $apiSecret,
                    'apiCallback' => $apiCallback,
                    'code' => $code,
                    'token' => $token,
                    'options' => $options
                )) === false)
        {
            throw new \Exception(self::TYPE . ' ' . $this->validationMessage,  1558515732);
        }
        /* validated */

        $this->api =  new Instagram(array('apiKey' => $this->apiKey, 'apiSecret' => $this->apiSecret, 'apiCallback' => $this->apiCallback));
        $this->api->setAccessToken($this->token);
    }

    /**
     * validates constructor input parameters in an individual way just for the adapter
     *
     * @param $parameter
     * @return bool
     */
    public function validateAdapterSettings($parameter)
    {
        $this->setApiKey($parameter['apiKey']);
        $this->setApiSecret($parameter['apiSecret']);
        $this->setApiCallback($parameter['apiCallback']);
        $this->setCode($parameter['code']);
        $this->setToken($parameter['token']);
        $this->setOptions($parameter['options']);

        if (empty($this->apiKey) || empty($this->apiSecret) ||  empty($this->apiCallback)||  empty($this->code)||  empty($this->token)) {
            $this->validationMessage = 'credentials not set: '
                . (empty($this->apiKey)?'apiKey ':'')
                . (empty($this->apiSecret)?'apiSecret ':'')
                . (empty($this->apiCallback)?'apiCallback ':'')
                . (empty($this->code)?'code ':'')
                . (empty($this->token)?'token ':'')
            ;
        } elseif (empty($this->options->instagramSearchIds) && empty($this->options->instagramHashTags)) {
            $this->validationMessage = 'no search term defined: '
                . (empty($this->instagramSearchIds)?'instagramSearchIds ':'')
                . (empty($this->instagramHashTags)?'instagramHashTags ':'')
            ;
        } else {
            $this->isValid = true;
        }

        return $this->isValid;
    }

    public function getResultFromApi()
    {
        $options = $this->options;
        $result = array();

        // If search ID is given and hashtag is given and filter is checked, only show posts with given hashtag
        $filterByHastags = $options->instagramPostFilter && $options->instagramSearchIds && $options->instagramHashTags;

        if (!$filterByHastags) {
            foreach (explode(',', $options->instagramSearchIds) as $searchId) {
                $searchId = trim($searchId);
                if ($searchId != ""){
                    $feeds = $this->itemRepository->findByTypeAndCacheIdentifier(self::TYPE, $this->composeCacheIdentifierForListItem($this->cacheIdentifier , $searchId));
                    if ($feeds && $feeds->count() > 0) {
                        $feed = $feeds->getFirst();
                        /**
                         * todo: (AM) "$options->refreshTimeInMin * 60) < time()" locks it to a certain cache lifetime - users want to bee free...
                         */
                        if ($options->devMod || ($feed->getDate()->getTimestamp() + $options->refreshTimeInMin * 60) < time()) {
                            try {
                                $userPosts = $this->api->getUserMedia($searchId, $options->feedRequestLimit);
                                if ($userPosts->meta->code >= 400) {
                                    $this->logAdapterWarning('warning: ' . json_encode($userPosts->meta),1558435723);
                                    continue;
                                }
                                $feed->setDate(new \DateTime('now'));
                                $feed->setResult(json_encode($userPosts));
                                $this->itemRepository->updateFeed($feed);
                            } catch (\Exception $e) {
                                throw new \Exception("feeds can't be updated. " . $e->getMessage(), 1558515755);
                                continue;
                            }
                        }
                        $result[] = $feed;
                        continue;
                    }

                    try {
                        $userPosts = $this->api->getUserMedia($searchId, $options->feedRequestLimit);
                        if ($userPosts->meta->code >= 400) {
                            $this->logAdapterWarning('warning: ' . json_encode($userPosts->meta),1558435728);
                        }
                        $feed = new Item(self::TYPE);
                        $feed->setCacheIdentifier($this->composeCacheIdentifierForListItem($this->cacheIdentifier , $searchId));
                        $feed->setResult(json_encode($userPosts));

                        // save to DB and return current feed
                        $this->itemRepository->saveFeed($feed);
                        $result[] = $feed;
                    } catch (\Exception $e) {
                        throw new \Exception('initial load for feed failed' . $e->getMessage(), 1558515800);
                    }
                }
            }
        }

        foreach (explode(',', $options->instagramHashTags) as $searchId) {
            $searchId = trim($searchId);
            $searchId = ltrim($searchId, '#'); //strip hastags
            if ($searchId != "") {
                $feeds = $this->itemRepository->findByTypeAndCacheIdentifier(self::TYPE, $this->composeCacheIdentifierForListItem($this->cacheIdentifier , $searchId));

                if ($feeds && $feeds->count() > 0) {
                    $feed = $feeds->getFirst();
                    if ($options->devMod || ($feed->getDate()->getTimestamp() + $options->refreshTimeInMin * 60) < time()) {
                        try {
                            $tagPosts = $this->api->getTagMedia($searchId, $options->feedRequestLimit);
                            if ($tagPosts->meta->code >= 400) {
                                $this->logAdapterWarning('warning: ' . json_encode($tagPosts->meta),1558435751);
                            }
                            $feed->setDate(new \DateTime('now'));
                            $feed->setResult(json_encode($tagPosts));
                            $this->itemRepository->updateFeed($feed);
                        } catch (\Exception $e) {
                            throw new \Exception("feeds can't be updated. " . $e->getMessage(), 1558515771);
                        }
                    }
                    $result[] = $feed;
                    continue;
                }

                try {
                    $tagPosts = $this->api->getTagMedia($searchId, $options->feedRequestLimit);
                    if ($tagPosts->meta->code >= 400) {
                        $this->logAdapterWarning('warning: ' . json_encode($tagPosts->meta),1558435756);
                    }
                    $feed = new Item(self::TYPE);
                    $feed->setCacheIdentifier($this->composeCacheIdentifierForListItem($this->cacheIdentifier , $searchId));
                    $feed->setResult(json_encode($tagPosts));
                    // save to DB and return current feed
                    $this->itemRepository->saveFeed($feed);
                    $result[] = $feed;
                } catch (\Exception $e) {
                    throw new \Exception('initial load for feed failed' . $e->getMessage(), 1558515784);
                }
            }
        }

        return $this->getFeedItemsFromApiRequest($result, $options);
    }

    public function getFeedItemsFromApiRequest($result, $options)
    {
        $rawFeeds = array();
        $feedItems = array();

        if (!empty($result)) {
            foreach ($result as $ig_feed) {
                $rawFeeds[self::TYPE . '_' . $ig_feed->getCacheIdentifier() . '_raw'] = $ig_feed->getResult();
                if (is_array($ig_feed->getResult()->data)) {
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
