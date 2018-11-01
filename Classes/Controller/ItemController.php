<?php
namespace PlusB\PbSocial\Controller;

use PlusB\PbSocial\Adapter;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2014 Mikolaj Jedrzejewski <mj@plusb.de>, plusB
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

/**
 * ItemController
 */
class ItemController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{

    const TYPE_FACEBOOK = 'facebook';
    const TYPE_GOOGLE = 'googleplus';
    const TYPE_IMGUR = 'imgur';
    const TYPE_INSTAGRAM = 'instagram';
    const TYPE_LINKEDIN = 'linkedin';
    const TYPE_PINTEREST = 'pinterest';
    const TYPE_TWITTER = 'twitter';
    const TYPE_TUMBLR = 'tumblr';
    const TYPE_YOUTUBE = 'youtube';
    const TYPE_TX_NEWS = 'tx_news';
    const TYPE_VIMEO = 'vimeo';
    const TYPE_DUMMY = 'dummy';

    const EXTKEY = 'pb_social';

    /**
     * @var \PlusB\PbSocial\Service\OptionService
     * @inject
     */
    protected $optionService;

    /**
     * @var \PlusB\PbSocial\Service\CacheService
     * @inject
     */
    protected $cacheService;


    /**
     * cacheManager
     *
     * @var \TYPO3\CMS\Core\Cache\CacheManager
     * @inject
     */
    protected $cacheManager = null;

    /**
     * itemRepository
     *
     * @var \PlusB\PbSocial\Domain\Repository\ItemRepository
     * @inject
     */
    protected $itemRepository = null;

    /**
     * credentialRepository
     *
     * @var \PlusB\PbSocial\Domain\Repository\CredentialRepository
     * @inject
     */
    protected $credentialRepository = null;

    protected $logger;

    /**
     * action showSocialFeedAction
     * @return void
     */
    public function showSocialFeedAction()
    {
        # Get feeds #
        $feeds = array();

        $results = $this->getFeedsFromCache($this->settings);

        # Provide feeds to frontend #
        foreach ($results as $result) {
            foreach ($result['rawFeeds'] as $rfid => $rf) {
                $this->view->assign($rfid, $rf);
            }
            foreach ($result['feedItems'] as $feed) {
                $feeds[] = $feed;
            }
        }

        # Sort array if not empty #
        if (!empty($feeds)) {
            usort($feeds, array($this, 'cmp'));
        }
        $this->view->assign('feeds', $feeds);

        // load facebook images with full resolution
        if ($this->settings['facebookFullPicture']) {
            $this->view->assign('fb_full_res', 1);
        }

        // request via ajax
        if ($this->settings['asynchRequest']) {
            $this->view->assign('asynch_request', 1);
            $asynch_show = $this->settings['asynchShow'] > 0 ? $this->settings['asynchShow'] : $this->settings['feedRequestLimit'];
            $this->view->assign('asynch_show', $asynch_show);
        }
    }

    /**
     * @param $settings array of typoscript settings
     * @return array
     */
    public function getFeedsFromCache($settings)
    {
        $results = array();

        if ($settings['facebookEnabled'] === '1') {

            $results = $this->cacheService->getCacheContent(
                self::TYPE_FACEBOOK, $settings, $this->configurationManager->getContentObject()->data['uid'], $results
            );
        }


        if ($settings['imgurEnabled'] === '1') {
            $results = $this->cacheService->getCacheContent(
                self::TYPE_IMGUR, $settings, $this->configurationManager->getContentObject()->data['uid'], $results
            );
        }



        if ($settings['instagramEnabled'] === '1') {
            $results = $this->cacheService->getCacheContent(
                self::TYPE_INSTAGRAM, $settings, $this->configurationManager->getContentObject()->data['uid'], $results
            );
        }

        if ($settings['linkedinEnabled'] === '1') {
            $results = $this->cacheService->getCacheContent(
                self::TYPE_LINKEDIN, $settings, $this->configurationManager->getContentObject()->data['uid'], $results
            );
        }

        if ($settings['pinterestEnabled'] === '1') {
            $results = $this->cacheService->getCacheContent(
                self::TYPE_PINTEREST, $settings, $this->configurationManager->getContentObject()->data['uid'], $results
            );
        }

        if ($settings['tumblrEnabled'] === '1') {
            $results = $this->cacheService->getCacheContent(
                self::TYPE_TUMBLR, $settings, $this->configurationManager->getContentObject()->data['uid'], $results
            );
        }

        if ($settings['twitterEnabled'] === '1') {
            $results = $this->cacheService->getCacheContent(
                self::TYPE_TWITTER, $settings, $this->configurationManager->getContentObject()->data['uid'], $results
            );
        }

        if ($settings['youtubeEnabled'] === '1') {
            $results = $this->cacheService->getCacheContent(
                self::TYPE_YOUTUBE, $settings, $this->configurationManager->getContentObject()->data['uid'], $results
            );
        }

        if ($settings['vimeoEnabled'] === '1') {
            $results = $this->cacheService->getCacheContent(
                self::TYPE_VIMEO, $settings, $this->configurationManager->getContentObject()->data['uid'], $results
            );
        }

        if ($settings['newsEnabled'] === '1') {
            $results = $this->cacheService->getCacheContent(
                self::TYPE_TX_NEWS, $settings, $this->configurationManager->getContentObject()->data['uid'], $results
            );
        }

        return $results;
    }

    public function cmp($a, $b)
    {
        if ($a == $b) {
            return 0;
        }
        return ($a->getTimeStampTicks() > $b->getTimeStampTicks()) ? -1 : 1;
    }

    public function check_end($str, $ends)
    {
        foreach ($ends as $try) {
            if (substr($str, -1 * strlen($try)) === $try) {
                return $try;
            }
        }
        return false;
    }
}
