<?php

//订单处理类
class Order {

    public static function updateStatus($orderNo, $payment_id = 0, $callback_info = null) {
        $model = new Model("order");
        $order = $model->where("order_no='" . $orderNo . "'")->find();
        if (isset($callback_info['out_trade_no']))
            $trading_info = $callback_info['out_trade_no'];
        else
            $trading_info = '';
        if (empty($order))
            return false;
        if ($order['pay_status'] == 1) {
            return $order['id'];
        } else if ($order['pay_status'] == 0) {
            //更新订单信息
            if($order['type']==4){
                if($order['is_new']==0){
                        $data = array(
                       'status' => 2,
                       'pay_time' => date('Y-m-d H:i:s'),
                       'trading_info' => $trading_info,
                       'pay_status' => 0,
                       // 'otherpay_status'=>1,       
                        ); 
                       
                       
                        $data['pay_status']=0;
                       
                }else{//新的华点订单
                    $data = array(
                       'status' => 3,
                       'pay_time' => date('Y-m-d H:i:s'),
                       'trading_info' => $trading_info,
                       'pay_status' => 1,
                        ); 
                }
            }else{
                $data = array(
                'status' => 3,
                'pay_time' => date('Y-m-d H:i:s'),
                'trading_info' => $trading_info,
                'pay_status' => 1,
             );
            }
            
            //修改用户最后选择的支付方式
            if ($payment_id != 0) {
                $data['payment'] = $payment_id;
            } else {
                $payment_id = $order['payment'];
            }
            //更新订单支付状态
            $model->table("order")->data($data)->where("id=" . $order['id'])->update();

            //商品中优惠券的处理
            $products = $model->table("order_goods")->where("order_id=" . $order['id'])->findAll();
            $order_goods_info = array();
            $goods_ids = array();
            foreach ($products as $pro) {
                $prom = unserialize($pro['prom_goods']);
                if (isset($prom['prom'])) {
                    $prom = $prom['prom'];
                    //商品中优惠券的处理
                    if (isset($prom['type']) && $prom['type'] == 3 && $order['type'] == 0) {
                        $voucher_template_id = $prom['expression'];
                        $voucher_template = $model->table("voucher_template")->where("id=" . $voucher_template_id)->find();
                        Common::paymentVoucher($voucher_template, $order['user_id']);
                        //优惠券发放日志
                    }
                }
                //更新货品中的库存信息
                $goods_nums = $pro['goods_nums'];
                $product_id = $pro['product_id'];
                $model->table("products")->where("id=" . $product_id)->data(array('store_nums' => "`store_nums`-" . $goods_nums))->update();
                $goods_ids[$pro['goods_id']] = $pro['goods_id'];
                $order_goods_info[] = array('goods_id'=>$pro['goods_id'],'product_id'=>$pro['product_id'],'goods_nums'=>$pro['goods_nums'],'unit_price'=>$pro['real_price']);
            }


            //发货提醒
            $template_data = $order;
            $area_parse = array();
            $area_model = new Model('area');
            $areas = $area_model->where("id in(" . $order['province'] . "," . $order['city'] . "," . $order['county'] . ")")->findall();
            foreach ($areas as $area) {
                $area_parse[$area['id']] = $area['name'];
            }
            $template_data['address'] = $area_parse[$order['province']] . $area_parse[$order['city']] . $area_parse[$order['county']] . $order['addr'];
            $NoticeService = new NoticeService();
            $NoticeService->send('payment_order', $template_data);

            //更新商品表里的库存信息
            foreach ($goods_ids as $id) {
                $objs = $model->table('products')->fields('sum(store_nums) as store_nums')->where('goods_id=' . $id)->query();
                if ($objs) {
                    $num = $objs[0]['store_nums'];
                    $model->table('goods')->data(array('store_nums' => $num))->where('id=' . $id)->update();
                }
            }

            //普通订单的处理
            if ($order['type'] == 0 ||$order['type'] == 4) {
                //订单优惠券活动事后处理
                $prom = unserialize($order['prom']);
                if (!empty($prom) && $prom['type'] == 3) {
                    $voucher_template_id = $prom['expression'];
                    $voucher_template = $model->table("voucher_template")->where("id=" . $voucher_template_id)->find();
                    Common::paymentVoucher($voucher_template, $order['user_id']);
                }
            } else if ($order['type'] == 1) {
                //更新团购信息
                $prom = unserialize($order['prom']);
                if (isset($prom['id'])) {
                    $groupbuy = $model->table("groupbuy")->where("id=" . $prom['id'])->find();
                    if ($groupbuy) {
                        $goods_num = $groupbuy['goods_num'];
                        $order_num = $groupbuy['order_num'];
                        $max_num = $groupbuy['max_num'];
                        $end_time = $groupbuy['end_time'];
                        $time_diff = time() - strtotime($end_time);
                        foreach ($products as $pro) {
                            $data = array('goods_num' => ($goods_num + $pro['goods_nums']), 'order_num' => $order_num + 1);
                        }
                        if ($time_diff >= 0 || $max_num <= $data['goods_num'])
                            $data['is_end'] = 1;
                        $model->table("groupbuy")->where("id=" . $prom['id'])->data($data)->update();
                    }
                    $groupbuy_log = $model->table('groupbuy_log')->where('groupbuy_id='.$prom['id'].' and user_id='.$order['user_id'])->find();
                    $model->table('groupbuy_log')->data(['pay_status'=>1])->where('groupbuy_id='.$prom['id'].' and user_id='.$order['user_id'])->update();
                    if($groupbuy_log) {
                        $groupbuy_join = $model->table('groupbuy_join')->where('id='.$groupbuy_log['join_id'])->find();
                        if($groupbuy_join) {
                            $model->table('groupbuy_join')->data(['pay_status'=>1])->where('id='.$groupbuy_log['join_id'])->update();
                        }
                    }
                }
            }else if ($order['type'] == 2) {
                //更新抢购信息
                $prom = unserialize($order['prom']);
                if (isset($prom['id'])) {
                    $flashbuy = $model->table("flash_sale")->where("id=" . $prom['id'])->find();
                    if ($flashbuy) {
                        $goods_num = $flashbuy['goods_num'];
                        $order_num = $flashbuy['order_num'];
                        $max_num = $flashbuy['max_num'];
                        $end_time = $flashbuy['end_time'];
                        $time_diff = time() - strtotime($end_time);
                        foreach ($products as $pro) {
                            $data = array('goods_num' => ($goods_num + $pro['goods_nums']), 'order_num' => $order_num + 1);
                        }
                        if ($time_diff >= 0 || $max_num <= $data['goods_num'])
                            $data['is_end'] = 1;
                        $model->table("flash_sale")->where("id=" . $prom['id'])->data($data)->update();
                    }
                }
            }else if($order['type'] == 6){
                 //更新抢购信息
                $prom = unserialize($order['prom']);
                if (isset($prom['id'])) {
                    $pointflash = $model->table("pointflash_sale")->where("id=" . $prom['id'])->find();
                    if ($pointflash) {
                        $order_count = $pointflash['order_count'];
                        $max_sell_num = $pointflash['max_sell_count'];
                        $end_time = $pointflash['end_date'];
                        $time_diff = time() - strtotime($end_time);
                        $data['order_count']=$order_count + 1;
                        if ($time_diff >= 0 || $max_sell_num <= $order_count)
                            $data['is_end'] = 1;
                        $model->table("pointflash_sale")->where("id=" . $prom['id'])->data($data)->update();
                    }
                }
            }
            //送积分
//            if ($order['point'] > 0) {
//                Pointlog::write($order['user_id'], $order['point'], '购买商品，订单：' . $order['order_no'] . ' 赠送' . $order['point'] . '积分');
//            }
            if($order['type']==2&&$order['point']>0){
                $result = $model->table("customer")->data(array("point_coin"=>"`point_coin`+".$order['point']))->where("user_id=".$order['user_id'])->update();
                if($result){
                    Log::pointcoin_log($order['point'], $order['user_id'], $order['order_no'], "购买商品赠送", 9);
                }
            }
            //记录支付日志
            // $paymentModel = new Model('payment');
            // $paymentObj = $paymentModel->where("id=$payment_id")->find();
            // $paymentName = $paymentObj['pay_name'];
            // Log::balance((0-$order['order_amount']),$order['user_id'],'通过'.$paymentName.'方式进行商品购买,订单编号：'.$order['order_no']);
            //对使用代金券的订单，修改代金券的状态
            if ($order['voucher_id']) {
                $model->table("voucher")->where("id=" . $order['voucher_id'])->data(array('status' => 1))->update();
            }

            //生成收款单
            $receivingData = array(
                'order_id' => $order['id'],
                'user_id' => $order['user_id'],
                'amount' => $order['order_amount'],
                'create_time' => date('Y-m-d H:i:s'),
                'payment_time' => date('Y-m-d H:i:s'),
                'doc_type' => 0,
                'payment_id' => $payment_id,
                'pay_status' => 1
            );
            //防止重复生成收款单
            $issetReceiving = $model->table("doc_receiving")->where("order_id=".$order['id']." and doc_type = 0")->find();
            if(empty($issetReceiving)){
                $model->table("doc_receiving")->data($receivingData)->insert();
            }else{
                $model->table("doc_receiving")->data($receivingData)->where("id =".$issetReceiving['id'])->update();
            }
            
            //统计会员规定时间内的消费金额,进行会员升级。
            $config = Config::getInstance();
            $config_other = $config->get('other');
            $grade_days = isset($config_other['other_grade_days']) ? intval($config_other['other_grade_days']) : 365;
            $time = date("Y-m-d H:i:s", strtotime("-" . $grade_days . " day"));
            $obj = $model->table("doc_receiving")->fields("sum(amount) as amount")->where("user_id=" . $order['user_id'] . " and doc_type=0 and payment_time > '$time'")->query();
            if (isset($obj[0])) {
                $amount = $obj[0]['amount'];
                $grade = $model->table('grade')->where('money < ' . $amount)->order('money desc')->find();
                if ($grade) {
                    $model->table('customer')->data(array('group_id' => $grade['id']))->where("user_id=" . $order['user_id'])->update();
                }
            }
            $client_type = Common::getPayClientByPaymentID($payment_id);
            if($client_type=='ios'||$client_type=='android'){
                //jpush
                $jpush = $NoticeService->getNotice('jpush');
                $audience['alias']=array($order['user_id']);
                $jpush->setPushData('all', $audience, '恭喜您，订单支付成功！', 'order_pay_success', $order['order_no']);
                $jpush->push();
            }
            /*不再使用此功能
            self::updatePromoter($order['user_id']);//OK
            if($order['type']==0&&self::isOnlineCashPay($payment_id)){
                 self::updateCommission(1,$order['id'],$order['user_id']); 
            }
             */
            if($order['type']==0){
                // Common::setIncomeByInviteShip($order);
                Common::setIncomeByInviteShipEachGoods($order);
                if($order['qr_flag']==""){
                    $goods_ids_info = array_column($order_goods_info, "goods_id");
                    Common::autoCreatePersonalShop($order['user_id'], $goods_ids_info);//购买指定商品即可开通店铺权限
                }
                // else{
                //     DistrictLogic::getInstance()->districtIncomeAssign($order_goods_info,array('order_amount'=>$order['order_amount'],'order_id'=>$order['id'],'order_no'=>$order['order_no'],'qr_flag'=>$order['qr_flag']));
                // }
            }
            return $order['id'];
        } else {
            return false;
        }
    }

