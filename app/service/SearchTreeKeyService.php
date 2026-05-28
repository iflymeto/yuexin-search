<?php

namespace app\service;

class SearchTreeKeyService
{
    private $panTreePreviewService;

    public function __construct(PanTreePreviewService $panTreePreviewService = null)
    {
        $this->panTreePreviewService = $panTreePreviewService ?: new PanTreePreviewService();
    }

    public function appendKey(array $item)
    {
        $treeKey = $this->panTreePreviewService->createKey(
            isset($item['url']) ? $item['url'] : '',
            isset($item['is_type']) ? $item['is_type'] : -1,
            isset($item['code']) ? $item['code'] : '',
            isset($item['stoken']) ? $item['stoken'] : ''
        );

        if ($treeKey !== '') {
            $item['tree_key'] = $treeKey;
        }

        return $item;
    }
}
