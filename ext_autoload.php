<?php
$extensionPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('pb_social') . 'Resources/Private/Libs/';

return array(
    'facebook'                      => $extensionPath . 'facebook/src/Facebook/autoload.php',
    'imgur'                         => $extensionPath . 'imgur/Imgur.php',
    'instagram'                     => $extensionPath . 'instagram/src/Instagram.php',
    'twitterAPIExchange'            => $extensionPath . 'twitter/TwitterAPIExchange.php',
    'twitterOAuth'                  => $extensionPath . 'twitteroauth/autoload.php',
    'google'                        => $extensionPath . 'google/src/Google/autoload.php',
    'pinterest'                     => $extensionPath . 'pinterest/autoload.php',
    //'tumblr_autoload'               => $extensionPath . 'tumblr/vendor/autoload.php',
);
