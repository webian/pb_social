<?php
if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

//\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
//	$_EXTKEY,
//	'Socialbar',
//	'SocialBar'
//);

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerPlugin(
    $_EXTKEY,
    'Socialfeed',
    'SocialFeed'
);

/**
 * Add Plugin CategoryTeaser to New Content Element Wizard
 */
if (TYPO3_MODE === 'BE') {
    // Add Plugin to CE Wizard
    $pluginSignature = str_replace('_', '', $_EXTKEY) . '_socialfeed';
    $TBE_MODULES_EXT['xMOD_db_new_content_el']['addElClasses'][$pluginSignature . '_wizicon'] = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY) . 'Resources/Private/Php/class.socialfeed_wizicon.php';
}

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript', 'Social Media Stream');

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('tx_pbsocial_domain_model_item', 'EXT:pb_social/Resources/Private/Language/locallang_csh_tx_pbsocial_domain_model_item.xlf');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_pbsocial_domain_model_item');
$GLOBALS['TCA']['tx_pbsocial_domain_model_item'] = array(
    'ctrl' => array(
        'title'    => 'LLL:EXT:pb_social/Resources/Private/Language/locallang_db.xlf:tx_pbsocial_domain_model_item',
        'label' => 'type',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'dividers2tabs' => true,

        'versioningWS' => 2,
        'versioning_followPages' => true,

        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'delete' => 'deleted',
        'enablecolumns' => array(
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime',
        ),
        'searchFields' => 'type,cacheidentifier,date,result,',
        'dynamicConfigFile' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY) . 'Configuration/TCA/Item.php',
        'iconfile' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath($_EXTKEY) . 'Resources/Public/Icons/tx_pbsocial_domain_model_item.gif'
    ),
);
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('tx_pbsocial_domain_model_credential', 'EXT:pb_social/Resources/Private/Language/locallang_csh_tx_pbsocial_domain_model_credential.xlf');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_pbsocial_domain_model_credential');
$GLOBALS['TCA']['tx_pbsocial_domain_model_credential'] = array(
    'ctrl' => array(
        'title'    => 'LLL:EXT:pb_social/Resources/Private/Language/locallang_db.xlf:tx_pbsocial_domain_model_credential',
        'label' => 'type',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'dividers2tabs' => true,

        'versioningWS' => 2,
        'versioning_followPages' => true,

        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'delete' => 'deleted',
        'enablecolumns' => array(
            'disabled' => 'hidden',
            'starttime' => 'starttime',
            'endtime' => 'endtime',
        ),
        'searchFields' => 'type,appId,expiration_date,access_token,valid',
        'dynamicConfigFile' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY) . 'Configuration/TCA/Credential.php',
        'iconfile' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath($_EXTKEY) . 'Resources/Public/Icons/tx_pbsocial_domain_model_credential.gif'
    ),
);

$extensionName = strtolower(\TYPO3\CMS\Core\Utility\GeneralUtility::underscoredToUpperCamelCase($_EXTKEY));

$pluginName_feed = strtolower('Socialfeed');
$pluginSignature_feed = $extensionName . '_' . $pluginName_feed;
$TCA['tt_content']['types']['list']['subtypes_excludelist'][$pluginSignature_feed] = 'layout,select_key,pages';
$TCA['tt_content']['types']['list']['subtypes_addlist'][$pluginSignature_feed] = 'pi_flexform';
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue($pluginSignature_feed, 'FILE:EXT:' . $_EXTKEY . '/Configuration/FlexForms/socialfeed.xml');

//$pluginName_bar = strtolower('SocialBar');
//$pluginSignature_bar = $extensionName.'_'.$pluginName_bar;
//$TCA['tt_content']['types']['list']['subtypes_excludelist'][$pluginSignature_bar] = 'layout,select_key,pages';
//$TCA['tt_content']['types']['list']['subtypes_addlist'][$pluginSignature_bar] = 'pi_flexform';
//\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPiFlexFormValue($pluginSignature_bar, 'FILE:EXT:'.$_EXTKEY . '/Configuration/FlexForms/socialbar.xml');
