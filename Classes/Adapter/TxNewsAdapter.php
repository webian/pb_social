<?php

namespace PlusB\PbSocial\Adapter;

use GeorgRinger\News\Domain\Model\Dto\NewsDemand;
use GeorgRinger\News\Domain\Repository\NewsRepository;
use PlusB\PbSocial\Domain\Model\Feed;
use PlusB\PbSocial\Domain\Model\Item;
use PlusB\PbSocial\Domain\Repository\ItemRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

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

class TxNewsAdapter extends SocialMediaAdapter
{

    const TYPE = 'txnews';

    protected $cObj;
    protected $detailPageUid;

    /**
     * newsRepository
     *
     * @var \GeorgRinger\News\Domain\Repository\NewsRepository
     * @inject
     */
    protected $newsRepository;
    public $newsDemand;

    /**
     * TxNewsAdapter constructor.
     * @param NewsDemand $newsDemand
     * @param ItemRepository $itemRepository
     */
    public function __construct($newsDemand, $itemRepository)
    {
        parent::__construct($itemRepository);

        $this->cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);
        $this->newsDemand = $newsDemand;

        $om = new ObjectManager();
        $this->newsRepository = $om->get(NewsRepository::class);
    }

    public function getResultFromApi($options)
    {
        $result = array();

        $this->detailPageUid = $options->newsDetailPageUid;
        $newsCategories = GeneralUtility::trimExplode(',', $options->newsCategories);

        foreach ($newsCategories as $newsCategory) {
            $searchString = trim($newsCategory);
            $feeds = $this->itemRepository->findByTypeAndCacheIdentifier(self::TYPE, $searchString);
            $this->newsDemand->setCategories(array($newsCategory));
            if(!empty($newsCategory)) $this->newsDemand->setCategoryConjunction('or');
            if ($feeds && $feeds->count() > 0) {
                $feed = $feeds->getFirst();
                if ($options->devMod || ($feed->getDate()->getTimestamp() + $options->refreshTimeInMin * 60) < time()) {
                    try {
                        $feed->setDate(new \DateTime('now'));
                        $demanded = $this->newsRepository->findDemanded($this->newsDemand)->toArray();
                        $mapped = empty($demanded) ? array() : $this->demandedNewsToString($demanded, $options->useHttps);
                        $feed->setResult($mapped);
                        $this->itemRepository->updateFeed($feed);
                        $result[] = $feed;
                    } catch (\Exception $e) {
                        $this->logError("feeds can't be updated " . $e->getMessage());
                    }
                }
                continue;
            }

            try {
                $feed = new Item(self::TYPE);
                $feed->setCacheIdentifier($searchString);
                $demanded = $this->newsRepository->findDemanded($this->newsDemand)->toArray();
                $mapped = empty($demanded) ? array() : $this->demandedNewsToString($demanded, $options->useHttps);
                $feed->setResult($mapped);
                // save to DB and return current feed
                $this->itemRepository->saveFeed($feed);
                $result[] = $feed;
            } catch (\Exception $e) {
                $this->logError('initial load for ' . self::TYPE . ' feeds failed. ' . $e->getMessage());
            }
        }

        return $this->getFeedItemsFromApiRequest($result, $options);
    }

    public function getFeedItemsFromApiRequest($result, $options)
    {
        $rawFeeds = array();
        $feedItems = array();

        if (!empty($result)) {
            foreach ($result as $news_feed) {
                $rawFeeds[self::TYPE . '_' . $news_feed->getCacheIdentifier() . '_raw'] = $news_feed->getResult();
                # traverse each single news item
                $i = 0;
                foreach ($news_feed->getResult() as $rawFeed) {
                    if ($i < $options->feedRequestLimit)
                    {
                        $feed = new Feed(self::TYPE, $rawFeed);

                        $feed->setId($rawFeed->id);
                        $feed->setText($this->trim_text($rawFeed->name, $options->textTrimLength, true));
                        $feed->setImage($rawFeed->image);
                        $feed->setLink($rawFeed->link);
                        $feed->setTimeStampTicks($rawFeed->crdate);
                        $feedItems[] = $feed;
                        $i++;
                    }
                }
            }
        }

        return array('rawFeeds' => $rawFeeds, 'feedItems' => $feedItems);
    }

    private function demandedNewsToString($demanded, $useHttps = false)
    {
        $mapped = array();
        /** @var \GeorgRinger\News\Domain\Model\News $news */
        foreach ($demanded as $news)
        {

            $img_link = '';
            if ($news->getMedia()->count() > 0)
            {
                $img_link = '/' .$news->getMedia()->current()->getOriginalResource()->getPublicUrl();
            }

            $newsItem = array(
                'id' => $news->getUid(),
                'name' => $news->getTitle(),
                'image' => $img_link,
                'link' => $this->detailPageUid,
                'crdate' => $news->getCrdate()->getTimestamp()
            );

            $mapped[] = $newsItem;
        }

        return json_encode($mapped);
    }

}
