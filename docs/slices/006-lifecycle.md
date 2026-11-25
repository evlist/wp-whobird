<!--
SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>

SPDX-License-Identifier: GPL-3.0-or-later
-->

# Slice 006: uninstall and translations

## Why

Conventions: start each plugin with `uninstall.php`, translatable strings (text
domain, `.pot`) and capability and nonce checks (the last one is slice 004).
Before this slice the plugin left three tables and its options in the database
after being deleted, did not declare its text domain in the plugin header, and
its `.pot` and JavaScript translations were out of date (they date from before
the block was rewritten; `languages/*.json` is keyed by the old `build/…` path).

## What

- `uninstall.php` (runs only on deletion, guarded by `WP_UNINSTALL_PLUGIN`)
  calls `WPWhoBird\Uninstaller::run()` (`includes/Uninstaller.php`):
  - drops the SPARQL cache, mapping and remote files tables;
  - deletes the options (`Uninstaller::optionNames()`: the four registered
    settings, `wpwhobird_fallback_languages`, `whobird_fetched_wikidata_qids`);
  - removes the throttle lock file (`throttle_wikidata.lock` in the system
    temporary directory);
  - on a multisite network, does this for every site;
  - **keeps** the whoBIRD database and the recordings in the uploads directory
    (the user's files).
- Plugin header: `Text Domain: wp-whobird` and `Domain Path: /languages`.
- `WhoBirdActivator`: the mapping table `CREATE TABLE` used an undefined
  variable (`$charset_collate`) and so ignored the charset; fixed.
- Tests: uninstall (tables, options, lock file, uploads untouched, multisite,
  the `uninstall.php` entry point itself), the text domain header, and a scan
  that every translation call in the PHP files uses the `wp-whobird` domain.

## To run in the codespace (not done in this slice, see below)

The translation catalogues need WP-CLI and gettext, which were not available in
the session that wrote this slice:

```bash
wp i18n make-pot . languages/wp-whobird.pot --slug=wp-whobird --domain=wp-whobird \
  --exclude=vendor,tests,third-party,docs,node_modules,languages
```

```bash
msgmerge --update languages/wp-whobird-fr_FR.po languages/wp-whobird.pot
```

Translate the new French entries (the new messages of slices 004 and 005, for
instance "You are not allowed to do this."), then:

```bash
wp i18n make-mo languages
wp i18n make-php languages
wp i18n make-json languages --no-purge
```

`make-json` produces the JavaScript translations from the `.po` references to
`blocks/wp-whobird/*.js` (the scripts call `wp_set_script_translations()`).
Then remove the old `languages/wp-whobird-fr_FR-4c85…json`.

## Known limits, open

- Activation recreates the mapping table and the cache, so re-activating the
  plugin loses a regenerated mapping; see
  [open question 1](../open-questions.md). Not changed here (behaviour decision).
- The activation hook of the settings class never fires; see
  [open question 2](../open-questions.md).
- Strings passed to `__()` as variables (messages returned by the mapping
  sources) cannot be extracted; that is the clean-up slice.

## Dependencies

Slices 001 (tests) and 005 (option names, `Config`).

## Verification status

PHPUnit with WordPress functions stubbed. Not tried: a real deletion from the
WordPress plugins screen (single site and network), the `.pot` / `.mo` /
JavaScript translations (not regenerated).
