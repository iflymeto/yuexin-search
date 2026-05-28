<?php

namespace app\service;

use Exception;

class PanTreeService
{
    const API_TIMEOUT = 30;
    const QUARK_UC_PAGE_SIZE = 200;
    const BAIDU_PAGE_SIZE = 100;
    const MAX_DEPTH = 20;
    const MAX_ITEMS = 2000;

    private $itemCount = 0;
    private $truncated = false;

    public function getTree($url, $isType, $code = '', $stoken = '')
    {
        $this->itemCount = 0;
        $this->truncated = false;

        $isType = intval($isType);
        if ($isType === 0) {
            return $this->getQuarkTree($url, $code, $stoken);
        }
        if ($isType === 2) {
            return $this->getBaiduTree($url, $code);
        }
        if ($isType === 3) {
            return $this->getUcTree($url, $code, $stoken);
        }
        if ($isType === 4) {
            throw new Exception('暂不支持迅雷目录预览');
        }

        throw new Exception('当前网盘暂不支持目录预览');
    }

    private function getQuarkTree($url, $code, $stoken)
    {
        $pwdId = $this->parseQuarkShareUrl($url);
        if (!$pwdId) {
            throw new Exception('资源地址格式有误，请提供正确的夸克分享链接');
        }

        $tokenInfo = [];
        if ($stoken === '') {
            $tokenInfo = $this->getQuarkUcPublicStoken(
                'https://drive-pc.quark.cn/1/clouddrive/share/sharepage/token',
                $pwdId,
                $code,
                $this->quarkQueryParams()
            );
            $stoken = isset($tokenInfo['stoken']) ? $tokenInfo['stoken'] : '';
        }
        if ($stoken === '') {
            throw new Exception('获取分享访问令牌失败');
        }

        $rootDetail = $this->getQuarkUcShareDetail(
            'https://drive-pc.quark.cn/1/clouddrive/share/sharepage/detail',
            $pwdId,
            $stoken,
            '0',
            1,
            $this->quarkQueryParams()
        );
        $share = isset($rootDetail['share']) ? $rootDetail['share'] : [];
        $title = isset($share['title']) ? $share['title'] : (isset($tokenInfo['title']) ? $tokenInfo['title'] : '未知资源');
        $items = isset($rootDetail['list']) && is_array($rootDetail['list']) ? $rootDetail['list'] : [];
        $tree = $this->buildQuarkUcTree('quark', $pwdId, $stoken, $items, 0);

        return $this->formatResult($title, 'https://pan.quark.cn/s/' . $pwdId, ['pwd_id' => $pwdId], $tree);
    }

    private function getUcTree($url, $code, $stoken)
    {
        $pwdId = $this->parseUcShareUrl($url);
        if (!$pwdId) {
            throw new Exception('资源地址格式有误，请提供正确的UC网盘分享链接');
        }

        $tokenInfo = [];
        if ($stoken === '') {
            $tokenInfo = $this->getQuarkUcPublicStoken(
                'https://pc-api.uc.cn/1/clouddrive/share/sharepage/token',
                $pwdId,
                $code,
                $this->ucQueryParams()
            );
            $stoken = isset($tokenInfo['stoken']) ? $tokenInfo['stoken'] : '';
        }
        if ($stoken === '') {
            throw new Exception('获取分享访问令牌失败');
        }

        $rootDetail = $this->getQuarkUcShareDetail(
            'https://pc-api.uc.cn/1/clouddrive/share/sharepage/detail',
            $pwdId,
            $stoken,
            '0',
            1,
            $this->ucQueryParams()
        );
        $share = isset($rootDetail['share']) ? $rootDetail['share'] : [];
        $title = isset($share['title']) ? $share['title'] : (isset($tokenInfo['title']) ? $tokenInfo['title'] : '未知资源');
        $items = isset($rootDetail['list']) && is_array($rootDetail['list']) ? $rootDetail['list'] : [];
        $tree = $this->buildQuarkUcTree('uc', $pwdId, $stoken, $items, 0);

        return $this->formatResult($title, 'https://drive.uc.cn/s/' . $pwdId, ['pwd_id' => $pwdId], $tree);
    }

