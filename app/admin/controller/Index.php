<?php

namespace app\admin\controller;

use app\admin\QfShop;
use think\facade\Config;
use think\facade\Cache;
use think\facade\Db;
use util\Time;

class Index extends QfShop
{
    public function index()
    {
        // 系统信息
        $systemInfo = [
            'os' => PHP_OS,
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'think_version' => \think\facade\App::version(),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
        ];

        // 数据库版本
        try {
            $res = Db::query('SELECT VERSION() as ver');
            $systemInfo['mysql_version'] = $res[0]['ver'] ?? 'Unknown';
            $sizeRows = Db::query('SELECT IFNULL(SUM(data_length + index_length), 0) AS bytes FROM information_schema.tables WHERE table_schema = DATABASE()');
            $bytes = isset($sizeRows[0]['bytes']) ? (int)$sizeRows[0]['bytes'] : 0;
            $systemInfo['database_size'] = $this->formatBytes($bytes);
        } catch (\Exception $e) {
            $systemInfo['mysql_version'] = '获取失败';
            $systemInfo['database_size'] = '获取失败';
        }

        $cacheDriver = strtolower((string)Db::name('conf')->where('conf_key', 'cache_driver')->value('conf_value'));
        if (!in_array($cacheDriver, ['file', 'redis', 'memcached'], true)) {
            $cacheDriver = 'file';
        }
        $cacheStatus = '运行正常';
        if ($cacheDriver === 'redis') {
            if (!extension_loaded('redis')) {
                $cacheStatus = 'Redis扩展未安装';
            } else {
                try {
                    $host = Db::name('conf')->where('conf_key', 'redis_host')->value('conf_value') ?: '127.0.0.1';
                    $port = Db::name('conf')->where('conf_key', 'redis_port')->value('conf_value') ?: '6379';
                    $auth = Db::name('conf')->where('conf_key', 'redis_password')->value('conf_value');
                    $redis = new \Redis();
                    $redis->connect($host, (int)$port, 2);
                    if ($auth) {
                        $redis->auth($auth);
                    }
                    $redis->ping();
                } catch (\Exception $e) {
                    $cacheStatus = 'Redis连接失败';
                }
            }
        } elseif ($cacheDriver === 'memcached') {
            if (!extension_loaded('memcached')) {
                $cacheStatus = 'Memcached扩展未安装';
            } else {
                try {
                    $host = Db::name('conf')->where('conf_key', 'memcached_host')->value('conf_value') ?: '127.0.0.1';
                    $port = (int)(Db::name('conf')->where('conf_key', 'memcached_port')->value('conf_value') ?: 11211);
                    $memcached = new \Memcached();
                    $memcached->addServer($host, $port);
                    $version = $memcached->getVersion();
                    $versionItem = is_array($version) && !empty($version) ? reset($version) : false;
                    if ($versionItem === false || $versionItem === '0.0.0') {
                        $cacheStatus = 'Memcached连接失败';
                    }
                } catch (\Exception $e) {
                    $cacheStatus = 'Memcached连接失败';
                }
            }
        } else {
            $cachePath = root_path() . 'runtime/cache';
            if (!is_dir($cachePath) || !is_writable($cachePath)) {
                $cacheStatus = '文件缓存目录不可写';
            }
        }
        $systemInfo['cache_driver'] = strtoupper($cacheDriver);
        $systemInfo['cache_status'] = $cacheStatus;
        $systemInfo['redis_status'] = $cacheStatus;

        \think\facade\View::assign('system_info', $systemInfo);
        return \think\facade\View::fetch();
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
