<?php

namespace app\admin\controller;

use think\App;
use think\facade\Db;
use app\admin\QfShop;
use app\model\ApiList as ApiListModel;
use app\service\SearchLineService;

class ApiList extends QfShop
{
    private $panTypeMap = [
        0 => '夸克网盘',
        2 => '百度网盘',
        3 => 'UC网盘',
        4 => '迅雷云盘',
    ];

    private function getPanSouKey($panType)
    {
        $map = [
            0 => 'quark',
            2 => 'baidu',
            3 => 'uc',
            4 => 'xunlei',
        ];
        return $map[$panType] ?? 'quark';
    }

    private function normalizePanSouSearchUrl($url)
    {
        $url = rtrim(trim((string)$url), '/');
        if ($url === '') {
            return '';
        }
        if (preg_match('#/api/search$#i', $url)) {
            return $url;
        }
        if (preg_match('#/api$#i', $url)) {
            return $url . '/search';
        }
        return $url . '/api/search';
    }

    private function normalizePanSouHealthUrl($url)
    {
        $url = rtrim(trim((string)$url), '/');
        if ($url === '') {
            return '';
        }
        $url = preg_replace('#/api/search$#i', '', $url);
        $url = preg_replace('#/api$#i', '', $url);
        return rtrim($url, '/') . '/api/health';
    }

    private function normalizePanSouToken($token)
    {
        $token = trim((string)$token);
        if ($token === '') {
            return '';
        }
        return preg_match('/^Bearer\s+/i', $token) ? $token : 'Bearer ' . $token;
    }

    private function normalizePanSouListParam($value)
    {
        if (is_array($value)) {
            $items = $value;
        } else {
            $items = preg_split('/[,\r\n]+/', (string)$value);
        }
        $result = [];
        foreach ($items as $item) {
            $item = trim((string)$item);
            if ($item !== '' && !in_array($item, $result, true)) {
                $result[] = $item;
            }
        }
        return $result;
    }

    private function applyPanSouConfig($data)
    {
        if (($data['type'] ?? '') !== 'pansou') {
            return $data;
        }
        $panTypes = $this->normalizePanTypes(input('pan_types', $data['pan_types'] ?? []), $data['pantype'] ?? 0);
        $panKeys = [];
        foreach ($panTypes as $panTypeItem) {
            $panKeys[] = $this->getPanSouKey($panTypeItem);
        }
        $panType = intval($data['search_pantype'] ?? ($data['pantype'] ?? $panTypes[0]));
        if (!in_array($panType, $panTypes, true)) {
            $panType = $panTypes[0];
        }
        $panKey = $this->getPanSouKey($panType);
        $headers = json_decode($data['headers'] ?? '', true);
        $headers = is_array($headers) ? $headers : [];
        $token = input('pansou_token', '');
        if (trim((string)$token) !== '') {
            $headers['Authorization'] = $this->normalizePanSouToken($token);
        }
        if (!empty($headers['Authorization'])) {
            $headers['Authorization'] = $this->normalizePanSouToken($headers['Authorization']);
        }
        $headers['Content-Type'] = 'application/json';
        $headers['Accept'] = 'application/json, text/plain, */*';

        $params = json_decode($data['fixed_params'] ?? '', true);
        $params = is_array($params) ? $params : [];
        $params = array_merge($params, [
            'kw' => '{keyword}',
            'res' => $params['res'] ?? 'merge',
            'cloud_types' => [$panKey],
        ]);
        foreach (['channels', 'plugins'] as $listKey) {
            if (array_key_exists($listKey, $params)) {
                $list = $this->normalizePanSouListParam($params[$listKey]);
                if (!empty($list)) {
                    $params[$listKey] = $list;
                } else {
                    unset($params[$listKey]);
                }
            }
        }

        $data['url'] = $this->normalizePanSouSearchUrl($data['url'] ?? '');
        $data['method'] = 'POST';
        $data['pan_types'] = implode(',', $panTypes);
        $data['pantype'] = $panTypes[0];
        $data['headers'] = json_encode($headers, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $data['fixed_params'] = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $data['field_map'] = json_encode([
            'list_path' => 'data.merged_by_type.' . $panKey,
            'fields' => [
                'title' => 'note',
                'url' => 'url',
                'images' => 'images',
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return $data;
    }

    private function normalizePanTypes($value, $fallback = 0)
    {
        if (is_array($value)) {
            $items = $value;
        } else {
            $items = preg_split('/[,\r\n]+/', (string)$value);
        }
        $valid = array_keys($this->panTypeMap);
        $result = [];
        foreach ($items as $item) {
            $type = intval($item);
            if (in_array($type, $valid, true) && !in_array($type, $result, true)) {
                $result[] = $type;
            }
        }
        if (empty($result)) {
            $fallback = intval($fallback);
            $result[] = in_array($fallback, $valid, true) ? $fallback : 0;
        }
        sort($result);
        return $result;
    }

    private function normalizeTgChannels($value)
    {
        if (is_array($value)) {
            $value = implode("\n", $value);
        }
        $items = preg_split('/[,\r\n]+/', (string)$value);
        $channels = [];
        foreach ($items as $item) {
            $channel = trim($item);
            $channel = preg_replace('#^https?://t\.me/s/#i', '', $channel);
            $channel = preg_replace('#^https?://t\.me/#i', '', $channel);
            $channel = trim($channel, " \t\n\r\0\x0B/@");
            if ($channel !== '' && !in_array($channel, $channels, true)) {
                $channels[] = $channel;
            }
        }
        return $channels;
    }

    private function normalizeTgChannelRows($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $value = $decoded;
            }
        }
        if (!is_array($value)) {
            $value = $this->normalizeTgChannels($value);
        }

        $rows = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $channelList = $this->normalizeTgChannels($item['channel'] ?? ($item['url'] ?? ($item['name'] ?? '')));
                $channel = $channelList[0] ?? '';
                $enabled = isset($item['enabled']) ? (intval($item['enabled']) === 1) : true;
            } else {
                $channelList = $this->normalizeTgChannels($item);
                $channel = $channelList[0] ?? '';
                $enabled = true;
            }
            if ($channel === '' || isset($rows[$channel])) {
                continue;
            }
            $rows[$channel] = [
                'channel' => $channel,
                'enabled' => $enabled ? 1 : 0,
            ];
        }
        return array_values($rows);
    }

