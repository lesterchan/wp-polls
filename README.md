# WP-Polls
Contributors: GamerZ  
Donate link: http://lesterchan.net/site/donation/  
Tags: poll, polls, polling, vote, booth, democracy, ajax, survey, post, widget  
Requires at least: 3.9  
Tested up to: 4.3  
Stable tag: 2.70  

Adds an AJAX poll system to your WordPress blog. You can also easily add a poll into your WordPress's blog post/page.

## Description
WP-Polls is extremely customizable via templates and css styles and there are tons of options for you to choose to ensure that WP-Polls runs the way you wanted. It now supports multiple selection of answers.

### Build Status
[![Build Status](https://travis-ci.org/lesterchan/wp-polls.svg?branch=master)](https://travis-ci.org/lesterchan/wp-polls)

### Development
[https://github.com/lesterchan/wp-polls](https://github.com/lesterchan/wp-polls "https://github.com/lesterchan/wp-polls")

### Translations
[http://dev.wp-plugins.org/browser/wp-polls/i18n/](http://dev.wp-plugins.org/browser/wp-polls/i18n/ "http://dev.wp-plugins.org/browser/wp-polls/i18n/")

### Credits
* Plugin icon by [Freepik](http://www.freepik.com) from [Flaticon](http://www.flaticon.com)

### Donations
I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appreciate it. If not feel free to use it without any obligations.

## Changelog

### Version 2.70
* NEW: Add wp_polls_vote_poll_success action hook
* NEW: Add wp_polls_add_poll, wp_polls_update_poll, wp_polls_delete_poll action hooks
* FIXED: PHP Notices
* FIXED: Removed not needed wp_print_scripts
* FIXED: Use esc_attr() and esc_textarea() instead of htmlspecialchars(). Props [Govind Singh](https://in.linkedin.com/pub/govind-singh/21/1a9/bab)

### Version 2.69
* NEW: Make use of wp_add_inline_style. Props @pathawks.
* NEW: Create 2 filters for secret ballot. Props @afragen.
* FIXED: Added new index to wp_pollsip. Props ArtemR.
* FIXED: Integration with WP-Stats
* FIXED: Proper IP checking

### Version 2.68
* NEW: Poll answer percentage are now not rounded off, previously it was always rounded to add up to 100%
* NEW: Support WordPress MultiSite Network Activation
* NEW: Uses native WordPress uninstall.php
* NEW: Show shortcode in success message after creating a poll
* NEW: Checks and ensure that Poll Question and Poll Answers are not empty
* NEW: Checks whether Poll is closed before checking whether user has voted


### Version 2.67
* NEW: Use POST for View Results and Vote link
* FIXED: Added ?v=VERSION_NUMBER to the plugin TinyMCE JS because it is breaking a lot of editors due to cache issue
* FIXED: Added backward compatibility with [poll=1] in order not to break older polls

### Version 2.66
* FIXED: Notices from polls_archive function. Props. @prettyboymp.
* FIXED: Ajax request in parallel with animation. Props @nodecode.
* FIXED: Editor button was outputting the wrong shortcode.
* FIXED: ReferenceError: pollsEdL10n is not defined if TinyMCE 4.0 is loaded outside the Add/Edit Posts/Pages.

### Version 2.65
* NEW: Use Dashicons
* NEW: Supports TinyMCE 4.0 For WordPress 3.9
* NEW: Added Poll ID after adding it
* FIXED: Use SITECOOKIEPATH instead of COOKIEPATH.
* FIXED: Use http://ipinfo.io instead of http://ws.arin.net to get check IP information.
* FIXED: Wrapped all JS function in jQuery.ready(). It is ugly, but it will do till I have time to rewrite it.
* FIXED: Add INDEX for wp_pollsip: pollip_ip_qid (pollip_ip, pollip_qid) to prevent full table scan. Thanks archon810 from AndroidPolice.

### Version 2.64
* NEW: Add in various filters in the plugin. Props Machiel.
* FIXED: Deveral undefined variable / undefined index notices. Props Machiel.

### Version 2.63 (21-05-2012)
* Move AJAX Request to wp-admin/admin-ajax.php
* Added nonce To AJAX Calls
* FIXED: PHP Notices/add_options() Deprecated Arguments ([Dewey Bushaw](http://www.parapxl.com/ "Dewey Bushaw"))

### Version 2.62 (31-08-2011)
* FIXED: Escaped Hostname. Thanks to Renaud Feil ([Renaud Feil](http://www.stratsec.net "Renaud Feil"))
* FIXED: Ensure Poll ID In Shortcode Is An Integer. Thanks to Renaud Feil ([Renaud Feil](http://www.stratsec.net "Renaud Feil"))

### Version 2.61 (14-02-2011)
* FIXED: XSS Vulnerability. Thanks to Dweeks, Leon Juranic and Chad Lavoie of the Swiftwill Security Team Inc ([www.swiftwill.com](http://www.swiftwill.com "www.swiftwill.com"))

### Version 2.60 (01-12-2009)
* NEW: Uses WordPress nonce Throughout
* NEW: Display 2,000 Records In Poll Logs Instead Of 100

### Version 2.50 (01-06-2009)
* NEW: Works For WordPress 2.8 Only
* NEW: Javascript Now Placed At The Footer
* NEW: Uses jQuery Instead Of tw-sack
* NEW: Minified Javascript Instead Of Packed Javascript
* NEW: Renamed polls-admin-js-packed.js To polls-admin-js.js
* NEW: Renamed polls-admin-js.js To polls-admin-js.dev.js
* NEW: Renamed polls-js-packed.js To polls-js.js
* NEW: Renamed polls-js.js To polls-js.dev.js
* NEW: Translate Javascript Variables Using wp_localize_script()
* NEW: Add "Add Poll" To WordPress Favourite Actions
* NEW: Minified plugin.js And Added Non-Minified plugin.min.js
* NEW: Able To Remove Individual Answers When Adding Or Editing A Poll
* NEW: Use _n() Instead Of __ngettext() And _n_noop() Instead Of __ngettext_noop()
* NEW: Uses New Widget Class From WordPress
* NEW: Merge Widget Code To wp-polls.php And Remove wp-polls-widget.php
* FIXED: Uses $_SERVER['PHP_SELF'] With plugin_basename(__FILE__) Instead Of Just $_SERVER['REQUEST_URI']
* FIXED: Ensure That Percentage Always Add Up To 100%
* FIXED: More Efficient WP-Polls Archive
* FIXED: Logged By Username Now Shows Poll Results To Users Who Did Not Login

### Version 2.40 (12-12-2008)
* NEW: Works For WordPress 2.7 Only
* NEW: Load Admin JS And CSS Only In WP-Polls Admin Pages
* NEW: Added polls-admin-css.css For WP-Polls Admin CSS Styles
* NEW: Right To Left Language Support by Kambiz R. Khojasteh
* NEW: Added "polls-css-rtl.css" by Kambiz R. Khojasteh
* NEW: Applied Output Of polls_archive() To "polls_archive" Filter by Kambiz R. Khojasteh
* NEW: Added Call To polls_textdomain() In create_poll_table() and vote_poll() functions by Kambiz R. Khojasteh
* NEW: Uses wp_register_style(), wp_print_styles(), plugins_url() And site_url()
* NEW: [poll id="-2"] or <?php get_poll(-2); ?> Will Randomize The Poll
* FIXED: SSL Support
* FIXED: Moved Call To update_pollbar() From onblur To onclick Event. It Was Showing The Last Selection Instead Of Current One by Kambiz R. Khojasteh

### Version 2.31 (16-07-2008)
* NEW: Works For WordPress 2.6
* NEW: Better Translation Using __ngetext() by Anna Ozeritskaya
* FIXED: MYSQL Charset Issue Should Be Solved

### Version 2.30 (01-06-2008)
* NEW: Works For WordPress 2.5 Only
* NEW: Added Paging Header And Footer Template For Polls Archive Page
* NEW: Uses WP-PageNavi Style Paging For Polls Archive Page
* NEW: WP-Polls Will Load 'polls-css.css' Inside Your Theme Directory If It Exists. If Not, It Will Just Load The Default 'polls-css.css' By WP-Polls
* NEW: Uses Shortcode API
* NEW: When Inserting Poll Into Post, It is Now [poll id="1"], Where 1 Is Your Poll ID
* NEW: When User Does Not Have Permission To Vote, The Voting Form Is Now Disabled Instead Of Showing Poll's Result
* NEW: Added A New Action Called "Display Disabled Poll's Voting Form" To Action Taken When A Poll Is Closed
* NEW: Updated WP-Polls TinyMCE Plugin To Work With TinyMCE 3.0
* NEW: Add Time Expiry For Cookie/Log
* NEW: Removed polls-usage.php
* NEW: Removed "Fade Anything Technique" In Polls Admin
* NEW: Uses /wp-polls/ Folder Instead Of /polls/
* NEW: Uses wp-polls.php Instead Of polls.php
* NEW: Uses wp-polls-widget.php Instead Of polls-widget.php
* NEW: Use number_format_i18n() Instead
* NEW: Renamed polls-admin-js.php To polls-admin-js.js and Move The Dynamic Javascript Variables To The PHP Pages
* NEW: Renamed polls-js.php To polls-js.js and Move The Dynamic Javascript Variables To The PHP Pages
* NEW: Uses polls-js-packed.js And polls-admin-js-packed.js
* FIXED: Unable To Delete Poll Or Poll Answers If There Is Quotes Within The Poll Or Poll Answer
* FIXED: number_format() Not Used In Polls Archive
* FIXED: Unable To Schedule Future Poll If The Year Is Different From Current Year
* FIXED: TinyMCE Tool Tip For Insert Poll Not Translated
* FIXED: Content-Type Not Being Sent Back When AJAX Return Results

### Version 2.21 (01-10-2007)
* NEW: Works For WordPress 2.3 Only
* NEW: Added Quick Tag For Poll To Visual (TinyMCE) / Code Editor
* NEW: New CSS Style For WP-Polls Archive (.wp-polls-archive)
* NEW: Uses WP-Stats Filter To Add Stats Into WP-Stats Page
* NEW: Ability To Add Polls To Excerpt
* NEW: Added "Random Order" For Sorting Poll's Answers And Poll's Result Answers
* FIXED: Language Problem By Setting Database Table To UTF8
* FIXED: Some Text Not Translated In Polls Widget
* FIXED: 2 Wrong Options Name In Polls Uninstall
* FIXED: Some Translation Bug in polls-usage.php

### Version 2.20 (01-06-2007)
* NEW: Poll Archive Link, Individual Poll Header And Footer In Poll Archive Template
* NEW: Poll Templates Has Now Its Own Page 'WP-Admin -> Polls -> Poll Templates'
* NEW: Poll Widget Can Now Display Multiple Polls
* NEW: Ability To Allow User To Select More Than 1 Poll Answer
* NEW: Added AJAX Style Option: "Show Loading Image With Text"
* NEW: Added AJAX Style Option: "Show Fading In And Fading Out Of Polls"
* NEW: Major Changes To The Administration Panel For WP-Polls
* NEW: AJAX Added To The Administration Panel For WP-Polls
* NEW: Default Poll's Result Template Will Now Show Number Of Votes Beside The Percentage
* NEW: Term "Total Votes" Changed To "Total Voters"
* NEW: Removed Polls From Feed If The Poll Is Embedded Into The Post Using [poll=ID]
* NEW: Filtering Of Individual Poll Logs
* FIXED: Poll Archive Will Now Show Only Polls Results

### Version 2.14 (01-02-2007)
* NEW: Works For WordPress 2.1 Only
* NEW: Renamed polls-js.js to polls-js.php To Enable PHP Parsing
* NEW: Ability To Make A Poll Expire
* NEW: Ability To Make A Future Poll
* NEW: Future Poll Will Automatically Open When The Poll's Date Is Reached
* NEW: Expired Poll Will Automatically Closed When The Poll's Date Is Reached
* NEW: Ablity To Choose What To Do When The Poll Is Closed (Display Result, Remove Poll From Sidebar)
* FIXED: Future Dated Polls Will Not Appear In The Post/Sidebar/Polls Archive

### Version 2.13 (02-01-2007)
* NEW: polls.php Now Handles The AJAX Processing Instead Of index.php
* NEW: Able To Modify The Style Of Poll Results Bar in 'Polls -> Poll Option'
* NEW: Usage Instructions Is Also Included Within The Plugin Itself
* NEW: Uninstaller Done By Philippe Corbes
* NEW: Localization Done By Ravan
* NEW: Ability To Add HTML Into Poll Question and Answers
* FIXED: AJAX Not Working On Servers Running On PHP CGI
* FIXED: Added Some Default Styles To polls-css.css To Ensure That WP-Polls Does Not Break
* FIXED: Other Languages Not Appearing Properly
* FIXED: Poll IP Logs Of Deleted Poll's Answer Did Not Get Deleted
* FIXED: There Is An Error In Voting If There Is Only 1 Poll's Answer

### Version 2.12 (01-10-2006)
* NEW: Polls Archive Is Now Embedded Into A Page, And Hence No More Integrating Of Polls Archive
* NEW: WP-Polls Is Now Using DIV To Display The Poll's Results Instead Of The Image Bar
* NEW: Added Widget Title Option To WP-Polls Widget
* NEW: Ability To Logged By UserName
* NEW: Added CSS Class 'wp-polls-image' To All IMG Tags
* FIXED: If Site URL Doesn't Match WP Option's Site URL, WP-Polls Will Not Work

### Version 2.11 (08-06-2006)
* NEW: You Can Now Place The Poll On The Sidebar As A Widget
* NEW: Moved wp-polls.php To wp-content/plugins/polls/ Folder
* FIXED: AJAX Not Working In Opera Browser
* FIXED: Poll Not Working On Physical Pages That Is Integrated Into WordPress

### Version 2.1 (01-06-2006)
* NEW: Poll Is Now Using AJAX
* NEW: Ability To Close/Open Poll
* NEW: Added Poll Option For Logging Method
* NEW: Added Poll Option For Who Can Vote
* NEW: Added Poll Results Footer Template Variable (Used When User Click "View Results")
* NEW: Added The Ability To Delete All Poll Logs Or Logs From A Specific Poll
* NEW: Poll Administration Panel And The Code That WP-Polls Generated Is XHTML 1.0 Transitional

### Version 2.06b (26-04-2006)
* FIXED: Bug In vote_poll();

### Version 2.06a (02-04-2006)
* FIXED: Random Poll Not Working Correctly

### Version 2.06 (01-04-2006)
* NEW: Poll Bar Is Slightly Nicer
* NEW: Got Rid Of Tables, Now Using List
* NEW: Added In Most Voted And Least Voted Answer/Votes/Percentage For Individual Poll As Template Variables
* NEW: Display Random Poll Option Under Poll -> Poll Options -> Current Poll
* FIXED: Totally Removed Tables In wp-polls.php

### Version 2.05 (01-03-2006)
* NEW: Improved On 'manage_polls' Capabilities
* NEW: Neater Structure
* NEW: No More Install/Upgrade File, It Will Install/Upgrade When You Activate The Plugin
* NEW: Added Poll Stats Function

### Version 2.04 (01-02-2006)
* NEW: Added 'manage_polls' Capabilities To Administrator Role
* NEW: [poll=POLL_ID] Tag To Insert Poll Into A Post
* NEW: Ability To Edit Poll's Timestamp
* NEW: Ability To Edit Individual Poll's Answer Votes
* NEW: %POLL_RESULT_URL% To Display Poll's Result URL
* FIXED: Cannot Sent Header Error

### Version 2.03 (01-01-2006)
* NEW: Compatible With WordPress 2.0 Only
* NEW: Poll Administration Menu Added Automatically Upon Activating The Plugin
* NEW: Removed Add Poll Link From The Administration Menu
* NEW: GPL License Added
* NEW: Page Title Added To wp-polls.php

### Version 2.02a (17-11-2005)
* FIXED: poll-install.php And poll-upgrade.php will Now Be Installed/Upgraded To 2.02 Instead Of 2.01

### Version 2.02 (05-11-2005)
* FIXED: Showing 0 Vote On Poll Edit Page
* FIXED: Null Vote Being Counted As A Vote
* FIXED: Auto Loading Of Poll Option: Polls Per Page In Poll Archive Page Is Now "No"
* NEW: Host Column In Poll IP Table To Prevent Network Lagging When Resolving IP
* NEW: New Poll Error Template

### Version 2.01 (25-10-2005)
* FIXED: Upgrade Script To Insert Lastest Poll ID Of User's Current Polls, Instead Of Poll ID 1
* FIXED: Replace All <?### With <?php
* FIXED: Added addalshes() To $pollip_user
* FIXED: Better Localization Support (80% Done, Will Leave It In The Mean Time)

### Version 2.0 (20-10-2005)
* NEW: IP Logging
* NEW: Poll Options: Sorting Of Answers In Voting Form
* NEW: Poll Options: Sorting Of Answers In Results View
* NEW: Poll Options: Number Of Polls Per Page In Poll Archive
* NEW: Poll Options: Choose Poll To Display On Index Page
* NEW: Poll Options: Able To Disable Poll With Custom Message
* NEW: Poll Options: Poll Templates
* NEW: Display User's Voted Choice
* FIXED: Better Install/Upgrade Script

## Installation

1. Open `wp-content/plugins` Folder
2. Put: `Folder: wp-polls`
3. Activate `WP-Polls` Plugin
4. Go to `WP-Admin -> WP-Polls`

### General Usage (Without Widget)
1. Open `wp-content/themes/<YOUR THEME NAME>/sidebar.php`
2. Add:
<code>
&lt;?php if (function_exists('vote_poll') && !in_pollarchive()): ?&gt;
&nbsp;&nbsp;&lt;li&gt;
&nbsp;&nbsp;&nbsp;&nbsp;&lt;h2&gt;Polls&lt;/h2&gt;
&nbsp;&nbsp;&nbsp;&nbsp;&lt;ul&gt;
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&lt;li&gt;&lt;?php get_poll();?&gt;&lt;/li&gt;
&nbsp;&nbsp;&nbsp;&nbsp;&lt;/ul&gt;
&nbsp;&nbsp;&nbsp;&nbsp;&lt;?php display_polls_archive_link(); ?&gt;
&nbsp;&nbsp;&lt;/li&gt;
&lt;?php endif; ?&gt;
</code>

* To show specific poll, use `<?php get_poll(2); ?>` where 2 is your poll id.
* To show random poll, use `<?php get_poll(-2); ?>`
* To embed a specific poll in your post, use `[poll id="2"]` where 2 is your poll id.
* To embed a random poll in your post, use `[poll id="-2"]`
* To embed a specific poll's result in your post, use `[poll id="2" type="result"]` where 2 is your poll id.

### General Usage (With Widget)
1. Go to `WP-Admin -> Appearance -> Widgets`.
2. You can add the Polls Widget by clicking on the 'Add' link besides it.
3. After adding, you can configure the Polls Widget by clicking on the 'Edit' link besides it.
4. Click 'Save Changes'.
5. Scroll down for instructions on how to create a Polls Archive.

## Upgrading

1. Deactivate `WP-Polls` Plugin
2. Open `wp-content/plugins` Folder
3. Put/Overwrite: `Folder: wp-polls`
4. Activate `WP-Polls` Plugin
5. Go to `WP-Admin -> Polls -> Polls Templates` and restore all the template variables to `Default`
6. Go to `WP-Admin -> Appearance -> Widgets` and re-add the Poll Widget

## Upgrade Notice

N/A

## Screenshots

1. Admin - All Poll
2. Admin - Manage Polls
3. Admin - Poll Options
4. Admin - Poll Templates
5. Admin - Poll Widget
6. Admin - Uninstall Poll
7. Poll - Single Poll Answer
8. Poll - Mutiple Poll Answers
9. Poll - Results
10. Poll - Archive

## Frequently Asked Questions


### How To Add A Polls Archive?
1. Go to `WP-Admin -> Pages -> Add New`.
2. Type any title you like in the post's title area.
3. If you ARE  using nice permalinks,  after typing the title, WordPress will generate the permalink to the page. You will see an 'Edit' link just beside the permalink.
4. Click 'Edit' and type in `pollsarchive` in the text field and click 'Save'.
5. Type `[page_polls]` in the post's content area.
6. Click 'Publish'.

* If you ARE NOT using nice permalinks, you need to go to `WP-Admin -> Polls -> Poll Options` and under `Poll Archive -> Polls Archive URL`, you need to fill in the URL to the Polls Archive Page you created above.

### Why doesn't my poll's answers add up to 100%?
* It is because of rounding issues. To make it always round up to 100%, the last poll's answer will get the remainding percentage added to it. To enable this feature, add this to your theme's functions.php: `add_filter( 'wp_polls_round_percentage', '__return_true' );`

### How Does WP-Polls Load CSS?
* WP-Polls will load `polls-css.css` from your theme's directory if it exists.
* If it doesn't exists, it will just load the default `polls-css.css` that comes with WP-Polls.
* This will allow you to upgrade WP-Polls without worrying about overwriting your polls styles that you have created.

### Why In Internet Explorer (IE) The poll's Text Appear Jagged?
* To solve this issue, Open poll-css.css
* Find: `/* background-color: #ffffff; */`
* Replace: `background-color: #ffffff;` (where #ffffff should be your background color for the poll.)

### How Do I Have Individual Colors For Each Poll's Bar?
* Courtesy Of [TreedBox.com](http://treedbox.com "TreedBox.com")
* Open poll-css.css
* Add to the end of the file:

<code>
.wp-polls-ul li:nth-child(01) .pollbar{ background:#8FA0C5}
.wp-polls-ul li:nth-child(02) .pollbar{ background:#FF8}
.wp-polls-ul li:nth-child(03) .pollbar{ background:#ff8a3b}
.wp-polls-ul li:nth-child(04) .pollbar{ background:#a61e2a}
.wp-polls-ul li:nth-child(05) .pollbar{ background:#4ebbff}
.wp-polls-ul li:nth-child(06) .pollbar{ background:#fbca54}
.wp-polls-ul li:nth-child(07) .pollbar{ background:#aad34f}
.wp-polls-ul li:nth-child(08) .pollbar{ background:#66cc9a}
.wp-polls-ul li:nth-child(09) .pollbar{ background:#98CBCB}
.wp-polls-ul li:nth-child(10) .pollbar{ background:#a67c52}
.wp-polls-ul li .pollbar{ transition: background 0.7s ease-in-out }
.wp-polls-ul li .pollbar:hover{ background:#F00 }
</code>

### Polls Stats (Outside WP Loop)

### To Display Total Polls
* Use:
<code>
&lt;?php if (function_exists('get_pollquestions')): ?&gt;
&nbsp;&nbsp;&lt;?php get_pollquestions(); ?&gt;
&lt;?php endif; ?&gt;
</code>

### To Display Total Poll Answers
* Use:
<code>
&lt;?php if (function_exists('get_pollanswers')): ?&gt;
&nbsp;&nbsp;&lt;?php get_pollanswers(); ?&gt;
&lt;?php endif; ?&gt;
</code>

### To Display Total Poll Votes
* Use:
<code>
&lt;?php if (function_exists('get_pollvotes')): ?&gt;
&nbsp;&nbsp;&lt;?php get_pollvotes(); ?&gt;
&lt;?php endif; ?&gt;
</code>

### To Display Total Poll Voters
* Use:
<code>
&lt;?php if (function_exists('get_pollvoters')): ?&gt;
&nbsp;&nbsp;&lt;?php get_pollvoters(); ?&gt;
&lt;?php endif; ?&gt;
</code>
