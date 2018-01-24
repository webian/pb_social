<?php

namespace PlusB\PbSocial\Adapter;

$extensionPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('pb_social') . 'Resources/Private/Libs/';
require $extensionPath . 'facebook/src/Facebook/autoload.php';
use Facebook\Facebook;
use FluidTYPO3\Flux\Outlet\Pipe\Exception;
use PlusB\PbSocial\Domain\Model\Feed;
use PlusB\PbSocial\Domain\Model\Item;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2016 Ramon Mohi <rm@plusb.de>, plusB
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
class FacebookAdapter extends SocialMediaAdapter
{

    const TYPE = 'facebook';

    const api_url = 'https://graph.facebook.com';

    private $api;

    private $access_token;

    public function __construct($apiId, $apiSecret, $itemRepository)
    {
        parent::__construct($itemRepository);


        $extConf = @unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['pb_social']);
        $ignoreVerifySSL = $extConf['socialfeed.']['ignoreVerifySSL'] == '1' ? true : false;

        $this->api = new Facebook([
            'app_id' => $apiId,
            'app_secret' => $apiSecret,
            'default_graph_version' => 'v2.9',
        ]);

        // Get access_token via grant_type=client_credentials
        $url = 'https://graph.facebook.com/v2.8/oauth/access_token?client_id=' . $apiId . '&client_secret=' . $apiSecret . '&grant_type=client_credentials';

        $accessTokenResponse = $this->itemRepository->curl_download($url, $ignoreVerifySSL);
        if (($accessTokenJson = json_decode($accessTokenResponse)) != NULL) {
            $this->access_token = $accessTokenJson->access_token;
        }

