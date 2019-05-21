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

    const TYPE_FACEBOOK = 'facebook';
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
     * @var \PlusB\PbSocial\Service\OptionService
     * @inject
     */
    protected $optionService;

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
     */
    public function syncFeed(
        $socialNetworkTypeString,
        $flexformSettings,
        $ttContentUid,
        $ttContenPid,
        $isVerbose = false
    ){

        $flexformOptions = $this->optionService->convertFlexformSettings($flexformSettings);

        switch ($socialNetworkTypeString){
            case self::TYPE_FACEBOOK:
                $this->syncFacebookFeed($socialNetworkTypeString, $flexformOptions, $ttContentUid, $ttContenPid, $isVerbose);
                break;
            case self::TYPE_INSTAGRAM:
                $return = $this->syncInstagramFeed($socialNetworkTypeString, $flexformOptions, $ttContentUid, $ttContenPid, $isVerbose);
                break;





            case self::TYPE_LINKEDIN:
                $return = $this->syncLinkedInFeed($socialNetworkTypeString, $flexformOptions, $ttContentUid, $ttContenPid, $return, $isVerbose);
                break;
            case self::TYPE_PINTEREST:
                $return = $this->syncPinterestFeed($socialNetworkTypeString, $flexformOptions, $ttContentUid, $ttContenPid, $return, $isVerbose);
                break;
            case self::TYPE_TUMBLR:
                $return = $this->syncTumblrFeed($socialNetworkTypeString, $flexformOptions, $ttContentUid, $ttContenPid, $return, $isVerbose);
                break;
            case self::TYPE_TWITTER:
                $return = $this->syncTwitterFeed($socialNetworkTypeString, $flexformOptions, $ttContentUid, $ttContenPid, $return, $isVerbose);
                break;
            case self::TYPE_YOUTUBE:
                $return = $this->syncYoutubeFeed($socialNetworkTypeString, $flexformOptions, $ttContentUid, $ttContenPid, $return, $isVerbose);
                break;
            case self::TYPE_VIMEO:
                $return = $this->syncVimeoFeed($socialNetworkTypeString, $flexformOptions, $ttContentUid, $ttContenPid, $return, $isVerbose);
                break;

            case self::TYPE_IMGUR:
                $return = $this->syncImgurFeed($socialNetworkTypeString, $flexformOptions, $ttContentUid, $ttContenPid, $return, $isVerbose);
                break;

            case self::TYPE_TX_NEWS:
                $return = $this->syncNewsFeed($socialNetworkTypeString, $flexformOptions, $ttContentUid, $ttContenPid, $return, $isVerbose);
                break;
        }
    }


    /**
     * @param string $socialNetworkTypeString
     * @param object $flexformOptions converted flexform options by convertFlexformSettings()
     * @param integer $ttContentUid uid of plugin, for logging purpose - and for registering in cache identifier
     * @param integer $ttContentPid page uid in which plugin is located, for logging purpose, only
     * @param bool $isVerbose
     */
    public function syncFacebookFeed(
        $socialNetworkTypeString,
        $flexformOptions,
        $ttContentUid,
        $ttContentPid,
        $isVerbose = false
    ){

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
                $config_apiId, $config_apiSecret, $this->itemRepository, $flexformOptions,
                $ttContentUid,
                $ttContentPid,
                $this->cacheService->calculateCacheIdentifier($socialNetworkTypeString, $flexformOptions->settings, $ttContentUid)
            );
            // if you get here, all is fine and you can use object

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
            $this->logError($e->getMessage(), $ttContentUid, $ttContentPid, self::TYPE_FACEBOOK, 1558101905);
        }
    }


    /**
     * @param string $socialNetworkTypeString
     * @param object $flexformOptions converted flexform options by convertFlexformSettings()
     * @param integer $ttContentUid uid of plugin, for logging purpose - and for registering in cache identifier
     * @param integer $ttContentPid page uid in which plugin is located, for logging purpose, only
     * @param object $return
     * @param bool $isVerbose
     * @return object of message->isSuccessfull and message->message
     */
    public function syncInstagramFeed(
        $socialNetworkTypeString,
        $flexformOptions,
        $ttContentUid,
        $ttContentPid,
        $return,
        $isVerbose = false
    ){

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
            $return = $this->doRequestAndSetContentToCache(
                $adapter,
                $flexformOptions,
                $socialNetworkTypeString,
                $ttContentUid,
                $ttContentPid,
                $return,
                $isVerbose
            );
        }
        catch( \Exception $e ) {
            // if you get here, something went terribly wrong.
            // also, object is undefined because the object was not created
            $return->message = "flexform $ttContentUid on page $ttContentPid tab ".self::TYPE_FACEBOOK. ": 1558354940 " . $e->getMessage();
        }
        return $return;
    }

    /**
     * @param string $socialNetworkTypeString
     * @param object $flexformOptions converted flexform options by convertFlexformSettings()
     * @param integer $ttContentUid uid of plugin, for logging purpose - and for registering in cache identifier
     * @param integer $ttContentPid page uid in which plugin is located, for logging purpose, only
     * @param object $return
     * @param bool $isVerbose
     * @return object of message->isSuccessfull and message->message
     */
    public function syncPinterestFeed(
        $socialNetworkTypeString,
        $flexformOptions,
        $ttContentUid,
        $ttContentPid,
        $return,
        $isVerbose = false
    ){

        # check api key #
        $config_appId = $this->extConf['socialfeed.']['pinterest.']['app.']['id'];
        $config_appSecret = $this->extConf['socialfeed.']['pinterest.']['app.']['secret'];
        $config_accessCode = $this->extConf['socialfeed.']['pinterest.']['app.']['code'];
        $flexformOptions->pinterest_username = $flexformSettings['username'];
        $flexformOptions->pinterest_boardname = $flexformSettings['boardname'];

        # retrieve data from adapter #
        $adapter = new Adapter\PinterestAdapter($config_appId, $config_appSecret, $config_accessCode, $this->itemRepository, $this->credentialRepository, $flexformOptions);

        if($adapter->isValid === true){
            $return = $this->doRequestAndSetContentToCache($adapter, $flexformOptions, $socialNetworkTypeString,
                $ttContentUid, $ttContentPid, $return, $isVerbose);
        }else{
            $return->message = $adapter->validationMessage;
        }

        return $return;
    }

    /**
     * @param string $socialNetworkTypeString
     * @param object $flexformOptions converted flexform options by convertFlexformSettings()
     * @param integer $ttContentUid uid of plugin, for logging purpose - and for registering in cache identifier
     * @param integer $ttContentPid page uid in which plugin is located, for logging purpose, only
     * @param object $return
     * @param bool $isVerbose
     * @return object of message->isSuccessfull and message->message
     */
    public function syncLinkedInFeed(
        $socialNetworkTypeString,
        $flexformOptions,
        $ttContentUid,
        $ttContentPid,
        $return,
        $isVerbose = false
    ){

        # check api key #
        $config_clientId = $this->extConf['socialfeed.']['linkedin.']['client.']['key'];
        $config_clientSecret = $this->extConf['socialfeed.']['linkedin.']['client.']['secret'];
        $config_clientCallback = $this->extConf['socialfeed.']['linkedin.']['client.']['callback_url'];
        $config_access_code = $this->extConf['socialfeed.']['linkedin.']['access_token'];
        $flexformOptions->companyIds = $flexformSettings['linkedinCompanyIds'];
        $flexformOptions->linkedinFilterChoice = $flexformSettings['linkedinFilterChoice'];


        # retrieve data from adapter #
        $adapter = new Adapter\LinkedInAdapter($config_clientId, $config_clientSecret, $config_clientCallback, $config_access_code, $this->itemRepository, $this->credentialRepository, $flexformOptions);

        if($adapter->isValid === true){
            $return = $this->doRequestAndSetContentToCache($adapter, $flexformOptions, $socialNetworkTypeString,
                $ttContentUid, $ttContentPid, $return, $isVerbose);
        }else{
            $return->message = $adapter->validationMessage;
        }

        return $return;
    }

    /**
     * @param string $socialNetworkTypeString
     * @param object $flexformOptions converted flexform options by convertFlexformSettings()
     * @param integer $ttContentUid uid of plugin, for logging purpose - and for registering in cache identifier
     * @param integer $ttContentPid page uid in which plugin is located, for logging purpose, only
     * @param object $return
     * @param bool $isVerbose
     * @return object of message->isSuccessfull and message->message
     */
    public function syncTumblrFeed(
        $socialNetworkTypeString,
        $flexformOptions,
        $ttContentUid,
        $ttContentPid,
        $return,
        $isVerbose = false
    ){

        # check api key #
        $config_consumerKey = $this->extConf['socialfeed.']['tumblr.']['consumer.']['key'];
        $config_consumerSecret = $this->extConf['socialfeed.']['tumblr.']['consumer.']['secret'];
        $config_Token = $this->extConf['socialfeed.']['tumblr.']['token'];
        $config_TokenSecret = $this->extConf['socialfeed.']['tumblr.']['token_secret'];

        $flexformOptions->tumblrHashtag = strtolower(str_replace('#', '', $flexformSettings['tumblrHashTag']));
        $flexformOptions->tumblrBlogNames = $flexformSettings['tumblrBlogNames'];
        $flexformOptions->tumblrShowOnlyImages = $flexformSettings['tumblrShowOnlyImages'];

        # retrieve data from adapter #
        $adapter = new Adapter\TumblrAdapter($config_consumerKey, $config_consumerSecret, $config_Token, $config_TokenSecret, $this->itemRepository, $flexformOptions);

        if($adapter->isValid === true){
            $return = $this->doRequestAndSetContentToCache($adapter, $flexformOptions, $socialNetworkTypeString,
                $ttContentUid, $ttContentPid, $return, $isVerbose);
        }else{
            $return->message = $adapter->validationMessage;
        }

        return $return;
    }

    /**
     * @param string $socialNetworkTypeString
     * @param object $flexformOptions converted flexform options by convertFlexformSettings()
     * @param integer $ttContentUid uid of plugin, for logging purpose - and for registering in cache identifier
     * @param integer $ttContentPid page uid in which plugin is located, for logging purpose, only
     * @param object $return
     * @param bool $isVerbose
     * @return object of message->isSuccessfull and message->message
     */
    public function syncTwitterFeed(
        $socialNetworkTypeString,
        $flexformOptions,
        $ttContentUid,
        $ttContentPid,
        $return,
        $isVerbose = false
    ){


        # check api key #
        $config_consumerKey = $this->extConf['socialfeed.']['twitter.']['consumer.']['key'];
        $config_consumerSecret = $this->extConf['socialfeed.']['twitter.']['consumer.']['secret'];
        $config_accessToken = $this->extConf['socialfeed.']['twitter.']['oauth.']['access.']['token'];
        $config_accessTokenSecret = $this->extConf['socialfeed.']['twitter.']['oauth.']['access.']['token_secret'];

        $flexformOptions->twitterSearchFieldValues = $flexformSettings['twitterSearchFieldValues'];
        $flexformOptions->twitterProfilePosts = $flexformSettings['twitterProfilePosts'];
        $flexformOptions->twitterLanguage = $flexformSettings['twitterLanguage'];
        $flexformOptions->twitterGeoCode = $flexformSettings['twitterGeoCode'];
        $flexformOptions->twitterHideRetweets = $flexformSettings['twitterHideRetweets'];
        $flexformOptions->twitterShowOnlyImages = $flexformSettings['twitterShowOnlyImages'];

        # retrieve data from adapter #
        $adapter = new Adapter\TwitterAdapter($config_consumerKey, $config_consumerSecret, $config_accessToken, $config_accessTokenSecret, $this->itemRepository, $flexformOptions);

        if($adapter->isValid === true){
            $return = $this->doRequestAndSetContentToCache($adapter, $flexformOptions, $socialNetworkTypeString,
                $ttContentUid, $ttContentPid, $return, $isVerbose);
        }else{
            $return->message = $adapter->validationMessage;
        }

        return $return;
    }

    /**
     * @param string $socialNetworkTypeString
     * @param object $flexformOptions converted flexform options by convertFlexformSettings()
     * @param integer $ttContentUid uid of plugin, for logging purpose - and for registering in cache identifier
     * @param integer $ttContentPid page uid in which plugin is located, for logging purpose, only
     * @param object $return
     * @param bool $isVerbose
     * @return object of message->isSuccessfull and message->message
     */
    public function syncYoutubeFeed(
        $socialNetworkTypeString,
        $flexformOptions,
        $ttContentUid,
        $ttContentPid,
        $return,
        $isVerbose = false
    ){

        # check api key #
        $config_apiKey = $this->extConf['socialfeed.']['youtube.']['apikey'];
        $flexformOptions->youtubeSearch = $flexformSettings['youtubeSearch'];
        $flexformOptions->youtubePlaylist = $flexformSettings['youtubePlaylist'];
        $flexformOptions->youtubeChannel = $flexformSettings['youtubeChannel'];
        $flexformOptions->youtubeType = $flexformSettings['youtubeType'];
        $flexformOptions->youtubeLanguage = $flexformSettings['youtubeLanguage'];
        $flexformOptions->youtubeOrder = $flexformSettings['youtubeOrder'];

        # retrieve data from adapter #
        $adapter = new Adapter\YoutubeAdapter($config_apiKey, $this->itemRepository, $flexformOptions);

        if($adapter->isValid === true){
            $return = $this->doRequestAndSetContentToCache($adapter, $flexformOptions, $socialNetworkTypeString,
                $ttContentUid, $ttContentPid, $return, $isVerbose);
        }else{
            $return->message = $adapter->validationMessage;
        }

        return $return;
    }

    /**
     * @param string $socialNetworkTypeString
     * @param object $flexformOptions converted flexform options by convertFlexformSettings()
     * @param integer $ttContentUid uid of plugin, for logging purpose - and for registering in cache identifier
     * @param integer $ttContentPid page uid in which plugin is located, for logging purpose, only
     * @param object $return
     * @param bool $isVerbose
     * @return object of message->isSuccessfull and message->message
     */
    public function syncVimeoFeed(
        $socialNetworkTypeString,
        $flexformOptions,
        $ttContentUid,
        $ttContentPid,
        $return,
        $isVerbose = false
    ){

        # check api key #
        $config_clientIdentifier = $this->extConf['socialfeed.']['vimeo.']['client.']['identifier'];
        $config_clientSecret = $this->extConf['socialfeed.']['vimeo.']['client.']['secret'];
        $config_token = $this->extConf['socialfeed.']['vimeo.']['token'];

        /**
         * todo: vimeo Channel as member of flexformOptions - is not included if somebody enters $flexformOptions->settings (!) - we will change it (AM)
         */
        $flexformOptions->vimeoChannel = $flexformSettings['vimeoChannel'];

        # retrieve data from adapter #
        $adapter = new Adapter\VimeoAdapter($config_clientIdentifier, $config_clientSecret, $config_token, $this->itemRepository, $flexformOptions);

        if($adapter->isValid === true){
            $return = $this->doRequestAndSetContentToCache($adapter, $flexformOptions, $socialNetworkTypeString,
                $ttContentUid, $ttContentPid, $return, $isVerbose);
        }else{
            $return->message = $adapter->validationMessage;
        }

        return $return;
    }

    /**
     * @param string $socialNetworkTypeString
     * @param object $flexformOptions converted flexform options by convertFlexformSettings()
     * @param integer $ttContentUid uid of plugin, for logging purpose - and for registering in cache identifier
     * @param integer $ttContentPid page uid in which plugin is located, for logging purpose, only
     * @param object $return
     * @param bool $isVerbose
     * @return object of message->isSuccessfull and message->message
     */
    public function syncImgurFeed(
        $socialNetworkTypeString,
        $flexformOptions,
        $ttContentUid,
        $ttContentPid,
        $return,
        $isVerbose = false
    ){

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
            $adapter = new Adapter\ImgurAdapter($config_apiId, $config_apiSecret, $this->itemRepository, $flexformOptions,
                $this->cacheService->calculateCacheIdentifier($socialNetworkTypeString, $flexformOptions->settings, $ttContentUid));
            // if you get here, all is fine and you can use object

            $return = $this->doRequestAndSetContentToCache(
                $adapter,
                $flexformOptions,
                $socialNetworkTypeString,
                $ttContentUid,
                $ttContentPid,
                $return,
                $isVerbose
            );
        }
        catch( \Exception $e ) {
            // if you get here, something went terribly wrong.
            // also, object is undefined because the object was not created
            $return->message = "imgur $ttContentUid on page $ttContentPid tab ".self::TYPE_FACEBOOK. ": 1558348310 " . $e->getMessage();
        }


        return $return;
    }

    /**
     * @param string $socialNetworkTypeString
     * @param object $flexformOptions converted flexform options by convertFlexformSettings()
     * @param integer $ttContentUid uid of plugin, for logging purpose - and for registering in cache identifier
     * @param integer $ttContentPid page uid in which plugin is located, for logging purpose, only
     * @param object $return
     * @param bool $isVerbose
     * @return object of message->isSuccessfull and message->message
     */
    public function syncNewsFeed(
        $socialNetworkTypeString,
        $flexformOptions,
        $ttContentUid,
        $ttContentPid,
        $return,
        $isVerbose = false
    ){


        $flexformOptions->newsCategories = $flexformSettings['newsCategories'];
        $flexformOptions->newsDetailPageUid = $flexformSettings['newsDetailPageUid'];
        if ($flexformSettings['useHttpsLinks']) $flexformOptions->useHttps = true;

        # retrieve data from adapter #
        $adapter = new Adapter\TxNewsAdapter(new \GeorgRinger\News\Domain\Model\Dto\NewsDemand(), $this->itemRepository, $flexformOptions);

        if($adapter->isValid === true){
            $return = $this->doRequestAndSetContentToCache($adapter, $flexformOptions, $socialNetworkTypeString,
                $ttContentUid, $ttContentPid, $return, $isVerbose);
        }else{
            $return->message = $adapter->validationMessage;
        }

        return $return;
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
     */
    private function doRequestAndSetContentToCache(
        $adapterObj,
        $flexformOptions,
        $socialNetworkTypeString,
        $ttContentUid,
        $ttContentPid,
        $isVerbose = false
    ){
        try {

            $content = $adapterObj->getResultFromApi();

            //writing to cache
            $this->cacheService->setCacheContent(
                $socialNetworkTypeString, $flexformOptions->settings, $ttContentUid, $content
            );

            $this->logInfo("update feed successfull" . ($isVerbose?"\n". var_export($content, true):""),
                $ttContentUid, $ttContentPid, $socialNetworkTypeString, 1558441967);

        } catch (\Exception $e) {
            $this->logError($e->getMessage(), $ttContentUid, $ttContentPid, $socialNetworkTypeString, 1558441962);
        }
    }

}