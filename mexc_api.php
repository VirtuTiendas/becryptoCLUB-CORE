<?php
/**
 * Lightweight MEXC API client for BeCrypto Dashboard.
 * Provides ticker price and 24h change using cURL with sane defaults
 * compatible with restrictive hosting environments.
 */

if (!function_exists('mexc_request')) {
    /**
     * Perform an HTTP GET request using cURL with tight error handling.
     *
     * @param string $url
     * @param int $timeout
     * @return array{success:bool, data:array|null, error:string|null}
     */
    function mexc_request(string $url, int $timeout = 8): array
    {
        $ch = curl_init();
        if ($ch === false) {
            return ['success' => false, 'data' => null, 'error' => 'curl_init_failed'];
        }

        $headers = [
            'Accept: application/json',
            'User-Agent: BeCryptoDashboard/1.0 (+https://becrypto.club)'
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['success' => false, 'data' => null, 'error' => $error];
        }

        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($status < 200 || $status >= 300) {
            return ['success' => false, 'data' => null, 'error' => 'http_status_' . $status];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return ['success' => false, 'data' => null, 'error' => 'invalid_json'];
        }

        return ['success' => true, 'data' => $decoded, 'error' => null];
    }
}

if (!function_exists('mexc_get_ticker')) {
    /**
     * Fetch ticker information for a given symbol.
     *
     * @param string $symbol Example: BTC_USDT
     * @return array{success:bool, price:float|null, change:float|null, error:string|null}
     */
    function mexc_get_ticker(string $symbol): array
    {
        $symbol = strtoupper(trim($symbol));
        if ($symbol === '') {
            return ['success' => false, 'price' => null, 'change' => null, 'error' => 'empty_symbol'];
        }

        $endpoint = 'https://www.mexc.com/open/api/v2/market/ticker?symbol=' . rawurlencode($symbol);
        $result = mexc_request($endpoint);
        if ($result['success'] !== true) {
            return ['success' => false, 'price' => null, 'change' => null, 'error' => $result['error']];
        }

        $data = $result['data'];
        if (!isset($data['data']) || !is_array($data['data']) || count($data['data']) === 0) {
            return ['success' => false, 'price' => null, 'change' => null, 'error' => 'empty_payload'];
        }

        $item = $data['data'][0];
        if (!is_array($item) || !isset($item['last'], $item['change_rate'])) {
            return ['success' => false, 'price' => null, 'change' => null, 'error' => 'missing_fields'];
        }

        $price = is_numeric($item['last']) ? (float) $item['last'] : null;
        $change = is_numeric($item['change_rate']) ? ((float) $item['change_rate']) * 100.0 : null;

        if ($price === null) {
            return ['success' => false, 'price' => null, 'change' => $change, 'error' => 'invalid_price'];
        }

        return ['success' => true, 'price' => $price, 'change' => $change, 'error' => null];
    }
}
