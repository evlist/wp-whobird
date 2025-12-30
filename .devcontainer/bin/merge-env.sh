#!/usr/bin/env bash

# SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
#
# SPDX-License-Identifier: GPL-3.0-or-later OR MIT

set -euo pipefail

log() { printf "[merge-env] %s\n" "$*"; }
die() { printf "[merge-env:ERROR] %s\n" "$*" >&2; exit 1; }

LOCALENV=${PWD}/.env
DEVCONTAINER=${PWD}/.devcontainer
DEFAULTENV=${DEVCONTAINER}/.env
TMP=${DEVCONTAINER}/tmp
MERGEDENV=${TMP}/.env.merged

mkdir -p $TMP
rm -f $MERGEDENV

for FILE in $DEFAULTENV $LOCALENV
do
    log "Copying $FILE (if exists)"
    echo "# Copied from $FILE:" >> $MERGEDENV
    cat $FILE >> $MERGEDENV 2>/dev/null || true
    echo >> $MERGEDENV
done
