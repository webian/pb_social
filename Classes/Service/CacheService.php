<?php

namespace PlusB\PbSocial\Service;

use PlusB\PbSocial\Service\Base\AbstractBaseService;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

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

class CacheService extends AbstractBaseService
{

    const EXTKEY = 'pb_social';

    /**
     * @var \PlusB\PbSocial\Service\OptionService
     * @inject
     */
    protected $optionService;


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
     * @var int
     */
    protected $cacheLifetime = 3600;

    /**
     * @param int $cacheLifetime
     */
    public function setCacheLifetime($cacheLifetime)
    {
        $this->cacheLifetime = intval($cacheLifetime);
    }

    /**
     * @return int
     */
    public function getCacheLifetime()
    {
        return $this->cacheLifetime;
    }



    /**
     * @var FrontendInterface $cache
     */
    private $cache;

    protected function initializeConfiguration(){
        parent::initializeConfiguration();

        //merge cache lifetime settings
        $this->setCacheLifetime(
            intval(
                $this->settings['cacheLifetime']?:$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['pb_social_cache']['options']['defaultLifetime']?:0
            )
        );

        //get caching backend
        $this->cache = $this->cacheManager->getCache('pb_social_cache');
    }

    /**
     * combines array of strings which are different by their configuration issues
     * - calculating a crypted string to be able to find this again in cache for FE
     *
     * @param $cacheIdentifierElementsArray
     * @param $ttContentUid
     * @return string
     */
    private function calculateCacheIdentifier($cacheIdentifierElementsArray, $ttContentUid){
        array_walk($cacheIdentifierElementsArray, function (&$item, $key, $ttContentUid) {
            $item .= "_tt_content_uid". $ttContentUid ;
        }, $ttContentUid);

        return sha1(json_encode($cacheIdentifierElementsArray)); // in average json_encode is four times faster than serialize()
    }


    /**
     * getCacheContent - reads cache content by calculated cacheIdentifier
     *
     * @param $socialNetworkTypeString string
     * @param $settings array
     * @param $ttContentUid int
     * @param $results array - getting results, appending results if success
     * @return array
     */
    public function getCacheContent(
        $socialNetworkTypeString,
        $settings,
        $ttContentUid,
        &$results
    ){

        try {

            $cacheIdentifierElementsArray = $this->optionService->getCacheIdentifierElementsArray($socialNetworkTypeString, $settings);

            $cacheIdentifier = $this->calculateCacheIdentifier($cacheIdentifierElementsArray, $ttContentUid);

            //if there is not already a cache, try to get a api sync and get a filled cache, but it only gets this requested network type
            if($this->cache->has($cacheIdentifier) === false){
                $this->feedSyncService->syncFeed($socialNetworkTypeString, $settings, $ttContentUid, $isVerbose = false);
            }

            if($content = $this->cache->get($cacheIdentifier)){
                $results[] = $content;
            }

            return $results;
        } catch (\Exception $e) {
            if(isset($GLOBALS["BE_USER"])){
                $GLOBALS['BE_USER']->writelog($type = 4, $action = 0,  $error = 1, $details_nr = 1558354948, $details = '[pb_social] ' .$socialNetworkTypeString . ' flexform '. $ttContentUid.': ' . $e->getMessage(), $data = []);
            }else{
                $this->logger->warning('[pb_social] ' .$socialNetworkTypeString . ' flexform '. $ttContentUid.': ' . $e->getMessage());
            }
            return $results;
        }
    }

    /**
     * Sets given content to cache by calculated cacheIdentifier
     *
     * @param $socialNetworkTypeString string
     * @param $settings array
     * @param $ttContentUid int
     * @param $content
     */
    public function setCacheContent(
        $socialNetworkTypeString,
        $settings,
        $ttContentUid,
        $content
    ){
        $cacheIdentifierElementsArray = $this->optionService->getCacheIdentifierElementsArray($socialNetworkTypeString, $settings);
        $cacheIdentifier = $this->calculateCacheIdentifier($cacheIdentifierElementsArray, $ttContentUid);

        //todo set(, , array $tags = [], );
        $this->cache->set(
            $cacheIdentifier,
            $data = $content,
            $tags = array(self::EXTKEY),
            $lifetime = $this->getCacheLifetime()
        );
    }
}