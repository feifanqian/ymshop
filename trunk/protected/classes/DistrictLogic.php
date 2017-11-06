<?php

/*
 * 小区逻辑类
 */

class DistrictLogic {

    public $config;
    public $model;
    public $token;
    public $jpush;
    public $client_type;
    private function __construct() {
        $this->config = Config::getInstance()->get("district_set");
        $this->model = new Model();
        
    }

    public static function getInstance() {
        $instance = new DistrictLogic();
        return $instance;
    }
    /**
     * 记录逻辑运行状况
     *
     * @access public
     * @param int $id 订单id
     * @param string $info 运行信息
     * @return array
     */
    public function recordLogicResult($id,$info){
        $this->model->table("district_order")->data(array("do_logic_result"=>"{$info}"))->where("id = $id")->update();
    }
    /**
     * 创建推广者账号
     *
     * @access public
     * @param array $params 构建参数
     * @return array
     */
    public function buildPrmoterAccount($params) {
        $user_id = $params['user_id'];
        $invitor_id = $params['invitor_id'];
        $invitor_role = $params['invitor_role'];

        if (!$user_id || !$invitor_id || !$invitor_role) {
            
            return array('status' => 'fail', "msg" => "参数错误");
        }
        $isset = $this->model->table("district_promoter")->where("user_id = $user_id")->find();
        if ($isset) {
            return array('status' => 'fail', "msg" => "该用户已经是代理商");
        }
        if ($invitor_role == 'shop') {
            $hirer_id = $invitor_id;
        } else if ($invitor_role == 'promoter') {
            $district_info = $this->model
                    ->table("district_promoter as dp")
                    ->join("left join district_shop as ds on dp.hirer_id = ds.id")
                    ->where("dp.id = $invitor_id")
                    ->fields("ds.*,dp.user_id as invitor_user_id")
                    ->find();
            $hirer_id = $district_info['id'];
        } else {
            return array('status' => 'fail', "msg" => "参数错误");
        }
        $data['user_id'] = $user_id;
        $data['type'] = $invitor_role == 'shop' ? 1 : 2; //1:小区商户自主邀请2:代理商推广
        $data['join_time'] = date("Y-m-d H:i:s");
        $data['hirer_id'] = $hirer_id;
        $data['create_time'] = date('Y-m-d H:i:s');
        $data['valid_income'] = $data['frezze_income'] = $data['settled_income'] = 0.00;
        $data['invitor_id'] = $invitor_id;
        $data['status'] = 0;
        $result = $this->model->table("district_promoter")->data($data)->insert();
        if ($result) {
            $this->sendMessage($user_id, "恭喜您，成为圆梦商城代理商，您获得了".$this->config['join_send_point']."商城积分！","http://www.ymlypt.com/ucenter/index","promoter_join_success");
            if($invitor_role == 'shop'){
                $shop_info = $this->model->table("district_shop")->where("id=$hirer_id")->find();
                $this->sendMessage($shop_info['owner_id'], "恭喜您，您邀请的代理商加入了您的团队，您获得了".$this->config['shop_invite_promoter_money']."余额奖励和".$this->config['promoter_invite_promoter_point']."商城积分奖励！","http://www.ymlypt.com/district/district","has_promoter_join");
            }else{
                $this->sendMessage($district_info['owner_id'], "恭喜您，有新的代理商加入您的团队，您获得了". $this->config['shop_invite_indirect_money']."余额奖励！","http://www.ymlypt.com/district/district","has_promoter_join");
                $this->sendMessage($district_info['invitor_user_id'],"恭喜您，您邀请的代理商加入了您的团队，您获得了".$this->config['promoter_invite_promoter_money']."余额奖励和".$this->config['promoter_invite_promoter_point']."商城积分奖励！","http://www.ymlypt.com/ucenter/promoter_invite","invite_promoter_success");
            }
            return array("status" => 'success');
        } else {
            return array('status' => 'fail', "msg" => "数据库错误");
        }
    }

