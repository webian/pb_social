<?php
namespace PlusB\PbSocial\Domain\Repository;

use PlusB\PbSocial\Domain\Model;
use Tumblr\API\Client;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

require_once \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('pb_social') . 'Resources/Private/Libs/tumblr/vendor/autoload.php';


/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2014 Mikolaj Jedrzejewski <mj@plusb.de>, plusB
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
 * The repository for Items
 */
class ItemRepository extends \TYPO3\CMS\Extbase\Persistence\Repository {
    /**
     * @param $type
     * @param $settings
     * @return array
     * @throws Exception
     */
    function findFeedsByType($type, $settings) {
        $logger = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Core\Log\LogManager')->getLogger(__CLASS__);
        $extConf = @unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['pb_social']); //TODO => search for a better way of accessing extconf
        $devMod = $extConf['socialfeed.']['devmod'] == "1" ? true : false;
        $twitterHideRetweets = empty($settings['twitterHideRetweets']) ? false : ($settings['twitterHideRetweets'] == "1" ? true : false);
        $twitterShowOnlyImages = empty($settings['twitterShowOnlyImages']) ? false : ($settings['twitterShowOnlyImages'] == "1" ? true : false);
        $tumblrShowOnlyImages = empty($settings['tumblrShowOnlyImages']) ? false : ($settings['tumblrShowOnlyImages'] == "1" ? true : false);
        $feedRequestLimit = intval(empty($settings['feedRequestLimit']) ? "10" : $settings['feedRequestLimit']);
        $refreshTimeInMin = intval(empty($settings['refreshTimeInMin']) ? "10" : $settings['refreshTimeInMin']);
        if ($refreshTimeInMin == 0) {
            $refreshTimeInMin = 10;
        } //reset to 10 if intval() cant convert
        $result = array();

        switch ($type) {

            //
            // FACEBOOK
            //
            case "facebook":
                $config_apiId = $extConf['socialfeed.']['facebook.']['api.']['id'];
                $config_apiSecret = $extConf['socialfeed.']['facebook.']['api.']['secret'];
                if (empty($config_apiId) || empty($config_apiSecret)) {
                    $logger->warning($type . ' credentials not set');
                    break;
                }

                $facebookSearchIds = $settings['facebookSearchIds'];
                if (empty($facebookSearchIds)) {
                    $logger->warning($type . ' - no search term defined');
                    break;
                }

                $facebook = new \Facebook(array('appId' => $config_apiId, 'secret' => $config_apiSecret));
                foreach (explode(",", $facebookSearchIds) as $searchId) {
                    $searchId = trim($searchId);
                    $feeds = $this->findByTypeAndCacheIdentifier($type, $searchId);

                    // /links? /statuses? /tagged?
                    $url = '/' . $searchId . '/posts?filter=app_2392950137&limit=' . $feedRequestLimit;

                    if ($feeds && $feeds->count() > 0) {
                        $feed = $feeds->getFirst();

                        if ($devMod || ($feed->getDate()->getTimestamp() + $refreshTimeInMin * 60) < time()) {
                            try {
                                $feed->setDate(new \DateTime('now'));
                                $feed->setResult(json_encode($facebook->api($url)));
                                $this->update($feed);
                            } catch (\FacebookApiException $e) {
                                $logger->warning($type . ' feeds can\'t be updated', array("data" => $e->getMessage())); //TODO => handle FacebookApiException
                            }
                        }
                        $result[] = $feed;
                        continue;
                    }

                    try {
                        $feed = new Model\Item($type);
                        $feed->setCacheIdentifier($searchId);
                        $feed->setResult(json_encode($facebook->api($url)));

                        // save to DB and return current feed
                        $this->saveFeed($feed);
                        $result[] = $feed;

                    } catch (\FacebookApiException $e) {
                        $logger->warning('initial load for ' . $type . ' feeds failed', array("data" => $e->getMessage())); //TODO => handle FacebookApiException
                    }
                }
                break;

            //
            // GOOGLE
            //
            case "googleplus":
                $config_appKey = $extConf['socialfeed.']['googleplus.']['app.']['key'];
                if (empty($config_appKey)) {
                    $logger->warning($type . ' credentials not set');
                    break;
                }

                $googlePlusSearchIds = $settings['googleSearchIds'];
                if (empty($googlePlusSearchIds)) {
                    $logger->warning($type . ' - no search term defined');
                    break;
                }

                $headers = array('Content-Type: application/json',);
                $fields = array('key' => $config_appKey, 'format' => 'json', 'ip' => $_SERVER['REMOTE_ADDR']);

                foreach (explode(",", $googlePlusSearchIds) as $searchId) {
                    $searchId = trim($searchId);
                    $feeds = $this->findByTypeAndCacheIdentifier($type, $searchId);
                    $url = 'https://www.googleapis.com/plus/v1/people/' . $searchId .
                        '/activities/public?maxResults=' . $feedRequestLimit . '&' . http_build_query($fields);

                    if ($feeds && $feeds->count() > 0) {
                        $feed = $feeds->getFirst();
                        if ($devMod || ($feed->getDate()->getTimestamp() + $refreshTimeInMin * 60) < time()) {
                            try {
                                $ch = curl_init();
                                curl_setopt($ch, CURLOPT_URL, $url);
                                curl_setopt($ch, CURLOPT_POST, false);
                                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

                                $curl_response = curl_exec($ch);

                                // check if google error object is set => throw exception with json response
                                if (property_exists(json_decode($curl_response), "error")) {
                                    throw new \Exception($curl_response);
                                }

                                $feed->setDate(new \DateTime('now'));
                                $feed->setResult($curl_response);
                                $this->update($feed);
                                curl_close($ch);
                            } catch (\Exception $e) {
                                $logger->error($type . ' feeds cant be updated', array("data" => $e->getMessage()));
                            }
                        }
                        $result[] = $feed;
                        continue;
                    }

                    try {
                        $feed = new Model\Item($type);
                        $feed->setCacheIdentifier($searchId);

                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_POST, false);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

                        $curl_response = curl_exec($ch);

                        // check if google error object is set => throw exception with json response
                        if (property_exists(json_decode($curl_response), "error")) {
                            throw new \Exception($curl_response);
                        }

                        $feed->setResult($curl_response);
                        curl_close($ch);

                        // save to DB and return current feed
                        $this->saveFeed($feed);
                        $result[] = $feed;

                    } catch (\Exception $e) {
                        $logger->error('initial load for ' . $type . ' feeds failed', array("data" => $e->getMessage()));
                    }
                }
                break;

