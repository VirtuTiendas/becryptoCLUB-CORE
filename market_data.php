<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$baseDir = __DIR__;
$cacheDir = $baseDir . DIRECTORY_SEPARATOR . 'cache';
$exchangeCacheDir = $baseDir . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR . 'exchange' . DIRECTORY_SEPARATOR . 'cache';

/**
 * Ensure directory exists without emitting warnings.
 */
function ensure_directory(string $path): void
{
    if (is_dir($path)) {
        return;
    }
    $parent = dirname($path);
    if ($parent !== '' && !is_dir($parent)) {
        ensure_directory($parent);
    }
    if (!@mkdir($path, 0755) && !is_dir($path)) {
        // Fallback: try with default permissions
        @mkdir($path);
    }
}

ensure_directory($cacheDir);
ensure_directory($exchangeCacheDir);

$coinsFile = $baseDir . DIRECTORY_SEPARATOR . 'coins.json';
if (!is_readable($coinsFile)) {
    http_response_code(500);
    echo json_encode([
        'error' => 'coins_configuration_missing'
    ]);
    exit;
}

$coinsConfig = json_decode((string) file_get_contents($coinsFile), true);
if (!is_array($coinsConfig)) {
    http_response_code(500);
    echo json_encode([
        'error' => 'coins_configuration_invalid'
    ]);
    exit;
}

require_once $baseDir . DIRECTORY_SEPARATOR . 'mexc_api.php';

$symbolsParam = isset($_GET['symbols']) ? (string) $_GET['symbols'] : '';
$fiatsParam = isset($_GET['fiats']) ? (string) $_GET['fiats'] : 'USD';

$requestedSymbols = [];
if ($symbolsParam !== '') {
    $parts = explode(',', $symbolsParam);
    foreach ($parts as $part) {
        $symbol = strtoupper(trim($part));
        if ($symbol !== '') {
            $requestedSymbols[$symbol] = true;
        }
    }
}

if (count($requestedSymbols) === 0) {
    foreach ($coinsConfig as $symbol => $_config) {
        $requestedSymbols[strtoupper($symbol)] = true;
    }
}

$requestedFiats = [];
if ($fiatsParam !== '') {
    $fiatParts = explode(',', $fiatsParam);
    foreach ($fiatParts as $fiatPart) {
        $fiat = strtoupper(trim($fiatPart));
        if ($fiat !== '') {
            $requestedFiats[$fiat] = true;
        }
    }
}
if (count($requestedFiats) === 0) {
    $requestedFiats['USD'] = true;
}

$fiat = array_key_first($requestedFiats);
if ($fiat === null) {
    $fiat = 'USD';
}

/**
 * Perform HTTP GET request using cURL.
 */
function http_get_json(string $url, int $timeout = 10): array
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

    $body = curl_exec($ch);
    if ($body === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['success' => false, 'data' => null, 'error' => $error];
    }

    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($status < 200 || $status >= 300) {
        return ['success' => false, 'data' => null, 'error' => 'http_status_' . $status];
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        return ['success' => false, 'data' => null, 'error' => 'invalid_json'];
    }

    return ['success' => true, 'data' => $decoded, 'error' => null];
}

/**
 * Load cached data if available.
 */
function read_cache(string $path): ?array
{
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }
    $contents = file_get_contents($path);
    if ($contents === false || $contents === '') {
        return null;
    }
    $decoded = json_decode($contents, true);
    return is_array($decoded) ? $decoded : null;
}

/**
 * Store cache atomically.
 */
function write_cache(string $path, array $data): void
{
    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return;
    }
    $tempPath = $path . '.tmp';
    if (file_put_contents($tempPath, $json, LOCK_EX) !== false) {
        @rename($tempPath, $path);
    }
}

/**
 * Fetch coin data from CoinPaprika.
 */
