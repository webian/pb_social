<?php

namespace PlusB\PbSocial\Adapter;

use PlusB\PbSocial\Service\LogTrait;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2016 Ramon Mohi <rm@plusb.de>, plus B
 *  (c) 2018 Arend Maubach <am@plusb.de>, plus B
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

abstract class SocialMediaAdapter implements SocialMediaAdapterInterface
{
    use LogTrait;

    const TYPE = 'socialMediaAdapter';
    const EXTKEY = 'pb_social';

    /**
     * @var string $type name of social network
     */
    public $type;

    /**
     * @var object $itemRepository object reference
     */
    public $itemRepository;

    /**
     * @var object $cacheIdentifier object reference
     */
    protected $cacheIdentifier;

    /**
     * @var integer uid of plugin for logging information
     */
    protected $ttContentUid;
    /**
     * @var integer page uid of plugin for logging information
     */
    protected $ttContentPid;

    public function __construct(
        $itemRepository,
        $cacheIdentifier,
        $ttContentUid,
        $ttContentPid
    )
    {
        $this->type = static::TYPE; //set default, adapter plays its game
        $this->itemRepository = $itemRepository;
        $this->cacheIdentifier = $cacheIdentifier;
        $this->ttContentUid = $ttContentUid;
        $this->ttContentPid = $ttContentPid;
    }

    abstract public function validateAdapterSettings($parameter);
    abstract public function getResultFromApi();

    abstract public function getFeedItemsFromApiRequest($result, $options);

    /*
     * item must be written, cache identifier may be added by a value, but must be unique in this foreach.
     * so cache identifier is unique for page/plugin/tab - but in this tab a comma separated string of search ids is not unique -
     * it would repeat first one - and ignore following search ids.
     * solution: $cacheIdentifierForListItem = $this->cacheIdentifier . "_" . $searchId
     *
     *      $this->cacheIdentifier // unique for page.uid and tt_content.uid and flexform-option of network
     *      . "_" .
     *      $searchId // unique in (page.uid/tt_content.uid/flexform-option of network) and list item of search id
     *
     * Why do we write do database? Because we want to trigger cache, thats all.
     */
    protected function composeCacheIdentifierForListItem($cacheIdentifier, $listItem){
        return $cacheIdentifier ."_". sha1($listItem);
    }

    /**
     * trims text to a space then adds ellipses if desired
     * @param string $input text to trim
     * @param int $length in characters to trim to
     * @param bool $ellipses if ellipses (...) are to be added
     * @param bool $strip_html if html tags are to be stripped
     * @return string
     */
    public function trim_text($input, $length, $ellipses = true, $strip_html = true)
    {
        if (empty($input)) {
            return '';
        }

        //strip tags, if desired
        if ($strip_html) {
            $input = strip_tags($input);
        }

        //no need to trim, already shorter than trim length
        if (strlen($input) <= $length) {
            return $input;
        }

        //find last space within length
        $last_space = strrpos(substr($input, 0, $length), ' ');
        $trimmed_text = substr($input, 0, $last_space);

        //add ellipses (...)
        if ($ellipses) {
            $trimmed_text .= '...';
        }

        return $trimmed_text;
    }

    public function check_end($str, $ends)
    {
        foreach ($ends as $try) {
            if (substr($str, -1 * strlen($try)) === $try) {
                return $try;
            }
        }
        return false;
    }

    /**
     * Abstraction in social media adapters for logging trait
     *
     * @param string $message
     * @param integer $locationInCode timestamp to find in code
     */
    public function logAdapterError($message, $locationInCode)
    {
        $this->logError($message, $this->ttContentUid, $this->ttContentPid, $this->type, $locationInCode);
    }

    /**
     * Abstraction in social media adapters for logging trait
     *
     * @param string $message
     * @param integer $locationInCode timestamp to find in code
     */
    public function logAdapterWarning($message, $locationInCode)
    {
        $this->logWarning($message, $this->ttContentUid, $this->ttContentPid, $this->type, $locationInCode);
    }
}
