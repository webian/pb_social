<?php
namespace PlusB\PbSocial\Command;

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
     * @var \PlusB\PbSocial\Service\FeedSyncService
     * @inject
     */
    protected $feedSyncService;

    /**
     * @var \TYPO3\CMS\Core\Cache\CacheManager
     * @inject
     */
    protected $cacheManager = null;



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
     * @var string $sysLogWarnings String collected for syslog
     */
    private $sysLogWarnings = "";

    /**
     * @var bool $isSyslogWarning Bool whether syslogs are to be pullte out or not
     */
    private $isSyslogWarning = false;

    /**
     * @return string
     */
    public function getSysLogWarnings()
    {
        return $this->sysLogWarnings;
    }

    /**
     * @param string $sysLogWarnings
     */
    public function setSysLogWarnings($sysLogWarnings)
    {
        $this->sysLogWarnings .= ", ". $sysLogWarnings;
    }



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
            $this->outputConsoleInfo("entering verbose output");
        }

        # Initialize logger
        $this->logger = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Core\Log\LogManager')->getLogger(__CLASS__);

        // get extConf (will be different in Version 9)
        $this->extConf = @unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][self::EXTKEY]);
    }

    /**
     * @var array
     */
    private $extConf = array();

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

                $this->setSysLogWarnings("Flexform: ".$xml_string['uid']);

                /* starting procedural list of requrests */
                if ($flexformSettings['facebookEnabled'] === '1') {

                    $this->outputLogInformation(
                        $this->feedSyncService->syncFeed(self::TYPE_FACEBOOK, $flexformSettings, $xml_string['uid'], $this->isVerbose())
                    );

                }

                if ($flexformSettings['imgurEnabled'] === '1') {

                    $this->outputLogInformation(
                        $this->feedSyncService->syncFeed(self::TYPE_IMGUR, $flexformSettings, $xml_string['uid'], $this->isVerbose())
                    );

                }

                if ($flexformSettings['instagramEnabled'] === '1') {

                    $this->outputLogInformation(
                        $this->feedSyncService->syncFeed(self::TYPE_INSTAGRAM, $flexformSettings, $xml_string['uid'], $this->isVerbose())
                    );

                }

                if ($flexformSettings['linkedinEnabled'] === '1') {

                    $this->outputLogInformation(
                        $this->feedSyncService->syncFeed(self::TYPE_LINKEDIN, $flexformSettings, $xml_string['uid'], $this->isVerbose())
                    );

                }

                if ($flexformSettings['pinterestEnabled'] === '1') {

                    $this->outputLogInformation(
                        $this->feedSyncService->syncFeed(self::TYPE_PINTEREST, $flexformSettings, $xml_string['uid'], $this->isVerbose())
                    );
                }

                if ($flexformSettings['tumblrEnabled'] === '1') {

                    $this->outputLogInformation(
                        $this->feedSyncService->syncFeed(self::TYPE_TUMBLR, $flexformSettings, $xml_string['uid'], $this->isVerbose())
                    );

                }

                if ($flexformSettings['twitterEnabled'] === '1') {

                    $this->outputLogInformation(
                        $this->feedSyncService->syncFeed(self::TYPE_TWITTER, $flexformSettings, $xml_string['uid'], $this->isVerbose())
                    );

                }

                if ($flexformSettings['youtubeEnabled'] === '1') {

                    $this->outputLogInformation(
                        $this->feedSyncService->syncFeed(self::TYPE_YOUTUBE, $flexformSettings, $xml_string['uid'], $this->isVerbose())
                    );

                }

                if ($flexformSettings['vimeoEnabled'] === '1') {

                    $this->outputLogInformation(
                        $this->feedSyncService->syncFeed(self::TYPE_VIMEO, $flexformSettings, $xml_string['uid'], $this->isVerbose())
                    );
                }

                if ($flexformSettings['newsEnabled'] === '1') {

                    $this->outputLogInformation(
                        $this->feedSyncService->syncFeed(self::TYPE_TX_NEWS, $flexformSettings, $xml_string['uid'], $this->isVerbose())
                    );

                }

                $this->outputSysLog();
            }
        }
    }

    /** Converts some complex flexform xml structure into an easy to use array.
     * todo replace by typo3 core function
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
     * @param $message object
     */
    /**
     * @param $messageObject object of message->isSuccessfull and message->message
     */
    private function outputLogInformation($messageObject){
        if($messageObject->isSuccessfull === true){
            $this->outputLogInfo($messageObject->message);
        }else{
            $this->outputLogWarning($messageObject->message);
            $this->collectSyslogWarnings($messageObject->message);
        }
        $this->outputConsoleInfo($messageObject->message);

    }

    private function collectSyslogWarnings($message){
        $this->isSyslogWarning = true;
        $this->setSysLogWarnings($message);
    }

    private function outputSysLog(){
        if($this->isSyslogWarning === true){
            $GLOBALS['BE_USER']->simplelog($this->getSysLogWarnings(), self::EXTKEY, 1);
        }
    }

    /**
     * Output in Logfile
     * @param $message string
     */
    private function outputLogInfo($message){
        $this->logger->info($message);
    }

    /**
     * Output in Logfile
     * @param $message string
     */
    private function outputLogWarning($message){
        $this->logger->warning($message);
    }

    /**
     * Output in command line console
     * @param $message
     */
    private function outputConsoleInfo($message){
        if($this->isSilent() !== true){
            $this->outputFormatted($message);
        }
    }
}
