<?php

namespace app\api\controller;

use app\api\QfShop;
use app\model\Source as SourceModel;
use app\model\SourceCategory as SourceCategoryModel;

class Search extends QfShop
{
    /**
     * 搜索联想接口：优先使用腾讯视频联想词，失败时回退本地标题联想。
     *
     * @return \think\Response
     */
    public function suggestion()
    {
        $keyword = trim((string)input('keyword', ''));
        if ($keyword === '') {
            return $this->success([], '无关键词');
        }

        $suggestions = $this->fetchTencentVideoSuggestions($keyword);
        if (empty($suggestions)) {
            $suggestions = $this->fetchLocalSuggestions($keyword);
        }

        return $this->success(array_values(array_unique($suggestions)), '获取成功');
    }

    public function index()
    {
        $SourceModel = new SourceModel();
        $data = $SourceModel->getList(input(''));
        return jok('获取成功', $data);
    }

    public function getDetail()
    {
        $SourceModel = new SourceModel();
        $data = $SourceModel->getDetail(input(''));
        return jok('获取成功', $data);
    }

    public function getNew()
    {
        $SourceModel = new SourceModel();
        $data = input('');
        $data['page_size'] = $data['page_size'] ?? 20;
        $data = $SourceModel->getNew($data);
        return jok('获取成功', $data);
    }

    public function getHot()
    {
        $SourceModel = new SourceModel();
        $data = $SourceModel->getHot(input(''));
        return jok('获取成功', $data);
    }

    public function getCategory()
    {
        $SourceCategoryModel = new SourceCategoryModel();
        $data = $SourceCategoryModel->getList(input(''));
        return jok('获取成功', $data);
    }

    private function fetchTencentVideoSuggestions($keyword)
    {
        $url = 'https://actapi.video.qq.com/trpc.videosearch.smartboxServer.SugRecallHttp/GetSugHttp';
        $response = $this->httpPostJson($url, [
            'query' => $keyword,
            'page_size' => 10,
            'auth_info' => new \stdClass(),
        ], 4);
        if (!$response) {
            return [];
        }

        $data = json_decode($response, true);
        $items = $data['data']['result_list']['item_list'] ?? [];
        if (!is_array($data) || empty($items) || !is_array($items)) {
            return [];
        }

        $suggestions = [];
        foreach ($items as $item) {
            $value = $item['view']['lines'][0]['text'] ?? '';
            if ($value === '') {
                continue;
            }
            $value = trim(html_entity_decode(strip_tags((string)$value), ENT_QUOTES, 'UTF-8'));
            if ($value !== '') {
                $suggestions[] = $value;
            }
            if (count($suggestions) >= 10) {
                break;
            }
        }

        return $suggestions;
    }

    private function fetchLocalSuggestions($keyword)
    {
        $SourceModel = new SourceModel();

        $suggestions = $SourceModel->where('title', 'like', $keyword . '%')
            ->where('is_delete', 0)
            ->where('status', 1)
            ->limit(10)
            ->column('title');

        if (count($suggestions) < 5) {
            $moreSuggestions = $SourceModel->where('title', 'like', '%' . $keyword . '%')
                ->where('is_delete', 0)
                ->where('status', 1)
                ->whereNotIn('title', $suggestions ?: [''])
                ->limit(10 - count($suggestions))
                ->column('title');

            $suggestions = array_merge($suggestions, $moreSuggestions);
        }

        return array_map(static function ($item) {
            return trim(strip_tags((string)$item));
        }, $suggestions);
    }

    private function httpPostJson($url, array $data, $timeout = 4)
    {
        $ch = curl_init($url);
        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json,text/plain,*/*',
                'Content-Type: application/json',
                'Origin: https://v.qq.com',
                'Referer: https://v.qq.com/',
            ],
            CURLOPT_USERAGENT => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1',
        ]);

        $response = curl_exec($ch);
        $error = curl_errno($ch);
        curl_close($ch);

        return $error ? '' : (string)$response;
    }
}
