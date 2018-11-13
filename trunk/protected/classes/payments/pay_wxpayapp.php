<?php

/**
 * @class pay_wxpayapp
 * @brief 微信支付(APP支付)
 */
class pay_wxpayapp extends PaymentPlugin {

    //支付插件名称
    public $name = '微信支付(APP支付)';

    //提交地址
    public function submitUrl() {
        return '';
    }

    //取得配制参数
    public static function config() {
        return array(
            array('field' => 'app_id', 'caption' => '应用app_id', 'type' => 'string'),
            array('field' => 'mch_id', 'caption' => '商户号mch_id', 'type' => 'string'),
            array('field' => 'app_key', 'caption' => '商户密钥Key', 'type' => 'string'),
            array('field' => 'app_secret', 'caption' => '应用app_secret', 'type' => 'string'),
        );
    }

    //同步处理
    public function callback($callbackData, &$paymentId, &$money, &$message, &$orderNo) {

        return false;
    }

    //异步处理
    public function asyncCallback($callbackData, &$paymentId, &$money, &$message, &$orderNo) {
        $this->notify = new PayNotifyCallBack();
        $msg = "OK";
        WxPayConfig::setConfig($this->getClassConfig());
        WxPayConfig::setAppSecret($this->classConfig['app_secret']);
        WxPayConfig::setSslCert('/appkey/apiclient_cert.pem');
        WxPayConfig::setSslKey('/appkey/apiclient_key.pem');
        //当返回false的时候，表示notify中调用NotifyCallBack回调失败获取签名校验失败，此时直接回复失败
        $result = WxPayApi::notify(array($this->notify, 'NotifyCallBack'), $msg);
        if ($result == false) {
            $this->notify->SetReturn_code("FAIL");
            $this->notify->SetReturn_msg($msg);
            return false;
        } else {
            //该分支在成功回调到NotifyCallBack方法，处理完成之后流程
            $this->notify->SetReturn_code("SUCCESS");
            $this->notify->SetReturn_msg("OK");
            //回传数据
            $orderNo = $this->notify->data['attach'];
            $money =  round($this->notify->data['total_fee']/100,2);
            if (stripos($orderNo, 'recharge') == false){ 
                $order = new Model("order");
                $order->data(array("out_trade_no"=>$this->notify->data['out_trade_no']))->where("order_no=$orderNo")->update();//将out_trade_no保存起来
            }
            return true;
        }
        //$this->notify->Handle(false);
    }

    public function asyncStop() {
        $this->notify->ReplyNotify(FALSE);
    }

