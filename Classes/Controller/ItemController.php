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
     * action showSocialBarAction
     * @return void
     */
    public function showSocialBarAction()
    {
        // function has nothing to do with database => only as template ref dummy
        // the magic is located only in the template and main.js :)
    }

    /**
     * action showSocialFeedAction
     * @param bool $ajax is true, when the request is coming from an ajax request
     * @return void
     */
    public function showSocialFeedAction($ajax = false)
    {

        // update feedRequestLimit if request is asynchronous
        if ($ajax) {
            $this->settings['feedRequestLimit'] = $this->settings['asynchLimit'] > 0 ? $this->settings['asynchLimit'] : $this->settings['feedRequestLimit'];
        }
        if ($this->settings['asynchRequest']) {
            $extConf['socialfeed.']['devmod'] = 1;
        }

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

    /** Returns Facebook recations (wow, love, sad..) for post with given id
     *
     * @param string $id
     * @return array|void
     */
    public function _facebookReactionAction($id)
    {
        $this->logger = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Core\Log\LogManager')->getLogger(__CLASS__);
        $extConf = @unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['pb_social']); //TODO => search for a better way of accessing extconf

        # check api key #
        $config_apiId = $extConf['socialfeed.']['facebook.']['api.']['id'];
        $config_apiSecret = $extConf['socialfeed.']['facebook.']['api.']['secret'];

        $reactions = array();

        if (empty($config_apiId) || empty($config_apiSecret)) {
            $this->logger->warning(self::TYPE_FACEBOOK . ' credentials not set');
        } else {
            # retrieve data from adapter #
            $adapter = new Adapter\FacebookAdapter($config_apiId, $config_apiSecret, $this->itemRepository, NULL);
            $reactions = $adapter->getReactions($id);
        }

        $this->view->assign('reactions', $reactions);
        return $reactions;
    }


    /**
     * @param $settings array of typoscript settings
     * @return array
     */
    public function getFeedsFromCache($settings)
    {
        //result array, sorted and foreached in action method
        $results = array();
        //cache specified by $identifier



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
