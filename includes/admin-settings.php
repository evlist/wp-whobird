<?php
// vi: set ft=php ts=4 sw=4 expandtab:
namespace WPWhoBird;

class WhoBirdAdminSettings
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'addSettingsPage']);
        add_action('admin_init', [$this, 'registerSettings']);
        register_activation_hook(__FILE__, [$this, 'setDefaultFallback']); // Hook for default value
    }

    public function addSettingsPage()
    {
        add_options_page(
            __('WhoBird Settings', 'wpwhobird'),
            __('WhoBird Settings', 'wpwhobird'),
            'manage_options',
            'wpwhobird-settings',
            [$this, 'renderSettingsPage']
        );
    }

    public function renderSettingsPage()
    {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('WhoBird Settings', 'wpwhobird'); ?></h1>
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

        // Register new setting for fallback languages without enforcing "en" by default
        register_setting('wpwhobird_settings', 'wpwhobird_fallback_languages', [
            'sanitize_callback' => function ($value) {
                // Ensure the value is a valid comma-separated list of two-letter language codes
                return implode(',', array_filter(array_map('trim', explode(',', $value)), function ($lang) {
                    return preg_match('/^[a-z]{2}$/', $lang); // Validate two-letter language codes
                }));
            },
        ]);

        // Add settings section
        add_settings_section(
            'wpwhobird_main_settings',
            __('Main Settings', 'wpwhobird'),
            null,
            'wpwhobird-settings'
        );

        // Add settings fields
        $this->addTextField('wpwhobird_recordings_path', __('Recordings Path', 'wpwhobird'));
        $this->addTextField('wpwhobird_database_path', __('Database Path', 'wpwhobird'));
        $this->addNumberField('wpwhobird_threshold', __('Threshold', 'wpwhobird'), 0, 1, 0.01);
        $this->addTextField(
            'wpwhobird_fallback_languages',
            __('Fallback Languages', 'wpwhobird'),
            __('Enter fallback languages as a comma-separated list (e.g., "en,fr,de"). Leave empty to disable fallback.', 'wpwhobird')
        );
    }

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
                    <p class="description"><?php echo esc_html($description); ?></p>
                <?php endif; ?>
                <?php
            },
            'wpwhobird-settings',
            'wpwhobird_main_settings'
        );
    }

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