    /**
     * 代理商支付入驻费用后
     *
     * @access public
     * @param string $order_no 订单号
     * @param float $money 支付金额
     * @param int $payment_id 支付方式id
     * @param array $callbackData 支付返回参数
     * @return array
     */
    public function promoterPayCallback($order_no, $money, $payment_id, $callbackData) {
        $order = $this->model->table("district_order")->where("order_no ='{$order_no}'")->find();
        if ($order) {
            if ($order['pay_status'] != 0) {
                return array('status' => 'fail', "msg" => "订单已处理");
            }
            if ($order['fee'] > $money) {
                $this->recordLogicResult($order['id'], "入驻订单金额与支付金额不一致");
                return array('status' => 'fail', "msg" => "订单金额错误");
            }
            $update['pay_status'] = 1;
            $update['pay_date'] = date("Y-m-d H:i:s");
            $update['payment_id'] = $payment_id;
            //步骤1：更新代理商入驻订单状态
            $step1 = $this->model->table("district_order")->data($update)->where("id = " . $order['id'])->update();
            $this->client_type = Common::getPayClientByPaymentID($payment_id);
            $result_str = array();
            $return = true;
            if ($step1) {
                $result_str[]="入驻订单状态更新成功";
                $result = $this->addSendPointCoin("user", $order['user_id'], $this->config['join_send_point'], $order_no,5);
                if($result){
                     $result_str[]="入驻积分赠送成功";
                }else{
                     $result_str[]="入驻积分赠送失败";
                     $return =false;
                }
                $params['user_id'] = $order['user_id'];
                $params['invitor_id'] = $order['invitor_id'];
                $params['invitor_role'] = $order['invitor_role'];
                //步骤2:创建代理商账号
                $step2 = $this->buildPrmoterAccount($params);
                if ($step2['status'] == "success") {
                    $result_str[]="创建代理商账号成功";
                } else {
                    $result_str[]="创建代理商账号失败";
                    $return =false;
                }
                //步骤3:自动创建礼品订单
                $step3 = $this->autoCreateOrderForPromoter($order);
                if ($step3) {
                    $result_str[]="代理商赠送订单创建成功";
                } else {
                    $result_str[]="代理商赠送订单创建失败";
                    $return =false;
                }
                //步骤4：分配邀请收益
                $step4 = $this->addInviteIncome($order);
                if ($step4['status'] == 'success') {
                    $result_str[]="分配邀请收益成功";
                } else {
                     $result_str[]= "分配邀请收益失败：".$step4['msg'];
                     $return =false;
                }
                $this->recordLogicResult($order['id'], implode("->", $result_str));
                return $return;
            } else {
                $this->recordLogicResult($order['id'], "入驻订单状态更新失败");
                return false;
            }
        } else {
            return array('status' => 'fail', "msg" => "订单不存在");
        }
    }

    /*
     * 邀请收益
     * @access public
     * @param  array $order 订单
     * @return array
     */
    function addInviteIncome($order) {
        $error_msg = array();
        if ($order['invitor_role'] == 'shop') {//经营商直推
            $amount = $this->config['shop_invite_promoter_money'];
            if ($amount <= 0) {
                return array("status" => 'fail', 'msg' => '配置错误');
            }
            $result = Log::incomeLog($amount, 3, $order['invitor_id'], $order['id'], 9);
            if (!$result) {
                $error_msg[]= 'shop邀请收益分配失败';
            }
            $result = $this->addSendPointCoin("shop", $order['invitor_id'], $this->config['promoter_invite_promoter_point'],$order['order_no'],6);
            if (!$result) {
                $error_msg[] = "shop邀请积分奖励失败";
            }
            if (empty($error_msg)) {
                return array("status" => 'success');
            } else {
                return array("status" => 'fail', 'msg' => implode("|", $error_msg));
            }
        } else if ($order['invitor_role'] == 'promoter') {//代理商推荐代理商
            
            $amount = $this->config['promoter_invite_promoter_money'];
            if ($amount <= 0) {
                return array("status" => 'fail', 'msg' => '配置错误');
            }
            $promoter_info = $this->model->table("district_promoter")->where("id=".$order['invitor_id'])->find();
            if(!$promoter_info){
                $error_msg[] = "代理商邀请者信息未找到";
            }else{
                $result = Log::incomeLog($amount, 2, $promoter_info['user_id'], $order['id'], 8);
                if (!$result) {
                    $error_msg[] = "promoter邀请收益分配失败";
                }
                $result = $this->addSendPointCoin("promoter", $order['invitor_id'], $this->config['promoter_invite_promoter_point'],$order['order_no'],6);
                if (!$result) {
                    $error_msg[] = "promoter积分奖励失败";
                }
            }
            $amount = $this->config['shop_invite_indirect_money'];
            $result = Log::incomeLog($amount, 3, $promoter_info['hirer_id'], $order['id'], 9,"专区间接邀请代理商收益");
            if (!$result) {
                $error_msg[] = '雇主的间接收益分配记录失败';
            }
            if (empty($error_msg)) {
                return array("status" => 'success');
            } else {
                return array("status" => 'fail', 'msg' => implode("|", $error_msg));
            }
        }
    }

