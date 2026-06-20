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
        $validItems = [];
        $skippedCount = 0;
        $this->emitter->event('cache_start', [
            'message' => '正在验证上次搜索结果...',
            'count' => $cachedResults['result_count'],
            'cache_time' => date('Y-m-d H:i:s', $cachedResults['cache_time'])
        ]);

        $clientItems = [];
        foreach ($cachedResults['data'] as $item) {
            if (empty($item['url'])) {
                $skippedCount++;
                continue;
            }

            $rawItem = $item;
            $validIndex = count($validItems);
            $validItems[] = $rawItem;
            $cachedUrls[$rawItem['url']] = true;
            if (!empty($rawItem['source_url'])) {
                $cachedUrls[$rawItem['source_url']] = true;
            }
            if (!empty($rawItem['original_url'])) {
                $cachedUrls[$rawItem['original_url']] = true;
            }
            $clientItem = $rawItem;
            $clientItem['is_new'] = false;
            $clientItem = $this->treeKeyService->appendKey($clientItem);
            $clientItem = $this->protectUrlForClient($clientItem, $isShow);
            $clientItems[$validIndex] = $clientItem;
            $this->emitter->data($clientItem);
        }

        foreach ($validItems as $index => $rawItem) {
            if (!$this->validator->validateCached($rawItem)) {
                $skippedCount++;
                $this->removeCachedItem($clientItems[$index] ?? $rawItem);
                unset($validItems[$index]);
                unset($cachedUrls[$rawItem['url']]);
                if (!empty($rawItem['source_url'])) {
                    unset($cachedUrls[$rawItem['source_url']]);
                }
                if (!empty($rawItem['original_url'])) {
                    unset($cachedUrls[$rawItem['original_url']]);
                }
                continue;
            }
            $validItems[$index] = $rawItem;
        }
        $validItems = array_values($validItems);

        $this->emitter->event('cache_end', [
            'message' => '缓存验证完成，正在搜索新资源...',
            'valid_count' => count($validItems),
            'skipped_count' => $skippedCount
        ]);

        Log::info('[搜索缓存] 缓存输出完成: 已输出=' . count($validItems) . '条 跳过=' . $skippedCount . '条');
        return [
            'urls' => $cachedUrls,
            'items' => $validItems,
            'skipped' => $skippedCount,
        ];
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
        $sourceUrl = $item['source_url'] ?? ($item['original_url'] ?? '');
        $cacheItem = [
            'url' => $item['url'],
            'title' => $item['title'],
            'is_type' => $item['is_type'],
            'is_local' => true,
            'source' => $item['source'] ?? '本地资源',
        ];
        if (!empty($sourceUrl)) {
            $cacheItem['source_url'] = $sourceUrl;
            $cacheItem['original_url'] = $sourceUrl;
        }
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
        if (!empty($sourceUrl)) {
            $item['source_url'] = $sourceUrl;
            $item['original_url'] = $sourceUrl;
        }
        $item = $this->treeKeyService->appendKey($item);
        $item = $this->protectUrlForClient($item, $isShow);
        $this->emitter->data($item);

        return $cacheItem;
    }

    private function protectUrlForClient(array $item, $isShow)
    {
        if (config('qfshop.is_quan_type') != 1 && $isShow != 1) {
            $item['url'] = encryptObject($item['url']);
            if (!empty($item['source_url'])) {
                $item['source_url'] = encryptObject($item['source_url']);
            }
            if (!empty($item['original_url'])) {
                $item['original_url'] = encryptObject($item['original_url']);
            }
        }
        return $item;
    }

    private function removeCachedItem(array $item)
    {
        $payload = [
            'url' => $item['url'] ?? '',
            'source_url' => $item['source_url'] ?? '',
            'original_url' => $item['original_url'] ?? '',
            'title' => $item['title'] ?? '',
        ];
        $this->emitter->event('cache_remove', $payload);
    }
}
