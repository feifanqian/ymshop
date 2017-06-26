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
                //充值套餐判断，套餐充值是充值的银点
                $package = Filter::int(Req::args('package'));
                $address_id = Filter::int(Req::args('address_id'));
                $recommend = Filter::sql(Req::args('recommend'));
                $recharge_type =Filter::int(Req::args('recharge_type'));
                $gift = Filter::int(Req::args("gift"));
                $package = $package==null ? 0 : $package;
                if(in_array($package,array(1,2,3,4))){
                    $this->model = new Model();
                    $config = Config::getInstance();
                    $package_set = $config->get("recharge_package_set"); 
                    //判断礼物是否是套餐真实的
                    $gift_arr = explode("|",$package_set[$package]['gift']);
                    if($gift==NULL){
                        $gift = $gift_arr[0];
                    }else if(!in_array($gift, $gift_arr)){
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
                    if($recharge_type!=1){
                        $this->code = 1125;
                        return;
                    }
                    switch ($package){
                        case 0 : $recharge=$recharge;break;
                        case 1 : $recharge=600;break;
                        case 2 : $recharge=3600;break;
                        case 3 : $recharge=10800;break;
                        case 4 : $recharge=18000;break;
                    }
                    
                    if($recommend){//如果填写了推荐人
                        if(is_numeric($recommend) && strlen($recommend)==11){
                            $info = $this->model->table("customer")->where("mobile='$recommend'")->find();
                        }else{
                            $info = $this->model->table('user')->where("name='$recommend'")->find();
                        }
                        if(empty($info)){
                            $this->code = 1126;
                            return;
                        }else{
                            $recommend=  isset($info['id'])?$info['id']:$info['user_id'];
                        }
                    }
                }
                if(is_numeric($recharge)){
                    $user['id']=$this->user['id'];
                    if($user['id']==5||$user['id']==693||$user['id']==683||$user['id']==2||$user['id']==6||$user['id']==42||$user['id']==52){
                        $recharge = 0.01;
                    }
                    $recharge = round($recharge,2);
                    $paymentInfo = $payment->getPayment();
                    $data = array('user_id'=>$this->user['id'],'account' => $recharge, 'paymentName' => $paymentInfo['name'],'recharge_type'=>$recharge_type,'package'=>$package);
                    $packData = $payment->getPaymentInfo('recharge', $data);
                    $packData = array_merge($extendDatas, $packData);
                    $recharge_no = substr($packData['M_OrderNO'], 8);
                    if($package!=0){//如果是套餐充值
                         $recharge_gift_model = new Model();
                         $recharge_count = $this->model->table('recharge')->where("package in (1,2,3,4) and status =1 and user_id=".$this->user['id'])->count();
                         $gift_data['user_id']=$user['id'];
                         $gift_data['recharge_no']=$recharge_no;
                         $gift_data['package']=$package;
                         $gift_data['address_id']=$address_id;
                         $gift_data['gift']=$gift;
                         $gift_data['is_first']=$recharge_count>0?2:1;//判断是否是首次充
                         $gift_data['status']=0;
                         if($recommend){
                             $gift_data['recommend']=$recommend;
                         }
                         $recharge_gift_model ->table("recharge_gift")->data($gift_data)->insert();
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
//                    if($order['type']==4 && ($order['otherpay_status']==1 ||$order['otherpay_amount']==0)){
//                        $this->code =0 ;
//                        $this->content['order_id']=$order_id;
//                        $this->content['payment_id']=$payment_id;
//                        $this->content['senddata']=array('return_code'=>'ZERO_ORDER','return_msg'=>'0元华点订单，不需要在线支付');
//                        return;
//                    }
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

    //金点支付
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
    //银点支付
    public function pay_silver() {
        $model = new Model('user as us');
        $userInfo = $model->join('left join customer as cu on us.id = cu.user_id')->where('cu.user_id = ' . $this->user['id'])->find();
        if ($userInfo['pay_password_open'] == 1) {
            $pay_password = Req::args('pay_password');
            if ($userInfo['pay_password'] != CHash::md5($pay_password, $userInfo['pay_validcode'])) {
               $this->code = 1016;
               return;
            }
        }
        $sign = Req::post('sign');
        
        $args['attach'] = Req::args('attach');
        $args['total_fee'] = Req::args('total_fee');
        $args['order_no'] = Req::args('order_no');
        $args['return_url'] = Req::args('return_url');
        
        $total_fee = Filter::float(Req::post('total_fee'));
        $attach = Filter::int(Req::post('attach'));

        $return['attach'] = $attach;
        $return['total_fee'] = $total_fee;
        $return['order_no'] = Filter::sql(Req::post('order_no'));
        $return['return_url'] = Req::post('return_url');

        if (stripos($return['order_no'], 'recharge') !== false) {
            $this->code = 1142;
            return;
        }
        if (stripos($return['order_no'], 'district') !== false) {
            $this->code = 1137;
            return;
        }
        if (floatval($return['total_fee']) < 0 || $return['order_no'] == '' || $return['return_url'] == '') {
             $this->code = 1143;
             return;
        } else {
            $payment = new Payment($attach);
            $pay_silver = $payment->getPaymentPlugin();
            $classConfig = $pay_silver->getClassConfig();

            $filter_param = $pay_silver->filterParam($args);
            //对待签名参数数组排序
            $para_sort = $pay_silver->argSort($filter_param);
            $mysign = $pay_silver->buildSign($para_sort, $classConfig['partner_key']);
            if ($mysign == $sign) {
                $user_id = $this->user['id'];
                $model = new Model("customer");
                $customer = $model->where("user_id=" . $user_id)->find();
                if ($customer['silver_coin'] >= $total_fee) {
                    $order = $model->table("order")->where("order_no='" . $return['order_no'] . "' and user_id=" . $user_id)->find();
                    if ($order) {
                        if ($order['pay_status'] == 0) {
                            $silver_coin_component = Common::getSilverCoinComponent($user_id);
                            $package_info = Common::getPackageAreaGoodsAmount($return['order_no']);
                            if($silver_coin_component==false||$silver_coin_component['other_silver']<0){
                                file_put_contents('silverCoinErr.txt', date("Y-m-d H:i:s")."|========用户{$user_id}银点异常======|\n",FILE_APPEND);
                                $this->code = 1144;
                                exit();
                            }
                            /*
                             * 银点使用顺序 1：定向套餐银点 2：有华点限制的赠送银点 3：无华点限制的赠送银点 4：普通充值银点
                             */
                            if($order['type']==4){//为华点订单时，不能使用充值活动赠送的银点
                                if(($silver_coin_component['all']-$silver_coin_component['send_silver_limit'])>$package_info['all']&&($silver_coin_component['all']-$silver_coin_component['send_silver_limit']-$silver_coin_component['package_limit_silver'])>$package_info['other_amount']){
                                    $flag = $model->table("customer")->where("user_id=" . $user_id)->data(array('silver_coin' => "`silver_coin`-" . $total_fee))->update();
                                    if($flag){
                                        $real_record = 0.00;
                                        $note = array();
                                        //如果有套餐商品就先使用定向套餐银点
                                        if($package_info['package_amount']>0&&$silver_coin_component['package_limit_silver']>0){
                                            //先记录定向银点的使用
                                            $package_silver_record = Common::recordUseDetail4LimitSilverCoin($user_id, $package_info['package_amount'], $order['id']);
                                            $real_record += $package_silver_record;
                                            $note[] = $package_silver_record>0?"{$package_silver_record}定向银点":"";
                                        }
                                        if($real_record<$package_info['all']&&$silver_coin_component['send_silver_no_limit']>0){
                                            $send_silver_no_limit_record = Common::recordUseDetail4SendSilverCoin($user_id, $package_info['all']-$real_record, $order['id'],0);
                                            $real_record +=$send_silver_no_limit_record;
                                            $note[] = $send_silver_no_limit_record>0?"{$send_silver_no_limit_record}赠送无限制银点":"";
                                        }
                                        if($real_record<$package_info['all']){
                                            $other_silver_record = $package_info['all'] - $real_record;
                                            $note[] =$other_silver_record>0?"{$other_silver_record}普通银点":"";
                                        }
                                        Order::updateStatus($return['order_no'], $attach);
                                       //记录支付日志
                                        Log::silver_log((0 - $total_fee), $user_id, $return['order_no'],  "使用了". implode(",", $note),0);
                                        $this->code  =0;
                                        return;
                                   }else{
                                       $this->code = 1005;
                                       return;
                                   }
                                }else{
                                    $this->code = 1145;
                                    return;
                                }
                            }else{
                                 if(($silver_coin_component['all'])>$package_info['all']&&($silver_coin_component['all']-$silver_coin_component['package_limit_silver'])>$package_info['other_amount']){
                                    $flag = $model->table("customer")->where("user_id=" . $user_id)->data(array('silver_coin' => "`silver_coin`-" . $total_fee))->update();
                                    if($flag){
                                            $real_record = 0.00;
                                            $note =array();
                                            //1.如果有套餐商品就先使用定向套餐银点
                                            if($package_info['package_amount']>0&&$silver_coin_component['package_limit_silver']>0){
                                                $package_silver_record = Common::recordUseDetail4LimitSilverCoin($user_id, $package_info['package_amount'], $order['id']);
                                                $real_record += $package_silver_record;
                                                $note[]= $package_silver_record>0?"{$package_silver_record}定向银点":"";
                                            }
                                            //2.如果还没记录完，就继续，开始记录到赠送的有华点限制的银点
                                            if($real_record<$package_info['all']&&$silver_coin_component['send_silver_limit']>0){
                                                $send_silver_limit_record = Common::recordUseDetail4SendSilverCoin($user_id, $package_info['all']-$real_record, $order['id'],1);
                                                $real_record +=$send_silver_limit_record;
                                                $note[]= $send_silver_limit_record>0?"{$send_silver_limit_record}赠送银点（不可用于华点订单）":"";
                                            }
                                            //3.记录到赠送的没有限制的银点
                                            if($real_record<$package_info['all']&&$silver_coin_component['send_silver_no_limit']>0){
                                                $send_silver_no_limit_record = Common::recordUseDetail4SendSilverCoin($user_id, $package_info['all']-$real_record, $order['id'],0);
                                                $real_record +=$send_silver_no_limit_record;
                                                $note[]= $send_silver_no_limit_record>0?"{$send_silver_no_limit_record}赠送无限制银点":"";
                                            }
                                            //4.记录到普通充值的银点上
                                            if($real_record<$package_info['all']){
                                                $other_silver_record = $package_info['all'] - $real_record;
                                                $note[]=$other_silver_record>0?"{$other_silver_record}普通银点":"";
                                            }
                                            Order::updateStatus($return['order_no'], $attach);
                                            //记录支付日志
                                            Log::silver_log((0 - $total_fee), $user_id, $return['order_no'], "使用了". implode(",", $note),0);
                                            $this->code = 0;
                                            return;
                                        }else{
                                            $this->code = 1005;
                                            return;
                                        }
                                }else{
                                    $this->code = 1145;
                                    return;
                                }
                            }
                        } else {
                                $this->code = 1062;
                                return;
                        }
                    } else {
                         $this->code = 1063;
                          return;
                    }
                } else {
                     $this->code = 1146;
                     return;
                }
            } else {
                $this->code = 1001;
                $this->content['sign']=$mysign;
                $this->content['arg']=$args;
                return;
            }
        }
    }  
    public function huabipay_info(){
        $user_id = $this->user['id'];
        $order_id = Req::args("order_id");
        $order = new Model("order");
        $order_info = $order->where("id=$order_id and user_id = $user_id and pay_status = 0 and status < 4")->fields('voucher_id,order_amount,type,prom_id')->find();
        if(empty($order_info)){
            $this->code = 1063;
            return;
        }else{
            $order_goods = new Model("order_goods as og");
            $goods = $order_goods->where("og.order_id = $order_id and g.is_huabipay = 1")->join("left join goods as g on og.goods_id = g.id")->fields("og.goods_id,og.product_id,og.goods_nums,og.real_price,g.huabipay_set")->findAll();
           if(empty($goods) || $order_info['type']== 2 ||  $order_info['type']==3||$order_info['voucher_id']!= 0||$order_info['prom_id']!=0){
               $this->code = 1102;
               return;
           }else{
               //判断华点用
                $productarr =array();
                $goods_arr = array();
                $product_amount=array();
                foreach ($goods as $item) {
                    $productarr[$item['product_id']] = $item['goods_nums'];
                    $goods_arr[]=$item['goods_id'];
                    if(!isset($product_amount[$item['product_id']])){
                         $product_amount[$item['product_id']]=0.00;
                    }
                    $product_amount[$item['product_id']]+=$item['real_price']*$item['goods_nums'];
                }
                
                $huadian_result =Common::parserHuadianOrder($goods_arr, $productarr, $product_amount);
                if($huadian_result===false){
                    $this->code = 1102;
                    return;
                }
                
               $this->code = 0;
               $this->content['huabipay_amount']=$huadian_result['huadian'];
               $this->content['otherpay_amount']=$huadian_result['rmb'];
               $this->content['shop_huabi_account']="wlucky2101";
               
            }
        }
    }
    public function huabi_pay(){
        $user_id = $this->user['id'];
        $order_id = Req::args("order_id");
        $huabi_account = Req::args("huabi_account");
        $order = new Model("order");
        $order_info = $order->where("id=$order_id and user_id = $user_id and pay_status = 0 and status < 4")->fields('id,order_amount,type,voucher_id,prom_id')->find();
        if(empty($order_info)){
            $this->code = 1063;
            return;
        }else{
            if($order_info['type']==4){
                $this->code=0;
                $this->content=$order_info['id'];
                return;
            }
            if($huabi_account ==""){
                $this->code = 1103;
                return;
            }
            $order_goods = new Model("order_goods as og");
            $goods = $order_goods->where("og.order_id = $order_id and g.is_huabipay = 1")->join("left join goods as g on og.goods_id = g.id")->fields("og.goods_id,og.product_id,og.goods_nums,og.real_price,g.huabipay_set")->findAll();
           if(empty($goods) || $order_info['type']!= 0||$order_info['voucher_id']!= 0||$order_info['prom_id']!=0){
               $this->code = 1102;
               return;
           }else{
               //判断华点用
                $productarr =array();
                $goods_arr = array();
                $product_amount=array();
                foreach ($goods as $item) {
                    $productarr[$item['product_id']] = $item['goods_nums'];
                    $goods_arr[]=$item['goods_id'];
                    if(!isset($product_amount[$item['product_id']])){
                         $product_amount[$item['product_id']]=0.00;
                    }
                    $product_amount[$item['product_id']]+=$item['real_price']*$item['goods_nums'];
                }
                $huadian_result =Common::parserHuadianOrder($goods_arr, $productarr, $product_amount);
                if($huadian_result===false){
                    $this->code = 1102;
                    return;
                }
                if($huadian_result['rmb']==0){
                    $otherpay_status=1;
                }else{
                    $otherpay_status=0;
                }
                //新华点订单
               $result = $order->data(array("huabipay_amount"=>$huadian_result['huadian'],"otherpay_amount"=>$huadian_result['rmb'],"huabipay_status"=>0,"otherpay_status"=>$otherpay_status,"huabi_account"=>$huabi_account,"type"=>4,'is_return'=>0,'is_new'=>1))->where("id =$order_id and user_id = $user_id")->update();
               if($result){
                 $this->code = 0;
                 $this->content=$order_info['id'];
               }else{
                   $this->code = 1005;
               }
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
    
//    public function pay_district() {
//        // 获得payment_id 获得相关参数
//        $payment_id = Filter::int(Req::args('payment_id'));
//        $apply_id = Filter::int(Req::args('apply_id'));
//        $district_info = $this->model->table("district_apply")->where("id={$apply_id} and user_id=".$this->user['id'])->find();
//        if(!empty($district_info)){
//            if($district_info['pay_status']==0){
//                    $payment = new Payment($payment_id);
//                    $paymentPlugin = $payment->getPaymentPlugin();
//                    if(!is_object($paymentPlugin)){
//                        $this->code =1113;
//                        exit();
//                    }
//                    $paymentInfo = $payment->getPayment();
//                    $district_order_no = "district".sprintf("%08d",$district_info['id']);
//                    $config_all = Config::getInstance();
//                    $set = $config_all->get('district_set');
//                    if(isset($set['join_fee'])){
//                        $amount = $set['join_fee'];
//                    }else{
//                        $amount = 10000;
//                    }
//                    if(isset($this->user['id'])&&($this->user['id']==5||$this->user['id']==693||$this->user['id']==683||$this->user['id']==2||$this->user['id']==6||$this->user['id']==42||$this->user['id']==52)){
//                        $amount = 0.01;
//                    }
//                    $data = array('amount' => $amount,'district_order_no'=>$district_order_no,'district_id'=>$district_info['id']);
//                    $packData = $payment->getPaymentInfo('district', $data);
//                    $sendData = $paymentPlugin->packData($packData);
//                    if (!$paymentPlugin->isNeedSubmit()) {
//                         if(isset($sendData['tn'])){
//                                $this->code =0;
//                                $this->content['tn']= $sendData['tn'];
//                                return;
//                           }else{
//                               $this->code =0;
//                               $this->content['senddata']=$sendData;
//                               exit();
//                          }
//                    }
//                    if (!empty($sendData)) {
//                        $this->content = array(
//                                'apply_id'=>$apply_id,
//                                'payment_id' => $payment_id,
//                                'senddata' => $sendData,
//                            );
//                        $this->content['senddata'] = $sendData;
//                        $this->code = 0;
//                    }
//            }else if($district_info['pay_status']==1){
//                $this->code=1138;
//                return;
//            }
//        }else{
//            $this->code = 1139;
//            return;
//        }
//        
//    }
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
