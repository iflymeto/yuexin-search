# 前端主题开发规范

前台主题目录位于 `public/views/index`。每个主题使用一个独立目录，例如：

```text
public/views/index/news
public/views/index/simple
```

## 必需文件

主题必须包含以下 3 个模板，否则后台主题管理会标记为“不可用”，不能预览或启用。

```text
index.html   首页
list.html    搜索结果页
detail.html  资源详情页
```

## 推荐结构

```text
public/views/index/theme_key
  index.html
  list.html
  detail.html
  theme.json
  screenshot.png
  common/
    head.html
    header.html
    footer.html
    foot.html
    static/
      css/
      js/
      images/
```

`theme_key` 只能使用字母、数字、下划线、短横线，例如 `news`、`simple`、`mobile-clean`。

## theme.json

`theme.json` 用于后台主题管理展示主题信息。

```json
{
  "key": "simple",
  "name": "简洁主题",
  "version": "1.0.0",
  "author": "xinyue",
  "description": "用于验证主题切换和后续视觉迭代。",
  "screenshot": "screenshot.png"
}
```

字段说明：

- `key`：主题目录名，仅用于人工识别，系统以目录名为准。
- `name`：后台显示名称。
- `version`：主题版本。
- `author`：作者。
- `description`：主题说明。
- `screenshot`：后台主题卡片预览图，可省略。省略时会自动尝试读取 `screenshot.png`、`screenshot.jpg`、`screenshot.jpeg`、`screenshot.webp`。

## 静态资源路径

主题内模板应引用自己的静态资源路径，例如：

```html
<link rel="stylesheet" href="/views/index/simple/common/static/css/home.css">
<script src="/views/index/simple/common/static/js/home.js"></script>
```

复制默认主题时，需要把模板中 `/views/index/news/` 替换为新主题目录，例如 `/views/index/simple/`。

## 预览与启用

- 后台入口：`系统 / 主题管理`
- 后台创建：点击“创建主题”，填写主题标识和名称，系统会基于默认主题复制一个独立主题目录。
- 后台编辑：自定义主题可在卡片“更多 / 编辑信息”中编辑名称、版本、作者、说明和预览图字段；默认主题 `news` 只读。
- 预览主题：后台主题卡片可分别预览首页、搜索页和详情页；详情页需要站内存在可用资源，否则会置灰。
- 正式启用：后台点击“启用”，会写入 `qf_conf.frontend_theme`
- 导出主题：后台在卡片“更多 / 导出主题”中导出 zip，zip 内保留主题目录作为根目录。
- 导入主题：后台点击“导入主题”，填写新的主题标识并上传 zip；导入不会覆盖已有主题，也不能覆盖默认主题。
- 删除主题：后台在卡片“更多 / 删除主题”中删除；只允许删除未启用的自定义主题，默认主题 `news` 受系统保护，不能删除。

预览不会修改正式配置。正式主题缺失模板时，前台会回退到默认 `news`。

后台创建主题时会自动替换模板中的静态资源路径，例如将 `/views/index/news/` 替换为 `/views/index/theme_key/`。

## 健康检查

后台主题管理会对每个主题显示健康检查状态：

- `模板`：检查 `index.html`、`list.html`、`detail.html` 是否完整。
- `主题信息`：检查 `theme.json` 是否存在、JSON 是否有效、名称是否填写。
- `静态目录`：检查 `common/static` 是否存在。
- `预览图`：检查 `screenshot` 字段是否配置；本地图片会检查文件是否存在，外链图片会标记为外链。

黄色状态一般不影响启用，例如未配置预览图；红色状态表示主题不可用或需要修复。

## 导入安全规则

主题 zip 导入采用保守策略：

- 只允许导入为新主题，已有目录不会被覆盖。
- 主题标识只能包含字母、数字、下划线、短横线。
- zip 最大 20MB，解压后最大 50MB，最多 500 个文件。
- 会拒绝 `../`、绝对路径、Windows 盘符路径、隐藏文件和不在白名单内的文件类型。
- 导入会先解压到 `runtime/theme_import` 临时目录，检查通过后再移动到正式主题目录。
- 导入失败会清理临时目录和半成品主题目录。

## 安装与升级

新安装脚本已包含：

- `frontend_theme` 默认配置
- `qfadmin/system/theme` 后台菜单节点

老站升级执行：

```sql
docs/upgrade_frontend_theme_20260522.sql
```