    private function prepareTgData($data)
    {
        if (($data['type'] ?? '') !== 'tg') {
            return $data;
        }
        $panTypes = $this->normalizePanTypes(input('pan_types', $data['pan_types'] ?? []), $data['pantype'] ?? 0);
        $channelsInput = input('tg_channels', $data['tg_channels'] ?? ($data['url'] ?? ''));
        $channels = $this->normalizeTgChannelRows($channelsInput);
        $data['pan_types'] = implode(',', $panTypes);
        $data['pantype'] = $panTypes[0];
        $data['tg_channels'] = json_encode($channels, JSON_UNESCAPED_UNICODE);
        $data['url'] = 'TG群组 ' . count($channels) . ' 个';
        $data['method'] = 'GET';
        $data['tg_scan_limit'] = max(1, min(50, intval($data['tg_scan_limit'] ?? 5)));
        if (empty($data['fixed_params'])) {
            $data['fixed_params'] = json_encode(['search' => '{keyword}'], JSON_UNESCAPED_UNICODE);
        }
        if (empty($data['field_map'])) {
            $data['field_map'] = json_encode([
                'list_path' => '数组字段',
                'fields' => [
                    'title' => '资源名称',
                    'url' => '资源地址',
                    'image' => '图片URL（可选）',
                    'images' => ['图片数组（可选，自动选择第一张）'],
                ],
            ], JSON_UNESCAPED_UNICODE);
        }
        return $data;
    }

    private function mergePanTypes($current, $incoming)
    {
        return implode(',', $this->normalizePanTypes(array_merge(
            $this->normalizePanTypes($current),
            $this->normalizePanTypes($incoming)
        )));
    }

    private function getApiListSchemaColumns()
    {
        return [
            'pan_types' => [
                'sql' => "ALTER TABLE `%s` ADD COLUMN `pan_types` varchar(50) NOT NULL DEFAULT '' COMMENT 'TG/PanSou支持的网盘类型，多个用英文逗号分隔：0=夸克 2=百度 3=UC 4=迅雷' AFTER `status`",
                'label' => 'TG/PanSou支持网盘字段 pan_types',
            ],
            'tg_channels' => [
                'sql' => "ALTER TABLE `%s` ADD COLUMN `tg_channels` text COMMENT 'TG群组资源池JSON，包含群组ID和启用状态' AFTER `pan_types`",
                'label' => 'TG群组资源池字段 tg_channels',
            ],
            'tg_scan_limit' => [
                'sql' => "ALTER TABLE `%s` ADD COLUMN `tg_scan_limit` int(11) NOT NULL DEFAULT '5' COMMENT 'TG资源池每次搜索最多扫描的群组数' AFTER `tg_channels`",
                'label' => 'TG扫描群组数字段 tg_scan_limit',
            ],
        ];
    }

    private function getApiListTableName()
    {
        return (string)config('database.connections.mysql.prefix', '') . 'api_list';
    }

    private function checkApiListSchema()
    {
        $table = $this->getApiListTableName();
        $rows = Db::query("SHOW COLUMNS FROM `{$table}`");
        $exists = [];
        foreach ($rows as $row) {
            $field = $row['Field'] ?? ($row['field'] ?? '');
            if ($field !== '') {
                $exists[$field] = true;
            }
        }

        $columns = $this->getApiListSchemaColumns();
        $missing = [];
        $present = [];
        foreach ($columns as $field => $meta) {
            $item = [
                'field' => $field,
                'label' => $meta['label'],
            ];
            if (isset($exists[$field])) {
                $present[] = $item;
            } else {
                $missing[] = $item;
            }
        }

        return [
            'table' => $table,
            'ok' => empty($missing),
            'missing' => $missing,
            'present' => $present,
        ];
    }

