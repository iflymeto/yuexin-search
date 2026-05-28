-- 2026-05-15 安装 SQL 对齐升级脚本
-- 用途：让旧站补齐当前 public/install/data.sql 已包含的缓存/诊断/配置默认结构。
-- 注意：如果数据库表前缀不是 qf_，请先替换本文件中的 qf_ 前缀。
-- 建议先执行 docs/upgrade_api_list_pan_types.sql，再执行本脚本。

SET NAMES utf8mb4;

-- ----------------------------
-- qf_search_cache：搜索结果缓存，只存关键词搜索结果，不存网盘临时目录
-- ----------------------------
CREATE TABLE IF NOT EXISTS `qf_search_cache` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `keyword` varchar(255) NOT NULL DEFAULT '' COMMENT '搜索关键词',
  `keyword_hash` char(32) NOT NULL DEFAULT '' COMMENT '关键词MD5哈希值（用于快速查询）',
  `is_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '网盘类型：0=夸克 1=阿里云盘 2=百度网盘 3=UC网盘 4=迅雷',
  `cache_data` longtext COMMENT 'JSON格式的搜索结果数组',
  `result_count` int(11) NOT NULL DEFAULT '0' COMMENT '结果数量',
  `last_search_time` int(11) NOT NULL DEFAULT '0' COMMENT '最后搜索时间（时间戳）',
  `expire_time` int(11) NOT NULL DEFAULT '0' COMMENT '缓存过期时间（时间戳）',
  `create_time` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int(11) NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_keyword_type` (`keyword_hash`, `is_type`) COMMENT '联合唯一索引：防止重复记录',
  KEY `idx_expire` (`expire_time`) COMMENT '过期时间索引（用于清理）'
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='搜索结果缓存表（支持智能增量更新）';

