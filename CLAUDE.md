# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What it is

AJAX polls: a question with answers, a voting form, a result bar, a repeat-vote
check, a per-poll vote log, an archive page, a widget, two shortcodes and a
TinyMCE button. Menu: **Manage Polls**, **Add Poll**, **Settings** (Settings /
Templates tabs). Dashicon `chart-bar`, because it draws bars. At ~6,900 lines of
`includes/` it is a large plugin.

## Data

* **Three custom tables**: `$wpdb->pollsq` (questions), `$wpdb->pollsa`
  (answers), `$wpdb->pollsip` (the vote log).
* `wp_polls_options` — folds in the thirty-odd `poll_*` rows plus the older
  `poll_options`. `legacy_map()` drives both the migration and uninstall so the
  two cannot disagree.
* `wp_polls_version` — the `plugin` and `db` upgrade markers in one row,
  replacing `poll_version` and `poll_db_version`. Keep them out of the settings
  array: a marker in there has to be rescued from the stored value on every
  save, because the settings form never posts one.
* It contributes a section to **WP-Stats**, a separate plugin, by answering the
  `wp_stats_sections` filter.

## The shared WP-Stats row, and why there are two legacy lists

`legacy_extra_rows()` holds the rows WP-Polls owns; `legacy_shared_rows()` holds
`stats_display`, which it never did. Both are deleted by the migration, and only
the first is on `WP_Polls_Install::option_names()`, which drives uninstall.

That split is the fix for a release blocker: one list did both jobs, so
uninstalling WP-Polls deleted a row that WP-Stats and its other companion
plugins were still reading, and silently reconfigured every one of them. The
line is: **the migration deletes a shared row because it has folded it in;
uninstall leaves it alone**, because a sibling that has not upgraded is still
reading it. **Do not merge the two lists back together**, however tempting the
single-source-of-truth argument looks — it is the argument that caused this.

Pinned by `test_the_shared_stats_row_is_not_on_the_uninstall_list` (the contract)
and `test_uninstall_leaves_the_shared_stats_row_alone` (the behaviour). The
mirror test, `test_every_row_the_plugin_owns_is_on_the_uninstall_list`, walks
`legacy_extra_rows()` and is what stops a row the plugin *does* own drifting off
the list.

## Traps

* **`dbDelta()` runs only for tables that do not exist yet, gated on the schema
  marker.** Re-diffing an existing table makes dbDelta decide `pollq_id`
  (`int NOT NULL auto_increment`) needs a default and emit
  `ALTER TABLE wp_pollsq ALTER COLUMN pollq_id SET DEFAULT ''`, which MySQL
  rejects — and that landed in the error log on **every single activation**.
  Schema changes after the initial create are handled by the explicit index and
  column work below it (`class-wp-polls-install.php:342`+).
* **The 3.0.0 migration is gated on the stored *shape* as well as the version.**
  3.0.0 spent a long time unreleased on the development branch, so an install can
  be stamped 3.0.0 and still hold the scattered rows; a version-only gate would
  skip it and quietly drop that site to defaults.
* **The stored XSS fix is structural, not a patch.** A poll question or answer
  containing markup reached the Poll Templates screen through an inline
  `onclick`. Inline handlers are `data-poll-action` / `data-poll-id` attributes
  throughout, `onclick` is **no longer an allowed attribute in poll templates**,
  and the bar colours, the voting form action and `%POLL_RESULT_URL%` are escaped
  on output. `poll_vote()`, `poll_result()` and `poll_booth()` as global JS
  functions are gone; the migration converts the stock templates and an admin
  notice names any it could not convert.
* **Repeat-vote checking and logging are now separate concerns.** "Poll Logging
  Method" became **"Check For Repeat Votes"** (`logging_method` → `check_method`),
  and **every vote is logged whatever the check says**; `wp_polls_log_vote` turns
  logging off. Stored numbers are unchanged.
* **"Remember A Voter For" (was "Expiry Time For Cookie And Log") — zero makes
  the block permanent.** The old hint said "0 to disable", which was the opposite
  of the truth. Nothing is deleted by it either; it only sets how far back a
  check looks.
* **`setcookie()` is guarded by `headers_sent()`**, matching wp-postratings.
* **Only the first address in the trusted proxy header is read**, so votes
  recorded against a forged chain no longer count as separate voters. Sites that
  left the header blank are unaffected and their vote logs still match.
* **`.wp-polls input { display: inline; border: 0 }` is gone and must not come
  back.** With `appearance: none` an input is a non-replaced inline box, so
  width/height do not apply — Twenty Twenty-One's 25px radio circles rendered as
  6px slivers. The label half of that reset stays.
* **"Polls AJAX Style" is removed.** The loading indicator always shows and the
  fade reads `prefers-reduced-motion` — **from the script**, because the
  transition is inline and an inline style beats a media query.
* **Result Body and Result Body (Voted) templates are overwritten on upgrade and
  customisations are lost.** There was no way to carry them forward: the class
  names and the stylesheet changed with the markup, so a customised copy of the
  old template has no rules left to match it. `%POLL_ANSWER_IMAGEWIDTH%` is gone.
* **The bar is CSS custom properties now, not PHP-generated rules.** A theme copy
  of the stylesheet made before 3.0.0 has no `.wp-polls-bar` rules at all;
  overriding `--wp-polls-bar-*` is the supported route. The theme override is
  `wp-polls.css`, not `polls-css.css`.
* **`check_allowtovote()` reads `$user_ID` into a local rather than reassigning
  the global.** Do not "simplify" it.
