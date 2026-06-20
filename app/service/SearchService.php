<?php

namespace app\service;

use think\facade\Log;
use app\model\ApiList as ApiListModel;
use app\service\DiagnosticLogService;
use app\service\SearchDedupService;
use app\service\SearchLineService;
use app\service\SearchResultPresenter;
use app\service\SearchResourceValidator;
use app\service\SearchResultCacheService;
use app\service\SearchSseEmitter;

class SearchService
{
    protected $ApiListModel;
    protected $SearchLineService;
    protected $SearchDedupService;
    protected $SearchResourceValidator;
    protected $SearchResultCacheService;
    protected $SearchSseEmitter;
    protected $SearchResultPresenter;

    public function __construct()
    {
        $this->ApiListModel = new ApiListModel();
        $this->SearchLineService = new SearchLineService();
        $this->SearchDedupService = new SearchDedupService();
        $this->SearchResourceValidator = new SearchResourceValidator();
        $this->SearchResultCacheService = new SearchResultCacheService();
        $this->SearchSseEmitter = new SearchSseEmitter();
        $this->SearchResultPresenter = new SearchResultPresenter(
            $this->SearchSseEmitter,
            null,
            $this->SearchResourceValidator
        );
    }

    /**
     * 执行全网搜索
     */
    public function search($title, $is_type, $is_show)
    {
        $searchStart = microtime(true);
        DiagnosticLogService::record('search', 'start', 'info', '开始搜索', [
            'keyword' => $title,
            'is_type' => $is_type,
            'is_show' => $is_show,
        ]);

        // 1. 检查搜索缓存
        $cachedResults = $this->SearchResultCacheService->getCache($title, $is_type);
        
        $cachedUrls = [];
        $validCachedItems = [];
        $cachedSkippedCount = 0;
        $debugMode = request()->param('debug/d', 0) === 1;
        DiagnosticLogService::record('search', 'mode', 'info', '搜索模式已确定', [
            'keyword' => $title,
            'is_type' => $is_type,
            'debug_mode' => $debugMode ? 1 : 0,
            'cache_hit' => !empty($cachedResults) ? 1 : 0,
            'cache_count' => !empty($cachedResults['data']) ? count($cachedResults['data']) : 0,
            'dedup_enabled' => (!$debugMode && config('qfshop.web_search_dedup') == 1) ? 1 : 0,
        ]);

        // 2. 如果有缓存，先输出缓存结果
        if (!empty($cachedResults)) {
            DiagnosticLogService::record('search', 'cache_hit', 'success', '命中搜索结果缓存', [
                'keyword' => $title,
                'is_type' => $is_type,
                'result_count' => $cachedResults['result_count'],
                'cache_time' => $cachedResults['cache_time'],
            ]);
            $cacheOutput = $this->SearchResultPresenter->outputCachedResults($cachedResults, $is_show);
            $cachedUrls = $cacheOutput['urls'];
            $validCachedItems = $cacheOutput['items'];
            $cachedSkippedCount = $cacheOutput['skipped'];
            DiagnosticLogService::record('search', 'cache_output', 'success', '缓存结果输出完成', [
                'keyword' => $title,
                'is_type' => $is_type,
                'output' => count($validCachedItems),
                'skipped' => $cachedSkippedCount,
                'cache_count' => !empty($cachedResults['data']) ? count($cachedResults['data']) : 0,
            ]);
        }
        if (empty($cachedResults)) {
            DiagnosticLogService::record('search', 'cache_miss', 'info', '未命中搜索结果缓存', [
                'keyword' => $title,
                'is_type' => $is_type,
            ]);
        }

        // 3. 准备新搜索
        $newCacheResults = [];
        $cacheSaveBatchSize = 5;
        $lastIncrementalSaveCount = 0;
        $saveIncrementalCache = function ($reason) use ($title, $is_type, &$newCacheResults, &$validCachedItems, &$lastIncrementalSaveCount) {
            if (empty($newCacheResults) || count($newCacheResults) <= $lastIncrementalSaveCount) {
                return;
            }
            $this->SearchResultCacheService->saveIncrementally($title, $is_type, $newCacheResults, $validCachedItems);
            $lastIncrementalSaveCount = count($newCacheResults);
            DiagnosticLogService::record('search', 'cache_save_batch', 'success', '搜索缓存批量增量保存完成', [
                'keyword' => $title,
                'is_type' => $is_type,
                'reason' => $reason,
                'saved_count' => $lastIncrementalSaveCount,
            ]);
        };
        $localResources = $this->SearchDedupService->getMatchedLocalResources($title, $is_type);
        $localUrlsMap = $debugMode ? [] : $this->SearchDedupService->getLocalUrlsMap($title, $is_type);
        Log::info('[搜索模式] keyword=' . $title . ' is_type=' . $is_type . ' debug_mode=' . ($debugMode ? '1' : '0') . ' local_dedup=' . ($debugMode ? 'off' : 'on'));
        DiagnosticLogService::record('search', 'dedup_ready', 'info', '去重上下文已准备', [
            'keyword' => $title,
            'is_type' => $is_type,
            'debug_mode' => $debugMode ? 1 : 0,
            'cached_url_count' => count($cachedUrls),
            'local_url_count' => count($localUrlsMap),
            'local_resource_count' => count($localResources),
        ]);

        $localOutputCount = 0;
        $localInvalidSkipped = 0;
        $localOutputResources = [];
        foreach ($localResources as $item) {
            if (empty($item['url'])) {
                continue;
            }
            $item['skip_deep_check'] = true;
            if (!$this->SearchResourceValidator->validate($item)) {
                $localInvalidSkipped++;
                DiagnosticLogService::record('search', 'local_invalid_skip', 'warn', '跳过无效本地资源', [
                    'keyword' => $title,
                    'is_type' => isset($item['is_type']) ? intval($item['is_type']) : intval($is_type),
                    'local_source_id' => intval($item['local_source_id'] ?? 0),
                    'url' => $item['url'],
                ]);
                continue;
            }
            $newCacheResults[] = $this->SearchResultPresenter->outputLocalResult($item, $is_show);
            $cachedUrls[$item['url']] = true;
            $localUrlsMap[$item['url']] = true;
            if (!empty($item['source_url'])) {
                $cachedUrls[$item['source_url']] = true;
                $localUrlsMap[$item['source_url']] = true;
            }
            if (!empty($item['original_url'])) {
                $cachedUrls[$item['original_url']] = true;
                $localUrlsMap[$item['original_url']] = true;
            }
            $localOutputResources[] = $item;
            $localOutputCount++;
            if ((count($newCacheResults) - $lastIncrementalSaveCount) >= $cacheSaveBatchSize) {
                $saveIncrementalCache('local_batch');
            }
        }
        if ($localOutputCount > 0) {
            $this->SearchDedupService->markLocalResourcesUsed($localOutputResources);
            DiagnosticLogService::record('search', 'local_output', 'success', '本地资源已优先输出', [
                'keyword' => $title,
                'is_type' => $is_type,
                'output' => $localOutputCount,
                'invalid_skip' => $localInvalidSkipped,
            ]);
            Log::info('[搜索本地资源] keyword=' . $title . ' is_type=' . $is_type . ' output=' . $localOutputCount . ' invalid_skip=' . $localInvalidSkipped);
        } elseif ($localInvalidSkipped > 0) {
            DiagnosticLogService::record('search', 'local_output', 'warn', '本地资源均未通过有效性校验', [
                'keyword' => $title,
                'is_type' => $is_type,
                'output' => 0,
                'invalid_skip' => $localInvalidSkipped,
            ]);
            Log::info('[搜索本地资源] keyword=' . $title . ' is_type=' . $is_type . ' output=0 invalid_skip=' . $localInvalidSkipped);
        }

        // 4. 获取可用线路
        $lines = $this->getAvailableLines($is_type);
        if (empty($lines)) {
            DiagnosticLogService::record('search', 'no_line', 'warn', '暂无可用搜索线路', [
                'keyword' => $title,
                'is_type' => $is_type,
            ]);
            $this->SearchSseEmitter->event('DONE', '暂无可用线路');
            exit;
        }

        // 5. 遍历线路搜索
        $totalLines = count($lines);
        $currentLine = 0;
        $this->SearchSseEmitter->event('progress', ['total' => $totalLines, 'current' => 0, 'message' => '开始搜索...']);

        foreach ($lines as $line) {
            $currentLine++;
            $this->SearchSseEmitter->event('progress', ['total' => $totalLines, 'current' => $currentLine, 'message' => '正在搜索：' . $line['name']]);
            
            $this->SearchSseEmitter->line('线路：' . $line['name']);

            $lineStart = microtime(true);
            DiagnosticLogService::record('search', 'line_start', 'info', '开始请求搜索线路', [
                'keyword' => $title,
                'is_type' => $is_type,
                'line_name' => isset($line['name']) ? $line['name'] : '',
                'line_type' => isset($line['type']) ? $line['type'] : '',
                'line_pantype' => isset($line['pantype']) ? intval($line['pantype']) : -1,
                'limit' => isset($line['count']) ? intval($line['count']) : 0,
            ]);
            try {
                $result = $this->executeLineSearch($line, $title);
            } catch (\Throwable $e) {
                DiagnosticLogService::record('search', 'line_error', 'error', '搜索线路异常：' . $e->getMessage(), [
                    'keyword' => $title,
                    'is_type' => $is_type,
                    'line_name' => isset($line['name']) ? $line['name'] : '',
                    'line_type' => isset($line['type']) ? $line['type'] : '',
                    'duration_ms' => intval((microtime(true) - $lineStart) * 1000),
                ]);
                $result = [];
            }
            $lineTotal = is_array($result) ? count($result) : 0;
            $lineDuplicateSkipped = 0;
            $lineInvalidSkipped = 0;
            $lineOutputCount = 0;
            $linePantype = isset($line['pantype']) ? intval($line['pantype']) : -1;

            foreach ($result as $item) {
                if (($line['type'] ?? '') === 'tg' && !$this->isKeywordMatchedTitle($item['title'] ?? '', $title)) {
                    continue;
                }
                $detectedType = determineIsType($item['url']);
                if ($detectedType === 0 && $linePantype > 0) {
                    $detectedType = $linePantype;
                }
                $item['is_type'] = $detectedType;

                // 去重检查
                if (!$debugMode && $this->SearchDedupService->isDuplicate($item['url'], $cachedUrls, $localUrlsMap)) {
                    $lineDuplicateSkipped++;
                    DiagnosticLogService::record('search', 'duplicate_skip', 'info', '跳过重复搜索结果', [
                        'keyword' => $title,
                        'is_type' => $detectedType,
                        'line_name' => isset($line['name']) ? $line['name'] : '',
                        'line_type' => isset($line['type']) ? $line['type'] : '',
                        'url' => $item['url'],
                    ]);
                    continue;
                }

                // 有效性检测
                if (!$this->SearchResourceValidator->validate($item)) {
                    $lineInvalidSkipped++;
                    DiagnosticLogService::record('search', 'invalid_skip', 'warn', '跳过无效搜索结果', [
                        'keyword' => $title,
                        'is_type' => $detectedType,
                        'line_name' => isset($line['name']) ? $line['name'] : '',
                        'line_type' => isset($line['type']) ? $line['type'] : '',
                        'url' => $item['url'],
                    ]);
                    continue;
                }

                $newCacheResults[] = $this->SearchResultPresenter->outputNewResult($item, $is_show);
                $lineOutputCount++;
                if ((count($newCacheResults) - $lastIncrementalSaveCount) >= $cacheSaveBatchSize) {
                    $saveIncrementalCache('result_batch');
                }
            }

            Log::info('[搜索线路统计] line=' . ($line['name'] ?? '') . ' pantype=' . $linePantype . ' parsed=' . $lineTotal . ' output=' . $lineOutputCount . ' dedup_skip=' . $lineDuplicateSkipped . ' invalid_skip=' . $lineInvalidSkipped);
            DiagnosticLogService::record('search', 'line_result', 'success', '搜索线路完成', [
                'keyword' => $title,
                'is_type' => $is_type,
                'line_name' => isset($line['name']) ? $line['name'] : '',
                'line_type' => isset($line['type']) ? $line['type'] : '',
                'parsed' => $lineTotal,
                'output' => $lineOutputCount,
                'dedup_skip' => $lineDuplicateSkipped,
                'invalid_skip' => $lineInvalidSkipped,
                'duration_ms' => intval((microtime(true) - $lineStart) * 1000),
            ]);

            // 线路结束时保存不足一个批次的尾巴，避免中断时丢失已输出结果。
            $saveIncrementalCache('line_end');
        }

        // 6. 最终保存缓存
        if (!empty($newCacheResults) || !empty($cachedResults)) {
            $this->SearchResultCacheService->saveFinal($title, $is_type, $newCacheResults, $validCachedItems);
            $lastIncrementalSaveCount = count($newCacheResults);
        }

        $this->SearchSseEmitter->event('progress', ['total' => $totalLines, 'current' => $totalLines, 'message' => '搜索完成']);
        DiagnosticLogService::record('search', 'done', 'success', '搜索完成', [
            'keyword' => $title,
            'is_type' => $is_type,
            'line_count' => $totalLines,
            'new_count' => count($newCacheResults),
            'cache_count' => count($validCachedItems),
            'cache_skipped' => $cachedSkippedCount,
            'duration_ms' => intval((microtime(true) - $searchStart) * 1000),
        ]);
        $this->SearchSseEmitter->event('DONE');
        exit;
    }

