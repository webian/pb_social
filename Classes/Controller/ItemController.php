<?php
namespace PlusB\PbSocial\Controller;
use PlusB\PbSocial\Adapter;


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
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

/**
 * ItemController
 */
class ItemController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController {

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
     * itemRepository
     *
     * @var \PlusB\PbSocial\Domain\Repository\ItemRepository
     * @inject
     */
    protected $itemRepository = NULL;

    /**
     * credentialRepository
     *
     * @var \PlusB\PbSocial\Domain\Repository\CredentialRepository
     * @inject
     */
    protected $credentialRepository = NULL;

    protected $logger;

    /**
     * action list
     *
     * @return void
     */
    public function listAction() {
        $items = $this->itemRepository->findAll();
        $this->view->assign('items', $items);
    }

    /**
     * action show
     *
     * @param \PlusB\PbSocial\Domain\Model\Item $item
     * @return void
     */
    public function showAction(\PlusB\PbSocial\Domain\Model\Item $item) {
        $this->view->assign('item', $item);
    }

    /**
     * action edit
     *
     * @param \PlusB\PbSocial\Domain\Model\Item $item
     * @ignorevalidation $item
     * @return void
     */
    public function editAction(\PlusB\PbSocial\Domain\Model\Item $item) {
        $this->view->assign('item', $item);
    }

    /**
     * action update
     *
     * @param \PlusB\PbSocial\Domain\Model\Item $item
     * @return void
     */
    public function updateAction(\PlusB\PbSocial\Domain\Model\Item $item) {
        $this->addFlashMessage('The object was updated. Please be aware that this action is publicly accessible unless you implement an access check. See <a href=\'http://wiki.typo3.org/T3Doc/Extension_Builder/Using_the_Extension_Builder#1._Model_the_domain\' target=\'_blank\'>Wiki</a>', '', \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR);
        $this->itemRepository->update($item);
        $this->redirect('list');
    }

    /**
     * action delete
     *
     * @param \PlusB\PbSocial\Domain\Model\Item $item
     * @return void
     */
    public function deleteAction(\PlusB\PbSocial\Domain\Model\Item $item) {
        $this->addFlashMessage('The object was deleted. Please be aware that this action is publicly accessible unless you implement an access check. See <a href=\'http://wiki.typo3.org/T3Doc/Extension_Builder/Using_the_Extension_Builder#1._Model_the_domain\' target=\'_blank\'>Wiki</a>', '', \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR);
        $this->itemRepository->remove($item);
        $this->redirect('list');
    }

    /**
     * action showSocialBarAction
     * @return void
     */
    public function showSocialBarAction() {
        // function has nothing to do with database => only as template ref dummy
        // the magic is located only in the template and main.js :)
    }

    /**
     * action showSocialFeedAction
     * @return void
     */
    public function showSocialFeedAction() {

        $extConf = @unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['pb_social']); //TODO => search for a better way of accessing extconf
        $this->logger = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Core\Log\LogManager')->getLogger(__CLASS__);

        # Get feeds #
        $feeds = array();
        $results = $this->getFeeds($extConf, $this->settings, $this->itemRepository, $this->credentialRepository);

        # Provide feeds to frontend #
        foreach ($results as $result) {
            foreach ($result['rawFeeds'] as $rfid => $rf) $this->view->assign($rfid, $rf);
            foreach ($result['feedItems'] as $feed) $feeds[] = $feed;
        }

