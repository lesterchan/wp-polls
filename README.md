# WP-Polls
Contributors: GamerZ  
Donate link: https://lesterchan.net/site/donation/  
Tags: poll, polls, polling, vote, booth, democracy, ajax, survey, post, widget  
Requires at least: 4.0  
Tested up to: 4.7  
Stable tag: 2.73.3  

Adds an AJAX poll system to your WordPress blog. You can also easily add a poll into your WordPress's blog post/page.

## Description
WP-Polls is extremely customizable via templates and css styles and there are tons of options for you to choose to ensure that WP-Polls runs the way you wanted. It now supports multiple selection of answers.

### General Usage (Without Widget)
1. Open `wp-content/themes/<YOUR THEME NAME>/sidebar.php`
2. Add:
```php
<?php if ( function_exists( 'vote_poll' ) && ! in_pollarchive() ): ?>
	<li>
		<h2>Polls</h2>
		<ul>
			<li><?php get_poll();?></li>
		</ul>
		<?php display_polls_archive_link(); ?>
	</li>
<?php endif; ?>
```

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
### Version 2.73.3
* NEW: Added sort by votes casted to poll answers.
* NEW: For polls with mutiple answers, we divided by total votes instead of total voters. Props @ljxprime.
* FIXED: Do not display poll option is not respected when poll is closed.
* FIXED: pollip_qid, pollip_aid, pollip_timestamp are now int(10) in pollsip table.
* FIXED: pollq_expiry is now int(10) in pollsq table.

### Version 2.73.2
* NEW: Bump WordPress 4.7
* FIXED: Change cron to hourly instead of twice daily.

### Version 2.73.1
* FIXED: Allow local IP
* FIXED: XSS on Poll bar option. Props [Netsparker Web Application Security Scanner](https://www.netsparker.com/)
* FIXED: Stricter Poll pptions check
 
### Version 2.73
* NEW: Display Poll Questions at the top of the Poll Logs table
* FIXED: Remove slashes

### Version 2.72
* NEW: Use translate.wordpress.org to translate the plugin
* FIXED: SQL Injection fixes. Props [Jay Dansand](https://github.com/jaydansand)
* FIXED: Use $wpdb->insert(), $wpdb->update() and $wpdb->delete() as much as possible
* FIXED Remove poll_archive_show option from UI

### Version 2.71
* FIXED: Use wp_kses_post() to get filter always bad tags

### Version 2.70
* NEW: Add wp_polls_vote_poll_success action hook
* NEW: Add wp_polls_add_poll, wp_polls_update_poll, wp_polls_delete_poll action hooks
* FIXED: PHP Notices
* FIXED: Removed not needed wp_print_scripts
* FIXED: Use esc_attr() and esc_textarea() instead of htmlspecialchars(). Props [Govind Singh](https://in.linkedin.com/pub/govind-singh/21/1a9/bab)

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
```php
<?php if ( function_exists( 'get_pollquestions' ) ): ?><?php get_pollquestions(); ?><?php endif; ?> 
```

### To Display Total Poll Answers
* Use:
```php
<?php if ( function_exists( 'get_pollanswers' ) ): ?>
	<?php get_pollanswers(); ?>
<?php endif; ?> 
```

### To Display Total Poll Votes
* Use:
```php
<?php if ( function_exists( 'get_pollvotes' ) ): ?>
	<?php get_pollvotes(); ?>
<?php endif; ?> 
```

### To Display Total Poll Voters
* Use:
```php
<?php if ( function_exists( 'get_pollvoters' ) ): ?>
	<?php get_pollvoters(); ?>
<?php endif; ?> 
```
