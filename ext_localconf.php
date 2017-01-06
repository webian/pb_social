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

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][] = 'PlusB\\PbSocial\\Command\\PBSocialCommandController';

/**
 * register cache
 */
if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['pb_social_cache'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['pb_social_cache'] = array();
}
if( !isset($TYPO3_CONF_VARS['SYS']['caching']['cacheConfigurations']['pb_social_cache']['groups'] ) ) {
    $TYPO3_CONF_VARS['SYS']['caching']['cacheConfigurations']['pb_social_cache']['groups'] = array( 'system' );
}