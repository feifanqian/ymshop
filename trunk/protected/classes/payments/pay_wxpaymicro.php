<?php

/**
 * @class pay_wxpaymicro
 * @brief 微信支付(刷卡支付)
 */
class pay_wxpaymicro extends PaymentPlugin {

    //支付插件名称
    public $name = '微信支付(刷卡支付)';

    //提交地址
    public function submitUrl() {
        return '';
    }

    //取得配制参数
    public static function config() {
        return array(
            array('field' => 'app_id', 'caption' => '应用app_id', 'type' => 'string'),
            array('field' => 'mch_id', 'caption' => '商户号mch_id', 'type' => 'string'),
            array('field' => 'app_key', 'caption' => '商户密钥Key', 'type' => 'string'),
            array('field' => 'app_secret', 'caption' => '应用app_secret', 'type' => 'string'),
        );
    }

    //同步处理
    public function callback($callbackData, &$paymentId, &$money, &$message, &$orderNo) {
        return false;
    }

    //异步处理
    public function asyncCallback($callbackData, &$paymentId, &$money, &$message, &$orderNo) {
        return false;
    }

    //后期与服务同步处理类
    public function afterAsync() {
        
    }

    //打包数据
    public function packData($payment) {
        $return = array();

        return $return;
    }

}
