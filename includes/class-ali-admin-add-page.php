<?php

if (!defined('ABSPATH')) {
    exit;
}

class Ali_Admin_Add_Page {
    private $service;

    public function __construct(Ali_Product_Service $service) {
        $this->service = $service;
        add_action('admin_menu', [$this, 'register_page']);
        add_action('admin_post_ali_add_product', [$this, 'handle_add_product']);
    }

    public function register_page() {
        add_menu_page(
            'Dodaj produkt z Ali',
            'Dodaj produkt z Ali',
            'manage_woocommerce',
            'ali-add-product',
            [$this, 'render_page'],
            'dashicons-download',
            56
        );
    }

    public function render_page() {
        $error = isset($_GET['ali_error']) ? sanitize_text_field(wp_unslash($_GET['ali_error'])) : '';
        ?>
        <div class="wrap">
            <h1>Dodaj produkt z Ali</h1>
            <?php if ($error) : ?>
                <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('ali_add_product_nonce'); ?>
                <input type="hidden" name="action" value="ali_add_product" />
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="ali_product_raw">Product ID + SKU</label></th>
                        <td>
                            <textarea name="ali_product_raw" id="ali_product_raw" rows="4" class="large-text" placeholder="Product ID: 1005005065054764&#10;SKU: 12000031501341897" required></textarea>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Dodaj produkt'); ?>
            </form>
        </div>
        <?php
    }

    public function handle_add_product() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Brak uprawnień.');
        }
        check_admin_referer('ali_add_product_nonce');

        $raw = isset($_POST['ali_product_raw']) ? wp_unslash($_POST['ali_product_raw']) : '';
        [$product_id, $sku_id] = $this->service->extract_product_and_sku($raw);

        if (!$product_id || !$sku_id) {
            $this->redirect_with_error('Niepoprawny Product ID lub SKU.');
        }

        $data = $this->service->fetch_product_data($product_id, $sku_id);
        if (is_wp_error($data)) {
            $this->redirect_with_error($data->get_error_message());
        }

        $product = $this->service->create_wc_product_from_api($product_id, $sku_id, $data['api1'], $data['api2']);
        if (is_wp_error($product)) {
            $this->redirect_with_error($product->get_error_message());
        }

        wp_safe_redirect(add_query_arg([
            'page' => 'ali-edit-product',
            'product_id' => $product->get_id(),
            'ali_new' => 1,
        ], admin_url('admin.php')));
        exit;
    }

    private function redirect_with_error($error_message) {
        wp_safe_redirect(add_query_arg([
            'page' => 'ali-add-product',
            'ali_error' => rawurlencode((string) $error_message),
        ], admin_url('admin.php')));
        exit;
    }
}
