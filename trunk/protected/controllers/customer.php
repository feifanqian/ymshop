<?php

class CustomerController extends Controller {

    public $layout = 'admin';
    private $top = null;
    public $needRightActions = array('*' => true);

    public function init() {
        $menu = new Menu();
        $this->assign('mainMenu', $menu->getMenu());
        $menu_index = $menu->current_menu();
        $this->assign('menu_index', $menu_index);
        $this->assign('subMenu', $menu->getSubMenu($menu_index['menu']));
        $this->assign('menu', $menu);
        $nav_act = Req::get('act') == null ? $this->defaultAction : Req::get('act');
        $nav_act = preg_replace("/(_edit)$/", "_list", $nav_act);
        $this->assign('nav_link', '/' . Req::get('con') . '/' . $nav_act);
        $this->assign('node_index', $menu->currentNode());
        $this->safebox = Safebox::getInstance();
        $this->assign('manager', $this->safebox->get('manager'));
        $this->assign('upyuncfg', Config::getInstance()->get("upyun"));

        $currentNode = $menu->currentNode();
        if (isset($currentNode['name']))
            $this->assign('admin_title', $currentNode['name']);
    }

    public function noRight() {
        $this->redirect("admin/noright");
    }

    //充值与退款
    public function balance_op() {
        $user_id = Filter::int(Req::args('user_id'));
        $type = Filter::int(Req::args('type'));
        $amount = Filter::float(Req::args('amount'));
        $recharge_type = Filter::int(Req::args('recharge_type'));
        //事件类型: 0:订单支付 1:用户充值 2:管理员充值 3:提现 4:退款到余额  ||||余额的
        //事件类型: 0:购物下单 1:余额转换  2：用户充值 3:管理员充值  4:管理员退款  5:过期扣减 ||||银点的
        $model = new Model("customer");
        $obj = $model->where("user_id=$user_id")->find();
        $info = array('status' => 'fail');
        $range = 1000000000 - $obj['balance'];
        if ($obj && $amount > 0 && $amount <= $range) {
            if($recharge_type==0){
                if ($type == 2) {
                    $model->data(array('balance' => "`balance`+" . $amount))->where("user_id=$user_id")->update();
                    Log::balance($amount, $user_id, '' ,'管理员充值', 2, $this->manager['id']);
                    $info = array('status' => 'success', 'msg' => '余额充值成功。');
                } else if ($type == 4) {
                    $model->data(array('balance' => "`balance`+" . $amount))->where("user_id=$user_id")->update();
                    Log::balance($amount, $user_id,'' ,'管理员退款', 4, $this->manager['id']);
                    $info = array('status' => 'success', 'msg' => '余额退款成功。');
                }
            }else if($recharge_type==2){
                if ($type == 2) {
                    $model->data(array('point_coin' => "`point_coin`+" . $amount))->where("user_id=$user_id")->update();
                    Log::pointcoin_log($amount, $user_id, '' ,'管理员充值', 3, $this->manager['id']);
                    $info = array('status' => 'success', 'msg' => '积分币充值成功。');
                } else if ($type == 4) {
                    $model->data(array('point_coin' => "`point_coin`+" . $amount))->where("user_id=$user_id")->update();
                    Log::pointcoin_log($amount, $user_id,'' ,'管理员退款', 4, $this->manager['id']);
                    $info = array('status' => 'success', 'msg' => '积分币退款成功。');
                }
            }
        } else {
            $info = array('status' => 'fail', 'msg' => '此用户可充值的金额范围0.01-' . sprintf("%01.2f", $range));
        }
        echo JSON::encode($info);
    }

    public function balance_list() {
        $condition = Req::args("condition");
        $condition_str = Common::str2where($condition);
        if ($condition_str)
            $this->assign("where", $condition_str);
        else
            $this->assign("where", "1=1");
        $this->assign("condition", $condition);
        $this->redirect();
    }

    public function benefit_list() {
        $condition = Req::args("condition");
        $condition_str = Common::str2where($condition);
        if ($condition_str)
            $this->assign("where", $condition_str);
        else
            $this->assign("where", "1=1");
        $this->assign("condition", $condition);
        $this->assign("type", array('-1' => '<span class="red">未支付</span>', '0' => '结算中', '1' => '已结算', '2' => '未结算'));
        $this->redirect();
    }

    public function pointcoin_list() {
        $condition = Req::args("condition");
        $condition_str = Common::str2where($condition);
        if ($condition_str)
            $this->assign("where", $condition_str);
        else
            $this->assign("where", "1=1");
        $this->assign("condition", $condition);
        $this->redirect();
    }
    public function withdraw_list() {
        $condition = Req::args("condition");
        $condition_str = Common::str2where($condition);
        if ($condition_str)
            $this->assign("where", $condition_str);
        else
            $this->assign("where", "type!=2");
        $this->assign("condition", $condition);
        $this->redirect();
    }

    public function withdraw_view() {
        $this->layout = "blank";
        $id = Filter::int(Req::args('id'));
        if ($id) {
            $model = new Model('balance_withdraw as wd');
            $withdraw = $model->fields("wd.*,us.name as uname,cu.balance,cu.offline_balance")->join("left join user as us on wd.user_id = us.id left join customer as cu on wd.user_id = cu.user_id")->where("wd.id=$id")->find();
            $withdraw['balance']=$withdraw['balance']+$withdraw['offline_balance'];
            $this->assign("withdraw", $withdraw);
            $this->redirect();
        }
    }

    public function re_withdraw_view() {
        $this->layout = "blank";
        $id = Filter::int(Req::args('id'));
        if ($id) {
            $model = new Model('balance_withdraw as wd');
            $withdraw = $model->fields("wd.*,us.name as uname,cu.balance,cu.offline_balance")->join("left join user as us on wd.user_id = us.id left join customer as cu on wd.user_id = cu.user_id")->where("wd.id=$id")->find();
            $withdraw['balance']=$withdraw['balance']+$withdraw['offline_balance'];
            $this->assign("withdraw", $withdraw);
            $this->redirect();
        }
    }

