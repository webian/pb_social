<?php

namespace PlusB\PbSocial\Domain\Repository;


use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;

class ContentRepository extends \TYPO3\CMS\Extbase\Persistence\Repository
{

    public function initializeObject()
    {
        /** @var $defaultQuerySettings Typo3QuerySettings */
        $defaultQuerySettings = $this->objectManager->get(Typo3QuerySettings::class);

        // don't add the pid constraint
        $defaultQuerySettings->setRespectStoragePage(false);
        $this->setDefaultQuerySettings($defaultQuerySettings);
    }

    /**
     * @param string $ctype
     * @param string $list_type
     * @return array|\TYPO3\CMS\Extbase\Persistence\QueryResultInterface
     */
    public function findFlexforms($ctype, $list_type)
    {
        $query = $this->createQuery();

        $query->matching(
            $query->logicalAnd(
                array(
                    $query->equals('ctype', $ctype),
                    $query->equals('list_type', $list_type),
                )
            )
        );

        return $query->execute();
    }


}