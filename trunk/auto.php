<?php
class LinuxCliTask{
    public $model;
    public function __construct($configPath,$argc,$argv) {
        if(strtolower(PHP_SAPI)!="cli"){
            exit("访问被拒绝");
        }else if($argc <3){
            exit("未设置参数");
        }else{
            if(is_string($configPath)){
                $config = require($configPath);
                if(!is_array($config)){
                    exit("加载配置失败");
                }else{
                    DBFactory::setDbInfo($config['db']);
                    if (isset($config['classes']))
                    Tiny::setClasses($config['classes']);
                    $this->model = new Model();
                }
            }
            $token = $argv[1];
            if($token !="token12345"){
                exit("非法访问");
            }
            $action = $argv[2];
            $this->$action();
        }
    }
    public function voucherNotice(){
             $yesterday   = date('Y-m-d 23:59:59',strtotime("-1 days"));//昨天过期的时间
             $threedays   = date('Y-m-d 23:59:59',strtotime("+3 days"));
             $tendays     = date('Y-m-d 23:59:59',strtotime("+10 days"));
             $fifteendays = date('Y-m-d 23:59:59',strtotime("+15 days"));
             
             $voucher_list1 = $this->model->table('voucher as v')->join("left join oauth_user as o on v.user_id = o.user_id")
                     ->where("v.end_time ='{$yesterday}' and v.status =0 and v.is_send=1 and v.user_id is not NULL and o.oauth_type='wechat'")
                     ->fields("v.account,v.value,o.user_id,o.open_id,o.open_name")
                     ->findAll();
             $voucher_list2 = $this->model->table('voucher as v')->join("left join oauth_user as o on v.user_id = o.user_id")
                     ->where("v.end_time ='{$threedays}' and v.status =0 and v.is_send=1 and v.user_id is not NULL and o.oauth_type='wechat'")
                     ->fields("v.account,v.value,o.user_id,o.open_id,o.open_name")
                     ->findAll();
             $voucher_list3 = $this->model->table('voucher as v')->join("left join oauth_user as o on v.user_id = o.user_id")
                     ->where("v.end_time ='{$tendays}' and v.status =0 and v.is_send=1 and v.user_id is not NULL and o.oauth_type='wechat'")
                     ->fields("v.account,v.value,o.user_id,o.open_id,o.open_name")
                     ->findAll();
             $voucher_list4 = $this->model->table('voucher as v')->join("left join oauth_user as o on v.user_id = o.user_id")
                     ->where("v.end_time ='{$fifteendays}' and v.status =0 and v.is_send=1 and v.user_id is not NULL and o.oauth_type='wechat'")
                     ->fields("v.account,v.value,o.user_id,o.open_id,o.open_name")
                     ->findAll();        
             if(empty($voucher_list1) && empty($voucher_list2) && empty($voucher_list3) && empty($voucher_list4)){
                 echo date("Y-m-d H:i:s").":"."none need to notify\r\n";die;
             }
             //获取token
              $wechatcfg = $this->model->table("oauth")->where("class_name='WechatOAuth'")->find();
              $wechat = new WechatMenu($wechatcfg['app_key'], $wechatcfg['app_secret'], '');
              $token = $wechat->getAccessToken();
              if($token==''){
                  echo date("Y-m-d H:i:s").":"."get access_token fail\r\n";die;
              }
             if(!empty($voucher_list1)){
                 foreach($voucher_list1 as $k => $v){
                     $v['open_name'] = $v['open_name']==""?"圆梦用户":$v['open_name'];
                     $params = array(
                         'touser'=>$v['open_id'],
                         'msgtype'=>'news',
                         'news'=>array(
                             'articles'=>array('0'=>array(
                                 'title'=>'圆梦温馨提示',
                                 'description'=>"亲爱的{$v['open_name']},您有一张价值{$v['value']}元的优惠券已经过期了。",
                                 'url'=>'www.buy-d.cn',
                                 'picurl'=>'http://img.buy-d.cn/data/uploads/2017/02/13/0da1a14455a4b1b63bd09eaa2209f809.png'
                             )
                            )
                         )
                     );
                    //print_r($params);
                    Http::curlPost("https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token={$token}", json_encode($params,JSON_UNESCAPED_UNICODE));
                 }
             }
             if(!empty($voucher_list2)){
                 foreach($voucher_list2 as $k => $v){
                     $v['open_name'] = $v['open_name']==""?"圆梦用户":$v['open_name'];
                     $params = array(
                         'touser'=>$v['open_id'],
                         'msgtype'=>'news',
                         'news'=>array(
                             'articles'=>array('0'=>array(
                                 'title'=>'圆梦温馨提示',
                                 'description'=>"亲爱的{$v['open_name']},您有一张价值{$v['value']}元的优惠券将于三日后过期，赶快去使用吧>>>",
                                 'url'=>'www.buy-d.cn',
                                 'picurl'=>'http://img.buy-d.cn/data/uploads/2017/02/13/0da1a14455a4b1b63bd09eaa2209f809.png'
                             )
                             )
                         )
                     );
                     //print_r($params);
                   Http::curlPost("https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token={$token}", json_encode($params,JSON_UNESCAPED_UNICODE));
                 }
             }
             if(!empty($voucher_list3)){
                 foreach($voucher_list3 as $k => $v){
                     $v['open_name'] = $v['open_name']==""?"圆梦用户":$v['open_name'];
                     $params = array(
                         'touser'=>$v['open_id'],
                         'msgtype'=>'news',
                         'news'=>array(
                             'articles'=>array('0'=>array(
                                 'title'=>'圆梦温馨提示',
                                 'description'=>"亲爱的{$v['open_name']},您有一张价值{$v['value']}元的优惠券将于十日后过期，赶快去使用吧>>>",
                                 'url'=>'www.buy-d.cn',
                                 'picurl'=>'http://img.buy-d.cn/data/uploads/2017/02/13/0da1a14455a4b1b63bd09eaa2209f809.png'
                             )
                             )
                         )
                     );
                    // print_r($params);
                   Http::curlPost("https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token={$token}", json_encode($params,JSON_UNESCAPED_UNICODE));
                 }
             }
             if(!empty($voucher_list4)){
                 foreach($voucher_list4 as $k => $v){
                     $v['open_name'] = $v['open_name']==""?"圆梦用户":$v['open_name'];
                     $params = array(
                         'touser'=>$v['open_id'],
                         'msgtype'=>'news',
                         'news'=>array(
                             'articles'=>array('0'=>array(
                                 'title'=>'圆梦温馨提示',
                                 'description'=>"亲爱的{$v['open_name']},您有一张价值{$v['value']}元的优惠券还有十五天就要过期啦，赶快去使用吧>>>",
                                 'url'=>'www.buy-d.cn',
                                 'picurl'=>'http://img.buy-d.cn/data/uploads/2017/02/13/0da1a14455a4b1b63bd09eaa2209f809.png'
                             )
                             )
                         )
                     );
                    //print_r($params);
                    Http::curlPost("https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token={$token}", json_encode($params,JSON_UNESCAPED_UNICODE));
                 }
             }
            echo date("Y-m-d H:i:s").":"."notify success\r\n";die;
     }

