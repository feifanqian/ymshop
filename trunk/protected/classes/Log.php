<?php

//日志类
class Log {

    //操作日志写入
    public static function op($manager_id, $action, $content) {
        $logs = array('manager_id' => $manager_id, 'action' => $action, 'content' => $content, 'ip' => Chips::getIP(), 'url' => Url::requestUri(), 'time' => date('Y-m-d H:i:s'));
        $model = new Model('log_operation');
        $model->data($logs)->insert();
    }

    //余额日志变化写入
    public static function balance($amount, $user_id, $order_no='', $note = '', $type = 0, $admin_id = 0) {
        //事件类型: 0:购物下单 1:用户充值 2:管理员充值 3:余额提现 4:管理员退款 5：线上消费收益 6:推广收益 7：商城分红 8:下级会员线下消费提成 9:商家余额转入 10:推广收益提现到余额 11:商家余额提现 12:提现回退到余额 13:提现回退到商家余额 14:抢红包所得 15:红包退回 16：卡券兑换 17:余额发红包 18:拼团失败余额退回 19:线上消费商家收款 20:线下消费商家收款 21:优惠购收益 22:优惠购收益退回 23:加盟商家服务费
        $model = new Model();
        $customer = $model->table('customer')->fields("balance,offline_balance")->where("user_id=" . $user_id)->find();
        if ($customer) {
            $log = array(   'amount' => $amount, 
                            'user_id' => $user_id, 
                            'time' => date('Y-m-d H:i:s'), 
                            'amount_log' => $customer['balance']+$customer['offline_balance'], 
                            'admin_id' => $admin_id, 
                            'type' => $type, 
                            'note' => $note,
                            'order_no'=>$order_no,);
            $id = $model->table("balance_log")->data($log)->insert();
        }
    }
    public static function pointcoin_log($amount, $user_id, $order_no='', $note = '', $type = 0, $admin_id = 0) {
        //事件类型: 0:购物下单 1:套餐充值 2:系统退回 3：管理员充值 4：管理员退款 5:代理商入驻赠送 6:代理商推荐奖励 7：专区销售积分奖励 8:经营商入驻赠送 9:购买商品赠送 10:每日签到赠送 11：商城分红 12：订单作废，积分退回 13：卡券兑换 14:积分兑换VIP
        $model = new Model();
        if($type==0){
            $amount = 0 - abs($amount);
        }
        $customer = $model->table('customer')->fields("point_coin")->where("user_id=" . $user_id)->find();
        if ($customer) {
            $log = array(   'admin_id' => $admin_id, 
                            'user_id' => $user_id, 
                            'type' => Filter::int($type),
                            'log_date' => date('Y-m-d H:i:s'), 
                            'amount' => $amount, 
                            'current_amount' => $customer['point_coin'], 
                            'note' => $note,
                            'order_no'=>$order_no,);
            $model->table("pointcoin_log")->data($log)->insert();
        }
    }
    
