
<?php

/**
 * 手机版
 */
class V1Controller extends Controller {

    public $layout = '';
    public $model = null;
    public $user = null;
    public $code = 1000;
    public $content = NULL;
    public $message = "提示信息";
    public $actions = array(
        //'接口名'=>array("对应控制器方法",是否需要用户验证,是否正常)
        'signup' => array('ucenter/app_signup', 0,1), //注册接口
        'login' => array('ucenter/login', 0,1), //登陆接口
        'userinfo' => array('ucenter/info', 1,1), //用户信息接口
        'thirdlogin' => array('ucenter/thirdlogin', 0,1), //第三方登录
        'thirdbind' => array('ucenter/thirdbind', 0,1), //第三方登录绑定
        'send_code' => array('ucenter/send_code', 0,1),
        'send_sms' => array('ucenter/send_sms', 0,1),
        'index' => array('index/index', 0,1), //获取主页接口
        'index_category' => array('index/index_category', 0,1), //获取主页接口
        'index_area'=>array('index/index_area',0,1), //首页抢购积分专区
        'category_nav' => array('index/category_nav', 0,1), //新首页导航接口
        'index_goods' => array('index/index_goods', 0,1),
        'product' => array('product/info', 0,1), //获取产品信息接口
        'category' => array('product/category', 0,1),
        'search' => array('product/category', 0,1), //搜索接口    
        'flash' => array('product/flash', 0,1), //秒杀专区接口
        "announcement"=>array("index/announcement",0,1), //公告接口
        // 'flash1' => array('product/flash1', 0,1), //秒杀专区接口添加积分抢购
        'weishang' => array('product/wei',0,1), //微商专区接口
        'group' => array('product/group', 0,1),
        'get_review' => array('product/get_review', 0,1),//取得评价
        'get_ask' => array('product/get_ask', 0,1),//取得咨询
        'flashbuy' => array('product/flashbuy', 0,1),//商品秒杀接口
        'groupbuy' => array('product/groupbuy', 0,1),//商品团购接口
        'pointflash'=> array('product/pointflash', 0,1),//商品积分秒杀接口
        'cart_add' => array('cart/add', 0,1), //添加商品到购物车接口
        'cart_del' => array('cart/del', 0,1), //移除购物车接口
        'cart_num' => array('cart/num', 0,1),//购买商品数量接口
        'order_confirm' => array('order/confirm', 1,1), //确认订单
        'order_info' => array('order/info', 1,1),
        'order_submit' => array('order/submit', 1,1),
        'order_calculate_fare' => array('order/calculate_fare', 0,1),
        'order_express' => array('order/express', 0,1),
        "order_send" => array("order/order_send",1,1), //商家订单发货
        "express_company_list" => array("order/express_company_list",0,1), //快递公司列表
        'address_list' => array('address/lists', 1,1),
        'address_info' => array('address/info', 1,1),
        'address_del' => array('address/del', 1,1),
        'address_save' => array('address/save', 1,1),
        'goods_add' => array('goods/add', 1,1), //直接购买
        'rongyun_token'=> array('ucenter/rongyun_token',1,1), // 获取融云token
        'caculate_fare'=>array('address/caculateFare',0,1), //计算运费
        'version_update'=>array('index/version_update',0,1), //ios版本更新提醒
        "mobile_exist"=>array('ucenter/mobile_exist',0,1),
        "make_shop_qrcode_no"=>array("goods/make_shop_qrcode_no",0,1),
        "get_all_child_shop_id"=>array("district/getAllChildShopId",0,1),
        "get_all_child_promoters_ids"=>array("district/getAllChildPromotersIds",0,1),
        "invite_bg_banner"=>array("index/invite_bg_banner",0,1),
        "invite_bg_banners"=>array("index/invite_bg_banners",0,1),
        "get_my_districters"=>array("district/get_my_districters",0,1),
        //TODO tian
        "order" => array("ucenter/order", 1,1), //查询订单
        "offline_order" => array("order/offlineorder_list",1,1), //线下订单
        "order_detail" => array("ucenter/order_detail", 1,1), //查询单个订单的详情 
        "order_sign" => array("ucenter/order_sign", 1,1), //签收订单
        "order_express_detail" => array("ucenter/order_express_detail", 1,1), //查询订单分包
        "shop_order_list" => array("order/shop_order_list",1,1), //商家订单列表
        "my_review" => array("ucenter/my_review", 1,1), //我的评论
        "post_review" => array("ucenter/post_review", 1,1), //发表评论
        "get_message" => array("ucenter/get_message", 1,1), //消息接口
        "push_message" => array("ucenter/push_message", 1,1), //推送消息接口
        "del_message" => array("ucenter/del_message", 1,1), //删除消息
        "read_message" => array("ucenter/read_message", 1,1), //将消息标记为已读
        "sign_all_message" => array("ucenter/sign_all_message", 1,1),
        "save_info" => array("ucenter/save_info", 1,1), //用户信息保存修改接口
        "huabi" => array("ucenter/huabi", 1,1), //华点信息接口
        "balance_log" => array("ucenter/balance_log", 1,1), //余额的使用记录
        "offline_balance_log" => array("ucenter/offlinebalance_log", 1,1), //商家余额的使用记录
        "dopay" => array("payment/dopay", 1,1), //发起支付
        "dopays" => array("payment/dopays", 1,1), //发起线下扫码支付
        "dinpay" => array("payment/dinpay", 1,1), //智付支付
        "jpush_test"=> array("payment/jpushTest",1,1), //极光消息推送测试
        "pay_qrcode" => array("payment/pay_qrcode",1,1), //收款二维码
        "seller_name" => array("payment/seller_name",1,1), //线下扫码卖家信息
        "pay_success" => array("payment/pay_success",1,1), //线下扫码支付成功页面
        "pay_balance" => array("payment/pay_balance", 1,1), //通过余额支付
        "paytype_list" => array("payment/paytype_list", 1,1), //支付方式
        "set_avatar" => array("ucenter/set_avatar", 1,1), //设置头像
        "set_nickname" => array("ucenter/set_nickname", 1,1), //设置昵称
        "verify" => array("ucenter/verify", 1,1), // 验证身份
        "update_obj" => array("ucenter/update_obj", 1,1), //修改目标属性
        'andorid_logout' => array('ucenter/logout', 1,1), //安卓端登出
        "check_verify" => array('ucenter/check_verify', 1,1), //查询用户的验证信息
        "reset_loginpwd" => array('ucenter/reset_loginpwd', 1,1), //使用旧密码更新密码
        "add_collect" => array('ucenter/add_collect', 1,1), //添加收藏
        "get_collect" => array('ucenter/get_collect', 1,1), //获得收藏
        "del_collect" => array('ucenter/del_collect', 1,1), //删除收藏
        "is_collected" => array('ucenter/is_collected', 1,1), //判断是否收藏过某个商品
        "forget_loginpwd" => array('ucenter/forget_loginpwd', 0,1), //忘记密码，通过手机验证找回
        "app_signup" => array('simple/register_act', 0,1), //通过手机注册
        "bp_banner" => array('index/bp_banner', 0,1),
        "category_ad" => array('index/category_ad', 0,1), //获取分类广告
        "get_category" => array('index/get_category', 0,1),
        'get_hot' => array('product/get_hot', 0,1),
        "guess" => array("product/guess", 0,1),
        "get_index_ad" => array("index/get_index_ad", 0,1),
        "tbk_index_banner" => array("goods/tbk_index_banner", 0,1),
        "tbk_dg_material_optional"=>array("goods/tbk_dg_material_optional",0,1),
        'want' => array('product/want', 1,1),
        "sale_support" => array('ucenter/sale_support', 1,1),
        "support_info" => array('ucenter/support_info', 1,1),
        "badge" => array("ucenter/badge", 1,1),
        "about" => array("ucenter/about", 0,1),
        'next_flash' => array('product/next_flash', 0,1),
        "complaint" => array("ucenter/complaint", 1,1),
        "update_paypwd" => array('ucenter/updatePayPassword', 1,1),
        "reset_paypwd" => array('ucenter/resetPayPasswordByMobile', 1,1),
        "get_upyun" => array('ucenter/getUpyun', 1,1),
        "huabipay_info" => array("payment/huabipay_info", 1,1), //去掉
        "huabipay" => array("payment/huabi_pay", 1,1), //去掉
        "my_commission" => array("ucenter/myCommission", 1,1),
        "commission_log" => array("ucenter/commissionLog", 1,1),
        "commission_withdraw" => array("ucenter/commissionWithdraw", 1,1),
        "withdraw_history" => array("ucenter/withdrawHistory", 1,1),
        "my_invite" => array("ucenter/myInvite", 1,1),
        "my_invite_url" => array("ucenter/myInviteQRCodeUrl", 1,1),
        "huabipage_goods" => array("product/huabiPageGoods", 0,1), //去掉
        "huabipage_recommend" => array("product/huabiPageRecommend", 0,1), //去掉
        "get_express" => array("ucenter/getExpress", 1,1),
        'order_delete' => array("ucenter/order_delete", 1,1),
        'refund_apply_info' => array('ucenter/refund_apply_info', 1,1),
        'refund_apply_submit' => array('ucenter/refund_apply_submit', 1,1),
        'refund_progress' => array('ucenter/refund_progress', 1,1),
        'silver_coin_log' => array("ucenter/silver_coin_log", 1,1), //银点记录接口 去掉
        'gold_to_silver' => array("ucenter/gold_to_silver", 1,1), //金点兑换银点接口 去掉
        'get_recharge_package_gift' => array("ucenter/get_recharge_package_gift", 0,1), //获取套餐充值礼品 //去掉
        'balance_withdraw' => array("ucenter/balance_withdraw", 1,1),
        'offline_balance_withdraw' => array("ucenter/offline_balance_withdraw", 1,1),
        "name_verified" =>array("ucenter/name_verified",1,1),
        "get_rongyun_token"=>array("ucenter/get_rongyun_token",1,1),
        "pay_pwd_valid"=>array("ucenter/payPwdValid",1,1),
        //淘宝客
        "tbk_item_get"=>array("goods/tbk_item_get",0,1),
        "tbk_item_coupon_get"=>array("goods/tbk_item_coupon_get2",0,1),
        "tbk_item_coupon_gets"=>array("goods/tbk_item_coupon_gets",0,1),
        "taobao_item_detail_get"=>array("goods/taobao_item_detail_get",0,1),
        "tbk_cat_nav"=>array("goods/tbk_cat_nav",0,1),
        "tbk_item_guess_like"=>array("goods/tbk_item_guess_like",0,1),
        "tbk_tpwd_create"=>array("goods/tbk_tpwd_create",0,1),
        "tbk_get_height_url"=>array("goods/tbk_get_height_url",1,1),
        "taobao_tpwd_share"=>array("goods/taobao_tpwd_share",0,1),
        //小区相关接口
        'apply_for_district' => array("district/applyForDistrict", 1,1), //申请小区
        'get_district_list' => array("district/getDistrictList", 1,1), //获取小区列表
        'get_district_info' => array("district/getDistrictInfo", 1,1), //获取小区详情
        'get_district_withdraw_record' => array("district/getDistrictWithdrawRecord", 1,1), //获取小区提现记录
        'get_district_income_record' => array("district/getDistrictIncomeRecord", 1,1), //获取小区收益记录
        'get_district_sale_record' => array("district/getDistrictSaleRecorde", 1,1), //获取小区销售记录
        'apply_do_settle' => array("district/applyDoSettle", 1,1), //申请提现
        'pay_district' => array("payment/pay_district", 1,1), //小区加盟费支付
        // 'get_promoter_list' => array("district/getPromoterList", 1,1), //获取推广员列表
        'get_promoter_list' => array("ucenter/getPromoterList", 1,1), //获取推广员列表
        'get_subordinate' => array("ucenter/getSubordinate", 1,1), //获取我的拓展小区
        'district_achievement' => array("district/districtAchievement", 1,1), //获取小区业绩数据
        //推广员相关
        'isDistrictPromoter' => array("ucenter/isDistrictPromoter", 1,1),//判断是否是小区推广员
        'get_district_info_by_id' => array("ucenter/getDistrictInfoById", 1,1),//获取小区信息
        'become_promoter' => array("ucenter/becomepromoter", 1,1),//成为小区推广员
        'get_promoter_income_static' => array("ucenter/getPromoterIncomeStatic", 1,1),//获取推广员收益统计
        'get_promoter_sale_record' => array("ucenter/getPromoterSaleRecord", 1,1),//获取推广员销售记录
        'get_promoter_income_record'=> array("ucenter/getPrmoterIncomeRecord",1,1),//获取推广员收益记录
        'get_promoter_settled_record' => array("ucenter/getPromoterSettledRecord", 1,1),//获取推广员提现记录
        'promoter_do_settle' => array("ucenter/promoterDoSettle", 1,1),//申请提现
        'get_qrcode_flag_by_goods_id' => array("ucenter/getQrcodeFlagByGoodsId", 1,1),
        'get_my_balance_withdraw_record'=>array("ucenter/getMyGoldWithdrawRecord",1,1), //获取我的余额提现记录
        'get_my_invite_promoter'=>array("ucenter/getMyInvitePromoter",1,1),//获取我邀请的推广员列表
        "point_sale"=>array("product/point_sale",0,1),//获取积分购列表
        "pointbuy"=>array("product/pointbuy",0,1),//获取积分购列表
        "make_promoter_code"=>array("district/makePromoterCode",1,1), //生成激活码
        "promoter_code_list"=>array("district/promoterCodeList",1,1), //激活码列表
        "input_code"=>array("district/inputCode",1,1), //激活码输入
        "send_active_code"=>array("district/sendActiveCode",1,1), //经销商互赠激活码数量
        "code_log"=>array("district/code_log",1,1), //激活码使用记录
        "build_inviteship_qrcode"=>array("ucenter/build_inviteship_qrcode",1,1), //app扫个人邀请码
        "build_inviteship_goods_qrcode"=>array("goods/build_inviteship_goods_qrcode",1,1), //app扫商品邀请码
        "get_my_inviter"=>array("ucenter/get_my_inviter",1,1), //获取上级邀请人信息
        //提现
        "get_withdraw_set"=>array("index/getWithdrawSet",0,1),//提现设置
        "get_merchant_balance"=>array("ucenter/getMerchantBalance",1,1),//商家余额提现
        "offline_balance_withdraw"=>array("ucenter/offlineBalanceWithdraw",1,1), //商家余额提现到银行卡
        "bankcard_list"=>array("ucenter/bankcardList",1,1), //银行卡列表
        "bind_card_temp"=>array("ucenter/bindCardTemp",1,1), //绑定银行卡(临时)
        "unbind_card_temp"=>array("ucenter/unbindCardTemp",1,1), //解绑银行卡(临时)
        "name_verified_temp"=>array("ucenter/nameVerifiedTemp",1,1), //二要素实名认证(临时)
        "shop_check"=>array("ucenter/shop_check",1,1), // 商家信息认证申请
        "new_shop_check"=>array("ucenter/new_shop_check",1,1), // 新商家信息认证申请
        "shop_checked"=>array("ucenter/shop_checked",1,1), // 判断商家是否认证过
        "shop_check_info"=>array("ucenter/shop_check_info",1,1), //商家认证信息
        "industry_list"=>array("goods/industry_list",0,1), //行业列表

        "pointcoin_log"=>array("ucenter/pointcoin_log",1,1),
        //充值套餐详情
        "package_info"=>array("index/package_info",0,1),
        
        //圆梦new
        "recharge_package_set"=>array("index/recharge_package_set",0,1),//套餐设置
        "sign_in"=>array("ucenter/sign_in",1,1),
        "get_sign_in_data_by_ym"=>array("ucenter/getSignInDataByYm",1,1),
        "get_my_sign_info"=>array("ucenter/get_my_sign_info",1,1),
        "personal_shop_list"=>array("personalShop/shopList",0,1),
        "shop_index_goods"=>array("personalShop/shopIndexGoods",0,1),
        "shop_goods_list_by_time"=>array("personalShop/shopGoodsListByTime",0,1),
        //红包
        "redbag_list"=>array("address/redbagList",0,1),
        "my_redbag"=>array("address/myRedbag",1,1), //我的红包
        "redbag_make"=>array("address/redbagMake",1,1), //发红包(商家自定义广告红包)
        "redbag_open"=>array("address/redbagOpen",1,1), //抢红包
        "redbag_had_opened"=>array("address/redbagHadOpened",1,1), //判断该红包是否抢过
        //地图
        "get_maps"=>array("map/getMaps",0,1),//地图功能
        "get_map" => array("address/getMap", 0, 1),//附近商家
        "get_area" => array("address/getArea", 0, 1),//按区域查找商家
        "get_subway"=>array("address/getSubway",0,1),//按地铁线查找
        "business_member"=>array("address/businessMember",1,1),//商家or会员
        "get_area_by_city"=>array("address/getAreaByCity",0,1), //通过城市查找地区
        "get_lnglat"=>array('address/getLnglat',0,1), //通过地理位置查询经纬度
        //商家
        "seller_list"=>array("address/promoterList",0,1),
        "seller_info"=>array("address/promoterInfo",0,1),
        "promoter_edit"=>array('address/promoterEdit',1,1),
        "promoter_type"=>array('map/promoterType',0,1), //商家类型
        "promoter_attention"=>array("address/promoterAttention",1,1), //关注商家
        "has_attentioned"=>array('address/hasAttentioned',1,1), //判断商家是否已关注
        "set_picture"=>array("district/setPicture",1,1), //设置商家店铺头像
        "my_promoter_detail"=>array("address/myPromoterDetail",1,1), //我的商家信息详情
        "shop_register"=>array("ucenter/shop_register",1,1),
        "yin_df_test"=>array("ucenter/yin_df_test",1,1),
        "yin_fz_test"=>array("ucenter/yin_fz_test",1,1),
        "get_all_child_promoters"=>array('address/get_all_child_promoters',0,1),
        //收银员
        "add_cashier"=>array("cashier/add_cashier",1,1), //邀请
        "cashier_operate"=>array("cashier/cashier_operate",1,1), //接受或拒绝
        "cashier_list"=>array("cashier/cashier_list",1,1), //收银员列表
        "cashier_detail"=>array("cashier/cashier_detail",1,1), //收银员收银明细
        "add_cashier_desk"=>array("cashier/add_cashier_desk",1,1), //添加收银台
        "cashier_desk_list"=>array("cashier/cashier_desk_list",1,1), //收银台列表
        "cashier_desk_income"=>array("cashier/cashier_desk_income",1,1), //收银台收易明细
        "cashier_edit_name"=>array("cashier/cashier_edit_name",1,1), //商家修改收银员昵称
        "cashier_sign_in"=>array("cashier/cashier_sign_in",1,1), //收银员上下班打卡
        "cashier_desk_sign_list"=>array("cashier/cashier_desk_sign_list",1,1), //收银员打卡时选择的收银台列表
        "cashier_work_log"=>array("cashier/cashier_work_log",1,1), //收银员上班记录
        "cashier_income_remark"=>array("cashier/cashier_income_remark",1,1), //收银台收款记录添加备注
        "cashier_qrcode_url"=>array("cashier/cashier_qrcode_url",1,1), //收银员收款二维码扫码跳转地址
        "cashier_ready_sign"=>array("cashier/cashier_ready_sign",1,1), //查看收银员打卡状态
        "cashier_my_income_log"=>array("cashier/cashier_my_income_log",1,1), // 收银员我的收款记录
        "cashier_manage"=>array("cashier/cashier_manage",0,1), //商家启用或删除收银员
        "cashier_my_info"=>array('cashier/cashier_my_info',1,1), //收银员详情
        "cashier_off_duty"=>array('cashier/cashier_off_duty',1,1), //商家操作收银员下班
        "cashier_ship_remove"=>array("cashier/cashier_ship_remove",1,1), //收银员主动解除绑定关系
        "cashier_desk_del"=>array("cashier/cashier_desk_del",1,1), //删除收银台
        "cashier_desk_edit"=>array("cashier/cashier_desk_edit",1,1), //修改收银台名字
        //拉新活动
        "voucher_list"=>array('cashier/voucher_list',1,1), //卡券列表
        "voucher_detail"=>array('cashier/voucher_detail',0,1), //卡券详细
        "voucher_address"=>array('cashier/voucher_address',1,1), //卡券添加收货地址
        "voucher_user"=>array('cashier/voucher_user',1,1), //卡券使用
        //商家上传商品
        "upload_goods"=>array('goods/upload_goods',1,1), //商家上传商品
        "get_all_category"=>array('goods/get_all_category',0,1), //所有分类
        "fare_list"=>array('goods/fare_list',0,1), //运费模板
        "my_goods_list"=>array('goods/my_goods_list',1,1), //商品列表
        "manage_my_goods"=>array('goods/manage_my_goods',1,1), //商品上下架、删除
        "goods_detail"=>array('goods/goods_detail',0,1), //商品详情
        "test"=>array('test/test',0,1),
        "test2"=>array('test/test2',0,1),
        //收益
        "my_benefit_income"=>array("cashier/my_benefit_income",1,1), // 收益
        "my_order_list"=>array("cashier/my_order_list",1,1), // 订单明细
        "income_withdraw_balance"=>array("cashier/income_withdraw_balance",1,1), //结算佣金提现到余额
        "my_withdraw_log"=>array("cashier/my_withdraw_log",1,1), //提现记录
        //拼团
        "groupbuy_list"=>array("groupbuy/groupbuy_list",0,1), //团购专区商品列表
        "groupbuy_join"=>array("groupbuy/groupbuy_join",1,1), //开团
        "groupbuy_detail"=>array("groupbuy/groupbuy_detail",0,1), //拼团详细页面
        "groupbuy_join_detail"=>array("groupbuy/groupbuy_join_detail",1,1), //拼团详情
        "my_groupbuy_active"=>array("groupbuy/myGroupbuyActive",1,1), //我的活动
        "image_merge_test"=>array("map/image_merge_test",1,1), //图片合成测试
        "image_merge_tests"=>array("map/image_merge_tests",1,1), //图片合成测试
        "save_contract_image"=>array("map/save_contract_image",1,1), //图片保存
        //商圈
        "business_center_list"=>array("map/business_center_list",0,1), //商圈列表
        "publish_dynamic"=>array("map/publish_dynamic",1,1), //发布动态
        "recommend_goods_list"=>array("map/recommend_goods_list",1,1), //推荐商品列表
        "dynamic_report"=>array("map/dynamic_report",1,1), //动态举报
        "center_dynamic_list"=>array("map/center_dynamic_list",1,1), //商圈动态列表
        "center_dynamic_detail"=>array("map/center_dynamic_detail",1,1), //商圈动态详情
        "dynamic_click_laud"=>array("map/dynamic_click_laud",1,1), //商圈动态点赞
        "dynamic_comment"=>array("map/dynamic_comment",1,1), //商圈动态发布评论
        "area_list"=>array("map/area_list",0,1), //地区列表
        //通联支付接口
        "createMember"=>array('paytonglian/actionCreateMember',1,1), //创建会员接口
        "set_company_info"=>array('paytonglian/actionSetCompanyInfo',1,1),//设置企业会员信息
        "get_member_info"=>array('paytonglian/actionGetMemberInfo',1,1),//获取个人(企业)会员信息
        "set_member_info"=>array('paytonglian/actionSetMemberInfo',1,1),//设置个人会员信息
        "send_verification_code"=>array('paytonglian/actionSendVerificationCode',1,1), //发送短信验证码接口
        "check_verification_code"=>array('paytonglian/actionCheckVerificationCode',1,1),//验证短信验证码接口
        "bind_phone"=>array('paytonglian/actionBindPhone',1,1),//绑定手机接口
        "change_bind_phone"=>array('paytonglian/actionChangeBindPhone',1,1),//更改绑定手机
        "set_realname"=>array('paytonglian/actionSetRealName',1,1),//实名认证接口
        "apply_bind_bankcard"=>array('paytonglian/actionApplyBindBankCard',1,1),//请求绑定银行卡接口
        "get_bankcardbin"=>array('paytonglian/actionGetBankCardBin',1,1),//查询银行卡bin
        "bind_bankcard"=>array('paytonglian/actionBindBankCard',1,1),//确认 绑定银行卡信息，四要素+短信验证时，才需要调用这个接口
        "set_safecard"=>array('paytonglian/actionSetSafeCard',1,1),//设置安全卡
        "query_bankcard"=>array('paytonglian/actionQueryBankCard',1,1),//查询银行卡
        "unbind_bankcard"=>array('paytonglian/actionUnbindBankCard',1,1),//解除绑定银行卡
        "deposit_apply"=>array('paytonglian/actionDepositApply',1,1),//充值申请
        "withdraw_apply"=>array('paytonglian/actionWithdrawApply',1,1),//提现申请
        "consume_apply"=>array('paytonglian/actionConsumeApply',1,1),//消费申请
        "agent_collect_apply"=>array('paytonglian/actionAgentCollectApply',1,1),//托管待收申请
        "signal_agent_pay"=>array('paytonglian/actionSignalAgentPay',1,1),//单笔代付
        "batch_agent_pay"=>array('paytonglian/actionBatchAgentPay',1,1),//批量托管代付
        "action_pay"=>array('paytonglian/actionPay',1,1),//确认支付
        "action_entry_goods"=>array('paytonglian/actionEntryGoods',1,1),//商品录入
        "action_query_modify_goods"=>array('paytonglian/actionQueryModifyGoods',1,1),//查询、修改商品
        "action_freeze_money"=>array('paytonglian/actionFreezeMoney',1,1),//冻结金额
        "action_unfreeze_money"=>array('paytonglian/actionUnfreezeMoney',1,1),//解冻金额
        "action_refund"=>array('paytonglian/actionRefund',1,1),//退款申请
        "action_failure_bid_refund"=>array('paytonglian/actionFailureBidRefund',1,1),//流标专用退款
        "realNameVerify"=>array("paytonglian/realNameVerify",1,1), //创建会员、实名认证的合并
        "application_transfer"=>array('paytonglian/actionApplicationTransfer',1,1),//平台转账接口
        "query_balance"=>array('paytonglian/actionQueryBalance',1,1),//查询余额接口
        "get_order_detail"=>array('paytonglian/actionGetOrderDetail',1,1),//查询订单状态接口
        "Query_inexp_detail"=>array('paytonglian/actionQueryInExpDetail',1,1),//查询账户明细
        "tl_search"=>array('paytonglian/actionTlSearch',1,1),//通联通头寸查询
        "platform_download"=>array('paytonglian/platformDownload',1,1),//平台集合对账下载
        "lock_member"=>array('paytonglian/actionLockMember',1,1),//锁定会员
        "unlock_member"=>array('paytonglian/actionUnlockMember',1,1),//解锁会员
    );

