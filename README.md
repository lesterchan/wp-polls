# WP-Polls
Contributors: GamerZ  
Donate link: https://lesterchan.net/site/donation/  
Tags: poll, polls, polling, vote, booth, democracy, ajax, survey, post, widget  
Requires at least: 6.0  
Tested up to: 7.0  
Stable tag: 3.0.0  
Requires PHP: 7.4  
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds an AJAX poll system to your WordPress blog. You can also easily add a poll into your WordPress's blog post/page.

## Description
WP-Polls is extremely customizable via templates and css styles and there are tons of options for you to choose to ensure that WP-Polls runs the way you wanted. It now supports multiple selection of answers.

### Development
[https://github.com/lesterchan/wp-polls](https://github.com/lesterchan/wp-polls "https://github.com/lesterchan/wp-polls")

### Credits
* Plugin icon by [Freepik](https://www.freepik.com) from [Flaticon](https://www.flaticon.com)

### Donations
I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appreciate it. If not feel free to use it without any obligations.

## Changelog
### 3.0.0
* BREAKING: Requires WordPress 6.0 and PHP 7.4.
* BREAKING: The scripts no longer define any global JavaScript functions. `poll_vote()`, `poll_result()`, `poll_booth()` and the admin equivalents are now private, so custom templates or themes that called them directly must move to `data-poll-id` / `data-poll-action` attributes. WP-Polls converts the stock templates for you on upgrade and warns in wp-admin about any it could not convert.
* CHANGED: Options, templates, settings, the widget and the install/upgrade routine moved into classes under `includes/`. The documented extension points are unchanged: every `wp_polls_*` filter and action, both the `[poll]` and `[page_polls]` shortcodes, and the template tags keep their exact names and signatures.
* CHANGED: The thirty-odd separate `wp_options` rows are now a single `poll_options` row holding a nested array. Your settings are migrated automatically on upgrade; the old rows are removed once they have been folded in.
* FIXED: XSS in polls-templates.php. Inline `onclick` handlers are replaced by `data-poll-action` / `data-poll-id` attributes and `onclick` is no longer an allowed attribute in poll templates.
* FIXED: Network activating on multisite was a fatal error. The activation routine called `wp_get_sites()`, which WordPress removed in 5.1.
* FIXED: On multisite the three poll tables were not registered with `$wpdb`, so any query made inside `switch_to_blog()` read and wrote the wrong site's polls.
* FIXED: Adding or removing a poll answer in wp-admin no longer breaks. It called jQuery's `.size()`, which was removed in jQuery 3.
* FIXED: The poll bar tooltip showed a literal `&amp;` for any answer containing an ampersand.
* FIXED: Poll Logs had the same double encoding: an answer containing an ampersand showed a literal `&amp;` in both the answer filter and the log table.
* FIXED: Voters identified by the comment author cookie were logged with a backslash before any apostrophe, so a commenter named `O'Brien` appeared in Poll Logs as `O\'Brien`.
* FIXED: `$_SERVER['REMOTE_ADDR']` is no longer read unguarded, which warned under WP-CLI and cron on PHP 8.
* FIXED: The result and vote links no longer follow their placeholder `href` when clicked.
* FIXED: Removed the duplicated shortcode registration.
* FIXED: Undefined array key warnings on missing stats_display options.
* FIXED: Warnings when rendering a poll whose ID no longer exists.
* FIXED: The voting endpoint read `$_REQUEST['view']` without checking it was set, which warned on PHP 8 for any request that left it out.
* FIXED: Activation looked for `wp-admin/upgrade-functions.php`, which WordPress removed in 2.x, and stopped with an error message if it found neither that nor the current file.
* FIXED: Poll Options could offer a poll bar style that saving then rejected, reverting it while still reporting "Settings saved." The screen and the sanitiser now build the list of available styles the same way.
* FIXED: Every stylesheet, script and image URL was built from a hardcoded `wp-polls/` path, so renaming the plugin directory left WP-Polls loading none of its own assets. All paths now come from the plugin file itself.
* CHANGED: Poll Options and Poll Templates now use the WordPress Settings API instead of hand-rolled form handling.
* CHANGED: Removed every remaining inline `onclick`/`onblur`/`onchange` handler from the admin pages in favour of `data-poll-action` attributes and delegated listeners, so poll questions and answers no longer have to be escaped into a JavaScript context.
* CHANGED: Dropped the jQuery dependency. Both scripts, the inline admin scripts and the TinyMCE plugin now use the browser's own APIs, so WP-Polls no longer forces jQuery to load on the front end.
* CHANGED: `polls-js.js` and `polls-admin-js.js` now ship as readable source, the `.dev.js` copies have been removed.
* SECURITY: The `polls-admin` AJAX handler now checks the `manage_polls` capability instead of relying on its nonces for authorisation.
* SECURITY: Escaped the poll bar colours, the voting form action and `%POLL_RESULT_URL%` on output, and validated the poll bar colours on save.
* SECURITY: If you set "Header That Contains The IP", WP-Polls used the whole header as the voter's identity. `X-Forwarded-For` is a chain the visitor controls the left of, so appending one more hop produced a different identity and another vote. It now takes the first valid address in the header, and falls back to `REMOTE_ADDR` when the header holds no address at all. Sites that left the setting blank are unaffected and their existing vote logs still match.
* CHANGED: Added the `WP_POLLS_TRUST_PROXY` constant and the `wp_polls_trust_proxy` filter, matching WP-Email and WP-UserOnline, so sites behind Cloudflare or a load balancer can opt in to the usual proxy headers without naming one on the settings screen. Proxy headers are still ignored unless you opt in.
* CHANGED: Reformatted to the WordPress Coding Standards. Fifteen translatable strings gained numbered placeholders (`%1$s`), which changes their msgid, so those strings need retranslating.

#### Upgrade Notice
Your settings move into a single `poll_options` row automatically the first time wp-admin is loaded after upgrading. If you customised the Voting Form Footer or Result Footer templates, the `onclick` handlers in them are converted too; anything too customised to convert is reported on the Poll Templates page.

If you renamed the plugin directory from `wp-polls`, its stylesheets, scripts and poll bar images were not loading. They will start working on upgrade with no action needed.

If you set "Header That Contains The IP" to `X-Forwarded-For`, check it is a header your proxy always overwrites. WP-Polls now reads only the first address in it, so votes recorded against a forged chain no longer count as separate voters.

### 2.77.3
* FIXED: XSS In poll-logs.php.

### 2.77.2
* FIXED: Read from default REMOTE_ADDR unless specified in options

### 2.77.1
* FIXED: Support mutex lock for multi-site. Props @yrkmann.

### 2.77.0
* NEW: Use mutex lock to prevent race condition.

### 2.76.0
* NEW: Supports specifying which header to read the user's IP from. Props Marc Montpas.

### 2.75.6
* NEW: New filter for template variables: wp_polls_template_votebody_variables, wp_polls_template_votefooter, wp_polls_template_resultheader_variables, wp_polls_template_resultbody_variables, wp_polls_template_resultfooter_variables. Props @Liblastic.
* NEW: composer.json
* FIXED: Missing space for check_voted_username MySQL query

### 2.75.5
* NEW: New filter for templates: wp_polls_template_resultheader_markup, wp_polls_template_resultbody_markup, wp_polls_template_resultbody2_markup, wp_polls_template_resultfooter_markup, wp_polls_template_resultfooter2_markup. Props @Jaska.

### 2.75.4
* FIXED: Unable to edit poll because of class-wp-block-parser.php.

### 2.75.3
* FIXED: Broken filter for templates
* FIXED: Divison by 0 by totalvoters
* FIXED: Add whitelist to sortby poll answers

### 2.75.2
* FIXED: Missing str_replace for wp_polls_template filter

### 2.75.1
* FIXED: Use array() instead of [] as a few users are still on < PHP 5.4. Props @bearlydoug.
* FIXED: pollq_expiry is now 0 instead of blank string. Props @hpiirainen.

### 2.75
* FIXED: Standardize all filters to begin with `wp_polls` rather than `poll`
* NEW: Added `wp_polls_ipaddress` and `wp_polls_hostname` to allow user to overwrite it.

### 2.74.1
* FIXED: Don't use PHP 5.4 Short array syntax.
* FIXED: Division by zero 
* FIXED: Wrong database column type for pollq_expiry

### 2.74
* NEW: Hashed IP and anonymize Hostname to make it GDPR compliance
* NEW: If Do Not Log is set in Poll Options, do not log to DB
* NEW: Support %POLL_MULTIPLE_ANSWER_PERCENTAGE%. This is total votes divided by total voters.

### 2.73.8
* FIXED: Bug fixes and stricter type checking

### 2.73.7
* FIXED: Unable to save input HTML tags for footer templates

### 2.73.6
* FIXED: Unable to vote for multiple answers
* FIXED: input HTML tags being removed when saving templates

### 2.73.5
* FIXED: Parsed error in SERVER variable.

### 2.73.4
* FIXED: sanitize_key on top of intval.

### 2.73.3
* NEW: Added sort by votes casted to poll answers.
* NEW: For polls with mutiple answers, we divided by total votes instead of total voters. Props @ljxprime.
* FIXED: Do not display poll option is not respected when poll is closed.
* FIXED: pollip_qid, pollip_aid, pollip_timestamp are now int(10) in pollsip table.
* FIXED: pollq_expiry is now int(10) in pollsq table.

### 2.73.2
* NEW: Bump WordPress 4.7
* FIXED: Change cron to hourly instead of twice daily.

### 2.73.1
* FIXED: Allow local IP
* FIXED: XSS on Poll bar option. Props [Netsparker Web Application Security Scanner](https://www.netsparker.com/)
* FIXED: Stricter Poll pptions check
 
### 2.73
* NEW: Display Poll Questions at the top of the Poll Logs table
* FIXED: Remove slashes

### 2.72
* NEW: Use translate.wordpress.org to translate the plugin
* FIXED: SQL Injection fixes. Props [Jay Dansand](https://github.com/jaydansand)
* FIXED: Use $wpdb->insert(), $wpdb->update() and $wpdb->delete() as much as possible
* FIXED Remove poll_archive_show option from UI

### 2.71
* FIXED: Use wp_kses_post() to get filter always bad tags

### 2.70
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
* Courtesy Of [TreedBox.com](https://treedbox.com "TreedBox.com")
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
