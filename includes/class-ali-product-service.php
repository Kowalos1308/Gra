<?php

if (!defined('ABSPATH')) {
    exit;
}

class Ali_Product_Service {
    private $api_client;

    public function __construct(Ali_Api_Client $api_client) {
        $this->api_client = $api_client;
    }

    public function extract_product_and_sku($raw) {
        $raw = (string) $raw;
        preg_match('/product\s*id\s*:\s*(\d+)/i', $raw, $pm);
        preg_match('/sku\s*:\s*(\d+)/i', $raw, $sm);

        // Fallback: same liczby w osobnych liniach.
        if (empty($pm[1]) || empty($sm[1])) {
            preg_match_all('/\b(\d{11,})\b/', $raw, $nm);
            $nums = $nm[1] ?? [];
            if (empty($pm[1]) && !empty($nums[0])) {
                $pm[1] = $nums[0];
            }
            if (empty($sm[1]) && !empty($nums[1])) {
                $sm[1] = $nums[1];
            }
        }

        return [
            sanitize_text_field($pm[1] ?? ''),
            sanitize_text_field($sm[1] ?? ''),
        ];
    }

    public function fetch_product_data($product_id, $sku_id) {
        $api1 = $this->api_client->call_affiliate_api($product_id, $sku_id);
        if (is_wp_error($api1)) {
            return $api1;
        }

        $api2 = $this->api_client->call_ds_product_api($product_id, $sku_id);
        if (is_wp_error($api2)) {
            return $api2;
        }

        return [
            'api1' => $api1,
            'api2' => $api2,
        ];
    }

