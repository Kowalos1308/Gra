<?php

if (!defined('ABSPATH')) {
    exit;
}

class Ali_Api_Client {
    private $api_urls = [
        'https://api-sg.aliexpress.com/sync',
        'https://gw.api.taobao.com/router/rest',
    ];

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

        $decoded = $this->request_json($params);
        if (is_wp_error($decoded)) {
            return $decoded;
        }

        if (isset($decoded['error_response'])) {
            return new WP_Error('ali_api1_error', 'Błąd API SKU: ' . sanitize_text_field((string) ($decoded['error_response']['msg'] ?? 'Nieznany błąd')));
        }

        $result = $decoded['aliexpress_affiliate_product_sku_detail_get_response']['result']['result'] ?? null;
        if (!is_array($result)) {
            return new WP_Error('ali_api1_payload', 'Brak danych SKU');
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

        $decoded = $this->request_json($params);
        if (is_wp_error($decoded)) {
            return $decoded;
        }

        if (isset($decoded['error_response'])) {
            return new WP_Error('ali_api2_error', 'Błąd API Product: ' . sanitize_text_field((string) ($decoded['error_response']['msg'] ?? 'Nieznany błąd')));
        }

        $result = $decoded['aliexpress_ds_product_get_response']['result'] ?? null;
        if (!is_array($result)) {
            return new WP_Error('ali_api2_payload', 'Brak danych');
        }

        return $result;
    }

    private function request_json($params) {
        $errors = [];

        foreach ($this->api_urls as $base_url) {
            $url = add_query_arg($params, $base_url);

            $wp = $this->request_via_wp_http($url);
            if (!is_wp_error($wp)) {
                return $wp;
            }
            $errors[] = 'wp_http(' . $base_url . '): ' . $wp->get_error_message();

            $curl = $this->request_via_curl($url);
            if (!is_wp_error($curl)) {
                return $curl;
            }
            $errors[] = 'curl(' . $base_url . '): ' . $curl->get_error_message();

            $stream = $this->request_via_stream($url);
            if (!is_wp_error($stream)) {
                return $stream;
            }
            $errors[] = 'stream(' . $base_url . '): ' . $stream->get_error_message();
        }

        return new WP_Error('ali_http_error', 'Błąd HTTP: ' . implode(' | ', array_unique($errors)));
    }

    private function request_via_wp_http($url) {
        $response = wp_remote_get($url, [
            'timeout' => 60,
            'redirection' => 3,
            'httpversion' => '1.1',
            'sslverify' => false,
            'blocking' => true,
            'headers' => [
                'Connection' => 'close',
            ],
            'user-agent' => 'AliWooImporter/1.0 (+WordPress)',
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);

        if ($code < 200 || $code >= 300 || $body === '') {
            return new WP_Error('ali_wp_http_status', 'HTTP ' . $code . ' / empty body');
        }

        return $this->decode_json_body($body);
    }

    private function request_via_curl($url) {
        if (!function_exists('curl_init')) {
            return new WP_Error('ali_curl_missing', 'Brak cURL');
        }

        $ch = curl_init();
        if (!$ch) {
            return new WP_Error('ali_curl_init', 'Nie udało się uruchomić cURL');
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => ['Connection: close'],
            CURLOPT_USERAGENT => 'AliWooImporter/1.0 (+cURL)',
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ]);

        $body = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            return new WP_Error('ali_curl_error', $err ?: 'Nieznany błąd cURL');
        }

        if ($code < 200 || $code >= 300 || $body === '') {
            return new WP_Error('ali_curl_status', 'HTTP ' . $code . ' / empty body');
        }

        return $this->decode_json_body((string) $body);
    }

    private function request_via_stream($url) {
        if (!function_exists('stream_context_create')) {
            return new WP_Error('ali_stream_missing', 'Brak stream_context_create');
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 60,
                'ignore_errors' => true,
                'header' => "Connection: close\r\nUser-Agent: AliWooImporter/1.0 (+stream)\r\n",
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false || $body === '') {
            $msg = error_get_last();
            return new WP_Error('ali_stream_error', sanitize_text_field((string) ($msg['message'] ?? 'Błąd stream')));
        }

        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $code = (int) $m[1];
            if ($code < 200 || $code >= 300) {
                return new WP_Error('ali_stream_status', 'HTTP ' . $code);
            }
        }

        return $this->decode_json_body((string) $body);
    }

    private function decode_json_body($body) {
        if (strpos($body, '<!DOCTYPE html>') !== false) {
            return new WP_Error('ali_html_response', 'Nieprawidłowe klucze API / HTML response');
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            return new WP_Error('ali_json_error', 'Niepoprawny JSON z API');
        }

        return $data;
    }

    private function generate_sign($params, $secret) {
        ksort($params);
        $string = '';
        foreach ($params as $k => $v) {
            $string .= $k . $v;
        }
        return strtoupper(hash_hmac('sha256', $string, $secret));
    }
}
