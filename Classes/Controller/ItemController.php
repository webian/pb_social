<?php
namespace PlusB\PbSocial\Controller;


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
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

/**
 * ItemController
 */
class ItemController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController {

    const TYPE_FACEBOOK = "facebook";
    const TYPE_GOOGLE = "googleplus";
    const TYPE_INSTAGRAM = "instagram";
    const TYPE_TWITTER = "twitter";
    const TYPE_TUMBLR = "tumblr";
    const TYPE_DUMMY = "dummy";


	/**
	 * itemRepository
	 *
	 * @var \PlusB\PbSocial\Domain\Repository\ItemRepository
	 * @inject
	 */
	protected $itemRepository = NULL;

	/**
	 * action list
	 *
	 * @return void
	 */
	public function listAction() {
		$items = $this->itemRepository->findAll();
		$this->view->assign('items', $items);
	}

	/**
	 * action show
	 *
	 * @param \PlusB\PbSocial\Domain\Model\Item $item
	 * @return void
	 */
	public function showAction(\PlusB\PbSocial\Domain\Model\Item $item) {
		$this->view->assign('item', $item);
	}

	/**
	 * action edit
	 *
	 * @param \PlusB\PbSocial\Domain\Model\Item $item
	 * @ignorevalidation $item
	 * @return void
	 */
	public function editAction(\PlusB\PbSocial\Domain\Model\Item $item) {
		$this->view->assign('item', $item);
	}

	/**
	 * action update
	 *
	 * @param \PlusB\PbSocial\Domain\Model\Item $item
	 * @return void
	 */
	public function updateAction(\PlusB\PbSocial\Domain\Model\Item $item) {
		$this->addFlashMessage('The object was updated. Please be aware that this action is publicly accessible unless you implement an access check. See <a href="http://wiki.typo3.org/T3Doc/Extension_Builder/Using_the_Extension_Builder#1._Model_the_domain" target="_blank">Wiki</a>', '', \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR);
		$this->itemRepository->update($item);
		$this->redirect('list');
	}

	/**
	 * action delete
	 *
	 * @param \PlusB\PbSocial\Domain\Model\Item $item
	 * @return void
	 */
	public function deleteAction(\PlusB\PbSocial\Domain\Model\Item $item) {
		$this->addFlashMessage('The object was deleted. Please be aware that this action is publicly accessible unless you implement an access check. See <a href="http://wiki.typo3.org/T3Doc/Extension_Builder/Using_the_Extension_Builder#1._Model_the_domain" target="_blank">Wiki</a>', '', \TYPO3\CMS\Core\Messaging\AbstractMessage::ERROR);
		$this->itemRepository->remove($item);
		$this->redirect('list');
	}

    /**
     * action showSocialBarAction
     * @return void
     */
    public function showSocialBarAction() {
        // function has nothing to do with database => only as template ref dummy
        // the magic is located only in the template and main.js :)
    }

