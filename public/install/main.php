<?php
// 调试信息：检查POST数据
if(empty($_POST['manager']) || empty($_POST['manager_pwd'])) {
	return array('status'=>0,'info'=>'管理员账号或密码不能为空！请检查表单数据。');
}

$username = trim($_POST['manager']);
$password = trim($_POST['manager_pwd']);
//网站名称
$site_name = addslashes(trim($_POST['sitename']));

// 调试信息：检查数据库连接
if(!isset($mysqli) || $mysqli->connect_error) {
	return array('status'=>0,'info'=>'数据库连接不可用：' . (isset($mysqli) ? $mysqli->connect_error : '数据库连接对象不存在'));
}

//更新配置信息
if(!$mysqli->query("UPDATE `{$dbPrefix}conf` SET  `conf_value` = '$site_name' WHERE conf_key='app_name'")){
	return array('status'=>0,'info'=>'更新网站名称配置失败：' . $mysqli->error);
}

if(INSTALLTYPE == 'HOST'){
	        $db_str=<<<php
APP_DEBUG = true
SYSTEM_SALT= {$site_name}

[APP]
DEFAULT_TIMEZONE = Asia/Chongqing

[DATABASE]
TYPE = mysql
HOSTNAME = {$dbHost}
DATABASE = {$dbName}
USERNAME = {$dbUser}
PASSWORD = {$dbPwd}
HOSTPORT = {$dbPort}
CHARSET = utf8mb4
DEBUG = false
PREFIX = {$dbPrefix}

[LANG]
default_lang = zh-cn
php;
        // 创建数据库链接配置文件
        if(file_put_contents('../../.env', $db_str) === false){
        	return array('status'=>0,'info'=>'创建配置文件失败，请检查目录权限');
        }
}

//插入管理员
//生成随机认证码
$salt = genRandomString(4);
$time = time();
$ip = get_client_ip();
$password = sha1($password . $salt . $password . $salt);
$url = "INSERT INTO `{$dbPrefix}admin` (`admin_account`, `admin_password`, `admin_salt`, `admin_name`, `admin_idcard`, `admin_truename`, `admin_email`, `admin_money`, `admin_group`, `admin_ipreg`, `admin_status`, `admin_createtime`, `admin_updatetime`) VALUES ('{$username}', '{$password}', '{$salt}', '超级管理员', '', '超级管理员', '', 0.00, 1, '{$ip}', 0, '{$time}', '{$time}')";

// 调试信息已移除，避免影响返回值处理

if(!$mysqli->query($url)){
	return array('status'=>0,'info'=>'创建管理员账户失败：' . $mysqli->error . '<br/>SQL语句：' . htmlspecialchars($url));
}

// 验证插入是否成功
$check_result = $mysqli->query("SELECT COUNT(*) as count FROM `{$dbPrefix}admin` WHERE admin_account='{$username}'");
if($check_result) {
	$row = $check_result->fetch_assoc();
	if($row['count'] == 0) {
		return array('status'=>0,'info'=>'管理员账户插入失败：数据未成功写入数据库');
	}
}

$mysqli->close();
return array('status'=>2,'info'=>'成功添加管理员<br />成功写入配置文件<br>安装完成...');