    /**
     * 自动为入驻代理商创建订单
     * @access public
     * @param array $callbackData 支付返回参数
     * @return boolean
     */
    public function autoCreateOrderForPromoter($order, $gift_num = 1) {

        $address_id = $order['address_id'];
        $user_id = $order['user_id'];
        //地址信息
        $address_model = new Model('address');
        $address = $address_model->where("id=$address_id and user_id=$user_id")->find();

        $gift_product = $order['gift'];
        $product = $this->model->table('products as p')->where("p.id = $gift_product")->join("left join goods as g on p.goods_id = g.id")->fields("p.*,g.shop_id")->find();

        $data['type'] = 0;
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
        $data['payable_amount'] = $product['sell_price'] * $gift_num;
        $data['payable_freight'] = 0;
        $data['real_freight'] = 0;
        $data['create_time'] = date('Y-m-d H:i:s');
        $data['pay_time'] = date("Y-m-d H:i:s");
        $data['is_invoice'] = 0;
        $data['handling_fee'] = 0;
        $data['invoice_title'] = '';
        $data['taxes'] = 0;
        $data['discount_amount'] = 0;
        $data['order_amount'] = $product['sell_price'] * $gift_num;
        $data['real_amount'] = $product['sell_price'] * $gift_num;
        $data['point'] = 0;
        $data['voucher_id'] = 0;
        $data['voucher'] = serialize(array());
        $data['prom_id'] = 0;
        $data['admin_remark'] = "自动创建订单，来自于代理商入驻";
        $data['shop_ids']=$product['shop_id'];
        $order_id = $this->model->table('order')->data($data)->insert();

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
        $this->model->table("order_goods")->data($tem_data)->insert();
        if ($order_id) {
            $this->model->table("products")->where("id=" . $order['gift'])->data(array('store_nums' => "`store_nums`-" . $gift_num))->update(); //更新库存
            $this->model->table('goods')->data(array('store_nums' => "`store_nums`-" . $gift_num))->where('id=' . $product['goods_id'])->update();
            $this->model->table('district_order')->where("id=" . $order['id'])->data(array('auto_order_id' => $order_id))->update(); //更新状态
            return true;
        } else {
            return false;
        }
    }

