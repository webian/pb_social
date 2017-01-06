<?php
namespace PlusB\PbSocial\Domain\Repository;

require_once \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('pb_social') . 'Resources/Private/Libs/tumblr/vendor/autoload.php';
require_once \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('pb_social') . 'Resources/Private/Libs/google/src/Google/autoload.php';

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2014 Mikolaj Jedrzejewski <mj@plusb.de>, plusB
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

/**
 * The repository for Items
 */
class ItemRepository extends \TYPO3\CMS\Extbase\Persistence\Repository
{
    /**
     * @param string $type
     * @param string $cacheIdentifier
     * @return array|\TYPO3\CMS\Extbase\Persistence\QueryResultInterface
     */
    public function findByTypeAndCacheIdentifier($type, $cacheIdentifier)
    {
        $query = $this->createQuery();
        $query->matching($query->logicalAnd($query->like('type', $type), $query->equals('cacheIdentifier', $cacheIdentifier)));
        return $query->execute();
    }

    /**
     * @param $feed
     */
    public function saveFeed($feed)
    {
        $this->add($feed);
        $this->persistenceManager->persistAll();
    }

    /**
     * @param $feed
     */
    public function updateFeed($feed)
    {
        $this->update($feed);
        $this->persistenceManager->persistAll();
    }

    /**
     * basic cURL example
     *
     * @param string $Url
     * @param bool $ignoreVerifySSL
     * @return mixed
     */
    public function curl_download($Url, $ignoreVerifySSL = false)
    {

        // is cURL installed yet?
        if (!function_exists('curl_init')) {
            die('Sorry cURL is not installed!');
        }

        // OK cool - then let's create a new cURL resource handle
        $ch = curl_init();

        // Now set some options (most are optional)

        // Set URL to download
        curl_setopt($ch, CURLOPT_URL, $Url);

        // Set a referer
//    curl_setopt($ch, CURLOPT_REFERER, 'http://www.example.org/yay.htm');

        // User agent
//    curl_setopt($ch, CURLOPT_USERAGENT, 'MozillaXYZ/1.0');

        // Include header in result? (0 = yes, 1 = no)
        curl_setopt($ch, CURLOPT_HEADER, 0);

        // Should cURL return or print out the data? (true = return, false = print)
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Timeout in seconds
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, !$ignoreVerifySSL);

        // Download the given URL, and return output
        $output = curl_exec($ch);

        // Log errors
        if (curl_error($ch)||curl_errno($ch)||false==$output) {
            error_log('|||||cURL errors|||||');
            error_log('Info: ' . json_encode(curl_getinfo($ch)));
            error_log('Error: ' . curl_error($ch));
            error_log('Error number: ' . curl_errno($ch));
            error_log('|||end cURL errors|||');
        }

        // Close the cURL resource, and free system resources
        curl_close($ch);

        return $output;
    }
}
