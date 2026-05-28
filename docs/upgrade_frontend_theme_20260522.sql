-- 前端主题配置升级脚本（可重复执行）
-- 用于老站补充 WordPress-like 前端主题选择配置。
-- 主题选项会在后台读取配置时扫描 public/views/index/*/theme.json 动态生成；
-- conf_content 只保留默认兜底值，避免新增主题时必须改数据库。

INSERT INTO `qf_conf` (`conf_key`, `conf_value`, `conf_title`, `conf_desc`, `conf_status`, `conf_type`, `conf_spec`, `conf_content`, `conf_sort`, `conf_system`, `conf_createtime`, `conf_updatetime`)
SELECT 'frontend_theme', 'news', '前端主题', '选择前台首页、搜索页、详情页使用的主题；主题缺失时自动回退默认主题', 1, 3, 2, '默认主题=>news\n简洁主题=>simple', 100, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `qf_conf` WHERE `conf_key` = 'frontend_theme');

UPDATE `qf_conf`
SET `conf_type` = 3,
    `conf_spec` = 2,
    `conf_content` = '默认主题=>news\n简洁主题=>simple',
    `conf_updatetime` = UNIX_TIMESTAMP()
WHERE `conf_key` = 'frontend_theme';

INSERT INTO `qf_node` (`node_id`, `node_title`, `node_desc`, `node_module`, `node_controller`, `node_action`, `node_pid`, `node_order`, `node_show`, `node_icon`, `node_extend`, `node_status`, `node_createtime`, `node_updatetime`)
SELECT 121, '主题管理', '', 'qfadmin', 'system', 'theme', 3, 4, 1, 'el-icon-brush', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM `qf_node`
    WHERE `node_module` = 'qfadmin' AND `node_controller` = 'system' AND `node_action` = 'theme'
);

UPDATE `qf_node`
SET `node_title` = '主题管理',
    `node_pid` = 3,
    `node_order` = 4,
    `node_show` = 1,
    `node_icon` = 'el-icon-brush',
    `node_status` = 0,
    `node_updatetime` = UNIX_TIMESTAMP()
WHERE `node_module` = 'qfadmin' AND `node_controller` = 'system' AND `node_action` = 'theme';
