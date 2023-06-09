<?php
$is_defend=true;
include("../includes/common.php");
if(!$conf['test_open'])sysmsg("未开启测试支付");
if(isset($_GET['ok']) && isset($_GET['trade_no'])){
	$trade_no=daddslashes($_GET['trade_no']);
	$row=$DB->getRow("SELECT * FROM pre_order WHERE trade_no='{$trade_no}' AND uid='{$conf['test_pay_uid']}' limit 1");
	if(!$row)sysmsg('订单号不存在');
	if($row['status']!=1)sysmsg('订单未完成支付');
	$money = $row['money'];
}else{
	$trade_no=date("YmdHis").rand(111,999);
	$gid = $DB->getColumn("SELECT gid FROM pre_user WHERE uid='{$conf['test_pay_uid']}' limit 1");
	$paytype = \lib\Channel::getTypes($gid);
	$csrf_token = md5(mt_rand(0,999).time());
	$_SESSION['csrf_token'] = $csrf_token;
	$money = 1;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<body>
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
	<title><?php echo $conf['sitename']?> - 测试支付</title>
    <link href="<?php echo $cdnpublic?>twitter-bootstrap/3.4.1/css/bootstrap.min.css" rel="stylesheet"/>
</head>
<div class="container">
<div class="col-xs-12 col-sm-10 col-lg-8 center-block" style="float: none;">
<div class="page-header">
  <h4><?php echo $conf['sitename']?> - 测试支付<a href="/" class="pull-right"><small>返回首页</small></a></h4>
</div>
<div class="panel panel-primary">
<div class="panel-body">

<form name="alipayment">
<input type="hidden" name="csrf_token" value="<?php echo $csrf_token?>">
<div class="input-group">
<span class="input-group-addon"><span class="glyphicon glyphicon-barcode"></span></span>
<input class="form-control" placeholder="商户订单号" value="<?php echo $trade_no?>" name="trade_no" type="text" disabled="">
</div>
<br>
<div class="input-group">
<span class="input-group-addon"><span class="glyphicon glyphicon-shopping-cart"></span></span>
<input class="form-control" placeholder="商品名称" value="支付测试" name="name" type="text" disabled="" >
</div>
<br>
<div class="input-group">
<span class="input-group-addon"><span class="glyphicon glyphicon-yen"></span></span>
<input class="form-control" placeholder="付款金额" value="<?php echo $money?>" name="money" type="text" <?php echo isset($_GET['ok'])?'disabled=""':'required=""'?>>	        
</div>        			
<br>
<center>
<?php if(isset($_GET['ok'])){?>
<div class="alert alert-success"><i class="glyphicon glyphicon-ok-circle"></i>&nbsp;订单已支付成功！</div>
<?php }else{?>
<div class="btn-group btn-group-justified" role="group" aria-label="...">
<?php foreach($paytype as $rows){?>
<div class="btn-group" role="group">
  <button type="button" name="type" value="<?php echo $rows['id']?>" class="btn btn-default" onclick="submitPay(this)"><img src="/assets/icon/<?php echo $rows['name']?>.ico" height="18">&nbsp;<?php echo $rows['showname']?></button>
</div>
<?php }?>
</div>
<?php }?>
</center>
</form>
</div>
<div class="panel-footer text-center">
 注意:本功能仅供打算接入的网站测试支付功能，如果你不打算接入请勿轻易测试。支付后如需退款 <a href="https://t.me/oldcoolcook" target="_blank" class="btn btn-danger btn-rounded"><i class="fa fa-user-plus"></i>&nbsp;请点击联系</a>
</div>
<div class="panel-footer text-center">
<?php echo $conf['sitename']?> © <?php echo date("Y")?> All Rights Reserved.
</div>
</div>
</div>
</div>
<script src="<?php echo $cdnpublic?>jquery/3.4.1/jquery.min.js"></script>
<script src="<?php echo $cdnpublic?>twitter-bootstrap/3.4.1/js/bootstrap.min.js"></script>
<script src="<?php echo $cdnpublic?>layer/3.1.1/layer.min.js"></script>
<script>
function submitPay(obj){
	var csrf_token=$("input[name='csrf_token']").val();
	var money=$("input[name='money']").val();
	var typeid=$(obj).val();
	if(money==''){
		layer.alert("金额不能为空");
		return false;
	}
	var ii = layer.load();
	$.ajax({
		type: "POST",
		dataType: "json",
		data: {money:money, typeid:typeid, csrf_token:csrf_token},
		url: "ajax.php?act=testpay",
		success: function (data, textStatus) {
			layer.close(ii);
			if (data.code == 0) {
				window.location.href=data.url;
			}else{
				layer.alert(data.msg, {icon: 2});
			}
		},
		error: function (data) {
			layer.msg('服务器错误', {icon: 2});
		}
	});
	return false;
}
</script>
</body>