    /**
     * 分配推广收益
     * @access public
     * @param array $order_goods_info 
     * @param array $order_info 
     * @return boolean
     */
    public function districtIncomeAssign($order_goods_info, $order_info) {
        $ids = $order_info['qr_flag'];
        if ($ids == NULL) {
            return FALSE;
        }

        $qrcode_info = $this->model->table("promote_qrcode")->where("id in ({$ids}) and status = 1")->findAll();
        if (empty($qrcode_info)) {
            return FALSE;
        }
        
        $promoter_info = array();
        foreach ($qrcode_info as $v) {
            $promoter_info[$v['goods_id']] = $v;
        }
        if (empty($promoter_info)) {
            return false;
        }
        //获取分配比例
        $config_all = Config::getInstance();
        $set = $config_all->get('district_set');
        $normal_config = array('beneficiary_one'=>5,'beneficiary_two'=>10,'beneficiary_three'=>3,'beneficiary_four'=>1);
        $config = array_merge($normal_config, $set);
        //分析订单中是否有与推广信息匹配的
        foreach ($order_goods_info as $k => $v) {
            if (isset($promoter_info[$v['goods_id']])) {
                $sale_data = array(); //销售记录
                $income_log = array();
                //1:判断推荐人类型 为普通类型还是付费代理商，若为普通代理商（普通会员）则要查询是否有邀请关系，根据邀请关系分配
                $sale_data['goods_id']   = $v['goods_id'];
                $sale_data['goods_nums'] = $v['goods_nums'];
                $sale_data['product_id'] = $v['product_id'];
                $sale_data['unit_price'] = $v['unit_price'];
                $sale_data['amount']     = $v['unit_price'] * $v['goods_nums'];
                $sale_data['record_date']= date("Y-m-d H:i:s");
                $sale_data['contributor_user_id']=$promoter_info[$v['goods_id']]['user_id'];
                $my_info = Common::getMyPromoteInfo($promoter_info[$v['goods_id']]['user_id']);
                $sale_data['contributor_role']=$my_info['my_role'];
                switch ($my_info['my_role']){
                    case 1:
                        $sale_data['beneficiary_one_user_id']=$promoter_info[$v['goods_id']]['user_id'];
                        $sale_data['beneficiary_one_income'] =round($config['beneficiary_one']*$sale_data['amount']/100,2);
                        $income_log[] = array('amount'=>$sale_data['beneficiary_one_income'],'role_type'=>1,'role_id'=>$sale_data['beneficiary_one_user_id'],'type'=>1);
                        if(isset($my_info['inviter_role'])&&$my_info['inviter_role']!=1){
                            $sale_data['beneficiary_two_user_id']=$my_info['inviter_user_id'];
                            $sale_data['beneficiary_two_income'] =round(($config['beneficiary_two']-$config['beneficiary_one'])*$sale_data['amount']/100,2);
                            $income_log[] = array('amount'=>$sale_data['beneficiary_two_income'],'role_type'=>2,'role_id'=>$sale_data['beneficiary_two_user_id'],'type'=>2);
                        }
                        break;
                    case 2:
                        $sale_data['beneficiary_two_user_id']=$my_info[$v['goods_id']]['user_id'];
                        $sale_data['beneficiary_two_income'] =round($config['beneficiary_two']*$sale_data['amount']/100,2);
                        $income_log[] = array('amount'=>$sale_data['beneficiary_two_income'],'role_type'=>2,'role_id'=>$sale_data['beneficiary_two_user_id'],'type'=>4);
                        break;
                    case 3:
                        $sale_data['beneficiary_two_user_id']=$my_info[$v['goods_id']]['user_id'];
                        $sale_data['beneficiary_two_income'] =round($config['beneficiary_two']*$sale_data['amount']/100,2);
                        $income_log[] = array('amount'=>$sale_data['beneficiary_two_income'],'role_type'=>2,'role_id'=>$sale_data['beneficiary_two_user_id'],'type'=>4);
                        break;
                    default :
                        break;
                }
                
                $sale_data['beneficiary_three_id']=$my_info['district_id'];
                $sale_data['beneficiary_three_income'] =round($config['beneficiary_three']*$sale_data['amount']/100,2);
                $sale_data['order_no']=$order_info['order_no'];
                $type = $my_info['my_role']== 1 ? 3 : 5;
                $income_log[] = array('amount'=>$sale_data['beneficiary_three_income'],'role_type'=>3,'role_id'=>$sale_data['beneficiary_three_id'],'type'=>$type);
                if(isset($my_info['superior_district_id'])&&$my_info['superior_district_id']!=NULL){
                    $sale_data['beneficiary_four_id']=$my_info['superior_district_id'];
                    $sale_data['beneficiary_four_income'] =round($config['beneficiary_four']*$sale_data['amount']/100,2);
                    $income_log[] = array('amount'=>$sale_data['beneficiary_two_user_id'],'role_type'=>3,'role_id'=>$sale_data['beneficiary_two_income'],'type'=>6);
                }
                $last_id = $this->model->table("promote_sale_log")->data($sale_data)->insert();
                if($last_id){
                    $this->model->table("promote_qrcode")->data(array('sell_count'=>"`sell_count`+".$v['goods_nums']))->where("id=".$promoter_info[$v['goods_id']]['id'])->update();
                    foreach($income_log as $v){
                        Log::incomeLog($v['amount'], $v['role_type'],$v['role_id'], $last_id, $v['type']);
                    }
                }else{
                    file_put_contents('saleLogError.txt', "销售数据插入失败：|".json_encode($sale_data)."\n",FILE_APPEND);
                }
            }
        }
        return true;
    }

