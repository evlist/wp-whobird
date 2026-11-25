<?php
// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace WPWhoBird;

/**
 * Test stand-in for thumbnail URL computation (shadows includes/ImageUtils.php).
 */
function getThumbnailUrl( $image, $size ) {
	return $image;
}
