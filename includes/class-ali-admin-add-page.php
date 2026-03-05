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
        add_action('admin_post_ali_replace_product', [$this, 'handle_replace_product']);
    }

    public function register_page() {
        add_submenu_page(
            'ali-super-wtyka',
            'Dodaj produkt z Ali',
            'Dodaj produkt z Ali',
            'manage_woocommerce',
            'ali-add-product',
            [$this, 'render_page']
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
            <?php if (!empty($_GET['ali_exists']) && !empty($_GET['existing_id']) && !empty($_GET['product_id']) && !empty($_GET['sku_id'])) : ?>
                <div class="notice notice-warning">
                    <p>Produkt już istnieje. Chcesz go usunąć i dodać na nowo?</p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:8px;">
                        <?php wp_nonce_field('ali_replace_product_nonce'); ?>
                        <input type="hidden" name="action" value="ali_replace_product" />
                        <input type="hidden" name="existing_id" value="<?php echo esc_attr(absint($_GET['existing_id'])); ?>" />
                        <input type="hidden" name="product_id" value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_GET['product_id']))); ?>" />
                        <input type="hidden" name="sku_id" value="<?php echo esc_attr(sanitize_text_field(wp_unslash($_GET['sku_id']))); ?>" />
                        <button class="button" style="background:#d63638;border-color:#d63638;color:#fff;">TAK</button>
                    </form>
                    <a class="button" style="background:#00a32a;border-color:#00a32a;color:#fff;" href="<?php echo esc_url(admin_url('admin.php?page=ali-add-product')); ?>">NIE</a>
                </div>
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

        $existing_id = $this->service->find_existing_product_id($product_id, $sku_id);
        if ($existing_id > 0) {
            wp_safe_redirect(add_query_arg([
                'page' => 'ali-add-product',
                'ali_exists' => 1,
                'existing_id' => $existing_id,
                'product_id' => $product_id,
                'sku_id' => $sku_id,
            ], admin_url('admin.php')));
            exit;
        }

        $this->import_product($product_id, $sku_id);
    }

    public function handle_replace_product() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Brak uprawnień.');
        }
        check_admin_referer('ali_replace_product_nonce');

        $existing_id = absint($_POST['existing_id'] ?? 0);
        $product_id = sanitize_text_field(wp_unslash($_POST['product_id'] ?? ''));
        $sku_id = sanitize_text_field(wp_unslash($_POST['sku_id'] ?? ''));

        if (!$existing_id || !$product_id || !$sku_id) {
            $this->redirect_with_error('Brak danych do podmiany produktu.');
        }

        wp_delete_post($existing_id, true);
        $this->import_product($product_id, $sku_id);
    }

    private function import_product($product_id, $sku_id) {

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
