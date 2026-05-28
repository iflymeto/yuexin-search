<?php

namespace app\qfadmin\controller;

use app\qfadmin\QfShop;
use think\facade\View;

class Error extends QfShop
{
    /**
     * 监听所有请求 渲染对应控制器下方法的页面
     */
    public function __call($method, $args)
    {
        // 判断是否是登录/注册/找回密码
        // 否则进行accesss授权验证 如错误 直接返回
        if (!(strtolower($this->controller) == "admin" && in_array(strtolower($this->action), ['login', 'resetpassword', 'reg']))) {
            $error = $this->access();
            if ($error) {
                return $error;
            }
        }else{
            cookie('access_token', null);
        }

        // 如果是首页，注入系统信息
        if (strtolower($this->controller) == 'index' && strtolower($this->action) == 'index') {
            $this->assignSystemInfo();
        }

        if (key_exists('callback', $args)) {
            View::assign('callback', $args['callback']);
        } else {
            View::assign('callback', '/qfadmin');
        }
        View::assign('datas', $args);
        return View::fetch();
    }

    /**
     * 注入系统信息
     */
    private function assignSystemInfo()
    {
        // 1. PHP信息
        $php = [
            'version' => PHP_VERSION,
            'sapi' => php_sapi_name(),
            'memory_limit' => ini_get('memory_limit'),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'opcache_enabled' => function_exists('opcache_get_status') && opcache_get_status(false)['opcache_enabled'] ?? false,
            'xdebug' => extension_loaded('xdebug'),
            'apcu' => extension_loaded('apcu'),
        ];

        // 计算PHP内存使用率
        $memory_limit_bytes = $this->return_bytes($php['memory_limit']);
        $php['memory_usage_percent'] = $memory_limit_bytes > 0 ? round(($php['memory_usage'] / $memory_limit_bytes) * 100, 1) : 0;
        $php['memory_usage_human'] = $this->formatBytes($php['memory_usage']);
        $php['peak_memory_human'] = $this->formatBytes($php['peak_memory']);

        // 2. 数据库信息
        $db = ['connected' => false, 'error' => '未连接'];
        try {
            // 使用 ThinkPHP 的数据库配置
            $config = \think\facade\Config::get('database.connections.mysql');
            
            $res = \think\facade\Db::query("SHOW GLOBAL STATUS WHERE Variable_name IN ('Threads_connected', 'Threads_running', 'Queries', 'Uptime', 'Slow_queries')");
            $status = [];
            foreach ($res as $row) {
                $status[$row['Variable_name']] = $row['Value'];
            }
            
            $max_connections = \think\facade\Db::query("SHOW VARIABLES LIKE 'max_connections'");
            $max_connections = $max_connections[0]['Value'] ?? 151;
            
            $version = \think\facade\Db::query('SELECT VERSION() as ver');
            $sizeRows = \think\facade\Db::query('SELECT IFNULL(SUM(data_length + index_length), 0) AS bytes FROM information_schema.tables WHERE table_schema = DATABASE()');
            $databaseSize = isset($sizeRows[0]['bytes']) ? (int)$sizeRows[0]['bytes'] : 0;
            
            $db = [
                'connected' => true,
                'version' => $version[0]['ver'] ?? 'Unknown',
                'database_size' => $databaseSize,
                'database_size_human' => $this->formatBytes($databaseSize),
                'uptime' => $status['Uptime'] ?? 0,
                'uptime_human' => $this->formatDuration($status['Uptime'] ?? 0),
                'threads_connected' => $status['Threads_connected'] ?? 0,
                'threads_running' => $status['Threads_running'] ?? 0,
                'max_connections' => $max_connections,
                'total_queries' => $status['Queries'] ?? 0,
                'slow_queries' => $status['Slow_queries'] ?? 0,
                'host_info' => $config['hostname'] . ':' . $config['hostport'],
            ];
        } catch (\Exception $e) {
            $db['error'] = $e->getMessage();
        }

        $cacheDriver = strtolower((string)\think\facade\Db::name('conf')->where('conf_key', 'cache_driver')->value('conf_value'));
        if (!in_array($cacheDriver, ['file', 'redis', 'memcached'], true)) {
            $cacheDriver = 'file';
        }
        $cacheInfo = [
            'driver' => $cacheDriver,
            'connected' => false,
            'status' => '离线',
            'error' => '缓存未就绪',
        ];
        if ($cacheDriver === 'file') {
            $runtimePath = rtrim(str_replace('\\', '/', root_path() . 'runtime'), '/');
            $cachePath = $runtimePath . '/cache';
            $dirStats = $this->getDirectoryStats($cachePath);
            $cacheInfo = [
                'driver' => 'file',
                'connected' => $dirStats['writable'],
                'status' => $dirStats['writable'] ? '可写' : '不可写',
                'path' => $cachePath,
                'file_count' => $dirStats['file_count'],
                'size' => $dirStats['size'],
                'size_human' => $this->formatBytes($dirStats['size']),
                'error' => $dirStats['writable'] ? '' : '缓存目录不可写',
            ];
        } elseif ($cacheDriver === 'redis') {
            if (!extension_loaded('redis')) {
                $cacheInfo = [
                    'driver' => 'redis',
                    'connected' => false,
                    'status' => '扩展缺失',
                    'error' => 'Redis扩展未安装'
                ];
            } else {
                try {
                    $host = \think\facade\Db::name('conf')->where('conf_key', 'redis_host')->value('conf_value') ?: '127.0.0.1';
                    $port = \think\facade\Db::name('conf')->where('conf_key', 'redis_port')->value('conf_value') ?: '6379';
                    $auth = \think\facade\Db::name('conf')->where('conf_key', 'redis_password')->value('conf_value');
                    $redis = new \Redis();
                    $redis->connect($host, (int)$port, 2);
                    if ($auth) {
                        $redis->auth($auth);
                    }
                    $info = $redis->info();
                    $usedMemory = $info['used_memory'] ?? 0;
                    $maxMemory = $info['maxmemory'] ?? 0;
                    $memoryPercent = $maxMemory > 0 ? round(($usedMemory / $maxMemory) * 100, 1) : 0;
                    $hits = (int)($info['keyspace_hits'] ?? 0);
                    $misses = (int)($info['keyspace_misses'] ?? 0);
                    $total = $hits + $misses;
                    $hitRate = $total > 0 ? round(($hits / $total) * 100, 1) : 0;
                    $totalKeys = 0;
                    $keyspace = $redis->info('keyspace');
                    if ($keyspace) {
                        foreach ($keyspace as $dbInfo) {
                            if (isset($dbInfo['keys'])) {
                                $totalKeys += (int)$dbInfo['keys'];
                            } elseif (is_string($dbInfo) && preg_match('/keys=(\d+)/', $dbInfo, $matches)) {
                                $totalKeys += (int)$matches[1];
                            }
                        }
                    }
                    $cacheInfo = [
                        'driver' => 'redis',
                        'connected' => true,
                        'status' => '在线',
                        'host' => $host . ':' . $port,
                        'version' => $info['redis_version'] ?? '未知',
                        'mode' => $info['redis_mode'] ?? 'standalone',
                        'used_memory' => $usedMemory,
                        'used_memory_human' => $info['used_memory_human'] ?? $this->formatBytes($usedMemory),
                        'memory_percent' => $memoryPercent,
                        'connected_clients' => (int)($info['connected_clients'] ?? 0),
                        'total_keys' => $totalKeys,
                        'hit_rate' => $hitRate,
                        'error' => ''
                    ];
                } catch (\Exception $e) {
                    $cacheInfo = [
                        'driver' => 'redis',
                        'connected' => false,
                        'status' => '离线',
                        'error' => $e->getMessage()
                    ];
                }
            }
        } else {
            if (!extension_loaded('memcached')) {
                $cacheInfo = [
                    'driver' => 'memcached',
                    'connected' => false,
                    'status' => '扩展缺失',
                    'error' => 'Memcached扩展未安装'
                ];
            } else {
                try {
                    $host = \think\facade\Db::name('conf')->where('conf_key', 'memcached_host')->value('conf_value') ?: '127.0.0.1';
                    $port = (int)(\think\facade\Db::name('conf')->where('conf_key', 'memcached_port')->value('conf_value') ?: 11211);
                    $username = \think\facade\Db::name('conf')->where('conf_key', 'memcached_username')->value('conf_value') ?: '';
                    $password = \think\facade\Db::name('conf')->where('conf_key', 'memcached_password')->value('conf_value') ?: '';
                    $memcached = new \Memcached();
                    $memcached->setOption(\Memcached::OPT_CONNECT_TIMEOUT, 2000);
                    if ($username !== '' && $password !== '') {
                        $memcached->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);
                        $memcached->setSaslAuthData($username, $password);
                    }
                    $memcached->addServer($host, $port);
                    $stats = $memcached->getStats();
                    $versionMap = $memcached->getVersion();
                    $statsItem = is_array($stats) && !empty($stats) ? reset($stats) : [];
                    $versionItem = is_array($versionMap) && !empty($versionMap) ? reset($versionMap) : false;
                    if ($versionItem === false || $versionItem === '0.0.0') {
                        throw new \RuntimeException('Memcached连接失败');
                    }
                    $bytes = (int)($statsItem['bytes'] ?? 0);
                    $limitMaxbytes = (int)($statsItem['limit_maxbytes'] ?? 0);
                    $memoryPercent = $limitMaxbytes > 0 ? round(($bytes / $limitMaxbytes) * 100, 1) : 0;
                    $hits = (int)($statsItem['get_hits'] ?? 0);
                    $misses = (int)($statsItem['get_misses'] ?? 0);
                    $total = $hits + $misses;
                    $hitRate = $total > 0 ? round(($hits / $total) * 100, 1) : 0;
                    $cacheInfo = [
                        'driver' => 'memcached',
                        'connected' => true,
                        'status' => '在线',
                        'host' => $host . ':' . $port,
                        'version' => (string)$versionItem,
                        'used_memory' => $bytes,
                        'used_memory_human' => $this->formatBytes($bytes),
                        'memory_limit_human' => $this->formatBytes($limitMaxbytes),
                        'memory_percent' => $memoryPercent,
                        'curr_items' => (int)($statsItem['curr_items'] ?? 0),
                        'hit_rate' => $hitRate,
                        'error' => ''
                    ];
                } catch (\Exception $e) {
                    $cacheInfo = [
                        'driver' => 'memcached',
                        'connected' => false,
                        'status' => '离线',
                        'error' => $e->getMessage()
                    ];
                }
            }
        }

