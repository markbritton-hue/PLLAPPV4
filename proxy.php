<?php
/**
 * PLLApp Google Sheets Caching Proxy
 *
 * Caches Google Sheets responses server-side so all clients share one fetch.
 * type=sheet  → CSV export (20s cache)  — used by all live data pages
 * type=sheets → htmlview HTML (5min cache) — used to discover sheet names/GIDs
 */

header('Access-Control-Allow-Origin: *');

$type = $_GET['type'] ?? 'sheet';
$id   = $_GET['id']   ?? '';
$gid  = $_GET['gid']  ?? '';

// --- Input validation ---
if (!$id || !preg_match('/^[\w-]+$/', $id)) {
    http_response_code(400);
    exit('Invalid id');
}

if ($type === 'sheets') {
    header('Content-Type: text/html; charset=utf-8');
    $cacheFile = sys_get_temp_dir() . '/pllapp_meta_' . md5($id) . '.html';
    $cacheTTL  = 300; // 5 minutes
    $url       = "https://docs.google.com/spreadsheets/d/{$id}/htmlview";
} else {
    if (!$gid || !preg_match('/^\d+$/', $gid)) {
        http_response_code(400);
        exit('Invalid gid');
    }
    header('Content-Type: text/csv; charset=utf-8');
    $cacheFile = sys_get_temp_dir() . '/pllapp_sheet_' . md5($id . '_' . $gid) . '.csv';
    $cacheTTL  = 20; // 20 seconds
    $url       = "https://docs.google.com/spreadsheets/d/{$id}/export?format=csv&gid={$gid}";
}

// --- Serve from cache if fresh ---
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
    header('X-Cache: HIT');
    header('X-Cache-Age: ' . (time() - filemtime($cacheFile)) . 's');
    readfile($cacheFile);
    exit;
}

// --- Fetch from Google ---
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT      => 'PLLApp/1.0',
]);
$data     = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($data === false || $httpCode < 200 || $httpCode >= 300) {
    // Return stale cache rather than a blank error if we have one
    if (file_exists($cacheFile)) {
        header('X-Cache: STALE');
        readfile($cacheFile);
        exit;
    }
    http_response_code(502);
    exit('Failed to fetch data from Google Sheets');
}

file_put_contents($cacheFile, $data);
header('X-Cache: MISS');
echo $data;
