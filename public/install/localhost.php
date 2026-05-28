<?php
//检测是否已经安装
$step = isset($_GET['step']) ? (string) $_GET['step'] : '1';
$isInstallCompletePage = $step === '5' && !empty($_SESSION['INSTALLOK']);

if(file_exists(__DIR__ . '/install.lock') && !$isInstallCompletePage){
	exit(get_tip_html($config['alreadyInstallInfo']));
}

// filewrite函数已在index.php中定义，此处移除重复定义
