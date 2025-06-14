<!--
SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>

SPDX-License-Identifier: GPL-3.0-or-later
-->

whobird_mapping.json is a consolidation of data from Wikidata and :
* https://github.com/woheller69/whoBIRD/blob/master/app/src/main/assets/taxo_code.txt
* https://github.com/woheller69/whoBIRD/blob/master/app/src/main/assets/labels_en.txt

These two files are borowed from the BirdNET Framework by [@kahst](https://github.com/kahst) where they are published under CC BY NC SA 4.0 license with permission to be used under GPL 3.0.

The versions from the whoBIRD project have been prefered to the "original" ones in order to be synchronized with whoBIRD if/when these files are updated.

The process to generate whobird_mapping.json is  not optimized and could be improved as it has been developed while trying to find a solution to map BirdNET and Wikidata ids.

The generation can be done using the WordPress tools/whoBIRD tools page with the following steps:

* Import taxo_code.txt in a SQL table
* Import label_en.txt in a SQL table
* Import Wikidata bird species in a SQL table
* Generate the mapping. This action does:
  1. Match birds based of latin names (using SQL tables)
  2. Match unmatched birds based on eBird ids (using SQL tables)
  3. Match unmatched bird on "truthy" scientific names (using Wikidata SPARQL endpoint and not limited to birds so that it works with insects, batracians, when they are supported)
  4. Match unmatched bird on all scientific names (using Wikidata SPARQL endpoint and not limited to birds so that it works with insects, batracians, when they are supported)
* Download the resulting file.


