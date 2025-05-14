<?php

namespace WPWhoBird;

/**
 * Abstract class defining the interface for throttling mechanisms.
 */
abstract class Throttle {
    protected string $namespace; // Namespace to differentiate independent throttling instances
    protected int $minimalDelayMs; // Minimal delay between actions in milliseconds

    /**
     * Constructor for the Throttle class.
     *
     * @param string $namespace A unique namespace for this throttle instance.
     * @param int $minimalDelayMs The minimal delay between actions in milliseconds.
     */
    public function __construct(string $namespace, int $minimalDelayMs) {
        $this->namespace = $namespace;
        $this->minimalDelayMs = $minimalDelayMs;
    }

    /**
     * Wait until the minimal delay has passed before proceeding.
     * Must be implemented by subclasses.
     */
    abstract public function waitUntilAllowed(): void;
}
