# WP-Polls
Contributors: GamerZ  
Donate link: https://lesterchan.net/site/donation/  
Tags: poll, polls, vote, ajax, survey  
Requires at least: 6.8  
Tested up to: 7.1  
Stable tag: 3.0.1  
Requires PHP: 8.2  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds an AJAX poll system to your WordPress blog. You can also easily add a poll into your WordPress's blog post/page.

## Description
WP-Polls adds a poll to your site: a question, its answers, and a result bar that replaces the voting form once a visitor has voted, without a page reload. A poll can go in a post with a shortcode or a block, in a sidebar with the widget, or anywhere in your theme with a template tag.

A poll can accept one answer or several, close on a date you set, and be shown as a form or as its result. The markup of every part of it is a template you can edit, and the bars are styled with CSS custom properties your theme can override.

### Donations
I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appreciate it. If not feel free to use it without any obligations.


## Installation

1. Install and activate the plugin.
1. Create your first poll at `WP-Admin -> Polls -> Add Poll`.
1. Show it: type `[poll]` into a post, add the **Poll** block, add the **Polls** widget to a sidebar, or call `get_poll()` from your theme. Usage below covers all four.
1. Templates, the bar, who may vote and how a repeat vote is spotted are all at `WP-Admin -> Polls -> Settings`.

## Usage

### Showing A Poll From A Theme

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

### Showing A Poll In A Block

Two blocks are available in the editor, under **Widgets**:

* **Poll** — one poll, as its voting form or as its result. Set **Poll ID** in the sidebar, or leave it at zero for the current poll, which is what an empty `[poll]` does. **Show** chooses between the voting form and the result.
* **Polls Archive** — every poll with its results, the same listing `[page_polls]` produces.

Both render on the server, so the block preview in the editor is the real poll rather than an approximation, and changing a poll updates every post showing it without re-saving anything.

**The shortcodes still work and are not going anywhere.** `[poll]`, `[poll=2]` and `[page_polls]` behave exactly as they always have, and a post already containing one needs no change. The blocks call the same code the shortcodes call, so the two render identically — use whichever suits the post.

### Showing A Poll In A Widget
1. Go to `WP-Admin -> Appearance -> Widgets`.
2. Add the **Polls** widget to a widget area. On block themes the widget is under the *Legacy Widget* block, or in `Appearance -> Editor` if your theme has no widget areas at all.
3. Set its title and which poll it shows, then save.
4. Scroll down for instructions on how to create a Polls Archive.

### How To Add A Polls Archive?
1. Go to `WP-Admin -> Pages -> Add New`.
2. Type any title you like in the post's title area.
3. If you ARE  using nice permalinks,  after typing the title, WordPress will generate the permalink to the page. You will see an 'Edit' link just beside the permalink.
4. Click 'Edit' and type in `pollsarchive` in the text field and click 'Save'.
5. Type `[page_polls]` in the post's content area.
6. Click 'Publish'.

* If you ARE NOT using nice permalinks, go to `WP-Admin -> Polls -> Settings` and fill in `Poll Archive URL`, under the `Archive` heading, with the URL of the Polls Archive page you created above.

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

### WP-CLI
```
wp polls list
wp polls list --status=open
wp polls get 3
wp polls open 3
wp polls close 3
wp polls delete 3 --yes
```

`wp polls list --status=open --format=ids | xargs -n1 wp polls close` closes every open poll one at a time.

### REST API
```
GET  /wp-json/polls/v1/poll/<id>
GET  /wp-json/polls/v1/poll/<id>/result
POST /wp-json/polls/v1/poll/<id>/vote
```

Reading is public, because a poll is public. Voting takes the same `poll_<id>-nonce` the rendered voting form already carries, passed as a `nonce` parameter, and is subject to the same eligibility and repeat-vote settings as voting through the page.

Each response carries the rendered markup as well as the numbers, because your templates decide what a poll looks like and a client that rebuilt the markup itself would ignore them.

