<?php
/**
 * Plugin Name: AliExpress Woo Importer (Minimal)
 * Description: Minimalna wtyczka do dodawania produktów AliExpress do WooCommerce przez API affiliate + DS.
 * Version: 0.2.0
 * Author: Codex
 */

if (!defined('ABSPATH')) {
    exit;
}

// --- KONFIGURACJA API ---
if (!defined('ALI_AFFILIATE_APP_KEY')) {
    define('ALI_AFFILIATE_APP_KEY', '525638');
}
if (!defined('ALI_AFFILATE_APP_SECRET')) {
    define('ALI_AFFILATE_APP_SECRET', 'xBCxCzmFQm4boCZSiZimIXXeH2KnLE5K');
}
if (!defined('ALI_APP_KEY')) {
    define('ALI_APP_KEY', '525642');
}
if (!defined('ALI_APP_SECRET')) {
    define('ALI_APP_SECRET', 'EwpDUCBrgnaiKsMmiUuGsD7oi2DSfFuI');
}
if (!defined('ALI_SESSION')) {
    define('ALI_SESSION', '50000800f40Dc4ooaadHuCjPevPlZcCQgOuvkojtK3DrVGGX9rPH15da5895KR0wH1f2');
}

require_once __DIR__ . '/includes/class-ali-api-client.php';
require_once __DIR__ . '/includes/class-ali-product-service.php';
require_once __DIR__ . '/includes/class-ali-admin-add-page.php';
require_once __DIR__ . '/includes/class-ali-admin-edit-page.php';

add_action('plugins_loaded', static function () {
    if (!class_exists('WooCommerce') || !class_exists('WC_Product_External')) {
        return;
    }

    $api_client = new Ali_Api_Client();
    $service = new Ali_Product_Service($api_client);

    new Ali_Admin_Add_Page($service);
    new Ali_Admin_Edit_Page($service);
});
