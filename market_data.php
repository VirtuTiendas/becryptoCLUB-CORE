<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$baseDir = __DIR__;
$cacheDir = $baseDir . '/api/exchange/cache';
$globalCacheFile = $baseDir . '/cache_market.json';

ensureDirectory($cacheDir);

$coinsFile = $baseDir . '/coins.json';
if (!file_exists($coinsFile)) {
    http_response_code(500);
    echo json_encode(['error' => 'coins.json not found']);
    exit;
}

$coinsJson = file_get_contents($coinsFile);
$coinsData = json_decode($coinsJson, true);
if (!is_array($coinsData)) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid coins.json']);
    exit;
}

$symbolsParam = isset($_GET['symbols']) ? (string)$_GET['symbols'] : '';
$fiatsParam = isset($_GET['fiats']) ? (string)$_GET['fiats'] : '';

$requestedSymbols = parseSymbols($symbolsParam, array_keys($coinsData));
$fiat = parseFiat($fiatsParam);

require_once $baseDir . '/mexc_api.php';
$mexcClient = new MexcApiClient();

$results = [];
$globalSources = [];
$now = time();
$isoNow = gmdate('c', $now);

foreach ($requestedSymbols as $symbol) {
    $coinMeta = $coinsData[$symbol] ?? null;
    if ($coinMeta === null) {
        continue;
    }

    $coinResult = [
        strtoupper($fiat) => 'N/A',
        'name' => (string)($coinMeta['name'] ?? $symbol),
        'rank' => null,
        'src' => 'unavailable',
        'timestamp' => $isoNow,
        'timestamp_unix' => $now,
    ];

    $dataSources = [];

    $paprikaData = fetchFromCoinPaprika($coinMeta, $fiat);
    if ($paprikaData !== null) {
        $coinResult = $paprikaData;
        $dataSources[] = 'Paprika';
    } else {
        $geckoData = fetchFromCoinGecko($coinMeta, $fiat);
        if ($geckoData !== null) {
            $coinResult = $geckoData;
            $dataSources[] = 'Gecko';
        } else {
            $mexcData = fetchFromMexc($mexcClient, $coinMeta, $fiat);
            if ($mexcData !== null) {
                $coinResult = $mexcData;
                $dataSources[] = 'MEXC';
            } else {
                $cachedData = loadCoinCache($symbol, $cacheDir);
                if ($cachedData !== null) {
                    $coinResult = $cachedData;
                    $dataSources[] = 'LocalCache';
                } else {
                    $dataSources[] = 'LocalCache';
                }
            }
        }
    }

    if ($coinResult['src'] !== 'unavailable') {
        saveCoinCache($symbol, $coinResult, $cacheDir);
    }

    foreach ($dataSources as $src) {
        if (!in_array($src, $globalSources, true)) {
            $globalSources[] = $src;
        }
    }

    $results[$symbol] = $coinResult;
}

$response = [
    'timestamp' => gmdate('c'),
    'timestamp_unix' => time(),
    'fiat' => strtoupper($fiat),
    'source' => implode('+', $globalSources) ?: 'LocalCache',
    'prices' => $results,
];

saveGlobalCache($globalCacheFile, $response);

echo json_encode($response, JSON_PRETTY_PRINT);
exit;

/**
 * Parse symbols from query string.
 *
 * @param string $symbolsParam
 * @param array $defaultSymbols
 * @return array
 */
function parseSymbols(string $symbolsParam, array $defaultSymbols): array
{
    if ($symbolsParam === '') {
        return array_values(array_unique(array_map('strtoupper', $defaultSymbols)));
    }

    $parts = array_filter(array_map('trim', explode(',', $symbolsParam)));
    if (empty($parts)) {
        return array_values(array_unique(array_map('strtoupper', $defaultSymbols)));
    }

    $normalized = [];
    foreach ($parts as $part) {
        $upper = strtoupper($part);
        if (!in_array($upper, $normalized, true)) {
            $normalized[] = $upper;
        }
    }

    return $normalized;
}

/**
 * Parse fiat currency from query string.
 *
 * @param string $fiatParam
 * @return string
 */
function parseFiat(string $fiatParam): string
{
    if ($fiatParam === '') {
        return 'USD';
    }

    $parts = array_filter(array_map('trim', explode(',', $fiatParam)));
    $fiat = strtoupper($parts[0] ?? 'USD');
    return $fiat === '' ? 'USD' : $fiat;
}

/**
 * Fetch coin data from CoinPaprika API.
 *
 * @param array $coinMeta
 * @param string $fiat
 * @return array|null
 */