    public function create_wc_product_from_api($product_id, $sku_id, $api1, $api2) {
        $item_info = $api1['ae_item_info'] ?? [];
        $sku_rows = $this->normalize_affiliate_sku_rows($api1);
        $sku_info = $this->pick_sku_info($sku_rows, $sku_id);

        $detail_html = $api2['ae_item_base_info_dto']['detail'] ?? '';
        $detail = $this->format_detail_text($detail_html);
        $sales_count = sanitize_text_field((string) ($api2['ae_item_base_info_dto']['sales_count'] ?? ''));

        $properties = $this->normalize_ds_properties($api2);
        $attributes = [];
        foreach ($properties as $prop) {
            $name = sanitize_text_field((string) ($prop['attr_name'] ?? ''));
            $value = sanitize_text_field((string) ($prop['attr_value'] ?? ''));
            if ($name && $value) {
                $attributes[] = ['name' => $name, 'value' => $value, 'selected' => true];
            }
        }

        $brand_from_attrs = '';
        foreach ($attributes as $attr) {
            $attr_name = strtolower((string) $attr['name']);
            if (($attr_name === 'brand name' || strpos($attr_name, 'mark') !== false) && !empty($attr['value'])) {
                $brand_from_attrs = $attr['value'];
                break;
            }
        }

        $product = new WC_Product_External();
        $title = sanitize_text_field((string) ($item_info['title'] ?? ('AliExpress ' . $product_id)));
        $price = wc_format_decimal((string) ($sku_info['sale_price_with_tax'] ?? ($sku_info['price_with_tax'] ?? '')));

        $product->set_name($title);
        if ($price !== '') {
            $product->set_regular_price($price);
            $product->set_price($price);
        }
        if ((string) $sku_id !== '') {
            $product->set_sku((string) $sku_id);
        }
        if ($detail !== '') {
            $product->set_description($detail);
        }
        $product->set_status('draft');
        $product->set_product_url(esc_url_raw((string) ($item_info['original_link'] ?? '')));
        $product->set_button_text('Kup na AliExpress');

        $new_id = $product->save();
        if (!$new_id) {
            return new WP_Error('create_failed', 'Nie udało się utworzyć produktu WooCommerce.');
        }

        $meta = [
            'ali_product_id' => sanitize_text_field((string) $product_id),
            'ali_sku_id' => sanitize_text_field((string) $sku_id),
            'image_link' => esc_url_raw((string) ($item_info['image_link'] ?? '')),
            'image_white' => esc_url_raw((string) ($item_info['image_white'] ?? '')),
            'original_link' => esc_url_raw((string) ($item_info['original_link'] ?? '')),
            'product_category' => sanitize_text_field((string) ($item_info['product_category'] ?? '')),
            'store_name' => sanitize_text_field((string) ($item_info['store_name'] ?? '')),
            'product_score' => sanitize_text_field((string) ($item_info['product_score'] ?? '')),
            'order_number' => $sales_count !== ''
                ? $sales_count
                : sanitize_text_field((string) ($item_info['order_number'] ?? '')),
            'review_number' => sanitize_text_field((string) ($item_info['review_number'] ?? '')),
            'shipping_fees' => sanitize_text_field((string) ($sku_info['shipping_fees'] ?? ($sku_info['shipping_fee'] ?? ($sku_info['shipping_cost'] ?? '')))),
            'min_delivery_days' => sanitize_text_field((string) ($sku_info['min_delivery_days'] ?? ($sku_info['delivery_days'] ?? ''))),
            'max_delivery_days' => sanitize_text_field((string) ($sku_info['max_delivery_days'] ?? ($sku_info['delivery_days'] ?? ''))),
            'ship_from_country' => sanitize_text_field((string) ($sku_info['ship_from_country'] ?? ($sku_info['ship_from'] ?? ''))),
            'brand' => sanitize_text_field((string) ($item_info['brand'] ?? $brand_from_attrs)),
            'detail' => $detail,
            'attributes' => $attributes,
            'image_choice' => 'image_link',
        ];

        if ($meta['ship_from_country'] === '' && !empty($api2['logistics_info_dto']['ship_to_country'])) {
            $meta['ship_from_country'] = sanitize_text_field((string) $api2['logistics_info_dto']['ship_to_country']);
        }

        update_post_meta($new_id, '_ali_import_data', $meta);

        $product_attrs = [];
        $position = 0;
        foreach ($attributes as $attr) {
            $product_attrs[sanitize_title($attr['name'])] = [
                'name' => $attr['name'],
                'value' => $attr['value'],
                'position' => $position++,
                'is_visible' => 1,
                'is_variation' => 0,
                'is_taxonomy' => 0,
            ];
        }
        update_post_meta($new_id, '_product_attributes', $product_attrs);

        $default_tags = $this->build_default_tags($meta);
        if (!empty($default_tags)) {
            wp_set_object_terms($new_id, $default_tags, 'product_tag');
        }

        return wc_get_product($new_id);
    }

    private function build_default_tags($meta) {
        $tags = [];

        $shipping_fees = trim((string) ($meta['shipping_fees'] ?? ''));
        if ($shipping_fees === '0' || $shipping_fees === '0.0' || $shipping_fees === '0.00') {
            $tags[] = 'Darmowa dostawa';
        }

        $ship = strtoupper(trim((string) ($meta['ship_from_country'] ?? '')));
        if ($ship === 'PL') {
            $tags[] = 'Wysyłka z Polski';
        }
        if (in_array($ship, ['ES', 'IT', 'FR', 'DE'], true)) {
            $tags[] = 'Wysyłka z Europy';
        }
        if ($ship === 'CN') {
            $tags[] = 'Wysyłka z Chin';
        }

        $min_days = (int) preg_replace('/\D+/', '', (string) ($meta['min_delivery_days'] ?? '0'));
        $max_days = (int) preg_replace('/\D+/', '', (string) ($meta['max_delivery_days'] ?? '0'));
        if ($max_days > 0 && $max_days <= 5) {
            $tags[] = 'Szybka dostawa';
        }
        if ($max_days > 0 && $max_days <= 7) {
            $tags[] = 'Dostawa w tydzień';
        }

        $sales = strtoupper(trim((string) ($meta['order_number'] ?? '')));
        if ($sales === '1000+' || preg_match('/^(\d{4,})\+?$/', $sales)) {
            $tags[] = 'Ponad 1000 sprzedaży';
        }

        return array_values(array_unique($tags));
    }

