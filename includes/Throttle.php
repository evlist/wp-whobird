<?php

// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
//
// SPDX-License-Identifier: GPL-3.0-or-later

namespace WPWhoBird;

/**
 * Abstract class defining the interface for throttling mechanisms.
 *
 * Provides base properties and constructor for different throttle implementations
 * (e.g., file-based, memory-based, etc.). Enforces a minimal delay between actions,
 * and requires subclasses to implement the waitUntilAllowed() method.
 *
 * @package   WPWhoBird
 * @author    Eric van der Vlist <vdv@dyomedea.com>
 * @copyright 2025 Eric van der Vlist
 * @license   GPL-3.0-or-later
 */
abstract class Throttle {
    /**
     * @var string Namespace to differentiate independent throttling instances.
     */
    protected string $namespace;

    /**
     * @var int Minimal delay between actions in milliseconds.
     */
    protected int $minimalDelayMs;

    /**
     * Constructor for the Throttle class.
     *
     * @param string $namespace      A unique namespace for this throttle instance.
     * @param int    $minimalDelayMs The minimal delay between actions in milliseconds.
     */
    public function __construct(string $namespace, int $minimalDelayMs) {
        $this->namespace = $namespace;
        $this->minimalDelayMs = $minimalDelayMs;
    }

    /**
     * Wait until the minimal delay has passed before proceeding.
     * Must be implemented by subclasses.
     *
     * @return void
     */
    abstract public function waitUntilAllowed(): void;
}

