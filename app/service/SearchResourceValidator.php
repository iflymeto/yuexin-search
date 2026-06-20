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
        $skipDeepCheck = !empty($item['skip_deep_check']);

        if (config('qfshop.is_quan_zc') == 1 && $isType == 0) {
            if ($this->invalidResourceModel->isInvalid($item['url'])) {
                return false;
            }
            if ($skipDeepCheck) {
                return true;
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
        $pwdId = $this->extractQuarkPwdId($url);
        if ($pwdId === '') {
            return 0;
        }

        $tokenData = $this->requestQuarkJson(
            'https://drive-pc.quark.cn/1/clouddrive/share/sharepage/token',
            'POST',
            [
                'passcode' => $this->extractQuarkPasscode($url),
                'pwd_id' => $pwdId,
                'support_visit_limit_private_share' => true,
            ],
            [
                'pr' => 'ucpro',
                'fr' => 'pc',
                'uc_param_str' => '',
            ]
        );

        if (!$this->isQuarkApiOk($tokenData) || empty($tokenData['data']['stoken'])) {
            return 0;
        }

        $stoken = str_replace(' ', '+', $tokenData['data']['stoken']);
        $detailData = $this->requestQuarkJson(
            'https://drive-pc.quark.cn/1/clouddrive/share/sharepage/detail',
            'GET',
            [],
            [
                'pr' => 'ucpro',
                'fr' => 'pc',
                'uc_param_str' => '',
                'pwd_id' => $pwdId,
                'stoken' => $stoken,
                'pdir_fid' => '0',
                'force' => '0',
                '_page' => '1',
                '_size' => '100',
                '_fetch_banner' => '1',
                '_fetch_share' => '1',
                '_fetch_total' => '1',
                '_sort' => 'file_type:asc,updated_at:desc',
            ]
        );

        if (!$this->isQuarkApiOk($detailData) || !$this->isQuarkShareUsable($detailData)) {
            return 0;
        }

        return [
            'title' => $tokenData['data']['title'] ?? ($detailData['data']['share']['title'] ?? ''),
            'share_url' => $url,
            'stoken' => $stoken,
        ];
    }

    private function extractQuarkPwdId($url)
    {
        $url = trim((string)$url, " \t\n\r\0\x0B`'\"");
        if (preg_match('~pan\.quark\.cn/s/([^/?#\s]+)~i', $url, $match)) {
            return trim($match[1]);
        }
        if (preg_match('~(?:^|[?&])pwd_id=([^&#\s]+)~i', $url, $match)) {
            return trim(urldecode($match[1]));
        }
        return '';
    }

    private function extractQuarkPasscode($url)
    {
        if (preg_match('/[?&]pwd=([^,\s&#]+)/', $url, $pwdMatch)) {
            return trim(urldecode($pwdMatch[1]));
        }
        if (preg_match('/[?&]passcode=([^,\s&#]+)/', $url, $pwdMatch)) {
            return trim(urldecode($pwdMatch[1]));
        }
        return '';
    }

    private function requestQuarkJson($url, $method, array $data = [], array $queryParams = [])
    {
        $headers = [
            'Accept: application/json, text/plain, */*',
            'Accept-Language: zh-CN,zh;q=0.9',
            'Content-Type: application/json;charset=UTF-8',
            'Origin: https://pan.quark.cn',
            'Referer: https://pan.quark.cn/',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ];

        try {
            $result = curlHelper($url, $method, empty($data) ? null : json_encode($data), $headers, $queryParams, '', 10);
            if (!empty($result['error']) || empty($result['body'])) {
                Log::warning('[QuarkCheck] 请求失败: ' . $url . ' ' . ($result['error'] ?? 'empty body'));
                return null;
            }

            $json = json_decode($result['body'], true);
            if (!is_array($json)) {
                Log::warning('[QuarkCheck] 响应不是JSON: ' . $url);
                return null;
            }
            return $json;
        } catch (\Exception $e) {
            Log::error('[QuarkCheck] 请求异常: ' . $e->getMessage());
            return null;
        }
    }

    private function isQuarkApiOk($response)
    {
        if (!is_array($response)) {
            return false;
        }
        if (isset($response['status']) && intval($response['status']) !== 200) {
            return false;
        }
        if (isset($response['code']) && intval($response['code']) !== 0) {
            return false;
        }
        $message = strtolower((string)($response['message'] ?? ''));
        if ($message !== '' && strpos($message, 'ok') === false) {
            return false;
        }
        return true;
    }

    private function isQuarkShareUsable(array $detailData)
    {
        $data = $detailData['data'] ?? [];
        $share = $data['share'] ?? [];
        $status = intval($share['status'] ?? 0);
        $auditStatus = intval($share['audit_status'] ?? ($data['audit_status'] ?? 0));
        $partialViolation = !empty($share['partial_violation']);
        $list = $data['list'] ?? [];
        $fileTotal = intval($share['all_file_num'] ?? ($share['file_num'] ?? ($detailData['metadata']['_total'] ?? 0)));
        $listCount = is_array($list) ? count($list) : 0;

        if ($status !== 1) {
            return false;
        }
        if ($auditStatus > 0 && $auditStatus !== 1) {
            return false;
        }
        if ($partialViolation && $listCount === 0) {
            return false;
        }
        if ($listCount === 0 && $fileTotal <= 0) {
            return false;
        }

        return true;
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
