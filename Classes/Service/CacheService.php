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

class CacheService extends AbstractBaseService
{

    const EXTKEY = 'pb_social';

    /**
     * @var \PlusB\PbSocial\Service\OptionService
     * @inject
     */
    protected $optionService;


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
     * @param $cacheObject FrontendInterface
     * @param $results array - getting results, appending results if success
     * @return array
     */
    public function getCacheContent(
        $socialNetworkTypeString,
        $settings,
        $ttContentUid,
        $cacheObject,
        &$results
    ){

        try {

            $cacheIdentifierElementsArray = $this->optionService->getCacheIdentifierElementsArray($socialNetworkTypeString, $settings);

            if ($content = $cacheObject->get($this->calculateCacheIdentifier($cacheIdentifierElementsArray, $ttContentUid))) { // the cached content is available, appending results if success
                $results[] = $content;
            }
            return $results;
        } catch (\Exception $e) {
            $GLOBALS['BE_USER']->simplelog(var_export($socialNetworkTypeString) . ': ' . $e->getMessage(), self::EXTKEY, 1);
            return $results;
        }
    }

    /**
     * aölksdkjf öasldk jföalsdk jfölasdk jfölaskd jfölaskd jfölaskdj fölasdkj
     *
     * @param $socialNetworkTypeString string
     * @param $settings array
     * @param $ttContentUid int
     * @param $cacheObject FrontendInterface
     * @param $content
     */
    public function setCacheContent(
        $socialNetworkTypeString,
        $settings,
        $ttContentUid,
        $cacheObject,
        $content
    ){
        $cacheIdentifierElementsArray = $this->optionService->getCacheIdentifierElementsArray($socialNetworkTypeString, $settings);

        //todo set($entryIdentifier, $data, array $tags = [], $lifetime = null);
        $cacheObject->set($this->calculateCacheIdentifier(
            $entryIdentifier = $cacheIdentifierElementsArray, $ttContentUid),
            $data = $content,
            $tags = array(self::EXTKEY),
            $lifetime = null
        );
    }




}