    /**
     * 获取可用搜索线路
     */
    protected function getAvailableLines($is_type)
    {
        $normalLines = $this->ApiListModel
            ->where('status', 1)
            ->where('pantype', $is_type)
            ->where('type', 'in', SearchLineService::supportedTypes())
            ->where('type', 'not in', ['tg', 'pansou'])
            ->order('weight desc')
            ->select()
            ->toArray();

        $panSouLines = $this->ApiListModel
            ->where('status', 1)
            ->where('type', 'pansou')
            ->whereRaw('(FIND_IN_SET(:pansou_pan_type, pan_types) OR ((pan_types IS NULL OR pan_types = "") AND pantype = :pansou_legacy_pan_type))', [
                'pansou_pan_type' => $is_type,
                'pansou_legacy_pan_type' => $is_type,
            ])
            ->order('weight desc')
            ->select()
            ->toArray();

        foreach ($panSouLines as &$panSouLine) {
            $panSouLine['search_pantype'] = $is_type;
        }
        unset($panSouLine);

        $tgLines = $this->ApiListModel
            ->where('status', 1)
            ->where('type', 'tg')
            ->whereRaw('(FIND_IN_SET(:tg_pan_type, pan_types) OR ((pan_types IS NULL OR pan_types = "") AND pantype = :tg_legacy_pan_type))', [
                'tg_pan_type' => $is_type,
                'tg_legacy_pan_type' => $is_type,
            ])
            ->order('weight desc')
            ->select()
            ->toArray();

        $tgLines = $this->expandTgPoolLines($tgLines, $is_type);

        $lines = array_merge($normalLines, $panSouLines, $tgLines);
        usort($lines, function ($a, $b) {
            return intval($b['weight'] ?? 0) <=> intval($a['weight'] ?? 0);
        });

        return array_merge($this->getCustomLines(), $lines);
    }