    private function pick_sku_info($sku_list, $sku_id) {
        if (!is_array($sku_list)) {
            return [];
        }

        foreach ($sku_list as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (!empty($row['sku_id']) && (string) $row['sku_id'] === (string) $sku_id) {
                return $row;
            }
        }

        return $sku_list[0] ?? [];
    }

    private function normalize_affiliate_sku_rows($api1) {
        $raw = $api1['ae_item_sku_info'] ?? $api1['ae_item_sku_infos'] ?? [];

        if (isset($raw['traffic_sku_info_list']) && is_array($raw['traffic_sku_info_list'])) {
            return $raw['traffic_sku_info_list'];
        }

        if (
            isset($raw['traffic_sku_info_list']['traffic_sku_info'])
            && is_array($raw['traffic_sku_info_list']['traffic_sku_info'])
        ) {
            return $raw['traffic_sku_info_list']['traffic_sku_info'];
        }

        if (isset($raw['traffic_sku_infos']) && is_array($raw['traffic_sku_infos'])) {
            return $raw['traffic_sku_infos'];
        }

        if (
            isset($raw['traffic_sku_infos']['traffic_sku_info'])
            && is_array($raw['traffic_sku_infos']['traffic_sku_info'])
        ) {
            return $raw['traffic_sku_infos']['traffic_sku_info'];
        }

        return is_array($raw) ? $raw : [];
    }

    private function normalize_ds_properties($api2) {
        $raw = $api2['ae_item_properties'] ?? $api2['item_properties'] ?? [];

        if (isset($raw['ae_item_property']) && is_array($raw['ae_item_property'])) {
            return $raw['ae_item_property'];
        }

        if (isset($raw['ae_item_properties']) && is_array($raw['ae_item_properties'])) {
            return $raw['ae_item_properties'];
        }

        return is_array($raw) ? $raw : [];
    }

    private function format_detail_text($html) {
        $text = html_entity_decode((string) $html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/<\s*br\s*\/?>/i', "\n", $text);
        $text = preg_replace('/<\/(p|div|li|h[1-6])>/i', "\n", $text);
        $text = wp_strip_all_tags($text);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        return sanitize_textarea_field(trim((string) $text));
    }

    public function save_edited_product($product_id, $input) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return new WP_Error('no_product', 'Produkt nie istnieje');
        }

        $meta = get_post_meta($product_id, '_ali_import_data', true);
        $meta = is_array($meta) ? $meta : [];

        $title = sanitize_text_field((string) ($input['title'] ?? ''));
        $price = wc_format_decimal((string) ($input['price'] ?? ''));
        $status = sanitize_key((string) ($input['product_status'] ?? 'draft'));
        $meta['brand'] = sanitize_text_field((string) ($input['brand'] ?? ''));
        $meta['original_link'] = esc_url_raw((string) ($input['original_link'] ?? ($meta['original_link'] ?? '')));
        $meta['image_link'] = esc_url_raw((string) ($input['image_link'] ?? ($meta['image_link'] ?? '')));
        $meta['image_white'] = esc_url_raw((string) ($input['image_white'] ?? ($meta['image_white'] ?? '')));
        $meta['product_category'] = sanitize_text_field((string) ($input['product_category'] ?? ($meta['product_category'] ?? '')));
        $meta['ship_from_country'] = sanitize_text_field((string) ($input['ship_from_country'] ?? ($meta['ship_from_country'] ?? '')));
        $meta['min_delivery_days'] = sanitize_text_field((string) ($input['min_delivery_days'] ?? ($meta['min_delivery_days'] ?? '')));
        $meta['max_delivery_days'] = sanitize_text_field((string) ($input['max_delivery_days'] ?? ($meta['max_delivery_days'] ?? '')));
        $meta['shipping_fees'] = sanitize_text_field((string) ($input['shipping_fees'] ?? ($meta['shipping_fees'] ?? '')));
        $meta['product_score'] = sanitize_text_field((string) ($input['product_score'] ?? ($meta['product_score'] ?? '')));
        $meta['review_number'] = sanitize_text_field((string) ($input['review_number'] ?? ($meta['review_number'] ?? '')));
        $meta['order_number'] = sanitize_text_field((string) ($input['order_number'] ?? ($meta['order_number'] ?? '')));
        $meta['store_name'] = sanitize_text_field((string) ($input['store_name'] ?? ($meta['store_name'] ?? '')));
        $meta['detail'] = sanitize_textarea_field((string) ($input['detail'] ?? ''));
        $meta['detail_corrected'] = !empty($input['detail_corrected']);