    public function withdraw_del() {
        $id = Req::args("id");
        if (is_array($id)) {
            $cond = ' in (' . implode(",", $id) . ')';
        } else {
            $cond = " = $id";
        }
        $model = new Model();
        $withdraw = $model->table("balance_withdraw")->where("id $cond")->findAll();
        $model->table("balance_withdraw")->where("id $cond")->delete();
        if ($withdraw) {
            $withdraw_nos = "";
            foreach ($withdraw as $item) {
                $withdraw_nos .= $item['withdraw_no'] . "、";
            }
            $withdraw_nos = trim($withdraw_nos, '、');
            Log::op($this->manager['id'], "删除提现请求", "管理员[" . $this->manager['name'] . "]:删除了提现请求 " . $withdraw_nos);
        }
        $this->redirect("withdraw_list");
    }
    public function withdraw_query(){
        if($this->is_ajax_request()){
            $id = Filter::int(Req::args('id'));
            $model = new Model('balance_withdraw as wd');
            $obj = $model->where("wd.id=$id")->find();
            if($obj){
                    // $ChinapayDf = new ChinapayDf();
                    $ChinapayDf = new AllinpayDf();
                    $params['merSeqId']=$obj['mer_seq_id'];
                    $params['merDate']= substr( $params['merSeqId'],0,8);
                    $merchantId=AppConfig::MERCHANT_ID;
                    $req_sn = $merchantId.$obj['withdraw_no'];

                    $third_pay = 0;
                    $payment_model = new Model();
                    $third_payment = $payment_model->table('third_payment')->where('id=1')->find();
                    if($third_payment){
                        $third_pay = $third_payment['third_payment'];
                    }
                    if($third_pay==0 && in_array($obj['user_id'], [42608,141580,141585])) {
                        $result = $ChinapayDf->DfYinshengQuery($obj['withdraw_no']); //使用银盛代付查询接口
                    } else {
                       $result = $ChinapayDf->DfQuery($req_sn); //使用通联代付查询接口 
                    }
                    if($result['code']==1){
                        if($obj['status']==4 || $obj['status']==0 || $obj['status']==2){
                            if($obj['type']==0){
                                $config = Config::getInstance();
                                $other = $config->get("other");
                                $real_amount = round($obj['amount']*(100-$other['withdraw_fee_rate'])/100,2);
                            }else{
                                $real_amount = $obj['amount'];
                            }
                            $model->data(array('status'=>1,'real_amount'=>$real_amount))->where("wd.id=$id")->update();
                        }
                        exit(json_encode(array('status'=>'success','msg'=>$result['msg'])));
                    }else{
                        exit(json_encode(array('status'=>'fail','msg'=>$result['msg'])));
                    }
                
            }else{
                exit(json_encode(array('status'=>'fail','msg'=>'信息错误')));
            }
        }
    }

    public function withdraw_querys($id){
        $model = new Model('balance_withdraw as wd');
        $obj = $model->where("wd.id=$id")->find();
        if($obj){
                // $ChinapayDf = new ChinapayDf();
                $ChinapayDf = new AllinpayDf();
                $params['merSeqId']=$obj['mer_seq_id'];
                $params['merDate']= substr( $params['merSeqId'],0,8);
                $merchantId=AppConfig::MERCHANT_ID;
                $req_sn = $merchantId.$obj['withdraw_no'];

                $third_pay = 0;
                $payment_model = new Model();
                $third_payment = $payment_model->table('third_payment')->where('id=1')->find();
                if($third_payment){
                    $third_pay = $third_payment['third_payment'];
                }
                if($third_pay==0 && in_array($obj['user_id'], [42608,141580,141585])) {
                    $result = $ChinapayDf->DfYinshengQuery($obj['withdraw_no']); //使用银盛代付查询接口
                } else {
                    $result = $ChinapayDf->DfQuery($req_sn); //使用通联代付查询接口 
                }
                if($result['code']==1){
                    if($obj['status']==4 || $obj['status']==0 || $obj['status']==2){
                        if($obj['type']==0){
                            $config = Config::getInstance();
                            $other = $config->get("other");
                            $real_amount = round($obj['amount']*(100-$other['withdraw_fee_rate'])/100,2);
                        }else{
                            $real_amount = $obj['amount'];
                        }
                        $model->data(array('status'=>1,'real_amount'=>$real_amount))->where("wd.id=$id")->update();
                    }
                    $return = array('status'=>'success','msg'=>$result['msg']);
                }else{
                    $return = array('status'=>'fail','msg'=>$result['msg']);
                }  
        }else{
            $return = array('status'=>'fail','msg'=>'信息错误1');
        }
        return $return;
    }

    public function re_withdraw_query($id){
            $model = new Model('balance_withdraw as wd');
            $obj = $model->where("wd.id=$id")->find();
            if($obj){
                
                    // $ChinapayDf = new ChinapayDf();
                    $ChinapayDf = new AllinpayDf();
                    $params['merSeqId']=$obj['mer_seq_id'];
                    $params['merDate']= substr( $params['merSeqId'],0,8);
                    $merchantId=AppConfig::MERCHANT_ID;
                    $req_sn = $merchantId.$obj['withdraw_no'];

                    $third_pay = 0;
                    $payment_model = new Model();
                    $third_payment = $payment_model->table('third_payment')->where('id=1')->find();
                    if($third_payment){
                        $third_pay = $third_payment['third_payment'];
                    }
                    if($third_pay==0 && in_array($obj['user_id'], [42608,141580,141585])) {
                        $result = $ChinapayDf->DfYinshengQuery($obj['withdraw_no']); //使用银盛代付查询接口
                    } else {
                       $result = $ChinapayDf->DfQuery($req_sn); //使用通联代付查询接口 
                    }

                    if($result['code']==1){
                        if($obj['status']==4 || $obj['status']==0 || $obj['status']==2){
                            if($obj['type']==0){
                                $config = Config::getInstance();
                                $other = $config->get("other");
                                $real_amount = round($obj['amount']*(100-$other['withdraw_fee_rate'])/100,2);
                            }else{
                                $real_amount = $obj['amount'];
                            }
                            $model->data(array('status'=>1,'real_amount'=>$real_amount))->where("wd.id=$id")->update();
                        }
                        return true;
                    }else{
                        return false;
                    }
                
            }else{
                return false;
            }
    }

