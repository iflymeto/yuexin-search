<?php

namespace app\admin\controller;

use think\App;
use think\facade\Db;
use think\facade\Cache;
use app\admin\QfShop;
use app\model\Validate as ValidateModel;
use app\service\FrontendThemeService;

class System extends QfShop
{
    public function __construct(App $app)
    {
        parent::__construct($app);
    }
    /**
     * 获取图形验证码
     *
     * @return void
     */
    public function getCaptcha()
    {
        // $error = $this->access();
        // if ($error) {
        //     return $error;
        // }
        $validateModel = new ValidateModel();
        $imgData = $validateModel->getImg();
        $code = strtoupper($validateModel->getCode());
        $token = sha1($code .  time()) . rand(100000, 999999);
        cache($token, $code, 60);
        return jok('验证码生成成功', [
            'img' => $imgData,
            'token' => $token
        ]);
    }
    /**
     * 清除缓存
     *
     * @return void
     */
    public function clean()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }

        $types = input('types/a', []);
        if (empty($types)) {
            $types = ['config', 'global', 'runtime'];
        }
        $types = array_values(array_unique(array_filter($types)));

        $result = [];

        if (in_array('config', $types, true)) {
            $this->clearConfigCache();
            $result[] = '系统配置缓存';
        }

        if (in_array('global', $types, true)) {
            Cache::clear();
            $this->clearConfigCache();
            $result[] = 'ThinkPHP全局缓存';
        }

        if (in_array('runtime', $types, true)) {
            $runtimePath = app()->getRuntimePath();
            if (!$this->clearDirectoryContents($runtimePath)) {
                return jerr('运行缓存清理失败，请检查 runtime 目录权限', 500);
            }
            $result[] = '运行缓存';
        }

        if (in_array('search', $types, true)) {
            try {
                Db::name('search_cache')->where('id', '>', 0)->delete();
            } catch (\Exception $e) {
                return jerr('搜索结果缓存清理失败：' . $e->getMessage(), 500);
            }
            $result[] = '搜索结果缓存';
        }

        if (empty($result)) {
            return jerr('请选择要清理的缓存类型', 400);
        }

        return jok('已清理：' . implode('、', $result), [
            'cleaned' => $result,
            'pan_tree_cache_protected' => true,
        ]);
    }

    /**
     * 搜索结果缓存统计
     *
     * @return void
     */
    public function searchCacheStats()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }

        try {
            $now = time();
            $total = (int)Db::name('search_cache')->count();
            $valid = (int)Db::name('search_cache')->where('expire_time', '>', $now)->count();
            $expired = (int)Db::name('search_cache')
                ->where('expire_time', '>', 0)
                ->where('expire_time', '<=', $now)
                ->count();
            $resultCount = (int)Db::name('search_cache')->sum('result_count');
            $typeRows = Db::name('search_cache')
                ->field('is_type, COUNT(*) as count, IFNULL(SUM(result_count), 0) as total_results')
                ->group('is_type')
                ->select()
                ->toArray();
            $latest = Db::name('search_cache')
                ->where('last_search_time', '>', 0)
                ->max('last_search_time');

            return jok('搜索缓存统计获取成功', [
                'total' => $total,
                'valid' => $valid,
                'expired' => $expired,
                'result_count' => $resultCount,
                'latest_search_time' => $latest ? (int)$latest : 0,
                'latest_search_text' => $latest ? date('Y-m-d H:i:s', (int)$latest) : '-',
                'type_stats' => $typeRows,
            ]);
        } catch (\Exception $e) {
            return jerr('搜索缓存统计获取失败：' . $e->getMessage(), 500);
        }
    }

    public function themeList()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }

        $current = (string)Db::name('conf')->where('conf_key', 'frontend_theme')->value('conf_value');
        if ($current === '') {
            $current = 'news';
        }

        $service = new FrontendThemeService();
        $themes = $service->all();
        $previewUrls = $this->themePreviewUrls();
        foreach ($themes as &$theme) {
            $theme['active'] = $theme['key'] === $current ? 1 : 0;
            $theme['preview_urls'] = $previewUrls;
        }

        return jok('主题列表获取成功', [
            'current' => $current,
            'themes' => $themes,
        ]);
    }

    public function enableTheme()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }

        $theme = trim((string)input('theme', ''));
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $theme)) {
            return jerr('主题标识不合法', 400);
        }

        $service = new FrontendThemeService();
        if (!$service->exists($theme)) {
            return jerr('主题不存在或缺少必要模板', 404);
        }

        $now = time();
        $exists = Db::name('conf')->where('conf_key', 'frontend_theme')->find();
        if ($exists) {
            Db::name('conf')->where('conf_key', 'frontend_theme')->update([
                'conf_value' => $theme,
                'conf_type' => 3,
                'conf_spec' => 2,
                'conf_content' => $service->optionsText(),
                'conf_updatetime' => $now,
            ]);
        } else {
            Db::name('conf')->insert([
                'conf_key' => 'frontend_theme',
                'conf_value' => $theme,
                'conf_title' => '前端主题',
                'conf_desc' => '选择前台首页、搜索页、详情页使用的主题；主题缺失时自动回退默认主题',
                'conf_status' => 1,
                'conf_type' => 3,
                'conf_spec' => 2,
                'conf_content' => $service->optionsText(),
                'conf_sort' => 100,
                'conf_system' => 1,
                'conf_createtime' => $now,
                'conf_updatetime' => $now,
            ]);
        }

        $this->clearConfigCache();

        return jok('主题已启用', ['current' => $theme]);
    }

    public function createTheme()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }

        $theme = trim((string)input('theme', ''));
        $name = trim((string)input('name', ''));
        $author = trim((string)input('author', ''));
        $description = trim((string)input('description', ''));

        try {
            $service = new FrontendThemeService();
            $themeInfo = $service->createFromDefault($theme, $name, $author, $description);
            Db::name('conf')->where('conf_key', 'frontend_theme')->update([
                'conf_content' => $service->optionsText(),
                'conf_updatetime' => time(),
            ]);
            $this->clearConfigCache();

            return jok('主题创建成功', ['theme' => $themeInfo]);
        } catch (\Exception $e) {
            return jerr($e->getMessage(), 400);
        }
    }

    public function deleteTheme()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }

        $theme = trim((string)input('theme', ''));
        $service = new FrontendThemeService();
        if ($theme === $service->defaultTheme()) {
            return jerr('默认主题受系统保护，不能删除', 400);
        }

        $current = (string)Db::name('conf')->where('conf_key', 'frontend_theme')->value('conf_value');
        if ($theme !== '' && $theme === $current) {
            return jerr('当前启用主题不能删除，请先切换到其他主题', 400);
        }

        try {
            $service->deleteTheme($theme);
            Db::name('conf')->where('conf_key', 'frontend_theme')->update([
                'conf_content' => $service->optionsText(),
                'conf_updatetime' => time(),
            ]);
            $this->clearConfigCache();

            return jok('主题已删除');
        } catch (\Exception $e) {
            return jerr($e->getMessage(), 400);
        }
    }

    public function updateThemeMeta()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }

        $theme = trim((string)input('theme', ''));
        $service = new FrontendThemeService();
        if ($theme === $service->defaultTheme()) {
            return jerr('默认主题受系统保护，不能编辑', 400);
        }

        try {
            $themeInfo = $service->updateMeta($theme, [
                'name' => input('name', ''),
                'version' => input('version', ''),
                'author' => input('author', ''),
                'description' => input('description', ''),
                'screenshot' => input('screenshot', ''),
                'settings' => input('settings/a', []),
            ]);
            Db::name('conf')->where('conf_key', 'frontend_theme')->update([
                'conf_content' => $service->optionsText(),
                'conf_updatetime' => time(),
            ]);
            $this->clearConfigCache();

            return jok('主题信息已保存', ['theme' => $themeInfo]);
        } catch (\Exception $e) {
            return jerr($e->getMessage(), 400);
        }
    }

    public function exportTheme()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }

        $theme = trim((string)input('theme', ''));
        try {
            $service = new FrontendThemeService();
            $export = $service->exportTheme($theme);
            $file = $export['path'];
            $filename = $export['filename'];
            if (!is_file($file)) {
                return jerr('导出文件不存在', 404);
            }

            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($file));
            header('Cache-Control: no-store, no-cache, must-revalidate');
            readfile($file);
            @unlink($file);
            exit;
        } catch (\Exception $e) {
            return jerr($e->getMessage(), 400);
        }
    }

    public function importTheme()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }

        $theme = trim((string)input('theme', ''));
        $name = trim((string)input('name', ''));
        $file = request()->file('file');
        if (!$file) {
            return jerr('请选择主题 zip 文件', 400);
        }
        if (strtolower($file->extension()) !== 'zip') {
            return jerr('只支持 zip 主题包', 400);
        }
        if ($file->getSize() > 20 * 1024 * 1024) {
            return jerr('主题包不能超过 20MB', 400);
        }

        $tempDir = app()->getRuntimePath() . 'theme_upload';
        if (!is_dir($tempDir) && !mkdir($tempDir, 0755, true)) {
            return jerr('主题上传缓存目录创建失败', 500);
        }
        $tempFile = $tempDir . DIRECTORY_SEPARATOR . date('YmdHis') . '-' . mt_rand(1000, 9999) . '.zip';

        try {
            $file->move($tempDir, basename($tempFile));
            if (!is_file($tempFile)) {
                return jerr('主题包上传失败', 500);
            }

            $service = new FrontendThemeService();
            $themeInfo = $service->importTheme($tempFile, $theme, $name);
            Db::name('conf')->where('conf_key', 'frontend_theme')->update([
                'conf_content' => $service->optionsText(),
                'conf_updatetime' => time(),
            ]);
            $this->clearConfigCache();

            return jok('主题导入成功', ['theme' => $themeInfo]);
        } catch (\Exception $e) {
            return jerr($e->getMessage(), 400);
        } finally {
            @unlink($tempFile);
        }
    }

    private function themePreviewUrls()
    {
        $detailId = 0;
        try {
            $detailId = (int)Db::name('source')
                ->where('status', 1)
                ->where('is_delete', 0)
                ->order('source_id desc')
                ->value('source_id');
        } catch (\Exception $e) {
            $detailId = 0;
        }

        return [
            'home' => [
                'label' => '首页',
                'path' => '/',
                'enabled' => 1,
            ],
            'search' => [
                'label' => '搜索页',
                'path' => '/s/凡人.html',
                'enabled' => 1,
            ],
            'detail' => [
                'label' => '详情页',
                'path' => $detailId > 0 ? '/d/' . $detailId . '.html' : '',
                'enabled' => $detailId > 0 ? 1 : 0,
            ],
        ];
    }

    /**
     * 临时转存资源统计
     *
     * @return void
     */
    public function temporaryResourceStats()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }

        $now = time();
        $total = (int)Db::name('source')->where('is_time', 1)->count();
        $expired = (int)Db::name('source')
            ->where('is_time', 1)
            ->where('update_time', '<=', $now - 1800)
            ->count();
        $oldest = Db::name('source')
            ->where('is_time', 1)
            ->where('update_time', '>', 0)
            ->min('update_time');
        $typeRows = Db::name('source')
            ->field('is_type, COUNT(*) as count')
            ->where('is_time', 1)
            ->group('is_type')
            ->select()
            ->toArray();

        return jok('临时资源统计获取成功', [
            'total' => $total,
            'expired_30_count' => $expired,
            'oldest_update_time' => $oldest ? (int)$oldest : 0,
            'oldest_age_minutes' => $oldest ? (int)floor(($now - (int)$oldest) / 60) : 0,
            'type_stats' => $typeRows,
        ]);
    }

    /**
     * 预览可清理的临时转存资源
     *
     * @return void
     */
    public function previewTemporaryResources()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }

        list($query, $limit) = $this->buildTemporaryResourceQuery();
        $total = (int)(clone $query)->count();
        $list = $query
            ->field('source_id,title,url,is_type,fid,update_time,create_time')
            ->order('update_time', 'asc')
            ->limit($limit)
            ->select()
            ->toArray();

        $now = time();
        foreach ($list as &$item) {
            $item['age_minutes'] = !empty($item['update_time']) ? (int)floor(($now - (int)$item['update_time']) / 60) : 0;
            $item['type_name'] = $this->getPanTypeName($item['is_type']);
            $item['short_url'] = $this->getShortUrl($item['url']);
            $item['has_fid'] = !empty($item['fid']);
            unset($item['fid']);
        }

        return jok('临时资源预览获取成功', [
            'total' => $total,
            'limit' => $limit,
            'list' => $list,
        ]);
    }

    /**
     * 按条件清理临时转存资源
     *
     * @return void
     */
    public function cleanTemporaryResources()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }

        list($query, $limit) = $this->buildTemporaryResourceQuery();
        $list = $query
            ->field('source_id,title,is_type,fid')
            ->order('update_time', 'asc')
            ->limit($limit)
            ->select()
            ->toArray();

        if (empty($list)) {
            return jok('没有符合条件的临时资源', [
                'deleted_count' => 0,
                'failed_count' => 0,
                'deleted_resources' => [],
                'failed_resources' => [],
            ]);
        }

        $deletedResources = [];
        $failedResources = [];

        foreach ($list as $item) {
            try {
                $fileList = $this->normalizeFidList($item['fid']);
                $resultCheck = [
                    'success' => true,
                    'message' => '无网盘文件ID，直接删除数据库记录',
                    'summary' => ['status' => 'no_filelist'],
                ];
                if (!empty($fileList)) {
                    $transfer = new \netdisk\Transfer();
                    $deleteResult = $transfer->deletepdirFid((int)$item['is_type'], $fileList);
                    $resultCheck = $this->normalizePanDeleteResult($deleteResult, (int)$item['is_type']);
                    if (!$resultCheck['success']) {
                        $failedResources[] = [
                            'source_id' => $item['source_id'],
                            'title' => $item['title'],
                            'is_type' => (int)$item['is_type'],
                            'type_name' => $this->getPanTypeName($item['is_type']),
                            'message' => $resultCheck['message'],
                            'result' => $deleteResult,
                            'filelist' => $fileList,
                        ];
                        \think\facade\Log::warning('后台清理临时资源网盘文件失败: source_id=' . $item['source_id'] . ' message=' . $resultCheck['message']);
                        continue;
                    }
                }

                Db::name('source')->where('source_id', $item['source_id'])->delete();

                $deletedResources[] = [
                    'source_id' => $item['source_id'],
                    'title' => $item['title'],
                    'is_type' => (int)$item['is_type'],
                    'type_name' => $this->getPanTypeName($item['is_type']),
                    'delete_result' => $resultCheck['summary'],
                ];
            } catch (\Exception $e) {
                $failedResources[] = [
                    'source_id' => $item['source_id'],
                    'title' => $item['title'],
                    'message' => $e->getMessage(),
                ];
                \think\facade\Log::error('后台清理临时资源失败: ' . $e->getMessage());
            }
        }

        return jok('临时资源清理完成', [
            'deleted_count' => count($deletedResources),
            'failed_count' => count($failedResources),
            'deleted_resources' => $deletedResources,
            'failed_resources' => $failedResources,
        ]);
    }

    private function buildTemporaryResourceQuery()
    {
        $expireMinutes = (int)input('expire_minutes', 30);
        if ($expireMinutes < 1) {
            $expireMinutes = 30;
        }
        if ($expireMinutes > 10080) {
            $expireMinutes = 10080;
        }

        $limit = (int)input('limit', 100);
        if ($limit < 1) {
            $limit = 100;
        }
        if ($limit > 1000) {
            $limit = 1000;
        }

        $isType = input('is_type', '');
        $keyword = trim((string)input('keyword', ''));

        $query = Db::name('source')
            ->where('is_time', 1)
            ->where('update_time', '<=', time() - ($expireMinutes * 60));

        if ($isType !== '' && $isType !== null && (int)$isType >= 0) {
            $query->where('is_type', (int)$isType);
        }

        if ($keyword !== '') {
            $query->where('title', 'like', '%' . $keyword . '%');
        }

        return [$query, $limit];
    }

    private function normalizeFidList($fid)
    {
        if (is_string($fid)) {
            $decoded = json_decode($fid, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_values(array_filter($decoded));
            }
        }

        if (empty($fid)) {
            return [];
        }

        return [(string)$fid];
    }

    private function normalizePanDeleteResult($result, $isType)
    {
        if ($result === null) {
            return [
                'success' => false,
                'message' => '网盘删除无返回，未确认删除完成',
                'summary' => ['status' => 'unknown']
            ];
        }

        if (!is_array($result)) {
            return [
                'success' => false,
                'message' => '网盘删除返回异常',
                'summary' => ['raw' => $result]
            ];
        }

        if (in_array((int)$isType, [0, 3], true) && isset($result['code']) && (int)$result['code'] === 23004) {
            return [
                'success' => true,
                'message' => '文件已不存在，视为删除完成',
                'summary' => $result
            ];
        }

        if (in_array((int)$isType, [0, 3], true) && isset($result['data']['task_id']) && isset($result['status']) && (int)$result['status'] === 200) {
            $finish = isset($result['data']['finish']) ? (bool)$result['data']['finish'] : null;
            $taskStatus = isset($result['data']['status']) ? (int)$result['data']['status'] : null;
            $isFinished = $finish === true || $taskStatus === 2;
            return [
                'success' => $isFinished,
                'message' => $isFinished ? '删除任务已完成' : '删除任务已提交但未完成，保留数据库记录',
                'summary' => $result
            ];
        }

        if (isset($result['data']) && is_array($result['data'])) {
            $data = $result['data'];
            $errorCode = $data['error_code'] ?? $data['errno'] ?? null;
            if ($errorCode !== null && (int)$errorCode !== 0) {
                return [
                    'success' => false,
                    'message' => (string)($data['error_description'] ?? $data['message'] ?? ('删除失败 error_code=' . $errorCode)),
                    'summary' => $result
                ];
            }
        }

        if (isset($result['code']) && (int)$result['code'] !== 200 && isset($result['message'])) {
            return [
                'success' => false,
                'message' => (string)$result['message'],
                'summary' => $result
            ];
        }

        if (isset($result['errno'])) {
            $errno = (int)$result['errno'];
            if ((int)$isType === 2 && $errno === 132) {
                return [
                    'success' => true,
                    'message' => '百度要求安全验证，已跳过网盘确认并清理数据库记录',
                    'summary' => $result
                ];
            }
            return [
                'success' => $errno === 0,
                'message' => $errno === 0 ? '删除成功' : ($result['message'] ?? ('删除失败 errno=' . $errno)),
                'summary' => $result
            ];
        }

        if (isset($result['status'])) {
            $status = (int)$result['status'];
            return [
                'success' => $status === 200,
                'message' => $status === 200 ? '删除成功' : ($result['message'] ?? ('删除失败 status=' . $status)),
                'summary' => $result
            ];
        }

        if (isset($result['code'])) {
            $code = (int)$result['code'];
            if ($isType == 4 && $code === 0) {
                return [
                    'success' => true,
                    'message' => '删除成功',
                    'summary' => $result
                ];
            }
            return [
                'success' => in_array($code, [0, 200], true),
                'message' => in_array($code, [0, 200], true) ? '删除成功' : ($result['message'] ?? $result['msg'] ?? ('删除失败 code=' . $code)),
                'summary' => $result
            ];
        }

        return [
            'success' => false,
            'message' => '网盘删除返回格式未知，未确认删除完成',
            'summary' => $result
        ];
    }

    private function getPanTypeName($type)
    {
        $names = [
            0 => '夸克',
            1 => '阿里',
            2 => '百度',
            3 => 'UC',
            4 => '迅雷',
        ];
        $type = (int)$type;
        return isset($names[$type]) ? $names[$type] : '未知';
    }

    private function getShortUrl($url)
    {
        $url = trim((string)$url);
        if ($url === '') {
            return '-';
        }

        $host = parse_url($url, PHP_URL_HOST);
        return $host ? $host : $url;
    }

    private function clearConfigCache()
    {
        Cache::delete('system_config_all');
        Cache::set('system_config_version', time(), 300);
        Db::name('conf')
            ->whereIn('conf_key', [
                'announce_status',
                'announce_title',
                'announce_content',
                'announce_type',
                'announce_interval_days'
            ])
            ->update(['conf_updatetime' => time()]);
    }

    private function clearDirectoryContents($dir)
    {
        $dir = rtrim((string)$dir, "\\/");
        if ($dir === '' || !is_dir($dir)) {
            return true;
        }

        $root = realpath($dir);
        $runtimeRoot = realpath(app()->getRuntimePath());
        if ($root === false || $runtimeRoot === false || strpos($root, $runtimeRoot) !== 0) {
            return false;
        }

        $items = scandir($root);
        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $root . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        return true;
    }

    private function deleteDirectory($dir)
    {
        $dir = rtrim((string)$dir, "\\/");
        if ($dir === '' || !is_dir($dir)) {
            return true;
        }

        $items = scandir($dir);
        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        return @rmdir($dir);
    }
}
