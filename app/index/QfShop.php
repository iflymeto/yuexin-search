<?php

declare(strict_types=1);

namespace app\index;

use think\App;
use EasyWeChat\Factory;
use app\model\Conf as ConfModel;
use app\model\User as UserModel;

/**
 * 控制器基础类
 */
abstract class QfShop
{
    /**
     * 应用实例
     * @var \think\App
     */
    protected $app;

    /**
     * Request实例
     * @var \think\Request
     */
    protected $request;

    /**
     * 配置模型
     * @var \app\model\Conf
     */
    protected $confModel;

    /**
     * 构造方法
     * @access public
     * @param  App  $app  应用对象
     */
    public function __construct(App $app)
    {
        $this->app     = $app;
        $this->request = $this->app->request;
        // 控制器初始化
        $this->initialize();
    }

    // 初始化
    protected function initialize()
    {
        // ✅ 方案B优化：进程级静态缓存 + 条件日志（仅DEBUG模式）
        // 性能提升：移除日志写入瓶颈，生产环境性能接近原版v3.6
        static $cachedConfigs = null;
        static $cachedVersion = null;
        $isDebug = config('app.debug', false);
        $cacheKey = 'system_config_all';
        $versionKey = 'system_config_version';
        $currentVersion = \think\facade\Cache::get($versionKey);
        
        // 长驻进程中，配置保存后需要通过版本号让进程级静态缓存失效。
        if ($cachedConfigs === null || $cachedVersion !== $currentVersion) {
            $startTime = $isDebug ? microtime(true) : 0;
            
            // 尝试从缓存读取
            $cachedConfigs = \think\facade\Cache::get($cacheKey);
            $cacheHit = !empty($cachedConfigs);
            
            if (empty($cachedConfigs)) {
                // 缓存未命中，查询数据库
                $this->confModel = new ConfModel();
                $cachedConfigs = $this->confModel->select()->toArray();
                
                // 写入缓存（永久，直到配置修改）
                try {
                    \think\facade\Cache::set($cacheKey, $cachedConfigs, 0);
                } catch (\Exception $e) {
                    // 缓存写入失败不影响正常流程
                }
            }
            $cachedVersion = $currentVersion;
            
            // ✅ 仅DEBUG模式记录日志（生产环境无日志开销）
            if ($isDebug) {
                $elapsed = round((microtime(true) - $startTime) * 1000, 2);
                \think\facade\Log::info(sprintf(
                    '[前台缓存] %s | 耗时: %sms',
                    $cacheHit ? '✓ HIT' : '✗ MISS',
                    $elapsed
                ));
                
                // 响应头也仅DEBUG时添加
                header('X-Cache-Status: ' . ($cacheHit ? 'HIT' : 'MISS'));
                header('X-Cache-Time: ' . $elapsed . 'ms');
            }
        }
        
        // 转换配置格式并设置
        $c = [];
        foreach ($cachedConfigs as $config) {
            $c[$config['conf_key']] = $config['conf_value'];
        }
        config($c, 'qfshop');
        
        // 动态设置缓存驱动
        if (isset($c['cache_driver']) && !empty($c['cache_driver'])) {
            try {
                $driver = strtolower((string)$c['cache_driver']);
                if (!in_array($driver, ['file', 'redis', 'memcached'], true)) {
                    $driver = 'file';
                }
                config(['default' => $driver], 'cache');
            } catch (\Exception $e) {
                // 设置失败不影响系统运行
            }
        }
    }
}