    public function autoUpdateFinancial(){
        // $customer=$this->model->table('customer')->fields('user_id,real_name,financial_coin,financial_stock')->where('financial_coin>0')->findAll();
        // if($customer){
        //     foreach($customer as $k => $v){
        //         if($v['financial_coin']>=5000){
        //             $stock=intval($v['financial_coin']/5000);
        //             $data=array(
        //                   'financial_coin'=>"`financial_coin`-5000*({$stock})",
        //                   'financial_stock'=>"`financial_stock`+({$stock})",
        //                 );
        //             $ret=$this->model->table('customer')->data($data)->where('user_id='.$v['user_id'])->update();//自动分配分红股
        //             if($ret){
        //                 echo date("Y-m-d H:i:s")."=={$v['real_name']}==自动分配{$stock}个分红股成功==\n";
        //             }            
        //             $current_date = date('Y-m-d',time());
        //             ignore_user_abort();//关掉浏览器，PHP脚本也可以继续执行.
        //             set_time_limit(0);    
        //             if(time()>strtotime("$current_date + 360 days")){ //360天后自动清除该分红股
        //                 $res=$this->model->table('customer')->data(array('financial_stock'=>"`financial_stock`-({$stock})"))->where('user_id='.$v['user_id'])->update();
        //                 if($res){
        //                     echo date("Y-m-d H:i:s")."success=={$v['real_name']}==360天后自动清除该分红股==\n";
        //                 }else{
        //                     echo date("Y-m-d H:i:s")."error";
        //                 }
        //             }
        //         }
        //     }
        // }
    }

