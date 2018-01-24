<?php

namespace PlusB\PbSocial\Adapter;

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

abstract class SocialMediaAdapter
{

    const TYPE = 'socialMediaAdapter';

    public $type;
    public $logger;
    public $itemRepository;

    public function __construct($itemRepository)
    {
        $this->logger = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Core\Log\LogManager')->getLogger(__CLASS__);

        $this->itemRepository = $itemRepository;

        $this->type = static::TYPE;
    }

    abstract public function getResultFromApi($options);

    abstract public function getFeedItemsFromApiRequest($result, $options);

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

    public function cmp($a, $b)
    {
        if ($a == $b) {
            return 0;
        }
        return ($a->getTimeStampTicks() > $b->getTimeStampTicks()) ? -1 : 1;
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

    public function logError($message)
    {
        $GLOBALS["BE_USER"]->simplelog($this->type . ": " . $message, "pb_social", 1);
        $this->logger->error($this->type . " " . $message, array("data" => $this->type . " " . $message));
    }

    public function logWarning($message)
    {
        $GLOBALS["BE_USER"]->simplelog($this->type . ": " . $message, "pb_social", 0);
        $this->logger->warning($this->type . " " . $message, array("data" => $this->type . " " . $message));
    }
}
