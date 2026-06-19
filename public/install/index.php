<?php
header("Content-type: text/html; charset=utf-8");
//开启session
session_start();
//配置信息
$config = include __DIR__ . '/config.php';
if(empty($config)){
	exit(get_tip_html('安装配置信息不存在，无法继续安装！'));
}
//安装环境验证，获取相应判断信息
define('INSTALLTYPE', 'HOST');
//本地
require __DIR__ . '/localhost.php';

//限制最大的执行时间
set_time_limit(1000);
//php版本
$phpversion = phpversion();
//数据库文件
if(!file_exists(__DIR__ . '/'.$config['sqlFileName'])){
	exit(get_tip_html('数据库文件不存在，无法继续安装！'));
}
//写入数据库完成后处理的文件
if (!file_exists(__DIR__ . '/'.$config['handleFile'])) {
	exit(get_tip_html('处理文件不存在，无法继续安装！'));
}
//设置报错级别并返回当前级别。
error_reporting(E_ALL & ~E_NOTICE);

function install_create_mysqli($dbHost, $dbUser, $dbPwd)
{
	$mysqli = @new mysqli($dbHost, $dbUser, $dbPwd);
	return $mysqli;
}

function install_format_db_connect_error($mysqli)
{
	$error = '';
	if ($mysqli instanceof mysqli) {
		$error = $mysqli->connect_error;
	}
	if ($error === '') {
		$error = mysqli_connect_error();
	}
	if ($error === '') {
		$error = '未知错误';
	}

	if (
		stripos($error, 'caching_sha2_password') !== false
		|| stripos($error, 'authentication method unknown') !== false
		|| stripos($error, 'requested authentication method unknown') !== false
	) {
		return '数据库链接失败！当前数据库用户使用 caching_sha2_password 认证方式，但当前 PHP mysqli/mysqlnd 不支持。请将该数据库用户认证方式改为 mysql_native_password，或升级 PHP 的 mysqli/mysqlnd 后重试。原始错误信息：' . $error;
	}

	return '数据库链接失败！错误信息：' . $error;
}