function fetch_from_paprika(array $coinConfig, string $fiat): array
{
    $slug = isset($coinConfig['slug']) ? (string) $coinConfig['slug'] : '';
    if ($slug === '' || strtolower($slug) === 'null') {
        return ['success' => false, 'data' => null, 'source' => null];
    }
    $url = 'https://api.coinpaprika.com/v1/tickers/' . rawurlencode($slug) . '?quotes=' . rawurlencode(strtoupper($fiat));
    $response = http_get_json($url);
    if ($response['success'] !== true) {
        return ['success' => false, 'data' => null, 'source' => null];
    }
    $data = $response['data'];
    $quotes = $data['quotes'] ?? [];
    $fiatQuote = $quotes[strtoupper($fiat)] ?? null;
    if (!is_array($fiatQuote) || !isset($fiatQuote['price'])) {
        return ['success' => false, 'data' => null, 'source' => null];
    }
    $price = is_numeric($fiatQuote['price']) ? (float) $fiatQuote['price'] : null;
    if ($price === null) {
        return ['success' => false, 'data' => null, 'source' => null];
    }
    $timestampUnix = isset($data['last_updated']) ? strtotime((string) $data['last_updated']) : time();
    if ($timestampUnix === false) {
        $timestampUnix = time();
    }

    return [
        'success' => true,
        'data' => [
            'price' => $price,
            'name' => $data['name'] ?? ($coinConfig['name'] ?? ''),
            'rank' => isset($data['rank']) && is_numeric($data['rank']) ? (int) $data['rank'] : null,
            'src' => 'Paprika',
            'timestamp' => gmdate('c', $timestampUnix),
            'timestamp_unix' => $timestampUnix,
            'change_24h' => isset($fiatQuote['percent_change_24h']) && is_numeric($fiatQuote['percent_change_24h']) ? (float) $fiatQuote['percent_change_24h'] : null
        ],
        'source' => 'Paprika'
    ];
}

/**
 * Fetch coin data from CoinGecko.
 */
function fetch_from_gecko(array $coinConfig, string $fiat): array
{
    $geckoId = isset($coinConfig['gecko']) ? (string) $coinConfig['gecko'] : '';
    if ($geckoId === '' || strtolower($geckoId) === 'null') {
        return ['success' => false, 'data' => null, 'source' => null];
    }
    $url = 'https://api.coingecko.com/api/v3/coins/markets?vs_currency=' . rawurlencode(strtolower($fiat)) . '&ids=' . rawurlencode(strtolower($geckoId)) . '&order=market_cap_desc&per_page=1&page=1&sparkline=false&price_change_percentage=24h';
    $response = http_get_json($url);
    if ($response['success'] !== true) {
        return ['success' => false, 'data' => null, 'source' => null];
    }
    $data = $response['data'];
    if (!is_array($data) || count($data) === 0) {
        return ['success' => false, 'data' => null, 'source' => null];
    }
    $item = $data[0];
    $priceKey = 'current_price';
    if (!isset($item[$priceKey]) || !is_numeric($item[$priceKey])) {
        return ['success' => false, 'data' => null, 'source' => null];
    }
    $timestampUnix = isset($item['last_updated']) ? strtotime((string) $item['last_updated']) : time();
    if ($timestampUnix === false) {
        $timestampUnix = time();
    }

    return [
        'success' => true,
        'data' => [
            'price' => (float) $item[$priceKey],
            'name' => $item['name'] ?? ($coinConfig['name'] ?? ''),
            'rank' => isset($item['market_cap_rank']) && is_numeric($item['market_cap_rank']) ? (int) $item['market_cap_rank'] : null,
            'src' => 'Gecko',
            'timestamp' => gmdate('c', $timestampUnix),
            'timestamp_unix' => $timestampUnix,
            'change_24h' => isset($item['price_change_percentage_24h']) && is_numeric($item['price_change_percentage_24h']) ? (float) $item['price_change_percentage_24h'] : null
        ],
        'source' => 'Gecko'
    ];
}

/**
 * Fetch coin data from MEXC.
 */
function fetch_from_mexc(array $coinConfig): array
{
    $mexcSymbol = isset($coinConfig['mexc']) ? (string) $coinConfig['mexc'] : '';
    if ($mexcSymbol === '' || strtolower($mexcSymbol) === 'null') {
        return ['success' => false, 'data' => null, 'source' => null];
    }
    $response = mexc_get_ticker($mexcSymbol);
    if ($response['success'] !== true || $response['price'] === null) {
        return ['success' => false, 'data' => null, 'source' => null];
    }
    $timestampUnix = time();
    return [
        'success' => true,
        'data' => [
            'price' => (float) $response['price'],
            'name' => $coinConfig['name'] ?? '',
            'rank' => null,
            'src' => 'MEXC',
            'timestamp' => gmdate('c', $timestampUnix),
            'timestamp_unix' => $timestampUnix,
            'change_24h' => $response['change']
        ],
        'source' => 'MEXC'
    ];
}

