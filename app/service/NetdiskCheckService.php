<?php

namespace app\service;

use think\facade\Log;

class NetdiskCheckService
{
    /**
     * HTTP GET 请求
     */
    private function http_get($url, $headers = []) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => array_merge([
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            ], $headers)
        ]);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        return $error ? null : $response;
    }

    /**
     * HTTP POST 请求
     */
    private function http_post($url, $data, $headers = []) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => array_merge([
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            ], $headers)
        ]);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        return $error ? null : $response;
    }

    /**
     * 百度网盘检测
     */
    private function check_baidu($shareId) {
        $html = $this->http_get("https://pan.baidu.com/s/{$shareId}");
        if (!$html) return 0;
        
        if (strpos($html, '过期时间：') !== false) return 1;
        if (strpos($html, '输入提取') !== false) return 2;
        if (strpos($html, '不存在') !== false || strpos($html, '已失效') !== false) return -1;
        
        return 0;
    }

    /**
     * 阿里云盘检测
     */
    private function check_aliyun($shareId) {
        $response = $this->http_post(
            "https://api.aliyundrive.com/adrive/v3/share_link/get_share_by_anonymous",
            json_encode(['share_id' => $shareId]),
            ['Content-Type: application/json']
        );
        
        if (!$response) return 0;
        
        $data = json_decode($response, true);
        if (isset($data['code']) && strpos($data['code'], 'ShareLink') !== false) return -1;
        if (isset($data['file_count'])) return 1;
        
        return 0;
    }

    /**
     * 腾讯微云检测
     */
    private function check_weiyun($shareId) {
        $html = $this->http_get("https://share.weiyun.com/{$shareId}");
        if (!$html) return 0;
        
        if (strpos($html, '已删除') !== false || 
            strpos($html, '违反相关法规') !== false || 
            strpos($html, '已过期') !== false ||
            strpos($html, '已经删除') !== false ||
            strpos($html, '目录无效') !== false) return -1;
        
        if (strpos($html, '"need_pwd":null') !== false && strpos($html, '"pwd":""') !== false) return 1;
        if (strpos($html, '"need_pwd":1') !== false || strpos($html, '"pwd":"') !== false) return 2;
        
        return 0;
    }

    /**
     * 蓝奏云检测
     */
    private function check_lanzou($shareId) {
        $html = $this->http_get("https://leon.lanzoue.com/{$shareId}");
        if (!$html) return 0;
        
        if (strpos($html, '输入密码') !== false) return 2;
        if (strpos($html, '来晚啦') !== false || 
            strpos($html, '不存在') !== false || 
            strpos($html, '链接失效') !== false) return -1;
        
        return 1;
    }

    /**
     * 123网盘检测
     */
    private function check_pan123($shareId) {
        $response = $this->http_get("https://www.123pan.com/api/share/info?shareKey={$shareId}");
        if (!$response) return 0;
        
        $data = json_decode($response, true);
        if (!isset($data['code'])) return 0;
        
        if ($data['code'] != 0) return -1;
        if (isset($data['data']['HasPwd']) && $data['data']['HasPwd']) return 2;
        
        return 1;
    }

    /**
     * 天翼云盘检测
     */
    private function check_ty189($shareId) {
        $response = $this->http_post(
            "https://api.cloud.189.cn/open/share/getShareInfoByCodeV2.action",
            http_build_query(['shareCode' => $shareId])
        );
        
        if (!$response) return 0;
        
        if (strpos($response, 'ShareInfoNotFound') !== false || 
            strpos($response, 'ShareNotFound') !== false ||
            strpos($response, 'FileNotFound') !== false ||
            strpos($response, 'ShareExpiredError') !== false ||
            strpos($response, 'ShareAuditNotPass') !== false) return -1;
        
        if (strpos($response, 'needAccessCode') !== false) return 2;
        
        return 1;
    }

    /**
     * 夸克网盘检测（两步验证）
     */
    private function check_quark($shareId) {
        // 步骤1：获取token
        $tokenResp = $this->http_post(
            "https://drive-h.quark.cn/1/clouddrive/share/sharepage/token?pr=ucpro&fr=pc",
            json_encode(['pwd_id' => $shareId, 'passcode' => '']),
            ['Content-Type: application/json']
        );
        
        if (!$tokenResp) return 0;
        
        $tokenData = json_decode($tokenResp, true);
        
        if (strpos($tokenData['message'] ?? '', '需要提取码') !== false) return 2;
        if (strpos($tokenData['message'] ?? '', 'ok') === false) return -1;
        
        // 步骤2：获取详情
        $token = urlencode($tokenData['data']['stoken']);
        $detailResp = $this->http_get("https://drive-h.quark.cn/1/clouddrive/share/sharepage/detail?pwd_id={$shareId}&stoken={$token}&_fetch_share=1");
        
        if (!$detailResp) return 0;
        
        $detailData = json_decode($detailResp, true);
        $status = $detailData['data']['share']['status'] ?? 0;
        $partial = $detailData['data']['share']['partial_violation'] ?? false;
        
        if ($status == 1) return $partial ? 11 : 1;
        if ($status == 3) return $partial ? -1 : 1;
        if ($status > 1) return -1;
        
        return 0;
    }

    /**
     * 迅雷云盘检测（两步验证）
     */
    private function check_xunlei($shareId) {
        // 步骤1：获取captcha token
        $tokenResp = $this->http_post(
            "https://xluser-ssl.xunlei.com/v1/shield/captcha/init",
            json_encode([
                'client_id' => 'Xqp0kJBXWhwaTpB6',
                'device_id' => '925b7631473a13716b791d7f28289cad',
                'action' => 'get:/drive/v1/share',
                'meta' => [
                    'package_name' => 'pan.xunlei.com',
                    'client_version' => '1.45.0',
                    'captcha_sign' => '1.fe2108ad808a74c9ac0243309242726c',
                    'timestamp' => '1645241033384'
                ]
            ]),
            ['Content-Type: application/json']
        );
        
        if (!$tokenResp) return 0;
        
        $tokenData = json_decode($tokenResp, true);
        $captchaToken = $tokenData['captcha_token'] ?? '';
        
        if (!$captchaToken) return 0;
        
        // 步骤2：检测分享
        $detailResp = $this->http_get(
            "https://api-pan.xunlei.com/drive/v1/share?share_id={$shareId}",
            [
                "x-captcha-token: {$captchaToken}",
                "x-client-id: Xqp0kJBXWhwaTpB6",
                "x-device-id: 925b7631473a13716b791d7f28289cad"
            ]
        );
        
        if (!$detailResp) return 0;
        
        if (strpos($detailResp, 'NOT_FOUND') !== false || 
            strpos($detailResp, 'SENSITIVE_RESOURCE') !== false || 
            strpos($detailResp, 'EXPIRED') !== false) return -1;
        
        if (strpos($detailResp, 'PASS_CODE_EMPTY') !== false) return 2;
        
        return 1;
    }

    /**
     * 奶牛网盘检测
     */
    private function check_nainiu($shareId) {
        $response = $this->http_get("https://cowtransfer.com/core/api/transfer/share?uniqueUrl={$shareId}");
        if (!$response) return 0;
        
        $data = json_decode($response, true);
        
        if (($data['code'] ?? '') != '0000') return -1;
        if ($data['data']['needPassword'] ?? false) return 2;
        
        return 1;
    }

    /**
     * 文叔叔检测
     */
    private function check_wenshushu($shareId) {
        $response = $this->http_post(
            "https://www.wenshushu.cn/ap/task/mgrtask",
            json_encode(['tid' => $shareId]),
            ['Content-Type: application/json', 'x-token: wss:7pmakczzw6i']
        );
        
        if (!$response) return 0;
        
        $data = json_decode($response, true);
        return ($data['code'] ?? -1) == 0 ? 1 : -1;
    }

    /**
     * 115网盘检测
     */
    private function check_pan115($shareId) {
        $response = $this->http_get("https://115cdn.com/webapi/share/snap?share_code={$shareId}&receive_code=");
        if (!$response) return 0;
        
        $data = json_decode($response, true);
        
        if ($data['state'] ?? false) return 1;
        
        if (isset($data['error'])) {
            if (strpos($data['error'], '访问码') !== false) return 2;
            if (strpos($data['error'], '不存在或已被删除') !== false || 
                strpos($data['error'], '分享已取消') !== false) return -1;
        }
        
        return 0;
    }

    /**
     * UC网盘检测
     */
    private function check_uc($shareId) {
        $timestamp = round(microtime(true) * 1000);
        $apiUrl = "https://pc-api.uc.cn/1/clouddrive/share/sharepage/detail";
        
        $params = [
            'pr' => 'UCBrowser',
            'fr' => 'h5',
            '_t_group' => '0%3A_s_vp%3A1',
            '__t' => $timestamp,
            'pwd_id' => $shareId,
            'stoken' => 'oQ/wbxAp1F1zaIpxVoK4ui3IEgqHJNEF2U0wY83oCm4=', // 需要定期更新
            'pdir_fid' => '6c58ceaf70094e83a1e29277ad3a8133',
            'force' => 0,
            '_page' => 1,
            '_size' => 50,
            '_fetch_banner' => 0,
            '_fetch_share' => 0,
            '_fetch_total' => 1,
            '_sort' => 'file_type%3Aasc%2Cfile_name%3Aasc'
        ];
        
        $fullUrl = $apiUrl . '?' . http_build_query($params);
        
        $response = $this->http_get($fullUrl, [
            'Referer: https://drive.uc.cn/'
        ]);
        
        if (!$response) return 0;
        
        $data = json_decode($response, true);
        if (!$data) return 0;
        
        if (isset($data['code']) && $data['code'] == 0) {
            if (isset($data['data']['list']) && count($data['data']['list']) > 0) {
                return 1; 
            }
            if (isset($data['data'])) return 1;
        }
        
        if (isset($data['message'])) {
            $message = $data['message'];
            if (strpos($message, '不存在') !== false || 
                strpos($message, '已删除') !== false ||
                strpos($message, '失效') !== false ||
                strpos($message, '过期') !== false ||
                strpos($message, '分享已取消') !== false) {
                return -1;
            }
            
            if (strpos($message, '密码') !== false || 
                strpos($message, '提取码') !== false ||
                strpos($message, 'password') !== false) {
                return 2;
            }
        }
        
        if (isset($data['code']) && $data['code'] != 0) return -1;
        if (isset($data['status']) && $data['status'] != 200) return -1;
        
        return 0;
    }

    /**
     * 核心检测入口
     */
    public function check($url) {
        $startTime = microtime(true);
        
        // 网盘正则规则
        $patterns = [
            'baidu' => [
                '/(?:pan|yun)\.baidu\.com\/s\/([\w\-]{5,})/',
                '/(?:pan|yun)\.baidu\.com\/(?:share|wap)\/init\?surl=([\w\-]{5,})/'
            ],
            'aliyun' => [
                '/aliyundrive\.com\/s\/([\w\-]{8,})/',
                '/alipan\.com\/s\/([\w\-]{8,})/',
                '/aliyundrive\.com\/t\/([\w\-]{8,})/',
                '/alipan\.com\/t\/([\w\-]{8,})/'
            ],
            'weiyun' => ['/share\.weiyun\.com\/([\w\-]{7,})/'],
            'lanzou' => ['/lanzou.?\.com\/([\w\-]{7,})/'],
            'pan123' => ['/(?:123pan|123865|123684|123912)\.com\/s\/([\w\-]{8,})/'],
            'ty189' => ['/cloud\.189\.cn\/(?:t\/|web\/share\?code=)([\w\-]{8,})/'],
            'quark' => ['/pan\.quark\.cn\/s\/([\w\-]{8,})/'],
            'xunlei' => ['/pan\.xunlei\.com\/s\/([\w\-]{25,})/'],
            'nainiu' => ['/cowtransfer\.com\/s\/([\w\-]{10,})/'],
            'wenshushu' => ['/(?:t\.wss\.ink|wss1\.cn)\/f\/([\w\-]{8,})/'],
            'pan115' => ['/(?:115|anxia|115cdn)\.com\/s\/([\w\-]{8,})/'],
            'uc' => [
                '/drive\.uc\.cn\/s\/([\w\-]+)/',
                '/uc\.cn\/s\/([\w\-]+)/'
            ]
        ];
        
        // 遍历所有规则尝试匹配
        foreach ($patterns as $type => $pattern_list) {
            foreach ($pattern_list as $pattern) {
                if (preg_match($pattern, $url, $matches)) {
                    $shareId = $matches[1];
                    
                    $checkFunc = "check_{$type}";
                    if (!method_exists($this, $checkFunc)) {
                        continue;
                    }
                    
                    $state = $this->$checkFunc($shareId);
                    
                    // 状态映射
                    $state_map = [
                        -1 => ['code' => -1, 'text' => '失效', 'message' => '分享链接已失效'],
                        0 => ['code' => 0, 'text' => '检测失败', 'message' => '网络错误或无法判断状态'],
                        1 => ['code' => 1, 'text' => '有效', 'message' => '分享链接有效'],
                        2 => ['code' => 2, 'text' => '需要密码', 'message' => '需要提取码/访问码'],
                        11 => ['code' => 11, 'text' => '部分违规', 'message' => '部分文件违规但可访问（夸克）']
                    ];
                    
                    $state_info = $state_map[$state] ?? $state_map[0];
                    
                    $endTime = microtime(true);
                    $duration = round(($endTime - $startTime) * 1000, 2);
                    
                    return [
                        'status' => 'success',
                        'platform_code' => $type,
                        'validity' => $state_info['code'],
                        'validity_text' => $state_info['text'],
                        'message' => $state_info['message'],
                        'duration_ms' => $duration
                    ];
                }
            }
        }
        
        return [
            'status' => 'error',
            'message' => '不支持的网盘链接格式',
            'validity' => -2
        ];
    }
}
