<?php

namespace PlusB\PbSocial\Adapter;

$extensionPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('pb_social') . 'Resources/Private/Libs/';
#require_once $extensionPath . 'dummy/autoload.php'; # Include provider library if necessary
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

class DummyAdapter extends SocialMediaAdapter
{

    /**
     *  The TYPE property is used to identify the feed's provider.
     */
    const TYPE = 'dummy';

    /**
     *  If you're using a SDK you can assign the service to this variable.
     */
    private $api;

    /**
     * Use this credentialRepository to store OAuth access tokens
     *
     * @var \PlusB\PbSocial\Domain\Repository\CredentialRepository
     */
    private $credentialRepository;

    public function __construct($appId, $itemRepository, $credentialRepository)
    {
        parent::__construct($itemRepository);

        //TODO: Initialize your service or implement any other initializing logic here.

        // Optional test request
        try {
            // Simple request to test if the service is working
        } catch (\Exception $e) {
            $this->logger->warning(self::TYPE . ' exception - ' . $e->getMessage());
        }
    }

    public function getResultFromApi($options)
    {

        // Store Item objects in this array and pass it to $this->getFeedItemsFromApiRequest()
        $result = array();

        // Get posts for each search value. dummySearchValues should be a comma,seperated,list,of,search,values,or,ids
        foreach (explode(',', $options->dummySearchValues) as $searchValue) {
            $searchValue = trim($searchValue);
            $feeds = $this->itemRepository->findByTypeAndCacheIdentifier(self::TYPE, $searchValue);

            if ($feeds && $feeds->count() > 0) {
                $feed = $feeds->getFirst();

                if ($options->devMod || ($feed->getDate()->getTimestamp() + $options->refreshTimeInMin * 60) < time()) {
                    try {
                        // TODO => GET SOME DATA FROM PROVIDER AND UPDATE FEED-ITEM
                        $posts = $this->getPosts($searchValue);
                        $feed->setDate(new \DateTime('now'));
                        $feed->setResult($posts);
                        $this->itemRepository->updateFeed($feed);
                    } catch (\FacebookApiException $e) {
                        $this->logger->warning(self::TYPE . ' feeds can\'t be updated', array('data' => $e->getMessage())); //TODO => handle FacebookApiException
                    }
                }
                $result[] = $feed;
                continue;
            }

            try {
                // TODO => GET SOME DATA FROM YOUR PROVIDER AND INSERT THAT INTO NEW FEED-ITEM
                $posts = $this->getPosts($searchValue);
                $feed = new Item(self::TYPE);
                $feed->setCacheIdentifier($searchValue);
                $feed->setResult($posts);

                // save to DB and return current feed
                $this->itemRepository->saveFeed($feed);
                $result[] = $feed;
            } catch (\FacebookApiException $e) {
                $this->logger->warning('initial load for ' . self::TYPE . ' feeds failed', array('data' => $e->getMessage())); //TODO => handle FacebookApiException
            }
        }

        return $this->getFeedItemsFromApiRequest($result, $options);
    }

    public function getFeedItemsFromApiRequest($result, $options)
    {
        $rawFeeds = array();
        $feedItems = array();

        // TODO => Process post data from your service and create a Feed item for each post.
        ## THIS IMPLEMENTATION IS ONLY AN EXAMPLE! MODIFY THIS CODE TO MATCH YOUR SERVICE'S RESPONSE ##
        if (!empty($result)) {
            foreach ($result as $rawFeed) {
                $rawFeeds[self::TYPE . '_' . $rawFeed->getCacheIdentifier() . '_raw'] = $rawFeed->getResult();
                foreach ($rawFeed->getResult()->data as $dummy_post) {
                    if ($options->onlyWithPicture && empty($rawFeed->TODO_PROVIDER_JSON_PICTURE_NODE)) {
                        continue;
                    }
                    $feed = new Feed(self::TYPE, $dummy_post);
                    $feed->setId($dummy_post->TODO_PROVIDER_JSON_PICTURE_NODE);
                    $feed->setText(trim_text($dummy_post->TODO_PROVIDER_JSON_TEXT_NODE, $options->textTrimLength, true));
                    $feed->setImage($dummy_post->TODO_PROVIDER_JSON_PICTURE_NODE);
                    $feed->setLink($dummy_post->TODO_PROVIDER_JSON_LINK_NODE);
                    $feed->setTimeStampTicks($dummy_post->TODO_PROVIDER_JSON_MODIFY_DATE_NODE);
                    $feeds[] = $feed;
                }
            }
        }

        return array('rawFeeds' => $rawFeeds, 'feedItems' => $feedItems);
    }

    public function getPosts($searchValue)
    {
        $posts = $this->api->TODO_METHOD_TO_GET_POST_FROM_SERVICE($searchValue);

        return $posts;
    }

    /** Dummy method to get OAuth access token
     *
     * @param $code
     * @return null|string
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException
     */
    private function getAccessToken($code)
    {

        // If your service does not provide a method to get the appId,
        // you may have to store the appId in a private variable while initializing this adapter.
        $apiKey =  $this->api->TODO_METHOD_TO_GET_APP_ID();

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
            $token = $this->api->TODO_METHOD_TO_GET_OAUTH_ACCESS($code);
            $access_token = $token->access_token;
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
                error_log('-------- need new code ---------');
                $this->logger->error(self::TYPE . ' access code expired. Please provide new code in pb_social extension configuration.', array('data' => self::TYPE . ' access code invalid. Provide new code in pb_social extension configuration.'));
                return null;
            }
        }

        return $credential->getAccessToken();
    }
}
