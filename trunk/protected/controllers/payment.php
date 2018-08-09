<?php

class PaymentController extends Controller {

    public $layout = '';
    public $model = null;
    private $user;
    public $needRightActions = array('dopay' => true,'dopays'=>true, 'callback' => true, 'pay_district' => true, 'pay_silver' => true, 'pay_balance' => true);

    public function init() {
        header("Content-type: text/html; charset=" . $this->encoding);
        $this->model = new Model();
        $this->arrayXml = new ArrayAndXml();
        $safebox = Safebox::getInstance();
        $this->user = $safebox->get('user');
        // $this->param = array();
        // $this->param['pfxpath'] = 'http://' . $_SERVER['HTTP_HOST'] . "/trunk/protected/classes/yinpay/certs/shanghu_test.pfx";
        // $this->param['businessgatecerpath'] = 'http://' . $_SERVER['HTTP_HOST'] . "/trunk/protected/classes/yinpay/certs/businessgate.cer";
        // $this->param['pfxpassword'] = "123456";
    }

    public function checkRight($actionId) {
        $rights = $this->needRightActions;
        if (isset($rights[$actionId]) && $rights[$actionId]) {
            if (isset($this->user['name']) && $this->user['name'] != null)
                return true;
            else
                return false;
        }else {
            return true;
        }
    }

    public function noRight() {
        $this->redirect("/simple/login");
    }

    //余额支付方式，服务器端处理
    public function pay_balance() {
        $model = new Model('user as us');
        $userInfo = $model->join('left join customer as cu on us.id = cu.user_id')->where('cu.user_id = ' . $this->user['id'])->find();
        if ($userInfo['pay_password_open'] == 1) {
            $pay_password = Req::args('pay_password');
            $order_id = Req::post('order_id');
            $payment_id = Req::post('payment_id');
            if ($userInfo['pay_password'] != CHash::md5($pay_password, $userInfo['pay_validcode'])) {
                $msg = '支付密码错误';
                $this->redirect('/payment/dopay/order_id/' . $order_id . '/payment_id/' . $payment_id, true, array('msg' => $msg));
                exit;
            }
        }
        $sign = Req::post('sign');
        $args = Req::post();
        unset($args['sign']);
        unset($args['pay_password']);
        unset($args['order_id']);
        unset($args['payment_id']);

        $total_fee = Filter::float(Req::post('total_fee'));
        $attach = Filter::int(Req::post('attach'));

        $return['attach'] = $attach;
        $return['total_fee'] = $total_fee;
        $return['order_no'] = Filter::sql(Req::post('order_no'));
        $return['return_url'] = Req::post('return_url');

        if (stripos($return['order_no'], 'recharge_') !== false) {
            $msg = array('type' => 'fail', 'msg' => '余额支付方式,不能用于在线充值功能！');
            $this->redirect('/index/msg', false, $msg);
            exit;
        }
        if (floatval($return['total_fee']) < 0 || $return['order_no'] == '' || $return['return_url'] == '') {
            $msg = array('type' => 'fail', 'msg' => '支付参数不正确！');
            $this->redirect('/index/msg', false, $msg);
        } else {

            $payment = new Payment($attach);
            $pay_balance = $payment->getPaymentPlugin();
            $classConfig = $pay_balance->getClassConfig();

            $filter_param = $pay_balance->filterParam($args);
            //对待签名参数数组排序
            $para_sort = $pay_balance->argSort($filter_param);
            $mysign = $pay_balance->buildSign($para_sort, $classConfig['partner_key']);

            if ($mysign == $sign) {
                $user_id = $this->user['id'];
                $model = new Model("customer");
                $customer = $model->where("user_id=" . $user_id)->find();
                if ($customer['balance'] >= $total_fee) {
                    $order = $model->table("order")->where("order_no='" . $return['order_no'] . "' and user_id=" . $user_id)->find();
                    if ($order) {
                        if ($order['pay_status'] == 0) {
                            $flag = $model->table("customer")->where("user_id=" . $user_id)->data(array('balance' => "`balance`-" . $total_fee))->update();
                            $return['order_status'] = 'TINY_SECCESS';

                            //记录支付日志
                            Log::balance((0 - $total_fee), $user_id, $return['order_no'], '购物下单');

                            $filter_param = $pay_balance->filterParam($return);
                            $para_sort = $pay_balance->argSort($filter_param);
                            $sign = $pay_balance->buildSign($para_sort, $classConfig['partner_key']);
                            $prestr = $pay_balance->createLinkstring($para_sort);

                            $nextUrl = urldecode($return['return_url']);
                            if (stripos($nextUrl, '?') === false) {
                                // $return_url = $nextUrl.'?'.$prestr;
                            } else {
                                //$return_url = $nextUrl.'&'.$prestr;
                            }
                            $return_url = $nextUrl; //.= '&sign='.$sign;
                            $return['sign'] = $sign;
                            
                            $this->redirect("$return_url", true, $return);
                            //header('location:'.$return_url,true,$result);
                            exit;
                        } else {
                            $msg = array('type' => 'fail', 'msg' => '订单已经处理过，请查看订单信息！');
                            $this->redirect('/index/msg', false, $msg);
                            exit;
                        }
                    } else {
                        $msg = array('type' => 'fail', 'msg' => '订单不存在！');
                        $this->redirect('/index/msg', false, $msg);
                        exit;
                    }
                } else {
                    $msg = array('type' => 'fail', 'msg' => '余额不足,请选择其它支付方式！');
                    $this->redirect('/index/msg', false, $msg);
                    exit;
                }
            } else {
                $msg = array('type' => 'fail', 'msg' => '签名错误！');
                $this->redirect('/index/msg', false, $msg);
                exit;
            }
        }
    }

    //货到付款方式，服务器端处理
    public function pay_received() {

        $sign = Req::post('sign');
        $args = Req::post();
        unset($args['sign']);

        $total_fee = Filter::float(Req::post('total_fee'));
        $attach = Filter::int(Req::post('attach'));

        $return['attach'] = $attach;
        $return['total_fee'] = $total_fee;
        $return['order_no'] = Filter::sql(Req::post('order_no'));
        $return['return_url'] = Req::post('return_url');

        if (stripos($return['order_no'], 'recharge_') !== false) {
            $msg = array('type' => 'fail', 'msg' => '货到贷款方式,不能用于在线充值功能！');
            $this->redirect('/index/msg', false, $msg);
            exit;
        }
        if (floatval($return['total_fee']) <= 0 || $return['order_no'] == '' || $return['return_url'] == '') {
            $msg = array('type' => 'fail', 'msg' => '支付参数不正确！');
            $this->redirect('/index/msg', false, $msg);
        } else {
            $payment = new Payment($attach);
            $pay_received = $payment->getPaymentPlugin();
            $classConfig = $pay_received->getClassConfig();

            $filter_param = $pay_received->filterParam($args);
            //对待签名参数数组排序
            $para_sort = $pay_received->argSort($filter_param);
            $mysign = $pay_received->buildSign($para_sort, $classConfig['partner_key']);

            if ($mysign == $sign) {
                $user_id = $this->user['id'];
                $model = new Model("customer");
                $customer = $model->where("user_id=" . $user_id)->find();
                if ($customer) {
                    $order = $model->table("order")->where("order_no='" . $return['order_no'] . "' and user_id=" . $user_id)->find();
                    if ($order) {
                        if ($order['pay_status'] == 0) {
                            //$flag = $model->table("customer")->where("user_id=".$user_id)->data(array('balance'=>"`balance`-".$total_fee))->update();
                            $return['order_status'] = 'TINY_SECCESS';

                            //记录支付日志
                            //Log::balance((0-$total_fee),$user_id,'通过货到付款的方式进行商品购买,订单编号：'.$return['order_no']);

                            $filter_param = $pay_received->filterParam($return);
                            $para_sort = $pay_received->argSort($filter_param);
                            $sign = $pay_received->buildSign($para_sort, $classConfig['partner_key']);
                            $prestr = $pay_received->createLinkstring($para_sort);

                            $nextUrl = urldecode($return['return_url']);
                            $return_url = $nextUrl;
                            $return['sign'] = $sign;
                            $this->redirect("$return_url", true, $return);
                            exit;
                        } else {
                            $msg = array('type' => 'fail', 'msg' => '订单已经处理过，请查看订单信息！');
                            $this->redirect('/index/msg', false, $msg);
                            exit;
                        }
                    } else {
                        $msg = array('type' => 'fail', 'msg' => '订单不存在！');
                        $this->redirect('/index/msg', false, $msg);
                        exit;
                    }
                } else {
                    $msg = array('type' => 'fail', 'msg' => '用户不存在！');
                    $this->redirect('/index/msg', false, $msg);
                    exit;
                }
            } else {
                $msg = array('type' => 'fail', 'msg' => '签名错误！');
                $this->redirect('/index/msg', false, $msg);
                exit;
            }
        }
    }

