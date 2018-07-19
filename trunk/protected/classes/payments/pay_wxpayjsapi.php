<?php

/**
 * @class pay_wechatjsapi
 * @brief 微信支付(JSAPI)
 */
class pay_wxpayjsapi extends PaymentPlugin {

    //支付插件名称
    public $name = '微信支付(JSAPI)';
    protected $notify = NULL;

    //提交地址
    public function submitUrl() {
        return '/payment/pay_wxpayjsapi_submit/';
    }

    //线下提交地址
    public function submitUrls() {
        return '/payment/pay_wxpayjsapi_submits/';
    }

    public function isNeedSubmit() {
        return true;
    }

    //取得配制参数
    public static function config() {
        return array(
            array('field' => 'appid', 'caption' => '绑定支付的APPID', 'type' => 'string'),
            array('field' => 'mchid', 'caption' => '商户号', 'type' => 'string'),
            array('field' => 'key', 'caption' => '商户支付密钥', 'type' => 'string'),
            array('field' => 'appsecret', 'caption' => '公众帐号secert', 'type' => 'string'),
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
        //当返回false的时候，表示notify中调用NotifyCallBack回调失败获取签名校验失败，此时直接回复失败
        $result = WxPayApi ::notify(array($this->notify, 'NotifyCallBack'), $msg);
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
                if(stripos($orderNo, 'promoter') != false){//保存推广员订单的out_trade_no
                    $district_order = new Model("district_order");
                    $district_order->data(array("out_trade_no"=>$this->notify->data['out_trade_no']))->where("order_no=$orderNo")->update();
                }else{
                    $order = new Model("order");
                    $order->data(array("out_trade_no"=>$this->notify->data['out_trade_no']))->where("order_no=$orderNo")->update();//将out_trade_no保存起来
                }
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
        $return = array();
        //基本参数
        $return['return_url'] = $this->callbackUrl . "/out_trade_no/" . $payment['M_OrderNO'];
        $return['notify_url'] = $this->asyncCallbackUrl;
          
        $return['subject'] = $payment['R_Name'];
        // $return['out_trade_no'] = substr(time(), 6) . "_" . $payment['M_OrderNO'];
        $return['out_trade_no'] = $payment['M_OrderNO'];
        $return['price'] = number_format($payment['M_Amount'], 2, '.', '');
        $return['quantity'] = 1;

        return $return;
    }

    //打包数据
    public function packDatas($payment) {
        WxPayConfig::setConfig($this->getClassConfig());
        $return = array();
        //基本参数
        $return['return_url'] = $this->callbackUrl . "/out_trade_no/" . $payment['M_OrderNO'];
        // $return['notify_url'] = $this->asyncCallbackUrl;
        $return['notify_url'] = 'http://www.ymlypt.com/payment/async_callbacks';
        
        // $return['notify_url'] = $payment['notify_url'];
        // var_dump($return['notify_url']);die;  
        $return['subject'] = $payment['R_Name'];
        $return['out_trade_no'] = substr(time(), 6) . "_" . $payment['M_OrderNO'];
        $return['price'] = number_format($payment['M_Amount'], 2, '.', '');
        $return['quantity'] = 1;

        return $return;
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
        $refund = new Model("refund");
        
        if($result['return_code']=="SUCCESS" && $result['result_code']=="SUCCESS"){
            //查询是退款到哪里
            $refund = new Model("refund");
            $queryResult = $this->Query(array('wx_refund_id'=>$result['refund_id']));
            if($queryResult!=false && $queryResult['return_code']=="SUCCESS" && $queryResult['result_code']=="SUCCESS"){
                $refund_info = "钱款已经退至".$queryResult['refund_recv_accout_0'].",系统可能会有延迟，若超过三个工作日未收到请与客服联系";
                $refund->data(array('bank_handle_time'=>date("Y-m-d H:i:s"),"refund_no"=>$refund_no,'refund_info'=>$refund_info))->where("id =$refund_id")->update();
                $ordermodel = new Model("order as o");
                   $order_model = $ordermodel->fields('o.user_id,o.order_amount,o.id,og.goods_id')->join('left join order_goods as og on o.id=og.order_id')->where('o.order_no='.$order_no)->find();
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
           file_put_contents("wxjsapi.txt", "微信退款失败：\r\n".json_encode($result)."\r\n__________________________",FILE_APPEND);
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