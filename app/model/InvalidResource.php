<?php

namespace app\model;

use app\model\QfShop;

/**
 * 无效资源缓存模型
 * 用于记录已检测为无效的网盘资源，避免重复检测
 */
class InvalidResource extends QfShop
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
    protected $name = 'invalid_resource';

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
        'id'            => 'integer',
        'is_type'       => 'integer',
        'check_count'   => 'integer',
        'last_check_time' => 'integer',
        'expire_time'   => 'integer',
        'create_time'   => 'integer',
        'update_time'   => 'integer',
    ];

    /**
     * 检查URL是否已被标记为无效（未过期）
     * 
     * @param string $url 资源URL
     * @return bool true=已标记为无效, false=未标记或已过期
     */
    public function isInvalid($url)
    {
        $urlHash = md5($url);
        $currentTime = time();
        
        $record = $this->where('url_hash', $urlHash)
            ->where(function($query) use ($currentTime) {
                $query->where('expire_time', '>', $currentTime)
                      ->whereOr('expire_time', '=', 0);
            })
            ->find();
        
        return !empty($record);
    }

    /**
     * 批量检查多个URL是否无效
     * 
     * @param array $urls URL数组
     * @return array 返回无效的URL哈希数组
     */
    public function batchCheckInvalid($urls)
    {
        if (empty($urls)) {
            return [];
        }
        
        $urlHashes = array_map('md5', $urls);
        $currentTime = time();
        
        $records = $this->whereIn('url_hash', $urlHashes)
            ->where(function($query) use ($currentTime) {
                $query->where('expire_time', '>', $currentTime)
                      ->whereOr('expire_time', '=', 0);
            })
            ->column('url_hash');
        
        return $records;
    }

    /**
     * 记录无效资源
     * 
     * @param string $url 资源URL
     * @param int $isType 网盘类型（0=夸克 1=阿里 2=百度 3=UC）
     * @param string $failReason 失效原因（可选）
     * @param int $expireDays 记录保留天数（默认7天，0表示永不过期）
     * @return bool 是否记录成功
     */
    public function recordInvalid($url, $isType = 0, $failReason = '', $expireDays = 7)
    {
        $urlHash = md5($url);
        $currentTime = time();
        
        // 计算过期时间（0表示永不过期）
        $expireTime = $expireDays > 0 ? ($currentTime + $expireDays * 86400) : 0;
        
        // 检查是否已存在记录
        $existing = $this->where('url_hash', $urlHash)->find();
        
        if ($existing) {
            // 已存在，更新检测次数和时间
            return $this->where('url_hash', $urlHash)->update([
                'check_count' => $existing['check_count'] + 1,
                'last_check_time' => $currentTime,
                'expire_time' => $expireTime,
                'update_time' => $currentTime,
            ]) !== false;
        } else {
            // 不存在，插入新记录
            return $this->save([
                'url' => $url,
                'url_hash' => $urlHash,
                'is_type' => $isType,
                'fail_reason' => $failReason,
                'check_count' => 1,
                'last_check_time' => $currentTime,
                'expire_time' => $expireTime,
                'create_time' => $currentTime,
                'update_time' => $currentTime,
            ]) !== false;
        }
    }

    /**
     * 批量记录无效资源
     * 
     * @param array $urlData 格式：[['url' => '...', 'is_type' => 0], ...]
     * @param int $expireDays 记录保留天数
     * @return int 成功记录的数量
     */
    public function batchRecordInvalid($urlData, $expireDays = 7)
    {
        $successCount = 0;
        
        foreach ($urlData as $item) {
            if (empty($item['url'])) {
                continue;
            }
            
            $url = $item['url'];
            $isType = $item['is_type'] ?? 0;
            $failReason = $item['fail_reason'] ?? '';
            
            if ($this->recordInvalid($url, $isType, $failReason, $expireDays)) {
                $successCount++;
            }
        }
        
        return $successCount;
    }

    /**
     * 清理过期的无效资源记录
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
     * 获取无效资源统计数据
     * 
     * @return array 按网盘类型分组的统计数据
     */
    public function getStatistics()
    {
        $currentTime = time();
        
        // 有效的无效记录统计（未过期）
        $stats = $this->field('is_type, COUNT(*) as count')
            ->where(function($query) use ($currentTime) {
                $query->where('expire_time', '>', $currentTime)
                      ->whereOr('expire_time', '=', 0);
            })
            ->group('is_type')
            ->select()
            ->toArray();
        
        // 网盘类型名称映射
        $typeNames = [
            0 => '夸克网盘',
            1 => '阿里云盘',
            2 => '百度网盘',
            3 => 'UC网盘',
        ];
        
        // 格式化统计数据
        $result = [];
        foreach ($stats as $item) {
            $result[] = [
                'is_type' => $item['is_type'],
                'type_name' => $typeNames[$item['is_type']] ?? '未知',
                'count' => $item['count'],
            ];
        }
        
        return $result;
    }

    /**
     * 删除指定URL的无效记录（用于资源恢复有效时）
     * 
     * @param string $url 资源URL
     * @return bool 是否删除成功
     */
    public function removeInvalid($url)
    {
        $urlHash = md5($url);
        return $this->where('url_hash', $urlHash)->delete() !== false;
    }

    /**
     * 获取最近失效的资源列表（用于分析）
     * 
     * @param int $limit 返回数量
     * @return array 无效资源列表
     */
    public function getRecentInvalid($limit = 100)
    {
        $currentTime = time();
        
        return $this->field('url, is_type, fail_reason, check_count, create_time')
            ->where(function($query) use ($currentTime) {
                $query->where('expire_time', '>', $currentTime)
                      ->whereOr('expire_time', '=', 0);
            })
            ->order('create_time', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();
    }
}