    public function doPay() {
        // 获得payment_id 获得相关参数
        $payment_id = Filter::int(Req::args('payment_id'));
        $order_id = Filter::int(Req::args('order_id'));
        $recharge = Req::args('recharge');
        $rate = Req::args('base_rate');
        $extendDatas = Req::args();
        if ($payment_id) {
            $payment = new Payment($payment_id);
            $paymentPlugin = $payment->getPaymentPlugin();
            if (!is_object($paymentPlugin)) {
                $this->redirect("/index/msg", false, array('type' => 'fail', 'msg' => '支付方式不存在，或被禁用'));
                exit();
            }
            $payment_info = $payment->getPayment();
            //充值处理
            if ($recharge != null) {
                //充值套餐判断
                $package = Filter::int(Req::args('package'));
                
                $package = $package == null ? 0 : $package;
                
                if (in_array($package, array(1, 2, 3, 4))) {
                    $safebox = Safebox::getInstance();
                    $user = $safebox->get('user');
                    $config = Config::getInstance();
                    $package_set = $config->get("recharge_package_set");
                    
                    // if($package==4){
                    //     $address_id = Filter::int(Req::args('address_id'));
                    //     $gift = Filter::int(Req::args("gift"));
                    //     //判断礼物是否是套餐真实的
                    //     $gift_arr = explode("|", $package_set[4]['gift']);
                    //     if (!in_array($gift, $gift_arr)) {
                    //         $this->redirect("/index/msg", false, array('type' => 'fail', 'msg' => '抱歉，您选择的套餐礼品不正确'));
                    //         exit();
                    //     }
                    //     if (!$address_id) {
                    //         $this->redirect("/index/msg", false, array('type' => 'fail', 'msg' => '信息错误,未选择地址'));
                    //         exit();
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
                if (is_numeric($recharge)) {
                    $safebox = Safebox::getInstance();
                    $user = $safebox->get('user');
                    // $recharge = round($recharge, 2);
                    $paymentInfo = $payment->getPayment();
                    $data = array('account' => $recharge, 'paymentName' => $paymentInfo['name'],'package' => $package,'rate'=>$rate);
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
                        exit();
                    }
                    $order_amount = $recharge;
                    $order_no = $recharge_no;
                } else {
                    $this->redirect("/index/msg", false, array('type' => 'fail', 'msg' => '请输入正确的充值金额'));
                    exit();
                }
            } else if ($order_id != null) {
                $model = new Model('order');
                $order = $model->where('id=' . $order_id)->find();
                if ($order['pay_status'] == '1') {
                    $this->redirect("/index/msg", false, array('type' => 'fail', 'msg' => '已付款订单，无法再次进行支付。'));
                    exit();
                }
                if ($order) {
                    if($order['type']==2){
                        $flash_model = new Model('flash_sale');
                        $flash_sale = $flash_model->where('id='.$order['prom_id'])->find();
                        if($flash_sale){
                            if($flash_sale['goods_num']>=$flash_sale['max_num'] || $flash_sale['is_end']==1){
                                $this->redirect("/index/msg", false, array('type' => 'fail', 'msg' => '支付晚了，抢购活动已结束'));
                                exit();
                            }
                        }
                    }
                    if ($order['order_amount'] == 0 && $payment_info['class_name'] != 'balance') {
                        $this->redirect("/index/msg", false, array('type' => 'fail', 'msg' => '0元订单，仅限预付款支付，请选择预付款支付方式。'));
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
                        case '7':
                            $order_delay = isset($config_other['other_order_delay_pointwei']) ? intval($config_other['other_order_delay_pointwei']) : 0;
                            break;    
                        default:
                            $order_delay = 0;
                            break;
                    }

                    $time = strtotime("-" . $order_delay . " Minute");
                    $create_time = strtotime($order['create_time']);
                    if ($create_time >= $time || $order_delay == 0) {
                        //取得所有订单商品
                        $order_goods = $model->table('order_goods')->fields("product_id,goods_nums")->where('order_id=' . $order_id)->findAll();
                        $product_ids = array();
                        $order_products = array();
                        foreach ($order_goods as $value) {
                            $product_ids[] = $value['product_id'];
                            $order_products[$value['product_id']] = $value['goods_nums'];
                        }
                        $product_ids = implode(',', $product_ids);

                        $products = $model->table('products')->fields("id,store_nums")->where("id in ($product_ids)")->findAll();
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
                            if ($order['type'] == 1 || $order['type'] == 2 || $order['type']==6) {
                                if ($order['type'] == 1) {
                                    $prom_name = '团购';
                                    $prom_table = "groupbuy";
                                } else if($order['type'] == 2) {
                                    $prom_name = '抢购';
                                    $prom_table = "flash_sale";
                                }else if($order['type'] == 6){
                                    $prom_name = '抢购';
                                    $prom_table = "flash_sale";
                                }
                                
                                $prom = $model->table($prom_table)->where("id=" . $order['prom_id'])->find();
                                if ($prom) {
                                    if($order['type']!=6){
                                        if (time() > strtotime($prom['end_time']) || $prom['max_num'] <= $prom["goods_num"]) {
                                            $model->table("order")->data(array('status' => 6))->where('id=' . $order_id)->update();
                                            $this->redirect("/index/msg", false, array('type' => 'fail', 'msg' => '支付晚了，' . $prom_name . "活动已结束。"));
                                            exit;
                                        }
                                    }else{
                                         if (time() > strtotime($prom['end_date']) || $prom['max_sell_count'] <= $prom["order_count"]) {
                                            $model->table("order")->data(array('status' => 6))->where('id=' . $order_id)->update();
                                            if($order['pay_point']>0){
                                                $model->table("customer")->where("user_id=" . $order['user_id'])->data(array("point_coin" => "`point_coin`+" . $order['pay_point']))->update();
                                                Log::pointcoin_log($order['pay_point'], $order['user_id'], $order['order_no'], "取消订单，退回积分", 2);
                                            }
                                            $this->redirect("/index/msg", false, array('type' => 'fail', 'msg' => '支付晚了，' . $prom_name . "活动已结束。"));
                                            exit;
                                        }
                                    }
                                }
                            }
                            $packData = $payment->getPaymentInfo('order', $order_id);
                            $packData = array_merge($extendDatas, $packData);
                            $sendData = $paymentPlugin->packData($packData);
                            if (!$paymentPlugin->isNeedSubmit()) {
                                exit();
                            }
                        } else {
                            if ($order['status'] < 4 && $order['pay_status'] == 0) {
                                $model->table("order")->data(array('status' => 6))->where('id=' . $order_id)->update();
                                
                                if($order['type']==5||$order['type']==6){
                                    if($order['pay_point']>0){
                                        $model->table("customer")->where("user_id=" . $order['user_id'])->data(array("point_coin" => "`point_coin`+" . $order['pay_point']))->update();
                                        Log::pointcoin_log($order['pay_point'], $order['user_id'], $order['order_no'], "取消订单，退回积分", 2);
                                    }
                                }
                                
                                $this->redirect("/index/msg", false, array('type' => 'fail', 'msg' => '支付晚了，库存已不足。'));
                            } else {
                                $this->redirect("/index/msg", false, array('type' => 'fail', 'msg' => '已付款订单，无法再次进行支付。'));
                            }
                            exit;
                        }
                    } else {
                        $model->data(array('status' => 6))->where('id=' . $order_id)->update();
                        if($order['type']==5||$order['type']==6){
                            if($order['pay_point']>0){
                                $model->table("customer")->where("user_id=" . $order['user_id'])->data(array("point_coin" => "`point_coin`+" . $order['pay_point']))->update();
                                Log::pointcoin_log($order['pay_point'], $order['user_id'], $order['order_no'], "取消订单，退回积分", 2);
                            }
                        }
                        $this->redirect("/index/msg", false, array('type' => 'fail', 'msg' => '订单超出了规定时间内付款，已作废.'));
                        exit;
                    }
                }
                $order_amount = $order['order_amount'];
                $order_no = $order['order_no'];
            }
            if (!empty($sendData)) {
                $this->assign("paymentPlugin", $paymentPlugin);
                $this->assign("sendData", $sendData);
                if ($paymentPlugin instanceof pay_balance) {
                    $model = new Model('user as us');
                    $userInfo = $model->join('left join customer as cu on us.id = cu.user_id')->where('cu.user_id = ' . $this->user['id'])->find();
                    if($this->user['id']==126935 || $this->user['id']==126676 || $this->user['id']==126663 || $this->user['id']==126243 || $this->user['id']==126002){
                        $this->redirect("/index/msg", false, array('type' => 'fail', 'msg' => '账号已被冻结，请联系官方客服！'));
                        exit;
                    }
                    if ($userInfo['pay_password_open'] == 1) {
                        $this->assign('pay_balance', true);
                        $this->assign('userInfo', $userInfo);
                        $this->assign('order_id', $order_id);
                        $this->assign('payment_id', $payment_id);
                    }
                }

                //*************使用通联支付************************
                   // $randomstr=rand(1000000000000,9999999999999);
                   // $open=$this->model->table('oauth_user')->where('user_id='.$this->user['id'])->find();
                   // $params = array();
                   // $params["cusid"] = AppConfig::CUSID;
                   // $params["appid"] = AppConfig::APPID;
                   // $params["version"] = AppConfig::APIVERSION;
                   // $params["trxamt"] = $order_amount*100;
                   // $params["reqsn"] = $order_no;//订单号,自行生成
                   // $params["paytype"] = "W02";
                   // $params["randomstr"] = $randomstr;//
                   // $params["body"] = "商品名称";
                   // $params["remark"] = "备注信息";
                   // $params["acct"] = $open['open_id'];
                   // // $params["limit_pay"] = "no_credit";
                   // // $params["notify_url"] = "http://172.16.2.46:8080/vo-apidemo/OrderServlet";
                   // $params["notify_url"] = 'http://www.ymlypt.com/payment/async_callback';
                   // // $params["notify_url"] = Url::fullUrlFormat("/payment/async_callback");
                   // // $params["notify_url"] = 'http://'.$_SERVER['HTTP_HOST'].'/payment/async_callback';
                   // $params["sign"] = AppUtil::SignArray($params,AppConfig::APPKEY);//签名

                   // $paramsStr = AppUtil::ToUrlParams($params);
                   // $url = AppConfig::APIURL . "/pay";
                   // $rsp = AppUtil::Request($url, $paramsStr);
                   // $rspArray = json_decode($rsp, true);
                   // if(AppUtil::ValidSigns($rspArray)){
                   //     if(isset($rspArray['payinfo'])){
                   //         $this->assign('payinfo',$rspArray['payinfo']);
                   //         Session::set('payinfo',$rspArray['payinfo']);
                   //     }
                   //  }else{
                   //      echo "fail";
                   //  }
                  //*******************************************
                   
                $this->assign('offline',0);
                $this->redirect('pay_form', false);
            } else {
                $this->redirect("/index/msg", false, array('type' => 'fail', 'msg' => '需要支付的订单已经不存在。'));
                exit();
            }
        } else {
            echo "fail";
        }
    }

    public function dopays(){
       if($this->user['id']!=1776){
           $payment_id = Filter::int(Req::args('payment_id'));
           $order_no = Req::args('order_no');
           $order_amount = (Req::args('order_amount'));
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
           if($order_amount==0){
            $this->redirect("/index/msg", false, array('type' => 'fail', 'msg' => '请输入正确的充值金额'));
            exit();
           }
           if(!$seller_id || $seller_id==0){
             $seller_id = Filter::int(Req::args('seller_ids'));
           }
           $user_id = $this->user['id'];
           $invite=$this->model->table('invite')->where('invite_user_id='.$user_id)->find();
           if($invite){
               $invite_id=intval($invite['user_id']);//邀请人用户id
           }else{
               $invite_id=1;
           }
           Session::set('invite_id',$invite_id);
           $user=$this->model->table('customer')->fields('mobile')->where('user_id='.$user_id)->find();
           if($user){
            $mobile=$user['mobile'];
           }else{
            $mobile='';
           }
           $accept_name = Session::get('openname');
           $config = Config::getInstance()->get("district_set");
           $data['type']=1;
           $data['order_no'] = $order_no;
           $data['user_id'] = $user_id;
           $data['payment'] = $payment_id;
           $data['status'] = 2;
           $data['pay_status'] = 0;
           $data['accept_name'] = $accept_name;
           $data['mobile'] = $mobile;
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
           }else{
              $order_id = $exist['id']; 
              if($exist['status']==3 && $exist['pay_status']==1){
                $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '支付信息错误', "content" => "支付成功，请勿重复支付"));
              }
           }

           $payment = new Payment($payment_id);
           $paymentPlugin = $payment->getPaymentPlugin();

           $packData = $payment->getPaymentInfo('offline_order', $order_id);
            $sendData = $paymentPlugin->packData($packData);
            $this->assign("paymentPlugin", $paymentPlugin);
            $this->assign("sendData", $sendData);
            $this->assign("offline",1);
            $this->redirect('pay_form', false);
       }else{
           exit();
       }    
    }

    public function dinpay(){
        $payment_id = Filter::int(Req::args('payment_id'));
           $order_no = Req::args('order_no');
           $order_amount = Req::args('order_amount');
           $randomstr=rand(1000000000000,9999999999999);
           $seller_id = Filter::int(Req::args('seller_id'));//卖家用户id
           if(!$seller_id || $seller_id==0){
             $seller_id = Filter::int(Req::args('seller_ids'));
           }
           $user_id = $this->user['id'];
           $invite=$this->model->table('invite')->where('invite_user_id='.$user_id)->find();
           if($invite){
               $invite_id=intval($invite['user_id']);//邀请人用户id
           }else{
               $invite_id=1;
           }
           Session::set('invite_id',$invite_id);
           $user=$this->model->table('customer')->fields('mobile')->where('user_id='.$user_id)->find();
           if($user){
            $mobile=$user['mobile'];
           }else{
            $mobile='';
           }
           $accept_name = Session::get('openname');
           $config = Config::getInstance()->get("district_set");
           $data['type']=0;
           $data['order_no'] = $order_no;
           $data['user_id'] = $user_id;
           $data['payment'] = $payment_id;
           $data['status'] = 2;
           $data['pay_status'] = 0;
           $data['accept_name'] = $accept_name;
           $data['mobile'] = $mobile;
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
           $data['third_pay'] = 1;
           $model = new Model('order_offline');
           $exist=$model->where('order_no='.$order_no)->find();
           //防止重复生成同笔订单
           if(!$exist){
              $order_id=$model->data($data)->insert();
           }else{
              $order_id = $exist['id']; 
           }
           /*******************智付支付**********************/
        $merchant_private_key='MIICeAIBADANBgkqhkiG9w0BAQEFAASCAmIwggJeAgEAAoGBAKwJnd8sHJojXIFxuf4Ibsdtc2cJHPlN2d/IKMBw5cuoRknNeMCTlR89MxEqfuPqYR7o1dGgOiehswR9T4vWByzhJlrLEFcgOcJFnDINzU9iZW4RcRKf187sLXYL8b5Vf5WjEfudXjnxSGt8HXPe+V0VimUVaIAQSWvBCWgHkFV/AgMBAAECgYBivF40EJAV0serrwatCk/x+xopf2x2lLy/l5Pz5pesS9aTUu7Dr6/9LtWZO4d57TFyWPUmi0v1JPOmVvkJa3vPz6HhZIzg5M4jd23Kj8fl94PaTSyGM3NEMRJDLPxWEB9ydR60VtRlieCf2lyH0JSKa5YMS09A6ks13W4SVNRqaQJBAOF22itr0KonXZaQxNIOrnGifCvBA11cKV1SMxT5iLOuYu5j2VOZNExC5oD4j1fkT/7kEq+7OSTEOhZwgcNkcGUCQQDDVmOlmKHBjUpMmv0xfc789Zj7PLoKO9WpYkDTbl7xPdc/Yb0OeeZlS123ZlplXLMVPpOQTpFcrbk9nhShaSYTAkEAhnrPsqqCMZt9VPtQikI7hof2LFrZ2OvJuGH5Gf+krBfN5ocj75sn+HzG5BJd3XzOwifjhXHUqbtpMk00+QiFiQJBAIv2JGQM3yn+ANSu4OhLSrp5h2nM80hN4yQA4I4eMS0NsGMbtwjeUzUVMUstrWufZjm8oqLtiL4tQ+Ngl0uoOb0CQQCuOR315Fwm/BW3QXjaASDwN8sahQxfNAtUyh7oGJfieKWYEjd3VYfaWXyful7FWW/Ry8H1pOSbIJZo07gLVTvA';
               
        $merchant_public_key='MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCsCZ3fLByaI1yBcbn+CG7HbXNnCRz5TdnfyCjAcOXLqEZJzXjAk5UfPTMRKn7j6mEe6NXRoDonobMEfU+L1gcs4SZayxBXIDnCRZwyDc1PYmVuEXESn9fO7C12C/G+VX+VoxH7nV458UhrfB1z3vldFYplFWiAEElrwQloB5BVfwIDAQAB';

        $dinpay_public_key='MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCOglLSDWk8iIHH5zFvAg9n++I4iew5Zj4M/8J8TLRj7UShJ3roroNgCkH1Iyw65xIddlCfJK8wkszpZ4OvPRiCDUBaEMENF/TQmscL2M+Ly7XEQ34RTQ1WVcpkZb7KJuiK3XIByYM0fETM1RVhQGJsnC7QpDaorjkWjpuLcR6bDwIDAQAB ';
                                   
        $merchant_code = "4000038801";

        $service_type ="wxpub_pay";                   

        $interface_version ="V3.0";

        $sign_type ="RSA-S";

        $input_charset = "UTF-8";

        $notify_url ="http://www.ymlypt.com/payment/dinpay_callback";        

        $order_time = $data['create_time'];      

        $product_name ="offline_testpay";  
                             
        $return_url ="http://www.ymlypt.com/ucenter/order_details/id/{$order_id}";    

        $pay_type = "";

        $redo_flag = "";    

        $product_code = ""; 

        $product_desc = ""; 

        $product_num = "";

        $show_url = ""; 

        $client_ip ="" ;    

        $bank_code = "";    

        $extend_param = "";

        $extra_return_param = "";   

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
                                    
        $merchant_private_key = "-----BEGIN PRIVATE KEY-----"."\r\n".wordwrap(trim($merchant_private_key),64,"\r\n",true)."\r\n"."-----END PRIVATE KEY-----";

        $merchant_private_key= openssl_get_privatekey($merchant_private_key);

        openssl_sign($signStr,$sign_info,$merchant_private_key,OPENSSL_ALGO_MD5);

        $sign = base64_encode($sign_info);

        $this->assign('sign',$sign);
        $this->assign('merchant_code',$merchant_code);
        $this->assign('service_type',$service_type);
        $this->assign('interface_version',$interface_version);
        $this->assign('sign_type',$sign_type);
        $this->assign('input_charset',$input_charset);
        $this->assign('notify_url',$notify_url);
        $this->assign('order_time',$order_time);
        $this->assign('order_amount',$order_amount);
        $this->assign('order_no',$order_no);
        $this->assign('client_ip',$client_ip);
        $this->assign('extend_param',$extend_param);
        $this->assign('extra_return_param',$extra_return_param);
        $this->assign('pay_type',$pay_type);
        $this->assign('product_code',$product_code);
        $this->assign('product_name',$product_name);
        $this->assign('product_desc',$product_desc);
        $this->assign('product_num',$product_num);
        $this->assign('return_url',$return_url);
        $this->assign('show_url',$show_url);
        $this->assign('redo_flag',$redo_flag);
        /*******************智付支付**********************/
           $payment = new Payment($payment_id);
           $paymentPlugin = $payment->getPaymentPlugin();

           $packData = $payment->getPaymentInfo('offline_order', $order_id);
            // $packData = array_merge($extendDatas, $packData);
            $sendData = $paymentPlugin->packData($packData);
            $this->assign("paymentPlugin", $paymentPlugin);
            $this->assign("sendData", $sendData);
            $this->assign("offline",1);
            $this->redirect('pay_forms', false);
    }

    public function yinpay(){
        //第三方银盛支付
           $payment_id = Filter::int(Req::args('payment_id'));
           $order_no = Req::args('order_no');
           $order_amount = Req::args('order_amount');
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
           if(!$seller_id || $seller_id==0){
             $seller_id = Filter::int(Req::args('seller_ids'));
           }
           $user_id = $this->user['id'];
           $invite=$this->model->table('invite')->where('invite_user_id='.$user_id)->find();
           if($invite){
               $invite_id=intval($invite['user_id']);//邀请人用户id
           }else{
               $invite_id=1;
           }
           Session::set('invite_id',$invite_id);
           $user=$this->model->table('customer')->fields('mobile')->where('user_id='.$user_id)->find();
           if($user){
            $mobile=$user['mobile'];
           }else{
            $mobile='';
           }
           $accept_name = Session::get('openname');
           $config = Config::getInstance()->get("district_set");
           $data['type']=0;
           $data['order_no'] = $order_no;
           $data['user_id'] = $user_id;
           $data['payment'] = $payment_id;
           $data['status'] = 2;
           $data['pay_status'] = 0;
           $data['accept_name'] = $accept_name;
           $data['mobile'] = $mobile;
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
           $data['third_pay'] = 2;
           $data['cashier_id'] = $cashier_id;
           $data['desk_id'] = $desk_id;
           $model = new Model('order_offline');
           $exist=$model->where('order_no='.$order_no)->find();
           //防止重复生成同笔订单
           if(!$exist){
              $order_id=$model->data($data)->insert();
           }else{
              $order_id = $exist['id']; 
           }
           
           $oauth_user=$this->model->table('oauth_user')->fields('open_id')->where('user_id='.$user_id)->find();
           if($oauth_user){
            $sub_openid=$oauth_user['open_id'];
           }else{
            $sub_openid='';
           }

           $myParams = array();
            $myParams['charset'] = 'utf-8';
            $myParams['method'] = 'ysepay.online.jsapi.pay';
            $myParams['notify_url'] = 'http://www.ymlypt.com/payment/yinpay_callback';
            $myParams['partner_id'] = 'yuanmeng';
            // $myParams['return_url'] = 'http://www.ymlypt.com/ucenter/order_details/id/{$order_id}';
            $myParams['return_url'] = 'http://www.ymlypt.com/ucenter/order_details';
            // $myParams['return_url'] = 'http://www.ymlypt.com/travel/order_details';
            $myParams['sign_type'] = 'RSA';
            $myParams['timestamp'] = date('Y-m-d H:i:s', time());
            $myParams['version'] = '3.0';
            
            $biz_content_arr = array(
            "out_trade_no"=>$order_no,
            "subject"=>'圆梦共享网',
            "total_amount"=>$order_amount,
            "currency"=>"CNY",
            "seller_id"=>'yuanmeng',
            "seller_name"=>'圆梦互联网科技（深圳）有限公司',
            "timeout_express"=>'24h',
            // "business_code"=>'3010001',
            "business_code" => "01000010",
            "sub_openid"=>$sub_openid,
            );
          if($payment_id==8){
            $myParams['method'] = 'ysepay.online.wap.directpay.createbyuser';
            $myParams['bank_type'] = "1903000";
            $myParams['pay_mode'] = "native";
            $myParams['out_trade_no'] = $order_no;
            $myParams['subject'] = '圆梦共享网';
            $myParams["total_amount"]=$order_amount;
            $myParams["seller_id"]='yuanmeng';
            $myParams["seller_name"]='圆梦互联网科技（深圳）有限公司';
            $myParams["timeout_express"]='1d';
            $myParams['business_code'] = '3010001'; 
           }
           if($payment_id==6){
            $myParams['biz_content'] = json_encode($biz_content_arr, JSON_UNESCAPED_UNICODE);//构造字符串
           }  
    //        网银直连需添加以下参数
    //        $myParams['pay_mode']           = 'internetbank';
    //        $myParams['bank_type']           = '';
    //        $myParams['bank_account_type']           = 'personal';
    //        $myParams['support_card_type']           = 'debit';
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
        // echo "<pre>";
        // print_r($myParams);
        // echo "<pre>";
        // var_dump($ret);die;
        $success_url = Url::urlFormat("/ucenter/order_details/id/{$order_id}");
        // $success_url = Url::urlFormat("/travel/order_details/id/{$order_id}");
        $cancel_url = Url::urlFormat("/simple/offline_order_status/order_id/{$order_id}");
        $error_url = Url::urlFormat("/simple/offline_order_status/order_id/{$order_id}");
        $this->assign("success_url", $success_url);
        $this->assign("cancel_url", $cancel_url);
        $this->assign("error_url", $error_url);
        $this->assign('business_code',$biz_content_arr['business_code']);
        $this->assign('charset',$myParams['charset']);
        $this->assign('method',$myParams['method']);
        $this->assign('notify_url',$myParams['notify_url']);
        $this->assign('out_trade_no',$biz_content_arr['out_trade_no']);
        $this->assign('partner_id',$myParams['partner_id']);
        $this->assign('return_url',$myParams['return_url']);
        $this->assign('seller_id',$biz_content_arr['seller_id']);
        $this->assign('seller_name',$biz_content_arr['seller_name']);
        $this->assign('sign_type',$myParams['sign_type']);
        $this->assign('subject',$biz_content_arr['subject']);
        $this->assign('timeout_express',$biz_content_arr['timeout_express']);
        $this->assign('timestamp',$myParams['timestamp']);
        $this->assign('total_amount',$order_amount);
        $this->assign('version',$myParams['version']);
        $this->assign('sign',$myParams['sign']);
        if($payment_id==6){
            $this->assign('biz_content',$myParams['biz_content']);
        }
        if(!isset($ret['ysepay_online_jsapi_pay_response']['jsapi_pay_info']) && $payment_id==6) {
            echo "<pre>";
            print_r($myParams);
            echo "<pre>";
            die;
            $this->redirect("/index/msg", false, array('type' => 'fail', 'msg' => $ret['ysepay_online_jsapi_pay_response']['sub_msg']));
            exit();
        }
        $this->assign("jsApiParameters", $ret['ysepay_online_jsapi_pay_response']['jsapi_pay_info']);
        if($payment_id==8){
            $this->assign('bank_type',$myParams['bank_type']);
            $this->assign('pay_mode',$myParams['pay_mode']);
        }
        $payment = new Payment($payment_id);
        $paymentPlugin = $payment->getPaymentPlugin();

        $packData = $payment->getPaymentInfo('offline_order', $order_id);
        // $packData = array_merge($extendDatas, $packData);
        $sendData = $paymentPlugin->packData($packData);
        $this->assign("paymentPlugin", $paymentPlugin);
        $this->assign("sendData", $sendData);
        $this->assign("payment_id",$payment_id);
        $agent = strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger');
        // var_dump($agent);die;
        $this->assign("agent",$agent);
        if($payment_id==6){
           $this->redirect('yinpay', false); 
       }else{
           $this->redirect('yinpay_form', false);
       }     
    }

    public function yinpay_alipay(){
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
            $out_trade_no = $_GET['out_trade_no'];
            $order_no = $out_trade_no;
            $order = $this->model->table("order_offline")->where("order_no='{$order_no}'")->find();
            if (!$order) {
                $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '支付信息错误', "content" => "抱歉，找不到您的订单信息"));
                exit();
            }
            $success_url = Url::urlFormat("/ucenter/order_details/id/{$order['id']}");
            $cancel_url = Url::urlFormat("/simple/offline_order_status/order_id/{$order['id']}");
            $error_url = Url::urlFormat("/simple/offline_order_status/order_id/{$order['id']}"); 
            $this->assign("success_url", $success_url);
            $this->assign("cancel_url", $cancel_url);
            $this->assign("error_url", $error_url);
            $this->redirect();
        } else {
            $sendData = $_GET;
            unset($sendData['con'], $sendData['act']);
            // echo "<pre>";
            // print_r($sendData);
            // echo "<pre>";
            // die;
            $this->assign("sendData", $sendData);
            $this->redirect();
        }
    }

    public function sign_encrypt($input)
    {
        // $pfxpath = 'http://' . $_SERVER['HTTP_HOST'] . "/trunk/protected/classes/yinpay/certs/shanghu_test.pfx";
        $pfxpath = "./protected/classes/yinpay/certs/yuanmeng.pfx";
        $pfxpassword = '008596';
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

    public function yinpay_callback(){
        $model = new Model();
        
       //返回的数据处理
        @$sign = trim($_POST['sign']);
        $result = $_POST;
        unset($result['sign']);
        ksort($result);
        $url = "";
        foreach ($result as $key => $val) {
            if ($val) $url .= $key . '=' . $val . '&';
        }
        $data = trim($url, '&');
        /*写入日志*/
        $file = "./notify.txt";
        file_put_contents($file, "\r\n", FILE_APPEND);
        file_put_contents($file, "return|data:" . $data . "|sign:" . $sign, FILE_APPEND);
        /* 验证签名 仅作基础验证*/
        if ($this->sign_check($sign, $data) == true) {
            if($result['trade_status']  == 'TRADE_SUCCESS'){
                $orderNo = $_POST['out_trade_no'];
                $money = $_POST['out_trade_no'];
                $callbackData = array();
                if (stripos($orderNo, 'promoter') !== false) {
                    
                } else if (stripos($orderNo, 'district') !== false) {
                    
                } else if (stripos($orderNo, 'recharge') !== false) {//充值方式
                       
                } else if (stripos($orderNo, 'redbag') !== false) {//发送红包
                      
                } else {
                    //如果是订单支付的话    
                    $order_info = $model->table('order')->where("order_no='{$orderNo}'")->find();
                    $order_offline = $model->table('order_offline')->where("order_no='{$orderNo}'")->find();
                    if (!empty($order_info)) {
                        $payment_id = $order_info['payment'];
                        if ($order_info['type'] == 4 && $order_info['is_new'] == 0) {
                            if ($order_info['otherpay_amount'] > $money) {
                                file_put_contents('payErr.txt', date("Y-m-d H:i:s") . "|========订单金额不符,订单号：{$orderNo}|{$order_info['order_amount']}元|{$money}元|{$payment_id}========|\n", FILE_APPEND);
                                exit;
                            }
                        } else if ($order_info['order_amount'] > $money) {
                            file_put_contents('payErr.txt', date("Y-m-d H:i:s") . "|========订单金额不符,订单号：{$orderNo}|{$order_info['order_amount']}元|{$money}元|{$payment_id}========|\n", FILE_APPEND);
                            exit;
                        }
                        $order_id = Order::updateStatus($orderNo, $payment_id, $callbackData);
                        if ($order_id) {
                            $paymentPlugin->asyncStop();
                            exit;
                        } 
                    }elseif(!empty($order_offline)){
                        $order_no = $orderNo;
                         $order=$this->model->table('order_offline')->where("order_no='{$order_no}'")->find();
                            $this->model->table('order_offline')->where("order_no='{$order_no}'")->data(array('status'=>3,'pay_status'=>1,'delivery_status'=>1,'pay_time'=>date('Y-m-d H:i:s')))->update();
                            // $invite_id=Session::get('invite_id');
                            $invite_id=$order['prom_id'];
                            $seller_id=$order['shop_ids'];             
                            if($invite_id==null){
                                $invite_id=1;
                            }
                            if($seller_id==0){
                                $seller_id=$invite_id;
                            }
                            // $promoter_id=Common::getFirstPromoter($order['user_id']);
                            $exist=$this->model->table('balance_log')->where("order_no='{$order_no}'")->find();
                            if(!$exist){
                                //如果卖家是邀请人的话不参与分账
                                if($seller_id!=$invite_id){
                                    $config = Config::getInstance()->get("district_set");
                                    $promoter = $this->model->table('district_promoter')->fields('base_rate')->where('user_id='.$seller_id)->find();
                                    if($promoter){
                                        $amount = round($order['order_amount']*(100-$promoter['base_rate'])/100,2);
                                    }else{
                                        $amount = round($order['order_amount']*(100-$config['offline_base_rate'])/100,2);
                                    }     
                                    $this->model->table('customer')->where('user_id='.$seller_id)->data(array("offline_balance"=>"`offline_balance`+({$amount})"))->update();//平台收益提成
                                    Log::balance($amount, $seller_id, $order_no,'线下会员消费卖家收益', 8);
                                    Common::offlineBeneficial($order_no,$invite_id,$seller_id);
                                    $this->model->table('order_offline')->where("order_no='{$order_no}'")->data(array('payable_amount'=>$amount))->update();
                                    $money = $amount;
                                }else{
                                    $this->model->table('customer')->where('user_id='.$seller_id)->data(array("offline_balance"=>"`offline_balance`+({$order['order_amount']})"))->update();//平台收益提成
                                     Log::balance($order['order_amount'], $seller_id, $order_no,'线下会员消费卖家收益(不参与分账)', 8);
                                     $money = $order['order_amount'];
                                }
                                 //微信公众号消息推送
                                    #*****************推送消息***************
                                    $wechatcfg = $this->model->table("oauth")->where("class_name='WechatOAuth'")->find();
                                    $wechat = new WechatMenu($wechatcfg['app_key'], $wechatcfg['app_secret'], '');
                                    $token = $wechat->getAccessToken();
                                    $oauth_info = $this->model->table("oauth_user")->fields("open_id,open_name")->where("user_id=".$seller_id." and oauth_type='wechat'")->find();
                                    if($oauth_info){
                                        $params = array(
                                        'touser' => $oauth_info['open_id'],
                                        'msgtype' => 'text',
                                        "text" => array(
                                                'content' => "亲爱的商家:{$oauth_info['open_name']},您获得商家消费收益{$money}元，请登录个人中心查看。"
                                                )
                                            );
                                        $result = Http::curlPost("https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token={$token}", json_encode($params, JSON_UNESCAPED_UNICODE));
                                    }      
                                    #****************************************
                                 //APP极光推送
                                 if($order['type']==2 || $order['type']==3){
                                      $type = 'offline_balance';
                                      $content = "收款到账{$money}元";
                                      $platform = 'all';
                                      if (!$this->jpush) {
                                              $NoticeService = new NoticeService();
                                              $this->jpush = $NoticeService->getNotice('jpush');
                                          }
                                      $audience['alias'] = array($seller_id);
                                      $this->jpush->setPushData($platform, $audience, $content, $type, "");
                                      $this->jpush->push();
                                 }              
                            }
                    }  
                }
            }
            file_put_contents($file, "\r\n", FILE_APPEND);
            file_put_contents($file, "Verify success!|notify|:" . $data . "|sign:" . $sign, FILE_APPEND);
            echo 'success';
            exit();
        } else {
            
            file_put_contents($file, "\r\n", FILE_APPEND);
            file_put_contents($file, "Validation failure!|notify|:" . $data . "|sign:" . $sign, FILE_APPEND);
            echo 'fail';
            exit();
        }
    }

    public function sign_check($sign, $data)
    {
        $businessgatecerpath = "./protected/classes/yinpay/certs/businessgate.cer";
        $publickeyFile = $businessgatecerpath; //公钥
        $certificateCAcerContent = file_get_contents($publickeyFile);
        $certificateCApemContent = '-----BEGIN CERTIFICATE-----' . PHP_EOL . chunk_split(base64_encode($certificateCAcerContent), 64, PHP_EOL) . '-----END CERTIFICATE-----' . PHP_EOL;
        // 签名验证
        $success = openssl_verify($data, base64_decode($sign), openssl_get_publickey($certificateCApemContent), OPENSSL_ALGO_SHA1);

        return $success;
    }

    public function dopayss(){
        $payment_id = Filter::int(Req::args('payment_id'));
           $order_no = Req::args('order_no');
           $order_amount = (Req::args('order_amount'));
           $randomstr=rand(1000000000000,9999999999999);
           $seller_id = Filter::int(Req::args('seller_id'));//卖家用户id
           // $seller_id = Session::get('seller_id');
           // $oauth = new WechatOAuth();
           // $userinfo = $oauth->getUserInfo();
           if(!$seller_id || $seller_id==0){
             $seller_id = Filter::int(Req::args('seller_ids'));
           }
           $user_id = $this->user['id'];
           $invite=$this->model->table('invite')->where('invite_user_id='.$user_id)->find();
           if($invite){
               $invite_id=intval($invite['user_id']);//邀请人用户id
           }else{
               $invite_id=1;
           }
           Session::set('invite_id',$invite_id);
           $user=$this->model->table('customer')->fields('mobile')->where('user_id='.$user_id)->find();
           if($user){
            $mobile=$user['mobile'];
           }else{
            $mobile='';
           }
           $accept_name = Session::get('openname');
           $config = Config::getInstance()->get("district_set");
           $data['type']=8;
           $data['order_no'] = $order_no;
           $data['user_id'] = $user_id;
           $data['payment'] = $payment_id;
           $data['status'] = 2;
           $data['pay_status'] = 0;
           $data['accept_name'] = $accept_name;
           $data['mobile'] = $mobile;
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
           $open=$this->model->table('oauth_user')->where('user_id='.$user_id)->find();

           $params = array();
           $params["cusid"] = AppConfig::CUSID;
           $params["appid"] = AppConfig::APPID;
           $params["version"] = AppConfig::APIVERSION;
           $params["trxamt"] = $order_amount*100;
           $params["reqsn"] = $order_no;//订单号,自行生成
           $params["paytype"] = "W02";
           $params["randomstr"] = $randomstr;//
           $params["body"] = "商品名称";
           $params["remark"] = "备注信息";
           $params["acct"] = $open['open_id'];
           // $params["limit_pay"] = "no_credit";
           // $params["notify_url"] = "http://172.16.2.46:8080/vo-apidemo/OrderServlet";
           $params["notify_url"] = 'http://www.ymlypt.com/payment/async_callbacks';
           // $params["notify_url"] = Url::fullUrlFormat("/payment/async_callback");
           // $params["notify_url"] = 'http://'.$_SERVER['HTTP_HOST'].'/payment/async_callback';
           $params["sign"] = AppUtil::SignArray($params,AppConfig::APPKEY);//签名

           $paramsStr = AppUtil::ToUrlParams($params);
           $url = AppConfig::APIURL . "/pay";
           $rsp = AppUtil::Request($url, $paramsStr);

           $rspArray = json_decode($rsp, true);
           if(AppUtil::ValidSigns($rspArray)){
              exit(json_encode(array('status'=>0,'jsApiParameters'=>$rspArray['payinfo'])));
           }else{
             exit(json_encode(array('status'=>-1)));
           }
    }

    public function notify() {
        $payment = new Payment('wxpayqrcode');
        $payment_weixin = $payment->getPaymentPlugin();
        WxPayConfig::setConfig($payment_weixin->getClassConfig());
        $paymentId = $payment_weixin->getPaymentId();
        $notify = new PayNotifyCallBack();
        $notify->paymentId = $paymentId;
        $notify->Handle(false);
    }

    //同步回调
    public function callback() {
        //从URL中获取支付方式
        $payment_id = Filter::int(Req::get('payment_id'));
        $payment = new Payment($payment_id);
        $paymentPlugin = $payment->getPaymentPlugin();

        if (!is_object($paymentPlugin)) {
            $msg = array('type' => 'fail', 'msg' => '支付方式不存在！');
            $this->redirect('/index/msg', false, $msg);
            exit;
        }

        //初始化参数
        $money = '';
        $message = '支付失败';
        $orderNo = '';

        //执行接口回调函数
        $callbackData = Req::args(); //array_merge($_POST,$_GET);
        unset($callbackData['con']);
        unset($callbackData['act']);
        unset($callbackData['payment_id']);
        unset($callbackData['tiny_token_redirect']);
        $return = $paymentPlugin->callback($callbackData, $payment_id, $money, $message, $orderNo);
        //支付成功
        if ($return == 1) {
            $model = new Model("order");
            $orders = $model->where("order_no='{$orderNo}'")->find();
            if($orders){
               if($orders['type']==7){
                 $result=Common::setIncomeByInviteShip1($orders);
                } 
            }
            if (stripos($orderNo, 'promoter') !== false) {//如果是推广员入驻订单
                $order = $this->model->table("district_order")->where("order_no ='" . $orderNo . "'")->find();
                if ($order) {
                    if ($order['pay_status'] == 1) {
                        $this->redirect("/ucenter/index?first=1");
                        exit();
                    } else {
                        DistrictLogic::getInstance()->promoterPayCallback($orderNo, $money, $payment_id, $callbackData);
                        $this->redirect("/ucenter/index?first=1");
                        exit();
                    }
                } else {
                    file_put_contents('district_payErr.txt', date("Y-m-d H:i:s") . "==promoter==\n" . json_encode($callbackData) . "\n", FILE_APPEND);
                    exit;
                }
            } else if (stripos($orderNo, 'district') !== false) {
                $district_id = intval(substr($orderNo, stripos($orderNo, 'district') + 8));
                $district = new Model("district_apply");
                $district_info = $district->where("id=$district_id")->find();
                if (!empty($district_info)) {
                    if ($district_info['pay_status'] == 1) {
                        
                    } else {
                        $payment_info = $payment->getPayment();
                        $district->where("id=" . $district_id)->data(array("pay_status" => 1, "payment_name" => $payment_info['name'], 'pay_time' => date("Y-m-d H:i:s")))->update();
                        Common::autoPassDistrictApply($district_id);
                        file_put_contents('district_pay.txt', date("Y-m-d H:i:s") . "==district ID:$district_id==Money:$money==\n", FILE_APPEND);
                        $this->redirect("/district/district");
                    }
                } else {
                    file_put_contents('district_payErr.txt', date("Y-m-d H:i:s") . "==district  ID:$district_id==\n", FILE_APPEND);
                    exit;
                }
            } else if (stripos($orderNo, 'recharge') !== false) {//充值方式
                $recharge_no = substr($orderNo, stripos($orderNo, 'recharge') + 8);
                $recharge_no = $recharge_no == "" ? 0 : $recharge_no;
                $recharge = new Model('recharge');
                $recharge_info = $recharge->where("recharge_no='{$recharge_no}'")->find();
                if (!empty($recharge_info)) {
                    if ($recharge_info['account'] > $money) {
                        file_put_contents('payErr.txt', date("Y-m-d H:i:s") . "|========充值订单金额不符,订单号：{$orderNo}|{$recharge_info['account']}元|{$money}元|{$payment_id}======|\n", FILE_APPEND);
                        exit;
                    }
                }
                $recharge_id = Order::recharge($recharge_no, $payment_id);
                if ($recharge_id) {
                    //$this->redirect("/ucenter/account/$recharge_id");
                    $model = new Model('recharge');
                    $obj = $model->where("id=" . $recharge_id . ' and status=1')->find();
                    if ($obj) {
                        $msg = array('type' => 'success', 'msg' => '充值成功！', 'content' => '充值编号：' . $recharge_no . ',充值方式：' . $obj['payment_name'], 'redirect' => '/ucenter/account');
                        $this->redirect('/index/msg', true, $msg);
                    }
                    exit;
                }
                $msg = array('type' => 'fail', 'msg' => '充值失败！');
                $this->redirect('/index/msg', false, $msg);
                exit;
            } else {
                $payment_plugin = $payment->getPayment();
                //货到付款的处理
                if ($payment_plugin['class_name'] == 'received') {
                    $model = new Model("order");
                    $order = $model->where("order_no='" . $orderNo . "'")->find();
                    if (!empty($order)) {
                        $model->where("order_no='" . $orderNo . "'")->data(array('payment' => $payment_id))->update();
                        $this->redirect("/simple/order_completed/order_id/" . $order['id']);
                        exit;
                    }
                } else {
                    $orderarr = explode('_', $orderNo);
                    $orderNo = end($orderarr);
                    $order = new Model("order");
                    $orders = new Model("order_offline");
                    $order_info = $order->where("order_no='{$orderNo}'")->find();
                    $order_offline = $orders->where("order_no='{$orderNo}'")->find();
                    if (!empty($order_info)) {
                        if ($order_info['type'] == 4 && $order_info['is_new'] == 0) {
                            if ($order_info['otherpay_amount'] > $money) {
                                file_put_contents('payErr.txt', date("Y-m-d H:i:s") . "|========订单金额不符,订单号：{$orderNo}|{$order_info['order_amount']}元|{$money}元|{$payment_id}========|\n", FILE_APPEND);
                                exit;
                            }
                        } else if ($order_info['order_amount'] > $money) {
                            file_put_contents('payErr.txt', date("Y-m-d H:i:s") . "|========订单金额不符,订单号：{$orderNo}|{$order_info['order_amount']}元|{$money}元|{$payment_id}========|\n", FILE_APPEND);
                            exit;
                        }
                        $order_id = Order::updateStatus($orderNo, $payment_id, $callbackData);
                        if ($order_id) {
                            $this->redirect("/simple/order_completed/order_id/" . $order_id);
                            exit;
                        }
                    }
                    if(!empty($order_offline)){
                        $this->redirect("/ucenter/order_details/id/{$order_offline['id']}");
                            exit;
                    }
                    $msg = array('type' => 'fail', 'msg' => '订单修改失败了！');
                    $this->redirect('/index/msg', false, $msg);
                    exit;
                }
            }
        }
        //支付失败
        else {
            $message = $message ? $message : '支付失败';
            $msg = array('type' => 'fail', 'msg' => $message);
            $this->redirect('/index/msg', false, $msg);
            exit;
        }
    }
    
    //线下微信支付回调
    public function async_callbacks(){
        $xml = @file_get_contents('php://input');
        // $array=Common::xmlToArray($xml);
        // file_put_contents('./wxpay.php', json_encode($xml) . PHP_EOL, FILE_APPEND);
        $str=substr(json_encode($xml),-5);
        $strs=substr($str,0,4);
        $trxstatus=0;  
        if($strs=='0000'){    
            $trxstatus=1;
        }
        $payinfo=explode('&',json_encode($xml));
        $orderarr=$payinfo[4];
        $order_no=substr($orderarr,-18);
        //从URL中获取支付方式
        $payment_id = 6;
        // var_dump($payment_id);die;
        $payment = new Payment($payment_id);
        $paymentPlugin = $payment->getPaymentPlugin();
        if (!is_object($paymentPlugin)) {
            echo "fail";
        }

        if($trxstatus==1){
            $order=$this->model->table('order_offline')->where("order_no='{$order_no}'")->find();
            $this->model->table('order_offline')->where("order_no='{$order_no}'")->data(array('status'=>3,'pay_status'=>1,'delivery_status'=>1,'pay_time'=>date('Y-m-d H:i:s')))->update();
            // $invite_id=Session::get('invite_id');
            $invite_id=$order['prom_id'];
            $seller_id=$order['shop_ids'];             
            if($invite_id==null){
                $invite_id=1;
            }
            if($seller_id==0){
                $seller_id=$invite_id;
            }
            // $promoter_id=Common::getFirstPromoter($order['user_id']);
            $exist=$this->model->table('balance_log')->where("order_no='{$order_no}'")->find();
            if(!$exist){
                //如果卖家是邀请人的话不参与分账
                if($seller_id!=$invite_id){
                    $config = Config::getInstance()->get("district_set");
                    $promoter = $this->model->table('district_promoter')->fields('base_rate')->where('user_id='.$seller_id)->find();
                    if($promoter){
                        $amount = round($order['order_amount']*(100-$promoter['base_rate'])/100,2);
                    }else{
                        $amount = round($order['order_amount']*(100-$config['offline_base_rate'])/100,2);
                    }     
                    $this->model->table('customer')->where('user_id='.$seller_id)->data(array("offline_balance"=>"`offline_balance`+({$amount})"))->update();//平台收益提成
                    Log::balance($amount, $seller_id, $order_no,'线下会员消费卖家收益', 8);
                    Common::offlineBeneficial($order_no,$invite_id,$seller_id);
                    $this->model->table('order_offline')->where("order_no='{$order_no}'")->data(array('payable_amount'=>$amount))->update();
                    $money = $amount;
                }else{
                    $this->model->table('customer')->where('user_id='.$seller_id)->data(array("offline_balance"=>"`offline_balance`+({$order['order_amount']})"))->update();//平台收益提成
                     Log::balance($order['order_amount'], $seller_id, $order_no,'线下会员消费卖家收益(不参与分账)', 8);
                     $money = $order['order_amount'];
                }
                #*****************推送消息***************
                $wechatcfg = $this->model->table("oauth")->where("class_name='WechatOAuth'")->find();
                $wechat = new WechatMenu($wechatcfg['app_key'], $wechatcfg['app_secret'], '');
                $token = $wechat->getAccessToken();
                $oauth_info = $this->model->table("oauth_user")->fields("open_id,open_name")->where("user_id=".$seller_id." and oauth_type='wechat'")->find();
                if($oauth_info){
                    $params = array(
                    'touser' => $oauth_info['open_id'],
                    'msgtype' => 'text',
                    "text" => array(
                            'content' => "亲爱的商家:{$oauth_info['open_name']},您获得商家消费收益{$money}元，请登录个人中心查看。"
                            )
                        );
                    $result = Http::curlPost("https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token={$token}", json_encode($params, JSON_UNESCAPED_UNICODE));
                }        
                #****************************************
            }                            
        }

        if($trxstatus==1){
            echo 'success';
        }else{
            echo 'fail';
        }       
        // echo $str;
        // $this->returnStatus($trxstatus);
    }

    // 支付回调[异步]
    public function async_callback() {
        $payment_id = Filter::int(Req::args('payment_id')); 
        if($payment_id==6 || $payment_id==7 || $payment_id==18){
            $xml = @file_get_contents('php://input');
            $array=Common::xmlToArray($xml);
            // file_put_contents('./wxpay.php', json_encode($array) . PHP_EOL, FILE_APPEND);
            // file_put_contents("./wxpay.php", $GLOBALS['HTTP_RAW_POST_DATA']);
            //从URL中获取支付方式
            // var_dump($payment_id);die;
            $payment = new Payment($payment_id);
            $paymentPlugin = $payment->getPaymentPlugin();
            if (!is_object($paymentPlugin)) {
                echo "fail";
            }

            //初始化参数
            $money = '';
            $message = '支付失败';
            $orderNo = '';
            
            $callbackData=$array;
            $orderNo = $array['attach'];
            
            $money = round(intval($array['total_fee'])/100,2);
            if($array['result_code']=='SUCCESS'){
                $return=1;
            }else{
                $return=0;
            }

        }else{
            $payment = new Payment($payment_id);
            $paymentPlugin = $payment->getPaymentPlugin();
            if (!is_object($paymentPlugin)) {
                echo "fail";
            }
            //初始化参数
            $money = '';
            $message = '支付失败';
            $orderNo = '';

            //执行接口回调函数
            $callbackData = Req::args(); //array_merge($_POST,$_GET);
            
            unset($callbackData['con']);
            unset($callbackData['act']);
            unset($callbackData['payment_id']);
            
            if(isset($callbackData['out_trade_no'])){
                $orderNo = $callbackData['out_trade_no'];
            }
            if(isset($callbackData['total_fee'])){
                $money = $callbackData['total_fee'];
            }
                   
            $return = $paymentPlugin->asyncCallback($callbackData, $payment_id, $money, $message, $orderNo);
        }
        
        //支付成功        
        if ($return == 1 ) {
            if (stripos($orderNo, 'promoter') !== false) {
                $order = $this->model->table("district_order")->where("order_no ='" . $orderNo . "'")->find();
                if ($order) {
                    if ($order['pay_status'] == 1) {
                        $paymentPlugin->asyncStop();
                        exit;
                    } else {
                        DistrictLogic::getInstance()->promoterPayCallback($orderNo, $money, $payment_id, $callbackData);
                        $paymentPlugin->asyncStop();
                        exit;
                    }
                } else {
                    file_put_contents('district_payErr.txt', date("Y-m-d H:i:s") . "==promoter==\n" . json_encode($callbackData) . "\n", FILE_APPEND);
                    exit;
                }
            } else if (stripos($orderNo, 'district') !== false) {
                $district_id = intval(substr($orderNo, stripos($orderNo, 'district') + 8));
                $district = new Model("district_apply");
                $district_info = $district->where("id=$district_id")->find();
                if (!empty($district_info)) {
                    if ($district_info['pay_status'] == 1) {
                        $paymentPlugin->asyncStop();
                        exit;
                    } else {
                        $payment_info = $payment->getPayment();
                        $result = $district->where("id=" . $district_id)->data(array("pay_status" => 1, "payment_name" => $payment_info['name'], 'pay_time' => date("Y-m-d H:i:s")))->update();
                        if ($result) {
                            Common::autoPassDistrictApply($district_id);
                            $paymentPlugin->asyncStop();
                            exit;
                        } else {
                            file_put_contents('district_payErr.txt', date("Y-m-d H:i:s") . "==district ID:$district_id==\n" . json_encode($callbackData) . "\n", FILE_APPEND);
                            exit;
                        }
                    }
                } else {
                    file_put_contents('district_payErr.txt', date("Y-m-d H:i:s") . "==district ID:$district_id==\n" . json_encode($callbackData) . "\n", FILE_APPEND);
                    exit;
                }
            } else if (stripos($orderNo, 'recharge') !== false) {//充值方式
                $recharge_no = substr($orderNo, stripos($orderNo, 'recharge') + 8);
                $recharge_no = $recharge_no == "" ? 0 : $recharge_no;
                $recharge = new Model('recharge');
                $recharge_info = $recharge->where("recharge_no='{$recharge_no}'")->find();
                if (!empty($recharge_info)) {
                    if ($recharge_info['account'] > $money) {
                        file_put_contents('payErr.txt', date("Y-m-d H:i:s") . "|========充值订单金额不符,订单号：{$orderNo}|{$recharge_info['account']}元|{$money}元|{$payment_id}======|\n", FILE_APPEND);
                        echo 'fail';
                        exit;
                    }
                } 
                if (Order::recharge($recharge_no, $payment_id)) {
                    $paymentPlugin->asyncStop();
                    exit;
                }
            } else if (stripos($orderNo, 'redbag') !== false) {//发送红包
                $redbag = new Model('redbag');
                $redbag_info = $redbag->where("order_no='{$orderNo}'")->find();
                if($redbag_info){
                    $ret = $redbag->data(array('pay_status'=>1))->where("order_no='{$orderNo}'")->update();
                    if ($ret) {
                        $paymentPlugin->asyncStop();
                        exit;
                    }
                }  
            } else {
                // $orderarr = explode('_', $orderNo);
                // $orderNo = end($orderarr);
                //如果是订单支付的话
                $model = new Model();
                $order_info = $model->table('order')->where("order_no='{$orderNo}'")->find();
                $order_offline = $model->table('order_offline')->where("order_no='{$orderNo}'")->find();
                if (!empty($order_info)) {
                    if ($order_info['type'] == 4 && $order_info['is_new'] == 0) {
                        if ($order_info['otherpay_amount'] > $money) {
                            file_put_contents('payErr.txt', date("Y-m-d H:i:s") . "|========订单金额不符,订单号：{$orderNo}|{$order_info['order_amount']}元|{$money}元|{$payment_id}========|\n", FILE_APPEND);
                            exit;
                        }
                    } else if ($order_info['order_amount'] > $money) {
                        file_put_contents('payErr.txt', date("Y-m-d H:i:s") . "|========订单金额不符,订单号：{$orderNo}|{$order_info['order_amount']}元|{$money}元|{$payment_id}========|\n", FILE_APPEND);
                        echo 'fail';
                        exit;
                    }
                    $order_id = Order::updateStatus($orderNo, $payment_id, $callbackData);
                    if ($order_id) {
                        $paymentPlugin->asyncStop();
                        exit;
                    } 
                }elseif(!empty($order_offline)){
                    // if($order_offline['user_id']==42608){
                    //    exit;
                    // }
                    $order_no = $orderNo;
                     $order=$this->model->table('order_offline')->where("order_no='{$order_no}'")->find();
                     if ($order['order_amount'] != $money) {
                        file_put_contents('payErr.txt', date("Y-m-d H:i:s") . "|========订单金额不符,订单号：{$orderNo}|{$order['order_amount']}元|{$money}元|{$payment_id}========|\n", FILE_APPEND);
                        echo 'fail';
                        exit;
                    }
                        $this->model->table('order_offline')->where("order_no='{$order_no}'")->data(array('status'=>3,'pay_status'=>1,'delivery_status'=>1,'pay_time'=>date('Y-m-d H:i:s')))->update();
                        // $invite_id=Session::get('invite_id');
                        $invite_id=$order['prom_id'];
                        $seller_id=$order['shop_ids'];             
                        if($invite_id==null){
                            $invite_id=1;
                        }
                        if($seller_id==0){
                            $seller_id=$invite_id;
                        }
                        // $promoter_id=Common::getFirstPromoter($order['user_id']);
                        $exist=$this->model->table('balance_log')->where("order_no='{$order_no}'")->find();
                        if(!$exist){
                            //如果卖家是邀请人的话不参与分账
                            if($seller_id!=$invite_id){
                                $config = Config::getInstance()->get("district_set");
                                $promoter = $this->model->table('district_promoter')->fields('base_rate')->where('user_id='.$seller_id)->find();
                                if($promoter){
                                    $amount = round($order['order_amount']*(100-$promoter['base_rate'])/100,2);
                                }else{
                                    $amount = round($order['order_amount']*(100-$config['offline_base_rate'])/100,2);
                                }     
                                $this->model->table('customer')->where('user_id='.$seller_id)->data(array("offline_balance"=>"`offline_balance`+({$amount})"))->update();//平台收益提成
                                Log::balance($amount, $seller_id, $order_no,'线下会员消费卖家收益', 8);
                                Common::offlineBeneficial($order_no,$invite_id,$seller_id);
                                $this->model->table('order_offline')->where("order_no='{$order_no}'")->data(array('payable_amount'=>$amount))->update();
                                $money = $amount;
                            }else{
                                $this->model->table('customer')->where('user_id='.$seller_id)->data(array("offline_balance"=>"`offline_balance`+({$order['order_amount']})"))->update();//平台收益提成
                                 Log::balance($order['order_amount'], $seller_id, $order_no,'线下会员消费卖家收益(不参与分账)', 8);
                                 $money = $order['order_amount'];
                            }
                             //微信公众号消息推送
                                #*****************推送消息***************
                                $wechatcfg = $this->model->table("oauth")->where("class_name='WechatOAuth'")->find();
                                $wechat = new WechatMenu($wechatcfg['app_key'], $wechatcfg['app_secret'], '');
                                $token = $wechat->getAccessToken();
                                $oauth_info = $this->model->table("oauth_user")->fields("open_id,open_name")->where("user_id=".$seller_id." and oauth_type='wechat'")->find();
                                if($oauth_info){
                                    $params = array(
                                    'touser' => $oauth_info['open_id'],
                                    'msgtype' => 'text',
                                    "text" => array(
                                            'content' => "亲爱的商家:{$oauth_info['open_name']},您获得商家消费收益{$money}元，请登录个人中心查看。"
                                            )
                                        );
                                    $result = Http::curlPost("https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token={$token}", json_encode($params, JSON_UNESCAPED_UNICODE));
                                }      
                                #****************************************
                             //APP极光推送
                             $type = 'offline_balance';
                             $content = "收款到账{$money}元";
                             $platform = 'all';
                             if (!$this->jpush) {
                                    $NoticeService = new NoticeService();
                                    $this->jpush = $NoticeService->getNotice('jpush');
                                    }
                             $audience['alias'] = array($seller_id);
                             $this->jpush->setPushData($platform, $audience, $content, $type, "");
                             $this->jpush->push();
                                          
                        }
                }
                
            }
            echo 'success';
        }else{
            echo 'fail';
        }
    }

    public function dinpay_callback(){
        $model = new Model();
        $array = $_POST;
        file_put_contents('./wxpay.php', json_encode($array) . PHP_EOL, FILE_APPEND);

        $merchant_private_key='MIICeAIBADANBgkqhkiG9w0BAQEFAASCAmIwggJeAgEAAoGBAKwJnd8sHJojXIFxuf4Ibsdtc2cJHPlN2d/IKMBw5cuoRknNeMCTlR89MxEqfuPqYR7o1dGgOiehswR9T4vWByzhJlrLEFcgOcJFnDINzU9iZW4RcRKf187sLXYL8b5Vf5WjEfudXjnxSGt8HXPe+V0VimUVaIAQSWvBCWgHkFV/AgMBAAECgYBivF40EJAV0serrwatCk/x+xopf2x2lLy/l5Pz5pesS9aTUu7Dr6/9LtWZO4d57TFyWPUmi0v1JPOmVvkJa3vPz6HhZIzg5M4jd23Kj8fl94PaTSyGM3NEMRJDLPxWEB9ydR60VtRlieCf2lyH0JSKa5YMS09A6ks13W4SVNRqaQJBAOF22itr0KonXZaQxNIOrnGifCvBA11cKV1SMxT5iLOuYu5j2VOZNExC5oD4j1fkT/7kEq+7OSTEOhZwgcNkcGUCQQDDVmOlmKHBjUpMmv0xfc789Zj7PLoKO9WpYkDTbl7xPdc/Yb0OeeZlS123ZlplXLMVPpOQTpFcrbk9nhShaSYTAkEAhnrPsqqCMZt9VPtQikI7hof2LFrZ2OvJuGH5Gf+krBfN5ocj75sn+HzG5BJd3XzOwifjhXHUqbtpMk00+QiFiQJBAIv2JGQM3yn+ANSu4OhLSrp5h2nM80hN4yQA4I4eMS0NsGMbtwjeUzUVMUstrWufZjm8oqLtiL4tQ+Ngl0uoOb0CQQCuOR315Fwm/BW3QXjaASDwN8sahQxfNAtUyh7oGJfieKWYEjd3VYfaWXyful7FWW/Ry8H1pOSbIJZo07gLVTvA';
       
       $merchant_public_key='MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCsCZ3fLByaI1yBcbn+CG7HbXNnCRz5TdnfyCjAcOXLqEZJzXjAk5UfPTMRKn7j6mEe6NXRoDonobMEfU+L1gcs4SZayxBXIDnCRZwyDc1PYmVuEXESn9fO7C12C/G+VX+VoxH7nV458UhrfB1z3vldFYplFWiAEElrwQloB5BVfwIDAQAB';

       $dinpay_public_key='MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCOglLSDWk8iIHH5zFvAg9n++I4iew5Zj4M/8J8TLRj7UShJ3roroNgCkH1Iyw65xIddlCfJK8wkszpZ4OvPRiCDUBaEMENF/TQmscL2M+Ly7XEQ34RTQ1WVcpkZb7KJuiK3XIByYM0fETM1RVhQGJsnC7QpDaorjkWjpuLcR6bDwIDAQAB ';

        $merchant_code  = $_POST["merchant_code"];  

        $interface_version = $_POST["interface_version"];

        $sign_type = $_POST["sign_type"];

        $dinpaySign = base64_decode($_POST["sign"]);

        $notify_type = $_POST["notify_type"];

        $notify_id = $_POST["notify_id"];

        $order_no = $_POST["order_no"];

        $order_time = $_POST["order_time"]; 

        $order_amount = $_POST["order_amount"];

        $trade_status = $_POST["trade_status"];

        $trade_time = $_POST["trade_time"];

        $trade_no = $_POST["trade_no"];

        $bank_seq_no = isset($_POST["bank_seq_no"])?$_POST["bank_seq_no"]:'';

        $extra_return_param = '';


    /////////////////////////////   参数组装  /////////////////////////////////
    /** 
    除了sign_type dinpaySign参数，其他非空参数都要参与组装，组装顺序是按照a~z的顺序，下划线"_"优先于字母 
    */

        
        $signStr = "";
        
        if($bank_seq_no != ""){
            $signStr = $signStr."bank_seq_no=".$bank_seq_no."&";
        }

        if($extra_return_param != ""){
            $signStr = $signStr."extra_return_param=".$extra_return_param."&";
        }   

        $signStr = $signStr."interface_version=".$interface_version."&";    

        $signStr = $signStr."merchant_code=".$merchant_code."&";

        $signStr = $signStr."notify_id=".$notify_id."&";

        $signStr = $signStr."notify_type=".$notify_type."&";

        $signStr = $signStr."order_amount=".$order_amount."&";  

        $signStr = $signStr."order_no=".$order_no."&";  

        $signStr = $signStr."order_time=".$order_time."&";  

        $signStr = $signStr."trade_no=".$trade_no."&";  

        $signStr = $signStr."trade_status=".$trade_status."&";

        $signStr = $signStr."trade_time=".$trade_time;
        
        //echo $signStr;
        
    /////////////////////////////   RSA-S验证  /////////////////////////////////

        $dinpay_public_key = "-----BEGIN PUBLIC KEY-----"."\r\n".wordwrap(trim($dinpay_public_key),62,"\r\n",true)."\r\n"."-----END PUBLIC KEY-----";
        
        $dinpay_public_key = openssl_get_publickey($dinpay_public_key);
        
        $flag = openssl_verify($signStr,$dinpaySign,$dinpay_public_key,OPENSSL_ALGO_MD5);   
        
        
    ///////////////////////////   响应“SUCCESS” /////////////////////////////
        if($flag){
            $orderNo = $order_no;
            $money = $order_amount;
            $callbackData = array();
            if (stripos($orderNo, 'promoter') !== false) {
                $order = $this->model->table("district_order")->where("order_no ='" . $orderNo . "'")->find();
                if ($order) {
                    $payment_id = $order['payment_id'];
                    if ($order['pay_status'] == 1) {
                        $paymentPlugin->asyncStop();
                        exit;
                    } else {
                        DistrictLogic::getInstance()->promoterPayCallback($orderNo, $money, $payment_id, $callbackData);
                        $paymentPlugin->asyncStop();
                        exit;
                    }
                } else {
                    file_put_contents('district_payErr.txt', date("Y-m-d H:i:s") . "==promoter==\n" . json_encode($callbackData) . "\n", FILE_APPEND);
                    exit;
                }
            } else if (stripos($orderNo, 'district') !== false) {
                $district_id = intval(substr($orderNo, stripos($orderNo, 'district') + 8));
                $district = new Model("district_apply");
                $district_info = $district->where("id=$district_id")->find();
                if (!empty($district_info)) {
                    if ($district_info['pay_status'] == 1) {
                        $paymentPlugin->asyncStop();
                        exit;
                    } else {
                        $payment_info = $payment->getPayment();
                        $result = $district->where("id=" . $district_id)->data(array("pay_status" => 1, "payment_name" => $payment_info['name'], 'pay_time' => date("Y-m-d H:i:s")))->update();
                        if ($result) {
                            Common::autoPassDistrictApply($district_id);
                            $paymentPlugin->asyncStop();
                            exit;
                        } else {
                            file_put_contents('district_payErr.txt', date("Y-m-d H:i:s") . "==district ID:$district_id==\n" . json_encode($callbackData) . "\n", FILE_APPEND);
                            exit;
                        }
                    }
                } else {
                    file_put_contents('district_payErr.txt', date("Y-m-d H:i:s") . "==district ID:$district_id==\n" . json_encode($callbackData) . "\n", FILE_APPEND);
                    exit;
                }
            } else if (stripos($orderNo, 'recharge') !== false) {//充值方式
                $recharge_no = substr($orderNo, stripos($orderNo, 'recharge') + 8);
                $recharge_no = $recharge_no == "" ? 0 : $recharge_no;
                $recharge = new Model('recharge');
                $recharge_info = $recharge->where("recharge_no='{$recharge_no}'")->find();
                if($recharge_info){
                    switch ($recharge_info['payment_name']) {
                        case '微信[JSAPI]':
                            $payment_id = 6;
                            break;
                        case '微信[APP]':
                            $payment_id = 7;
                            break;
                        case '支付宝[APP]':
                            $payment_id = 16;
                            break;
                        case '银联手机控件支付':
                            $payment_id = 14;
                            break;        
                        default:
                            $payment_id = 6;
                            break;
                    }
                    if (Order::recharge($recharge_no, $payment_id)) {
                        $paymentPlugin->asyncStop();
                        exit;
                    }
                }   
            } else if (stripos($orderNo, 'redbag') !== false) {//发送红包
                $redbag = new Model('redbag');
                $redbag_info = $redbag->where("order_no='{$orderNo}'")->find();
                if($redbag_info){
                    $ret = $redbag->data(array('pay_status'=>1))->where("order_no='{$orderNo}'")->update();
                    if ($ret) {
                        $paymentPlugin->asyncStop();
                        exit;
                    }
                }  
            } else {
                //如果是订单支付的话
                $model = new Model();
                $order_info = $model->table('order')->where("order_no='{$orderNo}'")->find();
                $order_offline = $model->table('order_offline')->where("order_no='{$orderNo}'")->find();
                if (!empty($order_info)) {
                    $payment_id = $order_info['payment'];
                    if ($order_info['type'] == 4 && $order_info['is_new'] == 0) {
                        if ($order_info['otherpay_amount'] > $money) {
                            file_put_contents('payErr.txt', date("Y-m-d H:i:s") . "|========订单金额不符,订单号：{$orderNo}|{$order_info['order_amount']}元|{$money}元|{$payment_id}========|\n", FILE_APPEND);
                            exit;
                        }
                    } else if ($order_info['order_amount'] > $money) {
                        file_put_contents('payErr.txt', date("Y-m-d H:i:s") . "|========订单金额不符,订单号：{$orderNo}|{$order_info['order_amount']}元|{$money}元|{$payment_id}========|\n", FILE_APPEND);
                        exit;
                    }
                    $order_id = Order::updateStatus($orderNo, $payment_id, $callbackData);
                    if ($order_id) {
                        $paymentPlugin->asyncStop();
                        exit;
                    } 
                }elseif(!empty($order_offline)){
                    $order_no = $orderNo;
                     $order=$this->model->table('order_offline')->where("order_no='{$order_no}'")->find();
                        $this->model->table('order_offline')->where("order_no='{$order_no}'")->data(array('status'=>3,'pay_status'=>1,'delivery_status'=>1,'pay_time'=>date('Y-m-d H:i:s')))->update();
                        // $invite_id=Session::get('invite_id');
                        $invite_id=$order['prom_id'];
                        $seller_id=$order['shop_ids'];             
                        if($invite_id==null){
                            $invite_id=1;
                        }
                        if($seller_id==0){
                            $seller_id=$invite_id;
                        }
                        // $promoter_id=Common::getFirstPromoter($order['user_id']);
                        $exist=$this->model->table('balance_log')->where("order_no='{$order_no}'")->find();
                        if(!$exist){
                            //如果卖家是邀请人的话不参与分账
                            if($seller_id!=$invite_id){
                                $config = Config::getInstance()->get("district_set");
                                $promoter = $this->model->table('district_promoter')->fields('base_rate')->where('user_id='.$seller_id)->find();
                                if($promoter){
                                    $amount = round($order['order_amount']*(100-$promoter['base_rate'])/100,2);
                                }else{
                                    $amount = round($order['order_amount']*(100-$config['offline_base_rate'])/100,2);
                                }     
                                $this->model->table('customer')->where('user_id='.$seller_id)->data(array("offline_balance"=>"`offline_balance`+({$amount})"))->update();//平台收益提成
                                Log::balance($amount, $seller_id, $order_no,'线下会员消费卖家收益', 8);
                                Common::offlineBeneficial($order_no,$invite_id,$seller_id);
                                $this->model->table('order_offline')->where("order_no='{$order_no}'")->data(array('payable_amount'=>$amount))->update();
                                $money = $amount;
                            }else{
                                $this->model->table('customer')->where('user_id='.$seller_id)->data(array("offline_balance"=>"`offline_balance`+({$order['order_amount']})"))->update();//平台收益提成
                                 Log::balance($order['order_amount'], $seller_id, $order_no,'线下会员消费卖家收益(不参与分账)', 8);
                                 $money = $order['order_amount'];
                            }
                             //微信公众号消息推送
                                #*****************推送消息***************
                                $wechatcfg = $this->model->table("oauth")->where("class_name='WechatOAuth'")->find();
                                $wechat = new WechatMenu($wechatcfg['app_key'], $wechatcfg['app_secret'], '');
                                $token = $wechat->getAccessToken();
                                $oauth_info = $this->model->table("oauth_user")->fields("open_id,open_name")->where("user_id=".$seller_id." and oauth_type='wechat'")->find();
                                if($oauth_info){
                                    $params = array(
                                    'touser' => $oauth_info['open_id'],
                                    'msgtype' => 'text',
                                    "text" => array(
                                            'content' => "亲爱的商家:{$oauth_info['open_name']},您获得商家消费收益{$money}元，请登录个人中心查看。"
                                            )
                                        );
                                    $result = Http::curlPost("https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token={$token}", json_encode($params, JSON_UNESCAPED_UNICODE));
                                }      
                                #****************************************
                             //APP极光推送
                             if($order['type']==2 || $order['type']==3){
                                  $type = 'offline_balance';
                                  $content = "收款到账{$money}元";
                                  $platform = 'all';
                                  if (!$this->jpush) {
                                          $NoticeService = new NoticeService();
                                          $this->jpush = $NoticeService->getNotice('jpush');
                                      }
                                  $audience['alias'] = array($seller_id);
                                  $this->jpush->setPushData($platform, $audience, $content, $type, "");
                                  $this->jpush->push();
                             }              
                        }
                }
                
            }    
            echo"SUCCESS";  
        }else{
            echo"Verification Error"; 
        }  
    }

    public function pay_alipay_submit() {
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
            $out_trade_no = $_GET['out_trade_no'];
            $order_no = $out_trade_no;
            if (stripos($out_trade_no, 'promoter') !== false) {//推广员入驻订单
                $order = $this->model->table("district_order")->where("order_no ='" . $order_no . "'")->find();
                if (!$order) {
                    $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '支付信息错误', "content" => "抱歉，找不到您的订单信息"));
                    exit();
                }
                $success_url = Url::urlFormat("/ucenter/index/first/1");
                $cancel_url = Url::urlFormat("/ucenter/becomepromoter/reference/{$order['invitor_id']}/invitor_role/{$order['invitor_role']}");
                $error_url = Url::urlFormat("/ucenter/becomepromoter/reference/{$order['invitor_id']}/invitor_role/{$order['invitor_role']}");
            } else if (stripos($out_trade_no, 'district') !== false) {//专区入驻订单
                $apply_id = intval(substr($order_no, stripos($order_no, 'district') + 8));
                $order = $this->model->table("district_apply")->where("id=$apply_id")->find();
                if (!$order) {
                    $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '支付信息错误', "content" => "抱歉，找不到您的订单信息"));
                    exit();
                }
                $success_url = Url::urlFormat("/district/district");
                $cancel_url = Url::urlFormat("/ucenter/district_pay/id/{$order['id']}");
                $error_url = Url::urlFormat("/ucenter/district_pay/id/{$order['id']}");
            } else if (stripos($out_trade_no, 'recharge') !== false) {//充值订单
                $recharge_no = substr($order_no, 8);
                $order = $this->model->table("recharge")->where("recharge_no ='" . $recharge_no . "'")->find();
                if (!$order) {
                    $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '支付信息错误', "content" => "抱歉，找不到您的订单信息"));
                    exit();
                }
                $success_url = Url::urlFormat("/ucenter/asset");
                $cancel_url = Url::urlFormat("/ucenter/recharge_center");
                $error_url = Url::urlFormat("/ucenter/recharge_center");
            } else {//商品订单
                $order = $this->model->table("order")->where("order_no='{$order_no}'")->find();
                $offline_order = $this->model->table("order_offline")->where("order_no='{$order_no}'")->find();
                if ($order) {
                    $success_url = Url::urlFormat("/ucenter/order_detail/id/{$order['id']}");
                    $cancel_url = Url::urlFormat("/simple/order_status/order_id/{$order['id']}");
                    $error_url = Url::urlFormat("/simple/order_status/order_id/{$order['id']}");
                }elseif($offline_order){
                    // var_dump($offline_order);die;
                    $success_url = Url::urlFormat("/ucenter/order_details/id/{$offline_order['id']}");
                    $cancel_url = Url::urlFormat("/simple/offline_order_status/order_id/{$offline_order['id']}");
                    $error_url = Url::urlFormat("/simple/offline_order_status/order_id/{$offline_order['id']}");
                }else{
                    $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '支付信息错误', "content" => "抱歉，找不到您的订单信息啦"));
                    exit();
                }
            } 
            $this->assign("success_url", $success_url);
            $this->assign("cancel_url", $cancel_url);
            $this->assign("error_url", $error_url);
            $this->redirect();
        } else {
            $sendData = $_GET;
            unset($sendData['con'], $sendData['act']);
            $this->assign("sendData", $sendData);
            $this->redirect();
        }
    }

    //微信jsapi提交处理
    public function pay_wxpayjsapi_submit() {
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
            $out_trade_no = isset($_POST['out_trade_no'])?Filter::sql($_POST['out_trade_no']):$_GET['out_trade_no'];
            $return_url = isset($_POST['return_url'])?Filter::sql($_POST['return_url']):'';
            //获取真实订单号 exp :5567_promoter2017050514260743
            if (stripos($out_trade_no, 'promoter') !== false) {//推广员入驻订单
                $order_no = substr($out_trade_no, 5);
                $order = $this->model->table("district_order")->where("order_no ='" . $order_no . "'")->find();
                if (!$order) {
                    $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '支付信息错误', "content" => "抱歉，找不到您的订单信息"));
                    exit();
                }
                $success_url = Url::urlFormat("/ucenter/index/first/1");
                $cancel_url = Url::urlFormat("/ucenter/becomepromoter/reference/{$order['invitor_id']}/invitor_role/{$order['invitor_role']}");
                $error_url = Url::urlFormat("/ucenter/becomepromoter/reference/{$order['invitor_id']}/invitor_role/{$order['invitor_role']}");
            } else if (stripos($out_trade_no, 'district') !== false) {//专区入驻订单
                $order_no = substr($out_trade_no, 5);
                $apply_id = intval(substr($order_no, stripos($order_no, 'district') + 8));
                $order = $this->model->table("district_apply")->where("id=$apply_id")->find();
                if (!$order) {
                    $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '支付信息错误', "content" => "抱歉，找不到您的订单信息"));
                    exit();
                }
                $success_url = Url::urlFormat("/district/district");
                $cancel_url = Url::urlFormat("/ucenter/district_pay/id/{$order['id']}");
                $error_url = Url::urlFormat("/ucenter/district_pay/id/{$order['id']}");
            } else if (stripos($out_trade_no, 'recharge') !== false) {//充值订单
                $order_no = substr($out_trade_no, 5);
                $recharge_no = substr($order_no, 8);
                $order = $this->model->table("recharge")->where("recharge_no ='" . $recharge_no . "'")->find();
                if (!$order) {
                    $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '支付信息错误', "content" => "抱歉，找不到您的订单信息"));
                    exit();
                }
                $success_url = Url::urlFormat("/ucenter/asset");
                $cancel_url = Url::urlFormat("/ucenter/recharge_center");
                $error_url = Url::urlFormat("/ucenter/recharge_center");
            } else {//商品订单
                $order_no = $out_trade_no;
                $order = $this->model->table("order")->where("order_no='{$order_no}'")->find();
                $offline_order = $this->model->table("order_offline")->where("order_no='{$order_no}'")->find();
                if ($order) {
                    $success_url = Url::urlFormat("/ucenter/order_detail/id/{$order['id']}");
                    $cancel_url = Url::urlFormat("/simple/order_status/order_id/{$order['id']}");
                    $error_url = Url::urlFormat("/simple/order_status/order_id/{$order['id']}");
                }elseif($offline_order){
                    // var_dump($offline_order);die;
                    // $success_url = Url::urlFormat("/ucenter/order_details/id/{$offline_order['id']}");
                    
                    $success_url = Url::urlFormat("/travel/order_details/id/{$offline_order['id']}");
                    
                    $cancel_url = Url::urlFormat("/simple/offline_order_status/order_id/{$offline_order['id']}");
                    $error_url = Url::urlFormat("/simple/offline_order_status/order_id/{$offline_order['id']}");
                }else{
                    $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '支付信息错误', "content" => "抱歉，找不到您的订单信息啦"));
                    exit();
                }
            }
            $this->assign("success_url", $success_url);
            $this->assign("cancel_url", $cancel_url);
            $this->assign("error_url", $error_url);
            //①、获取用户openid
            $tools = new JsApiPay();
            $oauth = $this->model->table("oauth_user")->where("oauth_type='wechat' AND user_id='{$this->user['id']}'")->find();
            $openId = $oauth ? $oauth['open_id'] : $tools->GetOpenid();

            //②、统一下单
            $input = new WxPayUnifiedOrder();
            $input->SetBody($_POST['subject']);
            $input->SetAttach($order_no);
            $input->SetOut_trade_no($out_trade_no);
            $input->SetTotal_fee(intval(bcmul($_POST['price'],100)));
            $input->SetTime_start(date("YmdHis"));
            $input->SetTime_expire(date("YmdHis", time() + 3600));
            $input->SetGoods_tag("test");
            $input->SetNotify_url($_POST['notify_url']);
            $input->SetTrade_type("JSAPI");
            $input->SetOpenid($openId);

            $order = WxPayApi::unifiedOrder($input);
            
            $jsApiParameters = $tools->GetJsApiParameters($order);

            // if($this->user['id']==20942){
            //     $jsApiParameters = Session::get('payinfo');
            //     var_dump($jsApiParameters);die;
            // }
            // $jsApiParameters = Session::get('payinfo');
            $offline=0;
            // var_dump($jsApiParameters);die;
            //获取共享收货地址js函数参数
            $editAddress = $tools->GetEditAddressParameters();

            $this->assign("return_url", $return_url); //好像没用到
            $this->assign("jsApiParameters", $jsApiParameters);
            $this->assign("offline",$offline);
            $this->assign("editAddress", $editAddress);
            $this->redirect();
        } else {
            $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '抱歉', "content" => "请在微信中发起支付"));
            exit();
            // $sendData = $_POST;
            // unset($sendData['con'], $sendData['act']);
            // $this->assign("sendData", $sendData);
            // $this->redirect();
        }
    }

    //线下微信jsapi提交处理
    public function pay_wxpayjsapi_submits() {
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
            $out_trade_no = Filter::sql($_POST['out_trade_no']);
            $return_url = Filter::sql($_POST['return_url']);
            //获取真实订单号 exp :5567_promoter2017050514260743
            $order_no = substr($out_trade_no, 5);
            $order = $this->model->table("order_offline")->where("order_no='{$order_no}'")->find();
            if (!$order) {
                $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '支付信息错误', "content" => "抱歉，找不到您的订单信息了"));
                exit();
            }
                
            $success_url = Url::urlFormat("/ucenter/order_details/id/{$order['id']}");
            
            $cancel_url = Url::urlFormat("/simple/offline_order_status/order_id/{$order['id']}");
            $error_url = Url::urlFormat("/simple/offline_order_status/order_id/{$order['id']}");
            
            $this->assign("success_url", $success_url);
            $this->assign("cancel_url", $cancel_url);
            $this->assign("error_url", $error_url);
            //①、获取用户openid
            $tools = new JsApiPay();
            $payinfo=Session::get('payinfo');
            $jsApiParameters=json_encode($payinfo);
            $offline=1;
            // var_dump($jsApiParameters);die;
            //获取共享收货地址js函数参数
            $editAddress = $tools->GetEditAddressParameters();

            $this->assign("return_url", $return_url); //好像没用到
            $this->assign("jsApiParameters", $jsApiParameters);
            $this->assign("offline",$offline);
            $this->assign("editAddress", $editAddress);
            $this->redirect();
        } else {
            $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '抱歉', "content" => "请在微信中发起支付"));
            exit();
            // $sendData = $_POST;
            // unset($sendData['con'], $sendData['act']);
            // $this->assign("sendData", $sendData);
            // $this->redirect();
        }
    }

    public function pay_wxpayqrcode_submit() {
        $config = Config::getInstance();
        $site_config = $config->get("globals");
        $this->assign('seo_title', $site_config['site_name']);
        $this->assign('site_title', $site_config['site_name']);
        $selectids = Req::args('selectids');
        $cart = Cart::getCart();
        $this->cart = $cart->all();
        $this->selectcart = $this->cart;
        $this->assign("cart", $this->cart);

        $orderarr = explode('_', $_POST['out_trade_no']);
        $order_no = end($orderarr);
        $id = '';
        if (stripos($order_no, 'recharge') !== false) {
            $this->assign('isRecharge', true);
        } else {
            $this->assign("isRecharge", false);
            $order = new Model('order');
            $result = $order->where("order_no ='" . $order_no . "'")->fields('id')->find();
            $id = $result['id'];
        }

        $order = array(
            'id' => $id,
            'payurl' => $_POST['payurl'],
            'price' => Filter::sql($_POST['price'])
        );
        $this->layout = "simple";
        $this->assign("order_no", $order_no);
        $this->assign("order", $order);
        $this->assign("user", $this->user);
        $this->assign("category", array());

        $this->redirect();
    }

    public function refund_callback() {
        $callbackData = Req::args(); //array_merge($_POST,$_GET);
        file_put_contents("callbackTxt.txt", "退款回调数据：\r\n" . json_encode($callbackData) . "\r\n______" . date('Y-m-d H:i:s') . "___________", FILE_APPEND);
        $payment_id = Filter::int(Req::args('payment_id'));
        $payment = new Payment($payment_id);
        $paymentPlugin = $payment->getPaymentPlugin();

        if (!is_object($paymentPlugin)) {
            echo "fail";
        }
        unset($callbackData['con']);
        unset($callbackData['act']);
        unset($callbackData['payment_id']);
        unset($callbackData['tiny_token_redirect']);
        $result = $paymentPlugin->refundCallback($callbackData);
        if ($result['status'] == "success") {
            //更新订单，并将退款进度更新
            $order = new Model('refund');
            $refund_id = array();
            if (isset($result['order_no'])) {
                $refund_id = $order->where("order_no ='" . $result['order_no'] . "'")->fields('id,refund_progress')->find();
            } else if (isset($result['refund_no'])) {
                $refund_id = $order->where("refund_no ='" . $result['refund_no'] . "'")->fields('id,refund_progress')->find();
            }
            if (!empty($refund_id)) {
                if ($refund_id['refund_progress'] == 3) {//已经操作过了，不需要重复更新了
                    echo 200;
                }
                Order::refunded($refund_id['id'], 1);
            } else {
                file_put_contents("refundError.txt", "获取refund_id失败：\r\n" . json_encode($callbackData) . "\r\n__________________________", FILE_APPEND);
            }
        } else {
            file_put_contents("refundError.txt", "失败了：\r\n" . json_encode($result) . '\r\n' . json_encode($callbackData) . "\r\n__________________________", FILE_APPEND);
        }
    }

    public function mdpay() {
        $type = Filter::int(Req::args("type"));
        $id = Filter::int(Req::args("id"));
        $model = new Model();
        if ($type == 1) {
            $apply = $model->table("district_apply")->where("status = 0 and id=$id")->find();
            if ($apply) {
                if ($apply['pay_status'] == '0') {
                    $pay_params = $model->table('mdpay_params')->where("id = 1")->find();
                    if (empty($pay_params) || !isset($pay_params['mid'])) {
                        exit("尚未配置支付参数");
                    }
                    $config_all = Config::getInstance();
                    $set = $config_all->get('district_set');
                    if (isset($set['join_fee'])) {
                        $amount = $set['join_fee'] * 100;
                    } else {
                        $amount = 10000 * 100;
                    }
                    $insert_id = $model->table("mdpay_order")
                                    ->data(array(
                                        'md_amount' => $amount,
                                        'md_mid' => $pay_params['mid'],
                                        'remark' => "专区加盟",
                                        'create_time' => date("Y-m-d H:i:s"),
                                        'type' => 1,
                                        'type_id' => $id,
                                        'pay_status' => 0,
                                    ))->insert();
                    if ($insert_id) {
                        if ($insert_id > 9999999) {
                            exit("订单号记录超出");
                        }
                        $no = "1" . sprintf("%05d", $insert_id) . rand(10, 99); //产生唯一的no
                        $isOk = $model->table("mdpay_order")->where("id = $insert_id")->data(array("md_no" => $no))->update();
                        if ($isOk) {
                            $input = new MdPayPreOrderData();
                            $input->SetPid($pay_params['pid']);
                            $input->SetMid($pay_params['mid']);
                            $input->SetPkey($pay_params['pkey']);
                            $input->SetNo($no);
                            $input->SetAmount($amount); //1
                            $input->SetState("join_fee");
                            $input->SetSign();
                            $result = MdPayApi::preOrder($input);
                            if ($result['result'] == '0000') {
                                $pay_input = new MdPayQrPayData();
                                $pay_input->SetAmount($amount);
                                $pay_input->SetNo($no);
                                $pay_input->SetDpPid($pay_params['pid']);
                                $pay_input->SetDpMid($pay_params['mid']);
                                $pay_input->SetMtoken($result['mtoken']);
                                $pay_input->SetSign($result['sign']);
                                $pay_url = MdPayApi::qrPayUrl($pay_input);
                                $this->assign("pay_url", $pay_url);
                                $this->redirect();
                            } else {
                                $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '操作失败', "content" => $result['errStr'], "redirect_url" => Url::urlFormat("/payment/mdpay/type/1/id/$id"), 'url_name' => '重试'));
                                exit();
                            }
                        }
                    } else {
                        $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '操作失败', "content" => "数据库错误,请重试", "redirect_url" => Url::urlFormat("/payment/mdpay/type/1/id/$id"), 'url_name' => '重试'));
                        exit();
                    }
                } else if ($apply['pay_status'] == '1') {
                    $this->redirect("/index/msg", false, array('type' => "success", "msg" => '支付成功', "content" => "您已经支付了加盟费用，请等待后台审核申请"));
                    exit();
                }
            } else {
                $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '操作失败', "content" => "查询出错，请检查连接是否正确"));
                exit();
            }
        } else {
            $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '操作失败', "content" => "暂不支持其他类型"));
            exit();
        }
    }

    //秒到支付的回调
    public function mdCallback() {
        $data = json_decode(file_get_contents("php://input"), true);
        if (is_array($data) && !empty($data)) {
            if ($data['result'] == '0000') {
                $input = new MdPayQueryData();
                $input->SetDpMid($data['merchId']);
                $input->SetDpNo($data['moid']);
                $query_result = MdPayApi::query($input);
                if ($query_result['result'] == '0000') {
                    $model = new Model();
                    $order = $model->table('mdpay_order')->where("md_no ='" . $data['moid'] . "' and md_mid = " . $data['merchId'])->find();
                    if ($order) {
                        if ($order['type'] == 1) {
                            $model->table('district_apply')->where("id=" . $order['type_id'])->data(array("pay_status" => 1, "payment_name" => "秒到支付", 'pay_time' => date("Y-m-d H:i:s")))->update();
                            $model->table('mdpay_order')->where("md_no ='" . $data['moid'] . "' and md_mid = " . $data['merchId'])
                                    ->data(array("pay_time" => date("Y-m-d H:i:s"), "pay_type" => $data['tradeType'], 'pay_status' => 1))
                                    ->update();
                            Common::autoPassDistrictApply($order['type_id']);
                            exit("SUCCESS");
                        }
                    }
                } else {
                    file_put_contents('mdpayError.txt', date("Y-m-d H:i:s") . "验证不通过\r\n" . json_encode($data) . "\r\n", FILE_APPEND);
                    exit("fail");
                }
            } else {
                file_put_contents('mdpayError.txt', date("Y-m-d H:i:s") . json_encode($data) . "\r\n", FILE_APPEND);
                exit("fail");
            }
        } else {
            exit("数据格式错误");
        }
    }

    public function pay_district() {
        // 获得payment_id 获得相关参数
        $payment_id = Filter::int(Req::args('payment_id'));
        $district_id = Filter::int(Req::args('district_id'));

        if ($district_id) {
            $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '抱歉', "content" => "专区支付已经关闭"));
            exit();