    //分析请求的action,将请求分发到不同的action中去
    public function __call($name, $args = null) {
        if (isset($this->actions[$name])) {
            $arr = explode('/', $this->actions[$name][0]);
            $className = ucfirst($arr[0]) . "Action";
            try {
                if($this->actions[$name][2]==0){
                    $this->code = 1150;
                }else{
                    $action = new $className();
                    $action->user = &$this->user;
                    $action->$arr[1]();
                    $this->code = $action->code;
                    $this->content = $action->content;
                }
            } catch (Exception $e) {
                $this->code = 1005;
                $this->message = $e->getMessage();
            }
        } else {
            $this->code = 1004;
        }
    }

    //初始化
    public function init() {
        header("Content-type: application/json; charset=" . $this->encoding);
        $this->model = new Model();
        $code = Req::args('code');
        $sign = Req::args('sign');
        //验证签名
        if (md5(md5($code) . '123456') != $sign && FALSE) {
            $this->code = 1001;
            exit;
        }
        //将JSON数据合并到POST中去
        $code = json_decode($code, TRUE);
        if ($code && is_array($code)) {
            $_POST = array_merge($_POST, $code);
        }
    }

    //启动,版本检测,服务器时间,公告列表
    public function bootstrap() {
        $version = Req::args('version');
        $platform = Req::args('platform');
        if ($version != "" && $platform != "") {
            $ver = new Version($platform);
            $versiondata = $ver->check($version);
        } else {
            $versiondata = NULL;
        }
        $upyun = Config::getInstance()->get("upyun");
        $this->code = 0;
        $this->content = array(
            'cdnurl' => "/",
            'servertime' => time(),
            'noticelist' => array(
            ),
            'session_id' => session_id(),
            'versiondata' => $versiondata,
            'img_host' => $upyun['upyun_cdnurl'],
            'uploadurl' => $upyun['upyun_uploadurl'],
        );
    }