**A refusal answers 403**, not 400 — a closed poll, a vote already cast, a bad nonce, nothing selected. 400 is kept for a parameter this plugin never had a chance to look at, and a poll that does not exist is 404. So a 403 means the request was understood and the answer is no, and sending it again differently will not help.

**These routes are an addition.** The `admin-ajax.php` `polls` action is unchanged and still supported.

### Translating the template

The plugin templates can be translated via template variables.
There are these filters for the custom template variables
```
wp_polls_template_voteheader_variables
wp_polls_template_votebody_variables
wp_polls_template_votefooter_variables
wp_polls_template_resultheader_variables
wp_polls_template_resultbody_variables
wp_polls_template_resultfooter_variables
```

The Result Body (Voted) and Result Footer (Voted) templates are filtered by
`wp_polls_template_resultbody_variables` and `wp_polls_template_resultfooter_variables`
too - the variables are shared, only the markup filters are separate.

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

## Frequently Asked Questions

### Everyone can vote as many times as they like
Check **Settings -> Header That Contains The IP**. If it names a header such as
`HTTP_X_FORWARDED_FOR`, make sure the proxy in front of WordPress always overwrites
that header. Anything a visitor can set themselves can be changed on every request,
and WP-Polls has no way to tell a real proxy from a forged header.

Before 3.0.0 WP-Polls also used the entire header value as the voter's identity.
`X-Forwarded-For` is a list, `client, proxy1, proxy2`, so a visitor could append one
more entry and count as somebody new. It now reads only the first address in the list.

If you are not behind a proxy, leave the setting blank.

### Every voter is logged with the same IP, or the wrong one
WordPress is behind a proxy or a CDN and every request reaches it from the proxy's
address. Name the header your proxy sets under **Settings -> Header That Contains
The IP** — `HTTP_CF_CONNECTING_IP` for Cloudflare — or, to trust the usual set of
proxy headers without naming one, add this to `wp-config.php`:

```php
define( 'WP_POLLS_TRUST_PROXY', true );
```

Sites that need to decide per request can use the `wp_polls_trust_proxy` filter, which
defaults to that constant.

### Poll logs show a hashed IP rather than an address
That is deliberate and unchanged: WP-Polls stores `wp_hash()` of the address, so the
logs can tell two voters apart without keeping their IP addresses.

### My poll bars lost their styling, or my customised result template came back
3.0.0 rebuilt the poll bar. It used to be one `<div class="pollbar">` sized by an
inline width; it is now a track holding a fill:

```
<div class="wp-polls-bar" aria-hidden="true"><div class="wp-polls-bar-fill" style="width: 42%;"></div></div>
```

The **Result Body** and **Result Body (Voted)** templates are rewritten to that markup
on upgrade, *including customised ones*. There was no way to keep them: the class names
and the stylesheet changed with the markup, so a customised copy of the old template
would have rendered a bar with no rules left to match it. Re-apply your changes on
the **Templates** tab, keeping the two `wp-polls-bar` elements.

If you re-add a bar by hand, use `%POLL_ANSWER_PERCENTAGE%` for the width.
`%POLL_ANSWER_IMAGEWIDTH%` was removed in 3.0.0 and is no longer substituted, so a
template still containing it emits the literal token into the `style` attribute and
the bar renders with no width at all.

If your theme ships its own `wp-polls.css`, the bar rules are not in it — they used to
be generated by PHP into an inline `<style>` block, and they now live in the plugin's
stylesheet. Either copy the `.wp-polls-bar` rules across, or delete your copy and
override these instead, which is the supported way now:

```
.wp-polls {
	--wp-polls-bar-height: 8px;
	--wp-polls-bar-background: #d8e1eb;
	--wp-polls-bar-border: #c8c8c8;
	--wp-polls-bar-radius: 3px;
}
```

