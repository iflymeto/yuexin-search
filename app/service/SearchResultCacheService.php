<?php

namespace app\service;

use app\model\SearchCache as SearchCacheModel;
use think\facade\Log;

class SearchResultCacheService
{
    private $cacheModel;

    public function __construct(SearchCacheModel $cacheModel = null)
    {
        $this->cacheModel = $cacheModel ?: new SearchCacheModel();
    }

    public function getCache($title, $isType)
    {
        $cachedResults = $this->cacheModel->getCache($title, $isType);
        Log::info('[搜索缓存] 查询缓存: keyword=' . $title . ' is_type=' . $isType . ' 结果=' . ($cachedResults ? 'HIT(' . count($cachedResults['data']) . '条)' : 'MISS'));
        return $cachedResults;
    }

    public function saveIncrementally($title, $isType, array $newResults, array $oldResults)
    {
        Log::info('[搜索缓存] 增量保存: keyword=' . $title . ' 已收集=' . count($newResults) . '条');
        $uniqueResults = $this->mergeUnique($newResults, $oldResults);
        $this->cacheModel->saveCache($title, $isType, $uniqueResults, 1);
        DiagnosticLogService::record('search', 'cache_save_increment', 'success', '搜索缓存增量保存完成', [
            'keyword' => $title,
            'is_type' => $isType,
            'new_count' => count($newResults),
            'old_count' => count($oldResults),
            'saved_count' => count($uniqueResults),
        ]);
    }

    public function saveFinal($title, $isType, array $newResults, array $oldResults)
    {
        $uniqueResults = $this->mergeUnique($newResults, $oldResults);
        $this->cacheModel->saveCache($title, $isType, $uniqueResults, 1);
        Log::info('[搜索缓存] 最终保存: keyword=' . $title . ' 总数量=' . count($uniqueResults) . '条');
        DiagnosticLogService::record('search', 'cache_save_final', 'success', '搜索缓存最终保存完成', [
            'keyword' => $title,
            'is_type' => $isType,
            'new_count' => count($newResults),
            'old_count' => count($oldResults),
            'saved_count' => count($uniqueResults),
        ]);
    }

    private function mergeUnique(array $newResults, array $oldResults)
    {
        $unique = [];
        $keySet = [];
        foreach (array_merge($newResults, $oldResults) as $item) {
            $key = $this->resultKey($item);
            if ($key === '' || isset($keySet[$key])) {
                continue;
            }
            $unique[] = $item;
            $keySet[$key] = true;
        }
        return $unique;
    }

    private function resultKey(array $item)
    {
        foreach (['source_url', 'original_url', 'url'] as $field) {
            if (!empty($item[$field])) {
                return trim((string)$item[$field]);
            }
        }
        return '';
    }
}