* `pollsL10n` / `pollsAdminL10n` are `wpPollsL10n` / `wpPollsAdminL10n`.
* The `tinymce/` directory holds the Classic Editor button. Its `plugin.js` is
  vanilla JS; do not reintroduce jQuery.
* **`wp_get_sites()` was never removed from core** — it is deprecated and still
  ships in `ms-deprecated.php`, loaded for multisite only. A test asserting
  `! function_exists( 'wp_get_sites' )` therefore passed single-site for the
  wrong reason. The real defect was that it silently activated on only the first
  100 sites (commits `5a7e9ec`, `9101059`).

## The blocks

`wp-polls/poll` and `wp-polls/page-polls`, registered by `WP_Polls_Blocks` from
the metadata `bin/build` compiles out of `src/` into `build/`. **`build/` is
generated and gitignored; a checkout that has never been built registers no
blocks**, which `register()` handles by skipping rather than fatalling, so the
shortcodes keep working. `bin/test.sh` and `bin/test-e2e.sh` both build first.

**The blocks wrap the shortcodes and never replace them.** `[poll]`, `[poll=1]`
and `[page_polls]` stay registered and supported; a post holding one needs no
editing. Both entry points meet at `WP_Polls_Display::render_poll( $id, $type )`
and **neither calls the other** -- the block does not run `do_shortcode()`, and
routing it through one would make it inherit shortcode parsing it cannot
produce and break it the day anybody unregisters the shortcode.

**The feed guard lives in the shared renderer, not in the shortcode.** A dynamic
block renders in a feed too, so a guard left in `poll_shortcode()` would put a
voting form in somebody's RSS reader. The positional `[poll=1]` parsing stays in
the shortcode, being syntax a block has no way to express.

**The block name keeps the `wp-` prefix where the command and the namespace drop
it.** `<!-- wp:wp-polls/poll -->` is written into `post_content` and stays there
for the life of the post, so a collision would render another plugin's block
inside published posts -- damage in the database rather than in a shell session.

**`block_editor_styles()` exists because the editor draws the front end's markup
with none of its styles.** `.wp-polls-loading` is hidden by a stylesheet rule
rather than an attribute, so without this every poll in the editor carries a
permanent "Loading ...". Styles only, never the script: the front-end script
attaches vote handlers, and a preview that can be voted in casts real votes from
the editor. Guarded on `is_admin()` because `enqueue_block_assets` fires on the
front end too, where `poll_scripts()` has already run -- and
`wp_add_inline_style()` appends, so twice would emit the bar variables twice.

## WP-CLI and REST

`wp polls list|get|open|close|delete`, and `polls/v1` with three routes: read a
poll, read its result, vote.

**Both go through `WP_Polls_Poll`, and that class exists for a reason.** The
admin handler used to interleave its `$wpdb` calls with the notice markup
announcing them, so a second caller could only copy the queries -- and three
copies of "delete a poll" is three chances to forget that deleting one also
clears its answers, its vote log and the stored latest-poll id. Every method
there returns data or a boolean and prints nothing, because the three callers
disagree about what to say: a notice div, a `WP_CLI::success()` line, a JSON
body.

**The `admin-ajax.php` `polls` action is still registered and still supported.**
A theme or a cached script may be calling it, so the routes were added beside it
rather than in place of it. A test asserts the action survives; if it ever
stops, the routes have become a replacement and somebody's site has broken.

**Voting over REST takes the same `poll_<id>-nonce` the rendered form carries**,
as a `nonce` parameter, and runs the same eligibility and repeat-vote checks.
Reading is public, because a poll is public.

## Migrations, and why they are tested through a browser

`WP_Polls_Install::upgrade()` hangs off `admin_init`, because activation hooks
do not fire on a plugin update — the usual reason a migration never runs at all.
Three separate things happen on that path and `tests/e2e/upgrade.spec.js` covers
each:

* thirty-odd `poll_*` rows fold into one, including the WP-Stats toggle this
  plugin used to read out of a shared row;
* the two result templates are replaced outright, because the bar's markup,
  class names and stylesheet changed together — a template still holding the old
  single `div.pollbar` renders an invisible bar;
* the inline `onclick="poll_vote(%POLL_ID%)"` handlers in the two footer
  templates become `data-poll-*` attributes.

That last one is why the browser test exists at all. Whether the rewrite
produced something that still *works* is a question about a browser: the test
asserts no inline handler survives on the rendered control, then votes through
it and waits for the results to swap in. A row-level assertion would say the
same thing about a template that no longer votes.

Two rules the fixtures follow. **Read rows raw** — `WP_Polls_Options::all()`
merges over the defaults, so it cannot tell a written row from an absent one,
which is the state a migration that read, deleted and never wrote leaves behind.
And **a scalar legacy row reads back as a string**: `poll_close` comes out of
`wp_options` as `"0"` whatever was written, while the rows that were arrays keep
their types. Every reader casts; a test asserting the integer is asserting
something untrue of every install.

## Tests

`bin/test.sh` runs PHPUnit, `bin/test-multisite.sh` the network pass, and
`bin/test-e2e.sh` the Playwright suite. **Run them rather than trusting a note
about their last result** — CI is the authority, and this file cannot be.

`test-upgrade.php` and `test-migration-schema.php` are separate on purpose —
the option fold-in and the table work fail differently. `test-vote-guards.php`
covers the five `check_method` branches.

## Open

The logs stay a per-poll sub-view, because the poll is the entry point. The one
misplaced control is **Delete All Logs**, which is cross-poll but sits inside a
per-poll screen.
