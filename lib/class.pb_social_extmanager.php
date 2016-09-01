<?php

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015 Sergej Junker <sergej.junker@plusb.de>, plus B
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
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
class pb_social_extmanager
{

    // Outputs the logo with link to our website in the extension manager window
    public function getLogoAndLink()
    {
        $str_prompt = null;
        $str_prompt = $str_prompt . '<div class="message-body"><a target="_blank" href="http://www.plusb.de"><img src="../../../../typo3conf/ext/pb_social/Resources/Public/Icons/plusblogobig.gif"></a></div>';

        return $str_prompt;
    }
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pb_social/lib/class.pb_social_extmanager.php']) {
    include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/pb_social/lib/class.pb_social_extmanager.php']);
}
