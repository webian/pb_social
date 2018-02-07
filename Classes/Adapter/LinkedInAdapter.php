<?php

namespace PlusB\PbSocial\Adapter;

$extensionPath = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('pb_social') . 'Resources/Private/Libs/';
require_once $extensionPath . 'linkedin/src/Client.php'; # Include provider library
// ... please, add composer autoloader first
include_once $extensionPath . 'linkedin' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use LinkedIn\AccessToken;
use LinkedIn\Client;
use PlusB\PbSocial\Domain\Model\Credential;
use PlusB\PbSocial\Domain\Model\Feed;
use PlusB\PbSocial\Domain\Model\Item;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2018 Ramon Mohi <rm@plusb.de>, plusB
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

class LinkedInAdapter extends SocialMediaAdapter
{

    const TYPE = 'linkedin';
    const linkedin_company_post_uri = "https://www.linkedin.com/feed/update/urn:li:activity:";

    private $api;

    /**
     * credentialRepository
     *
     * @var \PlusB\PbSocial\Domain\Repository\CredentialRepository
     * @inject
     */
    protected $credentialRepository;

    public function __construct($apiKey, $apiSecret, $apiCallback, $token, $itemRepository, $credentialRepository)
    {
        parent::__construct($itemRepository);

        $this->api =  new Client($apiKey,$apiSecret);

        $this->credentialRepository = $credentialRepository;

        // get access token from database
        $this->setAccessToken($token, $apiKey);
    }

    public function getResultFromApi($options)
    {
        $result = array();

        # set filters
        $filters = "";

        if ($options->showJobPostings || $options->showNewProducts || $options->showStatusUpdates)
        {
            $filters = "&event-type=";
            $filtered = false;
            if ($options->showJobPostings)
            {
                $filters .= 'job-posting';
                $filtered = true;
            }
            if ($options->showNewProducts)
            {
                $filters .= $filtered ? ',new-product' : 'new-product';
                $filtered = true;
            }
            if ($options->showStatusUpdates)
            {
                $filters .= $filtered ? ',status-update' : 'status-update';
            }
        }

        # get company updates
        # additional filters for job postings, new products and status updates may be applied
        foreach (explode(',', $options->companyIds) as $searchId) {

            $searchId = trim($searchId);

            if ($searchId != ""){
                $feeds = $this->itemRepository->findByTypeAndCacheIdentifier(self::TYPE, $searchId);
                if ($feeds && $feeds->count() > 0) {
                    $feed = $feeds->getFirst();
                    if ($options->devMod || ($feed->getDate()->getTimestamp() + $options->refreshTimeInMin * 60) < time()) {
                        try {
                            # api call
                            $companyUpdates = $this->api->get('companies/' . $searchId .'/updates?format=json' . $filters); # filters is empty ("") if no filters are applied..
                            $feed->setDate(new \DateTime('now'));
                            $feed->setResult(json_encode($companyUpdates));
                            $this->itemRepository->updateFeed($feed);
                        } catch (\Exception $e) {
                            $this->logError("feeds cannot be updated  - " . $e->getMessage());
                            continue;
                        }
                    }
                    $result[] = $feed;
                    continue;
                }

                try {
                    # api call
                    $companyUpdates = $this->api->get('companies/' . $searchId .'/updates?format=json' . $filters);
                    $feed = new Item(self::TYPE);
                    $feed->setCacheIdentifier($searchId);
                    $feed->setResult(json_encode($companyUpdates));

                    // save to DB and return current feed
                    $this->itemRepository->saveFeed($feed);
                    $result[] = $feed;
                } catch (\Exception $e) {
                    $this->logError("get_updates failed - " . $e->getMessage());
                }
            }
        }

        return $this->getFeedItemsFromApiRequest($result, $options);
    }

    public function getFeedItemsFromApiRequest($result, $options)
    {
        $rawFeeds = array();
        $feedItems = array();

        if (!empty($result)) {
            foreach ($result as $linkedin_feed) {
                $rawFeeds[self::TYPE . '_' . $linkedin_feed->getCacheIdentifier() . '_raw'] = $linkedin_feed->getResult();
                $i = 0;
                if (is_array($linkedin_feed->getResult()->values)) {
                    foreach ($linkedin_feed->getResult()->values as $rawFeed) {
                        if ($i < $options->feedRequestLimit)
                        {
                            $feed = new Feed(self::TYPE, $rawFeed);
                            $feed->setId($rawFeed->timestamp);
                            $feed->setText($this->trim_text($rawFeed->updateContent->companyStatusUpdate->share->comment, $options->textTrimLength, true));
                            $feed->setImage($rawFeed->updateContent->companyStatusUpdate->share->content->thumbnailUrl);
                            $link = self::linkedin_company_post_uri . array_reverse(explode('-', $rawFeed->updateKey))[0];
                            $feed->setLink($link);
                            $feed->setTimeStampTicks($rawFeed->timestamp);
                            $feedItems[] = $feed;
                            $i++;
                        }
                    }
                }
            }
        }

        return array('rawFeeds' => $rawFeeds, 'feedItems' => $feedItems);
    }

    private function setAccessToken($token, $apiKey)
    {
        if (empty($token))
        {
            $this->logError('Access token empty.');
            return null;
        }
        if (empty($apiKey))
        {
            $this->logError('Client ID empty.');
            return null;
        }
        # generate AccessToken class
        try
        {
            $access_token = new AccessToken();
            $access_token->setToken($token);
        }
        catch (\Exception $e)
        {
            $this->logError('failed to setup AccessToken - ' . $e->getMessage());
            return null;
        }
        # get access token from database #
        $credentials = $this->credentialRepository->findByTypeAndAppId(self::TYPE, $apiKey);

        if ($credentials->count() > 1)
        {
            foreach ($credentials as $c)
            {
                if ($c->getAccessToken != '')
                {
                    $credential = $c;
                } else {
                    $this->credentialRepository->remove($c);
                }
            }
        }
        else {
            $credential = $credentials->getFirst();
        }

//        if (!empty($this->api->getAccessTokenExpiration()) && $this->api->getAccessTokenExpiration() < strtotime('tomorrow'))
//        {
//            # api doc says you can reuse the old access code.. maybe I misinterpreted something? we'll give it a shot
//            # https://developer.linkedin.com/docs/oauth2
//            # todo: renew LinkedIn access token when $accessToken->getExpiresAt() < strtotime('tomorrow')
//        }

        if (!isset($credential) || !$credential->isValid())
        {
            if (isset($credential))
            {
                $credential->setAccessToken($token);
                $this->credentialRepository->update($credential);
            }
            else {
                # create new credential #
                $credential = new Credential(self::TYPE, $apiKey);
                $credential->setAccessToken($token);
                $this->credentialRepository->saveCredential($credential);
            }
        }

        $this->api->setAccessToken($access_token);

        return $credential->getAccessToken();
    }
}
