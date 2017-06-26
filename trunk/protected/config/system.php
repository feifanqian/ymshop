<?php return array (
  'photo' => 
  array (
    'photo_width' => '13',
    'photo_small_width' => '13',
  ),
  'globals' => 
  array (
    'site_name' => '买一点商城',
    'site_logo' => '',
    'site_keywords' => '买一点商城',
    'site_description' => '买一点商城',
    'site_icp' => '粤ICP备16060168号',
    'site_url' => '',
    'site_androidurl' => 'http://www.baidu.com',
    'site_iosurl' => 'http://www.baidu.com/aa/',
    'site_addr' => '',
    'site_mobile' => '',
    'site_email' => '',
    'site_zip' => '',
    'site_phone' => '',
    'site_contactqq' => '3268242523|2177257693',
  ),
  'safe' => 
  array (
    'safe_reg_limit' => '0',
    'safe_reg_num' => '1',
    'safe_comment_limit' => '0',
    'safe_comment_num' => '2',
    'safe_album_limit' => '0',
    'safe_album_num' => '2',
    'safe_click_count' => '1',
  ),
  'email' => 
  array (
    'email_sendtype' => 'smtp',
    'email_host' => 'smtp.exmail.qq.com',
    'email_ssl' => '1',
    'email_port' => '465',
    'email_account' => 'no-reply@gamenew100.com',
    'email_password' => '3WbUXXju',
    'email_sender_name' => 'no-reply',
  ),
  'other' => 
  array (
    'other_currency_symbol' => '￥',
    'other_reg_way' => '0,1',
    'other_currency_unit' => '元',
    'other_is_invoice' => '1',
    'other_tax' => '6',
    'other_grade_days' => '365',
    'other_order_delay' => '0',
    'other_order_delay_flash' => '120',
    'other_order_delay_group' => '120',
    'other_order_delay_bund' => '0',
    'other_order_delay_point' => '0',
    'other_order_delay_pointflash' => '120',
    'other_verification_eamil' => NULL,
    'rmb2huabi' => '0.085',
    'gold2silver' => '1',
    'withdraw_fee_rate' => '3',
    'min_withdraw_amount' => '100',
  ),
  'wechat' => 
  array (
    'wechat_appid' => 'wxe0aa019de0eb8673',
    'wechat_appsecret' => '7cebd99cb8f75770b3907cd1f64e5181',
    'wechat_token' => 'SycW1uJoICT6BWqzCDd9Kgq8HuzZJI6q',
    'wechat_openid' => 'oI0X5weP-bF4x4TYlhpWXLWa3oFI',
    'wechat_menu' => 
    array (
      'button' => 
      array (
        0 => 
        array (
          'name' => '扫码',
          'sub_button' => 
          array (
            0 => 
            array (
              'type' => 'scancode_waitmsg',
              'name' => '扫码带提示',
              'key' => 'rselfmenu_0_0',
            ),
            1 => 
            array (
              'type' => 'scancode_push',
              'name' => '扫码推事件',
              'key' => 'rselfmenu_0_1',
            ),
          ),
        ),
        1 => 
        array (
          'name' => '发图',
          'sub_button' => 
          array (
            0 => 
            array (
              'type' => 'pic_sysphoto',
              'name' => '系统拍照发图',
              'key' => 'rselfmenu_1_0',
            ),
            1 => 
            array (
              'type' => 'pic_photo_or_album',
              'name' => '拍照或者相册发图',
              'key' => 'rselfmenu_1_1',
            ),
            2 => 
            array (
              'type' => 'pic_weixin',
              'name' => '微信相册发图',
              'key' => 'rselfmenu_1_2',
            ),
          ),
        ),
        2 => 
        array (
          'name' => '其他',
          'sub_button' => 
          array (
            0 => 
            array (
              'type' => 'location_select',
              'name' => '发送位置',
              'key' => 'rselfmenu_2_0',
            ),
            1 => 
            array (
              'type' => 'click',
              'name' => '今日歌曲',
              'key' => 'V1001_TODAY_MUSIC',
            ),
            2 => 
            array (
              'type' => 'view',
              'name' => '搜索',
              'url' => 'http://www.soso.com',
            ),
          ),
        ),
      ),
    ),
  ),
  'upyun' => 
  array (
    'upyun_cdnurl' => 'https://buy-d.b0.upaiyun.com',
    'upyun_bucket' => 'buy-d',
    'upyun_save-key' => '/data/uploads/{year}/{mon}/{day}/{filemd5}{.suffix}',
    'upyun_formkey' => '4mWM42NxdLLDdF6+aSA4oHPYLJQ=',
    'upyun_expiration' => '86400',
    'upyun_uploadurl' => 'http://v1.api.upyun.com/buy-d',
    'upyun_notify-url' => 'http://shop.gamenew100.com/ajax/upyun',
  ),
  'commission_set' => 
  array (
    'status' => '1',
    'commission_order_amount' => '500',
    'recharge_min' => '100',
    'withdraw_min' => '100',
    'commission_rate2recharge' => '15',
    'level_1' => '2',
    'level_2' => '3',
    'level_3' => '5',
    'commission_locktime' => '5',
  ),
  'district_set' => 
  array (
    'join_fee' => '180000',
    'promoter_fee' => '3600',
    'min_withdraw_amount' => '1',
    'promoter_join_line' => '500',
    'join_send_gift' => '159|161|1079|1080',
    'join_send_point' => '3600',
    'promoter_invite_promoter_money' => '1000',
    'promoter_invite_promoter_point' => '1000',
    'shop_invite_promoter_money' => '2600',
    'shop_invite_indirect_money' => '1600',
    'withdraw_fee_rate' => '0.5',
    'percentage2join_fee' => '10',
    'level_line_1' => '50000',
    'percentage_lv1' => '10',
    'level_line_2' => '100000',
    'percentage_lv2' => '12',
    'percentage_lv3' => '15',
    'percentage2hirer' => '1',
    'percentage2inviter' => '3',
    'invite_promoter_num' => '1',
    'auto_pass' => '1',
    'income_lockdays' => '30',
  ),
  'recharge_package_set' => 
  array (
    1 => 
    array (
      'point' => '600',
      'withdraw_time' => '1',
      'gift' => '1068|1058|1080',
      'gift_num' => '1',
    ),
    2 => 
    array (
      'point' => '3600',
      'withdraw_time' => '6',
      'gift' => '1074|239|161|240|241|242|1075|162|1073|192|163',
      'gift_num' => '1',
    ),
    3 => 
    array (
      'point' => '10800',
      'withdraw_time' => '18',
      'gift' => '1074|239|161|240|241|242|1075|162|1073|192|163',
      'gift_num' => '1',
    ),
    4 => 
    array (
      'point' => '18000',
      'withdraw_time' => '30',
      'gift' => '1074|239|161|240|241|242|1075|162|1073|192|163',
      'gift_num' => '1',
    ),
  ),
);