/**
 * Load data from local cache.
 */
function fetch_from_cache(string $cachePath): array
{
    $cached = read_cache($cachePath);
    if ($cached === null || !isset($cached['price'])) {
        return ['success' => false, 'data' => null, 'source' => null];
    }
    return [
        'success' => true,
        'data' => $cached,
        'source' => 'LocalCache'
    ];
}

$prices = [];
$sourcesUsed = [];

foreach ($requestedSymbols as $symbol => $_true) {
    $symbolUpper = strtoupper($symbol);
    $coinConfig = $coinsConfig[$symbolUpper] ?? null;
    if ($coinConfig === null) {
        $coinConfig = [
            'slug' => null,
            'gecko' => null,
            'mexc' => null,
            'name' => $symbolUpper,
            'priority' => 99
        ];
    }

    $perCoinCachePath = $exchangeCacheDir . DIRECTORY_SEPARATOR . $symbolUpper . '.json';
    $result = null;
    $sourceOrder = ['Paprika', 'Gecko', 'MEXC', 'LocalCache'];

    $attempts = [
        fetch_from_paprika($coinConfig, $fiat),
        fetch_from_gecko($coinConfig, $fiat),
        fetch_from_mexc($coinConfig),
        fetch_from_cache($perCoinCachePath)
    ];

    foreach ($attempts as $attempt) {
        if ($attempt['success'] === true && is_array($attempt['data'])) {
            $result = $attempt['data'];
            $sourcesUsed[$attempt['source']] = true;
            break;
        }
        if ($attempt['source'] !== null) {
            $sourcesUsed[$attempt['source']] = true;
        }
    }

    if ($result === null) {
        $now = time();
        $prices[$symbolUpper] = [
            $fiat => 'N/A',
            'name' => $coinConfig['name'] ?? $symbolUpper,
            'rank' => null,
            'src' => 'Unavailable',
            'timestamp' => gmdate('c', $now),
            'timestamp_unix' => $now,
            'change_24h' => null
        ];
        continue;
    }

    $sourcesUsed[$result['src']] = true;
    $priceValue = $result['price'];
    $cachePayload = [
        'price' => $priceValue,
        'name' => $result['name'] ?? ($coinConfig['name'] ?? $symbolUpper),
        'rank' => $result['rank'] ?? null,
        'src' => $result['src'] ?? 'Unknown',
        'timestamp' => $result['timestamp'] ?? gmdate('c'),
        'timestamp_unix' => $result['timestamp_unix'] ?? time(),
        'change_24h' => $result['change_24h'] ?? null
    ];

    write_cache($perCoinCachePath, $cachePayload);

    $prices[$symbolUpper] = [
        $fiat => $priceValue,
        'name' => $cachePayload['name'],
        'rank' => $cachePayload['rank'],
        'src' => $cachePayload['src'],
        'timestamp' => $cachePayload['timestamp'],
        'timestamp_unix' => $cachePayload['timestamp_unix'],
        'change_24h' => $cachePayload['change_24h']
    ];
}

$sourceLabel = 'Paprika+Gecko+MEXC+LocalCache';
if (count($sourcesUsed) > 0) {
    $order = ['Paprika', 'Gecko', 'MEXC', 'LocalCache'];
    $ordered = [];
    foreach ($order as $name) {
        if (isset($sourcesUsed[$name])) {
            $ordered[] = $name;
        }
    }
    foreach ($sourcesUsed as $name => $_true) {
        if (!in_array($name, $ordered, true)) {
            $ordered[] = $name;
        }
    }
    if (count($ordered) > 0) {
        $sourceLabel = implode('+', $ordered);
    }
}

$timestampUnix = time();
$response = [
    'timestamp' => gmdate('c', $timestampUnix),
    'timestamp_unix' => $timestampUnix,
    'fiat' => $fiat,
    'source' => $sourceLabel,
    'prices' => $prices
];

$globalCachePath = $cacheDir . DIRECTORY_SEPARATOR . 'cache_market.json';
write_cache($globalCachePath, $response);

echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