### Why doesn't my poll's answers add up to 100%?
* It is because of rounding issues. To make it always round up to 100%, the last poll's answer will get the remainding percentage added to it. To enable this feature, add this to your theme's functions.php: `add_filter( 'wp_polls_round_percentage', '__return_true' );`

### How Does WP-Polls Load CSS?
* The stylesheet and the voting script load only on pages that show a poll, a polls archive or the poll widget; a page without one carries neither.
* WP-Polls will load `wp-polls.css` from your theme's directory if it exists.
* If it does not exist, it loads the `css/wp-polls.css` that ships with WP-Polls.
* This will allow you to upgrade WP-Polls without worrying about overwriting your polls styles that you have created.
* A theme copy made before 3.0.0 has no poll bar rules in it, because they used to be generated by PHP. See *My poll bars lost their styling* above.

### How Do I Have Individual Colors For Each Poll's Bar?
* Courtesy Of [TreedBox.com](https://treedbox.com "TreedBox.com")
* Set `--wp-polls-bar-background` on the answer rather than styling the bar itself. The bar reads that custom property, so this works whichever poll bar style is configured, and it keeps working if the markup changes again.
* Add to the end of your `wp-polls.css`:

```
.wp-polls-ul li:nth-child(01) { --wp-polls-bar-background: #8fa0c5; }
.wp-polls-ul li:nth-child(02) { --wp-polls-bar-background: #ffff88; }
.wp-polls-ul li:nth-child(03) { --wp-polls-bar-background: #ff8a3b; }
.wp-polls-ul li:nth-child(04) { --wp-polls-bar-background: #a61e2a; }
.wp-polls-ul li:nth-child(05) { --wp-polls-bar-background: #4ebbff; }
.wp-polls-ul li:nth-child(06) { --wp-polls-bar-background: #fbca54; }
.wp-polls-ul li:nth-child(07) { --wp-polls-bar-background: #aad34f; }
.wp-polls-ul li:nth-child(08) { --wp-polls-bar-background: #66cc9a; }
.wp-polls-ul li:nth-child(09) { --wp-polls-bar-background: #98cbcb; }
.wp-polls-ul li:nth-child(10) { --wp-polls-bar-background: #a67c52; }
.wp-polls-ul li:hover { --wp-polls-bar-background: #ff0000; }
.wp-polls .wp-polls-bar-fill { transition: background-color 0.7s ease-in-out; }
```

Before 3.0.0 this was written against `.pollbar`, the single element the bar used to
be. That class no longer exists, so the old snippet colours nothing.

## Screenshots

1. Polls -> Manage Polls, every poll with its voters, its dates and whether it is open
2. Add Poll: the question, its answers, when it closes, and how many answers a voter may pick
3. Poll Settings: the bar, the sort order, who may vote, and how a repeat vote is spotted
4. The Templates tab, holding the markup of the poll, of its results and of the archive
5. A poll in a post, waiting for a vote
6. The polls archive, every poll with what people said
7. The Poll block in the editor: the preview is the real poll, and the sidebar picks which poll and whether to show the voting form or the result

## Changelog
### 3.0.1
* CHANGED: The stylesheet, the voting script and the inline poll bar styles load only on pages that show a poll, a polls archive or the poll widget; every other page sheds all three. If a caching or optimisation plugin combines assets per page, its combined file now differs between pages with and without a poll — that is this change, not a fault, and its cache can simply be regenerated.
* NEW: A Settings link on the plugin's row on the Plugins screen.
* CHANGED: Upgrade routines run on `init` rather than `admin_init`, so a site updated in the background — by automatic updates or WP-CLI — is migrated on its next request of any kind instead of waiting for somebody to open wp-admin.
* CHANGED: Only one request at a time runs the outstanding upgrade. Running it on `init` means running it on front-end requests, so two visitors arriving together could both start the option migration and do the same work twice over; now the second waits for the first and finds nothing left to do.

### 3.0.0
* FIXED: The limit on how many answers a vote may select was enforced in the browser and nowhere else, so one crafted request could vote for every answer of a single-choice poll — each answer gaining a vote and the poll's total gaining several, while the voter count went up by one. That leaves the percentages, `%POLL_MOST_ANSWER%` and `%POLL_LEAST_ANSWER%` permanently wrong. The maximum is checked on the server now, on both the AJAX and REST paths
* NEW: A `wp polls` WP-CLI command — `list`, `get`, `open`, `close` and `delete`.
* NEW: A `polls/v1` REST API for reading a poll, reading its result and voting. The `admin-ajax.php` `polls` action is unchanged and still supported.
* NEW: Two editor blocks, **Poll** and **Polls Archive**, both under Widgets. They render on the server through the same code the shortcodes use, so a block and a shortcode showing the same poll produce the same markup. The `[poll]`, `[poll=2]` and `[page_polls]` shortcodes are unchanged and still supported — nothing needs converting, and posts already containing them keep working.
* BREAKING: Requires WordPress 6.8 and PHP 8.2.
* BREAKING: The scripts no longer define any global JavaScript functions. `poll_vote()`, `poll_result()`, `poll_booth()` and the admin equivalents are now private, so custom templates or themes that called them directly must move to `data-poll-id` / `data-poll-action` attributes. WP-Polls converts the stock templates for you on upgrade and warns in wp-admin about any it could not convert.
* CHANGED: Options, templates, settings, the widget and the install/upgrade routine moved into classes under `includes/`. The documented extension points are unchanged: every `wp_polls_*` filter and action, both the `[poll]` and `[page_polls]` shortcodes, and the template tags keep their exact names and signatures.
* CHANGED: The thirty-odd separate `wp_options` rows are now a single `wp_polls_options` row holding a nested array. Your settings are migrated automatically on upgrade; the old rows are removed once they have been folded in.
* FIXED: XSS in the Poll Templates screen. Inline `onclick` handlers are replaced by `data-poll-action` / `data-poll-id` attributes and `onclick` is no longer an allowed attribute in poll templates.
* FIXED: On multisite, uninstall called `restore_current_blog()` once after the loop rather than once per site. `switch_to_blog()` pushes onto a stack, so the stack was left unwound by every site but the first.
* FIXED: On uninstall the three poll tables were dropped from inside the loop over option rows, so the drop ran 36 times per site and issued three `DROP TABLE` statements each instead of three in total.
* CHANGED: Uninstall asks `get_sites()` for IDs only rather than hydrating a `WP_Site` object per site, and the table-dropping work now lives on `WP_Polls_Install::uninstall_site()` instead of occupying the unprefixed global name `plugin_uninstalled()`.
* FIXED: Network activating on multisite was a fatal error. The activation routine called `wp_get_sites()`, which WordPress removed in 5.1.
* FIXED: On multisite the three poll tables were not registered with `$wpdb`, so any query made inside `switch_to_blog()` read and wrote the wrong site's polls.
* FIXED: Adding or removing a poll answer in wp-admin no longer breaks. It called jQuery's `.size()`, which was removed in jQuery 3.
* FIXED: The poll bar tooltip showed a literal `&amp;` for any answer containing an ampersand. The polls archive had its own copy of the same bug in `%POLL_ANSWER_TEXT%`, which is fixed too.
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
* CHANGED: The Settings and Templates tabs now use the WordPress Settings API instead of hand-rolled form handling. Every row on both tabs is registered with `add_settings_section()` and `add_settings_field()` and rendered by `do_settings_sections()`, so the screens look and behave like the rest of wp-admin and neither one writes any table markup of its own.
* CHANGED: Poll Bar Background and Poll Bar Border are colour pickers now - the browser's own colour input, the same control WP-Postratings uses - rather than six character text fields with a `#` printed beside them and a swatch kept in step by JavaScript. The bar preview follows the colour as it is picked. The setting is still stored as the six digits without the `#`, so a theme or filter reading it sees what it always did; a three digit value left over from 2.x is expanded to six on the way out, because a colour input will not display `#abc`.
* CHANGED: Manage Polls is now a `WP_List_Table`, so it paginates at 20 polls a page, sorts on ID, Total Voters and Start Date, and puts Edit, Logs and Delete in hover row actions instead of three columns of links. The poll the site is currently showing is still highlighted.
* CHANGED: Add Poll, Edit Poll and Poll Logs are built out of the standard wp-admin furniture - `form-table` rows with real labels, `submit_button()`, `notice` messages, and one `h1` per screen - rather than tables laid out with `width`, `valign` and `align` attributes. The Cancel buttons are links back to Manage Polls instead of a `history.go(-1)` that could not be middle-clicked or opened in a new tab.
* FIXED: The stats under Manage Polls were accumulated while printing rows, which would have counted only the page being looked at once the list paginated. They are summed in SQL now.
* FIXED: Opening the logs of a poll that allows multiple answers raised four undefined variable warnings: that filter's fields were only initialised inside the branch that handles its own submission.
* FIXED: The poll logs named their answers from a list built while printing the filter dropdown, so with the `wp_polls_log_show_log_filter` filter turned off every group heading in the log was blank. A vote recorded against no answer at all also warned instead of printing "Null Votes".
* FIXED: Rows in the by-voter poll log never alternated: the row counter was reset inside the loop rather than at each new voter.
* FIXED: Changing "Expiry Time For Cookie And Log" did not reschedule the cron job. The callback that rebuilds the schedule was registered while the Poll Options screen rendered, but the save happens on `options.php`, which never loads that screen.
* CHANGED: Removed every remaining inline `onclick`/`onblur`/`onchange` handler from the admin pages in favour of `data-poll-action` attributes and delegated listeners, so poll questions and answers no longer have to be escaped into a JavaScript context.
* CHANGED: Dropped the jQuery dependency. Both scripts, the inline admin scripts and the TinyMCE plugin now use the browser's own APIs, so WP-Polls no longer forces jQuery to load on the front end.
* CHANGED: The scripts ship as readable source; the `.dev.js` copies have been removed.
* FIXED: The `polls-admin` AJAX handler now checks the `manage_polls` capability instead of relying on its nonces for authorisation.
* FIXED: Escaped the poll bar colours, the voting form action and `%POLL_RESULT_URL%` on output, and validated the poll bar colours on save.
* BREAKING: "Poll Logging Method" is now "Check For Repeat Votes", and it no longer decides whether anything is logged. It never did anything but choose what a returning visitor is matched against, and the two readings pointed opposite ways: a site that picked "Do Not Log" or "Logged By Cookie" got no vote log, no rows on the Logs screen and no WP-Stats figures, which is not what either choice sounds like. **Every vote is now recorded whatever this is set to.** The choices read "Do Not Check", "Check By Cookie", "Check By IP Address", "Check By Cookie And IP Address" and "Check By Username"; the stored numbers are unchanged, so your setting means what it always meant. The option key inside `wp_polls_options` is `check_method` rather than `logging_method`, migrated automatically. Sites that would rather not keep the record can return false from the new `wp_polls_log_vote` filter; the answer tallies and poll totals are columns on the poll tables and are updated either way.
* BREAKING: "Expiry Time For Cookie And Log" is now "Remember A Voter For", and its hint no longer says the opposite of what the setting does. It offered "0 to disable"; zero disables nothing, it is what makes the block permanent -- a cookie lasting a year and no time limit at all on the IP and username queries. Nothing expires in the sense of being deleted either: the poll log keeps every vote whatever this says. All it decides is how far back a repeat-vote check looks. The value is unchanged, so existing sites behave exactly as before.
* BREAKING: The "Polls AJAX Style" settings are gone. They chose whether to show a loading indicator and whether to fade the poll while a vote was in flight. Telling somebody their vote is being processed is feedback rather than decoration, so the indicator now always shows; and whether to animate is the visitor's answer rather than the site owner's, so the fade follows `prefers-reduced-motion` like the spinner and the result bars already did. `poll_ajax_style` is dropped on upgrade rather than carried.
* BREAKING: The poll bar has been rebuilt and the change is applied on upgrade. It was one `<div class="pollbar">` sized by an inline width; it is now a `wp-polls-bar` track holding a `wp-polls-bar-fill`, which is what lets a 100% answer render at a true 100%. The Result Body and Result Body (Voted) templates are rewritten to the new markup on upgrade **even if you had customised them** - the class names and the stylesheet moved with the markup, so a customised copy of the old template has no rules left to match it. Re-apply your changes on Poll Templates. See the FAQ.
* BREAKING: The poll bar rules moved out of the PHP-generated inline `<style>` block and into `css/wp-polls.css`. Only four custom properties are emitted inline now - `--wp-polls-bar-height`, `--wp-polls-bar-background`, `--wp-polls-bar-border` and `--wp-polls-bar-image` - and overriding those is the supported way to restyle the bar. If your theme ships its own `wp-polls.css`, the bar rules are not in it; see the FAQ.
* BREAKING: The `images/default` and `images/default_gradient` poll bar tiles are gone. The two shaded styles were 1px wide GIFs that carried their own colours, so picking one silently discarded the Poll Bar Background setting. Poll Bar Style is now Flat or Gradient, both drawn in CSS from the colour you configure. `default` and `default_gradient` become Gradient on upgrade, and the old "Use CSS Style" becomes Flat.
* FIXED: A poll answer with every vote drew a 99% bar, and in the polls archive every bar drew at 90% of its real percentage, so the same answer was a visibly different length in the two places. Both fudges existed to stop the old bar's border overflowing its container and are no longer needed.
* FIXED: An answer with no votes drew a 1% sliver of a bar rather than an empty one.
* FIXED: The polls archive applied the `wp_polls_round_percentage` rounding buffer unconditionally while the poll itself only applied it when the filter was turned on, so on a default install the same poll reported one set of percentages on the page and a different set in the archive. The archive now honours the filter, which defaults to off. If you relied on archive percentages summing to exactly 100, add `add_filter( 'wp_polls_round_percentage', '__return_true' );` to turn it back on — it now applies in both places.
* FIXED: With the `wp_polls_round_percentage` filter enabled, the last answer's printed percentage gets a rounding buffer added so the column sums to 100. The bar width was calculated before that happened, so the last answer printed one number and drew a different one. Every bar now matches the percentage beside it.
* BREAKING: The `%POLL_ANSWER_IMAGEWIDTH%` template variable has been removed and is no longer substituted. It only ever held the percentage with a fudge applied so the old bar's border did not overflow, and with the fudges gone it said nothing `%POLL_ANSWER_PERCENTAGE%` does not. Use `%POLL_ANSWER_PERCENTAGE%` instead. The upgrade rewrites both result templates, so you only need to act if you re-customise them afterwards.
* CHANGED: The poll bar no longer carries a `title` tooltip repeating the percentage and vote count that are already printed beside it, and is marked `aria-hidden` so screen readers do not read those numbers twice. On Result Body (Voted) the "You Have Voted For This Choice" tooltip moves onto the answer text itself.
* CHANGED: The bar fill animates in, and honours `prefers-reduced-motion`.
* CHANGED: `wp-polls-rtl.css` has been removed. `css/wp-polls.css` now uses CSS logical properties, so the one stylesheet lays out correctly in both directions and right-to-left sites load one file fewer. If your theme overrides `wp-polls-rtl.css`, fold those rules into your `wp-polls.css`.
* BREAKING: If you set "Header That Contains The IP", WP-Polls used the whole header as the voter's identity. `X-Forwarded-For` is a chain the visitor controls the left of, so appending one more hop produced a different identity and another vote. It now takes the first valid address in the header, and falls back to `REMOTE_ADDR` when the header holds no address at all. Sites that left the setting blank are unaffected and their existing vote logs still match. See the FAQ.
* CHANGED: Added the `WP_POLLS_TRUST_PROXY` constant and the `wp_polls_trust_proxy` filter, matching WP-Email and WP-UserOnline, so sites behind Cloudflare or a load balancer can opt in to the usual proxy headers without naming one on the settings screen. Proxy headers are still ignored unless you opt in.
* CHANGED: Reformatted to the WordPress Coding Standards. Fifteen translatable strings gained numbered placeholders (`%1$s`), which changes their msgid, so those strings need retranslating.
* BREAKING: The settings row is named `wp_polls_options` rather than `poll_options`, and the two version markers `poll_version` and `poll_db_version` collapse into one `wp_polls_version` row holding `plugin` and `db`. All three old rows are folded in and removed on upgrade.
* BREAKING: Poll Options and Poll Templates are two tabs of one **Polls -> Settings** screen instead of two menu entries. Their URLs change from `admin.php?page=wp-polls/polls-options.php` and `…/polls-templates.php` to `admin.php?page=wp-polls-settings&tab=options` and `&tab=templates`, and Manage Polls and Add Poll move to `admin.php?page=wp-polls` and `?page=wp-polls-add`.
* BREAKING: WP-Polls no longer reads or writes WP-Stats' shared `stats_display` row. Whether the polls block appears on the WP-Stats page is now a WP-Polls setting, under **WP-Polls -> Settings -> WP-Stats**, and WP-Polls contributes its block through the `wp_stats_sections` filter. Update all seven WP-Stats plugins together.
* BREAKING: Every class is prefixed `WP_Polls_`: `Polls_Options` is `WP_Polls_Options`, `Polls_Display` is `WP_Polls_Display`, `Polls_Vote` is `WP_Polls_Vote`, `Polls_Settings` is `WP_Polls_Settings`, `Polls_Install` is `WP_Polls_Install`, `Polls_Widget` is `WP_Polls_Widget`, `Polls_List_Table` is `WP_Polls_List_Table`, `Polls_Templates` is `WP_Polls_Template` and `Polls_Core` is plain `WP_Polls`. The template tags, the shortcodes and every `wp_polls_*` hook are unchanged.
* NEW: `WP_POLLS_DB_VERSION` alongside `WP_POLLS_VERSION`, and a `wp_polls_capability` filter over the `manage_polls` check.
* CHANGED: The plugin files are laid out the way every other plugin here is: the five loose screens move into `includes/`, the stylesheets into `css/` and the scripts into `js/`, named after the plugin. A theme overriding `polls-css.css` should rename its copy `wp-polls.css`.
* CHANGED: The localised script objects are `wpPollsL10n` and `wpPollsAdminL10n`, replacing `pollsL10n` and `pollsAdminL10n`.
* CHANGED: The loading indicator is drawn in CSS instead of loading `images/loading.gif`, so it inherits the theme's colour, scales with the font size and stops animating for a visitor who has asked for reduced motion. `images/` is gone.
* CHANGED: No wp-admin screen carries an inline `style` attribute any more: status messages use core's `notice` classes and anything that starts hidden uses core's `.hidden` class.
* CHANGED: The front end stylesheet sets no font and no text colour, and its hardcoded blues, blacks and whites become `currentColor` plus the `--wp-polls-border` and `--wp-polls-surface` custom properties, so a poll is legible on a dark theme without a second stylesheet.
* CHANGED: The two inline `<script>` blocks the settings screens carried - the poll bar preview and the Restore Default Template buttons - moved into `js/wp-polls-admin.js`.
* FIXED: `current_time( 'timestamp' )`, which WordPress deprecated, is replaced by `WP_Polls::now()`. Poll times are still stored as site-local, so nothing shifts.
* NOTE: Every filter and action WP-Polls fires now carries a docblock recording what it is passed and which release introduced it.
* FIXED: The "maximum number of choices allowed" alert ended in a trailing space inside the translated string, and the script added a space of its own -- so the number was preceded by two. The space belongs to the script, which is the same in every language

## Upgrade Notice

### 3.0.0

Requires WordPress 6.8 and PHP 8.2.

**This release fixes a stored XSS, so take it even if nothing else here applies.** A poll question or answer containing markup was written into the Poll Templates screen through an inline `onclick`, where it could put a working script into the screen of anyone able to manage polls. Inline handlers are replaced by `data-poll-action` / `data-poll-id` attributes throughout, `onclick` is no longer an allowed attribute in poll templates, and the poll bar colours, the voting form action and `%POLL_RESULT_URL%` are escaped on output.

**Update all seven WP-Stats plugins together.** WP-Polls, WP-Stats, WP-PostRatings, WP-PostViews, WP-UserOnline, WP-EMail and WP-DownloadManager shared one unprefixed `stats_display` row recording which blocks the WP-Stats page shows. Each keeps its own copy now, and the shared row is deleted by whichever you update first. A missing row means "on", so a block you had hidden may reappear; if a block you wanted is missing, switch it back on from that plugin's own settings. For WP-Polls that is **WP-Polls -> Settings -> WP-Stats**.

**The settings screens moved.** Poll Options and Poll Templates are two tabs of one **Polls -> Settings** screen. `admin.php?page=wp-polls/polls-options.php` and `.../polls-templates.php` no longer resolve; use `admin.php?page=wp-polls-settings`. Manage Polls is `admin.php?page=wp-polls` and Add Poll is `admin.php?page=wp-polls-add`.

**Settings migrate on the first admin page load.** The thirty-odd `poll_*` rows, the older `poll_options` row and both version markers are folded into `wp_polls_options` and `wp_polls_version`; the old rows are removed once they have been read.

**The poll bar is rebuilt, and two templates are replaced.** Customised **Result Body** or **Result Body (Voted)** templates are overwritten and those changes are lost. There was no way to carry them forward: the class names and the stylesheet changed with the markup, so a customised copy of the old template has no rules left to match it. Re-apply your changes on Poll Templates, keeping the two `wp-polls-bar` elements. `%POLL_ANSWER_IMAGEWIDTH%` no longer exists; use `%POLL_ANSWER_PERCENTAGE%`.

**If your theme ships its own copy of the stylesheet**, rename it from `polls-css.css` to `wp-polls.css` or it will stop being used. Delete `polls-css-rtl.css`; there is one stylesheet now. The poll bar rules are absent from any copy made before 3.0.0, because they used to be generated by PHP — either copy the `.wp-polls-bar` rules across, or override the `--wp-polls-bar-*` custom properties, which is the supported way.

**If a custom template calls `poll_vote()`, `poll_result()` or `poll_booth()` from an inline `onclick`,** those functions no longer exist. The stock templates are converted on upgrade and a warning in wp-admin names any that could not be converted; replace the handler with `data-poll-id="%POLL_ID%"` and `data-poll-action="vote"` (or `result` / `booth`).

**Classes are prefixed `WP_Polls_`**, and `Polls_Core` is plain `WP_Polls`. The template tags — `get_poll()`, `vote_poll()`, `display_polls_archive_link()`, `in_pollarchive()` and the `get_poll*` counters — both shortcodes, and all thirty-odd `wp_polls_*` filters and actions are unchanged.

**If you set "Header That Contains The IP",** check it is a header your proxy always overwrites. WP-Polls reads only the first address in it now, so votes recorded against a forged chain no longer count as separate voters. Sites that left it blank are unaffected and their vote logs still match.

**`pollsL10n` and `pollsAdminL10n` are now `wpPollsL10n` and `wpPollsAdminL10n`.**