//安装步骤
$steps = array(
	'1' => '安装许可协议',
	'2' => '运行环境检测',
	'3' => '安装参数设置',
	'4' => '安装详细过程',
	'5' => '安装完成',
);
$step = isset($_GET['step']) ? $_GET['step'] : 1;
//当前安装步骤
$step_html = '';
foreach ($steps as $key => $value) {
	$current = $key == $step? 'current':'';
	$step_html .= '<li class="'.$current.'"><em>'.$key.'</em>'.$value.'</li>';
}
//安装页面
switch ($step) {
	//安装许可协议
	case '1':
		$license = file_get_contents(__DIR__ . '/license.txt');
		include (__DIR__ . "/templates/1.php");
		break;
	//运行环境检测	
	case '2':
		$server = array(
			//操作系统
			'os' => php_uname(),
			//PHP版本
			'php' => $phpversion,
		);
		$error = 0;
		//php版本
		if ($phpversion>=$config['php']) {
			$server['php'] = '<span class="correct_span">&radic;</span> 支持';
		} else {
			$server['php'] = '<span class="correct_span error_span">&radic;</span> '.$phpversion;
			$error++;
		}
		//上传限制
		if (ini_get('file_uploads')) {
			$server['uploadSize'] = '<span class="correct_span">&radic;</span> ' . ini_get('upload_max_filesize');
		} else {
			$server['uploadSize'] = '<span class="correct_span error_span">&radic;</span>禁止上传';
		}
		//session
		if (function_exists('session_start')) {
			$server['session'] = '<span class="correct_span">&radic;</span> 支持';
		} else {
			$server['session'] = '<span class="correct_span error_span">&radic;</span> 不支持';
			$error++;
		}


		//需要读写权限的目录
		$folder = $config['dirAccess'];
		$install_path = str_replace('\\','/',getcwd()).'/';
		$site_path = str_replace('Install/', '', $install_path);
		include (__DIR__ . "/templates/2.php");
		$_SESSION['INSTALLSTATUS'] = $error == 0?'SUCCESS':$error;
		break;
	//安装参数设置
	case '3':
		verify(3);
		//测试数据库链接
		if (isset($_GET['testdbpwd'])) {
			empty($_POST['dbhost'])?alert(0,'数据库服务器地址不能为空！','dbhost'):'';
			empty($_POST['dbuser'])?alert(0,'数据库用户名不能为空！','dbuser'):'';
			empty($_POST['dbname'])?alert(0,'数据库名不能为空！','dbname'):'';
			empty($_POST['dbport'])?alert(0,'数据库端口不能为空！','dbport'):'';
			$dbHost = $_POST['dbhost'] . ':' . $_POST['dbport'];
			$mysqli = install_create_mysqli($dbHost,  $_POST['dbuser'], $_POST['dbpw']);
			// 改进错误检查机制
			if($mysqli->connect_error)  {
				alert(0, install_format_db_connect_error($mysqli), 'dbpw');
			}else{
				// 测试数据库版本
				if ($mysqli->server_info < 5.0) {
					alert(0,'MySQL版本过低，请升级到5.0以上！当前版本：' . $mysqli->server_info,'dbpw');
				}
				alert(1,'数据库链接成功！MySQL版本：' . $mysqli->server_info,'dbpw');
			}
			$mysqli->close();
		}
		//域名+路径
		$domain = empty($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];
		if ((int) $_SERVER['SERVER_PORT'] != 80) {
			$domain .= ":" . $_SERVER['SERVER_PORT'];
		}
		$scriptName = !empty($_SERVER["REQUEST_URI"]) ? $scriptName = $_SERVER["REQUEST_URI"] : $scriptName = $_SERVER["PHP_SELF"];
		$rootpath = preg_replace("/\/(I|i)nstall\/index\.php(.*)$/", "", $scriptName);
		$domain = $domain . $rootpath;
		include ("./templates/3.php");
		break;
	//安装详细过程
	case '4':
		if (!isset($_GET['install'])){
			switch (INSTALLTYPE){
				case 'SAE':
					// 服务器地址
					$_POST['dbhost'] = SAE_MYSQL_HOST_M;
					// 端口
					$_POST['dbport'] = SAE_MYSQL_PORT;
					// 数据库名
					$_POST['dbname'] = SAE_MYSQL_DB;
					// 用户名
					$_POST['dbuser'] = SAE_MYSQL_USER;
					// 密码
					$_POST['dbpw'] = SAE_MYSQL_PASS;
					break;
				case 'BAE':
					// 服务器地址
					$_POST['dbhost'] = HTTP_BAE_ENV_ADDR_SQL_IP;
					// 端口
					$_POST['dbport'] = HTTP_BAE_ENV_ADDR_SQL_PORT;
					// 用户名
					$_POST['dbuser'] = HTTP_BAE_ENV_SK;
					// 密码
					$_POST['dbpw'] = SAE_MYSQL_PASS;
					break;
			}
		}
		verify(4);
		if (intval($_GET['install'])) {
			dataVerify();
			//关闭特殊字符提交处理到数据库
			//设置时区
			date_default_timezone_set('PRC');
			
			// 设置脚本执行时间和内存限制，防止安装过程超时
			set_time_limit(300); // 5分钟超时
			ini_set('memory_limit', '256M'); // 增加内存限制
			
			// 输出缓冲区设置，确保实时输出
			ob_implicit_flush(true);
			ob_end_flush();
			//当前进行的数据库操作
			$n = intval($_GET['n']);
			$arr = array();
			//数据库服务器地址
			$dbHost = trim($_POST['dbhost']);
			//数据库端口
			$dbPort = trim($_POST['dbport']);
			//数据库名
			$dbName = trim($_POST['dbname']);
			$dbHost = empty($dbPort) || $dbPort == 3306 ? $dbHost : $dbHost . ':' . $dbPort;
			//数据库用户名
			$dbUser = trim($_POST['dbuser']);
			//数据库密码
			$dbPwd = trim($_POST['dbpw']);
			//表前缀
			$dbPrefix = empty($_POST['dbprefix']) ? 'db_' : trim($_POST['dbprefix']);
			//链接数据库
			$mysqli = install_create_mysqli($dbHost, $dbUser, $dbPwd);
			//导入政采商品
			$sitegoods = trim($_POST['sitegoods']);
			// 改进数据库连接错误检查
			if ($mysqli->connect_error) {
				alert(0, install_format_db_connect_error($mysqli));
			}
			
			// 设置字符集
			if(!$mysqli->query("SET NAMES 'utf8mb4'")){
				alert(0,'设置字符集失败！错误信息：' . $mysqli->error);
			}
			
			// 检查MySQL版本
			if ($mysqli->server_info < 5.0) {
				alert(0,'MySQL版本过低，请升级到5.0以上！当前版本：' . $mysqli->server_info);
			}
			
			// 创建数据库并选中
			if(!$mysqli->select_db($dbName)){
				$create_sql='CREATE DATABASE IF NOT EXISTS `'.$dbName.'` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;';
				if(!$mysqli->query($create_sql)){
					alert(0,'创建数据库失败！错误信息：' . $mysqli->error);
				}
				if(!$mysqli->select_db($dbName)){
					alert(0,'选择数据库失败！错误信息：' . $mysqli->error);
				}
			}
			
			// 导入sql数据并创建表
			$sqldata = file_get_contents('./'.$config['sqlFileName']);
			if(empty($sqldata)){
				alert(0,'数据库文件不能为空！');
			}
			
			// 使用原始的SQL分割方式，保持兼容性
			$sql_array = preg_split("/;[\r\n]+/", str_replace($config['dbPrefix'], $dbPrefix, $sqldata));
			$counts = count($sql_array);
			$created_tables = array(); // 记录创建的表
			
			for ($i = $n; $i < $counts; $i++) {
				$sql = trim($sql_array[$i]);
				if (strstr($sql, 'CREATE TABLE')) {
					preg_match('/CREATE TABLE `([^ ]*)`/', $sql, $matches);
				if($mysqli->query($sql_array[$i])){
					$created_tables[] = $matches[1];
				} else {
					// 如果是因为 ngram 不支持导致的错误，尝试移除 ngram 再次创建
					if (strpos($mysqli->error, 'ngram') !== false) {
						$newSql = str_replace(' WITH PARSER ngram', '', $sql_array[$i]);
						if ($mysqli->query($newSql)) {
							$created_tables[] = $matches[1] . ' (Fallback: No ngram)';
							continue;
						}
					}
					alert(0, '创建数据表' . $matches[1] . '失败！错误信息：' . $mysqli->error);
				}
			}else{
					if(!empty($sql)){
						$mysqli->query($sql);
					}
				}
			}
			
			// 所有表创建完成后，生成成功信息
			$info = '';
			foreach($created_tables as $table_name){
				$info .= '<li><span class="correct_span">&radic;</span>创建数据表' . $table_name . '，完成！<span style="float: right;">'.date('Y-m-d H:i:s').'</span></li> ';
			}
			
			//处理管理员账号创建和配置文件生成
			$data = include __DIR__ . '/'.$config['handleFile'];
			$_SESSION['INSTALLOK'] = $data['status']?1:0;
			
			// 合并所有安装信息
			if($data['status'] == 2) {
				$info .= '<li><span class="correct_span">&radic;</span>' . $data['info'] . '<span style="float: right;">'.date('Y-m-d H:i:s').'</span></li>';
				// 安装完成，返回特殊的type标识
				exit(json_encode(array('status'=>2,'info'=>$info,'type'=>'install_complete')));
			} else {
				$info .= '<li><span class="error_span">×</span>' . $data['info'] . '<span style="float: right;">'.date('Y-m-d H:i:s').'</span></li>';
				alert(0, $info);
			}
		}
		include (__DIR__ . "/templates/4.php");
		break;
	//安装完成
	case '5':
		verify(5);
		include (__DIR__ . "/templates/5.php");
		//安装完成,生成.lock文件
		if(isset($_SESSION['INSTALLOK']) && $_SESSION['INSTALLOK'] == 1){
			if(!filewrite(__DIR__ . '/install.lock')){
				// 锁文件创建失败，记录错误但不阻止安装完成
				echo '<script>console.error("警告：安装锁文件创建失败，请手动创建 install.lock 文件以防止重复安装");</script>';
			}
		}
		unset($_SESSION);
		break;
}	

