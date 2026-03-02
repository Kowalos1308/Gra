<?php
/**
 * Plugin Name: AliExpress Woo Product Importer
 * Description: Importuje dane produktu z API AliExpress (Affiliate + DS) i pozwala je edytować przed zapisaniem do WooCommerce.
 * Version: 1.0.0
 * Author: Codex
 */

if (!defined('ABSPATH')) {
    exit;
}

// --- KONFIGURACJA API ---
// API #1 - Affiliate (aliexpress.affiliate.product.sku.detail.get)
define('ALI_AFFILIATE_APP_KEY', '525638');
define('ALI_AFFILATE_APP_SECRET', 'xBCxCzmFQm4boCZSiZimIXXeH2KnLE5K');

// API #2 - DS Product (aliexpress.ds.product.get)
define('ALI_APP_KEY', '525642');
define('ALI_APP_SECRET', 'EwpDUCBrgnaiKsMmiUuGsD7oi2DSfFuI');
define('ALI_SESSION', '50000800c23uvqBmwoUBZYhThlQiupE7EEs1f6c9adf5s0zCJlthYkUTAfXppRfQKAZs');

class AliExpress_Woo_Product_Importer {
    const OPTION_PRODUCTS = 'ali_woo_imported_products';
    const MENU_SLUG = 'ali-woo-importer';

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_post_ali_add_product', [$this, 'handle_add_product']);
        add_action('admin_post_ali_save_product', [$this, 'handle_save_product']);
    }

    public function register_menu() {
        add_menu_page(
            'AliExpress Import',
            'AliExpress Import',
            'manage_woocommerce',
            self::MENU_SLUG,
            [$this, 'render_add_page'],
            'dashicons-cart',
            56
        );

        add_submenu_page(
            null,
            'Edycja produktu AliExpress',
            'Edycja produktu AliExpress',
            'manage_woocommerce',
            self::MENU_SLUG . '-edit',
            [$this, 'render_edit_page']
        );
    }

    public function render_add_page() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Brak uprawnień.');
        }

        $products = get_option(self::OPTION_PRODUCTS, []);
        ?>
        <div class="wrap">
            <h1>Dodaj produkt z Ali</h1>
            <p>Wklej dane w jednej linii lub dwóch liniach, np:</p>
            <pre>Product ID: 1005005065054764
