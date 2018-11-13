<?php
require dirname(__FILE__).'/pay_unionpay/B2C/SecssUtil.class.php';
/**
 * @class pay_unionpay
 * @brief 中国银联【网关支付接口】插件类
 */
class pay_unionpayb2c extends PaymentPlugin {

    //支付插件名称
    public $name = '中国银联网银支付接口';
    protected $needSubmit = true;
    protected $config = array(
    );

    //提交地址
    public function submitUrl() {
       //  return 'http://newpayment-test.chinapay.com/CTITS/service/rest/page/nref/000000000017/0/0/0/0/0'; //测试环境请求地址
       return 'https://payment.chinapay.com/CTITS/service/rest/page/nref/000000000017/0/0/0/0/0'; //生产环境
    }

    //取得配制参数
    public static function config() {
        return array(
            array('field' => 'merId', 'caption' => '商户代码（merId）', 'type' => 'string'),
            array('field' => 'certPwd', 'caption' => '证书密码(certPwd)', 'type' => 'string'),
        );
    }

    //同步处理
    public function callback($callbackData, &$paymentId, &$money, &$message, &$orderNo) {
        
        if (isset($callbackData['Signature'])) {
                $securityPropFile= dirname(__FILE__)."/pay_unionpay/B2C/security.properties";
                $secssUtil = new SecssUtil();
                $secssUtil->init($securityPropFile); //初始化安全控件：
                $secssUtil->verify($callbackData);
               
                if($secssUtil->getErrCode()=='00'){
                      $orderNo = $callbackData['MerOrderNo'];
                      $money = round($callbackData['OrderAmt']/100,2);
                      return true;
                    }else{
                      $message = $secssUtil->getErrMsg();
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
        
        $securityPropFile= dirname(__FILE__)."/pay_unionpay/B2C/security.properties";
        $params = array(
            //以下信息非特殊情况不需要改动
            'Version' => '20140728', //版本号
            'AccessType' => '0', //编码方式
            'MerPageUrl' => $this->callbackUrl, //前台通知地址
            'MerBgUrl' => $this->asyncCallbackUrl, //后台通知地址
            'BusiType'=>'0001',
            'CurryNo'=>'CNY',
            //TODO 以下信息需要填写
            'MerId' => $merId, //商户代码，请改自己的测试商户号，此处默认取demo演示页面传递的参数
            'MerOrderNo' => $payment['M_OrderNO'], //商户订单号，8-32位数字字母，不能含“-”或“_”，此处默认取demo演示页面传递的参数，可以自行定制规则
            'TranDate' => date('Ymd'), //订单发送日期 20150102
            'TranTime' => date('His'), //订单发送时间 101122
            'OrderAmt' => $payment['M_Amount'] * 100, //交易金额，单位分，此处默认取demo演示页面传递的参数
        );
        
        $secssUtil = new SecssUtil();
        $result=$secssUtil->init($securityPropFile);
        $secssUtil->sign($params);
        $signature=$secssUtil->getSign();
        $params['Signature'] = $signature;
        
        return $params;
    }
    
    public function applyRefund($refundParams){//向银行申请
        $order_no=isset($refundParams['order_no'])?$refundParams['order_no']:"";
        $refund_amount=isset($refundParams['refund_amount'])?$refundParams['refund_amount']:"";
        $pay_time=isset($refundParams['pay_time'])?$refundParams['pay_time']:"";
        $refund_id=isset($refundParams['refund_id'])?$refundParams['refund_id']:"";
        if($order_no==""||$refund_amount==""||$pay_time==""||$refund_id==""){
            return array('status'=>'fail','msg'=>"退款参数错误");
        }
        $merId = $this->classConfig['merId'];
        $securityPropFile= dirname(__FILE__)."/pay_unionpay/B2C/security.properties";
        $params = array(
            //以下信息非特殊情况不需要改动
            'Version' => '20140728', //版本号
            'AccessType' => '0', //编码方式
            'MerOrderNo'=>'R'.$order_no,//退款订单号
            'TranDate' => date("Ymd"), //订单发送日期 20150102
            'TranTime' => date("His"), //订单发送时间 101122
            'OriOrderNo' => $order_no,//原始订单号
            'OriTranDate'=>date("Ymd",strtotime($pay_time)),//原始交易日期
            'MerId' => $merId, //商户代码
            'RefundAmt'=>$refund_amount*100,
            'TranType' =>'0401',
            'BusiType' =>'0001',
            'MerBgUrl'=>$this->refundCallbackUrl //退款回调地址
        );
        $secssUtil = new SecssUtil();
        $result=$secssUtil->init($securityPropFile);
        $secssUtil->sign($params);
        $signature=$secssUtil->getSign();
        $params['Signature'] = $signature;
        $refundUrl = "https://payment.chinapay.com/CTITS/service/rest/forward/syn/000000000065/0/0/0/0/0";
        $refundApplyResult = Http::curlPost($refundUrl, $params);
        $refund = new Model("refund");
        if($refundApplyResult['respCode']!="0000" && $refundApplyResult['respCode']!="1003" ){
            $error = isset($refundApplyResult['respMsg']) ? $refundApplyResult['respMsg']:"未知";
            $refund->data(array("error_record"=>$error.'['.date("Y-m-d H:i:s").']'))->where("id =$refund_id")->update();
            return array('status'=>'fail','msg'=>$refundApplyResult['respMsg']);
        }else{
            $refund->data(array('bank_handle_time'=>date("Y-m-d H:i:s"),'refund_progress'=>2,'refund_no'=>'R'.$order_no))->where("id =$refund_id")->update();
            $ordermodel = new Model("order as o");
                $order_model = $ordermodel->fields('o.user_id,o.order_amount,o.id,og.goods_id,o.order_no')->join('left join order_goods as og on o.id=og.order_id')->where('o.order_no='.$order_no)->find();
                if($order_model){
                   Common::backIncomeByInviteShip($order_model); //收回收益
                }
            return array('status'=>'success','msg'=>'退款申请已经提交至银行处理');
        }     
    }
    
    public function query($queryInfo){
        $merId = $this->classConfig['merId'];
        $securityPropFile= dirname(__FILE__)."/pay_unionpay/B2C/security.properties";
        $params = array(
            //以下信息非特殊情况不需要改动
            'Version' => '20140728', //版本号
            'AccessType' => '0', //编码方式
            'MerOrderNo'=>$queryInfo['order_no'],//订单号
            'TranDate' => date("Ymd",  strtotime($queryInfo['time'])), //订单发送日期 20150102
            'TranType' => "0502",//原始订单号
            'MerId' => $merId, //商户代码
            'BusiType' =>'0001',
        );
        $secssUtil = new SecssUtil();
        $result=$secssUtil->init($securityPropFile);
        $secssUtil->sign($params);
        $signature=$secssUtil->getSign();
        $params['Signature'] = $signature;
        $queryUrl = "https://payment.chinapay.com/CTITS/service/rest/forward/syn/000000000060/0/0/0/0/0";
        $result = Http::curlPost($queryUrl, $params);
        if($result['respCode']=="0000"){
            return result;
        }else{
            return false;
        }
    }
    
    public function refundCallback($callbackData){
        if (isset($callbackData['Signature'])) {
                $securityPropFile= dirname(__FILE__)."/pay_unionpay/B2C/security.properties";
                $secssUtil = new SecssUtil();
                $secssUtil->init($securityPropFile); //初始化安全控件：
                $secssUtil->verify($callbackData);
                if($secssUtil->getErrCode()=='00'){
                      $orderNo = $callbackData['OriOrderNo']; //退款的原始单号
                      $refundNo = $callbackData['MerOrderNo'];//退款单号
                      $refundAmount = $callbackData['RefundAmt'];
                      $orderStatus = $callbackData['OrderStatus'];
                      if($orderStatus=="0008"){
                          return array('status'=>'success','msg'=>'退款成功','order_no'=>$orderNo,'refund_no'=>$refundNo,'refund_amount'=>$refundAmount);
                      }
                    }else{
                        return array('status'=>'fail','msg'=>$secssUtil->getErrMsg());
                   }
           } else {
            return array('status'=>'fail','msg'=>'签名为空');
        }
        return array('status'=>'fail','msg'=>'失败');
    }
}