    private function backfillApiListSchemaData()
    {
        $updated = [];
        $tgRows = $this->model->where('type', 'tg')->select();
        foreach ($tgRows as $row) {
            $item = is_array($row) ? $row : $row->toArray();
            $data = [];

            $panTypes = trim((string)($item['pan_types'] ?? ''));
            if ($panTypes === '') {
                $data['pan_types'] = implode(',', $this->normalizePanTypes($item['pantype'] ?? 0));
            }

            $channelsSource = $item['tg_channels'] ?? '';
            if (trim((string)$channelsSource) === '' && !preg_match('/^TG群组\s+\d+\s+个$/u', (string)($item['url'] ?? ''))) {
                $channelsSource = $item['url'] ?? '';
            }
            $channels = $this->normalizeTgChannelRows($channelsSource);
            if (!empty($channels)) {
                $data['tg_channels'] = json_encode($channels, JSON_UNESCAPED_UNICODE);
                $data['url'] = 'TG群组 ' . count($channels) . ' 个';
            }

            if (!isset($item['tg_scan_limit']) || intval($item['tg_scan_limit']) <= 0) {
                $data['tg_scan_limit'] = 5;
            }

            if (!empty($data)) {
                $data['update_time'] = time();
                $this->model->where('id', $item['id'])->update($data);
                $updated[] = [
                    'id' => $item['id'],
                    'name' => $item['name'] ?? '',
                    'fields' => array_keys($data),
                ];
            }
        }

        return $updated;
    }

    public function __construct(App $app)
    {
        parent::__construct($app);
        //查询列表时允许的字段
        $this->selectList = "*";
        //查询详情时允许的字段
        $this->selectDetail = "*";
        //筛选字段
        $this->searchFilter = [
        ];
        $this->insertFields = [
            //允许添加的字段列表
            "name","type","url","method","fixed_params","headers","field_map","status","weight","pantype","pan_types","tg_channels","tg_scan_limit","count","html_item","html_title","html_url","html_type","html_url2"
        ];
        $this->updateFields = [
            //允许更新的字段列表
            "name","type","url","method","fixed_params","headers","field_map","status","weight","pantype","pan_types","tg_channels","tg_scan_limit","count","html_item","html_title","html_url","html_type","html_url2"
        ];
        $this->insertRequire = [
            //添加时必须填写的字段
            // "字段名称"=>"该字段不能为空"
        ];
        $this->updateRequire = [
            //修改时必须填写的字段
            // "字段名称"=>"该字段不能为空"
            "name"=>"分类名称必须填写",
        ];
        $this->model = new ApiListModel();
    }

