<?php

class WeixinService {

    private $config;
    public $wechat;

    public function __construct(&$config) {
        $this->config = &$config;
    }

    public static function appConfig() {
        return array(
            'signin' => array(
                'name' => '签到送积分',
                'config' => array(
                    array(
                        'type' => 'text',
                        'caption' => '商品ID',
                        'options' => 'fdsfdsfdsfds',
                    )
                )
            ),
            'pushgoods' => array(
                'name' => '推送商品',
                'config' => array(
                    array(
                        'type' => 'textarea',
                        'caption' => '商品ID',
                        'options' => 'fdsfdsfdsfds',
                    )
                )
            ),
            'article' => array(
                'name' => '关联文章',
                'config' => array(
                    array(
                        'type' => 'select',
                        'caption' => '文章ID',
                        'options' => 'aa:bb,cc:dd,ff:ee'
                    )
                )
            ),
            'help' => array(
                'name' => '在线客服',
                'config' => array(
                    array(
                        'type' => 'checkbox',
                        'caption' => '商品ID',
                        'options' => 'aa:bb,cc:dd,ff:ee'
                    )
                )
            ),
            'score' => array(
                'name' => '积分兑换',
                'config' => array(
                    array(
                        'type' => 'radio',
                        'caption' => '商品ID',
                        'options' => 'aa:bb,cc:dd,ff:ee'
                    )
                )
            ),
        );
    }

    public function searchGoods() {
        
    }

}
