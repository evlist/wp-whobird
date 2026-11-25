<!--
SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>

SPDX-License-Identifier: GPL-3.0-or-later
-->

# CLAUDE.md

Context for Claude Code sessions on `wp-whobird`. The conventions shared by all of Eric's projects are in
[`evlist/shared-room`](https://github.com/evlist/shared-room) (`CLAUDE.md`): read them first. This file only
adds what is specific to this plugin, and stays short; details are in `docs/`.

## Rules that matter most (from shared-room)

- Talk to Eric in **French**; write everything stored in the repository (code comments, docs, commit messages) in **English**.
- Ask before modifying anything until Eric says to go ahead; no pull requests, and no push to other branches than the one agreed, without being asked.
- Never ask for, store or commit tokens or passwords.
- Say what only ran under automated tests and what was never tried on a real WordPress site or against a real external service.

## What this plugin is

A WordPress plugin with one block, `wpwbd/wp-whobird`, that lists the birds identified by the whoBIRD app for the period
around a post's date. Data: a whoBIRD SQLite export and recordings in the uploads directory, a BirdNET id to Wikidata
mapping table, names, images and Wikipedia links fetched from Wikidata. The map of the code is in
[`docs/architecture.md`](docs/architecture.md).

## Working here

- **Slices**: one document per feature in `docs/slices/` (index in its `README.md`), written before the code. Points that need
  a decision go in [`docs/open-questions.md`](docs/open-questions.md); nothing in the code may depend on an assumed answer.
- **No build step**: the files in `blocks/wp-whobird/` are loaded as they are (plain JavaScript, plain CSS, no JSX, no
  `npm install`). FontAwesome is committed in `third-party/`. Do not reintroduce a bundler without a slice.
- **Tests**: `composer install`, then `vendor/bin/phpunit` (PHP; WordPress functions are stubbed in `tests/bootstrap.php`,
  collaborators in `tests/stubs/`) and `node --test tests/js/block.test.js` (editor script, fake `wp` global).
  Every entry point (block, AJAX route, `admin-post` action, admin screen, uninstall) has tests; add one with any new entry point.
  Check that a new test fails without the change it covers.
- **Style**: `composer lint` (phpcs, WordPress standard). The existing code does not follow it yet (see open question 3):
  do not reformat files you do not otherwise change.
- **Security**: every handler checks the capability (`manage_options`) and a nonce, except the public AJAX refresh
  `update_bird_data`, which has none on purpose (see slice 004). Paths from settings go through `Config::sanitizeRelativePath()`
  and stay inside the uploads directory. Settings names, defaults and getters are in `Config`.
- **Hooks and handles** are set up in `includes/plugin-setup.php`, with explicit priority and prefixed handles.
- **Translations**: text domain `wp-whobird`, never translate a variable. The `.pot`, `.po`, `.mo`, `.l10n.php` and JavaScript JSON files
  are generated with WP-CLI (commands in slice 006); regenerate them whenever a string changes.
- **Releases**: `bin/make-zip.sh` builds `wp-whobird.zip` from git (what `.gitattributes` marks `export-ignore` is left out).
  Plugins are tagged `rollout-N` for the blog's rollouts (see shared-room).
- Files carry SPDX headers (GPL-3.0-or-later); JSON and binary files use a `.license` file next to them.
