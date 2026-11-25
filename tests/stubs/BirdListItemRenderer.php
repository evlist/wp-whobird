<?php
// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
// SPDX-License-Identifier: GPL-3.0-or-later

// AjaxBirdListItemRefresher.php requires this file through plugin_dir_path(), which the
// tests point to tests/stubs: forward to the real renderer.
require_once dirname( __DIR__, 2 ) . '/includes/BirdListItemRenderer.php';
