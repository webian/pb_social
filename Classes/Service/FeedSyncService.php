<?php

namespace PlusB\PbSocial\Service;

use PlusB\PbSocial\Adapter;
use PlusB\PbSocial\Service\Base\AbstractBaseService;


/***************************************************************
 *
 *  Copyright notice
 *
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

class FeedSyncService extends AbstractBaseService
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
     * @param $socialNetworkTypeString
     * @param $flexformSettings
     * @param $ttContentUid
     * @param bool $isVerbose
     * @return object of message->isSuccessfull and message->message
     */
    public function syncFeed($socialNetworkTypeString, $flexformSettings, $ttContentUid, $isVerbose = false){
        $return = (object)array();

        switch ($socialNetworkTypeString){
            case self::TYPE_FACEBOOK:
                $return = $this->syncFacebookFeed($flexformSettings, $socialNetworkTypeString, $ttContentUid, $isVerbose);
                break;
            case self::TYPE_IMGUR:
                $return = $this->syncImgurFeed($flexformSettings, $socialNetworkTypeString, $ttContentUid, $isVerbose);
                break;
            case self::TYPE_INSTAGRAM:
                $return = $this->syncInstagramFeed($flexformSettings, $socialNetworkTypeString, $ttContentUid, $isVerbose);
                break;
            case self::TYPE_LINKEDIN:
                $return = $this->syncLinkedInFeed($flexformSettings, $socialNetworkTypeString, $ttContentUid, $isVerbose);
                break;
            case self::TYPE_PINTEREST:
                $return = $this->syncPinterestFeed($flexformSettings, $socialNetworkTypeString, $ttContentUid, $isVerbose);
                break;
            case self::TYPE_TUMBLR:
                $return = $this->syncTumblrFeed($flexformSettings, $socialNetworkTypeString, $ttContentUid, $isVerbose);
                break;
            case self::TYPE_TWITTER:
                $return = $this->syncTwitterFeed($flexformSettings, $socialNetworkTypeString, $ttContentUid, $isVerbose);
                break;
            case self::TYPE_YOUTUBE:
                $return = $this->syncYoutubeFeed($flexformSettings, $socialNetworkTypeString, $ttContentUid, $isVerbose);
                break;
            case self::TYPE_VIMEO:
                $return = $this->syncVimeoFeed($flexformSettings, $socialNetworkTypeString, $ttContentUid, $isVerbose);
                break;
            case self::TYPE_TX_NEWS:
                $return = $this->syncNewsFeed($flexformSettings, $socialNetworkTypeString, $ttContentUid, $isVerbose);
                break;
        }

        return $return;
    }


    /**
     * @param $flexformSettings
     * @param $socialNetworkTypeString
     * @param $ttContentUid
     * @param bool $isVerbose
     * @return object of message->isSuccessfull and message->message
     */
    public function syncFacebookFeed($flexformSettings, $socialNetworkTypeString, $ttContentUid, $isVerbose = false){
        $return = (object)array();
        $return->isSuccessfull = false;
        $return->message = "";

        $flexformOptions = $this->optionService->convertFlexformSettings($flexformSettings);
        $flexformOptions->devMod = $this->extConf['socialfeed.']['devmod'];

        //api key
        $config_apiId = $this->extConf['socialfeed.']['facebook.']['api.']['id'];
        $config_apiSecret = $this->extConf['socialfeed.']['facebook.']['api.']['secret'];

        //adapter
        $adapter = new Adapter\FacebookAdapter($config_apiId, $config_apiSecret, $this->itemRepository, $flexformOptions);
        if($adapter->isValid === true){
            $return = $this->doRequestAndSetContentToCache($adapter, $flexformOptions, $socialNetworkTypeString,$ttContentUid, $return, $isVerbose);
        }else{
            $return->message = $adapter->validationMessage;
        }

        return $return;
    }

    /**
     * @param $flexformSettings
     * @param $socialNetworkTypeString
     * @param $ttContentUid
     * @param bool $isVerbose
     * @return object of message->isSuccessfull and message->message
     */
    public function syncImgurFeed($flexformSettings, $socialNetworkTypeString, $ttContentUid, $isVerbose = false){
        $return = (object)array();
        $return->isSuccessfull = false;
        $return->message = "";

        $flexformOptions = $this->optionService->convertFlexformSettings($flexformSettings);
        $flexformOptions->devMod = $this->extConf['socialfeed.']['devmod'];

        # check api key #
        $config_apiId = $this->extConf['socialfeed.']['imgur.']['client.']['id'];
        $config_apiSecret = $this->extConf['socialfeed.']['imgur.']['client.']['secret'];
        $flexformOptions->imgSearchTags = $flexformSettings['imgurTags'];

        // TODO: not yet implemented in backend configuration
        $flexformOptions->imgSearchUsers = $flexformSettings['imgurUsers'];

        # retrieve data from adapter #
        $adapter = new Adapter\ImgurAdapter($config_apiId, $config_apiSecret, $this->itemRepository, $flexformOptions);

        if($adapter->isValid === true){
            $return = $this->doRequestAndSetContentToCache($adapter, $flexformOptions, $socialNetworkTypeString ,$ttContentUid, $return, $isVerbose);
        }else{
            $return->message = $adapter->validationMessage;
        }

        return $return;
    }

    /**
     * @param $flexformSettings
     * @param $socialNetworkTypeString
     * @param $ttContentUid
     * @param bool $isVerbose
     * @return object of message->isSuccessfull and message->message
     */
    public function syncInstagramFeed($flexformSettings, $socialNetworkTypeString, $ttContentUid, $isVerbose = false){
        $return = (object)array();
        $return->isSuccessfull = false;
        $return->message = "";

        $flexformOptions = $this->optionService->convertFlexformSettings($flexformSettings);
        $flexformOptions->devMod = $this->extConf['socialfeed.']['devmod'];

        # check api key #
        $config_clientId = $this->extConf['socialfeed.']['instagram.']['client.']['id'];
        $config_clientSecret = $this->extConf['socialfeed.']['instagram.']['client.']['secret'];
        $config_clientCallback = $this->extConf['socialfeed.']['instagram.']['client.']['callback'];
        $config_access_code = $this->extConf['socialfeed.']['instagram.']['client.']['access_code'];
        $flexformOptions->instagramHashTags = $flexformSettings['instagramHashTag'];
        $flexformOptions->instagramSearchIds = $flexformSettings['instagramSearchIds'];
        $flexformOptions->instagramPostFilter = $flexformSettings['instagramPostFilter'];


        # retrieve data from adapter #
        $adapter = new Adapter\InstagramAdapter($config_clientId, $config_clientSecret, $config_clientCallback, $config_access_code, $this->itemRepository, $this->credentialRepository, $flexformOptions);

        if($adapter->isValid === true){
            $return = $this->doRequestAndSetContentToCache($adapter, $flexformOptions, $socialNetworkTypeString ,$ttContentUid, $return, $isVerbose);
        }else{
            $return->message = $adapter->validationMessage;
        }

        return $return;
    }

    /**
     * @param $flexformSettings
     * @param $socialNetworkTypeString
     * @param $ttContentUid
     * @param bool $isVerbose
     * @return object of message->isSuccessfull and message->message
     */
    public function syncPinterestFeed($flexformSettings, $socialNetworkTypeString, $ttContentUid, $isVerbose = false){
        $return = (object)array();
        $return->isSuccessfull = false;
        $return->message = "";

        $flexformOptions = $this->optionService->convertFlexformSettings($flexformSettings);
        $flexformOptions->devMod = $this->extConf['socialfeed.']['devmod'];

        # check api key #
        $config_appId = $this->extConf['socialfeed.']['pinterest.']['app.']['id'];
        $config_appSecret = $this->extConf['socialfeed.']['pinterest.']['app.']['secret'];
        $config_accessCode = $this->extConf['socialfeed.']['pinterest.']['app.']['code'];
        $flexformOptions->pinterest_username = $flexformSettings['username'];
        $flexformOptions->pinterest_boardname = $flexformSettings['boardname'];

        # retrieve data from adapter #
        $adapter = new Adapter\PinterestAdapter($config_appId, $config_appSecret, $config_accessCode, $this->itemRepository, $this->credentialRepository, $flexformOptions);

        if($adapter->isValid === true){
            $return = $this->doRequestAndSetContentToCache($adapter, $flexformOptions, $socialNetworkTypeString ,$ttContentUid, $return, $isVerbose);
        }else{
            $return->message = $adapter->validationMessage;
        }

        return $return;
    }

    /**
     * @param $flexformSettings array
     * @param $socialNetworkTypeString string
     * @param $ttContentUid int
     * @param bool $isVerbose
     * @return object of message->isSuccessfull and message->message
     */
    public function syncLinkedInFeed($flexformSettings, $socialNetworkTypeString, $ttContentUid, $isVerbose = false){
        $return = (object)array();
        $return->isSuccessfull = false;
        $return->message = "";

        $flexformOptions = $this->optionService->convertFlexformSettings($flexformSettings);
        $flexformOptions->devMod = $this->extConf['socialfeed.']['devmod'];

        # check api key #
        $config_clientId = $this->extConf['socialfeed.']['linkedin.']['client.']['key'];
        $config_clientSecret = $this->extConf['socialfeed.']['linkedin.']['client.']['secret'];
        $config_clientCallback = $this->extConf['socialfeed.']['linkedin.']['client.']['callback_url'];
        $config_access_code = $this->extConf['socialfeed.']['linkedin.']['access_token'];
        $flexformOptions->companyIds = $flexformSettings['linkedinCompanyIds'];
        $flexformOptions->showJobPostings = $flexformSettings['linkedinJobPostings'];
        $flexformOptions->showNewProducts = $flexformSettings['linkedinNewProducts'];
        $flexformOptions->showStatusUpdates = $flexformSettings['linkedinStatusUpdates'];

        # retrieve data from adapter #
        $adapter = new Adapter\LinkedInAdapter($config_clientId, $config_clientSecret, $config_clientCallback, $config_access_code, $this->itemRepository, $this->credentialRepository, $flexformOptions);

        if($adapter->isValid === true){
            $return = $this->doRequestAndSetContentToCache($adapter, $flexformOptions, $socialNetworkTypeString ,$ttContentUid, $return, $isVerbose);
        }else{
            $return->message = $adapter->validationMessage;
        }

        return $return;
    }

    /**
     * @param $flexformSettings
     * @param $socialNetworkTypeString
     * @param $ttContentUid
     * @param bool $isVerbose
     * @return object of message->isSuccessfull and message->message
     */
    public function syncTumblrFeed($flexformSettings, $socialNetworkTypeString, $ttContentUid, $isVerbose = false){
        $return = (object)array();
        $return->isSuccessfull = false;
        $return->message = "";

        $flexformOptions = $this->optionService->convertFlexformSettings($flexformSettings);
        $flexformOptions->devMod = $this->extConf['socialfeed.']['devmod'];

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
            $return = $this->doRequestAndSetContentToCache($adapter, $flexformOptions, $socialNetworkTypeString ,$ttContentUid, $return, $isVerbose);
        }else{
            $return->message = $adapter->validationMessage;
        }

        return $return;
    }

    /**
     * @param $flexformSettings
     * @param $socialNetworkTypeString
     * @param $ttContentUid
     * @param bool $isVerbose
     * @return object of message->isSuccessfull and message->message
     */
    public function syncTwitterFeed($flexformSettings, $socialNetworkTypeString, $ttContentUid, $isVerbose = false){
        $return = (object)array();
        $return->isSuccessfull = false;
        $return->message = "";

        $flexformOptions = $this->optionService->convertFlexformSettings($flexformSettings);
        $flexformOptions->devMod = $this->extConf['socialfeed.']['devmod'];

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
            $return = $this->doRequestAndSetContentToCache($adapter, $flexformOptions, $socialNetworkTypeString ,$ttContentUid, $return, $isVerbose);
        }else{
            $return->message = $adapter->validationMessage;
        }

        return $return;
    }

    /**
     * @param $flexformSettings
     * @param $socialNetworkTypeString
     * @param $ttContentUid
     * @param bool $isVerbose
     * @return object of message->isSuccessfull and message->message
     */
    public function syncYoutubeFeed($flexformSettings, $socialNetworkTypeString, $ttContentUid, $isVerbose = false){
        $return = (object)array();
        $return->isSuccessfull = false;
        $return->message = "";

        $flexformOptions = $this->optionService->convertFlexformSettings($flexformSettings);
        $flexformOptions->devMod = $this->extConf['socialfeed.']['devmod'];

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
            $return = $this->doRequestAndSetContentToCache($adapter, $flexformOptions, $socialNetworkTypeString ,$ttContentUid, $return, $isVerbose);
        }else{
            $return->message = $adapter->validationMessage;
        }

        return $return;
    }

    /**
     * @param $flexformSettings
     * @param $socialNetworkTypeString
     * @param $ttContentUid
     * @param bool $isVerbose
     * @return object of message->isSuccessfull and message->message
     */
    public function syncVimeoFeed($flexformSettings, $socialNetworkTypeString, $ttContentUid, $isVerbose = false){
        $return = (object)array();
        $return->isSuccessfull = false;
        $return->message = "";

        $flexformOptions = $this->optionService->convertFlexformSettings($flexformSettings);
        $flexformOptions->devMod = $this->extConf['socialfeed.']['devmod'];

        # check api key #
        $config_clientIdentifier = $this->extConf['socialfeed.']['vimeo.']['client.']['identifier'];
        $config_clientSecret = $this->extConf['socialfeed.']['vimeo.']['client.']['secret'];
        $config_token = $this->extConf['socialfeed.']['vimeo.']['token'];
        $flexformOptions->vimeoChannel = $flexformSettings['vimeoChannel'];

        # retrieve data from adapter #
        $adapter = new Adapter\VimeoAdapter($config_clientIdentifier, $config_clientSecret, $config_token, $this->itemRepository, $flexformOptions);

        if($adapter->isValid === true){
            $return = $this->doRequestAndSetContentToCache($adapter, $flexformOptions, $socialNetworkTypeString ,$ttContentUid, $return, $isVerbose);
        }else{
            $return->message = $adapter->validationMessage;
        }

        return $return;
    }

    /**
     * @param $flexformSettings
     * @param $socialNetworkTypeString
     * @param $ttContentUid
     * @param bool $isVerbose
     * @return object of message->isSuccessfull and message->message
     */
    public function syncNewsFeed($flexformSettings, $socialNetworkTypeString, $ttContentUid, $isVerbose = false){
        $return = (object)array();
        $return->isSuccessfull = false;
        $return->message = "";

        $flexformOptions = $this->optionService->convertFlexformSettings($flexformSettings);
        $flexformOptions->devMod = $this->extConf['socialfeed.']['devmod'];

        $flexformOptions->newsCategories = $flexformSettings['newsCategories'];
        $flexformOptions->newsDetailPageUid = $flexformSettings['newsDetailPageUid'];
        if ($flexformSettings['useHttpsLinks']) $flexformOptions->useHttps = true;

        # retrieve data from adapter #
        $adapter = new Adapter\TxNewsAdapter(new \GeorgRinger\News\Domain\Model\Dto\NewsDemand(), $this->itemRepository, $flexformOptions);

        if($adapter->isValid === true){
            $return = $this->doRequestAndSetContentToCache($adapter, $flexformOptions, $socialNetworkTypeString ,$ttContentUid, $return, $isVerbose);
        }else{
            $return->message = $adapter->validationMessage;
        }

        return $return;
    }


    /**
     * setResultToCache gets Result from Api setting it to caching framework
     *
     * @param $adapterObj Object reference of adapter
     * @param $flexformOptions Object of Optionsettings  of specific adapter
     * @param $socialNetworkTypeString String name of social network, set from class constant
     * @param $ttContentUid int uid of flexform
     * @param $success Object of Successmessage and status
     * @param bool $isVerbose bool for verbose mode in command line
     * @return object of success information
     */
    private function doRequestAndSetContentToCache(
        $adapterObj,
        $flexformOptions,
        $socialNetworkTypeString,
        $ttContentUid,
        $success,
        $isVerbose = false
    ){

       $success->isSuccessfull = false;
        try {
            $content = $adapterObj->getResultFromApi();

            //writing to cache
            $this->cacheService->setCacheContent(
                $socialNetworkTypeString, $flexformOptions->settings, $ttContentUid, $content
            );

            $success->isSuccessfull = true;
            $success->message = $socialNetworkTypeString . " flexform {$ttContentUid}: update feed successfull";

            if($isVerbose === true){
                $success->message .= "\n". var_export($content, true);
            }

        } catch (\Exception $e) {
            $success->message = $socialNetworkTypeString . ": " . $e->getMessage();
        }
        return $success;
    }

}