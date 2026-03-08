<?php

if (!defined('ABSPATH')) {
    exit;
}

class Ali_Api_Client {
    private const API_URL = 'https://api-sg.aliexpress.com/sync';

    public function call_affiliate_api($product_id, $sku_id) {
        $affiliate_app_key = $this->get_setting('affiliate_app_key', defined('ALI_AFFILIATE_APP_KEY') ? ALI_AFFILIATE_APP_KEY : '');
        $affiliate_app_secret = $this->get_setting('affiliate_app_secret', defined('ALI_AFFILATE_APP_SECRET') ? ALI_AFFILATE_APP_SECRET : '');

        $params = [
            'app_key' => $affiliate_app_key,
            'method' => 'aliexpress.affiliate.product.sku.detail.get',
            'sign_method' => 'sha256',
            'timestamp' => (string) round(microtime(true) * 1000),
            'product_id' => (string) $product_id,
            'sku_ids' => (string) $sku_id,
            'target_language' => 'PL',
            'target_currency' => 'PLN',
            'ship_to_country' => 'PL',
            'need_deliver_info' => 'Yes',
        ];

        $json = $this->request($params, $affiliate_app_secret);
        if (is_wp_error($json)) {
            return $json;
        }

        if (isset($json['error_response'])) {
            return new WP_Error('ali_api1_error', 'Błąd API SKU: ' . sanitize_text_field((string) ($json['error_response']['msg'] ?? 'Nieznany błąd')));
        }

        $result = $json['aliexpress_affiliate_product_sku_detail_get_response']['result']['result']
            ?? $json['result']['result']
            ?? null;
        return is_array($result) ? $result : new WP_Error('ali_api1_payload', 'Brak danych SKU');
    }

    public function call_ds_product_api($product_id, $sku_id = '') {
        $ds_app_key = $this->get_setting('ds_app_key', defined('ALI_APP_KEY') ? ALI_APP_KEY : '');
        $ds_app_secret = $this->get_setting('ds_app_secret', defined('ALI_APP_SECRET') ? ALI_APP_SECRET : '');
        $ds_session = $this->get_setting('ds_session', defined('ALI_SESSION') ? ALI_SESSION : '');

        $params = [
            'app_key' => $ds_app_key,
            'method' => 'aliexpress.ds.product.get',
            'session' => $ds_session,
            'sign_method' => 'sha256',
            'timestamp' => (string) round(microtime(true) * 1000),
            'product_id' => (string) $product_id,
            'target_language' => 'pl',
            'target_currency' => 'PLN',
            'ship_to_country' => 'PL',
            'remove_personal_benefit' => 'false',
        ];

        if ($sku_id !== '') {
            $params['sku_id'] = (string) $sku_id;
        }

        $json = $this->request($params, $ds_app_secret);
        if (is_wp_error($json)) {
            return $json;
        }

        if (isset($json['error_response'])) {
            return new WP_Error('ali_api2_error', 'Błąd API Product: ' . sanitize_text_field((string) ($json['error_response']['msg'] ?? 'Nieznany błąd')));
        }

        $result = $json['aliexpress_ds_product_get_response']['result']
            ?? $json['result']
            ?? null;
        return is_array($result) ? $result : new WP_Error('ali_api2_payload', 'Brak danych API2');
    }


    private function get_setting($key, $default = '') {
        $settings = get_option('ali_super_wtyka_settings', []);
        if (!is_array($settings)) {
            return $default;
        }

        $value = $settings[$key] ?? '';
        return $value !== '' ? $value : $default;
    }

    private function request($params, $secret) {
        $params['sign'] = $this->generate_sign($params, $secret);

        $response = wp_remote_get(add_query_arg($params, self::API_URL), [
            'timeout' => 30,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('ali_http_error', 'Błąd HTTP: ' . $response->get_error_message());
        }

        $body = (string) wp_remote_retrieve_body($response);
        if ($body === '' || strpos($body, '<!DOCTYPE html>') !== false) {
            return new WP_Error('ali_http_body', 'Niepoprawna odpowiedź API');
        }

        $json = json_decode($body, true);
        return is_array($json) ? $json : new WP_Error('ali_json_error', 'Niepoprawny JSON z API');
    }

    private function generate_sign($params, $secret) {
        ksort($params);
        $string_to_sign = '';
        foreach ($params as $key => $value) {
            $string_to_sign .= $key . $value;
        }
        return strtoupper(hash_hmac('sha256', $string_to_sign, $secret));
    }
}
