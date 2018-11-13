<?php

/**
 * @class pay_alipaydirect
 * @brief 支付宝[即时到帐]插件类
 */
class pay_alipaydirect extends PaymentPlugin {

    //支付插件名称
    public $name = '支付宝';

    //提交地址
    public function submitUrl() {
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
            $this->method = "GET";
            return '/payment/pay_alipay_submit';
        } else {
            return 'https://mapi.alipay.com/gateway.do?_input_charset=utf-8';
        }
    }

    //取得配制参数
    public static function config() {
        return array(
            array('field' => 'partner_id', 'caption' => '合作身份者id', 'type' => 'string'),
            array('field' => 'partner_key', 'caption' => '安全检验码key', 'type' => 'string'),
        );
    }

    //同步处理
    public function callback($callbackData, &$paymentId, &$money, &$message, &$orderNo) {
        //除去待签名参数数组中的空值和签名参数
        $filter_param = $this->filterParam($callbackData);

        //对待签名参数数组排序
        $para_sort = $this->argSort($filter_param);

        //生成签名结果
        $payment = new Payment($paymentId);
        $payment_plugin = $payment->getPaymentPlugin();
        $classConfig = $payment_plugin->getClassConfig();

        $mysign = $this->buildSign($para_sort, $classConfig['partner_key']);

        if ($callbackData['sign'] == $mysign) {
            //回传数据
            $orderNo = $callbackData['out_trade_no'];
            $money = $callbackData['total_fee'];

            if ($callbackData['trade_status'] == 'TRADE_FINISHED' || $callbackData['trade_status'] == 'TRADE_SUCCESS') {
                return true;
            }
        } else {
            $message = '签名不正确';
        }
        return false;
    }

    //异步处理
    public function asyncCallback($callbackData, &$paymentId, &$money, &$message, &$orderNo) {
        //除去待签名参数数组中的空值和签名参数
        $filter_param = $this->filterParam($callbackData);

        //对待签名参数数组排序
        $para_sort = $this->argSort($filter_param);

        //生成签名结果
        $payment = new Payment($paymentId);
        $payment_plugin = $payment->getPaymentPlugin();
        $classConfig = $payment_plugin->getClassConfig();

        $mysign = $this->buildSign($para_sort, $classConfig['partner_key']);

        if ($callbackData['sign'] == $mysign) {
            //回传数据
            $orderNo = $callbackData['out_trade_no'];
            $money = $callbackData['total_fee'];

            if ($callbackData['trade_status'] == 'TRADE_FINISHED' || $callbackData['trade_status'] == 'TRADE_SUCCESS') {
                if(stripos($orderNo, 'promoter') !== false){//保存推广员订单的out_trade_no
                    $district_order = new Model("district_order");
                    $district_order->data(array("out_trade_no"=>$callbackData['trade_no']))->where("order_no=$orderNo")->update();
                }else if(stripos($orderNo, 'recharge') === false){
                    $order = new Model("order");
                    $order->data(array("out_trade_no"=>$callbackData['trade_no']))->where("order_no=$orderNo")->update();//将out_trade_no保存起来
                }
                return true;
            }
        }
        return false;
    }

    //打包数据
    public function packData($payment) {
        $return = array();

        //基本参数
        if (Chips::clientType() == 'desktop') {
            $return['service'] = 'create_direct_pay_by_user';
            // $return['service'] = 'trade_create_by_buyer';
        } else {
            $return['service'] = 'alipay.wap.create.direct.pay.by.user';
        }
        $return['partner'] = $return['seller_id'] = $payment['M_PartnerId'];
        $return['_input_charset'] = 'utf-8';
        $return['payment_type'] = 1;
        // $return['return_url'] = $this->callbackUrl;
        $return['return_url'] = "http://www.ymlypt.com/travel/order_details/id/".$payment['M_OrderId'];
        $return['notify_url'] = $this->asyncCallbackUrl;
        
        //业务参数
        //$return['enable_paymethod'] = 'directPay^bankPay';
        $return['subject'] = $payment['R_Name'];
        $return['out_trade_no'] = $payment['M_OrderNO'];
        $return['total_fee'] = $payment['M_Amount'];
        $return['show_url'] = Url::fullUrlFormat('/');
//        $return['price'] = number_format($payment['M_Amount'], 2, '.', '');
//        $return['quantity'] = 1;
//        $return['logistics_fee'] = "0.00";
//        $return['logistics_type'] = "EXPRESS";
//        $return['logistics_payment'] = "SELLER_PAY";
//
//        if (isset($payment['P_Name'])) {
//            $return['receive_name'] = $payment['P_Name'];
//            $return['receive_address'] = $payment['P_Address'];
//            $return['receive_zip'] = $payment['P_PostCode'];
//            $return['receive_phone'] = $payment['P_Telephone'];
//            $return['receive_mobile'] = $payment['P_Mobile'];
//        }
        //除去待签名参数数组中的空值和签名参数
        $filter_param = $this->filterParam($return);

        //对待签名参数数组排序
        $para_sort = $this->argSort($filter_param);

        //生成签名结果
        $mysign = $this->buildSign($para_sort, $payment['M_PartnerKey']);

        //签名结果与签名方式加入请求提交参数组中
        $return['sign'] = $mysign;
        $return['sign_type'] = 'MD5';
        return $return;
    }

    /**
     * 除去数组中的空值和签名参数
     * @param $para 签名参数组
     * return 去掉空值与签名参数后的新签名参数组
     */
    private function filterParam($para) {
        $filter_param = array();
        foreach ($para as $key => $val) {
            if ($key == "sign" || $key == "sign_type" || $val == "") {
                continue;
            } else {
                $filter_param[$key] = $para[$key];
            }
        }
        return $filter_param;
    }

    /**
     * 对数组排序
     * @param $para 排序前的数组
     * return 排序后的数组
     */
    private function argSort($para) {
        ksort($para);
        reset($para);
        return $para;
    }

    /**
     * 生成签名结果
     * @param $sort_para 要签名的数组
     * @param $key 支付交易安全校验码
     * @param $sign_type 签名类型 默认值：MD5
     * return 签名结果字符串
     */
    private function buildSign($sort_para, $key, $sign_type = "MD5") {
        //把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
        $prestr = $this->createLinkstring($sort_para);
        //把拼接后的字符串再与安全校验码直接连接起来
        $prestr = $prestr . $key;
        //把最终的字符串签名，获得签名结果
        $mysgin = md5($prestr);
        return $mysgin;
    }

    /**
     * 把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
     * @param $para 需要拼接的数组
     * return 拼接完成以后的字符串
     */
    private function createLinkstring($para) {
        $arg = "";
        foreach ($para as $key => $val) {
            $arg.=$key . "=" . $val . "&";
        }

        //去掉最后一个&字符
        $arg = trim($arg, '&');

        //如果存在转义字符，那么去掉转义
        if (get_magic_quotes_gpc()) {
            $arg = stripslashes($arg);
        }

        return $arg;
    }
    
    public function applyRefund($refundParams) {
        $order_no=isset($refundParams['order_no'])?$refundParams['order_no']:"";
        $refund_amount=isset($refundParams['refund_amount'])?$refundParams['refund_amount']:"";
        $refund_id=isset($refundParams['refund_id'])?$refundParams['refund_id']:"";
        if($order_no==""||$refund_amount==""||$refund_id==""){
            return array('status'=>'fail','msg'=>"退款参数错误");
        }
       $order = new Model("order");
       $out_trade_no = $order->where("order_no=$order_no")->fields("out_trade_no,order_amount")->find();
       
       $detail_data=$out_trade_no['out_trade_no'].'^'.$refund_amount.'^'."协商退款";
       $refund_no = date("Ymd")."R".substr($order_no,0,18);
       $parameter = array(
		"service" => "refund_fastpay_by_platform_nopwd",
		"partner" => trim($this->classConfig['partner_id']),
		"batch_no"	=> $refund_no,
		"refund_date"	=> date("Y-m-d H:i:s"),
		"batch_num"	=> 1,
		"detail_data"	=> $detail_data,
		"_input_charset"=> "utf-8"
        );
        $alipay_config['partner']=$this->classConfig['partner_id'];
        $alipay_config['key']=$this->classConfig['partner_key'];
        //签名方式 不需修改
        $alipay_config['sign_type']    = strtoupper('MD5');

        //字符编码格式 目前支持 gbk 或 utf-8
        $alipay_config['input_charset']= strtolower('utf-8');
        //ca证书路径地址，用于curl中ssl校验
        //请保证cacert.pem文件在当前文件夹目录中
        $alipay_config['cacert']    = dirname(dirname(__FILE__)).'/delivery/alipay/cacert.pem';
        //访问模式,根据自己的服务器是否支持ssl访问，若支持请选择https；若不支持请选择http
        $alipay_config['transport']    = 'http';
        //建立请求
        $alipaySubmit = new AlipaySubmit($alipay_config);
        $html_text = $alipaySubmit->buildRequestHttp($parameter);
        
        //解析XML
        //注意：该功能PHP5环境及以上支持，需开通curl、SSL等PHP配置环境。建议本地调试时使用PHP开发软件
        $doc = new DOMDocument();
        $doc->loadXML($html_text);
        if(!empty($doc->getElementsByTagName( "alipay" )->item(0)->nodeValue) ) {
	$alipay = $doc->getElementsByTagName( "alipay" )->item(0)->nodeValue;
	   if(trim($alipay)=='T'){
               $refund = new Model('refund');
               $refund->data(array('bank_handle_time'=>date("Y-m-d H:i:s"),"refund_no"=>$refund_no))->where("id =$refund_id")->update();
               $ordermodel = new Model("order as o");
                $order_model = $ordermodel->fields('o.user_id,o.order_amount,o.id,og.goods_id,o.order_no')->join('left join order_goods as og on o.id=og.order_id')->where('o.order_no='.$order_no)->find();
                if($order_model){
                   Common::backIncomeByInviteShip($order_model);
                }
               if(Order::refunded($refund_id)){
                       echo json_encode(array('status' => 'success', 'msg' => '退款操作成功，可能会有延迟哦'));
                       exit();
                 }else{
                     $refund->data(array("error_record"=>"退款成功，但是订单和退款信息未更新！！"))->where("id =$refund_id")->update();
                     echo json_encode(array('status' => 'fail', 'msg' => '退款操作成功，但是订单和退款信息未更新！！'));
                     exit();
                 }
           }else{
               echo json_encode(array('status' => 'fail', 'msg' => '退款失败：原因'.$alipay));
               exit();
           }
        }
        echo json_encode(array('status' => 'fail', 'msg' => '退款失败，原因：参数错误'));
        exit();
    }
    
    public function refundCallback($callbackData) {
        parent::refundCallback($callbackData);
    }
}
