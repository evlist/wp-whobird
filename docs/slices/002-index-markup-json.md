<!--
SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>

SPDX-License-Identifier: GPL-3.0-or-later
-->

# Slice 002: index mark as one JSON attribute

## Why

Slice 001 wrote three attributes (`data-index`, `data-index-kind`,
`data-index-label`). The `wp-otherguise` Index module now reads one JSON
object in `data-index`, with a `source` field naming who wrote the mark. This
slice replaces the three attributes by that single attribute. The reader accepts
both forms, so slice 001 stayed valid during the transition.

## What

On each `<li class="wpwbd-bird-entry">`, when `wikidata_qid` is well-formed
(`Q` + digits), and nothing otherwise:

```
data-index="{&quot;topic&quot;:&quot;wikidata:Q25422&quot;,&quot;kind&quot;:&quot;species&quot;,&quot;label&quot;:&quot;Pinson des arbres&quot;,&quot;source&quot;:&quot;whobird&quot;}"
```

Built with `esc_attr( wp_json_encode( $mark, JSON_UNESCAPED_UNICODE ) )`.

| Key | Value |
| --- | --- |
| `topic` | `wikidata:Q…` |
| `kind` | `species` |
| `label` | common name from Wikidata, only when the name comes from Wikidata (Wikidata capitalisation); key absent otherwise |
| `source` | literal `whobird` |

`data-index-kind` and `data-index-label` are no longer written.

## Constraints

Same as slice 001: no extra network request, output unchanged without a usable
QID, independent of the block settings, AJAX refresh does not touch the `<li>`
attributes. Non-ASCII characters stay readable (no `\uXXXX`).

## Dependencies

Slice 001 (it replaces its attribute format). Reader side:
`wp-otherguise`, `Harvest/PageReader::read_marks()`.

## Verification status

Automated unit tests only (WordPress functions stubbed). Not tried on a real
WordPress site, nor with the real mapping table.
