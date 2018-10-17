<?php
namespace PlusB\PbSocial\Command;

use PlusB\PbSocial\Adapter;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Cli\Request;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

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

class PBSocialCommandController extends \TYPO3\CMS\Extbase\Mvc\Controller\CommandController
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
     * @var \TYPO3\CMS\Core\Cache\CacheManager
     * @inject
     */
    protected $cacheManager = null;

    /**
     * @var \PlusB\PbSocial\Domain\Repository\ItemRepository
     * @inject
     */
    protected $itemRepository;

     /**
     * @var \PlusB\PbSocial\Domain\Repository\CredentialRepository
     * @inject
     */
    protected $credentialRepository;

    /**
     * @var \TYPO3\CMS\Core\Database\DatabaseConnection
     */
    protected $db;


    /**
     * @var bool Verbose output
     */
    protected $verbose = false;


    /**
     * @var bool Silent output, nothing is displayed, but still log in general typo3 log file
     */
    protected $silent = false;

    /**
     * @return bool
     */
    protected function isVerbose()
    {
        return $this->verbose;
    }

    /**
     * @param bool $verbose
     */
    protected function setVerbose($verbose)
    {
        $this->verbose = $verbose;
    }

    /**
     * @return bool
     */
    public function isSilent()
    {
        return $this->silent;
    }

    /**
     * @param bool $silent
     */
    public function setSilent($silent)
    {
        $this->silent = $silent;
    }

    /** @var $logger \TYPO3\CMS\Core\Log\Logger */
    protected $logger;

    private function getDB()
    {
        return $GLOBALS['TYPO3_DB'];
    }



    /**
     * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
     * @inject
     */
    protected $configurationManager;

    /**
     * @var array
     */
    protected $typoscriptSettings = array();

    /**
     * initializing
     */
    private function initializeUpdateFeedDataCommand($verbose, $silent) {

        $this->setTyposcriptSettings($this->configurationManager->getConfiguration(
            \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS
        ));

        /*
        * using --verbose to get json return values and more
        */
        $this->setVerbose($verbose);

        /*
        * using --silent to get simply no output
        */
        $this->setSilent($silent);

        /*
        * it's a radio button situation: if you have silent, you do not want to have verbose
        */
        if($this->isSilent() === true){
            $this->setVerbose(false);
        }

        if($this->isVerbose() === true){
            $this->outputConsoleInfo("", "entering verbose output");
        }

        # Initialize logger
        $this->logger = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Core\Log\LogManager')->getLogger(__CLASS__);

        // get extConf (will be different in Version 9)
        $this->setExtConf(@unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][self::EXTKEY]));

        # Get caching backend #
        $this->setCache($this->cacheManager->getCache('pb_social_cache'));
    }

    /**
     * @var array
     */
    private $extConf = array();

    /**
     * @var FrontendInterface $cache
     */
    private $cache;

    /**
     * @param FrontendInterface $cache
     */
    public function setCache($cache)
    {
        $this->cache = $cache;
    }

    /**
     * @param array $extConf
     */
    private function setExtConf($extConf){
        $this->extConf = $extConf;
    }

    /**
     * @return array
     */
    public function getTyposcriptSettings()
    {
        return $this->typoscriptSettings;
    }

    /**
     * @param array $typoscriptSettings
     */
    public function setTyposcriptSettings($typoscriptSettings)
    {
        $this->typoscriptSettings = $typoscriptSettings;
    }


    /**
     * @var \TYPO3\CMS\Extbase\Service\FlexFormService
     * @inject
     */
    protected $flexformService;

    /**
     * Updates database with feeds
     * Use this in TYPO3 backend scheduler or in command line  ./your-path-to-typo3/cli_dispatch.phpsh extbase pbsocial:updatefeeddata --verbose
     *
     * @param bool $verbose Enter verbose output
     * @param bool $silent Silent mode outputs nothing, but logs still into general typo3 log file
     */
    public function updateFeedDataCommand($verbose = false, $silent = false)
    {
        $this->initializeUpdateFeedDataCommand($verbose, $silent);

        # Setup database connection and fetch all flexform settings #
        /*
         * todo: read flexform in BE mode but clean
         */
        $this->db = $this->getDB();
        $xml_settings = $this->db->exec_SELECTgetRows('pi_flexform, uid', 'tt_content', 'CType = "list" AND list_type = "pbsocial_socialfeed" AND deleted = 0');


        # Convert flexform settings into usable array structure #
        if (!empty($xml_settings)) {

            # Update feeds #
            foreach ($xml_settings as $xml_string) {
                /* initializing procedural request */
                $flexformSettings = $this->flexform2SettingsArray($xml_string);
                $adapterOptions = $this->optionService->getAdapterOptions($flexformSettings);
                $adapterOptions->devMod = $this->extConf['socialfeed.']['devmod'];

                /* starting procedural list of requrests */
                if ($flexformSettings['facebookEnabled'] === '1') {
                    # check api key #
                    $config_apiId = $this->extConf['socialfeed.']['facebook.']['api.']['id'];
                    $config_apiSecret = $this->extConf['socialfeed.']['facebook.']['api.']['secret'];

                    if (empty($config_apiId) || empty($config_apiSecret)) {
                        $this->outputLogInformation(self::TYPE_FACEBOOK , ' credentials not set');
                    } elseif (empty($adapterOptions->settings['facebookSearchIds'])) {
                        $this->outputLogInformation(self::TYPE_FACEBOOK , ' no search term defined');
                    } else {

                        $adapter = new Adapter\FacebookAdapter($config_apiId, $config_apiSecret, $this->itemRepository);
                        $this->doRequestSetToCache($adapter, $this->cache, $adapterOptions, self::TYPE_FACEBOOK, $xml_string['uid']);

                    }
                }


                /*
                 * Google+ has been shut down
                 * https://www.heise.de/newsticker/meldung/Soziale-Netzwerke-Google-stellt-Google-ein-4183950.html
                 */
                /*if ($settings['googleEnabled'] === '1') {
                    # check api key #
                    $config_appKey = $this->extConf['socialfeed.']['googleplus.']['app.']['key'];

                    if (empty($config_appKey)) {
                        $this->outputLogInformation(self::TYPE_GOOGLE, ' credentials not set');
                    } elseif (empty($settings['googleSearchIds'])) {
                        $this->outputLogInformation(self::TYPE_GOOGLE, ' no search term defined');
                    } else {
                        $cacheIdentifier = array(
                            "googleplus_".$adapterOptions->settings['googleSearchIds'], // cache depends on the searchids
                        );

                        # retrieve data from adapter #
                        $adapter = new Adapter\GooglePlusAdapter($config_appKey, $this->itemRepository);
                        
                        $this->setResultToCache($adapter, $this->cache, $adapterOptions, $cacheIdentifier,self::TYPE_GOOGLE,$xml_string['uid']);
                    }
                }*/

                if ($flexformSettings['imgurEnabled'] === '1') {
                    # check api key #
                    $config_apiId = $this->extConf['socialfeed.']['imgur.']['client.']['id'];
                    $config_apiSecret = $this->extConf['socialfeed.']['imgur.']['client.']['secret'];
                    $adapterOptions->imgSearchTags = $flexformSettings['imgurTags'];

                    // TODO: not yet implemented in backend configuration
                    $adapterOptions->imgSearchUsers = $flexformSettings['imgurUsers'];

                    if (empty($config_apiId) || empty($config_apiSecret)) {
                        $this->outputLogInformation(self::TYPE_IMGUR, ' credentials not set');
                    } elseif (empty($adapterOptions->imgSearchUsers) && empty($adapterOptions->imgSearchTags)) {
                        $this->outputLogInformation(self::TYPE_IMGUR, ' no search term defined');
                    } else {

                        # retrieve data from adapter #
                        $adapter = new Adapter\ImgurAdapter($config_apiId, $config_apiSecret, $this->itemRepository);
                        $this->doRequestSetToCache($adapter, $this->cache, $adapterOptions, self::TYPE_IMGUR, $xml_string['uid']);
                    }
                }

                if ($flexformSettings['instagramEnabled'] === '1') {
                    # check api key #
                    $config_clientId = $this->extConf['socialfeed.']['instagram.']['client.']['id'];
                    $config_clientSecret = $this->extConf['socialfeed.']['instagram.']['client.']['secret'];
                    $config_clientCallback = $this->extConf['socialfeed.']['instagram.']['client.']['callback'];
                    $config_access_code = $this->extConf['socialfeed.']['instagram.']['client.']['access_code'];
                    $adapterOptions->instagramHashTags = $flexformSettings['instagramHashTag'];
                    $adapterOptions->instagramSearchIds = $flexformSettings['instagramSearchIds'];
                    $adapterOptions->instagramPostFilter = $flexformSettings['instagramPostFilter'];

                    if (empty($config_clientId) || empty($config_clientSecret) || empty($config_clientCallback)) {
                        $this->outputLogInformation(self::TYPE_INSTAGRAM, ' credentials not set');
                    } elseif (empty($adapterOptions->instagramSearchIds) && empty($adapterOptions->instagramHashTags)) {
                        $this->outputLogInformation(self::TYPE_INSTAGRAM, ' no search term defined');
                    } else {

                        # retrieve data from adapter #
                        $adapter = new Adapter\InstagramAdapter($config_clientId, $config_clientSecret, $config_clientCallback, $config_access_code, $this->itemRepository, $this->credentialRepository);
                        $this->doRequestSetToCache($adapter, $this->cache, $adapterOptions, self::TYPE_INSTAGRAM, $xml_string['uid']);
                    }
                }

                if ($flexformSettings['linkedinEnabled'] === '1') {

                    # check api key #
                    $config_clientId = $this->extConf['socialfeed.']['linkedin.']['client.']['key'];
                    $config_clientSecret = $this->extConf['socialfeed.']['linkedin.']['client.']['secret'];
                    $config_clientCallback = $this->extConf['socialfeed.']['linkedin.']['client.']['callback_url'];
                    $config_access_code = $this->extConf['socialfeed.']['linkedin.']['access_token'];
                    $adapterOptions->companyIds = $flexformSettings['linkedinCompanyIds'];
                    $adapterOptions->showJobPostings = $flexformSettings['linkedinJobPostings'];
                    $adapterOptions->showNewProducts = $flexformSettings['linkedinNewProducts'];
                    $adapterOptions->showStatusUpdates = $flexformSettings['linkedinStatusUpdates'];

                    if (empty($config_clientId) || empty($config_clientSecret) || empty($config_access_code)|| empty($config_clientCallback)) {
                        $this->outputLogInformation(self::TYPE_LINKEDIN, ' credentials not set');
                        $GLOBALS['BE_USER']->simplelog(self::TYPE_LINKEDIN . ' credentials not set', self::EXTKEY, 1);
                    } elseif (empty($adapterOptions->companyIds)) {
                        $this->outputLogInformation(self::TYPE_LINKEDIN, ' no company ID term defined');
                        $GLOBALS['BE_USER']->simplelog(self::TYPE_LINKEDIN . ' no company ID term defined', self::EXTKEY, 1);
                    } else {

                        # retrieve data from adapter #
                        $adapter = new Adapter\LinkedInAdapter($config_clientId, $config_clientSecret, $config_clientCallback, $config_access_code, $this->itemRepository, $this->credentialRepository);
                        $this->doRequestSetToCache($adapter, $this->cache, $adapterOptions, self::TYPE_LINKEDIN,$xml_string['uid']);

                    }
                }

                if ($flexformSettings['pinterestEnabled'] === '1') {
                    # check api key #
                    $config_appId = $this->extConf['socialfeed.']['pinterest.']['app.']['id'];
                    $config_appSecret = $this->extConf['socialfeed.']['pinterest.']['app.']['secret'];
                    $config_accessCode = $this->extConf['socialfeed.']['pinterest.']['app.']['code'];
                    $adapterOptions->pinterest_username = $flexformSettings['username'];
                    $adapterOptions->pinterest_boardname = $flexformSettings['boardname'];

                    if (empty($config_appId) || empty($config_appSecret) || empty($config_accessCode)) {
                        $this->outputLogInformation(self::TYPE_PINTEREST, ' credentials not set');
                    } elseif (empty($adapterOptions->pinterest_username) || empty($adapterOptions->pinterest_boardname)) {
                        $this->outputLogInformation(self::TYPE_PINTEREST, ' no username or no boardname defined');
                    } else {
                        # retrieve data from adapter #
                        $adapter = new Adapter\PinterestAdapter($config_appId, $config_appSecret, $config_accessCode, $this->itemRepository, $this->credentialRepository);
                        $this->doRequestSetToCache($adapter, $this->cache, $adapterOptions, self::TYPE_PINTEREST,$xml_string['uid']);
                    }
                }

                if ($flexformSettings['tumblrEnabled'] === '1') {
                    # check api key #
                    $config_consumerKey = $this->extConf['socialfeed.']['tumblr.']['consumer.']['key'];
                    $config_consumerSecret = $this->extConf['socialfeed.']['tumblr.']['consumer.']['secret'];
                    $config_Token = $this->extConf['socialfeed.']['tumblr.']['token'];
                    $config_TokenSecret = $this->extConf['socialfeed.']['tumblr.']['token_secret'];

                    $adapterOptions->tumblrHashtag = strtolower(str_replace('#', '', $flexformSettings['tumblrHashTag']));
                    $adapterOptions->tumblrBlogNames = $flexformSettings['tumblrBlogNames'];
                    $adapterOptions->tumblrShowOnlyImages = $flexformSettings['tumblrShowOnlyImages'];

                    if (empty($config_consumerKey) || empty($config_consumerSecret) || empty($config_Token) || empty($config_TokenSecret)) {
                        $this->outputLogInformation(self::TYPE_TUMBLR, ' credentials not set');
                    } elseif (empty($adapterOptions->tumblrBlogNames)) {
                        $this->outputLogInformation(self::TYPE_TUMBLR, ' - no blog names for search term defined');
                    } else {
                        # retrieve data from adapter #
                        $adapter = new Adapter\TumblrAdapter($config_consumerKey, $config_consumerSecret, $config_Token, $config_TokenSecret, $this->itemRepository);
                        $this->doRequestSetToCache($adapter, $this->cache, $adapterOptions, self::TYPE_TUMBLR, $xml_string['uid']);
                        
                    }
                }

                if ($flexformSettings['twitterEnabled'] === '1') {
                    # check api key #
                    $config_consumerKey = $this->extConf['socialfeed.']['twitter.']['consumer.']['key'];
                    $config_consumerSecret = $this->extConf['socialfeed.']['twitter.']['consumer.']['secret'];
                    $config_accessToken = $this->extConf['socialfeed.']['twitter.']['oauth.']['access.']['token'];
                    $config_accessTokenSecret = $this->extConf['socialfeed.']['twitter.']['oauth.']['access.']['token_secret'];

                    $adapterOptions->twitterSearchFieldValues = $flexformSettings['twitterSearchFieldValues'];
                    $adapterOptions->twitterProfilePosts = $flexformSettings['twitterProfilePosts'];
                    $adapterOptions->twitterLanguage = $flexformSettings['twitterLanguage'];
                    $adapterOptions->twitterGeoCode = $flexformSettings['twitterGeoCode'];
                    $adapterOptions->twitterHideRetweets = $flexformSettings['twitterHideRetweets'];
                    $adapterOptions->twitterShowOnlyImages = $flexformSettings['twitterShowOnlyImages'];

                    if (empty($config_consumerKey) || empty($config_consumerSecret) || empty($config_accessToken) || empty($config_accessTokenSecret)) {
                        $this->outputLogInformation(self::TYPE_TWITTER, ' credentials not set');
                    } elseif (empty($adapterOptions->twitterSearchFieldValues) && empty($adapterOptions->twitterProfilePosts)) {
                        $this->outputLogInformation(self::TYPE_TWITTER, ' no search term defined');
                    } else {
                        # retrieve data from adapter #
                        $adapter = new Adapter\TwitterAdapter($config_consumerKey, $config_consumerSecret, $config_accessToken, $config_accessTokenSecret, $this->itemRepository);
                        $this->doRequestSetToCache($adapter, $this->cache, $adapterOptions, self::TYPE_TWITTER,$xml_string['uid']);
                    }
                }

                if ($flexformSettings['youtubeEnabled'] === '1') {

                    # check api key #
                    $config_apiKey = $this->extConf['socialfeed.']['youtube.']['apikey'];
                    $adapterOptions->youtubeSearch = $flexformSettings['youtubeSearch'];
                    $adapterOptions->youtubePlaylist = $flexformSettings['youtubePlaylist'];
                    $adapterOptions->youtubeChannel = $flexformSettings['youtubeChannel'];
                    $adapterOptions->youtubeType = $flexformSettings['youtubeType'];
                    $adapterOptions->youtubeLanguage = $flexformSettings['youtubeLanguage'];
                    $adapterOptions->youtubeOrder = $flexformSettings['youtubeOrder'];

                    if (empty($config_apiKey)) {
                        $this->outputLogInformation(self::TYPE_YOUTUBE, ' credentials not set');
                    } elseif (empty($adapterOptions->youtubeSearch) && empty($adapterOptions->youtubePlaylist) && empty($adapterOptions->youtubeChannel)) {
                        $this->outputLogInformation(self::TYPE_YOUTUBE, ' no search term defined');
                    } else {

                        # retrieve data from adapter #
                        $adapter = new Adapter\YoutubeAdapter($config_apiKey, $this->itemRepository);
                        $this->doRequestSetToCache($adapter, $this->cache, $adapterOptions, self::TYPE_YOUTUBE, $xml_string['uid']);
                    }
                }

                if ($flexformSettings['vimeoEnabled'] === '1') {

                    # check api key #
                    $config_clientIdentifier = $this->extConf['socialfeed.']['vimeo.']['client.']['identifier'];
                    $config_clientSecret = $this->extConf['socialfeed.']['vimeo.']['client.']['secret'];
                    $config_token = $this->extConf['socialfeed.']['vimeo.']['token'];
                    $adapterOptions->vimeoChannel = $flexformSettings['vimeoChannel'];

                    // if (empty($config_clientIdentifier) || empty($config_clientSecret) || empty($config_token)) {
                    if (empty($config_clientIdentifier) || empty($config_clientSecret) || empty($config_token)) {
                        $this->outputLogInformation(self::TYPE_VIMEO, ' credentials not set');
                    } elseif (empty($adapterOptions->vimeoChannel)) {
                        $this->outputLogInformation(self::TYPE_VIMEO, ' no channel defined');
                    } else {
                        # retrieve data from adapter #
                        $adapter = new Adapter\VimeoAdapter($config_clientIdentifier, $config_clientSecret, $config_token, $this->itemRepository);
                        $this->doRequestSetToCache($adapter, $this->cache, $adapterOptions, self::TYPE_VIMEO,$xml_string['uid']);
                    }
                }

                if ($flexformSettings['newsEnabled'] === '1') {
                    if (!ExtensionManagementUtility::isLoaded('news'))
                    {
                        $this->outputLogInformation(self::TYPE_TX_NEWS, ' extension not loaded.');
                        $GLOBALS['BE_USER']->simplelog(self::TYPE_TX_NEWS . ' extension not loaded', self::EXTKEY, 1);
                    } else {
                        $adapterOptions->newsCategories = $flexformSettings['newsCategories'];
                        $adapterOptions->newsDetailPageUid = $flexformSettings['newsDetailPageUid'];
                        if ($flexformSettings['useHttpsLinks']) $adapterOptions->useHttps = true;

                        # user should set a news category but it is not required. in this case all news are shown in feed
                        if (empty($adapterOptions->newsCategories))
                        {
                            $this->outputLogInformation(self::TYPE_TX_NEWS, ': no news category defined, will output all available news');
                            $GLOBALS['BE_USER']->simplelog(self::TYPE_TX_NEWS . ': no news category defined, will output all available news', self::EXTKEY, 0);
                        }

                        $adapter = new Adapter\TxNewsAdapter(new \GeorgRinger\News\Domain\Model\Dto\NewsDemand(), $this->itemRepository);
                        $this->doRequestSetToCache($adapter, $this->cache, $adapterOptions, self::TYPE_TX_NEWS,$xml_string['uid']);
                    }
                }

            }
        }
    }

    /** Converts some complex flexform xml structure into an easy to use array.
     *
     * @param $xml_string
     * @return array
     */
    public function flexform2SettingsArray($xml_string)
    {
        $xml_obj = simplexml_load_string($xml_string['pi_flexform']);
        $settings = array();
        $extract = 'settings.';

        # Traverse all sheet nodes #
        /** @var \SimpleXMLElement $sheet */
        foreach ($xml_obj->children()->children() as $sheet) {

            # Get data from field nodes #
            /** @var \SimpleXMLElement $field */
            foreach ($sheet->children()->children() as $field) {

                # if index is settings.xyzabcdef* #
                if (strpos($field['index'], $extract) == 0) {
                    $index = str_replace($extract, '', $field['index']);
                    $settings[$index] = (string) $field->children();
                }
            }
        }

        return $settings;
    }

    /**
     * setResultToCache gets Result from Api setting it to caching framework
     *
     * @param $adapterObj Object reference of adapter
     * @param $cacheObj FrontendInterface reference of caching framework
     * @param $adapterOptions Object of Optionsettings  of specific adapter
     * @param $socialNetworkTypeString String name of social network, set from class constant
     * @param $ttContentUid int uid of flexform
     */
    private function doRequestSetToCache(
        $adapterObj,
        $cacheObj,
        $adapterOptions,
        $socialNetworkTypeString,
        $ttContentUid
    ){
        try {
            $content = $adapterObj->getResultFromApi($adapterOptions);
            //writing to cache
            $this->cacheService->setCacheContent(
                $socialNetworkTypeString,
                $adapterOptions->settings,
                $ttContentUid,
                $cacheObj,
                $content
            );

            if($this->isVerbose() === true){
                $this->outputConsoleInfo($socialNetworkTypeString, var_export($content, true));
            }
            $this->outputConsoleInfo($socialNetworkTypeString, "update feed successfull");    
        } catch (\Exception $e) {
            $this->outputLogInformation($socialNetworkTypeString, $e->getMessage());
            $GLOBALS['BE_USER']->simplelog($socialNetworkTypeString . ': ' . $e->getMessage(), self::EXTKEY, 1);
        }
    }

    /**
     * @param string $type
     * @param $message
     */
    private function outputLogInformation($type, $message){
        $this->outputLogWarning($type, $message);
        $this->outputConsoleInfo($type, $message);
    }

    /**
     * @param $type string
     * @param $message string
     */
    private function outputLogWarning($type, $message){
        $this->logger->warning($type . ' ' . $message);
    }

    /**
     * @param $type
     * @param $message
     */
    private function outputConsoleInfo($type, $message){
        if($this->isSilent() !== true){
            $this->outputFormatted($type . ' ' . $message);
        }
    }
}
