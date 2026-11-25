<!--
SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>

SPDX-License-Identifier: GPL-3.0-or-later
-->

# Slice 005: read the registered settings, keep paths inside uploads

## Why

Found while auditing (slice 004): the settings page registers its options as
`wpwhobird_*`, but `WhoBirdRenderer` read `wp-whobird_*`. The threshold, the
recordings path and the database path set on the settings page were therefore
never used; the hard-coded defaults applied. The defaults were also
inconsistent (threshold 0.4 registered, 0.7 read, shown and used as the
sanitising fallback; "no observations" text off when registered, on when read).

The two paths are free text joined to the uploads directory, so a value such as
`../../..` could point anywhere on the server.

## What

- One source of truth, in `Config`: option names, defaults and getters
  (`getRecordingsPath()`, `getDatabasePath()`, `getThreshold()`).
  `WhoBirdRenderer` and the settings page use them. The old `wp-whobird_*`
  names are never read (they could not have been saved from the UI).
- Defaults, aligned on what sites effectively do today: recordings
  `WhoBird/recordings`, database `WhoBird/databases/BirdDatabase.db`,
  threshold `0.7`, text when there are no observations: on. The registered
  defaults now use the same constants (threshold 0.4 and text off disappear).
- Paths are relative to the uploads directory (which is inside `wp-content`
  on a standard install). `Config::sanitizeRelativePath()` rejects: empty
  values, absolute paths (`/…`, `C:…`), URL schemes (`phar://…`), `..`
  segments, control characters; it normalises backslashes, `.` segments and
  repeated slashes.
- On save, an invalid path is refused with an error message and the previous
  value is kept (`add_settings_error`).
- On use (so also for values stored by other means), an invalid path falls back
  to the default, and the resolved file must be inside the uploads directory
  after resolving symbolic links (`realpath`): a recording or the database that
  resolves outside is ignored (recording) or refused (database).
- Applies to both paths (recordings and database), through the same helper.

## Constraints

- No change for a site that never saved the settings (defaults are what it
  effectively used).
- A site that did save values on the settings page will, from now on, see them
  take effect (threshold, paths, text option).
- If the uploads directory is configured outside `wp-content`, the limit is the
  uploads directory.

## Dependencies

Slice 004 (security audit that found it). Test set-up from slice 001.

## Verification status

PHPUnit with a temporary uploads directory and a real SQLite file (the
renderer queries it for real); WordPress functions stubbed. Not tried on a real
WordPress site, in the settings screen, nor with a real whoBIRD export.