    /**
     * 发货
     * @param type $orderNo
     * @return boolean
     */
    public static function updateInvoice($orderNo) {
        $model = new Model("order");
        $order_info = $model->where("order_no='" . $orderNo . "'")->find();

        //同步发货信息
        $order_info = $model->table("order")->where("id=$order_id")->find();
        if ($order_info) {
            
        }
    }

    /*
     * 充值
     */
    public static function recharge($recharge_no, $payment_id = 0) {
        $model = new Model("recharge");
        $recharge = $model->where("recharge_no='" . $recharge_no . "'")->find();
        if (empty($recharge)) {
            return false;
        }
        if ($recharge['status'] == 1) {
            return $recharge['id'];
        } else {
            //更新充值订单信息
            $model->data(array('status' => 1))->where("recharge_no='" . $recharge_no . "'")->update();
            $account = $recharge['account'];
            $user_id = $recharge['user_id'];
            //给用户充值
            $result = "";
            if($recharge['package']!=4){
                //增加余额
                $result = $model->table("customer")->where("user_id =".$user_id)->data(array("balance"=>"`balance`+".$account))->update();
                if($result){
                    Log::balance($account, $user_id, $recharge['recharge_no'], "用户充值", 1);
                }
            }
            
            if($recharge['package']==0){//普通充值
                $result = $model->table("customer")->data(array('point_coin' => "`point_coin`+" . $account))->where("user_id=" . $user_id)->update();
                if($result){
                    Log::pointcoin_log($account, $user_id, $recharge_no,"充值送积分", 1);
                }
                // $result = $model->table("customer")->data(array('financial_coin' => "`financial_coin`+" . $account))->where("user_id=" . $user_id)->update();
            }else{//套餐充值处理
                $config = Config::getInstance();
                $package_set = $config->get("recharge_package_set"); 
                switch ($recharge['package']){
                    case 1: $set = $package_set[1];break;
                    case 2: $set = $package_set[2];break;
                    case 3: $set = $package_set[3];break;
                    case 4: $set = $package_set[4];break;
                }
                //如果需要加积分的
                if(isset($set['point'])&& $set['point']>0){
                    $result = $model->table("customer")->data(array('point_coin' => "`point_coin`+" . $set['point']))->where("user_id=" . $user_id)->update();
                    if($result){
                        Log::pointcoin_log($set['point'], $user_id, $recharge_no,"套餐赠送积分", 1);
                    }
                }
                //如果需要加理财金币
                // if(isset($set['financial_coin'])&& $set['financial_coin']>0){
                //     $result = $model->table("customer")->data(array('financial_coin' => "`financial_coin`+" . $set['financial_coin']))->where("user_id=" . $user_id)->update();
                // }
                //如果需要赠送礼品
                // if(isset($set['gift'])){
                //    $order_result = self::autoCreateOrderForRechargeGift($recharge['recharge_no'],"",1);//套餐赠送的商品
                //    if(!$order_result){
                //         file_put_contents('autoCreateOrderErr.txt', date("Y-m-d H:i:s")."==充值订单号=={$recharge['recharge_no']}==\n",FILE_APPEND);
                //    }
                // }
                if($recharge['package']==4){
                    $isPromoter = $model->table("district_promoter")->where("user_id=".$recharge['user_id'])->find();
                    if(!$isPromoter){
                        $inviter_info = $model->table("invite")->where("invite_user_id=".$recharge['user_id'])->find();

                        //自动升级为代理商
                        $promoter_data['user_id']=$recharge['user_id'];
                        $promoter_data['type']=5;
                        $promoter_data['create_time']=$promoter_data['join_time']=date("Y-m-d H:i:s");
                        $promoter_data['hirer_id']=$inviter_info?$inviter_info['district_id']:1;
                        $promoter_data['status']=1;
                        $promoter_data['base_rate']=$recharge['rate'];
                        $model->table("district_promoter")->data($promoter_data)->insert();
                        if($inviter_info){
                            $config = Config::getInstance()->get("district_set");

                            // $first_promoter_user_id = Common::getFirstPromoter($inviter_info['user_id']);
                            $first_promoter_user_id = Common::getFirstPromoter($recharge['user_id']);
                            if($first_promoter_user_id){
                                Log::incomeLog($config['up_income1'], 2, $first_promoter_user_id, $recharge['id'], 14,"下级会员升级为代理商奖励");
                                $result = $model->table("customer")->data(array("point_coin"=>"`point_coin`+".$config['up_point1']))->where("user_id=".$first_promoter_user_id)->update();
                                if($result){
                                    Log::pointcoin_log($config['up_point1'],$first_promoter_user_id,'','下级会员升级为代理商奖励',5);
                                }

                            }
                        }
                        $district_info = $model->table("district_shop")->where("id=".$promoter_data['hirer_id'])->find();
                        if($district_info){
                            Log::incomeLog($config['up_income2'], 3, $district_info['id'], $recharge['id'], 14,"专区会员升级为代理商奖励");
                            $result = $model->table("customer")->data(array("point_coin"=>"`point_coin`+".$config['up_point2']))->where("user_id=".$district_info['owner_id'])->update();
                            if($result){
                                Log::pointcoin_log($config['up_point2'],$district_info['owner_id'],'','专区会员升级为代理商奖励',5);
                            }
                        }
                    }
                    // else{ //曾经是代理商，后降级为普通会员，重新升级为代理商，但上级推广员和经销商不用给奖励，因为第一次成为代理商的时候已经给了
                    //     $promoter_data['type']=5;
                    //     $promoter_data['create_time']=$promoter_data['join_time']=date("Y-m-d H:i:s");
                    //     // $promoter_data['hirer_id']=$inviter_info?$inviter_info['district_id']:1;
                    //     $promoter_data['status']=1;
                    //     $model->table("district_promoter")->data($promoter_data)->where("user_id=".$recharge['user_id'])->update();
                    // }
                }
                $result =true;
            }
            if ($result) {
                //填写收款单
                $receivingData = array(
                    'order_id' => $recharge['id'],
                    'user_id' => $user_id,
                    'amount' => $account,
                    'create_time' => date('Y-m-d H:i:s'),
                    'payment_time' => date('Y-m-d H:i:s'),
                    'doc_type' => 1,
                    'payment_id' => $payment_id,
                    'pay_status' => 1
                );
                //防止重复生成收款单
                $issetReceiving = $model->table("doc_receiving")->where("order_id=".$recharge['id']." and doc_type = 1")->find();
                if(empty($issetReceiving)){
                    $model->table("doc_receiving")->data($receivingData)->insert();
                }else{
                    $model->table("doc_receiving")->data($receivingData)->where("id =".$issetReceiving['id'])->update();
                }
//                self::updateCommission(2, $recharge['id'], $user_id);//分销系统佣金
//                self::rechargeActivity($recharge);
                return $recharge['id'];
            }
            return false;
        }
    }