    private function getBaiduTree($url, $code)
    {
        $info = $this->parseBaiduShareUrl($url);
        if (!$info) {
            throw new Exception('资源地址格式有误，请提供正确的百度网盘分享链接');
        }
        if ($code === '' && $info['pwd'] !== '') {
            $code = $info['pwd'];
        }

        $cookieFile = tempnam(sys_get_temp_dir(), 'bd_tree_');
        try {
            $client = [
                'cookie_file' => $cookieFile,
                'referer' => $url,
            ];

            if ($code !== '') {
                $this->verifyBaiduShareCode($client, $info['surl'], $code);
            }

            $pageHtml = $this->baiduHttpRequest($client, $url, 'GET');
            $locals = $this->parseBaiduLocalsData($pageHtml);
            if (isset($locals['errno']) && intval($locals['errno']) !== 0) {
                throw new Exception(isset($locals['show_msg']) ? $locals['show_msg'] : '分享页面读取失败');
            }

            $shareId = isset($locals['shareid']) ? $locals['shareid'] : null;
            $shareUk = isset($locals['share_uk']) ? $locals['share_uk'] : null;
            if (!$shareId || !$shareUk) {
                throw new Exception('分享参数解析失败');
            }

            $rootItems = $this->normalizeBaiduFileList(isset($locals['file_list']) ? $locals['file_list'] : []);
            $tree = $this->buildBaiduTree($client, $shareId, $shareUk, $rootItems, 0);

            return $this->formatResult('', $url, [
                'shareid' => $shareId,
                'share_uk' => $shareUk,
                'surl' => $info['surl'],
            ], $tree);
        } finally {
            if (is_string($cookieFile) && file_exists($cookieFile)) {
                @unlink($cookieFile);
            }
        }
    }

    private function formatResult($title, $shareUrl, $extra, $tree)
    {
        return array_merge($extra, [
            'title' => $title,
            'share_url' => $shareUrl,
            'total' => $this->countTreeItems($tree),
            'truncated' => $this->truncated,
            'tree_text' => $this->renderTreeText($tree),
            'tree' => $tree,
        ]);
    }

    private function parseQuarkShareUrl($url)
    {
        if (preg_match('~pan\.quark\.cn/s/([^/?#\s]+)~', $url, $matches)) {
            return $matches[1];
        }
        if (preg_match('~^([A-Za-z0-9_-]+)$~', $url, $matches)) {
            return $matches[1];
        }
        return false;
    }

    private function parseUcShareUrl($url)
    {
        if (preg_match('~drive\.uc\.cn/s/([^/?#\s]+)~', $url, $matches)) {
            return $matches[1];
        }
        if (preg_match('~^([A-Za-z0-9_-]+)$~', $url, $matches)) {
            return $matches[1];
        }
        return false;
    }

    private function parseBaiduShareUrl($url)
    {
        $pwd = '';
        $parts = parse_url($url);
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
            if (isset($query['pwd'])) {
                $pwd = trim($query['pwd']);
            }
        }

