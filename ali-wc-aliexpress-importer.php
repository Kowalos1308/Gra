<?php
/**
 * Plugin Name: AliExpress Woo Importer
 * Description: Prosty importer produktu z API AliExpress do WooCommerce.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

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
    define('ALI_SESSION', '50000801932gWybqpeBDbP5KwxDogKIWGUvBk5EOSitp1ef8abe9YwPNwyfVctfmPx01');
}

class Ali_WC_AliExpress_Importer {
    private $api_url = 'https://api-sg.aliexpress.com/sync';

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
    }

    public function register_menu() {
        add_menu_page('Ali Woo Import', 'Ali Woo Import', 'manage_woocommerce', 'ali-woo-import', [$this, 'render_add_page'], 'dashicons-download');
        add_submenu_page(null, 'Edycja importu Ali', 'Edycja importu Ali', 'manage_woocommerce', 'ali-woo-import-edit', [$this, 'render_edit_page']);
    }

    private function make_sign(array $params, $secret) {
        unset($params['sign']);
        ksort($params);
        $base = $secret;
        foreach ($params as $k => $v) {
            $base .= $k . $v;
        }
        $base .= $secret;
        return strtoupper(hash('sha256', $base));
    }

    private function call_api(array $params, $secret) {
        $params['sign'] = $this->make_sign($params, $secret);
        $response = wp_remote_post($this->api_url, [
            'timeout' => 30,
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

    private function parse_input($raw) {
        $product_id = '';
        $sku_id = '';
        if (preg_match('/Product\s*ID\s*:\s*(\d+)/i', $raw, $m)) {
            $product_id = $m[1];
        }
        if (preg_match('/SKU\s*:\s*(\d+)/i', $raw, $m)) {
            $sku_id = $m[1];
        }
        return [$product_id, $sku_id];
    }

    public function render_add_page() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Brak uprawnień.');
        }

        echo '<div class="wrap"><h1>Dodaj produkt z Ali</h1>';

        if (!class_exists('WooCommerce')) {
            echo '<p>WooCommerce nie jest aktywny.</p></div>';
            return;
        }

        if (!empty($_POST['ali_add_product'])) {
            check_admin_referer('ali_add_product_action');

            $raw = isset($_POST['ali_product_line']) ? wp_unslash($_POST['ali_product_line']) : '';
            list($product_id, $sku_id) = $this->parse_input($raw);

            if (!$product_id || !$sku_id) {
                echo '<div class="notice notice-error"><p>Podaj Product ID i SKU w formacie z opisu.</p></div>';
            } else {
                $timestamp = (string) round(microtime(true) * 1000);

                $api1_params = [
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

                $api2_params = [
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

                $api1 = $this->call_api($api1_params, ALI_AFFILATE_APP_SECRET);
                $api2 = $this->call_api($api2_params, ALI_APP_SECRET);

                if (is_wp_error($api1) || is_wp_error($api2)) {
                    $msg = is_wp_error($api1) ? $api1->get_error_message() : $api2->get_error_message();
                    echo '<div class="notice notice-error"><p>Błąd API: ' . esc_html($msg) . '</p></div>';
                } else {
                    $item = $api1['result']['result']['ae_item_info'] ?? [];
                    $skus = $api1['result']['result']['ae_item_sku_info'] ?? [];
                    $sku = [];
                    foreach ($skus as $one) {
                        if (($one['sku_id'] ?? '') === $sku_id) {
                            $sku = $one;
                            break;
                        }
                    }
                    if (empty($sku) && !empty($skus[0])) {
                        $sku = $skus[0];
                    }

                    $api2_result = $api2['result'] ?? [];
                    $detail_raw = $api2_result['ae_item_base_info_dto']['detail'] ?? '';
                    $detail_text = trim(wp_strip_all_tags(html_entity_decode($detail_raw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                    $attrs = $api2_result['ae_item_properties'] ?? [];

                    $product = new WC_Product_External();
                    $product->set_name($item['title'] ?? ('AliExpress #' . $product_id));
                    $product->set_status('draft');
                    $product->set_catalog_visibility('visible');
                    $product->set_regular_price((string) ($sku['sale_price_with_tax'] ?? ''));
                    $product->set_description($detail_text);
                    $product->set_product_url($item['original_link'] ?? '');
                    $product->set_button_text('Kup na AliExpress');
                    $product_id_wp = $product->save();

                    update_post_meta($product_id_wp, '_ali_product_id', $product_id);
                    update_post_meta($product_id_wp, '_ali_sku_id', $sku_id);
                    update_post_meta($product_id_wp, '_ali_image_link', $item['image_link'] ?? '');
                    update_post_meta($product_id_wp, '_ali_image_white', $item['image_white'] ?? '');
                    update_post_meta($product_id_wp, '_ali_product_category', $item['product_category'] ?? '');
                    update_post_meta($product_id_wp, '_ali_store_name', $item['store_name'] ?? '');
                    update_post_meta($product_id_wp, '_ali_product_score', $item['product_score'] ?? '');
                    update_post_meta($product_id_wp, '_ali_order_number', $item['order_number'] ?? '');
                    update_post_meta($product_id_wp, '_ali_review_number', $item['review_number'] ?? '');
                    update_post_meta($product_id_wp, '_ali_shipping_fees', $sku['shipping_fees'] ?? '');
                    update_post_meta($product_id_wp, '_ali_min_delivery_days', $sku['min_delivery_days'] ?? '');
                    update_post_meta($product_id_wp, '_ali_max_delivery_days', $sku['max_delivery_days'] ?? '');
                    update_post_meta($product_id_wp, '_ali_ship_from_country', $sku['ship_from_country'] ?? '');
                    update_post_meta($product_id_wp, '_ali_brand', $item['brand'] ?? '');
                    update_post_meta($product_id_wp, '_ali_attrs_raw', $attrs);
                    update_post_meta($product_id_wp, '_ali_attrs_selected', wp_list_pluck($attrs, 'attr_name'));

                    wp_safe_redirect(admin_url('admin.php?page=ali-woo-import-edit&product_id=' . $product_id_wp));
                    exit;
                }
            }
        }

        echo '<form method="post">';
        wp_nonce_field('ali_add_product_action');
        echo '<p><textarea name="ali_product_line" rows="4" cols="60" placeholder="Product ID: 1005005065054764&#10;SKU: 12000031501341897"></textarea></p>';
        submit_button('Dodaj produkt', 'primary', 'ali_add_product');
        echo '</form></div>';
    }

    public function render_edit_page() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Brak uprawnień.');
        }

        $product_id = isset($_GET['product_id']) ? absint($_GET['product_id']) : 0;
        $product = wc_get_product($product_id);
        if (!$product) {
            echo '<div class="wrap"><h1>Edycja importu Ali</h1><p>Nie znaleziono produktu.</p></div>';
            return;
        }

        if (!empty($_POST['ali_save_product'])) {
            check_admin_referer('ali_save_product_' . $product_id);

            $title = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
            $price = wc_format_decimal(wp_unslash($_POST['price'] ?? ''));
            $brand = sanitize_text_field(wp_unslash($_POST['brand'] ?? ''));
            $url = esc_url_raw(wp_unslash($_POST['original_link'] ?? ''));
            $desc = sanitize_textarea_field(wp_unslash($_POST['detail'] ?? ''));
            $image_choice = sanitize_text_field(wp_unslash($_POST['image_choice'] ?? 'normal'));
            $selected_cat = absint($_POST['shop_category'] ?? 0);

            $selected_attrs = isset($_POST['attrs']) ? array_map('sanitize_text_field', (array) wp_unslash($_POST['attrs'])) : [];

            $product->set_name($title);
            $product->set_regular_price((string) $price);
            $product->set_product_url($url);
            $product->set_description($desc);
            $product->set_status('draft');
            $product->save();

            if ($selected_cat) {
                wp_set_post_terms($product_id, [$selected_cat], 'product_cat', false);
            }

            update_post_meta($product_id, '_ali_brand', $brand);
            update_post_meta($product_id, '_ali_image_choice', $image_choice);
            update_post_meta($product_id, '_ali_attrs_selected', $selected_attrs);
            update_post_meta($product_id, '_ali_shipping_fees', sanitize_text_field(wp_unslash($_POST['shipping_fees'] ?? '')));
            update_post_meta($product_id, '_ali_min_delivery_days', sanitize_text_field(wp_unslash($_POST['min_delivery_days'] ?? '')));
            update_post_meta($product_id, '_ali_max_delivery_days', sanitize_text_field(wp_unslash($_POST['max_delivery_days'] ?? '')));
            update_post_meta($product_id, '_ali_ship_from_country', sanitize_text_field(wp_unslash($_POST['ship_from_country'] ?? '')));
            update_post_meta($product_id, '_ali_product_score', sanitize_text_field(wp_unslash($_POST['product_score'] ?? '')));
            update_post_meta($product_id, '_ali_review_number', sanitize_text_field(wp_unslash($_POST['review_number'] ?? '')));
            update_post_meta($product_id, '_ali_order_number', sanitize_text_field(wp_unslash($_POST['order_number'] ?? '')));

            $raw_attrs = get_post_meta($product_id, '_ali_attrs_raw', true);
            $wc_attributes = [];
            if (is_array($raw_attrs)) {
                foreach ($raw_attrs as $idx => $attr) {
                    $name = trim((string) ($attr['attr_name'] ?? ''));
                    $value = trim((string) ($attr['attr_value'] ?? ''));
                    if (!$name || !$value || !in_array($name, $selected_attrs, true)) {
                        continue;
                    }
                    $attribute = new WC_Product_Attribute();
                    $attribute->set_id(0);
                    $attribute->set_name($name);
                    $attribute->set_options([$value]);
                    $attribute->set_visible(true);
                    $attribute->set_variation(false);
                    $wc_attributes[$idx] = $attribute;
                }
            }
            $product->set_attributes($wc_attributes);
            $product->save();

            echo '<div class="notice notice-success"><p>Zapisano.</p></div>';
        }

        $title = $product->get_name();
        $price = $product->get_regular_price();
        $brand = get_post_meta($product_id, '_ali_brand', true);
        $image_link = get_post_meta($product_id, '_ali_image_link', true);
        $image_white = get_post_meta($product_id, '_ali_image_white', true);
        $image_choice = get_post_meta($product_id, '_ali_image_choice', true) ?: 'normal';
        $original_link = $product->get_product_url();
        $detail = $product->get_description();
        $ali_category = get_post_meta($product_id, '_ali_product_category', true);
        $shipping_fees = get_post_meta($product_id, '_ali_shipping_fees', true);
        $min_delivery_days = get_post_meta($product_id, '_ali_min_delivery_days', true);
        $max_delivery_days = get_post_meta($product_id, '_ali_max_delivery_days', true);
        $ship_from_country = get_post_meta($product_id, '_ali_ship_from_country', true);
        $product_score = get_post_meta($product_id, '_ali_product_score', true);
        $review_number = get_post_meta($product_id, '_ali_review_number', true);
        $order_number = get_post_meta($product_id, '_ali_order_number', true);
        $raw_attrs = get_post_meta($product_id, '_ali_attrs_raw', true);
        $selected_attrs = get_post_meta($product_id, '_ali_attrs_selected', true);
        if (!is_array($selected_attrs)) {
            $selected_attrs = [];
        }

        echo '<div class="wrap"><h1>Edycja importu Ali</h1>';
        echo '<form method="post">';
        wp_nonce_field('ali_save_product_' . $product_id);

        echo '<h2>Dane produktu</h2>';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label>Tytuł</label></th><td><input type="text" class="regular-text" name="title" value="' . esc_attr($title) . '"></td></tr>';
        echo '<tr><th><label>Cena</label></th><td><input type="text" name="price" value="' . esc_attr($price) . '"></td></tr>';
        echo '<tr><th><label>Marka</label></th><td><input type="text" name="brand" value="' . esc_attr($brand) . '"></td></tr>';
        echo '<tr><th><label>Link AliExpress</label></th><td><input type="url" class="regular-text" name="original_link" value="' . esc_attr($original_link) . '"></td></tr>';
        echo '<tr><th><label>Opis (czysty tekst)</label></th><td><textarea name="detail" rows="6" class="large-text">' . esc_textarea($detail) . '</textarea></td></tr>';
        echo '</tbody></table>';

        echo '<h2>Zdjęcia</h2>';
        echo '<p><label><input type="radio" name="image_choice" value="normal" ' . checked($image_choice, 'normal', false) . '> Zwykłe</label> ';
        echo '<label><input type="radio" name="image_choice" value="white" ' . checked($image_choice, 'white', false) . '> White</label></p>';
        if ($image_link) {
            echo '<p>Normal: <a href="' . esc_url($image_link) . '" target="_blank" rel="noopener">' . esc_html($image_link) . '</a><br><img src="' . esc_url($image_link) . '" alt="Normal"></p>';
        }
        if ($image_white) {
            echo '<p>White: <a href="' . esc_url($image_white) . '" target="_blank" rel="noopener">' . esc_html($image_white) . '</a><br><img src="' . esc_url($image_white) . '" alt="White"></p>';
        }

        echo '<h2>Kategorie</h2>';
        echo '<p><strong>Kategoria AliExpress:</strong> ' . esc_html($ali_category) . '</p>';
        wp_dropdown_categories([
            'show_option_none' => '— Wybierz kategorię sklepu —',
            'taxonomy' => 'product_cat',
            'name' => 'shop_category',
            'hierarchical' => true,
            'hide_empty' => false,
            'selected' => current(wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids'])) ?: 0,
        ]);

        echo '<h2>Dostawa</h2>';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th>Kraj wysyłki</th><td><input type="text" name="ship_from_country" value="' . esc_attr($ship_from_country) . '"></td></tr>';
        echo '<tr><th>Min dni</th><td><input type="text" name="min_delivery_days" value="' . esc_attr($min_delivery_days) . '"></td></tr>';
        echo '<tr><th>Max dni</th><td><input type="text" name="max_delivery_days" value="' . esc_attr($max_delivery_days) . '"></td></tr>';
        echo '<tr><th>Koszty wysyłki</th><td><input type="text" name="shipping_fees" value="' . esc_attr($shipping_fees) . '"></td></tr>';
        echo '</tbody></table>';

        echo '<h2>Statystyki</h2>';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th>Ocena /5</th><td><input type="text" name="product_score" value="' . esc_attr($product_score) . '"></td></tr>';
        echo '<tr><th>Liczba opinii</th><td><input type="text" name="review_number" value="' . esc_attr($review_number) . '"></td></tr>';
        echo '<tr><th>Sprzedanych sztuk</th><td><input type="text" name="order_number" value="' . esc_attr($order_number) . '"></td></tr>';
        echo '</tbody></table>';

        echo '<h2>Atrybuty produktu</h2>';
        echo '<p><button type="button" class="button" id="ali-select-all">Zaznacz wszystkie</button> <button type="button" class="button" id="ali-unselect-all">Odznacz wszystkie</button></p>';
        echo '<table class="widefat striped"><thead><tr><th>Dodaj</th><th>Nazwa</th><th>Wartość</th></tr></thead><tbody>';
        if (is_array($raw_attrs) && !empty($raw_attrs)) {
            foreach ($raw_attrs as $attr) {
                $name = (string) ($attr['attr_name'] ?? '');
                $value = (string) ($attr['attr_value'] ?? '');
                if (!$name && !$value) {
                    continue;
                }
                echo '<tr>';
                echo '<td><input type="checkbox" class="ali-attr-check" name="attrs[]" value="' . esc_attr($name) . '" ' . checked(in_array($name, $selected_attrs, true), true, false) . '></td>';
                echo '<td>' . esc_html($name) . '</td>';
                echo '<td>' . esc_html($value) . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="3">Brak atrybutów.</td></tr>';
        }
        echo '</tbody></table>';

        submit_button('Zapisz', 'primary', 'ali_save_product');
        echo '</form></div>';

        echo '<script>
        document.getElementById("ali-select-all")?.addEventListener("click", function(){
            document.querySelectorAll(".ali-attr-check").forEach(function(el){ el.checked = true; });
        });
        document.getElementById("ali-unselect-all")?.addEventListener("click", function(){
            document.querySelectorAll(".ali-attr-check").forEach(function(el){ el.checked = false; });
        });
        </script>';
    }
}

new Ali_WC_AliExpress_Importer();