    public function redbag($order_no,$payment_id){
      $model = new Model('redbag');
      $redbag = $model->where("order_no ='".$order_no."'")->find();
      if($redbag){
        $model->data(array('pay_status'=>1))->where('order_no='.$order_no)->update();
        return true;
      }else{
        return false;
      }
    }

    public function calculate_fare() {
        $weight = Filter::int(Req::args('weight'));
        $id = Filter::int(Req::args('id'));
        $fare = new Fare($weight);
        $fee = $fare->calculate($id);
        $this->code = 0;
        $this->content = array(
            'totalfee' => $fee
        );
    }
    /*
     * 根据订单，充值分配佣金
     */
    public static function updateCommission($type,$order_id,$user_id){
        $commission_set= Config::getInstance()->get("commission_set");
        if($commission_set['status']=='0'){//如果关闭了分佣
            return false;
        }
        $level = self::commissionLevelInfo($user_id);
   
        if(empty($level)){
            return false;
        }else{
            //去掉不满足条件的上级
            foreach($level as $k =>$v){
                if(!self::isPromoter($v)){
                    unset($level[$k]);
                }
            }
            if(empty($level)){
                return false;
            }
        }
        if($type==1){//订单分佣
                $order_goods = new model('order_goods');
                $goods = $order_goods->where('order_id ='.$order_id)->findAll();
                if(!empty($goods)){
                    $commission_set_model = new Model('commission_set');
                    $product  = new Model('products');
                    $commission_amount = 0;
                    foreach($goods as $k=>$v){
                        $goods_id = $v['goods_id'];
                        $product_id = $v['product_id'];
                        $price = $product->where('id ='.$product_id)->fields('sell_price,cost_price')->find();
                        $profit =  $price['sell_price'] - $price['cost_price'];//利润
                        $set = $commission_set_model->where('goods_id='.$goods_id)->find();
                        if(!empty($set) && $set['status']!='0'){
                                $setting = unserialize($set['setting']);
                                if($setting[$v['product_id']]['type']==1){ //按利润分配
                                    if($profit>0){
                                       $commission = ($profit * $setting[$v['product_id']]['type_value'] / 100)*$v['goods_nums'];//佣金 
                                    }else{
                                        return false;
                                    }
                                }else if($setting[$v['product_id']]['type']==2){//按销售价比例
                                    $commission = $price['sell_price'] * $setting[$v['product_id']]['type_value'] /100 *$v['goods_nums']; 
                                }else if($setting[$v['product_id']]['type']==3){//按固定分配
                                     $commission = $setting[$v['product_id']]['type_value']*$v['goods_nums'];
                                }
                                if($commission>0){
                                       $commission_amount += round($commission,2);
                                       $commission_set_record[$v['product_id']]=$setting[$v['product_id']];
                                }
                            }
                        }
                    }
        }else if($type ==2){//充值分佣
            $recharge = new Model('recharge');
            $recharge_info = $recharge->where("id = $order_id and status = 1 and user_id = $user_id")->find();
            if(!empty($recharge_info)){
                if($recharge_info['account']>$commission_set['recharge_min']){
                    $commission_amount = round($recharge_info['account']*$commission_set['commission_rate2recharge']/100,2);
                }else{
                    return false;
                }
            }else{
                return false;
            }
        }
          if($commission_amount>0){
            //计算各级佣金金额
            $all = $commission_set['level_3']+$commission_set['level_2']+$commission_set['level_1'];//总份额
            
            $level_three_commission = isset($level[3]) ? round($commission_amount * $commission_set['level_3'] / $all,2) : 0.00;
            $level_two_commission   = isset($level[2]) ? round($commission_amount * $commission_set['level_2'] / $all,2) : 0.00;
            $level_one_commission   = isset($level[1]) ? round($commission_amount * $commission_set['level_1'] / $all,2) : 0.00;
            $commission_amount =  round($level_three_commission+$level_two_commission+$level_one_commission,2);
            
            $commission_log = new Model('commission_log');
            $commission_account = new Model('commission');  
            //开始分配
            foreach ($level as $k=>$v){
                switch($k){
                    case 3: $commission_get = $level_three_commission;break;
                    case 2: $commission_get = $level_two_commission;break;
                    case 1: $commission_get = $level_one_commission;break;
                }
                //记录佣金详细
                $id = $commission_log->data(array('user_id'=>$v,'buyer_id'=>$user_id,'order_id'=>$order_id,'commission_level'=>$k,'commission_amount'=>$commission_amount,'commission_get'=>$commission_get,'type'=>$type,'time'=>date('Y-m-d H:i:s')))->insert();
                $account_record = $commission_account->where('user_id='.$v)->fields('id,commission_possess_now')->find();
                if(empty($account_record)){
                    //不再新增
                }else{
                    //给推客加佣金
                    $commission_account->data(array('commission_possess_now'=>$account_record['commission_possess_now']+$commission_get))->where('id='.$account_record['id'])->update();
                }
            }   
            //记录佣金设置
            $commission_record = new Model('commission_record');
            $commission_set_record = isset($commission_set_record) ? $commission_set_record : array();
            $result=$commission_record->data(array('order_id'=>$order_id,'commission_amount'=>$commission_amount,'commission_set_record'=>serialize($commission_set_record),'rate_record'=>  serialize($commission_set),'record_time'=>date('Y-m-d H:i:s')))->insert();
      }
    }
    /*
     * 查询分佣级别信息
     */
    public static function commissionLevelInfo($user_id){
        $invite = new Model('invite');
        $level_three = $invite->where('invite_user_id='.$user_id)->find();
        $level = array();
        if(!empty($level_three)){
            $level[3] = $level_three['user_id'];
            $level_two = $invite->where('invite_user_id='.$level[3])->find();
            if(!empty($level_two)){
                 $level[2]=$level_two['user_id'];
                 $level_one = $invite->where('invite_user_id='.$level[2])->find();
                 if(!empty($level_one)){
                     $level[1]=$level_one['user_id'];
                  }
            }
        }
        
        return $level;
    }
   /*
    * 判断是否是在线的现金支付
    */
   public static function isOnlineCashPay($payment_id){
        $payment = new Model('payment');
        $pay_info = $payment->where("id = $payment_id and plugin_id not in (1,7,12,19,20)")->find();
        if(empty($pay_info)){
            return false;
        }else{
            return true;
        }
   }
   
