<?php
/**
 * Lightweight MEXC market data client.
 * All outbound HTTP requests use curl for compatibility with restrictive PHP environments.
 */
class MexcApiClient
{
    private const BASE_URL = 'https://www.mexc.com';

    /**
     * Fetch the latest ticker for a trading pair from MEXC.
     *
     * @param string $symbol Trading pair, e.g. "BTC_USDT".
     * @return array|null    Associative array with ticker data or null on failure.
     */
    public function fetchTicker(string $symbol): ?array
    {
        $query = http_build_query([
            'symbol' => strtoupper($symbol),
        ]);

        $url = self::BASE_URL . '/open/api/v2/market/ticker?' . $query;
        $response = $this->curlGetJson($url);

        if (!is_array($response) || (int)($response['code'] ?? 1) !== 0) {
            return null;
        }

        $data = $response['data'][0] ?? null;
        if (!is_array($data)) {
            return null;
        }

        return $data;
    }

    /**
     * Retrieve the last trade price in USD (USDT) for the provided trading pair.
     *
     * @param string $symbol Trading pair symbol in MEXC format (e.g. BTC_USDT).
     * @return array|null    Returns an associative array containing price, timestamp, and other fields, or null on failure.
     */
    public function getUsdPrice(string $symbol): ?array
    {
        $ticker = $this->fetchTicker($symbol);
        if ($ticker === null) {
            return null;
        }

        $price = $ticker['last'] ?? $ticker['lastPrice'] ?? null;
        if ($price === null) {
            return null;
        }

        $timestamp = isset($ticker['time']) ? (int)$ticker['time'] : time();

        return [
            'price' => (float)$price,
            'timestamp' => $timestamp,
            'source' => 'mexc',
        ];
    }

    /**
     * Execute a GET request via curl and decode the JSON response.
     *
     * @param string $url
     * @return array|null
     */
    private function curlGetJson(string $url): ?array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: BeCryptoDashboard/1.0',
            ],
        ]);

        $result = curl_exec($ch);
        if ($result === false) {
            curl_close($ch);
            return null;
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 200) {
            return null;
        }

        $decoded = json_decode($result, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }
}
