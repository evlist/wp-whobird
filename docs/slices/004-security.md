<!--
SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>

SPDX-License-Identifier: GPL-3.0-or-later
-->

# Slice 004: capability and nonce checks

## Why

An audit against the conventions of `shared-room` (capability and nonce checks
from the start; the server never trusts what the browser sends; every entry
point gets a test) found the following.

| # | Entry point | Problem |
| --- | --- | --- |
| 1 | AJAX `whobird_generate_mapping` (logged-in users) | nonce checked, **no capability check**: any logged-in user can run the mapping steps (SPARQL queries, writes and `DROP TABLE` on the mapping table) |
| 2 | Admin screen, mapping sources (`bird-mappings-ui.php`) | POST actions check `manage_options` but have **no nonce** (CSRF): update a source file, rebuild the mapping table |
| 3 | Admin screen, cache tool (`cache-tools.php`) | nonce checked, **no capability check** (the page itself needs `manage_options`, but the handler does not re-check): `TRUNCATE TABLE` |
| 4 | `admin-post.php?action=whobird_export_mapping_json` | **no capability check**: any logged-in user can download the mapping table and the exporting user's login |
| 5 | Public AJAX `update_bird_data` (`nopriv`) | takes a `locale` from the browser, although the cache is keyed by BirdNET id only (a visitor can poison the cached names with another language); it waits for the Wikidata throttle before looking at the cache, so a fresh entry still costs time and holds the lock |

## What

1. `whobird_generate_mapping`: after the nonce, require `manage_options`; a
   plain error answer (HTTP 403) otherwise.
2. Mapping sources: the form carries a nonce (`wp_nonce_field`); the POST is
   handled by a function (`whobird_process_mapping_sources_request()`, new file
   `includes/mapping-sources-actions.php`) that requires `manage_options` and a
   valid nonce, and does nothing otherwise (an error notice for a bad nonce).
3. Cache tool: the handler (`clear_cache_if_requested()`) requires
   `manage_options` and the nonce. The include-time call moves to
   `admin-tools.php` so the file can be tested.
4. Export: the `admin_post` handler becomes a named function that requires
   `manage_options` (`wp_die` 403 otherwise).
5. `update_bird_data`: the browser's `locale` is ignored (the site locale is
   used, as for the page); `birdnet_id` must be a positive integer; a **fresh**
   cache entry is answered from the cache with no throttle wait and no network
   request. No nonce on this endpoint on purpose: it serves anonymous visitors
   of pages that may be cached, where a nonce would expire; the bound on abuse
   is the cache freshness plus the existing throttle.

## Constraints

- No change of behaviour for users who are allowed to do the action.
- No new dependency.

## Dependencies

Slice 001 added the PHPUnit set-up used for the tests (WordPress functions are
stubbed in `tests/bootstrap.php`).

## Out of scope, found on the way (to decide separately)

- Settings are registered as `wpwhobird_*` but read as `wp-whobird_*`
  (`WhoBirdRenderer`): the threshold, the recordings path and the database path
  set on the settings page appear never to be read. Fixing it changes behaviour
  on existing sites (the defaults currently apply), so it needs a decision.
- The recordings and database paths are free text, joined to the uploads
  directory without checking for `..`.
- `$_POST['step']`-style input in the AJAX handler is checked against a list
  (fine); `__( $variable )` calls hide strings from the `.pot`.
- Debug `error_log` calls on every request.

## Verification status

PHPUnit with WordPress functions stubbed (the stubs decide the capability and
the nonce answers). Not tried on a real WordPress site: nonce lifetimes, real
roles (editor, subscriber) and the real `admin-post.php` / `admin-ajax.php`
flows have to be tried by hand.