            //
            // INSTAGRAM
            // https://github.com/cosenary/Instagram-PHP-API
            case "instagram":
                $config_clientId = $extConf['socialfeed.']['instagram.']['client.']['id'];
                if (empty($config_clientId)) {
                    $logger->warning($type . ' credentials not set');
                    break;
                }

                $instagramHashTags = $settings['instagramHashTag'];
                $instagramSearchIds = $settings['instagramSearchIds'];

                if (empty($instagramSearchIds) && empty($instagramHashTags)) {
                    $logger->warning($type . ' - no search term defined');
                }

                $instagram = new \Instagram($config_clientId);
                foreach (explode(",", $instagramSearchIds) as $searchId) {
                    $searchId = trim($searchId);
                    $feeds = $this->findByTypeAndCacheIdentifier($type, $searchId);

                    if ($feeds && $feeds->count() > 0) {
                        $feed = $feeds->getFirst();
                        if ($devMod || ($feed->getDate()->getTimestamp() + $refreshTimeInMin * 60) < time()) {
                            try {
                                $feed->setDate(new \DateTime('now'));
                                $feed->setResult(json_encode($instagram->getUserMedia($searchId, $feedRequestLimit)));
                                $this->update($feed);
                            } catch (\Exception $e) {
                                $logger->error($type . ' feeds cant be updated', array("data" => $e->getMessage()));
                            }
                        }
                        $result[] = $feed;
                        continue;
                    }

                    try {
                        $feed = new Model\Item($type);
                        $feed->setCacheIdentifier($searchId);
                        $feed->setResult(json_encode($instagram->getUserMedia($searchId, $feedRequestLimit)));

                        // save to DB and return current feed
                        $this->saveFeed($feed);
                        $result[] = $feed;

                    } catch (\Exception $e) {
                        $logger->error('initial load for ' . $type . ' feeds failed', array("data" => $e->getMessage()));
                    }
                }