    public function autoUnlocked(){
        $config = Config::getInstance()->get("district_set");
        $income=$this->model->table('promote_income_log')->where('frezze_income_change>0')->findAll();
        if($income){
           foreach ($income as $k => $v) {
           $t1=strtotime($v['date']);
           $t2=$t1+$config['income_lockdays']*24*60*60;
           if(time()>$t2){
            $ret=$this->model->table('promote_income_log')->data(array('valid_income_change'=>"`valid_income_change`+({$v['frezze_income_change']})",'frezze_income_change'=>0,'current_valid_income'=>"`current_valid_income`+({$v['frezze_income_change']})",'current_frezze_income'=>"`current_frezze_income`-({$v['frezze_income_change']})"))->where('id='.$v['id'])->update();
            if($ret){
               echo date("Y-m-d H:i:s")."自动解锁锁定收益\n";
            }else{
                echo date("Y-m-d H:i:s")."解锁失败";
            }
           }
         }
        }
    }

    #7天自动清理未支付订单
    public function autoDelOrder(){
       $order=$this->model->table('order')->where('pay_status=0 and status=2')->findAll();
       if($order){
          foreach($order as $k=>$v){
             if((time()-strtotime($v['create_time']))>7*24*60*60){
                $this->model->table('order')->where('pay_status=0 and status=2 and id='.$v['id'])->delete();
             }
          }
       }
       
       $offline_order=$this->model->table('order_offline')->where('pay_status=0 and status=2')->findAll();
       if($offline_order){
          foreach($offline_order as $k=>$v){
             if((time()-strtotime($v['create_time']))>7*24*60*60){
                $this->model->table('order_offline')->where('pay_status=0 and status=2 and id='.$v['id'])->delete();
             }
          }
       }
    }

    #自动清理24小时后没人抢的红包
    public function autoClearRedbag(){
        $redbag = $this->model->table('redbag')->fields('id,user_id,create_time,amount,order_no')->where("lat!='' and lng!='' and status!=2 and pay_status=1 and amount>0")->findAll();
        foreach ($redbag as $k => $v) {
            if(time()-strtotime($v['create_time'])>24*60*60){
                $this->model->table('redbag')->data(['status'=>2,'remark'=>'1天未领取完自动清除'])->where('id='.$v['id'])->update();
                $this->model->table('customer')->data(array('balance' => "`balance`+" . $v['amount']))->where('user_id=' . $v['user_id'])->update();
                Log::balance($v['amount'], $v['user_id'],$v['order_no'],"红包一天未领取余额退回", 15, 1);
            }
        }
    }