        // 4. 服务器信息
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : [0, 0, 0];
        $total_disk = disk_total_space('.');
        $free_disk = disk_free_space('.');
        $used_disk = $total_disk - $free_disk;
        $disk_percent = $total_disk > 0 ? round(($used_disk / $total_disk) * 100, 1) : 0;
        
        $server = [
            'os' => PHP_OS,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'load_1' => $load[0] ?? 0,
            'load_5' => $load[1] ?? 0,
            'load_15' => $load[2] ?? 0,
            'cpu_cores' => function_exists('shell_exec') ? (int)shell_exec('nproc 2>/dev/null || echo 1') : 1,
            'disk_total' => $total_disk,
            'disk_free' => $free_disk,
            'disk_used_percent' => $disk_percent,
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
        ];

        // 5. 当前请求信息
        $request = [
            'time' => round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2),
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown',
            'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'Unknown',
            'client_ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'client_port' => $_SERVER['REMOTE_PORT'] ?? 'Unknown',
        ];

        View::assign([
            'php' => $php,
            'db' => $db,
            'cache' => $cacheInfo,
            'redis' => $cacheInfo,
            'server' => $server,
            'request' => $request
        ]);
    }

    private function getDirectoryStats($directory)
    {
        $result = [
            'file_count' => 0,
            'size' => 0,
            'writable' => false
        ];
        if (!is_dir($directory)) {
            return $result;
        }
        $result['writable'] = is_writable($directory);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $result['file_count']++;
                $result['size'] += $item->getSize();
            }
        }
        return $result;
    }

    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    private function formatDuration($seconds) {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        if ($days > 0) return "{$days}d {$hours}h {$minutes}m";
        if ($hours > 0) return "{$hours}h {$minutes}m {$secs}s";
        return "{$minutes}m {$secs}s";
    }

    private function return_bytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = (int)$val;
        switch($last) {
            case 'g': $val *= 1024;
            case 'm': $val *= 1024;
            case 'k': $val *= 1024;
        }
        return $val;
    }
}
