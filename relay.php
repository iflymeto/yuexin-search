<?php
/**
 * Yuexin Search relay gateway.
 *
 * 部署到香港或国内直连机器后，在主站后台填写本文件 URL 与 RELAY_SECRET。
 * 建议同时配置 $allowedClientIps，只允许主站服务器访问。
 */

define('RELAY_SECRET', 'change-this-relay-secret');
define('SIGN_TTL', 300);

$allowedClientIps = [
    // '64.120.92.228',
];

$allowedHosts = [
    'pan.quark.cn',
    'drive.quark.cn',
    'drive-pc.quark.cn',
    'drive-h.quark.cn',
    'pan.baidu.com',
    'drive.uc.cn',
    'pan.xunlei.com',
    'api-pan.xunlei.com',
];

function relayJson($code, $message, $httpCode = 400, $data = [])
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'code' => $code,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function relayClientIp()
{
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

function relayMakeSign($secret, $method, $targetUrl, $timestamp, $nonce, $headersJson, $cookies, $postData)
{
    $base = strtoupper($method) . "\n"
        . $targetUrl . "\n"
        . $timestamp . "\n"
        . $nonce . "\n"
        . hash('sha256', (string)$headersJson) . "\n"
        . hash('sha256', (string)$cookies) . "\n"
        . hash('sha256', (string)$postData);

    return hash_hmac('sha256', $base, $secret);
}

function relayHashEquals($known, $user)
{
    if (function_exists('hash_equals')) {
        return hash_equals($known, $user);
    }
    if (strlen($known) !== strlen($user)) {
        return false;
    }
    $result = 0;
    for ($i = 0, $len = strlen($known); $i < $len; $i++) {
        $result |= ord($known[$i]) ^ ord($user[$i]);
    }
    return $result === 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    relayJson(405, 'Method not allowed', 405);
}

$clientIp = relayClientIp();
if (!empty($allowedClientIps) && !in_array($clientIp, $allowedClientIps, true)) {
    relayJson(403, 'Client IP is not allowed', 403, ['ip' => $clientIp]);
}

$targetUrl = trim($_POST['target_url'] ?? '');
$method = strtoupper(trim($_POST['method'] ?? 'GET'));
$headersJson = $_POST['headers'] ?? '[]';
$cookies = $_POST['cookies'] ?? '';
$postData = $_POST['post_data'] ?? '';
$timeout = intval($_POST['timeout'] ?? 20);
$timestamp = intval($_POST['timestamp'] ?? 0);
$nonce = trim($_POST['nonce'] ?? '');
$sign = trim($_POST['sign'] ?? '');

if ($timeout <= 0 || $timeout > 120) {
    $timeout = 20;
}

if ($targetUrl === '' || $timestamp <= 0 || $nonce === '' || $sign === '') {
    relayJson(400, 'Missing required parameters');
}

if (abs(time() - $timestamp) > SIGN_TTL) {
    relayJson(401, 'Signature expired', 401);
}

$expectedSign = relayMakeSign(RELAY_SECRET, $method, $targetUrl, $timestamp, $nonce, $headersJson, $cookies, $postData);
if (!relayHashEquals($expectedSign, $sign)) {
    relayJson(401, 'Invalid signature', 401);
}

if ($targetUrl === '__health_check__') {
    relayJson(200, 'ok', 200, [
        'time' => time(),
        'ip' => $clientIp,
    ]);
}

if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'], true)) {
    relayJson(400, 'Unsupported target method');
}

$parts = parse_url($targetUrl);
$scheme = strtolower($parts['scheme'] ?? '');
$host = strtolower($parts['host'] ?? '');
if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
    relayJson(400, 'Invalid target URL');
}
if (!in_array($host, $allowedHosts, true)) {
    relayJson(403, 'Target host is not allowed', 403, ['host' => $host]);
}

$headers = json_decode($headersJson, true);
if (!is_array($headers)) {
    $headers = [];
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min($timeout, 10));
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
curl_setopt($ch, CURLOPT_ENCODING, '');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_COOKIE, $cookies);
curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT'] ?? 'YuexinSearch-Relay/1.0');

switch ($method) {
    case 'POST':
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        break;
    case 'GET':
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        break;
    default:
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($postData !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }
        break;
}

$body = curl_exec($ch);
if ($body === false) {
    $error = curl_error($ch);
    curl_close($ch);
    relayJson(502, 'Target request failed: ' . $error, 502);
}

$targetHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

http_response_code($targetHttpCode > 0 ? $targetHttpCode : 200);
header('X-Relay-Target-Http-Code: ' . $targetHttpCode);
if ($contentType) {
    header('Content-Type: ' . $contentType);
}
echo $body;
