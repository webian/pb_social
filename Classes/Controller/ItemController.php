<?php
namespace PlusB\PbSocial\Controller;

use GeorgRinger\News\Domain\Model\Dto\NewsDemand;
use PlusB\PbSocial\Adapter;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2014 Mikolaj Jedrzejewski <mj@plusb.de>, plusB
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
    const TYPE_PINTEREST = 'pinterest';
    const TYPE_TWITTER = 'twitter';
    const TYPE_TUMBLR = 'tumblr';
    const TYPE_YOUTUBE = 'youtube';
    const TYPE_VIMEO = 'vimeo';
    const TYPE_DUMMY = 'dummy';

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
     * action list
     *
     * @return void
     */
    public function listAction()
    {
        $items = $this->itemRepository->findAll();
        $this->view->assign('items', $items);
    }

    /**
     * action show
     *
     * @param \PlusB\PbSocial\Domain\Model\Item $item
     * @return void
     */
    public function showAction(\PlusB\PbSocial\Domain\Model\Item $item)
    {
        $this->view->assign('item', $item);
    }

    /**
     * action edit
     *
     * @param \PlusB\PbSocial\Domain\Model\Item $item
     * @ignorevalidation $item
     * @return void
     */
    public function editAction(\PlusB\PbSocial\Domain\Model\Item $item)
    {
        $this->view->assign('item', $item);
    }

    /**
     * action update
     *
     * @param \PlusB\PbSocial\Domain\Model\Item $item
     * @return void
     */
    public function updateAction(\PlusB\PbSocial\Domain\Model\Item $item)
    {
        $this->addFlashMessage('The object was updated. Please be aware that this action is publicly accessible unless you implement an access check. See <a href=\'http://wiki.typo3.org/T3Doc/Extension_Builder/Using_the_Extension_Builder#1._Model_the_domain\' target=\'_blank\'>Wiki</a>', '', \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR);
        $this->itemRepository->updateFeed($item);
        $this->redirect('list');
    }

    /**
     * action delete
     *
     * @param \PlusB\PbSocial\Domain\Model\Item $item
     * @return void
     */
    public function deleteAction(\PlusB\PbSocial\Domain\Model\Item $item)
    {
        $this->addFlashMessage('The object was deleted. Please be aware that this action is publicly accessible unless you implement an access check. See <a href=\'http://wiki.typo3.org/T3Doc/Extension_Builder/Using_the_Extension_Builder#1._Model_the_domain\' target=\'_blank\'>Wiki</a>', '', \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR);
        $this->itemRepository->remove($item);
        $this->redirect('list');
    }

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

        $extConf = @unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['pb_social']); //TODO => search for a better way of accessing extconf
        $this->logger = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Core\Log\LogManager')->getLogger(__CLASS__);

        // update feedRequestLimit if request is asynchronous
        if ($ajax) {
            $this->settings['feedRequestLimit'] = $this->settings['asynchLimit'] > 0 ? $this->settings['asynchLimit'] : $this->settings['feedRequestLimit'];
        }
        if ($this->settings['asynchRequest']) {
            $extConf['socialfeed.']['devmod'] = 1;
        }

        # Get feeds #
        $feeds = array();
        $results = $this->getFeeds($extConf, $this->settings, $this->itemRepository, $this->credentialRepository);

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
    public function facebookReactionAction($id)
    {
        $extConf = @unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['pb_social']); //TODO => search for a better way of accessing extconf

        # check api key #
        $config_apiId = $extConf['socialfeed.']['facebook.']['api.']['id'];
        $config_apiSecret = $extConf['socialfeed.']['facebook.']['api.']['secret'];

        $reactions = array();

        if (empty($config_apiId) || empty($config_apiSecret)) {
            $this->logger->warning(self::TYPE_FACEBOOK . ' credentials not set');
        } else {
            # retrieve data from adapter #
            $adapter = new Adapter\FacebookAdapter($config_apiId, $config_apiSecret, $this->itemRepository);
            $reactions = $adapter->getReactions($id);
        }

        $this->view->assign('reactions', $reactions);
        return $reactions;
    }

    public function getFeeds($extConf, $settings, $itemRepository, $credentialRepository)
    {

        // Build configuration from plugin settings
        $adapterOptions = $this->getAdapterOptions($settings);
        $adapterOptions->devMod = $extConf['socialfeed.']['devmod'] == '1' ? true : false;

        $results = array();

        $cache = $this->cacheManager->getCache('pb_social_cache');

        if ($settings['facebookEnabled'] === '1') {
            $cacheIdentifier = $this->calculateCacheIdentifier(array(
                "facebook_".$adapterOptions->settings['facebookSearchIds'], // cache depends on the searchids
            ));

            if ($content = $cache->get($cacheIdentifier)) { // the cached content is available
                $results[] = $content;
            }
        }

        if ($settings['googleEnabled'] === '1') {
            $cacheIdentifier = $this->calculateCacheIdentifier(array(
                "googleplus_".$adapterOptions->settings['googleSearchIds'], // cache depends on the searchids
            ));

            if ($content = $cache->get($cacheIdentifier)) { // the cached content is available
                $results[] = $content;
            }
        }

        if ($settings['imgurEnabled'] === '1') {
            $cacheIdentifier = $this->calculateCacheIdentifier(array(
                "imgur_".$adapterOptions->settings['imgurTags'],
                "imgur_".$adapterOptions->settings['imgurUsers']
            ));

            if ($content = $cache->get($cacheIdentifier)) { // the cached content is available
                $results[] = $content;
            }
        }

        if ($settings['instagramEnabled'] === '1') {
            $cacheIdentifier = $this->calculateCacheIdentifier(array(
                "instagram_".$adapterOptions->settings['instagramSearchIds'],
                "instagram_".$adapterOptions->settings['instagramHashTag'],
                "instagram_".$adapterOptions->settings['instagramPostFilter']
            ));

            if ($content = $cache->get($cacheIdentifier)) { // the cached content is available
                $results[] = $content;
            }
        }

        if ($settings['linkedinEnabled'] === '1') {
            $linkedInFeedFilters =
                ($adapterOptions->settings['linkedinJobPostings']) .
                ($adapterOptions->settings['linkedinNewProducts']) .
                ($adapterOptions->settings['linkedinStatusUpdates']);
            $cacheIdentifier = $this->calculateCacheIdentifier(array(
                "linkedin_".$adapterOptions->settings['linkedinCompanyIds'],
                "linkedin_".$linkedInFeedFilters
            ));

            if ($content = $cache->get($cacheIdentifier)) { // the cached content is available
                $results[] = $content;
            }
        }

        if ($settings['pinterestEnabled'] === '1') {
            $cacheIdentifier = $this->calculateCacheIdentifier(array(
                "pinterest_".$adapterOptions->settings['username'],
                "pinterest_".$adapterOptions->settings['boardname']
            ));

            if ($content = $cache->get($cacheIdentifier)) { // the cached content is available
                $results[] = $content;
            }
        }

        if ($settings['tumblrEnabled'] === '1') {
            $cacheIdentifier = $this->calculateCacheIdentifier(array(
                "tumblr_".$adapterOptions->settings['tumblrBlogNames']
            ));

            if ($content = $cache->get($cacheIdentifier)) { // the cached content is available
                $results[] = $content;
            }
        }

        if ($settings['twitterEnabled'] === '1') {
            $cacheIdentifier = $this->calculateCacheIdentifier(array(
                "twitter_".$adapterOptions->settings['twitterSearchFieldValues'],
                "twitter_".$adapterOptions->settings['twitterProfilePosts'],
                "twitter_".$adapterOptions->settings['twitterLanguage'],
                "twitter_".$adapterOptions->settings['twitterGeoCode'],
                "twitter_".$adapterOptions->settings['twitterHideRetweets'],
                "twitter_".$adapterOptions->settings['twitterShowOnlyImages']
            ));

            if ($content = $cache->get($cacheIdentifier)) { // the cached content is available
                $results[] = $content;
            }
        }

        if ($settings['youtubeEnabled'] === '1') {
            $cacheIdentifier = $this->calculateCacheIdentifier(array(
                "youtube_".$adapterOptions->settings['youtubeSearch'],
                "youtube_".$adapterOptions->settings['youtubePlaylist'],
                "youtube_".$adapterOptions->settings['youtubeChannel'],
                "youtube_".$adapterOptions->settings['youtubeType'],
                "youtube_".$adapterOptions->settings['youtubeLanguage'],
                "youtube_".$adapterOptions->settings['youtubeOrder']
            ));

            if ($content = $cache->get($cacheIdentifier)) { // the cached content is available
                $results[] = $content;
            }
        }

        if ($settings['vimeoEnabled'] === '1') {
            $cacheIdentifier = $this->calculateCacheIdentifier(array(
                "vimeo_".$adapterOptions->settings['vimeoChannel']
            ));

            if ($content = $cache->get($cacheIdentifier)) { // the cached content is available
                $results[] = $content;
            }
        }

        if ($settings['newsEnabled'] === '1') {

            $cacheIdentifier = $this->calculateCacheIdentifier(array(
                "txnews_".$adapterOptions->settings['newsCategories']
            ));

            if ($content = $cache->get($cacheIdentifier)) { // the cached content is available
                $results[] = $content;
            }
        }

        if ($settings['dummyEnabled'] === '1') {

            // TODO => set some configuration 'ext/pb_social/ext_conf_template.txt'
            $config_dummyKey = $extConf['socialfeed.']['youtube.']['apikey'];

            // TODO => move search params to flexform for usability
            $adapterOptions->dummySearchValues = $settings['dummySearchValues'];

            if (empty($config_dummyKey)) {
                $this->logger->warning(self::TYPE_DUMMY . ' credentials not set');
            } elseif (empty($adapterOptions->dummySearchValue)) {
                $this->logger->warning(self::TYPE_DUMMY . ' no search term defined');
            } else {
                # retrieve data from adapter #
                $adapter = new Adapter\DummyAdapter($config_dummyKey, $itemRepository, $credentialRepository);
                try {
                    $results[] = $adapter->getResultFromApi($adapterOptions);
                } catch (\Exception $e) {
                    $this->logger->warning($e->getMessage());
                }
            }
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

    public function getAdapterOptions($settings)
    {
        $options = (object)array();

        $options->twitterHideRetweets = empty($settings['twitterHideRetweets']) ? false : ($settings['twitterHideRetweets'] == '1' ? true : false);
        $options->twitterShowOnlyImages = empty($settings['twitterShowOnlyImages']) ? false : ($settings['twitterShowOnlyImages'] == '1' ? true : false);
        $options->twitterHTTPS = empty($settings['twitterHTTPS']) ? false : ($settings['twitterHTTPS'] == '1' ? true : false);
        $options->tumblrShowOnlyImages = empty($settings['tumblrShowOnlyImages']) ? false : ($settings['tumblrShowOnlyImages'] == '1' ? true : false);
//        $options->facebookShowOnlyImages = empty($settings['facebookShowOnlyImages']) ? false : ($settings['facebookShowOnlyImages'] == '1' ? true : false);
        $options->feedRequestLimit = intval(empty($settings['feedRequestLimit']) ? '10' : $settings['feedRequestLimit']);

        $refreshTimeInMin = intval(empty($settings['refreshTimeInMin']) ? '10' : $settings['refreshTimeInMin']);
        if ($refreshTimeInMin == 0) {
            $refreshTimeInMin = 10;
        } //reset to 10 if intval() cant convert
        $options->refreshTimeInMin = $refreshTimeInMin;

        $options->settings = $settings;
        $options->onlyWithPicture = $settings['onlyWithPicture'] === '1' ? true : false;
        $options->textTrimLength = intval($settings['textTrimLength']) > 0 ? intval($settings['textTrimLength']) : 130;
        $options->feedRequestLimit = intval(empty($settings['feedRequestLimit']) ? 10 : $settings['feedRequestLimit']);

        return $options;
    }

    /**
     * Calculates the cache identifier
     * @param \array $arr
     * @return \string
     */
    public static function calculateCacheIdentifier($arr) {
        return sha1(json_encode($arr)); // in average json_encode is four times faster than serialize()
    }
}

/**
 * trims text to a space then adds ellipses if desired
 * @param string $input text to trim
 * @param int $length in characters to trim to
 * @param bool $ellipses if ellipses (...) are to be added
 * @param bool $strip_html if html tags are to be stripped
 * @return string
 */
function trim_text($input, $length, $ellipses = true, $strip_html = true)
{
    if (empty($input)) {
        return '';
    }

    //strip tags, if desired
    if ($strip_html) {
        $input = strip_tags($input);
    }

    //no need to trim, already shorter than trim length
    if (strlen($input) <= $length) {
        return $input;
    }

    //find last space within length
    $last_space = strrpos(substr($input, 0, $length), ' ');
    $trimmed_text = substr($input, 0, $last_space);

    //add ellipses (...)
    if ($ellipses) {
        $trimmed_text .= '...';
    }

    return $trimmed_text;
}
