<?php


namespace PlusB\PbSocial\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * Class Content
 *
 * @package PlusB\PbSocial\Domain\Model
 */
class Content extends AbstractEntity
{
    /**
     * @var string
     */
    protected $ctype;

    /**
     * @var string
     */
    protected $piFlexform;

    /**
     * @return string
     */
    public function getCtype()
    {
        return $this->ctype;
    }

    /**
     * @param string $ctype
     */
    public function setCtype($ctype)
    {
        $this->ctype = $ctype;
    }

    /**
     * @return string
     */
    public function getPiFlexform()
    {
        return $this->piFlexform;
    }

    /**
     * @param string $piFlexform
     */
    public function setPiFlexform($piFlexform)
    {
        $this->piFlexform = $piFlexform;
    }




}