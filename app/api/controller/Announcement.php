<?php

namespace app\api\controller;

use app\api\QfShop;
use think\facade\Config;

class Announcement extends QfShop
{
    /**
     * 获取当前启用的公告（从配置表读取）
     * 
     * @return mixed
     */
    public function getAnnouncement()
    {
        // 检查公告开关
        $status = Config::get('qfshop.announce_status', 0);
        
        if ($status != 1) {
            return jok('公告已关闭', null);
        }
        
        // 从配置中读取公告信息
        $title = Config::get('qfshop.announce_title', '');
        $content = Config::get('qfshop.announce_content', '');
        $type = (int)Config::get('qfshop.announce_type', 1);
        $intervalDays = (int)Config::get('qfshop.announce_interval_days', 7);
        
        if (empty($title) || empty($content)) {
            return jok('暂无公告', null);
        }
        
        // 返回数据包含内容MD5，用于前端判断内容是否变化
        $data = [
            'id' => 1, // 固定ID，因为只有一条公告
            'title' => $title,
            'content' => $content,
            'type' => $type,
            'interval_days' => $intervalDays,
            'content_hash' => md5($content), // 用于判断内容是否变化
        ];
        
        return jok('获取成功', $data);
    }
}
