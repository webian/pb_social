<?php
if (!defined('TYPO3_MODE')) {
    die('Access denied.');
}


call_user_func(function () {

        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
            'PlusB.PbSocial',
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
            \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript('pb_social','constants',' <INCLUDE_TYPOSCRIPT: source="FILE:EXT:pb_social/Configuration/TypoScript/constants.txt">');
            // Setup
            \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScript('pb_social','setup',' <INCLUDE_TYPOSCRIPT: source="FILE:EXT:pb_social/Configuration/TypoScript/setup.txt">');

            $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][] = 'PlusB\\PbSocial\\Command\\PBSocialCommandController';

            // Include new content elements to modWizards
            \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig(
                '<INCLUDE_TYPOSCRIPT: source="FILE:EXT:pb_social/Configuration/PageTSconfig/PbSocialSocialfeed.ts">'
            );

            $iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class);
            $iconRegistry->registerIcon(
                    'pbsocial_socialfeed',
                    \TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider::class,
                    ['source' => 'EXT:pb_social/Resources/Public/Icons/Extension.svg']
                );
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

});