        $this->api->setDefaultAccessToken($this->access_token);

//
//        $this->oAuth2Client = $this->api->getOAuth2Client();
//
//        $this->oAuth2Client->getLongLivedAccessToken($this->access_token);
//        $accessTokenMetadata = $this->oAuth2Client->debugToken($this->access_token);
//
//        if ($accessTokenMetadata->getExpiresAt() < new \DateTime()) {
//
//            error_log('facebook access token expired');
//            error_log(json_encode($accessTokenMetadata));
//            /**
//             * todo: we have to do something here if page access token has expired!
//             *
//             * the user should visit this page: https://developers.facebook.com/tools/explorer
//             * and generator new long lived page access token
//             */
//        }
//
//        $this->api->setDefaultAccessToken($this->access_token);
    }

    public function getResultFromApi($options)
    {
        $result = array();

        $facebookSearchIds = $options->settings['facebookSearchIds'];
        if (empty($facebookSearchIds)) {
            $this->logWarning('- no search term defined');
            return null;
        }

        foreach (explode(',', $facebookSearchIds) as $searchId) {
            $searchId = trim($searchId);
            $feeds = $this->itemRepository->findByTypeAndCacheIdentifier(self::TYPE, $searchId);
            if ($feeds && $feeds->count() > 0) {
                $feed = $feeds->getFirst();
                if ($options->devMod || ($feed->getDate()->getTimestamp() + $options->refreshTimeInMin * 60) < time()) {
                    try
                    {
                        $posts = $this->getPosts($searchId, $options->feedRequestLimit, $options->settings['facebookEdge']);
                        if ($posts !== null)
                        {
                            $feed->setDate(new \DateTime('now'));
                            $feed->setResult($posts);
                            $this->itemRepository->updateFeed($feed);
                        }
                    }
                    catch (\FacebookApiException $e)
                    {
                        $this->logError("feeds can't be updated - " . $e->getMessage());
                    }
                }
                $result[] = $feed;
                continue;
            }

            try {
                $feed = new Item(self::TYPE);
                $feed->setCacheIdentifier($searchId);
                $feed->setResult($this->getPosts($searchId, $options->feedRequestLimit, $options->settings['facebookEdge']));

                // save to DB and return current feed
                $this->itemRepository->saveFeed($feed);
                $result[] = $feed;
            } catch (\FacebookApiException $e) {
                $this->logError('initial load for feed failed - ' . $e->getMessage());
            }

        }

        return $this->getFeedItemsFromApiRequest($result, $options);
    }

    public function getFeedItemsFromApiRequest($result, $options)
    {
        $rawFeeds = array();
        $feedItems = array();

        //this can probably go in SocialMediaAdapter
        if (!empty($result)) {
            foreach ($result as $fb_feed) {
                $rawFeeds[self::TYPE . '_' . $fb_feed->getCacheIdentifier() . '_raw'] = $fb_feed->getResult();
                foreach ($fb_feed->getResult()->data as $rawFeed) {
                    if ($options->onlyWithPicture && (empty($rawFeed->picture) || empty($rawFeed->full_picture))) {
                        continue;
                    }
                    $feed = new Feed(self::TYPE, $rawFeed);
                    $feed->setId($rawFeed->id);
                    $feed->setText($this->trim_text($rawFeed->message, $options->textTrimLength, true));
                    if (property_exists($rawFeed, 'picture')) {
                        $feed->setImage(urldecode($rawFeed->picture));
                    }
                    // ouput link to facebook post instead of article
                    if ($options->settings['facebookLinktopost']) {
                        $feed->setLink('https://facebook.com/' . $rawFeed->id);
                    } else {
                        $feed->setLink($rawFeed->link);
                    }
                    $d = new \DateTime($rawFeed->created_time);
                    $feed->setTimeStampTicks($d->getTimestamp());

                    $feedItems[] = $feed;
                }
            }
        }

        return array('rawFeeds' => $rawFeeds, 'feedItems' => $feedItems);
    }

    /** Make API request via Facebook sdk function
     *
     * @param string $searchId
     * @param int $limit
     * @return string
     */
    public function getPosts($searchId, $limit, $edge)
    {
        // only posts or feed possible
        if ($edge == 'posts') {
            $request = 'posts';
        } else {
            $request = 'feed';
        }

        try {
            /** @var \Facebook\FacebookResponse $resp */
            $resp = $this->api->sendRequest(
                'GET',
                '/' . $searchId . '/' . $request,
                array(
                    'fields' => '
                        id,
                        link,
                        message,
                        picture,
                        comments.summary(total_count).limit(0).as(comments),
                        created_time,
                        full_picture,
                        reactions.summary(total_count).limit(0).as(reactions),
                        reactions.type(NONE).summary(total_count).limit(0).as(none),
                        reactions.type(LIKE).summary(total_count).limit(0).as(like),
                        reactions.type(LOVE).summary(total_count).limit(0).as(love),
                        reactions.type(WOW).summary(total_count).limit(0).as(wow),
                        reactions.type(HAHA).summary(total_count).limit(0).as(haha),
                        reactions.type(SAD).summary(total_count).limit(0).as(sad),
                        reactions.type(ANGRY).summary(total_count).limit(0).as(angry),
                        reactions.type(THANKFUL).summary(total_count).limit(0).as(thankful)
                        ',
                    'limit' => $limit
                    // 'include_hidden' => false,
                    // 'is_published' => true
                ),
                $this->access_token
            );
        } catch (\Exception $e) {
            $this->logWarning('request failed: ' . $e->getMessage());
            return null;
        }

        if (empty(json_decode($resp->getBody())->data) || json_encode($resp->getBody()->data) == null) {
            $this->logWarning('no posts found for ' . $searchId);
            return null;
        }

        // count reaction types
        $raw_body = json_decode($resp->getBody());
        for ($c = 0; $c < count($raw_body->data); $c++) {
            $reactions = $raw_body->data[$c]->reactions->data;
            $_reactions = array(
                'NONE' => $raw_body->data[$c]->none->summary->total_count,
                'LIKE' => $raw_body->data[$c]->like->summary->total_count,
                'LOVE' => $raw_body->data[$c]->love->summary->total_count,
                'WOW' => $raw_body->data[$c]->wow->summary->total_count,
                'HAHA' => $raw_body->data[$c]->haha->summary->total_count,
                'SAD' => $raw_body->data[$c]->sad->summary->total_count,
                'ANGRY' => $raw_body->data[$c]->angry->summary->total_count,
                'THANKFUL' => $raw_body->data[$c]->thankful->summary->total_count
            );
            if (is_array($reactions)) {
                foreach ($reactions as $reaction) {
                    if (in_array($reaction->type, $_reactions)) {
                        $_reactions[$reaction->type]++;
                    }
                }
            }
            $raw_body->data[$c]->reactions_detail = $_reactions;
        }

        return json_encode($raw_body);
    }

    public function getReactions($post_id)
    {
        /** @var \Facebook\FacebookResponse $resp */
        $resp = $this->api->sendRequest(
            'GET',
            '/' . $post_id . '/reactions',
            array(
                'limit' => 999
            ),
            $this->access_token
        );

        if (empty(json_decode($resp->getBody())->data)) {
            $this->logWarning('failed to get reactions for post ' . $post_id);
        }

        return $resp->getBody();
    }
}
