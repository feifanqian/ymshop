<?php

return array(
    'appid' => '',
    'appsecret' => '',
    'token' => '',
    'menu' => array(
        'button' =>
        array(
            array(
                'name' => '扫码',
                'sub_button' =>
                array(
                    array(
                        'type' => 'scancode_waitmsg',
                        'name' => '扫码带提示',
                        'key' => 'rselfmenu_0_0',
                    ),
                    array(
                        'type' => 'scancode_push',
                        'name' => '扫码推事件',
                        'key' => 'rselfmenu_0_1',
                    ),
                ),
            ),
            array(
                'name' => '发图',
                'sub_button' =>
                array(
                    array(
                        'type' => 'pic_sysphoto',
                        'name' => '系统拍照发图',
                        'key' => 'rselfmenu_1_0',
                    ),
                    array(
                        'type' => 'pic_photo_or_album',
                        'name' => '拍照或者相册发图',
                        'key' => 'rselfmenu_1_1',
                    ),
                    array(
                        'type' => 'pic_weixin',
                        'name' => '微信相册发图',
                        'key' => 'rselfmenu_1_2',
                    ),
                ),
            ),
            array(
                'name' => '其他',
                'sub_button' =>
                array(
                    array(
                        'type' => 'location_select',
                        'name' => '发送位置',
                        'key' => 'rselfmenu_2_0',
                    ),
                    array(
                        'type' => 'click',
                        'name' => '今日歌曲',
                        'key' => 'V1001_TODAY_MUSIC',
                    ),
                    array(
                        'type' => 'view',
                        'name' => '搜索',
                        'url' => 'http://www.soso.com',
                    ),
                ),
            ),
        ),
    )
);
