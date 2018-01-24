<?php

namespace PlusB\PbSocial\Adapter;

use PlusB\PbSocial\Domain\Model\Credential;
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

class InstagramAdapter extends SocialMediaAdapter
{

    const TYPE = 'instagram';

    private $api;

    /**
     * credentialRepository
     *
     * @var \PlusB\PbSocial\Domain\Repository\CredentialRepository
     * @inject
     */
    protected $credentialRepository;

    public function __construct($apiKey, $apiSecret, $apiCallback, $code, $itemRepository, $credentialRepository)
    {
        parent::__construct($itemRepository);

        $this->api =  new \Instagram(array('apiKey' => $apiKey, 'apiSecret' => $apiSecret, 'apiCallback' => $apiCallback));

        $this->credentialRepository = $credentialRepository;

        // get access token from database
        $this->getAccessToken($code);
    }

    public function getResultFromApi($options)
    {
        $result = array();

        // If search ID is given and hashtag is given and filter is checked, only show posts with given hashtag
        $filterByHastags = $options->instagramPostFilter && $options->instagramSearchIds && $options->instagramHashTags;

        if (!$filterByHastags) {
            foreach (explode(',', $options->instagramSearchIds) as $searchId) {
                $searchId = trim($searchId);
                if ($searchId != ""){
                    $feeds = $this->itemRepository->findByTypeAndCacheIdentifier(self::TYPE, $searchId);
                    if ($feeds && $feeds->count() > 0) {
                        $feed = $feeds->getFirst();
                        if ($options->devMod || ($feed->getDate()->getTimestamp() + $options->refreshTimeInMin * 60) < time()) {
                            try {
                                $userPosts = $this->api->getUserMedia($searchId, $options->feedRequestLimit);
                                if ($userPosts->meta->code >= 400) {
                                    $this->logWarning('error: ' . json_encode($userPosts->meta));
                                    continue;
                                }
                                $feed->setDate(new \DateTime('now'));
                                $feed->setResult(json_encode($userPosts));
                                $this->itemRepository->updateFeed($feed);
                            } catch (\Exception $e) {
                                $this->logError("feeds can't be updated - " . $e->getMessage());
                                continue;
                            }
                        }
                        $result[] = $feed;
                        continue;
                    }

                    try {
                        $userPosts = $this->api->getUserMedia($searchId, $options->feedRequestLimit);
                        if ($userPosts->meta->code >= 400) {
                            $this->logWarning('error: ' . json_encode($userPosts->meta));
                        }
                        $feed = new Item(self::TYPE);
                        $feed->setCacheIdentifier($searchId);
                        $feed->setResult(json_encode($userPosts));

                        // save to DB and return current feed
                        $this->itemRepository->saveFeed($feed);
                        $result[] = $feed;
                    } catch (\Exception $e) {
                        $this->logError('initial load for feed failed - ' . $e->getMessage());
                    }
                }
            }
        }

        foreach (explode(',', $options->instagramHashTags) as $searchId) {
            $searchId = trim($searchId);
            $searchId = ltrim($searchId, '#'); //strip hastags
            if ($searchId != "") {
                $feeds = $this->itemRepository->findByTypeAndCacheIdentifier(self::TYPE, $searchId);

                if ($feeds && $feeds->count() > 0) {
                    $feed = $feeds->getFirst();
                    if ($options->devMod || ($feed->getDate()->getTimestamp() + $options->refreshTimeInMin * 60) < time()) {
                        try {
                            $tagPosts = $this->api->getTagMedia($searchId, $options->feedRequestLimit);
                            if ($tagPosts->meta->code >= 400) {
                                $this->logWarning('error: ' . json_encode($tagPosts->meta));
                            }
                            $feed->setDate(new \DateTime('now'));
                            $feed->setResult(json_encode($tagPosts));
                            $this->itemRepository->updateFeed($feed);
                        } catch (\Exception $e) {
                            $this->logError("feeds can't be updated - " . $e->getMessage());
                        }
                    }
                    $result[] = $feed;
                    continue;
                }

                try {
                    $tagPosts = $this->api->getTagMedia($searchId, $options->feedRequestLimit);
                    if ($tagPosts->meta->code >= 400) {
                        $this->logWarning('error: ' . json_encode($tagPosts->meta));
                    }
                    $feed = new Item(self::TYPE);
                    $feed->setCacheIdentifier($searchId);
                    $feed->setResult(json_encode($tagPosts));
                    // save to DB and return current feed
                    $this->itemRepository->saveFeed($feed);
                    $result[] = $feed;
                } catch (\Exception $e) {
                    $this->logError('initial load for feed failed - ' . $e->getMessage());
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

    private function getAccessToken($code)
    {
        $apiKey = $this->api->getApiKey();

        # get access token from database #
        $credentials = $this->credentialRepository->findByTypeAndAppId(self::TYPE, $apiKey);

        if ($credentials->count() > 1) {
            foreach ($credentials as $c) {
                if ($c->getAccessToken != '') {
                    $credential = $c;
                } else {
                    $this->credentialRepository->remove($c);
                }
            }
        } else {
            $credential = $credentials->getFirst();
        }

        if (!isset($credential) || !$credential->isValid()) {
            # validate code to get access token #
            $access_token = $this->api->getOAuthToken($code, true);
            if ($access_token) {
                if (isset($credential)) {
                    $credential->setAccessToken($access_token);
                    $this->credentialRepository->update($credential);
                } else {
                    # create new credential #
                    $credential = new Credential(self::TYPE, $apiKey);
                    $credential->setAccessToken($access_token);
                    $this->credentialRepository->saveCredential($credential);
                }
            } else {
                $this->logError('access code expired. Please provide new code in pb_social extension configuration.');
                return null;
            }
        }

        $this->api->setAccessToken($credential->getAccessToken());

        // test request
        $testRequest = $this->api->getUserMedia('self');
        if ($testRequest->meta->code == 400) {
            $this->logError('access code expired. Please provide new code in pb_social extension configuration.');
        }

        return $credential->getAccessToken();
    }
}
