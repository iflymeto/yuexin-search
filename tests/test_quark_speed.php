<?php
// 加载 Composer
require __DIR__ . '/../vendor/autoload.php';

use think\App;
use think\facade\Config;
use netdisk\pan\QuarkPan;

// 初始化应用
$app = new App();
$app->initialize();

// 设置测试 URL
$testUrl = "https://pan.quark.cn/s/a011cd3dc393";
echo "测试 URL: " . $testUrl . "\n";

// 解析 pwd_id
$pwd_id = '';
if (preg_match('/s\/([a-zA-Z0-9]+)/', $testUrl, $matches)) {
    $pwd_id = $matches[1];
    echo "pwd_id: " . $pwd_id . "\n\n";
} else {
    die("无法解析 pwd_id\n");
}

// ---------------------------------------------------------
// 测试 1: 本地逻辑 (QuarkPan)
// ---------------------------------------------------------
echo ">>> 开始测试: 本地逻辑 (QuarkPan)\n";
$start1 = microtime(true);

try {
    // 实例化 QuarkPan
    $pan = new QuarkPan();
    
    // 模拟 verificationUrl 中的逻辑
    // 核心是调用 getStoken
    // 注意：这需要配置有效的 quark_cookie，否则会失败
    // 为了防止因 cookie 为空导致报错，我们检查一下配置
    $cookie = Config::get('netdisk.quark_cookie');
    if (empty($cookie)) {
        echo "警告: netdisk.quark_cookie 未配置，本地逻辑可能会失败\n";
    }

    $res = $pan->getStoken($pwd_id);
    
    $end1 = microtime(true);
    $time1 = round(($end1 - $start1) * 1000, 2);
    
    echo "耗时: " . $time1 . " ms\n";
    echo "结果: " . json_encode($res, JSON_UNESCAPED_UNICODE) . "\n";
    
    if (isset($res['status']) && $res['status'] === 200) {
        echo "判定: 有效\n";
    } else {
        echo "判定: 失效 (或 Cookie 无效)\n";
    }

} catch (\Exception $e) {
    echo "异常: " . $e->getMessage() . "\n";
}

echo "\n---------------------------------------------------------\n\n";

// ---------------------------------------------------------
// 测试 2: jc.php 逻辑 (完全模拟 check_quark)
// ---------------------------------------------------------
echo ">>> 开始测试: jc.php 逻辑 (无 Cookie)\n";
$start2 = microtime(true);

function http_post_test($url, $data, $headers = []) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => array_merge([
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Content-Type: application/json'
        ], $headers)
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

function http_get_test($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ]
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

// jc.php 逻辑实现
// 步骤1：获取token
$tokenResp = http_post_test(
    "https://drive-h.quark.cn/1/clouddrive/share/sharepage/token?pr=ucpro&fr=pc",
    json_encode(['pwd_id' => $pwd_id, 'passcode' => '']),
    ['Content-Type: application/json']
);

$jc_status = 0;
$jc_msg = "";

if (!$tokenResp) {
    $jc_status = 0;
    $jc_msg = "Token请求失败";
} else {
    $tokenData = json_decode($tokenResp, true);
    
    if (strpos($tokenData['message'] ?? '', '需要提取码') !== false) {
        $jc_status = 2;
        $jc_msg = "需要密码";
    } elseif (strpos($tokenData['message'] ?? '', 'ok') === false) {
        $jc_status = -1;
        $jc_msg = "失效 (Token阶段)";
    } else {
        // 步骤2：获取详情
        $token = urlencode($tokenData['data']['stoken']);
        $detailResp = http_get_test("https://drive-h.quark.cn/1/clouddrive/share/sharepage/detail?pwd_id={$pwd_id}&stoken={$token}&_fetch_share=1");
        
        if (!$detailResp) {
            $jc_status = 0;
            $jc_msg = "详情请求失败";
        } else {
            $detailData = json_decode($detailResp, true);
            $status = $detailData['data']['share']['status'] ?? 0;
            $partial = $detailData['data']['share']['partial_violation'] ?? false;
            
            if ($status == 1) {
                $jc_status = $partial ? 11 : 1;
                $jc_msg = $partial ? "部分违规" : "有效";
            } elseif ($status == 3) {
                $jc_status = $partial ? -1 : 1; // jc.php逻辑: if ($status == 3) return $partial ? -1 : 1;
                $jc_msg = "状态3";
            } elseif ($status > 1) {
                $jc_status = -1;
                $jc_msg = "失效 (状态码: $status)";
            } else {
                $jc_status = 0;
                $jc_msg = "未知状态";
            }
        }
    }
}

$end2 = microtime(true);
$time2 = round(($end2 - $start2) * 1000, 2);

echo "耗时: " . $time2 . " ms\n";
echo "结果: 状态码=$jc_status, 消息=$jc_msg\n";
