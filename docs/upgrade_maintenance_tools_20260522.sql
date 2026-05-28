-- 后台维护工具入口升级脚本（可重复执行）
-- 只补菜单节点，不新增业务表字段。

INSERT INTO `qf_node` (`node_id`, `node_title`, `node_desc`, `node_module`, `node_controller`, `node_action`, `node_pid`, `node_order`, `node_show`, `node_icon`, `node_extend`, `node_status`, `node_createtime`, `node_updatetime`)
SELECT 122, '维护工具', '', 'qfadmin', 'system', 'maintenance', 3, 3, 1, 'el-icon-tools', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (
    SELECT 1 FROM `qf_node`
    WHERE `node_module` = 'qfadmin' AND `node_controller` = 'system' AND `node_action` = 'maintenance'
);

UPDATE `qf_node`
SET `node_title` = '维护工具',
    `node_pid` = 3,
    `node_order` = 3,
    `node_show` = 1,
    `node_icon` = 'el-icon-tools',
    `node_status` = 0,
    `node_updatetime` = UNIX_TIMESTAMP()
WHERE `node_module` = 'qfadmin' AND `node_controller` = 'system' AND `node_action` = 'maintenance';
