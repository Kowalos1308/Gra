<?php

if (!defined('ABSPATH')) {
    exit;
}

class Ali_Api_Client {
    private $api_url = 'https://api-sg.aliexpress.com/sync';

    public function call_affiliate_api($product_id, $sku_id) {
        $timestamp = (string) round(microtime(true) * 1000);

        $params = [
            'app_key' => ALI_AFFILIATE_APP_KEY,
            'method' => 'aliexpress.affiliate.product.sku.detail.get',
            'sign_method' => 'sha256',
            'timestamp' => $timestamp,
            'product_id' => (string) $product_id,
            'sku_ids' => (string) $sku_id,
            'target_language' => 'PL',
            'target_currency' => 'PLN',
            'ship_to_country' => 'PL',
            'need_deliver_info' => 'Yes',
        ];

        $params['sign'] = $this->generate_sign($params, ALI_AFFILATE_APP_SECRET);

        $response = wp_remote_get(add_query_arg($params, $this->api_url), [
            'timeout' => 30,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('ali_http_error', 'Błąd HTTP: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data || isset($data['error_response'])) {
            return new WP_Error(
                'ali_api1_error',
                'Błąd API SKU: ' . sanitize_text_field((string) ($data['error_response']['msg'] ?? 'Nieznany błąd'))
            );
        }

        $result = $data['aliexpress_affiliate_product_sku_detail_get_response']['result']['result'] ?? null;
        if (!is_array($result)) {
            return new WP_Error('ali_api1_payload', 'Brak danych SKU');
        }

        return $result;
    }

    public function call_ds_product_api($product_id, $sku_id = '') {
        $timestamp = (string) round(microtime(true) * 1000);

        $params = [
            'app_key' => ALI_APP_KEY,
            'method' => 'aliexpress.ds.product.get',
            'session' => ALI_SESSION,
            'sign_method' => 'sha256',
            'timestamp' => $timestamp,
            'product_id' => (string) $product_id,
            'target_language' => 'pl',
            'target_currency' => 'PLN',
            'ship_to_country' => 'PL',
            'remove_personal_benefit' => 'false',
        ];

        if (!empty($sku_id)) {
            $params['sku_id'] = (string) $sku_id;
        }

        $params['sign'] = $this->generate_sign($params, ALI_APP_SECRET);

        $response = wp_remote_get(add_query_arg($params, $this->api_url), [
            'timeout' => 30,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('ali_http_error', 'Błąd HTTP: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        if (strpos($body, '<!DOCTYPE html>') !== false) {
            return new WP_Error('ali_api2_html', 'Nieprawidłowe klucze API');
        }

        $data = json_decode($body, true);

        if (!$data || isset($data['error_response'])) {
            return new WP_Error(
                'ali_api2_error',
                'Błąd API Product: ' . sanitize_text_field((string) ($data['error_response']['msg'] ?? 'Nieznany błąd'))
            );
        }

        $result = $data['aliexpress_ds_product_get_response']['result'] ?? null;
        if (!is_array($result)) {
            return new WP_Error('ali_api2_payload', 'Brak danych');
        }

        return $result;
    }

    private function generate_sign($params, $secret) {
        ksort($params);
        $string_to_sign = '';
        foreach ($params as $k => $v) {
            $string_to_sign .= $k . $v;
        }
        return strtoupper(hash_hmac('sha256', $string_to_sign, $secret));
    }
}