    public function checkSchema()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }

        try {
            return jok('结构检测完成', $this->checkApiListSchema());
        } catch (\Throwable $e) {
            return jerr('结构检测失败：' . $e->getMessage(), 500);
        }
    }

    public function repairSchema()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }

        try {
            $before = $this->checkApiListSchema();
            $table = $before['table'];
            $columns = $this->getApiListSchemaColumns();
            $fixed = [];

            foreach ($before['missing'] as $item) {
                $field = $item['field'];
                if (!isset($columns[$field])) {
                    continue;
                }
                Db::execute(sprintf($columns[$field]['sql'], $table));
                $fixed[] = $item;
            }

            $updatedRows = $this->backfillApiListSchemaData();
            $after = $this->checkApiListSchema();

            return jok('结构修复完成', [
                'before' => $before,
                'after' => $after,
                'fixed' => $fixed,
                'updated_rows' => $updatedRows,
            ]);
        } catch (\Throwable $e) {
            return jerr('结构修复失败：' . $e->getMessage(), 500);
        }
    }


    /**
     * 获取列表接口
     *
     * @return void
     */
    public function getList()
    {
        //校验Access与RBAC
        $error = $this->access();
        if ($error) {
            return $error;
        }
        //查询数据
        $dataList = $this->model->order('weight', 'desc')->select();
        return jok('数据获取成功', $dataList);
    }

    /**
     * 获取详情基类 子类自动继承 如有特殊需求 可重写到子类 请勿修改父类方法
     *
     * @return void
     */
    public function detail()
    {
        //校验Access与RBAC
        $error = $this->access();
        if ($error) {
            return $error;
        }
        $id = input("id");
        if (!$id) {
            return jerr("ID参数必须填写", 400);
        }
        //根据主键获取一行数据
        $item = $this->model->where("id", $id)->field($this->selectDetail)->find();
        if (empty($item)) {
            return jerr("没有查询到数据", 404);
        }
        return jok('数据加载成功', $item);
    }

    /**
     * 添加接口基类 子类自动继承 如有特殊需求 可重写到子类 请勿修改父类方法
     *
     * @return void
     */
    public function add()
    {
        //校验Access与RBAC
        $error = $this->access();
        if ($error) {
            return $error;
        }
        
        if (input('type') !== 'tg' && trim((string)input('name', '')) === '') {
            return jerr('线路名称必须填写', 400);
        }
        //从请求中获取Insert数据
        $data = $this->getInsertDataFromRequest();
        $data = $this->applyPanSouConfig($data);
        $data = $this->prepareTgData($data);

        if (($data['type'] ?? '') === 'tg') {
            $channels = $this->normalizeTgChannelRows($data['tg_channels'] ?? ($data['url'] ?? ''));
            if (empty($channels)) {
                return jerr('请填写TG群组', 400);
            }
            if (empty($data['name'])) {
                $data['name'] = 'TG资源池';
            }
            $exists = $this->model->where('name', $data['name'])->where('type', 'tg')->find();
            if ($exists) {
                $row = is_array($exists) ? $exists : $exists->toArray();
                $mergedPanTypes = $this->mergePanTypes($row['pan_types'] ?? $row['pantype'] ?? 0, $data['pan_types']);
                $oldChannels = $this->normalizeTgChannelRows($row['tg_channels'] ?? ($row['url'] ?? ''));
                $newChannels = $this->normalizeTgChannelRows($data['tg_channels']);
                $channelMap = [];
                foreach (array_merge($oldChannels, $newChannels) as $channelRow) {
                    $channelMap[$channelRow['channel']] = $channelRow;
                }
                $mergedChannels = array_values($channelMap);
                $this->model->where('id', $row['id'])->update([
                    'pan_types' => $mergedPanTypes,
                    'pantype' => intval(explode(',', $mergedPanTypes)[0]),
                    'tg_channels' => json_encode($mergedChannels, JSON_UNESCAPED_UNICODE),
                    'url' => 'TG群组 ' . count($mergedChannels) . ' 个',
                    'update_time' => time(),
                ]);
                $this->clearSearchCache();
                return jok('该TG线路名称已存在，已合并群组和网盘类型');
            }
        }
        
        //添加这行数据
        $data['create_time'] = time();
        $data['update_time'] = time();
        $this->model->insertGetId($data);
        $this->clearSearchCache();
        return jok('添加成功');
    }
    
    /**
     * 修改接口基类 子类自动继承 如有特殊需求 可重写到子类 请勿修改父类方法
     *
     * @return void
     */
    public function update()
    {
        //校验Access与RBAC
        $error = $this->access();
        if ($error) {
            return $error;
        }
        $id = input("id");
        if (!$id) {
            return jerr("ID参数必须填写", 400);
        }
        
        //根据主键获取一行数据
        $item = $this->model->where("id", $id)->field($this->selectDetail)->find();
        if (empty($item)) {
            return jerr("数据查询失败", 404);
        }
        
        //校验Update字段是否填写
        $error  = $this->validateUpdateFields();
        if ($error) {
            return $error;
        }
        //从请求中获取Update数据
        $data = $this->getUpdateDataFromRequest();
        $data = $this->applyPanSouConfig($data);
        $data = $this->prepareTgData($data);
        //根据主键更新这条数据
        $data['update_time'] = time();
        $this->model->where("id", $id)->update($data);
        $this->clearSearchCache();
        return jok('修改成功');
    }

    public function test()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }

        $type = input('type', 'api');
        if (!in_array($type, SearchLineService::supportedTypes(), true)) {
            return jerr('仅支持 API/PanSou/TG/HTML 类型测试，KK 线路已停用', 400);
        }

        $url = trim((string)input('url', ''));
        if ($url === '') {
            return jerr('接口地址不能为空', 400);
        }

        $line = [
            'type' => $type,
            'url' => $url,
            'method' => input('method', 'GET'),
            'fixed_params' => input('fixed_params', ''),
            'headers' => input('headers', ''),
            'field_map' => input('field_map', ''),
            'pantype' => intval(input('pantype', 0)),
            'count' => intval(input('count', 5)),
            'html_item' => input('html_item', ''),
            'html_title' => input('html_title', ''),
            'html_url' => input('html_url', ''),
            'html_type' => intval(input('html_type', 0)),
            'html_url2' => input('html_url2', ''),
            'num' => intval(input('num', 0)),
        ];
        $line = $this->prepareTestLine($line);

        $keyword = trim((string)input('keyword', '仙逆'));
        if ($keyword === '') {
            $keyword = '仙逆';
        }

        try {
            $result = $this->executeTestByType($line, $keyword);
            return jok('测试成功', [
                'total' => count($result),
                'list' => array_slice($result, 0, 3),
            ]);
        } catch (\Throwable $e) {
            return jerr('测试失败：' . $e->getMessage(), 400);
        }
    }

    public function panSouHealth()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }

        $url = trim((string)input('url', ''));
        if ($url === '') {
            return jerr('请先填写 PanSou 地址', 400);
        }

        $headers = json_decode(input('headers', ''), true);
        $headers = is_array($headers) ? $headers : [];
        $token = input('pansou_token', '');
        if (trim((string)$token) !== '') {
            $headers['Authorization'] = $this->normalizePanSouToken($token);
        } elseif (!empty($headers['Authorization'])) {
            $headers['Authorization'] = $this->normalizePanSouToken($headers['Authorization']);
        }

        $healthUrl = $this->normalizePanSouHealthUrl($url);
        $headerArr = [];
        foreach ($headers as $k => $v) {
            $k = trim((string)$k);
            $v = trim((string)$v);
            if ($k !== '' && $v !== '') {
                $headerArr[] = $k . ': ' . $v;
            }
        }
        $headerArr[] = 'Accept: application/json, text/plain, */*';

        $start = microtime(true);
        $response = $this->performCurlHealthRequest($healthUrl, 'GET', $headerArr, null);
        $delayMs = intval((microtime(true) - $start) * 1000);
        if (!$response['ok']) {
            return jerr('PanSou健康检查失败：' . $response['error_message'], 400);
        }

        $body = $response['body'] ?? '';
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return jerr('PanSou健康检查返回不是JSON', 400);
        }

        return jok('PanSou健康检查成功', [
            'url' => $healthUrl,
            'delay_ms' => max(1, $delayMs),
            'auth_enabled' => $this->extractPanSouAuthEnabled($data),
            'plugins' => $this->extractPanSouListFromHealth($data, ['plugins', 'plugin_list', 'available_plugins']),
            'channels' => $this->extractPanSouListFromHealth($data, ['channels', 'channel_list', 'available_channels']),
            'raw' => $data,
        ]);
    }

    public function testLatency()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }
        $id = intval(input('id', 0));
        if ($id <= 0) {
            return jerr('ID参数必须填写', 400);
        }
        $item = $this->model->where('id', $id)->field($this->selectDetail)->find();
        if (empty($item)) {
            return jerr('数据查询失败', 404);
        }
        $row = is_array($item) ? $item : $item->toArray();
        $result = $this->runLatencyTestForRow($row, input('keyword', '仙逆'));
        return jok('延迟测试完成', $result);
    }

    public function batchTestLatency()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }
        $ids = input('ids', []);
        if (!$ids || !is_array($ids)) {
            return jerr('请选择要测试的项目', 400);
        }
        $list = $this->model->where('id', 'in', $ids)->field($this->selectDetail)->select();
        $rowMap = [];
        foreach ($list as $item) {
            $row = is_array($item) ? $item : $item->toArray();
            $rowMap[intval($row['id'])] = $row;
        }
        $resultList = [];
        $successCount = 0;
        $failedCount = 0;
        $keyword = input('keyword', '仙逆');
        foreach ($ids as $id) {
            $intId = intval($id);
            if (!isset($rowMap[$intId])) {
                $resultList[] = [
                    'id' => $intId,
                    'name' => '',
                    'type' => '',
                    'delay_ms' => 0,
                    'status' => 'failed',
                    'error_message' => '线路不存在',
                    'test_time' => date('Y-m-d H:i:s'),
                ];
                $failedCount++;
                continue;
            }
            $itemResult = $this->runLatencyTestForRow($rowMap[$intId], $keyword);
            $resultList[] = $itemResult;
            if ($itemResult['status'] === 'ok') {
                $successCount++;
            } else {
                $failedCount++;
            }
        }
        return jok('批量延迟测试完成', [
            'total' => count($resultList),
            'success' => $successCount,
            'failed' => $failedCount,
            'list' => $resultList,
        ]);
    }

    private function prepareTestLine($line)
    {
        $line = $this->applyPanSouConfig($line);
        if (($line['count'] ?? 0) <= 0) {
            $line['count'] = 5;
        }
        return $line;
    }

    private function executeTestByType($line, $keyword)
    {
        $service = new SearchLineService($this->app);
        return $service->search($line, $keyword);
    }

    private function runLatencyTestForRow($row, $keyword)
    {
        $line = $this->prepareTestLine($row);
        $status = 'ok';
        $errorMessage = '';
        $delayMs = 0;
        try {
            $healthResult = $this->performHealthRequest($line, trim((string)$keyword) === '' ? '仙逆' : trim((string)$keyword));
            $delayMs = intval($healthResult['delay_ms'] ?? 0);
            if (!$healthResult['ok']) {
                $status = 'failed';
                $errorMessage = $healthResult['error_message'];
            }
        } catch (\Throwable $e) {
            $status = 'failed';
            $errorMessage = $e->getMessage();
            $delayMs = 0;
        }
        return [
            'id' => intval($row['id'] ?? 0),
            'name' => $row['name'] ?? '',
            'type' => $line['type'] ?? '',
            'delay_ms' => $delayMs,
            'status' => $status,
            'error_message' => $errorMessage,
            'test_time' => date('Y-m-d H:i:s'),
        ];
    }

    private function performHealthRequest($line, $keyword)
    {
        $request = $this->buildHealthRequest($line, $keyword);
        $response = $this->sendHealthProbe($request);
        if (!$response['ok']) {
            return [
                'ok' => false,
                'delay_ms' => $response['delay_ms'],
                'error_message' => $response['error_message'],
            ];
        }
        return [
            'ok' => true,
            'delay_ms' => $response['delay_ms'],
            'error_message' => '',
        ];
    }

    private function buildHealthRequest($line, $keyword)
    {
        $type = $line['type'] ?? 'api';
        if ($type === 'tg') {
            $channels = $this->normalizeTgChannelRows($line['tg_channels'] ?? ($line['url'] ?? ''));
            $channel = '';
            foreach ($channels as $item) {
                if (intval($item['enabled'] ?? 1) === 1) {
                    $channel = $item['channel'];
                    break;
                }
            }
            if ($channel === '' && !empty($channels)) {
                $channel = $channels[0]['channel'];
            }
            return [
                'url' => 'https://t.me/s/' . $channel,
                'method' => 'GET',
                'query' => ['q' => $keyword],
                'headers' => [],
                'body' => null,
            ];
        }
        $url = trim((string)($line['url'] ?? ''));
        if ($type === 'html') {
            $url = str_replace('{keyword}', urlencode($keyword), $url);
            return [
                'url' => $url,
                'method' => 'GET',
                'query' => [],
                'headers' => [],
                'body' => null,
            ];
        }
        $method = strtoupper(trim((string)($line['method'] ?? 'GET')));
        if (!in_array($method, ['GET', 'POST'])) {
            $method = 'GET';
        }
        $headers = json_decode($line['headers'] ?? '', true);
        $headers = is_array($headers) ? $headers : [];
        $params = json_decode($line['fixed_params'] ?? '', true);
        $params = is_array($params) ? $params : [];
        foreach ($params as $k => $v) {
            if (is_string($v)) {
                $params[$k] = str_replace('{keyword}', $keyword, $v);
            }
        }
        $headerArr = [];
        $contentType = '';
        foreach ($headers as $k => $v) {
            $headerArr[] = $k . ': ' . $v;
            if (strtolower(trim((string)$k)) === 'content-type') {
                $contentType = strtolower(trim((string)$v));
            }
        }
        if ($method === 'POST' && $contentType === '') {
            $contentType = 'application/x-www-form-urlencoded';
            $headerArr[] = 'Content-Type: application/x-www-form-urlencoded';
        }
        $body = null;
        $query = [];
        if ($method === 'GET') {
            $query = $params;
        } elseif (!empty($params)) {
            if (strpos($contentType, 'application/json') !== false) {
                $body = json_encode($params, JSON_UNESCAPED_UNICODE);
            } else {
                $body = http_build_query($params);
            }
        }
        return [
            'url' => $url,
            'method' => $method,
            'query' => $query,
            'headers' => $headerArr,
            'body' => $body,
        ];
    }

    private function sendHealthProbe($request)
    {
        $url = $request['url'] ?? '';
        $query = $request['query'] ?? [];
        if (!empty($query) && is_array($query)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query);
        }
        if ($url === '') {
            return [
                'ok' => false,
                'delay_ms' => 0,
                'error_message' => '接口地址不能为空',
            ];
        }
        $headResult = $this->performCurlHealthRequest($url, 'HEAD', $request['headers'] ?? [], null);
        if ($headResult['ok']) {
            return [
                'ok' => true,
                'delay_ms' => $headResult['delay_ms'],
                'error_message' => '',
            ];
        }
        $method = strtoupper(trim((string)($request['method'] ?? 'GET')));
        if ($method === '' || $method === 'HEAD') {
            return [
                'ok' => false,
                'delay_ms' => $headResult['delay_ms'],
                'error_message' => $headResult['error_message'],
            ];
        }
        $methodResult = $this->performCurlHealthRequest($url, $method, $request['headers'] ?? [], $request['body'] ?? null);
        if ($methodResult['ok']) {
            return [
                'ok' => true,
                'delay_ms' => $methodResult['delay_ms'],
                'error_message' => '',
            ];
        }
        $fallbackDelay = $methodResult['delay_ms'] > 0 ? $methodResult['delay_ms'] : $headResult['delay_ms'];
        $fallbackError = $methodResult['error_message'] !== '' ? $methodResult['error_message'] : $headResult['error_message'];
        return [
            'ok' => false,
            'delay_ms' => $fallbackDelay,
            'error_message' => $fallbackError,
        ];
    }

    private function performCurlHealthRequest($url, $method, $headers, $body)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        if ($method === 'HEAD') {
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
        } elseif ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 2000);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 5000);
        if (!empty($headers) && is_array($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        $rawResponse = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        $headerSize = intval(curl_getinfo($ch, CURLINFO_HEADER_SIZE));
        $delayMs = intval(floatval(curl_getinfo($ch, CURLINFO_TOTAL_TIME)) * 1000);
        curl_close($ch);
        $body = is_string($rawResponse) ? substr($rawResponse, $headerSize) : '';
        if ($delayMs < 1) {
            $delayMs = 1;
        }
        if ($error !== '') {
            return [
                'ok' => false,
                'http_code' => $httpCode,
                'delay_ms' => $delayMs,
                'body' => $body,
                'error_message' => $error,
            ];
        }
        if (($httpCode >= 200 && $httpCode < 400) || ($method === 'HEAD' && $httpCode === 405)) {
            return [
                'ok' => true,
                'http_code' => $httpCode,
                'delay_ms' => $delayMs,
                'body' => $body,
                'error_message' => '',
            ];
        }
        return [
            'ok' => false,
            'http_code' => $httpCode,
            'delay_ms' => $delayMs,
            'body' => $body,
            'error_message' => 'HTTP状态码：' . $httpCode,
        ];
    }

    private function extractPanSouAuthEnabled($data)
    {
        foreach ([
            ['auth_enabled'],
            ['data', 'auth_enabled'],
            ['auth', 'enabled'],
            ['data', 'auth', 'enabled'],
        ] as $path) {
            $value = $this->getArrayValueByPath($data, $path);
            if ($value !== null) {
                return (bool)$value;
            }
        }
        return false;
    }

    private function extractPanSouListFromHealth($data, $keys)
    {
        $candidates = [];
        foreach ($keys as $key) {
            $candidates[] = [$key];
            $candidates[] = ['data', $key];
        }
        foreach ($candidates as $path) {
            $value = $this->getArrayValueByPath($data, $path);
            $list = $this->normalizePanSouHealthList($value);
            if (!empty($list)) {
                return $list;
            }
        }
        return [];
    }

    private function getArrayValueByPath($data, $path)
    {
        $value = $data;
        foreach ($path as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }
        return $value;
    }

    private function normalizePanSouHealthList($value)
    {
        if (!is_array($value)) {
            return [];
        }
        $result = [];
        foreach ($value as $key => $item) {
            if (is_string($item)) {
                $id = $item;
                $name = $item;
                $enabled = true;
            } elseif (is_array($item)) {
                $id = $item['id'] ?? ($item['name'] ?? ($item['key'] ?? (is_string($key) ? $key : '')));
                $name = $item['name'] ?? ($item['title'] ?? $id);
                $enabled = isset($item['enabled']) ? (bool)$item['enabled'] : true;
            } else {
                continue;
            }
            $id = trim((string)$id);
            if ($id === '') {
                continue;
            }
            $result[] = [
                'id' => $id,
                'name' => trim((string)$name) ?: $id,
                'enabled' => $enabled,
            ];
        }
        return $result;
    }

    /**
     * 删除接口基类 子类自动继承 如有特殊需求 可重写到子类 请勿修改父类方法
     *
     * @return void
     */
    public function delete()
    {
        //校验Access与RBAC
        $error = $this->access();
        if ($error) {
            return $error;
        }
        $id = input("id");
        if (!$id) {
            return jerr("ID参数必须填写", 400);
        }
        
        //根据主键获取一行数据
        $item = $this->model->where("id", $id)->field($this->selectDetail)->find();
        
        if (empty($item)) {
            return jerr("数据查询失败", 404);
        }
        
        //单个操作
        $map = ["id" => $id];
        $this->model->where($map)->delete();
        $this->clearSearchCache();
       
        return jok('删除成功');
    }


    /**
     * 禁用接口基类 子类自动继承 如有特殊需求 可重写到子类 请勿修改父类方法
     *
     * @return void
     */
    public function disable()
    {
        //校验Access与RBAC
        $error = $this->access();
        if ($error) {
            return $error;
        }
        $id = input("id");
        if (!$id) {
            return jerr("ID参数必须填写", 400);
        }

        
        $d = [
            "status" => 0,
            "update_time" => time(),
        ];

        if (isInteger($id)) {
            //根据主键获取一行数据
            $item = $this->model->where("id", $id)->field($this->selectDetail)->find();
            if (empty($item)) {
                return jerr("数据查询失败", 404);
            }
            //单个操作
            $map = ["id" => $id];
            $this->model->where($map)->update($d);
        } else {
            //批量操作
            $list = explode(',', $id);
            $this->model->where("id", 'in', $list)->update($d);
        }
        $this->clearSearchCache();
        return jok("禁用成功");
    }


    /**
     * 启用接口基类 子类自动继承 如有特殊需求 可重写到子类 请勿修改父类方法
     *
     * @return void
     */
    public function enable()
    {
        //校验Access与RBAC
        $error = $this->access();
        if ($error) {
            return $error;
        }
        $id = input("id");
        if (!$id) {
            return jerr("ID参数必须填写", 400);
        }

        $d = [
            "status" => 1,
            "update_time" => time(),
        ];

        if (isInteger($id)) {
            //根据主键获取一行数据
            $item = $this->model->where("id", $id)->field($this->selectDetail)->find();
            if (empty($item)) {
                return jerr("数据查询失败", 404);
            }
            //单个操作
            $map = ["id" => $id];
            $this->model->where($map)->update($d);
        } else {
            //批量操作
            $list = explode(',', $id);
            $this->model->where("id", 'in', $list)->update($d);
        }
        $this->clearSearchCache();
        return jok("启用成功");
    }

    /**
     * 批量删除接口配置
     *
     * @return array
     */
    public function batchDelete()
    {
        //校验Access与RBAC
        $error = $this->access();
        if ($error) {
            return $error;
        }
        
        $ids = input("ids");
        if (!$ids || !is_array($ids)) {
            return jerr("请选择要删除的项目", 400);
        }
        
        // 验证所有ID是否存在
        $count = $this->model->where("id", 'in', $ids)->count();
        if ($count != count($ids)) {
            return jerr("部分数据不存在", 404);
        }
        
        // 执行批量删除
        $this->model->where("id", 'in', $ids)->delete();
        $this->clearSearchCache();
        
        return jok("批量删除成功，共删除了 " . count($ids) . " 个配置");
    }

    /**
     * 导入JSON配置
     *
     * @return array
     */
    public function import()
    {
        //校验Access与RBAC
        $error = $this->access();
        if ($error) {
            return $error;
        }
        
        $data = input("data");
        $overwrite = input("overwrite", false);
        
        if (!$data || !is_array($data)) {
            return jerr("导入数据格式错误", 400);
        }
        
        $successCount = 0;
        $skipCount = 0;
        $errorCount = 0;
        $errors = [];
        
        foreach ($data as $item) {
            try {
                // 验证必要字段
                if (empty($item['name'])) {
                    if (($item['type'] ?? 'api') === 'tg' && !empty($item['url'])) {
                        $item['name'] = $item['url'];
                    } else {
                        $errors[] = "线路名称不能为空";
                        $errorCount++;
                        continue;
                    }
                }
                
                // 检查是否已存在相同配置
                $itemType = $item['type'] ?? 'api';
                if ($itemType === 'tg') {
                    $item['pan_types'] = implode(',', $this->normalizePanTypes($item['pan_types'] ?? [], $item['pantype'] ?? 0));
                    $item['pantype'] = intval(explode(',', $item['pan_types'])[0]);
                    $item['tg_channels'] = json_encode($this->normalizeTgChannelRows($item['tg_channels'] ?? ($item['url'] ?? '')), JSON_UNESCAPED_UNICODE);
                    $channelRows = $this->normalizeTgChannelRows($item['tg_channels']);
                    $item['url'] = 'TG群组 ' . count($channelRows) . ' 个';
                    $exists = $this->model->where('type', 'tg')
                                         ->where('name', $item['name'])
                                         ->find();
                } else if ($itemType === 'pansou') {
                    $item = $this->applyPanSouConfig($item);
                    $exists = $this->model->where('type', 'pansou')
                                         ->where('name', $item['name'])
                                         ->find();
                } else {
                    $exists = $this->model->where('name', $item['name'])
                                         ->where('pantype', $item['pantype'] ?? 0)
                                         ->find();
                }
                
                if ($exists && !$overwrite) {
                    if ($itemType === 'tg') {
                        $row = is_array($exists) ? $exists : $exists->toArray();
                        $mergedPanTypes = $this->mergePanTypes($row['pan_types'] ?? $row['pantype'] ?? 0, $item['pan_types']);
                        $this->model->where('id', $row['id'])->update([
                            'pan_types' => $mergedPanTypes,
                            'pantype' => intval(explode(',', $mergedPanTypes)[0]),
                            'tg_channels' => $item['tg_channels'],
                            'url' => $item['url'],
                            'update_time' => time(),
                        ]);
                        $successCount++;
                    } else {
                        $skipCount++;
                    }
                    continue;
                }
                
                // 准备数据
                $insertData = [
                    'name' => $item['name'],
                    'type' => $item['type'] ?? 'api',
                    'url' => $item['url'] ?? '',
                    'method' => $item['method'] ?? 'GET',
                    'fixed_params' => $item['fixed_params'] ?? '',
                    'headers' => $item['headers'] ?? '',
                    'field_map' => $item['field_map'] ?? '',
                    'status' => isset($item['status']) ? ($item['status'] ? 1 : 0) : 1,
                    'weight' => $item['weight'] ?? 0,
                    'pantype' => $item['pantype'] ?? 0,
                    'pan_types' => $item['pan_types'] ?? '',
                    'tg_channels' => $item['tg_channels'] ?? '',
                    'count' => $item['count'] ?? 5,
                    'html_item' => $item['html_item'] ?? '',
                    'html_title' => $item['html_title'] ?? '',
                    'html_url' => $item['html_url'] ?? '',
                    'html_type' => $item['html_type'] ?? 0,
                    'html_url2' => $item['html_url2'] ?? '',
                    'create_time' => time(),
                    'update_time' => time()
                ];
                
                if ($exists && $overwrite) {
                    if ($itemType === 'tg' || $itemType === 'pansou') {
                        $insertData['pan_types'] = $this->mergePanTypes($exists['pan_types'] ?? $exists['pantype'] ?? 0, $insertData['pan_types']);
                        $insertData['pantype'] = intval(explode(',', $insertData['pan_types'])[0]);
                    }
                    // 更新现有配置
                    $this->model->where('id', $exists['id'])->update($insertData);
                } else {
                    // 添加新配置
                    $this->model->insertGetId($insertData);
                }
                
                $successCount++;
                
            } catch (\Exception $e) {
                $errors[] = "导入 {$item['name']} 失败：" . $e->getMessage();
                $errorCount++;
            }
        }
        
        $message = "导入完成！成功：{$successCount} 个";
        if ($skipCount > 0) {
            $message .= "，跳过：{$skipCount} 个";
        }
        if ($errorCount > 0) {
            $message .= "，失败：{$errorCount} 个";
        }
        
        if (!empty($errors)) {
            $message .= "\n错误详情：" . implode("；", array_slice($errors, 0, 5));
            if (count($errors) > 5) {
                $message .= "等...";
            }
        }
        
        if ($successCount > 0) {
            $this->clearSearchCache();
        }

        return jok($message);
    }

    private function clearSearchCache()
    {
        try {
            Db::name('search_cache')->where('id', '>', 0)->delete();
        } catch (\Throwable $e) {
            // 搜索缓存清理失败不影响线路配置主流程。
        }
    }

}