/**
 * 错误提示html
 */
function get_tip_html($info){
	return '<div>'.$info.'</div>';
}
//返回提示信息
function alert($status,$info,$type = 0){
	exit(json_encode(array('status'=>$status,'info'=>$info,'type'=>$type)));
}
function verify($step = 3){
	if($step >= 3){
		//未运行环境检测，跳转到安装许可协议页面
		if(!isset($_SESSION['INSTALLSTATUS'])){
			header('location:./index.php');
			exit();
		}
		//运行环境检测存在错误，返回运行环境检测
		if($_SESSION['INSTALLSTATUS'] != 'SUCCESS'){
			header('location:./index.php?step=2');
			exit();
		}
	}
	if($step == 4){
		//未提交数据
		if(empty($_POST)){
			header('location:./index.php?step=3');
			exit();
		}
	}
	if($step >= 5){
		//数据库未写入完成
		if(!isset($_SESSION['INSTALLOK'])){
			header('location:./index.php?step=4');
			exit();
		}
	}
}
function dataVerify(){
	empty($_POST['dbhost'])?alert(0,'数据库服务器不能为空！'):'';
	empty($_POST['dbport'])?alert(0,'数据库端口不能为空！'):'';
	empty($_POST['dbuser'])?alert(0,'数据库用户名不能为空！'):'';
	empty($_POST['dbname'])?alert(0,'数据库名不能为空！'):'';
	empty($_POST['dbprefix'])?alert(0,'数据库表前缀不能为空！'):'';
	empty($_POST['manager'])?alert(0,'管理员帐号不能为空！'):'';
	empty($_POST['manager_pwd'])?alert(0,'管理员密码不能为空！'):'';
}
/**
 * 判断目录是否可写
 */