        $image_choice = sanitize_key((string) ($input['image_choice'] ?? 'image_link'));
        $meta['image_choice'] = in_array($image_choice, ['image_link', 'image_white'], true) ? $image_choice : 'image_link';

        $attrs = [];
        foreach (($input['attrs'] ?? []) as $attr) {
            $name = sanitize_text_field((string) ($attr['name'] ?? ''));
            $value = sanitize_text_field((string) ($attr['value'] ?? ''));
            if (!$name || !$value) {
                continue;
            }
            $attrs[] = [
                'name' => $name,
                'value' => $value,
                'selected' => !empty($attr['selected']),
            ];
        }
        $meta['attributes'] = $attrs;

        update_post_meta($product_id, '_ali_import_data', $meta);

        if ($title) {
            $product->set_name($title);
        }
        if ($price !== '') {
            $product->set_regular_price($price);
            $product->set_price($price);
        }
        if (in_array($status, ['draft', 'publish', 'pending', 'private'], true)) {
            $product->set_status($status);
        }
        if (!empty($meta['original_link'])) {
            $product->set_product_url($meta['original_link']);
        }
        $featured_image_id = absint($input['featured_image_id'] ?? 0);
        if ($featured_image_id > 0) {
            $product->set_image_id($featured_image_id);
        } elseif (isset($input['featured_image_id'])) {
            $product->set_image_id(0);
        }
        $product->save();

        $product_attrs = [];
        $pos = 0;
        foreach ($attrs as $attr) {
            if (empty($attr['selected'])) {
                continue;
            }
            $product_attrs[sanitize_title($attr['name'])] = [
                'name' => $attr['name'],
                'value' => $attr['value'],
                'position' => $pos++,
                'is_visible' => 1,
                'is_variation' => 0,
                'is_taxonomy' => 0,
            ];
        }
        update_post_meta($product_id, '_product_attributes', $product_attrs);

        if (isset($input['tax_input']['product_cat']) && is_array($input['tax_input']['product_cat'])) {
            $cat_ids = array_filter(array_map('absint', $input['tax_input']['product_cat']));
            wp_set_object_terms($product_id, $cat_ids, 'product_cat');
        }

        $tag_names = [];
        if (isset($input['selected_tags']) && is_array($input['selected_tags'])) {
            foreach ($input['selected_tags'] as $tag_name) {
                $name = sanitize_text_field((string) $tag_name);
                if ($name !== '') {
                    $tag_names[] = $name;
                }
            }
        }
        $extra_tags = sanitize_text_field((string) ($input['extra_tags'] ?? ''));
        if ($extra_tags !== '') {
            foreach (array_map('trim', explode(',', $extra_tags)) as $name) {
                if ($name !== '') {
                    $tag_names[] = sanitize_text_field($name);
                }
            }
        }
        $tag_names = array_values(array_unique($tag_names));
        if (!empty($tag_names)) {
            wp_set_object_terms($product_id, $tag_names, 'product_tag');
        }

        return true;
    }
}