    /**
     * 加积分
     * @access public
     * @param string $role 角色  user promoter shop
     * @param int $role_id 角色id
     * @param float $amount 数值
     * @param string $order_no 订单号
     * @return boolean
     */
    public function addSendPointCoin($role, $role_id, $amount, $order_no,$type) {
        if ($amount == 0) {
            return true;
        }
        $type_info = array("5"=>"代理商入驻赠送","6"=>"代理商推荐奖励","7"=>"专区销售奖励");
        if ($role == 'user') {
            //加积分
            $result = $this->model->table("customer")->where("user_id=" . $role_id)->data(array("point_coin" => "`point_coin`+$amount"))->update();
            // $this->model->table("customer")->data(array('financial_coin' => "`financial_coin`+" .$amount ))->where("user_id=" .$role_id)->update();

            if ($result) {
                Log::pointcoin_log($amount, $role_id, $order_no, $type_info["$type"], $type);
                return true;
            } else {
                return false;
            }
        } else if ($role == "promoter") {
            $promoter_info = $this->model->table("district_promoter")->where("id =" . $role_id)->find();
            if ($promoter_info) {
                //加积分
                $result = $this->model->table("customer")->where("user_id=" . $promoter_info['user_id'])->data(array("point_coin" => "`point_coin`+$amount"))->update();
                // $this->model->table("customer")->data(array('financial_coin' => "`financial_coin`+" .$amount ))->where("user_id=" .$promoter_info['user_id'])->update();
                if ($result) {
                    Log::pointcoin_log($amount, $promoter_info['user_id'], $order_no, $type_info["$type"], $type);
                    return true;
                } else {
                    return false;
                }
            }
        }else if($role=='shop'){
            $shop_info = $this->model->table("district_shop")->where("id =" . $role_id)->find();
            if ($shop_info) {
                //加积分
                $result = $this->model->table("customer")->where("user_id=" . $shop_info['owner_id'])->data(array("point_coin" => "`point_coin`+$amount"))->update();
                // $this->model->table("customer")->data(array('financial_coin' => "`financial_coin`+" .$amount ))->where("user_id=" .$shop_info['owner_id'])->update();
                if ($result) {
                    Log::pointcoin_log($amount, $shop_info['owner_id'], $order_no, $type_info["$type"], $type);
                    return true;
                } else {
                    return false;
                }
            }
        }
        return false;
    }
    
    /*
     * 发送微信通知
     * @params 
     */
    public function sendMessage($user_id,$content,$url,$type){
        $need_weixin = true;
        $need_jpush = true;
        if($type=="promoter_join_success"){
            if(!$this->client_type||$this->client_type=='unknow'){
                return false;
            }
            if($this->client_type=='ios'||$this->client_type=='android'){
                $need_weixin = false;
            }else if($this->client_type=='weixin'){
                $need_jpush = false;
            }else{
                return false;
            }
        }
        if($need_weixin){
            if($this->token==NULL){
                $wechatcfg = $this->model->table("oauth")->where("class_name='WechatOAuth'")->find();
                $wechat = new WechatMenu($wechatcfg['app_key'], $wechatcfg['app_secret'], '');
                $this->token = $wechat->getAccessToken();
            }
            $oauth_info = $this->model->table("oauth_user")->fields("open_id,open_name")->where("user_id=".$user_id." and oauth_type='wechat'")->find();
            if(!empty($oauth_info)){
                $oauth_info['open_name'] = $oauth_info['open_name']==""?"圆梦用户":$oauth_info['open_name'];
                $params = array(
                    'touser'=>$oauth_info['open_id'],
                    'msgtype'=>'text',
                    "text"=>array(
                        'content'=>"<a href=\"$url\">亲爱的{$oauth_info['open_name']},$content</a>"
                    )
                );
                Http::curlPost("https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=".$this->token, json_encode($params,JSON_UNESCAPED_UNICODE));
            }
        }
        if($need_jpush){
            if(!$this->jpush){
                $NoticeService = new NoticeService();
                $this->jpush = $NoticeService->getNotice('jpush');
            }
            $audience['alias']=array($user_id);
            $this->jpush->setPushData('all', $audience, $content, $type, "");
            $this->jpush->push();
        }
    }
}
