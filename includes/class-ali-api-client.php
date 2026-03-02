<?php

if (!defined('ABSPATH')) {
    exit;
}

class Ali_Api_Client {
    private $api_url = 'https://api-sg.aliexpress.com/sync';

    public function call_affiliate_api($product_id, $sku_id) {
        $params = [
            'app_key' => ALI_AFFILIATE_APP_KEY,
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

        $params['sign'] = $this->generate_sign($params, ALI_AFFILATE_APP_SECRET);
        $decoded = $this->request_get($params);
        if (is_wp_error($decoded)) {
            return $decoded;
        }

        if (isset($decoded['error_response'])) {
            return new WP_Error('ali_api1_error', 'API1: ' . sanitize_text_field((string) ($decoded['error_response']['msg'] ?? 'Nieznany błąd')));
        }

        $result = $decoded['aliexpress_affiliate_product_sku_detail_get_response']['result']['result'] ?? null;
        if (!is_array($result)) {
            return new WP_Error('ali_api1_payload', 'API1: Brak result/result w odpowiedzi.');
        }

        return $result;
    }

    public function call_ds_product_api($product_id, $sku_id = '') {
        $params = [
            'app_key' => ALI_APP_KEY,
            'method' => 'aliexpress.ds.product.get',
            'session' => ALI_SESSION,
            'sign_method' => 'sha256',
            'timestamp' => (string) round(microtime(true) * 1000),
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
        $decoded = $this->request_get($params);
        if (is_wp_error($decoded)) {
            return $decoded;
        }

        if (isset($decoded['error_response'])) {
            return new WP_Error('ali_api2_error', 'API2: ' . sanitize_text_field((string) ($decoded['error_response']['msg'] ?? 'Nieznany błąd')));
        }

        $result = $decoded['aliexpress_ds_product_get_response']['result'] ?? null;
        if (!is_array($result)) {
            return new WP_Error('ali_api2_payload', 'API2: Brak result w odpowiedzi.');
        }

        return $result;
    }

    private function request_get($params) {
        $url = add_query_arg($params, $this->api_url);

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'sslverify' => false,
            'user-agent' => 'AliWooImporter/0.4 (+WordPress)',
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('ali_http_error', 'Błąd HTTP: ' . $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $code = (int) wp_remote_retrieve_response_code($response);

        if ($code < 200 || $code >= 300 || $body === '') {
            return new WP_Error('ali_http_status', 'Błąd HTTP status: ' . $code);
        }

        if (strpos($body, '<!DOCTYPE html>') !== false) {
            return new WP_Error('ali_html_response', 'API zwróciło HTML zamiast JSON.');
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return new WP_Error('ali_json_error', 'Niepoprawny JSON z API.');
        }

        return $decoded;
    }

    private function generate_sign($params, $secret) {
        ksort($params);
        $to_sign = '';
        foreach ($params as $key => $value) {
            if ($key === 'sign' || $value === '' || $value === null) {
                continue;
            }
            $to_sign .= $key . $value;
        }

        return strtoupper(hash_hmac('sha256', $to_sign, $secret));
    }
}
