<?php

if (!defined('ABSPATH')) {
    exit;
}

class Ali_Api_Client {
    private $urls = [
        'https://api-sg.aliexpress.com/sync',
        'http://api-sg.aliexpress.com/sync',
        'https://gw.api.taobao.com/router/rest',
        'http://gw.api.taobao.com/router/rest',
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

        return $this->request_with_fallback($params);
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

        return $this->request_with_fallback($params);
    }

    private function request_with_fallback($params) {
        $errors = [];

        foreach ($this->urls as $url) {
            $result = $this->request_get($url, $params);
            if (!is_wp_error($result)) {
                return $result;
            }
            $errors[] = $result->get_error_message();

            $result = $this->request_post($url, $params);
            if (!is_wp_error($result)) {
                return $result;
            }
            $errors[] = $result->get_error_message();
        }

        return new WP_Error('ali_api_error', 'Błąd API: ' . implode(' | ', array_unique($errors)));
    }

    private function request_get($url, $params) {
        $response = wp_remote_get(add_query_arg($params, $url), [
            'timeout' => 45,
            'connect_timeout' => 20,
            'sslverify' => false,
            'user-agent' => 'AliWooImporter/0.3 (+WordPress)',
        ]);

        $decoded = $this->decode_response($response);
        if (!is_wp_error($decoded)) {
            return $decoded;
        }

        if (function_exists('curl_init')) {
            $fallback = $this->curl_request('GET', add_query_arg($params, $url), []);
            if (!is_wp_error($fallback)) {
                return $fallback;
            }
        }

        return $decoded;
    }

    private function request_post($url, $params) {
        $response = wp_remote_post($url, [
            'timeout' => 45,
            'connect_timeout' => 20,
            'sslverify' => false,
            'body' => $params,
            'user-agent' => 'AliWooImporter/0.3 (+WordPress)',
        ]);

        $decoded = $this->decode_response($response);
        if (!is_wp_error($decoded)) {
            return $decoded;
        }

        if (function_exists('curl_init')) {
            $fallback = $this->curl_request('POST', $url, $params);
            if (!is_wp_error($fallback)) {
                return $fallback;
            }
        }

        return $decoded;
    }

    private function curl_request($method, $url, $params) {
        $ch = curl_init();
        if (!$ch) {
            return new WP_Error('ali_api_curl_init', 'Nie udało się zainicjalizować cURL');
        }

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'AliWooImporter/0.3 (+WordPress)',
        ];

        if ($method === 'POST') {
            $opts[CURLOPT_URL] = $url;
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = http_build_query($params);
        } else {
            $opts[CURLOPT_URL] = $url;
        }

        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            return new WP_Error('ali_api_curl_error', 'cURL fallback error: ' . $error);
        }
        if ($code < 200 || $code >= 300) {
            return new WP_Error('ali_api_curl_http', 'cURL fallback HTTP ' . $code);
        }

        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            return new WP_Error('ali_api_curl_json', 'cURL fallback: niepoprawny JSON');
        }

        return $decoded;
    }

    private function decode_response($response) {
        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code < 200 || $code >= 300 || empty($body)) {
            return new WP_Error('ali_api_http', 'HTTP ' . $code . ' empty/invalid body');
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return new WP_Error('ali_api_json', 'Niepoprawny JSON z API');
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