    public function withdraw_back(){
        $id = Filter::int(Req::args('id'));
        $model = new Model();
        $withdraw = $model->table('balance_withdraw')->where('id='.$id)->find();
        $res1 = $model->table('balance_withdraw')->data(array('status'=>3))->where('id='.$id)->update();
        if($withdraw['type']==0){
            $res2 = $model->table('customer')->data(array('balance' => "`balance`+" . $withdraw['amount']))->where('user_id=' . $withdraw['user_id'])->update();
            Log::balance($withdraw['amount'], $withdraw['user_id'],$withdraw['withdraw_no'],"余额提现失败退回", 12, $this->manager['id']);
        }elseif($withdraw['type']==1){
            $res2 = $model->table('customer')->data(array('offline_balance' => "`offline_balance`+" . $withdraw['amount']))->where('user_id=' . $withdraw['user_id'])->update();
            Log::balance($withdraw['amount'], $withdraw['user_id'],$withdraw['withdraw_no'],"商家余额提现失败退回", 13, $this->manager['id']);
        }
        exit(json_encode(array('status'=>'success','msg'=>'退回成功')));   
    }
    public function df_balance_query(){
        if($this->is_ajax_request()){
           $ChinapayDf = new ChinapayDf();
           $ChinapayDf->DfBalanceQuery();
        }
    }
    //处理提现
    public function withdraw_act() {
        $id = Filter::int(Req::args('id'));
        $status = Req::args('status');
        $note = Filter::text(Req::args('note'));
        $model = new Model('balance_withdraw as wd');
        $models = new Model();
        $obj = $model->fields("wd.*,cu.balance,cu.offline_balance")->join("left join customer as cu on wd.user_id = cu.user_id")->where("wd.id=$id")->find();
        if ($obj) {
            // $can_withdraw = Common::getCanWithdrawAmount4GoldCoin($obj['user_id']);
            // if($obj['type']==0){
            //     $can_withdraw=$obj['balance'];  
            // }elseif($obj['type']==1){
            //     $can_withdraw=$obj['offline_balance']; 
            // }
            
                if($status==1){
                    $config = Config::getInstance();
                    $other = $config->get("other");
                    
                    // $ChinapayDf = new ChinapayDf();
                    $ChinapayDf = new AllinpayDf();
                    $params["merDate"]=date("Ymd");
                    $params["merSeqId"]=date("YmdHis").rand(10,99);
                    $params["cardNo"]=$obj['card_no'];
                    $params["usrName"]=$obj['open_name'];
                    $params["openBank"]=$obj['open_bank'];
                    $params["prov"]=$obj['province'];
                    $params["city"]=$obj['city'];
                    $params['withdraw_no']=$obj['withdraw_no'];
                    if($obj['type']==0){
                      $params["transAmt"]=round($obj['amount']*(100-$other['withdraw_fee_rate']));//转化成分，并减去手续费
                    }else{
                      $params["transAmt"]=round($obj['amount']*100); //商家线下收益余额不扣手续费
                    }      
                    if($params["transAmt"]<=0){
                         exit(json_encode(array('status'=>'fail','msg'=>'代付金额小于或等于0')));
                    }
                    $params['purpose']="用户{$obj['user_id']}提现";
                    $third_pay = 0;
                    $payment_model = new Model();
                    $third_payment = $payment_model->table('third_payment')->where('id=1')->find();
                    if($third_payment){
                        $third_pay = $third_payment['third_payment'];
                    }
                    // $result = $ChinapayDf->DfPay($params);
                    if($third_pay==0 && in_array($obj['user_id'], [42608,141580,141585])) {
                        $result = $ChinapayDf->DfYinsheng($params); //使用银盛代付接口
                        if($result['status']!=1) {
                            $result = $ChinapayDf->DFAllinpay($params);
                        }
                    } else {
                       $result = $ChinapayDf->DFAllinpay($params); //使用通联代付接口
                       if($result['status']!=1) {
                           $result = $ChinapayDf->DfYinsheng($params);
                       } 
                    }
                    $query = $this->withdraw_querys($id);
                    $date = date("Y-m-d H:i:s");
                    $real_amount = round($params['transAmt']/100,2);
                    $data = array(
                        'status'=> 1,
                        'note'  => $note,
                        'real_amount' => $real_amount,
                        'fee_rate' => $other['withdraw_fee_rate'],
                        'mer_seq_id' => $params['merSeqId'],
                        'submit_date' => $date
                        );
                    if($result['status']==1 && $query['status']=='success') {
                        $update = $models->table('balance_withdraw')->data($data)->where('id='.$id)->update();
                        // if($update==false) {
                        //     var_dump($data);die;
                        // }
                        if($update){
                            Log::op($this->manager['id'], "通过提现申请", "管理员[" . $this->manager['name'] . "]:通过了提现申请 " . $obj['withdraw_no']);
                            exit(json_encode(array('status'=>'success','msg'=>'提现成功')));
                        }
                    } elseif ($result['status']==4){
                        $model->query("update tiny_balance_withdraw set status='4' where id = $id and status= 0");
                        exit(json_encode(array('status'=>'fail','msg'=>$result['msg'])));
                    } else { 
                        if($result['msg']=='处理成功') {
                            $update = $models->table('balance_withdraw')->data($data)->where('id='.$id)->update();
                            if($update){
                                Log::op($this->manager['id'], "通过提现申请", "管理员[" . $this->manager['name'] . "]:通过了提现申请 " . $obj['withdraw_no']);
                                exit(json_encode(array('status'=>'success','msg'=>'提现成功')));
                            }
                        } else {
                            $model->query("update tiny_balance_withdraw set status='2' where id = $id and status= 0");
                            exit(json_encode(array('status'=>'fail','msg'=>$result['msg'])));
                        }
                    }
                }else if($status=="-1"){
                    $result = $model->query("update tiny_balance_withdraw set status='-1',note='$note' where id = $id and status= 0");
                    if($obj['type']==0){
                        $model->table('customer')->data(array('balance' => "`balance`+" . $obj['amount']))->where('user_id=' . $obj['user_id'])->update();
                        Log::balance($obj['amount'], $obj['user_id'],$obj['withdraw_no'],"拒绝提现申请回退到余额", 12, $this->manager['id']);
                    }elseif($obj['type']==1){
                        $model->table('customer')->data(array('offline_balance' => "`offline_balance`+" . $obj['amount']))->where('user_id=' . $obj['user_id'])->update();
                        Log::balance($obj['amount'], $obj['user_id'],$obj['withdraw_no'],"拒绝提现申请回退到商家余额", 13, $this->manager['id']);
                    }
                    Log::op($this->manager['id'], "拒绝提现申请", "管理员[" . $this->manager['name'] . "]:拒绝了提现申请 " . $obj['withdraw_no']);
                    if($result){
                        exit(json_encode(array('status'=>'success','msg'=>'拒绝申请成功')));
                    }
                }
            
            //扣除账户里的余额
        }
        exit(json_encode(array('status'=>'fail','msg'=>'信息错误2')));
    }