    #淘宝订单定时分佣
    public function autoMaidByAdzoneid() {
        $order = $this->model->table('taoke')->fields('id,create_time,goods_name,goods_id,goods_number,order_status,order_amount,goods_price,effect_prediction,estimated_revenue,order_sn,adv_id')->where("is_handle=0")->findAll();
        if($order) {
            foreach ($order as $k => $v) {
                $price = $v['order_status'] =='订单失效'?$v['goods_price']:$v['order_amount'];
                $user = $this->model->table('user')->fields('id')->where('adzoneid='.$v['adv_id'])->find();
                switch ($v['order_status']) {
                    case '订单失效':
                        $type = -1;
                        break;
                    case '订单结算':
                        $type = 0;
                        break;    
                    case '订单付款':
                        $type = 2;
                        break;
                    default:
                        $type = -1;
                        break;
                }
                if($user) {
                    //买家id
                    $user_id = $user['id'];
                    //邀请人id
                    // $inviter_id = Common::getInviterId($user_id);

                    $log = array(
                            'goods_name'   => $v['goods_name'],
                            'goods_id'     => $v['goods_id'],
                            'goods_num'    => $v['goods_number'],
                            'order_id'     => $v['id'],
                            'order_sn'     => $v['order_sn'], 
                            'price'        => $price,  
                            'order_time'   => $v['create_time'],
                            'create_time'  => date('Y-m-d H:i:s'),
                            'order_status' => $v['order_status'],
                            'month'        => date('Y-m',strtotime($v['create_time'])),
                            'type'         => $type,
                            'adzoneid'     => $v['adv_id'] 
                            );
                    
                    if($v['order_status']=='订单失效') {
                        $log['user_id'] = $user_id;
                        $log['amount'] = $v['effect_prediction'];
                        $this->model->table('benefit_log')->data($log)->insert();
                    } else {
                        //上级代理商
                        $promoter = $this->model->table('district_promoter')->where('user_id='.$user_id)->find();
                        if($promoter) {
                            $promoter_id = $user_id;
                        } else {
                            $promoter_id = Common::getFirstPromoter($user_id);
                        }
                        // $promoter_id = Common::getFirstPromoter($user_id);
                        //上级经销商
                        $district = $this->model->table('district_shop')->where('owner_id='.$user_id)->find();
                        if($district) {
                            $district_id = $user_id;
                        } else {
                            $district_id = Common::getFirstDistrictId($user_id);
                        }
                        // $district_id = Common::getFirstDistrictId($user_id);
                        
                        if($user_id == $promoter_id) {
                            if($district_id == $user_id) {
                                $log['user_id'] = $user_id;
                                $log['amount'] = $v['effect_prediction']*0.7;
                                $this->model->table('benefit_log')->data($log)->insert();
                            }else{
                                $log['user_id'] = $user_id;
                                $log['amount'] = $v['effect_prediction']*0.6;
                                $this->model->table('benefit_log')->data($log)->insert();

                                $log['user_id'] = $district_id;
                                $log['amount'] = $v['effect_prediction']*0.1;
                                $this->model->table('benefit_log')->data($log)->insert();
                            }
                        } else {
                            $log['user_id'] = $user_id;
                            $log['amount'] = $v['effect_prediction']*0.4;
                            $this->model->table('benefit_log')->data($log)->insert();

                            if($district_id == $promoter_id){
                                $log['user_id'] = $promoter_id;
                                $log['amount'] = $v['effect_prediction']*0.3;
                                $this->model->table('benefit_log')->data($log)->insert();
                            }else{
                                $log['user_id'] = $promoter_id;
                                $log['amount'] = $v['effect_prediction']*0.2;
                                $this->model->table('benefit_log')->data($log)->insert();

                                $log['user_id'] = $district_id;
                                $log['amount'] = $v['effect_prediction']*0.1;
                                $this->model->table('benefit_log')->data($log)->insert();
                            }
                            
                        }
                    }
                    
                }
            $this->model->table('taoke')->data(array('is_handle'=>1))->where('id='.$v['id'])->update();    
            }
        }
    }

    #定期结算上个月淘客订单
    public function autoSettleTaoke(){
        $BeginDate=date('Y-m-01', strtotime(date("Y-m-d"))); //当前月份第一天
        $log = $this->model->table('benefit_log')->fields('id,user_id,amount,order_time,create_time,order_sn')->where("type in (0,2) and order_time<'{$BeginDate}'")->findAll();
        if($log) {
            foreach ($log as $k => $v) {
                $amount = $v['amount'];
                $this->model->table('customer')->data(array('balance'=>"`balance`+{$amount}"))->where('user_id='.$v['user_id'])->update();
                Log::balance($amount,$v['user_id'],$v['order_sn'],'淘客订单佣金自动结算',21);
                // $this->model->table('user')->data(array('total_income'=>"`total_income`+{$amount}"))->where('id='.$v['user_id'])->update();
                $this->model->table('benefit_log')->data(array('type'=>1))->where('id='.$v['id'])->update();
            }
        }
    }

