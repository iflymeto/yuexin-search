<?php require __DIR__ . '/header.php';?>
	<div class="section">
		<div class="main install">
			<ul id="loginner"></ul>
		</div>
	</div>
	<div class="btn-box">
		<a href="javascript:;" class="btn_old" id="installloading">
			<img src="templates/images/loading.gif" align="absmiddle">&nbsp;正在安装...
		</a>
	</div>
	<script src="./templates/js/jquery.js"></script> 
	<script type="text/javascript">
		var n=0;
		var postData = <?php echo json_encode($_POST);?>; // 重命名避免变量冲突
		$.ajaxSetup ({ cache: false });
		function reloads(n) {
			var url =	"./index.php?step=4&install=1&n="+n;
			$.ajax({
				type: "POST",		
				url: url,
				data: postData, // 使用重命名后的变量
				dataType: 'json',
				success: function(response){ // 重命名响应参数避免冲突
					$('#loginner').append(response.info);
					if(response.status == 1){
						reloads(response.type);
					}
					if(response.status == 0){
						$('#installloading').removeClass('btn_old').addClass('btn').html('继续安装').unbind('click').click(function(){
							reloads(0);
						});
						alert('安装已停止！');
					}

					// 安装完成
					if(response.status == 2 && response.type == 'install_complete'){
						$('#installloading').removeClass('btn_old').addClass('btn').attr('href','./index.php?step=5').html('安装完成');
						$('#loginner').append('<li><em>安装完成！正在跳转到完成页面...</em></li>');
						setTimeout(function(){
							window.location.href='./index.php?step=5';
						},2000); // 缩短跳转时间到2秒
					}
				}
			});
		}
		$(function(){
			reloads(n);
		})
	</script> 
<?php require __DIR__ . '/footer.php';?>