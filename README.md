# WP-Polls
Contributors: GamerZ  
Donate link: https://lesterchan.net/site/donation/  
Tags: poll, polls, polling, vote, booth, democracy, ajax, survey, post, widget  
Requires at least: 4.9.6  
Tested up to: 6.0  
Stable tag: 2.76.0

Adds an AJAX poll system to your WordPress blog. You can also easily add a poll into your WordPress's blog post/page.

## Description
WP-Polls is extremely customizable via templates and css styles and there are tons of options for you to choose to ensure that WP-Polls runs the way you wanted. It now supports multiple selection of answers.

### Build Status
[![Build Status](https://travis-ci.org/lesterchan/wp-polls.svg?branch=master)](https://travis-ci.org/lesterchan/wp-polls)

### Development
[https://github.com/lesterchan/wp-polls](https://github.com/lesterchan/wp-polls "https://github.com/lesterchan/wp-polls")

### Credits
* Plugin icon by [Freepik](http://www.freepik.com) from [Flaticon](http://www.flaticon.com)

### Donations
I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appreciate it. If not feel free to use it without any obligations.

## Changelog

### Version 2.76.0
* NEW: Supports specifying which header to read the user's IP from. Props Marc Montpas.

### Version 2.75.6
* NEW: New filter for template variables: wp_polls_template_votebody_variables, wp_polls_template_votefooter, wp_polls_template_resultheader_variables, wp_polls_template_resultbody_variables, wp_polls_template_resultfooter_variables. Props @Liblastic.
* NEW: composer.json
* FIXED: Missing space for check_voted_username MySQL query

### Version 2.75.5
* NEW: New filter for templates: wp_polls_template_resultheader_markup, wp_polls_template_resultbody_markup, wp_polls_template_resultbody2_markup, wp_polls_template_resultfooter_markup, wp_polls_template_resultfooter2_markup. Props @Jaska.

### Version 2.75.4
* FIXED: Unable to edit poll because of class-wp-block-parser.php.

### Version 2.75.3
* FIXED: Broken filter for templates
* FIXED: Divison by 0 by totalvoters
* FIXED: Add whitelist to sortby poll answers

### Versiob 2.75.2
* FIXED: Missing str_replace for wp_polls_template filter

### Version 2.75.1
* FIXED: Use array() instead of [] as a few users are still on < PHP 5.4. Props @bearlydoug.
* FIXED: pollq_expiry is now 0 instead of blank string. Props @hpiirainen.

### Version 2.75
* FIXED: Standardize all filters to begin with `wp_polls` rather than `poll`
* NEW: Added `wp_polls_ipaddress` and `wp_polls_hostname` to allow user to overwrite it.

### Version 2.74.1
* FIXED: Don't use PHP 5.4 Short array syntax.
* FIXED: Division by zero 
* FIXED: Wrong database column type for pollq_expiry

### Version 2.74
* NEW: Hashed IP and anonymize Hostname to make it GDPR compliance
* NEW: If Do Not Log is set in Poll Options, do not log to DB
* NEW: Support %POLL_MULTIPLE_ANSWER_PERCENTAGE%. This is total votes divided by total voters.

### Version 2.73.8
* FIXED: Bug fixes and stricter type checking

### Version 2.73.7
* FIXED: Unable to save input HTML tags for footer templates

### Version 2.73.6
* FIXED: Unable to vote for multiple answers
* FIXED: input HTML tags being removed when saving templates

### Version 2.73.5
* FIXED: Parsed error in SERVER variable.

### Version 2.73.4
* FIXED: sanitize_key on top of intval.

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

### General Usage (Without Widget)

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

```
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
```

### To Display Total Polls

```php
<?php if ( function_exists( 'get_pollquestions' ) ): ?>
	<?php get_pollquestions(); ?>
<?php endif; ?> 
```
 
### To Display Total Poll Answers

```php
<?php if ( function_exists( 'get_pollanswers' ) ): ?>
	<?php get_pollanswers(); ?>
<?php endif; ?> 
```
 
### To Display Total Poll Votes

```php
<?php if ( function_exists( 'get_pollvotes' ) ): ?>
	<?php get_pollvotes(); ?>
<?php endif; ?> 
```
 
### To Display Poll Votes by ID

```php
<?php if ( function_exists( 'get_pollvotes_by_id' ) ): ?>
	<?php get_pollvotes_by_id($poll_id); ?>
<?php endif; ?>
```

### To Display Total Poll Voters

```php
<?php if ( function_exists( 'get_pollvoters' ) ): ?>
	<?php get_pollvoters(); ?>
<?php endif; ?> 
```

### To Display Poll Time by ID and date format

```php
<?php if ( function_exists( 'get_polltime' ) ): ?>
	<?php get_polltime( $poll_id, $date_format ); ?>
<?php endif; ?>
```

### Translating the template

The plugin templates can be translated via template variables.
There are these filters for the custom template variables
```
wp_polls_template_votebody_variables
wp_polls_template_votefooter
wp_polls_template_resultheader_variables
wp_polls_template_resultbody_variables
wp_polls_template_resultfooter_variables
```

Add filter to your theme and register custom variable where you will add your translation.
Good practice is to name them for example with prefix `STR_` in the example `STR_TOTAL_VOTERS`.
```php
    /**
     * Localize wp_polls_template_resultfooter_variables.
     *
     * @param array $variables An array of template variables.
     * @return array $variables Modified template variables.
     */
    function wp_polls_template_resultfooter_variables( $variables ) {

        // Add strings.
        $variables['%STR_TOTAL_VOTERS%'] = __( 'Total voters', 'theme-textdomain' );

        return $variables;
    }

// Trigger the filter
add_filter( 'wp_polls_template_resultfooter_variables', 'wp_polls_template_resultfooter_variables' , 10, 1 );
```
In the admin side just call the custom variable like so and the variable has been translated in the front-end.
`%STR_TOTAL_VOTERS%'`
