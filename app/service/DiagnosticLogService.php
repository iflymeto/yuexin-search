<?php

namespace app\service;

use think\facade\Db;

class DiagnosticLogService
{
    private static $tableReady = false;
    private static $enabled = null;

    public static function record($module, $action, $status, $message, array $context = [])
    {
        try {
            if (!self::isEnabled()) {
                return;
            }

            $url = isset($context['url']) ? (string)$context['url'] : '';
            $urlPreview = self::maskUrl($url);
            $urlHash = $url !== '' ? md5($url) : '';
            unset($context['url']);
            unset($context['share_url']);

            $now = time();
            $data = [
                'module' => self::cut($module, 30),
                'action' => self::cut($action, 60),
                'status' => self::cut($status, 20),
                'message' => self::cut($message, 500),
                'keyword' => self::cut(isset($context['keyword']) ? $context['keyword'] : '', 255),
                'is_type' => isset($context['is_type']) ? intval($context['is_type']) : -1,
                'line_name' => self::cut(isset($context['line_name']) ? $context['line_name'] : '', 120),
                'url_hash' => $urlHash,
                'url_preview' => self::cut($urlPreview, 500),
                'duration_ms' => isset($context['duration_ms']) ? intval($context['duration_ms']) : 0,
                'context' => self::encodeContext($context),
                'create_time' => $now,
                'update_time' => $now,
            ];
            try {
                self::insert($data);
            } catch (\Throwable $e) {
                self::ensureTable();
                self::insert($data);
            }
        } catch (\Throwable $e) {
            // 诊断日志不能影响搜索、转存和目录树主流程。
        }
    }

    public static function isEnabled()
    {
        if (self::$enabled !== null) {
            return self::$enabled;
        }

        try {
            $value = Db::name('conf')->where('conf_key', 'diagnostic_log_enable')->value('conf_value');
            self::$enabled = (string)$value === '1';
        } catch (\Throwable $e) {
            self::$enabled = false;
        }
        return self::$enabled;
    }

    public static function setEnabled($enabled)
    {
        self::$enabled = (bool)$enabled;
    }

    private static function insert(array $data)
    {
        Db::name('diagnostic_log')->insert($data);
    }

    public static function ensureTable()
    {
        if (self::$tableReady) {
            return;
        }

        $prefix = (string)config('database.connections.mysql.prefix', '');
        $table = $prefix . 'diagnostic_log';
        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
            `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
            `module` varchar(30) NOT NULL DEFAULT '' COMMENT '模块 search/transfer/pan_tree',
            `action` varchar(60) NOT NULL DEFAULT '' COMMENT '动作',
            `status` varchar(20) NOT NULL DEFAULT '' COMMENT '状态 info/success/warn/error',
            `message` varchar(500) NOT NULL DEFAULT '' COMMENT '摘要',
            `keyword` varchar(255) NOT NULL DEFAULT '' COMMENT '关键词',
            `is_type` tinyint(4) NOT NULL DEFAULT -1 COMMENT '网盘类型',
            `line_name` varchar(120) NOT NULL DEFAULT '' COMMENT '搜索线路',
            `url_hash` char(32) NOT NULL DEFAULT '' COMMENT '原链接哈希',
            `url_preview` varchar(500) NOT NULL DEFAULT '' COMMENT '脱敏链接预览',
            `duration_ms` int(11) NOT NULL DEFAULT 0 COMMENT '耗时毫秒',
            `context` text COMMENT '上下文JSON',
            `create_time` int(11) NOT NULL DEFAULT 0,
            `update_time` int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`) USING BTREE,
            KEY `idx_module_time` (`module`, `create_time`),
            KEY `idx_status_time` (`status`, `create_time`),
            KEY `idx_url_hash` (`url_hash`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='搜索转存目录树诊断日志'";
        Db::execute($sql);
        self::$tableReady = true;
    }

    private static function encodeContext(array $context)
    {
        $safe = self::sanitizeContext($context);
        $json = json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return '';
        }
        return mb_substr($json, 0, 5000, 'UTF-8');
    }

    private static function sanitizeContext($value)
    {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                if (in_array($key, ['url', 'share_url', 'content', 'password', 'cookie', 'token', 'stoken'], true)) {
                    $result[$key] = '[hidden]';
                    continue;
                }
                $result[$key] = self::sanitizeContext($item);
            }
            return $result;
        }
        if (is_string($value)) {
            return self::cut($value, 500);
        }
        return $value;
    }

    private static function maskUrl($url)
    {
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return self::cut($url, 120);
        }

        $scheme = isset($parts['scheme']) ? $parts['scheme'] : 'https';
        $path = isset($parts['path']) ? $parts['path'] : '';
        if (mb_strlen($path, 'UTF-8') > 60) {
            $path = mb_substr($path, 0, 60, 'UTF-8') . '...';
        }

        return $scheme . '://' . $parts['host'] . $path;
    }

    private static function cut($value, $length)
    {
        $value = is_scalar($value) ? (string)$value : '';
        if (mb_strlen($value, 'UTF-8') <= $length) {
            return $value;
        }
        return mb_substr($value, 0, $length, 'UTF-8');
    }
}
