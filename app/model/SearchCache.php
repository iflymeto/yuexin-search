<?php

namespace app\model;

use app\model\QfShop;
use think\facade\Db;

/**
 * 搜索结果缓存模型
 * 用于记录已检测的网盘搜索结果，避免重复检测，提升用户体验
 */
class SearchCache extends QfShop
{
    /**
     * 主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 数据表名
     * @var string
     */
    protected $name = 'search_cache';

    /**
     * 是否需要自动写入时间戳
     * @var bool
     */
    protected $autoWriteTimestamp = true;

    /**
     * 只读属性
     * @var array
     */
    protected $readonly = [
        'id',
    ];

    /**
     * 字段类型或者格式转换
     * @var array
     */
    protected $type = [
        'id'                => 'integer',
        'is_type'           => 'integer',
        'result_count'      => 'integer',
        'last_search_time'  => 'integer',
        'expire_time'       => 'integer',
        'create_time'       => 'integer',
        'update_time'       => 'integer',
    ];

    /**
     * 获取搜索缓存
     * 
     * @param string $keyword 搜索关键词
     * @param int $isType 网盘类型（0=夸克 1=阿里 2=百度 3=UC 4=迅雷）
     * @return array|null 返回缓存数据或null
     */
    public function getCache($keyword, $isType = 0)
    {
        $keywordHash = md5($keyword . '_' . $isType);
        $currentTime = time();
        
        $record = $this->where('keyword_hash', $keywordHash)
            ->where('is_type', $isType)
            ->where('expire_time', '>', $currentTime)
            ->find();
        
        if (!$record) {
            return null;
        }
        
        // 解析JSON数据
        $cacheData = json_decode($record['cache_data'], true);
        
        if (!is_array($cacheData)) {
            return null;
        }
        
        return [
            'data' => $cacheData,
            'cache_time' => $record['last_search_time'],
            'result_count' => $record['result_count']
        ];
    }

    /**
     * 保存搜索缓存
     * 
     * @param string $keyword 搜索关键词
     * @param int $isType 网盘类型
     * @param array $results 搜索结果数组
     * @param int $expireDays 缓存天数（默认1天）
     * @return bool 是否保存成功
     */
    public function saveCache($keyword, $isType, $results, $expireDays = 1)
    {
        $keywordHash = md5($keyword . '_' . $isType);
        $currentTime = time();
        $expireTime = $currentTime + ($expireDays * 86400);
        
        $data = [
            'keyword' => $keyword,
            'keyword_hash' => $keywordHash,
            'is_type' => $isType,
            'cache_data' => json_encode($results),
            'result_count' => count($results),
            'last_search_time' => $currentTime,
            'expire_time' => $expireTime,
            'update_time' => $currentTime,
        ];
        
        // 检查是否已存在
        $existing = $this->where('keyword_hash', $keywordHash)
            ->where('is_type', $isType)
            ->find();
        
        if ($existing) {
            // 更新现有记录
            return $this->where('id', $existing['id'])->update($data) !== false;
        } else {
            // 插入新记录
            $data['create_time'] = $currentTime;
            return $this->save($data) !== false;
        }
    }

    /**
     * 清理过期的缓存记录
     * 
     * @return int 删除的记录数
     */
    public function cleanExpired()
    {
        $currentTime = time();
        
        return $this->where('expire_time', '>', 0)
            ->where('expire_time', '<', $currentTime)
            ->delete();
    }

    /**
     * 获取缓存统计数据
     * 
     * @return array 按网盘类型分组的统计数据
     */
    public function getStatistics()
    {
        $currentTime = time();
        
        // 有效的缓存记录统计（未过期）
        $stats = $this->field('is_type, COUNT(*) as count, SUM(result_count) as total_results')
            ->where('expire_time', '>', $currentTime)
            ->group('is_type')
            ->select()
            ->toArray();
        
        // 网盘类型名称映射
        $typeNames = [
            0 => '夸克网盘',
            1 => '阿里云盘',
            2 => '百度网盘',
            3 => 'UC网盘',
            4 => '迅雷网盘',
        ];
        
        // 格式化统计数据
        $result = [];
        foreach ($stats as $item) {
            $result[] = [
                'is_type' => $item['is_type'],
                'type_name' => $typeNames[$item['is_type']] ?? '未知',
                'cache_count' => $item['count'],
                'total_results' => $item['total_results'],
            ];
        }
        
        return $result;
    }

    /**
     * 删除指定关键词的缓存
     * 
     * @param string $keyword 搜索关键词
     * @param int $isType 网盘类型
     * @return bool 是否删除成功
     */
    public function removeCache($keyword, $isType)
    {
        $keywordHash = md5($keyword . '_' . $isType);
        return $this->where('keyword_hash', $keywordHash)
            ->where('is_type', $isType)
            ->delete() !== false;
    }

    /**
     * 获取热门搜索关键词（按搜索次数排序）
     * 
     * @param int $limit 返回数量
     * @return array 热门关键词列表
     */
    public function getHotKeywords($limit = 20)
    {
        $currentTime = time();
        
        return $this->field('keyword, is_type, result_count, last_search_time')
            ->where('expire_time', '>', $currentTime)
            ->order('last_search_time', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();
    }

    /**
     * 批量获取多个关键词的缓存状态
     * 
     * @param array $keywords 关键词数组
     * @param int $isType 网盘类型
     * @return array 返回缓存状态数组 [keyword => has_cache]
     */
    public function batchCheckCache($keywords, $isType = 0)
    {
        if (empty($keywords)) {
            return [];
        }
        
        $keywordHashes = [];
        $hashToKeyword = [];
        foreach ($keywords as $keyword) {
            $hash = md5($keyword . '_' . $isType);
            $keywordHashes[] = $hash;
            $hashToKeyword[$hash] = $keyword;
        }
        
        $currentTime = time();
        
        $records = $this->whereIn('keyword_hash', $keywordHashes)
            ->where('is_type', $isType)
            ->where('expire_time', '>', $currentTime)
            ->column('keyword_hash');
        
        // 构建结果数组
        $result = [];
        foreach ($keywords as $keyword) {
            $hash = md5($keyword . '_' . $isType);
            $result[$keyword] = in_array($hash, $records);
        }
        
        return $result;
    }
}

