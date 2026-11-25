<!--
SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>

SPDX-License-Identifier: GPL-3.0-or-later
-->

# Architecture

A map of the code, as of slice 008. It describes what is there, not what is wanted: wishes and undecided points are in
[`open-questions.md`](open-questions.md), the history of the changes in [`slices/`](slices/README.md).

## What happens when a page shows the block

1. WordPress renders `blocks/wp-whobird/render.php`, which calls `WhoBirdRenderer::displayObservations()`.
2. The renderer computes the period from the post's date and the block attributes (`periodNumber`, `periodUnit`: day, week or
   month), reads the whoBIRD SQLite file **read-only** (path from the settings, inside the uploads directory), keeps the
   observations whose probability reaches the threshold setting, and groups them by bird.
3. For each bird, `BirdListItemRenderer::render()` outputs an `<li class="wpwbd-bird-entry">` from the Wikidata data **already in
   the cache table** (name, Latin name, image, Wikipedia link) or, if there is none, the name given by the app. When the cache
   is missing or stale the entry carries `data-birdnet-id`; when the mapping table has a Wikidata id it carries a
   `data-index` JSON mark read by the `wp-otherguise` Index module (slices 001, 002).
4. The front-end script (`view.js`) asks `update_bird_data` (AJAX) to refresh the entries marked `data-birdnet-id`, one at a
   time; the server answers from a fresh cache, or fetches Wikidata (throttled) and caches the result.
5. `view.js` also drives the audio player over the recordings found for the observations.

## Entry points

| Entry point | Where | Who may call it | Tests |
| --- | --- | --- | --- |
| Block `wpwbd/wp-whobird` (render, editor) | `blocks/wp-whobird/`, `includes/plugin-setup.php` | everybody (render), editors | `SetupTest`, `BirdListItemRendererTest`, `SettingsTest`, `tests/js/block.test.js` |
| AJAX `update_bird_data` | `includes/AjaxBirdListItemRefresher.php` | everybody, no nonce (slice 004) | `SecurityTest` |
| AJAX `whobird_generate_mapping` | `includes/ajax-generate-mapping.php` | `manage_options` + nonce | `SecurityTest` |
| `admin-post.php?action=whobird_export_mapping_json` | `includes/bird-mappings.php` | `manage_options` | `SecurityTest` |
| Admin page Tools > whoBIRD tools (cache tool, mapping sources) | `includes/admin-tools.php`, `cache-tools.php`, `bird-mappings-ui.php`, `mapping-sources-actions.php` | `manage_options` + nonce | `SecurityTest` |
| Admin page Settings > WhoBird Settings | `includes/admin-settings.php` | `manage_options` | `SettingsTest` |
| Activation | `WhoBirdActivator::activate()`, `whobirdMappingTableInit()` | WordPress | none yet (open question 1) |
| Uninstall | `uninstall.php`, `includes/Uninstaller.php` | WordPress | `LifecycleTest` |

There is no REST route, no WP-CLI command, no cron job and no shortcode.

## Stored data

- Tables (prefix `wp_`): `whobird_sparql_cache` (Wikidata results by BirdNET id, with expiration), `whobird_mapping`
  (`birdnet_id`, `scientific_name`, `wikidata_qid`), `whobird_remote_files` (downloaded mapping source files).
- Options: `wpwhobird_recordings_path`, `wpwhobird_database_path`, `wpwhobird_threshold`,
  `wpwhobird_should_generate_text_when_no_observations` (names, defaults and getters in `Config`),
  `wpwhobird_fallback_languages`, `whobird_fetched_wikidata_qids`.
- Files: the whoBIRD database and the recordings, in the uploads directory, put there by hand (never written or deleted by the
  plugin); a lock file `throttle_wikidata.lock` in the system temporary directory.

## External services

- Wikidata SPARQL endpoint (`query.wikidata.org`): names, descriptions, images, Wikipedia links, through
  `WikidataQuery`, throttled by `FileLockThrottle` (one request at a time, with a minimal delay).
- Wikimedia (`upload.wikimedia.org`): thumbnails, computed from the Commons image URL (`ImageUtils`).
- GitHub (`github.com/woheller69/whoBIRD`, `api.github.com`): the whoBIRD files that feed the mapping, downloaded from the
  admin mapping tool (`WhoBirdSources`).

No automated test calls these services.

## Files

| Path | Role |
| --- | --- |
| `wp-whobird.php` | plugin header, loads the parts, activation hooks |
| `includes/plugin-setup.php` | block, assets, admin script and text domain hooks |
| `includes/config.php` | table names, option names, defaults, getters, path filter |
| `includes/WhoBirdRenderer.php`, `BirdListItemRenderer.php` | the observation list |
| `includes/WikidataQuery.php`, `ImageUtils.php`, `SparqlUtils.php`, `Throttle.php`, `FileLockThrottle.php` | Wikidata access, cache, images, throttling |
| `includes/bird-mappings*.php`, `WhoBirdSources.php`, `WhobirdMappingTableManager.php`, `WhoBirdActivator.php` | mapping table: generation, sources, creation |
| `includes/admin-*.php`, `cache-tools.php`, `mapping-sources-actions.php` | admin screens |
| `blocks/wp-whobird/` | block: `block.json`, `index.js` (editor), `view.js`, `render.php`, CSS |
| `third-party/fontawesome/` | FontAwesome Free 6.7.2 (icons CC BY 4.0, fonts OFL 1.1, code MIT) |
| `resources/` | default mapping JSON and images |
| `tests/`, `bin/make-zip.sh` | tests and release script |
