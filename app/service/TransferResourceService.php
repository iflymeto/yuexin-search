<?php

namespace app\service;

use think\facade\Cache;

class TransferResourceService
{
    private $sourceModel;
    private $panTreeService;
    private $transferProcessService;

    public function __construct($sourceModel, PanTreePreviewService $panTreeService = null, TransferProcessService $transferProcessService = null)
    {
        $this->sourceModel = $sourceModel;
        $this->panTreeService = $panTreeService ?: new PanTreePreviewService();
        $this->transferProcessService = $transferProcessService ?: new TransferProcessService($sourceModel, $this->panTreeService);
    }

    public function saveUrl(array $value, array $passwordConfig)
    {
        $transferStart = microtime(true);
        $value = $this->normalizeValue($value);

        if (empty($value['title']) || empty($value['url'])) {
            DiagnosticLogService::record('transfer', 'invalid_param', 'warn', '获取资源参数不完整', [
                'keyword' => $value['title'],
                'url' => $value['url'],
            ]);
            return jerr("参数不对");
        }

        DiagnosticLogService::record('transfer', 'start', 'info', '开始获取资源', [
            'keyword' => $value['title'],
            'url' => $value['url'],
            'is_type' => determineIsType($value['url']),
        ]);

        $passwordError = $this->checkPassword($value, $passwordConfig, $transferStart);
        if ($passwordError !== null) {
            return $passwordError;
        }

        $existing = $this->findBySourceUrl($value);
        if ($existing !== null) {
            DiagnosticLogService::record('transfer', 'local_hit', 'success', '命中已转存临时资源', [
                'keyword' => $value['title'],
                'url' => $value['url'],
                'duration_ms' => intval((microtime(true) - $transferStart) * 1000),
            ]);
            return jok('临时资源获取成功', $existing);
        }

        $savedUrl = $this->findBySavedUrl($value);
        if ($savedUrl !== null) {
            DiagnosticLogService::record('transfer', 'saved_url_hit', 'success', '命中已转存分享链接', [
                'keyword' => $value['title'],
                'url' => isset($savedUrl['_source_url']) ? $savedUrl['_source_url'] : $value['url'],
                'duration_ms' => intval((microtime(true) - $transferStart) * 1000),
            ]);
            unset($savedUrl['_source_url']);
            return jok('临时资源获取成功', $savedUrl);
        }

        $keys = $value['url'] . 'ACAA';
        if (Cache::has($keys)) {
            DiagnosticLogService::record('transfer', 'short_cache_hit', 'success', '命中短期转存结果缓存', [
                'keyword' => $value['title'],
                'url' => $value['url'],
                'duration_ms' => intval((microtime(true) - $transferStart) * 1000),
            ]);
            return jok('临时资源获取成功', $this->panTreeService->appendKeyForClient(Cache::get($keys), $value['url'], '', $value['stoken']));
        }

        if (Cache::has($keys . '_processing')) {
            $startTime = time();
            while (Cache::has($keys . '_processing')) {
                usleep(100000);

                if (time() - $startTime > 10) {
                    DiagnosticLogService::record('transfer', 'wait_timeout', 'warn', '等待同链接转存超时', [
                        'keyword' => $value['title'],
                        'url' => $value['url'],
                        'duration_ms' => intval((microtime(true) - $transferStart) * 1000),
                    ]);
                    return jok('临时资源获取成功', []);
                }
            }
            DiagnosticLogService::record('transfer', 'wait_hit', 'success', '等待后命中同链接转存结果', [
                'keyword' => $value['title'],
                'url' => $value['url'],
                'duration_ms' => intval((microtime(true) - $transferStart) * 1000),
            ]);
            return jok('临时资源获取成功', $this->panTreeService->appendKeyForClient(Cache::get($keys), $value['url'], '', $value['stoken']));
        }

        Cache::set($keys . '_processing', true, 60);

        $datas = [];
        $numSuccess = 0;
        try {
            $res = $this->transferProcessService->processUrl($value, $numSuccess, $datas, true);
        } finally {
            Cache::delete($keys . '_processing');
        }

        if ($res['code'] !== 200) {
            DiagnosticLogService::record('transfer', 'transfer_error', 'error', '转存失败：' . $res['message'], [
                'keyword' => $value['title'],
                'url' => $value['url'],
                'duration_ms' => intval((microtime(true) - $transferStart) * 1000),
            ]);
            return jerr($res['message']);
        }

        $result = [
            'title' => $res['data']['title'],
            'url' => $res['data']['url'],
            'is_type' => isset($res['data']['is_type']) ? $res['data']['is_type'] : determineIsType($res['data']['url']),
        ];
        Cache::set($keys, $result, 60);
        DiagnosticLogService::record('transfer', 'transfer_success', 'success', '转存成功', [
            'keyword' => $value['title'],
            'url' => $value['url'],
            'is_type' => $result['is_type'],
            'duration_ms' => intval((microtime(true) - $transferStart) * 1000),
        ]);

        return jok('临时资源获取成功', $this->panTreeService->appendKeyForClient($result, $value['url'], '', $value['stoken']));
    }

