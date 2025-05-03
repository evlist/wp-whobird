<?php

namespace WPWhoBird;

/**
 * Get the Wikimedia thumbnail URL for a given image.
 *
 * @param string $imageUrl The original Wikimedia image URL.
 * @param string $size The desired thumbnail size (e.g., "100px").
 * @return string The transformed thumbnail URL, or the original URL if resolution fails.
 */
function getThumbnailUrl(string $imageUrl, string $size): string
{
    // Handle Special:FilePath URLs
    if (strpos($imageUrl, 'commons.wikimedia.org/wiki/Special:FilePath') !== false) {
        $resolvedUrl = resolveSpecialFilePathUrl($imageUrl);
    }

    // Check if the URL is a valid Wikimedia Commons URL
    if (strpos($imageUrl, 'upload.wikimedia.org') === false) {
        return $imageUrl; // Return the original URL if it's not from Wikimedia Commons
    }

    // Extract the file name
    $fileName = urldecode(basename($imageUrl));

    // Compute the hash-based directory structure
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

    // Configure cURL options
    curl_setopt($ch, CURLOPT_URL, $filePathUrl);
    curl_setopt($ch, CURLOPT_NOBODY, true); // Use HEAD request
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute the request
    curl_exec($ch);

    // Get the final URL after all redirects
    $resolvedUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

    // Check for errors
    if (curl_errno($ch)) {
        curl_close($ch);
        return $filePathUrl; // Return the original URL if the request fails
    }

    // Close the cURL session
    curl_close($ch);

    return $resolvedUrl;
}
