<!--
SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>

SPDX-License-Identifier: GPL-3.0-or-later
-->

# Slice 008: documentation

## Why

After slices 001 to 007 the repository had no description of how it works, no
instructions for the next session, an outdated `readme.txt` (paths, installation,
no changelog) and a `README.md` made only of a Codespaces badge. The
`shared-room` repository still said the plugin had no Claude Code work and
"to be completed".

## What

In this repository:

- `CLAUDE.md`: short instructions for Claude Code sessions (links to the
  `shared-room` conventions; what is specific here: slices, no build, tests,
  security rules, translations, releases).
- `docs/architecture.md`: how a page with the block works, the entry points and
  their tests, stored data, external services, file map.
- `README.md`: what the plugin is, install, develop, documentation, license.
- `readme.txt`: installation and FAQ say that paths are relative to the uploads
  directory and where the files go; a changelog entry "Unreleased" lists the
  changes of slices 001 to 007 that users can see. The version number is not
  changed (Eric's decision when releasing).

In `shared-room` (prepared in a local commit; to push only when Eric says so):

- `README.md`: the `wp-whobird` row says Claude Code was used and what was done.
- `wp-whobird/README.md`: companion directory (principles and links only, as the
  filing rule says), pointing to the files of this repository.
- `CLAUDE.md`: a "wp-whobird" entry under Projects (pointer, no copy).

## Constraints

Written in English. No copy of the design between the repositories: links only.
No change of code.

## Dependencies

Slices 001 to 007 (what is documented).

## Verification status

Documents only. The commands given (`composer install`, `phpunit`, `node --test`,
`bin/make-zip.sh`) were run in this session; the architecture description comes
from reading the code and was not checked on a running site.
