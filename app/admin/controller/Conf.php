<?php

namespace app\admin\controller;

use think\App;
use think\facade\Cache;
use app\admin\QfShop;
use app\model\Conf as ConfModel;
use app\service\FrontendThemeService;

class Conf extends QfShop
{
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->searchFilter = [
            "conf_id" => "=",
            "conf_key" => "like",
            "conf_value" => "like",
            "conf_title" => "like",
            "conf_status" => "=",
            "conf_type" => "=",
        ];
        $this->insertFields = [
            "conf_key", "conf_value", "conf_title", "conf_desc", "conf_status", "conf_type", "conf_spec", "conf_content", "conf_sort", "conf_system"
        ];
        $this->updateFields = [
            "conf_key", "conf_value", "conf_title", "conf_desc", "conf_status", "conf_type", "conf_spec", "conf_content", "conf_sort", "conf_system"
        ];
        $this->insertRequire = [
            'conf_title' => "参数名称必须填写",
            'conf_key' => "参数字段必须填写",
        ];
        $this->updateRequire = [
            'conf_title' => "参数名称必须填写",
            'conf_key' => "参数字段必须填写",
        ];
        $this->model = new ConfModel();
    }
    /**
     * 获取配置列表
     *
     * @return void
     */
    public function getList()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }
        $map = $this->getDataFilterFromRequest();
        $order = "conf_sort desc, conf_id asc";
        $this->setGetListPerPage();
        $dataList = $this->model->getListByPage($map, $order, $this->selectList);
        return jok('数据获取成功', $dataList);
    }
    /**
     * 读取基础配置
     *
     * @return void
     */
    public function getBaseConfig()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }
        $this->ensureCacheConfigEntries();

        $datalist = $this->model->where('conf_status', 1)->order("conf_sort desc " . $this->pk . " asc")->select();
        foreach ($datalist as $key => &$value) {
            if (in_array($value['conf_key'], ['fake_mode_enable', 'fake_quark_url', 'fake_baidu_url'], true)) {
                $value['conf_type'] = 4;
            }
            if ($value['conf_key'] === 'cache_driver') {
                $content = (string)$value['conf_content'];
                if (strpos($content, 'memcached') === false) {
                    $value['conf_content'] = trim($content . "\nMemcached缓存=>memcached", "\n");
                }
            }
            if ($value['conf_key'] === 'frontend_theme') {
                $value['conf_content'] = $this->buildFrontendThemeOptions();
                if (!$this->isFrontendThemeAvailable((string)$value['conf_value'])) {
                    $value['conf_value'] = 'news';
                }
            }
            if ($value['conf_content']) {
                $value['conf_content'] = explode("\n", $value['conf_content']);
            }
        }
        return jok('', $datalist);
    }

    private function buildFrontendThemeOptions()
    {
        return (new FrontendThemeService())->optionsText();
    }

    private function isFrontendThemeAvailable($themeKey)
    {
        return (new FrontendThemeService())->exists($themeKey);
    }

    private function ensureCacheConfigEntries()
    {
        $needItems = [
            'memcached_host' => ['127.0.0.1', 'Memcached服务地址', 'Memcached服务器IP地址，默认本机：127.0.0.1', 5],
            'memcached_port' => ['11211', 'Memcached端口', 'Memcached服务端口，默认：11211', 6],
            'memcached_username' => ['', 'Memcached用户名', 'SASL认证用户名，未启用可留空', 7],
            'memcached_password' => ['', 'Memcached密码', 'SASL认证密码，未启用可留空', 8],
            'fake_mode_enable' => ['0', '伪装模式开关', '开启后，前台资源链接会按网盘类型替换为下方配置的测试链接', 1006, 2, "关闭=>0\n开启=>1"],
            'fake_quark_url' => ['', '伪装夸克链接', '伪装模式开启后，夸克类型资源会展示此链接', 1007, 0, null],
            'fake_baidu_url' => ['', '伪装百度链接', '伪装模式开启后，百度类型资源会展示此链接', 1008, 0, null],
            'network_accel_mode' => ['off', '网盘API加速模式', '选择网盘API请求加速方式；relay 和代理二选一', 999, 2, "关闭=>off\n代理=>proxy\nRelay中转=>relay", 1],
            'relay_url' => ['', 'Relay中转地址', '部署在香港或国内直连机器上的 relay.php 地址', 1009, 0, null, 1],
            'relay_secret' => ['', 'Relay密钥', '主站与 relay.php 之间的签名密钥，必须与 relay.php 配置一致', 1010, 0, null, 1],
            'relay_timeout' => ['20', 'Relay超时时间', '通过 Relay 请求网盘API的超时时间，单位秒', 1011, 0, null, 1],
            'frontend_theme' => ['news', '前端主题', '选择前台首页、搜索页、详情页使用的主题；主题缺失时自动回退默认主题', 100, 3, "默认主题=>news\n简洁主题=>simple", 2],
        ];
        $exists = $this->model->whereIn('conf_key', array_keys($needItems))->column('conf_key');
        $insertRows = [];
        $now = time();
        foreach ($needItems as $key => $meta) {
            if (in_array($key, $exists, true)) {
                continue;
            }
            $insertRows[] = [
                'conf_key' => $key,
                'conf_value' => $meta[0],
                'conf_title' => $meta[1],
                'conf_desc' => $meta[2],
                'conf_status' => 1,
                'conf_type' => $meta[4] ?? 4,
                'conf_spec' => $meta[6] ?? 0,
                'conf_content' => $meta[5] ?? null,
                'conf_sort' => $meta[3],
                'conf_system' => 0,
                'conf_createtime' => $now,
                'conf_updatetime' => $now,
            ];
        }
        if (!empty($insertRows)) {
            $this->model->insertAll($insertRows);
            if (in_array('network_accel_mode', array_column($insertRows, 'conf_key'), true)) {
                $proxyEnabled = (string)$this->model->where('conf_key', 'proxy_enable')->value('conf_value');
                if ($proxyEnabled === '1') {
                    $this->model->where('conf_key', 'network_accel_mode')->update([
                        'conf_value' => 'proxy',
                        'conf_updatetime' => $now,
                    ]);
                }
            }
            $this->clearConfigCache();
        }
    }

    private function clearConfigCache()
    {
        Cache::delete('system_config_all');
        Cache::set('system_config_version', time(), 300);
    }

    public function updateBaseConfig()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }
        $now = time();
        foreach (input("post.") as $k => $v) {
            $map["conf_key"] = $k;
            $item = $this->model->where($map)->find();
            if (empty($item)) {
                continue;
            }
            if ($k === 'cache_driver') {
                $v = strtolower((string)$v);
                if (!in_array($v, ['file', 'redis', 'memcached'], true)) {
                    $v = 'file';
                }
            }
            if ($k === 'network_accel_mode') {
                $v = strtolower((string)$v);
                if (!in_array($v, ['off', 'proxy', 'relay'], true)) {
                    $v = 'off';
                }
            }
            if(is_array($v)){
                $v = implode(",",$v);
            }
            $this->model->where("conf_key", $k)->update([
                "conf_value" => $v,
                "conf_updatetime" => $now
            ]);
        }
        if (array_key_exists('network_accel_mode', input("post."))) {
            $mode = strtolower((string)input('network_accel_mode', 'off'));
            $this->model->where("conf_key", 'proxy_enable')->update([
                "conf_value" => $mode === 'proxy' ? '1' : '0',
                "conf_updatetime" => $now
            ]);
        }
        $this->clearConfigCache();
        return jok("配置修改成功");
    }

    /**
     * 添加配置
     *
     * @return void
     */
    public function add()
    {
        // 校验 Access 与 RBAC
        $error = $this->access();
        if ($error) {
            return $error;
        }
        // 校验新增字段
        $error = $this->validateInsertFields();
        if ($error) {
            return $error;
        }
        // 从请求中获取新增数据
        $data = $this->getInsertDataFromRequest();

        $res = $this->model->where('conf_key',$data['conf_key'])->find();
        if ($res) {
            return jerr("参数字段已存在");
        }
        
        // 添加配置行
        $data['conf_value'] = '';
        $this->insertRow($data);
        
        $this->clearConfigCache();
        return jok('添加成功');
    }

    /**
     * 修改配置
     *
     * @return void
     */
    public function update()
    {
        // 校验 Access 与 RBAC
        $error = $this->access();
        if ($error) {
            return $error;
        }
        if (!$this->pk_value) {
            return jerr($this->pk . "参数必须填写", 400);
        }
        $item = $this->getRowByPk();
        if (empty($item)) {
            return jerr("数据查询失败", 404);
        }
        // 校验更新字段
        $error  = $this->validateUpdateFields();
        if ($error) {
            return $error;
        }
        // 从请求中获取更新数据
        $data = $this->getUpdateDataFromRequest();

        $res = $this->model->where('conf_key',$data['conf_key'])->find();
        if ($res['conf_id']!= input("conf_id") && $res) {
            return jerr("参数字段已存在");
        }
        // 根据主键更新配置行
        $this->updateByPk($data);
        
        $this->clearConfigCache();
        return jok('修改成功');
    }

    /**
     * 删除配置
     *
     * @return void
     */
    public function delete()
    {
        // 校验 Access 与 RBAC
        $error = $this->access();
        if ($error) {
            return $error;
        }
        if (!$this->pk_value) {
            return jerr($this->pk . "必须填写", 400);
        }
        if (isInteger($this->pk_value)) {
            $item = $this->getRowByPk();
            if (empty($item)) {
                return jerr("数据查询失败", 404);
            }
            if($item['conf_system']==1){
                return jerr("系统参数，禁止删除", 404);
            }
            // 单个删除
            $this->deleteBySingle();
            
            $this->clearConfigCache();
        } else {
            return jerr("暂不支持批量删除", 400);
        }
        return jok('删除成功');
    }
    
    /**
     * 导出配置为 JSON
     *
     * @return void
     */
    public function exportConfig()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }
        
        // 定义需要导出的配置类型
        $exportTypes = ['0', '9', '3', '1', '2', '4', '11'];
        
        $configs = $this->model
            ->where('conf_status', 1)
            ->where('conf_type', 'in', $exportTypes)
            ->order("conf_sort desc, conf_id asc")
            ->select()
            ->toArray();
        
        // 构建导出数据结构
        $exportData = [
            'export_time' => date('Y-m-d H:i:s'),
            'export_version' => '1.0',
            'site_name' => config('qfshop.site_name', '未知站点'),
            'config_count' => count($configs),
            'configs' => []
        ];
        
        foreach ($configs as $config) {
            $exportData['configs'][] = [
                'conf_key' => $config['conf_key'],
                'conf_value' => $config['conf_value'],
                'conf_title' => $config['conf_title'],
                'conf_desc' => $config['conf_desc'],
                'conf_type' => $config['conf_type'],
                'conf_spec' => $config['conf_spec'],
                'conf_content' => $config['conf_content'],
                'conf_sort' => $config['conf_sort']
            ];
        }
        
        // 设置响应头，触发下载
        $filename = 'config_backup_' . date('YmdHis') . '.json';
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        
        echo json_encode($exportData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
    
    /**
     * 从 JSON 导入配置
     *
     * @return void
     */
    public function importConfig()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }
        
        // 获取上传的 JSON 内容
        $jsonContent = input('post.json_content', '');
        
        if (empty($jsonContent)) {
            return jerr('请上传有效的配置文件');
        }
        
        // 解析 JSON
        $importData = json_decode($jsonContent, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return jerr('JSON格式错误：' . json_last_error_msg());
        }
        
        // 验证数据结构
        if (!isset($importData['configs']) || !is_array($importData['configs'])) {
            return jerr('配置文件格式不正确');
        }
        
        $successCount = 0;
        $failCount = 0;
        $errors = [];
        
        foreach ($importData['configs'] as $config) {
            if (!isset($config['conf_key'])) {
                $failCount++;
                continue;
            }
            
            try {
                $existConfig = $this->model
                    ->where('conf_key', $config['conf_key'])
                    ->find();
                
                if ($existConfig) {
                    // 更新现有配置
                    $updateData = [
                        'conf_value' => $config['conf_value'] ?? '',
                        'conf_updatetime' => time(),
                    ];
                    
                    if (isset($config['conf_title'])) {
                        $updateData['conf_title'] = $config['conf_title'];
                    }
                    if (isset($config['conf_desc'])) {
                        $updateData['conf_desc'] = $config['conf_desc'];
                    }
                    
                    $this->model
                        ->where('conf_key', $config['conf_key'])
                        ->update($updateData);
                    
                    $successCount++;
                } else {
                    // 配置不存在，记录但不创建
                    $errors[] = "配置项 {$config['conf_key']} 不存在于当前系统";
                    $failCount++;
                }
            } catch (\Exception $e) {
                $errors[] = "导入 {$config['conf_key']} 失败: " . $e->getMessage();
                $failCount++;
            }
        }
        
        $this->clearConfigCache();
        
        // 返回导入结果
        $message = "导入完成，成功：{$successCount} 条，失败：{$failCount} 条";
        
        if (!empty($errors)) {
            return jok($message, [
                'success_count' => $successCount,
                'fail_count' => $failCount,
                ]);
        }
        
        return jok($message, [
            'success_count' => $successCount,
            'fail_count' => $failCount
        ]);
    }
    
    public function testProxy()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }

        $host = trim(input('host', ''));
        $port = intval(input('port', 0));
        $type = strtolower(trim(input('type', 'http')));
        $user = trim(input('user', ''));
        $pass = input('pass', '');

        if ($host === '' || $port <= 0) {
            return jerr('代理地址和端口不能为空');
        }

        $proxyType = CURLPROXY_HTTP;
        if ($type === 'socks5') {
            $proxyType = CURLPROXY_SOCKS5;
        } else if ($type === 'socks4') {
            $proxyType = CURLPROXY_SOCKS4;
        }

        $startTime = microtime(true);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://pan.baidu.com/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_PROXY, $host);
        curl_setopt($ch, CURLOPT_PROXYPORT, $port);
        curl_setopt($ch, CURLOPT_PROXYTYPE, $proxyType);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        if ($user !== '') {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $user . ':' . $pass);
        }

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $latency = round((microtime(true) - $startTime) * 1000, 2);
        if (!empty($curlError)) {
            return jerr('代理连接失败: ' . $curlError);
        }
        if ($httpCode < 200 || $httpCode >= 500) {
            return jerr('代理响应异常: HTTP ' . $httpCode);
        }

        return jok('代理连接成功', [
            'latency' => $latency,
            'http_code' => $httpCode,
            'target' => 'https://pan.baidu.com/',
            'proxy' => $host . ':' . $port,
            'type' => $type
        ]);
    }

    public function testProxyConfig()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }

        $dbConfigs = $this->model
            ->where('conf_key', 'in', ['proxy_enable', 'proxy_host', 'proxy_port', 'proxy_type', 'proxy_user', 'proxy_pass'])
            ->select()
            ->toArray();

        $configValues = [
            'proxy_enable' => config('qfshop.proxy_enable'),
            'proxy_host' => config('qfshop.proxy_host'),
            'proxy_port' => config('qfshop.proxy_port'),
            'proxy_type' => config('qfshop.proxy_type'),
            'proxy_user' => config('qfshop.proxy_user'),
            'proxy_pass' => config('qfshop.proxy_pass'),
        ];

        $proxyEnabled = config('qfshop.proxy_enable');
        $proxyHost = trim((string)config('qfshop.proxy_host'));
        $proxyPort = intval(config('qfshop.proxy_port'));
        $willEnable = ($proxyEnabled && $proxyHost !== '' && $proxyPort > 0);

        return jok('配置读取成功', [
            'database' => $dbConfigs,
            'config_values' => $configValues,
            'logic_test' => [
                'proxy_enabled' => $proxyEnabled,
                'proxy_host' => $proxyHost,
                'proxy_port' => $proxyPort,
                'proxy_enabled_bool' => (bool)$proxyEnabled,
                'proxy_host_empty' => $proxyHost === '',
                'proxy_port_invalid' => $proxyPort <= 0,
                'will_enable' => $willEnable,
                'diagnosis' => ($willEnable ? '代理会启用' : '代理不会启用')
            ]
        ]);
    }

    public function testRelay()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }

        $url = trim(input('url', ''));
        $secret = trim(input('secret', ''));
        $timeout = intval(input('timeout', 20));
        if ($timeout <= 0) {
            $timeout = 20;
        }

        if ($url === '' || $secret === '') {
            return jerr('Relay地址和密钥不能为空');
        }
        if (!preg_match('/^https?:\/\//i', $url)) {
            return jerr('Relay地址必须以 http:// 或 https:// 开头');
        }

        $healthStart = microtime(true);
        $health = $this->requestRelay($url, $secret, '__health_check__', 'GET', [], '', '', $timeout);
        $healthLatency = round((microtime(true) - $healthStart) * 1000, 2);
        if (!empty($health['error'])) {
            return jerr('Relay健康检查失败: ' . $health['error']);
        }

        $target = 'https://pan.baidu.com/';
        $targetStart = microtime(true);
        $targetRes = $this->requestRelay($url, $secret, $target, 'GET', [], '', '', $timeout);
        $targetLatency = round((microtime(true) - $targetStart) * 1000, 2);
        if (!empty($targetRes['error'])) {
            return jerr('Relay目标请求失败: ' . $targetRes['error']);
        }

        $httpCode = intval($targetRes['http_code'] ?? 0);
        if ($httpCode < 200 || $httpCode >= 500) {
            return jerr('Relay目标响应异常: HTTP ' . $httpCode);
        }

        return jok('Relay连接成功', [
            'health_latency' => $healthLatency,
            'target_latency' => $targetLatency,
            'target_http_code' => $httpCode,
            'relay' => $url,
            'target' => $target,
        ]);
    }

    private function requestRelay($relayUrl, $secret, $targetUrl, $method = 'GET', $headers = [], $cookies = '', $postData = '', $timeout = 20)
    {
        $timestamp = time();
        $nonce = bin2hex(random_bytes(8));
        $headersJson = json_encode($headers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $sign = $this->makeRelaySign($secret, $method, $targetUrl, $timestamp, $nonce, $headersJson, $cookies, $postData);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $relayUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'target_url' => $targetUrl,
            'method' => strtoupper($method),
            'headers' => $headersJson,
            'cookies' => $cookies,
            'post_data' => $postData,
            'timeout' => $timeout,
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'sign' => $sign,
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout + 3);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min($timeout, 10));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['error' => $error];
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            return ['error' => 'Relay HTTP ' . $httpCode . ': ' . substr((string)$body, 0, 200)];
        }

        return [
            'body' => $body,
            'http_code' => $httpCode,
        ];
    }

    private function makeRelaySign($secret, $method, $targetUrl, $timestamp, $nonce, $headersJson, $cookies, $postData)
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

    /**
     * 测试 PanCheck 连接
     */
    public function testPanCheck()
    {
        $url = input('url');
        $mode = input('mode', 'jc');
        
        if (empty($url)) {
            return jerr('请输入API地址');
        }

        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            if ($mode === 'pancheck') {
                // PanCheck 模式：POST 请求
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                    'links' => ['https://pan.baidu.com/s/1test'], // 测试链接
                    'selectedPlatforms' => ['baidu']
                ]));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            } else {
                // jc.php 模式：GET 请求
                if (strpos($url, '?') !== false) {
                    $testUrl = $url . '&url=' . urlencode('https://pan.baidu.com/s/1test');
                } else {
                    $testUrl = $url . '?url=' . urlencode('https://pan.baidu.com/s/1test');
                }
                curl_setopt($ch, CURLOPT_URL, $testUrl);
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                return jerr('CURL閿欒: ' . $error);
            }

            if ($httpCode != 200) {
                return jerr('HTTP鐘舵€佺爜: ' . $httpCode);
            }

            // 解析响应
            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return jerr('返回数据不是有效的JSON');
            }

            if ($mode === 'pancheck') {
                // PanCheck 响应校验
                if (isset($data['submission_id'])) {
                    return jok('连接成功，API正常响应');
                }
            } else {
                // jc.php 响应校验
                if (isset($data['status']) || isset($data['code'])) {
                    return jok('连接成功，API正常响应');
                }
            }

            return jerr('API返回格式不符合预期: ' . substr($response, 0, 100));

        } catch (\Exception $e) {
            return jerr('异常: ' . $e->getMessage());
        }
    }

    public function testRedisCache()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }

        if (!extension_loaded('redis')) {
            return jerr('Redis扩展未安装，当前 PHP 环境无法测试 Redis');
        }

        $host = trim(input('host', '127.0.0.1'));
        $port = intval(input('port', 6379));
        $password = input('password', '');
        if ($host === '' || $port <= 0) {
            return jerr('Redis地址和端口不能为空');
        }

        $startTime = microtime(true);
        try {
            $redis = new \Redis();
            $redis->connect($host, $port, 2);
            if ($password !== '') {
                $redis->auth($password);
            }
            $pong = $redis->ping();
            $info = $redis->info();
            $totalKeys = 0;
            $keyspace = $redis->info('keyspace');
            if (is_array($keyspace)) {
                foreach ($keyspace as $dbInfo) {
                    if (is_array($dbInfo) && isset($dbInfo['keys'])) {
                        $totalKeys += (int)$dbInfo['keys'];
                    } elseif (is_string($dbInfo) && preg_match('/keys=(\d+)/', $dbInfo, $matches)) {
                        $totalKeys += (int)$matches[1];
                    }
                }
            }
            $latency = round((microtime(true) - $startTime) * 1000, 2);
            return jok('Redis连接成功', [
                'host' => $host . ':' . $port,
                'latency' => $latency,
                'ping' => is_string($pong) ? $pong : 'PONG',
                'version' => isset($info['redis_version']) ? $info['redis_version'] : '未知',
                'used_memory' => isset($info['used_memory']) ? (int)$info['used_memory'] : 0,
                'used_memory_human' => isset($info['used_memory_human']) ? $info['used_memory_human'] : $this->formatBytes(isset($info['used_memory']) ? (int)$info['used_memory'] : 0),
                'connected_clients' => isset($info['connected_clients']) ? (int)$info['connected_clients'] : 0,
                'total_keys' => $totalKeys,
            ]);
        } catch (\Exception $e) {
            return jerr('Redis连接失败: ' . $e->getMessage());
        }
    }

    public function testMemcachedCache()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }

        if (!extension_loaded('memcached')) {
            return jerr('Memcached扩展未安装，当前 PHP 环境无法测试 Memcached');
        }

        $host = trim(input('host', '127.0.0.1'));
        $port = intval(input('port', 11211));
        $username = trim(input('username', ''));
        $password = input('password', '');
        if ($host === '' || $port <= 0) {
            return jerr('Memcached地址和端口不能为空');
        }

        $startTime = microtime(true);
        try {
            $memcached = new \Memcached();
            $memcached->setOption(\Memcached::OPT_CONNECT_TIMEOUT, 2000);
            if ($username !== '' && $password !== '') {
                $memcached->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);
                $memcached->setSaslAuthData($username, $password);
            }
            $memcached->addServer($host, $port);
            $versionMap = $memcached->getVersion();
            $statsMap = $memcached->getStats();
            $version = is_array($versionMap) && !empty($versionMap) ? reset($versionMap) : false;
            if ($version === false || $version === '0.0.0') {
                return jerr('Memcached连接失败: ' . $memcached->getResultMessage());
            }
            $stats = is_array($statsMap) && !empty($statsMap) ? reset($statsMap) : [];
            $latency = round((microtime(true) - $startTime) * 1000, 2);
            $bytes = isset($stats['bytes']) ? (int)$stats['bytes'] : 0;
            $limit = isset($stats['limit_maxbytes']) ? (int)$stats['limit_maxbytes'] : 0;
            return jok('Memcached连接成功', [
                'host' => $host . ':' . $port,
                'latency' => $latency,
                'version' => (string)$version,
                'used_memory' => $bytes,
                'used_memory_human' => $this->formatBytes($bytes),
                'memory_limit_human' => $this->formatBytes($limit),
                'curr_items' => isset($stats['curr_items']) ? (int)$stats['curr_items'] : 0,
            ]);
        } catch (\Exception $e) {
            return jerr('Memcached连接失败: ' . $e->getMessage());
        }
    }

    private function formatBytes($bytes)
    {
        $bytes = (float)$bytes;
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int)floor(log($bytes, 1024)), count($units) - 1);
        return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }
}