SKU: 12000031501341897</pre>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('ali_add_product'); ?>
                <input type="hidden" name="action" value="ali_add_product" />
                <textarea name="product_input" rows="4" style="width: 100%; max-width: 700px;" required></textarea>
                <p>
                    <button class="button button-primary" type="submit">Dodaj produkt</button>
                </p>
            </form>

            <?php if (!empty($products)) : ?>
                <hr />
                <h2>Zaimportowane produkty</h2>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>ID rekordu</th>
                            <th>Tytuł</th>
                            <th>Product ID</th>
                            <th>SKU</th>
                            <th>Woo ID</th>
                            <th>Akcja</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($products as $id => $product) : ?>
                        <tr>
                            <td><?php echo esc_html($id); ?></td>
                            <td><?php echo esc_html($product['title'] ?? ''); ?></td>
                            <td><?php echo esc_html($product['product_id'] ?? ''); ?></td>
                            <td><?php echo esc_html($product['sku_id'] ?? ''); ?></td>
                            <td><?php echo esc_html($product['woo_product_id'] ?? '-'); ?></td>
                            <td>
                                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=' . self::MENU_SLUG . '-edit&id=' . rawurlencode($id))); ?>">Edytuj</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    public function handle_add_product() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Brak uprawnień.');
        }

        check_admin_referer('ali_add_product');

        $input = sanitize_textarea_field(wp_unslash($_POST['product_input'] ?? ''));
        [$product_id, $sku_id] = $this->parse_product_input($input);

        if (empty($product_id) || empty($sku_id)) {
            wp_die('Nie udało się odczytać Product ID i SKU z podanego tekstu.');
        }

        $api1 = $this->call_affiliate_api($product_id, $sku_id);
        if (is_wp_error($api1)) {
            wp_die('Błąd API #1: ' . esc_html($api1->get_error_message()));
        }

        $api2 = $this->call_ds_product_api($product_id);
        if (is_wp_error($api2)) {
            wp_die('Błąd API #2: ' . esc_html($api2->get_error_message()));
        }

        $record_id = uniqid('ali_', true);

        $product_data = [
            'record_id' => $record_id,
            'created_at' => current_time('mysql'),
            'product_id' => $product_id,
            'sku_id' => $sku_id,
            'title' => $this->find_value($api1, 'title'),
            'image_link' => $this->find_value($api1, 'image_link'),
            'image_white' => $this->find_value($api1, 'image_white'),
            'original_link' => $this->find_value($api1, 'original_link'),
            'product_category' => $this->find_value($api1, 'product_category'),
            'sale_price_with_tax' => $this->find_value($api1, 'sale_price_with_tax'),
            'store_name' => $this->find_value($api1, 'store_name'),
            'product_score' => $this->find_value($api1, 'product_score'),
            'order_number' => $this->find_value($api1, 'order_number'),
            'review_number' => $this->find_value($api1, 'review_number'),
            'shipping_fees' => $this->find_value($api1, 'shipping_fees'),
            'min_delivery_days' => $this->find_value($api1, 'min_delivery_days'),
            'max_delivery_days' => $this->find_value($api1, 'max_delivery_days'),
            'ship_from_country' => $this->find_value($api1, 'ship_from_country'),
            'detail_text' => $this->clean_html_to_text((string) $this->find_value($api2, 'detail')),
            'selected_image' => 'image_link',
            'selected_categories' => [],
            'selected_attributes' => [],
            'woo_product_id' => null,
        ];

        $attributes = $this->find_value($api2, 'ae_item_properties');
        if (is_array($attributes)) {
            foreach ($attributes as $attr) {
                if (!is_array($attr)) {
                    continue;
                }
                $name = trim((string) ($attr['attr_name'] ?? ''));
                $value = trim((string) ($attr['attr_value'] ?? ''));
                if ($name === '' || $value === '') {
                    continue;
                }
                $product_data['attributes'][] = [
                    'name' => $name,
                    'value' => $value,
                    'selected' => true,
                ];
            }
        }

        $products = get_option(self::OPTION_PRODUCTS, []);
        $products[$record_id] = $product_data;
        update_option(self::OPTION_PRODUCTS, $products, false);

        wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '-edit&id=' . rawurlencode($record_id)));
        exit;
    }

    public function render_edit_page() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Brak uprawnień.');
        }

        $id = sanitize_text_field(wp_unslash($_GET['id'] ?? ''));
        $products = get_option(self::OPTION_PRODUCTS, []);
        $product = $products[$id] ?? null;

        if (empty($product)) {
            wp_die('Nie znaleziono produktu.');
        }

        $shop_categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ]);

        ?>
        <div class="wrap">
            <h1>Edycja produktu AliExpress</h1>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('ali_save_product_' . $id); ?>
                <input type="hidden" name="action" value="ali_save_product" />
                <input type="hidden" name="record_id" value="<?php echo esc_attr($id); ?>" />

                <table class="form-table">
                    <tr>
                        <th><label>Tytuł</label></th>
                        <td><input type="text" name="title" class="regular-text" value="<?php echo esc_attr($product['title'] ?? ''); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label>Cena</label></th>
                        <td><input type="text" name="sale_price_with_tax" class="regular-text" value="<?php echo esc_attr($product['sale_price_with_tax'] ?? ''); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label>Marka / sklep</label></th>
                        <td><input type="text" name="store_name" class="regular-text" value="<?php echo esc_attr($product['store_name'] ?? ''); ?>" /></td>
                    </tr>
                    <tr>
                        <th><label>Zdjęcia</label></th>
                        <td>
                            <label>
                                <input type="radio" name="selected_image" value="image_link" <?php checked(($product['selected_image'] ?? 'image_link'), 'image_link'); ?> />
                                Zwykłe
                            </label>
                            <?php if (!empty($product['image_link'])) : ?>
                                <div><img src="<?php echo esc_url($product['image_link']); ?>" style="max-width:200px;height:auto;" /></div>
                            <?php endif; ?>
                            <br />
                            <label>
                                <input type="radio" name="selected_image" value="image_white" <?php checked(($product['selected_image'] ?? 'image_link'), 'image_white'); ?> />
                                White
                            </label>
                            <?php if (!empty($product['image_white'])) : ?>
                                <div><img src="<?php echo esc_url($product['image_white']); ?>" style="max-width:200px;height:auto;" /></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Kategoria z AliExpress</th>
                        <td><?php echo esc_html($product['product_category'] ?? ''); ?></td>
                    </tr>
                    <tr>
                        <th>Kategorie sklepu</th>
                        <td>
                            <fieldset style="max-height: 220px; overflow: auto; border: 1px solid #ddd; padding: 12px;">
                            <?php if (!is_wp_error($shop_categories) && !empty($shop_categories)) : ?>
                                <?php foreach ($shop_categories as $cat) : ?>
                                    <?php $checked = in_array((int) $cat->term_id, array_map('intval', $product['selected_categories'] ?? []), true); ?>
                                    <label style="display:block;margin:4px 0;">
                                        <input type="checkbox" name="selected_categories[]" value="<?php echo esc_attr($cat->term_id); ?>" <?php checked($checked); ?> />
                                        <?php echo esc_html($this->category_label($cat)); ?>
                                    </label>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <em>Brak kategorii WooCommerce.</em>
                            <?php endif; ?>
                            </fieldset>
                        </td>
                    </tr>
                    <tr><th colspan="2"><h2>Informacje o dostawie</h2></th></tr>
                    <tr><th>Kraj wysyłki</th><td><input type="text" name="ship_from_country" value="<?php echo esc_attr($product['ship_from_country'] ?? ''); ?>" /></td></tr>
                    <tr><th>Min / max dni dostawy</th><td>
                        <input type="number" name="min_delivery_days" value="<?php echo esc_attr($product['min_delivery_days'] ?? ''); ?>" style="width:100px;" /> /
                        <input type="number" name="max_delivery_days" value="<?php echo esc_attr($product['max_delivery_days'] ?? ''); ?>" style="width:100px;" />
                    </td></tr>
                    <tr><th>Koszt dostawy</th><td><input type="text" name="shipping_fees" value="<?php echo esc_attr($product['shipping_fees'] ?? ''); ?>" /></td></tr>

                    <tr><th colspan="2"><h2>Statystyki</h2></th></tr>
                    <tr><th>Ocena / 5</th><td><input type="text" name="product_score" value="<?php echo esc_attr($product['product_score'] ?? ''); ?>" /></td></tr>
                    <tr><th>Liczba opinii</th><td><input type="number" name="review_number" value="<?php echo esc_attr($product['review_number'] ?? ''); ?>" /></td></tr>
                    <tr><th>Sprzedane sztuki</th><td><input type="number" name="order_number" value="<?php echo esc_attr($product['order_number'] ?? ''); ?>" /></td></tr>

                    <tr>
                        <th>Opis (tekst)</th>
                        <td>
                            <textarea name="detail_text" rows="8" style="width:100%;"><?php echo esc_textarea($product['detail_text'] ?? ''); ?></textarea>
                        </td>
                    </tr>

                    <tr>
                        <th>Atrybuty produktu</th>
                        <td>
                            <p>
                                <button type="button" class="button" id="ali-select-all">Zaznacz wszystkie</button>
                                <button type="button" class="button" id="ali-unselect-all">Odznacz wszystkie</button>
                            </p>
                            <div style="max-height: 260px; overflow: auto; border: 1px solid #ddd; padding: 10px;">
                                <?php foreach (($product['attributes'] ?? []) as $idx => $attr) : ?>
                                    <?php $attr_name = $attr['name'] ?? ''; $attr_value = $attr['value'] ?? ''; $selected = !empty($attr['selected']); ?>
                                    <label style="display:block; margin-bottom:8px;">
                                        <input class="ali-attr-checkbox" type="checkbox" name="attributes[<?php echo esc_attr($idx); ?>][selected]" value="1" <?php checked($selected); ?> />
                                        <strong><?php echo esc_html($attr_name); ?>:</strong> <?php echo esc_html($attr_value); ?>
                                    </label>
                                    <input type="hidden" name="attributes[<?php echo esc_attr($idx); ?>][name]" value="<?php echo esc_attr($attr_name); ?>" />
                                    <input type="hidden" name="attributes[<?php echo esc_attr($idx); ?>][value]" value="<?php echo esc_attr($attr_value); ?>" />
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                </table>

                <p>
                    <button class="button button-primary" type="submit">Zapisz</button>
                    <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=' . self::MENU_SLUG)); ?>">Powrót</a>
                </p>
            </form>
        </div>

        <script>
            (function () {
                var selectAllBtn = document.getElementById('ali-select-all');
                var unselectAllBtn = document.getElementById('ali-unselect-all');
                var boxes = document.querySelectorAll('.ali-attr-checkbox');
                if (selectAllBtn) {
                    selectAllBtn.addEventListener('click', function () {
                        boxes.forEach(function (b) { b.checked = true; });
                    });
                }
                if (unselectAllBtn) {
                    unselectAllBtn.addEventListener('click', function () {
                        boxes.forEach(function (b) { b.checked = false; });
                    });
                }
            })();
        </script>
        <?php
    }

    public function handle_save_product() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Brak uprawnień.');
        }

        $record_id = sanitize_text_field(wp_unslash($_POST['record_id'] ?? ''));
        check_admin_referer('ali_save_product_' . $record_id);

        $products = get_option(self::OPTION_PRODUCTS, []);
        if (empty($products[$record_id])) {
            wp_die('Nie znaleziono rekordu do zapisu.');
        }

        $product = $products[$record_id];
        $product['title'] = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
        $product['sale_price_with_tax'] = sanitize_text_field(wp_unslash($_POST['sale_price_with_tax'] ?? ''));
        $product['store_name'] = sanitize_text_field(wp_unslash($_POST['store_name'] ?? ''));
        $product['selected_image'] = in_array($_POST['selected_image'] ?? 'image_link', ['image_link', 'image_white'], true) ? sanitize_text_field(wp_unslash($_POST['selected_image'])) : 'image_link';
        $product['selected_categories'] = array_map('intval', (array) ($_POST['selected_categories'] ?? []));

        $product['ship_from_country'] = sanitize_text_field(wp_unslash($_POST['ship_from_country'] ?? ''));
        $product['min_delivery_days'] = sanitize_text_field(wp_unslash($_POST['min_delivery_days'] ?? ''));
        $product['max_delivery_days'] = sanitize_text_field(wp_unslash($_POST['max_delivery_days'] ?? ''));
        $product['shipping_fees'] = sanitize_text_field(wp_unslash($_POST['shipping_fees'] ?? ''));

        $product['product_score'] = sanitize_text_field(wp_unslash($_POST['product_score'] ?? ''));
        $product['review_number'] = sanitize_text_field(wp_unslash($_POST['review_number'] ?? ''));
        $product['order_number'] = sanitize_text_field(wp_unslash($_POST['order_number'] ?? ''));
        $product['detail_text'] = sanitize_textarea_field(wp_unslash($_POST['detail_text'] ?? ''));

        $attributes = [];
        foreach ((array) ($_POST['attributes'] ?? []) as $idx => $attr) {
            $attributes[$idx] = [
                'name' => sanitize_text_field($attr['name'] ?? ''),
                'value' => sanitize_text_field($attr['value'] ?? ''),
                'selected' => !empty($attr['selected']),
            ];
        }
        $product['attributes'] = $attributes;

        $woo_id = $this->upsert_woocommerce_product($product);
        if (is_wp_error($woo_id)) {
            wp_die('Błąd zapisu produktu WooCommerce: ' . esc_html($woo_id->get_error_message()));
        }

        $product['woo_product_id'] = $woo_id;
        $product['updated_at'] = current_time('mysql');

        $products[$record_id] = $product;
        update_option(self::OPTION_PRODUCTS, $products, false);

        wp_safe_redirect(admin_url('admin.php?page=' . self::MENU_SLUG . '-edit&id=' . rawurlencode($record_id) . '&saved=1'));
        exit;
    }

    private function upsert_woocommerce_product(array $product) {
        if (!function_exists('wc_get_product')) {
            return new WP_Error('missing_wc', 'WooCommerce nie jest aktywne.');
        }

        $postarr = [
            'post_title' => $product['title'] ?: 'Produkt AliExpress',
            'post_content' => $product['detail_text'] ?? '',
            'post_status' => 'publish',
            'post_type' => 'product',
        ];

        if (!empty($product['woo_product_id'])) {
            $postarr['ID'] = (int) $product['woo_product_id'];
            $product_id = wp_update_post($postarr, true);
        } else {
            $product_id = wp_insert_post($postarr, true);
        }

        if (is_wp_error($product_id)) {
            return $product_id;
        }

        update_post_meta($product_id, '_regular_price', $product['sale_price_with_tax'] ?? '');
        update_post_meta($product_id, '_price', $product['sale_price_with_tax'] ?? '');
        update_post_meta($product_id, '_sku', (string) ($product['sku_id'] ?? ''));
        update_post_meta($product_id, '_ali_original_link', $product['original_link'] ?? '');
        update_post_meta($product_id, '_ali_product_category', $product['product_category'] ?? '');
        update_post_meta($product_id, '_ali_store_name', $product['store_name'] ?? '');
        update_post_meta($product_id, '_ali_product_score', $product['product_score'] ?? '');
        update_post_meta($product_id, '_ali_order_number', $product['order_number'] ?? '');
        update_post_meta($product_id, '_ali_review_number', $product['review_number'] ?? '');
        update_post_meta($product_id, '_ali_shipping_fees', $product['shipping_fees'] ?? '');
        update_post_meta($product_id, '_ali_min_delivery_days', $product['min_delivery_days'] ?? '');
        update_post_meta($product_id, '_ali_max_delivery_days', $product['max_delivery_days'] ?? '');
        update_post_meta($product_id, '_ali_ship_from_country', $product['ship_from_country'] ?? '');

        if (!empty($product['selected_categories'])) {
            wp_set_object_terms($product_id, $product['selected_categories'], 'product_cat', false);
        }

        $wc_attributes = [];
        foreach (($product['attributes'] ?? []) as $attr) {
            if (empty($attr['selected']) || empty($attr['name']) || empty($attr['value'])) {
                continue;
            }
            $wc_attributes[] = [
                'name' => $attr['name'],
                'value' => $attr['value'],
                'position' => count($wc_attributes),
                'is_visible' => 1,
                'is_variation' => 0,
                'is_taxonomy' => 0,
            ];
        }
        update_post_meta($product_id, '_product_attributes', $wc_attributes);

        return (int) $product_id;
    }

    private function parse_product_input($input) {
        preg_match('/Product\s*ID\s*:\s*(\d+)/i', $input, $p1);
        preg_match('/SKU\s*:\s*(\d+)/i', $input, $p2);

        return [$p1[1] ?? '', $p2[1] ?? ''];
    }

    private function call_affiliate_api($product_id, $sku_id) {
        $timestamp = (string) round(microtime(true) * 1000);
        $params = [
            'app_key' => ALI_AFFILIATE_APP_KEY,
            'method' => 'aliexpress.affiliate.product.sku.detail.get',
            'sign_method' => 'sha256',
            'timestamp' => $timestamp,
            'product_id' => $product_id,
            'sku_ids' => $sku_id,
            'target_language' => 'PL',
            'target_currency' => 'PLN',
            'ship_to_country' => 'PL',
            'need_deliver_info' => 'Yes',
        ];

        $params['sign'] = $this->generate_sign($params, ALI_AFFILATE_APP_SECRET);
        return $this->make_api_request($params);
    }

    private function call_ds_product_api($product_id) {
        $timestamp = (string) round(microtime(true) * 1000);
        $params = [
            'app_key' => ALI_APP_KEY,
            'method' => 'aliexpress.ds.product.get',
            'session' => ALI_SESSION,
            'sign_method' => 'sha256',
            'timestamp' => $timestamp,
            'product_id' => $product_id,
            'target_language' => 'pl',
            'target_currency' => 'PLN',
            'ship_to_country' => 'PL',
            'remove_personal_benefit' => 'false',
        ];

        $params['sign'] = $this->generate_sign($params, ALI_APP_SECRET);
        return $this->make_api_request($params);
    }

    private function make_api_request(array $params) {
        $response = wp_remote_post('https://api-sg.aliexpress.com/sync', [
            'timeout' => 35,
            'body' => $params,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            return new WP_Error('ali_invalid_json', 'Niepoprawna odpowiedź JSON z API AliExpress.');
        }

        return $decoded;
    }

    private function generate_sign(array $params, $secret) {
        ksort($params);
        $string_to_sign = '';
        foreach ($params as $k => $v) {
            $string_to_sign .= $k . $v;
        }

        return strtoupper(hash('sha256', $secret . $string_to_sign . $secret));
    }

    private function find_value($array, $needle) {
        if (!is_array($array)) {
            return null;
        }
        if (array_key_exists($needle, $array)) {
            return $array[$needle];
        }
        foreach ($array as $value) {
            if (is_array($value)) {
                $found = $this->find_value($value, $needle);
                if ($found !== null) {
                    return $found;
                }
            }
        }
        return null;
    }

    private function clean_html_to_text($html) {
        $text = wp_strip_all_tags($html);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim((string) $text);
    }

    private function category_label($cat) {
        $prefix = '';
        $parent = (int) $cat->parent;
        while ($parent > 0) {
            $prefix .= '— ';
            $parent_obj = get_term($parent, 'product_cat');
            if (is_wp_error($parent_obj) || empty($parent_obj) || (int) $parent_obj->parent === $parent) {
                break;
            }
            $parent = (int) $parent_obj->parent;
        }
        return $prefix . $cat->name;
    }
}

new AliExpress_Woo_Product_Importer();
