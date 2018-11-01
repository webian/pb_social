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


    // wizards
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig(
        'mod {
                wizards.newContentElement.wizardItems.plugins {
                    elements {
                        socialfeed {
                            iconIdentifier = pb_social-plugin-socialfeed
                            title = LLL:EXT:'. $_EXTKEY .'/Resources/Private/Language/de.locallang_db.xlf:socialfeed_wizard.title
                            description = LLL:EXT:'. $_EXTKEY .'/Resources/Private/Language/de.locallang_db.xlf:socialfeed_wizard.description
                            tt_content_defValues {
                                CType = list
                                list_type = pbsocial_socialfeed
                            }
                        }
                    }
                    show = *
                }
           }'
    );

    $iconRegistry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Imaging\IconRegistry::class);
    $iconRegistry->registerIcon(
            'pb_social-plugin-socialfeed',
            \TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider::class,
            ['source' => 'EXT:'. $_EXTKEY .'/Resources/Public/Icons/pb_social_wizicon.png']
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