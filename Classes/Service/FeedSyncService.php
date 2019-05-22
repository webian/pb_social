<?php

namespace PlusB\PbSocial\Service;

use PlusB\PbSocial\Adapter;
use PlusB\PbSocial\Service\Base\AbstractBaseService;


/***************************************************************
 *
 *  Copyright notice
 *
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

class FeedSyncService extends AbstractBaseService
{

    /**
     * @var \PlusB\PbSocial\Domain\Repository\ItemRepository
     * @inject
     */
    protected $itemRepository;

    /**
     * @var \PlusB\PbSocial\Service\CacheService
     * @inject
     */
    protected $cacheService;

    /**
     * @var \PlusB\PbSocial\Domain\Repository\CredentialRepository
     * @inject
     */
    protected $credentialRepository;


    /**
     * @param string $socialNetworkTypeString
     * @param array $flexformSettings Settings from flexform
     * @param integer $ttContentUid uid of plugin, for logging purpose - and for registering in cache identifier
     * @param integer $ttContenPid page uid in which plugin is located, for logging purpose, only
     * @param bool $isVerbose
     * @return string message
     */
    public function syncFeed(
        $socialNetworkTypeString,
        $flexformSettings,
        $ttContentUid,
        $ttContenPid,
        $isVerbose = false
    ){
        $flexformOptions = $this->convertFlexformSettings($flexformSettings);
        $message = "";

        switch ($socialNetworkTypeString){
            case self::TYPE_FACEBOOK:
                $message .= $this->syncFacebookFeed($socialNetworkTypeString, $flexformOptions, $ttContentUid, $ttContenPid, $isVerbose);
                break;
            case self::TYPE_INSTAGRAM:
                $message .= $this->syncInstagramFeed($socialNetworkTypeString, $flexformOptions, $ttContentUid, $ttContenPid, $isVerbose);
                break;
            case self::TYPE_LINKEDIN:
                $message .= $this->syncLinkedInFeed($socialNetworkTypeString, $flexformOptions, $ttContentUid, $ttContenPid, $isVerbose);
                break;
            case self::TYPE_PINTEREST:
                $message .= $this->syncPinterestFeed($socialNetworkTypeString, $flexformOptions, $ttContentUid, $ttContenPid, $isVerbose);
                break;
            case self::TYPE_TWITTER:
                $message .= $this->syncTwitterFeed($socialNetworkTypeString, $flexformOptions, $ttContentUid, $ttContenPid, $isVerbose);
                break;
            case self::TYPE_YOUTUBE:
                $message .= $this->syncYoutubeFeed($socialNetworkTypeString, $flexformOptions, $ttContentUid, $ttContenPid, $isVerbose);
                break;
            case self::TYPE_VIMEO:
                $message .= $this->syncVimeoFeed($socialNetworkTypeString, $flexformOptions, $ttContentUid, $ttContenPid, $isVerbose);
                break;
            case self::TYPE_TUMBLR:
                $message .= $this->syncTumblrFeed($socialNetworkTypeString, $flexformOptions, $ttContentUid, $ttContenPid, $isVerbose);
                break;
            case self::TYPE_IMGUR:
                $message .= $this->syncImgurFeed($socialNetworkTypeString, $flexformOptions, $ttContentUid, $ttContenPid, $isVerbose);
                break;
            case self::TYPE_TX_NEWS:
                $message .= $this->syncNewsFeed($socialNetworkTypeString, $flexformOptions, $ttContentUid, $ttContenPid, $isVerbose);
                break;
        }

        return $message;
    }


    /**
     * @param string $socialNetworkTypeString
     * @param object $flexformOptions converted flexform options by convertFlexformSettings()
     * @param integer $ttContentUid uid of plugin, for logging purpose - and for registering in cache identifier
     * @param integer $ttContentPid page uid in which plugin is located, for logging purpose, only
     * @param bool $isVerbose
     * @return string error message
     */
    public function syncFacebookFeed(
        $socialNetworkTypeString,
        $flexformOptions,
        $ttContentUid,
        $ttContentPid,
        $isVerbose = false
    ){
        $message = "";
        //facebook credentials - from extension manager globally, or from plugin overridden
        $config_apiId =
            ($flexformOptions->settings['facebookPluginKeyfieldEnabled'] === '1')
                ?
                $flexformOptions->settings['facebookApiId']
                :
                $this->extConf['socialfeed.']['facebook.']['api.']['id'];

        $config_apiSecret =
            ($flexformOptions->settings['facebookPluginKeyfieldEnabled'] === '1')
                ?
                $flexformOptions->settings['facebookApiSecret']
                :
                $this->extConf['socialfeed.']['facebook.']['api.']['secret'];

        try {
            //adapter
            $adapter = new Adapter\FacebookAdapter(
                $config_apiId,
                $config_apiSecret,
                $this->itemRepository,
                $flexformOptions,
                $ttContentUid,
                $ttContentPid,
                $this->cacheService->calculateCacheIdentifier($socialNetworkTypeString, $flexformOptions->settings, $ttContentUid)
            );
            // if you get here, all is fine and you can use object

            $message = $this->doRequestAndSetContentToCache(
                $adapter,
                $flexformOptions,
                $socialNetworkTypeString,
                $ttContentUid,
                $ttContentPid,
                $isVerbose
            );
        }
        catch( \Exception $e ) {
            // if you get here, something went terribly wrong.
            // also, object is undefined because the object was not created
            $message = $this->logError($e->getMessage(), $ttContentUid, $ttContentPid, $socialNetworkTypeString, $e->getCode());
        }

        return $message ."\n";
    }


    /**
     * @param string $socialNetworkTypeString
     * @param object $flexformOptions converted flexform options by convertFlexformSettings()
     * @param integer $ttContentUid uid of plugin, for logging purpose - and for registering in cache identifier
     * @param integer $ttContentPid page uid in which plugin is located, for logging purpose, only
     * @param bool $isVerbose
     * @return string message
     */
    public function syncInstagramFeed(
        $socialNetworkTypeString,
        $flexformOptions,
        $ttContentUid,
        $ttContentPid,
        $isVerbose = false
    ){

        $message = "";
        //facebook credentials - from extension manager globally, or from plugin overridden
        $config_clientId =
            ($flexformOptions->settings['instagramPluginKeyfieldEnabled'] === '1')
                ?
                $flexformOptions->settings['instagramClientId']
                :
                $this->extConf['socialfeed.']['instagram.']['client.']['id'];

        $config_clientSecret =
            ($flexformOptions->settings['instagramPluginKeyfieldEnabled'] === '1')
                ?
                $flexformOptions->settings['instagramClientSecret']
                :
                $this->extConf['socialfeed.']['instagram.']['client.']['secret'];

        $config_clientCallback =
            ($flexformOptions->settings['instagramPluginKeyfieldEnabled'] === '1')
                ?
                $flexformOptions->settings['instagramClientCallback']
                :
                $this->extConf['socialfeed.']['instagram.']['client.']['callback'];

        $config_access_code =
            ($flexformOptions->settings['instagramPluginKeyfieldEnabled'] === '1')
                ?
                $flexformOptions->settings['instagramClientAccess_code']
                :
                $this->extConf['socialfeed.']['instagram.']['client.']['access_code'];

        $config_access_token =
            ($flexformOptions->settings['instagramPluginKeyfieldEnabled'] === '1')
                ?
                $flexformOptions->settings['instagramClientAaccess_token']
                :
                $this->extConf['socialfeed.']['instagram.']['client.']['access_token'];

        $flexformOptions->instagramHashTags = $flexformOptions->settings['instagramHashTag'];
        $flexformOptions->instagramSearchIds = $flexformOptions->settings['instagramSearchIds'];
        $flexformOptions->instagramPostFilter = $flexformOptions->settings['instagramPostFilter'];

        try {
            # retrieve data from adapter #
            $adapter = new Adapter\InstagramAdapter(
                $config_clientId,
                $config_clientSecret,
                $config_clientCallback,
                $config_access_code,
                $config_access_token,
                $this->itemRepository,
                $flexformOptions,
                $ttContentUid,
                $ttContentPid,
                $this->cacheService->calculateCacheIdentifier($socialNetworkTypeString, $flexformOptions->settings, $ttContentUid));
            // if you get here, all is fine and you can use object
            $message = $this->doRequestAndSetContentToCache(
                $adapter,
                $flexformOptions,
                $socialNetworkTypeString,
                $ttContentUid,
                $ttContentPid,
                $isVerbose
            );
        }
        catch( \Exception $e ) {
            // if you get here, something went terribly wrong.
            // also, object is undefined because the object was not created
            $message = $this->logError($e->getMessage(), $ttContentUid, $ttContentPid, $socialNetworkTypeString, $e->getCode());
        }

        return $message ."\n";
    }

    /**
     * @param string $socialNetworkTypeString
     * @param object $flexformOptions converted flexform options by convertFlexformSettings()
     * @param integer $ttContentUid uid of plugin, for logging purpose - and for registering in cache identifier
     * @param integer $ttContentPid page uid in which plugin is located, for logging purpose, only
     * @param bool $isVerbose
     * @return string message
     */
    public function syncLinkedInFeed(
        $socialNetworkTypeString,
        $flexformOptions,
        $ttContentUid,
        $ttContentPid,
        $isVerbose = false
    ){
        $message = "";
        # check api key #

        #todo override
        $config_clientId = $this->extConf['socialfeed.']['linkedin.']['client.']['key'];
        $config_clientSecret = $this->extConf['socialfeed.']['linkedin.']['client.']['secret'];
        $config_clientCallback = $this->extConf['socialfeed.']['linkedin.']['client.']['callback_url'];
        $config_access_code = $this->extConf['socialfeed.']['linkedin.']['access_token'];
        $flexformOptions->companyIds = $flexformOptions->settings['linkedinCompanyIds'];
        $flexformOptions->linkedinFilterChoice = $flexformOptions->settings['linkedinFilterChoice'];

        try{
            # retrieve data from adapter #
            $adapter = new Adapter\LinkedInAdapter(
                $config_clientId,
                $config_clientSecret,
                $config_clientCallback,
                $config_access_code,
                $this->itemRepository,
                $this->credentialRepository,
                $flexformOptions,
                $ttContentUid,
                $ttContentPid,
                $this->cacheService->calculateCacheIdentifier($socialNetworkTypeString, $flexformOptions->settings, $ttContentUid)
            );
            $message = $this->doRequestAndSetContentToCache(
                $adapter,
                $flexformOptions,
                $socialNetworkTypeString,
                $ttContentUid,
                $ttContentPid,
                $isVerbose);

        }catch( \Exception $e ) {
            // if you get here, something went terribly wrong.
            // also, object is undefined because the object was not created
            $message = $this->logError($e->getMessage(), $ttContentUid, $ttContentPid, $socialNetworkTypeString, $e->getCode());
        }
        return $message ."\n";
    }

    /**
     * @param string $socialNetworkTypeString
     * @param object $flexformOptions converted flexform options by convertFlexformSettings()
     * @param integer $ttContentUid uid of plugin, for logging purpose - and for registering in cache identifier
     * @param integer $ttContentPid page uid in which plugin is located, for logging purpose, only
     * @param bool $isVerbose
     * @return string §message
     */
    public function syncPinterestFeed(
        $socialNetworkTypeString,
        $flexformOptions,
        $ttContentUid,
        $ttContentPid,
        $isVerbose = false
    ){
        $message = "";
        # check api key #
        #todo override
        $config_appId = $this->extConf['socialfeed.']['pinterest.']['app.']['id'];
        $config_appSecret = $this->extConf['socialfeed.']['pinterest.']['app.']['secret'];
        $config_accessCode = $this->extConf['socialfeed.']['pinterest.']['app.']['code'];
        $flexformOptions->pinterest_username = $flexformOptions->settings['username'];
        $flexformOptions->pinterest_boardname = $flexformOptions->settings['boardname'];

        try {
            # retrieve data from adapter #
            $adapter = new Adapter\PinterestAdapter(
                $config_appId,
                $config_appSecret,
                $config_accessCode,
                $this->itemRepository,
                $this->credentialRepository,
                $flexformOptions,
                $ttContentUid,
                $ttContentPid,
                $this->cacheService->calculateCacheIdentifier($socialNetworkTypeString, $flexformOptions->settings,
                    $ttContentUid)
            );
            // if you get here, all is fine and you can use object

            $message = $this->doRequestAndSetContentToCache(
                $adapter,
                $flexformOptions,
                $socialNetworkTypeString,
                $ttContentUid,
                $ttContentPid,
                $isVerbose
            );

        }
        catch( \Exception $e ) {
            // if you get here, something went terribly wrong.
            // also, object is undefined because the object was not created
            $message = $this->logError($e->getMessage(), $ttContentUid, $ttContentPid, $socialNetworkTypeString, $e->getCode());
        }

        return $message ."\n";
    }

    /**
     * @param string $socialNetworkTypeString
     * @param object $flexformOptions converted flexform options by convertFlexformSettings()
     * @param integer $ttContentUid uid of plugin, for logging purpose - and for registering in cache identifier
     * @param integer $ttContentPid page uid in which plugin is located, for logging purpose, only
     * @param bool $isVerbose
     * @return string message
     */
    public function syncTwitterFeed(
        $socialNetworkTypeString,
        $flexformOptions,
        $ttContentUid,
        $ttContentPid,
        $isVerbose = false
    ){

        $message = "";
        # check api key #
        #todo override
        $config_consumerKey = $this->extConf['socialfeed.']['twitter.']['consumer.']['key'];
        $config_consumerSecret = $this->extConf['socialfeed.']['twitter.']['consumer.']['secret'];
        $config_accessToken = $this->extConf['socialfeed.']['twitter.']['oauth.']['access.']['token'];
        $config_accessTokenSecret = $this->extConf['socialfeed.']['twitter.']['oauth.']['access.']['token_secret'];

        $flexformOptions->twitterSearchFieldValues = $flexformOptions->settings['twitterSearchFieldValues'];
        $flexformOptions->twitterProfilePosts = $flexformOptions->settings['twitterProfilePosts'];
        $flexformOptions->twitterLanguage = $flexformOptions->settings['twitterLanguage'];
        $flexformOptions->twitterGeoCode = $flexformOptions->settings['twitterGeoCode'];
        $flexformOptions->twitterHideRetweets = $flexformOptions->settings['twitterHideRetweets'];
        $flexformOptions->twitterShowOnlyImages = $flexformOptions->settings['twitterShowOnlyImages'];

        try {
            # retrieve data from adapter #
            $adapter = new Adapter\TwitterAdapter(
                $config_consumerKey,
                $config_consumerSecret,
                $config_accessToken,
                $config_accessTokenSecret,
                $this->itemRepository,
                $flexformOptions,
                $ttContentUid,
                $ttContentPid,
                $this->cacheService->calculateCacheIdentifier($socialNetworkTypeString, $flexformOptions->settings, $ttContentUid)
            );

            $message = $this->doRequestAndSetContentToCache(
                $adapter,
                $flexformOptions,
                $socialNetworkTypeString,
                $ttContentUid,
                $ttContentPid,
                $isVerbose
            );
        }
        catch( \Exception $e ) {
            // if you get here, something went terribly wrong.
            // also, object is undefined because the object was not created
            $message = $this->logError($e->getMessage(), $ttContentUid, $ttContentPid, $socialNetworkTypeString, $e->getCode());
        }

        return $message ."\n";
    }

    /**
     * @param string $socialNetworkTypeString
     * @param object $flexformOptions converted flexform options by convertFlexformSettings()
     * @param integer $ttContentUid uid of plugin, for logging purpose - and for registering in cache identifier
     * @param integer $ttContentPid page uid in which plugin is located, for logging purpose, only     
     * @param bool $isVerbose
     * @return string message
     */
    public function syncYoutubeFeed(
        $socialNetworkTypeString,
        $flexformOptions,
        $ttContentUid,
        $ttContentPid,
        $isVerbose = false
    ){
        $message = "";
        # check api key #
        $config_apiKey = $this->extConf['socialfeed.']['youtube.']['apikey'];
        $flexformOptions->youtubeSearch = $flexformOptions->settings['youtubeSearch'];
        $flexformOptions->youtubePlaylist = $flexformOptions->settings['youtubePlaylist'];
        $flexformOptions->youtubeChannel = $flexformOptions->settings['youtubeChannel'];
        $flexformOptions->youtubeType = $flexformOptions->settings['youtubeType'];
        $flexformOptions->youtubeLanguage = $flexformOptions->settings['youtubeLanguage'];
        $flexformOptions->youtubeOrder = $flexformOptions->settings['youtubeOrder'];

        try{
            # retrieve data from adapter #
            $adapter = new Adapter\YoutubeAdapter(
                $config_apiKey,
                $this->itemRepository,
                $flexformOptions,
                $ttContentUid,
                $ttContentPid,
                $this->cacheService->calculateCacheIdentifier($socialNetworkTypeString, $flexformOptions->settings, $ttContentUid)
            );

            $message = $this->doRequestAndSetContentToCache(
                $adapter,
                $flexformOptions,
                $socialNetworkTypeString,
                $ttContentUid,
                $ttContentPid,
                $isVerbose);
        }
        catch( \Exception $e ) {
            // if you get here, something went terribly wrong.
            // also, object is undefined because the object was not created
            $message = $this->logError($e->getMessage(), $ttContentUid, $ttContentPid, $socialNetworkTypeString, $e->getCode());
        }

        return $message ."\n";
    }

    /**
     * @param string $socialNetworkTypeString
     * @param object $flexformOptions converted flexform options by convertFlexformSettings()
     * @param integer $ttContentUid uid of plugin, for logging purpose - and for registering in cache identifier
     * @param integer $ttContentPid page uid in which plugin is located, for logging purpose, only
     * @param bool $isVerbose
     * @return string message
     */
    public function syncVimeoFeed(
        $socialNetworkTypeString,
        $flexformOptions,
        $ttContentUid,
        $ttContentPid,
        $isVerbose = false
    ){
        $message = "";
        # check api key #
        $config_clientIdentifier = $this->extConf['socialfeed.']['vimeo.']['client.']['identifier'];
        $config_clientSecret = $this->extConf['socialfeed.']['vimeo.']['client.']['secret'];
        $config_token = $this->extConf['socialfeed.']['vimeo.']['token'];

        /**
         * todo: vimeo Channel as member of flexformOptions - is not included if somebody enters $flexformOptions->settings (!) - we will change it (AM)
         */
        $flexformOptions->vimeoChannel = $flexformOptions->settings['vimeoChannel'];

        try{
            # retrieve data from adapter #
            $adapter = new Adapter\VimeoAdapter(
                $config_clientIdentifier,
                $config_clientSecret,
                $config_token,
                $this->itemRepository,
                $flexformOptions,
                $ttContentUid,
                $ttContentPid,
                $this->cacheService->calculateCacheIdentifier($socialNetworkTypeString, $flexformOptions->settings, $ttContentUid)
            );

            $message = $this->doRequestAndSetContentToCache(
                $adapter,
                $flexformOptions,
                $socialNetworkTypeString,
                $ttContentUid,
                $ttContentPid,
                $isVerbose
            );
        }
        catch( \Exception $e ) {
            // if you get here, something went terribly wrong.
            // also, object is undefined because the object was not created
            $message = $this->logError($e->getMessage(), $ttContentUid, $ttContentPid, $socialNetworkTypeString, $e->getCode());
        }

        return $message ."\n";
    }

    /**
     * @param string $socialNetworkTypeString
     * @param object $flexformOptions converted flexform options by convertFlexformSettings()
     * @param integer $ttContentUid uid of plugin, for logging purpose - and for registering in cache identifier
     * @param integer $ttContentPid page uid in which plugin is located, for logging purpose, only
     * @param bool $isVerbose
     * @return string message
     */
    public function syncTumblrFeed(
        $socialNetworkTypeString,
        $flexformOptions,
        $ttContentUid,
        $ttContentPid,
        $isVerbose = false
    ){
        $message = "";
        # check api key #
        $config_consumerKey = $this->extConf['socialfeed.']['tumblr.']['consumer.']['key'];
        $config_consumerSecret = $this->extConf['socialfeed.']['tumblr.']['consumer.']['secret'];
        $config_Token = $this->extConf['socialfeed.']['tumblr.']['token'];
        $config_TokenSecret = $this->extConf['socialfeed.']['tumblr.']['token_secret'];

        $flexformOptions->tumblrHashtag = strtolower(str_replace('#', '', $flexformOptions->settings['tumblrHashTag']));
        $flexformOptions->tumblrBlogNames = $flexformOptions->settings['tumblrBlogNames'];
        $flexformOptions->tumblrShowOnlyImages = $flexformOptions->settings['tumblrShowOnlyImages'];
        try {
            # retrieve data from adapter #
            $adapter = new Adapter\TumblrAdapter(
                $config_consumerKey,
                $config_consumerSecret,
                $config_Token,
                $config_TokenSecret,
                $this->itemRepository,
                $flexformOptions,
                $ttContentUid,
                $ttContentPid,
                $this->cacheService->calculateCacheIdentifier($socialNetworkTypeString, $flexformOptions->settings, $ttContentUid)
            );

            $message = $this->doRequestAndSetContentToCache(
                $adapter,
                $flexformOptions,
                $socialNetworkTypeString,
                $ttContentUid,
                $ttContentPid,
                $isVerbose
            );
        }
        catch( \Exception $e ) {
            // if you get here, something went terribly wrong.
            // also, object is undefined because the object was not created
            $message = $this->logError($e->getMessage(), $ttContentUid, $ttContentPid, $socialNetworkTypeString, $e->getCode());
        }

        return $message ."\n";
    }

    /**
     * @param string $socialNetworkTypeString
     * @param object $flexformOptions converted flexform options by convertFlexformSettings()
     * @param integer $ttContentUid uid of plugin, for logging purpose - and for registering in cache identifier
     * @param integer $ttContentPid page uid in which plugin is located, for logging purpose, only
     * @param bool $isVerbose
     * @return string message
     */
    public function syncImgurFeed(
        $socialNetworkTypeString,
        $flexformOptions,
        $ttContentUid,
        $ttContentPid,
        $isVerbose = false
    ){
        $message = "";
        //imgur credentials - from extension manager gobally, or from plugin overridden
        $config_apiId =
            ($flexformOptions->settings['imgurPluginKeyfieldEnabled'] === '1')
                ?
                $flexformOptions->settings['imgurClientId']
                :
                $this->extConf['socialfeed.']['imgur.']['client.']['id'];

        $config_apiSecret =
            ($flexformOptions->settings['imgurPluginKeyfieldEnabled'] === '1')
                ?
                $flexformOptions->settings['imgurClientSecret']
                :
                $this->extConf['socialfeed.']['imgur.']['client.']['secret'];

        $flexformOptions->imgSearchTags = $flexformOptions->settings['imgurTags'];
        // TODO: not yet implemented in backend configuration
        $flexformOptions->imgSearchUsers = $flexformOptions->settings['imgurUsers'];

        try{
            # retrieve data from adapter #
            $adapter = new Adapter\ImgurAdapter(
                $config_apiId,
                $config_apiSecret,
                $this->itemRepository,
                $flexformOptions,
                $ttContentUid,
                $ttContentPid,
                $this->cacheService->calculateCacheIdentifier($socialNetworkTypeString, $flexformOptions->settings, $ttContentUid)
            );
            // if you get here, all is fine and you can use object

            $message = $this->doRequestAndSetContentToCache(
                $adapter,
                $flexformOptions,
                $socialNetworkTypeString,
                $ttContentUid,
                $ttContentPid,
                $isVerbose
            );
        }
        catch( \Exception $e ) {
            // if you get here, something went terribly wrong.
            // also, object is undefined because the object was not created
            $message = $this->logError($e->getMessage(), $ttContentUid, $ttContentPid, $socialNetworkTypeString, $e->getCode());
        }

        return $message ."\n";
    }

    /**
     * @param string $socialNetworkTypeString
     * @param object $flexformOptions converted flexform options by convertFlexformSettings()
     * @param integer $ttContentUid uid of plugin, for logging purpose - and for registering in cache identifier
     * @param integer $ttContentPid page uid in which plugin is located, for logging purpose, only
     * @param bool $isVerbose
     * @return string message
     */
    public function syncNewsFeed(
        $socialNetworkTypeString,
        $flexformOptions,
        $ttContentUid,
        $ttContentPid,
        $isVerbose = false
    ){


        $flexformOptions->newsCategories = $flexformOptions->settings['newsCategories'];
        $flexformOptions->newsDetailPageUid =$flexformOptions->settings['newsDetailPageUid'];
        if ($flexformOptions->settings['useHttpsLinks']) $flexformOptions->useHttps = true;
        try {
            # retrieve data from adapter #
            $adapter = new Adapter\TxNewsAdapter(
                new \GeorgRinger\News\Domain\Model\Dto\NewsDemand(),
                $this->itemRepository,
                $flexformOptions,
                $ttContentUid,
                $ttContentPid,
                $this->cacheService->calculateCacheIdentifier($socialNetworkTypeString, $flexformOptions->settings, $ttContentUid)

            );

            $this->doRequestAndSetContentToCache(
                $adapter,
                $flexformOptions,
                $socialNetworkTypeString,
                $ttContentUid,
                $ttContentPid,
                $isVerbose
            );
        }
        catch( \Exception $e ) {
            // if you get here, something went terribly wrong.
            // also, object is undefined because the object was not created
            $message = $this->logError($e->getMessage(), $ttContentUid, $ttContentPid, $socialNetworkTypeString, $e->getCode());
        }

        return $message ."\n";
    }


    /**
     * setResultToCache gets Result from Api setting it to caching framework
     *
     * @param object $adapterObj Object reference of adapter
     * @param mixed $flexformOptions Object of option settings  of specific adapter
     * @param string $socialNetworkTypeString String name of social network, set from class constant
     * @param integer $ttContentUid int uid of plugin, for logging purpose - and for registering in cache identifier
     * @param integer $ttContentPid int page uid in which plugin is located, for logging purpose, only
     * @param bool $isVerbose bool for verbose mode in command line
     * @return string message
     */
    private function doRequestAndSetContentToCache(
        $adapterObj,
        $flexformOptions,
        $socialNetworkTypeString,
        $ttContentUid,
        $ttContentPid,
        $isVerbose = false
    ){
        $message = "";
        try {

            $content = $adapterObj->getResultFromApi();

            //writing to cache
            $this->cacheService->setCacheContent(
                $socialNetworkTypeString, $flexformOptions->settings, $ttContentUid, $content
            );

            $message =  $this->logInfo("update feed successfull" . ($isVerbose?"\n". var_export($content, true):""),
                $ttContentUid, $ttContentPid, $socialNetworkTypeString, 1558441967);

        } catch (\Exception $e) {
            $message =  $this->logError($e->getMessage(), $ttContentUid, $ttContentPid, $socialNetworkTypeString, $e->getCode() );
        }
        return $message;
    }

}