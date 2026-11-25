<!--
SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>

SPDX-License-Identifier: GPL-3.0-or-later
-->

# Slices

One document per feature, written before the code.

| Slice | Title | Depends on |
| --- | --- | --- |
| [001](001-index-markup.md) | Index markup on bird entries | none |
| [002](002-index-markup-json.md) | Index mark as one JSON attribute | 001 |
| [003](003-no-build.md) | Remove the build step | none |
| [004](004-security.md) | Capability and nonce checks | 001 (test set-up) |
| [005](005-settings.md) | Read the registered settings, keep paths inside uploads | 004 |
| [006](006-lifecycle.md) | Uninstall and translations | 001, 005 |
| [007](007-cleanup.md) | Clean-up (debug code, assets, hooks, strings) | 003, 006 |