    /**
     * action showSocialFeedAction
     * @return void
     */
    public function showSocialFeedAction() {
        $feeds = array();
        $feeds_facebook = null;
        $feeds_googleplus = null;
        $feeds_instagram = null;
        $feeds_twitter = null;
        $feeds_tumblr = null;
        $feeds_dummy = null;
        $onlyWithPicture = intval($this->settings["onlyWithPicture"]) != 0 ? true : false;
        $textTrimLength = intval($this->settings["textTrimLength"]) > 0 ? intval($this->settings["textTrimLength"]) : 130;


        if(intval($this->settings["facebookEnabled"]) != 0){
            $fb_feeds = $this->itemRepository->findFeedsByType(self::TYPE_FACEBOOK, $this->settings);

            if($fb_feeds !== NULL){
                foreach($fb_feeds as $fb_feed){
                    $this->view->assign(self::TYPE_FACEBOOK.'_'.$fb_feed->getCacheIdentifier().'_raw', $fb_feed->getResult());
                    foreach($fb_feed->getResult()->data as $rawFeed){
                        if($onlyWithPicture && empty($rawFeed->picture)){ continue; }

                        $feed = new Feed($fb_feed->getType(), $rawFeed);
                        $feed->setId($rawFeed->id);
                        $feed->setText(trim_text($rawFeed->message, $textTrimLength, true));
                        $feed->setImage(urldecode($rawFeed->picture));
                        $feed->setLink($rawFeed->link);
                        $d = new \DateTime($rawFeed->created_time);
                        $feed->setTimeStampTicks($d->getTimestamp());

                        $feeds[] = $feed;
                    }
                }
            }
        }

        if(intval($this->settings["googleEnabled"]) != 0){
            $feeds_googleplus = $this->itemRepository->findFeedsByType(self::TYPE_GOOGLE, $this->settings);

            if($feeds_googleplus !== NULL){
                foreach($feeds_googleplus as $gp_feed){
                    $this->view->assign(self::TYPE_GOOGLE.'_'.$gp_feed->getCacheIdentifier().'_raw', $gp_feed->getResult());
                    foreach($gp_feed->getResult()->items as $rawFeed){
                        if($onlyWithPicture && empty($rawFeed->object->attachments[0]->image->url)){ continue; }
                        $feed = new Feed($gp_feed->getType(), $rawFeed);
                        $feed->setId($rawFeed->id);
                        $feed->setText(trim_text($rawFeed->title, $textTrimLength, true));
                        $feed->setImage($rawFeed->object->attachments[0]->image->url);

                        // only for type photo
                        if($rawFeed->object->attachments[0]->objectType == "photo" && $rawFeed->object->attachments[0]->fullImage->url != ""){
                            $feed->setImage($rawFeed->object->attachments[0]->fullImage->url);
                        }

                        // only if no title is set but somehow the video is labeled
                        if($rawFeed->title == "" && $rawFeed->object->attachments[0]->displayName != ""){
                            $feed->setText(trim_text($rawFeed->object->attachments[0]->displayName, $textTrimLength, true));
                        }

                        $feed->setLink($rawFeed->url);
                        $d = new \DateTime($rawFeed->updated);
                        $feed->setTimeStampTicks($d->getTimestamp());
                        $feeds[] = $feed;
                    }
                }
            }
        }

        if(intval($this->settings["instagramEnabled"]) != 0){
            $feeds_instagram = $this->itemRepository->findFeedsByType(self::TYPE_INSTAGRAM, $this->settings);

            if($feeds_instagram !== NULL){
                foreach($feeds_instagram as $ig_feed){
                    $this->view->assign(self::TYPE_INSTAGRAM.'_'.$ig_feed->getCacheIdentifier().'_raw', $ig_feed->getResult());
                    foreach($ig_feed->getResult()->data as $rawFeed){
                        if($onlyWithPicture && empty($rawFeed->images->standard_resolution->url)){ continue; }
                        $feed = new Feed($ig_feed->getType(), $rawFeed);
                        $feed->setId($rawFeed->id);
                        $feed->setText(trim_text($rawFeed->caption->text, $textTrimLength, true));
                        $feed->setImage($rawFeed->images->standard_resolution->url);
                        $feed->setLink($rawFeed->link);
                        $feed->setTimeStampTicks($rawFeed->created_time);
                        $feeds[] = $feed;
                    }
                }
            }
        }

        if(intval($this->settings["twitterEnabled"]) != 0){
            $feeds_twitter = $this->itemRepository->findFeedsByType(self::TYPE_TWITTER, $this->settings);

            if($feeds_twitter !== NULL){
                foreach($feeds_twitter as $twt_feed){
                    if(empty($twt_feed->getResult()->statuses)){ break; }
                    $this->view->assign(self::TYPE_TWITTER.'_'.$twt_feed->getCacheIdentifier().'_raw', $twt_feed->getResult());
                    foreach($twt_feed->getResult()->statuses as $rawFeed){
                        DebuggerUtility::var_dump($rawFeed);
    //                    if($onlyWithPicture && empty($rawFeed->images->)){ continue; }
                        $feed = new Feed($twt_feed->getType(), $rawFeed);
                        $feed->setId($rawFeed->id);
                        $feed->setText(trim_text($rawFeed->text,$textTrimLength,true));
    //                    $feed->setImage($rawFeed->images->);
                        $feed->setLink($rawFeed->entities->urls[0]->expanded_url);
                        $dateTime = new \DateTime($rawFeed->created_at);
                        $feed->setTimeStampTicks($dateTime->getTimestamp());
                        $feeds[] = $feed;
                    }
                }
            }
        }

        if(intval($this->settings["tumblrEnabled"]) != 0){
            $feeds_tumblr = $this->itemRepository->findFeedsByType(self::TYPE_TUMBLR, $this->settings);

            if($feeds_tumblr !== NULL){
                foreach($feeds_tumblr as $tblr_feed){
                    $this->view->assign(self::TYPE_TUMBLR.'_'.$tblr_feed->getCacheIdentifier().'_raw', $tblr_feed->getResult());
                    foreach($tblr_feed->getResult()->posts as $rawFeed){
                        if($onlyWithPicture && empty($rawFeed->photos[0]->original_size->url) ){ continue; }
                        $feed = new Feed($tblr_feed->getType(),$rawFeed);
                        $feed->setId($rawFeed->id);
                        $feed->setText(trim_text(strip_tags($rawFeed->caption), $textTrimLength, true));
                        $feed->setImage($rawFeed->photos[0]->original_size->url);
                        $feed->setLink($rawFeed->post_url);
                        $feed->setTimeStampTicks($rawFeed->timestamp);
                        $feeds[] = $feed;
                    }
                }
            }
        }

        if(intval($this->settings["dummyEnabled"]) != 0){
            $feeds_dummy = $this->itemRepository->findFeedsByType(self::TYPE_DUMMY, $this->settings);

            if($feeds_dummy !== NULL){
                foreach($feeds_dummy as $dmy_feed){
                    $this->view->assign(self::TYPE_DUMMY.'_'.$dmy_feed->getCacheIdentifier().'_raw', $dmy_feed->getResult());
                    foreach($dmy_feed->getResult()->PROVIDER_AWESOME_JSON_STRUCTURE as $rawFeed){
                        if($onlyWithPicture && empty($rawFeed->TODO_PROVIDER_JSON_PICTURE_NODE) ){ continue; }
                        $feed = new Feed($dmy_feed->getType(), $rawFeed);
                        $feed->setId($rawFeed->TODO_PROVIDER_JSON_PICTURE_NODE);
                        $feed->setText(trim_text($rawFeed->TODO_PROVIDER_JSON_TEXT_NODE, $textTrimLength, true));
                        $feed->setImage($rawFeed->TODO_PROVIDER_JSON_PICTURE_NODE);
                        $feed->setLink($rawFeed->TODO_PROVIDER_JSON_LINK_NODE);
                        $feed->setTimeStampTicks($rawFeed->TODO_PROVIDER_JSON_MODIFY_DATE_NODE);
                        $feeds[] = $feed;
                    }
                }
            }
        }

        // sort array if not empty
        if(!empty($feeds)) { usort($feeds,array($this,"cmp")); }

        $this->view->assign('feeds', $feeds);
    }