    #定时结算退回退款淘客订单
    public function autoBackTaokeOrder()
    {
        $log = $this->model->table('benefit_log')->fields('order_sn')->where("type=2 and order_status='订单付款'")->findAll();
        if($log) {
            foreach ($log as $k => $v) {
                 $order = $this->model->table("taoke")->where("order_sn ='" . $v['order_sn'] . "'")->find();
                 if($order['order_status']=='订单失效') {
                    $logs = $this->model->table('benefit_log')->fields('id,user_id,amount')->where("order_sn ='" . $v['order_sn'] . "'")->findAll();
                    if($logs) {
                        foreach ($logs as $key => $value) {
                            $this->model->table('benefit_log')->data(['order_status'=>'订单失效','type'=>-1])->where('id='.$value['id'])->update();
                            // $this->model->table('user')->data(array('total_income'=>"`total_income`-{$value['amount']}"))->where('id='.$value['user_id'])->update();
                            // $this->model->table('customer')->data(array('balance'=>"`balance`-{$value['amount']}"))->where('user_id='.$value['user_id'])->update();
                            // Log::balance($value['amount'],$value['user_id'],$v['order_sn'],'优惠购收益退回',22);
                            $data = array(
                                'order_sn'     => $v['order_sn'],
                                'amount'       => $value['amount'],
                                'user_id'      => $value['user_id'],
                                'order_status' => '订单失效',
                                'create_time'  => date('Y-m-d H:i:s')
                                );
                            $this->model->table('taoke_refund')->data($data)->insert();
                        }
                    }
                 }
            }
        }
    }

    #定时退回拼团失败余额
    public function autoBackGroupbuyMoney()
    {
        $NoticeService = new NoticeService();
        $order = $this->model->table('order')->where('pay_status=1 and type=1 and delivery_status=0 and status!=4')->findAll();
        if($order) {
            foreach($order as $k=>$v) {
                $now = time();
                $amount = $v['order_amount']; 
                if($v['join_id']!=0) {
                    $groupbuy_log = $this->model->table('groupbuy_log')->fields('id,join_id,user_id,join_time')->where('id='.$v['join_id'].' and pay_status=1')->findAll();
                    if($groupbuy_log) {
                        foreach ($groupbuy_log as $key => $value) {
                            $groupbuy_join = $this->model->table('groupbuy_join')->where('id='.$value['join_id'])->find();
                            if(strtotime('+1 day',strtotime($value['join_time']))<time() && $groupbuy_join['need_num']>0) {
                                $this->model->table('customer')->data(array('balance'=>"`balance`+{$amount}"))->where("user_id=".$value['user_id'])->update();
                                Log::balance($amount,$value['user_id'],$v['order_no'],'拼团失败订单自动退回到余额',18);
                                $this->model->table('groupbuy_join')->data(array('status'=>3))->where('id='.$value['join_id'])->update();
                                $this->model->table('groupbuy_log')->data(array('pay_status'=>3))->where('id='.$value['id'])->update();
                                $this->model->table('order')->data(['status'=>5,'pay_status'=>3])->where('id='.$v['id'])->update();
                                //推送
                                $client_type = Common::getPayClientByPaymentID($v['payment']);
                                if($client_type=='ios'||$client_type=='android'){
                                    //jpush
                                    $jpush = $NoticeService->getNotice('jpush');
                                    $audience['alias']=array($value['user_id']);
                                    $res = $value['groupbuy_id'].','.$value['join_id'];
                                    $jpush->setPushData('all', $audience, '抱歉，人数不足拼团失败了', 'order_pay_fail', $res);
                                    $jpush->push();
                                }
                            } 
                        }
                    }
                }     
            }
        }
    }

