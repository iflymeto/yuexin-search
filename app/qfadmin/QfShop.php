<?php

declare(strict_types=1);

namespace app\qfadmin;

use think\App;
use think\facade\View;

use app\model\Admin as AdminModel;
use app\model\Access as AccessModel;
use app\model\Auth as AuthModel;
use app\model\Node as NodeModel;
use app\model\Group as GroupModel;
use app\model\Conf as ConfModel;

/**
 * 控制器基础类
 */
abstract class QfShop
{
    /**
     * Request实例
     * @var \think\Request
     */
    protected $request;

    /**
     * 应用实例
     * @var \think\App
     */
    protected $app;
    protected $module;
    protected $controller;
    protected $action;

    //模型
    protected $AdminModel;
    protected $accessModel;
    protected $authModel;
    protected $nodeModel;
    protected $groupModel;
    protected $confModel;

    //主键key
    protected $pk = '';
    //表名称
    protected $table = '';
    //主键value
    protected $pk_value = '';
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
        $this->module = "qfadmin";
        $this->controller = $this->request->controller() ? $this->request->controller() : "Index";
        $this->action = strtolower($this->request->action()) ? strtolower($this->request->action()) : "index";
        View::assign('controller', strtolower($this->controller));
        View::assign('action', strtolower($this->action));

        $this->table = strtolower($this->controller);
        $this->pk = $this->table . "_id";
        $this->pk_value = input($this->pk);

        $this->adminModel = new AdminModel();
        $this->accessModel = new AccessModel();
        $this->authModel = new AuthModel();
        $this->nodeModel = new NodeModel();
        $this->groupModel = new GroupModel();
        $this->confModel = new ConfModel();

        // ✅ 方案B优化：进程级静态缓存 + 条件日志（仅DEBUG模式）
        // 性能提升：移除日志写入瓶颈，生产环境性能接近原版v3.6
        static $cachedConfigs = null;
        $isDebug = config('app.debug', false);
        
        // 仅在首次请求或缓存为空时执行
        if ($cachedConfigs === null) {
            $cacheKey = 'system_config_all';
            $startTime = $isDebug ? microtime(true) : 0;
            
            // 尝试从缓存读取
            $cachedConfigs = \think\facade\Cache::get($cacheKey);
            $cacheHit = !empty($cachedConfigs);
            
            if (empty($cachedConfigs)) {
                // 缓存未命中，查询数据库
                $cachedConfigs = $this->confModel->select()->toArray();
                
                // 写入缓存（永久，直到配置修改）
                try {
                    \think\facade\Cache::set($cacheKey, $cachedConfigs, 0);
                } catch (\Exception $e) {
                    // 缓存写入失败不影响正常流程
                }
            }
            
            // ✅ 仅DEBUG模式记录日志（生产环境无日志开销）
            if ($isDebug) {
                $elapsed = round((microtime(true) - $startTime) * 1000, 2);
                \think\facade\Log::info(sprintf(
                    '[后台缓存] %s | 耗时: %sms',
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
        config($c, 'yadmin');
        
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
    /**
     * 后台简单的身份判断
     *
     * @return void
     */
    protected function access()
    {
        $callback = "/qfadmin";
        if (strtolower($this->controller) != "index") {
            $callback .= "/" . strtolower($this->controller);
        }
        if ($this->action != "index") {
            $callback .= "/" . $this->action;
        }
        $access_token = cookie('access_token');
        if (!$access_token) {
            return redirect('/qfadmin/admin/login/?callback=' . urlencode($callback));
        }
        View::assign("access_token", $access_token);
        $this->admin = $this->adminModel->getAdminByAccessToken($access_token);
        if (!$this->admin) {
            return redirect('/qfadmin/admin/login/?callback=' . urlencode($callback));
        }
        if ($this->admin['admin_status']  > 0) {
            return $this->error("抱歉，你的帐号已被禁用，暂时无法登录系统！");
        }
        cookie("access_token", $access_token);
        View::assign('adminInfo', $this->admin);
        $this->group = $this->groupModel->where('group_id', $this->admin['admin_group'])->find();
        if ($this->group) {
            if ($this->group['group_id'] != 1 && $this->group['group_status'] == 1) {
                return $this->error("抱歉，你所在的用户组已被禁用，暂时无法登录系统");
            } else {
                $menuList = $this->authModel->getAdminMenuListByAdminId($this->group['group_id']);
                View::assign('menuList', $menuList);

                $node = $this->nodeModel->where(['node_module' => $this->module, 'node_controller' => strtolower($this->controller), 'node_action' => $this->action])->find();
                View::assign('node', $node);

                if($node['node_pid']==0){
                    View::assign('menu', 0);
                }else{
                    $res = $this->nodeModel->where('node_id',$node['node_pid'])->find();
                    View::assign('menu', $res['node_pid']);
                }
                $menuLists = [];
                foreach ($menuList as $key => $value) {
                    if($value['node_id'] == $node['node_pid']){
                        $menuLists = $value['subList'];
                    }else{
                        foreach ($value['subList'] as $k => $v) {
                            if($v['node_id'] == $node['node_pid']){
                                $menuLists = $value['subList'];
                            }
                        }
                    }
                }
                View::assign('menuLists', $menuLists);
                View::assign('action', $this->request->action());
            }
        } else {
            return $this->error("抱歉，没有查到你的用户组信息，暂时无法登录系统");
        }
    }
    protected function error($message)
    {
        echo $message;
        die;
    }
}
