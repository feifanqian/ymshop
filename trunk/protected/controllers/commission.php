<?php

class CommissionController extends Controller {

    public $layout = 'admin';
    private $top = null;
    private $manager = null;
    private $parse_returns_status = array(0 => '<b class="red">等待审核</b>', 1 => '<b class="red">等待回寄货品</b>', 2 => '<b class="red">拒绝</b>', 3 => '货品回寄中', 4 => '已结束');
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
        $this->manager = $this->safebox->get('manager');
        $this->assign('manager', $this->manager);
        $this->assign('upyuncfg', Config::getInstance()->get("upyun"));

        $currentNode = $menu->currentNode();
        if (isset($currentNode['name']))
            $this->assign('admin_title', $currentNode['name']);
    }

    public function noRight() {
        $this->layout = '';
        $this->redirect("admin/noright");
    }


    public function commission_list_order(){
        $condition = Req::args("condition");
        $condition_str = Common::str2where($condition);
        
        if ($condition_str){
            $this->assign("where", $condition_str." and c.type = 1");
         }else {
            $this->assign("where", "c.type = 1");
        }
        $this->assign("condition", $condition);
        $this->redirect();
    }
    
    public function commission_list_recharge(){
        $condition = Req::args("condition");
        $condition_str = Common::str2where($condition);
        if ($condition_str){
            $this->assign("where", $condition_str." and c.type = 2");
         }else {
            $this->assign("where", "c.type = 2");
        }
        $this->assign("condition", $condition);
        $this->redirect();
    }
    public function commission_setting(){
        $group = "commission_set";
        $config = Config::getInstance();
        if (Req::args('submit') != null) {
            $configService = new ConfigService($config);
            if (method_exists($configService, $group)) {
                $result = $configService->$group();
                if (is_array($result)) {
                    $this->assign('message', $result['msg']);
                } else if ($result == true) {
                    $this->assign('message', '信息保存成功！');
                }
                //清除opcache缓存
                if (extension_loaded('opcache')) {
                    opcache_reset();
                }
                Log::op($this->manager['id'], "修改佣金配置", "管理员[" . $this->manager['name'] . "]:修改了佣金配置 ");
            }
        }
        $this->assign('data', $config->get($group));
        $this->redirect();
    }
    public function commission_count(){
        $condition = Req::args("condition");
        $condition_str = Common::str2where($condition);
        if ($condition_str){
            $this->assign("where", $condition_str);
         }else {
            $this->assign("where", "1=1");
        }
        $this->assign("condition", $condition);
        $this->redirect();
    }
    public function commission_withdraw(){
        $condition = Req::args("condition");
        $condition_str = Common::str2where($condition);
        if ($condition_str){
            $this->assign("where", $condition_str);
         }else {
            $this->assign("where", "1=1");
        }
        
        $this->assign("condition", $condition);
        $this->redirect();
    }
    
    public function setPromoter(){
        $uid = Filter::sql(Req::args('user_id'));
        $status = Filter::sql(Req::args("status"));
        if($status!=1&&$status!=2){
            $info=array('status'=>'fail','msg'=>"操作失败,参数错误");
        }else{
           $customer = new Model("customer");
           $record = $customer->where("user_id =$uid")->fields("user_id,is_promoter")->find();
           if(empty($record)){
               $info=array('status'=>'fail','msg'=>"操作失败，记录为空");
           }else{
               $commission = new Model("commission");
               if($record['is_promoter']==0 && $status==1){//后台新增推客
                   $result1 = $commission->where("user_id =$uid")->find();
                   if(empty($result1)){//如果没有记录
                        $result1 = $commission->data(array('user_id'=>$uid,'commission_available'=>0.00,'commission_possess_now'=>0.00,'commission_withdrew'=>0.00,'create_time'=>date("Y-m-d H:i:s"),'status'=>0,'type'=>1))->insert();
                   }
                   if($result1){//数据库更新失败  
                         $result2 = $customer ->data(array('is_promoter'=>1))->where("user_id = $uid")->update();
                   }
               }else{
                   $result1 = $commission->data(array('status'=>$status-1))->where("user_id = $uid")->update();
                   if($result1){
                        $result2 = $customer ->data(array('is_promoter'=>$status))->where("user_id = $uid")->update();
                   }
               }
               if($result1 && $result2){
                   $info=array('status'=>'success','msg'=>"操作成功");
                   Log::op($this->manager['id'], "添加了推客", "管理员[" . $this->manager['name'] . "]:添加了新推客[user_id=$uid]");
               }else{
                    $info=array('status'=>'fail','msg'=>"操作失败，数据库更新失败");
               }
           }
        }
        echo JSON::encode($info);
    }
    
    public function updateWithdrawStatus(){
        $id = Filter::sql(Req::args("id"));
        $status = Filter::sql(Req::args("status"));
        $withdraw = new Model("commission_withdraw");
        if($status ==1 || $status ==2){
            $result = $withdraw ->where("id = $id")->find();
            if(empty($result)){
                 $info=array('status'=>'fail','msg'=>"操作失败，记录为空");
            }else{
                if($result['status']!=0){
                     $info=array('status'=>'fail','msg'=>"操作失败，该请求已经处理过了");
                }else{
                    if($status==1){
                        $commission = new Model("commission");
                        $record = $commission->where("user_id =".$result['user_id'])->find();
                        if(empty($record) || $record['status']==1){
                             $info=array('status'=>'fail','msg'=>"操作失败，提现推客不存在或被锁定");
                        }else{
                            if($result['withdraw_amount']>$record['commission_available']){
                                $info=array('status'=>'fail','msg'=>"操作失败，提现金额大于推客的可用佣金");
                            }else{
                                //更新佣金统计记录
                                $isOk1 = $commission ->data(array('commission_available'=>round($record['commission_available']-round($result['withdraw_amount'],2),2),
                                    'commission_possess_now'=>round($record['commission_possess_now']-round($result['withdraw_amount'],2),2),
                                    'commission_withdrew'=>round($record['commission_withdrew']+round($result['withdraw_amount'],2),2),
                                    'last_withdrew_time'=>date("Y-m-d H:i:s")))->where("user_id=".$result['user_id'])->update();
                                if($isOk1){
                                    //更新佣金申请状态
                                    $isOk2 = $withdraw->where("id = $id")->data(array('status'=>1))->update();
                                }
                                if($result['withdraw_type']==1 && $isOk1 && $isOk2){//提现至金点
                                    $model = new Model("customer");
                                    $commission_amount = $result['withdraw_amount'];
                                    $flag = $model->query("update tiny_customer set balance = balance + $commission_amount where user_id =".$result['user_id']);
                                    if($flag){
                                        Log::balance($commission_amount, $result['user_id'], $result['withdraw_no'], '佣金获取',5);
                                    }
                                    $info=array('status'=>'success','msg'=>"操作成功,已将佣金计入金点账户");
                                    Log::op($this->manager['id'], "处理提现申请", "管理员[" . $this->manager['name'] . "]:处理了提现申请，佣金计入金点账户[佣金提现记录withdraw_no={$result['withdraw_no']}]");
                                }else if( $result['withdraw_type']==2 && $isOk1 && $isOk2){
                                    $info=array('status'=>'success','msg'=>"操作成功");
                                    Log::op($this->manager['id'], "处理提现申请", "管理员[" . $this->manager['name'] . "]:处理了提现申请，人工转账成功[佣金提现记录withdraw_no={$result['withdraw_no']}]");
                                }else{
                                    $info=array('status'=>'fail','msg'=>"操作失败，数据库更新失败");
                                    file_put_contents("commissionError.txt", "佣金操作失败：\r\n"."佣金提现记录id=$id"."记录".  json_encode($result) ."出错时间：".date("Y-m-d H:i:s")."\r\n__________________________",FILE_APPEND);
                                }
                            }
                        }
                    }else if($status==2){
                        $isOk2 = $withdraw->where("id = $id")->data(array('status'=>2))->update();
                        if($isOk2){
                            $info=array('status'=>'success','msg'=>"操作成功");
                            Log::op($this->manager['id'], "处理提现申请", "管理员[" . $this->manager['name'] . "]:作废了提现申请[提现记录id=$id]");
                        }else{
                            $info=array('status'=>'fail','msg'=>"操作失败，数据库更新失败");
                        }
                    }
                    
                }
            }
        }else{
            $info=array('status'=>'fail','msg'=>"操作失败，参数错误");
        }
        echo JSON::encode($info);
    }
}
