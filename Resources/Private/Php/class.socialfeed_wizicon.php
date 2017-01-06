<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015 Sergej Junker <sergej.junker@plusb.de>, plus B
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
 * Class that adds the socialfeeds wizard icon.
 */
class pbsocial_socialfeed_wizicon
{

    /** @var string */
    protected $extKey = '';
    /** @var string  */
    protected $plugin = '';
    /** @var string  */
    protected $pluginSignature = '';
    /** @var \TYPO3\CMS\Lang\LanguageService */
    protected $LANG;

    public function __construct()
    {
        $this->extKey = 'pb_social';
        $this->plugin = 'socialfeed';
        $this->pluginSignature = strtolower('pbsocial_' . $this->plugin);
        $this->LANG =& $GLOBALS['LANG'];
    }

    /**
     * Processing the wizard items array
     *
     * @param array $wizardItems: The wizard items
     * @return array Modified array with wizard items
     */
    public function proc($wizardItems)
    {
        $wizardItems['plugins_tx_' . $this->extKey] = array(
            'icon' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath($this->extKey) . 'Resources/Public/Icons/pb_logo.gif',
            'title' => $GLOBALS['LANG']->sL('LLL:EXT:pb_social/Resources/Private/Language/de.locallang_db.xlf:socialfeed_wizard.title'),
            'description' => $GLOBALS['LANG']->sL('LLL:EXT:pb_social/Resources/Private/Language/de.locallang_db.xlf:socialfeed_wizard.description'),
            'params' => '&defVals[tt_content][CType]=list&defVals[tt_content][list_type]=' . $this->pluginSignature
        );
        return $wizardItems;
    }
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pb_social/Resources/Private/Php/class.socialfeed_wizicon.php']) {
    include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pb_social/Resources/Private/Php/class.socialfeed_wizicon.php']);
}
