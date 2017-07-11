
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
        'signup' => array('ucenter/signup', 0,1), //注册接口
        'login' => array('ucenter/login', 0,1), //登陆接口
        'userinfo' => array('ucenter/info', 1,1), //用户信息接口
        'thirdlogin' => array('ucenter/thirdlogin', 0,1), //第三方登录
        'thirdbind' => array('ucenter/thirdbind', 0,1), //第三方登录绑定
        'send_code' => array('ucenter/send_code', 0,1),
        'send_sms' => array('ucenter/send_sms', 0,1),
        'index' => array('index/index', 0,1), //获取主页接口
        'index_goods' => array('index/index_goods', 0,1),
        'product' => array('product/info', 0,1), //获取产品信息接口
        'category' => array('product/category', 0,1),
        'search' => array('product/category', 0,1), //搜索接口    
        'flash' => array('product/flash', 0,1),
        'group' => array('product/group', 0,1),
        'get_review' => array('product/get_review', 0,1),
        'get_ask' => array('product/get_ask', 0,1),
        'flashbuy' => array('product/flashbuy', 0,1),
        'groupbuy' => array('product/groupbuy', 0,1),
        'cart_add' => array('cart/add', 0,1),
        'cart_del' => array('cart/del', 0,1),
        'cart_num' => array('cart/num', 0,1),
        'order_confirm' => array('order/confirm', 1,1), //确认订单
        'order_info' => array('order/info', 1,1),
        'order_submit' => array('order/submit', 1,1),
        'order_calculate_fare' => array('order/calculate_fare', 0,1),
        'order_express' => array('order/express', 0,1),
        'address_list' => array('address/lists', 1,1),
        'address_info' => array('address/info', 1,1),
        'address_del' => array('address/del', 1,1),
        'address_save' => array('address/save', 1,1),
        'goods_add' => array('goods/add', 1,1), //直接购买
        //TODO tian
        "order" => array("ucenter/order", 1,1), //查询订单
        "order_detail" => array("ucenter/order_detail", 1,1), //查询单个订单的详情 
        "order_sign" => array("ucenter/order_sign", 1,1), //签收订单
        "order_express_detail" => array("ucenter/order_express_detail", 1,1), //查询订单分包
        "my_review" => array("ucenter/my_review", 1,1), //我的评论
        "post_review" => array("ucenter/post_review", 1,1), //发表评论
        "get_message" => array("ucenter/get_message", 1,1), //消息接口
        "del_message" => array("ucenter/del_message", 1,1), //删除消息
        "read_message" => array("ucenter/read_message", 1,1), //讲消息标记为已读
        "sign_all_message" => array("ucenter/sign_all_message", 1,1),
        "save_info" => array("ucenter/save_info", 1,1), //用户信息保存修改接口
        "huabi" => array("ucenter/huabi", 1,1), //华点信息接口
        "huabi_log" => array("ucenter/balance_log", 1,1), //华点的使用记录
        "dopay" => array("payment/dopay", 1,1), //发起支付
        "pay_balance" => array("payment/pay_balance", 1,1), //通过华点支付
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
        "app_signup" => array('ucenter/app_signup', 0,1), //忘记密码，通过手机注册
        "bp_banner" => array('index/bp_banner', 0,1),
        "category_ad" => array('index/category_ad', 0,1), //获取分类广告
        "get_category" => array('index/get_category', 0,1),
        'get_hot' => array('product/get_hot', 0,1),
        "guess" => array("product/guess", 0,1),
        "get_index_ad" => array("index/get_index_ad", 0,1),
        'next_flash' => array('product/next_flash', 0,1),
        'want' => array('product/want', 1,1),
        "sale_support" => array('ucenter/sale_support', 1,1),
        "support_info" => array('ucenter/support_info', 1,1),
        "badge" => array("ucenter/badge", 1,1),
        "about" => array("ucenter/about", 0,1),
        "complaint" => array("ucenter/complaint", 1,1),
        "update_paypwd" => array('ucenter/updatePayPassword', 1,1),
        "reset_paypwd" => array('ucenter/resetPayPasswordByMobile', 1,1),
        "get_upyun" => array('ucenter/getUpyun', 1,1),
        "huabipay_info" => array("payment/huabipay_info", 1,1),
        "huabipay" => array("payment/huabi_pay", 1,1),
        "my_commission" => array("ucenter/myCommission", 1,1),
        "commission_log" => array("ucenter/commissionLog", 1,1),
        "commission_withdraw" => array("ucenter/commissionWithdraw", 1,1),
        "withdraw_history" => array("ucenter/withdrawHistory", 1,1),
        "my_invite" => array("ucenter/myInvite", 1,1),
        "my_invite_url" => array("ucenter/myInviteQRCodeUrl", 1,1),
        "huabipage_goods" => array("product/huabiPageGoods", 0,1),
        "huabipage_recommend" => array("product/huabiPageRecommend", 0,1),
        "get_express" => array("ucenter/getExpress", 1,1),
        'order_delete' => array("ucenter/order_delete", 1,1),
        'refund_apply_info' => array('ucenter/refund_apply_info', 1,1),
        'refund_apply_submit' => array('ucenter/refund_apply_submit', 1,1),
        'refund_progress' => array('ucenter/refund_progress', 1,1),
        'silver_coin_log' => array("ucenter/silver_coin_log", 1,1), //银点记录接口
        'gold_to_silver' => array("ucenter/gold_to_silver", 1,1), //金点兑换银点接口
        'get_recharge_package_gift' => array("ucenter/get_recharge_package_gift", 0,1), //获取套餐充值礼品
        'balance_withdraw' => array("ucenter/balance_withdraw", 1,1),
        //小区相关接口
        'apply_for_district' => array("district/applyForDistrict", 1,1), //申请小区
        'get_district_list' => array("district/getDistrictList", 1,1), //获取小区列表
        'get_district_info' => array("district/getDistrictInfo", 1,1), //获取小区详情
        'get_district_withdraw_record' => array("district/getDistrictWithdrawRecord", 1,1), //获取小区提现记录
        'get_district_income_record' => array("district/getDistrictIncomeRecord", 1,1), //获取小区收益记录
        'get_district_sale_record' => array("district/getDistrictSaleRecorde", 1,1), //获取小区销售记录
        'apply_do_settle' => array("district/applyDoSettle", 1,1), //申请提现
        'pay_district' => array("payment/pay_district", 1,1), //小区加盟费支付
        'get_promoter_list' => array("district/getPromoterList", 1,1), //获取推广员列表
        'get_subordinate' => array("district/getSubordinate", 1,1), //获取我的拓展小区
        'district_achievement' => array("district/districtAchievement", 1,1), //获取小区业绩数据
        //推广员相关
        'is_district_promoter' => array("ucenter/isDistrictPromoter", 1,1),//判断是否是小区推广员
        'get_district_info_by_id' => array("ucenter/getDistrictInfoById", 1,1),//获取小区信息
        'become_promoter' => array("ucenter/becomepromoter", 1,1),//成为小区推广员
        'get_promoter_income_static' => array("ucenter/getPromoterIncomeStatic", 1,1),//获取推广员收益统计
        'get_promoter_sale_record' => array("ucenter/getPromoterSaleRecord", 1,1),//获取推广员销售记录
        'get_promoter_income_record'=> array("ucenter/getPrmoterIncomeRecord",1,1),//获取推广员收益记录
        'get_promoter_settled_record' => array("ucenter/getPromoterSettledRecord", 1,1),//获取推广员提现记录
        'promoter_do_settle' => array("ucenter/promoterDoSettle", 1,1),//申请提现
        'get_qrcode_flag_by_goods_id' => array("ucenter/getQrcodeFlagByGoodsId", 1,1),
        'get_my_gold_withdraw_record'=>array("ucenter/getMyGoldWithdrawRecord",1,1),
        'get_my_invite_promoter'=>array("ucenter/getMyInvitePromoter",1,1),//获取我邀请的推广员列表
        //银点支付
        'pay_silver'=>array("payment/pay_silver",1,1),
        "point_sale"=>array("product/point_sale",0,1),//获取积分购列表
        "pointbuy"=>array("product/pointbuy",0,1),//获取积分购列表
        //提现
        "get_withdraw_set"=>array("index/getWithdrawSet",0,1),//提现设置
        
        "pointcoin_log"=>array("ucenter/pointcoin_log",1,1),
        //充值套餐详情
        "package_info"=>array("index/package_info",0,1),
        
        //圆梦new
        "recharge_package_set"=>array("index/recharge_package_set",0,1),//套餐设置
        "sign_in"=>array("ucenter/sign_in",1,1),
        "get_sign_in_data_by_ym"=>array("ucenter/getSignInDataByYm",1,1),
        "personal_shop_list"=>array("personalShop/shopList",0,1),
        "shop_index_goods"=>array("personalShop/shopIndexGoods",0,1),
        "shop_goods_list_by_time"=>array("personalShop/shopGoodsListByTime",0,1),
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
        echo json_encode(array('code' => $this->code, 'content' => $this->content, 'message' => $message));
    }

}
