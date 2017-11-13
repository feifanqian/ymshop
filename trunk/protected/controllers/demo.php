<?php
/*
	调试接口的demo的文件
 */
header("Content-type: text/html; charset=utf-8");
date_default_timezone_set('PRC'); //设置默认时区
require_once 'Pay_tonglian.php';


// 创建会员接口
	public function index(){
		$pay = new Pay_tonglian();
		$result = $pay->actionCreateMember();
	   return json_encode($result,true) ;
	}
	


