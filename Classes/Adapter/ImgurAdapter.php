<?php

namespace PlusB\PbSocial\Adapter;

$extensionPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('pb_social') . 'Resources/Private/Libs/';
require $extensionPath . 'imgur/Imgur.php';

use PlusB\PbSocial\Domain\Model\Feed;
use PlusB\PbSocial\Domain\Model\Item;

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

class ImgurAdapter extends SocialMediaAdapter
{

    const TYPE = 'imgur';

    public $isValid = false, $validationMessage = "";
    private $apiId, $apiSecret, $options;

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
     * @param mixed $options
     */
    public function setOptions($options)
    {
        $this->options = $options;
    }

    private $api;

    public function __construct($apiId, $apiSecret, $itemRepository, $options)
    {
        parent::__construct($itemRepository);
        /**
         * todo: quickfix - but we better add a layer for adapter inbetween, here after "return $this" intance is not completet but existend (AM)
         */
        /* validation - interrupt instanciating if invalid */
        if($this->validateAdapterSettings(
                array(
                    'apiId' => $apiId,
                    'apiSecret' => $apiSecret,
                    'options' => $options
                )) === false)
        {return $this;}
        /* validated */

        $this->api =  new \Imgur($this->apiId, $this->apiSecret);

        //TODO: Implement OAuth authentication (to get a user's images etc)
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
        $this->setOptions($parameter['options']);

        if (empty($this->apiId) || empty($this->apiSecret)) {
            $this->validationMessage = ' credentials not set';
        } elseif (empty($this->options->imgSearchUsers) && empty($this->options->imgSearchTags)) {
            $this->validationMessage = ' no search term defined';
        } else {
            $this->isValid = true;
        }

        return $this->isValid;
    }

    public function getResultFromApi()
    {
        $options = $this->options;
        $result = array();
        /*
        * todo: duplicate cache writing, must be erazed here - $searchId is invalid cache identifier OptionService:getCacheIdentifierElementsArray returns valid one (AM)
        */
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
                        $this->logError("feeds can't be updated - " . $e->getMessage());
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
                $this->logError('initial load for feed failed - ' . $e->getMessage());
            }
        }

        // search for tags
        foreach (explode(',', $options->imgSearchTags) as $searchId) {
            $searchId = trim($searchId);
            $feeds = $this->itemRepository->findByTypeAndCacheIdentifier(self::TYPE, $searchId);
            if ($feeds && $feeds->count() > 0) {
                $feed = $feeds->getFirst();
                /**
                 * todo: (AM) "$options->refreshTimeInMin * 60) < time()" locks it to a certain cache lifetime - users want to bee free, so... change!
                 * todo: try to get rid of duplicate code
                 */
                if ($options->devMod || ($feed->getDate()->getTimestamp() + $options->refreshTimeInMin * 60) < time()) {
                    try {
                        $posts = json_encode($this->api->gallery()->search($searchId));
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
                $posts = json_encode($this->api->gallery()->search($searchId));
                $feed = new Item(self::TYPE);
                $feed->setCacheIdentifier($searchId);
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