function testwrite($d) {
	$tfile = "_test.txt";
	$fp = fopen($d . "/" . $tfile, "w");
	if (!$fp) {
		return false;
	}
	fclose($fp);
	$rs = unlink($d . "/" . $tfile);
	if ($rs) {
		return true;
	}
	return false;
}
/**
 * 创建目录
 */
function dir_create($path, $mode = 0777) {
	if (is_dir($path)) {
		return TRUE;
	}
	mkdir($path, $mode, true);
	chmod($path, $mode);
}
/**
 * 数据库语句解析
 * @param $sql 数据库
 * @param $newTablePre 新的前缀
 * @param $oldTablePre 旧的前缀
 */
function sql_split($sql, $newTablePre, $oldTablePre) {
	//前缀替换
	if ($newTablePre != $oldTablePre){
		$sql = str_replace($oldTablePre, $newTablePre, $sql);
	}
	$sql = preg_replace("/TYPE=(InnoDB|MyISAM|MEMORY)( DEFAULT CHARSET=[^; ]+)?/", "ENGINE=\\1 DEFAULT CHARSET=utf8", $sql);

	// 统一换行符
	$sql = str_replace(array("\r\n", "\r"), "\n", $sql);
	
	// 按分号分割SQL语句
	$queriesarray = explode(";", $sql);
	$ret = array();
	
	foreach ($queriesarray as $query) {
		$query = trim($query);
		if (empty($query)) continue;
		
		// 移除注释行
		$lines = explode("\n", $query);
		$cleanQuery = '';
		foreach ($lines as $line) {
			$line = trim($line);
			if (empty($line)) continue;
			$firstChar = substr($line, 0, 1);
			if ($firstChar != '#' && $firstChar != '-' && substr($line, 0, 2) != '--') {
				$cleanQuery .= $line . ' ';
			}
		}
		
		$cleanQuery = trim($cleanQuery);
		if (!empty($cleanQuery)) {
			$ret[] = $cleanQuery;
		}
	}
	
	return $ret;
}
/**
 * 产生随机字符串
* 产生一个指定长度的随机字符串,并返回给用户
* @access public
* @param int $len 产生字符串的位数
* @return string
*/
function genRandomString($len = 6) {
	$chars = array(
			"a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k",
			"l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v",
			"w", "x", "y", "z", "A", "B", "C", "D", "E", "F", "G",
			"H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R",
			"S", "T", "U", "V", "W", "X", "Y", "Z", "0", "1", "2",
			"3", "4", "5", "6", "7", "8", "9", '!', '@', '#', '$',
			'%', '^', '&', '*', '(', ')'
	);
	$charsLen = count($chars) - 1;
	shuffle($chars);	// 将数组打乱
	$output = "";
	for ($i = 0; $i < $len; $i++) {
		$output .= $chars[mt_rand(0, $charsLen)];
	}
	return $output;
}
/**
 * 获取客户端IP地址
 * @param integer $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
 * @return mixed
 */
 function get_client_ip($type = 0) {
	$type	   =  $type ? 1 : 0;
	static $ip  =   NULL;
	if ($ip !== NULL) return $ip[$type];
	if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		$arr	=   explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
		$pos	=   array_search('unknown',$arr);
		if(false !== $pos) unset($arr[$pos]);
		$ip	 =   trim($arr[0]);
	}elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
		$ip	 =   $_SERVER['HTTP_CLIENT_IP'];
	}elseif (isset($_SERVER['REMOTE_ADDR'])) {
		$ip	 =   $_SERVER['REMOTE_ADDR'];
	}
	// IP地址合法验证
	$long = sprintf("%u",ip2long($ip));
	$ip   = $long ? array($ip, $long) : array('0.0.0.0', 0);
	return $ip[$type];
 }
/**
  * 写入文件
  */
 function filewrite($file, $content = '') {
 	$fp = fopen($file, 'w');
 	if ($fp) {
 		fwrite($fp, $content);
 		fclose($fp);
 		return true;
 	}
 	return false;
 }
 ?>