    //再次处理提现
    public function re_withdraw_act() {
        $id = Filter::int(Req::args('id'));
        $status = Req::args('status');
        $note = Filter::text(Req::args('note'));
        $model = new Model('balance_withdraw as wd');
        $obj = $model->fields("wd.*,cu.balance,cu.offline_balance")->join("left join customer as cu on wd.user_id = cu.user_id")->where("wd.id=$id")->find();
        if ($obj) {
                if($status==1){
                    $return = $this->re_withdraw_query($id);
                    if($return==false) {
                        $config = Config::getInstance();
                        $other = $config->get("other");
                        
                        $ChinapayDf = new AllinpayDf();
                        $params["merDate"]=date("Ymd");
                        $params["merSeqId"]=date("YmdHis").rand(10,99);
                        $params["cardNo"]=$obj['card_no'];
                        $params["usrName"]=$obj['open_name'];
                        $params["openBank"]=$obj['open_bank'];
                        $params["prov"]=$obj['province'];
                        $params["city"]=$obj['city'];
                        $params['withdraw_no']=$obj['withdraw_no'];
                        if($obj['type']==0){
                          $params["transAmt"]=round($obj['amount']*(100-$other['withdraw_fee_rate']));//转化成分，并减去手续费
                        }else{
                          $params["transAmt"]=round($obj['amount']*100); //商家线下收益余额不扣手续费
                        }      
                        if($params["transAmt"]<=0){
                             exit(json_encode(array('status'=>'fail','msg'=>'代付金额小于或等于0')));
                        }
                        $params['purpose']="用户{$obj['user_id']}提现";
                        $third_pay = 0;
                        $payment_model = new Model();
                        $third_payment = $payment_model->table('third_payment')->where('id=1')->find();
                        if($third_payment){
                            $third_pay = $third_payment['third_payment'];
                        }
                        // $result = $ChinapayDf->DfPay($params);
                        if($third_pay==0 && in_array($obj['user_id'], [42608,141580,141585])) {
                            $result = $ChinapayDf->DfYinsheng($params); //使用银盛代付接口
                            if($result['status']!=1) {
                                $result = $ChinapayDf->DFAllinpay($params);
                            }
                        } else {
                           $result = $ChinapayDf->DFAllinpay($params); //使用通联代付接口
                           if($result['status']!=1) {
                               $result = $ChinapayDf->DfYinsheng($params);
                           } 
                        }
                        
                        if($result['status']==1){
                            $date = date("Y-m-d H:i:s");
                            $real_amount = round($params['transAmt']/100,2);
                            $update = $model->query("update tiny_balance_withdraw set status=1,note='{$note}',real_amount={$real_amount},fee_rate={$other['withdraw_fee_rate']},mer_seq_id='{$params['merSeqId']}',submit_date='{$date}' where id = $id and status= 0");
                            if($update){
                                Log::op($this->manager['id'], "通过提现申请", "管理员[" . $this->manager['name'] . "]:通过了提现申请 " . $obj['withdraw_no']);
                                exit(json_encode(array('status'=>'success','msg'=>'提现成功')));
                            }
                        }elseif($result['status']==4){
                            $model->query("update tiny_balance_withdraw set status='4' where id = $id and status= 0");
                            exit(json_encode(array('status'=>'fail','msg'=>$result['msg'])));
                        }else{
                            $model->query("update tiny_balance_withdraw set status='2' where id = $id and status= 0");
                            // $model->table('customer')->data(array('offline_balance' => "`offline_balance`+" . $obj['amount']))->where('user_id=' . $obj['user_id'])->update();
                            // Log::balance($obj['amount'], $obj['user_id'],$obj['withdraw_no'],"余额提现失败退回", 3, $this->manager['id']);
                            exit(json_encode(array('status'=>'fail','msg'=>$result['msg'])));
                        }
                    } else {
                        exit(json_encode(array('status'=>'success','msg'=>'提现成功')));
                    }
                    
                }else if($status=="-1"){
                    $result = $model->query("update tiny_balance_withdraw set status='-1',note='$note' where id = $id and status= 0");
                    if($obj['type']==0){
                        $model->table('customer')->data(array('balance' => "`balance`+" . $obj['amount']))->where('user_id=' . $obj['user_id'])->update();
                        Log::balance($obj['amount'], $obj['user_id'],$obj['withdraw_no'],"拒绝提现申请回退到余额", 12, $this->manager['id']);
                    }elseif($obj['type']==1){
                        $model->table('customer')->data(array('offline_balance' => "`offline_balance`+" . $obj['amount']))->where('user_id=' . $obj['user_id'])->update();
                        Log::balance($obj['amount'], $obj['user_id'],$obj['withdraw_no'],"拒绝提现申请回退到商家余额", 13, $this->manager['id']);
                    }
                    Log::op($this->manager['id'], "拒绝提现申请", "管理员[" . $this->manager['name'] . "]:拒绝了提现申请 " . $obj['withdraw_no']);
                    if($result){
                        exit(json_encode(array('status'=>'success','msg'=>'拒绝申请成功')));
                    }
                }
            
            //扣除账户里的余额
        }
        exit(json_encode(array('status'=>'fail','msg'=>'信息错误')));
    }


    public function export_excel() {
        $this->layout = '';
        $condition = Req::args("condition");
        $fields = Req::args("fields");
        $condition = Common::str2where($condition);
        $notify_model = new Model("notify as n");
        if ($condition) {
            $items = $notify_model->fields("n.*,go.name as goods_name,u.name as user_name")->join("left join user as u on n.user_id = u.id left join goods as go on n.goods_id = go.id")->where($condition)->findAll();
            if ($items) {
                header("Content-type:application/vnd.ms-excel");
                header("Content-Disposition:filename=csat.xls");
                $fields_array = array('email' => '邮件', 'mobile' => '电话', 'user_name' => '用户名', 'goods_name' => '商品名', 'register_time' => '登记时间', 'notify_status' => '是否通知');
                $str = "<table border=1><tr>";
                foreach ($fields as $value) {
                    $str .= "<th>" . iconv("UTF-8", "GB2312", $fields_array[$value]) . "</th>";
                }
                $str .= "</tr>";
                foreach ($items as $item) {
                    $str .= "<tr>";
                    foreach ($fields as $value) {
                        $str .= "<td>" . iconv("UTF-8", "GB2312", $item[$value]) . "</td>";
                    }
                    $str .= "</tr>";
                }
                $str .= "</table>";
                echo $str;
                exit;
            } else {
                $this->msg = array("warning", "没有符合该筛选条件的数据，请重新筛选！");
                $this->redirect("notify_list", false, Req::args());
            }
        } else {
            $this->msg = array("warning", "请选择筛选条件后再导出！");
            $this->redirect("notify_list", false);
        }
    }

