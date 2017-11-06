<?php

/**
 * @class pay_unionpay
 * @brief 中国银联【手机控件支付】插件类
 */
class pay_unionpayapp extends PaymentPlugin {

    //支付插件名称
    public $name = '中国银联手机控件支付接口';
    protected $needSubmit = false;
    //protected $postUrl = "https://101.231.204.80:5000/gateway/api/appTransReq.do";//测试提交地址
    protected $postUrl = "https://gateway.95516.com/gateway/api/appTransReq.do";
        
    protected $config = array(
    );

    //提交地址
    public function submitUrl() {
        return '';
    }

    //取得配制参数
    public static function config() {
        return array(
            array('field' => 'merId', 'caption' => '商户代码（merId）', 'type' => 'string'),
            array('field' => 'certPwd', 'caption' => '证书密码(certPwd)', 'type' => 'string'),
            array('field' => 'bizType', 'caption' => '业务类型', 'type' => 'select', 'options' => '000201:网关支付,000202:企业网银支付')
        );
    }

    //同步处理
    public function callback($callbackData, &$paymentId, &$money, &$message, &$orderNo) {

        if (isset($callbackData['signature'])) {
            $certPwd = $this->classConfig['certPwd'];
            UnionPayServices::setCertPwd($certPwd);
            UnionPayServices::setCertPath('app');
            if (UnionPayServices::verify($callbackData)) {
                if ($callbackData['respCode'] == "00") {
                    $orderNo = $callbackData['orderId'];
                    $money = round($callbackData['txnAmt']/100,2);
                    return true;
                }
                $message = '状态码不正确:' . $callbackData['respCode'];
            } else {
                $message = '签名不正确';
            }
        } else {
            $message = '签名为空';
        }
        return false;
    }

    //异步处理
    public function asyncCallback($callbackData, &$paymentId, &$money, &$message, &$orderNo) {
        return $this->callback($callbackData, $paymentId, $money, $message, $orderNo);
    }

    //打包数据
    public function packData($payment) {
        $merId = $this->classConfig['merId'];
        $certPwd = $this->classConfig['certPwd'];
        $bizType = $this->classConfig['bizType'];
        
        UnionPayServices::setCertPwd($certPwd);
        UnionPayServices::setCertPath('app');
        
        $params = array(
            //以下信息非特殊情况不需要改动
            'version' => '5.0.0', //版本号
            'encoding' => 'utf-8', //编码方式
            'txnType' => '01', //交易类型
            'txnSubType' => '01', //交易子类
            'bizType' => $bizType, //000201                //业务类型
            'frontUrl' => $this->callbackUrl, //前台通知地址
            'backUrl' => $this->asyncCallbackUrl, //后台通知地址
            'signMethod' => '01', //签名方法
            'channelType' => '08', //渠道类型，07-PC，08-手机
            'accessType' => '0', //接入类型
            'currencyCode' => '156', //交易币种，境内商户固定156
            //TODO 以下信息需要填写
            'merId' => $merId, //商户代码，请改自己的测试商户号，此处默认取demo演示页面传递的参数
            'orderId' => $payment['M_OrderNO'], //商户订单号，8-32位数字字母，不能含“-”或“_”，此处默认取demo演示页面传递的参数，可以自行定制规则
            'txnTime' => date('YmdHis'), //订单发送时间，格式为YYYYMMDDhhmmss，取北京时间，此处默认取demo演示页面传递的参数
            'txnAmt' => $payment['M_Amount'] * 100, //交易金额，单位分，此处默认取demo演示页面传递的参数
            'reqReserved' => $payment['M_OrderId'], //请求方保留域，透传字段，查询、通知、对账文件中均会原样出现，如有需要请启用并修改自己希望透传的数据
        );
       
        UnionPayServices::sign($params);
        $url = $this->postUrl;//???不解 为什么直接传会出错呢？
        $params = UnionPayServices::post($params,$url);
        return $params;
    }
    /*
     * Array ( [accessType] => 0 
     * [bizType] => 000201 
     * [encoding] => UTF-8 
     * [merId] => 802440353110664 
     * [orderId] => Rrecharge201611291337133479 
     * [origQryId] => 201611291337135947668 
     * [queryId] => 201611291342527539088 
     * [respCode] => 00 
     * [respMsg] => 成功[0000000] 
     * [signMethod] => 01 
     * [txnAmt] => 1 
     * [txnSubType] => 00 
     * [txnTime] => 20161129134252 
     * [txnType] => 04 
     * [version] => 5.0.0 
     * [certId] => 69597475696 
     */
    public function applyRefund($refundParams){//向银行申请
        $order_no=isset($refundParams['order_no'])?$refundParams['order_no']:"";
        $refund_amount=isset($refundParams['refund_amount'])?$refundParams['refund_amount']:"";
        $pay_time=isset($refundParams['pay_time'])?$refundParams['pay_time']:"";
        $refund_id=isset($refundParams['refund_id'])?$refundParams['refund_id']:"";
        if($order_no==""||$refund_amount==""||$pay_time==""||$refund_id==""){
            return array('status'=>'fail','msg'=>"退款参数错误");
        }
        $merId     = $this->classConfig['merId'];
        $certPwd   = $this->classConfig['certPwd'];
        //$bizType   = $this->classConfig['bizType'];
        
        UnionPayServices::setCertPwd($certPwd);
        UnionPayServices::setCertPath('app');
        $query = $this->Query(array('order_no'=>$order_no, 'pay_time'=>$pay_time));
        if($query===false){
            return array("status"=>'fail',"msg"=>"获取订单交易流水号失败");
        }
        $params = array(
            'version' => '5.0.0',		      //版本号
            'encoding' => 'UTF-8',		      //编码方式
            'signMethod' => '01',		      //签名方法
            'txnType' => '04',		              //交易类型
            'txnSubType' => '00',		      //交易子类
            'bizType' => '000201',		      //业务类型
            'accessType' => '0',		      //接入类型
            'channelType' => '08',		      //渠道类型
            'backUrl' => $this->refundCallbackUrl,    //后台通知地址
		
            //TODO 以下信息需要填写
            'orderId' => 'R'.$order_no,	    //商户订单号，8-32位数字字母，不能含“-”或“_”，可以自行定制规则，重新产生，不同于原消费，此处默认取demo演示页面传递的参数
            'merId' => $merId,	            //商户代码，请改成自己的测试商户号，此处默认取demo演示页面传递的参数
            'origQryId' => $query['queryId'],        //原消费的queryId，可以从查询接口或者通知接口中获取，此处默认取demo演示页面传递的参数
            'txnTime' =>date('YmdHis'),	    //订单发送时间，格式为YYYYMMDDhhmmss，重新产生，不同于原消费，此处默认取demo演示页面传递的参数
            'txnAmt' => $refund_amount * 100,     //交易金额，退货总金额需要小于等于原消费
// 	    'reqReserved' =>'透传信息',       //请求方保留域，透传字段，查询、通知、对账文件中均会原样出现，如有需要请启用并修改自己希望透传的数据
        );
        UnionPayServices::sign($params);
        $url = "https://gateway.95516.com/gateway/api/backTransReq.do";
        $refundApplyResult = UnionPayServices::post($params,$url);
         $refund = new Model("refund");
        if($refundApplyResult['respCode']!="00"){
            $error = isset($refundApplyResult['respMsg']) ? $refundApplyResult['respMsg']:"未知";
            $refund->data(array("error_record"=>$error.'['.date("Y-m-d H:i:s").']'))->where("id =$refund_id")->update();
            return array('status'=>'fail','msg'=>$refundApplyResult['respMsg']);
        }else{
            $refund->data(array('bank_handle_time'=>date("Y-m-d H:i:s"),'refund_progress'=>2,'refund_no'=>'R'.$order_no))->where("id =$refund_id")->update();
            $ordermodel = new Model("order");
                $order_model = $ordermodel->fields('user_id,order_amount,id')->where('order_no='.$order_no)->find();
                if($order_model){
                   Common::backIncomeByInviteShip($order_model); //收回收益
                }
            return array('status'=>'success','msg'=>'退款申请已经提交至银行处理');
        }     
    }
    
