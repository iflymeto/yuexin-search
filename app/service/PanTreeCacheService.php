<?php

namespace app\service;

class PanTreeCacheService
{
    private $baseDir;

    public function __construct($baseDir = null)
    {
        if ($baseDir !== null) {
            $this->baseDir = $baseDir;
            return;
        }

        $rootPath = rtrim(app()->getRootPath(), "\\/");
        $this->baseDir = $rootPath . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'pan_tree_cache';
    }

    public function makeKey($isType, $url, $code = '', $stoken = '')
    {
        // stoken 是夸克/UC 的临时访问凭证，会过期；目录树缓存应按原链接和提取码长期复用。
        return md5((int)$isType . '|' . (string)$url . '|' . (string)$code);
    }

    public function get($key)
    {
        $file = $this->getFilePath($key);
        if (!is_file($file)) {
            return null;
        }

        $content = @file_get_contents($file);
        if ($content === false || $content === '') {
            return null;
        }

        $payload = json_decode($content, true);
        if (!is_array($payload) || !array_key_exists('data', $payload)) {
            return null;
        }

        return $payload['data'];
    }

    public function set($key, $data)
    {
        $file = $this->getFilePath($key);
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $payload = [
            'created_at' => time(),
            'data' => $data,
        ];

        return @file_put_contents(
            $file,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        ) !== false;
    }

    private function getFilePath($key)
    {
        $safeKey = preg_replace('/[^a-f0-9]/', '', strtolower((string)$key));
        if (strlen($safeKey) !== 32) {
            $safeKey = md5($safeKey);
        }

        return $this->baseDir
            . DIRECTORY_SEPARATOR . substr($safeKey, 0, 2)
            . DIRECTORY_SEPARATOR . $safeKey . '.json';
    }
}