    //收益记录
    public static function incomeLog($amount , $role_type, $role_id , $record_id ,$type ,$note=""){
        $type_info=array(
            "0"=>"下级会员购买收益分成",
            "1"=>"用户推广商品收益",
            "2"=>"代理商享受用户的推广商品收益分成",
            "3"=>"专区主享受用户的推广商品收益分成",
            "4"=>"代理商推广商品分成",
            "5"=>"专区享受代理商的推广商品收益分成",
            "6"=>"上级专区享受下级专区推广商品分成",
            "7"=>"会员邀请收益",
            "8"=>"代理商邀请代理商收益",
            "9"=>"专区邀请代理商收益",
            "10"=>"专区邀请专区收益",
            "11"=>"收益提取",
            "12"=>"收益撤销",
            "13"=>"收益解锁",
            "14"=>"下级会员升级奖励",
            "15"=>"退款收回收益",
            "16"=>"收益提现申请"
        );
        $model = new model();
       
        $data = array();
        if(in_array($type,array(0,1,2,3,4,5,6))){
            $data['valid_income_change'] = 0.00;
            $data['frezze_income_change'] = abs($amount);
            $data['settled_income_change'] = 0.00;
        }else if(in_array($type, array(7,8,9,10,14))){
            $data['valid_income_change'] = abs($amount);
            $data['frezze_income_change'] = 0.00;
            $data['settled_income_change'] = 0.00;
        }else if(in_array($type,array(11,12))){
            if($type==11){
                $data['valid_income_change'] =0 - abs($amount);
                $data['frezze_income_change'] = 0.00;
                $data['settled_income_change'] = abs($amount);
            }else if($type ==12){
                $data['valid_income_change'] = 0.00;
                $data['frezze_income_change'] = 0 - abs($amount);
                $data['settled_income_change'] = abs($amount);
            }
        }else if($type == 13){
            $data['valid_income_change'] =  abs($amount);
            $data['frezze_income_change'] = 0 - abs($amount);
            $data['settled_income_change'] =0.00;
        }else if($type == 15){
            $role_id=intval($role_id);
            $customer = $model->table("customer")->where("user_id={$role_id}")->fields("valid_income,frezze_income,settled_income")->find();
            if($customer){
                if($customer['frezze_income']>=abs($amount)){
                    $data['valid_income_change'] =  0.00;
                    $data['frezze_income_change'] = 0 - abs($amount);
                    $data['settled_income_change'] =0.00; 
                }else{
                    $amount1=abs($amount)-$customer['frezze_income'];
                    $data['valid_income_change'] =  0 - $amount1;
                    $data['frezze_income_change'] = 0 - $customer['frezze_income'];
                    $data['settled_income_change'] =0.00;
                }
            }else{
                $data['valid_income_change'] =  0.00;
                $data['frezze_income_change'] = 0.00;
                $data['settled_income_change'] =0.00;
            }      
        }elseif($type==16){
            $data['valid_income_change'] =0 - abs($amount);
            $data['frezze_income_change'] = 0.00;
            $data['settled_income_change'] = 0.00;
        }else{
            return false;
        }
        if($role_type==1||$role_type==2){
             $customer = $model->table("customer")->where("user_id={$role_id}")->fields("valid_income,frezze_income,settled_income")->find();
             if(!$customer){
                return false;
            }
            $result = $model->table("customer")->data(array("valid_income"=>"`valid_income`+({$data['valid_income_change']})","frezze_income"=>"`frezze_income`+({$data['frezze_income_change']})","settled_income"=>"`settled_income`+({$data['settled_income_change']})"))->where("user_id={$role_id}")->update();
        }else if($role_type==3){
            $customer = $model->table("district_shop")->where("id={$role_id}")->fields("valid_income,frezze_income,settled_income")->find();
             if(!$customer){
                return false;
            }
            $result = $model->table("district_shop")->data(array("valid_income"=>"`valid_income`+({$data['valid_income_change']})","frezze_income"=>"`frezze_income`+({$data['frezze_income_change']})","settled_income"=>"`settled_income`+({$data['settled_income_change']})"))
                  ->where("id={$role_id}")->update();
        }else{
            return false;
        }
        if($result){
            $data['role_id']=$role_id;
            $data['role_type']=$role_type;
            $data['type']=$type;
            $data['record_id']=$record_id;
            $data['current_valid_income']=$customer['valid_income']+$data['valid_income_change'];
            $data['current_frezze_income']=$customer['frezze_income']+$data['frezze_income_change'];
            $data['current_settled_income']=$customer['settled_income']+$data['settled_income_change'];
            $data['date']=date("Y-m-d H:i:s");
            $data['note']=$note==""?$type_info[$type]:$note;
            $result = $model->table("promote_income_log")->data($data)->insert();
            if($result){
                return true;
            }else{
                file_put_contents('incomelogError.txt', "记录失败：user_id:$role_id|".json_encode($data)."\n",FILE_APPEND);
                return false;
            }
        }else{
            file_put_contents('incomelogError.txt', "更新失败：user_id:$role_id|".json_encode($data)."\n",FILE_APPEND);
            return false;
        }
    }

}
