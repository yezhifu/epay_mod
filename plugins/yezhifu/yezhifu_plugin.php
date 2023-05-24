<?php

class yezhifu_plugin
{
	static public $info = [
		'name'        => 'yezhifu', //支付插件英文名称，需和目录名称一致，不能有重复
		'showname'    => '也支付插件', //支付插件显示名称
		'author'      => '也支付', //支付插件作者
		'link'        => 'https://www.yezhifu.com/', //支付插件作者链接
		'types'       => ['alipay','wxpay'], //支付插件支持的支付方式，可选的有alipay,qqpay,wxpay,bank
		'inputs' => [ //支付插件要求传入的参数以及参数显示名称，可选的有appid,appkey,appsecret,appurl,appmchid
			'appurl' => [
				'name' => '网关地址',
				'type' => 'input',
				'note' => '填写完整的网关地址( 结尾不含 / )',
			],
			'appid' => [
				'name' => '应用APPID',
				'type' => 'input',
				'note' => '',
			],
			'appkey' => [
				'name' => '应用密钥',
				'type' => 'input',
				'note' => '',
			],
			'appswitch' => [
				'name' => '微信是否支持H5',
				'type' => 'select',
				'options' => [0=>'否',1=>'是'],
			],
		],
		'select' => null,
		'note' => '', //支付密钥填写说明
		'bindwxmp' => false, //是否支持绑定微信公众号
		'bindwxa' => false, //是否支持绑定微信小程序
	];

	static private function make_sign($param, $key)
	{
		ksort($param);
		$signstr = '';
		foreach($param as $k => $v){
			if($k=='sign' || $v=='')continue;
			$signstr .= $k.'='.$v.'&';
		}
		$signstr = substr($signstr,0,-1);
		$sign = md5($signstr.$key);
		return $sign;
	}

	static private function addOrder($channel_type){
		global $channel, $order, $ordername, $conf, $clientip;

		session_start();

		$apiurl = $channel['appurl'];

		if($channel_type == 'ALIPAY'){
		    $type = 'alipay';
		}else{
		    $type = 'wxpay';
		}
		
		$param = [
		    'appid' => $channel['appid'],
		    'type' => $type,
		    'channel_type' => $channel_type,
		    'price' => $order['realmoney'],
		    'name' => $order['name'],
		    'body' => $ordername,
		    'notify_url' => $conf['localurl'].'pay/notify/'.TRADE_NO.'/',
		    'out_trade_no' => TRADE_NO,
		    'ip' => $clientip,
		    'server' => $_SERVER['HTTP_HOST']
		];

		$param['sign'] = self::make_sign($param, $channel['appkey']);

		if($_SESSION[TRADE_NO.'_pay']){
			$data = $_SESSION[TRADE_NO.'_pay'];
		}else{
			$data = get_curl($apiurl.'/api/gateway/request', http_build_query($param));
			$_SESSION[TRADE_NO.'_pay'] = $data;
		}
		$result = json_decode($data, true);

		if($result["state"]=='succeeded'){
			$api_trade_no = $result['return_data']['system_order'];
			\lib\Payment::updateOrder(TRADE_NO, $api_trade_no);
			return $result['return_data'];
		}else{
			throw new Exception($result["message"]?$result["message"]:'接口请求失败');
		}
	}

	static public function submit(){
		global $siteurl, $channel, $order, $sitename;

		if($order['typename']=='alipay'){
			return ['type'=>'jump','url'=>'/pay/alipay/'.TRADE_NO.'/'];
		}elseif($order['typename']=='wxpay'){
            if (checkmobile()==true && $channel['appswitch'] == 1) {
				return ['type'=>'jump','url'=>'/pay/wxh5pay/'.TRADE_NO.'/'];
            }else{
				return ['type'=>'jump','url'=>'/pay/wxpay/'.TRADE_NO.'/'];
			}
		}
	}

	static public function mapi(){
		global $siteurl, $channel, $order, $device, $mdevice;

		if($order['typename']=='alipay'){
			self::alipay();
		}elseif($order['typename']=='wxpay'){
            if ($device == 'mobile' && $channel['appswitch'] == 1) {
				self::wxh5pay();
            }else{
				self::wxpay();
			}
		}
	}

	//支付宝扫码支付
	static public function alipay(){
		try{
			$arr = self::addOrder('ALIPAY');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'支付宝支付下单失败！'.$ex->getMessage()];
		}

		return ['type'=>'qrcode','page'=>'alipay_qrcode','url'=>$arr['url']];
	}

	//微信扫码支付
	static public function wxpay(){
		try{
			$arr = self::addOrder('WECHAT_MP');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
		}

		if(strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')!==false){
			return ['type'=>'jump','url'=>$arr['url']];
		} elseif (checkmobile()==true) {
			return ['type'=>'qrcode','page'=>'wxpay_wap','url'=>$arr['url']];
		} else {
			return ['type'=>'qrcode','page'=>'wxpay_qrcode','url'=>$arr['url']];
		}
	}

	//微信H5支付（小程序）
	static public function wxh5pay(){
		try{
			$arr = self::addOrder('WECHAT_H5');
		}catch(Exception $ex){
			return ['type'=>'error','msg'=>'微信支付下单失败！'.$ex->getMessage()];
		}

		return ['type'=>'scheme','page'=>'wxpay_mini','url'=>$arr['h5']];
	}

	//异步回调
	static public function notify(){
		global $channel, $order;

		if(isset($_POST['out_trade_no'])){
			$data = $_POST;
		}else{
			$data = $_GET;
		}
		//file_put_contents('logs.txt',http_build_query($data));

		$sign = self::make_sign($data, $channel['appkey']);

		if($sign===$data['sign']){
			$out_trade_no = daddslashes($data['out_trade_no']);
			$trade_no = daddslashes($data['trade_no']);

			if ($out_trade_no == TRADE_NO) {
				processNotify($order, $trade_no);
			}
			return ['type'=>'html','data'=>'SUCCESS'];
		}else{
			return ['type'=>'html','data'=>'sign fail'];
		}
	}

	//支付返回页面
	static public function return(){
		return ['type'=>'page','page'=>'return'];
	}

	//退款
	static public function refund($order){
		global $channel;
		if(empty($order))exit();

		$apiurl = $channel['appurl'];
		
		$param = [
		    'system_order' => $order['api_trade_no'],
		    'appid' => $channel['appid']
		];

		$param['sign'] = self::make_sign($param, $channel['appkey']);

		$data = get_curl($apiurl.'/api/gateway/refund.html', http_build_query($param));
		$result = json_decode($data, true);

		if($result["state"]=='succeeded'){
			$result = ['code'=>0, 'trade_no'=>$order['api_trade_no'], 'refund_fee'=>$order['realmoney']];
		}else{
			$result = ['code'=>-1, 'msg'=>$result["message"]];
		}
		return $result;
	}
}