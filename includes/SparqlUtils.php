<?php

// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
//
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * Utility functions for handling SPARQL queries within WPWhoBird.
 *
 * Includes sanitization to prevent SPARQL injection or query manipulation.
 *
 * @package   WPWhoBird
 * @author    Eric van der Vlist <vdv@dyomedea.com>
 * @copyright 2025 Eric van der Vlist
 * @license   GPL-3.0-or-later
 */
namespace WPWhoBird;

/**
 * Sanitize input for SPARQL queries to prevent injection or query manipulation.
 *
 * Escapes special characters (such as backslashes and quotes) and removes
 * any unwanted characters to help ensure that user input cannot alter or
 * break out of the intended SPARQL query structure.
 *
 * @param string $input The input string to sanitize.
 * @return string Sanitized input.
 */
function sanitizeForSparql($input)
{
    // Escape special characters for SPARQL (backslash and quote)
    $escaped = str_replace(
        ['\\', '"'],
        ['\\\\', '\\"'],  // Escape backslashes and quotes
        $input
    );

    // Optionally remove any unwanted characters, such as non-word, non-space, and non-hyphen
    return preg_replace('/[^\w\s\-]/u', '', $escaped);
}

