<?php
if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'PlusB.' . $_EXTKEY,
    'Socialfeed',
    array(
        'Item' => 'showSocialFeed',

    ),
    // non-cacheable actions
    array(
        'Item' => 'showSocialFeed,facebookReaction',

    )
);


if(TYPO3_MODE === 'BE') {

    // Constants
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript($_EXTKEY,'constants',' <INCLUDE_TYPOSCRIPT: source="FILE:EXT:'. $_EXTKEY .'/Configuration/TypoScript/constants.txt">');

    // Setup
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript($_EXTKEY,'setup',' <INCLUDE_TYPOSCRIPT: source="FILE:EXT:'. $_EXTKEY .'/Configuration/TypoScript/setup.txt">');

    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][] = 'PlusB\\PbSocial\\Command\\PBSocialCommandController';
}

/**
 * register cache
 */
if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['pb_social_cache'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['pb_social_cache'] = array();
}
if( !isset($TYPO3_CONF_VARS['SYS']['caching']['cacheConfigurations']['pb_social_cache']['groups'] ) ) {
    $TYPO3_CONF_VARS['SYS']['caching']['cacheConfigurations']['pb_social_cache']['groups'] = array( 'system' );
}