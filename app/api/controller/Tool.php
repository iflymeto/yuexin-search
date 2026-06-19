<?php

namespace app\api\controller;

use think\App;
use think\facade\Request;
use think\facade\Cache;
use think\facade\Db;
use app\api\QfShop;
use app\model\User as Usermodel;
use app\model\Ads as Adsmodel;
use app\model\Feedback as FeedbackModel;
use app\model\SourceCategory as SourceCategoryModel;

class Tool extends QfShop
{
    /**
     * 系统配置参数
     *
     * @return void
     */
    public function getConfig()
    {
        $data = [
            'app_name'        => Config('qfshop.app_name'),
            'qcode'   => getimgurl(Config('qfshop.qcode')),
            'logo'   => getimgurl(Config('qfshop.logo')),
            'app_description'   => Config('qfshop.app_description'),
        ];
        return jok('获取成功',$data);
    }
    /**
     * 上传图片
     *
     * @return void
     */
    public function Upload()
    {
        // 获取当前登录的用户信息
        $userInfo = $this->getLoginUser();
        
        try {
            $file = request()->file('file');
        } catch (\Exception $error) {
            return jerr('上传文件失败，请检查你的文件！');
        }
        $Usermodel = new Usermodel();
        $data = $Usermodel->Upload($file, $userInfo);
        return jok('上传成功',$data);
    }

    /**
     * 根据广告位关键词获取广告图片列表
     * 
     * @return void
     */
    public function getAdsCode()
    {
        $Adsmodel = new Adsmodel();
        $data = $Adsmodel->getAdsCode(input(''));
        return jok('获取成功',$data);
    }

    /**
     * 用户反馈
     * 
     * @return void
     */
    public function feedback()
    {
        $data = input('');
        if (empty($data['content'])) {
            return jerr("请输入要看的内容");
        }
        
        // 获取用户IP地址
        $ip = Request::ip();
        
        // 准备保存的数据
        $saveData = [
            'content' => $data['content'],
            'ip' => $ip
        ];
        
        // 如果提供了邮箱，则保存邮箱信息
        if (!empty($data['email'])) {
            $saveData['email'] = $data['email'];
        }
        
        $FeedbackModel = new FeedbackModel();
        $FeedbackModel->save($saveData);
        return jok('已反馈');
    }
    
    

    /**
     * 获取首页排行榜数据
     *
     * @return void
     */
    public function ranking()
    {
        $channel = input('channel');
        $is_m = input('is_m')??0;
        $rankingNum = $this->getConfigIntFromDb('ranking_num', (int)(Config('qfshop.ranking_num') ?: 10), 1, 200);
        $rankingMobileNum = $this->getConfigIntFromDb('ranking_m_num', (int)(Config('qfshop.ranking_m_num') ?: 6), 1, 200);
        
        if (empty($channel)) {
            return [];
        }
    
        // 使用 ThinkPHP 提供的 runtime_path() 函数获取 runtime 目录路径
        $cacheDir = runtime_path('cache'); // runtime/cache 目录
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true); // 确保缓存目录存在
        }
    
        // 根据 channel 和拉取数量生成缓存文件名，避免后台数量变更后继续命中旧缓存。
        $cacheFile = $cacheDir . 'ranking_data_' . md5((string)$channel) . "_{$rankingNum}.cache";
        $cacheTime = 12*3600; // 缓存时间为 12 小时
    
        // 检查缓存文件是否存在且在缓存时间内
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
            // 从缓存中读取数据
            $data = json_decode(file_get_contents($cacheFile), true);
            if (!is_array($data)) {
                $data = [];
            }
        } else {
            $data = [];
            if (!empty($channel)) {
                $queryParams =  array(
                    "area" =>  "全部",
                    "year" =>  "全部",
                    "channel" =>  $channel,
                    "rank_type" =>  "最热",
                    "cate" =>  "全部",
                    "from" =>  "hot_page",
                    "start" =>  0,
                    "hit" =>  $rankingNum,
                );
                $res = curlHelper("https://biz.quark.cn/api/trending/ranking/getYingshiRanking", "GET", null, [], $queryParams)['body'];
                $res = json_decode($res, true);
                try {
                    // 检查返回数据结构是否完整
                    if (isset($res['data']['hits']['hit']['item']) && is_array($res['data']['hits']['hit']['item'])) {
                        foreach ($res['data']['hits']['hit']['item'] as $key => $value) {
                            $data[] = array(
                                "title" => $value['title'] ?? '',
                                "src" => $value['src'] ?? '',
                                "ranking" => $value['ranking'] ?? 0,
                                "hot_score" => $value['hot_score'] ?? '',
                                "desc" => $value['desc'] ?? '',
                            );
                        }
                    } else {
                        // 记录调试信息
                        trace('排行榜数据结构异常: channel=' . $channel . ', response=' . json_encode($res), 'error');
                        $data = [];
                    }
                } catch (\Exception $error) {
                    // 记录错误日志
                    trace('排行榜数据解析失败: ' . $error->getMessage() . ', channel=' . $channel, 'error');
                    $data = [];
                }
    
                // 将数据缓存到文件中
                file_put_contents($cacheFile, json_encode($data));
            }
        }
        
        if($is_m==1){
            $data = array_slice($data, 0, $rankingMobileNum);
        }
       
        return json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    private function getConfigIntFromDb($key, $fallback, $min = 1, $max = 200)
    {
        try {
            $value = Db::name('conf')->where('conf_key', $key)->value('conf_value');
            if ($value !== null && $value !== '') {
                $fallback = (int)$value;
            }
        } catch (\Exception $error) {
            trace('读取配置失败: key=' . $key . ', error=' . $error->getMessage(), 'error');
        }

        $value = (int)$fallback;
        if ($value < $min) {
            return $min;
        }
        if ($value > $max) {
            return $max;
        }
        return $value;
    }


    /**
     * 网页端全网搜接口
     *
     * @return void
     */
    public function Qsearch()
    {
        $title = input('title');
        $list = [];


        $userAgent = Request::header('user-agent');
        // 定义常见爬虫的 User-Agent 关键字
        $bots = ['Googlebot', 'Bingbot', 'Baiduspider'];
        foreach ($bots as $bot) {
            if (strpos($userAgent, $bot) !== false) {
                return jerr('该接口禁止爬虫访问');
            }
        }

        if (empty($title)) {
            return jok('临时资源获取成功', $list);
        }
        
        $keys = Request::ip()."_".$title;
        if(Cache::get($keys) == 1){
            return jerr('调用太过频繁啦');
        }
        Cache::set($keys, 1, 10);

        $bController = app(\app\api\controller\Other::class);
        $list = $bController->all_search($title);

        Cache::delete($keys); 
        return jok('临时资源获取成功', $list);
    }

}
