-- qf_api_list TG资源池字段升级脚本
-- 可重复执行：字段存在时不会重复 ALTER。

SET @table_name := 'qf_api_list';

SET @sql := IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'pan_types'),
  'SELECT ''pan_types exists''',
  'ALTER TABLE `qf_api_list` ADD COLUMN `pan_types` varchar(50) NOT NULL DEFAULT '''' COMMENT ''TG/PanSou支持的网盘类型，多个用英文逗号分隔：0=夸克 2=百度 3=UC 4=迅雷'' AFTER `status`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'tg_channels'),
  'SELECT ''tg_channels exists''',
  'ALTER TABLE `qf_api_list` ADD COLUMN `tg_channels` text COMMENT ''TG群组资源池JSON，包含群组ID和启用状态'' AFTER `pan_types`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @table_name AND COLUMN_NAME = 'tg_scan_limit'),
  'SELECT ''tg_scan_limit exists''',
  'ALTER TABLE `qf_api_list` ADD COLUMN `tg_scan_limit` int(11) NOT NULL DEFAULT ''5'' COMMENT ''TG资源池每次搜索最多扫描的群组数'' AFTER `tg_channels`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `qf_api_list`
SET `pan_types` = CAST(`pantype` AS CHAR)
WHERE `type` IN ('tg', 'pansou') AND (`pan_types` IS NULL OR `pan_types` = '');

UPDATE `qf_api_list`
SET `tg_channels` = CONCAT('[{"channel":"', REPLACE(`url`, '"', '\\"'), '","enabled":1}]')
WHERE `type` = 'tg'
  AND (`tg_channels` IS NULL OR `tg_channels` = '')
  AND `url` <> ''
  AND `url` NOT LIKE 'TG群组 % 个';

UPDATE `qf_api_list`
SET `tg_scan_limit` = 5
WHERE `type` = 'tg' AND (`tg_scan_limit` IS NULL OR `tg_scan_limit` <= 0);

-- 历史上散开的 TG 群组线路建议用后台“检测结构 / 一键修复结构”收口；
-- 纯 SQL 不强行合并多行，避免不同 MySQL 版本的 JSON 聚合兼容问题。
