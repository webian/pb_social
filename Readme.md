#TYPO3 Extension `pb_social`

> Social media stream enables you to get posts from social media channels on your website / into your TYPO3 system.
> Current Channels are: Facebook, Imgur, Instagram, LinkedIn, Pinterest, Tumblr, Twitter, YouTube, Vimeo and TYPO3 Extension tx_news

With this extension we decided to fill a gap of social media integration in TYPO3. Our goal is it to provide an easy
and time-saving way of integrating and interacting with common social media platforms. As you know nothing is perfect
but we will give our best to make this extension as comfortable as you need it. For this we need your feedback, so
if you need anything or have something to say, don't hesitate to contact us. Simply write an email to <hello@plusb.de>.

> Please visit the our homepage [plusb.de](http://plusb.de/log/social-media-streams-pb_social/ "find more at our home page")

It can display feeds from social media platforms in the way you like it. Maybe you want to get your
Facebook-Page content? No problem, set your Facebook-Credentials and Facebook-UserId and you're ready to go.
The Extension will do all the tricky authentication stuff and leave the relaxed part of making the result pretty to you.

Sometimes you'll need to follow a link to generate access codes for our plugin. 
> Be sure that **we don't have access** to any of **your sensitive data**. 

If you do not change code, **everything of pb_social extension is stored in your TYPO3 database and in your TYPO3 file system!** 

The reason why you need to provide these access codes is that some social media platforms recently strated to use OAuth 2 authentication.
Read more about OAuth here: http://oauth.net/articles/authentication/

##1. Features

* different configuration and channels on each plugin and on different pages
* scheduler cron job to fetch channel data according to configuration of plugins
* Request limit for posts and filter setting for each channel on each plugin
* *tx_news* api access, you can include your own tx_news items in your feeds [learn more about extension *tx_news* of Georg Ringer](https://github.com/georgringer/news)
* actual channels: Facebook, Imgur, Instagram, LinkedIn, Pinterest, Tumblr, Twitter, Youtube, Vimeo, tx_news 

##2. Usage
###2.1. Installation
#### Installation using Composer
  
The recommended way to install the extension is by using [Composer](https://getcomposer.org/ "Learn more about composer"). 
In your Composer based TYPO3 project root, just do `composer require plusb/pb_social`.

#### Installation as extension from TYPO3 Extension Repository (TER)

Download and install the extension with the extension manager module.

###2.2. Minimal setup

1. Include the static TypoScript of the extension in your root template record or in template record of the page containing a pb_social plugin. 
1. Go to extension configuration of pb_social in Admin Tools / Extensions / pb_social, click on gear wheel icon.
    * Get some API credentials of a social channel and enter it in extension configuration
1. Create a plugin of pb_social on a page and set this social channel to "Active" by clicking checkbox, save plugin changes (perhaps you need some further data like an search id or s.th. else)

###2.3 Detailed Setup 

1. Enter all your available social media account data into the respective input fields. 
   If you're new to this or don't have the data, the following links will give you a 
   base direction where to get these credentials.

   - developers.facebook.com/apps
   - code.google.com/apis/console/ (Public Api-Access -> Generate New Key -> Server Key)
   - instagram.com/developer/clients/manage/
   - dev.twitter.com/apps/
   - tumblr.com/oauth/apps
   - developers.pinterest.com/apps/
   - api.imgur.com/
   - linkedin.com/developer/apps

    You might need to grant special permissions and add users to your app etc.
    All the details should be documented on the pages above.
    If you encounter any difficulties, check the FAQ section or contact us at hello@plusb.de
    With version 1.2.7 you will be able to integrate tx_news posts into the feed.
    It is possible to display news by category. The plugin needs a news plugin running on any other site to generate detail view links.
    Just make sure tx_news is installed and running and you have some news to display.
1. Include the extension typoscript
1. Navigate to an empty page and insert the "Socialfeed"-Plugin
1. Open the flexform and navigate through the Provider-Tabs you want to activate.
1. You can use multiple search values by making a comma separated string
1. Add the Scheduler Task "Extbase CommandController Task" and choose "PbSocial PbSocial: updateFeedData" (Note: the frequency should be set to a relatively small value, because of the flexform property "Feed update interval" that is controlling the refresh rate, too. On the other hand most APIs restrict requests to every 10-15 minutes. Be sure to respect those request limits. A scheduler task that runs every minute will only fill your error_log.)
   * check that you have a backend user called "_cli_scheduler"
    * check that you have a cron-job, that calls "./TYPO3/cli_dispatch.phpsh scheduler" from your project's root directory
1. Clear all caches and enjoy the result.
   * Feed-Caching by the CommandController is saved in the system-cache. Clearing the System cache will also clear your posts. Be sure to run your scheduler task again after every system cache clear command.
1. If you get the following error in the PHP error log: "Error: SSL certificate problem: unable to get local issuer certificate". 
   This happens due to an outdated root certification authority file (cacert.pem).
   Check these links for further details:

   - https://curl.haxx.se/docs/sslcerts.html
   - https://curl.haxx.se/ca/cacert.pem
   - TL:DR http://flwebsites.biz/posts/how-fix-curl-error-60-ssl-issue
   - add/upload a valid cacert.pem file to your php root directory and add the following line to the php.ini file
   - curl.cainfo="PATH TO\php\cacert.pem"

   For a quick an dirty solution we included a checkbox in the extension configuration that turns off ssl verification for all pb_social requests
   ATTENTION: Activating this checkbox might be a potential security risk!
1. Testing the Scheduler Task
    For testing you can execute the single controller command from the cli via:
    `./typo3/cli_dispatch.phpsh extbase pbsocial:updatefeeddata` or `./vendor/bin/typo3 pb_social:pbsocial:updatefeeddata`
    
    Called from your project's root directory.
    This hint should give you an example how to add a test to your scheduler [CommandController In Scheduler Task](https://wiki.typo3.org/CommandController_In_Scheduler_Task)
    Be sure the scheduler extension is installed, you have a backend user named _cli_lowlevel and your crontab executes the command  periodically. 
    
    If you need help, please refer [TYPO3 Documentation 'Setting up the cron job'](https://docs.typo3.org/typo3cms/extensions/scheduler/Installation/CronJob/Index.html "TYPO3 Documentation, Setting up the cron job")
    As we understood right, you can use this link (filling your credentials)  
    <pre>https://api.instagram.com/oauth/authorize/?client_id=YOUR_CLIENT_ID&redirect_uri=YOUR_REDIRECT_URI&response_type=token</pre>
    

##3. How To Migrate

###3.1 Migration from `1.3.1` to `1.3.3`: Instagram access token
* Instagram changed its access token procedure. You seemingly would need a logged in browser session to get the access token. 
So automatic generation by our code will not longer work. To get it, please log in into your instagram and refer to 
information of [instagram developer information](http://instagram.com/developer/clients/manage/). But please notice, you would need
to check "Disable implicit OAuth:" for this time (pleas uncheck after having done successfully). 

After having received the access token, fill in this long string into `pb_scoial extension configuration` in tab `"Instagram"` / `"Instagram access token"` and hit "Save". 


##4. Known Issues

- linkedIn causes some issues, in case a "LinkedIn company ID" does not exist or `Show job postings` and `Show product postings` is activated in plugin settings. 
    * hint: in this case, try to uncheck both `Show job postings` and `Show product postings` in your plugin settings, clear cache of this page and try a reload.
- Setting up plugin by TypoScript only would need to have many TypoScript setting parameters for getting a channel run, could be confusing. 
- refactor caching of feed items. remove from system cache and integrate into new pb_social cache.

- If you are testing the extension locally, you may encounter some small problems:
    - Facebook posts may not be loaded because of some xampp malconfiguration. You have two choices here:
        - Update your ssl certificates (.cert files) or Turn off ssl verification (considered as unsafe method because you'll send your credentials unencrypted) - See FAQ for details
        - Private Facebook profile posts may be not displayed.

- Posts without image may not load the placeholder image because of a 'Not allowed to load local resource' error.
- Depending on your Instagram developer app status, you may not be allowed to get data from other users. See the FAQ section for more information.
- Clearing the system cache, will also clear all posts, a white page could result until posts are reloaded. 
- Scheduler Task should only run each 10-15 minutes due to API restrictions.


##5. FAQ

#### Q: How can I get in contact to plus B, in case of suggestions, trouble or need help?
* Go to [our Website](https://plusb.de/ "our Website plus B in Berlin, Germany")
* write an Email to <hello@plusb.de>
* create an issue at [our Bitbucket Repository](https://bitbucket.org/plus-b/pb_social/issues?status=new&status=open "create Issue")

#### Q: Where do I find error logs?
* Please go to TYPO3 Backend and click in Admin Tool "System" on "Log"
* Please refer to (/typo3temp/log/) on your Webserver in your document root/ project folder

#### Q: Do you see nothing on your page?
* Perhaps you use ad add blocker like Adblock or uBlock in your browser?

#### Q: Your feeds are updated not often enouhg?
* Go to your plugin configuration and have a look at database field "General". Check entry at `Feed update interval (in minutes)` (Minimum: 10 minutes).  
* Go to your database and check date field of table `tx_pbsocial_domain_model_item`

#### Q: Your feeds are not clickable?
* check if jQuery is loaded and check JavaScript errors.
* check if jQuery is eventally loaded duplicate and switch off jQuery loading by pb_social: TypoScript Constant `plugin.tx_pbsocial.settings.load-jquery = 0`
    * If you need general help for this: [Declaring constants for the Constant Editor](https://docs.typo3.org/typo3cms/TyposcriptSyntaxReference/7.6/TypoScriptTemplates/TheConstantEditor/Index.html)

#### Q: Can I stop pb_social to include jQuery and include it by myself?
* configure TypoScript Constant `plugin.tx_pbsocial.settings.load-jquery = 0`

#### Q: Are there limits of posts?
* Likes and comments of Facebook posts are limited to 70. Posts with more than 70 likes/comments are marked as 70+.

#### Q: Do all channels need a numeric channel ID/ search ID in my plugin settings?
* Youtube and Vimeo channel IDs do not necessarily have to be a numeric value.
* Facebook search IDs is a name.
* Instagram search IDs is numeric value.
* LinkedIn company ID is numeric value.
    
#### Q: How many tags can I enter in Tumbler plugin settings?
* Tumblr posts can only be filtered by one of the first five tags, because only the first 5 of your tags are searchable on Tumblr.

#### Q: What can I do when nothing or only fragments visible?
* Clear all cache
* Check if some kind of Adblock is running
* Check TYPO3 System log in Backend 
* Check log file (/typo3temp/log/typo3.log)
* Check TYPO3 log (/typo3temp/log/typo3.log)
* Database table "tx_pbsocial_domain_model_item" ... check the date field of your feed.

#### Q: Feeds not refreshing fast enough?
* check the flexform at the general tab. Is the "refresh time in minutes" correct set? (minimal value 10min)

#### Q: Feeds not interacting with your clicks?
* check if jQuery is installed and ready.

#### Q: Strange php errors?
* check if curl is enabled on your server

#### Q: Your Instagram feed should work but the plugin can't find the user you're looking for?
* Maybe you are running your Instagram app in sandbox mode. That should be no problem, as long as you can invite
the users whose feed you want to display. Read more about sandbox mode here: https://www.instagram.com/developer/sandbox/
   
#### Q: The Instagram feed can't display a user's posts?
* If your Instagram app is still in sandbox mode, you have to send a sandbox invite to the user you want to get posts from. Instagram's policy has changed recently
so you'll now have to invite users to your sandbox in roder to get their posts. In addition please check the profile in the app, that is publishing the pictures: the option "private account" has to be deactivated.

#### Q: My page doesn't even redirect! What is my redirect uri?
* The redirect uri is just an obligatory value you must provide to be able to authenticate via OAuth. Simply type in the base url of the page you use the plugin for
or your business homepage. The APIs just need an url they can send the access code to, so just provide any url you like in the respective developer console.

#### Q: How do I setup LinkedIn?
* For showing LinkedIn company posts, you need to be administrator of this company. In the developer backend you'll need to set permissions "r_basicprofile" and "rw_company_admin".
After setting up permissions proceed by following steps 1 to 3 of this manual: https://developer.linkedin.com/docs/oauth2 (as usual redirect URL can be any url since you only need the given codes).
It is important to exchange the authorization code, obtained in step 2, for an access token very quickly, since these codes expire after approximately 20 seconds.
Tools like postman can be useful for this but are not necessary.

#### Q: Can I use my own facebook parameter list?
* Fist: Yes, but please know, what you are doing. 
* In your TypoScript Contants you can configure `plugin.tx_pbsocial.settings.facebook.requestParameterList`. 
* You can add a parameter by using "addToList()" in TypoScript Setup e.g.: `plugin.tx_pbsocial.settings.facebook.requestParameterList := addToList(status_type)`.
    * If TypoScript appending methods do not work for you, copy the default string and append your parameters after this string by clearly comma separation. (But `addToList()` above is coolest way to do it).

            picture,comments.summary(total_count).limit(0).as(comments),created_time,full_picture,reactions.summary(total_count).limit(0).as(reactions),reactions.type(NONE).summary(total_count).limit(0).as(none),reactions.type(LIKE).summary(total_count).limit(0).as(like),reactions.type(LOVE).summary(total_count).limit(0).as(love),reactions.type(WOW).summary(total_count).limit(0).as(wow),reactions.type(HAHA).summary(total_count).limit(0).as(haha),reactions.type(SAD).summary(total_count).limit(0).as(sad),reactions.type(ANGRY).summary(total_count).limit(0).as(angry),reactions.type(THANKFUL).summary(total_count).limit(0).as(thankful)

* Important: "id", "link" and "message" are always prepended in list (so do not repeat) and you will not find it in TypoScript Constant string above. It is appended in php code.
* To pull up your own parameter according to https://developers.facebook.com/docs/workplace/integrations/custom-integrations/reference/,
* Please consider change in Partials\Feed\Provider-facebook.html as well! Your request parameter is only shown if you note it down there. 
    * Please fill in fluid template `Resources/Private/Partials/Feed/Provider-facebook.html` as well, always prepending `"feed.raw"`: `{feed.raw.my_facebook_parameter_i_desire}`
    e.g. `{feed.raw.status_type}`
    * To change a fluid template, please copy this in your own configuration area for not to be overwritten after a update: Read quickly [Extending an Extbase extension](https://docs.typo3.org/typo3cms/ExtbaseGuide/Extbase/ExtendExtbaseExtension.html, "how to do it")