                foreach (explode(",", $instagramHashTags) as $searchId) {
                    $searchId = trim($searchId);
                    $feeds = $this->findByTypeAndCacheIdentifier($type, $searchId);

                    if ($feeds && $feeds->count() > 0) {
                        $feed = $feeds->getFirst();
                        if ($devMod || ($feed->getDate()->getTimestamp() + $refreshTimeInMin * 60) < time()) {
                            try {
                                $feed->setDate(new \DateTime('now'));
                                $feed->setResult(json_encode($instagram->getTagMedia($searchId, $feedRequestLimit)));
                                $this->update($feed);
                            } catch (\Exception $e) {
                                $logger->error($type . ' feeds cant be updated', array("data" => $e->getMessage()));
                            }
                        }
                        $result[] = $feed;
                        continue;
                    }

                    try {
                        $feed = new Model\Item($type);
                        $feed->setCacheIdentifier($searchId);
                        $feed->setResult(json_encode($instagram->getTagMedia($searchId, $feedRequestLimit)));

                        // save to DB and return current feed
                        $this->saveFeed($feed);
                        $result[] = $feed;

                    } catch (\Exception $e) {
                        $logger->error('initial load for ' . $type . ' feeds failed', array("data" => $e->getMessage()));
                    }
                }
                break;

            //
            // TWITTER
            //
            case "twitter":
                $apiParameters = '';
                $config_consumerKey = $extConf['socialfeed.']['twitter.']['consumer.']['key'];
                $config_consumerSecret = $extConf['socialfeed.']['twitter.']['consumer.']['secret'];
                $config_accessToken = $extConf['socialfeed.']['twitter.']['oauth.']['access.']['token'];
                $config_accessTokenSecret = $extConf['socialfeed.']['twitter.']['oauth.']['access.']['token_secret'];
                if (empty($config_consumerKey) || empty($config_consumerSecret) || empty($config_accessToken) || empty($config_accessTokenSecret)) {
                    $logger->warning($type . ' credentials not set');
                    break;
                }

                $twitterSearchFieldValues = $settings['twitterSearchFieldValues'];
                $twitterProfilePosts = $settings['twitterProfilePosts'];
                $twitterLanguage = $settings['twitterLanguage'];
                $twitterGeoCode = $settings['twitterGeoCode'];

                $requestMethod = 'GET';
                $url = 'https://api.twitter.com/1.1/search/tweets.json';
                $twitter = new \TwitterAPIExchange(array(
                    'oauth_access_token' => $config_accessToken,
                    'oauth_access_token_secret' => $config_accessTokenSecret,
                    'consumer_key' => $config_consumerKey,
                    'consumer_secret' => $config_consumerSecret
                ));

                if (empty($twitterSearchFieldValues) && empty($twitterProfilePosts)) {
                    $logger->warning($type . ' - no search term defined');
                    break;
                }

                // because of the amount of data twitter is sending, the database can only carry 20 tweets.
                // 20 Tweets = ~86000 Character
                if ($feedRequestLimit > 20) {
                    $feedRequestLimit = 20;
                }
                if ($twitterHideRetweets) {
                    $apiParameters .= "+exclude%3Aretweets";
                }
                if ($twitterShowOnlyImages) {
                    $apiParameters .= "+filter%3Aimages";
                }
                if ($twitterLanguage) {
                    $apiParameters .= "&lang=" . $twitterLanguage;
                }
                if ($twitterGeoCode) {
                    $apiParameters .= "&geocode=" . $twitterGeoCode;
                }

                if ($twitterSearchFieldValues) {
                    foreach (explode(",", $twitterSearchFieldValues) as $searchValue) {
                        $searchValue = trim($searchValue);
                        $feeds = $this->findByTypeAndCacheIdentifier($type, $searchValue);

                        //https://dev.twitter.com/rest/reference/get/search/tweets
                        //include_entities=false => The entities node will be disincluded when set to false.

                        $getfield = '?q=' . $searchValue . $apiParameters . '&count=' . $feedRequestLimit;

                        if ($feeds && $feeds->count() > 0) {
                            $feed = $feeds->getFirst();
                            if ($devMod || ($feed->getDate()->getTimestamp() + $refreshTimeInMin * 60) < time()) {
                                try {
                                    $feed->setDate(new \DateTime('now'));
                                    $feed->setResult($twitter->setGetfield($getfield)->buildOauth($url, $requestMethod)->performRequest());
                                    $this->update($feed);

                                } catch (\Exception $e) {
                                    $logger->error($type . ' feeds can\'t be updated', array("data" => $e->getMessage()));
                                }
                            }
                            $result[] = $feed;
                            continue;
                        }

                        try {
                            $feed = new Model\Item($type);
                            $feed->setCacheIdentifier($searchValue);
                            $feed->setResult($twitter->setGetfield($getfield)->buildOauth($url, $requestMethod)->performRequest());

                            // save to DB and return current feed
                            $this->saveFeed($feed);
                            $result[] = $feed;

                        } catch (\Exception $e) {
                            $logger->error('initial load for ' . $type . ' feeds failed', array("data" => $e->getMessage()));
                        }
                    }
                }

