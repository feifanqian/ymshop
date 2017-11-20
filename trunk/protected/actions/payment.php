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
                    
                     if($package==4){
                        $address_id = Filter::int(Req::args('address_id'));
                        $gift = Filter::int(Req::args("gift"));
                        //判断礼物是否是套餐真实的
                        $gift_arr = explode("|", $package_set[4]['gift']);
                        if (!in_array($gift, $gift_arr)) {
                            $this->code  = 1122;
                            return;
                        }
                        if(!$address_id){
                            $this->code = 1124;
                            return;
                        }else{
                            $address_isset = $this->model->table("address")->where("user_id =".$this->user['id']." and id =".$address_id)->count();
                            if($address_isset==0){
                                 $this->code = 1124;
                                 return;
                            }
                        }
                    }
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
                    $data = array('account' => $recharge, 'paymentName' => $paymentInfo['name'],'package' => $package);
                    $packData = $payment->getPaymentInfo('recharge', $data);
                    $packData = array_merge($extendDatas, $packData);
                    $recharge_no = substr($packData['M_OrderNO'], 8);
                    if ($package == 4) {
                        $recharge_gift_model = new Model();
//                      $recharge_count = $model->table('recharge')->where("package in (1,2,3,4) and status =1 and user_id=" . $user['id'])->count();
                        $gift_data['user_id'] = $user['id'];
                        $gift_data['recharge_no'] = $recharge_no;
                        $gift_data['package'] = $package;
                        $gift_data['address_id'] = $address_id;
                        $gift_data['gift'] = $gift;
//                      $gift_data['is_first'] = $recharge_count > 0 ? 2 : 1; //判断是否是首次充
                        $gift_data['status'] = 0;
                        
                        $recharge_gift_model->table("recharge_gift")->data($gift_data)->insert();
                    }
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
                                        return;
                                     }else{
                                        $this->code =0;
                                        $this->content['senddata']=$sendData;
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
       $order_amount = (Req::args('order_amount'));
       $randomstr=rand(1000000000000,9999999999999);
       $seller_id = Filter::int(Req::args('seller_id'));//卖家用户id
       if(!$seller_id){
        $this->code = 1158;
       }
       if(!$seller_id || $seller_id==0){
         $seller_id = Filter::int(Req::args('seller_ids'));
       }
       if(!$payment_id){
         $this->code = 1157;
       }
       $user_id = $this->user['id'];
       $invite=$this->model->table('invite')->where('invite_user_id='.$user_id)->find();
       if($invite){
           $invite_id=intval($invite['user_id']);//邀请人用户id
       }else{
           $invite_id=1;
       }
       
       $user=$this->model->table('customer')->fields('mobile,real_name')->where('user_id='.$user_id)->find();
       if(!$user){
        $this->code = 1159;
       }
       $accept_name = Session::get('openname');
       $config = Config::getInstance()->get("district_set");
       $data['type']=8;
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
       // $open=$this->model->table('oauth_user')->where("oauth_type = 'wechat' and user_id=".$user_id)->find();
       // if(!$open){
       //    $this->code = 1160;
       // }
       // if(!$open['open_id']){
       //   $this->code = 1162;
       // } 
       $params = array();
       $params["cusid"] = AppConfig::CUSIDS;
       // $params["cusid"] = "1486189412";
       $params["appid"] = AppConfig::APPIDS;
       // $params["appid"] = "wx167f2c4da1f798b0";
       $params["version"] = AppConfig::APIVERSION;
       $params["trxamt"] = $order_amount*100;
       $params["reqsn"] = $order_no;//订单号,自行生成
       $params["paytype"] = "0";
       $params["randomstr"] = $randomstr;//
       $params["body"] = "商品名称";
       $params["remark"] = "备注信息";
       // $params["acct"] = $open['open_id'];
       $params["open_id"] = '';
       // $params["limit_pay"] = "no_credit";
       $params["notify_url"] = 'http://www.ymlypt.com/payment/async_callbacks';
       $params["sign"] = AppUtil::SignArray($params,AppConfig::APPKEYS);//签名
       
       $paramsStr = AppUtil::ToUrlParams($params);
       $url = AppConfig::APIURL . "/pay";
       $rsp = AppUtil::Request($url, $paramsStr);

       $rspArray = json_decode($rsp, true);
       if(AppUtil::ValidSigns($rspArray)){
           if(isset($rspArray['payinfo'])){
               $this->code = 0;
               $this->content = array(
                        'order_id' => $order_id,
                        'payment_id' => $payment_id,
                        'senddata' => json_decode($rspArray['payinfo'],true),
                    );
           }else{
             $this->code = 1161;
             $this->content = array(
                        'senddata' => $rspArray['errmsg'],
                    );
           }       
       }else{
           $this->code = 1065;
       }
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
                if ($order) {
                    if ($order['pay_status'] == 0 ) {
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
                            Order::updateStatus($order_no, 15);
                            //记录支付日志
                            Log::balance((0 - $total_fee), $user_id, $order_no, '购物下单');
                        }
                        $this->code = 0;
                        return;
                    } else {
                        $this->code = 1062;
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
                default:
                     $notin="12";
                    break;
            }
        }else{
            $client_type =9999;
        }
        $this->model = new Model("payment as pa");
        $paytypelist = $this->model->fields("pa.id,pa.pay_name,pa.description,pa.pay_fee,pa.sort,pp.logo,pp.class_name")->join("left join pay_plugin as pp on pa.plugin_id = pp.id")
                        ->where("pa.status = 0 and pa.plugin_id not in({$notin}) and pa.client_type = $client_type")->order("pa.sort desc")->findAll();
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

}
