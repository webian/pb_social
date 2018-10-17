<?php

namespace PlusB\PbSocial\Service;

use PlusB\PbSocial\Service\Base\AbstractBaseService;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

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

class OptionService extends AbstractBaseService
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
     * Takes settings and returns options
     *
     * @param $settings
     * @return object
     */
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
     * Gets name of social network, returns cacheIdentifierElementsArray by its options
     *
     * @param $socialNetworkTypeString
     * @return array
     */
    public function getCacheIdentifierElementsArray($socialNetworkTypeString, $settings){
        $array = array();

        switch ($socialNetworkTypeString){
            case self::TYPE_FACEBOOK:
                $array =  array(
                    "facebook_" . $this->getAdapterOptions($settings)->settings['facebookSearchIds']
                );
                break;
            case self::TYPE_IMGUR:
                $array =  array(
                    "imgur_" . $this->getAdapterOptions($settings)->settings['imgurTags'],
                    "imgur_" . $this->getAdapterOptions($settings)->settings['imgurUsers']
                );
                break;
            case self::TYPE_INSTAGRAM:
                $array =  array(
                    "instagram_" . $this->getAdapterOptions($settings)->settings['instagramSearchIds'],
                    "instagram_" . $this->getAdapterOptions($settings)->settings['instagramHashTag'],
                    "instagram_" . $this->getAdapterOptions($settings)->settings['instagramPostFilter']
                );
                break;
            case self::TYPE_LINKEDIN:
                $linkedInFeedFilters =
                    ($this->getAdapterOptions($settings)->settings['linkedinJobPostings']) .
                    ($this->getAdapterOptions($settings)->settings['linkedinNewProducts']) .
                    ($this->getAdapterOptions($settings)->settings['linkedinStatusUpdates']);
                $array =  array(
                    "linkedin_" . $this->getAdapterOptions($settings)->settings['linkedinCompanyIds'],
                    "linkedin_" . $linkedInFeedFilters
                );
                break;
            case self::TYPE_PINTEREST:
                $array =  array(
                    "pinterest_" . $this->getAdapterOptions($settings)->settings['username'],
                    "pinterest_" . $this->getAdapterOptions($settings)->settings['boardname']
                );
                break;
            case self::TYPE_TUMBLR:
                $array =  array(
                    "tumblr_" . $this->getAdapterOptions($settings)->settings['tumblrBlogNames']
                );
                break;
            case self::TYPE_TWITTER:
                $array =  array(
                    "twitter_" . $this->getAdapterOptions($settings)->settings['twitterSearchFieldValues'],
                    "twitter_" . $this->getAdapterOptions($settings)->settings['twitterProfilePosts'],
                    "twitter_" . $this->getAdapterOptions($settings)->settings['twitterLanguage'],
                    "twitter_" . $this->getAdapterOptions($settings)->settings['twitterGeoCode'],
                    "twitter_" . $this->getAdapterOptions($settings)->settings['twitterHideRetweets'],
                    "twitter_" . $this->getAdapterOptions($settings)->settings['twitterShowOnlyImages']
                );
                break;
            case self::TYPE_YOUTUBE:
                $array =  array(
                    "youtube_" . $this->getAdapterOptions($settings)->settings['youtubeSearch'],
                    "youtube_" . $this->getAdapterOptions($settings)->settings['youtubePlaylist'],
                    "youtube_" . $this->getAdapterOptions($settings)->settings['youtubeChannel'],
                    "youtube_" . $this->getAdapterOptions($settings)->settings['youtubeType'],
                    "youtube_" . $this->getAdapterOptions($settings)->settings['youtubeLanguage'],
                    "youtube_" . $this->getAdapterOptions($settings)->settings['youtubeOrder']
                );
                break;
            case self::TYPE_VIMEO:
                $array =  array(
                    "vimeo_" . $this->getAdapterOptions($settings)->settings['vimeoChannel']
                );
                break;
            case self::TYPE_TX_NEWS:
                $array =  array(
                    "txnews_" . $this->getAdapterOptions($settings)->settings['newsCategories']
                );
                break;
        }

        return $array;
    }



}