    private function normalizeValue(array $value)
    {
        $value = array_merge([
            'title' => '',
            'url' => '',
            'stoken' => '',
            'password' => '',
        ], $value);

        $value['url'] = urldecode((string)$value['url']);
        if (!empty($value['url']) && strpos($value['url'], 'http') !== 0) {
            $decryptedUrl = decryptObject($value['url']);
            if (is_string($decryptedUrl) && $decryptedUrl !== '') {
                $value['url'] = $decryptedUrl;
            }
        }

        return $value;
    }

    private function checkPassword(array $value, array $config, $transferStart)
    {
        $passwordEnable = isset($config['enable']) ? $config['enable'] : '0';
        if ($passwordEnable != '1') {
            return null;
        }

        $resourcePassword = isset($config['password']) ? $config['password'] : '';
        $passwordError = isset($config['error_message']) ? $config['error_message'] : '密码错误，请重新输入';
        $passwordExpire = isset($config['expire']) ? $config['expire'] : 24;
        $timeKey = date('Y-m-d-H', time() - ($passwordExpire * 3600));
        $expectedHash = md5($resourcePassword . $timeKey);
        $cookieName = 'resource_password_verified';
        $cookieValue = cookie($cookieName);

        if (empty($cookieValue) || $cookieValue !== $expectedHash) {
            if (empty($value['password']) || $value['password'] !== $resourcePassword) {
                DiagnosticLogService::record('transfer', 'password_required', 'warn', '资源密码验证未通过', [
                    'keyword' => $value['title'],
                    'url' => $value['url'],
                    'duration_ms' => intval((microtime(true) - $transferStart) * 1000),
                ]);
                return jerr($passwordError, 401);
            }

            $cookieExpire = time() + ($passwordExpire * 3600);
            cookie($cookieName, $expectedHash, $cookieExpire);
        }

        return null;
    }

    private function findBySourceUrl(array $value)
    {
        $map = [];
        $map[] = ['status', '=', 1];
        $map[] = ['is_delete', '=', 0];
        $map[] = ['is_time', '=', 1];
        $map[] = ['content', '=', $value['url']];

        $url = $this->sourceModel->where($map)->field('source_id as id, title, url')->find();
        if (empty($url)) {
            return null;
        }

        $this->sourceModel->where('source_id', $url['id'])->update(['update_time' => time()]);
        $sourceUrl = $this->sourceModel->where('source_id', $url['id'])->value('content');
        unset($url['id']);
        $url = $url->toArray();

        return $this->panTreeService->appendKeyForClient($url, $sourceUrl ?: $value['url'], '', $value['stoken']);
    }

    private function findBySavedUrl(array $value)
    {
        $savedUrl = $this->sourceModel
            ->where('status', 1)
            ->where('is_delete', 0)
            ->where('is_time', 1)
            ->where('url', $value['url'])
            ->field('source_id as id, title, url, content')
            ->find();

        if (empty($savedUrl)) {
            return null;
        }

        $this->sourceModel->where('source_id', $savedUrl['id'])->update(['update_time' => time()]);
        $savedUrl = $savedUrl->toArray();
        $sourceUrl = isset($savedUrl['content']) ? $savedUrl['content'] : $value['url'];
        unset($savedUrl['id']);
        $result = $this->panTreeService->appendKeyForClient($savedUrl, $sourceUrl, '', $value['stoken']);
        $result['_source_url'] = $sourceUrl;

        return $result;
    }
}
