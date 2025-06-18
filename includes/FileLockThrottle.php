<?php

// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
//
// SPDX-License-Identifier: GPL-3.0-or-later

namespace WPWhoBird;

require_once 'Throttle.php';

/**
 * FileLockThrottle
 *
 * A file-based implementation of the Throttle class. This class uses a lock file
 * in a configurable directory to enforce a minimum delay between actions, 
 * allowing safe rate-limiting across multiple PHP processes or requests.
 * Useful for limiting access to shared resources (like APIs) in environments where
 * inter-process communication is limited.
 *
 * @package   WPWhoBird
 * @author    Eric van der Vlist <vdv@dyomedea.com>
 * @copyright 2025 Eric van der Vlist
 * @license   GPL-3.0-or-later
 */
class FileLockThrottle extends Throttle {
    /**
     * @var string Directory to store the throttle files.
     */
    private string $throttleDirectory;

    /**
     * Constructor for the FileLockThrottle class.
     *
     * @param string $namespace        A unique namespace for this throttle instance.
     * @param int $minimalDelayMs      The minimal delay between actions in milliseconds.
     * @param string|null $throttleDirectory Directory to store throttle files (defaults to system temp directory).
     * @throws \RuntimeException If the throttle directory does not exist.
     */
    public function __construct(string $namespace, int $minimalDelayMs, ?string $throttleDirectory = null) {
        parent::__construct($namespace, $minimalDelayMs);
        $this->throttleDirectory = $throttleDirectory ?? sys_get_temp_dir();

        // Ensure the throttle directory exists
        if (!is_dir($this->throttleDirectory)) {
            throw new \RuntimeException("Throttle directory does not exist: {$this->throttleDirectory}");
        }
    }

    /**
     * Get the path to the throttle file for this namespace.
     *
     * @return string Throttle file path.
     */
    private function getThrottleFile(): string {
        return "{$this->throttleDirectory}/throttle_{$this->namespace}.lock";
    }

    /**
     * Calculate the time left before the next action can be performed.
     *
     * @return int Time left in microseconds (0 if no waiting is required).
     */
    private function getTimeLeft(): int {
        $throttleFile = $this->getThrottleFile();
        $currentTime = microtime(true) * 1000; // Current time in milliseconds

        if (file_exists($throttleFile)) {
            $lastRequestTime = filemtime($throttleFile) * 1000; // Last request time in milliseconds
            $timeElapsed = $currentTime - $lastRequestTime;

            if ($timeElapsed < $this->minimalDelayMs) {
                return ($this->minimalDelayMs - $timeElapsed) * 1000; // Return time left in microseconds
            }
        }

        return 0; // No waiting required
    }

    /**
     * Wait until the minimal delay has passed before proceeding.
     *
     * This method blocks until it is allowed to proceed, enforcing the throttle.
     * It dynamically recalculates the time left and updates the throttle file's timestamp
     * once the operation is allowed.
     *
     * @return void
     */
    public function waitUntilAllowed(): void {
        // Use a for loop to dynamically recalculate the time left
        for ($timeLeft = $this->getTimeLeft(); $timeLeft > 0; ) {
            usleep($timeLeft); // Sleep for the remaining time in microseconds
        }

        // Update the throttle file's modification time to the current time
        touch($this->getThrottleFile());
    }
}

