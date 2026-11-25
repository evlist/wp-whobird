<?php
// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * Runs when the plugin is deleted from the WordPress admin (not when it is deactivated).
 *
 * @package WPWhoBird
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/Uninstaller.php';

\WPWhoBird\Uninstaller::run();
