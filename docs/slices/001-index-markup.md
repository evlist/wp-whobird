<!--
SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>

SPDX-License-Identifier: GPL-3.0-or-later
-->

# Slice 001: index markup on bird entries

## Why

The `wp-otherguise` plugin (Index module) builds a topic index from the pages
as WordPress renders them. Its page reader already trusts explicit
`data-index`, `data-index-kind` and `data-index-label` marks. The whoBIRD block
does not emit them, so birds are invisible to the index even though this
plugin knows the Wikidata identifier of each species (mapping table
`birdnet_id` / `scientific_name` / `wikidata_qid`).

## What

`BirdListItemRenderer::render()` adds attributes to each
`<li class="wpwbd-bird-entry">`:

| Attribute | Value | Present when |
| --- | --- | --- |
| `data-index` | `wikidata:Q…` (from the mapping table) | the mapping has a well-formed QID (`Q` + digits) |
| `data-index-kind` | `species` | same as `data-index` |
| `data-index-label` | common name from Wikidata (`commonName`) | `data-index` is present **and** the name comes from cached Wikidata data |

Decision (Eric): the label is emitted only when the name comes from Wikidata,
so it carries Wikidata's capitalisation ("Pinson des arbres"), never the
lowercase fallback name from the whoBIRD application.

## Constraints

- All values go through `esc_attr`.
- No extra network request: only the local mapping table (one SQL query per
  bird) and the Wikidata data already cached are used.
- Output is identical to the previous output when there is no usable QID.
- Independent of the block settings (day, week, month): all go through
  `render()`.
- The AJAX refresh (`view.js`) replaces the `innerHTML` of the `<li>`, so the
  attributes on the `<li>` itself are unaffected.

## Dependencies

None. Adds the test tooling (PHPUnit, `phpcs`) the repository did not have yet.

## Verification status

Automated unit tests only (WordPress functions stubbed). Not tried on a real
WordPress site, nor with the real mapping table.
