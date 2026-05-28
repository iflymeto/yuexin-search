<?php

namespace app\service;

class FrontendThemeService
{
    private $defaultTheme = 'news';
    private $requiredTemplates = ['index', 'list', 'detail'];

    public function all()
    {
        $baseDir = $this->getThemeBaseDir();
        if (!is_dir($baseDir)) {
            return [$this->fallbackTheme()];
        }

        $themes = [];
        $dirs = scandir($baseDir);
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $dir)) {
                continue;
            }

            $themeDir = $baseDir . DIRECTORY_SEPARATOR . $dir;
            if (!is_dir($themeDir)) {
                continue;
            }
            if (!$this->looksLikeTheme($themeDir)) {
                continue;
            }

            $themes[] = $this->buildThemeInfo($themeDir, $dir);
        }

        usort($themes, function ($a, $b) {
            if ($a['sort'] === $b['sort']) {
                return strcmp($a['key'], $b['key']);
            }
            return $a['sort'] <=> $b['sort'];
        });

        return empty($themes) ? [$this->fallbackTheme()] : $themes;
    }

    public function exists($themeKey)
    {
        $themeKey = (string)$themeKey;
        foreach ($this->all() as $theme) {
            if ($theme['key'] === $themeKey && !empty($theme['is_valid'])) {
                return true;
            }
        }

        return false;
    }

    public function optionsText()
    {
        $lines = [];
        foreach ($this->all() as $theme) {
            if (empty($theme['is_valid'])) {
                continue;
            }
            $lines[] = $theme['name'] . '=>' . $theme['key'];
        }

        return implode("\n", $lines);
    }

    public function defaultTheme()
    {
        return $this->defaultTheme;
    }

    public function createFromDefault($themeKey, $themeName, $author = '', $description = '')
    {
        $themeKey = trim((string)$themeKey);
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $themeKey)) {
            throw new \Exception('主题标识只能包含字母、数字、下划线和短横线');
        }
        if ($themeKey === $this->defaultTheme) {
            throw new \Exception('不能覆盖默认主题');
        }

        $themeName = trim((string)$themeName);
        if ($themeName === '') {
            throw new \Exception('主题名称不能为空');
        }

        $baseDir = $this->getThemeBaseDir();
        $sourceDir = $baseDir . DIRECTORY_SEPARATOR . $this->defaultTheme;
        $targetDir = $baseDir . DIRECTORY_SEPARATOR . $themeKey;
        if (!is_dir($sourceDir)) {
            throw new \Exception('默认主题目录不存在');
        }
        if (file_exists($targetDir)) {
            throw new \Exception('主题目录已存在');
        }

        try {
            $this->copyDirectory($sourceDir, $targetDir, $this->defaultTheme, $themeKey);
            $this->writeMeta($targetDir, [
                'key' => $themeKey,
                'name' => $themeName,
                'version' => '1.0.0',
                'author' => trim((string)$author),
                'description' => trim((string)$description) ?: '基于默认主题创建的自定义主题。',
                'screenshot' => 'screenshot.png',
            ]);
        } catch (\Exception $e) {
            $this->removeDirectory($targetDir);
            throw $e;
        }

        return $this->buildThemeInfo($targetDir, $themeKey);
    }

    public function deleteTheme($themeKey)
    {
        $themeKey = trim((string)$themeKey);
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $themeKey)) {
            throw new \Exception('主题标识不合法');
        }
        if ($themeKey === $this->defaultTheme) {
            throw new \Exception('默认主题受系统保护，不能删除');
        }

        $themeDir = $this->getThemeBaseDir() . DIRECTORY_SEPARATOR . $themeKey;
        $baseReal = realpath($this->getThemeBaseDir());
        $themeReal = realpath($themeDir);
        if ($baseReal === false || $themeReal === false || !is_dir($themeReal)) {
            throw new \Exception('主题目录不存在');
        }
        if (strpos($themeReal, $baseReal . DIRECTORY_SEPARATOR) !== 0) {
            throw new \Exception('主题目录路径异常，已拒绝删除');
        }
        if (!$this->looksLikeTheme($themeReal)) {
            throw new \Exception('目标目录不是有效主题目录，已拒绝删除');
        }

        $this->removeDirectory($themeReal);
        if (is_dir($themeReal)) {
            throw new \Exception('主题删除失败，请检查目录权限');
        }

        return true;
    }

    public function updateMeta($themeKey, array $meta)
    {
        $themeKey = trim((string)$themeKey);
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $themeKey)) {
            throw new \Exception('主题标识不合法');
        }
        if ($themeKey === $this->defaultTheme) {
            throw new \Exception('默认主题受系统保护，不能编辑');
        }

        $themeDir = $this->getThemeBaseDir() . DIRECTORY_SEPARATOR . $themeKey;
        $themeReal = realpath($themeDir);
        $baseReal = realpath($this->getThemeBaseDir());
        if ($baseReal === false || $themeReal === false || !is_dir($themeReal)) {
            throw new \Exception('主题目录不存在');
        }
        if (strpos($themeReal, $baseReal . DIRECTORY_SEPARATOR) !== 0) {
            throw new \Exception('主题目录路径异常，已拒绝编辑');
        }
        if (!$this->looksLikeTheme($themeReal)) {
            throw new \Exception('目标目录不是有效主题目录，已拒绝编辑');
        }

        $name = trim((string)($meta['name'] ?? ''));
        if ($name === '') {
            throw new \Exception('主题名称不能为空');
        }
        $version = trim((string)($meta['version'] ?? '1.0.0'));
        $author = trim((string)($meta['author'] ?? ''));
        $description = trim((string)($meta['description'] ?? ''));
        $screenshot = trim((string)($meta['screenshot'] ?? ''));
        $settings = $this->normalizeSettings($meta['settings'] ?? []);
        if (strpos($screenshot, '..') !== false || strpos($screenshot, '\\') !== false) {
            throw new \Exception('预览图路径不合法');
        }

        $this->writeMeta($themeReal, [
            'key' => $themeKey,
            'name' => $name,
            'version' => $version,
            'author' => $author,
            'description' => $description,
            'screenshot' => $screenshot,
            'settings' => $settings,
        ]);

        return $this->buildThemeInfo($themeReal, $themeKey);
    }

    public function exportTheme($themeKey)
    {
        $themeKey = trim((string)$themeKey);
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $themeKey)) {
            throw new \Exception('主题标识不合法');
        }

        $themeDir = $this->getThemeBaseDir() . DIRECTORY_SEPARATOR . $themeKey;
        $baseReal = realpath($this->getThemeBaseDir());
        $themeReal = realpath($themeDir);
        if ($baseReal === false || $themeReal === false || !is_dir($themeReal)) {
            throw new \Exception('主题目录不存在');
        }
        if (strpos($themeReal, $baseReal . DIRECTORY_SEPARATOR) !== 0) {
            throw new \Exception('主题目录路径异常，已拒绝导出');
        }
        if (!$this->looksLikeTheme($themeReal)) {
            throw new \Exception('目标目录不是有效主题目录，已拒绝导出');
        }
        if (!class_exists('\ZipArchive')) {
            throw new \Exception('当前 PHP 未启用 ZipArchive，无法导出主题');
        }

        $exportDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'theme_export';
        if (!is_dir($exportDir) && !mkdir($exportDir, 0755, true)) {
            throw new \Exception('导出缓存目录创建失败');
        }

        $zipPath = $exportDir . DIRECTORY_SEPARATOR . $themeKey . '-' . date('YmdHis') . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('导出文件创建失败');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($themeReal, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $relativePath = str_replace('\\', '/', substr($item->getPathname(), strlen($themeReal) + 1));
            $zipPathName = $themeKey . '/' . $relativePath;
            if ($item->isDir()) {
                $zip->addEmptyDir($zipPathName);
            } else {
                $zip->addFile($item->getPathname(), $zipPathName);
            }
        }
        $zip->close();

        if (!is_file($zipPath)) {
            throw new \Exception('导出文件生成失败');
        }

        return [
            'path' => $zipPath,
            'filename' => basename($zipPath),
        ];
    }

    public function importTheme($zipPath, $themeKey, $themeName = '')
    {
        $themeKey = trim((string)$themeKey);
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $themeKey)) {
            throw new \Exception('主题标识只能包含字母、数字、下划线和短横线');
        }
        if ($themeKey === $this->defaultTheme) {
            throw new \Exception('不能覆盖默认主题');
        }
        if (!is_file($zipPath)) {
            throw new \Exception('上传文件不存在');
        }
        if (strtolower(pathinfo($zipPath, PATHINFO_EXTENSION)) !== 'zip') {
            throw new \Exception('只支持 zip 主题包');
        }
        if (filesize($zipPath) > 20 * 1024 * 1024) {
            throw new \Exception('主题包不能超过 20MB');
        }
        if (!class_exists('\ZipArchive')) {
            throw new \Exception('当前 PHP 未启用 ZipArchive，无法导入主题');
        }

        $baseDir = $this->getThemeBaseDir();
        $targetDir = $baseDir . DIRECTORY_SEPARATOR . $themeKey;
        if (file_exists($targetDir)) {
            throw new \Exception('主题目录已存在，导入不会覆盖已有主题');
        }

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new \Exception('主题包打开失败');
        }
        $this->validateZipEntries($zip);

        $importDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'theme_import';
        if (!is_dir($importDir) && !mkdir($importDir, 0755, true)) {
            $zip->close();
            throw new \Exception('导入缓存目录创建失败');
        }
        $tempDir = $importDir . DIRECTORY_SEPARATOR . $themeKey . '-' . date('YmdHis') . '-' . mt_rand(1000, 9999);
        if (!mkdir($tempDir, 0755, true)) {
            $zip->close();
            throw new \Exception('临时解压目录创建失败');
        }

        try {
            if (!$zip->extractTo($tempDir)) {
                throw new \Exception('主题包解压失败');
            }
            $zip->close();

            $sourceDir = $this->findImportedThemeRoot($tempDir);
            if (!$sourceDir || !$this->looksLikeTheme($sourceDir)) {
                throw new \Exception('主题包内未找到有效主题目录');
            }
            $missing = $this->missingTemplates($sourceDir);
            if (!empty($missing)) {
                throw new \Exception('主题包缺少模板：' . implode('、', $missing));
            }

            if (!rename($sourceDir, $targetDir)) {
                $this->copyDirectory($sourceDir, $targetDir, '', '');
            }
            if (!is_dir($targetDir)) {
                throw new \Exception('主题目录移动失败');
            }

            $meta = $this->readMeta($targetDir, $themeKey);
            if (trim((string)$themeName) !== '') {
                $meta['name'] = trim((string)$themeName);
            }
            $this->writeMeta($targetDir, [
                'key' => $themeKey,
                'name' => trim((string)$meta['name']) ?: $themeKey,
                'version' => trim((string)$meta['version']) ?: '1.0.0',
                'author' => trim((string)$meta['author']),
                'description' => trim((string)$meta['description']),
                'screenshot' => trim((string)$meta['screenshot']),
            ]);

            $this->removeDirectory($tempDir);
            return $this->buildThemeInfo($targetDir, $themeKey);
        } catch (\Exception $e) {
            $zip->close();
            $this->removeDirectory($targetDir);
            $this->removeDirectory($tempDir);
            throw $e;
        }
    }

    private function getThemeBaseDir()
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public/views/index';
    }

    private function fallbackTheme()
    {
        return [
            'key' => $this->defaultTheme,
            'name' => '默认主题',
            'version' => '',
            'author' => '',
            'description' => '当前前台默认主题。',
            'path' => 'public/views/index/' . $this->defaultTheme,
            'preview_url' => '/?__preview_theme=' . $this->defaultTheme,
            'screenshot' => '',
            'settings' => $this->defaultSettings(),
            'screenshot_url' => '',
            'required_templates' => $this->requiredTemplates,
            'missing_templates' => [],
            'is_valid' => 1,
            'is_default' => 1,
            'can_edit' => 0,
            'can_delete' => 0,
            'health_status' => 'success',
            'health_items' => [
                ['label' => '模板', 'status' => 'success', 'message' => '完整'],
                ['label' => '主题信息', 'status' => 'warning', 'message' => '使用兜底信息'],
                ['label' => '静态目录', 'status' => 'warning', 'message' => '未检测'],
                ['label' => '预览图', 'status' => 'warning', 'message' => '未配置'],
            ],
            'sort' => 0,
        ];
    }

    private function buildThemeInfo($themeDir, $themeKey)
    {
        $meta = $this->readMeta($themeDir, $themeKey);
        $missingTemplates = $this->missingTemplates($themeDir);
        $isValid = empty($missingTemplates) ? 1 : 0;
        $healthItems = $this->healthItems($themeDir, $meta, $missingTemplates);
        $healthStatus = $this->healthStatus($healthItems);

        return [
            'key' => $themeKey,
            'name' => $meta['name'],
            'version' => $meta['version'],
            'author' => $meta['author'],
            'description' => $meta['description'],
            'path' => 'public/views/index/' . $themeKey,
            'preview_url' => '/?__preview_theme=' . rawurlencode($themeKey),
            'screenshot' => $meta['screenshot'],
            'settings' => $meta['settings'],
            'screenshot_url' => $this->screenshotUrl($themeDir, $themeKey, $meta['screenshot']),
            'required_templates' => $this->requiredTemplates,
            'missing_templates' => $missingTemplates,
            'is_valid' => $isValid,
            'is_default' => $themeKey === $this->defaultTheme ? 1 : 0,
            'can_edit' => $themeKey === $this->defaultTheme ? 0 : 1,
            'can_delete' => $themeKey === $this->defaultTheme ? 0 : 1,
            'health_status' => $healthStatus,
            'health_items' => $healthItems,
            'sort' => $themeKey === $this->defaultTheme ? 0 : ($isValid ? 10 : 90),
        ];
    }

    private function missingTemplates($themeDir)
    {
        $missing = [];
        foreach ($this->requiredTemplates as $template) {
            if (!is_file($themeDir . DIRECTORY_SEPARATOR . $template . '.html')) {
                $missing[] = $template . '.html';
            }
        }

        return $missing;
    }

    private function looksLikeTheme($themeDir)
    {
        if (is_file($themeDir . DIRECTORY_SEPARATOR . 'theme.json')) {
            return true;
        }
        foreach ($this->requiredTemplates as $template) {
            if (is_file($themeDir . DIRECTORY_SEPARATOR . $template . '.html')) {
                return true;
            }
        }

        return false;
    }

    private function readMeta($themeDir, $themeKey)
    {
        $fallback = [
            'name' => $themeKey === $this->defaultTheme ? '默认主题' : $themeKey,
            'version' => '',
            'author' => '',
            'description' => '',
            'screenshot' => '',
            'settings' => $this->defaultSettings(),
        ];
        $metaFile = $themeDir . DIRECTORY_SEPARATOR . 'theme.json';
        if (!is_file($metaFile)) {
            return $fallback;
        }

        $data = json_decode((string)file_get_contents($metaFile), true);
        if (!is_array($data)) {
            return $fallback;
        }

        foreach (['name', 'version', 'author', 'description', 'screenshot'] as $key) {
            $value = trim((string)($data[$key] ?? ''));
            if ($value !== '') {
                $fallback[$key] = $value;
            }
        }
        $fallback['settings'] = $this->normalizeSettings($data['settings'] ?? []);

        return $fallback;
    }

    private function defaultSettings()
    {
        return [
            'download_url' => '',
            'notice_text' => '点击继续观看 雨霖铃 - 第1集',
            'banners' => [
                [
                    'title' => '剑来',
                    'subtitle' => '少年仗剑，一路向前',
                    'keyword' => '剑来',
                    'image' => '/views/index/mofa/common/static/mac/vajra-bg.jpg',
                ],
            ],
            'cards' => [
                [
                    'key' => 'theme',
                    'title' => '白天模式',
                    'subtitle' => '点击切换主题',
                    'image' => '',
                    'action' => 'theme',
                    'url' => '',
                ],
                [
                    'key' => 'history',
                    'title' => '历史搜索',
                    'subtitle' => '查看搜索记录',
                    'image' => '/views/index/mofa/common/static/mac/historypt.svg',
                    'action' => 'history',
                    'url' => '',
                ],
                [
                    'key' => 'download',
                    'title' => '下载APP',
                    'subtitle' => '追剧不迷路',
                    'image' => '/views/index/mofa/common/static/mac/logo1.PNG',
                    'action' => 'url',
                    'url' => '',
                ],
            ],
            'bottom_tabs' => [
                [
                    'title' => '首页',
                    'type' => 'panel',
                    'target' => 'home',
                    'icon' => '/views/index/mofa/common/static/mac/home-1.svg',
                    'active_icon' => '/views/index/mofa/common/static/mac/home-2.svg',
                ],
                [
                    'title' => '发现',
                    'type' => 'panel',
                    'target' => 'discover',
                    'icon' => '/views/index/mofa/common/static/mac/fx-1.svg',
                    'active_icon' => '/views/index/mofa/common/static/mac/fx-2.svg',
                ],
                [
                    'title' => '我的',
                    'type' => 'panel',
                    'target' => 'mine',
                    'icon' => '/views/index/mofa/common/static/mac/user-1.svg',
                    'active_icon' => '/views/index/mofa/common/static/mac/user-2.svg',
                ],
            ],
            'friend_links' => [
                [
                    'title' => '悦心搜索',
                    'url' => 'https://pan.033030.xyz',
                    'icon' => '',
                    'description' => '发现和分享优质资源',
                ],
            ],
        ];
    }

    private function normalizeSettings($settings)
    {
        $defaults = $this->defaultSettings();
        if (!is_array($settings)) {
            return $defaults;
        }

        $result = $defaults;
        $result['download_url'] = trim((string)($settings['download_url'] ?? $defaults['download_url']));
        $result['notice_text'] = trim((string)($settings['notice_text'] ?? $defaults['notice_text']));

        $banners = $settings['banners'] ?? [];
        if (is_string($banners)) {
            $decoded = json_decode($banners, true);
            $banners = is_array($decoded) ? $decoded : [];
        }
        $result['banners'] = [];
        if (is_array($banners)) {
            foreach ($banners as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $title = trim((string)($item['title'] ?? ''));
                $image = trim((string)($item['image'] ?? ''));
                if ($title === '' && $image === '') {
                    continue;
                }
                $result['banners'][] = [
                    'title' => $title,
                    'subtitle' => trim((string)($item['subtitle'] ?? '')),
                    'keyword' => trim((string)($item['keyword'] ?? $title)),
                    'image' => $image,
                ];
            }
        }
        if (empty($result['banners'])) {
            $result['banners'] = $defaults['banners'];
        }

        $cards = $settings['cards'] ?? [];
        if (is_string($cards)) {
            $decoded = json_decode($cards, true);
            $cards = is_array($decoded) ? $decoded : [];
        }
        $result['cards'] = $defaults['cards'];
        if (is_array($cards)) {
            $normalizedCards = [];
            foreach ($cards as $index => $item) {
                if (!is_array($item)) {
                    continue;
                }
                $defaultCard = $defaults['cards'][$index] ?? [
                    'key' => 'card' . ($index + 1),
                    'title' => '',
                    'subtitle' => '',
                    'image' => '',
                    'action' => 'none',
                    'url' => '',
                ];
                $normalizedCards[] = [
                    'key' => trim((string)($item['key'] ?? $defaultCard['key'])),
                    'title' => trim((string)($item['title'] ?? $defaultCard['title'])),
                    'subtitle' => trim((string)($item['subtitle'] ?? $defaultCard['subtitle'])),
                    'image' => trim((string)($item['image'] ?? $defaultCard['image'])),
                    'action' => trim((string)($item['action'] ?? $defaultCard['action'])),
                    'url' => trim((string)($item['url'] ?? $defaultCard['url'])),
                ];
            }
            if (!empty($normalizedCards)) {
                $result['cards'] = [];
                for ($i = 0; $i < 3; $i++) {
                    $result['cards'][] = array_merge($defaults['cards'][$i] ?? [], $normalizedCards[$i] ?? []);
                }
            }
        }

        $tabs = $settings['bottom_tabs'] ?? [];
        if (is_string($tabs)) {
            $decoded = json_decode($tabs, true);
            $tabs = is_array($decoded) ? $decoded : [];
        }
        $result['bottom_tabs'] = $defaults['bottom_tabs'];
        if (is_array($tabs)) {
            $normalizedTabs = [];
            foreach ($tabs as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $title = trim((string)($item['title'] ?? ''));
                $type = trim((string)($item['type'] ?? 'panel'));
                $target = trim((string)($item['target'] ?? ''));
                if ($title === '' || $target === '') {
                    continue;
                }
                $normalizedTabs[] = [
                    'title' => $title,
                    'type' => $type,
                    'target' => $target,
                    'icon' => trim((string)($item['icon'] ?? '')),
                    'active_icon' => trim((string)($item['active_icon'] ?? '')),
                ];
            }
            if (!empty($normalizedTabs)) {
                $result['bottom_tabs'] = array_slice($normalizedTabs, 0, 5);
            }
        }

        $links = $settings['friend_links'] ?? [];
        if (is_string($links)) {
            $decoded = json_decode($links, true);
            $links = is_array($decoded) ? $decoded : [];
        }
        $result['friend_links'] = $defaults['friend_links'];
        if (is_array($links)) {
            $normalizedLinks = [];
            foreach ($links as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $title = trim((string)($item['title'] ?? ''));
                $url = trim((string)($item['url'] ?? ''));
                if ($title === '' || $url === '') {
                    continue;
                }
                $normalizedLinks[] = [
                    'title' => $title,
                    'url' => $url,
                    'icon' => trim((string)($item['icon'] ?? '')),
                    'description' => trim((string)($item['description'] ?? '')),
                ];
            }
            if (!empty($normalizedLinks)) {
                $result['friend_links'] = array_slice($normalizedLinks, 0, 30);
            }
        }

        return $result;
    }

    private function healthItems($themeDir, array $meta, array $missingTemplates)
    {
        $items = [];
        $items[] = [
            'label' => '模板',
            'status' => empty($missingTemplates) ? 'success' : 'error',
            'message' => empty($missingTemplates) ? '完整' : '缺少 ' . implode('、', $missingTemplates),
        ];

        $metaFile = $themeDir . DIRECTORY_SEPARATOR . 'theme.json';
        $metaStatus = 'success';
        $metaMessage = '正常';
        if (!is_file($metaFile)) {
            $metaStatus = 'warning';
            $metaMessage = '缺少 theme.json';
        } else {
            $data = json_decode((string)file_get_contents($metaFile), true);
            if (!is_array($data)) {
                $metaStatus = 'error';
                $metaMessage = 'JSON 格式错误';
            } elseif (trim((string)($data['name'] ?? '')) === '') {
                $metaStatus = 'warning';
                $metaMessage = '缺少名称';
            }
        }
        $items[] = ['label' => '主题信息', 'status' => $metaStatus, 'message' => $metaMessage];

        $staticDir = $themeDir . DIRECTORY_SEPARATOR . 'common' . DIRECTORY_SEPARATOR . 'static';
        $items[] = [
            'label' => '静态目录',
            'status' => is_dir($staticDir) ? 'success' : 'warning',
            'message' => is_dir($staticDir) ? '存在' : '未找到 common/static',
        ];

        $screenshot = trim((string)($meta['screenshot'] ?? ''));
        if ($screenshot === '') {
            $items[] = ['label' => '预览图', 'status' => 'warning', 'message' => '未配置'];
        } elseif (preg_match('/^https?:\/\//i', $screenshot)) {
            $items[] = ['label' => '预览图', 'status' => 'success', 'message' => '外链'];
        } elseif (strpos($screenshot, '..') !== false || strpos($screenshot, '\\') !== false) {
            $items[] = ['label' => '预览图', 'status' => 'error', 'message' => '路径不合法'];
        } elseif (is_file($themeDir . DIRECTORY_SEPARATOR . ltrim($screenshot, '/'))) {
            $items[] = ['label' => '预览图', 'status' => 'success', 'message' => '存在'];
        } else {
            $items[] = ['label' => '预览图', 'status' => 'warning', 'message' => '文件不存在'];
        }

        return $items;
    }

    private function healthStatus(array $items)
    {
        $status = 'success';
        foreach ($items as $item) {
            if (($item['status'] ?? '') === 'error') {
                return 'error';
            }
            if (($item['status'] ?? '') === 'warning') {
                $status = 'warning';
            }
        }

        return $status;
    }

    private function screenshotUrl($themeDir, $themeKey, $screenshot)
    {
        $screenshot = trim((string)$screenshot);
        if ($screenshot === '') {
            foreach (['screenshot.png', 'screenshot.jpg', 'screenshot.jpeg', 'screenshot.webp'] as $file) {
                if (is_file($themeDir . DIRECTORY_SEPARATOR . $file)) {
                    $screenshot = $file;
                    break;
                }
            }
        }
        if ($screenshot === '' || preg_match('/^https?:\/\//i', $screenshot)) {
            return $screenshot;
        }
        if (strpos($screenshot, '..') !== false || strpos($screenshot, '\\') !== false) {
            return '';
        }
        if (!is_file($themeDir . DIRECTORY_SEPARATOR . ltrim($screenshot, '/'))) {
            return '';
        }

        return '/views/index/' . rawurlencode($themeKey) . '/' . ltrim($screenshot, '/');
    }

    private function copyDirectory($sourceDir, $targetDir, $fromTheme, $toTheme)
    {
        $sourceReal = realpath($sourceDir);
        if ($sourceReal === false || !is_dir($sourceReal)) {
            throw new \Exception('源主题目录不存在');
        }
        if (!mkdir($targetDir, 0755, true)) {
            throw new \Exception('主题目录创建失败，请检查 public/views/index 权限');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceReal, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen($sourceReal) + 1);
            $targetPath = $targetDir . DIRECTORY_SEPARATOR . $relativePath;
            if ($item->isDir()) {
                if (!is_dir($targetPath) && !mkdir($targetPath, 0755, true)) {
                    throw new \Exception('主题子目录创建失败：' . $relativePath);
                }
                continue;
            }

            if (!$this->copyThemeFile($item->getPathname(), $targetPath, $fromTheme, $toTheme)) {
                throw new \Exception('主题文件复制失败：' . $relativePath);
            }
        }
    }

    private function copyThemeFile($sourcePath, $targetPath, $fromTheme, $toTheme)
    {
        $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        $textExt = ['html', 'css', 'js', 'json', 'txt', 'md'];
        if (!in_array($ext, $textExt, true)) {
            return copy($sourcePath, $targetPath);
        }

        $content = file_get_contents($sourcePath);
        if ($content === false) {
            return false;
        }
        if ($fromTheme !== '' && $toTheme !== '') {
            $content = str_replace('/views/index/' . $fromTheme . '/', '/views/index/' . $toTheme . '/', $content);
            $content = str_replace('index/' . $fromTheme . '/', 'index/' . $toTheme . '/', $content);
        }

        return file_put_contents($targetPath, $content) !== false;
    }

    private function validateZipEntries(\ZipArchive $zip)
    {
        $maxFiles = 500;
        $maxTotalSize = 50 * 1024 * 1024;
        $totalSize = 0;
        if ($zip->numFiles <= 0) {
            throw new \Exception('主题包为空');
        }
        if ($zip->numFiles > $maxFiles) {
            throw new \Exception('主题包文件数量过多');
        }

        $allowedExt = ['html', 'css', 'js', 'json', 'txt', 'md', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'eot', 'map'];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = str_replace('\\', '/', (string)($stat['name'] ?? ''));
            if ($name === '' || strpos($name, "\0") !== false || strpos($name, '../') !== false || strpos($name, '/..') !== false || preg_match('/^[a-zA-Z]:\//', $name) || strpos($name, '/') === 0) {
                throw new \Exception('主题包包含非法路径：' . $name);
            }
            $base = basename($name);
            if ($base !== '' && substr($base, 0, 1) === '.') {
                throw new \Exception('主题包包含隐藏文件：' . $name);
            }
            if (substr($name, -1) !== '/') {
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if ($ext === '' || !in_array($ext, $allowedExt, true)) {
                    throw new \Exception('主题包包含不允许的文件类型：' . $name);
                }
            }
            $totalSize += (int)($stat['size'] ?? 0);
            if ($totalSize > $maxTotalSize) {
                throw new \Exception('主题包解压后体积过大');
            }
        }
    }

    private function findImportedThemeRoot($tempDir)
    {
        if ($this->looksLikeTheme($tempDir)) {
            return $tempDir;
        }

        $entries = array_values(array_filter(scandir($tempDir), function ($entry) {
            return $entry !== '.' && $entry !== '..' && substr($entry, 0, 1) !== '.';
        }));
        if (count($entries) === 1) {
            $candidate = $tempDir . DIRECTORY_SEPARATOR . $entries[0];
            if (is_dir($candidate) && $this->looksLikeTheme($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function writeMeta($themeDir, array $meta)
    {
        $json = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false || file_put_contents($themeDir . DIRECTORY_SEPARATOR . 'theme.json', $json . PHP_EOL) === false) {
            throw new \Exception('主题信息写入失败');
        }
    }

    private function removeDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($dir);
    }
}