                if ($twitterProfilePosts) {
                    foreach (explode(",", $twitterProfilePosts) as $searchValue) {
                        $searchValue = trim($searchValue);
                        $feeds = $this->findByTypeAndCacheIdentifier($type, $searchValue);

                        //https://dev.twitter.com/rest/reference/get/search/tweets
                        //include_entities=false => The entities node will be disincluded when set to false.

                        $getfield = '?q=from:' . $searchValue . $apiParameters . '&count=' . $feedRequestLimit;
                        if ($feeds && $feeds->count() > 0) {
                            $feed = $feeds->getFirst();
                            if ($devMod || ($feed->getDate()->getTimestamp() + $refreshTimeInMin * 60) < time()) {
                                try {
                                    $feed->setDate(new \DateTime('now'));
                                    $feed->setResult($twitter->setGetfield($getfield)->buildOauth($url, $requestMethod)->performRequest());
                                    $this->update($feed);

                                } catch (\Exception $e) {
                                    $logger->error($type . ' feeds can\'t be updated', array("data" => $e->getMessage()));
                                }
                            }
                            $result[] = $feed;
                            continue;
                        }

                        try {
                            $feed = new Model\Item($type);
                            $feed->setCacheIdentifier($searchValue);
                            $feed->setResult($twitter->setGetfield($getfield)->buildOauth($url, $requestMethod)->performRequest());

                            // save to DB and return current feed
                            $this->saveFeed($feed);
                            $result[] = $feed;

                        } catch (\Exception $e) {
                            $logger->error('initial load for ' . $type . ' feeds failed', array("data" => $e->getMessage()));
                        }
                    }
                }
                break;

            //
            // TUMBLR
            // https://github.com/tumblr/tumblr.php
            case "tumblr":
                $config_consumerKey = $extConf['socialfeed.']['tumblr.']['consumer.']['key'];
                $config_consumerSecret = $extConf['socialfeed.']['tumblr.']['consumer.']['secret'];
                $config_Token = $extConf['socialfeed.']['tumblr.']['token'];
                $config_TokenSecret = $extConf['socialfeed.']['tumblr.']['token_secret'];

                $tumblrHashtag = strtolower(str_replace('#', '', $settings['tumblrHashTag']));

                if (empty($config_consumerKey) || empty($config_consumerSecret) || empty($config_Token) || empty($config_TokenSecret)) {
                    $logger->warning($type . ' credentials not set');
                    break;
                }

                $tumblrBlogNames = $settings['tumblrBlogNames'];
                if (empty($tumblrBlogNames)) {
                    $logger->warning($type . ' - no blog names for search term defined');
                    break;
                }

                $tumblr = new Client($config_consumerKey, $config_consumerSecret);
                $tumblr->setToken($config_Token, $config_TokenSecret);

                foreach (explode(",", $tumblrBlogNames) as $blogName) {
                    $feeds = $this->findByTypeAndCacheIdentifier($type, $blogName);
                    if ($feeds && $feeds->count() > 0) {
                        $feed = $feeds->getFirst();
                        if ($devMod || ($feed->getDate()->getTimestamp() + $refreshTimeInMin * 60) < time()) {
                            try {
                                $feed->setDate(new \DateTime('now'));
                                if ($blogName == "MYDASHBOARD") {
                                    $feed->setResult(json_encode($tumblr->getDashboardPosts(array('limit' => $feedRequestLimit))));
                                } else {
                                    if ($tumblrHashtag !== '') {
                                        if ($tumblrShowOnlyImages) {
                                            $feed->setResult(json_encode($tumblr->getBlogPosts($blogName, array('limit' => $feedRequestLimit, 'type' => 'photo', 'tag' => $tumblrHashtag, 'filter' => 'text'))));
                                        } else {
                                            $feed->setResult(json_encode($tumblr->getBlogPosts($blogName, array('limit' => $feedRequestLimit, 'tag' => $tumblrHashtag, 'filter' => 'text'))));
                                        }
                                    } else {
                                        if ($tumblrShowOnlyImages) {
                                            $feed->setResult(json_encode($tumblr->getBlogPosts($blogName, array('limit' => $feedRequestLimit, 'type' => 'photo', 'filter' => 'text'))));
                                        } else {
                                            $feed->setResult(json_encode($tumblr->getBlogPosts($blogName, array('limit' => $feedRequestLimit, 'filter' => 'text'))));
                                        }
                                    }
                                }
                                $this->update($feed);
                            } catch (\Exception $e) {
                                $logger->error($type . ' feeds cant be updated', array("data" => $e->getMessage()));
                            }
                        }
                        $result[] = $feed;
                        continue;
                    }

                    try {
                        $feed = new Model\Item($type);
                        $feed->setCacheIdentifier($blogName);

                        if ($blogName == "MYDASHBOARD") {
                            $feed->setResult(json_encode($tumblr->getDashboardPosts(array('limit' => $feedRequestLimit))));
                        } else {
                            if ($tumblrHashtag !== '') {
                                if ($tumblrShowOnlyImages) {
                                    $feed->setResult(json_encode($tumblr->getBlogPosts($blogName, array('limit' => $feedRequestLimit, 'type' => 'photo', 'tag' => $tumblrHashtag, 'filter' => 'text'))));
                                } else {
                                    $feed->setResult(json_encode($tumblr->getBlogPosts($blogName, array('limit' => $feedRequestLimit, 'tag' => $tumblrHashtag, 'filter' => 'text'))));
                                }
                            } else {
                                if ($tumblrShowOnlyImages) {
                                    $feed->setResult(json_encode($tumblr->getBlogPosts($blogName, array('limit' => $feedRequestLimit, 'type' => 'photo', 'filter' => 'text'))));
                                } else {
                                    $feed->setResult(json_encode($tumblr->getBlogPosts($blogName, array('limit' => $feedRequestLimit, 'filter' => 'text'))));
                                }
                            }
                        }

                        // save to DB and return current feed
                        $this->saveFeed($feed);
                        $result[] = $feed;

                    } catch (\Exception $e) {
                        $logger->error('initial load for ' . $type . ' feeds failed', array("data" => $e->getMessage()));
                    }
                }
                break;

