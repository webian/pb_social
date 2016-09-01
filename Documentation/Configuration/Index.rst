.. ==================================================
.. FOR YOUR INFORMATION
.. --------------------------------------------------
.. -*- coding: utf-8 -*- with BOM.

.. include:: ../Includes.txt


.. _configuration:

Configuration Reference
=======================

Dear Typo3 Integrator,

the next steps will hopefully explain you how to use this extension.

1. Go to the extension manger, install this extension and open it's configuration panel.
2. Enter all your available social media account data into the respective input fields. 
   If you're new to this or don't have the data, the following links will give you a 
   base direction where to get these credentials.

   - developers.facebook.com/apps
   - code.google.com/apis/console/ (Public Api-Access -> Generate New Key -> Server Key)
   - instagram.com/developer/clients/manage/
   - dev.twitter.com/apps/
   - www.tumblr.com/oauth/apps
   - developers.pinterest.com/apps/
   - api.imgur.com/

    You might need to grant special permissions and add users to your app etc.
    All the details should be documented on the pages above.
    If you encounter any difficulties, check the FAQ section or contat us at info@plusb.de

3. Include the extension typoscript
3. Navigate to an empty page and insert the "Socialfeed"-Plugin
4. Open the flexform and navigate through the Provider-Tabs you want to activate.

   - find facebook id => http://findmyfacebookid.com
   - find instagram id => http://jelled.com/instagram/lookup-user-id
   - find google+ id => http://ansonalex.com/google-plus/how-do-i-find-my-google-plus-user-id-google/

5. You can use multiple search values by making a comma separated string
6. clear all caches and enjoy the result.
7. If you get the following error in the PHP error log: "Error: SSL certificate problem: unable to get local issuer certificate". This happens due to an outdated root certification authority file (cacert.pem). Check these links for further details:

   - https://curl.haxx.se/docs/sslcerts.html
   - https://curl.haxx.se/ca/cacert.pem
   - TL:DR http://flwebsites.biz/posts/how-fix-curl-error-60-ssl-issue

   For a quick an dirty solution we included a checkbox in the extension configuration that turns off ssl verification for all pb_social requests
   ATTENTION: Activating this checkbox might be a potential security risk!

8. with great power comes great responsibility

9. OPTIONAL
    If you want to avoid long loading times you can add a scheduler task to store the feeds automatically
    in the database. For example if your refresh time is 15 minutes, let the task get the feeds every 10 minutes.
    For testing you can execute the controller comand from the cli via:
    ./typo3/cli_dispatch.phpsh extbase pbsocial:updatefeeddata
    Called from your typo3 root directory.
    This hint should give you an example how to add a tast to your scheduler https://wiki.typo3.org/CommandController_In_Scheduler_Task
    Be sure the scheduler extension is installed, you have a backend user named _cli_lowlevel and your crontab executes ./typo3/cli_dispatch.phpsh periodically.


.. _configuration-faq:

FAQ
---

**Nothing or only fragments visible?**

- clear all cache
- check if some kind of Adblock is running
- check typo3 log (/typo3temp/log/log.txt)

**Feeds not getting updated ?**

- check typo3 log (/typo3temp/log/log.txt)
- database table "tx_pbsocial_domain_model_item" ... check the date field of your feed.

**Feeds not refreshing fast enough?**

- check the flexform at the general tab. Is the "refresh time in minutes" correct set? (minimal value 10min)

**Feeds not interacting with your clicks?**

- check if jQuery is installed and ready.

**Strange php errors?**

- check if curl is enabled on your server

**Your Instagram feed should work but the plugin can't find the user you're looking for?**

- Maybe you are running your Instagram app in sandbox mode. That should be no problem, as long as you can invite
the users whose feed you want to display. Read more about sandbox mode here: https://www.instagram.com/developer/sandbox/
   
**Where do I get an Instagram access code from?**

- The link you need should be available above the input field for your access code. Simply replace the parts "YOUR_CLIENT_ID" and "YOUR_REDIRECT_URI"
with your data from the Instagram developer console and the link should work. Open the link and copy the access code. You will find the access code
at the end of the url in your browser's address bar.
   
**The Instagram feed can't display a user's posts?**

- If your Instagram app is still in sandbox mode, you have to send a sandbox invite to the user you want to get posts from. Instagram's policy has changed recently
so you'll now have to invite users to your sandbox in roder to get their posts.

**My page doesn't even redirect! What is my redirect uri?**

- The redirect uri is just an obligatory value you must provide to be able to authenticate via OAuth. Simply type in the base url of the page you use the plugin for
or your business homepage. The APIs just need an url they can send the access code to, so just provide any url you like in the respective developer console.
   
   
