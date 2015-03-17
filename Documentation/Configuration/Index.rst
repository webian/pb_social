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

1. Go to the extension manger, find this extension and open it configuration panel.
2. Type all your available social media account data in. If you new in this or haven't the data,
   the following links will give you a base direction where to get these credentials.
    - developers.facebook.com/apps
    - code.google.com/apis/console/ (Public Api-Access -> Generate New Key -> Server Key)
    - instagram.com/developer/clients/manage/
    - dev.twitter.com/apps/
    - www.tumblr.com/oauth/apps

3. Include the extension typoscript
3. Navigate to an empty page and insert the "Socialfeed"-Plugin
4. Open the flexform and navigate through the Provider-Tabs you want to activate.
    - find facebook id => http://findmyfacebookid.com
    - find instagram id => http://jelled.com/instagram/lookup-user-id
    - find twitter id => http://id.twidder.info

5. You can use multiple search values by making a comma separated string
6. clear all caches and enjoy the result.

7. with great power comes great responsibility


.. _configuration-faq:

FAQ
---

Nothing or only fragments visible ?
=> check if some kind of Adblock is running
=> check typo3 log (/typo3temp/log/log.txt)
=> check if AddBlock Extension is off ^^

Feeds not getting updated ?
=> check typo3 log (/typo3temp/log/log.txt)
=> database table "tx_pbsocial_domain_model_item" ... check the date field of your feed.

Feeds not refreshing fast enough ?
=> check the flexform at the general tab. Is the "refresh time in minutes" correct set? (minimal value 10min)

Feeds not interacting with your clicking ?
=> check if jQuery is built-in

Strange php errors ?
=> check if curl is enabled on your server