    public function send_notify() {
        $condition = Req::args("condition");
        $notify_model = new Model("notify as n");
        $condition = Common::str2where($condition);
        if ($condition != null) {
            $items = $notify_model->fields("n.*,go.name as goods_name,u.name as user_name")->join("left join user as u on n.user_id = u.id left join goods as go on n.goods_id = go.id")->where($condition)->findAll();
            $mail = new Mail();
            $msg_model = new Model("msg_template");
            $template = $msg_model->where("id=1")->find();
            $success = 0;
            $fail = 0;
            foreach ($items as $item) {
                $subject = str_replace(array('{$user_name}', '{$goods_name}'), array($item['user_name'], $item['goods_name']), $template['title']);
                $body = str_replace(array('{$user_name}', '{$goods_name}'), array($item['user_name'], $item['goods_name']), $template["content"]);
                $status = $mail->send_email($item['email'], $subject, $body);
                if ($status) {
                    $data = array('notify_time' => date('Y-m-d H:i:s'), 'notify_status' => '1');
                    $notify_model->data($data)->where('id=' . $item['id'])->update();
                    $success++;
                } else {
                    $fail++;
                }
            }
            $return = array('isError' => false, 'count' => count($items), 'success' => $success, 'fail' => $fail);
        } else {
            $return = array('isError' => true, 'msg' => '没有选择筛选条件！');
        }
        echo JSON::encode($return);
    }

    public function message_send() {
        $condition = Req::post("condition");
        $condition = Common::str2where($condition);
        $model = new Model();
        Req::post("time", date('Y-m-d H:i:s'));
        
        $has_user = true;
        if ($condition != '') {
            $users = $model->table("customer")->fields('count(*) as count')->where($condition)->find();
            
            if ($users['count']>1){
                $has_user = true;
            }else if ($users['count']==1){
                $has_user = true;
                $only = $model->table("customer")->fields('user_id')->where($condition)->find();
                Req::post("only", $only['user_id']);
            }else{
                $has_user = false;
            }
                
                
        }
        if ($has_user) {
            
            $last_id = $model->table("message")->insert();
            $model->table("customer")->data(array('message_ids' => "concat_ws (',',`message_ids`,'$last_id')"));
            if ($condition != '')
                $model->where($condition)->update();
            else
                $model->update();
            $this->redirect("message_list");
        }else {
            $this->msg = array("warning", "发送的对象不存在，因此无法发送，请修改筛选条件重新发送！");
            $this->redirect("message_edit", false, Req::args());
        }
    }

    public function message_edit() {
        $model = new Model('grade');
        $rows = $model->findAll();
        $grade = '';
        foreach ($rows as $row) {
            $grade .= $row['id'] . ":'" . $row['name'] . "',";
        }
        $grade = trim($grade, ',');
        $this->assign('grade', $grade);
        $this->redirect();
    }

    function ask_edit() {
        $id = intval(Req::args("id"));
        if ($id) {
            $model = new Model("ask");
            $obj = $model->where("id=$id")->find();
            if ($obj) {
                $goods = $model->table("goods")->fields("name")->where("id=" . $obj['goods_id'])->find();
                $user = $model->table("user")->fields("name")->where("id=" . $obj['user_id'])->find();
                $obj['goods_name'] = isset($goods['name']) ? $goods['name'] : '<h1 class="red">商品已经不存在</h1>';
                $obj['user_name'] = isset($user['name']) ? $user['name'] : '用户已不存在';
                $this->redirect("ask_edit", false, $obj);
            } else {
                $this->msg = array("error", "此咨询不存在，查证后再试！");
                $this->redirect("ask_edit", false, Req::args());
            }
        } else {
            $this->msg = array("error", "此咨询不存在，查证后再试！");
            $this->redirect("ask_edit", false, Req::args());
        }
    }

    //商品咨询
    function ask_list() {
        // $model = new Model();
        // $order = $model->table('order_offline')->where('id>60000 and type=9 and user_id=1')->findAll();
        // for($i=0;$i<count($order);$i++){
        //     $log = $model->table('balance_log')->where("order_no=".$order[$i]['order_no']." and user_id=".$order[$i]['shop_ids'])->find();
        //     $t1 = strtotime($log['time']);
        //     $t2 = strtotime($log['time'])-600;
        //     $invite = $model->table('invite')->where("createtime < $t1 and user_id=".$order[$i]['prom_id'])->order('createtime desc')->findAll();
        //     $uid = $invite?$invite[0]['invite_user_id']:1;
        //     $model->table('order_offline')->data(array('user_id'=>$uid,'type'=>11))->where('order_no='.$order[$i]['order_no'])->update();
        // }

        $condition = Req::args("condition");
        $condition_str = Common::str2where($condition);
        if ($condition_str)
            $this->assign("where", $condition_str);
        else
            $this->assign("where", "1=1");
        $this->assign("condition", $condition);
        $this->redirect();
    }

    //商品评价
    function review_list() {
        $condition = Req::args("condition");
        $condition_str = Common::str2where($condition);
        if ($condition_str)
            $this->assign("where", $condition_str);
        else
            $this->assign("where", "1=1");
        $this->assign("condition", $condition);
        $this->redirect();
    }

    //对应article的验证与过滤
    function ask_validator() {
        $manager = $this->safebox->get('manager');
        $rules = array('content:required:内容不能为空！');
        $info = Validator::check($rules);
        if ($info == true) {
            Filter::form(array('text' => 'content'));
            $content = TString::nl2br(Req::args('content'));
            Req::args('content', $content);
            if (Req::args('id') != null) {
                Req::args('reply_time', date('Y-m-d H:i:s'));
                Req::args('status', 1);
                Req::args('admin_id', $manager['id']);
            }
        }
        return $info;
    }

