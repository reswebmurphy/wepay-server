<?php
include('config.php');
include('packages.php');
session_start();

if (!isset($_POST['invoiceid']) && !isset($_SESSION['invoiceid'])) {
	echo "error!";
	exit;
}
else {
	$pay = new pay();
	$msg = $pay->Control($config);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
	<link rel="stylesheet" href="https://cdn.bootcss.com/bootstrap/3.3.7/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
	<link rel="stylesheet" href="style.css" type="text/css">
	<title>安全支付</title>
</head>
<body>
	<div class="container pay">
	<p><img class="logo" src="WePayLogo.png"></p>
	<hr>
	<div class="row">
	  <div class="col-md-6 left">
	  	<p>请在15分钟内付款，超时订单将取消。</p>
		<p>交易编号：<b><?php echo $msg['invoiceid'] ?></b></p>
		<p>应付金额：<b>￥<?php echo $msg['amount'] ?></b></p>
		<p>请打开手机微信的“扫一扫”，扫描右侧二维码进行支付</p>
	  </div>
	  <div class="col-md-6 right">
		<p><img class="qrcode" src="<?php echo 'images/'.$msg['qrdir'] ?>"></p>
		<p><a class="btn btn-success" href="#">付好了</a></p>
		<p><?php echo $msg['notify']; ?></p>
		<p>支付遇到问题联系客服微信：</p>
	  </div>
	</div>
	
	</div>

	  <div class="container footer">
	    <div class="copyright">@2017 Powered by 微信安全支付</p>
	  </div>


</body>
</html>