<?php
namespace PlusB\PbSocial\Domain\Repository;

#require_once \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('pb_social') . 'Resources/Private/Libs/tumblr/vendor/autoload.php';

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2014 Mikolaj Jedrzejewski <mj@plusb.de>, plus B
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
        $query->matching(
            $query->logicalAnd(
                array(
                    $query->like('type', $type),
                    $query->equals('cacheIdentifier', $cacheIdentifier
                    )
                )
            )
        );
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
}
