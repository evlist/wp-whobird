<!--
SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>

SPDX-License-Identifier: GPL-3.0-or-later
-->

# Slice 007: clean-up

## Why

Leftovers found by the audit: debug code that ran on every request, commented-out
code, strings that cannot be extracted for translation, a script that never got
its `ajaxurl`, a FontAwesome handle shared with other plugins and loaded on every
page, and hooks without a documented priority.

## What

- **Debug code removed**
  - `wpwhobird_debug_scripts` logged every enqueued script on every front-end
    request; the "script not enqueued" log is gone with the function it was in.
  - Timing logs (`microtime`) in `update_bird_data()` and `WikidataQuery`, the
    verbose logs of the mapping steps (SPARQL text, `print_r` of the missing
    rows) and the commented-out `error_log` lines. Error logs that report a real
    failure (SQL errors, missing or invalid mapping JSON) are kept.
  - Commented-out blocks of `wp-whobird.php`.
- **`ajaxurl` fixed.** The front-end script needs the `ajaxurl` global, which
  WordPress only defines in the admin. It used to be added from
  `wp_enqueue_scripts` only when the script was already enqueued, but a block's
  view script is enqueued when the block is rendered, after that hook: so the
  condition was never true and `ajaxurl` was not defined (the refresh of stale
  bird entries could not work unless the theme defined it). It is now attached to
  the script handle when the handle is registered, which prints it with the script
  whenever it is enqueued.
- **FontAwesome** is a style of the block (`block.json`: `style` lists
  `wpwhobird-fontawesome`), so it loads only on pages that show the block and in
  the editor, instead of on every page. The handle `font-awesome` (which another
  plugin or theme may register with another version) is no longer used.
- **Hooks in one file.** `wp-whobird.php` only loads the parts and registers the
  activation hooks; the block, assets, admin script and text domain hooks are in
  `includes/plugin-setup.php`, all with explicit priority 10 and handles prefixed
  `wpwbd-` / `wpwhobird-`; the file header documents the coexistence rules. Hooks
  of the plugin's own actions (`wp_ajax_*`, `admin_post_*`) are not shared.
- **Translations.** Messages returned by the mapping sources and the labels of
  the sources are translated where they are created; the code that displays them
  translated them a second time with a variable (`__( $msg )`). Those outer calls
  and the one on the settings descriptions are removed: strings are extractable
  and translated once.
- **phpcs** can be run (`composer lint`): the WordPress standard is registered
  by the Composer plugin (`allow-plugins` in `composer.json`); only PHP files,
  the plugin code and the block. See the open question 3 about the existing
  violations.
- Tests: handles and files registered, dependencies, `ajaxurl` attached before
  the view script, FontAwesome as a block style, admin script only on the tools
  page (with the nonce the AJAX handler checks), text domain, priorities, no debug
  logging left, `wp-whobird.php` without hooks of its own.

## Constraints

Same block name, attributes and markup. No change in what the block outputs.

## Dependencies

Slices 003 (no build step: handles registered in PHP) and 006 (text domain).

## Verification status

PHPUnit and the Node tests, with WordPress functions stubbed. Not tried on a real
site: the block on a page (FontAwesome icon and fonts loading only there, the
Wikipedia link icon), the editor (crow icon), the refresh of stale entries
(`ajaxurl`), the admin mapping tool. The `.pot` is still to regenerate (slice 006).
