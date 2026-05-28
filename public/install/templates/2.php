<?php require __DIR__ . '/header.php';?>
	<div class="section">
		<div class="main">
			<pre class="pact">
				<table width="100%">
					<tr>
						<th class="td1" width="25%">环境检测</th>
						<th class="td1" width="25%">推荐配置</th>
						<th class="td1" width="25%">当前状态</th>
						<th class="td1" width="25%">最低要求</th>
					</tr>
					<tr>
						<td>操作系统</td>
						<td>Linux</td>
						<td><span class="correct_span">&radic;</span> <?php echo $server['os'];?></td>
						<td>不限制</td>
					</tr>
					<tr>
						<td>PHP版本</td>
						<td>>5.4.x</td>
						<td><?php echo $server['php'];?></td>
						<td>5.3.x</td>
					</tr>
					<tr>
						<td>附件上传</td>
						<td>>2M</td>
						<td><?php echo $server['uploadSize'];?></td>
						<td>不限制</td>
					</tr>
					<tr>
						<td>session</td>
						<td>开启</td>
						<td><?php echo $server['session'];?></td>
						<td>开启</td>
					</tr>
				</table>
				<table width="100%">
					<tr>
						<th class="td1">目录、文件权限检查</th>
						<th class="td1" width="25%">写入</th>
						<th class="td1" width="25%">读取</th>
					</tr>
<?php
foreach($folder as $dir){
	$Testdir = $site_path.$dir;
	if(!is_dir($Testdir)){
		if(!file_exists($Testdir)){
			//不存在
			//尝试创建
			if(!dir_create($Testdir)){
				$w = '<span class="correct_span error_span">&radic;</span>不存在请创建';
				$r = '<span class="correct_span error_span">&radic;</span>不存在请创建';
				$error++;
			}else{
				if(testwrite($Testdir)){
					$w = '<span class="correct_span">&radic;</span>可写';
				}else{
					$w = '<span class="correct_span error_span">&radic;</span>不可写';
					$error++;
				}
				if(is_readable($Testdir)){
					$r = '<span class="correct_span">&radic;</span>可读';
				}else{
					$r = '<span class="correct_span error_span">&radic;</span>不可读';
					$error++;
				}
			}
		}else{
			if(is_writable($Testdir)){
				$w = '<span class="correct_span">&radic;</span>可写';
			}else{
				$w = '<span class="correct_span error_span">&radic;</span>不可写';
				$error++;
			}
			if(is_readable($Testdir)){
				$r = '<span class="correct_span">&radic;</span>可读';
			}else{
				$r = '<span class="correct_span error_span">&radic;</span>不可读';
				$error++;
			}
		}
	}else{
		if(testwrite($Testdir)){
			$w = '<span class="correct_span">&radic;</span>可写';
		}else{
			$w = '<span class="correct_span error_span">&radic;</span>不可写';
			$error++;
		}
		if(is_readable($Testdir)){
			$r = '<span class="correct_span">&radic;</span>可读';
		}else{
			$r = '<span class="correct_span error_span">&radic;</span>不可读';
			$error++;
		}
	}
?>
					<tr>
						<td><?php echo $dir;?></td>
						<td><?php echo $w;?></td>
						<td><?php echo $r;?></td>
					</tr>
<?php
}
?>
				</table>
			</pre>
		</div>
	</div>
	<div class="btn-box">
		<a href="./index.php?step=1" class="btn btn-error">上一步</a>
		<a href="./index.php?step=3" class="btn">下一步</a>
	</div>
<?php require __DIR__ . '/footer.php';?>