-- ----------------------------
-- qf_invalid_resource：失效资源缓存
-- ----------------------------
CREATE TABLE IF NOT EXISTS `qf_invalid_resource` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '资源URL（完整链接）',
  `url_hash` char(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT 'URL的MD5哈希值（用于快速查询和去重）',
  `is_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '网盘类型：0=夸克 1=阿里云盘 2=百度网盘 3=UC网盘 4=迅雷',
  `fail_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '' COMMENT '失效原因（可选，便于统计分析）',
  `check_count` int(11) NOT NULL DEFAULT 1 COMMENT '检测次数（记录该URL被检测失败的累计次数）',
  `last_check_time` int(11) NOT NULL DEFAULT 0 COMMENT '最后检测时间（时间戳）',
  `expire_time` int(11) NOT NULL DEFAULT 0 COMMENT '记录过期时间（时间戳，过期后可重新检测）',
  `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间（首次检测失败时间）',
  `update_time` int(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `idx_url_hash` (`url_hash`) USING BTREE,
  KEY `idx_is_type` (`is_type`) USING BTREE,
  KEY `idx_expire_time` (`expire_time`) USING BTREE,
  KEY `idx_create_time` (`create_time`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='无效资源缓存表（仅记录检测失败的资源）' ROW_FORMAT=Dynamic;

-- ----------------------------
-- qf_diagnostic_log：搜索/转存/目录树诊断日志
-- ----------------------------
CREATE TABLE IF NOT EXISTS `qf_diagnostic_log` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `module` varchar(30) NOT NULL DEFAULT '' COMMENT '模块 search/transfer/pan_tree',
  `action` varchar(60) NOT NULL DEFAULT '' COMMENT '动作',
  `status` varchar(20) NOT NULL DEFAULT '' COMMENT '状态 info/success/warn/error',
  `message` varchar(500) NOT NULL DEFAULT '' COMMENT '摘要',
  `keyword` varchar(255) NOT NULL DEFAULT '' COMMENT '关键词',
  `is_type` tinyint(4) NOT NULL DEFAULT -1 COMMENT '网盘类型',
  `line_name` varchar(120) NOT NULL DEFAULT '' COMMENT '搜索线路',
  `url_hash` char(32) NOT NULL DEFAULT '' COMMENT '原链接哈希',
  `url_preview` varchar(500) NOT NULL DEFAULT '' COMMENT '脱敏链接预览',
  `duration_ms` int(11) NOT NULL DEFAULT 0 COMMENT '耗时毫秒',
  `context` text COMMENT '上下文JSON',
  `create_time` int(11) NOT NULL DEFAULT 0,
  `update_time` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_module_time` (`module`, `create_time`),
  KEY `idx_status_time` (`status`, `create_time`),
  KEY `idx_url_hash` (`url_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='搜索转存目录树诊断日志' ROW_FORMAT=Dynamic;

-- ----------------------------
-- qf_api_list：多线路接口配置表。已存在旧表时，字段升级请执行 upgrade_api_list_pan_types.sql。
-- ----------------------------
CREATE TABLE IF NOT EXISTS `qf_api_list` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `name` varchar(100) NOT NULL COMMENT '线路名称',
  `type` varchar(20) NOT NULL DEFAULT 'api' COMMENT '接口类型：api（接口）、pansou（PanSou）、html（网页）、tg（TG频道）',
  `pantype` tinyint(1) NOT NULL DEFAULT '0' COMMENT '网盘类型：0=夸克 2=百度 3=UC 4=迅雷',
  `url` varchar(255) DEFAULT NULL COMMENT '请求地址或入口URL',
  `method` varchar(10) DEFAULT 'GET' COMMENT '请求方式：GET/POST，仅用于api/html类型',
  `fixed_params` text COMMENT '固定请求参数（JSON格式；PanSou可保存channels/plugins/cloud_types）',
  `headers` text COMMENT '请求头信息（JSON格式；PanSou认证可保存Authorization Bearer令牌）',
  `field_map` text COMMENT '返回字段映射（JSON格式）',
  `count` int(11) DEFAULT '0' COMMENT '最多取多少个资源',
  `html_item` varchar(255) DEFAULT NULL,
  `html_title` varchar(255) DEFAULT NULL,
  `html_url` varchar(255) DEFAULT NULL,
  `html_type` tinyint(4) DEFAULT '0',
  `html_url2` varchar(255) DEFAULT NULL,
  `weight` int(11) DEFAULT '0' COMMENT '权重，数值越大优先级越高',
  `status` tinyint(1) DEFAULT '1' COMMENT '是否启用：1启用，0禁用',
  `pan_types` varchar(50) NOT NULL DEFAULT '' COMMENT 'TG/PanSou支持的网盘类型，多个用英文逗号分隔：0=夸克 2=百度 3=UC 4=迅雷',
  `tg_channels` text COMMENT 'TG群组资源池JSON，包含群组ID和启用状态',
  `tg_scan_limit` int(11) NOT NULL DEFAULT '5' COMMENT 'TG资源池每次搜索最多扫描的群组数',
  `create_time` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
  `update_time` int(11) NOT NULL DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COMMENT='多线路接口配置表';

-- ----------------------------
-- 默认配置项：只补不存在的 conf_key
-- ----------------------------
INSERT INTO `qf_conf` (`conf_key`, `conf_value`, `conf_title`, `conf_desc`, `conf_status`, `conf_type`, `conf_spec`, `conf_content`, `conf_sort`, `conf_system`, `conf_createtime`, `conf_updatetime`)
SELECT 'cache_driver', 'file', '缓存驱动', '选择系统使用的缓存方式：file=文件缓存（无需服务）；redis=Redis缓存（需安装Redis）；memcached=Memcached缓存（需安装Memcached）', 1, 4, 2, '文件缓存=>file\nRedis缓存=>redis\nMemcached缓存=>memcached', 1, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `qf_conf` WHERE `conf_key` = 'cache_driver');

INSERT INTO `qf_conf` (`conf_key`, `conf_value`, `conf_title`, `conf_desc`, `conf_status`, `conf_type`, `conf_spec`, `conf_content`, `conf_sort`, `conf_system`, `conf_createtime`, `conf_updatetime`)
SELECT 'redis_host', '127.0.0.1', 'Redis服务器地址', 'Redis服务器IP地址，默认本机：127.0.0.1', 1, 4, 0, NULL, 2, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `qf_conf` WHERE `conf_key` = 'redis_host');

INSERT INTO `qf_conf` (`conf_key`, `conf_value`, `conf_title`, `conf_desc`, `conf_status`, `conf_type`, `conf_spec`, `conf_content`, `conf_sort`, `conf_system`, `conf_createtime`, `conf_updatetime`)
SELECT 'redis_port', '6379', 'Redis端口', 'Redis服务器端口，默认：6379', 1, 4, 0, NULL, 3, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `qf_conf` WHERE `conf_key` = 'redis_port');

INSERT INTO `qf_conf` (`conf_key`, `conf_value`, `conf_title`, `conf_desc`, `conf_status`, `conf_type`, `conf_spec`, `conf_content`, `conf_sort`, `conf_system`, `conf_createtime`, `conf_updatetime`)
SELECT 'redis_password', '', 'Redis密码', 'Redis密码，如无密码则留空', 1, 4, 0, NULL, 4, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `qf_conf` WHERE `conf_key` = 'redis_password');

INSERT INTO `qf_conf` (`conf_key`, `conf_value`, `conf_title`, `conf_desc`, `conf_status`, `conf_type`, `conf_spec`, `conf_content`, `conf_sort`, `conf_system`, `conf_createtime`, `conf_updatetime`)
SELECT 'memcached_host', '127.0.0.1', 'Memcached服务器地址', 'Memcached服务器IP地址，默认本机：127.0.0.1', 1, 4, 0, NULL, 5, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `qf_conf` WHERE `conf_key` = 'memcached_host');

INSERT INTO `qf_conf` (`conf_key`, `conf_value`, `conf_title`, `conf_desc`, `conf_status`, `conf_type`, `conf_spec`, `conf_content`, `conf_sort`, `conf_system`, `conf_createtime`, `conf_updatetime`)
SELECT 'memcached_port', '11211', 'Memcached端口', 'Memcached服务端口，默认：11211', 1, 4, 0, NULL, 6, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `qf_conf` WHERE `conf_key` = 'memcached_port');

INSERT INTO `qf_conf` (`conf_key`, `conf_value`, `conf_title`, `conf_desc`, `conf_status`, `conf_type`, `conf_spec`, `conf_content`, `conf_sort`, `conf_system`, `conf_createtime`, `conf_updatetime`)
SELECT 'memcached_username', '', 'Memcached用户名', 'SASL认证用户名，未启用可留空', 1, 4, 0, NULL, 7, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `qf_conf` WHERE `conf_key` = 'memcached_username');

INSERT INTO `qf_conf` (`conf_key`, `conf_value`, `conf_title`, `conf_desc`, `conf_status`, `conf_type`, `conf_spec`, `conf_content`, `conf_sort`, `conf_system`, `conf_createtime`, `conf_updatetime`)
SELECT 'memcached_password', '', 'Memcached密码', 'SASL认证密码，未启用可留空', 1, 4, 0, NULL, 8, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `qf_conf` WHERE `conf_key` = 'memcached_password');

INSERT INTO `qf_conf` (`conf_key`, `conf_value`, `conf_title`, `conf_desc`, `conf_status`, `conf_type`, `conf_spec`, `conf_content`, `conf_sort`, `conf_system`, `conf_createtime`, `conf_updatetime`)
SELECT 'fake_mode_enable', '0', '伪装模式开关', '开启后，前台资源链接会按网盘类型替换为下方配置的测试链接', 1, 4, 2, '关闭=>0\n开启=>1', 1006, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `qf_conf` WHERE `conf_key` = 'fake_mode_enable');

INSERT INTO `qf_conf` (`conf_key`, `conf_value`, `conf_title`, `conf_desc`, `conf_status`, `conf_type`, `conf_spec`, `conf_content`, `conf_sort`, `conf_system`, `conf_createtime`, `conf_updatetime`)
SELECT 'fake_quark_url', '', '伪装夸克链接', '伪装模式开启后，夸克类型资源会展示此链接', 1, 4, 0, NULL, 1007, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `qf_conf` WHERE `conf_key` = 'fake_quark_url');

INSERT INTO `qf_conf` (`conf_key`, `conf_value`, `conf_title`, `conf_desc`, `conf_status`, `conf_type`, `conf_spec`, `conf_content`, `conf_sort`, `conf_system`, `conf_createtime`, `conf_updatetime`)
SELECT 'fake_baidu_url', '', '伪装百度链接', '伪装模式开启后，百度类型资源会展示此链接', 1, 4, 0, NULL, 1008, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `qf_conf` WHERE `conf_key` = 'fake_baidu_url');

INSERT INTO `qf_conf` (`conf_key`, `conf_value`, `conf_title`, `conf_desc`, `conf_status`, `conf_type`, `conf_spec`, `conf_content`, `conf_sort`, `conf_system`, `conf_createtime`, `conf_updatetime`)
SELECT 'other_pan_check_enable', '0', '其他网盘检测开关', '开启后将对百度、UC、迅雷等非夸克网盘进行有效性检测', 1, 1, 2, '开启=>1\n关闭=>0', 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `qf_conf` WHERE `conf_key` = 'other_pan_check_enable');

INSERT INTO `qf_conf` (`conf_key`, `conf_value`, `conf_title`, `conf_desc`, `conf_status`, `conf_type`, `conf_spec`, `conf_content`, `conf_sort`, `conf_system`, `conf_createtime`, `conf_updatetime`)
SELECT 'other_pan_check_api', '', '网盘检测API地址', '检测API地址，选择PanCheck时必填，例如：http://your-domain.com/api/v1/links/check', 1, 1, 0, '', 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `qf_conf` WHERE `conf_key` = 'other_pan_check_api');

INSERT INTO `qf_conf` (`conf_key`, `conf_value`, `conf_title`, `conf_desc`, `conf_status`, `conf_type`, `conf_spec`, `conf_content`, `conf_sort`, `conf_system`, `conf_createtime`, `conf_updatetime`)
SELECT 'other_pan_check_mode', 'local', '检测API类型', '选择检测接口的类型', 1, 1, 2, '本地接口=>local\nPanCheck接口=>pancheck', 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `qf_conf` WHERE `conf_key` = 'other_pan_check_mode');

INSERT INTO `qf_conf` (`conf_key`, `conf_value`, `conf_title`, `conf_desc`, `conf_status`, `conf_type`, `conf_spec`, `conf_content`, `conf_sort`, `conf_system`, `conf_createtime`, `conf_updatetime`)
SELECT 'diagnostic_log_enable', '0', '诊断日志记录开关', '仅调试搜索、转存、目录树问题时开启，关闭后不再写入诊断日志', 0, 3, 2, '关闭=>0\n开启=>1', 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `qf_conf` WHERE `conf_key` = 'diagnostic_log_enable');

-- ----------------------------
-- 隐藏菜单节点：清理数据 / 诊断日志
-- ----------------------------
INSERT INTO `qf_node` (`node_title`, `node_desc`, `node_module`, `node_controller`, `node_action`, `node_pid`, `node_order`, `node_show`, `node_icon`, `node_extend`, `node_status`, `node_createtime`, `node_updatetime`)
SELECT '清理数据', '', 'qfadmin', 'system', 'clean', 0, 0, 0, '', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `qf_node` WHERE `node_module` = 'qfadmin' AND `node_controller` = 'system' AND `node_action` = 'clean');

INSERT INTO `qf_node` (`node_title`, `node_desc`, `node_module`, `node_controller`, `node_action`, `node_pid`, `node_order`, `node_show`, `node_icon`, `node_extend`, `node_status`, `node_createtime`, `node_updatetime`)
SELECT '诊断日志', '', 'qfadmin', 'system', 'diagnostic', 0, 0, 0, 'el-icon-warning-outline', NULL, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `qf_node` WHERE `node_module` = 'qfadmin' AND `node_controller` = 'system' AND `node_action` = 'diagnostic');
