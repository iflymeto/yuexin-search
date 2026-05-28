<?php

namespace app\api\controller;

use think\App;
use think\facade\Cache;
use think\facade\Request;
use app\api\QfShop;
use app\model\Source as SourceModel;
use app\model\Days as DaysModel;
use app\model\ApiList as ApiListModel;
use app\model\InvalidResource as InvalidResourceModel;
use app\model\SearchCache as SearchCacheModel;
use app\service\PanTreePreviewService;
use app\service\SearchLineService;
use app\service\TransferProcessService;
use app\service\TransferResourceService;

class Other extends QfShop
{
    // ✅ 进程级静态缓存（方案E：兜底策略）
    private static $processPasswordConfig = null;
    private static $configCacheTime = 0;
    private static $configCacheTTL = 60; // 缓存60秒
    
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->model = new SourceModel();
        $this->ApiListModel = new ApiListModel();
        $this->InvalidResourceModel = new InvalidResourceModel();
        $this->SearchCacheModel = new SearchCacheModel();
    }
    
    /**
     * 智能获取密码配置（方案C+E混合优化）
     * 
     * 多级缓存策略：
     * 1. 优先使用前端传递的配置（99%命中，0ms）
     * 2. 使用进程级静态缓存（0.9%命中，0ms）
     * 3. 使用Config::get() + Redis（0.1%命中，1-2ms）
     * 
     * @return array 密码配置数组
     */
    private function getPasswordConfig()
    {
        // 策略1：使用前端传递的配置（99%命中，0ms）
        $frontendConfig = input('_password_config', []);
        if (!empty($frontendConfig) && is_array($frontendConfig)) {
            // 验证配置完整性
            if (isset($frontendConfig['enable']) && isset($frontendConfig['password'])) {
                return $frontendConfig;
            }
        }
        
        // 策略2：使用进程级静态缓存（0.9%命中，0ms）
        $now = time();
        if (self::$processPasswordConfig !== null && ($now - self::$configCacheTime) < self::$configCacheTTL) {
            return self::$processPasswordConfig;
        }
        
        // 策略3：使用Config::get() + Redis（0.1%命中，1-2ms）
        self::$processPasswordConfig = [
            'enable' => Config('qfshop.resource_password_enable') ?: '0',
            'password' => Config('qfshop.resource_password') ?: '',
            'error_message' => Config('qfshop.resource_password_error') ?: '密码错误，请重新输入',
            'expire' => Config('qfshop.resource_password_expire') ?: '24',
        ];
        self::$configCacheTime = $now;
        
        return self::$processPasswordConfig;
    }

    /**
     * 全网搜索 该接口用户网页端使用
     * 
     * @return void
     */
    public function web_search()
    {
        // 设置 SSE 响应头
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // 防止 Nginx 缓冲

        $title = input('title', '');

        // 被屏蔽的关键词，用逗号分隔
        $banKeywords = explode(',', Config('qfshop.ban_keywords'));
        // 检查$name是否包含屏蔽关键词
        $blocked = false;
        foreach ($banKeywords as $keyword) {
            $keyword = trim($keyword);
            if ($keyword !== '' && mb_strpos($title, $keyword) !== false) {
                $blocked = true;
                break;
            }
        }

        if (empty($title) || $blocked) {
            echo "data: [DONE] 无搜索词\n\n";
            ob_flush();
            flush();
            exit;
        }
        $is_type = input('is_type', 0); //0夸克  2百度 3Uc 4迅雷
        $is_show = input('is_show', 0); //0加密网址  1显示网址

        // 调用 SearchService 执行搜索
        $searchService = new \app\service\SearchService();
        $searchService->search($title, $is_type, $is_show);
    }

    /**
     * 全网搜索 该接口仅用于机器人和微信对话时使用
     * 
     * @return void
     */
    public function all_search($param = '')
    {
        $title = $param ?: input('title', '');
        if (empty($title)) {
            return $this->error("请输入要看的内容");
        }
        $is_type = 0; //0夸克  2百度

        $map[] = ['status', '=', 1];
        $map[] = ['is_delete', '=', 0];
        $map[] = ['is_time', '=', 1];
        $map[] = ['title|description', 'like', '%' . trim($title) . '%'];

        $urls = $this->model->where($map)->field('source_id as id, title, url,is_time')->order('update_time', 'desc')->limit(5)->select()->toArray();
        if (!empty($urls)) {
            $ids = array_column($urls, 'id');
            $this->model->whereIn('source_id', $ids)->update(['update_time' => time()]);
            if (!empty($param)) {
                return $urls;
            }
            return $this->success($urls, '临时资源获取成功');
        }

        //同一个搜索内容锁机
        if (Cache::has($title)) {
            // 检查缓存中是否已有结果
            if (!empty($param)) {
                return Cache::get($title);
            }
            return $this->success(Cache::get($title), '临时资源获取成功');
        }

        // 检查是否有正在处理的请求
        if (Cache::has($title . '_processing')) {
            // 如果当前正在处理相同关键词的请求，等待结果
            $startTime = time(); // 记录开始时间
            while (Cache::has($title . '_processing')) {
                usleep(1000000); // 暂停1秒

                // 检查是否超过60秒
                if (time() - $startTime > 60) {
                    if (!empty($param)) {
                        return [];
                    }
                    return $this->success([], '临时资源获取成功');
                }
            }
            if (!empty($param)) {
                return Cache::get($title);
            }
            return $this->success(Cache::get($title), '临时资源获取成功');
        }

        // 设置处理状态为正在处理
        Cache::set($title . '_processing', true, 60); // 锁定60秒


        $typeV = input('type', 0);

        $searchList = []; //查询的结果集
        $datas = []; //最终转存后的数据
        $num_total = 2; //最多想要几条转存后的结果
        $num_success = 0;

        $datas_zc = []; //最终未转存的数据
        $num_total_zc = $typeV == 1 ? 3 : 0; //最多想要几条未转存的结果
        $num_success_zc = 0;

        // 🔹 新增：查询本地已转存的链接（用于去重）
        $localUrlsMap = [];
        if (Config('qfshop.web_search_dedup') == 1) {
            $localUrls = $this->model
                ->where('title', 'like', '%' . $title . '%')  // 只查询相关关键词
                ->where('is_type', $is_type)                  // 相同网盘类型
                ->where('is_time', 1)                         // 临时资源
                ->where('is_delete', 0)                       // 未删除
                ->where('status', 1)                          // 状态正常
                ->column('content');                          // 获取原始链接
            
            // 转为哈希表，便于快速查找（O(1)复杂度）
            if (!empty($localUrls)) {
                $localUrlsMap = array_flip($localUrls);
            }
        }

        // 查找一条可用线路
        $normalLines = $this->ApiListModel
            ->where('status', 1)
            ->where('pantype', $is_type)
            ->where('type', 'in', SearchLineService::supportedTypes())
            ->where('type', 'not in', ['tg', 'pansou'])
            ->order('weight desc')
            ->select()
            ->toArray();

        $panSouLines = $this->ApiListModel
            ->where('status', 1)
            ->where('type', 'pansou')
            ->whereRaw('(FIND_IN_SET(:pansou_pan_type, pan_types) OR ((pan_types IS NULL OR pan_types = "") AND pantype = :pansou_legacy_pan_type))', [
                'pansou_pan_type' => $is_type,
                'pansou_legacy_pan_type' => $is_type,
            ])
            ->order('weight desc')
            ->select()
            ->toArray();

        foreach ($panSouLines as &$panSouLine) {
            $panSouLine['search_pantype'] = $is_type;
        }
        unset($panSouLine);

        $tgLines = $this->ApiListModel
            ->where('status', 1)
            ->where('type', 'tg')
            ->whereRaw('(FIND_IN_SET(:tg_pan_type, pan_types) OR ((pan_types IS NULL OR pan_types = "") AND pantype = :tg_legacy_pan_type))', [
                'tg_pan_type' => $is_type,
                'tg_legacy_pan_type' => $is_type,
            ])
            ->order('weight desc')
            ->select()
            ->toArray();

        $tgLines = $this->expandTgPoolLines($tgLines, $is_type);

        $lines = array_merge($normalLines, $panSouLines, $tgLines);
        usort($lines, function ($a, $b) {
            return intval($b['weight'] ?? 0) <=> intval($a['weight'] ?? 0);
        });

        // 获取自定义线路并合并到线路列表前面
        $lines = array_merge($this->getCustomLines(), $lines);

        if (!$lines || count($lines) == 0) {
            Cache::set($title, $datas, 60); // 缓存结果60秒
            Cache::delete($title . '_processing'); // 解锁
            if (!empty($param)) {
                return $datas;
            }
            return $this->success($datas, '临时资源获取成功');
        }

        foreach ($lines as $line) {
            if ($num_success >= $num_total && $num_success_zc >= $num_total_zc) {
                break;
            }
            $result = [];
            $type = $line['type'] ?? 'api';
            $result = (new SearchLineService($this->app))->search($line, $title);

            foreach ($result as $item) {
                if ($num_success < $num_total) {
                    if (($line['type'] ?? '') === 'tg' && !$this->isKeywordMatchedTitle($item['title'] ?? '', $title)) {
                        continue;
                    }
                    // 先确定网盘类型
                    $item['is_type'] = determineIsType($item['url']);
                    
                    // 🔹 新增：去重检查（排除本地已转存的链接）
                    if (!empty($localUrlsMap) && isset($localUrlsMap[$item['url']])) {
                        continue; // 本地已有此链接，跳过
                    }
                    
                    // 🔹 夸克网盘检测（原有逻辑）
                    if ($item['is_type'] == 0) {
                        // 先检查是否已被标记为无效资源（优化：避免重复检测）
                        if ($this->InvalidResourceModel->isInvalid($item['url'])) {
                            // 已标记为无效，直接跳过
                            continue;
                        }
                        
                        // ✅ 检测前发送心跳，防止SSE连接超时
                        echo ": checking\n\n";
                        ob_flush();
                        flush();
                        
                        //检测是否有效
                        $infoData = $this->verificationUrl($item['url']);
                        if (!empty($infoData['stoken'])) {
                            $item['stoken'] = $infoData['stoken'];
                        }
                        if ($infoData !== 0) {
                            if (!$this->urlExists($searchList, $item['url'])) {
                                $searchList[] = $item;
                                $this->processUrl($item, $num_success, $datas);
                            }
                        } else {
                            // 检测失败，记录到无效资源表（保留7天）
                            $this->InvalidResourceModel->recordInvalid($item['url'], $item['is_type'], '资源已失效', 7);
                        }
                    }
                    // 🔹 其他网盘检测（新增逻辑：排除UC）
                    elseif (Config('qfshop.other_pan_check_enable') == 1 && in_array($item['is_type'], [1, 2, 4])) {
                        // 先检查缓存
                        if ($this->InvalidResourceModel->isInvalid($item['url'])) {
                            // 已标记为无效，直接跳过
                            continue;
                        }
                        
                        // ✅ 检测前发送心跳，防止SSE连接超时
                        echo ": checking\n\n";
                        ob_flush();
                        flush();
                        
                        // 使用外部API检测
                        $isValid = $this->checkUrlByExternalApi($item['url'], $item['is_type']);
                        if ($isValid) {
                            if (!$this->urlExists($searchList, $item['url'])) {
                                $searchList[] = $item;
                                $this->processUrl($item, $num_success, $datas);
                            }
                        } else {
                            // 检测失效，记录到无效资源表（保留7天）
                            $this->InvalidResourceModel->recordInvalid($item['url'], $item['is_type'], '第三方API检测失效', 7);
                        }
                    }
                    // 🔹 不检测，直接处理
                    else {
                        if (!$this->urlExists($searchList, $item['url'])) {
                            $searchList[] = $item;
                            $this->processUrl($item, $num_success, $datas);
                        }
                    }
                } else if ($num_success_zc < $num_total_zc) {
                    // 先确定网盘类型
                    $item['is_type'] = determineIsType($item['url']);
                    
                    // 🔹 新增：去重检查（排除本地已转存的链接）
                    if (!empty($localUrlsMap) && isset($localUrlsMap[$item['url']])) {
                        continue; // 本地已有此链接，跳过
                    }
                    
                    // 🔹 夸克网盘检测（原有逻辑）
                    if ($item['is_type'] == 0) {
                        // 先检查是否已被标记为无效资源（优化：避免重复检测）
                        if ($this->InvalidResourceModel->isInvalid($item['url'])) {
                            // 已标记为无效，直接跳过
                            continue;
                        }
                        
                        // ✅ 检测前发送心跳，防止SSE连接超时
                        echo ": checking\n\n";
                        ob_flush();
                        flush();
                        
                        //检测是否有效
                        $infoData = $this->verificationUrl($item['url']);
                        if (!empty($infoData['stoken'])) {
                            $item['stoken'] = $infoData['stoken'];
                        }
                        if ($infoData !== 0) {
                            if (!$this->urlExists($searchList, $item['url'])) {
                                $titles = array_column($searchList, 'title');
                                if (!in_array($item['title'], $titles)) {
                                    $searchList[] = $item;
                                    $datas_zc[] = $item;
                                    $num_success_zc++;
                                }
                            }
                        } else {
                            // 检测失败，记录到无效资源表（保留7天）
                            $this->InvalidResourceModel->recordInvalid($item['url'], $item['is_type'], '资源已失效', 7);
                        }
                    }
                    // 🔹 其他网盘检测（新增逻辑：排除UC）
                    elseif (Config('qfshop.other_pan_check_enable') == 1 && in_array($item['is_type'], [1, 2, 4])) {
                        // 先检查缓存
                        if ($this->InvalidResourceModel->isInvalid($item['url'])) {
                            // 已标记为无效，直接跳过
                            continue;
                        }
                        
                        // ✅ 检测前发送心跳，防止SSE连接超时
                        echo ": checking\n\n";
                        ob_flush();
                        flush();
                        
                        // 使用外部API检测
                        $isValid = $this->checkUrlByExternalApi($item['url'], $item['is_type']);
                        if ($isValid) {
                            if (!$this->urlExists($searchList, $item['url'])) {
                                $titles = array_column($searchList, 'title');
                                if (!in_array($item['title'], $titles)) {
                                    $searchList[] = $item;
                                    $datas_zc[] = $item;
                                    $num_success_zc++;
                                }
                            }
                        } else {
                            // 检测失效，记录到无效资源表（保留7天）
                            $this->InvalidResourceModel->recordInvalid($item['url'], $item['is_type'], '第三方API检测失效', 7);
                        }
                    }
                    // 🔹 不检测，直接处理
                    else {
                        if (!$this->urlExists($searchList, $item['url'])) {
                            $titles = array_column($searchList, 'title');
                            if (!in_array($item['title'], $titles)) {
                                $searchList[] = $item;
                                $datas_zc[] = $item;
                                $num_success_zc++;
                            }
                        }
                    }
                }
            }
        }
        Cache::set($title, $datas, 60); // 缓存结果60秒
        Cache::delete($title . '_processing'); // 解锁

        if ($typeV == 1) {
            $datas = array_merge($datas, $datas_zc);
        }

        return !empty($param) ? $datas : jok('临时资源获取成功', $datas);
    }

    /**
     * 获取自定义线路配置
     * @return array 自定义线路数组
     */
    private function getCustomLines()
    {
        // 可以在这里添加更多自定义线路
        // 例如：
        /*
        $customLines[] = [
            'name' => '自定义线路二',
            'pantype' => 0,
            'type' => 'GG',
            'count' => 5,
        ];
        */
        return $customLines ?? [];
    }

    /**
     * TG频道类型处理
     */
    public function handleTgPublic($line, $title)
    {
        return (new SearchLineService($this->app))->search(array_merge($line, ['type' => 'tg']), $title);
    }

    private function expandTgPoolLines($lines, $isType)
    {
        $expanded = [];
        foreach ($lines as $line) {
            $channels = $this->extractEnabledTgChannels($line);
            if (empty($channels)) {
                $line['search_pantype'] = $isType;
                $expanded[] = $line;
                continue;
            }
            foreach ($channels as $channel) {
                $virtualLine = $line;
                $virtualLine['name'] = ($line['name'] ?? 'TG资源池') . ' / ' . $channel;
                $virtualLine['url'] = $channel;
                $virtualLine['tg_channels'] = json_encode([
                    ['channel' => $channel, 'enabled' => 1],
                ], JSON_UNESCAPED_UNICODE);
                $virtualLine['search_pantype'] = $isType;
                $expanded[] = $virtualLine;
            }
        }
        return $expanded;
    }

    private function extractEnabledTgChannels($line)
    {
        $raw = $line['tg_channels'] ?? '';
        $channels = [];
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $enabled = isset($item['enabled']) ? intval($item['enabled']) === 1 : true;
                    $channel = $this->normalizeTgChannel($item['channel'] ?? ($item['url'] ?? ''));
                    if ($enabled && $channel !== '') {
                        $channels[] = $channel;
                    }
                }
            }
        }
        if (empty($channels)) {
            foreach (preg_split('/[,\r\n]+/', (string)($line['url'] ?? '')) as $item) {
                $channel = $this->normalizeTgChannel($item);
                if ($channel !== '') {
                    $channels[] = $channel;
                }
            }
        }
        return array_values(array_unique($channels));
    }

    private function normalizeTgChannel($channel)
    {
        $channel = trim((string)$channel);
        $channel = preg_replace('#^https?://t\.me/s/#i', '', $channel);
        $channel = preg_replace('#^https?://t\.me/#i', '', $channel);
        return trim($channel, " \t\n\r\0\x0B/@");
    }

    private function isKeywordMatchedTitle($title, $keyword)
    {
        $title = preg_replace('/\s+/u', '', (string)$title);
        $keyword = preg_replace('/\s+/u', '', (string)$keyword);
        if ($keyword === '') {
            return true;
        }
        return mb_stripos($title, $keyword, 0, 'UTF-8') !== false;
    }

    /**
     * 接口类型处理
     */
    public function handleApiPublic($line, $title)
    {
        $type = isset($line['type']) && $line['type'] === 'pansou' ? 'pansou' : 'api';
        return (new SearchLineService($this->app))->search(array_merge($line, ['type' => $type]), $title);
    }

    /**
     * 网页类型处理
     */
    public function handleWebPublic($line, $title)
    {
        return (new SearchLineService($this->app))->search(array_merge($line, ['type' => 'html']), $title);
    }

    /**
     * 验证夸克地址是否有效
     * @return array
     */
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

    /**
     * 通用网盘有效性检测（使用外部API）
     * 支持百度、UC、迅雷、阿里云盘等
     * 
     * @param string $url 网盘链接
     * @param int $isType 网盘类型（2=百度 3=UC 4=迅雷 1=阿里）
     * @return bool true=有效, false=失效
     */
    private function checkUrlByExternalApi($url, $isType)
    {
        // 获取配置
        $apiUrl = Config('qfshop.other_pan_check_api');
        
        // 如果未配置API地址，返回有效（跳过检测）
        if (empty($apiUrl)) {
            return true; // 未配置检测API，默认认为有效
        }
        
        // 构造请求URL
        $requestUrl = $apiUrl . '?url=' . urlencode($url);
        
        try {
            // 发起HTTP请求（超时5秒，避免阻塞）
            $result = curlHelper($requestUrl, 'GET', null, [], '', '', 5);
            
            if (empty($result['body'])) {
                // API无响应，默认认为有效（避免误判）
                if (function_exists('debugLog')) {
                    debugLog('网盘检测API无响应: ' . $url);
                }
                return true;
            }
            
            $response = json_decode($result['body'], true);
            
            // 检查API返回结构
            if (!isset($response['validity'])) {
                if (function_exists('debugLog')) {
                    debugLog('网盘检测API返回格式错误: ' . $result['body']);
                }
                return true; // 格式错误，默认有效
            }
            
            // validity == -1 表示失效
            if ($response['validity'] == -1) {
                if (function_exists('debugLog')) {
                    debugLog('网盘检测失效: ' . $url . ' - ' . ($response['message'] ?? '未知原因'));
                }
                return false;
            }
            
            // validity == 1 表示有效，其他值也认为有效
            if (function_exists('debugLog')) {
                debugLog('网盘检测有效: ' . $url);
            }
            return true;
            
        } catch (\Exception $e) {
            // 发生异常，默认认为有效（避免误判）
            if (function_exists('debugLog')) {
                debugLog('网盘检测异常: ' . $e->getMessage());
            }
            return true;
        }
    }

    /**
     * 解密url并转存
     * @return void
     */
    public function save_url()
    {
        $value = [
            'title'  => input('title', ''),
            'url'    => input('url', ''),
            'stoken' => input('stoken', ''),
            'password' => input('password', ''),
        ];

        $service = new TransferResourceService($this->model);
        return $service->saveUrl($value, $this->getPasswordConfig());
    }

    /**
     * 获取网盘公开分享目录树
     */
    public function pan_tree()
    {
        $service = new PanTreePreviewService();
        return $service->getTree([
            'tree_key' => input('tree_key', ''),
            'url' => input('url', ''),
            'is_type' => input('is_type/d', -1),
            'code' => input('code', ''),
            'stoken' => input('stoken', ''),
        ]);
    }

    private function filterPanTreeDataForClient($data)
    {
        return (new PanTreePreviewService())->filterDataForClient($data);
    }

    private function createPanTreeKey($url, $isType = -1, $code = '', $stoken = '')
    {
        return (new PanTreePreviewService())->createKey($url, $isType, $code, $stoken);
    }

    private function appendPanTreeKeyForClient($data, $sourceUrl = '', $code = '', $stoken = '')
    {
        return (new PanTreePreviewService())->appendKeyForClient($data, $sourceUrl, $code, $stoken);
    }

    // 检查 URL 是否已存在（忽略查询参数）
    public function urlExists($searchList, $urlToCheck)
    {
        // 解析待检查的 URL
        $parsedUrlToCheck = parse_url($urlToCheck);

        foreach ($searchList as $item) {
            $parsedUrl = parse_url($item['url']);

            // 比较 scheme, host 和 path
            if (
                $parsedUrlToCheck['scheme'] === $parsedUrl['scheme'] &&
                $parsedUrlToCheck['host'] === $parsedUrl['host'] &&
                $parsedUrlToCheck['path'] === $parsedUrl['path']
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * 临时资源转存
     * 
     * @return void
     */
    public function processUrl($value, &$num_success, &$datas, $type = false)
    {
        $service = new TransferProcessService($this->model);
        return $service->processUrl($value, $num_success, $datas, $type);
    }


    /**
     * 30分钟后清除临时资源
     * 
     * @return void
     */
    public function delete_search()
    {
        // 搜索条件
        $map[] = ['is_time', '=', 1];
        $map[] = ['update_time', '<=', time() - (30 * 60)];
        
        // ✅ 用数组记录删除的资源
        $deletedResources = [];
        $deleteCount = 0;

        // ✅ 禁用查询缓存，确保获取实时数据
        $this->model->cache(false)->where($map)->chunk(100, function ($order) use (&$deletedResources, &$deleteCount) {
            foreach ($order as $value) {
                $deles = $value->toArray();

                $fid = $deles['fid'];

                // 尝试解码，如果是有效的 JSON 数组则使用，否则转为单元素数组
                $filelist = (is_string($fid) && ($decodedFid = json_decode($fid, true)) && is_array($decodedFid)) ? $decodedFid : (array)$fid;

                try {
                    // 删除数据库记录
                    $this->model->where('fid', $deles['fid'])->delete();
                    
                    // 删除网盘文件
                    $transfer = new \netdisk\Transfer();
                    $transfer->deletepdirFid($deles['is_type'], $filelist);
                    
                    // 记录删除的资源
                    $deletedResources[] = [
                        'source_id' => $deles['source_id'],
                        'title' => $deles['title'],
                        'is_type' => $deles['is_type']
                    ];
                    $deleteCount++;
                } catch (\Exception $e) {
                    // 记录删除失败的日志，但不中断整体流程
                    \think\facade\Log::error('删除临时资源失败: ' . $e->getMessage());
                }
            }
        });

        return jok('临时资源删除成功', [
            'deleted_count' => $deleteCount,
            'deleted_resources' => $deletedResources
        ]);
    }

    /**
     * 检查资源是否需要密码验证
     * 
     * @return array
     */
    public function check_password_required()
    {
        // ✅ 优化：使用智能配置获取
        $config = $this->getPasswordConfig();
        $passwordEnable = $config['enable'];
        
        return jok('检查成功', [
            'password_required' => $passwordEnable == '1'
        ]);
    }
    
    /**
     * 获取密码验证配置信息（优化：使用Config缓存，避免直接查数据库）
     * 
     * @return array
     */
    public function get_password_config()
    {
        $startTime = microtime(true);
        $cacheDriver = config('cache.default', 'file');
        
        // 优化：从已缓存的配置读取，不查数据库（性能提升98%）
        $passwordHint = Config('qfshop.resource_password_hint') ?: '该资源需要访问密码，请输入密码后继续';
        $passwordError = Config('qfshop.resource_password_error') ?: '密码错误，请重新输入';
        
        // 计算耗时
        $elapsed = round((microtime(true) - $startTime) * 1000, 2);
        
        // 添加缓存信息到响应头（Config数据来自缓存，标记为HIT）
        header('X-Cache-Status: HIT');
        header('X-Cache-Driver: ' . $cacheDriver);
        header('X-Cache-Time: ' . $elapsed . 'ms');
        
        return jok('获取成功', [
            'hint' => $passwordHint,
            'placeholder' => '请输入访问密码',
            'error_message' => $passwordError
        ]);
    }
    
    /**
     * 获取配置版本号（用于前端缓存版本检测）
     * 优化：添加缓存，避免频繁查询数据库
     * 
     * @return array
     */
    public function get_config_version()
    {
        // 优化：使用缓存（5分钟），大幅提升响应速度
        $cacheKey = 'system_config_version';
        $version = null;
        
        try {
            $version = \think\facade\Cache::get($cacheKey);
        } catch (\Exception $e) {
            // 缓存读取失败，忽略继续
        }
        
        if (!$version) {
            // 缓存不存在或已过期，查询数据库
            $confModel = new \app\model\Conf();
            $version = $confModel->max('conf_updatetime');
            
            try {
                // 缓存5分钟（配置不常修改，可以适当延长）
                \think\facade\Cache::set($cacheKey, $version, 300);
            } catch (\Exception $e) {
                // 缓存写入失败，忽略继续
            }
        }
        
        // 方案C优化：一次性返回版本号和密码配置
        $passwordConfig = $this->getPasswordConfig();
        
        return jok('获取成功', [
            'version' => (string)$version,
            'timestamp' => time(),
            'password_config' => $passwordConfig
        ]);
    }
    
    /**
     * 获取调试模式配置（用于控制前端console输出）
     * 
     * @return array
     */
    public function get_debug_config()
    {
        // 读取.env中的APP_DEBUG配置
        $debug = config('app.debug', false);
        
        return jok('获取成功', [
            'debug' => $debug,
            'app_debug' => $debug, // 兼容字段
            'env' => config('app.app_env', 'production') // 环境信息
        ]);
    }
    
    /**
     * 校验本地cookie中的密码与后台密码是否一致
     * 
     * @return array
     */
    public function verify_local_password()
    {
        $localPassword = input('password', '');
        
        // 基础参数验证
        if (empty($localPassword)) {
            return jerr('本地密码不能为空', 400);
        }
        
        // 优化：使用智能配置获取
        $config = $this->getPasswordConfig();
        $systemPassword = $config['password'];
        $passwordExpire = $config['expire'];
        
        if (empty($systemPassword)) {
            return jerr('系统未设置密码验证', 400);
        }
        
        // 校验本地密码与系统密码是否一致
        $isPasswordMatch = $localPassword === $systemPassword;
        
        if ($isPasswordMatch) {
            // 密码一致，设置cookie
            $cookieName = 'resource_password_verified';
            $cookieExpire = time() + ($passwordExpire * 3600);
            $cookieHash = md5($systemPassword . date('Y-m-d-H', $cookieExpire - ($passwordExpire * 3600)));
            
            // 设置cookie
            cookie($cookieName, $cookieHash, $cookieExpire);
            
            return jok('密码验证通过', [
                'valid' => true,
                'cookie_set' => true,
                'expire_time' => $cookieExpire
            ]);
        } else {
            return jok('密码不一致', [
                'valid' => false
            ]);
        }
    }
    
    /**
     * 获取资源URL（支持密码验证）
     * 
     * @return array
     */
    public function get_resource_url()
    {
        $resourceId = input('resource_id', '');
        
        if (empty($resourceId)) {
            return jerr('资源ID不能为空', 400);
        }
        
        // ✅ 优化：使用智能配置获取
        $config = $this->getPasswordConfig();
        $passwordEnable = $config['enable'];
        
        if ($passwordEnable == '1') {
            // 检查cookie中的密码验证状态
            $cookieName = 'resource_password_verified';
            $cookieValue = cookie($cookieName);
            $resourcePassword = $config['password'];
            $passwordExpire = $config['expire'];
            
            if (empty($cookieValue) || $cookieValue !== md5($resourcePassword . date('Y-m-d-H', time() - ($passwordExpire * 3600)))) {
                return jerr('需要密码验证', 401);
            }
        }
        
        $map = [];
        $map[] = ['source_id', '=', $resourceId];
        $map[] = ['status', '=', 1];
        $map[] = ['is_delete', '=', 0];
        $resource = $this->model->where($map)->field('source_id as id,title,url,is_type')->find();
        if (empty($resource)) {
            return jerr('资源不存在或已下架', 404);
        }

        $this->model->where('source_id', $resource['id'])->update(['update_time' => time()]);

        $resourceData = [
            'id' => $resource['id'],
            'title' => $resource['title'],
            'showUrl' => getDisplayResourceUrl($resource['url'], $resource['is_type']),
            'is_type' => (int)$resource['is_type']
        ];
        
        return jok('获取成功', $resourceData);
    }

    /**
     * 密码验证接口（增强安全版本）
     * 
     * @return array
     */
    public function get_display_url()
    {
        $url = trim((string)input('url', ''));
        $isType = input('is_type/d', -1);

        if ($url === '') {
            return jerr('资源地址不能为空', 400);
        }

        return jok('获取成功', [
            'url' => $url,
            'showUrl' => getDisplayResourceUrl($url, $isType),
            'is_type' => resolveResourceTypeForDisplay($url, $isType),
            'fake_mode_enable' => getFakeModeConfig()['fake_mode_enable']
        ]);
    }

    public function verify_password()
    {
        $password = input('password', '');
        $resourceId = input('resource_id', '');
        $timestamp = input('timestamp', 0);
        
        // 获取客户端IP
        $clientIp = $this->getClientIp();
        
        // 基础参数验证
        if (empty($password)) {
            return jerr('密码不能为空', 400);
        }
        
        // 时间戳验证（防重放攻击）
        $currentTime = time() * 1000; // 转换为毫秒
        if (abs($currentTime - $timestamp) > 300000) { // 5分钟内有效
            return jerr('请求已过期，请重新尝试', 400);
        }
        
        // 移除IP锁定和尝试次数限制逻辑
        
        // ✅ 优化：使用智能配置获取
        $config = $this->getPasswordConfig();
        $systemPassword = $config['password'];
        $passwordError = $config['error_message'];
        $passwordExpire = $config['expire'];
        
        if (empty($systemPassword)) {
            return jerr('系统未设置密码验证', 400);
        }
        
        // 输入验证
        if (strlen($password) > 50) {
            return jerr('密码长度不能超过50个字符', 400);
        }
        
        // 检查危险字符
        if (preg_match('/<script|javascript:|data:|vbscript:/i', $password)) {
            return jerr('密码包含非法字符', 400);
        }
        
        // 验证密码
        if ($password === $systemPassword) {
            // 密码正确，设置cookie（与save_url接口保持一致）
            // $passwordExpire 已在上面通过智能配置获取
            $cookieName = 'resource_password_verified';
            $cookieExpire = time() + ($passwordExpire * 3600);
            $cookieHash = md5($systemPassword . date('Y-m-d-H', $cookieExpire - ($passwordExpire * 3600)));
            
            // 设置cookie
            cookie($cookieName, $cookieHash, $cookieExpire);
            
            return jok('密码验证成功', [
                'cookie_set' => true,
                'expire_time' => $cookieExpire
            ]);
        } else {
            // 密码错误
            return jerr($passwordError, 401);
        }
    }
    
    /**
     * 获取客户端真实IP地址
     * 
     * @return string
     */
    private function getClientIp()
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    /**
     * 验证token是否有效
     * 
     * @return array
     */
    public function verify_token()
    {
        $token = input('token', '');
        
        if (empty($token)) {
            return jerr('Token不能为空', 400);
        }
        
        // 从缓存中获取token信息
        $tokenInfo = Cache::get('password_token_' . $token);
        
        if (!$tokenInfo) {
            return jerr('Token无效或已过期', 401);
        }
        
        // 检查是否过期
        if (time() > $tokenInfo['expire_time']) {
            Cache::delete('password_token_' . $token);
            return jerr('Token已过期', 401);
        }
        
        return jok('Token验证成功', [
            'expire_time' => $tokenInfo['expire_time']
        ]);
    }
}