    public function cmp($a, $b) {
        if ($a == $b) { return 0; }
        return ($a->getTimeStampTicks() > $b->getTimeStampTicks()) ? -1 : 1;
    }

}






class Feed {
    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $Provider;

    /**
     * @var string
     */
    protected $Image;

    /**
     * @var string
     */
    protected $Text;

    /**
     * @var integer
     */
    protected $TimeStampTicks;

    /**
     * @var string
     */
    protected $Link;

    /**
     * @var string
     */
    protected $Raw;


    /**
     * @param string $provider
     * @param string $rawFeed
     */
    function __construct($provider, $rawFeed) {
        $this->setProvider($provider);
        $this->setRaw($rawFeed);
    }

    /**
     * @param string $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $Image
     */
    public function setImage($Image)
    {
        if($this->Provider == ItemController::TYPE_FACEBOOK){
            if($this->Raw->type == "photo"){
                if(strpos($Image,"//scontent") !== false){
                    //$Image = preg_replace('/\/v\/\S*\/p[0-9]*x[0-9]*\//', '/', $Image);
                }
                if(strpos($Image,"//fbcdn") !== false){
                    //$Image = str_replace("/v/","/",$Image);
                    //$Image = str_replace("/p130x130/","/p/",$Image);
                }
            }
            if($this->Raw->type == "link"){
                $Image = preg_replace('/&[wh]=[0-9]*/', '', $Image); // for embedded links
            }
        }
        $this->Image = $Image;
    }

    /**
     * @return string
     */
    public function getImage()
    {
        return $this->Image;
    }

    /**
     * @param string $Provider
     */
    public function setProvider($Provider)
    {
        $this->Provider = $Provider;
    }

    /**
     * @return string
     */
    public function getProvider()
    {
        return $this->Provider;
    }

    /**
     * @param string $Raw
     */
    public function setRaw($Raw)
    {
        $this->Raw = $Raw;
    }

    /**
     * @return string
     */
    public function getRaw()
    {
        return $this->Raw;
    }

    /**
     * @param string $Text
     */
    public function setText($Text)
    {
        $this->Text = $Text;
    }

    /**
     * @return string
     */
    public function getText()
    {
        return $this->Text;
    }

    /**
     * @param int $TimeStampTicks
     */
    public function setTimeStampTicks($TimeStampTicks)
    {
        $this->TimeStampTicks = $TimeStampTicks;
    }

    /**
     * @return int
     */
    public function getTimeStampTicks()
    {
        return $this->TimeStampTicks;
    }

    /**
     * @param string $Link
     */
    public function setLink($Link)
    {
        $this->Link = $Link;
    }

    /**
     * @return string
     */
    public function getLink()
    {
        return $this->Link;
    }
}

/**
 * trims text to a space then adds ellipses if desired
 * @param string $input text to trim
 * @param int $length in characters to trim to
 * @param bool $ellipses if ellipses (...) are to be added
 * @param bool $strip_html if html tags are to be stripped
 * @return string
 */
function trim_text($input, $length, $ellipses = true, $strip_html = true) {
    if(empty($input)){ return ""; }

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