    //检查权限
    //重写了父类的权限验证
    public function checkRight($name) {
        if (isset($this->actions[$name]) && isset($this->actions[$name][1]) && $this->actions[$name][1]) {
            $token = Req::args('token');
            $user_id = Filter::int(Req::args('user_id'));
            if ($user_id && $token) {
                $userinfo = $this->model->table("user")->where("id='{$user_id}'")->find();
                $time = time();
                if ($userinfo && $userinfo['token'] == $token && strtotime($userinfo['expire_time']) > $time) {
                    $model = new Model("user as us");
                    $this->user = $model->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.group_id,cu.user_id,cu.login_time,cu.mobile")->where("us.id='{$userinfo['id']}'")->find();
                    return true;
                } else {
                    $this->code = 1003;
                    return false;
                }
            } else {
                $this->code = 1000;

                return false;
            }
        } else {
            $this->code = 1004;
            return true;
        }
    }

    //当无权限时的操作
    public function noRight() {
        if (!$this->code) {
            $this->code = 1003;
        }
    }

    //析构函数调用时,返回json

    public function __destruct() {
        $message = $this->message;
        $obj = $this->model->table("code")->where("code='{$this->code}'")->find();
        $message = $obj && isset($obj['message']) ? $obj['message'] : $this->message;
        echo json_encode(array('code' => $this->code, 'content' => $this->content, 'message' => $message, 'during'=> microtime(true) - START_TIME));
    }

}
