# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

WP-Polls follows `_standards/STANDARDS.md` in the parent folder, which is the
contract for all nineteen plugins in the collection. Where this file and that
one disagree, that one wins.

## What it is

AJAX polls: a question with answers, a voting form, a result bar, a repeat-vote
check, a per-poll vote log, an archive page, a widget, two shortcodes and a
TinyMCE button. Menu: **Manage Polls**, **Add Poll**, **Settings** (Settings /
Templates tabs). Dashicon `chart-bar` — it draws bars, which is why wp-stats
gave the icon up and took `chart-area`.

At ~6,900 lines of `includes/` it is one of the three heaviest plugins.

## Data

* **Three custom tables**: `$wpdb->pollsq` (questions), `$wpdb->pollsa`
  (answers), `$wpdb->pollsip` (the vote log).
* `wp_polls_options` — folds in the thirty-odd `poll_*` rows plus the older
  `poll_options`. `legacy_map()` drives both the migration and uninstall so the
  two cannot disagree.
* `wp_polls_version` — from `poll_version` and `poll_db_version`.
* One of the seven WP-Stats plugins (§13).

## The shared WP-Stats row, and why there are two legacy lists

`legacy_extra_rows()` holds the rows WP-Polls owns; `legacy_shared_rows()` holds
`stats_display`, which it never did. Both are deleted by the migration, and only
the first is on `WP_Polls_Install::option_names()`, which drives uninstall.

That split is the fix for a release blocker: one list did both jobs, so
uninstalling WP-Polls deleted a row the other six WP-Stats plugins were still
reading and silently reconfigured every one of them. §13.2 draws the line —
the migration deletes a shared row because it has folded it in, uninstall leaves
it alone. **Do not merge the two lists back together**, however tempting the
single-source-of-truth argument looks: it is the argument that caused this.
wp-postratings documents the same arrangement at
`includes/class-wp-postratings-options.php:73-89`, and wp-downloadmanager had
the identical defect on both shared rows.

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
* The `tinymce/` directory is exempted by §1 (only wp-downloadmanager has the
  other). Its `plugin.js` is vanilla JS.
* **`wp_get_sites()` was never removed from core** — it is deprecated and still
  ships in `ms-deprecated.php`, loaded for multisite only. A test asserting
  `! function_exists( 'wp_get_sites' )` therefore passed single-site for the
  wrong reason. The real defect was that it silently activated on only the first
  100 sites (commits `5a7e9ec`, `9101059`).

## Tests

261 PHPUnit tests green single-site and multisite, 30 e2e tests green, PHPCS and
eslint clean — **wp-polls is the one plugin `_standards/RESUME.md` lists as
finished and safe.** It is committed but not pushed, and Lester ships it
manually: do not touch SVN or tags for it.

`test-migration.php` and `test-migration-schema.php` are separate on purpose —
the option fold-in and the table work fail differently. `test-vote-guards.php`
covers the five `check_method` branches.

## Open

`_standards/RESUME.md`: the logs stay a per-poll sub-view (the poll is the entry
point). The one misplaced control is **Delete All Logs**, which is cross-poll but
sits inside a per-poll screen.
