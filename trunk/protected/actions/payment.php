<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class PaymentAction extends Controller {

    public $model = null;
    public $code = 1000;
    public $content = NULL;
    public $user = null;

    public function __construct() {
        $this->model = new Model();
    }

    public function dopay() {
        // 获得payment_id 获得相关参数
        $payment_id = Filter::int(Req::args('payment_id'));
        $order_id = Filter::int(Req::args('order_id'));
        $recharge = Req::args('recharge');
        $extendDatas = Req::args();
        $rate = Req::args('base_rate');
        $this->model->table('order')->data(array('payment'=>$payment_id))->where('id='.$order_id)->update(); 
        unset($extendDatas['user_id']);
        unset($extendDatas['token']);
        if ($payment_id) {
            $payment = new Payment($payment_id);
            //生成支付类
            $paymentPlugin = $payment->getPaymentPlugin();
            if(!is_object($paymentPlugin)){
                $this->code =1113;
                exit();
            }
            $payment_info = $payment->getPayment();
             //充值处理
            if ($recharge != null) {
                $package = Filter::int(Req::args('package'));
                $package = $package==null ? 0 : $package;
                if(in_array($package,array(1,2,3,4))){
                    $config = Config::getInstance();
                    $package_set = $config->get("recharge_package_set");
                    
                    //  if($package==4){
                    //     $address_id = Filter::int(Req::args('address_id'));
                    //     $gift = Filter::int(Req::args("gift"));
                    //     //判断礼物是否是套餐真实的
                    //     $gift_arr = explode("|", $package_set[4]['gift']);
                    //     if (!in_array($gift, $gift_arr)) {
                    //         $this->code  = 1122;
                    //         return;
                    //     }
                    //     if(!$address_id){
                    //         $this->code = 1124;
                    //         return;
                    //     }else{
                    //         $address_isset = $this->model->table("address")->where("user_id =".$this->user['id']." and id =".$address_id)->count();
                    //         if($address_isset==0){
                    //              $this->code = 1124;
                    //              return;
                    //         }
                    //     }
                    // }
                    switch ($package) {
                        case 0 : $recharge = $recharge;
                            break;
                        case 1 : $recharge = $package_set[1]['money'];
                            break;
                        case 2 : $recharge = $package_set[2]['money'];
                            break;
                        case 3 : $recharge = $package_set[3]['money'];
                            break;
                        case 4 : $recharge = $package_set[4]['money'];
                            break;
                    }
                }
                if(is_numeric($recharge)){
                    $user['id']=$this->user['id'];
                    $recharge = round($recharge,2);
                    $paymentInfo = $payment->getPayment();
                    if($package==4){
                      if($rate<2 || $rate>99){
                        $this->code = 1195;
                        return;
                      }elseif(!$rate){
                        $rate = 3;
                      }
                    }
                    $data = array('user_id'=>$this->user['id'],'account' => $recharge, 'paymentName' => $paymentInfo['name'],'package' => $package,'rate'=>$rate);

                    $packData = $payment->getPaymentInfo('recharge', $data);
                    $packData = array_merge($extendDatas, $packData);
                    $recharge_no = substr($packData['M_OrderNO'], 8);
//                     if ($package == 4) {
//                         $recharge_gift_model = new Model();
// //                      $recharge_count = $model->table('recharge')->where("package in (1,2,3,4) and status =1 and user_id=" . $user['id'])->count();
//                         $gift_data['user_id'] = $user['id'];
//                         $gift_data['recharge_no'] = $recharge_no;
//                         $gift_data['package'] = $package;
//                         $gift_data['address_id'] = $address_id;
//                         $gift_data['gift'] = $gift;
// //                      $gift_data['is_first'] = $recharge_count > 0 ? 2 : 1; //判断是否是首次充
//                         $gift_data['status'] = 0;
                        
//                         $recharge_gift_model->table("recharge_gift")->data($gift_data)->insert();
//                     }
                    $sendData = $paymentPlugin->packData($packData);
                    if (!$paymentPlugin->isNeedSubmit()) {
                         if(isset($sendData['tn'])){
                                $this->code =0;
                                $this->content['tn']= $sendData['tn'];
                                return;
                           }else{
                                $this->code =0;
                                $this->content['senddata']=$sendData;
                                exit();
                            }
                    }
                }else{
                      $this->code = 1127;
                      return;
                }
            } else if ($order_id != null) {
                $this->model = new Model('order');
                $order = $this->model->where('id=' . $order_id)->find();
                if ($order) {
                    if($order['type']==2){
                        $flash_model = new Model('flash_sale');
                        $flash_sale = $flash_model->where('id='.$order['prom_id'])->find();
                        if($flash_sale){
                            if($flash_sale['goods_num']>=$flash_sale['max_num'] || $flash_sale['is_end']==1){
                                $this->code = 1205;
                                return;
                            }
                        }
                    }
                    if($order['type']==6){
                        $flash_model = new Model('pointflash_sale');
                        $flash_sale = $flash_model->where('id='.$order['prom_id'])->find();
                        if($flash_sale){
                            if($flash_sale['order_count']>=$flash_sale['max_sell_count'] || $flash_sale['is_end']==1){
                                $this->code = 1205;
                                return;
                            }
                        }
                    }
                    if($order['type']==1) {
                        $groupbuy_log = $this->model->table('groupbuy_log')->where('id='.$order['join_id'])->find();
                        if($groupbuy_log) {
                            $groupbuy_join = $this->model->table('groupbuy_join')->where('id='.$groupbuy_log['join_id'])->find();
                            if($groupbuy_join['need_num']==0) {
                                $this->model->table('order')->data(['status'=>5])->where('id='.$order['id'])->update();
                                $this->code = 1293; //人数已凑满
                                return;
                            }
                        }
                    }
                    if ($order['order_amount'] == 0 && $payment_info['class_name'] != 'balance') {
                        $this->code = 1066;
                        exit();
                    }
                    //获取订单可能延时时长，0不限制
                    $config = Config::getInstance();
                    $config_other = $config->get('other');
                     switch ($order['type']) {
                        case '1':
                            $order_delay = isset($config_other['other_order_delay_group']) ? intval($config_other['other_order_delay_group']) : 120;
                            break;
                        case '2':
                            $order_delay = isset($config_other['other_order_delay_flash']) ? intval($config_other['other_order_delay_flash']) : 120;
                            break;
                        case '3':
                            $order_delay = isset($config_other['other_order_delay_bund']) ? intval($config_other['other_order_delay_bund']) : 0;
                            break;
                        case '5':
                            $order_delay = isset($config_other['other_order_delay_point']) ? intval($config_other['other_order_delay_point']) : 0;
                            break;
                        case '6':
                            $order_delay = isset($config_other['other_order_delay_pointflash']) ? intval($config_other['other_order_delay_pointflash']) : 0;
                            break;
                        default:
                            $order_delay = 0;
                            break;
                    }

                    $time = strtotime("-" . $order_delay . " Minute");
                    $create_time = strtotime($order['create_time']);
                    if ($create_time >= $time || $order_delay == 0) {
                        //取得所有订单商品
                        $order_goods = $this->model->table('order_goods')->fields("product_id,goods_nums")->where('order_id=' . $order_id)->findAll();
                        $product_ids = array();
                        $order_products = array();
                        foreach ($order_goods as $value) {
                            $product_ids[] = $value['product_id'];
                            $order_products[$value['product_id']] = $value['goods_nums'];
                        }
                        $product_ids = implode(',', $product_ids);

                        $products = $this->model->table('products')->fields("id,store_nums")->where("id in ($product_ids)")->findAll();
                        $products_list = array();
                        foreach ($products as $value) {
                            $products_list[$value['id']] = $value['store_nums'];
                        }
                        $flag = true;
                        foreach ($order_goods as $value) {
                            if ($order_products[$value['product_id']] > $products_list[$value['product_id']]) {
                                $flag = false;
                                break;
                            }
                        }
                        //检测库存是否还能满足订单
                        if ($flag) {
                            //团购订单
                            if ($order['type'] == 1 || $order['type'] == 2) {
                                if ($order['type'] == 1) {
                                    $prom_name = '团购';
                                    $prom_table = "groupbuy";
                                } else {
                                    $prom_name = '抢购';
                                    $prom_table = "flash_sale";
                                }
                                $prom = $this->model->table($prom_table)->where("id=" . $order['prom_id'])->find();
                                if ($prom) {
                                    if (time() > strtotime($prom['end_time']) || $prom['max_num'] <= $prom["goods_num"]) {
                                        $this->model->table("order")->data(array('status' => 6))->where('id=' . $order_id)->update();
                                        $this->code = 1067;
                                        exit;
                                    }
                                }
                            }
                            $packData = $payment->getPaymentInfo('order', $order_id);
                            $packData = array_merge($extendDatas, $packData);
                            $sendData = $paymentPlugin->packData($packData);
                            if (!$paymentPlugin->isNeedSubmit()) {
                                     if(isset($sendData['tn'])){
                                        $this->code =0;
                                        $this->content['tn']= $sendData['tn'];
                                        $this->content['type'] = $order['type'];
                                        if($order['type']==1) {
                                            $groupbuy_log = $this->model->table('groupbuy_log')->where('id='.$order['join_id'])->find();
                                            if($groupbuy_log) {
                                                $this->content['join_id'] = $groupbuy_log['join_id'];
                                                $this->content['groupbuy_id'] = $groupbuy_log['groupbuy_id'];
                                            }
                                            
                                        }
                                        return;
                                     }else{
                                        $this->code =0;
                                        $this->content['senddata']=$sendData;
                                        $this->content['type'] = $order['type'];
                                        if($order['type']==1) {
                                            $groupbuy_log = $this->model->table('groupbuy_log')->where('id='.$order['join_id'])->find();
                                            if($groupbuy_log) {
                                                $this->content['join_id'] = $groupbuy_log['join_id'];
                                                $this->content['groupbuy_id'] = $groupbuy_log['groupbuy_id'];
                                            }
                                            
                                        }
                                        exit();
                                     }
                             }
                        } else {
                            if ($order['status'] < 4 && $order['pay_status'] == 0) {
                                $this->model->table("order")->data(array('status' => 6))->where('id=' . $order_id)->update();
                                $this->code = 1067;
                                exit();
                            } else {
                                $this->code = 1070;
                                return;
                            }
                        }
                    } else {
                        $this->model->data(array('status' => 6))->where('id=' . $order_id)->update();
                        $this->code = 1069;
                        exit;
                    }
                }
            }
            
            if (!empty($sendData)) {
                $this->content = array(
                    'order_id' => $order_id,
                    'payment_id' => $payment_id,
                    'senddata' => $sendData,
                );
                $this->content['senddata'] = $sendData;
                if ($paymentPlugin instanceof pay_silver) {
                    $this->model = new Model('user as us');
                    $userInfo = $this->model->join('left join customer as cu on us.id = cu.user_id')->where('cu.user_id = ' . $this->user['id'])->find();
                    if ($userInfo['pay_password_open'] == 1) {
                        $this->content['usebalance']=1;//兼容
                        $this->content['use_password'] = 1;
                    }else{
                        $this->content['use_password'] = 0;
                    }
                }
                if ($paymentPlugin instanceof pay_balance) {
                    $this->model = new Model('user as us');
                    $userInfo = $this->model->join('left join customer as cu on us.id = cu.user_id')->where('cu.user_id = ' . $this->user['id'])->find();
                    if ($userInfo['pay_password_open'] == 1) {
                        $this->content['use_password'] = 1;
                    }else{
                        $this->content['use_password'] = 0;
                    }
                }
                $this->code = 0;
                if ($recharge == null) {
                    $this->content['type'] = $order['type'];
                    if($order['type']==1) {
                        $groupbuy_log = $this->model->table('groupbuy_log')->where('id='.$order['join_id'])->find();
                        if($groupbuy_log) {
                            $this->content['join_id'] = $groupbuy_log['join_id'];
                            $this->content['groupbuy_id'] = $groupbuy_log['groupbuy_id'];
                        }                       
                    }
                }
            } else {
                $this->code = 1063;
            }
        } else {
            $this->code = 1000;
        }
    }

    //线下
    public function dopays(){
       $payment_id = Filter::int(Req::args('payment_id'));
       $order_no = date('YmdHis').rand(1000,9999);
       $order_amount = Filter::float(Req::args('order_amount'));
       $randomstr=rand(1000000000000,9999999999999);
       $seller_id = Filter::int(Req::args('seller_id'));//卖家用户id
       $cashier_id = Filter::int(Req::args('cashier_id'));//收银员id
       if(!$cashier_id) {
        $cashier_id = 0;
       }
       $desk_id = Filter::int(Req::args('desk_id'));//收银员id
       if(!$desk_id) {
        $desk_id = 0;
       }
       if(!$seller_id){
        $this->code = 1158;
       }
       if(in_array($seller_id, [101738,87455,55568,8158,25795,31751]) && date('Y-m-d H:i:s')>'2018-05-15 12:00:00' && date('Y-m-d H:i:s')<'2018-06-15 12:00:00'){
            $this->code = 1237;
            return;
        }
       if(in_array($seller_id, [55568,21079])) {
            $this->code = 1237;
            return;
        }
        if($seller_id==181199 && date('Y-m-d H:i:s')>'2018-09-26 00:00:00' && date('Y-m-d H:i:s')<'2018-10-02 23:59:59'){
            $this->code = 1237;
            return;
        }
       if(!$payment_id){
         $this->code = 1157;
       }
       $user_id = $this->user['id'];
       $invite=$this->model->table('invite')->where('invite_user_id='.$user_id)->find();
       if($invite){
           $invite_id=intval($invite['user_id']);//邀请人用户id
       }else{
           switch ($payment_id) {
                case 7:
                    $from = 'android_weixin';
                    break;
                case 18:
                    $from = 'ios_weixin';
                    break;
                case 16:
                    $from = 'android_alipay';
                    break;
                case 17:
                    $from = 'ios_alipay';
                    break;        
                default:
                    $from = 'android_weixin';
                    break;
            } 
           
           $district = $this->model->table('invite')->where('invite_user_id='.$seller_id)->find();
           if($district){
                $district_id = $district['district_id'];
            }else{
                $district_id = 1;
            }
           //添加邀请关系
           $data['user_id'] = $seller_id;
           $data['invite_user_id'] = $user_id;
           $data['from'] = $from;
           $data['district_id'] = $district_id;
           $this->model->table('invite')->data($data)->insert();
           $invite_id=$seller_id;
       }
       
       $user=$this->model->table('customer')->fields('mobile,real_name')->where('user_id='.$user_id)->find();
       if(!$user){
        $this->code = 1159;
       }
       $accept_name = Session::get('openname');
       $config = Config::getInstance()->get("district_set");
       if($payment_id==7 || $payment_id==16){
        $type = 2;
       }else{
        $type = 3;
       }
       $data['type']=$type;
       $data['order_no'] = $order_no;
       $data['user_id'] = $user_id;
       $data['payment'] = $payment_id;
       $data['status'] = 2;
       $data['pay_status'] = 0;
       $data['accept_name'] = $user['real_name'];
       $data['mobile'] = $user['mobile'];
       $data['payable_amount'] = $order_amount;
       $data['create_time'] = date('Y-m-d H:i:s');
       $data['pay_time'] = date("Y-m-d H:i:s");
       $data['handling_fee'] = round($order_amount*$config['handling_rate']/100,2);
       $data['order_amount'] = $order_amount;
       $data['real_amount'] = $order_amount;
       $data['point'] = 0;
       $data['voucher_id'] = 0;
       $data['prom_id']=$invite_id;
       $data['shop_ids']=$seller_id;
       $data['cashier_id'] = $cashier_id;
       $data['desk_id'] = $desk_id;
       $model = new Model('order_offline');
       $exist=$model->where('order_no='.$order_no)->find();
       //防止重复生成同笔订单
       if(!$exist){
          $order_id=$model->data($data)->insert();
       }

       $payment = new Payment($payment_id);
       $paymentPlugin = $payment->getPaymentPlugin();
       if(!is_object($paymentPlugin)){
                $this->code =1113;
                exit();
       }
       $third_pay = 0;
       $third_payment = $this->model->table('third_payment')->where('id=1')->find();
       if($third_payment){
          $third_pay = $third_payment['third_payment'];
       }
       $oauth_user=$this->model->table('oauth_user')->fields('open_id')->where('user_id='.$user_id)->find();
       if($oauth_user){
          $sub_openid=$oauth_user['open_id'];
        }else{
         $sub_openid='';
        }
        if($third_pay==0 and in_array($user_id, [42608,140531])){  //银盛支付
            $this->model->table('order_offline')->data(array('third_pay'=>2))->where('id='.$order_id)->update();
            //test       
            $myParams['method'] = 'ysepay.online.sdkpay';
            // $myParams['method'] = 'ysepay.online.jsapi.pay';
            $myParams['partner_id'] = 'yuanmeng';
            $myParams['timestamp'] = date('Y-m-d H:i:s', time());
            $myParams['charset'] = 'utf-8';
            $myParams['sign_type'] = 'RSA';
            $myParams['notify_url'] = 'http://www.ymlypt.com/payment/yinpay_callback';      
            // $myParams['return_url'] = 'http://www.ymlypt.com/ucenter/order_details/id/{$order_id}';
            $myParams['return_url'] = 'http://www.ymlypt.com/ucenter/order_details'; 
            $myParams['version'] = '3.0';
            
            $biz_content_arr = array(
            "out_trade_no"=>$order_no,
            "subject"=>'支付测试',
            "total_amount"=>$order_amount,
            "seller_id"=>'yuanmeng',
            "seller_name"=>'圆梦互联网科技（深圳）有限公司',
            "timeout_express"=>'1d',
            // "business_code"=>'3010001',
            "business_code" => "01000010",
            'appid'=>'wx167f2c4da1f798b0'
            );
            if($payment_id==7 || $payment_id==18) {
                $biz_content_arr['bank_type'] = '1902000'; //微信
            } else {
                $biz_content_arr['bank_type'] = '1903000'; //支付宝
            }
          // if($payment_id==16 || $payment_id==17){
          //   $myParams['method'] = 'ysepay.online.wap.directpay.createbyuser';
          //   // $myParams['bank_type'] = "1903000";
          //   // $myParams['pay_mode'] = "native";
          //   $myParams['out_trade_no'] = $order_no;
          //   $myParams['subject'] = '支付测试';
          //   $myParams["total_amount"]=$order_amount;
          //   $myParams["seller_id"]='yuanmeng';
          //   $myParams["seller_name"]='圆梦互联网科技（深圳）有限公司';
          //   $myParams["timeout_express"]='1d';
          //   $myParams['business_code'] = '3010001';
          //   $myParams['bank_type'] = '1902000'; 
          //  }
           // if($payment_id==7 || $payment_id==18){
            
           // }
           $myParams['biz_content'] = json_encode($biz_content_arr, JSON_UNESCAPED_UNICODE);//构造字符串
            ksort($myParams);
            $data = $myParams;
            $signStr = "";
            foreach ($myParams as $key => $val) {
                $signStr .= $key . '=' . $val . '&';
            }
            $signStr = rtrim($signStr, '&');
            $sign = $this->sign_encrypt(array('data' => $signStr));
            $myParams['sign'] = trim($sign['check']);
            
            $url = 'https://openapi.ysepay.com/gateway.do';
            $ret = Common::httpRequest($url,'POST',$myParams);
            $ret = json_decode($ret,true);
            // var_dump($ret);die;
            if(!isset($ret['ysepay_online_jsapi_pay_response']['jsapi_pay_info'])){
                // var_dump($myParams);
                // var_dump($ret);die;
               $this->code = 1228;
               return;
            }
            $sendData = json_decode($ret['ysepay_online_jsapi_pay_response']['jsapi_pay_info'],true);
            $sendData['appid'] = $sendData['appId'];
            unset($sendData['appId']);
            $sendData['partnerid'] = '1486189412';
            $sendData['noncestr'] = $sendData['nonceStr'];
            unset($sendData['nonceStr']);
            $sendData['timestamp'] = $sendData['timeStamp'];
            unset($sendData['timeStamp']);
            $sendData['prepayid'] = substr($sendData['package'],10);
            $sendData['package'] = 'Sign=WXPay';
            $sendData['sign'] = $sendData['paySign'];
            unset($sendData['paySign']);
        }else{
            //app微信支付    7,18    app支付宝支付 16,,17        
            $packData = $payment->getPaymentInfo('offline_order', $order_id);
            $sendData = $paymentPlugin->packData($packData);
        }
       
      $this->code = 0;
      $this->content = array(
              'order_id' => $order_id,
              'payment_id' => $payment_id,
              'senddata' => $sendData,
              );
   }

   public function sign_encrypt($input)
    {
        $pfxpath = "./protected/classes/yinpay/certs/yuanmeng.pfx";
        $pfxpassword = 'lc008596';
        $return = array('success' => 0, 'msg' => '', 'check' => '');
        $pkcs12 = file_get_contents($pfxpath); //私钥
        if (openssl_pkcs12_read($pkcs12, $certs, $pfxpassword)) {
            $privateKey = $certs['pkey'];
            $publicKey = $certs['cert'];
            $signedMsg = "";
            if (openssl_sign($input['data'], $signedMsg, $privateKey, OPENSSL_ALGO_SHA1)) {
                $return['success'] = 1;
                $return['check'] = base64_encode($signedMsg);
                $return['msg'] = base64_encode($input['data']);
            }
        }
        return $return;
    }

   public function pay_qrcode(){
    $user_id = $this->user['id'];
    // if($user_id==1) {
        $url = Url::fullUrlFormat("/travel/demo/inviter_id/".$user_id);
    // } else {
    //     $url = Url::fullUrlFormat("/ucenter/demo/inviter_id/".$user_id);
    // }
    $promoter = $this->model->table('district_promoter')->fields('id,user_id,qrcode_no,join_time')->where('user_id='.$user_id)->find();
    if($promoter['qrcode_no']=='') {
        $no = '0000'.$promoter['id'].rand(1000,9999);
        $this->model->table('district_promoter')->data(array('qrcode_no'=>$no))->where('id='.$promoter['id'])->update();
    }
    
    if(strtotime($promoter['join_time'])<=strtotime(date('2018-09-11'))) {
        $status = 1;
    } else {
        $contract = $this->model->table('promoter_contract')->where('user_id='.$user_id)->find();
        if($contract) {
            $status = $contract['status'];
        } else {
            $status = -1;
        }
    }
    $this->code = 0;
    $this->content['url'] = $url;
    $this->content['qrcode_no'] = $promoter?$promoter['qrcode_no']:'0000';
    $this->content['status'] = $status;
   }

    //余额支付
    public function pay_balance() {
        $this->model = new Model('user as us');
        $userInfo = $this->model->join('left join customer as cu on us.id = cu.user_id')->where('cu.user_id = ' . $this->user['id'])->find();
        //如果用户开启了支付密码
        if ($userInfo['pay_password_open'] == 1) {
            $pay_password = Req::args('pay_password');
            if ($userInfo['pay_password'] != CHash::md5($pay_password, $userInfo['pay_validcode'])) {
                $this->code = 1060;
                return;
            }
        }
        //参数
        $total_fee = Filter::float(Req::args('total_fee'));
        $order_no = Filter::sql(Req::args('order_no'));
        //不可用于充值
        if (stripos($order_no, 'recharge') !== false) {
            $this->code = 1061;
            return;
        }
        if (stripos($order_no, 'district') !== false) {
            $this->code = 1137;
            return;
        }
        //参数错误
        if (floatval($total_fee) < 0 || $order_no == '') {
            $this->code = 1000;
            return;
        } else {

            $user_id = $this->user['id'];
            $this->model = new Model("customer");
            $customer = $this->model->where("user_id=" . $user_id)->find();
            if ($customer['balance'] >= $total_fee) {
                $order = $this->model->table("order")->where("order_no='" . $order_no . "' and user_id=" . $user_id)->find();
                $redbag = $this->model->table('redbag')->where("order_no='".$order_no."' and user_id=".$user_id)->find();
                if ($order) {
                    if ($order['pay_status'] == 0 ) {
                        if($order['type']==2){
                            $flash_model = new Model('flash_sale');
                            $flash_sale = $flash_model->where('id='.$order['prom_id'])->find();
                            if($flash_sale){
                                if($flash_sale['goods_num']>=$flash_sale['max_num'] || $flash_sale['is_end']==1){
                                    $this->code = 1205;
                                    return;
                                }
                            }
                        }
                        if($order['type']==6){
                            $flash_model = new Model('pointflash_sale');
                            $flash_sale = $flash_model->where('id='.$order['prom_id'])->find();
                            if($flash_sale){
                                if($flash_sale['order_count']>=$flash_sale['max_sell_count'] || $flash_sale['is_end']==1){
                                    $this->code = 1205;
                                    return;
                                }
                            }
                        }
                        if($order['type']==4 && $order['otherpay_status']==1){
                             $this->code = 1062;
                             return;
                        }
                        if ($total_fee != $order['order_amount']) {
                            if($order['type']==4&&$order['is_new']==0){//如果是旧的华点订单
                                if($order['otherpay_amount']!=$total_fee){
                                    $this->code = 1000;
                                    return;
                                }
                            }else{
                                $this->code = 1000;
                                return;
                            }
                        }
                        //扣费并将订单状态更新
                        $flag = $this->model->table("customer")->data(array("balance"=>"`balance`-{$total_fee}"))->where("user_id = $user_id")->update();
                        if ($flag) {
                            //记录支付日志
                            Log::balance((0 - $total_fee), $user_id, $order_no, '购物下单');
                            Order::updateStatus($order_no, $order['payment']);
                        }
                        $this->code = 0;
                        $this->content['type'] = $order['type'];
                        if($order['type']==1) {
                            $groupbuy_log = $this->model->table('groupbuy_log')->where('id='.$order['join_id'])->find();
                            if($groupbuy_log) {
                                $this->content['join_id'] = $groupbuy_log['join_id'];
                                $this->content['groupbuy_id'] = $groupbuy_log['groupbuy_id'];
                            }
                            
                        }
                        return;
                    } else {
                        $this->code = 1062;
                        return;
                    }
                }elseif($redbag){
                  if($redbag['pay_status']==0){
                     //扣费并将订单状态更新
                      $flag = $this->model->table("customer")->data(array("balance"=>"`balance`-{$total_fee}"))->where("user_id =".$user_id)->update();
                      if ($flag) {
                          $this->model->table("redbag")->data(array('pay_status'=>1))->where("order_no='".$order_no."' and user_id=".$user_id)->update();
                          //记录余额 日志
                          Log::balance((0 - $total_fee), $user_id, $order_no, '发红包',17);
                      }
                      $this->code = 0;
                      return;
                  }else{
                      $this->code = 1199;
                      return;
                    }
                } else {
                    $this->code = 1063;
                    return;
                }
            } else {
                $this->code = 1064;
                $this->content = "金点不足";
                return;
            }
        }
    }
    
    public function paytype_list() {
        $platform =Filter::str(Req::args('platform'));
        $type = Filter::str(Req::args("type"));
        if(strtolower($platform) =='android'){
            $client_type = 3;
            switch ($type) {
                case 'recharge':
                     $notin="1,12,20";
                    break;
                case 'order':
                     $notin="12";
                    break;
                case 'district':
                    $notin ="1,12,20";
                    break;
                case 'offline':
                    $notin ="1,17";
                    break;
                case 'redbag':
                    $notin ="12";
                    break;        
                default:
                     $notin="12";
                    break;
            }
        }else if(strtolower($platform)=='ios'){
            $client_type = 4;
            switch ($type) {
                case 'recharge':
                     $notin="1,12,20";
                    break;
                case 'order':
                     $notin="12";
                    break;
                case 'district':
                    $notin ="1,12,20";
                    break;
                case 'offline':
                    $notin ="1,17";
                    break;
                case 'redbag':
                    $notin ="12";
                    break;        
                default:
                     $notin="12";
                    break;
            }
        }else{
            $client_type =9999;
        }
        $this->model = new Model("payment as pa");
        $paytypelist = $this->model->fields("pa.id,pa.pay_name,pa.description,pa.pay_fee,pa.sort,pp.logo,pp.class_name")->join("left join pay_plugin as pp on pa.plugin_id = pp.id")->where("pa.status = 0 and pa.plugin_id not in({$notin}) and pa.client_type = $client_type")->order("pa.sort desc")->findAll();
        foreach ($paytypelist as $k => $v){
            $paytypelist[$k]['logo'] = trim($paytypelist[$k]['logo'], '/');
        }
        $this->code = 0;
        $this->content = array(
            'paytypelist' => $paytypelist
        );
        return;
    }
    
    public function pay_district() {
        //获得payment_id 获得相关参数
        $payment_id = Filter::int(Req::args('payment_id'));
        $apply_id = Filter::int(Req::args('apply_id'));

        if ($apply_id) {
            $this->code = 1150;
            return;
        } else {
            $gift = Filter::int(Req::args('gift'));
            $address_id = Filter::int(Req::args('address_id'));
            $reference = Filter::int(Req::args("reference"));
            $invitor_role = Filter::str(Req::args("invitor_role"));
            //1.判断信息是否正确。
            $promoter = Promoter::getPromoterInstance($this->user['id']);
            if (is_object($promoter)) {
               $this->code = 1140;
               return;
            }
            $config = Config::getInstance()->get("district_set");
            $gift_list = explode("|",$config['join_send_gift']);
            if (!in_array($gift, $gift_list)) {
                $this->code= 1000;
                return;
            }
            $isset = $this->model->table("address")->where("user_id = " . $this->user['id'] . " and id = $address_id")->count();
            if ($isset != 1) {
                $this->code = 1055;
                return;
            }
            if ($invitor_role) {
                if ($invitor_role == 'shop') {
                    $district_info = $this->model->table("district_shop")->where("id = $reference")->find();
                    $insert_data['invitor_role'] = 'shop';
                } else if ($invitor_role == 'promoter') {
                    $district_info = $this->model
                            ->table("district_promoter as dp")->join("left join district_shop as ds on dp.hirer_id = ds.id")
                            ->where("dp.id = $reference")
                            ->find();
                    $insert_data['invitor_role'] = "promoter";
                } else {
                    $this->code =1000;
                    return;
                }
                if (!isset($district_info) || !$district_info) {
                    $this->code = 1131;
                    return;
                }
                //2.记录
                //防止订单重复
                $order = $this->model->table("district_order")->where("user_id=" . $this->user['id'] . " and invitor_id = $reference and invitor_role ='{$insert_data['invitor_role']}' and pay_status =0")->find();
                if (!$order) {
                    $insert_data['user_id'] = $this->user['id'];
                    $insert_data['invitor_id'] = $reference;
                    $insert_data['order_no'] = "promoter" . date("YmdHis") . rand(10, 99);
                    $insert_data['gift'] = $gift;
                    $insert_data['address_id'] = $address_id;
                    $insert_data['fee'] = $config['promoter_fee'];
                    $insert_data['create_date'] = date("Y-m-d H:i:s");
                    $insert_data['payment_id'] = $payment_id;
                    $id = $this->model->table("district_order")->data($insert_data)->insert();
                    $order_no = $insert_data['order_no'];
                } else {
                    $order_no = $order['order_no'];
                    $this->model->table("district_order")->data(array('gift'=>$gift,'address_id'=>$address_id,'fee'=>$config['promoter_fee']))->where("id=".$order['id'])->update();
                }
                if ($order || $id) {
                    $order_id = isset($order['id']) ? $order['id'] : $id;
                    $payment = new Payment($payment_id);
                    $paymentPlugin = $payment->getPaymentPlugin();
                    if(!is_object($paymentPlugin)){
                        $this->code = 1000;
                        return;
                    }
                    $paymentInfo = $payment->getPayment();
                    if ($paymentPlugin instanceof pay_balance || $paymentPlugin instanceof pay_silver) {
                        $this->code = 1113;
                        return;
                    }
                    $amount = $config['promoter_fee'];
                    $data = array('amount' => $amount, 'order_no' => $order_no, 'order_id' => $order_id);
                    $packData = $payment->getPaymentInfo('promoter', $data);
                    $sendData = $paymentPlugin->packData($packData);
                    if (!$paymentPlugin->isNeedSubmit()) {
                         if(isset($sendData['tn'])){
                                $this->code =0;
                                $this->content['tn']= $sendData['tn'];
                                return;
                           }else{
                               $this->code =0;
                               $this->content['senddata']=$sendData;
                               exit();
                          }
                    }
                    if (!empty($sendData)) {
                        $this->content = array(
                                'apply_id'=>$apply_id,
                                'payment_id' => $payment_id,
                                'senddata' => $sendData,
                            );
                        $this->content['senddata'] = $sendData;
                        $this->code = 0;
                    }
                } else {
                    $this->code = 1005;
                    return;
                }
            }
        }
    }
    
    public function seller_name(){
      $user_id = Filter::int(Req::args('seller_id'));
      if(!$user_id){
        $this->code = 1158;
        return;
      }
      $promoter = $this->model->table('district_promoter')->fields('shop_name')->where('user_id='.$user_id)->find();
      $seller = $this->model->table('customer')->fields('real_name')->where('user_id='.$user_id)->find();
      $user = $this->model->table('user')->fields('nickname')->where('id='.$user_id)->find();
      if(!$seller || !$user){
        $this->code = 1159;
        return;
      }
      
      $shop_name = isset($promoter['shop_name']) && $promoter['shop_name']!=''?$promoter['shop_name']:($seller['real_name']!=''?$seller['real_name']:$user['nickname']);
      if($shop_name==''){
        $shop_name = "匿名商家";
      }
      $this->code = 0;
      $this->content['shop_name'] = $shop_name;
      return;
    }

    public function pay_success(){
       $order_id = Filter::int(Req::args('order_id'));
       $order = $this->model->table('order_offline')->fields('shop_ids,order_no')->where('id='.$order_id)->find();
       if(!$order){
        $this->code = 1096;
        return;
       }
       $seller_id = $order['shop_ids'];
       $seller = $this->model->table('customer')->fields('real_name')->where('user_id='.$seller_id)->find();
       if(!$seller){
        $this->code = 1159;
        return;
       }
       $this->code = 0;
       $this->content['shop_name'] = $seller['real_name'];
       $this->content['order_no'] = $order['order_no'];
       $this->content['date'] = date("Y-m-d H:i:s");
    }

    public function jpushTest(){
      // $user_id = $this->user['id'];
      $user_id = Filter::int(Req::args('to_id'));;
      $money = Filter::float(Req::args('money'));

      $type = 'offline_balance';
      $content = "余额到账{$money}元";
      $platform = 'all';
      if (!$this->jpush) {
              $NoticeService = new NoticeService();
              $this->jpush = $NoticeService->getNotice('jpush');
          }
      $audience['alias'] = array($user_id);
      $this->jpush->setPushData($platform, $audience, $content, $type, "");
      $result = $this->jpush->push();
      $this->code = 0;
      $this->content = $result;
      return;
    }

    public function dinpay(){
       $merchant_private_key='MIICeAIBADANBgkqhkiG9w0BAQEFAASCAmIwggJeAgEAAoGBAKwJnd8sHJojXIFxuf4Ibsdtc2cJHPlN2d/IKMBw5cuoRknNeMCTlR89MxEqfuPqYR7o1dGgOiehswR9T4vWByzhJlrLEFcgOcJFnDINzU9iZW4RcRKf187sLXYL8b5Vf5WjEfudXjnxSGt8HXPe+V0VimUVaIAQSWvBCWgHkFV/AgMBAAECgYBivF40EJAV0serrwatCk/x+xopf2x2lLy/l5Pz5pesS9aTUu7Dr6/9LtWZO4d57TFyWPUmi0v1JPOmVvkJa3vPz6HhZIzg5M4jd23Kj8fl94PaTSyGM3NEMRJDLPxWEB9ydR60VtRlieCf2lyH0JSKa5YMS09A6ks13W4SVNRqaQJBAOF22itr0KonXZaQxNIOrnGifCvBA11cKV1SMxT5iLOuYu5j2VOZNExC5oD4j1fkT/7kEq+7OSTEOhZwgcNkcGUCQQDDVmOlmKHBjUpMmv0xfc789Zj7PLoKO9WpYkDTbl7xPdc/Yb0OeeZlS123ZlplXLMVPpOQTpFcrbk9nhShaSYTAkEAhnrPsqqCMZt9VPtQikI7hof2LFrZ2OvJuGH5Gf+krBfN5ocj75sn+HzG5BJd3XzOwifjhXHUqbtpMk00+QiFiQJBAIv2JGQM3yn+ANSu4OhLSrp5h2nM80hN4yQA4I4eMS0NsGMbtwjeUzUVMUstrWufZjm8oqLtiL4tQ+Ngl0uoOb0CQQCuOR315Fwm/BW3QXjaASDwN8sahQxfNAtUyh7oGJfieKWYEjd3VYfaWXyful7FWW/Ry8H1pOSbIJZo07gLVTvA';
       
       $merchant_public_key='MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCsCZ3fLByaI1yBcbn+CG7HbXNnCRz5TdnfyCjAcOXLqEZJzXjAk5UfPTMRKn7j6mEe6NXRoDonobMEfU+L1gcs4SZayxBXIDnCRZwyDc1PYmVuEXESn9fO7C12C/G+VX+VoxH7nV458UhrfB1z3vldFYplFWiAEElrwQloB5BVfwIDAQAB';

       $dinpay_public_key='MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCOglLSDWk8iIHH5zFvAg9n++I4iew5Zj4M/8J8TLRj7UShJ3roroNgCkH1Iyw65xIddlCfJK8wkszpZ4OvPRiCDUBaEMENF/TQmscL2M+Ly7XEQ34RTQ1WVcpkZb7KJuiK3XIByYM0fETM1RVhQGJsnC7QpDaorjkWjpuLcR6bDwIDAQAB ';
       
      //  $merchant_private_key='MIICdwIBADANBgkqhkiG9w0BAQEFAASCAmEwggJdAgEAAoGBALf/+xHa1fDTCsLYPJLHy80aWq3djuV1T34sEsjp7UpLmV9zmOVMYXsoFNKQIcEzei4QdaqnVknzmIl7n1oXmAgHaSUF3qHjCttscDZcTWyrbXKSNr8arHv8hGJrfNB/Ea/+oSTIY7H5cAtWg6VmoPCHvqjafW8/UP60PdqYewrtAgMBAAECgYEAofXhsyK0RKoPg9jA4NabLuuuu/IU8ScklMQIuO8oHsiStXFUOSnVeImcYofaHmzIdDmqyU9IZgnUz9eQOcYg3BotUdUPcGgoqAqDVtmftqjmldP6F6urFpXBazqBrrfJVIgLyNw4PGK6/EmdQxBEtqqgXppRv/ZVZzZPkwObEuECQQDenAam9eAuJYveHtAthkusutsVG5E3gJiXhRhoAqiSQC9mXLTgaWV7zJyA5zYPMvh6IviX/7H+Bqp14lT9wctFAkEA05ljSYShWTCFThtJxJ2d8zq6xCjBgETAdhiH85O/VrdKpwITV/6psByUKp42IdqMJwOaBgnnct8iDK/TAJLniQJABdo+RodyVGRCUB2pRXkhZjInbl+iKr5jxKAIKzveqLGtTViknL3IoD+Z4b2yayXg6H0g4gYj7NTKCH1h1KYSrQJBALbgbcg/YbeU0NF1kibk1ns9+ebJFpvGT9SBVRZ2TjsjBNkcWR2HEp8LxB6lSEGwActCOJ8Zdjh4kpQGbcWkMYkCQAXBTFiyyImO+sfCccVuDSsWS+9jrc5KadHGIvhfoRjIj2VuUKzJ+mXbmXuXnOYmsAefjnMCI6gGtaqkzl527tw=';
      // $merchant_public_key = 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQC3//sR2tXw0wrC2DySx8vNGlqt3Y7ldU9+LBLI6e1KS5lfc5jlTGF7KBTSkCHBM3ouEHWqp1ZJ85iJe59aF5gIB2klBd6h4wrbbHA2XE1sq21ykja/Gqx7/IRia3zQfxGv/qEkyGOx+XALVoOlZqDwh76o2n1vP1D+tD3amHsK7QIDAQAB';
    
      // $dinpay_public_key ='MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCWOq5aHSTvdxGPDKZWSl6wrPpnMHW+8lOgVU71jB2vFGuA6dwa/RpJKnz9zmoGryZlgUmfHANnN0uztkgwb+5mpgmegBbNLuGqqHBpQHo2EsiAhgvgO3VRmWC8DARpzNxknsJTBhkUvZdy4GyrjnUrvsARg4VrFzKDWL0Yu3gunQIDAQAB ';
      
      // $merchant_code = "1118004517";
      $merchant_code = "4000038801";//商户号，1118004517是测试商户号，线上发布时要更换商家自己的商户号！

      // $service_type ="wxpub_pay"; //微信公众号支付
      $service_type = "direct_pay"; //B2C网关支付

      $interface_version ="V3.0";

      $sign_type ="RSA-S";

      $input_charset = "UTF-8";
      
      $notify_url ="http://www.ymlypt.com/payment/callback";   
      
      $order_no = Common::createOrderNo(); 

      $order_time = date( 'Y-m-d H:i:s' );  

      $order_amount = Filter::float(Req::args('order_amount'));  

      $product_name ="testpay"; 

      //以下参数为可选参数，如有需要，可参考文档设定参数值
      
      $return_url ="";  
      
      $pay_type = "";
      
      $redo_flag = "";  
      
      $product_code = ""; 

      $product_desc = ""; 

      $product_num = "";

      $show_url = ""; 

      $client_ip = Common::getIp();  

      $bank_code = "";  

      $extend_param = "";

      $extra_return_param = ""; 

        
      

    /////////////////////////////   参数组装  /////////////////////////////////
    /**
    除了sign_type参数，其他非空参数都要参与组装，组装顺序是按照a~z的顺序，下划线"_"优先于字母  
    */
      
      $signStr= "";
      
      if($bank_code != ""){
        $signStr = $signStr."bank_code=".$bank_code."&";
      }
      if($client_ip != ""){
        $signStr = $signStr."client_ip=".$client_ip."&";
      }
      if($extend_param != ""){
        $signStr = $signStr."extend_param=".$extend_param."&";
      }
      if($extra_return_param != ""){
        $signStr = $signStr."extra_return_param=".$extra_return_param."&";
      }
      
      $signStr = $signStr."input_charset=".$input_charset."&";  
      $signStr = $signStr."interface_version=".$interface_version."&";  
      $signStr = $signStr."merchant_code=".$merchant_code."&";  
      $signStr = $signStr."notify_url=".$notify_url."&";    
      $signStr = $signStr."order_amount=".$order_amount."&";    
      $signStr = $signStr."order_no=".$order_no."&";    
      $signStr = $signStr."order_time=".$order_time."&";  

      if($pay_type != ""){
        $signStr = $signStr."pay_type=".$pay_type."&";
      }

      if($product_code != ""){
        $signStr = $signStr."product_code=".$product_code."&";
      } 
      if($product_desc != ""){
        $signStr = $signStr."product_desc=".$product_desc."&";
      }
      
      $signStr = $signStr."product_name=".$product_name."&";

      if($product_num != ""){
        $signStr = $signStr."product_num=".$product_num."&";
      } 
      if($redo_flag != ""){
        $signStr = $signStr."redo_flag=".$redo_flag."&";
      }
      if($return_url != ""){
        $signStr = $signStr."return_url=".$return_url."&";
      }   
      
      $signStr = $signStr."service_type=".$service_type;

      if($show_url != ""){  
        
        $signStr = $signStr."&show_url=".$show_url;
      }
      
        //echo $signStr."<br>";  
        
        
      
    /////////////////////////////   获取sign值（RSA-S加密）  /////////////////////////////////
        
      $merchant_private_key = "-----BEGIN PRIVATE KEY-----"."\r\n".wordwrap(trim($merchant_private_key),64,"\r\n",true)."\r\n"."-----END PRIVATE KEY-----";
      
      $merchant_private_key= openssl_get_privatekey($merchant_private_key);
      
      openssl_sign($signStr,$sign_info,$merchant_private_key,OPENSSL_ALGO_MD5);
      
      $sign = base64_encode($sign_info);
      
      $url = 'https://pay.dinpay.com/gateway?input_charset=UTF-8';
      
      $params = array(
        'sign'=>$sign,
        'merchant_code'=>$merchant_code,
        'bank_code'=>$bank_code,
        'order_no'=>$order_no,
        'order_amount'=>$order_amount,
        'service_type'=>$service_type,
        'input_charset'=>$input_charset,
        'notify_url'=>$notify_url,
        'interface_version'=>$interface_version,
        'sign_type'=>$sign_type,
        'order_time'=>$order_time,
        'product_name'=>$product_name,
        'client_ip'=>$client_ip,
        'extend_param'=>$extend_param,
        'extra_return_param'=>$extra_return_param,
        'pay_type'=>$pay_type,
        'product_code'=>$product_code,
        'product_desc'=>$product_desc,
        'product_num'=>$product_num,
        'return_url'=>$return_url,
        'show_url'=>$show_url,
        'redo_flag'=>$redo_flag
        );
      $ret = Common::httpRequest($url,'POST',$params);
      $result = json_decode($ret,true);
      
      echo "<pre>";
      print_r($params);
      echo "<pre>";
      
      var_dump($result);
    }

    public function be_promoter_by_balance()
    {
        $isPromoter = $this->model->table("district_promoter")->where("user_id=".$this->user['id'])->find();
        if($isPromoter) {
            $this->code = 1308;
            return;
        }
        $customer = $this->model->table('customer')->where('user_id='.$this->user['id'])->find();
        if($customer['balance']<3600) {
            $this->code = 1309;
            return;
        }
        $this->model->table('customer')->data(['balance'=>"`balance`-0.01"])->where('user_id='.$this->user['id'])->update();
        Log::balance(-3600, $this->user['id'], '','加盟商家服务费', 23);
        $inviter_info = $this->model->table("invite")->where("invite_user_id=".$this->user['id'])->find();
        $promoter_data['user_id']=$this->user['id'];
        $promoter_data['shop_name'] = $customer['real_name'];
        $promoter_data['type']=5;
        $promoter_data['create_time']=$promoter_data['join_time']=date("Y-m-d H:i:s");
        $promoter_data['hirer_id']=$inviter_info?$inviter_info['district_id']:1;
        $promoter_data['status']=1;
        $promoter_data['base_rate']='3.00';
        $this->model->table("district_promoter")->data($promoter_data)->insert();
        $this->code = 0;
        return;
    }

    public function be_vip_by_point()
    {
        $customer = $this->model->table('customer')->fields('point_coin')->where('user_id='.$this->user['id'])->find();
        $user = $this->model->table('user')->fields('is_vip')->where('id='.$this->user['id'])->find();
        if($user['is_vip']==1) {
            $this->code = 1310;
            return;
        }
        if($customer['point_coin']<3000) {
            $this->code = 1149;
            return;
        }
        $this->model->table("customer")->data(array('point_coin'=>"`point_coin`-3000"))->where('user_id='.$this->user['id'])->update();
        Log::pointcoin_log(3000, $this->user['id'], '', '积分兑换VIP', 14);
        $this->model->table('user')->data(['is_vip'=>1])->where('id='.$this->user['id'])->update();
        $this->code = 0;
        return;
    }
}
