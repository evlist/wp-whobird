<!--
SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>

SPDX-License-Identifier: GPL-3.0-or-later
-->

# Open questions

Points found while working on the slices that need a decision. Nothing here is
settled: no code depends on an assumed answer. When a point is decided, move it
to the slice that implements it and delete it from this list.

## 1. Activation recreates the mapping table and the cache (found in slice 006)

**Status: open, behaviour not changed.**

### What happens today

Two activation hooks are registered, in this order (`wp-whobird.php`):

1. `WhoBirdActivator::activate()` (`includes/WhoBirdActivator.php`):
   - drops and recreates the SPARQL cache table;
   - creates the remote files table with `dbDelta()` (existing rows are kept);
   - **drops** and recreates the mapping table (`birdnet_id INT UNSIGNED`,
     `scientific_name VARCHAR(255)`, `wikidata_qid VARCHAR(32)`), then fills it
     from `resources/data/whobird_mapping.json`.
2. `whobirdMappingTableInit()` (`includes/WhobirdMappingTableManager.php`), which
   runs right after: if the JSON is readable and valid, **drops** the mapping
   table again and recreates it with another schema (`birdnet_id INT`,
   `scientific_name VARCHAR(128)`, `wikidata_qid VARCHAR(128)`), then fills it
   from the same JSON.

A third schema is created by the "Generate/Update mapping table" admin action
(`whobird_get_mapping_steps()` in `includes/bird-mappings.php`: `INT(10)
UNSIGNED`, `VARCHAR(128)`, `VARCHAR(64)`).

### Consequences

- **Deactivating then re-activating the plugin discards the mapping table**,
  including a mapping regenerated from Wikidata with the admin action, and
  replaces it with the one shipped in `resources/data/`. The cached Wikidata
  data is lost too (it is rebuilt on demand, so this one is only slower).
- The mapping table ends up with the schema of the second hook, which is
  neither the first one's nor the one used by the admin action.
- WordPress runs activation hooks only on activation, **not when a new version
  is installed over the old one**. Deploying a new zip does not touch the tables
  unless the plugin is deactivated and activated again; there is also no schema
  version stored anywhere, so a future schema change has no migration path.
- The mapping JSON is therefore loaded (and the table written) twice at each
  activation.

### Questions to decide

1. Should re-activation keep an existing mapping table (create it only when it
   is missing, fill it from the JSON only then)? Keeping it means a regenerated
   mapping survives; the price is that the shipped JSON no longer refreshes the
   table on re-activation (the admin action would be the way to refresh).
2. Which schema is the right one (column types and lengths), once there is only
   one creation code path?
3. Keep only one of the two activation hooks (and which)?
4. Store a schema version in an option and migrate on `plugins_loaded` /
   `admin_init`, so that deploying a new version does not depend on activation?
   (The option would have to be removed by `uninstall.php`.)

### What would change (once decided)

A slice of its own: one activation path, `dbDelta()` for every table, tests with
`$wpdb` stubbed (and a try on a real site: activate, regenerate the mapping,
deactivate, re-activate, check the table).

## 2. Default activation hook of the settings class never fires (found in slice 006)

`WhoBirdAdminSettings` calls `register_activation_hook( __FILE__, … )` from
`includes/admin-settings.php`. WordPress ties that hook to the **main plugin
file**; with another file the hook is never run, so `setDefaultFallback()`
(which sets `wpwhobird_fallback_languages` to `en`) never runs. The option is
not read anywhere else in the plugin today.

Question: is `wpwhobird_fallback_languages` still wanted? If yes, the hook has to
be registered from `wp-whobird.php`; if not, remove the method and the option
(and its line in `uninstall.php`).

## 3. Coding standard: reformat the existing code? (found in slice 007)

**Status: open, nothing reformatted.**

`composer lint` (phpcs, WordPress standard) now runs. On the existing code it
reports about 4,800 errors and 370 warnings, of which about 4,200 can be fixed
automatically (`phpcbf`). Most are indentation: the code is indented with spaces,
while WordPress and `.editorconfig` say tabs. The rest: Yoda conditions, missing
or misformatted doc comments, escaping of output (some of these are real
security-relevant findings, to be looked at one by one).

Questions: reformat everything in one commit (a large diff that hides `git blame`;
`.git-blame-ignore-revs` can hide it), or only touch the files edited from now on,
or drop the WordPress indentation rules and keep the spaces? The escaping and
nonce warnings should be reviewed whichever is chosen.