   /*
    * 判断是否成为分销用户
    */
   public static function isPromoter($uid){
       $commission = new Model("commission");
       $record = $commission->where("user_id = $uid and status = 0")->fields("id")->find();
       if(empty($record)){
           return false;
       }else{
           return true;
       }
   }
   /*
    * 判断是否可以成为推客
    */
  public static function updatePromoter($uid){
      $commission_set = Config::getInstance()->get("commission_set");
      if($commission_set['status']==0){
          return false;
      }
       $commission = new Model('commission');
       //查询是否已经有推客记录
       $result = $commission->where("user_id = $uid")->find();
       if(empty($result)){
           //如果没有记录，则计算订单总额
           $order = new Model("order");
           $order_data = $order -> where("user_id = $uid and pay_status = 1")->fields("id,order_amount")->findAll();
           if(empty($order_data)){
               return false;
           }else{
               $order_amount = 0.00;
               foreach($order_data as $v){
                   $order_amount += $v['order_amount'];
               }
               //如果订单总额大于系统设定值，则将该用户加入推客
               if($order_amount >= $commission_set['commission_order_amount']){
                   $result1 = $commission->data(array('user_id'=>$uid,'commission_available'=>0.00,'commission_possess_now'=>0.00,'commission_withdrew'=>0.00,'create_time'=>date("Y-m-d H:i:s"),'status'=>0,'type'=>0))->insert();
                   if($result1){
                         $customer = new Model('customer');
                         $customer ->data(array('is_promoter'=>1))->where("user_id = $uid")->update();
                         return true;
                   } 
               }
           }
       }else{
           return false;
       }
  }
  