        if (preg_match('~pan\.baidu\.com/s/1([^/?#\s]+)~', $url, $matches)) {
            return ['surl' => $matches[1], 'pwd' => $pwd];
        }
        if (preg_match('~^1?([A-Za-z0-9_-]+)$~', $url, $matches)) {
            return ['surl' => $matches[1], 'pwd' => $pwd];
        }
        return false;
    }

    private function getQuarkUcPublicStoken($endpoint, $pwdId, $passcode, $baseQuery)
    {
        $res = $this->publicApiRequest($endpoint, 'POST', [
            'passcode' => $passcode,
            'pwd_id' => $pwdId,
        ], $baseQuery, $baseQuery['referer']);

        if (!isset($res['status']) || intval($res['status']) !== 200) {
            throw new Exception(isset($res['message']) ? $res['message'] : '获取分享访问令牌失败');
        }

        return isset($res['data']) && is_array($res['data']) ? $res['data'] : [];
    }

    private function getQuarkUcShareDetail($endpoint, $pwdId, $stoken, $pdirFid, $page, $baseQuery)
    {
        $query = array_merge($baseQuery, [
            'pwd_id' => $pwdId,
            'stoken' => $stoken,
            'pdir_fid' => $pdirFid,
            'force' => '0',
            '_page' => $page,
            '_size' => self::QUARK_UC_PAGE_SIZE,
            '_fetch_banner' => '1',
            '_fetch_share' => '1',
            '_fetch_total' => '1',
            '_sort' => 'file_type:asc,updated_at:desc',
        ]);
        $referer = $query['referer'];
        unset($query['referer']);

        $res = $this->publicApiRequest($endpoint, 'GET', [], $query, $referer);
        if (!isset($res['status']) || intval($res['status']) !== 200) {
            throw new Exception(isset($res['message']) ? $res['message'] : '获取分享目录失败');
        }

        return isset($res['data']) && is_array($res['data']) ? $res['data'] : [];
    }

    private function getAllQuarkUcShareItems($provider, $pwdId, $stoken, $pdirFid)
    {
        $all = [];
        $page = 1;
        $endpoint = $provider === 'uc'
            ? 'https://pc-api.uc.cn/1/clouddrive/share/sharepage/detail'
            : 'https://drive-pc.quark.cn/1/clouddrive/share/sharepage/detail';
        $query = $provider === 'uc' ? $this->ucQueryParams() : $this->quarkQueryParams();

        while (!$this->truncated) {
            $detail = $this->getQuarkUcShareDetail($endpoint, $pwdId, $stoken, $pdirFid, $page, $query);
            $list = isset($detail['list']) && is_array($detail['list']) ? $detail['list'] : [];
            $all = array_merge($all, $list);

            if (count($list) < self::QUARK_UC_PAGE_SIZE) {
                break;
            }
            $page++;
        }

        return $all;
    }

    private function buildQuarkUcTree($provider, $pwdId, $stoken, $items, $depth)
    {
        if ($depth >= self::MAX_DEPTH || $this->truncated) {
            return [];
        }

        $tree = [];
        foreach ($items as $item) {
            if (!$this->reserveItemSlot()) {
                break;
            }

            $isDir = !empty($item['dir']);
            $node = [
                'fid' => isset($item['fid']) ? $item['fid'] : '',
                'name' => isset($item['file_name']) ? $item['file_name'] : '',
                'type' => $isDir ? 'folder' : 'file',
                'size' => isset($item['size']) ? $item['size'] : 0,
                'children' => [],
            ];

            if ($isDir && $node['fid'] !== '') {
                $children = $this->getAllQuarkUcShareItems($provider, $pwdId, $stoken, $node['fid']);
                $node['children'] = $this->buildQuarkUcTree($provider, $pwdId, $stoken, $children, $depth + 1);
            }

            $tree[] = $node;
        }

        return $tree;
    }

    private function verifyBaiduShareCode($client, $surl, $code)
    {
        $url = 'https://pan.baidu.com/share/verify?surl=' . rawurlencode($surl);
        $body = http_build_query([
            'pwd' => $code,
            'vcode' => '',
            'vcode_str' => '',
        ]);

        $response = $this->baiduHttpRequest($client, $url, 'POST', $body, [
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With: XMLHttpRequest',
        ]);
        $result = json_decode($response, true);
        if (!is_array($result) || !isset($result['errno']) || intval($result['errno']) !== 0) {
            $message = is_array($result) && isset($result['err_msg']) ? $result['err_msg'] : '提取码校验失败';
            throw new Exception($message === '' ? '提取码校验失败' : $message);
        }
    }

    private function parseBaiduLocalsData($html)
    {
        if (!preg_match('~locals\.mset\((.*?)\);~s', $html, $matches)) {
            throw new Exception('页面初始化数据解析失败');
        }

        $data = json_decode($matches[1], true);
        if (!is_array($data)) {
            throw new Exception('页面初始化数据不是有效JSON');
        }

        return $data;
    }

    private function normalizeBaiduFileList($fileList)
    {
        if (!is_array($fileList)) {
            return [];
        }
        if (isset($fileList['fs_id']) || isset($fileList['server_filename'])) {
            return [$fileList];
        }
        return $fileList;
    }

    private function getBaiduShareDirItems($client, $shareId, $shareUk, $dir)
    {
        $all = [];
        $page = 1;

        while (!$this->truncated) {
            $query = [
                'app_id' => '250528',
                'web' => '1',
                'channel' => 'chunlei',
                'clienttype' => '0',
                'shareid' => $shareId,
                'uk' => $shareUk,
                'desc' => '1',
                'showempty' => '0',
                'page' => $page,
                'num' => self::BAIDU_PAGE_SIZE,
                'order' => 'time',
                'dir' => $dir,
            ];
            $url = 'https://pan.baidu.com/share/list?' . http_build_query($query);
            $response = $this->baiduHttpRequest($client, $url, 'GET', null, [
                'X-Requested-With: XMLHttpRequest',
            ]);
            $result = json_decode($response, true);
            if (!is_array($result) || !isset($result['errno']) || intval($result['errno']) !== 0) {
                $message = is_array($result) && isset($result['show_msg']) ? $result['show_msg'] : '获取分享目录失败';
                throw new Exception($message);
            }

            $list = isset($result['list']) && is_array($result['list']) ? $result['list'] : [];
            $all = array_merge($all, $list);
            if (count($list) < self::BAIDU_PAGE_SIZE) {
                break;
            }
            $page++;
        }

        return $all;
    }

    private function buildBaiduTree($client, $shareId, $shareUk, $items, $depth)
    {
        if ($depth >= self::MAX_DEPTH || $this->truncated) {
            return [];
        }

        $tree = [];
        foreach ($items as $item) {
            if (!$this->reserveItemSlot()) {
                break;
            }

            $isDir = isset($item['isdir']) && intval($item['isdir']) === 1;
            $node = [
                'fs_id' => isset($item['fs_id']) ? $item['fs_id'] : '',
                'name' => isset($item['server_filename']) ? $item['server_filename'] : '',
                'type' => $isDir ? 'folder' : 'file',
                'size' => isset($item['size']) ? $item['size'] : 0,
                'path' => isset($item['path']) ? $item['path'] : '',
                'children' => [],
            ];

            if ($isDir && $node['path'] !== '') {
                $children = $this->getBaiduShareDirItems($client, $shareId, $shareUk, $node['path']);
                $node['children'] = $this->buildBaiduTree($client, $shareId, $shareUk, $children, $depth + 1);
            }

            $tree[] = $node;
        }

        return $tree;
    }

    private function reserveItemSlot()
    {
        if ($this->itemCount >= self::MAX_ITEMS) {
            $this->truncated = true;
            return false;
        }
        $this->itemCount++;
        return true;
    }

    private function renderTreeText($tree, $prefix = '')
    {
        $lines = [];
        $count = count($tree);

        for ($i = 0; $i < $count; $i++) {
            $node = $tree[$i];
            $isLast = ($i === $count - 1);
            $branch = $isLast ? '└── ' : '├── ';
            $name = (isset($node['name']) ? $node['name'] : '') . ((isset($node['type']) && $node['type'] === 'folder') ? '/' : '');
            $lines[] = $prefix . $branch . $name;

            if (!empty($node['children'])) {
                $nextPrefix = $prefix . ($isLast ? '    ' : '│   ');
                $childText = $this->renderTreeText($node['children'], $nextPrefix);
                if ($childText !== '') {
                    $lines[] = $childText;
                }
            }
        }

        if ($prefix === '' && $this->truncated) {
            $lines[] = '... 目录过大，仅展示前 ' . self::MAX_ITEMS . ' 项';
        }

        return implode("\n", $lines);
    }

    private function countTreeItems($tree)
    {
        $total = 0;
        foreach ($tree as $node) {
            $total++;
            if (!empty($node['children'])) {
                $total += $this->countTreeItems($node['children']);
            }
        }

        return $total;
    }

    private function publicApiRequest($url, $method, $data, $queryParams, $referer)
    {
        if (isset($queryParams['referer'])) {
            unset($queryParams['referer']);
        }
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        $headers = [
            'Accept: application/json, text/plain, */*',
            'Accept-Language: zh-CN,zh;q=0.9',
            'Content-Type: application/json;charset=UTF-8',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Referer: ' . $referer,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::API_TIMEOUT);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::API_TIMEOUT);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('请求失败: ' . $error);
        }
        curl_close($ch);

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('响应解析失败');
        }

        return $result;
    }

    private function baiduHttpRequest($client, $url, $method = 'GET', $body = null, $extraHeaders = [])
    {
        $headers = array_merge([
            'Accept: application/json, text/plain, */*',
            'Accept-Language: zh-CN,zh;q=0.9',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Referer: ' . $client['referer'],
        ], $extraHeaders);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::API_TIMEOUT);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::API_TIMEOUT);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $client['cookie_file']);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $client['cookie_file']);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('请求失败: ' . $error);
        }

        curl_close($ch);
        return $response;
    }

    private function quarkQueryParams()
    {
        return [
            'pr' => 'ucpro',
            'fr' => 'pc',
            'uc_param_str' => '',
            'referer' => 'https://pan.quark.cn/',
        ];
    }

    private function ucQueryParams()
    {
        return [
            'pr' => 'UCBrowser',
            'fr' => 'pc',
            'uc_param_str' => '',
            'referer' => 'https://drive.uc.cn/',
        ];
    }
}