    //打包数据
    public function packData($payment) {
        WxPayConfig::setConfig($this->getClassConfig());
        WxPayConfig::setSslCert('/appkey/apiclient_cert.pem');
        WxPayConfig::setSslKey('/appkey/apiclient_key.pem');
        $input = new WxPayUnifiedOrder();
        $input->SetAppid($this->classConfig['app_id']);
        $input->SetMch_id($this->classConfig['mch_id']);
        $input->SetBody($payment['R_Name']);
        $input->SetAttach($payment['M_OrderNO']);
        $input->SetOut_trade_no(substr(time(), 6) . "_" . $payment['M_OrderNO']);
        $input->SetTotal_fee(intval($payment['M_Amount'] * 100));
        $input->SetTime_start(date("YmdHis"));
        $input->SetTime_expire(date("YmdHis", time() + 3600));
        $input->SetGoods_tag("test");
        $input->SetNotify_url($this->asyncCallbackUrl);
        $input->SetTrade_type("APP");
        $order = WxPayApi::unifiedOrder($input);
        if (isset($order['prepay_id'])) {
            $values = array();
            $values['appid'] = $this->classConfig['app_id'];
            $values['partnerid'] = $this->classConfig['mch_id'];
            $values['package'] = "Sign=WXPay";
            $values['noncestr'] = CHash::random(16, 'char');
            $values['prepayid'] = $order['prepay_id'];
            $values['timestamp'] = time();
            $second = new WxPayDataBase();
            $second->SetValues($values);
            $values['sign'] = $second->MakeSign();
            $order = $values;
        }
        return $order;
    }
/*
     * Array ( [appid] => wx70ace2143fc5d154 
     * [cash_fee] => 1 
     * [cash_refund_fee] => 1 
     * [coupon_refund_count] => 0 
     * [coupon_refund_fee] => 0 
     * [mch_id] => 1369681802 
     * [nonce_str] => NJlmXcLzG92PDWXs 
     * [out_refund_no] => R0867_recharge201611291141075827 
     * [out_trade_no] => 0867_recharge201611291141075827 
     * [refund_channel] => Array ( ) 
     * [refund_fee] => 1 
     * [refund_id] => 2006042001201611290614824750 
     * [result_code] => SUCCESS 
     * [return_code] => SUCCESS 
     * [return_msg] => OK 
     * [sign] => A8215B12F8DC77CD761E4238D29DDCAF 
     * [total_fee] => 1 
     * [transaction_id] => 4006042001201611291162659471 ) 
     */
    //默认全额退款
    public function applyRefund($refundParams){
        WxPayConfig::setConfig($this->getClassConfig());
        WxPayConfig::setSslCert('/appkey/apiclient_cert.pem');
        WxPayConfig::setSslKey('/appkey/apiclient_key.pem');
        $order_no=isset($refundParams['order_no'])?$refundParams['order_no']:"";
        $refund_amount=isset($refundParams['refund_amount'])?$refundParams['refund_amount']:"";
        $refund_id=isset($refundParams['refund_id'])?$refundParams['refund_id']:"";
        if($order_no==""||$refund_amount==""||$refund_id==""){
            return array('status'=>'fail','msg'=>"退款参数错误");
        }
        $order = new Model("order");
        $out_trade_no = $order->where("order_no=$order_no")->fields("out_trade_no,order_amount,trading_info")->find();
        if(empty($out_trade_no)){
            return array('status'=>'fail','msg'=>"订单未找到");
        }
        if($out_trade_no['out_trade_no']==""){
            $out_trade_no['out_trade_no'] = $out_trade_no['trading_info'];
            if($out_trade_no['out_trade_no']==""){
                return array('status'=>'fail','msg'=>"out_trade_no未找到");
            } 
        }
        $refund_no = 'R'.$out_trade_no['out_trade_no'];
	$total_fee = $refund_amount*100;
	$refund_fee = $refund_amount*100;
	$input = new WxPayRefund();
	$input->SetOut_trade_no($out_trade_no['out_trade_no']);
	$input->SetTotal_fee($total_fee);
	$input->SetRefund_fee($refund_fee);
        //$input->SetOut_refund_no(WxPayConfig::MCHID.date("YmdHis"));
        $input->SetOut_refund_no($refund_no);   //退款单号，相同单号只退一笔
        $input->SetOp_user_id(WxPayConfig::MCHID);
        //$input->SetRefund_account('REFUND_SOURCE_RECHARGE_FUNDS');//使用余额退款
	$result = WxPayApi::refund($input);
    // var_dump($result);exit();
        $refund = new Model("refund");
        if($result['return_code']=="SUCCESS" && $result['result_code']=="SUCCESS"){
            //查询是退款到哪里
            $refund = new Model("refund");
            $queryResult = $this->Query(array('wx_refund_id'=>$result['refund_id']));
            if($queryResult!=false && $queryResult['return_code']=="SUCCESS" && $queryResult['result_code']=="SUCCESS"){
                $refund_info = "钱款已经退至".$queryResult['refund_recv_accout_0'].",系统可能会有延迟，若超过三个工作日未收到请与客服联系";
                $refund->data(array('bank_handle_time'=>date("Y-m-d H:i:s"),"refund_no"=>$refund_no,'refund_info'=>$refund_info))->where("id =$refund_id")->update();
                $ordermodel = new Model("order as o");
                   $order_model = $ordermodel->fields('o.user_id,o.order_amount,o.id,og.goods_id,o.order_no')->join('left join order_goods as og on o.id=og.order_id')->where('o.order_no='.$order_no)->find();
                if($order_model){
                   Common::backIncomeByInviteShip($order_model); //收回收益
                }
                if(Order::refunded($refund_id)){
                       echo json_encode(array('status' => 'success', 'msg' => '退款操作成功，可能会有延迟哦'));
                       exit();
                 }else{
                     $refund->data(array("error_record"=>"退款成功，但是订单和退款信息未更新！！"))->where("id =$refund_id")->update();
                     echo json_encode(array('status' => 'fail', 'msg' => '退款操作成功，但是订单和退款信息未更新！！'));
                     exit();
                 }
            }
        }else{
           file_put_contents("wxapp.txt", "微信退款失败：\r\n".json_encode($result)."\r\n__________________________",FILE_APPEND);
           $error = isset($result['err_code_des']) ? $result['err_code_des']:"未知";
           $refund->data(array("error_record"=>$error.'['.date("Y-m-d H:i:s").']'))->where("id =$refund_id")->update();
           echo json_encode(array('status' => 'fail', 'msg' => '退款操作失败，原因：'.$error));
           exit();
        }
    }
    /*
     * Array ( [appid] => wx70ace2143fc5d154 
     * [cash_fee] => 1 
     * [mch_id] => 1369681802 
     * [nonce_str] => ypfFRKTkAT418NLx 
     * [out_refund_no_0] => R0867_recharge201611291141075827 
     * [out_trade_no] => 0867_recharge201611291141075827 
     * [refund_channel_0] => ORIGINAL //原路返回
     * [refund_count] => 1 
     * [refund_fee] => 1 
     * [refund_fee_0] => 1 
     * [refund_id_0] => 2006042001201611290614824750 
     * [refund_recv_accout_0] => 招商银行储蓄卡3352 
     * [refund_status_0] => SUCCESS 
     * [result_code] => SUCCESS 
     * [return_code] => SUCCESS 
     * [return_msg] => OK 
     * [sign] => 829938506BF3415747416EEDBCA2D63C 
     * [total_fee] => 1 
     * [transaction_id] => 4006042001201611291162659471 ) 
     */
    public function Query($queryInfo){
        WxPayConfig::setConfig($this->getClassConfig());
        WxPayConfig::setSslCert('/appkey/apiclient_cert.pem');
        WxPayConfig::setSslKey('/appkey/apiclient_key.pem');
	$input = new WxPayRefundQuery();
        if(isset($queryInfo['wx_refund_id'])){
            $input->SetRefund_id($queryInfo['wx_refund_id']);
        }else if(isset($queryInfo['refund_no'])){
            $input->SetOut_refund_no($queryInfo['refund_no']);
        }
        $result = WxPayApi::refundQuery($input);
        if($result['return_code']=="SUCCESS" && $result['result_code']=="SUCCESS"){
            return $result;
        }else{
            return false;
        }
    }
}