            //
            // DUMMY
            //
            case "dummy":
                // TODO => set some configuration "ext/pb_social/ext_conf_template.txt"
//                $config_Key= $extConf['socialfeed.']['dummy.']['key'];
//                $config_Secret= $extConf['socialfeed.']['dummy.']['secret'];
//                if(empty($config_Key) || empty($config_Secret)){
//                    $logger->error($type.' credentials not set');
//                    break;
//                }
//
                // TODO => move search params to flexform for usability
//              //  $dummyFlexFormSearchTerm = $settings['dummyFlexFormSearchTerm'];
//                if(empty($dummyFlexFormSearchTerm)){
//                    $logger->error($type.' - no blog names for search term defined');
//                    break;
//                }

                foreach (explode(",", "comma,seperated,list,of,search,values,or,ids") as $searchValueOrId) {
                    $feeds = $this->findByTypeAndCacheIdentifier($type, $searchValueOrId);

                    if ($feeds && $feeds->count() > 0) {
                        $feed = $feeds->getFirst();
                        if ($devMod || ($feed->getDate()->getTimestamp() + $refreshTimeInMin * 60) < time()) {
                            try {
                                // TODO => GET SOME DATA FROM PROVIDER AND UPDATE INTO FEED-ITEM
                                $feed->setResult(json_encode("NEW_AWESOME_DATA"));
                                $feed->setDate(new \DateTime('now'));   // update feed modify time
                                $this->update($feed);
                            } catch (\Exception $e) {
                                // log any Exception and add them to typo3temp/log file
                                $logger->warning($type . ' feeds cant be updated', $e);
                            }
                        }
                        $result[] = $feed;
                        continue;
                    }

                    try {
                        $feed = new Model\Item($type);                  // type of your feed
                        $feed->setCacheIdentifier($searchValueOrId);    // identifier for your request

                        // TODO => GET SOME DATA FROM YOUR PROVIDER AND INSERT THEM INTO NEW FEED-ITEM
                        $feed->setResult(json_encode("NEW_AWESOME_DATA"));

                        $this->saveFeed($feed);     // save to DB
                        $result[] = $feed;           // return current feed

                    } catch (\Exception $e) {
                        // log any Exception and add them to typo3temp/log file
                        $logger->warning('initial load for ' . $type . ' feeds failed', $e);
                    }
                }
                break;
        }

        return $result;
    }

    /**
     * @param string $type
     * @param string $cacheIdentifier
     * @return array|\TYPO3\CMS\Extbase\Persistence\QueryResultInterface
     */
    function findByTypeAndCacheIdentifier($type, $cacheIdentifier) {
        $query = $this->createQuery();
        $query->matching($query->logicalAnd($query->like('type', $type), $query->equals('cacheIdentifier', $cacheIdentifier)));
        return $query->execute();
    }

    /**
     * @param $feed
     */
    function saveFeed($feed) {
        $this->add($feed);
        $this->persistenceManager->persistAll(); // TODO => check if necessary
    }
}