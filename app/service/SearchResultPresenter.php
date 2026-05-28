<?php

namespace app\service;

use think\facade\Log;

class SearchResultPresenter
{
    private $emitter;
    private $treeKeyService;
    private $validator;

    public function __construct(
        SearchSseEmitter $emitter = null,
        SearchTreeKeyService $treeKeyService = null,
        SearchResourceValidator $validator = null
    ) {
        $this->emitter = $emitter ?: new SearchSseEmitter();
        $this->treeKeyService = $treeKeyService ?: new SearchTreeKeyService();
        $this->validator = $validator ?: new SearchResourceValidator();
    }

    public function outputCachedResults(array $cachedResults, $isShow)
    {
        $cachedUrls = [];
        $this->emitter->event('cache_start', [
            'message' => '正在显示上次搜索结果...',
            'count' => $cachedResults['result_count'],
            'cache_time' => date('Y-m-d H:i:s', $cachedResults['cache_time'])
        ]);

        foreach ($cachedResults['data'] as $item) {
            if ($this->validator->isInvalid($item['url'])) {
                continue;
            }

            $cachedUrls[$item['url']] = true;
            $item['is_new'] = false;
            $item = $this->treeKeyService->appendKey($item);
            $item = $this->protectUrlForClient($item, $isShow);
            $this->emitter->data($item);
        }

        $this->emitter->event('cache_end', [
            'message' => '缓存显示完成，正在搜索新资源...'
        ]);

        Log::info('[搜索缓存] 缓存输出完成: 已输出=' . count($cachedUrls) . '条');
        return $cachedUrls;
    }

    public function outputNewResult(array $item, $isShow)
    {
        $cacheItem = [
            'url' => $item['url'],
            'title' => $item['title'],
            'is_type' => $item['is_type'],
        ];
        if (!empty($item['image'])) {
            $cacheItem['image'] = $item['image'];
        }
        if (!empty($item['images'])) {
            $cacheItem['images'] = $item['images'];
        }
        if (!empty($item['stoken'])) {
            $cacheItem['stoken'] = $item['stoken'];
        }

        $item['is_new'] = true;
        $item['insert_position'] = 'top';
        $item['highlight'] = 'red';
        $item = $this->treeKeyService->appendKey($item);
        $item = $this->protectUrlForClient($item, $isShow);
        $this->emitter->data($item);

        return $cacheItem;
    }

    public function outputLocalResult(array $item, $isShow)
    {
        $cacheItem = [
            'url' => $item['url'],
            'title' => $item['title'],
            'is_type' => $item['is_type'],
            'is_local' => true,
            'source' => $item['source'] ?? '本地资源',
        ];
        if (!empty($item['image'])) {
            $cacheItem['image'] = $item['image'];
        }
        if (!empty($item['desc'])) {
            $cacheItem['desc'] = $item['desc'];
        }

        $item['is_new'] = true;
        $item['is_local'] = true;
        $item['insert_position'] = 'top';
        $item['highlight'] = 'red';
        $item['source'] = $item['source'] ?? '本地资源';
        $item = $this->treeKeyService->appendKey($item);
        $item = $this->protectUrlForClient($item, $isShow);
        $this->emitter->data($item);

        return $cacheItem;
    }

    private function protectUrlForClient(array $item, $isShow)
    {
        if (config('qfshop.is_quan_type') != 1 && $isShow != 1) {
            $item['url'] = encryptObject($item['url']);
        }
        return $item;
    }
}
