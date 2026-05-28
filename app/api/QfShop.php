<?php

declare(strict_types=1);

namespace app\api;

use think\App;
use think\facade\View;
use app\traits\ApiResponse;
use app\model\Conf as ConfModel;
use app\model\Token as TokenModel;

/**
 * 控制器基础类
 */
abstract class QfShop
{   
    use ApiResponse;
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
            // 缓存未命中，查询数据库（✅ 只查询启用的配置）
            $this->confModel = new ConfModel();
            $cachedConfigs = $this->confModel
                ->where('conf_status', 1)
                ->order('conf_sort desc, conf_id asc')
                ->select()
                ->toArray();
                
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
                    '[API缓存] %s | 耗时: %sms',
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
    public function __call($method, $args)
    {
        return jerr("API接口方法不存在", 404);
    }

    
    /**
     * 检测授权 获取当前用户登录信息 
     * @param $type false不提示登录失败状态
     * @param user_id 顾客时为用户id
     * @param action 操作者 顾客端为手机号，管理端为登录账号
     * @param client_type 0=顾客 1=管理组 -1=游客
     */
    protected function getLoginUser($type = true)
    {
        $is_login = true;
        // 获取请求中的token
        $access_token = request()->header('X-CSRF-TOKEN');
        if (!$access_token) {
            if($type){
                return jerr("用户未登录", 401);
            }else{
                $is_login = false;
            }
        }
        $Token = new TokenModel();
        $user = $Token->getToken($access_token);
        if (!$user) {
            if($type){
                return jerr("登录过期，请重新登录", 401);
            }else{
                $is_login = false;
            }
        }
        if($is_login){
            $user['action'] = $user['mobile'];
            $user['client_type'] = 0;
            unset($user['mobile']);
            unset($user['status']);
            return $user;
        }else{
            $user['client_type'] = -1;
            return $user;
        }
    }

}