    function customer_list() {
        $condition = Req::args("condition");
        if($condition) {
            var_dump($condition);die;
        }
        $condition_str = Common::str2where($condition);
        if ($condition_str)
            $this->assign("where", $condition_str);
        else
            $this->assign("where", "1=1");
        $this->assign("condition", $condition);
        $this->redirect();
    }

    function blacklist() {
        $condition = Req::args("condition");
        $condition_str = Common::str2where($condition);
        if ($condition_str)
            $this->assign("where", $condition_str);
        else
            $this->assign("where", "1=1");
        $this->assign("condition", $condition);
        $this->redirect();
    }

    public function customer_export(){
        $condition = Req::args("condition");
        $condition_str = Common::str2where($condition);
        if($condition_str){
            $where = $condition_str;
        }else{
            $where = '1=1';
        }
        $model=new Model('customer as c');
        $customer=$model->join('invite as i on c.user_id=i.invite_user_id')->where($where)->fields('c.user_id,c.real_name,c.mobile')->findAll();
        foreach($customer as $k => $v){
            $customer[$k]['inviter_id']=Common::getInviterId($v['user_id']);
            $customer[$k]['inviter_name']=Common::getInviterName($v['user_id']);
            $customer[$k]['first_promoter']=Common::getFirstPromoterName($v['user_id']);
            $customer[$k]['first_district']=Common::getFirstDistricter($v['user_id']);
        }
       $strTable = '<table width="500" border="1">';
        $strTable .= '<tr>';
        $strTable .= '<td style="text-align:center;font-size:20px;width:100px;">用户ID</td>';
        $strTable .= '<td style="text-align:center;font-size:20px;width:100px;">用户昵称</td>';
        $strTable .= '<td style="text-align:center;font-size:20px;" width="100px">联系电话</td>';
        $strTable .= '<td style="text-align:center;font-size:20px;" width="100px">上级邀请人ID</td>';
        $strTable .= '<td style="text-align:center;font-size:20px;" width="100px">上级邀请人</td>';
        $strTable .= '<td style="text-align:center;font-size:20px;" width="100px">上级代理商</td>';
        $strTable .= '<td style="text-align:center;font-size:20px;" width="100px">上级经销商</td>';  
        $strTable .= '</tr>';

        if (is_array($customer)) {
            foreach ($customer as $k => $val) {
                $strTable .= '<tr>';
                $strTable .= '<td style="text-align:center;font-size:20px;">' . $val['user_id'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:20px;">' . $val['real_name'] . ' </td>';
                $strTable .= '<td style="text-align:left;font-size:20px;">' . $val['mobile'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:20px;">' . $val['inviter_id'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:20px;">' . $val['inviter_name'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:20px;">' . $val['first_promoter'] . '</td>';
                $strTable .= '<td style="text-align:left;font-size:20px;">' . $val['first_district'] . '</td>';
                $strTable .= '</tr>';
            }
            unset($customer);
        }
        $strTable .= '</table>';
        Common::downloadExcel($strTable, 'customer');
        exit();
    }

    public function customer_edit() {
        $id = Req::args("id");

        $customer = Req::args();
        if ($id) {
            $model = new Model("customer as c");
            $customer = $model->join("user as u on c.user_id = u.id")->where("c.user_id=" . $id)->find();
        }
        $this->redirect('customer_edit', false, $customer);
    }

    public function blacklist_edit() {
        $id = Req::args("id");

        $customer = Req::args();
        if ($id) {
            $model = new Model("blacklist as b");
            $customer = $model->join("customer as c on b.user_id = c.user_id")->where("b.id=" . $id)->find();
            $this->assign('id',$id);
        }
        $this->redirect('blacklist_edit', false, $customer);
    }

    public function customer_del() {
        $id = Req::args("id");
        if (is_array($id)) {
            $cond = ' in (' . implode(",", $id) . ')';
        } else {
            $cond = " = $id";
        }
        $model = new Model();
        $users = $model->table("user")->where("id $cond")->findAll();
        $model->table("customer")->where("user_id $cond")->delete();
        $model->table("user")->where("id $cond")->delete();
        $model->table("oauth_user")->where("user_id $cond")->delete();
        if ($users) {
            $user_names = "";
            foreach ($users as $user) {
                $user_names .= $user['name'] . "、";
            }
            $user_names = trim($user_names, '、');
            Log::op($this->manager['id'], "删除会员", "管理员[" . $this->manager['name'] . "]:删除了会员 " . $user_names);
        }
        $this->redirect("customer_list");
    }

    public function customer_save() {
        $id = Req::args("id");
        $name = Req::args("name");
        $email = Req::args("email");
        $password = Req::args("password");
        $mobile = Req::args("mobile");
        $birthday = Req::post("birthday");
        $huabi_account = Req::post("huabi_account");
        $userModel = new Model("user");

        $customerModel = new Model("customer");
        if ($id) {
            $user = $userModel->where("id=$id")->find();
            if ($user) {
                if ($name && $email)
                    $userModel->data(array('name' => $name, 'email' => $email))->where("id=$id")->update();
                Req::args('user_id', $id);
                $customerModel->where("user_id=$id")->update();
                Log::op($this->manager['id'], "修改会员", "管理员[" . $this->manager['name'] . "]:修改了会员 " . $user['name'] . " 的信息");
            }
        }else {
            if($email==""){
                $where = "name='{$name}'";
            }else{
                $where = "name='{$name}' or email ='{$email}'";
            }
            $user = $userModel->where($where)->find();
            $customer = $customerModel->where("mobile='{$mobile}' and status = 1")->find();
            if ($user || $customer) {
                $this->msg = array("error", "用户名或邮箱已经存在！");
                $this->redirect("customer_edit", false);
                exit;
            } else {
                $validcode = CHash::random(8);
                $last_id = $userModel->data(array('name' => $name, 'password' => CHash::md5($password, $validcode), 'validcode' => $validcode, 'email' => $email,'avatar'=>'/0.png'))->add();
                Req::args('user_id', $last_id);
                Req::args('reg_time',date("Y-m-d H:i:s"));
                Req::args('real_name', $mobile);
                Req::args('mobile_verified', 1);
                Req::args('checkin_time',date("Y-m-d H:i:s"));
                if (!Validator::date(Req::post('birthday')))
                    Req::post('birthday', date('Y-m-d'));
                $customerModel->insert();
                Log::op($this->manager['id'], "添加会员", "管理员[" . $this->manager['name'] . "]:添加了会员 " . $name . " 的信息");
            }
        }
        $this->redirect("customer_list");
    }

    public function blacklist_save() {
        $id = Req::args("id");
        $user_id = Req::args("user_id");
        $start_time = Req::args("start_time");
        $end_time = Req::args("end_time");
        
        $blacklist = new Model("blacklist");

        $customerModel = new Model("customer");
        if ($id) {
            $user = $blacklist->where("id=$id")->find();
            if ($user) {
                if ($start_time || $end_time)
                    $blacklist->data(array('start_time' => $start_time, 'end_time' => $end_time))->where("id=$id")->update();
                Req::args('user_id', $id);
                Log::op($this->manager['id'], "修改黑名单", "管理员[" . $this->manager['name'] . "]:修改了会员 " . $user['user_id'] . " 的信息");
            }
        }else {
                $last_id = $blacklist->data(array('user_id'=>$user_id,'start_time' => $start_time,'end_time' => $end_time))->add();
                Log::op($this->manager['id'], "添加会员", "管理员[" . $this->manager['name'] . "]:添加了会员 " . $user_id . " 的信息");
            
        }
        $this->redirect("blacklist");
    }

    public function customer_password() {
        $id = Req::post("id");
        $password = Req::post("password");
        $repassword = Req::post("repassword");
        $info = array('status' => 'fail');
        if ($id && $password && $password == $repassword) {
            $model = new Model("user");
            $validcode = CHash::random(8);
            $flag = $model->where("id=$id")->data(array('password' => CHash::md5($password, $validcode), 'validcode' => $validcode))->update();
            if ($flag)
                $info = array('status' => 'success');
        }
        echo JSON::encode($info);
    }

    public function customer_invite() {
        $id = Req::get("id");
        if ($id) {
            $model = new Model("customer as c");
            $customer = $model->join("user as u on c.user_id = u.id")->where("c.user_id=" . $id)->find();
            if ($customer) {
                $model = new Model("invite");
                $inviteinfo = $model->where("invite_user_id='{$id}'")->find();
                $from = $inviteinfo ? $inviteinfo['from'] : '官网';
                $this->model = new Model("invite");
                $list = $this->get_category($id);
                $list[] = array('id' => $id, 'user_id' => 0, 'invite_user_id' => $id, 'name' => $customer['nickname'], 'realname' => $customer['real_name'], 'avatar' => $customer['avatar'], 'createtime' => strtotime($customer['reg_time']), 'from' => $from);
//                $tree = new Tree();
//                $tree->nbsp = "&nbsp;&nbsp;";
//                $rolearray = $tree->init($list, 'user_id')->get_tree_array(0);
//                $sonlist = $tree->get_tree_list($rolearray);
                $sonlist = array();
                $this->assign("sonlist", $sonlist);
                $this->assign("childlist", $list);
            }
        }
        $this->redirect();
    }

    public function customer_invited() {
        $id = Req::get("id");
        if ($id) {
            $model = new Model("customer as c");
            $customer = $model->join("user as u on c.user_id = u.id")->where("c.user_id=" . $id)->find();
            $name = $customer['name'];
            $real_name = $customer['real_name'];
            $this->assign("name", $name);
            $this->assign("real_name", $real_name);
            if ($customer) {
                $model = new Model("invite");
                $inviteinfo = $model->where("invite_user_id='{$id}'")->find();
                if($inviteinfo){
                    $model1=new Model('customer as c');
                    $user=$model1->join("user as u on c.user_id = u.id")->fields('c.user_id,c.real_name,u.avatar')->where("c.user_id=" . $inviteinfo['user_id'])->find();
                    $inviter_name=$user['real_name'];
                    $avatar=$user['avatar'];
                    $this->assign("inviter_name", $inviter_name);//上级邀请人
                    $this->assign("avatar", $avatar);
                    $model2=new Model('district_promoter');
                    $promoter=$model2->where('user_id='.$inviteinfo['user_id'])->find();
                    // if($promoter){
                    //     $model3=new Model('customer');
                    //     $promoter_user=$model3->fields('real_name')->where('user_id='.$promoter['user_id'])->find();
                    //     $promoter_name=$promoter_user['real_name'];
                    //     $this->assign("promoter_name", $promoter_name);
                    // }
                    $promoter_id=Common::getFirstPromoter($id);
                    $model3=new Model('customer');
                    $promoter_user=$model3->fields('real_name')->where('user_id='.$promoter_id)->find();
                    $promoter_name=$promoter_user['real_name'];
                    $this->assign("promoter_name", $promoter_name);//上级代理商
                    $model4=new Model('district_shop');
                    // $district=$model4->fields('name')->where('owner_id='.$inviteinfo['user_id'])->find();
                    $district=$model4->fields('name,owner_id')->where('id='.$inviteinfo['district_id'])->find();
                    if($district){
                        $district_name=$district['name'];
                        $this->assign("district_name", $district_name);//上级经销商
                        $model3=new Model('customer');
                        $district_user=$model3->fields('real_name')->where('user_id='.$district['owner_id'])->find();
                        $district_realname=$district_user['real_name'];
                        $this->assign("district_realname", $district_realname);//上级经销商真实名字
                    }
                }
            }
        }
        $this->redirect();
    }

    function get_category($id) {
        $ids = is_array($id) ? $id : explode(',', $id);
        $list = $this->model->table("invite AS inv")->fields('inv.*,us.nickname AS name,us.avatar,cu.real_name AS realname')->where("inv.user_id IN (" . implode(',', $ids) . ")")
                        ->join("left join user as us on inv.invite_user_id = us.id LEFT JOIN customer as cu ON inv.invite_user_id = cu.user_id")->findAll();
        $new = array();
        $datalist = array();
        foreach ($list as $k => $v) {
            $new[] = $v['invite_user_id'];
            $v['id'] = $v['invite_user_id'];
            $datalist[] = $v;
        }
        return array_merge($datalist, $new ? $this->get_category($new) : array());
    }

    public function withdrawDf(){
        header('Content-Type: text/html; Charset=UTF-8');
        $tools=new PhpTools();
        $merchantId=AppConfig::MERCHANT_ID;
        // 源数组
        $data = array(
            'INFO' => array(
                'TRX_CODE' => '100014',
                'VERSION' => '03',
                'DATA_TYPE' => '2',
                'LEVEL' => '6',
                // 'USER_NAME' => '20060400000044502',
                'USER_NAME' => '20058400001550504',
                // 'USER_PASS' => '`12qwe',
                'USER_PASS' => '111111',
                'REQ_SN' => $merchantId.date('YmdHis').rand(1000,9999),
            ),
            'TRANS' => array(
                'BUSINESS_CODE' => '09900',
                'MERCHANT_ID' => $merchantId,
                'SUBMIT_TIME' => date('YmdHis'),
                'E_USER_CODE' => '10101328',
                'BANK_CODE' => '',
                'ACCOUNT_TYPE' => '00',
                'ACCOUNT_NO' => '6227002021490888887',
                'ACCOUNT_NAME' => '潜非凡',
                'ACCOUNT_PROP' => '0',
                'AMOUNT' => '1',
                'CURRENCY' => 'CNY',
                'ID_TYPE' => '0',
                'CUST_USERID' => '2901347',
                'SUMMARY' => '春风贷提现',
                'REMARK' => '',
            ),
        );

        //发起请求
        $result = $tools->send($data);
        if($result!=FALSE){
            echo  '验签通过，请对返回信息进行处理';die;
            // return true;
            //下面商户自定义处理逻辑，此处返回一个数组
        }else{
            // return false;
                print_r("验签结果：验签失败，请检查通联公钥证书是否正确");die;
        }
        // $result = Common::allinpayDf();
        // var_dump($result);die;
    }

    public function withdrawDfs(){
        $result = Common::allinpayDf();
        var_dump($result);die;
    }

    public function withdraw_export_excel() {
        $this->layout = '';
        $condition = Req::args("condition");
        $fields = Req::args("fields");
        $condition = Common::str2where($condition);
        $model = new Model("balance_withdraw as wd");
        if ($condition) {
            $where = $condition;
        }else{
            $where = '1=1';
        } 
            $items = $model->where($where)->order('wd.id desc')->findAll();
            
            if ($items) {
                header("Content-type:application/vnd.ms-excel");
                header("Content-Disposition:filename=doc_receiving_list.xls");
                $fields_array = array('withdraw_no' => '提现单号','amount' => '金额', 'open_name' => '开户名', 'open_bank' => '开户行','card_no'=>'银行卡号', 'apply_date' => '时间');
                $str = "<table border=1><tr>";
                foreach ($fields as $value) {
                    $str .= "<th>" . iconv("UTF-8", "GBK", $fields_array[$value]) . "</th>";
                }
                $str .= "</tr>";
                foreach ($items as $item) {
                    $str .= "<tr>";
                    foreach ($fields as $value) {
                        $str .= "<td>" . iconv("UTF-8", "GBK", $item[$value]) . "</td>";
                    }
                    $str .= "</tr>";
                }
                $str .= "</table>";
                echo $str;
                exit;
            } else {
                $this->msg = array("warning", "没有符合该筛选条件的数据，请重新筛选！");
                $this->redirect("doc_receiving_list", false, Req::args());
            }
    }

    public function balance_export_excel() {
        $this->layout = '';
        $condition = Req::args("condition");
        $fields = Req::args("fields");
        $page = Filter::int(Req::args("page"));
        if(!$page){
            $page = 1;
        }
        $condition = Common::str2where($condition);
        $model = new Model("balance_log as bl");
        if ($condition) {
            $where = $condition;
        }else{
            $where = '1=1';
        } 
            $items = $model->join('left join user as us on bl.user_id = us.id left join customer as c on c.user_id = bl.user_id')->fields('bl.*,us.name,c.real_name,c.mobile')->where($where)->order('bl.id desc')->findPage($page,10);
            if ($items) {
                foreach($items['data'] as $k=>$v){
                    $items['data'][$k]['order_no'] ='@'.$v['order_no'];
                }
                header("Content-type:application/vnd.ms-excel");
                header("Content-Disposition:filename=balance_log_list.xls");
                $fields_array = array('order_no' => '订单号','time' => '时间', 'amount' => '金额', 'amount_log' => '当前余额','name'=>'用户', 'real_name' => '名称','note'=>'备注');
                $str = "<table border=1><tr>";
                foreach ($fields as $value) {
                    $str .= "<th>" . iconv("UTF-8", "GB2312", $fields_array[$value]) . "</th>";
                }
                $str .= "</tr>";
                foreach ($items['data'] as $item) {
                    $str .= "<tr>";
                    foreach ($fields as $value) {
                        $str .= "<td>" . iconv("UTF-8", "GB2312", $item[$value]) . "</td>";
                    }
                    $str .= "</tr>";
                }
                $str .= "</table>";
                echo $str;
                exit;
            } else {
                $this->msg = array("warning", "没有符合该筛选条件的数据，请重新筛选！");
                $this->redirect("balance_list", false, Req::args());
            }
    }

    public function pointcoin_export_excel(){
        $this->layout = '';
        $condition = Req::args("condition");
        $fields = Req::args("fields");
        $condition = Common::str2where($condition);
        $model = new Model("pointcoin_log as bl");
        if ($condition) {
            $where = $condition;
        }else{
            $where = '1=1';
        } 
            $items = $model->join('left join user as us on bl.user_id = us.id left join customer as c on c.user_id = bl.user_id')->fields('bl.*,us.name,c.real_name,c.mobile')->where($where)->order('bl.id desc')->findAll();
            if ($items) {
                header("Content-type:application/vnd.ms-excel");
                header("Content-Disposition:filename=pointcoin_log_list.xls");
                $fields_array = array('log_date' => '时间', 'amount' => '金额', 'current_amount' => '余额','name'=>'用户', 'real_name' => '名称','note'=>'备注');
                $str = "<table border=1><tr>";
                foreach ($fields as $value) {
                    $str .= "<th>" . iconv("UTF-8", "GB2312", $fields_array[$value]) . "</th>";
                }
                $str .= "</tr>";
                
                foreach ($items as $item) {
                    $str .= "<tr>";
                    foreach ($fields as $value) {
                        $str .= "<td>" . iconv("UTF-8", "GB2312", $item[$value]) . "</td>";
                    }
                    $str .= "</tr>";
                }
                $str .= "</table>";
                echo $str;
                exit;
            } else {
                $this->msg = array("warning", "没有符合该筛选条件的数据，请重新筛选！");
                $this->redirect("pointcoin_list", false, Req::args());
            }
    }

}