//            $model = new Model();
//            $district_info = $model->table("district_apply")->where("id={$district_id}")->find();
//            if (!empty($district_info)) {
//                if ($district_info['pay_status'] == 0) {
//                    if ($payment_id != 999) {
//                        $payment = new Payment($payment_id);
//                        $paymentPlugin = $payment->getPaymentPlugin();
//                        $paymentInfo = $payment->getPayment();
//                        $district_order_no = "district" . sprintf("%08d", $district_info['id']);
//                        $safebox = Safebox::getInstance();
//                        $user = $safebox->get('user');
//                        $config_all = Config::getInstance();
//                        $set = $config_all->get('district_set');
//                        if (isset($set['join_fee'])) {
//                            $amount = $set['join_fee'];
//                        } else {
//                            $amount = 10000;
//                        }
//                        if (isset($user['id']) && ($user['id'] == 5 || $user['id'] == 693 || $user['id'] == 683 || $user['id'] == 2 || $user['id'] == 6 || $user['id'] == 42 || $user['id'] == 52)) {
//                            $amount = 0.01;
//                        }
//                        $data = array('amount' => $amount, 'district_order_no' => $district_order_no, 'district_id' => $district_info['id']);
//                        $packData = $payment->getPaymentInfo('district', $data);
//                        $sendData = $paymentPlugin->packData($packData);
//                        if (!$paymentPlugin->isNeedSubmit()) {
//                            exit();
//                        }
//                        $this->assign("paymentPlugin", $paymentPlugin);
//                        $this->assign("sendData", $sendData);
//                        if ($paymentPlugin instanceof pay_balance) {
//                            $model = new Model('user as us');
//                            $userInfo = $model->join('left join customer as cu on us.id = cu.user_id')->where('cu.user_id = ' . $this->user['id'])->find();
//                            if ($userInfo['pay_password_open'] == 1) {
//                                $this->assign('pay_balance', true);
//                                $this->assign('userInfo', $userInfo);
//                                $this->assign('order_id', $order_id);
//                                $this->assign('payment_id', $payment_id);
//                            }
//                        } else if ($paymentPlugin instanceof pay_silver) {
//                            $model = new Model('user as us');
//                            $userInfo = $model->join('left join customer as cu on us.id = cu.user_id')->where('cu.user_id = ' . $this->user['id'])->find();
//                            if ($userInfo['pay_password_open'] == 1) {
//                                $this->assign('pay_silver', true);
//                                $this->assign('userInfo', $userInfo);
//                                $this->assign('order_id', $order_id);
//                                $this->assign('payment_id', $payment_id);
//                            }
//                        }
//                        $this->redirect('pay_form', false);
//                    } else {
//                        $this->redirect("payment/mdpay/type/1/id/$district_id");
//                        exit();
//                    }
//                } else if ($district_info['pay_status'] == 1) {
//                    $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '抱歉', "content" => "已经支付过了"));
//                    exit();
//                }
//            } else {
//                $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '抱歉', "content" => "信息不存在"));
//                exit();
//            }
        } else {
            $gift = Filter::int(Req::args('gift'));
            $address_id = Filter::int(Req::args('address_id'));
            $reference = Filter::int(Req::args("reference"));
            $invitor_role = Filter::str(Req::args("invitor_role"));
            //1.判断信息是否正确。
            $promoter = Promoter::getPromoterInstance($this->user['id']);
            if (is_object($promoter)&&$promoter->role_type==2) {
                $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '操作失败', "content" => "抱歉，您已经有雇佣关系了，暂时不能加入其他专区"));
                exit();
            }
            $config = Config::getInstance()->get("district_set");
            $gift_list = explode("|",$config['join_send_gift']);
            if (!in_array($gift, $gift_list)) {
                $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '抱歉', "content" => "选择的礼品不存在"));
                exit();
            }
            $isset = $this->model->table("address")->where("user_id = " . $this->user['id'] . " and id = $address_id")->count();
            if ($isset != 1) {
                $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '抱歉', "content" => "选择的地址不存在"));
                exit();
            }
            if ($invitor_role) {
                if ($invitor_role == 'shop') {
                    $district_info = $this->model->table("district_shop")->where("id = $reference")->find();
                    if (!$district_info) {
                        $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '抱歉', "content" => "专区信息错误"));
                        exit();
                    }
                    $insert_data['invitor_role'] = 'shop';
                } else if ($invitor_role == 'promoter') {
                    $district_info = $this->model
                            ->table("district_promoter as dp")->join("left join district_shop as ds on dp.hirer_id = ds.id")
                            ->where("dp.id = $reference")
                            ->find();
                    if (!$district_info) {
                        $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '抱歉', "content" => "专区信息错误"));
                        exit();
                    }
                    $insert_data['invitor_role'] = "promoter";
                } else {
                    $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '抱歉', "content" => "role信息错误"));
                    exit();
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
                    $paymentInfo = $payment->getPayment();
                    $amount = $config['promoter_fee'];
                    
                    $data = array('amount' => $amount, 'order_no' => $order_no, 'order_id' => $order_id);
                    $packData = $payment->getPaymentInfo('promoter', $data);
                    $sendData = $paymentPlugin->packData($packData);
                    if (!$paymentPlugin->isNeedSubmit()) {
                        exit();
                    }
                    $this->assign("paymentPlugin", $paymentPlugin);
                    $this->assign("sendData", $sendData);
                    if ($paymentPlugin instanceof pay_balance || $paymentPlugin instanceof pay_silver) {
                        $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '抱歉', "content" => "支付方式错误"));
                        exit();
                    }
                    $this->redirect('pay_form', false);
                } else {
                    $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '抱歉', "content" => "数据库错误"));
                    exit();
                }
            } else {
                $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '抱歉', "content" => "role信息错误"));
                exit();
            }
        }
    }
    
    public function receive(){
          //如果需要用证书加密，使用phpseclib包
        set_include_path(get_include_path() . PATH_SEPARATOR . 'phpseclib');
        require("./X509.php"); 
        require("./RSA.php");

        //如果不用证书加密，使用php_rsa.php函数
        require_once("./php_rsa.php"); 
        
        //测试商户的key! 请修改。
        $md5key = "1234567890";
        
        $merchantId=$_POST["merchantId"];
        $version=$_POST['version'];
        $language=$_POST['language'];
        $signType=$_POST['signType'];
        $payType=$_POST['payType'];
        $issuerId=$_POST['issuerId'];
        $paymentOrderId=$_POST['paymentOrderId'];
        $orderNo=$_POST['orderNo'];
        $orderDatetime=$_POST['orderDatetime'];
        $orderAmount=$_POST['orderAmount'];
        $payDatetime=$_POST['payDatetime'];
        $payAmount=$_POST['payAmount'];
        $ext1=$_POST['ext1'];
        $ext2=$_POST['ext2'];
        $payResult=$_POST['payResult'];
        $errorCode=$_POST['errorCode'];
        $returnDatetime=$_POST['returnDatetime'];
        $signMsg=$_POST["signMsg"];
        
        
        $bufSignSrc="";
        if($merchantId != "")
        $bufSignSrc=$bufSignSrc."merchantId=".$merchantId."&";      
        if($version != "")
        $bufSignSrc=$bufSignSrc."version=".$version."&";        
        if($language != "")
        $bufSignSrc=$bufSignSrc."language=".$language."&";      
        if($signType != "")
        $bufSignSrc=$bufSignSrc."signType=".$signType."&";      
        if($payType != "")
        $bufSignSrc=$bufSignSrc."payType=".$payType."&";
        if($issuerId != "")
        $bufSignSrc=$bufSignSrc."issuerId=".$issuerId."&";
        if($paymentOrderId != "")
        $bufSignSrc=$bufSignSrc."paymentOrderId=".$paymentOrderId."&";
        if($orderNo != "")
        $bufSignSrc=$bufSignSrc."orderNo=".$orderNo."&";
        if($orderDatetime != "")
        $bufSignSrc=$bufSignSrc."orderDatetime=".$orderDatetime."&";
        if($orderAmount != "")
        $bufSignSrc=$bufSignSrc."orderAmount=".$orderAmount."&";
        if($payDatetime != "")
        $bufSignSrc=$bufSignSrc."payDatetime=".$payDatetime."&";
        if($payAmount != "")
        $bufSignSrc=$bufSignSrc."payAmount=".$payAmount."&";
        if($ext1 != "")
        $bufSignSrc=$bufSignSrc."ext1=".$ext1."&";
        if($ext2 != "")
        $bufSignSrc=$bufSignSrc."ext2=".$ext2."&";
        if($payResult != "")
        $bufSignSrc=$bufSignSrc."payResult=".$payResult."&";
        if($errorCode != "")
        $bufSignSrc=$bufSignSrc."errorCode=".$errorCode."&";
        if($returnDatetime != "")
        $bufSignSrc=$bufSignSrc."returnDatetime=".$returnDatetime;
        
        //验签
        
        /*
        //解析publickey.txt文本获取公钥信息
        $publickeyfile = './publickey.txt';
        $publickeycontent = file_get_contents($publickeyfile);

        $publickeyarray = explode(PHP_EOL, $publickeycontent);
        $publickey_arr = explode('=',$publickeyarray[0]);
        $modulus_arr = explode('=',$publickeyarray[1]);
        $publickey = trim($publickey_arr[1]);
        $modulus = trim($modulus_arr[1]);
            
        $keylength = 1024;
        $verifyResult = rsa_verify($bufSignSrc,$signMsg, $publickey, $modulus, $keylength,"sha1");
        */
        
        
        //解析证书方式
        $certfile = file_get_contents('TLCert-test.cer');
        $x509 = new File_X509();
        $cert = $x509->loadX509($certfile);
        $pubkey = $x509->getPublicKey();
        $rsa = new Crypt_RSA();
        $rsa->loadKey($pubkey); // public key
        $rsa->setSignatureMode(CRYPT_RSA_SIGNATURE_PKCS1);
        $verifyResult = $rsa->verify($bufSignSrc, base64_decode(trim($signMsg)));
        
        
        $value = null;
        if($verifyResult){
            $value = "报文验签成功！";
        }
        else{
            $value = "报文验签失败！";
        }
        
        //验签成功，还需要判断订单状态，为"1"表示支付成功。
        $payvalue = null;
        $pay_result = false;
        if($verifyResult and $payResult == 1){
            $pay_result = true;
            $payvalue = "报文验签成功，且订单支付成功";
        }else{
          $payvalue = "报文验签成功，但订单支付失败";
        }
    }

    public function test(){
        $orderNo = Filter::str(Req::args('recharge_no'));
        var_dump(Order::recharge($orderNo));
    }
    
    //返回状态给微信服务器
    public function returnStatus($status){
         if($status==1){
            $str="<xml>
                   <return_code><![CDATA[SUCCESS]]></return_code>
                   <return_msg><![CDATA[OK]]></return_msg>
                  </xml>";
          }else{
            $str="<xml>
                  <return_code><![CDATA[FAIL]]></return_code>
                  <return_msg><![CDATA[签名失败]]></return_msg>
                </xml>";
          }
        echo $str;   
    }

}
