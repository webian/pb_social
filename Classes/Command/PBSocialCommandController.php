<?php
namespace PlusB\PbSocial\Command;

use PlusB\PbSocial\Adapter;
use TYPO3\CMS\Core\Exception;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

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

    /**
     * @var \PlusB\PbSocial\Controller\ItemController
     * @inject
     */
    protected $itemController;

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
     * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
     * @inject
     */
    protected $configurationManager;

    /**
     * @var \TYPO3\CMS\Core\Database\DatabaseConnection
     */
    protected $db;

    protected $logger;

    private function getDB()
    {
        return $GLOBALS['TYPO3_DB'];
    }

    /**
     * @var \TYPO3\CMS\Extbase\Service\FlexFormService
     * @inject
     */
    protected $flexformService;

    /**
     *  Updates database with feeds
     */
    public function updateFeedDataCommand()
    {
        # Initialize logger
        $this->logger = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Core\Log\LogManager')->getLogger(__CLASS__);

        # used for logging purposes
        $extKey = 'pb_social';

        # Get extension configuration #
        $extConf = @unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['pb_social']);

        # Get caching backend #
        $cache = $this->cacheManager->getCache('pb_social_cache');

        $itemRepository = $this->itemRepository;

        # Setup database connection and fetch all flexform settings #
        $this->db = $this->getDB();
        $xml_settings = $this->db->exec_SELECTgetRows('pi_flexform', 'tt_content', 'CType = "list" AND list_type = "pbsocial_socialfeed" AND deleted = 0');

        # Convert flexform settings into usable array structure #
        if (!empty($xml_settings)) {

            # Update feeds #
            foreach ($xml_settings as $xml_string) {
                $settings = $this->flexform2SettingsArray($xml_string);
                $adapterOptions = $this->itemController->getAdapterOptions($settings);
                $adapterOptions->devMod = $extConf['socialfeed.']['devmod'];

                if ($settings['facebookEnabled'] === '1') {
                    # check api key #
                    $config_apiId = $extConf['socialfeed.']['facebook.']['api.']['id'];
                    $config_apiSecret = $extConf['socialfeed.']['facebook.']['api.']['secret'];

                    if (empty($config_apiId) || empty($config_apiSecret)) {
                        $this->logger->warning(self::TYPE_FACEBOOK . ' credentials not set');
                    } elseif (empty($adapterOptions->settings['facebookSearchIds'])) {
                        $this->logger->warning(self::TYPE_FACEBOOK . ' no search term defined');
                    } else {
                        $cacheIdentifier = $this->itemController->calculateCacheIdentifier(array(
                            "facebook_".$adapterOptions->settings['facebookSearchIds'], // cache depends on the searchids
                        ));

                        $adapter = new Adapter\FacebookAdapter($config_apiId, $config_apiSecret, $itemRepository);
                        try {
                            $content = $adapter->getResultFromApi($adapterOptions);

                            $cache->set($cacheIdentifier, $content);

                        } catch (\Exception $e) {
                            $this->logger->warning($e->getMessage());
                        }
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
                        $cacheIdentifier = $this->itemController->calculateCacheIdentifier(array(
                            "googleplus_".$adapterOptions->settings['googleSearchIds'], // cache depends on the searchids
                        ));

                        # retrieve data from adapter #
                        $adapter = new Adapter\GooglePlusAdapter($config_appKey, $itemRepository);
                        try {
                            $cache->set($cacheIdentifier, $adapter->getResultFromApi($adapterOptions));
                        } catch (\Exception $e) {
                            $this->logger->warning($e->getMessage());
                        }
                    }
                }

                if ($settings['imgurEnabled'] === '1') {
                    # check api key #
                    $config_apiId = $extConf['socialfeed.']['imgur.']['client.']['id'];
                    $config_apiSecret = $extConf['socialfeed.']['imgur.']['client.']['secret'];
                    $adapterOptions->imgSearchTags = $settings['imgurTags'];

                    // TODO: not yet implemented in backend configuration
                    $adapterOptions->imgSearchUsers = $settings['imgurUsers'];

                    if (empty($config_apiId) || empty($config_apiSecret)) {
                        $this->logger->warning(self::TYPE_IMGUR . ' credentials not set');
                    } elseif (empty($adapterOptions->imgSearchUsers) && empty($adapterOptions->imgSearchTags)) {
                        $this->logger->warning(self::TYPE_IMGUR . ' no search term defined');
                    } else {
                        $cacheIdentifier = $this->itemController->calculateCacheIdentifier(array(
                            "imgur_".$adapterOptions->settings['imgurTags'],
                            "imgur_".$adapterOptions->settings['imgurUsers']
                        ));

                        # retrieve data from adapter #
                        $adapter = new Adapter\ImgurAdapter($config_apiId, $config_apiSecret, $itemRepository);
                        try {
                            $cache->set($cacheIdentifier, $adapter->getResultFromApi($adapterOptions));
                        } catch (\Exception $e) {
                            $this->logger->warning($e->getMessage());
                        }
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

                    if (empty($config_clientId) || empty($config_clientSecret) || empty($config_clientCallback)) {
                        $this->logger->warning(self::TYPE_INSTAGRAM . ' credentials not set');
                    } elseif (empty($adapterOptions->instagramSearchIds) && empty($adapterOptions->instagramHashTags)) {
                        $this->logger->warning(self::TYPE_INSTAGRAM . ' no search term defined');
                    } else {
                        $cacheIdentifier = $this->itemController->calculateCacheIdentifier(array(
                            "instagram_".$adapterOptions->settings['instagramSearchIds'],
                            "instagram_".$adapterOptions->settings['instagramHashTag'],
                            "instagram_".$adapterOptions->settings['instagramPostFilter']
                        ));

                        # retrieve data from adapter #
                        $adapter = new Adapter\InstagramAdapter($config_clientId, $config_clientSecret, $config_clientCallback, $config_access_code, $itemRepository, $this->credentialRepository);
                        try {
                            $cache->set($cacheIdentifier, $adapter->getResultFromApi($adapterOptions));
                        } catch (\Exception $e) {
                            $this->logger->warning($e->getMessage());
                        }
                    }
                }

                if ($settings['linkedinEnabled'] === '1') {

                    # check api key #
                    $config_clientId = $extConf['socialfeed.']['linkedin.']['client.']['key'];
                    $config_clientSecret = $extConf['socialfeed.']['linkedin.']['client.']['secret'];
                    $config_clientCallback = $extConf['socialfeed.']['linkedin.']['client.']['callback_url'];
                    $config_access_code = $extConf['socialfeed.']['linkedin.']['access_token'];
                    $adapterOptions->companyIds = $settings['linkedinCompanyIds'];
                    $adapterOptions->showJobPostings = $settings['linkedinJobPostings'];
                    $adapterOptions->showNewProducts = $settings['linkedinNewProducts'];
                    $adapterOptions->showStatusUpdates = $settings['linkedinStatusUpdates'];

                    if (empty($config_clientId) || empty($config_clientSecret) || empty($config_access_code)|| empty($config_clientCallback)) {
                        $this->logger->warning(self::TYPE_LINKEDIN . ' credentials not set');
                        $GLOBALS['BE_USER']->simplelog(self::TYPE_LINKEDIN . ' credentials not set', $extKey, 1);
                    } elseif (empty($adapterOptions->companyIds)) {
                        $this->logger->warning(self::TYPE_LINKEDIN . ' no company ID term defined');
                        $GLOBALS['BE_USER']->simplelog(self::TYPE_LINKEDIN . ' no company ID term defined', $extKey, 1);
                    } else {
                        $linkedInFeedFilters =
                            ($adapterOptions->settings['linkedinJobPostings']) .
                            ($adapterOptions->settings['linkedinNewProducts']) .
                            ($adapterOptions->settings['linkedinStatusUpdates']);
                        $cacheIdentifier = $this->itemController->calculateCacheIdentifier(array(
                            "linkedin_".$adapterOptions->companyIds,
                            "linkedin_".$linkedInFeedFilters
                        ));

                        # retrieve data from adapter #
                        $adapter = new Adapter\LinkedInAdapter($config_clientId, $config_clientSecret, $config_clientCallback, $config_access_code, $itemRepository, $this->credentialRepository);
                        try {
                            $cache->set($cacheIdentifier, $adapter->getResultFromApi($adapterOptions));
                        } catch (\Exception $e) {
                            $this->logger->warning($e->getMessage());
                            $GLOBALS['BE_USER']->simplelog($e->getMessage(), $extKey, 1);
                        }
                    }
                }

                if ($settings['pinterestEnabled'] === '1') {
                    # check api key #
                    $config_appId = $extConf['socialfeed.']['pinterest.']['app.']['id'];
                    $config_appSecret = $extConf['socialfeed.']['pinterest.']['app.']['secret'];
                    $config_accessCode = $extConf['socialfeed.']['pinterest.']['app.']['code'];
                    $adapterOptions->pinterest_username = $settings['username'];
                    $adapterOptions->pinterest_boardname = $settings['boardname'];

                    if (empty($config_appId) || empty($config_appSecret) || empty($config_accessCode)) {
                        $this->logger->warning(self::TYPE_PINTEREST . ' credentials not set');
                    } elseif (empty($adapterOptions->pinterest_username) || empty($adapterOptions->pinterest_boardname)) {
                        $this->logger->warning(self::TYPE_PINTEREST . ' no username or no boardname defined');
                    } else {
                        $cacheIdentifier = $this->itemController->calculateCacheIdentifier(array(
                            "pinterest_".$adapterOptions->settings['username'],
                            "pinterest_".$adapterOptions->settings['boardname']
                        ));

                        # retrieve data from adapter #
                        $adapter = new Adapter\PinterestAdapter($config_appId, $config_appSecret, $config_accessCode, $itemRepository, $this->credentialRepository);
                        try {
                            $cache->set($cacheIdentifier, $adapter->getResultFromApi($adapterOptions));
                        } catch (\Exception $e) {
                            $this->logger->warning($e->getMessage());
                        }
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
                        $cacheIdentifier = $this->itemController->calculateCacheIdentifier(array(
                            "tumblr_".$adapterOptions->settings['tumblrBlogNames']
                        ));

                        # retrieve data from adapter #
                        $adapter = new Adapter\TumblrAdapter($config_consumerKey, $config_consumerSecret, $config_Token, $config_TokenSecret, $itemRepository);
                        try {
                            $cache->set($cacheIdentifier, $adapter->getResultFromApi($adapterOptions));
                        } catch (\Exception $e) {
                            $this->logger->warning($e->getMessage());
                        }
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
                        $cacheIdentifier = $this->itemController->calculateCacheIdentifier(array(
                            "twitter_".$settings['twitterSearchFieldValues'],
                            "twitter_".$settings['twitterProfilePosts'],
                            "twitter_".$settings['twitterLanguage'],
                            "twitter_".$settings['twitterGeoCode'],
                            "twitter_".$settings['twitterHideRetweets'],
                            "twitter_".$settings['twitterShowOnlyImages']
                        ));

                        # retrieve data from adapter #
                        $adapter = new Adapter\TwitterAdapter($config_consumerKey, $config_consumerSecret, $config_accessToken, $config_accessTokenSecret, $itemRepository);
                        try {
                            $cache->set($cacheIdentifier, $adapter->getResultFromApi($adapterOptions));
                        } catch (\Exception $e) {
                            $this->logger->warning($e->getMessage());
                        }
                    }
                }

                if ($settings['youtubeEnabled'] === '1') {

                    # check api key #
                    $config_apiKey = $extConf['socialfeed.']['youtube.']['apikey'];
                    $adapterOptions->youtubeSearch = $settings['youtubeSearch'];
                    $adapterOptions->youtubePlaylist = $settings['youtubePlaylist'];
                    $adapterOptions->youtubeChannel = $settings['youtubeChannel'];
                    $adapterOptions->youtubeType = $settings['youtubeType'];
                    $adapterOptions->youtubeLanguage = $settings['youtubeLanguage'];
                    $adapterOptions->youtubeOrder = $settings['youtubeOrder'];

                    if (empty($config_apiKey)) {
                        $this->logger->warning(self::TYPE_YOUTUBE . ' credentials not set');
                    } elseif (empty($adapterOptions->youtubeSearch) && empty($adapterOptions->youtubePlaylist) && empty($adapterOptions->youtubeChannel)) {
                        $this->logger->warning(self::TYPE_YOUTUBE . ' no search term defined');
                    } else {
                        $cacheIdentifier = $this->itemController->calculateCacheIdentifier(array(
                            "youtube_".$adapterOptions->settings['youtubeSearch'],
                            "youtube_".$adapterOptions->settings['youtubePlaylist'],
                            "youtube_".$adapterOptions->settings['youtubeChannel'],
                            "youtube_".$adapterOptions->settings['youtubeType'],
                            "youtube_".$adapterOptions->settings['youtubeLanguage'],
                            "youtube_".$adapterOptions->settings['youtubeOrder']
                        ));

                        # retrieve data from adapter #
                        $adapter = new Adapter\YoutubeAdapter($config_apiKey, $itemRepository);
                        try {
                            $cache->set($cacheIdentifier, $adapter->getResultFromApi($adapterOptions));
                        } catch (\Exception $e) {
                            $this->logger->warning($e->getMessage());
                        }
                    }
                }

                if ($settings['vimeoEnabled'] === '1') {

                    # check api key #
                    $config_clientIdentifier = $extConf['socialfeed.']['vimeo.']['client.']['identifier'];
                    $config_clientSecret = $extConf['socialfeed.']['vimeo.']['client.']['secret'];
                    $config_token = $extConf['socialfeed.']['vimeo.']['token'];
                    $adapterOptions->vimeoChannel = $settings['vimeoChannel'];

                    // if (empty($config_clientIdentifier) || empty($config_clientSecret) || empty($config_token)) {
                    if (empty($config_clientIdentifier) || empty($config_clientSecret) || empty($config_token)) {
                        $this->logger->warning(self::TYPE_VIMEO . ' credentials not set');
                    } elseif (empty($adapterOptions->vimeoChannel)) {
                        $this->logger->warning(self::TYPE_VIMEO . ' no channel defined');
                    } else {
                        $cacheIdentifier = $this->itemController->calculateCacheIdentifier(array(
                            "vimeo_".$adapterOptions->settings['vimeoChannel']
                        ));

                        # retrieve data from adapter #
                        $adapter = new Adapter\VimeoAdapter($config_clientIdentifier, $config_clientSecret, $config_token, $itemRepository);
                        try {
                            $cache->set($cacheIdentifier, $adapter->getResultFromApi($adapterOptions));
                        } catch (\Exception $e) {
                            $this->logger->warning($e->getMessage());
                        }
                    }
                }

                if ($settings['newsEnabled'] === '1') {
                    if (!ExtensionManagementUtility::isLoaded('news'))
                    {
                        $this->logger->warning(self::TYPE_TX_NEWS . ' extension not loaded.');
                        $GLOBALS['BE_USER']->simplelog(self::TYPE_TX_NEWS . ' extension not loaded', $extKey, 1);
                    } else {
                        $adapterOptions->newsCategories = $settings['newsCategories'];
                        $adapterOptions->newsDetailPageUid = $settings['newsDetailPageUid'];
                        if ($settings['useHttpsLinks']) $adapterOptions->useHttps = true;

                        # user should set a news category but it is not required. in this case all news are shown in feed
                        if (empty($adapterOptions->newsCategories))
                        {
                            $this->logger->warning(self::TYPE_TX_NEWS . ': no news category defined, will output all available news');
                            $GLOBALS['BE_USER']->simplelog(self::TYPE_TX_NEWS . ': no news category defined, will output all available news', $extKey, 0);
                        }

                        $cacheIdentifier = $this->itemController->calculateCacheIdentifier(array(
                            "txnews_".$adapterOptions->newsCategories
                        ));

                        $adapter = new Adapter\TxNewsAdapter(new \GeorgRinger\News\Domain\Model\Dto\NewsDemand(), $itemRepository);
                        try {
                            $cache->set($cacheIdentifier, $adapter->getResultFromApi($adapterOptions));
                        } catch (\Exception $e) {
                            $this->logger->warning($e->getMessage());
                            $GLOBALS['BE_USER']->simplelog(self::TYPE_TX_NEWS . ': ' . $e->getMessage(), $extKey, 1);
                        }
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
}
