<?php

namespace PlusB\PbSocial\Service;

use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;


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

trait LogTrait
{
    protected $logger;
    private $extkey = 'pb_social';

    private function initializeTrait()
    {
        /** @var $logger Logger */
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);

    }

    /**
     * @param string $message
     * @param integer $ttContentUid actual plugin uid
     * @param integer $ttContentPid actual uid of page, plugin is located
     * @param string $type Name of social media network
     * @param integer $locationInCode timestamp to find in code
     * @return string
     */
    private function initializeMessage($message, $ttContentUid, $ttContentPid, $type, $locationInCode){
        return $this->extkey . " - flexform $ttContentUid on page $ttContentPid tab ".$type. ": $locationInCode " . strval($message);
    }

    /**
     * @param string $message
     * @param integer $ttContentUid actual plugin uid
     * @param integer $ttContentPid actual uid of page, plugin is located
     * @param string $type Name of social media network
     * @param integer $locationInCode timestamp to find in code
     */
    public function logError($message, $ttContentUid, $ttContentPid, $type, $locationInCode = 0)
    {
        $this->initializeTrait();
        if(isset($GLOBALS["BE_USER"])){
            $GLOBALS['BE_USER']->writelog(
                $type = 4, $action = 0,  $error = 1, $details_nr = $locationInCode,
                $details = $this->initializeMessage($message, $ttContentUid, $ttContentPid, $type, $locationInCode),
                $data = []);
        }
        $this->logger->error($this->initializeMessage($message, $ttContentUid, $ttContentPid, $type, $locationInCode));
    }

    /**
     * @param string $message
     * @param integer $ttContentUid actual plugin uid
     * @param integer $ttContentPid actual uid of page, plugin is located
     * @param string $type Name of social media network
     * @param integer $locationInCode timestamp to find in code
     */
    public function logWarning($message, $ttContentUid, $ttContentPid, $type, $locationInCode = 0)
    {
        $this->initializeTrait();
        $this->logger->warning($this->initializeMessage($message, $ttContentUid, $ttContentPid, $type, $locationInCode));
    }



    /**
     * @param string $message
     * @param integer $ttContentUid actual plugin uid
     * @param integer $ttContentPid actual uid of page, plugin is located
     * @param string $type Name of social media network
     * @param integer $locationInCode timestamp to find in code
     */
    public function logInfo($message, $ttContentUid, $ttContentPid, $type, $locationInCode = 0)
    {
        $this->initializeTrait();
        $this->logger->info($this->initializeMessage($message, $ttContentUid, $ttContentPid, $type, $locationInCode));
    }






}