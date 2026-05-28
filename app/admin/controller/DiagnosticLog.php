<?php

namespace app\admin\controller;

use think\App;
use think\facade\Cache;
use think\facade\Db;
use app\admin\QfShop;
use app\service\DiagnosticLogService;

class DiagnosticLog extends QfShop
{
    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    public function getList()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }

        DiagnosticLogService::ensureTable();

        $page = max(1, intval(input('page', 1)));
        $perPage = intval(input('per_page', 20));
        if ($perPage <= 0 || $perPage > 200) {
            $perPage = 20;
        }

        $filters = [
            'module' => trim((string)input('module', '')),
            'status' => trim((string)input('status', '')),
            'action' => trim((string)input('action', '')),
            'line_name' => trim((string)input('line_name', '')),
            'keyword' => trim((string)input('keyword', '')),
            'url_hash' => trim((string)input('url_hash', '')),
            'is_type' => trim((string)input('is_type', '')),
            'start_time' => trim((string)input('start_time', '')),
            'end_time' => trim((string)input('end_time', '')),
            'min_duration' => intval(input('min_duration', 0)),
        ];

        $query = $this->buildQuery($filters);
        $summary = $this->getSummary($filters);

        $dataList = $query->order('id desc')->paginate([
            'list_rows' => $perPage,
            'page' => $page,
        ])->toArray();

        foreach ($dataList['data'] as &$item) {
            $item['create_time_text'] = !empty($item['create_time']) ? date('Y-m-d H:i:s', $item['create_time']) : '';
            $item['is_type_text'] = $this->formatPanType($item['is_type']);
        }
        $dataList['summary'] = $summary;

        return jok('数据获取成功', $dataList);
    }

    public function getConfig()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }

        $this->ensureConfigRow();
        $enabled = (string)Db::name('conf')->where('conf_key', 'diagnostic_log_enable')->value('conf_value') === '1';
        $stats = $this->getStorageStats();

        return jok('配置获取成功', [
            'enabled' => $enabled ? 1 : 0,
            'log_count' => $stats['log_count'],
            'table_size' => $stats['table_size'],
            'table_size_human' => $stats['table_size_human'],
        ]);
    }

    public function saveConfig()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }

        $enabled = (int)input('enabled', 0) === 1 ? '1' : '0';
        $this->ensureConfigRow();
        Db::name('conf')->where('conf_key', 'diagnostic_log_enable')->update([
            'conf_value' => $enabled,
            'conf_updatetime' => time(),
        ]);
        DiagnosticLogService::setEnabled($enabled === '1');
        Cache::delete('system_config_all');
        Cache::set('system_config_version', time(), 300);

        return jok($enabled === '1' ? '诊断日志记录已开启' : '诊断日志记录已关闭');
    }

    private function buildQuery($filters)
    {
        $query = Db::name('diagnostic_log');
        if ($filters['module'] !== '') {
            $query = $query->where('module', $filters['module']);
        }
        if ($filters['status'] !== '') {
            $query = $query->where('status', $filters['status']);
        }
        if ($filters['action'] !== '') {
            $query = $query->whereLike('action', '%' . $filters['action'] . '%');
        }
        if ($filters['line_name'] !== '') {
            $query = $query->whereLike('line_name', '%' . $filters['line_name'] . '%');
        }
        if ($filters['keyword'] !== '') {
            $query = $query->whereLike('keyword|message|line_name|url_preview|action', '%' . $filters['keyword'] . '%');
        }
        if ($filters['url_hash'] !== '') {
            $query = $query->where('url_hash', $filters['url_hash']);
        }
        if ($filters['is_type'] !== '') {
            $query = $query->where('is_type', intval($filters['is_type']));
        }
        if ($filters['start_time'] !== '') {
            $start = strtotime($filters['start_time']);
            if ($start) {
                $query = $query->where('create_time', '>=', $start);
            }
        }
        if ($filters['end_time'] !== '') {
            $end = strtotime($filters['end_time']);
            if ($end) {
                $query = $query->where('create_time', '<=', $end);
            }
        }
        if ($filters['min_duration'] > 0) {
            $query = $query->where('duration_ms', '>=', $filters['min_duration']);
        }
        return $query;
    }

    private function getSummary($filters)
    {
        $base = $this->buildQuery($filters);
        $total = (clone $base)->count();
        $statusRows = (clone $base)->field('status, count(*) as total')->group('status')->select()->toArray();
        $moduleRows = (clone $base)->field('module, count(*) as total')->group('module')->select()->toArray();
        $errorCount = 0;
        $warnCount = 0;
        foreach ($statusRows as $row) {
            if ($row['status'] === 'error') {
                $errorCount = intval($row['total']);
            } elseif ($row['status'] === 'warn') {
                $warnCount = intval($row['total']);
            }
        }
        return [
            'total' => intval($total),
            'error' => $errorCount,
            'warn' => $warnCount,
            'status' => $statusRows,
            'module' => $moduleRows,
        ];
    }

    private function formatPanType($isType)
    {
        $map = [
            -1 => '-',
            0 => '夸克',
            1 => '阿里',
            2 => '百度',
            3 => 'UC',
            4 => '迅雷',
        ];
        $key = intval($isType);
        return isset($map[$key]) ? $map[$key] : (string)$isType;
    }

    public function clean()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }

        DiagnosticLogService::ensureTable();
        $days = intval(input('days', 7));
        if ($days <= 0) {
            Db::name('diagnostic_log')->where('id', '>', 0)->delete();
            return jok('诊断日志已清空');
        }

        $time = time() - ($days * 86400);
        $count = Db::name('diagnostic_log')->where('create_time', '<', $time)->delete();
        return jok('已清理 ' . $days . ' 天前诊断日志', ['count' => $count]);
    }

    private function ensureConfigRow()
    {
        $exists = Db::name('conf')->where('conf_key', 'diagnostic_log_enable')->find();
        if ($exists) {
            return;
        }

        $now = time();
        Db::name('conf')->insert([
            'conf_key' => 'diagnostic_log_enable',
            'conf_value' => '0',
            'conf_title' => '诊断日志记录开关',
            'conf_desc' => '仅调试搜索、转存、目录树问题时开启，关闭后不再写入诊断日志',
            'conf_status' => 0,
            'conf_type' => 3,
            'conf_spec' => 0,
            'conf_content' => "关闭=>0\n开启=>1",
            'conf_sort' => 0,
            'conf_system' => 0,
            'conf_createtime' => $now,
            'conf_updatetime' => $now,
        ]);
    }

    private function getStorageStats()
    {
        $count = 0;
        $bytes = 0;
        try {
            DiagnosticLogService::ensureTable();
            $count = (int)Db::name('diagnostic_log')->count();
            $prefix = (string)config('database.connections.mysql.prefix', '');
            $table = $prefix . 'diagnostic_log';
            $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            $rows = Db::query(
                "SELECT IFNULL(SUM(data_length + index_length), 0) AS bytes FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '" . $table . "'"
            );
            $bytes = isset($rows[0]['bytes']) ? (int)$rows[0]['bytes'] : 0;
        } catch (\Throwable $e) {
            $count = 0;
            $bytes = 0;
        }

        return [
            'log_count' => $count,
            'table_size' => $bytes,
            'table_size_human' => $this->formatBytes($bytes),
        ];
    }

    private function formatBytes($bytes)
    {
        $bytes = max(0, (float)$bytes);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;
        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes = $bytes / 1024;
            $index++;
        }
        return round($bytes, $index === 0 ? 0 : 2) . ' ' . $units[$index];
    }
}
