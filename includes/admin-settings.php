<?php
// SPDX-FileCopyrightText: 2025 Eric van der Vlist <vdv@dyomedea.com>
// SPDX-License-Identifier: GPL-3.0-or-later

/**
 * WhoBird Admin Settings
 *
 * Defines the WordPress admin settings page and option registration for the whoBIRD plugin.
 * Provides UI and logic for updating plugin settings such as paths, thresholds, and feature toggles.
 *
 * @package   WPWhoBird
 * @author    Eric van der Vlist <vdv@dyomedea.com>
 * @copyright 2025 Eric van der Vlist
 * @license   GPL-3.0-or-later
 */

namespace WPWhoBird;

class WhoBirdAdminSettings
{
    /**
     * Register admin menu, settings, and set default fallback on activation.
     */
    public function __construct()
    {
        add_action('admin_menu', [$this, 'addSettingsPage']);
        add_action('admin_init', [$this, 'registerSettings']);
        register_activation_hook(__FILE__, [$this, 'setDefaultFallback']); // Hook for default value
    }

    /**
     * Register the WhoBird settings page with WordPress.
     */
    public function addSettingsPage()
    {
        add_options_page(
            __('WhoBird Settings', 'wp-whobird'),
            __('WhoBird Settings', 'wp-whobird'),
            'manage_options',
            'wpwhobird-settings',
            [$this, 'renderSettingsPage']
        );
    }

    /**
     * Render the settings page HTML.
     */
    public function renderSettingsPage()
    {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('WhoBird Settings', 'wp-whobird'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('wpwhobird_settings');
                do_settings_sections('wpwhobird-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register plugin settings, fields, and sections.
     */
    public function registerSettings()
    {
        // Register existing settings
        register_setting('wpwhobird_settings', 'wpwhobird_recordings_path', [
            'default' => 'WhoBird/recordings',
        ]);
        register_setting('wpwhobird_settings', 'wpwhobird_database_path', [
            'default' => 'WhoBird/databases/BirdDatabase.db',
        ]);
        register_setting('wpwhobird_settings', 'wpwhobird_threshold', [
            'default' => 0.4,
            'sanitize_callback' => function ($value) {
                $value = floatval($value);
                return ($value >= 0 && $value <= 1) ? $value : 0.7; // Ensure it's between 0 and 1
            },
        ]);

        // Register new setting for generating a message when there are no observations for the selected period
        register_setting('wpwhobird_settings', 'wpwhobird_should_generate_text_when_no_observations', [
            'type' => 'boolean',
            'default' => false,
        ]);

        // Add settings section
        add_settings_section(
            'wpwhobird_main_settings',
            __('Main Settings', 'wp-whobird'),
            null,
            'wpwhobird-settings'
        );

        // Add settings fields
        $this->addTextField('wpwhobird_recordings_path', __('Recordings Path', 'wp-whobird'));
        $this->addTextField('wpwhobird_database_path', __('Database Path', 'wp-whobird'));
        $this->addNumberField('wpwhobird_threshold', __('Threshold', 'wp-whobird'), 0, 1, 0.01);
        $this->addCheckboxField(
            'wpwhobird_should_generate_text_when_no_observations',
            __('Generate text if there are no observations for the selected period', 'wp-whobird'),
            __('If checked, a message will be generated when there are no observations for the selected period.', 'wp-whobird'),
            true
        );
    }

    /**
     * Add a text input field to the settings page.
     *
     * @param string $optionName
     * @param string $label
     * @param string $description
     */
    private function addTextField($optionName, $label, $description = '')
    {
        add_settings_field(
            $optionName,
            $label,
            function () use ($optionName, $description) {
                $value = get_option($optionName, '');
                ?>
                <input type="text" id="<?php echo esc_attr($optionName); ?>"
                       name="<?php echo esc_attr($optionName); ?>"
                       value="<?php echo esc_attr($value); ?>" class="regular-text">
                <?php if ($description): ?>
                    <p class="description"><?php echo esc_html__($description, 'wp-whobird'); ?></p>
                <?php endif; ?>
                <?php
            },
            'wpwhobird-settings',
            'wpwhobird_main_settings'
        );
    }

    /**
     * Add a number input field to the settings page.
     *
     * @param string $optionName
     * @param string $label
     * @param float $min
     * @param float $max
     * @param float $step
     */
    private function addNumberField($optionName, $label, $min, $max, $step)
    {
        add_settings_field(
            $optionName,
            $label,
            function () use ($optionName, $min, $max, $step) {
                $value = get_option($optionName, '0.7');
                ?>
                <input type="number" id="<?php echo esc_attr($optionName); ?>"
                       name="<?php echo esc_attr($optionName); ?>"
                       value="<?php echo esc_attr($value); ?>"
                       min="<?php echo esc_attr($min); ?>"
                       max="<?php echo esc_attr($max); ?>"
                       step="<?php echo esc_attr($step); ?>" class="small-text">
                <?php
            },
            'wpwhobird-settings',
            'wpwhobird_main_settings'
        );
    }

    /**
     * Add a checkbox option to the settings page.
     *
     * @param string $optionName
     * @param string $label
     * @param string $description
     * @param bool $default
     */
    private function addCheckboxField($optionName, $label, $description = '', $default=true)
    {
        add_settings_field(
            $optionName,
            $label,
            function () use ($optionName, $description, $default) {
                $value = get_option($optionName, $default);
                ?>
                <input type="checkbox" id="<?php echo esc_attr($optionName); ?>"
                       name="<?php echo esc_attr($optionName); ?>"
                       value="1" <?php checked($value); ?>>
                <?php if ($description): ?>
                    <p class="description"><?php echo esc_html__($description, 'wp-whobird'); ?></p>
                <?php endif; ?>
                <?php
            },
            'wpwhobird-settings',
            'wpwhobird_main_settings'
        );
    }

    /**
     * Set default fallback language to "en" if not already set.
     * Called on plugin activation.
     */
    public function setDefaultFallback()
    {
        // Set default fallback language to "en" only if the option is not already set
        if (get_option('wpwhobird_fallback_languages') === false) {
            update_option('wpwhobird_fallback_languages', 'en');
        }
    }
}

// Initialize the settings page
new WhoBirdAdminSettings();
