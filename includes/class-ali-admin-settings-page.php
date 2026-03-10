<?php

if (!defined('ABSPATH')) {
    exit;
}

class Ali_Admin_Settings_Page {
    private const OPTION_KEY = 'ali_super_wtyka_settings';

    public function __construct() {
        add_action('admin_menu', [$this, 'register_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function register_page() {
        add_submenu_page(
            'ali-super-wtyka',
            'Ustawienia',
            'Ustawienia',
            'manage_woocommerce',
            'ali-super-wtyka-settings',
            [$this, 'render_page']
        );
    }

    public function register_settings() {
        register_setting(self::OPTION_KEY, self::OPTION_KEY, [$this, 'sanitize_settings']);
    }

    public function sanitize_settings($input) {
        $input = is_array($input) ? $input : [];

        return [
            'affiliate_app_key' => sanitize_text_field($input['affiliate_app_key'] ?? ''),
            'affiliate_app_secret' => sanitize_text_field($input['affiliate_app_secret'] ?? ''),
            'ds_app_key' => sanitize_text_field($input['ds_app_key'] ?? ''),
            'ds_app_secret' => sanitize_text_field($input['ds_app_secret'] ?? ''),
            'ds_session' => sanitize_text_field($input['ds_session'] ?? ''),
            'ai_provider' => sanitize_key($input['ai_provider'] ?? 'groq'),
            'ai_model' => sanitize_text_field($input['ai_model'] ?? ''),
            'ai_api_key' => sanitize_text_field($input['ai_api_key'] ?? ''),
        ];
    }

    public function render_page() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Brak uprawnień.');
        }

        $settings = $this->get_settings();

        echo '<div class="wrap">';
        echo '<h1>Ustawienia ALI SUPER WTYKA</h1>';
        echo '<form method="post" action="options.php">';

        settings_fields(self::OPTION_KEY);

        echo '<h2>API Affiliate</h2>';
        echo '<table class="form-table" role="presentation">';
        $this->render_row('Affiliate App Key', 'affiliate_app_key', $settings['affiliate_app_key']);
        $this->render_row('Affiliate App Secret', 'affiliate_app_secret', $settings['affiliate_app_secret']);
        echo '</table>';

        echo '<h2>API Dropshipping</h2>';
        echo '<table class="form-table" role="presentation">';
        $this->render_row('DS App Key', 'ds_app_key', $settings['ds_app_key']);
        $this->render_row('DS App Secret', 'ds_app_secret', $settings['ds_app_secret']);
        $this->render_row('DS Session', 'ds_session', $settings['ds_session']);
        echo '</table>';

        echo '<h2>AI</h2>';
        echo '<table class="form-table" role="presentation">';
        echo '<tr><th><label for="ai_provider">Wybór modelu AI / provider</label></th><td>';
        echo '<select name="' . esc_attr(self::OPTION_KEY) . '[ai_provider]" id="ai_provider">';
        echo '<option value="groq" ' . selected($settings['ai_provider'], 'groq', false) . '>Groq (llama-3.3-70b-versatile)</option>';
        echo '</select>';
        echo '</td></tr>';
        $this->render_row('Model AI', 'ai_model', $settings['ai_model']);
        $this->render_row('AI API Key', 'ai_api_key', $settings['ai_api_key']);
        echo '</table>';

        submit_button('Zapisz ustawienia');
        echo '</form>';
        echo '</div>';
    }

    private function render_row($label, $key, $value) {
        echo '<tr><th><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>';
        echo '<input class="regular-text" type="text" id="' . esc_attr($key) . '" name="' . esc_attr(self::OPTION_KEY) . '[' . esc_attr($key) . ']" value="' . esc_attr((string) $value) . '" />';
        echo '</td></tr>';
    }

    private function get_settings() {
        $saved = get_option(self::OPTION_KEY, []);
        $saved = is_array($saved) ? $saved : [];

        return [
            'affiliate_app_key' => $saved['affiliate_app_key'] ?? (defined('ALI_AFFILIATE_APP_KEY') ? ALI_AFFILIATE_APP_KEY : ''),
            'affiliate_app_secret' => $saved['affiliate_app_secret'] ?? (defined('ALI_AFFILATE_APP_SECRET') ? ALI_AFFILATE_APP_SECRET : ''),
            'ds_app_key' => $saved['ds_app_key'] ?? (defined('ALI_APP_KEY') ? ALI_APP_KEY : ''),
            'ds_app_secret' => $saved['ds_app_secret'] ?? (defined('ALI_APP_SECRET') ? ALI_APP_SECRET : ''),
            'ds_session' => $saved['ds_session'] ?? (defined('ALI_SESSION') ? ALI_SESSION : ''),
            'ai_provider' => $saved['ai_provider'] ?? 'groq',
            'ai_model' => $saved['ai_model'] ?? 'llama-3.3-70b-versatile',
            'ai_api_key' => $saved['ai_api_key'] ?? (defined('ALI_GROQ_API_KEY') ? ALI_GROQ_API_KEY : ''),
        ];
    }
}
