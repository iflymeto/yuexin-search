<?php

namespace app\service;

use think\App;

class SearchLineService
{
    private $app;
    private $apiParser;
    private $tgParser;
    private $htmlParser;

    public function __construct(App $app = null)
    {
        $this->app = $app ?: app();
        $this->apiParser = new SearchApiLineParser();
        $this->tgParser = new SearchTgLineParser();
        $this->htmlParser = new SearchHtmlLineParser();
    }

    public function search($line, $keyword)
    {
        $type = $this->normalizeType($line);
        if (!$this->isSupportedType($type)) {
            DiagnosticLogService::record('search', 'unsupported_line_type', 'warn', '搜索线路类型已停用或不支持', [
                'keyword' => $keyword,
                'is_type' => isset($line['pantype']) ? intval($line['pantype']) : -1,
                'line_name' => isset($line['name']) ? $line['name'] : '',
                'line_type' => $type,
            ]);
            return [];
        }

        if ($type === 'tg') {
            return $this->tgParser->parse($line, $keyword);
        }
        if ($type === 'html') {
            return $this->htmlParser->parse($line, $keyword);
        }
        return $this->apiParser->parse($line, $keyword);
    }

    public function isSupportedType($type)
    {
        return in_array($type, self::supportedTypes(), true);
    }

    public static function supportedTypes()
    {
        return ['api', 'pansou', 'tg', 'html'];
    }

    private function normalizeType($line)
    {
        $type = isset($line['type']) ? trim((string)$line['type']) : 'api';
        return $type === '' ? 'api' : strtolower($type);
    }
}
