<?php

namespace WPWhoBird;

/**
 * Get the Wikimedia thumbnail URL for a given image.
 *
 * @param string $imageUrl The original Wikimedia image URL.
 * @param string $size The desired thumbnail size (e.g., "100px").
 * @return string The transformed thumbnail URL.
 */
function getThumbnailUrl(string $imageUrl, string $size): string
{
    // Check if the URL is a valid Wikimedia Commons URL
    if (strpos($imageUrl, 'upload.wikimedia.org') === false) {
        return $imageUrl; // Return the original URL if it's not from Wikimedia Commons
    }

    // Parse the Wikimedia Commons image URL
    $urlParts = parse_url($imageUrl);
    $pathParts = explode('/', $urlParts['path']);

    // Extract the directory and file name
    $fileName = array_pop($pathParts); // File name (e.g., "Common_Buzzard.jpg")
    $imageDir = array_pop($pathParts); // Directory (e.g., "e/ef")

    // Construct the thumbnail URL
    $thumbnailUrl = $urlParts['scheme'] . '://' . $urlParts['host'] . '/wikipedia/commons/thumb/'
        . $imageDir . '/' . $fileName . '/' . $size . '-' . $fileName;

    return $thumbnailUrl;
}
