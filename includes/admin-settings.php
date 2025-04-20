<?php
namespace WPWhoBird;

class WhoBirdAdminSettings
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'addSettingsPage']);
        add_action('admin_init', [$this, 'registerSettings']);
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
        register_setting('wpwhobird_settings', 'wpwhobird_recordings_path', [
            'default' => 'WhoBird/recordings',
        ]);
        register_setting('wpwhobird_settings', 'wpwhobird_database_path', [
            'default' => 'WhoBird/databases/BirdDatabase.db',
        ]);

        add_settings_section(
            'wpwhobird_main_settings',
            __('Main Settings', 'wpwhobird'),
            null,
            'wpwhobird-settings'
        );

        add_settings_field(
            'wpwhobird_recordings_path',
            __('Recordings Path', 'wpwhobird'),
            [$this, 'renderTextInput'],
            'wpwhobird-settings',
            'wpwhobird_main_settings',
            [
                'label_for' => 'wpwhobird_recordings_path',
                'option_name' => 'wpwhobird_recordings_path',
            ]
        );

        add_settings_field(
            'wpwhobird_database_path',
            __('Database Path', 'wpwhobird'),
            [$this, 'renderTextInput'],
            'wpwhobird-settings',
            'wpwhobird_main_settings',
            [
                'label_for' => 'wpwhobird_database_path',
                'option_name' => 'wpwhobird_database_path',
            ]
        );
    }

    public function renderTextInput($args)
    {
        $option = get_option($args['option_name'], '');
        ?>
        <input type="text" id="<?php echo esc_attr($args['label_for']); ?>" 
               name="<?php echo esc_attr($args['option_name']); ?>" 
               value="<?php echo esc_attr($option); ?>" 
               class="regular-text">
        <?php
    }
}

// Initialize the settings page
new WhoBirdAdminSettings();
