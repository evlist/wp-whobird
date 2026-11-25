<!--
SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>

SPDX-License-Identifier: GPL-3.0-or-later
-->

# Slice 003: remove the build step

## Why

The build (`wp-scripts` and webpack) compiled about fifty lines of JSX and two
stylesheets, copied `render.php` and `block.json`, and copied FontAwesome out of
`node_modules`. Minification was disabled, so it brought no size gain. Its cost:
`build/` is ignored by git, so a checkout does not work until
`npm install && npm run build` has run, and every release needs that step.
Goal (Eric): make deploying new versions simpler. After this slice, the
repository content **is** the plugin.

## What

- `src/wp-whobird/` becomes `blocks/wp-whobird/`, loaded directly by WordPress:
  - `block.json` references script handles (registered in PHP, with their
    dependencies declared by hand) and plain CSS files;
  - `index.js` is plain JavaScript (no JSX, no import) using `wp.blocks`,
    `wp.element`, `wp.blockEditor`, `wp.components` and `wp.i18n`; it merges the
    former `index.js` and `edit.js`;
  - `style.scss` and `editor.scss` become `style.css` and `editor.css` (the
    SCSS features used were only `//` comments);
  - `view.js` and `admin-mapping.js` get `wp.i18n` instead of an `import`.
- `wp-whobird.php`: registers the script handles and the block with
  `register_block_type()` on `blocks/wp-whobird`; no more `build/` paths and no
  `blocks-manifest.php`. Script handle `wpwbd-wp-whobird-view-script` is kept
  (the inline `ajaxurl` code uses it). The admin script handle keeps its name.
- FontAwesome Free 6.7.2 is committed in `third-party/fontawesome/` (CSS, the
  two web fonts actually used, brands and solid, and its licence text) instead
  of being copied from `node_modules`.
- Removed: `webpack.config.js`, `package-lock.json`, the `@wordpress/scripts`
  and `copy-webpack-plugin` dependencies. `package.json` only keeps a `test`
  script.
- Releases: `bin/make-zip.sh` builds `wp-whobird.zip` from the git tree
  (`git archive`), with `.gitattributes` excluding development files.
- `readme.txt`: installation no longer asks for `npm`.
- Tests: `tests/js/block.test.js` (Node's built-in runner, no dependency) loads
  `index.js` against a fake `wp` global and checks the registration and the
  editor controls; `node --check` on every script.

## Constraints

- Same block name, attributes and front-end output as before.
- No new dependency, no network at build or install time.

## Dependencies

None on other slices. Open point: the JavaScript translations (`languages/*.json`)
were keyed by the old `build/…` paths and already out of date; they have to be
regenerated with WP-CLI (`wp i18n make-pot`, `make-json`) from the new paths.
The scripts call `wp_set_script_translations()` so they will be picked up then.

## Verification status

Node tests with a fake `wp` global, PHPUnit unchanged, PHP syntax checks. Not
tried in a real block editor, on a real front end, nor in the admin screen:
Eric has to try the block insertion, the period controls, the player, the
refresh of stale entries, the mapping generation and the icons (Wikipedia
link, editor crow).
