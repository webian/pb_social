<?php
if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'PlusB.' . $_EXTKEY,
    'Socialfeed',
    array(
        'Item' => 'showSocialFeed,facebookReaction',

    ),
    // non-cacheable actions
    array(
        'Item' => 'showSocialFeed,facebookReaction',

    )
);

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][] = 'PlusB\\PbSocial\\Command\\PBSocialCommandController';
