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
        'Item' => 'showSocialFeed',
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
// Register cache frontend for proxy class generation
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['pb_social_cache'] = array(
    'groups' => array(
        'system'
    ),
    'options' => array(
        'defaultLifetime' => 3600,
    )
);