    /*
     * 更新退款订单信息
     */
    public static function refunded($refund_id,$status=1,$admin_note=""){
      $refund = new Model("refund");
      $refund_info = $refund->where("id =$refund_id")->fields("user_id,order_id,refund_progress,payment")->find();
      if($status==1){
            if($refund_info['refund_progress']!=-1){
                $order = new Model("order");
                //更新订单状态
                $result = $order->data(array("pay_status"=>"3","status"=>"5"))->where("id =".$refund_info['order_id']." and user_id=".$refund_info['user_id'])->update();
                if($result){
                    //更新退款申请状态
                    $result = $refund->data(array("refund_progress"=>"3",'finish_time'=>date("Y-m-d H:i:s")))->where("id = $refund_id")->update();
                }
                if($result){
                    self::afterRefund($refund_info['order_id']);
                    return true;
                }else{
                    return false;
                }
            }
            return false;
    }else if($status==-1){
            if($refund_info['refund_progress']==0){
                $order = new Model("order");
                $orderInfo = $order->where('id ='.$refund_info['order_id'])->fields('type')->find();
                $result = NULL;
                if($orderInfo['type']==4){//未完全支付的华币订单
                    $result = $order->data(array("pay_status"=>"0"))->where("id =".$refund_info['order_id']." and user_id=".$refund_info['user_id'])->update();
                }else{
                    //更新订单状态
                    $result = $order->data(array("pay_status"=>"1"))->where("id =".$refund_info['order_id']." and user_id=".$refund_info['user_id'])->update();
                }
                if($result){
                    //更新退款申请状态
                    $result = $refund->data(array("refund_progress"=>"-1",'admin_handle_time'=>date("Y-m-d H:i:s"),"admin_note"=>$admin_note))->where("id = $refund_id")->update();
                }
                if($result){
                    return true;
                }else{
                    return false;
                }
            }
            return false;
   }
 }
   /*
    * 退款之后的一些佣金问题                 
    */
    public static function afterRefund($order_id){
        //小区佣金回收
        $model = new Model();
        $district_sale = $model->table('district_sales')->where('order_id = '.$order_id.' and status=0')->fields('id')->findAll();
        if(!empty($district_sale)){
            $ids = implode(',', $district_sale);
            $model->query("update tiny_district_incomelog set status=-1 where status=0 and orgin in ({$ids}) and (type=1 or type=2)");
            $model->query("update tiny_district_sales set status = -1 where status = 0 and id in ({$ids})");
        }
        $commission  = $model ->table("commission_log")->where("order_id ={$order_id} and status =0")->find();
        if(!empty($commission)){
            $mode->table("commission_log")->where("order_id = {$order_id}")->data(array('status'=>3))->update();
        }
        
    }
    /*
     * 套餐充值自动创建已付款订单
     */
    public static function autoCreateOrderForRechargeGift($recharge_no,$gift="",$gift_num=""){
        $model = new Model();
        $gift_info = $model->table('recharge_gift')->where("recharge_no=$recharge_no")->find();
        if(empty($gift_info)){
            return false;
        }
        $address_id = $gift_info['address_id'];
        $user_id = $gift_info['user_id'];
        //地址信息
        $address_model = new Model('address');
        $address = $address_model->where("id=$address_id and user_id=$user_id")->find();
        
        $gift_product = $gift==""?$gift_info['gift']:$gift;
        $gift_num = $gift_num;
        $product = $model->table('products as p')->where("p.id = $gift_product")->join("left join goods as g on p.goods_id = g.id")->fields("p.*,g.shop_id")->find();
        
        $data['type']=0;
        $data['order_no'] = Common::createOrderNo();
        $data['user_id'] = $user_id;
        $data['payment'] = 1;
        $data['status'] = 3; 
        $data['pay_status'] = 1;
        $data['accept_name'] = Filter::text($address['accept_name']);
        $data['phone'] = $address['phone'];
        $data['mobile'] = $address['mobile'];
        $data['province'] = $address['province'];
        $data['city'] = $address['city'];
        $data['county'] = $address['county'];
        $data['addr'] = Filter::text($address['addr']);
        $data['zip'] = $address['zip'];
        $data['payable_amount'] = $product['sell_price']*$gift_num;
        $data['payable_freight'] = 0;
        $data['real_freight'] = 0;
        $data['create_time'] = date('Y-m-d H:i:s');
        $data['pay_time'] = date("Y-m-d H:i:s");
        $data['is_invoice'] = 0;
        $data['handling_fee'] = 0;
        $data['invoice_title'] = '';
        $data['taxes'] = 0;
        $data['discount_amount'] = 0;
        $data['order_amount'] = $product['sell_price']*$gift_num;
        $data['real_amount'] = $product['sell_price']*$gift_num;
        $data['point'] = 0;
        $data['voucher_id'] = 0;
        $data['voucher'] = serialize(array());
        $data['prom_id']=0;
        $data['admin_remark']="自动创建订单，来自于充值套餐{$gift_info['package']}";
        $data['shop_ids']=$product['shop_id'];
        $order_id =$model->table('order')->data($data)->insert();
        
        $tem_data['order_id'] = $order_id;
        $tem_data['goods_id'] = $product['goods_id'];
        $tem_data['product_id'] = $product['id'];
        $tem_data['shop_id'] = $product['shop_id'];
        $tem_data['goods_price'] = $product['sell_price'];
        $tem_data['real_price'] = $product['sell_price'];
        $tem_data['goods_nums'] = $gift_num;
        $tem_data['goods_weight'] = $product['weight'];
        $tem_data['prom_goods'] = serialize(array());
        $tem_data['spec'] = serialize($product['spec']);
        $model->table("order_goods")->data($tem_data)->insert();
        if($order_id){
            $model->table("products")->where("id=" . $gift)->data(array('store_nums' => "`store_nums`-" . $gift_num))->update();//更新库存
            $model->table('goods')->data(array('store_nums' => "`store_nums`-" . $gift_num))->where('id=' . $product['goods_id'])->update();
            $model->table('recharge_gift')->where("recharge_no=$recharge_no")->data(array('auto_order_id'=>$order_id,'status'=>1))->update();//更新状态
            return true;
        }else{
            return false;
        }
    }
}
