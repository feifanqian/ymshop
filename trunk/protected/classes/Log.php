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
        //事件类型: 0:购物下单 1:用户充值 2:管理员充值 3:金点提现 4:管理员退款 5：佣金获取 6:推广收益 7：转化成银点 8:外平台返回
        $model = new Model('customer');
        $customer = $model->fields("balance")->where("user_id=" . $user_id)->find();
        if ($customer) {
            $log = array(   'amount' => $amount, 
                            'user_id' => $user_id, 
                            'time' => date('Y-m-d H:i:s'), 
                            'amount_log' => $customer['balance'], 
                            'admin_id' => $admin_id, 
                            'type' => $type, 
                            'note' => $note,
                            'order_no'=>$order_no,);
                $id = $model->table("balance_log")->data($log)->insert();
                if($type==7||$type==0||$type==3){
                    if($type==7){
                        $record_type = 2;
                    }else if($type==0){
                        $record_type = 3;
                    }else if($type==3){
                        $record_type = 1;
                    }
                    Common::recordUseDetail4GoldCoin($user_id, $record_type, abs($amount), $id);
                }
        }
    }
     //银点日志变化写入
    public static function silver_log($amount, $user_id, $order_no='', $note = '', $type = 0, $admin_id = 0) {
        //事件类型: 0:购物下单 1:金点转换  2：用户充值 3:管理员充值  4:管理员退款  5:过期扣减 
        $model = new Model('customer');
        $customer = $model->fields("silver_coin")->where("user_id=" . $user_id)->find();
        if ($customer) {
            $log = array(   'admin_id' => $admin_id, 
                            'user_id' => $user_id, 
                            'type' => $type,
                            'log_time' => date('Y-m-d H:i:s'), 
                            'amount' => $amount, 
                            'balance' => $customer['silver_coin'], 
                            'note' => $note,
                            'order_no'=>$order_no,);
            $model->table("silver_log")->data($log)->insert();
        }
    }
    
     //银点日志变化写入
    public static function pointcoin_log($amount, $user_id, $order_no='', $note = '', $type = 0, $admin_id = 0) {
        //事件类型: 0:购物下单 1:套餐充值 2:系统退回 3：管理员充值 4：管理员退款 5:推广员入驻赠送 6:推广员推荐奖励 7：小区销售积分奖励 8:经营商入驻赠送 9:购买商品赠送
        $model = new Model('customer');
        if($type==0){
            $amount = 0 - abs($amount);
        }
        $customer = $model->fields("point_coin")->where("user_id=" . $user_id)->find();
        if ($customer) {
            $log = array(   'admin_id' => $admin_id, 
                            'user_id' => $user_id, 
                            'type' => $type,
                            'log_date' => date('Y-m-d H:i:s'), 
                            'amount' => $amount, 
                            'current_amount' => $customer['point_coin'], 
                            'note' => $note,
                            'order_no'=>$order_no,);
            $model->table("pointcoin_log")->data($log)->insert();
        }
    }

}
