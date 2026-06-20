<?php

namespace app\service;

use app\model\Source as SourceModel;

class SearchDedupService
{
    private $sourceModel;

    public function __construct(SourceModel $sourceModel = null)
    {
        $this->sourceModel = $sourceModel ?: new SourceModel();
    }

    public function getLocalUrlsMap($title, $isType)
    {
        if (config('qfshop.web_search_dedup') != 1) {
            return [];
        }

        $localUrls = $this->getMatchedLocalUrls($title, $isType);

        return !empty($localUrls) ? array_flip($localUrls) : [];
    }

    public function getMatchedLocalResources($title, $isType, $limit = 20)
    {
        $title = trim((string)$title);
        if ($title === '') {
            return [];
        }

        $rows = $this->sourceModel
            ->where('title|description', 'like', '%' . $title . '%')
            ->where('is_type', $isType)
            ->where('is_delete', 0)
            ->where('status', 1)
            ->field('source_id, title, description, url, content, is_type, is_time, vod_pic, update_time')
            ->order('is_time asc, update_time desc, source_id desc')
            ->limit($limit)
            ->select()
            ->toArray();

        $items = [];
        foreach ($rows as $row) {
            $url = trim((string)($row['url'] ?? ''));
            $sourceUrl = trim((string)($row['content'] ?? ''));
            if ($url === '') {
                continue;
            }
            $items[] = [
                'url' => $url,
                'source_url' => $sourceUrl,
                'original_url' => $sourceUrl,
                'title' => trim((string)($row['title'] ?? '')),
                'desc' => trim((string)($row['description'] ?? '')),
                'is_type' => intval($row['is_type'] ?? $isType),
                'image' => trim((string)($row['vod_pic'] ?? '')),
                'source' => intval($row['is_time'] ?? 0) === 1 ? '本地临时资源' : '本地资源',
                'local_source_id' => intval($row['source_id'] ?? 0),
                'is_local' => true,
                'is_time' => intval($row['is_time'] ?? 0),
            ];
        }

        return $items;
    }

    public function markLocalResourcesUsed(array $items)
    {
        $ids = [];
        foreach ($items as $item) {
            if (!empty($item['local_source_id'])) {
                $ids[] = intval($item['local_source_id']);
            }
        }
        $ids = array_values(array_unique(array_filter($ids)));
        if (!empty($ids)) {
            $this->sourceModel->whereIn('source_id', $ids)->update(['update_time' => time()]);
        }
    }

    private function getMatchedLocalUrls($title, $isType)
    {
        $resources = $this->getMatchedLocalResources($title, $isType, 100);
        $urls = [];
        foreach ($resources as $resource) {
            if (!empty($resource['url'])) {
                $urls[] = $resource['url'];
            }
            if (!empty($resource['source_url'])) {
                $urls[] = $resource['source_url'];
            }
        }
        return $urls;
    }

    public function isDuplicate($url, array $cachedUrls, array $localUrlsMap)
    {
        return isset($cachedUrls[$url]) || isset($localUrlsMap[$url]);
    }
}
