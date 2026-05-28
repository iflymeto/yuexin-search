/*
 Navicat Premium Data Transfer

 Source Server         : cms本地
 Source Server Type    : MySQL
 Source Server Version : 50726
 Source Host           : localhost:3306
 Source Schema         : www_dj_com

 Target Server Type    : MySQL
 Target Server Version : 50726
 File Encoding         : 65001

 Date: 14/09/2024 16:54:39
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for qf_access
-- ----------------------------
DROP TABLE IF EXISTS `qf_access`;
CREATE TABLE `qf_access`  (
  `access_id` int(11) NOT NULL AUTO_INCREMENT,
  `access_admin` int(11) NOT NULL DEFAULT 0 COMMENT '用户ID',
  `access_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT 'AccessToken',
  `access_plat` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'all' COMMENT '登录平台',
  `access_ip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT 'IP',
  `access_status` int(11) NOT NULL DEFAULT 0 COMMENT '状态',
  `access_createtime` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `access_updatetime` int(11) NOT NULL DEFAULT 0 COMMENT '修改时间',
  PRIMARY KEY (`access_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '授权信息表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for qf_admin
-- ----------------------------
DROP TABLE IF EXISTS `qf_admin`;
CREATE TABLE `qf_admin`  (
  `admin_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'UID',
  `admin_account` varchar(64) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '帐号',
  `admin_password` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '密码',
  `admin_salt` varchar(4) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '密码盐',
  `admin_name` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '用户昵称',
  `admin_idcard` varchar(18) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '身份证',
  `admin_truename` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '真实姓名',
  `admin_email` varchar(64) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '邮箱',
  `admin_money` decimal(9, 2) NOT NULL DEFAULT 0.00 COMMENT '余额',
  `admin_group` int(11) NOT NULL DEFAULT 0 COMMENT '用户组',
  `admin_ipreg` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL COMMENT '注册IP',
  `admin_status` int(11) NOT NULL DEFAULT 0 COMMENT '1被禁用',
  `admin_createtime` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `admin_updatetime` int(11) NOT NULL DEFAULT 0 COMMENT '修改时间',
  PRIMARY KEY (`admin_id`) USING BTREE,
  INDEX `admin_group`(`admin_group`) USING BTREE,
  INDEX `admin_name`(`admin_name`) USING BTREE,
  INDEX `admin_password`(`admin_password`) USING BTREE,
  INDEX `admin_account`(`admin_account`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '用户表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for qf_attach
-- ----------------------------
DROP TABLE IF EXISTS `qf_attach`;
CREATE TABLE `qf_attach`  (
  `attach_id` int(11) NOT NULL AUTO_INCREMENT,
  `attach_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '文件名',
  `attach_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '路径',
  `attach_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '类型',
  `attach_size` int(11) NOT NULL DEFAULT 0 COMMENT '大小',
  `attach_admin` int(11) NOT NULL DEFAULT 0 COMMENT '用户',
  `attach_status` int(11) NOT NULL DEFAULT 0 COMMENT '状态',
  `attach_createtime` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `attach_updatetime` int(11) NOT NULL DEFAULT 0 COMMENT '修改时间',
  PRIMARY KEY (`attach_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '附件表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for qf_auth
-- ----------------------------
DROP TABLE IF EXISTS `qf_auth`;
CREATE TABLE `qf_auth`  (
  `auth_id` bigint(20) NOT NULL AUTO_INCREMENT COMMENT '权限ID',
  `auth_group` int(11) NOT NULL DEFAULT 0 COMMENT '权限管理组',
  `auth_node` int(11) NOT NULL DEFAULT 0 COMMENT '功能ID',
  `auth_status` int(11) NOT NULL DEFAULT 0 COMMENT '1被禁用',
  `auth_createtime` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `auth_updatetime` int(11) NOT NULL DEFAULT 0 COMMENT '修改时间',
  PRIMARY KEY (`auth_id`) USING BTREE,
  INDEX `role_group`(`auth_group`) USING BTREE,
  INDEX `role_auth`(`auth_node`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '权限表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for qf_conf
-- ----------------------------
DROP TABLE IF EXISTS `qf_conf`;
CREATE TABLE `qf_conf`  (
  `conf_id` int(11) NOT NULL AUTO_INCREMENT,
  `conf_key` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '参数名',
  `conf_value` text CHARACTER SET utf8 COLLATE utf8_general_ci COMMENT '参数值',
  `conf_title` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT '' COMMENT '参数名称',
  `conf_desc` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT '' COMMENT '参数描述',
  `conf_int` int(11) NOT NULL DEFAULT 0 COMMENT '参数到期',
  `conf_spec` int(11) NOT NULL DEFAULT 0 COMMENT '文本类型',
  `conf_content` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '单选多选等文本类型的数据集',
  `conf_type` int(11) NOT NULL DEFAULT 0 COMMENT '配置分类 ',
  `conf_status` int(11) NOT NULL DEFAULT 0 COMMENT '显示隐藏 0是隐藏',
  `conf_sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `conf_system` int(11) NOT NULL DEFAULT 0 COMMENT '1为系统参数，请勿删除',
  `conf_createtime` int(11) NOT NULL DEFAULT 0,
  `conf_updatetime` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`conf_id`) USING BTREE,
  INDEX `conf_key`(`conf_key`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 52 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '配置表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of qf_conf
-- ----------------------------
INSERT INTO `qf_conf` VALUES (1, 'app_name', '', '网站名称', '', 0, 0, NULL, 0, 1, 99, 1, 0, 1725411498);
INSERT INTO `qf_conf` VALUES (2, 'upload_max_file', '4097152', '最大文件上传限制', '', 0, 0, NULL, 2, 1, 0, 1, 0, 1617352067);
INSERT INTO `qf_conf` VALUES (3, 'upload_file_type', 'csv,xlsx', '允许文件上传类型', '', 0, 0, NULL, 2, 1, 0, 1, 0, 1617351959);
INSERT INTO `qf_conf` VALUES (4, 'upload_max_image', '2097152', '最大图片上传限制', '', 0, 0, NULL, 2, 1, 0, 1, 0, 1617351961);
INSERT INTO `qf_conf` VALUES (5, 'upload_image_type', 'jpg,png,gif,jpeg,bmp', '允许上传图片类型', '', 0, 0, NULL, 2, 1, 0, 1, 0, 1617351964);
INSERT INTO `qf_conf` VALUES (21, 'logo', '', '网站LOGO', '方形LOGO，最佳显示尺寸为80*80像素', 0, 4, NULL, 0, 1, 93, 1, 1711636952, 1725006864);
INSERT INTO `qf_conf` VALUES (22, 'quark_cookie', '', '夸克Cookie', '', 0, 0, NULL, 4, 1, 0, 1, 1712114435, 1712114652);
INSERT INTO `qf_conf` VALUES (23, 'qcode', '', '群二维码', '群二维码（支持上传图片或填写直链）', 0, 8, NULL, 3, 1, 80, 1, 1712451616, 1725326400);
INSERT INTO `qf_conf` VALUES (24, 'app_description', '', 'SEO描述', '', 0, 1, NULL, 9, 1, 996, 1, 1712451778, 1725411481);
INSERT INTO `qf_conf` VALUES (25, 'quark_banned', '失效,年会员,空间容量,微信,微信群,全网资源,影视资源,扫码,最新资源,公众号,IMG_,资源汇总,緑铯粢源,.url,网盘推广,大额优惠券,资源文档,dy8.xyz,妙妙屋,资源合集,kkdm', '广告词', '出现这些词的资源，转存时删除；格式如：影视资源,年会员', 0, 1, NULL, 4, 1, 999, 1, 1714035639, 1723795683);
INSERT INTO `qf_conf` VALUES (26, 'Authorization', '', '阿里Authorization', '此版本用不着', 0, 0, NULL, 4, 1, 0, 1, 1722010465, 1722010465);
INSERT INTO `qf_conf` VALUES (27, 'mp4_online', '0', '在线观看资源', '此版本用不着', 0, 2, '开启=>1\n关闭=>0', 4, 1, 0, 1, 1723014926, 1723014926);
INSERT INTO `qf_conf` VALUES (28, 'search_type', '1', '搜索模式', '精准搜索：只有查包含关键词的；模糊搜索：关键词顺序可乱但必须都包含；分词搜索：只要满足其中一个字就会搜索到', 0, 2, '精准搜索=>0\n模糊搜索=>1\n分词搜索=>2', 1, 1, 0, 1, 1724493746, 1724494058);
INSERT INTO `qf_conf` VALUES (29, 'app_keywords', '', 'SEO关键词', '网站关键词，有利于对整站的SEO优化', 0, 1, NULL, 9, 1, 998, 1, 1725006403, 1725411476);
INSERT INTO `qf_conf` VALUES (30, 'app_title', '', 'SEO标题', '', 0, 0, NULL, 9, 1, 999, 1, 1725006679, 1725325013);
INSERT INTO `qf_conf` VALUES (31, 'app_subname', '', '网站宣传语', '免费分享百万级网盘资源，致力打造顶尖网盘搜索引擎，让您畅享资源无忧！', 0, 0, NULL, 0, 1, 94, 1, 1725006792, 1725006869);
INSERT INTO `qf_conf` VALUES (32, 'home_bg', '', '大图背景', '', 0, 4, '', 3, 1, 75, 1, 1725007588, 1725007613);
INSERT INTO `qf_conf` VALUES (33, 'home_background', NULL, '背景颜色', '默认：#fafafa', 0, 7, NULL, 3, 1, 74, 1, 1725007770, 1725027349);
INSERT INTO `qf_conf` VALUES (34, 'footer_dec', '', '底部介绍','示例：声明：本站是网盘索引系统,所有内容均来自互联网所提供的公开引用资源，未提供资源上传、存储服务。', 0, 1, NULL, 0, 1, 90, 1, 1725025185, 1725325534);
INSERT INTO `qf_conf` VALUES (35, 'footer_copyright', '', '底部版权','示例：© 2024 XX搜剧 Powered by <a href=\"https://www.XXXXX.com/\" target=\"_blank\">XX导航</a>', 0, 1, NULL, 0, 1, 89, 1, 1725025262, 1725325624);
INSERT INTO `qf_conf` VALUES (36, 'home_color', NULL, '文字颜色', '默认文字颜色：#000000', 0, 7, NULL, 3, 1, 73, 1, 1725027432, 1725027445);
INSERT INTO `qf_conf` VALUES (37, 'home_theme', NULL, '主题色', '默认：#1e80ff', 0, 7, NULL, 3, 1, 72, 1, 1725027499, 1725027504);
INSERT INTO `qf_conf` VALUES (38, 'other_background', NULL, '其它元素背景', '搜索框及其它元素北背景色 默认：#ffffff', 0, 7, NULL, 3, 1, 71, 1, 1725028468, 1725028478);
INSERT INTO `qf_conf` VALUES (39, 'ranking_type', '0', '显示模式', '', 0, 2, '无图模式=>0\n有图模式=>1', 3, 1, 79, 1, 1725159933, 1725160022);
INSERT INTO `qf_conf` VALUES (40, 'ranking_num', '10', '排行榜数量', '下次更新生效；排行榜数据每12个小时更新一次；右上角清除缓存立即生效', 0, 0, NULL, 3, 1, 78, 1, 1725160003, 1725171288);
INSERT INTO `qf_conf` VALUES (41, 'home_css', '', '自定义CSS', '直接写css样式就行', 0, 1, NULL, 3, 1, 70, 1, 1725324697, 1725324697);
INSERT INTO `qf_conf` VALUES (42, 'seo_statistics', '', '统计代码', '直接填写统计代码即可，如51LA： <script charset=\"UTF-8\" id=\"XXXXX\" src=\"//sdk.51.la/js-sdk-pro.min.js\"></script> 	<script>LA.init({id:\"XXXXX\",ck:\"XXXX\",hashMode:true})</script>', 0, 1, NULL, 9, 1, 995, 1, 1725325341, 1725411486);
INSERT INTO `qf_conf` VALUES (43, 'app_icon', '', '网站icon', '', 0, 4, NULL, 0, 1, 92, 1, 1725326071, 1725326071);
INSERT INTO `qf_conf` VALUES (44, 'app_demand', '0', '提交需求', '前台是否开启此功能 ；  默认开启', 0, 2, '开启=>0\n关闭=>1', 3, 1, 81, 1, 1725326640, 1725326707);
INSERT INTO `qf_conf` VALUES (45, 'app_links', '', '顶部其他外链', '一行一个外链(a标签)：<a href=\"https://www.XXXXX.com/\" target=\"_blank\">更多资源</a>', 0, 1, NULL, 3, 1, 80, 1, 1725326838, 1725326838);
INSERT INTO `qf_conf` VALUES (46, 'app_name_hide', '0', '隐藏网站名称', '默认显示：logo包含文字的可以隐藏网站名称', 0, 2, '显示=>0\n隐藏=>1', 0, 1, 98, 1, 1725411632, 1725411763);
INSERT INTO `qf_conf` VALUES (47, 'ranking_m_num', '6', '移动端限制数量', '释：移动端最多显示数量', 0, 0, NULL, 3, 1, 77, 1, 1725412329, 1725412329);
INSERT INTO `qf_conf` VALUES (48, 'search_tips', '', '未搜索提示词', '为空时默认：未找到，可换个关键词尝试哦~', 0, 0, NULL, 1, 1, 0, 1, 1726108804, 1726108804);
INSERT INTO `qf_conf` VALUES (49, 'search_bg', '', '未搜索提示图', '', 0, 4, NULL, 1, 1, 0, 1, 1726108851, 1726108851);
INSERT INTO `qf_conf` VALUES (50, 'home_new', '1', '最新列表', '仅无图模式有效', 0, 2, '开启=>0\n关闭=>1', 3, 1, 79, 1, 1726299605, 1726299605);
INSERT INTO `qf_conf` VALUES (51, 'home_new_img', '', '最新图标', '', 0, 4, NULL, 3, 1, 79, 1, 1726302688, 1726302688);
INSERT INTO `qf_conf` VALUES (52, 'is_quan', '0', '全网搜', '', 0, 2, '关闭=>0\n开启=>1', 1, 1, 1, 1, 1729928547, 1729928547);
INSERT INTO `qf_conf` VALUES ('53', 'baidu_cookie', '', '百度Cookie', '', '0', '0', NULL, '4', '1', '89', '1', '1743145595', '1743145595');
INSERT INTO `qf_conf` VALUES ('54', 'quark_file', '', '夸克默认转存目录', '', '0', '0', NULL, '4', '1', '98', '1', '1743145595', '1743145595');
INSERT INTO `qf_conf` VALUES ('55', 'quark_file_time', '', '夸克临时资源目录', '', '0', '0', NULL, '4', '1', '97', '1', '1743145595', '1743145595');
INSERT INTO `qf_conf` VALUES ('66', 'resource_password_enable', '0', '资源密码验证', '是否开启资源获取密码验证功能', '0', '2', '关闭=>0\n开启=>1', '11', '1', '99', '1', '1756381135', '1756381135');
INSERT INTO `qf_conf` VALUES ('67', 'resource_password', '', '资源访问密码', '用户获取资源时需要输入的密码', '0', '0', NULL, '11', '1', '98', '1', '1756381135', '1756381135');
INSERT INTO `qf_conf` VALUES ('68', 'resource_password_hint', '请输入密码以获取资源地址', '密码提示信息', '密码验证框显示的提示信息', '0', '1', NULL, '11', '1', '97', '1', '1756381135', '1756381135');
INSERT INTO `qf_conf` VALUES ('69', 'resource_password_error', '密码错误，请重新输入', '密码错误提示', '密码验证失败时显示的错误信息', '0', '1', NULL, '11', '1', '96', '1', '1756381135', '1756381135');
INSERT INTO `qf_conf` VALUES ('70', 'resource_password_expire', '7', '密码记忆天数', '用户输入正确密码后，本地记忆的天数（1-30天）', '0', '0', NULL, '11', '1', '95', '1', '1756381135', '1756381135');
INSERT INTO `qf_conf` VALUES ('56', 'baidu_file', '', '百度默认转存目录', '', '0', '0', NULL, '4', '1', '88', '1', '1743145595', '1743145595');
INSERT INTO `qf_conf` VALUES ('57', 'baidu_file_time', '', '百度临时资源目录', '', '0', '0', NULL, '4', '1', '87', '1', '1743145595', '1743145595');
INSERT INTO `qf_conf` VALUES ('58', 'ali_file', '', '阿里默认转存目录', '', '0', '0', NULL, '4', '1', '78', '1', '1743145595', '1743145595');
INSERT INTO `qf_conf` VALUES ('59', 'ali_file_time', '', '阿里临时资源目录', '', '0', '0', NULL, '4', '1', '77', '1', '1743145595', '1743145595');
INSERT INTO `qf_conf` VALUES ('60', 'uc_cookie', '', 'UcCookie', '', '0', '0', NULL, '4', '1', '69', '1', '1743145595', '1743145595');
INSERT INTO `qf_conf` VALUES ('61', 'uc_file', '', 'UC默认转存目录', '', '0', '0', NULL, '4', '1', '68', '1', '1743145595', '1743145595');
INSERT INTO `qf_conf` VALUES ('62', 'uc_file_time', '', 'UC临时资源目录', '', '0', '0', NULL, '4', '1', '67', '1', '1743145595', '1743145595');
INSERT INTO `qf_conf` VALUES ('63', 'xunlei_cookie', '', '迅雷Cookie', '', '0', '0', NULL, '4', '1', '59', '1', '1743149794', '1743149794');
INSERT INTO `qf_conf` VALUES ('64', 'xunlei_file', '', '迅雷默认转存目录', '', '0', '0', NULL, '4', '1', '58', '1', '1743149819', '1743149819');
INSERT INTO `qf_conf` VALUES ('65', 'xunlei_file_time', '', '迅雷临时资源目录', '', '0', '0', NULL, '4', '1', '57', '1', '1743149860', '1743149860');
INSERT INTO `qf_conf` VALUES ('71', 'api_key', '', '接口api_key', '个别接口需要此参数方可调用', '0', '0', NULL, '1', '1', '0', '1', '1743154753', '1743154753');
INSERT INTO `qf_conf` VALUES ('72', 'pc_type', '0', 'PC端访问方式', '跳转：直接通过链接访问；扫码：提示用户用手机扫码访问', '0', '2', '跳转+扫码=>0\n仅跳转=>1\n仅扫码=>2', '3', '1', '90', '1', '1744861402', '1744861423');
INSERT INTO `qf_conf` VALUES ('73', 'is_quan_type', '0', '全网搜模式', '第三方直链: 直接展示第三方接口的资源', '0', '2', '转存分享=>0\n第三方直链=>1', '1', '1', '1', '1', '1744882575', '1744882612');
INSERT INTO `qf_conf` VALUES ('74', 'is_quan_zc', '1', '检测资源', '开启后全网搜将会过滤失效的资源；仅支持夸克', '0', '2', '开启=>1\n关闭=>0', '1', '1', '1', '1', '1744882765', '1744882765');
INSERT INTO `qf_conf` VALUES ('75', 'ban_keywords', '', '关键词屏蔽', '屏蔽搜索关键词，如：庆余年,凡人修仙传', '0', '1', NULL, '1', '1', '1', '1', '1755842460', '1755842580');
INSERT INTO `qf_conf` VALUES ('76', 'cache_driver', 'file', '缓存驱动', '选择系统使用的缓存方式：file=文件缓存（无需服务）；redis=Redis缓存（需安装Redis）；memcached=Memcached缓存（需安装Memcached）', '0', '2', '文件缓存=>file\nRedis缓存=>redis\nMemcached缓存=>memcached', '4', '1', '1', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
INSERT INTO `qf_conf` VALUES ('77', 'redis_host', '127.0.0.1', 'Redis服务器地址', 'Redis服务器IP地址，默认本机：127.0.0.1', '0', '0', NULL, '4', '1', '2', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
INSERT INTO `qf_conf` VALUES ('78', 'redis_port', '6379', 'Redis端口', 'Redis服务器端口，默认：6379', '0', '0', NULL, '4', '1', '3', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
INSERT INTO `qf_conf` VALUES ('79', 'redis_password', '', 'Redis密码', 'Redis密码，如无密码则留空', '0', '0', NULL, '4', '1', '4', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
INSERT INTO `qf_conf` VALUES ('91', 'proxy_enable', '0', '网盘API代理开关', '开启后，网盘API请求将优先通过代理IP发起。仅对夸克/百度/UC/迅雷网盘域名生效', '0', '2', '关闭=>0\n开启=>1', '1', '1', '1000', '1', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
INSERT INTO `qf_conf` VALUES ('92', 'proxy_host', '', '代理地址', '填写代理服务器IP或域名，例如：127.0.0.1 或 proxy.example.com', '0', '0', NULL, '1', '1', '1001', '1', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
INSERT INTO `qf_conf` VALUES ('93', 'proxy_port', '', '代理端口', '填写代理端口，例如：7890、1080', '0', '0', NULL, '1', '1', '1002', '1', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
INSERT INTO `qf_conf` VALUES ('94', 'proxy_type', 'http', '代理协议', '根据代理服务选择协议类型', '0', '2', 'HTTP=>http\nSOCKS5=>socks5\nSOCKS4=>socks4', '1', '1', '1003', '1', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
INSERT INTO `qf_conf` VALUES ('95', 'proxy_user', '', '代理账号', '如代理需要认证，请填写账号；不需要可留空', '0', '0', NULL, '1', '1', '1004', '1', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
INSERT INTO `qf_conf` VALUES ('96', 'proxy_pass', '', '代理密码', '如代理需要认证，请填写密码；不需要可留空', '0', '0', NULL, '1', '1', '1005', '1', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
INSERT INTO `qf_conf` VALUES ('87', 'memcached_host', '127.0.0.1', 'Memcached服务器地址', 'Memcached服务器IP地址，默认本机：127.0.0.1', '0', '0', NULL, '4', '1', '5', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
INSERT INTO `qf_conf` VALUES ('88', 'memcached_port', '11211', 'Memcached端口', 'Memcached服务端口，默认：11211', '0', '0', NULL, '4', '1', '6', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
INSERT INTO `qf_conf` VALUES ('89', 'memcached_username', '', 'Memcached用户名', 'SASL认证用户名，未启用可留空', '0', '0', NULL, '4', '1', '7', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
INSERT INTO `qf_conf` VALUES ('90', 'memcached_password', '', 'Memcached密码', 'SASL认证密码，未启用可留空', '0', '0', NULL, '4', '1', '8', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
INSERT INTO `qf_conf` VALUES ('86', 'quark_ad_filter_enable', '1', '广告过滤开关', '开启后转存时会递归扫描并删除包含广告关键词的文件；关闭可节省30-50%转存时间', '0', '2', '开启=>1\n关闭=>0', '4', '1', '998', '1', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
INSERT INTO `qf_conf` VALUES ('85', 'web_search_dedup', '1', '全网搜去重开关', '开启后，全网搜将自动排除本地已转存的资源链接，避免结果重复。关闭后显示所有搜索结果（可能包含重复）', '0', '2', '开启=>1\n关闭=>0', '1', '1', '6', '1', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

INSERT INTO `qf_conf` VALUES ('97', 'fake_mode_enable', '0', '伪装模式开关', '开启后，前台资源链接会按网盘类型替换为下方配置的测试链接', '0', '2', '关闭=>0\n开启=>1', '1', '1', '1006', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
INSERT INTO `qf_conf` VALUES ('98', 'fake_quark_url', '', '伪装夸克链接', '伪装模式开启后，夸克类型资源会展示此链接', '0', '0', NULL, '1', '1', '1007', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
INSERT INTO `qf_conf` VALUES ('99', 'fake_baidu_url', '', '伪装百度链接', '伪装模式开启后，百度类型资源会展示此链接', '0', '0', NULL, '1', '1', '1008', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());
INSERT INTO `qf_conf` VALUES ('100', 'frontend_theme', 'news', '前端主题', '选择前台首页、搜索页、详情页使用的主题；主题缺失时自动回退默认主题', '0', '2', '默认主题=>news\n简洁主题=>simple\n仿魔法影视模板=>mofa', '3', '1', '100', '1', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- ----------------------------
-- Table structure for qf_days
-- ----------------------------
DROP TABLE IF EXISTS `qf_days`;
CREATE TABLE `qf_days`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '',
  `time` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for qf_feedback
-- ----------------------------
DROP TABLE IF EXISTS `qf_feedback`;
CREATE TABLE `qf_feedback`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `content` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL COMMENT '用户想要的资源描述',
  `email` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL COMMENT '联系邮箱',
  `ip` varchar(45) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL COMMENT '用户IP地址',
  `create_time` int(11) NOT NULL DEFAULT 0,
  `update_time` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for qf_group
-- ----------------------------
DROP TABLE IF EXISTS `qf_group`;
CREATE TABLE `qf_group`  (
  `group_id` int(11) NOT NULL AUTO_INCREMENT,
  `group_name` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '管理组名称',
  `group_desc` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '管理组描述',
  `group_status` int(11) NOT NULL DEFAULT 0 COMMENT '1被禁用',
  `group_createtime` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `group_updatetime` int(11) NOT NULL DEFAULT 0 COMMENT '修改时间',
  PRIMARY KEY (`group_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '管理组表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of qf_group
-- ----------------------------
INSERT INTO `qf_group` VALUES (1, '超级管理员', '不允许删除', 0, 0, 1575903468);

-- ----------------------------
-- Table structure for qf_log
-- ----------------------------
DROP TABLE IF EXISTS `qf_log`;
CREATE TABLE `qf_log`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `ip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `domain` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `update_time` int(11) NOT NULL DEFAULT 0,
  `create_time` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for qf_node
-- ----------------------------
DROP TABLE IF EXISTS `qf_node`;
CREATE TABLE `qf_node`  (
  `node_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '功能ID',
  `node_title` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '功能名称',
  `node_desc` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '功能描述',
  `node_module` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'api' COMMENT '模块',
  `node_controller` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '控制器',
  `node_action` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '方法',
  `node_pid` int(11) NOT NULL DEFAULT 0 COMMENT '父ID',
  `node_order` int(11) NOT NULL DEFAULT 0 COMMENT '排序ID',
  `node_show` int(11) NOT NULL DEFAULT 1 COMMENT '1显示到菜单',
  `node_icon` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '图标',
  `node_extend` text CHARACTER SET utf8 COLLATE utf8_general_ci COMMENT '扩展数据',
  `node_status` int(11) NOT NULL DEFAULT 0 COMMENT '1被禁用',
  `node_createtime` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `node_updatetime` int(11) NOT NULL DEFAULT 0 COMMENT '修改时间',
  PRIMARY KEY (`node_id`) USING BTREE,
  INDEX `auth_pid`(`node_pid`) USING BTREE,
  INDEX `node_module`(`node_module`) USING BTREE,
  INDEX `node_controller`(`node_controller`) USING BTREE,
  INDEX `node_action`(`node_action`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 123 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '功能节点表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of qf_node
-- ----------------------------
INSERT INTO `qf_node` VALUES (1, '概况', '', 'qfadmin', 'index', 'index', 0, 999, 1, 'el-icon-house', NULL, 0, 0, 1620874188);
INSERT INTO `qf_node` VALUES (2, '运营人员', '', 'qfadmin', '', '', 3, 0, 1, 'el-icon-user', NULL, 0, 0, 1618302083);
INSERT INTO `qf_node` VALUES (3, '系统', '', 'qfadmin', '', '', 0, 0, 1, 'el-icon-data-board', NULL, 0, 0, 1618301765);
INSERT INTO `qf_node` VALUES (4, '配置', '', 'qfadmin', '', '', 0, 0, 1, 'el-icon-setting', NULL, 0, 1617269862, 1712241481);
INSERT INTO `qf_node` VALUES (100, '管理员列表', '', 'qfadmin', 'admin', 'index', 2, 0, 1, 'el-icon-user', '', 0, 0, 1618794624);
INSERT INTO `qf_node` VALUES (101, '用户组管理', '', 'qfadmin', 'group', 'index', 2, 0, 1, '', NULL, 0, 0, 1617246287);
INSERT INTO `qf_node` VALUES (102, '参数配置', '', 'qfadmin', 'conf', 'index', 4, 5, 1, 'el-icon-set-up', '', 0, 0, 1617350626);
INSERT INTO `qf_node` VALUES (104, '菜单管理', '', 'qfadmin', 'node', 'index', 4, 6, 1, 'el-icon-s-operation', '', 0, 0, 1617350880);
INSERT INTO `qf_node` VALUES (105, '附件管理', '', 'qfadmin', 'attach', 'index', 4, 4, 1, 'el-icon-connection', '', 0, 0, 1617345521);
INSERT INTO `qf_node` VALUES (106, '清理数据', '', 'qfadmin', 'system', 'clean', 0, 0, 0, '', '', 0, 0, 1712241480);
INSERT INTO `qf_node` VALUES (107, '基础设置', '', 'qfadmin', 'conf', 'base', 3, 5, 1, 'el-icon-s-operation', '', 0, 0, 1617773467);
INSERT INTO `qf_node` VALUES (122, '维护工具', '', 'qfadmin', 'system', 'maintenance', 3, 3, 1, 'el-icon-tools', '', 0, 1779408000, 1779408000);
INSERT INTO `qf_node` VALUES (121, '主题管理', '', 'qfadmin', 'system', 'theme', 3, 4, 1, 'el-icon-brush', '', 0, 1779408000, 1779408000);
INSERT INTO `qf_node` VALUES (108, '资源', '', 'qfadmin', '', '', 0, 1, 1, 'el-icon-files', NULL, 0, 1622538526, 1711117979);
INSERT INTO `qf_node` VALUES (109, '资源管理', '', 'qfadmin', 'source', 'index', 108, 10, 1, 'el-icon-folder-opened', NULL, 0, 1622538567, 1726190121);
INSERT INTO `qf_node` VALUES (112, '账号管理', '', 'qfadmin', 'source', 'deposit', 108, 1, 1, 'el-icon-crop', NULL, 0, 1712112542, 1726195575);
INSERT INTO `qf_node` VALUES (113, '资源日志', '', 'qfadmin', 'source', 'log', 108, 8, 1, 'el-icon-discover', NULL, 0, 1712208103, 1726195583);
INSERT INTO `qf_node` VALUES (114, '用户需求', '', 'qfadmin', 'source', 'feedback', 108, 1, 1, 'el-icon-edit', NULL, 0, 1712230638, 1712230717);
INSERT INTO `qf_node` VALUES (118, '分类管理', '', 'qfadmin', 'source', 'category', 108, 9, 1, 'el-icon-s-operation', NULL, 0, 1716363477, 1726190129);
INSERT INTO `qf_node` VALUES (119, '接口配置', '', 'qfadmin', 'source', 'apilist', '108', '1', '1', 'el-icon-link', NULL, '0', '1747119102', '1747119102');
INSERT INTO `qf_node` VALUES (120, '诊断日志', '', 'qfadmin', 'system', 'diagnostic', 0, 0, 0, 'el-icon-warning-outline', NULL, 0, 1778716800, 1778716800);

-- ----------------------------
-- Table structure for qf_source
-- ----------------------------
DROP TABLE IF EXISTS `qf_source`;
CREATE TABLE `qf_source`  (
  `source_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '资源名称',
  `url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '资源地址',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '目前用于副标题 搜索',
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci COMMENT '内容',
  `page_views` int(11) NOT NULL DEFAULT 0 COMMENT '浏览量',
  `is_time` int(11) NOT NULL DEFAULT 0 COMMENT '0正常 1临时文件',
  `is_user` tinyint(3) NOT NULL DEFAULT 0 COMMENT '状态 0=后台添加 1=用户添加',
  `fid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '夸克标识',
  `is_type` int(11) NOT NULL DEFAULT 0 COMMENT '0夸克网盘 1阿里网盘 2百度网盘 3UC网盘 4迅雷网盘',
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL COMMENT '提取码',
  `source_category_id` int(11) NOT NULL DEFAULT 0 COMMENT '分类ID',
  `vod_content` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '资源介绍',
  `vod_pic` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '资源图片',
  `status` tinyint(3) NOT NULL DEFAULT 1 COMMENT '状态 0=禁用 1=启用',
  `is_delete` tinyint(3) NOT NULL DEFAULT 0 COMMENT '是否删除 0=正常 1=软删除',
  `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NOT NULL DEFAULT 0 COMMENT '修改时间',
  PRIMARY KEY (`source_id`) USING BTREE,
  KEY `idx_create_time` (`create_time`),
  KEY `idx_category` (`source_category_id`),
  KEY `idx_is_type` (`is_type`),
  FULLTEXT INDEX `idx_fulltext_title_desc`(`title`, `description`) WITH PARSER ngram
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '会议管理表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for qf_invalid_resource
-- ----------------------------
DROP TABLE IF EXISTS `qf_invalid_resource`;
CREATE TABLE `qf_invalid_resource`  (
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
  UNIQUE INDEX `idx_url_hash`(`url_hash`) USING BTREE COMMENT '唯一索引：防止重复记录',
  INDEX `idx_is_type`(`is_type`) USING BTREE COMMENT '网盘类型索引',
  INDEX `idx_expire_time`(`expire_time`) USING BTREE COMMENT '过期时间索引（用于定期清理）',
  INDEX `idx_create_time`(`create_time`) USING BTREE COMMENT '创建时间索引'
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '无效资源缓存表（仅记录检测失败的资源）' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for qf_source_category
-- ----------------------------
DROP TABLE IF EXISTS `qf_source_category`;
CREATE TABLE `qf_source_category`  (
  `source_category_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '分类名称',
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `status` int(11) NOT NULL DEFAULT 0 COMMENT '状态',
  `is_sys` int(11) NOT NULL DEFAULT 0 COMMENT '1时不能删除',
  `is_update` int(11) NOT NULL DEFAULT 1 COMMENT '0不更新 1更新',
  `is_type` int(11) NOT NULL DEFAULT 0 COMMENT '0网络, 1本地',
  `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NOT NULL DEFAULT 0 COMMENT '修改时间',
  PRIMARY KEY (`source_category_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 6 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '文章分类表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of qf_source_category
-- ----------------------------
INSERT INTO `qf_source_category` VALUES (1, '短剧', '', 999, 0, 1, 1, 0, 1725114376, 1726299215);
INSERT INTO `qf_source_category` VALUES (2, '电影', '', 998, 0, 1, 1, 0, 1725114387, 1726303157);
INSERT INTO `qf_source_category` VALUES (3, '电视剧', '', 997, 0, 1, 1, 0, 1725114393, 1726303158);
INSERT INTO `qf_source_category` VALUES (4, '动漫', '', 996, 0, 1, 1, 0, 1725114400, 1726303159);
INSERT INTO `qf_source_category` VALUES (5, '综艺', '', 995, 0, 1, 1, 0, 1725114408, 1726303160);

-- ----------------------------
-- Table structure for qf_source_log
-- ----------------------------
DROP TABLE IF EXISTS `qf_source_log`;
CREATE TABLE `qf_source_log`  (
  `source_log_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '转存任务名称',
  `total_num` int(11) NOT NULL DEFAULT 0 COMMENT '转存总数',
  `new_num` int(11) NOT NULL DEFAULT 0 COMMENT '新增数',
  `update_num` int(11) NOT NULL DEFAULT 0 COMMENT '更新数(更新资源地址)',
  `skip_num` int(11) NOT NULL DEFAULT 0 COMMENT '重复跳过数',
  `fail_num` int(11) NOT NULL DEFAULT 0 COMMENT '失败数',
  `fail_dec` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL COMMENT '失败原因',
  `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NOT NULL DEFAULT 0 COMMENT '修改时间',
  `end_time` int(11) NOT NULL DEFAULT 0 COMMENT '结束时间',
  PRIMARY KEY (`source_log_id`) USING BTREE
) ENGINE = MyISAM AUTO_INCREMENT = 1 CHARACTER SET = utf8 COLLATE = utf8_general_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for qf_diagnostic_log
-- ----------------------------
DROP TABLE IF EXISTS `qf_diagnostic_log`;
CREATE TABLE `qf_diagnostic_log` (
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
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '搜索转存目录树诊断日志' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for qf_token
-- ----------------------------
DROP TABLE IF EXISTS `qf_token`;
CREATE TABLE `qf_token`  (
  `token_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL DEFAULT 0 COMMENT '用户ID',
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT 'AccessToken',
  `token_expires` int(11) NOT NULL DEFAULT 0 COMMENT '授权码过期时间',
  `platform` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'all' COMMENT '来源终端',
  `ip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '登录IP',
  `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '登录时间',
  PRIMARY KEY (`token_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '授权信息表' ROW_FORMAT = Dynamic;

DROP TABLE IF EXISTS `qf_api_list`;
CREATE TABLE `qf_api_list` (
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
-- Table structure for qf_user
-- ----------------------------
DROP TABLE IF EXISTS `qf_user`;
CREATE TABLE `qf_user`  (
  `user_id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'UID',
  `openid` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '微信openid',
  `nickname` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '用户昵称',
  `head_pic` varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT '' COMMENT '头像',
  `sex` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=保密 1=男 2=女',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0=禁用 1=启用',
  `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NOT NULL DEFAULT 0 COMMENT '修改时间',
  PRIMARY KEY (`user_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_general_ci COMMENT = '微信用户表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for qf_search_cache
-- ----------------------------
DROP TABLE IF EXISTS `qf_search_cache`;
CREATE TABLE `qf_search_cache` (
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
-- Records of qf_conf (公告弹窗配置项)
-- ----------------------------
INSERT INTO `qf_conf` (`conf_id`, `conf_key`, `conf_value`, `conf_title`, `conf_desc`, `conf_status`, `conf_type`, `conf_spec`, `conf_content`, `conf_sort`, `conf_system`, `conf_createtime`, `conf_updatetime`) 
VALUES 
(NULL, 'announce_status', '1', '公告弹窗开关', '是否启用首页公告弹窗', '1', '3', '2', '开启=>1\n关闭=>0', '100', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(NULL, 'announce_title', '欢迎使用本站', '公告标题', '弹窗顶部显示的标题', '1', '3', '0', '', '99', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(NULL, 'announce_content', '<p>欢迎使用本资源搜索平台！</p><p>我们致力于为您提供最优质的资源搜索服务。</p><p>如有任何问题，请随时联系我们。</p>', '公告内容', '支持HTML格式，如：<p>文字</p>、<br>、<strong>等', '1', '3', '1', '', '98', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(NULL, 'announce_type', '2', '显示类型', '控制公告的显示逻辑', '1', '3', '2', '每次显示=>1\n内容变化时显示=>2\n近期不再显示=>3', '97', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(NULL, 'announce_interval_days', '7', '间隔天数', '仅"近期不再显示"模式有效，用户点击"近期不再显示"后的隐藏天数', '1', '3', '0', '', '96', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(NULL, 'other_pan_check_enable', '0', '其他网盘检测开关', '开启后将对百度、UC、迅雷等非夸克网盘进行有效性检测', '1', '1', '2', '开启=>1\n关闭=>0', '0', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(NULL, 'other_pan_check_api', '', '网盘检测API地址', '检测API地址，选择PanCheck时必填，例如：http://your-domain.com/api/v1/links/check', '1', '1', '0', '', '0', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(NULL, 'other_pan_check_mode', 'local', '检测API类型', '选择检测接口的类型', '1', '1', '2', '本地接口=>local\nPanCheck接口=>pancheck', '0', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(NULL, 'diagnostic_log_enable', '0', '诊断日志记录开关', '仅调试搜索、转存、目录树问题时开启，关闭后不再写入诊断日志', '0', '3', '2', '关闭=>0\n开启=>1', '0', '0', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

SET FOREIGN_KEY_CHECKS = 1;