function fetchFromCoinPaprika(array $coinMeta, string $fiat): ?array
{
    $slug = $coinMeta['slug'] ?? null;
    if ($slug === null || $slug === '') {
        return null;
    }

    $url = 'https://api.coinpaprika.com/v1/tickers/' . rawurlencode($slug) . '?quotes=' . strtoupper($fiat);
    $response = curlGetJson($url);

    if (!is_array($response)) {
        return null;
    }

    $quotes = $response['quotes'][$fiat] ?? $response['quotes'][strtoupper($fiat)] ?? null;
    if (!is_array($quotes)) {
        $quotes = $response['quotes'][strtoupper($fiat)] ?? null;
    }

    if (!is_array($quotes)) {
        return null;
    }

    $price = $quotes['price'] ?? null;
    if ($price === null) {
        return null;
    }

    $timestamp = strtotime($quotes['last_updated'] ?? $response['last_updated'] ?? 'now');
    if ($timestamp === false) {
        $timestamp = time();
    }

    return [
        strtoupper($fiat) => round((float)$price, 8),
        'name' => (string)($response['name'] ?? $coinMeta['name'] ?? ''),
        'rank' => isset($response['rank']) ? (int)$response['rank'] : null,
        'src' => 'paprika',
        'timestamp' => gmdate('c', $timestamp),
        'timestamp_unix' => $timestamp,
    ];
}

/**
 * Fetch coin data from CoinGecko API.
 *
 * @param array $coinMeta
 * @param string $fiat
 * @return array|null
 */
function fetchFromCoinGecko(array $coinMeta, string $fiat): ?array
{
    $geckoId = $coinMeta['gecko'] ?? null;
    if ($geckoId === null || $geckoId === '') {
        return null;
    }

    $url = 'https://api.coingecko.com/api/v3/coins/' . rawurlencode($geckoId) . '?localization=false&tickers=false&market_data=true&community_data=false&developer_data=false&sparkline=false';
    $response = curlGetJson($url);

    if (!is_array($response)) {
        return null;
    }

    $marketData = $response['market_data'] ?? null;
    if (!is_array($marketData)) {
        return null;
    }

    $currentPrice = $marketData['current_price'][strtolower($fiat)] ?? null;
    if ($currentPrice === null) {
        return null;
    }

    $timestamp = strtotime($response['last_updated'] ?? 'now');
    if ($timestamp === false) {
        $timestamp = time();
    }

    return [
        strtoupper($fiat) => round((float)$currentPrice, 8),
        'name' => (string)($response['name'] ?? $coinMeta['name'] ?? ''),
        'rank' => isset($response['market_cap_rank']) ? (int)$response['market_cap_rank'] : null,
        'src' => 'coingecko',
        'timestamp' => gmdate('c', $timestamp),
        'timestamp_unix' => $timestamp,
    ];
}

/**
 * Fetch coin data from MEXC API.
 *
 * @param MexcApiClient $client
 * @param array $coinMeta
 * @param string $fiat
 * @return array|null
 */
function fetchFromMexc(MexcApiClient $client, array $coinMeta, string $fiat): ?array
{
    $symbol = $coinMeta['mexc'] ?? null;
    if ($symbol === null || $symbol === '') {
        return null;
    }

    $ticker = $client->getUsdPrice($symbol);
    if ($ticker === null) {
        return null;
    }

    $timestamp = (int)$ticker['timestamp'];
    if ($timestamp <= 0) {
        $timestamp = time();
    }

    return [
        strtoupper($fiat) => round((float)$ticker['price'], 8),
        'name' => (string)($coinMeta['name'] ?? ''),
        'rank' => null,
        'src' => 'mexc',
        'timestamp' => gmdate('c', $timestamp),
        'timestamp_unix' => $timestamp,
    ];
}

/**
 * Load cached data for a coin.
 *
 * @param string $symbol
 * @param string $cacheDir
 * @return array|null
 */
function loadCoinCache(string $symbol, string $cacheDir): ?array
{
    $path = $cacheDir . '/' . strtoupper($symbol) . '.json';
    if (!file_exists($path)) {
        return null;
    }

    $content = file_get_contents($path);
    $decoded = json_decode((string)$content, true);
    if (!is_array($decoded)) {
        return null;
    }

    return $decoded;
}

/**
 * Save coin cache data.
 *
 * @param string $symbol
 * @param array $data
 * @param string $cacheDir
 * @return void
 */
function saveCoinCache(string $symbol, array $data, string $cacheDir): void
{
    $path = $cacheDir . '/' . strtoupper($symbol) . '.json';
    safeFilePutContents($path, json_encode($data, JSON_PRETTY_PRINT));
}

/**
 * Save global cache file.
 *
 * @param string $path
 * @param array $data
 * @return void
 */
function saveGlobalCache(string $path, array $data): void
{
    safeFilePutContents($path, json_encode($data, JSON_PRETTY_PRINT));
}

/**
 * Ensure a directory exists.
 *
 * @param string $dir
 * @return void
 */
function ensureDirectory(string $dir): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

/**
 * Write file contents safely without triggering warnings.
 *
 * @param string $path
 * @param string $content
 * @return void
 */
function safeFilePutContents(string $path, string $content): void
{
    $tmpPath = $path . '.tmp';
    file_put_contents($tmpPath, $content, LOCK_EX);
    rename($tmpPath, $path);
}

/**
 * Perform HTTP GET request returning JSON.
 *
 * @param string $url
 * @return array|null
 */
function curlGetJson(string $url): ?array
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