    /**
     * 获取自定义线路配置
     */
    private function getCustomLines()
    {
        // 可以在这里添加自定义线路逻辑，保持与原代码一致
        return [];
    }

    private function expandTgPoolLines($lines, $isType)
    {
        $expanded = [];
        foreach ($lines as $line) {
            $channels = $this->extractEnabledTgChannels($line);
            if (empty($channels)) {
                $line['search_pantype'] = $isType;
                $expanded[] = $line;
                continue;
            }
            foreach ($channels as $channel) {
                $virtualLine = $line;
                $virtualLine['name'] = ($line['name'] ?? 'TG资源池') . ' / ' . $channel;
                $virtualLine['url'] = $channel;
                $virtualLine['tg_channels'] = json_encode([
                    ['channel' => $channel, 'enabled' => 1],
                ], JSON_UNESCAPED_UNICODE);
                $virtualLine['search_pantype'] = $isType;
                $expanded[] = $virtualLine;
            }
        }
        return $expanded;
    }

    private function extractEnabledTgChannels($line)
    {
        $raw = $line['tg_channels'] ?? '';
        $channels = [];
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $enabled = isset($item['enabled']) ? intval($item['enabled']) === 1 : true;
                    $channel = $this->normalizeTgChannel($item['channel'] ?? ($item['url'] ?? ''));
                    if ($enabled && $channel !== '') {
                        $channels[] = $channel;
                    }
                }
            }
        }
        if (empty($channels)) {
            foreach (preg_split('/[,\r\n]+/', (string)($line['url'] ?? '')) as $item) {
                $channel = $this->normalizeTgChannel($item);
                if ($channel !== '') {
                    $channels[] = $channel;
                }
            }
        }
        return array_values(array_unique($channels));
    }

    private function normalizeTgChannel($channel)
    {
        $channel = trim((string)$channel);
        $channel = preg_replace('#^https?://t\.me/s/#i', '', $channel);
        $channel = preg_replace('#^https?://t\.me/#i', '', $channel);
        return trim($channel, " \t\n\r\0\x0B/@");
    }

    /**
     * 执行单条线路搜索
     */
    protected function executeLineSearch($line, $title)
    {
        return $this->SearchLineService->search($line, $title);
    }

    private function isKeywordMatchedTitle($title, $keyword)
    {
        $title = preg_replace('/\s+/u', '', (string)$title);
        $keyword = preg_replace('/\s+/u', '', (string)$keyword);
        if ($keyword === '') {
            return true;
        }
        return mb_stripos($title, $keyword, 0, 'UTF-8') !== false;
    }

}
