<?php

namespace app\service;

use app\model\InvalidResource as InvalidResourceModel;
use think\facade\Log;

class SearchResourceValidator
{
    private $invalidResourceModel;

    public function __construct(InvalidResourceModel $invalidResourceModel = null)
    {
        $this->invalidResourceModel = $invalidResourceModel ?: new InvalidResourceModel();
    }

    public function isInvalid($url)
    {
        return $this->invalidResourceModel->isInvalid($url);
    }

    public function validate(&$item)
    {
        $isType = intval($item['is_type'] ?? -1);

        if (config('qfshop.is_quan_zc') == 1 && $isType == 0) {
            if ($this->invalidResourceModel->isInvalid($item['url'])) {
                return false;
            }

            $this->sendHeartbeat();
            $infoData = $this->verificationUrl($item['url']);
            if ($infoData === 0) {
                $this->invalidResourceModel->recordInvalid($item['url'], $isType, '资源已失效', 7);
                return false;
            }

            if (!empty($infoData['stoken'])) {
                $item['stoken'] = $infoData['stoken'];
            }
            return true;
        }

        if (config('qfshop.other_pan_check_enable') == 1 && in_array($isType, [1, 2, 3, 4])) {
            $wasInvalid = $this->invalidResourceModel->isInvalid($item['url']);
            $this->sendHeartbeat();

            $checkMode = config('qfshop.other_pan_check_mode');
            if ($checkMode === 'pancheck') {
                $isValid = $this->checkUrlByPanCheckApi($item['url'], $isType);
            } elseif ($checkMode === 'local') {
                $isValid = $this->checkUrlByLocal($item['url']);
            } else {
                $isValid = $this->checkUrlByExternalApi($item['url'], $isType);
            }

            if (!$isValid) {
                $this->invalidResourceModel->recordInvalid($item['url'], $isType, '第三方API检测失效', 7);
                return false;
            }
            if ($wasInvalid) {
                $this->invalidResourceModel->removeInvalid($item['url']);
            }
            return true;
        }

        return true;
    }

    private function sendHeartbeat()
    {
        echo ": checking\n\n";
        ob_flush();
        flush();
    }

    private function verificationUrl($url)
    {
        $code = '';
        if (preg_match('/\?pwd=([^,\s&]+)/', $url, $pwdMatch)) {
            $code = trim($pwdMatch[1]);
        }
        $urlData = [
            'url' => $url,
            'code' => $code,
            'isType' => 1
        ];

        $transfer = new \netdisk\Transfer();
        $res = $transfer->transfer($urlData);

        if ($res['code'] !== 200) {
            return 0;
        }

        return $res['data'];
    }

    private function checkUrlByPanCheckApi($url, $isType)
    {
        $apiUrl = config('qfshop.other_pan_check_api');
        if (empty($apiUrl)) {
            return true;
        }

        $platformMap = [
            0 => 'quark',
            1 => 'aliyun',
            2 => 'baidu',
            3 => 'uc',
            4 => 'xunlei'
        ];

        $platform = $platformMap[$isType] ?? '';
        if ($platform === '') {
            return true;
        }

        $targetUrl = $this->normalizePanLink($url);
        $postData = [
            'links' => [$targetUrl],
            'selectedPlatforms' => [$platform],
            'selected_platforms' => [$platform]
        ];

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode == 200 && !empty($response)) {
                $result = json_decode($response, true);
                $validLinks = $this->normalizePanLinkList($result['valid_links'] ?? ($result['validLinks'] ?? []));
                $invalidLinks = $this->normalizePanLinkList($result['invalid_links'] ?? ($result['invalidLinks'] ?? []));
                if (in_array($targetUrl, $validLinks, true)) {
                    return true;
                }
                if (in_array($targetUrl, $invalidLinks, true)) {
                    return false;
                }
            }
        } catch (\Exception $e) {
            Log::error('[PanCheck] 检测失败: ' . $e->getMessage());
        }

        return true;
    }

    private function checkUrlByLocal($url)
    {
        try {
            $service = new NetdiskCheckService();
            $result = $service->check($url);
            if (isset($result['validity']) && $result['validity'] == -1) {
                return false;
            }
            return true;
        } catch (\Exception $e) {
            Log::error('[LocalCheck] 检测异常: ' . $e->getMessage());
            return true;
        }
    }

    private function checkUrlByExternalApi($url, $isType)
    {
        $apiUrl = config('qfshop.other_pan_check_api');
        if (empty($apiUrl)) {
            return true;
        }

        $requestUrl = $apiUrl . '?url=' . urlencode($url);
        try {
            $result = curlHelper($requestUrl, 'GET', null, [], '', '', 5);
            if (empty($result['body'])) {
                return true;
            }

            $response = json_decode($result['body'], true);
            if (!isset($response['validity'])) {
                return true;
            }

            return $response['validity'] != -1;
        } catch (\Exception $e) {
            return true;
        }
    }

    private function normalizePanLink($url)
    {
        return trim((string)$url, " \t\n\r\0\x0B`'\"");
    }

    private function normalizePanLinkList($links)
    {
        $result = [];
        if (!is_array($links)) {
            return $result;
        }
        foreach ($links as $item) {
            $normalized = $this->normalizePanLink($item);
            if ($normalized !== '') {
                $result[] = $normalized;
            }
        }
        return $result;
    }
}
