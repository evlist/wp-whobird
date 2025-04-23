<?php

/**
 * Sanitize input for SPARQL queries to prevent injection or query manipulation.
 *
 * @param string $input The input string to sanitize.
 * @return string Sanitized input.
 */
function sanitizeForSparql($input)
{
    // Escape special characters for SPARQL
    $escaped = str_replace(
        ['\\', '"'],
        ['\\\\', '\\"'],  // Escape backslashes and quotes
        $input
    );

    // (Optional) Remove any unwanted characters, such as SPARQL keywords
    return preg_replace('/[^\w\s\-]/u', '', $escaped);
}
