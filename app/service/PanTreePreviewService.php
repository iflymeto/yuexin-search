<?php

namespace app\service;

use think\facade\Cache;

class PanTreePreviewService
{
    public function getTree(array $params)
    {
        $treeStart = microtime(true);
        $treeKey = trim((string)($params['tree_key'] ?? ''));
        $url = urldecode((string)($params['url'] ?? ''));
        $isType = isset($params['is_type']) ? (int)$params['is_type'] : -1;
        $code = trim((string)($params['code'] ?? ''));
        $stoken = trim((string)($params['stoken'] ?? ''));

        if ($treeKey !== '') {
            $treeContext = Cache::get('pan_tree_context_' . $treeKey);
            if (is_array($treeContext) && !empty($treeContext['url'])) {
                $url = $treeContext['url'];
                $isType = isset($treeContext['is_type']) ? (int)$treeContext['is_type'] : $isType;
                $code = isset($treeContext['code']) ? (string)$treeContext['code'] : $code;
                $stoken = isset($treeContext['stoken']) ? (string)$treeContext['stoken'] : $stoken;
            } else {
                DiagnosticLogService::record('pan_tree', 'context_expired', 'warn', '目录凭证已过期', [
                    'tree_key' => $treeKey,
                    'duration_ms' => intval((microtime(true) - $treeStart) * 1000),
                ]);
                return jerr('目录凭证已过期，请重新获取资源', 400);
            }
        }

        if (empty($url)) {
            DiagnosticLogService::record('pan_tree', 'empty_url', 'warn', '目录地址为空', [
                'tree_key' => $treeKey,
            ]);
            return jerr('资源地址不能为空', 400);
        }

        if (strpos($url, 'http') !== 0) {
            $decryptedUrl = decryptObject($url);
            if (is_string($decryptedUrl) && $decryptedUrl !== '') {
                $url = $decryptedUrl;
            }
        }

        if (strpos($url, 'http') !== 0) {
            DiagnosticLogService::record('pan_tree', 'parse_url_failed', 'warn', '目录地址解析失败', [
                'url' => $url,
                'duration_ms' => intval((microtime(true) - $treeStart) * 1000),
            ]);
            return jerr('资源地址解析失败', 400);
        }

        if ($isType < 0) {
            $isType = determineIsType($url);
        }

        if (!in_array((int)$isType, [0, 2, 3, 4], true)) {
            DiagnosticLogService::record('pan_tree', 'unsupported_type', 'warn', '当前网盘暂不支持目录预览', [
                'url' => $url,
                'is_type' => $isType,
            ]);
            return jerr('当前网盘暂不支持目录预览', 400);
        }

        if ((int)$isType === 4) {
            DiagnosticLogService::record('pan_tree', 'xunlei_unsupported', 'warn', '暂不支持迅雷目录预览', [
                'url' => $url,
                'is_type' => $isType,
            ]);
            return jerr('暂不支持迅雷目录预览', 400);
        }

        $treeCache = new PanTreeCacheService();
        $cacheKey = $treeCache->makeKey($isType, $url, $code, $stoken);
        $cachedTree = $treeCache->get($cacheKey);
        if ($cachedTree !== null) {
            DiagnosticLogService::record('pan_tree', 'cache_hit', 'success', '命中目录树永久缓存', [
                'url' => $url,
                'is_type' => $isType,
                'duration_ms' => intval((microtime(true) - $treeStart) * 1000),
            ]);
            return jok('获取成功', $cachedTree);
        }

        try {
            $service = new PanTreeService();
            try {
                $data = $service->getTree($url, $isType, $code, $stoken);
            } catch (\Exception $e) {
                if (!$this->shouldRetryWithoutStoken($e, $isType, $stoken)) {
                    throw $e;
                }

                DiagnosticLogService::record('pan_tree', 'stoken_retry', 'warn', '分享访问令牌过期，已自动刷新重试', [
                    'url' => $url,
                    'is_type' => $isType,
                    'duration_ms' => intval((microtime(true) - $treeStart) * 1000),
                ]);
                $data = $service->getTree($url, $isType, $code, '');
            }
            $clientData = $this->filterDataForClient($data);
            $treeCache->set($cacheKey, $clientData);
            DiagnosticLogService::record('pan_tree', 'fetch_success', 'success', '目录树获取成功', [
                'url' => $url,
                'is_type' => $isType,
                'duration_ms' => intval((microtime(true) - $treeStart) * 1000),
            ]);
            return jok('获取成功', $clientData);
        } catch (\Exception $e) {
            DiagnosticLogService::record('pan_tree', 'fetch_error', 'error', '目录树获取失败：' . $e->getMessage(), [
                'url' => $url,
                'is_type' => $isType,
                'duration_ms' => intval((microtime(true) - $treeStart) * 1000),
            ]);
            return jerr($e->getMessage(), 500);
        }
    }

    private function shouldRetryWithoutStoken(\Exception $e, $isType, $stoken)
    {
        if ($stoken === '' || !in_array((int)$isType, [0, 3], true)) {
            return false;
        }

        $message = $e->getMessage();
        return stripos($message, 'stoken') !== false
            || mb_strpos($message, '令牌') !== false
            || mb_strpos($message, '过期') !== false;
    }

    public function filterDataForClient($data)
    {
        if (is_array($data)) {
            unset($data['share_url']);
            unset($data['url']);
        }

        return $data;
    }

    public function createKey($url, $isType = -1, $code = '', $stoken = '')
    {
        if (empty($url) || strpos($url, 'http') !== 0) {
            return '';
        }

        try {
            $key = bin2hex(random_bytes(16));
        } catch (\Exception $e) {
            $key = md5(uniqid('', true) . mt_rand());
        }

        Cache::set('pan_tree_context_' . $key, [
            'url' => $url,
            'is_type' => (int)$isType,
            'code' => (string)$code,
            'stoken' => (string)$stoken,
        ], 3600);

        return $key;
    }

    public function appendKeyForClient($data, $sourceUrl = '', $code = '', $stoken = '')
    {
        if (!is_array($data)) {
            return $data;
        }

        $url = $sourceUrl ?: (isset($data['content']) ? $data['content'] : '');
        $isType = isset($data['is_type']) ? (int)$data['is_type'] : -1;
        $treeKey = $this->createKey($url, $isType, $code, $stoken);
        if ($treeKey !== '') {
            $data['tree_key'] = $treeKey;
        }

        unset($data['content']);
        unset($data['fid']);

        return $data;
    }
}
