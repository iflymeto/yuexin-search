<?php
/**
 * 清理过期的无效资源记录
 * 
 * 用途：定期清理数据库中已过期的无效资源缓存记录
 * 使用方式：
 *   1. 手动执行：php script/clean_invalid_resource.php
 *   2. 定时任务：0 3 * * * cd /path/to/project && php script/clean_invalid_resource.php
 */

namespace think;

// 加载框架基础文件
require __DIR__ . '/../vendor/autoload.php';

// 执行HTTP应用初始化
$http = (new App())->http;

// 执行应用并响应
$response = $http->run();

// 执行清理逻辑
try {
    $model = new \app\model\InvalidResource();
    $count = $model->cleanExpired();
    
    $message = date('Y-m-d H:i:s') . " - 清理完成，删除 {$count} 条过期记录\n";
    echo $message;
    
    // 记录到日志
    \think\facade\Log::info('[无效资源清理] ' . $message);
    
} catch (\Exception $e) {
    $error = date('Y-m-d H:i:s') . " - 清理失败：" . $e->getMessage() . "\n";
    echo $error;
    \think\facade\Log::error('[无效资源清理] ' . $error);
}

