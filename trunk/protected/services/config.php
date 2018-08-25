<?php

class ConfigService {

    private $config;

    public function __construct(&$config) {
        $this->config = &$config;
    }

    public function globals() {
        $globals = array(
            'site_name' => Req::args('site_name'),
            'site_logo' => Req::args('site_logo'),
            'site_keywords' => Req::args('site_keywords'),
            'site_description' => Req::args('site_description'),
            'site_icp' => Req::args('site_icp'),
            'site_url' => Req::args('site_url'),
            'site_androidurl' => Req::args('site_androidurl'),
            'site_iosurl' => Req::args('site_iosurl'),
            'site_addr' => Req::args('site_addr'),
            'site_mobile' => Req::args('site_mobile'),
            'site_email' => Req::args('site_email'),
            'site_zip' => Req::args('site_zip'),
            'site_phone' => Req::args('site_phone'),
            'site_contactqq'=>Req::args('site_contactqq'),
        );
        $this->config->set('globals', $globals);
        return true;
    }

    public function photo() {
        $photo = array(
            'photo_width' => Req::args('photo_width'),
            'photo_small_width' => Req::args('photo_small_width')
        );
        $this->config->set('photo', $photo);
        return true;
    }

    public function email() {
        $email = array(
            'email_sendtype' => Req::args('email_sendtype'),
            'email_host' => Req::args('email_host'),
            'email_ssl' => Req::args('email_ssl'),
            'email_port' => Req::args('email_port'),
            'email_account' => Req::args('email_account'),
            'email_password' => Req::args('email_password'),
            'email_sender_name' => Req::args('email_sender_name')
        );
        $this->config->set('email', $email);
        return true;
    }

    public function other() {
        $other_reg_way = Req::args('other_reg_way');
        $other_reg_way = is_array($other_reg_way) ? implode(',', $other_reg_way) : $other_reg_way;
        $other = array(
            'other_currency_symbol' => Req::args('other_currency_symbol'),
            'other_reg_way' => ($other_reg_way == null ? '0' : $other_reg_way),
            'other_currency_unit' => Req::args('other_currency_unit'),
            'other_is_invoice' => Req::args('other_is_invoice'),
            'other_tax' => Req::args('other_tax'),
            'other_grade_days' => Req::args('other_grade_days'),
            'other_order_delay' => Req::args('other_order_delay'),
            'other_order_delay_flash' => Req::args('other_order_delay_flash'),
            'other_order_delay_group' => Req::args('other_order_delay_group'),
            'other_order_delay_bund' => Req::args('other_order_delay_bund'),
            'other_order_delay_point' => Req::args('other_order_delay_point'),
            'other_order_delay_pointflash' => Req::args('other_order_delay_pointflash'),
            'other_verification_eamil' => Req::args('other_verification_eamil'),
            'rmb2huabi'=>Req::args('rmb2huabi'),
            'gold2silver'=>Req::args('gold2silver'),
            'withdraw_fee_rate'=>Req::args('withdraw_fee_rate'),
            'min_withdraw_amount'=>Req::args('min_withdraw_amount'),
            'available_distance'=>Req::args('available_distance'),
            'access_token'=>Req::args('access_token');
            'token_date'=>Req::args('token_date');
        );
        $this->config->set('other', $other);
        return true;
    }

    public function upyun() {
        $upyun = array(
            'upyun_cdnurl' => Req::args('upyun_cdnurl'),
            'upyun_bucket' => Req::args('upyun_bucket'),
            'upyun_save-key' => Req::args('upyun_save-key'),
            'upyun_formkey' => Req::args('upyun_formkey'),
            'upyun_expiration' => Req::args('upyun_expiration'),
            'upyun_uploadurl' => Req::args('upyun_uploadurl'),
            'upyun_notify-url' => Req::args('upyun_notify-url'),
        );
        $this->config->set('upyun', $upyun);
        return true;
    }

    public function safe() {
        $safe = array(
            'safe_reg_limit' => Req::args('safe_reg_limit'),
            'safe_reg_num' => Req::args('safe_reg_num'),
            'safe_comment_limit' => Req::args('safe_comment_limit'),
            'safe_comment_num' => Req::args('safe_comment_num'),
            'safe_album_limit' => Req::args('safe_album_limit'),
            'safe_album_num' => Req::args('safe_album_num'),
            'safe_click_count' => Req::args('safe_click_count')
        );
        $this->config->set('safe', $safe);
        return true;
    }
    public function commission_set(){
        $commission_set = array(
            'status'=>Req::args('status'),
            "commission_order_amount"=>Req::args("commission_order_amount"),
            'recharge_min'=>Req::args('recharge_min'),
            'withdraw_min'=>Req::args('withdraw_min'),
            'commission_rate2recharge'=>Req::args('commission_rate2recharge'),
           'level_1'=>Req::args('level_1'),
           'level_2'=>Req::args('level_2'), 
           'level_3'=>Req::args('level_3'), 
           'commission_locktime'=>Req::args('commission_locktime')
        );
        $this->config->set('commission_set', $commission_set);
        return true;
    }
     public function district_set(){
         $district_set = Req::args();
         unset($district_set['con']);
         unset($district_set['act']);
         unset($district_set['submit']);
        $this->config->set('district_set', $district_set);
        return true;
    }
    public function recharge_package_set(){
        $recharge_package_set=Req::args("package");
        $this->config->set('recharge_package_set', $recharge_package_set);
        return true;
    }
    public function sign_in_set(){
        $sign_in_set = array(
            'open'=>Req::args("open"),
            'type'=>Req::args("type"),
            'value'=>Req::args('value'),
            'max_sent'=>Req::args("max_sent"),
            'introduce'=>Req::args("introduce")
        );
        $this->config->set('sign_in_set', $sign_in_set);
        return true;
    }
    public function personal_shop_set(){
        $personal_shop_set = array(
            'open'=>Req::args("open"),
            "goods_name"=>Req::args("goods_name"),
            "goods_id"=>Req::args("goods_id")
        );
        $this->config->set("personal_shop_set",$personal_shop_set);
    }
}