    #添加机器人自动参与团购
    public function autoJoinGroupBuy()
    {
        $name = 'ROBOT';
        $order = $this->model->table('order')->where("pay_status=1 and type=1 and delivery_status=0 and status!=4 and accept_name not like '%{$name}%'")->findAll();
        if($order) {
            foreach($order as $k=>$v) {
                $now = time();
                $amount = $v['order_amount']; 
                if($v['join_id']!=0) {
                    $groupbuy_log = $this->model->table('groupbuy_log')->fields('id,groupbuy_id,join_id,user_id,join_time')->where('id='.$v['join_id'].' and pay_status=1 and type=1 and status!=1')->findAll();
                    if($groupbuy_log) {
                        foreach ($groupbuy_log as $key => $value) {
                            $groupbuy_id = $value['groupbuy_id'];
                            $join_id = $value['join_id'];
                            $groupbuy_join = $this->model->table('groupbuy_join')->where('id='.$value['join_id'])->find();
                            if($groupbuy_join['need_num']>0 && time()>strtotime($value['join_time']) && time()<strtotime($groupbuy_join['end_time'])) {
                                for($i=0;$i++;$i<$groupbuy_join['need_num']) {
                                    $user_id = $groupbuy_join['need_num']+1;
                                    $data = array(
                                    'user_id'  => $groupbuy_join['user_id'].','.$user_id,
                                    'need_num' => $groupbuy_join['need_num']-1
                                    );
                                    $this->model->table('groupbuy_join')->data($data)->where('id='.$value['join_id'])->update();
                                    $log = array(
                                    'join_id'     => $join_id,
                                    'groupbuy_id' => $groupbuy_id,
                                    'user_id'     => $user_id,
                                    'join_time'   => date('Y-m-d H:i:s'),
                                    'type'        => 2
                                    );
                                    $log_id = $this->model->table('groupbuy_log')->data($log)->insert();
                                    $order_goods = $this->model->table('order_goods')->where('order_id='.$v['id'])->find();
                                    $product_id = $order_goods['product_id'];
                                    $result = $this->autoCreateOrder($user_id,$product_id,$groupbuy_id,$log_id);
                                }
                                $NoticeService = new NoticeService();
                                $client_type = Common::getPayClientByPaymentID($v['payment']);
                                if($client_type=='ios'||$client_type=='android'){
                                    //jpush
                                    $jpush = $NoticeService->getNotice('jpush');
                                    $audience['alias']=array($v['user_id']);
                                    $res = $groupbuy_id.','.$join_id;
                                    $jpush->setPushData('all', $audience, '恭喜您，拼团成功！', 'order_pay_success', $res);
                                    $jpush->push();
                                }
                            }
                            $this->model->table('groupbuy_log')->data(['status'=>1])->where('id='.$value['id'])->update(); 
                        }
                    }
                }     
            }
        }
        
    }

    public function autoCreateOrder($user_id,$product_id,$groupbuy_id,$log_id){
        $model = new Model();
        
        $product = $model->table('products as p')->where("p.id = $product_id")->join("left join goods as g on p.goods_id = g.id")->fields("p.*,g.shop_id")->find();
        
        $customer = $model->table('customer')->fields('real_name')->where('user_id='.$user_id)->find();

        $gift_num = 1;

        $groupbuy = $model->table("groupbuy")->where("id=$groupbuy_id")->find();
        unset($groupbuy['description']);

        $data['type']=1;
        $data['order_no'] = Common::createOrderNo();
        $data['user_id'] = $user_id;
        $data['payment'] = 1;
        $data['status'] = 3; 
        $data['pay_status'] = 1;
        $data['accept_name'] = $customer['real_name'];
        $data['phone'] = '';
        $data['mobile'] = '';
        $data['province'] = '';
        $data['city'] = '';
        $data['county'] = '';
        $data['addr'] = '';
        $data['zip'] = '';
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
        $data['prom'] = serialize($groupbuy);
        $data['admin_remark'] = "请勿发货，来自拼团机器人凑数订单";
        $data['shop_ids'] = $product['shop_id'];
        $data['join_id'] = $log_id;
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
            $model->table("products")->where("id=" . $product_id)->data(array('store_nums' => "`store_nums`-" . $gift_num))->update();//更新库存
            $model->table('goods')->data(array('store_nums' => "`store_nums`-" . $gift_num))->where('id=' . $product['goods_id'])->update();
            return true;
        }else{
            return false;
        }
    }
  
    private function doCurl($url,$post_data,$time_out =30){
        $post_data = is_array($post_data)?http_build_query($post_data):$post_data;
        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => $url,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => $time_out,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_POSTFIELDS => $post_data,
          CURLOPT_HTTPHEADER => array(
            "cache-control: no-cache",
            "content-type: application/x-www-form-urlencoded",
          ),
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            echo "cURL Error #:" . $err;
            exit();
         } else {
              return $response;
       }
    }
}
//应用目录，为了程序的更好应用与开发。
define("APP_ROOT", dirname(__file__) . DIRECTORY_SEPARATOR);
//引入框架文件
include("framework/tiny.php");
$configPath = "protected/config/config.php";
new LinuxCliTask($configPath,$argc,$argv); 