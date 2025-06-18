<?php

// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
//
// SPDX-License-Identifier: GPL-3.0-or-later

namespace WPWhoBird;

/**
 * Get the Wikimedia thumbnail URL for a given image.
 *
 * Transforms a Wikimedia Commons image URL into its corresponding thumbnail URL
 * of the specified size. Handles Special:FilePath URLs and computes the correct
 * hash-based path used by Wikimedia for thumbnail storage. If the provided URL
 * is not from Wikimedia Commons, the function returns the original URL.
 *
 * @param string $imageUrl The original Wikimedia image URL.
 * @param string $size The desired thumbnail size (e.g., "100px").
 * @return string The transformed thumbnail URL, or the original URL if resolution fails.
 */
function getThumbnailUrl(string $imageUrl, string $size): string
{
    // Handle Special:FilePath URLs by resolving to the underlying upload URL
    if (strpos($imageUrl, 'commons.wikimedia.org/wiki/Special:FilePath') !== false) {
        $resolvedUrl = resolveSpecialFilePathUrl($imageUrl);
    }

    // Check if the URL is a valid Wikimedia Commons upload URL
    if (strpos($imageUrl, 'upload.wikimedia.org') === false) {
        return $imageUrl; // Return the original URL if it's not from Wikimedia Commons
    }

    // Extract the image file name from the URL
    $fileName = urldecode(basename($imageUrl));

    // Compute the hash-based directory structure as used by Wikimedia
    $hash = md5($fileName);
    $hash1 = $hash[0];
    $hash2 = $hash[0] . $hash[1];

    // Construct the thumbnail URL
    $thumbnailUrl = "https://upload.wikimedia.org/wikipedia/commons/thumb/"
        . $hash1 . "/" . $hash2 . "/" . urlencode($fileName) . "/" . $size . "-" . urlencode($fileName);

    return $thumbnailUrl;
}

/**
 * Resolve the actual file URL from a Special:FilePath URL.
 *
 * Uses a HEAD request to follow redirects and determine the true underlying file URL
 * for a Wikimedia Commons Special:FilePath endpoint. If resolution fails, the original
 * URL is returned.
 *
 * @param string $filePathUrl The Special:FilePath URL.
 * @return string|null The resolved URL, or null if resolution fails.
 */
function resolveSpecialFilePathUrl(string $filePathUrl): ?string
{
    if (strpos($filePathUrl, 'commons.wikimedia.org/wiki/Special:FilePath') === false) {
        return $filePathUrl;
    }

    // Initialize a cURL session
    $ch = curl_init();

    // Configure cURL options for a HEAD request and to follow redirects
    curl_setopt($ch, CURLOPT_URL, $filePathUrl);
    curl_setopt($ch, CURLOPT_NOBODY, true); // Use HEAD request
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute the request
    curl_exec($ch);

    // Get the final URL after all redirects
    $resolvedUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

    // Check for errors and clean up
    if (curl_errno($ch)) {
        curl_close($ch);
        return $filePathUrl; // Return the original URL if the request fails
    }

    // Close the cURL session
    curl_close($ch);

    return $resolvedUrl;
}