        # Sort array if not empty #
        if (!empty($feeds)) {
            usort($feeds, array($this, 'cmp'));
        }
        $this->view->assign('feeds', $feeds);
    }

    public function getFeeds($extConf, $settings, $itemRepository, $credentialRepository){

        // Build configuration from plugin settings
        $adapterOptions = $this->getAdapterOptions($settings);
        $adapterOptions->devMod = $extConf['socialfeed.']['devmod'] == '1' ? true : false;

        
        $results = array();

        if ($settings['facebookEnabled'] === '1') {
            # check api key #
            $config_apiId = $extConf['socialfeed.']['facebook.']['api.']['id'];
            $config_apiSecret = $extConf['socialfeed.']['facebook.']['api.']['secret'];

            if (empty($config_apiId) || empty($config_apiSecret)) {
                $this->logger->warning( self::TYPE_FACEBOOK . ' credentials not set');
            } elseif(empty($adapterOptions->settings['facebookSearchIds'])){
                $this->logger->warning( self::TYPE_FACEBOOK . ' no search term defined');
            } else {
                # retrieve data from adapter #
                $adapter = new Adapter\FacebookAdapter($config_apiId, $config_apiSecret, $itemRepository);
                $results[] = $adapter->getResultFromApi($adapterOptions);
            }

        }

        if ($settings['googleEnabled'] === '1') {

            # check api key #
            $config_appKey = $extConf['socialfeed.']['googleplus.']['app.']['key'];

            if (empty($config_appKey)) {
                $this->logger->warning(self::TYPE_GOOGLE . ' credentials not set');
            } elseif (empty($settings['googleSearchIds'])) {
                $this->logger->warning(self::TYPE_GOOGLE . ' no search term defined');
            } else {
                # retrieve data from adapter #
                $adapter = new Adapter\GooglePlusAdapter($config_appKey, $itemRepository);
                $results[] = $adapter->getResultFromApi($adapterOptions);
            }

        }

        if ($settings['imgurEnabled'] === '1') {

            # check api key #
            $config_apiId = $extConf['socialfeed.']['imgur.']['client.']['id'];
            $config_apiSecret = $extConf['socialfeed.']['imgur.']['client.']['secret'];
            $adapterOptions->imgSearchUsers = $settings['imgurUsers'];
            $adapterOptions->imgSearchTags = $settings['imgurTags'];

            if (empty($config_apiId) || empty($config_apiSecret)) {
                $this->logger->warning(self::TYPE_IMGUR . ' credentials not set');
            } elseif  (empty($adapterOptions->imgSearchUsers) && empty($adapterOptions->imgSearchTags)) {
                $this->logger->warning(self::TYPE_IMGUR . ' no search term defined');
            } else {
                # retrieve data from adapter #
                $adapter = new Adapter\ImgurAdapter($config_apiId, $config_apiSecret, $itemRepository);
                $results[] = $adapter->getResultFromApi($adapterOptions);
            }

        }

        if ($settings['instagramEnabled'] === '1') {

            # check api key #
            $config_clientId = $extConf['socialfeed.']['instagram.']['client.']['id'];
            $config_clientSecret = $extConf['socialfeed.']['instagram.']['client.']['secret'];
            $config_clientCallback = $extConf['socialfeed.']['instagram.']['client.']['callback'];
            $config_access_code = $extConf['socialfeed.']['instagram.']['client.']['access_code'];
            $adapterOptions->instagramHashTags = $settings['instagramHashTag'];
            $adapterOptions->instagramSearchIds = $settings['instagramSearchIds'];
            $adapterOptions->instagramPostFilter = $settings['instagramPostFilter'];

            if (empty($config_clientId) || empty($config_clientSecret) || empty($config_clientCallback) ) {
                $this->logger->warning(self::TYPE_INSTAGRAM . ' credentials not set');
            } elseif (empty($adapterOptions->instagramSearchIds) && empty($adapterOptions->instagramHashTags)) {
                $this->logger->warning(self::TYPE_INSTAGRAM . ' no search term defined');
            } else {
                # retrieve data from adapter #
                $adapter = new Adapter\InstagramAdapter($config_clientId, $config_clientSecret, $config_clientCallback, $config_access_code, $itemRepository, $credentialRepository);
                $results[] = $adapter->getResultFromApi($adapterOptions);
            }

        }

        if ($settings['pinterestEnabled'] === '1') {

            # check api key #
            $config_appId = $extConf['socialfeed.']['pinterest.']['app.']['id'];
            $config_appSecret = $extConf['socialfeed.']['pinterest.']['app.']['secret'];
            $config_accessCode = $extConf['socialfeed.']['pinterest.']['app.']['code'];
            $adapterOptions->pinterest_username = $settings['username'];
            $adapterOptions->pinterest_boardname = $settings['boardname'];


            if (empty($config_appId) || empty($config_appSecret) || empty($config_accessCode) ) {
                $this->logger->warning(self::TYPE_PINTEREST . ' credentials not set');
            } elseif (empty($adapterOptions->pinterest_username) || empty($adapterOptions->pinterest_boardname)) {
                $this->logger->warning(self::TYPE_PINTEREST . ' no username or no boardname defined');
            } else {
                # retrieve data from adapter #
                $adapter = new Adapter\PinterestAdapter($config_appId, $config_appSecret, $config_accessCode, $itemRepository, $credentialRepository);
                $results[] = $adapter->getResultFromApi($adapterOptions);
            }

        }

        if ($settings['tumblrEnabled'] === '1') {

            # check api key #
            $config_consumerKey = $extConf['socialfeed.']['tumblr.']['consumer.']['key'];
            $config_consumerSecret = $extConf['socialfeed.']['tumblr.']['consumer.']['secret'];
            $config_Token = $extConf['socialfeed.']['tumblr.']['token'];
            $config_TokenSecret = $extConf['socialfeed.']['tumblr.']['token_secret'];

            $adapterOptions->tumblrHashtag = strtolower(str_replace('#', '', $settings['tumblrHashTag']));
            $adapterOptions->tumblrBlogNames = $settings['tumblrBlogNames'];
            $adapterOptions->tumblrShowOnlyImages = $settings['tumblrShowOnlyImages'];

            if (empty($config_consumerKey) || empty($config_consumerSecret) || empty($config_Token) || empty($config_TokenSecret)) {
                $this->logger->warning(self::TYPE_TUMBLR . ' credentials not set');
            } elseif (empty($adapterOptions->tumblrBlogNames)) {
                $this->logger->warning(self::TYPE_TUMBLR . ' - no blog names for search term defined');
            } else {
                # retrieve data from adapter #
                $adapter = new Adapter\TumblrAdapter($config_consumerKey, $config_consumerSecret, $config_Token, $config_TokenSecret, $itemRepository);
                $results[] = $adapter->getResultFromApi($adapterOptions);
            }

        }

        if ($settings['twitterEnabled'] === '1') {

            # check api key #
            $config_consumerKey = $extConf['socialfeed.']['twitter.']['consumer.']['key'];
            $config_consumerSecret = $extConf['socialfeed.']['twitter.']['consumer.']['secret'];
            $config_accessToken = $extConf['socialfeed.']['twitter.']['oauth.']['access.']['token'];
            $config_accessTokenSecret = $extConf['socialfeed.']['twitter.']['oauth.']['access.']['token_secret'];

            $adapterOptions->twitterSearchFieldValues = $settings['twitterSearchFieldValues'];
            $adapterOptions->twitterProfilePosts = $settings['twitterProfilePosts'];
            $adapterOptions->twitterLanguage = $settings['twitterLanguage'];
            $adapterOptions->twitterGeoCode = $settings['twitterGeoCode'];
            $adapterOptions->twitterHideRetweets = $settings['twitterHideRetweets'];
            $adapterOptions->twitterShowOnlyImages = $settings['twitterShowOnlyImages'];

            if (empty($config_consumerKey) || empty($config_consumerSecret) || empty($config_accessToken) || empty($config_accessTokenSecret)) {
                $this->logger->warning(self::TYPE_TWITTER . ' credentials not set');
            } elseif (empty($adapterOptions->twitterSearchFieldValues) && empty($adapterOptions->twitterProfilePosts)) {
                $this->logger->warning(self::TYPE_TWITTER . ' no search term defined');
            } else {
                # retrieve data from adapter #
                $adapter = new Adapter\TwitterAdapter($config_consumerKey, $config_consumerSecret, $config_accessToken, $config_accessTokenSecret, $itemRepository);
                $results[] = $adapter->getResultFromApi($adapterOptions);
            }

        }

        if ($settings['youtubeEnabled'] === '1') {

            # check api key #
            $config_apiKey = $extConf['socialfeed.']['youtube.']['apikey'];
            $adapterOptions->youtubeSearch = $settings['youtubeSearch'];
            $adapterOptions->youtubePlaylist = $settings['youtubePlaylist'];
            $adapterOptions->youtubeType = $settings['youtubeType'];
            $adapterOptions->youtubeLanguage = $settings['youtubeLanguage'];
            $adapterOptions->youtubeOrder = $settings['youtubeOrder'];

            if (empty($config_apiKey)) {
                $this->logger->warning(self::TYPE_YOUTUBE . ' credentials not set');
            } elseif (empty($adapterOptions->youtubeSearch) && empty($adapterOptions->youtubePlaylist)) {
                $this->logger->warning(self::TYPE_YOUTUBE . ' no search term defined');
            } else {
                # retrieve data from adapter #
                $adapter = new Adapter\YoutubeAdapter($config_apiKey, $itemRepository);
                $results[] = $adapter->getResultFromApi($adapterOptions);
            }

        }

        if ($settings['vimeoEnabled'] === '1') {

            # check api key #
            
            $config_clientIdentifier = $extConf['socialfeed.']['vimeo.']['client.']['identifier'];
            $config_clientSecret = $extConf['socialfeed.']['vimeo.']['client.']['secret'];
            $config_token = $extConf['socialfeed.']['vimeo.']['token'];
            $adapterOptions->vimeoUrl = $settings['vimeoUrl'];
            $adapterOptions->vimeoFilter = $settings['vimeoFilter'];

            // if (empty($config_clientIdentifier) || empty($config_clientSecret) || empty($config_token)) {
            if (empty($config_clientIdentifier) || empty($config_clientSecret) || empty($config_token)) {
                $this->logger->warning(self::TYPE_VIMEO . ' credentials not set');
            } elseif (empty($adapterOptions->vimeoUrl)) {
                $this->logger->warning(self::TYPE_VIMEO . ' no url defined');
            } else {
                # retrieve data from adapter #
                $adapter = new Adapter\VimeoAdapter($config_clientIdentifier, $config_clientSecret, $config_token, $itemRepository);
                $results[] = $adapter->getResultFromApi($adapterOptions);
            }

        }

        if ($settings['dummyEnabled'] === '1') {

            // TODO => set some configuration 'ext/pb_social/ext_conf_template.txt'
            $config_dummyKey = $extConf['socialfeed.']['youtube.']['apikey'];

            // TODO => move search params to flexform for usability
            $adapterOptions->dummySearchValues = $settings['dummySearchValues'];

            if(empty($config_dummyKey)){
                $this->logger->warning(self::TYPE_DUMMY . ' credentials not set');
            } elseif (empty($adapterOptions->dummySearchValue)) {
                $this->logger->warning(self::TYPE_DUMMY . ' no search term defined');
            } else {
                # retrieve data from adapter #
                $adapter = new Adapter\DummyAdapter($config_dummyKey, $itemRepository, $credentialRepository);
                $results[] = $adapter->getResultFromApi($adapterOptions);
            }

        }

        return $results;

    }

    public function cmp($a, $b) {
        if ($a == $b) {
            return 0;
        }
        return ($a->getTimeStampTicks() > $b->getTimeStampTicks()) ? -1 : 1;
    }

    function check_end($str, $ends) {
        foreach ($ends as $try) {
            if (substr($str, -1 * strlen($try)) === $try) return $try;
        }
        return false;
    }

    public function getAdapterOptions($settings){

        $options = (object) array();

        $options->twitterHideRetweets = empty($settings['twitterHideRetweets']) ? false : ($settings['twitterHideRetweets'] == '1' ? true : false);
        $options->twitterShowOnlyImages = empty($settings['twitterShowOnlyImages']) ? false : ($settings['twitterShowOnlyImages'] == '1' ? true : false);
        $options->tumblrShowOnlyImages = empty($settings['tumblrShowOnlyImages']) ? false : ($settings['tumblrShowOnlyImages'] == '1' ? true : false);
//        $options->facebookShowOnlyImages = empty($settings['facebookShowOnlyImages']) ? false : ($settings['facebookShowOnlyImages'] == '1' ? true : false);
        $options->feedRequestLimit = intval(empty($settings['feedRequestLimit']) ? '10' : $settings['feedRequestLimit']);

        $refreshTimeInMin = intval(empty($settings['refreshTimeInMin']) ? '10' : $settings['refreshTimeInMin']);
        if ($refreshTimeInMin == 0) $refreshTimeInMin = 10; //reset to 10 if intval() cant convert
        $options->refreshTimeInMin = $refreshTimeInMin;

        $options->settings = $settings;
        $options->onlyWithPicture = $settings['onlyWithPicture'] === '1' ? true : false;
        $options->textTrimLength = intval($settings['textTrimLength']) > 0 ? intval($settings['textTrimLength']) : 130;
        $options->feedRequestLimit = intval(empty($settings['feedRequestLimit']) ? 10 : $settings['feedRequestLimit']);

        return $options;

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
function trim_text($input, $length, $ellipses = true, $strip_html = true) {
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