    public function Query($queryInfo){
        $queryUrl = "https://gateway.95516.com/gateway/api/queryTrans.do";//正式
        //$queryUrl = "https://101.231.204.80:5000/gateway/api/queryTrans.do";
        $merId = $this->classConfig['merId'];
        $certPwd = $this->classConfig['certPwd'];
        UnionPayServices::setCertPwd($certPwd);
        UnionPayServices::setCertPath('app');
        $params = array(
		//以下信息非特殊情况不需要改动
		'version' => '5.0.0',		  //版本号
		'encoding' => 'utf-8',		  //编码方式
		'signMethod' => '01',		  //签名方法
		'txnType' => '00',		      //交易类型
		'txnSubType' => '00',		  //交易子类
		'bizType' => '000201',		  //业务类型
		'accessType' => '0',		  //接入类型
		'channelType' => '08',		  //渠道类型
		//TODO 以下信息需要填写
		'merId' => $merId,	    
        );
        if(isset($queryInfo['queryid'])){//不知道为什么这里用queryid查就报错
            $params['queryId']=$queryInfo['queryid'];
        }else if(isset($queryInfo['order_no']) && isset($queryInfo['pay_time'])){
            $params['orderId']=$queryInfo['order_no'];
            $params['txnTime']=date("YmdHis",strtotime($queryInfo['pay_time']));	
        }else{
             return false;
        }
        UnionPayServices::sign($params);
        $queryResult = UnionPayServices::post($params,$queryUrl);
        if($queryResult['respCode']!="00"){
             return false;
         }else{
             return $queryResult;
         }
    }
    
    //同步处理
    public function refundCallback($callbackData) {
        if (isset($callbackData['signature'])) {
            $certPwd = $this->classConfig['certPwd'];
            UnionPayServices::setCertPwd($certPwd);
            UnionPayServices::setCertPath('app');
            if (UnionPayServices::verify($callbackData)) {
                if ($callbackData['respCode'] == "00") {
                    $refund_no = $callbackData['orderId'];
                    return array('status'=>'success','refund_no'=>$refund_no);
                }else{
                    return array('status'=>'fail','msg'=>$callbackData['respMsg']);
                }
            } else {
                return array('status'=>'fail','msg'=>'签名验证不通过');
            }
        } else {
            return array('status'=>'fail','msg'=>'签名为空');
        }
        return array('status'=>'fail','msg'=>'失败');
    }
   
}
