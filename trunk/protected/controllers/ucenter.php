<?php
header("Content-type: text/html; charset=utf-8");
define('ICLOD_USERID', '100009001000');//商户id
define('ICLOD_PATH', dirname(__FILE__) . '/100009001000.pem');
define('ICLOD_CERT_PATH', dirname(__FILE__) . '/private_rsa.pem'); //私钥文件
define('ICLOD_CERT_PUBLIC_PATH', dirname(__FILE__) . '/public_rsa.pem');//公钥文件
define('ICLOD_Server_URL', 'https://yun.allinpay.com/service/soa');  //接口网关
define('NOTICE_URL', 'http://122.227.225.142:23661/service/soa'); //前台通知地址
define('BACKURL', 'http://122.227.225.142:23661/service/soa');//后台通知地址
class UcenterController extends Controller
{

    public $layout = 'index';
    // public $safebox = null;
    private $model = null;
    private $category = array();
    private $cookie_time = 31622400;
    public $sidebar = array(
//        '交易管理' => array(
//            '我的订单' => 'order',
//            '退款申请' => 'refund',
//            '我的关注' => 'attention',
//        ),
        '客户服务' => array(
//            '商品咨询' => 'consult',
            '商品评价' => 'review',
            '我的消息' => 'message',
        ),
        '账户管理' => array(
            '个人资料' => 'info',
            '收货地址' => 'address',
//            '我的优惠券' => 'voucher',
//            '账户金额' => 'account',
            '账户安全' => 'safety',
//            '我的积分' => 'point',
        )
    );
    // public $user = null;
    public $code = 1000;
    public $content = NULL;
    public $date = '';
    public $version = '1.0';
    /*
     @param $serverAddress 服务地址
     @param $sysid 商户号
     @param $alias 证书名称
     @param $path 证书路径
     @param $pwd 证书密码
     @param $signMethod 签名验证方式
     */
    public $serverAddress = ICLOD_Server_URL;
    public $sysid = "100009001000";
    public $alias = "100009001000";
    public $path = ICLOD_PATH;
    public $pwd = "900724";
    public $signMethod = "SHA1WithRSA";


    public function init()
    {
        header("Content-type: text/html; charset=" . $this->encoding);
        $this->model = new Model();
        $this->safebox = Safebox::getInstance();
        $this->user = $this->safebox->get('user');
        if ($this->user == null) {
            $this->user = Common::autoLoginUserInfo();
            $this->safebox->set('user', $this->user);
        }
        $category = Category::getInstance();
        $this->category = $category->getCategory();
        $cart = Cart::getCart();
        $action = Req::args("act");
        switch ($action) {
            case 'order_detail':
                $action = 'order';
                break;
            case 'refund_detail':
                $action = 'refund';
            case 'check_identity':
                $action = 'safety';
            case 'update_obj':
                $action = 'safety';
            case 'update_obj_success':
                $action = 'safety';
                break;
        }
        $config = Config::getInstance();
        $site_config = $config->get("globals");
        $list = explode('_', $action);
        $current = is_array($list) ? $list[0] : NULL;
        $this->assign('current', $current);
        $this->assign('site_title', $site_config['site_name']);
        $this->assign("actionId", $action);
        $this->assign("cart", $cart->all());
        $this->assign("sidebar", $this->sidebar);
        $this->assign("category", $this->category);
        $this->assign("url_index", '');
        $this->assign('user_id', $this->user['id']);
        $this->assign("seo_title", "用户中心");

    }

    public function checkRight($actionId)
    {
        if ($actionId == 'buildinvite') {
            return true;
        }
        if (isset($this->user['name']) && $this->user['name'] != null) {
//            if($this->user['mobile']==""&&$actionId!='firstbind'){
//                $this->redirect('firstbind');
//                exit();
//            }
            return true;
        } else {
            return false;
        }
    }

    public function noRight()
    {
        Cookie::set("url", Url::requestUri());
        if (Common::checkInWechat()) {
            $wechat = new WechatOAuth();
            $url = $wechat->getRequestCodeURL();
            $this->redirect($url);
            exit;
        } elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient') !== false) {
            if (isset($_GET['inviter_id']) && !isset($_GET['auth_code'])) {
                $act = "https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?app_id=2017080107981760&scope=auth_user&redirect_uri=http://www.ymlypt.com/ucenter/noRight&state=test&inviter_id=" . $_GET['inviter_id'];
                $this->redirect($act);
                exit;
            } else {
                $auth_code = $_GET['auth_code'];
                $seller_id = $_GET['inviter_id'];
                $pay_alipayapp = new pay_alipayapp();
                $result = $pay_alipayapp->alipayLogin($auth_code);
                if (!isset($result['code']) || $result['code'] != 10000) {
                    $this->redirect("/index/msg", false, array('type' => 'fail', 'msg' => '支付宝授权登录失败！'));
                    exit;
                }
                $nick_name = isset($result['nick_name']) ? $result['nick_name'] : '';
                $is_oauth = $this->model->table('oauth_user')->where('open_id="' . $result['user_id'] . '" and oauth_type="alipay"')->find();
                if ($is_oauth) {
                    $obj = $this->model->table("user as us")->join("left join customer as cu on us.id = cu.user_id left join oauth_user as o on us.id = o.user_id")->fields("us.*,cu.mobile,cu.group_id,cu.login_time,cu.real_name")->where("o.open_id='{$result['user_id']}'")->find();
                    $this->safebox->set('user', $obj, 31622400);
                    $this->user = $this->safebox->get('user');
                } else {
                    $this->model->table('oauth_user')->data(array(
                        'open_name' => $nick_name,
                        'oauth_type' => 'alipay',
                        'posttime' => time(),
                        'token' => '',
                        'expires' => '7200',
                        'open_id' => $result['user_id']
                    ))->insert();
                    Session::set('openname', $nick_name);
                    $passWord = CHash::random(6);
                    $time = date('Y-m-d H:i:s');
                    $validcode = CHash::random(8);
                    $model = $this->model;
                    $avatar = isset($result['avatar'])?$result['avatar']:'http://www.ymlypt.com/themes/mobile/images/logo-new.png';
                    $last_id = $model->table("user")->data(array('nickname' => $nick_name, 'password' => CHash::md5($passWord, $validcode), 'avatar' => $avatar, 'validcode' => $validcode))->insert();
                    $name = "u" . sprintf("%09d", $last_id);
                    $email = $name . "@no.com";
                    //更新用户名和邮箱
                    $model->table("user")->data(array('name' => $name, 'email' => $email))->where("id = '{$last_id}'")->update();
                    //更新customer表
                    $sex = isset($result['gender']) && $result['gender']== 'm' ? 1 : 0;
                    $model->table("customer")->data(array('user_id' => $last_id, 'real_name' => $nick_name, 'sex' => $sex, 'point_coin' => 200, 'reg_time' => $time, 'login_time' => $time))->insert();
                    Log::pointcoin_log(200, $last_id, '', '支付宝新用户积分奖励', 10);
                    //记录登录信息
                    $obj = $model->table("user as us")->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.group_id,cu.login_time,cu.mobile")->where("us.id='$last_id'")->find();
                    $this->safebox->set('user', $obj, 31622400);
                    $this->user = $this->safebox->get('user');
                    $this->model->table('oauth_user')->where("oauth_type='alipay' and open_id='{$result['user_id']}'")->data(array('user_id' => $last_id))->update();
                }
                Session::set('pay_type', 'alipay');
                if($seller_id==1) {
                    $this->redirect("/travel/demo?inviter_id={$seller_id}");
                } else {
                    $this->redirect("/ucenter/demo?inviter_id={$seller_id}");
                }  
                exit;
            }
        } else {
            $this->redirect("/simple/login");
        }
    }

    public function alipaylogin()
    {
        if (!isset($_GET['auth_code'])) {
            $act = "https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?app_id=2017080107981760&scope=auth_user&redirect_uri=http://www.ymlypt.com/ucenter/alipaylogin&state=test";
            $this->redirect($act);
            exit;
        } else {
            $auth_code = $_GET['auth_code'];
            $pay_alipayapp = new pay_alipayapp();
            $result = $pay_alipayapp->alipayLogin($auth_code);
            if (!isset($result['code']) || $result['code'] != 10000) {
                $this->redirect("/index/msg", false, array('type' => 'fail', 'msg' => '支付宝授权登录失败！'));
                exit;
            }

            return $result;
        }
    }

    //生成邀请码
    public function buildinvite()
    {
        $user_id = Filter::int(Req::args('uid'));
        // var_dump($user_id);die;
        // if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
        //     // var_dump(123);die;
        //     $wechatcfg = $this->model->table("oauth")->where("class_name='WechatOAuth'")->find();
        //     $wechat = new WechatMenu($wechatcfg['app_key'], $wechatcfg['app_secret'], '');
        //     $token = $wechat->getAccessToken();
        //     $params = array(
        //         "action_name" => "QR_LIMIT_STR_SCENE",
        //         "action_info" => array("scene" => array("scene_str" => "invite-{$user_id}"))
        //     );
        //     $ret = Http::curlPost("https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token={$token}", json_encode($params));
        //     $ret = json_decode($ret, TRUE);
        //     if (isset($ret['ticket'])) {
        //         $this->redirect("https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket={$ret['ticket']}");
        //         exit;
        //     }
        // }
        // $url = Url::fullUrlFormat("/index/invite/inviter_id/" . $this->user['id']);
        // $url = Url::fullUrlFormat("/travel/invite_register/inviter_id/" . $this->user['id']);
        $url = Url::fullUrlFormat("/travel/bind_mobile/inviter_id/" . $this->user['id']);
        $qrCode = new QrCode();
        $qrCode
            ->setText($url)
            ->setSize(300)
            ->setPadding(10)
            ->setErrorCorrection('medium')
            ->setForegroundColor(array('r' => 0, 'g' => 0, 'b' => 0, 'a' => 0))
            ->setBackgroundColor(array('r' => 255, 'g' => 255, 'b' => 255, 'a' => 0))
            //->setLabel('扫描添加为好友')
            ->setLabelFontSize(16)
            ->setImageType(QrCode::IMAGE_TYPE_PNG);

        // now we can directly output the qrcode
        header('Content-Type: ' . $qrCode->getContentType());
        $qrCode->render();
        return;
    }

    //生成邀请支付码
    public function buildinvitepay()
    {
        $user_id = Filter::int(Req::args('uid'));
        // if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
        //     $wechatcfg = $this->model->table("oauth")->where("class_name='WechatOAuth'")->find();
        //     $wechat = new WechatMenu($wechatcfg['app_key'], $wechatcfg['app_secret'], '');
        //     $token = $wechat->getAccessToken();
        //     $params = array(
        //         "action_name" => "QR_LIMIT_STR_SCENE",
        //         "action_info" => array("scene" => array("scene_str" => "invite-{$user_id}"))
        //     );
        //     $ret = Http::curlPost("https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token={$token}", json_encode($params));
        //     $ret = json_decode($ret, TRUE);
        //     if (isset($ret['ticket'])) {
        //         $this->redirect("https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket={$ret['ticket']}");
        //         exit;
        //     }
        // }
        if($user_id==1) {
            $url = Url::fullUrlFormat("/travel/demo/inviter_id/" . $user_id);
        } else {
            $url = Url::fullUrlFormat("/ucenter/demo/inviter_id/" . $user_id);
        }
        
        $qrCode = new QrCode();
        $qrCode
            ->setText($url)
            ->setSize(300)
            ->setPadding(10)
            ->setErrorCorrection('medium')
            ->setForegroundColor(array('r' => 0, 'g' => 0, 'b' => 0, 'a' => 0))
            ->setBackgroundColor(array('r' => 255, 'g' => 255, 'b' => 255, 'a' => 0))
            //->setLabel('扫描添加为好友')
            ->setLabelFontSize(16)
            ->setImageType(QrCode::IMAGE_TYPE_PNG);

        // now we can directly output the qrcode
        header('Content-Type: ' . $qrCode->getContentType());
        $qrCode->render();
        return;
    }

    //新的提现操作
    public function balance_withdraw()
    {
        if ($this->is_ajax_request()) {
            if ($this->user['id'] == 126935 || $this->user['id'] == 126676 || $this->user['id'] == 126663 || $this->user['id'] == 126243 || $this->user['id'] == 126002) {
                exit(json_encode(array('status' => 'fail', 'msg' => '账号已被冻结，请联系官方客服！')));
            }
            Filter::form();
            $id = Filter::int(Req::args('id'));
            $bankcard = $this->model->table('bankcard')->where('id=' . $id)->find();
            if (!$bankcard) {
                exit(json_encode(array('status' => 'fail', 'msg' => '该银行卡不存在')));
            }
            $open_name = $bankcard['open_name'];
            $open_bank = $bankcard['bank_name'];
            $prov = $bankcard['province'];
            $city = $bankcard['city'];
            $card_no = $bankcard['cardno'];
            $amount = sprintf('%.2f', Req::args('amount'));
            $customer = $this->model->table("customer")->where("user_id =" . $this->user['id'])->fields('balance')->find();
            $can_withdraw_amount = $customer ? $customer['balance'] : 0;
            if ($can_withdraw_amount < $amount) {//提现金额中包含 暂时不能提现部分 
                exit(json_encode(array('status' => 'fail', 'msg' => '提现金额超出的账户可提现余额')));
            }
            $config = Config::getInstance();
            $other = $config->get("other");
            if ($amount < $other['min_withdraw_amount']) {
                exit(json_encode(array('status' => 'fail', 'msg' => "提现金额少于" . $other['min_withdraw_amount'])));
            }
            // $isset = $this->model->table("balance_withdraw")->where("user_id =" . $this->user['id'] . " and status =0")->find();
            // if ($isset) {
            //     exit(json_encode(array('status' => 'fail', 'msg' => '申请失败，还有未处理完的提现申请')));
            // }
            $withdraw_no = "BW" . date("YmdHis") . rand(100, 999);
            $data = array("withdraw_no" => $withdraw_no, "user_id" => $this->user['id'], "amount" => $amount, 'open_name' => $open_name, "open_bank" => $open_bank, 'province' => $prov, "city" => $city, 'card_no' => $card_no, 'apply_date' => date("Y-m-d H:i:s"), 'status' => 0, 'type' => 0);
            $result = $this->model->table('balance_withdraw')->data($data)->insert();
            if ($result) {
                $this->model->table('customer')->data(array('balance' => "`balance`-" . $amount))->where('user_id=' . $this->user['id'])->update();
                Log::balance(0 - $amount, $this->user['id'], $withdraw_no, "余额提现申请", 3, 1);
                exit(json_encode(array('status' => 'success', 'msg' => "申请提交成功")));
            } else {
                exit(json_encode(array('status' => 'fail', 'msg' => '申请提交失败，数据库错误')));
            }
        } else {
            if ($this->user['id'] == 126935 || $this->user['id'] == 126676 || $this->user['id'] == 126663 || $this->user['id'] == 126243 || $this->user['id'] == 126002) {
                $this->redirect("/index/msg", false, array('type' => 'fail', 'msg' => '账号已被冻结，请联系官方客服！'));
                exit;
            }
            $config = Config::getInstance();
            $other = $config->get("other");
            $info = $this->model->table("customer")->fields('balance,realname_verified')->where("user_id=" . $this->user['id'])->find();
            $card_num = $this->model->table("bankcard")->where("user_id=" . $this->user['id'])->count();
            $this->assign('card_num', $card_num);
            $this->assign("goldcoin", $info['balance']);
            $this->assign("realname_verified", $info['realname_verified']);
            $this->assign("gold2silver", $other['gold2silver']);
            $this->assign("withdraw_fee_rate", $other['withdraw_fee_rate']);
            $this->assign('min_withdraw_amount', $other['min_withdraw_amount']);
            $this->assign('seo_title', '余额提现');
            $this->redirect();
        }
    }

    //更新的提现操作(线下商家余额提现)
    public function offline_balance_withdraw()
    {   
        header("Access-Control-Allow-Credentials", "true");
        header("Access-Control-Allow-Origin:*");
        header('Access-Control-Allow-Methods:OPTIONS, GET, POST');
        header('Access-Control-Allow-Headers:x-requested-with,content-type');   
        if ($this->is_ajax_request()) {
            if ($this->user['id'] == 126935 || $this->user['id'] == 126676 || $this->user['id'] == 126663 || $this->user['id'] == 126243 || $this->user['id'] == 126002) {
                exit(json_encode(array('status' => 'fail', 'msg' => '账号已被冻结，请联系官方客服！')));
            }
            Filter::form();
            $id = Filter::int(Req::args('id'));
            $bankcard = $this->model->table('bankcard')->where('id=' . $id)->find();
            if (!$bankcard) {
                exit(json_encode(array('status' => 'fail', 'msg' => '该银行卡不存在')));
            }
            $open_name = $bankcard['open_name'];
            $open_bank = $bankcard['bank_name'];
            $prov = $bankcard['province'];
            $city = $bankcard['city'];
            $card_no = $bankcard['cardno'];
            $amount = sprintf('%.2f', Req::args('amount'));
            $customer = $this->model->table("customer")->where("user_id =" . $this->user['id'])->fields('offline_balance')->find();
            $can_withdraw_amount = $customer ? $customer['offline_balance'] : 0;
            if ($can_withdraw_amount < $amount) {//提现金额中包含 暂时不能提现部分 
                exit(json_encode(array('status' => 'fail', 'msg' => '提现金额超出的账户可提现余额')));
            }
            $config = Config::getInstance();
            $other = $config->get("other");
            if ($amount < $other['min_withdraw_amount']) {
                exit(json_encode(array('status' => 'fail', 'msg' => "提现金额少于" . $other['min_withdraw_amount'])));
            }
            // $isset = $this->model->table("balance_withdraw")->where("user_id =" . $this->user['id'] . " and status =0")->find();
            // if ($isset) {
            //     exit(json_encode(array('status' => 'fail', 'msg' => '申请失败，还有未处理完的提现申请')));
            // }
            $withdraw_no = "BW" . date("YmdHis") . rand(100, 999);
            $data = array("withdraw_no" => $withdraw_no, "user_id" => $this->user['id'], "amount" => $amount, 'open_name' => $open_name, "open_bank" => $open_bank, 'province' => $prov, "city" => $city, 'card_no' => $card_no, 'apply_date' => date("Y-m-d H:i:s"), 'status' => 0, 'type' => 1);
            $result = $this->model->table('balance_withdraw')->data($data)->insert();
            if ($result) {
                $this->model->table('customer')->data(array('offline_balance' => "`offline_balance`-" . $amount))->where('user_id=' . $this->user['id'])->update();
                Log::balance(0 - $amount, $this->user['id'], $withdraw_no, "商家余额提现申请", 11, 1);
                exit(json_encode(array('status' => 'success', 'msg' => "申请提交成功")));
            } else {
                exit(json_encode(array('status' => 'fail', 'msg' => '申请提交失败，数据库错误')));
            }
        } else {
            if ($this->user['id'] == 126935 || $this->user['id'] == 126676 || $this->user['id'] == 126663 || $this->user['id'] == 126243 || $this->user['id'] == 126002) {
                $this->redirect("/index/msg", false, array('type' => 'fail', 'msg' => '账号已被冻结，请联系官方客服！'));
                exit;
            }
            $config = Config::getInstance();
            $other = $config->get("other");
            $info = $this->model->table("customer")->fields('offline_balance,realname_verified')->where("user_id=" . $this->user['id'])->find();
            $card_num = $this->model->table("bankcard")->where("user_id=" . $this->user['id'])->count();
            $need_check = -2;
            $reason = '';
            $shop = $this->model->table('district_promoter')->fields('id')->where('user_id=' . $this->user['id'])->find();
            if ($shop) {
                $shop_check = $this->model->table('shop_check')->fields('*')->where('user_id=' . $this->user['id'])->find();
                if (!$shop_check) {
                    $need_check = -1; //需要上传
                } elseif ($shop_check['status'] == 0) {
                    $need_check = 0;  //等待审核
                } elseif ($shop_check['status'] == 1) {
                    $need_check = 1;  //通过审核
                } else {
                    $need_check = 2; //未通过，需要重新提交
                    $reason = $shop_check['reason'];
                }
            } else {
                $need_check = -2;
            }
            $this->assign('need_check', $need_check);
            $this->assign('reason', $reason);
            //银盛上传资料token获取
            $myParams = array();

            $myParams['method'] = 'ysepay.merchant.register.token.get';
            $myParams['partner_id'] = 'yuanmeng';
            // $myParams['partner_id'] = $this->user['id'];
            $myParams['timestamp'] = date('Y-m-d H:i:s', time());
            $myParams['charset'] = 'GBK';
            $myParams['notify_url'] = 'http://api.test.ysepay.net/atinterface/receive_return.htm';
            $myParams['sign_type'] = 'RSA';

            $myParams['version'] = '3.0';
            $biz_content_arr = array();

            $myParams['biz_content'] = '{}';
            ksort($myParams);

            $signStr = "";
            foreach ($myParams as $key => $val) {
                $signStr .= $key . '=' . $val . '&';
            }
            $signStr = rtrim($signStr, '&');
            $sign = $this->sign_encrypt(array('data' => $signStr));
            $myParams['sign'] = trim($sign['check']);
            $url = 'https://register.ysepay.com:2443/register_gateway/gateway.do';

            $ret = Common::httpRequest($url, 'POST', $myParams);
            $ret = json_decode($ret, true);

            if (isset($ret['ysepay_merchant_register_token_get_response']['token'])) {
                $this->assign('yin_token', $ret['ysepay_merchant_register_token_get_response']['token']);
            } else {
                $this->assign('yin_token', '');
            }
            $upyun = Config::getInstance()->get("upyun");

            $options = array(
                'bucket' => $upyun['upyun_bucket'],
                // 'allow-file-type' => 'jpg,gif,png,jpeg', // 文件类型限制，如：jpg,gif,png
                'expiration' => time() + $upyun['upyun_expiration'],
                // 'notify-url' => $upyun['upyun_notify-url'],
                // 'ext-param' => "",
                'save-key' => "/data/uploads/head/" . $this->user['id'] . ".jpg",
            );
            $policy = base64_encode(json_encode($options));
            $signature = md5($policy . '&' . $upyun['upyun_formkey']);
            $this->assign('secret', md5('ym123456'));
            $this->assign('policy', $policy);
            $this->assign('signature', $signature);
            $this->assign('user_id', $this->user['id']);
            $this->assign('card_num', $card_num);
            $this->assign("goldcoin", $info['offline_balance']);
            $this->assign("realname_verified", $info['realname_verified']);
            $this->assign("gold2silver", $other['gold2silver']);
            $this->assign("withdraw_fee_rate", $other['withdraw_fee_rate']);
            $this->assign('min_withdraw_amount', $other['min_withdraw_amount']);
            $this->assign('seo_title', '商家余额提现');
            $this->redirect();
        }
    }

    public function offline_balance_convert()
    {
        if ($this->is_ajax_request()) {
            $amount = Req::args('amount');
            $amount = round($amount, 2);
            $customer = $this->model->table("customer")->where("user_id =" . $this->user['id'])->fields('balance,offline_balance')->find();
            $can_withdraw_amount = $customer ? $customer['offline_balance'] : 0;
            if ($can_withdraw_amount < $amount) {//提现金额中包含 暂时不能提现部分 
                exit(json_encode(array('status' => 'fail', 'msg' => '提现金额超出的账户可提现余额')));
            }
            $config = Config::getInstance();
            $other = $config->get("other");
            if ($amount < $other['min_withdraw_amount']) {
                exit(json_encode(array('status' => 'fail', 'msg' => "提现金额少于" . $other['min_withdraw_amount'])));
            }
            $user_id = $this->user['id'];
            $user_id = intval($user_id);
            $balance = $customer['balance'] + $amount;
            $offline_balance = $customer['offline_balance'] - $amount;
            $result = $this->model->table("customer")->data(array('balance' => $balance, "offline_balance" => $offline_balance))->where("user_id=" . $user_id)->update();
            $withdraw_no = "OF" . date("YmdHis") . rand(100, 999);
            $data = array("withdraw_no" => $withdraw_no, "user_id" => $this->user['id'], "amount" => $amount, 'open_name' => '', "open_bank" => '', 'card_no' => '', 'apply_date' => date("Y-m-d H:i:s"), 'note' => '商家余额提现到可用余额', 'status' => 1, 'type' => 2);
            $this->model->table('balance_withdraw')->data($data)->insert();
            Log::balance($amount, $this->user['id'], '', '商家余额转入', 9, 0);
            if ($result) {
                exit(json_encode(array('status' => 'success', 'msg' => "提现成功")));
            } else {
                exit(json_encode(array('status' => 'fail', 'msg' => '提现失败，数据库错误')));
            }
        }
    }

    public function point()
    {
        $customer = $this->model->table("customer")->where("user_id=" . $this->user['id'])->find();
        $this->assign("customer", $customer);
        $this->redirect();
    }

    public function point_exchange()
    {
        $id = Filter::int(Req::args('id'));
        $voucher = $this->model->table("voucher_template")->where("id=$id")->find();
        if ($voucher) {
            $use_point = 0 - $voucher['point'];
            $result = Pointlog::write($this->user['id'], $use_point, '积分兑换代金券，扣除了' . $use_point . '积分');
            if (true === $result) {
                Common::paymentVoucher($voucher, $this->user['id']);
                $info = array('status' => 'success');
            } else {
                $info = array('status' => 'fail', 'msg' => $result['msg']);
            }
            echo JSON::encode($info);
        } else {
            $info = array('status' => 'fail', 'msg' => '你要兑换的代金券，不存在！');
            echo JSON::encode($info);
        }
    }

    public function upload_head()
    {
        $upfile_path = Tiny::getPath("uploads") . "head/";
        $upfile_url = preg_replace("|" . APP_URL . "|", '', Tiny::getPath("uploads_url") . "head/", 1);
        echo $upfile_path . "+" . $upfile_url;
        die;
        //$upfile_url = strtr(Tiny::getPath("uploads_url")."head/",APP_URL,'');
        $upfile = new UploadFile('imgFile', $upfile_path, '500k', '', 'hash', $this->user['id']);
        $upfile->save();
        $info = $upfile->getInfo();
        $result = array();
        if ($info[0]['status'] == 1) {
            $result = array('error' => 0, 'url' => $upfile_url . $info[0]['path']);
            $image_url = $upfile_url . $info[0]['path'];
            $image = new Image();
            $image->suffix = '';
            $image->thumb(APP_ROOT . $image_url, 100, 100);
            $model = new Model('user');
            $model->data(array('avatar' => $image_url))->where("id=" . $this->user['id'])->update();

            $safebox = Safebox::getInstance();
            $user = $this->user;
            $user['avatar'] = $image_url;
            $safebox->set('user', $user);
        } else {
            $result = array('error' => 1, 'message' => $info[0]['msg']);
        }
        echo JSON::encode($result);
    }

    public function account()
    {
        $customer = $this->model->table("customer")->where("user_id=" . $this->user['id'])->find();
        $this->assign("customer", $customer);
        $client_type = Chips::clientType();
        $client_type = ($client_type == "desktop") ? 0 : ($client_type == "wechat" ? 2 : 1);
        $model = new Model("payment as pa");
        $paytypelist = $model->fields("pa.*,pp.logo,pp.class_name")->join("left join pay_plugin as pp on pa.plugin_id = pp.id")
            ->where("pa.status = 0 and pa.plugin_id not in(1,12,19,20) and pa.client_type = $client_type")->order("pa.sort desc")->findAll();
        $paytypeone = reset($paytypelist);
        $this->assign("paytypelist", $paytypelist);
        //充值套餐的地址
        $area_ids = array();
        $address = $this->model->table("address")->where("user_id=" . $this->user['id'])->order("is_default desc")->findAll();
        foreach ($address as $add) {
            $area_ids[$add['province']] = $add['province'];
            $area_ids[$add['city']] = $add['city'];
            $area_ids[$add['county']] = $add['county'];
        }
        $area_ids = implode(",", $area_ids);
        $areas = array();
        if ($area_ids != '')
            $areas = $this->model->table("area")->where("id in($area_ids )")->findAll();
        $parse_area = array();
        foreach ($areas as $area) {
            $parse_area[$area['id']] = $area['name'];
        }
        $this->assign("parse_area", $parse_area);
        $this->assign('address', $address);
        $config = Config::getInstance();
        $other = $config->get("other");
        $package_set = $config->get("recharge_package_set");
        if (is_array($package_set)) {
            if (isset($package_set[4]['gift']) && $package_set[4]['gift'] != '') {
                $where = implode(',', array_reverse(explode("|", $package_set[4]['gift'])));
                $select4 = $this->model->table("products as p")->join("goods as g on p.goods_id=g.id")->where("p.id in ({$where})")->fields("p.id,g.img,g.name")->order("field(p.id,$where)")->findAll();
                $this->assign("select4", $select4);
            }
        }
        $this->assign("withdraw_fee_rate", $other['withdraw_fee_rate']);
        $this->assign('min_withdraw_amount', $other['min_withdraw_amount']);
        $package = Filter::int(Req::args('package'));
        //套餐充值
        $pid = Filter::int(Req::args('pid'));
        $this->assign('package_set', $package_set);
        if ($package && $pid) {
            $this->assign("package", $package);
            $this->assign("pid", $pid);
        }
        $this->redirect();
    }

    public function refund_act()
    {
        $order_no = Filter::sql(Req::args('order_no'));
        $order = $this->model->table("order")->where("order_no='$order_no' and user_id = " . $this->user['id'])->find();
        if ($order) {
            if ($order['pay_status'] == 1) {
                $refund = $this->model->table("doc_refund")->where("order_no='$order_no' and user_id = " . $this->user['id'])->find();
                if ($refund) {
                    $this->redirect("refund", false, array('msg' => array("warning", "不能重复申请退款！")));
                } else {
                    Filter::form(array('text' => 'account_name|refund_account|account_bank|content', 'int' => 'order_no|refund_type'));
                    $data = array(
                        'account_name' => Req::args('account_name'),
                        'refund_account' => Req::args('refund_account'),
                        'account_bank' => Req::args('account_bank'),
                        'order_no' => Req::args('order_no'),
                        'refund_type' => Req::args('refund_type'),
                        'create_time' => date('Y-m-d H:i:s'),
                        'user_id' => $this->user['id'],
                        'order_id' => $order['id'],
                        'content' => Req::args('content'),
                        'pay_status' => 0
                    );
                    $this->model->table("doc_refund")->data($data)->insert();
                    $this->redirect("refund", false, array('msg' => array("success", "申请已经成功提交,请等候处理！")));
                }
            } else {
                $this->redirect("refund", false, array('msg' => array("warning", "此订单还未支付，无法申请退款！")));
            }
        } else {
            $this->redirect("refund", false, array('msg' => array("warning", "此订单编号不存在！")));
        }
    }

    public function refund_detail()
    {
        $id = Filter::int(Req::args('id'));
        $refund = $this->model->table("doc_refund")->where("id=$id and user_id=" . $this->user['id'])->find();
        if ($refund) {
            $this->assign("refund", $refund);
            $this->redirect();
        } else {
            Tiny::Msg($this, 404);
        }
    }

    public function refund_del()
    {
        $order_no = Filter::sql(Req::args('order_no'));
        $obj = $this->model->table("doc_refund")->where("order_no='$order_no' and  pay_status=0 and user_id = " . $this->user['id'])->delete();
        $this->redirect("refund");
    }

    public function voucher_activated()
    {
        if (!Tiny::app()->checkToken())
            $this->redirect("voucher");
        $rules = array('account:required:账号不能为空!', 'password:required:密码不能为空！');
        $info = Validator::check($rules);
        if (!is_array($info) && $info == true) {
            Filter::form(array('sql' => 'account'));
            $account = Filter::sql(Req::args("account"));
            $voucher = $this->model->table("voucher")->where("account='$account' and is_send = 0")->find();
            if ($voucher && $voucher['password'] == Req::args("password")) {
                if (strtotime($voucher['end_time']) > time()) {
                    if ($voucher['status'] == 0) {
                        $this->model->table("voucher")->data(array('user_id' => $this->user['id'], 'is_send' => 1, 'status' => 0))->where("account='$account'")->update();
                        $this->redirect("voucher", false, array('msg' => array("success", "优惠券成功激活！")));
                    } else {
                        $this->redirect("voucher", false, array('msg' => array("warning", "此优惠券已使用过！")));
                    }
                } else {
                    //过期
                    $this->redirect("voucher", false, array('msg' => array("warning", "优惠券已过期！")));
                }
            } else {
                //不存在此优惠券
                $this->redirect("voucher", false, array('msg' => array("error", "优惠券账号或密码错误！")));
            }
        } else {
            //输入信息有误
            $this->redirect("voucher", false, array('msg' => array("info", "输入的信息不格式不正确")));
        }
    }

    public function get_consult()
    {
        $page = Filter::int(Req::args("page"));
        $type = Filter::int(Req::args("type"));
        $status = Req::args("status");
        $where = "ak.user_id = " . $this->user['id'];
        switch ($status) {
            case 'n':
                $where .= " and ak.status = 0";
                break;
            case 'y':
                $where .= " and ak.status = 1";
                break;
            default:
                break;
        }
        $ask = $this->model->table("ask as ak")->join("left join goods as go on ak.goods_id = go.id")->fields("ak.*,go.name,go.id as gid,go.img,go.sell_price")->where($where)->order("ak.id desc")->findPage($page, 10, $type, true);
        foreach ($ask['data'] as $key => $value) {
            $ask['data'][$key]['img'] = Common::thumb($value['img'], 100, 100);
        }
        $ask['status'] = "success";
        echo JSON::encode($ask);
    }

    //获取商品评价
    public function get_review()
    {
        $page = Filter::int(Req::args("page"));
        $type = Filter::int(Req::args("type"));
        $status = Req::args("status");
        $where = "re.user_id = " . $this->user['id'];
        switch ($status) {
            case 'n':
                $where .= " and re.status = 0";
                break;
            case 'y':
                $where .= " and re.status = 1";
                break;
            default:
                break;
        }
        $review = $this->model->table("review as re")->join("left join goods as go on re.goods_id = go.id left join order as rd on rd.order_no = re.order_no")->fields("re.*,go.name,rd.accept_name,rd.id as order_id,go.id as gid,go.img as img,go.sell_price")->where($where)->order("re.id desc")->findPage($page, 10, $type, true);
        $data = $review['data'];
        if (empty($data)) {
            echo JSON::encode(array('status' => 'fail'));
            exit();
        }
        foreach ($data as $key => $value) {
            $value['img'] = Url::urlFormat("@" . $value['img']);
            $value['point'] = ($value['point'] / 5) * 100;
            $data[$key] = $value;
        }
        $review['status'] = "success";
        $review['data'] = $data;
        echo JSON::encode($review);
    }

    //获取商品评价
    public function get_message()
    {
        $page = Filter::int(Req::args("page"));
        $type = Filter::int(Req::args("type"));
        $status = Req::args("status");
        $where = "";
        $customer = $this->model->table("customer")->where("user_id=" . $this->user['id'])->find();
        $message_ids = '';
        if ($customer) {
            $message_ids = ',' . $customer['message_ids'] . ',';
            switch ($status) {
                case 'y':
                    $message_ids = preg_replace('/,\d+,/i', ',', $message_ids);
                    $message_ids = preg_replace('/-/i', '', $message_ids);
                    break;
                case 'n':
                    $message_ids = preg_replace('/,-\d+,/i', ',', $message_ids);
                    break;
                default:
                    break;
            }
            $message_ids = trim($message_ids, ',');
        }

        $message = array();
        if ($message_ids != '') {
            $message = $this->model->table("message")->where("id in ($message_ids)")->order("id desc")->findPage($page, 10, $type, true);
        }
        $message['status'] = "success";
        echo JSON::encode($message);
    }

    public function message_read()
    {
        $id = Filter::int(Req::args("id"));
        $customer = $this->model->table("customer")->where("user_id=" . $this->user['id'])->find();
        $message_ids = ',' . $customer['message_ids'] . ',';
        $message_ids = str_replace(",$id,", ',-' . $id . ',', $message_ids);
        $message_ids = trim($message_ids, ',');
        $this->model->table("customer")->where("user_id=" . $this->user['id'])->data(array('message_ids' => $message_ids))->update();
        echo JSON::encode(array("status" => 'success'));
    }

    public function message_del()
    {
        $id = Filter::int(Req::args("id"));
        $customer = $this->model->table("customer")->where("user_id=" . $this->user['id'])->find();
        $message_ids = ',' . $customer['message_ids'] . ',';
        $message_ids = str_replace(",-$id,", ',', $message_ids);
        $message_ids = rtrim($message_ids, ',');
        $this->model->table("customer")->where("user_id=" . $this->user['id'])->data(array('message_ids' => $message_ids))->update();
        echo JSON::encode(array("status" => 'success'));
    }

    public function get_voucher()
    {
        $page = Filter::int(Req::args("page"));
        $pagetype = Filter::int(Req::args("pagetype"));
        $status = Req::args("status");
        $where = "user_id = " . $this->user['id'] . " and is_send = 1";
        switch ($status) {
            case 'n':
                $where .= " and status = 0 and '" . date("Y-m-d H:i:s") . "' <=end_time";
                break;
            case 'u':
                $where .= " and status = 1";
                break;
            case 'p':
                $where .= " and status = 0 and '" . date("Y-m-d H:i:s") . "' > end_time";
                break;
            default:
                break;
        }
        $voucher = $this->model->table("voucher")->where($where)->order("id desc")->findPage($page, 10, $pagetype, true);
        $data = $voucher['data'];
        foreach ($data as $key => $value) {
            $value['start_time'] = substr($value['start_time'], 0, 10);
            $value['end_time'] = substr($value['end_time'], 0, 10);
            $data[$key] = $value;
        }
        $voucher['data'] = $data;
        $voucher['status'] = "success";
        echo JSON::encode($voucher);
    }

    public function get_express_info()
    {
        $id = Filter::int(Req::args("id"));
        $number = Req::args("number");
        $ret = array('status' => "fail", 'data' => NULL);
        if ($id && $number) {
            $companyinfo = $this->model->table("express_company")->where("id='{$id}'")->find();
            if ($companyinfo) {
                $data = Common::getExpress($companyinfo['alias'], $number);
                if ($data['message'] == 'ok' && $data['status']) {
                    $ret['status'] = 'success';
                    $ret['data']['content'] = $data['data'];
                }
            }
        }
        echo json_encode($ret);
    }

    public function info()
    {
        $info = $this->model->table("customer as cu ")->fields("cu.*,us.email,us.name,us.nickname,us.avatar,gr.name as gname")->join("left join user as us on cu.user_id = us.id left join grade as gr on cu.group_id = gr.id")->where("cu.user_id = " . $this->user['id'])->find();
        if ($info) {
            $this->assign("info", $info);
            $info = array_merge($info, Req::args());
            $this->redirect("info", false, $info);
        } else
            Tiny::Msg($this, 404);
    }

    public function promoter_info()
    {
        $info = $this->model->table("district_promoter as dp")->fields("dp.*,c.real_name")->join("left join customer as c on dp.user_id = c.user_id")->where("dp.user_id = " . $this->user['id'])->find();
        if ($info) {
            $this->assign("promoter_info", $info);
            $info = array_merge($info, Req::args());
            $this->redirect("promoter_info", false, $info);
        } else
            Tiny::Msg($this, 404);
    }

    public function promoter_save()
    {
        // if($this->user['id']==42608){
        //     var_dump($_FILES);die;
        // }
        $upfile_path = Tiny::getPath("uploads") . "/head/";
        $upfile_url = preg_replace("|" . APP_URL . "|", '', Tiny::getPath("uploads_url") . "head/", 1);
        $upfile = new UploadFile('picture', $upfile_path, '500k', '', 'hash', $this->user['id']);
        $upfile->save();
        $info = $upfile->getInfo();
        $result = array();
        $picture = "";

        if ($info[0]['status'] == 1) {
            $result = array('error' => 0, 'url' => $upfile_url . $info[0]['path']);
            $image_url = $upfile_url . $info[0]['path'];
            $image = new Image();
            $image->suffix = '';
            $image->thumb(APP_ROOT . $image_url, 100, 100);
            $picture = "http://" . $_SERVER['HTTP_HOST'] . '/' . $image_url;
        }

        $location = Filter::text(Req::args('areas') . Req::args('road'));

        $data = array(
            'shop_name' => Req::args('shop_name'),
            'info' => Filter::text(Req::args('info')),
            'road' => Filter::text(Req::args('road')),
        );
        if (Req::args('areas') != '省份/直辖市市县/区') {
            $lnglat = Common::getLnglat($location);
            $data['location'] = $location;
            $data['lng'] = $lnglat['lng'];
            $data['lat'] = $lnglat['lat'];
        }
        if (Req::args('province')) {
            $data['province_id'] = Filter::int(Req::args('province'));
        }
        if (Req::args('city')) {
            $data['city_id'] = Filter::int(Req::args('city'));
        }
        if (Req::args('county')) {
            $data['region_id'] = Filter::int(Req::args('county'));
        }
        // if(Req::args('street')){
        //     $data['tourist_id'] = Filter::int(Req::args('street'));
        // }
        if ($picture) {
            $data['picture'] = $picture;
        }
        if (Req::args('classify_id')) {
            $data['classify_id'] = Filter::int(Req::args('classify_id'));
        }

        $id = $this->user['id'];

        $this->model->table("district_promoter")->data($data)->where("user_id=$id")->update();
        $this->redirect("promoter_info", false, array('msg' => array("success", "保存成功！")));
    }

    public function info_save()
    {
        $rules = array('nickname:required:昵称不能为空!', 'real_name:required:真实姓名不能为空!', 'sex:int:性别必需选择！', 'birthday:date:生日日期格式不正确！', 'province:[1-9]\d*:选择地区必需完成', 'city:[1-9]\d*:选择地区必需完成', 'county:[1-9]\d*:选择地区必需完成');
        $info = Validator::check($rules);
        if (is_array($info)) {
            $this->redirect("info", false, array('msg' => array("info", $info['msg'])));
        } else {
            $data = array(
                'nickname' => Filter::txt(Req::args('nickname')),
                'real_name' => Filter::text(Req::args('real_name')),
                'sex' => Filter::int(Req::args('sex')),
                'birthday' => Filter::sql(Req::args('birthday')),
                'phone' => Filter::sql(Req::args('phone')),
                'province' => Filter::int(Req::args('province')),
                'city' => Filter::int(Req::args('city')),
                'county' => Filter::int(Req::args('county')),
                'addr' => Filter::text(Req::args('addr'))
            );

//            //如果用户之前没有绑定过手机号码，则执行这一步
//            if ($this->user['mobile'] == '') {
//                $mobile = Filter::int(Req::args('mobile'));
//                $obj = $this->model->table("customer")->where("mobile='$mobile'")->find();
//                $data['mobile'] = $mobile;
//                if ($obj) {
//                    $this->redirect("info", false, array('msg' => array("info", '此手机号已经存在！')));
//                    exit;
//                }
//            }
            if ($this->user['email'] == $this->user['mobile'] . '@no.com') {
                $email = Req::args('email');
                if (Validator::email($email)) {
                    $userData['email'] = $email;
                    $obj = $this->model->table("user")->where("email='$email'")->find();
                    if ($obj) {
                        $this->redirect("info", false, array('msg' => array("info", '此邮箱号已存在')));
                        exit;
                    }
                }
            }

            $id = $this->user['id'];
            $this->model->table("user")->data($data)->where("id=$id")->update();
            $this->model->table("customer")->data($data)->where("user_id=$id")->update();
            $obj = $this->model->table("user as us")->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.group_id,cu.login_time,cu.mobile")->where("us.id=$id")->find();
            $this->safebox->set('user', $obj, $this->cookie_time);
            $this->redirect("info", false, array('msg' => array("success", "保存成功！")));
        }
    }

    public function firstbind()
    {
        $info = $this->model->table("customer as cu ")->fields("cu.*,us.email,us.name,us.nickname,us.avatar,gr.name as gname")->join("left join user as us on cu.user_id = us.id left join grade as gr on cu.group_id = gr.id")->where("cu.user_id = " . $this->user['id'])->find();
        if ($info) {
            // if($info['mobile']!=''){
            //     $user = $this->user;
            //     $user['mobile']=$info['mobile'];
            //     $user['real_name']=$info['real_name'];
            //     $this->safebox->set('user', $user);
            //     $this->redirect("index");
            //     exit();
            // }
            $this->assign("info", $info);
            $info = array_merge($info, Req::args());
            if ($this->is_ajax_request()) {
                $realname = Filter::sql(Req::post('realname'));
                $mobile = Filter::sql(Req::post('mobile'));
                $validatecode = Filter::sql(Req::post('validatecode'));
                if ($realname && $mobile && $validatecode) {
                    $exist = $this->model->table("customer")->where("mobile='{$mobile}'")->find();
                    if (!$exist) {
                        $ret = SMS::getInstance()->checkCode($mobile, $validatecode);
                        if ($ret['status'] == 'success') {
                            $data = array(
                                'mobile' => $mobile,
                                'real_name' => $realname,
                                'mobile_verified' => 1,
                                'point_coin' => 200
                            );
                            $this->model->table("customer")->data($data)->where("user_id={$this->user['id']}")->update();
                            Log::pointcoin_log(200, $this->user['id'], '', '微信新用户积分奖励', 10);
                            //默认密码
                            $passWord = $mobile;
                            $validcode = CHash::random(8);
                            $this->model->table('user')->data(array('password' => CHash::md5($passWord, $validcode), 'validcode' => $validcode))->where("id={$this->user['id']}")->update();

                            SMS::getInstance()->flushCode($mobile);
                            $user = $this->user;
                            $user['mobile'] = $mobile;
                            $user['real_name'] = $realname;
                            $this->safebox->set('user', $user);
                            // Common::sendPointCoinToNewComsumer($this->user['id']);
                            $ret['status'] = "success";
                            $ret["message"] = "验证成功";
                            $ret['show_point'] = 1;
                        } else {
                            $ret['status'] = "fail";
                            $ret['message'] = "验证码不正确";
                        }
                    } else {
                        if (Common::checkInWechat()) {
                            $ret = SMS::getInstance()->checkCode($mobile, $validatecode);
                            if ($ret['status'] == 'success') {
                                SMS::getInstance()->flushCode($mobile);
                                $is_bind = $this->model->table("oauth_user")->where("user_id=" . $exist['user_id'])->find();
                                if ($is_bind) {
                                    $ret['status'] = "fail";
                                    $ret['message'] = "该手机号已经绑定过了";
                                } else {
                                    $result = $this->model->table("oauth_user")->where("user_id=" . $this->user['id'])->data(array("user_id" => $exist['user_id']))->update();
                                    if ($result) {
                                        $user = $this->user;
                                        $user['id'] = $exist['user_id'];
                                        $user['mobile'] = $mobile;
                                        $user['real_name'] = $realname;
                                        $this->safebox->set('user', $user);
                                        $ret['status'] = "success";
                                        $ret["message"] = "微信绑定成功";
                                        $ret['show_point'] = 0;
                                    } else {
                                        $ret['status'] = "fail";
                                        $ret["message"] = "微信绑定失败";
                                    }
                                }
                            } else {
                                $ret['status'] = "fail";
                                $ret['message'] = "验证码不正确";
                            }

                        } else {
                            $ret['status'] = "fail";
                            $ret['message'] = "该手机号已经绑定过了";
                        }
                    }
                } else {
                    $ret['status'] = "fail";
                    $ret['message'] = "参数错误";
                }
                echo json_encode($ret);
                exit;
            }
            $this->layout = "";
            $this->redirect("firstbind");
        } else {
            Tiny::Msg($this, 404);
        }
    }

    public function notice()
    {
        $url = Cookie::get("url");
        $this->assign("seo_title", "紧急公告");
        $this->redirect();
    }

    public function invite()
    {
        $page = Filter::int(Req::args('p'));
        $invite = $this->model->table("invite as i")
            ->fields("i.*,u.nickname,u.avatar,cu.real_name")
            ->join("left join user as u on i.invite_user_id = u.id LEFT JOIN customer AS cu ON i.invite_user_id=cu.user_id")
            ->where("i.user_id = " . $this->user['id'])
            ->order('i.createtime desc')
            ->findPage($page, 10);
        if($invite) {
            if($invite['data']!=null) {
                foreach ($invite['data'] as $k => $v) {
                    switch ($v['from']) {
                        case 'second-wap':
                            $invite['data'][$k]['from'] = '微信公众号支付';
                            break;
                        case 'alipay':
                            $invite['data'][$k]['from'] = '支付宝支付';
                            break;
                        case 'wechat':
                            $invite['data'][$k]['from'] = '邀请二维码';
                            break;
                        case 'android_weixin':
                            $invite['data'][$k]['from'] = 'APP微信支付';
                            break;
                        case 'android_alipay':
                            $invite['data'][$k]['from'] = 'APP支付宝支付';
                            break;
                        case 'ios_weixin':
                            $invite['data'][$k]['from'] = 'APP微信支付';
                            break;
                        case 'ios_alipay':
                            $invite['data'][$k]['from'] = 'APP支付宝支付';
                            break;
                        case 'admin':
                            $invite['data'][$k]['from'] = '系统生成';
                            break;
                        case 'web':
                            $invite['data'][$k]['from'] = '系统生成';
                            break;    
                        case 'jihuo':
                            $invite['data'][$k]['from'] = '激活码';
                            break;
                        case 'wap':
                            $invite['data'][$k]['from'] = 'PC端扫码';
                            break;
                        case 'active':
                            $invite['data'][$k]['from'] = 'H5拉新活动';
                            break;
                        case 'goods_qrcode':
                            $invite['data'][$k]['from'] = '商品二维码';
                            break;                                        
                        default:
                            $invite['data'][$k]['from'] = '微信支付';
                            break;
                    }
                    $shop = $this->model->table('district_shop')->where('owner_id='.$v['invite_user_id'])->find();
                    $promoter = $this->model->table('district_promoter')->where('user_id='.$v['invite_user_id'])->find();
                    if($shop && $promoter){
                        $invite['data'][$k]['role_type'] = '经销商';
                    }elseif(!$shop && $promoter){
                        $invite['data'][$k]['role_type'] = '代理商';
                    }else{
                        $invite['data'][$k]['role_type'] = '普通会员';
                    }
                }
            }
        }    
        $this->assign("invite", $invite);
        $this->assign('uid', $this->user['id']);
        $this->assign("seo_title", "我的邀请");
        $this->redirect();
    }

    public function myinvite()
    {
        $model = new Model();
        $model = new Model("user as us");
        $user_id = $this->user['id'];

        $user = $model->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.group_id,cu.user_id,cu.login_time,cu.mobile")->where("us.id=" . $user_id)->find();
        $this->assign('user', $user);
        $this->assign('uid', $user_id);
        $this->redirect();
    }

    public function attention()
    {
        $page = Filter::int(Req::args('p'));
        $attention = $this->model->table("attention as at")->fields("at.*,go.name,go.store_nums,go.img,go.sell_price,go.id as gid")->join("left join goods as go on at.goods_id = go.id")->where("at.user_id = " . $this->user['id'])->findPage($page);
        $this->assign("attention", $attention);
        $this->assign("seo_title", "我的关注");
        $this->redirect();
    }

    public function attention_del()
    {
        $id = Filter::int(Req::args("id"));
        if (is_array($id)) {
            $ids = implode(",", $id);
        } else {
            $ids = $id;
        }
        $this->model->table("attention")->where("id in($ids) and user_id=" . $this->user['id'])->delete();
        $this->redirect("attention");
    }

    public function attention_addcart()
    {
        $ids = Req::args("ids");
        $ids = array_filter(explode(',', $ids));
        if ($ids) {
            $cart = Cart::getCart();
            foreach ($ids as $key => $v) {
                $cart->addItem($v, 1);
            }
            echo JSON::encode(array('status' => 'success'));
        } else {
            echo JSON::encode(array('status' => 'fail'));
        }
    }

    public function attention_cancelattention()
    {
        $ids = Req::args("ids");
        $ids = array_filter(explode(',', $ids));
        if ($ids) {
            $this->model->table("attention")->where("id in(" . implode(',', $ids) . ") and user_id=" . $this->user['id'])->delete();
            echo JSON::encode(array('status' => 'success'));
        } else {
            echo JSON::encode(array('status' => 'fail'));
        }
    }

    //商品展示与商品状态修改
    public function order()
    {
        $notice = Session::get('notice');
        Session::clear('notice');
        $status = Filter::str(Req::args("status"));
        $config = Config::getInstance();
        $config_other = $config->get('other');
        $valid_time = array();
        $valid_time[0] = isset($config_other['other_order_delay']) ? intval($config_other['other_order_delay']) : 0;
        $valid_time[1] = isset($config_other['other_order_delay_group']) ? intval($config_other['other_order_delay_group']) : 120;
        $valid_time[2] = isset($config_other['other_order_delay_flash']) ? intval($config_other['other_order_delay_flash']) : 120;
        $valid_time[3] = isset($config_other['other_order_delay_bund']) ? intval($config_other['other_order_delay_bund']) : 0;
        $valid_time[5] = isset($config_other['other_order_delay_point']) ? intval($config_other['other_order_delay_point']) : 0;
        $valid_time[6] = isset($config_other['other_order_delay_pointflash']) ? intval($config_other['other_order_delay_pointflash']) : 0;
        $query = new Query('order');
        $where = array("user_id = " . $this->user['id'], 'is_del = 0', 'type !=8');
        switch ($status) {
            case "unpay":
                $where[] = "status <= '2'";
                break;
            case "undelivery":
                $where[] = "status = '3'";
                $where[] = "delivery_status = '0'";
                break;
            case "unreceived":
                $where[] = "status = '3'";
                $where[] = "delivery_status = '1'";
            case "uncomment":

                break;
        }
        if ($where) {
            $where = implode(' AND ', $where);
        }
        $query->where = $where;
        $query->order = "id desc";
        $query->page = 1;
        $orders = $query->find();
        $order_id = array();
        $now = time();
        $ids = array();
        foreach ($orders as $order) {
            if ($order['pay_status'] == 0 && $order['status'] <= 3) {
                if (isset($valid_time[$order['type']])) {
                    $time = $valid_time[$order['type']] * 60;
                    if ($time && $now - strtotime($order['create_time']) >= $time) {
                        $order_id[] = $order['id'];
                    }
                }
            }
            $ids[] = $order['id'];
        }
        $orders = $query->find();
        $goodslist = array();
        if ($ids) {
            $list = $this->model->table("order_goods AS og")
                ->fields("og.product_id,og.spec,og.order_id,og.goods_id,og.goods_nums,og.goods_price,go.img,go.imgs,go.name")
                ->join("goods AS go ON og.goods_id=go.id")
                ->where("order_id IN (" . implode(',', $ids) . ")")
                ->findAll();
            foreach ($list as $k => $v) {
                $v['speclist'] = implode(' / ', Common::spec($v['spec']));
                $goodslist[$v['order_id']][] = $v;
            }
        }
        foreach ($orders as $k => &$v) {
            $v['goodslist'] = isset($goodslist[$v['id']]) ? $goodslist[$v['id']] : array();
        }
        unset($v);
        //处理过期订单状态
        if (count($order_id) > 0) {
            $ids = implode(',', $order_id);
            $order_model = new Model('order');
            $data = array("status" => 6);
            $order_model->where("id in (" . $ids . ")")->data($data)->update();
            $point_order = $order_model->where("id in (" . $ids . ") and type in (5,6)")->findAll();
            if ($point_order) {
                foreach ($point_order as $v) {
                    if ($v['pay_point'] > 0) {
                        $this->model->table("customer")->where("user_id=" . $v['user_id'])->data(array("point_coin" => "`point_coin`+" . $v['pay_point']))->update();
                        Log::pointcoin_log($v['pay_point'], $v['user_id'], $v['order_no'], "取消订单，退回积分", 2);
                    }
                }
            }
        }
        $index_notice = $this->model->table('index_notice')->where('id=1')->find();
        if ($index_notice) {
            $this->assign('index_notice', $index_notice);
        }
        $this->assign("status", $status);
        $this->assign("where", $where);
        $this->assign("orderlist", $orders);
        $this->assign("pagelist", $query->pageBar(2));
        $this->assign("notice", $notice);
        $this->assign("seo_title", "订单管理");
        $this->redirect();
    }

    protected function order_status($item)
    {
        $status = $item['status'];
        $pay_status = $item['pay_status'];
        $delivery_status = $item['delivery_status'];
        $order_type = $item['type'];
        $str = '';
        $btn = '';
        //status:1等待付款 2待审核(待付款) 3已付款 4已完成 5已取消 6已作废
        switch ($status) {
            case '1':

                $str = '<span class="text-danger">等待付款</span>';
                $btn = '<a href="' . Url::urlFormat("/simple/order_status/order_id/$item[id]") . '" class="btn btn-main btn-mini">立即付款</a><a href="javascript:;" class="btn btn-gray btn-mini " onclick="order_delete(' . $item['id'] . ')">删除订单</a>';

                break;
            case '2':
                if ($pay_status == 1)
                    $str = '<span class="text-warning">等待审核</span>';
                else {
                    //关闭货到付款的检测
                    //$payment_info = Common::getPaymentInfo($item['payment']);
                    if (FALSE && $payment_info['class_name'] == 'received') {
                        $str = '<span class="text-warning">等待审核</span>';
                        $btn = '<a href="' . Url::urlFormat("/simple/order_status/order_id/$item[id]") . '" class="btn btn-main btn-mini">另选支付</a>';
                    } else {
                        if ($order_type == 4) {

                            $str = '<span class="text-danger">等待付款</span>';
                            $btn = '<a href="javascript:;" class="btn  btn-gray btn-mini " style="margin-bottom:10px;" onclick="order_delete(' . $item['id'] . ')">删除订单</a><br><a href="' . Url::urlFormat("/simple/order_status/order_id/$item[id]") . '" class="btn btn-main btn-mini">立即付款</a>';

                        } else {
                            $str = '<span class="text-danger">等待付款</span>';
                            $btn = '<a href="javascript:;" class="btn  btn-gray btn-mini " style="margin-bottom:10px;"  onclick="order_delete(' . $item['id'] . ')">删除订单</a><br><a href="' . Url::urlFormat("/simple/order_status/order_id/$item[id]") . '" class="btn btn-main btn-mini">立即付款</a>';
                        }
                    }
                }
                break;
            case '3':
                if ($delivery_status == 0 && $order_type != 8) {
                    $str = '<span class="text-info">等待发货</span>';
                } else if ($delivery_status == 0 && $order_type == 8) {
                    $this->model->table('order')->data(array('delivery_status' => 2))->where('id=' . $item['id'])->update();
                    $str = '<span class="text-info">线下订单已成功支付</span>';
                } else if ($delivery_status == 1) {
                    $str = '<span class="text-info">已发货</span>';
                    $btn = '<a href="javascript:;" class="btn btn-main btn-mini" onclick="order_sign(' . $item['id'] . ')">确认收货</a>';
                }
                if ($pay_status == 3)
                    $str = '<span class="text-success">已退款</span>';
                break;
            case '4':
                $str = '<span class="text-success"><b>已完成</b></span>';
                if ($pay_status == 3)
                    $str = '<span class="text-success">已退款</span>';
                break;
            case '5':
                $str = '<span class="text-gray"><s>已取消</s></span>';
                if ($pay_status == 3)
                    $str = '<span class="text-success">已退款</span>';
                $btn = '<a href="javascript:;" class="btn btn-gray btn-mini" onclick="order_delete(' . $item['id'] . ')">删除订单</a>';
                break;
            case '6':
                $str = '<span class="text-gray"><s>已作废</s></span>';
                $btn = '<a href="javascript:;" class="btn btn-gray btn-mini " onclick="order_delete(' . $item['id'] . ')">删除订单</a>';
                break;
            default:
                # code...
                break;
        }
        return array($str, $btn);
    }

    public function order_detail()
    {
        $id = Filter::int(Req::args("id"));
        $order_model = $this->model->table('order')->where("id=$id")->find();
        // if($order_model['type']==8){
        //         $this->model->table('order')->where("id=$id")->data(array('status'=>3,'pay_status'=>1,'delivery_status'=>2))->update();
        //     }
        $order = $this->model->table("order as od")->fields("od.*,pa.pay_name")->join("left join payment as pa on od.payment = pa.id")->where("od.id = $id and od.user_id=" . $this->user['id'])->find();
        if ($order) {
            $invoice = $this->model->table("doc_invoice as di")->fields("di.*,ec.code as ec_code,ec.name as ec_name,ec.alias as ec_alias")->join("left join express_company as ec on di.express_company_id = ec.id")->where("di.order_id=" . $id)->findAll();
            $order_goods = $this->model->table("order_goods as og ")->fields("og.*,og.id as order_goods_id,go.*,pr.*")->join("left join goods as go on og.goods_id = go.id left join products as pr on og.product_id = pr.id")->where("og.order_id=" . $id)->findAll();
            $area_ids = $order['province'] . ',' . $order['city'] . ',' . $order['county'];
            if ($area_ids != '')
                $areas = $this->model->table("area")->where("id in ($area_ids)")->findAll();
            $parse_area = array();
            foreach ($areas as $area) {
                $parse_area[$area['id']] = $area['name'];
            }
            $shopgoods = array();
            $express_ids = array();
            foreach ($order_goods as $k => $v) {
                $express_ids[] = $v['express_company_id'];
                $v['speclist'] = implode(' / ', Common::spec($v['spec']));
                $shopgoods[$v['shop_id']] = isset($shopgoods[$v['shop_id']]) ? $shopgoods[$v['shop_id']] : array();
                $shopgoods[$v['shop_id']][] = $v;
            }
            //查询物流公司名称
            $expresslist = array();
            if ($express_ids) {
                $tmplist = $this->model->table("express_company")->where("id IN (" . implode(',', $express_ids) . ")")->findAll();
                foreach ($tmplist as $k => $v) {
                    $expresslist[$v['id']] = $v;
                }
            }
            $this->assign("expresslist", $expresslist);
            $this->assign("shopgoods", $shopgoods);
            $this->assign("parse_area", $parse_area);
            $this->assign("order_goods", $order_goods);
            $this->assign("invoice", $invoice);
            $this->assign("order", $order);
            $this->redirect();
        } else {
            Tiny::Msg($this, 404);
        }
    }

    public function order_details()
    {
        $id = Filter::int(Req::args("id"));
        $user_id = Filter::int($this->user['id']);
        $order = $this->model->table("order_offline")->where("id = $id and user_id= $user_id")->find();
        if (!$order) {
            $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '支付信息错误', "content" => "抱歉，找不到您的订单信息"));
            exit();
        }
        $shop = $this->model->table('customer')->fields('real_name')->where('user_id=' . $order['shop_ids'])->find();
        if ($shop) {
            $shopname = $shop['real_name'];
        } else {
            $shopname = '未知商家';
        }
        $paytypelist = $this->model->table('payment as pa')->fields("pa.*,pp.logo,pp.class_name")->join("left join pay_plugin as pp on pa.plugin_id = pp.id")->where("pa.status = 0 and pa.plugin_id=9 and pa.client_type =2")->order("pa.sort desc")->findAll();
        if ($paytypelist) {
            $paytype['payment'] = $paytypelist[0]['id'];
            $paytype['payname'] = $paytypelist[0]['pay_name'];
            $this->assign("paytype", $paytype);
        }

        $pay_status = $order['pay_status'];

        $this->assign("paytypelist", $paytypelist);
        $this->assign('pay_status', $pay_status);
        $this->assign('shopname', $shopname);
        $this->assign("order", $order);
        $this->assign("seo_title", "支付成功");
        $this->redirect();

    }

    //订单签收
    public function order_sign()
    {
        $id = Filter::int(Req::args("id"));
        $info = array('status' => 'fail');
        $result = $this->model->table('order')->where("id=$id and user_id=" . $this->user['id'] . " and status=3 and pay_status=1 and delivery_status=1")->data(array('delivery_status' => 2, 'status' => 4, 'completion_time' => date('Y-m-d H:i:s')))->update();
        if ($result) {
            $info = array('status' => 'success');
            //提取购买商品信息
            $products = $this->model->table('order as od')->join('left join order_goods as og on od.id=og.order_id')->where('od.id=' . $id)->findAll();
            foreach ($products as $product) {
                $data = array('goods_id' => $product['goods_id'], 'user_id' => $this->user['id'], 'order_id' => $product['order_id'], 'order_no' => $product['order_no'], 'buy_time' => $product['create_time']);
                $this->model->table('review')->data($data)->insert();
            }
        }
        echo JSON::encode($info);
    }

    //地址列表
    public function address()
    {
        // if($this->user['id']==31988){
        //     $wechatcfg = $this->model->table("oauth")->where("class_name='WechatOAuth'")->find();
        //     $wechat = new WechatMenu($wechatcfg['app_key'], $wechatcfg['app_secret'], '');
        //     $token = $wechat->getAccessToken();
        //     $oauth = $this->model->table('oauth_user')->fields('open_id')->where('user_id='.$this->user['id'])->find();
        //     $openid = $oauth['open_id'];
        //     $subscribe_msg = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=$token&openid=$openid";
        //     $subscribe = json_decode(file_get_contents($subscribe_msg));
        //     $gzxx = $subscribe->subscribe;
        //     if($gzxx==1){
        //         var_dump($gzxx);die;
        //     }

        // }
        $model = new Model("address");
        $address = $model->where("user_id=" . $this->user['id'])->order("id desc")->findAll();
        $area_ids = array();
        foreach ($address as $addr) {
            $area_ids[$addr['province']] = $addr['province'];
            $area_ids[$addr['city']] = $addr['city'];
            $area_ids[$addr['county']] = $addr['county'];
        }
        $area_ids = implode(',', $area_ids);
        $areas = array();
        if ($area_ids != '')
            $areas = $model->table("area")->where("id in ($area_ids)")->findAll();
        $parse_area = array();
        foreach ($areas as $area) {
            $parse_area[$area['id']] = $area['name'];
        }
        $this->assign("address", $address);
        $this->assign("parse_area", $parse_area);
        $this->assign("seo_title", "地址管理");
        $this->redirect();
    }

    //编辑地址
    public function address_other()
    {
        Session::set("order_status", Req::args());
        $id = Filter::int(Req::args("id"));
        $url = Req::args("url");
        $this->assign("url", $url);
        $this->assign("seo_title", $id ? "修改地址" : '添加地址');
        if ($id) {
            $model = new Model("address");
            $data = $model->where("id = $id and user_id =" . $this->user['id'])->find();
            $areas = $this->model->table("area")->where("id in({$data['province']},{$data['city']},{$data['county']})")->findAll();
            $parse_area = array();
            foreach ($areas as $area) {
                $parse_area[$area['id']] = $area['name'];
            }
            $this->assign("address", implode(' ', $parse_area));

            $this->redirect("address_other", false, $data);
        } else
            $this->redirect();
    }

    //保存地址
    public function address_save()
    {
        $rules = array('addr:required:内容不能为空！', 'accept_name:required:收货人姓名不能为空!,mobile:mobi:手机格式不正确!', 'province:[1-9]\d*:选择地区必需完成', 'city:[1-9]\d*:选择地区必需完成', 'county:[1-9]\d*:选择地区必需完成');
        $info = Validator::check($rules);

        if (!is_array($info) && $info == true) {
            Filter::form(array('sql' => 'accept_name|mobile|phone', 'txt' => 'addr', 'int' => 'province|city|county|zip|is_default|id'));
            $is_default = Filter::int(Req::args("is_default"));
            if ($is_default == 1) {
                $this->model->table("address")->where("user_id=" . $this->user['id'])->data(array('is_default' => 0))->update();
            } else {
                Req::args("is_default", "0");
            }

            Req::args("user_id", $this->user['id']);
            $id = Filter::int(Req::args('id'));
            if ($id) {
                $this->model->table("address")->where("id=$id and user_id=" . $this->user['id'])->update();
            } else {
                $obj = $this->model->table("address")->where('user_id=' . $this->user['id'])->fields("count(*) as total")->find();
                if ($obj && $obj['total'] >= 20) {
                    $this->assign("msg", array("error", '地址最大允许添加20个'));
                    $this->redirect("address_other", false, Req::args());
                    exit();
                } else {
                    $address_id = $this->model->table("address")->insert();
                    $order_status = Session::get("order_status");
                    $order_status['address_id'] = $address_id;
                    Session::set("order_status", $order_status);
                }
            }

            $this->assign("msg", array("success", "地址编辑成功!"));
            //$this->redirect("address_other",false);
            $url = Req::args("url");
            $url = $url ? $url : "address";
            $this->redirect($url);
        } else {
            $this->assign("msg", array("error", $info['msg']));
            $this->redirect("address_other", false, Req::args());
        }
    }

    public function address_del()
    {
        $id = Filter::int(Req::args("id"));
        $this->model->table("address")->where("id=$id and user_id=" . $this->user['id'])->delete();
        $url = Req::args("url");
        $url = $url ? $url : "address";
        $this->redirect($url);
        $this->redirect("address");
    }

    public function address_wechat()
    {
        $code = -1;
        $content = NULL;
        $one = $this->model->table("address")->where("user_id=" . $this->user['id'])->find();

        if (!$one) {
            $username = Filter::sql($_POST['userName']);
            $mobile = Filter::sql($_POST['telNumber']);
            $zip = Filter::sql($_POST['addressPostalCode']);
            $addr = Filter::sql($_POST['addressDetailInfo']);
            $nationalcode = Filter::sql($_POST['nationalCode']);
            $countyone = $cityone = $provinceone = null;
            $province = Filter::sql($_POST['proviceFirstStageName']);
            $city = Filter::sql($_POST['addressCitySecondStageName']);
            $county = Filter::sql($_POST['addressCountiesThirdStageName']);
            $province = substr($province, 0, strpos($province, "市"));
            // $countyone = $this->model->table("area")->where("id='{$nationalcode}'")->find();
            // if ($countyone) {
            //     $cityone = $this->model->table("area")->where("id='{$countyone['parent_id']}'")->find();
            //     if ($cityone) {
            //         $provinceone = $this->model->table("area")->where("id='{$cityone['parent_id']}'")->find();
            //     }
            // }

            $area_info = $this->model->table("area as a1")
                ->join("left join area as a2 on a1.id = a2.parent_id left join area as a3 on a2.id=a3.parent_id")
                ->where("a1.name like '{$province}%' and a1.parent_id = 0 and a2.name = '{$city}' and a3.name = '{$county}'")
                ->fields("a1.id as province,a2.id as city,a3.id as county")->find();

            if ($area_info) {
                $data = array(
                    'user_id' => $this->user['id'],
                    'accept_name' => $username,
                    'mobile' => $mobile,
                    'phone' => '',
                    'province' => $area_info['province'],
                    'city' => $area_info['city'],
                    'county' => $area_info['county'],
                    'zip' => $zip,
                    'addr' => $addr,
                    'is_default' => 1
                );
                $address_id = $this->model->table("address")->data($data)->insert();
                $code = 0;
                $content = $data;
                $content['id'] = $address_id;
            }
        }
        echo json_encode(array('code' => $code, 'conent' => $content));
        exit;
    }

    public function index()
    {
        $notice = Session::get('notice');
        Session::clear('notice');
        $id = $this->user['id'];
        $customer = $this->model->table("customer as cu")->fields("cu.*,gr.name as gname")->join("left join grade as gr on cu.group_id = gr.id")->where("cu.user_id = $id")->find();
        if(!$customer) {
            $open_id = $this->user['open_id'];
            $oauth_user = $this->model->table('oauth_user')->fields('user_id')->where("open_id='$open_id'")->find();
            var_dump($open_id);
            var_dump($oauth_user);die;
            $customer = $this->model->table("customer as cu")->fields("cu.*,gr.name as gname")->join("left join grade as gr on cu.group_id = gr.id")->where("cu.user_id = ".$oauth_user['user_id'])->find();
        }
        $orders = $this->model->table("order")->where("user_id = $id and is_del = 0 and type !=8")->findAll();
        $order = array('amount' => 0, 'todayamount' => 0, 'pending' => 0, 'undelivery' => 0, 'unreceived' => 0, 'uncomment' => 0);
        foreach ($orders as $obj) {
            if ($obj['status'] < 5 && ($obj['payment'] == 1 || $obj['payment'] == 12 || $obj['payment'] == 13 || $obj['payment'] == 15)) {
                if ($obj['type'] == 4) {
                    $obj['order_amount'] = $obj['otherpay_amount'];
                }
                $order['amount'] += $obj['order_amount'];
                if (strtotime($obj['pay_time']) >= strtotime('today')) {
                    $order['todayamount'] += $obj['order_amount'];
                }
            }
            if ($obj['status'] == 4) {

            } else if ($obj['status'] < 3) {
                $order['pending']++;
            } else if ($obj['status'] == 3) {
                if ($obj['delivery_status'] == 0) {
                    $order['undelivery']++;
                } else if ($obj['delivery_status'] == 1) {
                    $order['unreceived']++;
                }
            }
        }
        $comment = $this->model->table("review")->fields("count(*) as num")->where("user_id = $id and status=0")->find();
        $this->assign("comment", $comment);
        $where = "user_id = " . $this->user['id'] . " and is_send = 1";
        $where .= " and status = 0 and '" . date("Y-m-d H:i:s") . "' <=end_time";
        $voucherlist = $this->model->table("voucher")->where($where)->order("id desc")->limit("0,2")->findAll();
        //upyun设置
        $upyun = Config::getInstance()->get("upyun");

        $options = array(
            'bucket' => $upyun['upyun_bucket'],
            'save-key' => "/data/uploads/head/" . $this->user['id'] . "{.suffix}",
            'allow-file-type' => 'jpg,gif,png', // 文件类型限制，如：jpg,gif,png
            'expiration' => time() + $upyun['upyun_expiration'],
            'notify-url' => $upyun['upyun_notify-url'],
            'ext-param' => "avatar:{$id}",
        );
        $policy = base64_encode(json_encode($options));
        $signature = md5($policy . '&' . $upyun['upyun_formkey']);

        $options['policy'] = $policy;
        $options['signature'] = $signature;
        $options['action'] = $upyun['upyun_uploadurl'];
        $options['img_host'] = $upyun['upyun_cdnurl'];

        $change_info = $this->model->table("customer as c")->join("left join oauth_user as o on c.user_id = o.user_id")->fields("c.user_id,c.mobile,o.other_user_id")->where("o.oauth_type='wechat' and c.user_id=" . $this->user['id'])->find();
        if (empty($change_info)) {

        } else if ($change_info['mobile'] && $change_info['other_user_id']) {
            $this->assign("open_change", true);
        } else if (!$change_info['mobile'] && $change_info['other_user_id']) {
            $this->assign("open_change", true);
        } else if (!$change_info['mobile'] && !$change_info['other_user_id']) {
            $this->assign('open_bind', true);
        }
        $is_promoter = false;
        $promoter = Promoter::getPromoterInstance($this->user['id']);
        if (is_object($promoter) && $promoter->role_type == 2) {
            $is_promoter = true;
        }
        $is_hirer = false;
        $district_id = 0;
        $hirer = $this->model->table("district_shop")->where("owner_id=" . $this->user['id'])->find();
        if ($hirer) {
            $is_hirer = true;
            $district_id = $hirer['id'];
        }
        $this->assign('district_id', $district_id);
        //签到
        $sign_in_set = Config::getInstance()->get('sign_in_set');

        $index_notice = $this->model->table('index_notice')->where('id=1')->find();
        if ($index_notice) {
            $this->assign('index_notice', $index_notice);
        }
        $bankcard = $this->model->table('bankcard')->where('user_id=' . $this->user['id'])->findAll();
        if ($bankcard) {
            $card_bind = 1;
        } else {
            $card_bind = 0;
        }
        $this->assign('card_bind', $card_bind);
        $this->assign("sign_in_open", $sign_in_set['open']);
        $this->assign("random", rand(1000, 9999));
        $this->assign('is_hirer', $is_hirer);
        $this->assign('is_promoter', $is_promoter);
        $this->assign("option", $options);
        $this->assign("voucherlist", $voucherlist);
        $this->assign("order", $order);
        $this->assign("customer", $customer);
        $this->assign("notice", $notice);
        //$this->assign('id', $index);
        $this->redirect();
    }

    public function refreshinfo()
    {
        $id = $this->user['id'];
        $obj = $this->model->table("user as us")->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.group_id,cu.login_time,cu.mobile")->where("us.id=$id")->find();
        $this->safebox->set('user', $obj, $this->cookie_time);

        echo json_encode(array('status' => 'success'));
        exit;
    }

    //移动端的钱袋页
    public function asset()
    {
        $id = $this->user['id'];
        $customer = $this->model->table("customer as cu")->fields("cu.*,gr.name as gname")->join("left join grade as gr on cu.group_id = gr.id")->where("cu.user_id = $id")->find();
        // $customer['balance']=$customer['balance']+$customer['offline_balance'];

        //只记录余额支付的消费统计
        $orders = $this->model->table("order as o")->join("payment as p on o.payment = p.id ")->where("o.user_id = $id and p.plugin_id in(1,20)")->findAll();
        $order = array('amount' => 0, 'todayamount' => 0, 'pending' => 0, 'undelivery' => 0, 'unreceived' => 0, 'uncomment' => 0);
        foreach ($orders as $obj) {
            if ($obj['status'] < 5 && $obj['pay_status'] == 1) {
                $order['amount'] += $obj['order_amount'];
                if (strtotime($obj['pay_time']) >= strtotime('today')) {
                    $order['todayamount'] += $obj['order_amount'];
                }
            }
        }

        //充值礼品判断
        $info = $this->model->table("recharge_presentlog")->where("user_id =" . $this->user['id'] . " and status=0")->find();
        if ($info) {
            $activity = $this->model->table("recharge_activity")->where("id = 1")->fields("accept_end_time")->find();
            if (strtotime($activity['accept_end_time']) > time() && $info['status'] == 0) {
                $this->assign("show_message", true);
                $this->assign("present", $info['present']);
            } else if (strtotime($activity['accept_end_time']) < time() && $info['status'] == 0) {
                $this->model->query("update tiny_recharge_presentlog set status='-1' where user_id =" . $this->user['id']);
            }
        }
        //判断是否是商家
        $shop = $this->model->table('district_promoter')->where('user_id=' . $id)->find();
        if ($shop) {
            $is_shop = 1;
            if ($shop['unique_code'] == 1) {
                $show_code = 1;
            } else {
                $show_code = 0;
            }
        } else {
            $is_shop = 0;
            $show_code = 0;
        }
        $this->assign('is_shop', $is_shop);
        $this->assign('show_code', $show_code);
        $this->assign("order", $order);
        $this->assign("customer", $customer);
        $this->assign("id", $id);
        $this->assign("seo_title", "钱袋");
        $this->redirect();
    }

    //充值中心
    public function recharge_center()
    {
        $notice = Session::get('notice');
        Session::clear('notice');
        $package = Filter::int(Req::args('package'));
        $pid = Filter::int(Req::args('pid'));
        if ($package && $pid) {
            $this->assign("package", $package);
            $this->assign("pid", $pid);
        }
        //地址
        $area_ids = array();
        $address = $this->model->table("address")->where("user_id=" . $this->user['id'])->order("is_default desc")->findAll();
        foreach ($address as $add) {
            $area_ids[$add['province']] = $add['province'];
            $area_ids[$add['city']] = $add['city'];
            $area_ids[$add['county']] = $add['county'];
        }
        $area_ids = implode(",", $area_ids);
        $areas = array();
        if ($area_ids != '')
            $areas = $this->model->table("area")->where("id in($area_ids )")->findAll();
        $parse_area = array();
        foreach ($areas as $area) {
            $parse_area[$area['id']] = $area['name'];
        }
        $this->assign("parse_area", $parse_area);
        $this->assign('address', $address);
        //支付列表
        $paytypelist = Common::getValidPayList();
        $paytypeone = reset($paytypelist);
        $this->assign("paytypeone", $paytypeone);
        $this->assign("paytypelist", $paytypelist);

        $config = Config::getInstance();
        $package_set = $config->get("recharge_package_set");
        if (is_array($package_set)) {
            if (isset($package_set[4]['gift']) && $package_set[4]['gift'] != '') {
                $where = implode(',', array_reverse(explode("|", $package_set[4]['gift'])));
                $select4 = $this->model->table("products as p")->join("goods as g on p.goods_id=g.id")->where("p.id in ({$where})")->fields("p.id,g.img,g.name,g.id as goods_id")->order("field(p.id,$where)")->findAll();
                $this->assign("select4", $select4);
            }
        }
        $index_notice = $this->model->table('index_notice')->where('id=1')->find();
        if ($index_notice) {
            $this->assign('index_notice', $index_notice);
        }
        $this->assign("notice", $notice);
        $this->assign('package_set', $package_set);
        $this->assign("seo_title", '充值中心');
        $this->redirect();
    }

    //余额记录
    public function balance_log()
    {
        $customer = $this->model->table("customer")->where("user_id=" . $this->user['id'])->find();
        $this->assign("customer", $customer);
        $this->assign('seo_title', '余额记录');
        $this->redirect();
    }

    public function check_identity()
    {
        $verified = $this->verifiedType();
        $customer = $this->model->table('customer')->fields('pay_password')->where("user_id=" . $this->user['id'])->find();
        $pay_password = $customer['pay_password'];
        $this->assign('pay_password', $pay_password);
        $this->redirect();
    }

    public function verified()
    {
        $code = Req::args('code');
        $recode = Req::args('recode');
        $type = Req::args('type');
        $obj = Req::args('obj');
        $pay_password = Req::args('pay_password');
        $obj = $this->updateObj($obj); //默认是修改登陆密码

        if ($pay_password == '' && $recode != '') {
            if ($code != $recode) {
                $info = array('field' => 'code', 'msg' => '两次密码输入不一致！');
                $this->assign("invalid", $info);
                $this->redirect("/ucenter/check_identity/obj/" . $obj . "/type/" . $type, false);
            } else {
                $pay_validcode = CHash::random(8);
                $password = CHash::md5($code, $pay_validcode);
                $this->model->table('customer')->data(array('pay_password' => $password, 'pay_validcode' => $pay_validcode))->where('user_id=' . $this->user['id'])->update();
                $this->redirect('/ucenter/update_obj_success/obj/' . $obj);
            }
        }


        $verifiedInfo = Session::get("verifiedInfo");
        if (isset($verifiedInfo['code']) && $code == $verifiedInfo['code']) {
            $verifiedInfo['obj'] = $obj;
            Session::set("verifiedInfo", $verifiedInfo);
            $this->redirect("/ucenter/update_obj/r/" . $verifiedInfo['random']);
        } else {
            $customer = $this->model->table('customer')->where("user_id=" . $this->user['id'])->find();
            if ($customer['pay_password'] == CHash::md5($code, $customer['pay_validcode'])) {
                $random = CHash::random(20, 'char');
                $verifiedInfo = array('code' => $code, 'time' => time(), 'type' => 'paypwd', 'obj' => $obj, 'random' => $random);
                Session::set("verifiedInfo", $verifiedInfo);
                $this->redirect("/ucenter/update_obj/r/" . $verifiedInfo['random']);
            } else {
                $info = array('field' => 'code', 'msg' => '验证码错误！');
                if ($type == 'paypwd') {
                    $info = array('field' => 'code', 'msg' => '支付密码错误！');
                }
                $this->assign("invalid", $info);
                $this->redirect("/ucenter/check_identity/obj/" . $obj . "/type/" . $type, false);
            }
        }
    }

    public function update_obj()
    {
        $r = Req::args('r');
        $verifiedInfo = Session::get("verifiedInfo");

        if ($r == $verifiedInfo['random'] && $r != null) {
            $this->assign("obj", $verifiedInfo['obj']);
            $this->redirect();
        } else {
            $this->redirect("/ucenter/check_identity");
        }
    }

    public function activate_obj()
    {
        $obj = Req::args('obj');
        $obj = $this->updateObj($obj);
        $model = new Model('user as us');
        $userInfo = $model->join('left join customer as cu on us.id = cu.user_id')->where('cu.user_id = ' . $this->user['id'])->find();
        $random = CHash::random(20, 'char');
        $verifiedInfo = array('obj' => $obj, 'random' => $random);
        if ($obj == 'email' && $userInfo['email_verified'] == 0) {
            Session::set('verifiedInfo', $verifiedInfo);
            $this->redirect("/ucenter/update_obj/r/" . $verifiedInfo['random']);
        } elseif ($obj == 'mobile' && $userInfo['mobile_verified'] == 0) {
            Session::set('verifiedInfo', $verifiedInfo);
            $this->redirect("/ucenter/update_obj/r/" . $verifiedInfo['random']);
        } elseif ($obj == 'paypwd' && $userInfo['pay_password_open'] == 1) {
            Session::set('verifiedInfo', $verifiedInfo);
            $this->redirect("/ucenter/update_obj/r/" . $verifiedInfo['random']);
        } else {
            $this->redirect('/ucenter/safety');
        }
    }

    public function update_obj_act()
    {
        $verifiedInfo = Session::get("verifiedInfo");
        $obj = $verifiedInfo['obj'];
        $info = array();
        if ($obj == 'password' || $obj == 'paypwd') {
            $password = Req::args('password');
            $repassword = Req::args('repassword');
            if ($password == $repassword) {
                if ($obj == 'password') {
                    $validcode = CHash::random(8);
                    $this->model->table('user')->data(array('password' => CHash::md5($password, $validcode), 'validcode' => $validcode))->where('id=' . $this->user['id'])->update();
                    Session::clear('verifiedInfo');
                    $this->redirect('/ucenter/update_obj_success/obj/' . $obj);
                    exit;
                } elseif ($obj == 'paypwd') {
                    $validcode = CHash::random(8);
                    $this->model->table('customer')->data(array('pay_password' => CHash::md5($password, $validcode), 'pay_validcode' => $validcode, 'pay_password_open' => 1))->where('user_id=' . $this->user['id'])->update();
                    Session::clear('verifiedInfo');
                    $this->redirect('/ucenter/update_obj_success/obj/' . $obj);
                    exit;
                }
            } else {
                $info = array('field' => 'repassword', 'msg' => '两次密码不一致。');
            }
        } else if ($obj == 'mobile' || $obj == 'email') {
            $code = Req::args('code');
            $account = Req::args('account');
            $activateObj = Session::get('activateObj');
            $newCode = $activateObj['code'];
            $newAccount = $activateObj['obj'];
            if ($code == $newCode && $account == $newAccount) {
                if ($obj == 'email' && Validator::email($account)) {
                    $result = $this->model->table('user')->where("email='" . $account . "' and id != " . $this->user['id'])->find();
                    if (!$result) {
                        $this->model->table('user')->data(array('email' => $account))->where('id=' . $this->user['id'])->update();
                        $this->model->table('customer')->data(array('email_verified' => 1))->where('user_id=' . $this->user['id'])->update();
                        Session::clear('verifiedInfo');
                        Session::clear('activateObj');
                        $this->redirect('/ucenter/update_obj_success/obj/' . $obj);
                        exit;
                    } else {
                        $info = array('field' => 'account', 'msg' => '此邮箱已被其它用户占用，无法修改为此邮箱。');
                    }
                } elseif ($obj == 'mobile' && Validator::mobi($account)) {
                    $result = $this->model->table('customer')->where("mobile ='" . $account . "'" . '  and user_id!=' . $this->user['id'])->find();
                    $password = Req::args('password');
                    $repassword = Req::args('repassword');
                    if ($password != $repassword) {
                        $info = array('field' => 'repassword', 'msg' => '两次登录密码不一致。');
                    }
                    $validcode = CHash::random(8);
                    $this->model->table('user')->data(array('password' => CHash::md5($password, $validcode), 'validcode' => $validcode))->where('id=' . $this->user['id'])->update();
                    if (!$result) {
                        $this->model->table('customer')->data(array('mobile' => $account, 'mobile_verified' => 1))->where('user_id=' . $this->user['id'])->update();
                        Session::clear('verifiedInfo');
                        Session::clear('activateObj');
                        $this->redirect('/ucenter/update_obj_success/obj/' . $obj);
                        exit;
                    } else {
                        $user_id = $this->user['id'];
                        $mobile = $account;
                        $had_bind= $this->model->table("customer")->where("mobile='{$mobile}' and status=1")->findAll();
                        if($had_bind) {
                            foreach ($had_bind as $key => $value) {
                                $wechat = $this->model->table('oauth_user')->where("user_id=".$value['user_id']." and oauth_type='wechat'")->find();
                                if($wechat) {
                                    //微信公众号已绑定
                                    $this->redirect("/index/msg", false, array('type' => 'fail', 'msg' => '该手机号已被绑定过了'));
                                    exit;
                                }
                                $weixin = $this->model->table('oauth_user')->where("user_id=".$value['user_id']." and oauth_type='weixin'")->find();
                                if($weixin) {
                                    //微信app已绑定
                                    $promoter1 = $this->model->table('district_promoter')->where('user_id='.$user_id)->find();
                                    $promoter2 = $this->model->table('district_promoter')->where('user_id='.$weixin['user_id'])->find();
                                    //智能分配微信账号
                                    if($promoter1 || $promoter2) {
                                        if($promoter1) { //分配$user_id账号
                                            $customer = $this->model->table('customer')->where('user_id=' . $weixin['user_id'])->find();
                                            $this->model->table('customer')->data(array('mobile' => $mobile, 'mobile_verified' => 1,'balance'=>"`balance`+({$customer['balance']})",'offline_balance'=>"`offline_balance`+({$customer['offline_balance']})"))->where('user_id=' . $user_id)->update();
                                            $this->model->table('customer')->data(array('status' => 0))->where('user_id=' . $weixin['user_id'])->update();
                                            $this->model->table('oauth_user')->data(array('other_user_id' => $weixin['user_id']))->where('user_id=' . $user_id)->update();
                                            $last_id = $user_id;    
                                        } else { //分配$weixin['user_id']账号
                                            $customer = $this->model->table('customer')->where('user_id=' . $user_id)->find();
                                            $this->model->table('customer')->data(array('mobile' => $mobile, 'mobile_verified' => 1,'balance'=>"`balance`+({$customer['balance']})",'offline_balance'=>"`offline_balance`+({$customer['offline_balance']})"))->where('user_id=' . $weixin['user_id'])->update();
                                            $this->model->table('customer')->data(array('status' => 0))->where('user_id=' . $user_id)->update();
                                            $this->model->table('oauth_user')->data(array('other_user_id' => $user_id))->where('user_id=' . $user_id)->update();
                                            $this->model->table('oauth_user')->data(array('user_id' => $weixin['user_id']))->where('other_user_id=' . $user_id)->update();
                                            $last_id = $weixin['user_id'];  
                                        }
                                    } else {
                                        $customer1 = $this->model->table('customer')->where('user_id=' . $user_id)->find();
                                        $customer2 = $this->model->table('customer')->where('user_id=' . $value['user_id'])->find();
                                        //已注册时间早的为主
                                        if(strtotime($customer1['reg_time'])<strtotime($customer2['reg_time'])) {
                                            $this->model->table('customer')->data(array('mobile' => $mobile, 'mobile_verified' => 1,'balance'=>"`balance`+({$customer2['balance']})",'offline_balance'=>"`offline_balance`+({$customer2['offline_balance']})"))->where('user_id=' . $user_id)->update();
                                            $this->model->table('customer')->data(array('status' => 0))->where('user_id=' . $value['user_id'])->update();   
                                            $this->model->table('oauth_user')->data(array('other_user_id' => $value['user_id']))->where('user_id=' . $user_id)->update();
                                            $last_id = $user_id;
                                        } else {
                                            $this->model->table('customer')->data(array('mobile' => $mobile, 'mobile_verified' => 1,'balance'=>"`balance`+({$customer1['balance']})",'offline_balance'=>"`offline_balance`+({$customer1['offline_balance']})"))->where('user_id=' . $value['user_id'])->update();
                                            $this->model->table('customer')->data(array('status' => 0))->where('user_id=' . $user_id)->update();
                                            $this->model->table('oauth_user')->data(array('other_user_id' => $user_id))->where('user_id=' . $user_id)->update();
                                            $this->model->table('oauth_user')->data(array('user_id' => $value['user_id']))->where('other_user_id=' . $user_id)->update();
                                            $last_id = $value['user_id'];
                                        }        
                                    }
                                }
                                $oauth = $this->model->table('oauth_user')->where("user_id=".$value['user_id'])->find();
                                if(!$oauth) {
                                    $customer1 = $this->model->table('customer')->where('user_id=' . $user_id)->find();
                                    $customer2 = $this->model->table('customer')->where('user_id=' . $value['user_id'])->find();
                                    //绑定手机号
                                    $this->model->table('customer')->data(array('mobile' => $mobile, 'mobile_verified' => 1,'balance'=>"`balance`+({$customer2['balance']})",'offline_balance'=>"`offline_balance`+({$customer2['offline_balance']})"))->where('user_id=' . $user_id)->update();
                                    //已注册时间早的为主
                                    if(strtotime($customer1['reg_time'])<strtotime($customer2['reg_time'])) {
                                        $this->model->table('customer')->data(array('status' => 0))->where('user_id=' . $value['user_id'])->update();   
                                        $this->model->table('oauth_user')->data(array('other_user_id' => $value['user_id']))->where('user_id=' . $user_id)->update();
                                        $last_id = $user_id;
                                    } else {
                                        $this->model->table('customer')->data(array('status' => 0))->where('user_id=' . $user_id)->update();
                                        $this->model->table('oauth_user')->data(array('other_user_id' => $user_id))->where('user_id=' . $user_id)->update();
                                        $this->model->table('oauth_user')->data(array('user_id' => $value['user_id']))->where('other_user_id=' . $user_id)->update();
                                        $last_id = $value['user_id'];
                                    }
                                }
                            } 
                        }
                    }
                }
                Session::clear('verifiedInfo');
                Session::clear('activateObj');
                $this->redirect('/ucenter/update_obj_success/obj/' . $obj);
                exit;
            } else {
                $info = array('field' => 'account', 'msg' => '账号或验证码不正确。');
            }
        }
        $this->redirect("/ucenter/update_obj/r/" . $verifiedInfo['random'], true, array('invalid' => $info, 'account' => $account));
    }

    public function update_obj_success()
    {
        $obj = Req::args('obj');
        if ($obj != null) {
            $this->redirect();
        } else {
            $this->redirect('/ucenter/safety');
        }
    }

    public function send_objcode()
    {
        $account = Req::args('account');
        $activateObj = Session::get('activateObj');
        $sendAble = true;
        $haveTime = 120;
        if (isset($activateObj['time'])) {
            $time = $activateObj['time'];
            $haveTime = time() - $time;
            if ($haveTime <= 120) {
                $sendAble = false;
            }
        }

        if ($sendAble) {
            $code = CHash::random(6, 'int');
            if (Validator::email($account)) {
                $mail = new Mail();
                $flag = $mail->send_email($account, '您的邮箱身份核实验证码', "核实邮箱验证码：" . $code);
                if (!$flag) {
                    $info = array('status' => 'fail', 'msg' => '邮件发送失败请联系管理人员');
                } else {
                    $activateObj = array('time' => time(), 'code' => $code, 'obj' => $account);
                    Session::set('activateObj', $activateObj);
                    $info = array('status' => 'success');
                }
            } else if (Validator::mobi($account)) {
                $sms = SMS::getInstance();
                // if ($sms->getStatus()) {
                $result = $sms->sendCode($account, $code);
                // $result = $sms->actionSendVerificationCode($account, $this->user['id']); //使用云账户接口
                if ($result['status'] == 'success') {
                    $info = array('status' => 'success', 'msg' => $result['message']);
                    $activateObj = array('time' => time(), 'code' => $code, 'obj' => $account);
                    Session::set('activateObj', $activateObj);
                    $info = array('status' => 'success');
                } else {
                    $info = array('status' => 'fail', 'msg' => $result['message']);
                }
                // } else {
                //     $info = array('status' => 'fail', 'msg' => '系统没有开启手机验证功能!');
                // }
            } else {
                $info = array('status' => 'fail', 'msg' => '除邮箱及手机号外，不支持发送!');
            }
        } else {
            $info = array('status' => 'fail', 'msg' => '还有' . (120 - $haveTime) . '秒后可发送！');
        }
        $info['haveTime'] = (120 - $haveTime);
        echo JSON::encode($info);
    }

    public function send_code()
    {
        $info = array('status' => 'fail', 'msg' => '');
        $type = Req::args('type');
        $code = CHash::random(6, 'int');
        $obj = Req::args('obj');
        $verifiedInfo = Session::get('verifiedInfo');
        $sendAble = true;
        $haveTime = 120;

        if (isset($verifiedInfo['time']) && $type == $verifiedInfo['type']) {
            $time = $verifiedInfo['time'];
            $haveTime = time() - $time;
            if ($haveTime <= 120) {
                $sendAble = false;
            }
        }

        if ($sendAble) {

            $obj = $this->updateObj($obj);
            $random = CHash::random(20, 'char');
            $verifiedInfo = array('code' => $code, 'time' => time(), 'type' => $type, 'obj' => $obj, 'random' => $random);
            if ($type == 'email') {
                $mail = new Mail();
                $flag = $mail->send_email($this->user['email'], '您的验证身份验证码', "身份验证码：" . $code);
                if (!$flag) {
                    $info = array('status' => 'fail', 'msg' => '邮件发送失败请联系管理人员');
                } else {
                    Session::set('verifiedInfo', $verifiedInfo);
                    $info = array('status' => 'success');
                }
            } else if ($type == 'mobile') {
                $sms = SMS::getInstance();
                // if ($sms->getStatus()) {
                $customer = $this->model->table('customer')->fields('mobile')->where('user_id=' . $this->user['id'])->find();
                $mobile = $customer ? $customer['mobile'] : $this->user['mobile'];
                $result = $sms->sendCode($mobile, $code);
                if ($result['status'] == 'success') {
                    $info = array('status' => 'success', 'msg' => $result['message']);
                    Session::set('verifiedInfo', $verifiedInfo);
                    $info = array('status' => 'success');
                } else {
                    $info = array('status' => 'fail', 'msg' => $result['message']);
                }
                // } else {
                //     $info = array('status' => 'fail', 'msg' => '系统没有开启手机验证功能!');
                // }
            }
        } else {
            $info = array('status' => 'fail', 'msg' => '还有' . (120 - $haveTime) . '秒后可发送！');
        }
        $info['haveTime'] = (120 - $haveTime);

        echo JSON::encode($info);
    }

    public function safety()
    {
        $verified = $this->verifiedType();
        $this->redirect();
    }

    private function verifiedType()
    {
        $verified_type = array(
            'mobile' => "已验证手机",
            'email' => "已验证邮箱",
            'paypwd' => "支付密码"
        );

        $model = new Model('user as us');
        $userInfo = $model->join('left join customer as cu on us.id = cu.user_id')->where('cu.user_id = ' . $this->user['id'])->find();
        if ($userInfo) {
            //用户如果没有绑定手机或者邮箱时
            if ($userInfo['email_verified'] != 1)
                unset($verified_type['email']);
            if ($userInfo['mobile_verified'] != 1)
                unset($verified_type['mobile']);
            if ($userInfo['pay_password_open'] != 1)
                unset($verified_type['paypwd']);
            //隐藏敏感信息
            $userInfo['email'] = preg_replace("/^(\w{1}).*(\w{1}@.+)$/i", "$1*****$2", $userInfo['email']);
            $userInfo['mobile'] = preg_replace("/^(\d{3})\d+(\d{3})$/i", "$1*****$2", $userInfo['mobile']);
        }
        $type = Req::args('type');
        $obj = Req::args('obj');
        $obj = $this->updateObj($obj);

        $type = $type == null ? 'mobile' : $type;
        //跟前端显示有关
        if (isset($verified_type[$type])) {
            unset($verified_type[$type]);
        } else {
            if (count($verified_type) > 0) {
                $keys = array_keys($verified_type);
                $type = current($keys);
                unset($verified_type[$type]);
            } else {
                $type = null;
            }
        }
        $this->assign("userInfo", $userInfo);
        $this->assign("obj", $obj);
        $this->assign("verified", $verified_type);
        $this->assign("type", $type);
    }

    private function updateObj($obj)
    {
        $objs = array('email' => true, 'mobile' => true, 'password' => true, 'paypwd' => true);
        if (!isset($objs[$obj])) {
            $obj = 'password';
        }
        return $obj;
    }

    //检测用户是否在线
    private function checkOnline()
    {
        if (isset($this->user) && $this->user['name'] != null)
            return true;
        else
            return false;
    }

    public function commission()
    {
        $uid = $this->user['id'];
        $commission = $this->model->table("commission")->where('user_id=' . $uid)->find();
        if (empty($commission)) {
            $commission['commission_available'] = "0.00";
            $commission['commission_possess_now'] = "0.00";
            $commission['commission_withdrew'] = "0.00";
        } else {
            //更新可用状态
            $commission_set = Config::getInstance()->get("commission_set");
            $lockdays = $commission_set['commission_locktime'];
            $lockdays = is_int($lockdays) ? $lockdays : (int)$lockdays;
            $available_time = date('Y-m-d H:i:s', strtotime("-$lockdays days"));
            $result = $this->model->table('commission_log')->where("user_id = $uid and status = 0 and time < '$available_time'")->data(array('status' => 1))->update();
            if ($result > 0) {
                $available_commission = $this->model->query("select SUM(commission_get) as count from tiny_commission_log where user_id=$uid and status =1");
                $this->model->table('commission')->data(array('commission_available' => $available_commission[0]['count']))->where('user_id=' . $uid)->update();
                $commission = $this->model->table("commission")->where('user_id=' . $uid)->find();
            }
        }
        $this->assign("commission", $commission);
        $this->assign("seo_title", "我的佣金");
        $this->redirect();
    }

    public function commission_log()
    {
        $uid = $this->user['id'];
        $this->assign('uid', $uid);
        $this->assign("seo_title", "佣金记录");
        $this->redirect();
    }

    public function change_account()
    {
        $mobile = Filter::sql(Req::args('mobile'));
        $validatecode = Filter::sql(Req::args('validatecode'));
        // $type = Filter::int(Req::args('type'));

        if ($mobile != "" && $validatecode != "") {
            $ret = SMS::getInstance()->checkCode($mobile, $validatecode);
            // $ret = array('status' => 'success', 'message' => '验证成功');
            SMS::getInstance()->flushCode($mobile);
            if ($ret['status'] == 'success') {
                //查询当前微信公众号绑定的user_id
                $account_info_all = $this->model->table("oauth_user")->where("user_id =" . $this->user['id'] . " and oauth_type ='wechat'")->fields("id,user_id,other_user_id")->findAll();
                if (empty($account_info_all) || count($account_info_all) > 1) {
                    $ret['status'] = "fail";
                    $ret['message'] = "切换失败,oauth信息错误";
                } else {
                    $account_info = $account_info_all[0];
                    if ($account_info['other_user_id'] == 0 || $account_info['other_user_id'] == "") {//如果另一个账号信息不存在
                        //查询手机号绑定的账号
                        $other_account = $this->model->table('customer')->where("mobile='" . $mobile . "'")->fields('user_id,mobile')->find();
                        if (empty($other_account) || $other_account['user_id'] == 0) {
                            $ret['status'] = "fail";
                            $ret['message'] = "切换失败,该手机号绑定的账号不存在";
                            echo json_encode($ret);
                            exit;
                        } else {//查询成功
                            if ($other_account['user_id'] == $account_info['user_id']) {//绑定的就是本账号
                                $ret['status'] = "fail";
                                $ret['message'] = "切换失败,不存在另一个账号";
                                echo json_encode($ret);
                                exit;
                            }
                            //判断该账号是否已经绑定过微信公众号登陆
                            $isOk1 = $this->model->table("oauth_user")->where("user_id =" . $other_account['user_id'] . " and oauth_type ='wechat'")->fields("id,user_id,other_user_id")->findAll();
                            $isOk2 = $this->model->table("oauth_user")->where("other_user_id =" . $other_account['user_id'] . " and oauth_type ='wechat'")->fields("id,user_id,other_user_id")->findAll();
                            if (!empty($isOk1) && !empty($isOk2)) {
                                $ret['status'] = "fail";
                                $ret['message'] = "绑定失败，该手机号对应账号已经绑定了其他微信账号";
                                echo json_encode($ret);
                                exit;
                            }

                            $this->model->table('customer')->data(array('mobile' => $mobile))->where('user_id=' . $this->user['id'])->update();
                            $this->model->table('customer')->data(array('status' => 0))->where('user_id=' . $other_account['user_id'])->update();
                            $result = $this->model->table("oauth_user")->data(array('user_id' => $other_account['user_id'], 'other_user_id' => $account_info['user_id']))->where("id =" . $account_info['id'])->update();

                            //将微信账号密码与手机账号密码同步，用于app端手机号登录时以微信账号登录
                            $user = $this->model->table('user')->fields('password,validcode')->where('id=' . $other_account['user_id'])->find();
                            $this->model->table('user')->data(array('password' => $user['password'], 'validcode' => $user['validcode']))->where('id=' . $this->user['id'])->update();

                            if ($result) {
                                $this->safebox->clear('user');
                                $cookie = new Cookie();
                                $cookie->setSafeCode(Tiny::app()->getSafeCode());
                                $cookie->set('autologin', null, 0);
                                $ret['status'] = "success";
                                $ret['message'] = "绑定并切换成功";
                                echo json_encode($ret);
                                exit;
                            } else {
                                $ret['status'] = "fail";
                                $ret['message'] = "切换失败,数据库错误1";
                                echo json_encode($ret);
                                exit;
                            }
                        }
                        $ret['status'] = "fail";
                        $ret['message'] = "切换失败";
                        echo json_encode($ret);
                        exit;
                    } else {//存在另一个user_id
                        $ids = $account_info['other_user_id'] . "," . $account_info['user_id'];
                        //验证手机号
                        $isOk = $this->model->table("customer")->where("user_id in ($ids) and mobile='$mobile'")->find();
                        if (!$isOk) {
                            $ret['status'] = "fail";
                            $ret['message'] = "切换失败,对应手机号码错误";
                            echo json_encode($ret);
                            exit;
                        }
                        //查询另一个账号是否真实存在没被禁用或删除
                        $other_account = $this->model->table('user')->where("id=" . $account_info['other_user_id'] . " and status = 1")->find();
                        if (empty($other_account)) {
                            $ret['status'] = "fail";
                            $ret['message'] = "切换失败,绑定的另一个账号信息为空";
                            echo json_encode($ret);
                            exit;
                        } else {
                            $result = $this->model->table("oauth_user")->data(array('user_id' => $other_account['id'], 'other_user_id' => $account_info['user_id']))->where("id =" . $account_info['id'])->update();
                            if ($result) {
                                $this->safebox->clear('user');
                                $cookie = new Cookie();
                                $cookie->setSafeCode(Tiny::app()->getSafeCode());
                                $cookie->set('autologin', null, 0);
                                $ret['status'] = "success";
                                $ret['message'] = "切换成功";
                                echo json_encode($ret);
                                exit;
                            } else {
                                $ret['status'] = "fail";
                                $ret['message'] = "切换失败,数据库错误2";
                                echo json_encode($ret);
                                exit;
                            }
                        }
                    }
                }
            } else {
                $ret['status'] = "fail";
                $ret['message'] = "验证码错误，请重新获取";
                echo json_encode($ret);
                exit;
            }
        } else {
            $account_info = $this->model->table("oauth_user")->where("user_id =" . $this->user['id'] . " and oauth_type ='wechat'")->fields("id,user_id,other_user_id")->find();
            if (isset($account_info['other_user_id'])) {
                $nameinfo = $this->model->table("user")->where("id=" . $account_info['other_user_id'])->fields("nickname,name,avatar")->find();
                if (!empty($nameinfo)) {
                    $this->assign("other_account", $nameinfo);
                }
            }
            $this->assign("seo_title", "切换账号");
            $this->redirect("change_account");
        }
    }

    public function change_accounts()
    {
        $account_info = $this->model->table("oauth_user")->where("user_id =" . $this->user['id'] . " and oauth_type ='wechat'")->fields("id,user_id,other_user_id")->find();
        if (isset($account_info['other_user_id'])) {
            $nameinfo = $this->model->table("user")->where("id=" . $account_info['other_user_id'])->fields("nickname,name,avatar")->find();
            if (!empty($nameinfo)) {
                $this->assign("other_account", $nameinfo);
            }
        }
        $this->assign("seo_title", "切换账号");
        $this->redirect("change_accounts");

    }

    public function change_acct()
    {
        $mobile = Filter::sql(Req::args('mobile'));
        $validatecode = Filter::sql(Req::args('validatecode'));
        $ret = SMS::getInstance()->checkCode($mobile, $validatecode);
        // $ret = array('status' => 'success', 'message' => '验证成功');
        SMS::getInstance()->flushCode($mobile);
        if ($ret['status'] == 'success') {
            //查询当前微信公众号绑定的user_id
            $account_info_all = $this->model->table("oauth_user")->where("user_id =" . $this->user['id'] . " and oauth_type ='wechat'")->fields("id,user_id,other_user_id")->findAll();
            if (empty($account_info_all) || count($account_info_all) > 1) {
                $ret['status'] = "fail";
                $ret['message'] = "切换失败,oauth信息错误";
                echo json_encode($ret);
                exit;
            } else {
                $account_info = $account_info_all[0];
                if ($account_info['other_user_id'] == 0 || $account_info['other_user_id'] == "") {//如果另一个账号信息不存在
                    //查询手机号绑定的账号
                    $other_account = $this->model->table('customer')->where("mobile='" . $mobile . "'")->fields('user_id,mobile')->find();
                    if (empty($other_account) || $other_account['user_id'] == 0) {
                        $ret['status'] = "fail";
                        $ret['message'] = "切换失败,该手机号绑定的账号不存在";
                        echo json_encode($ret);
                        exit;
                    } else {//查询成功
                        if ($other_account['user_id'] == $account_info['user_id']) {//绑定的就是本账号
                            $ret['status'] = "fail";
                            $ret['message'] = "切换失败,不存在另一个账号";
                            echo json_encode($ret);
                            exit;
                        }
                        //判断该账号是否已经绑定过微信公众号登陆
                        $isOk1 = $this->model->table("oauth_user")->where("user_id =" . $other_account['user_id'] . " and oauth_type ='wechat'")->fields("id,user_id,other_user_id")->findAll();
                        $isOk2 = $this->model->table("oauth_user")->where("other_user_id =" . $other_account['user_id'] . " and oauth_type ='wechat'")->fields("id,user_id,other_user_id")->findAll();
                        if (!empty($isOk1) && !empty($isOk2)) {
                            $ret['status'] = "fail";
                            $ret['message'] = "绑定失败，该手机号对应账号已经绑定了其他微信账号";
                            echo json_encode($ret);
                            exit;
                        }
                        // var_dump($other_account['user_id']);die;

                        $this->model->table('customer')->data(array('mobile' => ''))->where('user_id=' . $other_account['user_id'])->update();
                        $result = $this->model->table("oauth_user")->data(array('user_id' => $other_account['user_id'], 'other_user_id' => ''))->where("id =" . $account_info['id'])->update();

                        //将微信账号密码与手机账号密码同步，用于app端手机号登录时以微信账号登录
                        $user = $this->model->table('user')->fields('password,validcode')->where('id=' . $other_account['user_id'])->find();
                        $this->model->table('user')->data(array('password' => $user['password'], 'validcode' => $user['validcode']))->where('id=' . $this->user['id'])->update();

                        if ($result) {
                            $this->safebox->clear('user');
                            $cookie = new Cookie();
                            $cookie->setSafeCode(Tiny::app()->getSafeCode());
                            $cookie->set('autologin', null, 0);
                            $ret['status'] = "success";
                            $ret['message'] = "绑定并切换成功";
                            echo json_encode($ret);
                            exit;
                        } else {
                            $ret['status'] = "fail";
                            $ret['message'] = "切换失败,数据库错误1";
                            echo json_encode($ret);
                            exit;
                        }
                    }
                    $ret['status'] = "fail";
                    $ret['message'] = "切换失败";
                    echo json_encode($ret);
                    exit;
                } else {//存在另一个user_id
                    $ids = $account_info['other_user_id'] . "," . $account_info['user_id'];
                    //验证手机号
                    $isOk = $this->model->table("customer")->where("user_id in ($ids) and mobile='$mobile'")->find();
                    if (!$isOk) {
                        $ret['status'] = "fail";
                        $ret['message'] = "切换失败,对应手机号码错误";
                        echo json_encode($ret);
                        exit;
                    }
                    //查询另一个账号是否真实存在没被禁用或删除
                    $other_account = $this->model->table('user')->where("id=" . $account_info['other_user_id'] . " and status = 1")->find();
                    if (empty($other_account)) {
                        $ret['status'] = "fail";
                        $ret['message'] = "切换失败,绑定的另一个账号信息为空";
                        echo json_encode($ret);
                        exit;
                    } else {
                        $this->model->table('customer')->data(array('mobile' => ''))->where('user_id=' . $other_account['id'])->update();
                        // $result = $this->model->table("oauth_user")->data(array('user_id' => $other_account['user_id'], 'other_user_id' => ''))->where("id =" . $account_info['id'])->update();

                        //将微信账号密码与手机账号密码同步，用于app端手机号登录时以微信账号登录
                        $this->model->table('user')->data(array('password' => $other_account['password'], 'validcode' => $other_account['validcode']))->where('id=' . $this->user['id'])->update();

                        $result = $this->model->table("oauth_user")->data(array('user_id' => $other_account['id'], 'other_user_id' => ''))->where("id =" . $account_info['id'])->update();
                        if ($result) {
                            $this->safebox->clear('user');
                            $cookie = new Cookie();
                            $cookie->setSafeCode(Tiny::app()->getSafeCode());
                            $cookie->set('autologin', null, 0);
                            $ret['status'] = "success";
                            $ret['message'] = "切换成功";
                            echo json_encode($ret);
                            exit;
                        } else {
                            $ret['status'] = "fail";
                            $ret['message'] = "切换失败,数据库错误2";
                            echo json_encode($ret);
                            exit;
                        }
                    }
                }
            }
        } else {
            $ret['status'] = "fail";
            $ret['message'] = "验证码错误，请重新获取";
            echo json_encode($ret);
            exit;
        }
    }

    private function _isCanApplyRefund($order_id)
    {
        $isset = $this->model->table("refund")->where("order_id =$order_id and user_id =" . $this->user['id'])->find();
        if ($isset) {
            return false;
        }
        $orderInfo = $this->model->table("order")->where("id = $order_id and user_id =" . $this->user['id'])->find();
        if (empty($orderInfo)) {
            return false;
        } else {
            if ($orderInfo['order_amount'] <= 0) {
                return false;
            }
            if ($orderInfo['type'] == 4) {//华币订单
                if ($orderInfo['is_new'] == 0) {
                    if ($orderInfo['otherpay_status'] == 1 || $orderInfo['pay_status'] == 1) {
                        if ($orderInfo['otherpay_amount'] > 0) {
                            return array("otherpay_status" => $orderInfo['otherpay_status'], "pay_status" => $orderInfo['pay_status'], "order_type" => 4, "order_id" => $order_id, "order_no" => $orderInfo['order_no'], "payment" => $orderInfo['payment'], "refund_amount" => $orderInfo['otherpay_amount']);
                        } else {
                            return false;
                        }
                    } else {
                        return false;
                    }
                } else if ($orderInfo['is_new'] == 1) {
                    if ($orderInfo['pay_status'] == 1) {
                        if ($orderInfo['is_return'] == 1) {
                            $refund_amount = $orderInfo['otherpay_amount'];
                            if ($refund_amount > 0) {
                                return array("order_type" => $orderInfo['type'], "order_id" => $order_id, "order_no" => $orderInfo['order_no'], "payment" => $orderInfo['payment'], "refund_amount" => $refund_amount);
                            } else {
                                return false;
                            }
                        } else {
                            $refund_amount = $orderInfo['order_amount'];
                            if ($refund_amount > 0) {
                                return array("order_type" => $orderInfo['type'], "order_id" => $order_id, "order_no" => $orderInfo['order_no'], "payment" => $orderInfo['payment'], "refund_amount" => $refund_amount);
                            } else {
                                return false;
                            }
                        }
                    } else {
                        return false;
                    }
                }
            } else {
                if ($orderInfo['pay_status'] == 1) {
                    return array("order_type" => $orderInfo['type'], "order_id" => $order_id, "order_no" => $orderInfo['order_no'], "payment" => $orderInfo['payment'], "refund_amount" => $orderInfo['order_amount']);
                } else {
                    return false;
                }
            }
        }
    }

    public function refund_apply()
    {
        $order_id = Filter::int(Req::args("order_id"));
        $info = $this->_isCanApplyRefund($order_id);
        if ($info == false || empty($info)) {
            $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '操作失败', "content" => "该订单没有申请退款权限或已经申请过退款操作"));
            exit();
        }
        $reason = array(
            array('title' => "发货太慢", "value" => "0"),
            array('title' => "我不喜欢了", "value" => "1"),
            array('title' => "拍错了，重拍", "value" => "2"),
            array('title' => "质量问题", "value" => "3"),
            array('title' => "其他原因", "value" => "4"),
            array('title' => "无理由", "value" => "5"),
        );
        $this->assign("reason", $reason);
        $this->assign('refund_amount', $info['refund_amount']);
        $this->assign("order_no", $info['order_no']);
        $this->assign("order_id", $info['order_id']);
        $this->assign("seo_title", "退款申请");
        $this->redirect();
    }

    public function refund_apply_submit()
    {
        $order_id = Filter::int(Req::args("order_id"));
        $reason = Filter::sql(Req::args("reason"));
        $reason_desc = Filter::sql(Req::args("reason_desc"));
        $return = $this->_isCanApplyRefund($order_id);
        if ($return == false || empty($return)) {
            $result = array('status' => 'fail', 'msg' => '该订单没有申请退款权限或已经申请过了');
            echo json_encode($result);
            exit();
        } else {
            $data['order_id'] = $return['order_id'];
            $data['order_no'] = $return['order_no'];
            $data['payment'] = $return['payment'];
            $data['user_id'] = $this->user['id'];
            $data['refund_amount'] = $return['refund_amount'];
            $data['apply_reason'] = $reason . ($reason_desc == "" ? "" : ":" . $reason_desc);
            $data['apply_time'] = date("Y-m-d H:i:s");
            $data['refund_progress'] = 0;
            $id = $this->model->table("refund")->data($data)->insert();
            if ($id) {
                //锁定订单，禁止发货
                if ($return['order_type'] == 4) {//华币订单
                    $isOk = $this->model->table("order")->data(array("pay_status" => '2'))->where("id = $order_id")->update();
                } else {
                    $isOk = $this->model->table("order")->data(array("pay_status" => '2'))->where("id = $order_id")->update();
                }
                if ($isOk) {
                    $result = array('status' => 'success', 'msg' => '申请成功');
                    echo json_encode($result);
                    exit();
                } else {
                    $result = array('status' => 'fail', 'msg' => '申请失败，数据库错误');
                    echo json_encode($result);
                    exit();
                }
            } else {
                $result = array('status' => 'fail', 'msg' => '申请失败，数据库错误');
                echo json_encode($result);
                exit();
            }
        }
    }

    public function refund_progress()
    {
        $order_id = Filter::sql(Req::args("order_id"));
        $refund_info = $this->model->table("refund as r")
            ->join("left join payment as p on r.payment = p.id")
            ->fields("r.*,p.pay_name,plugin_id")
            ->where("order_id = $order_id and user_id = " . $this->user['id'])
            ->find();
        if ($refund_info) {
            $this->assign("refund", $refund_info);
            $this->redirect();
        } else {
            $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '操作失败', 'content' => "退款信息不存在"));
            exit();
        }
    }

    public function pointcoin_log()
    {
        $this->assign("user_id", $this->user['id']);
        $this->assign("seo_title", '积分记录');
        $this->redirect();
    }

    public function order_delete()
    {
        $id = Filter::int(Req::args('id'));
        $isset = $this->model->table("order")->where("id=$id and user_id =" . $this->user['id'] . " and status in(1,2,5,6)")->find();
        if (empty($isset)) {
            echo json_encode(array('status' => 'fail', 'msg' => '失败'));
            exit();
        }
        $result = $this->model->table("order")->where("id = $id and user_id = " . $this->user['id'] . ' and status in (1,2,5,6)')->data(array('is_del' => '1'))->update();
        if ($result) {
            if ($isset['status'] != 6) {
                if (($isset['type'] == 5 || $isset['type'] == 6) && $isset['pay_point'] > 0) {
                    $this->model->table("customer")->where("user_id=" . $this->user['id'])->data(array("point_coin" => "`point_coin`+" . $isset['pay_point']))->update();
                    Log::pointcoin_log($isset['pay_point'], $this->user['id'], $isset['order_no'], "取消订单，退回积分", 2);
                }
            }
            echo json_encode(array('status' => 'success', 'msg' => '成功'));
            exit();
        } else {
            echo json_encode(array('status' => 'fail', 'msg' => '失败'));
            exit();
        }
    }

    public function promoter_home()
    {
        $promoter = Promoter::getPromoterInstance($this->user['id']);
        if (!is_object($promoter)) {
            exit();
        }
        $data = $promoter->getIncomeStatistics();
        $customer = $this->model->table('customer')->fields('financial_stock')->where('user_id=' . $this->user['id'])->find();
        $this->assign('data', $data);
        $this->assign('financial_stock', $customer['financial_stock']);
        $this->assign('seo_title', '我的收益');
        $this->redirect();
    }

    public function promoter_income()
    {
        $promoter = Promoter::getPromoterInstance($this->user['id']);
        if (!is_object($promoter)) {
            $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '操作失败', "content" => "抱歉，您还不是推广者"));
            exit();
        }
        if ($this->is_ajax_request()) {
            $page = Filter::int(Req::args('p'));
            $data = $promoter->getMyIncomeRecord($page);
            if (empty($data)) {
                echo json_encode(array('status' => 'fail', 'msg' => "数据为空"));
                exit();
            }
            echo json_encode(array('status' => 'success', 'data' => $data['data']));
            exit();
        } else {
            $this->assign('data', $promoter->getMyIncomeRecord(1));
            $this->assign("seo_title", "收益记录");
            $this->redirect();
        }
    }

    public function promoter_sale()
    {
        $promoter = Promoter::getPromoterInstance($this->user['id']);
        if (!is_object($promoter)) {
            $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '操作失败', "content" => "抱歉，您还不是推广者"));
            exit();
        }
        if ($this->is_ajax_request()) {
            $page = Filter::int(Req::args('p'));
            $data = $promoter->getMySaleRecord($page);
            if (empty($data)) {
                echo json_encode(array('status' => 'fail', 'msg' => "数据为空"));
                exit();
            }
            echo json_encode(array('status' => 'success', 'data' => $data['data']));
            exit();
        } else {
            $this->assign('data', $promoter->getMySaleRecord(1));
            $this->assign("seo_title", "销售记录");
            $this->redirect();
        }
    }

    public function promoter_withdraw()
    {
        $config = Config::getInstance();
        $other = $config->get("district_set");
        $withdraw_fee_rate = isset($other['withdraw_fee_rate']) ? $other['withdraw_fee_rate'] : 0.5;
        $min_withdraw_amount = isset($other['min_withdraw_amount']) ? $other['min_withdraw_amount'] : 0.1;
        $this->assign('withdraw_fee_rate', $withdraw_fee_rate);
        $this->assign('min_withdraw_amount', $min_withdraw_amount);
        $this->assign('seo_title', '收益提现');
        $this->redirect();
    }

    public function promoter_withdraw_submit()
    {
        $promoter = Promoter::getPromoterInstance($this->user['id']);
        if (!is_object($promoter)) {
            exit();
        }
        if ($this->is_ajax_request()) {
            $data = Req::args();
            unset($data['con']);
            unset($data['act']);
            echo json_encode($promoter->applyDoSettle($data));
        } else {
            echo json_encode(array('status' => 'fail', 'msg' => 'bad request'));
        }
    }

    public function promoter_withdraw_list()
    {
        $promoter = Promoter::getPromoterInstance($this->user['id']);
        if (!is_object($promoter)) {
            $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '操作失败', "content" => "抱歉，您还不是推广者"));
            exit();
        }
        if ($this->is_ajax_request()) {
            $page = Filter::int(Req::args('p'));
            $data = $promoter->getSettledHistory($page);
            if (empty($data)) {
                echo json_encode(array('status' => 'fail', 'msg' => "数据为空"));
                exit();
            }
            echo json_encode(array('status' => 'success', 'data' => $data['data']));
            exit();
        } else {
            $this->assign('data', $promoter->getSettledHistory(1));
            $this->assign("seo_title", "提现记录");
            $this->redirect();
        }
    }

    public function promoter_getqrcode()
    {
        $promoter = Promoter::getPromoterInstance($this->user['id']);
        if (!is_object($promoter)) {
            $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '操作失败', "content" => "抱歉，您还不是推广者"));
            exit();
        }
        $goods_id = Req::args('goods_id');
        $goods_id = substr($goods_id, 0, strpos($goods_id, '.png'));
        $promoter->getQrcodeByGoodsId($goods_id);
    }

    //申请创建专区
    public function apply_for_district()
    {
        if ($this->is_ajax_request()) {
            $data = Filter::inputFilter(Req::args());
            if ($data['name'] == NULL || $data['location'] == NULL || $data['linkman'] == NULL || $data['linkmobile'] == NULL) {
                echo json_encode(array('status' => 'fail', 'msg' => '请完善申请信息'));
                exit();
            }
            unset($data['con']);
            unset($data['act']);
            if (!empty($data)) {
                $data['user_id'] = $this->user['id'];
                $data['status'] = 0;
                $data['apply_time'] = date("Y-m-d H:i:s");
                if ($data['free'] == 1) {
                    $promoter = Promoter::getPromoterInstance($this->user['id']);
                    if (is_object($promoter)) {
                        $invite_count = $this->model->table("district_order")->where("pay_status=1 and invitor_role='promoter' and invitor_id=$promoter->id")->count();
                        $config = Config::getInstance()->get("district_set");
                        if ($invite_count < $config['invite_promoter_num']) {
                            exit(json_encode(array('status' => 'fail', "msg" => "您没有免费申请权限")));
                        }
                        $hirer = $this->model->table("district_shop")->where("owner_id =" . $this->user['id'])->find();
                        if ($hirer) {
                            exit(json_encode(array('status' => 'fail', "msg" => "您已经拥有专区了")));
                        }
                        $apply = $this->model->table("district_apply")->where("free = 1 and status = 0 and user_id = " . $this->user['id'])->find();
                        if ($apply) {
                            exit(json_encode(array('status' => 'fail', "msg" => "已经申请过了，请勿重复申请")));
                        }
                        $data['pay_status'] = 1; //将免费入驻标记为已经支付
                    } else {
                        exit(json_encode(array('status' => 'fail', "msg" => "您不是代理商")));;
                    }
                } else {
                    unset($data['free']);
                }
                if ($data['reference'] == '') {
                    unset($data['reference']);
                }
                $id = $this->model->table('district_apply')->data($data)->insert();
                if ($id) {
                    Cookie::clear("district_id");
                    Cookie::clear("test");
                    echo json_encode(array('status' => 'success', 'msg' => '申请提交成功', 'id' => $id));
                    exit();
                }
            } else {
                echo json_encode(array('status' => 'fail', 'msg' => '申请信息错误'));
                exit();
            }
        } else {
            $free = Filter::int(Req::args('free'));
            if ($free == 1) {
                $promoter = Promoter::getPromoterInstance($this->user['id']);
                if (is_object($promoter)) {
                    $invite_count = $this->model->table("district_order")->where("pay_status=1 and invitor_role='promoter' and invitor_id=$promoter->id")->count();
                    $config = Config::getInstance()->get("district_set");
                    if ($invite_count < $config['invite_promoter_num']) {
                        $this->redirect("/index/msg", false, array('type' => "info", "msg" => '抱歉', "content" => "您没有免费申请权限"));
                        exit();
                    }
                    $hirer = $this->model->table("district_shop")->where("owner_id =" . $this->user['id'])->find();
                    if ($hirer) {
                        $this->redirect("/index/msg", false, array('type' => "info", "msg" => '抱歉', "content" => "您已经拥有专区了"));
                        exit();
                    }
                    $this->assign('seo_title', "免费入驻申请");
                    $this->assign("free", 1);
                    $this->redirect();
                    exit();
                } else {
                    $this->redirect("/index/msg", false, array('type' => "info", "msg" => '抱歉', "content" => "您还不是代理商"));
                    exit();
                }
            }
            //推荐人
            $reference = Filter::int(Req::args('reference'));
            if ($reference != "") {
                $this->assign("reference", $reference);
            }
            $this->assign("free", 0);
            $this->assign('seo_title', '申请入驻');
            $this->redirect();
        }
    }

    //成为专区推广者
    public function becomepromoter()
    {
        if ($this->is_ajax_request()) {
            echo json_encode(array('status' => 'fail', 'msg' => '抱歉，接口关闭了'));
            exit();
        } else {
            $reference = Filter::int(Req::args('reference'));
            $invitor_role = Filter::str(Req::args('invitor_role'));
            $invitor_role = $invitor_role == NULL ? "shop" : $invitor_role; //默认是shop

            $promoter = Promoter::getPromoterInstance($this->user['id']);
            if (is_object($promoter) && $promoter->role_type == 2) {
                $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '操作失败', "content" => "抱歉，您已经有雇佣关系了，暂时不能加入其他专区"));
                exit();
            }
            if ($reference == null) {
                $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '操作失败', "content" => "抱歉，还没有接收到邀请哦"));
                exit();
            } else {
                if ($invitor_role == 'shop') {
                    $district_info = $this->model->table("district_shop")->where("id = $reference")->find();
                } else if ($invitor_role == 'promoter') {
                    $district_info = $this->model
                        ->table("district_promoter as dp")->join("left join district_shop as ds on dp.hirer_id = ds.id")
                        ->where("dp.id = $reference")
                        ->find();
                } else {
                    $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '抱歉', "content" => "role信息错误"));
                    exit();
                }
                if (!isset($district_info) || !$district_info) {
                    $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '抱歉', "content" => "专区信息错误"));
                    exit();
                }
                //礼品地址
                $area_ids = array();
                $address = $this->model->table("address")->where("user_id=" . $this->user['id'])->order("is_default desc")->findAll();
                foreach ($address as $add) {
                    $area_ids[$add['province']] = $add['province'];
                    $area_ids[$add['city']] = $add['city'];
                    $area_ids[$add['county']] = $add['county'];
                }
                $area_ids = implode(",", $area_ids);
                $areas = array();
                if ($area_ids != '')
                    $areas = $this->model->table("area")->where("id in($area_ids )")->findAll();
                $parse_area = array();
                foreach ($areas as $area) {
                    $parse_area[$area['id']] = $area['name'];
                }
                $this->assign("parse_area", $parse_area);
                $this->assign('address', $address);

                $config = Config::getInstance()->get("district_set");
                //礼品
                if (isset($config['join_send_gift']) && $config['join_send_gift'] != "") {
                    $gift = implode(",", explode("|", $config['join_send_gift']));
                } else {
                    $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '抱歉', "content" => "专区配置信息错误"));
                    exit();
                }
                $gift_list = $this->model->table("products as p")->join("goods as g on p.goods_id=g.id")->where("p.id in ({$gift})")->fields("p.id,g.img,g.name")->findAll();
                $this->assign("gift_list", $gift_list);
                //支付方式
                $client_type = Chips::clientType();
                $client_type = ($client_type == "desktop") ? 0 : ($client_type == "wechat" ? 2 : 1);
                $paytypelist = $this->model->table("payment as pa")->fields("pa.*,pp.logo,pp.class_name")->join("left join pay_plugin as pp on pa.plugin_id = pp.id")
                    ->where("pa.status = 0 and pa.plugin_id not in(1,12,19,20) and pa.client_type = $client_type")->order("pa.sort desc")->findAll();
                $this->assign("config", $config);
                $this->assign('paytypelist', $paytypelist);
                $this->assign('data', $district_info);
                $this->assign("reference", $reference);
                $this->assign("invitor_role", $invitor_role);
                $this->assign('seo_title', "成为推广者");
                $this->redirect();
            }
        }
    }

    //领取充值活动奖励 
    public function accept_present()
    {
        $activity = $this->model->table("recharge_activity")->where("id = 1")->fields("accept_end_time")->find();
        if ($this->is_ajax_request()) {
            $user_id = $this->user['id'];
            $accept_name = Filter::sql(Req::args("accept_name"));
            $mobile = Filter::sql(Req::args("mobile"));
            $address = Filter::sql(Req::args("address"));
            $addr = Filter::sql(Req::args("addr"));
            if ($accept_name == "" || $mobile == "" || $addr == "" || $address == "") {
                exit(json_encode(array("status" => 'fail', 'msg' => "抱歉，数据不完善")));
            }
            if (strtotime($activity['accept_end_time']) < time()) {
                $this->model->query("update tiny_recharge_presentlog set status='-1' where user_id = $user_id");
                exit(json_encode(array("status" => 'fail', 'msg' => "抱歉，已过领取时间")));
            }
            $info = $this->model->table("recharge_presentlog")->where("user_id =$user_id and status=0")->find();
            if ($info) {
                $result = $this->model->table("recharge_presentlog")->data(array("contact_man" => $accept_name, "contact_mobile" => $mobile, 'addr' => $address . " " . $addr, 'status' => 1))->where("user_id = $user_id and status=0")->update();
                if ($result) {
                    exit(json_encode(array("status" => 'success', 'msg' => "成功")));
                } else {
                    exit(json_encode(array("status" => 'fail', 'msg' => "抱歉，数据库错误")));
                }
            } else {
                exit(json_encode(array("status" => 'fail', 'msg' => "抱歉，不具备领取资格或已经领取过了")));
            }
        } else {

            if (strtotime($activity['accept_end_time']) > time()) {
                $this->assign('seo_title', "领取充值奖励");
                $this->redirect();
            } else {
                $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '抱歉', "content" => "礼品领取时间已过"));
                exit();
            }
        }
    }


    public function district_pay()
    {
        $id = Filter::int(Req::args('id'));
        $model = new Model();
        $district_info = $model->table("district_apply")->where("id={$id}")->find();
        if (!empty($district_info)) {
            if ($district_info['pay_status'] == 0) {
                $this->redirect("/index/msg", false, array('type' => "info", "msg" => '温馨提示', "content" => "不支持在线支付，请您与我司联系，洽谈相关事宜。谢谢"));
                exit();
                $config_all = Config::getInstance();
                $set = $config_all->get('district_set');
                if (isset($set['join_fee'])) {
                    $this->assign("join_fee", $set['join_fee']);
                } else {
                    $this->assign("join_fee", "10000");
                }
                $client_type = Chips::clientType();
                $client_type = ($client_type == "desktop") ? 0 : ($client_type == "wechat" ? 2 : 1);
                $paytypelist = $model->table("payment as pa")->fields("pa.*,pp.logo,pp.class_name")->join("left join pay_plugin as pp on pa.plugin_id = pp.id")
                    ->where("pa.status = 0 and pa.plugin_id not in(1,4,12,19,20) and pa.client_type = $client_type")->order("pa.sort desc")->findAll();
                $this->assign('paytypelist', $paytypelist);
                $this->assign("district", $district_info);
                $this->redirect();
            } else if ($district_info['pay_status'] == 1) {
                $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '抱歉', "content" => "已经支付过了"));
                exit();
            }
        } else {
            $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '抱歉', "content" => "信息不存在"));
            exit();
        }
    }

    //推广商品二维码页
    public function showQR()
    {
        $goods_id = Filter::int(Req::args("goods_id"));
        $goods_info = $this->model->table("goods")->where("id = " . $goods_id)->find();
        if ($goods_info) {
            $result = Common::getQrcodeFlag($goods_id, $this->user['id']);
            if ($result['status'] == 'success') {
                $this->assign("flag", $result['flag']);
                // var_dump($result['url']);die;
                // $uid = $this->user['id'];
                // $url=Url::fullUrlFormat("/index/productqr/pid/$goods_id/uid/" . $uid);
                $this->assign("url", $result['url']);
            } else {
                $this->redirect("/index/msg", false, array('type' => "info", "msg" => $result['msg']));
                exit();
            }
            $this->layout = "none";
            $this->assign("img_url", $goods_info['img']);
            $this->assign("goods_name", $goods_info['name']);
            $this->assign("goods_tags", $goods_info['tag_ids']);
            $this->assign("goods_subtitle", $goods_info['subtitle']);
            $this->redirect();
            exit();
        } else {
            $this->redirect("/index/msg", false, array('type' => "info", "msg" => "商品信息未找到"));
            exit();
        }
    }


    //获取代理商邀请信息
    public function promoter_invite()
    {
        if ($this->is_ajax_request()) {
            $page = Filter::int(Req::args('page'));
            $promoter = Promoter::getPromoterInstance($this->user['id']);
            if (is_object($promoter)) {
                if ($promoter->role_type == 1) {
                    echo json_encode(array('status' => 'fail', 'msg' => "你还不是付费代理商"));
                    exit();
                }
                $data = $promoter->getMyInviteList($page);
                if (empty($data)) {
                    echo json_encode(array('status' => 'fail', 'msg' => "数据为空"));
                    exit();
                }
                echo json_encode(array('status' => 'success', 'data' => $data['data']));
                exit();
            } else {
                exit("您还不是代理商");
            }
        } else {
            $this->assign("seo_title", "邀请入驻");
            $promoter = Promoter::getPromoterInstance($this->user['id']);
            if (is_object($promoter)) {
                if ($promoter->role_type == 1) {
                    $this->redirect("/index/msg", false, array('type' => "info", "msg" => '抱歉', "content" => "您还不是付费代理商，暂时没有邀请权限"));
                    exit();
                }
                $data = $promoter->getMyInviteList(1);
                $this->assign("data", $data);
                $invite_count = $this->model->table("district_order")->where("pay_status=1 and invitor_role='promoter' and invitor_id=$promoter->promoter_id")->count();
                $this->assign("invite_count", $invite_count);
                $config = Config::getInstance()->get("district_set");
                $this->assign("invite_promoter_num", $config['invite_promoter_num']);
                $hirer = $this->model->table("district_shop")->where("owner_id =" . $this->user['id'])->find();
                if ($hirer) {
                    $this->assign("has_district_shop", 1);
                } else {
                    $this->assign("has_district_shop", 0);
                }
            } else {
                $this->redirect("/index/msg", false, array('type' => "info", "msg" => '抱歉', "content" => "您还不是代理商"));
                exit();
            }
            $this->redirect();
        }
    }

    //获取代理商推荐二维码
    public function getPromoterInviteQR()
    {
        $promoter = Promoter::getPromoterInstance($this->user['id']);
        if (is_object($promoter)) {
            $promoter->getInviteQR4Promoter();
        } else {
            exit("您还不是代理商");
        }
    }

    //签到
    public function sign_in()
    {
        if ($this->is_ajax_request()) {
            $action = Filter::str(Req::args('action'));
            if ($action == 'sign') {
                $config = Config::getInstance();
                $set = $config->get('sign_in_set');
                if ($set['open'] == 0) {
                    exit(json_encode(array('status' => 'fail', 'msg' => "系统关闭了签到功能")));
                }
                //判断今天是否签到过
                $date = date("Y-m-d");
                $is_signed = $this->model->table("sign_in")->where("date='{$date}' and user_id=" . $this->user['id'])->find();
                if ($is_signed) {
                    exit(json_encode(array('status' => 'fail', 'msg' => "今天已经签到过了")));
                } else {
                    $last_sign = $this->model->table("sign_in")->order('date desc')->where("user_id=" . $this->user['id'])->find();
                    if ($last_sign) {
                        //判断上次签到和这次签到中间是否有缺
                        $yesterday = date("Y-m-d", strtotime("-1 day"));
                        if ($yesterday == $last_sign['date']) {
                            $data['serial_day'] = $last_sign['serial_day'] + 1;
                            $data['sign_in_count'] = $last_sign['sign_in_count'] + 1;
                        } else {
                            $data['serial_day'] = 1;
                            $data['sign_in_count'] = $last_sign['sign_in_count'] + 1;
                        }
                    } else {
                        $data['serial_day'] = 1;
                        $data['sign_in_count'] = 1;
                    }
                    $data['date'] = $date;
                    $data['user_id'] = $this->user['id'];
                    //读取签到送积分规则
                    $data['send_point'] = Common::getSignInSendPointAmount($data['serial_day']);
                    $result = $this->model->table("sign_in")->data($data)->insert();
                    if ($result) {
                        $this->model->table("customer")->data(array('point_coin' => "`point_coin`+" . $data['send_point']))->where("user_id=" . $this->user['id'])->update();
                        Log::pointcoin_log($data['send_point'], $this->user['id'], "", "每日签到赠送", 10);
                        exit(json_encode(array('status' => 'success', 'msg' => "签到成功", 'send_point' => $data['send_point'])));
                    } else {
                        exit(json_encode(array('status' => 'fail', 'msg' => "签到失败了")));
                    }
                }
            } else if ($action == 'data') {
                $year = Filter::int(Req::args("year"));
                $month = Filter::int(Req::args("month"));
                exit(json_encode(array("status" => 'success', 'data' => Common::getSignInDataByUserID($year, $month, $this->user['id']))));
            }
        } else {
            $today = $this->model->table("sign_in")->where("date='" . date("Y-m-d") . "' and user_id=" . $this->user['id'])->find();
            if ($today) {
                $this->assign('serial_day', $today['serial_day']);
                $this->assign("is_signed", true);
            } else {
                $yesterday = $this->model->table("sign_in")->where("date='" . date("Y-m-d", strtotime("-1 day")) . "' and user_id=" . $this->user['id'])->find();
                if ($yesterday) {
                    $this->assign('serial_day', $yesterday['serial_day']);
                } else {
                    $this->assign('serial_day', 0);
                }
                $this->assign("is_signed", false);
            }
            $config = Config::getInstance();
            $this->assign('sign_in_set', $config->get('sign_in_set'));
            $this->assign('sign_data', Common::getSignInDataByUserID(date("Y"), date("m"), $this->user['id']));
            $this->assign('year', date("Y"));
            $this->assign("month", date("m"));
            $this->assign('seo_title', "每日签到");
            $this->redirect();
        }
    }

    public function beagent()
    {
        $this->assign('random', rand(1000, 9999));
        $this->assign('seo_title', "圆梦共享网");
        $this->redirect();
    }

    public function invitepay()
    {
        // $id=$this->user['id'];
        $id = Req::args("user_id");
        $uid = Filter::int($id);

        $model = new Model();
        $user = $model->table('customer')->fields('real_name')->where('user_id=' . $uid)->find();
        $users = $model->table('user')->fields('avatar')->where('id=' . $uid)->find();
        if ($user) {
            $real_name = $user['real_name'];
        } else {
            $real_name = '未知商家';
        }
        if ($users) {
            if ($users['avatar'] == '' || $users['avatar'] == '/0') {
                $users['avatar'] = '/static/images/96.png';
            }
            $avatar = $users['avatar'];
        } else {
            $avatar = '';
        }
        Session::set('seller_id', $uid);
        $this->assign('real_name', $real_name);
        $this->assign('avatar', $avatar);
        $this->assign('uid', $uid);
        $this->redirect();
    }

    public function demo()
    {
        $model = new Model();
        Session::set('demo', 2);

        $inviter_id = intval(Req::args('inviter_id'));
        if (!$inviter_id) {
            $inviter_id = Session::get('seller_id');
        }
        $cashier_id = Filter::int(Req::args('cashier_id'));//收银员id
        if(!$cashier_id) {
            $cashier_id = 0;
        }
        $desk_id = Filter::int(Req::args('desk_id'));//收银员id
        if(!$desk_id) {
            $desk_id = 0;
        }
        if($cashier_id!=0 || $desk_id!=0) {
            $cash = 1;
        } else {
            $cash = 0;
        }
        if(in_array($inviter_id, [101738,87455,55568,8158,25795,31751]) && date('Y-m-d H:i:s')>'2018-05-15 12:00:00' && date('Y-m-d H:i:s')<'2018-06-15 12:00:00'){
            $this->redirect("/index/msg", false, array('type' => 'fail', 'msg' => '该商户违规操作，冻结收款功能！'));
            exit;
        }  
        if(in_array($inviter_id, [55568,21079])) {
            $this->redirect("/index/msg", false, array('type' => 'fail', 'msg' => '该商户违规操作，冻结收款功能！'));
            exit;
        }
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient') !== false) {
            $pay_type = 'alipay';
            $from = 'alipay';
        } else {
            $pay_type = 'wechat';
            $from = 'second-wap';
        }
        if (isset($this->user['id']) && $this->user['id']!=140531) {
            Common::buildInviteShip($inviter_id, $this->user['id'], $from);
        } else {
            // Cookie::set("inviter", $inviter_id);
            // $this->noRight();
            $redirect = "http://www.ymlypt.com/ucenter/demo/inviter_id/".$inviter_id;
            if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
               //微信授权登录
                    $code = Filter::sql(Req::args('code'));
                    $oauth = new WechatOAuth();
                    
                    $url = $oauth->getCodes($redirect);
                    if($code) {
                        $extend = null;
                        $token = $oauth->getAccessToken($code, $extend);
                        $userinfo = $oauth->getUserInfo();
                        if(!empty($userinfo)) {
                            $openid = $token['openid'];
                            $oauth_user = $this->model->table('oauth_user')->where("oauth_type='wechat' AND open_id='{$openid}'")->find();

                            if(!$oauth_user) { //未注册
                                //插入user表
                                $passWord = CHash::random(6);
                                $validcode = CHash::random(8);
                                $user_id = $this->model->table("user")->data(array('nickname' => $userinfo['open_name'], 'password' => CHash::md5($passWord, $validcode), 'avatar' => $userinfo['head'], 'validcode' => $validcode))->insert();
                                $name = "u" . sprintf("%09d", $user_id);
                                $email = $name . "@no.com";
                                $time = date('Y-m-d H:i:s');
                                $this->model->table("user")->data(array('name' => $name, 'email' => $email))->where("id = ".$user_id)->update();

                                //插入customer表
                                $this->model->table("customer")->data(array('user_id' => $user_id, 'real_name' => $userinfo['open_name'], 'point_coin'=>200, 'reg_time' => $time, 'login_time' => $time))->insert();
                                Log::pointcoin_log(200, $user_id, '', '微信新用户积分奖励', 10);

                                //插入oauth_user表
                                $this->model->table('oauth_user')->data(array(
                                        'user_id' => $user_id, 
                                        'open_name' => $userinfo['open_name'],
                                        'oauth_type' => "wechat",
                                        'posttime' => time(),
                                        'token' => $token['access_token'],
                                        'expires' => $token['expires_in'],
                                        'open_id' => $token['openid']
                                    ))->insert();

                                //记录登录信息
                                $obj = $this->model->table("user as us")->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.login_time,cu.mobile,cu.real_name")->where("us.id='$user_id'")->find();
                                $obj['open_id'] = $token['openid'];
                                $this->safebox->set('user', $obj, 1800);
                                $this->user['id'] = $user_id;
                            } else { //已注册
                                $this->model->table("customer")->data(array('login_time' => date('Y-m-d H:i:s')))->where('user_id='.$oauth_user['user_id'])->update();
                                $obj = $this->model->table("user as us")->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.mobile,cu.login_time,cu.real_name")->where("us.id=".$oauth_user['user_id'])->find();
                                $this->safebox->set('user', $obj, 31622400);
                                $user_id = $oauth_user['user_id'];
                                $this->user['id'] = $user_id;
                            }
                            if($inviter_id){
                                Common::buildInviteShip($inviter_id, $this->user['id'], 'second-wap');
                            }   
                        }
                    } else {
                        header("Location: {$url}"); 
                    }
            } elseif(strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient') !== false) {
                //支付宝授权登录
                if (isset($_GET['inviter_id']) && !isset($_GET['auth_code'])) {
                    $act = "https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?app_id=2017080107981760&scope=auth_user&redirect_uri=http://www.ymlypt.com/ucenter/demo&state=test&inviter_id=" . $_GET['inviter_id'];
                    if($cashier_id!=0) {
                        $act.='&cashier_id='.$cashier_id;
                    }
                    if($desk_id!=0) {
                        $act.='&desk_id='.$desk_id;
                    }
                    $this->redirect($act);
                    exit;
                } else {
                    $auth_code = $_GET['auth_code'];
                    $seller_id = $_GET['inviter_id'];
                    if(isset($_GET['cashier_id']) && $_GET['cashier_id']!=0) {
                        $cashier_id = $_GET['cashier_id'];
                    }
                    if(isset($_GET['desk_id']) && $_GET['desk_id']!=0) {
                        $desk_id = $_GET['desk_id'];
                    }
                    if($cashier_id!=0 || $desk_id!=0) {
                        $cash = 1;
                    } else {
                        $cash = 0;
                    }
                    $pay_alipayapp = new pay_alipayapp();
                    $result = $pay_alipayapp->alipayLogin($auth_code);
                    if (!isset($result['code']) || $result['code'] != 10000) {
                        $this->redirect("/index/msg", false, array('type' => 'fail', 'msg' => '支付宝授权登录失败！'));
                        exit;
                    }
                    $nick_name = isset($result['nick_name']) ? $result['nick_name'] : '';
                    $is_oauth = $this->model->table('oauth_user')->where('open_id="' . $result['user_id'] . '" and oauth_type="alipay"')->find();
                    if($result['user_id']=='2088702887592132') {
                        var_dump($_GET['cashier_id']);die;
                    }
                    if ($is_oauth) {
                        $obj = $this->model->table("user as us")->join("left join customer as cu on us.id = cu.user_id left join oauth_user as o on us.id = o.user_id")->fields("us.*,cu.mobile,cu.group_id,cu.login_time,cu.real_name")->where("o.open_id='{$result['user_id']}'")->find();
                        $this->safebox->set('user', $obj, 31622400);
                        $this->user = $this->safebox->get('user');
                        $this->user['id'] = $obj['id'];
                        // if($this->user['id']==140531 || $this->user['id']==190665) {
                        //     var_dump($cashier_id);die;
                        // }
                    } else {
                        $this->model->table('oauth_user')->data(array(
                            'open_name' => $nick_name,
                            'oauth_type' => 'alipay',
                            'posttime' => time(),
                            'token' => '',
                            'expires' => '7200',
                            'open_id' => $result['user_id']
                        ))->insert();
                        Session::set('openname', $nick_name);
                        $passWord = CHash::random(6);
                        $time = date('Y-m-d H:i:s');
                        $validcode = CHash::random(8);
                        $model = $this->model;
                        $avatar = isset($result['avatar'])?$result['avatar']:'http://www.ymlypt.com/themes/mobile/images/logo-new.png';
                        $last_id = $model->table("user")->data(array('nickname' => $nick_name, 'password' => CHash::md5($passWord, $validcode), 'avatar' => $avatar, 'validcode' => $validcode))->insert();
                        $name = "u" . sprintf("%09d", $last_id);
                        $email = $name . "@no.com";
                        //更新用户名和邮箱
                        $model->table("user")->data(array('name' => $name, 'email' => $email))->where("id = '{$last_id}'")->update();
                        //更新customer表
                        $sex = isset($result['gender']) && $result['gender']== 'm' ? 1 : 0;
                        $model->table("customer")->data(array('user_id' => $last_id, 'real_name' => $nick_name, 'sex' => $sex, 'point_coin' => 200, 'reg_time' => $time, 'login_time' => $time))->insert();
                        Log::pointcoin_log(200, $last_id, '', '支付宝新用户积分奖励', 10);
                        //记录登录信息
                        $obj = $model->table("user as us")->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.group_id,cu.login_time,cu.mobile")->where("us.id='$last_id'")->find();
                        $this->safebox->set('user', $obj, 31622400);
                        $this->user = $this->safebox->get('user');
                        $this->model->table('oauth_user')->where("oauth_type='alipay' and open_id='{$result['user_id']}'")->data(array('user_id' => $last_id))->update();
                        $this->user['id'] = $last_id;
                        if($inviter_id){
                            Common::buildInviteShip($inviter_id, $this->user['id'], 'alipay');
                        } 
                    }
                }
            } else {
                $this->redirect("/index/msg", false, array('type' => 'fail', 'msg' => '请在微信或在支付宝中打开'));
                exit;
            }
        }
        $user_id = $this->user['id'];
        $shop = $this->model->table('customer as c')->fields('c.real_name,u.nickname,u.avatar')->join('left join user as u on c.user_id=u.id')->where('c.user_id=' . $inviter_id)->find();

        if ($shop) {
            $this->assign('shop_name', $shop['real_name']);
        } else {
            $this->assign('shop_name', '未知商家');
        }
        $this->assign('avatar', $shop['avatar']); 
        $order_no = date('YmdHis') . rand(1000, 9999);
        // $jsApiParameters = Session::get('payinfo');
        // $this->assign("jsApiParameters",$jsApiParameters);
        $this->assign("seo_title", "向商家付款");
        $this->assign('seller_id', $inviter_id);
        $this->assign('cashier_id', $cashier_id);
        $this->assign('desk_id', $desk_id);
        $this->assign('seller_ids', Session::get('seller_id'));
        $this->assign('order_no', $order_no);
        $this->assign('user_id', $user_id);
        $third_pay = 0;
        $third_payment = $this->model->table('third_payment')->where('id=1')->find();
        if ($third_payment) {
            $third_pay = $third_payment['third_payment'];
        }
        $models = new Model("payment as pa");
        $paytypelist = $models->fields("pa.*,pp.logo,pp.class_name")->join("left join pay_plugin as pp on pa.plugin_id = pp.id")
            ->where("pa.id in (6,8)")->order("pa.sort desc")->findAll();
        $paytypeone = reset($paytypelist);
        $this->assign("paytypeone", $paytypeone);
        $this->assign("paytypelist", $paytypelist);
        $this->assign("pay_type", $pay_type);
        $this->assign('third_pay', $third_pay);
        $this->assign('cash', $cash);
        $this->redirect();
    }

    //实名认证
    public function set_realname()
    {
        $this->assign("seo_title", "实名认证");
        $this->redirect();
    }

    /**
     * 个人创建会员+实名认证
     * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
     * @param $isAuth  是否由云账户进行认证  true/false  默认为true   目前必须通过云账户认证
     * @param $name   姓名
     * @param $identityType 证件类型     身份证 1  护照 2   港澳通行证 3    目前只支持身份证。
     * @param $identityNo 证件号码      RSA加密
     */

    // public function realNameVerify()
    // {
    //     $user = $this->model->table('customer')->fields('realname_verified')->where('user_id=' . $this->user['id'])->find();
    //     if (!$user) {
    //         exit(json_encode(array('status' => 'fail', 'msg' => '用户不存在')));
    //     }
    //     if ($user['realname_verified'] == 1) {
    //         exit(json_encode(array('status' => 'fail', 'msg' => '您已经通过实名认证了')));
    //     }
    //     $name = Req::args('name');
    //     $bizUserId = date('YmdHis') . $this->user['id'];
    //     $identityType = Filter::int(Req::args('identityType'));
    //     $identityNo = Req::args('identityNo');
    //     $memberType = 3;
    //     $source = 1;

    //     $client = new SOAClient();
    //     $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
    //     $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
    //     $client->setServerAddress($this->serverAddress);
    //     $client->setSignKey($privateKey);
    //     $client->setPublicKey($publicKey);
    //     $client->setSysId($this->sysid);
    //     $client->setSignMethod($this->signMethod);
    //     $param["bizUserId"] = $bizUserId;
    //     $param["memberType"] = $memberType;    //会员类型
    //     $param["source"] = $source;        //访问终端类型
    //     $result1 = $client->request("MemberService", "createMember", $param);

    //     $params["bizUserId"] = $bizUserId;    //商户系统用户标识，商户系统中唯一编号
    //     $params["isAuth"] = true;
    //     $params["name"] = $name;
    //     $params["identityType"] = $identityType;
    //     $params["identityNo"] = $this->rsaEncrypt($identityNo, $publicKey, $privateKey);
    //     $result2 = $client->request("MemberService", "setRealName", $params);
    //     if ($result1['status'] == 'OK' && $result2['status'] == 'OK') {
    //         $this->model->table('customer')->data(array('realname_verified' => 1, 'bizuserid' => $bizUserId, 'realname' => $name, 'id_no' => $identityNo))->where('user_id=' . $this->user['id'])->update();
    //         exit(json_encode(array('status' => 'success', 'msg' => '实名认证成功')));
    //     } elseif ($result1['status'] == 'OK' && $result2['status'] != 'OK') {
    //         $this->model->table('customer')->data(array('realname_verified' => -1, 'bizuserid' => $bizUserId))->where('user_id=' . $this->user['id'])->update();
    //         exit(json_encode(array('status' => 'fail', 'msg' => '未通过验证')));
    //     } else {
    //         exit(json_encode(array('status' => 'fail', 'msg' => '实名认证失败，请核对信息是否准确无误！')));
    //     }

    // }

    public function realNameVerify()
    {
        $idcard = Req::args('identityNo');
        $realname = Filter::str(Req::args('name'));

        $customer = $this->model->table('customer')->fields('realname_verified')->where('user_id=' . $this->user['id'])->find();
        if (!$customer) {
            exit(json_encode(array('status' => 'fail', 'msg' => '用户不存在')));
        }

        if ($customer['realname_verified'] == 1) { //已认证
            exit(json_encode(array('status' => 'fail', 'msg' => '您已经通过实名认证了')));
        }

        $url = "https://aliyun-bankcard-verify.apistore.cn/bank?Mobile=&bankcard=&cardNo=" . $idcard . "&realName=" . $realname;
        $header = array(
            'Authorization:APPCODE 8d41495e483346a5a683081fd046c0f2'
        );

        $ret = Common::httpRequest($url, 'GET', NULL, $header);
        $result = json_decode($ret, true);
        if ($result['error_code'] == 0) {
            $this->model->table('customer')->data(array('realname_verified' => 1, 'realname' => $realname, 'id_no' => $idcard))->where('user_id=' . $this->user['id'])->update();
            exit(json_encode(array('status' => 'success', 'msg' => '实名认证成功')));
        } else {
            exit(json_encode(array('status' => 'fail', 'msg' => '实名认证失败，请核对信息是否准确无误！')));
        }
    }

    //加密
    public function rsaEncrypt($str, $publicKey, $privateKey)
    {
        $rsaUtil = new RSAUtil($publicKey, $privateKey);
        $encryptStr = $rsaUtil->encrypt($str);
        return $encryptStr;
    }

    //解密
    public function rsaDecrypt($str, $publicKey, $privateKey)
    {
        $rsaUtil = new RSAUtil($publicKey, $privateKey);
        $encryptStr = $rsaUtil->decrypt($str);
        return $encryptStr;
    }

    //绑定银行卡 html页面
    public function bind_bankcard()
    {
        $jump = Req::args('jump');
        $customer = $this->model->table('customer')->fields('realname_verified,realname,id_no')->where('user_id=' . $this->user['id'])->find();
        $this->assign('customer', $customer);
        $this->assign('jump', $jump);
        $this->assign("seo_title", "绑定银行卡");
        $this->redirect();
    }

    //绑定银行卡 业务逻辑处理
    // public function bindbancard_do()
    // {
    //     $client = new SOAClient();
    //     $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
    //     $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
    //     $client->setServerAddress($this->serverAddress);
    //     $client->setSignKey($privateKey);
    //     $client->setPublicKey($publicKey);
    //     $client->setSysId($this->sysid);
    //     $client->setSignMethod($this->signMethod);

    //     $user = $this->model->table('customer')->fields('id_no')->where('realname_verified=1 and user_id=' . $this->user['id'])->find();
    //     if (!$user) {
    //         exit(json_encode(array('status' => 'fail', 'msg' => '请先实名认证')));
    //     } else {
    //         $identityNo = $user['id_no'];
    //     }
    //     $bizUserId = date('YmdHis') . $this->user['id'];
    //     $cardNo = Req::args('cardNo');
    //     $phone = Req::args('phone');
    //     $name = Req::args('name');
    //     $cardCheck = 1; //绑卡方式 1三要素绑卡
    //     $identityType = 1;//证件类型 1是身份证 目前只支持身份证
    //     $param["bizUserId"] = $bizUserId;    //商户系统用户标识，商户系统中唯一编号
    //     $param["cardNo"] = $this->rsaEncrypt($cardNo, $publicKey, $privateKey);//银行卡号
    //     $param["phone"] = $phone;  //银行预留的手机卡号
    //     $param["name"] = $name; //用户的姓名
    //     $param["cardCheck"] = $cardCheck; //绑卡方式
    //     $param["identityType"] = $identityType;
    //     $param["identityNo"] = $this->rsaEncrypt($identityNo, $publicKey, $privateKey);//必须rsa加密 身份证号码
    //     $result = $client->request("MemberService", "applyBindBankCard", $param);
    //     if ($result['status'] == 'OK') {
    //         exit(json_encode(array('status'=>'success','msg'=>'绑定银行卡成功')));
    //     } else {
    //         exit(json_encode(array('status'=>'fail','msg'=>'绑定银行卡失败')));
    //     }
    // }

    public function bindbancard_do()
    {
        $bankcard = str_replace(' ', '', Req::args('cardNo'));

        $realname = Filter::str(Req::args('name'));
        $province = Filter::str(Req::args('province'));
        $city = Filter::str(Req::args('city'));

        $customer = $this->model->table('customer')->fields('realname_verified,id_no')->where('user_id=' . $this->user['id'])->find();

        if ($customer['realname_verified'] == 0) { //需要先实名认证
            exit(json_encode(array('status' => 'fail', 'msg' => '需要先实名认证')));
        }
        $idcard = $customer['id_no'];
        $url = "https://aliyun-bankcard-verify.apistore.cn/bank?Mobile=&bankcard=" . $bankcard . "&cardNo=" . $idcard . "&realName=" . $realname;
        $header = array(
            'Authorization:APPCODE 8d41495e483346a5a683081fd046c0f2'
        );

        $ret = Common::httpRequest($url, 'GET', NULL, $header);
        $result = json_decode($ret, true);
        if ($result['error_code'] == 0) {
            $has_bind = $this->model->table('bankcard')->where('cardno=' . $bankcard)->find();
            if ($has_bind) {
                exit(json_encode(array('status' => 'fail', 'msg' => '该银行卡已绑定了')));
            }
            $bank_code = $result['result']['information']['abbreviation'];
            if ($bank_code) {
                $logo = 'https://apimg.alipay.com/combo.png?d=cashier&t=' . $bank_code;
            } else {
                $logo = '';
            }
            $data = array(
                'user_id' => $this->user['id'],
                'cardno' => $bankcard,
                'bank_name' => $result['result']['information']['bankname'],
                'open_name' => $realname,
                'province' => $province,
                'city' => $city,
                'type' => intval($result['result']['information']['iscreditcard']),
                'bank_code' => $bank_code,
                'logo' => $logo,
                'bind_date' => date('Y-m-d H:i:s')
            );
            $this->model->table('bankcard')->data($data)->insert();
            exit(json_encode(array('status' => 'success', 'msg' => '绑定银行卡成功')));
        } else {
            exit(json_encode(array('status' => 'fail', 'msg' => '绑定银行卡失败')));
        }
    }

    public function code_input()
    {
        $this->redirect();
    }

    public function toBePromoter()
    {
        $code = Filter::str(Req::args('code'));
        $rules = array('code:required:激活码不能为空!');
        $info = Validator::check($rules);
        if (is_array($info)) {
            $this->redirect("code_input", false, array('msg' => array("info", $info['msg'])));
        } else {
            $exist = $this->model->table('district_promoter')->where('user_id=' . $this->user['id'])->find();
            if ($exist) {
                $this->redirect("code_input", false, array('msg' => array("info", '您已经是代理了')));
                exit;
            }
            $promoter_code = $this->model->table('promoter_code')->where("code ='{$code}'")->find();
            if (!$promoter_code) {
                $this->redirect("code_input", false, array('msg' => array("info", '激活码不正确')));
                exit;
            }
            if (time() > strtotime($promoter_code['end_date'])) {
                $this->redirect("code_input", false, array('msg' => array("info", '激活码已过期')));
                exit;
            }
            if ($promoter_code['status'] == 0) {
                $this->redirect("code_input", false, array('msg' => array("info", '激活码已失效')));
                exit;
            }
            $result = $this->model->table('district_promoter')->data(array('user_id' => $this->user['id'], 'type' => 6, 'invitor_id' => $promoter_code['user_id'], 'create_time' => date('Y-m-d H:i:s'), 'join_time' => date('Y-m-d H:i:s'), 'hirer_id' => $promoter_code['district_id']))->insert();
            $point = 3600.00;
            $this->model->table('customer')->data(array('point_coin'=>"`point_coin`+({$point})"))->where('user_id='.$this->user['id'])->update();
            Log::pointcoin_log($point,$this->user['id'], '', "激活码激活升级为代理商积分赠送", 5);
            $invite = $this->model->table('invite')->where('invite_user_id=' . $this->user['id'])->find();
            if (!$invite) {
                $this->model->table('invite')->data(array('user_id' => $promoter_code['user_id'], 'invite_user_id' => $this->user['id'], 'from' => 'jihuo', 'district_id' => $promoter_code['district_id'], 'createtime' => time()))->insert();
            }
            if ($result) {
                $this->model->table('promoter_code')->data(array('status' => 0))->where("code ='{$code}'")->update();
                $this->redirect("/ucenter/index", false, array('msg' => array("success", "激活成功！")));
            } else {
                $this->redirect("code_input", false, array('msg' => array("info", '激活失败')));
                exit;
            }
        }
    }

    public function dinpay()
    {
        $merchant_private_key = 'MIICeAIBADANBgkqhkiG9w0BAQEFAASCAmIwggJeAgEAAoGBAKwJnd8sHJojXIFxuf4Ibsdtc2cJHPlN2d/IKMBw5cuoRknNeMCTlR89MxEqfuPqYR7o1dGgOiehswR9T4vWByzhJlrLEFcgOcJFnDINzU9iZW4RcRKf187sLXYL8b5Vf5WjEfudXjnxSGt8HXPe+V0VimUVaIAQSWvBCWgHkFV/AgMBAAECgYBivF40EJAV0serrwatCk/x+xopf2x2lLy/l5Pz5pesS9aTUu7Dr6/9LtWZO4d57TFyWPUmi0v1JPOmVvkJa3vPz6HhZIzg5M4jd23Kj8fl94PaTSyGM3NEMRJDLPxWEB9ydR60VtRlieCf2lyH0JSKa5YMS09A6ks13W4SVNRqaQJBAOF22itr0KonXZaQxNIOrnGifCvBA11cKV1SMxT5iLOuYu5j2VOZNExC5oD4j1fkT/7kEq+7OSTEOhZwgcNkcGUCQQDDVmOlmKHBjUpMmv0xfc789Zj7PLoKO9WpYkDTbl7xPdc/Yb0OeeZlS123ZlplXLMVPpOQTpFcrbk9nhShaSYTAkEAhnrPsqqCMZt9VPtQikI7hof2LFrZ2OvJuGH5Gf+krBfN5ocj75sn+HzG5BJd3XzOwifjhXHUqbtpMk00+QiFiQJBAIv2JGQM3yn+ANSu4OhLSrp5h2nM80hN4yQA4I4eMS0NsGMbtwjeUzUVMUstrWufZjm8oqLtiL4tQ+Ngl0uoOb0CQQCuOR315Fwm/BW3QXjaASDwN8sahQxfNAtUyh7oGJfieKWYEjd3VYfaWXyful7FWW/Ry8H1pOSbIJZo07gLVTvA';

        $merchant_public_key = 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCsCZ3fLByaI1yBcbn+CG7HbXNnCRz5TdnfyCjAcOXLqEZJzXjAk5UfPTMRKn7j6mEe6NXRoDonobMEfU+L1gcs4SZayxBXIDnCRZwyDc1PYmVuEXESn9fO7C12C/G+VX+VoxH7nV458UhrfB1z3vldFYplFWiAEElrwQloB5BVfwIDAQAB';

        $dinpay_public_key = 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCOglLSDWk8iIHH5zFvAg9n++I4iew5Zj4M/8J8TLRj7UShJ3roroNgCkH1Iyw65xIddlCfJK8wkszpZ4OvPRiCDUBaEMENF/TQmscL2M+Ly7XEQ34RTQ1WVcpkZb7KJuiK3XIByYM0fETM1RVhQGJsnC7QpDaorjkWjpuLcR6bDwIDAQAB ';


        // $merchant_code = "1111110166";//商户号，1118004517是测试商户号，线上发布时要更换商家自己的商户号！
        $merchant_code = "4000038801";

        $service_type = "direct_pay";

        $interface_version = "V3.0";

        $sign_type = "RSA-S";

        $input_charset = "UTF-8";

        // $notify_url ="http://15l0549c66.iask.in:45191/testnewb2c/offline_notify.php";
        $notify_url = "http://www.ymlypt.com/payment/dinpay_callback";

        $order_no = Common::createOrderNo();

        // $order_no='dinpay'.$order_no;

        $order_time = date('Y-m-d H:i:s');

        $order_amount = Req::args('amount') ? Req::args('amount') : '0.01';

        $product_name = "testpay";

        //插入订单
        $data['type'] = 4;
        $data['order_no'] = $order_no;
        $data['user_id'] = $this->user['id'];
        $data['payment'] = 30;
        $data['status'] = 2;
        $data['pay_status'] = 0;
        $data['accept_name'] = '';
        $data['mobile'] = '';
        $data['payable_amount'] = $order_amount;
        $data['create_time'] = $order_time;
        $data['pay_time'] = $order_time;
        $data['handling_fee'] = 0.00;
        $data['order_amount'] = $order_amount;
        $data['real_amount'] = $order_amount;
        $data['point'] = 0;
        $data['voucher_id'] = 0;
        $data['prom_id'] = 0;
        $data['shop_ids'] = 0;
        $model = new Model('order_offline');
        $exist = $model->where('order_no=' . $order_no)->find();
        if (!$exist) {
            $order_id = $model->data($data)->insert();
        } else {
            $order_id = $exist['id'];
        }

        //以下参数为可选参数，如有需要，可参考文档设定参数值

        $return_url = "http://www.ymlypt.com/ucenter/order_details/id/{$order_id}";

        $pay_type = "";

        $redo_flag = "";

        $product_code = "";

        $product_desc = "";

        $product_num = "";

        $show_url = "";

        $client_ip = "";

        $bank_code = "";

        $extend_param = "";

        $extra_return_param = "";


/////////////////////////////   参数组装  /////////////////////////////////
        /**
         * 除了sign_type参数，其他非空参数都要参与组装，组装顺序是按照a~z的顺序，下划线"_"优先于字母
         */

        $signStr = "";

        if ($bank_code != "") {
            $signStr = $signStr . "bank_code=" . $bank_code . "&";
        }
        if ($client_ip != "") {
            $signStr = $signStr . "client_ip=" . $client_ip . "&";
        }
        if ($extend_param != "") {
            $signStr = $signStr . "extend_param=" . $extend_param . "&";
        }
        if ($extra_return_param != "") {
            $signStr = $signStr . "extra_return_param=" . $extra_return_param . "&";
        }

        $signStr = $signStr . "input_charset=" . $input_charset . "&";
        $signStr = $signStr . "interface_version=" . $interface_version . "&";
        $signStr = $signStr . "merchant_code=" . $merchant_code . "&";
        $signStr = $signStr . "notify_url=" . $notify_url . "&";
        $signStr = $signStr . "order_amount=" . $order_amount . "&";
        $signStr = $signStr . "order_no=" . $order_no . "&";
        $signStr = $signStr . "order_time=" . $order_time . "&";

        if ($pay_type != "") {
            $signStr = $signStr . "pay_type=" . $pay_type . "&";
        }

        if ($product_code != "") {
            $signStr = $signStr . "product_code=" . $product_code . "&";
        }
        if ($product_desc != "") {
            $signStr = $signStr . "product_desc=" . $product_desc . "&";
        }

        $signStr = $signStr . "product_name=" . $product_name . "&";

        if ($product_num != "") {
            $signStr = $signStr . "product_num=" . $product_num . "&";
        }
        if ($redo_flag != "") {
            $signStr = $signStr . "redo_flag=" . $redo_flag . "&";
        }
        if ($return_url != "") {
            $signStr = $signStr . "return_url=" . $return_url . "&";
        }

        $signStr = $signStr . "service_type=" . $service_type;

        if ($show_url != "") {

            $signStr = $signStr . "&show_url=" . $show_url;
        }

        //echo $signStr."<br>";


/////////////////////////////   获取sign值（RSA-S加密）  /////////////////////////////////
        $merchant_private_key = "-----BEGIN PRIVATE KEY-----" . "\r\n" . wordwrap(trim($merchant_private_key), 64, "\r\n", true) . "\r\n" . "-----END PRIVATE KEY-----";

        $merchant_private_key = openssl_get_privatekey($merchant_private_key);

        openssl_sign($signStr, $sign_info, $merchant_private_key, OPENSSL_ALGO_MD5);

        $sign = base64_encode($sign_info);

        // $params = array(
        //     'sign'=>$sign,
        //     'merchant_code'=>$merchant_code,
        //     'order_no'=>$order_no,
        //     'order_amount'=>$order_amount,
        //     'service_type'=>$service_type,
        //     'input_charset'=>$input_charset,
        //     'notify_url'=>$notify_url,
        //     'interface_version'=>$interface_version,
        //     'sign_type'=>$sign_type,
        //     'order_time'=>$order_time,
        //     'product_name'=>$product_name,
        //     'client_ip'=>$client_ip,
        //     'extend_param'=>$extend_param,
        //     'extra_return_param'=>$extra_return_param,
        //     'pay_type'=>$pay_type,
        //     'product_code'=>$product_code,
        //     'product_desc'=>$product_desc,
        //     'product_num'=>$product_num,
        //     'return_url'=>$return_url,
        //     'show_url'=>$show_url,
        //     'redo_flag'=>$redo_flag
        //     );
        //   echo "<pre>";
        //   print_r($params);
        //   echo "<pre>";
        //   die;

        $this->assign('sign', $sign);
        $this->assign('merchant_code', $merchant_code);
        $this->assign('service_type', $service_type);
        $this->assign('interface_version', $interface_version);
        $this->assign('sign_type', $sign_type);
        $this->assign('input_charset', $input_charset);
        $this->assign('notify_url', $notify_url);
        $this->assign('order_no', $order_no);
        $this->assign('order_time', $order_time);
        $this->assign('client_ip', $client_ip);
        $this->assign('extend_param', $extend_param);
        $this->assign('extra_return_param', $extra_return_param);
        $this->assign('pay_type', $pay_type);
        $this->assign('product_code', $product_code);
        $this->assign('product_name', $product_name);
        $this->assign('product_desc', $product_desc);
        $this->assign('product_num', $product_num);
        $this->assign('return_url', $return_url);
        $this->assign('show_url', $show_url);
        $this->assign('redo_flag', $redo_flag);
        $this->redirect();
    }

    public function district_login()
    {
        $this->redirect();
        // $district = $this->model->table('district_shop')->where('owner_id='.$this->user['id'])->find();
        // if(!$district){
        //     $this->redirect();
        // }else{
        //     $this->redirect('/district/district');
        // }     
    }

    public function district()
    {
        $district = $this->model->table('district_shop')->where('owner_id=' . $this->user['id'])->find();
        if (!$district) {
            $this->redirect('/ucenter/district_login');
        } else {
            // $this->layout = "district_layout";
            $this->assign('test', false);
            $this->assign('district_name', $district['name']);
            $this->assign("seo_title", "专区管理");
            $this->redirect();
        }
    }

    public function shop_check_do()
    {
        $myParams = array();  

        $myParams['method'] = 'ysepay.merchant.register.token.get';
        $myParams['partner_id'] = 'yuanmeng';
        // $myParams['partner_id'] = $this->user['id'];
        $myParams['timestamp'] = date('Y-m-d H:i:s', time());
        $myParams['charset'] = 'GBK';
        $myParams['notify_url'] = 'http://api.test.ysepay.net/atinterface/receive_return.htm';      
        $myParams['sign_type'] = 'RSA';  

        $myParams['version'] = '3.0';
        $biz_content_arr = array(
        );

        $myParams['biz_content'] = '{}';
        ksort($myParams);

        $signStr = "";
        foreach ($myParams as $key => $val) {
            $signStr .= $key . '=' . $val . '&';
        }
        $signStr = rtrim($signStr, '&');
        $sign = $this->sign_encrypt(array('data' => $signStr));
        $myParams['sign'] = trim($sign['check']);
        $url = 'https://register.ysepay.com:2443/register_gateway/gateway.do';

        $ret = Common::httpRequest($url,'POST',$myParams);
        $ret = json_decode($ret,true);

        $type = Filter::int(Req::args('shop_type')); //1实体商家 2个人微商
        if ($type == 0) {
            $this->redirect("ucenter/offline_balance_withdraw", false, array('msg' => array("warning", "店铺类型未选择")));
        }
        $business_licence = Req::args('business_licence_url'); //营业执照
        $positive_idcard = Req::args('positive_idcard_url'); //身份证正面照
        $native_idcard = Req::args('native_idcard_url'); //身份证反面照
        $positive_bankcard = Req::args('positive_bankcard_url'); //银行卡正面照
        $native_bankcard = Req::args('native_bankcard_url'); //银行卡反面照
        $account_picture = Req::args('account_picture'); //开户许可证照
        $account_card = Req::args('account_card'); //结算银行卡号
        $bank_name = Req::args('bank_name');
        $shop_photo = Req::args('shop_photo_url'); //门店照
        $hand_idcard = Req::args('hand_idcard_url'); //手持身份证照
        
        // if ($this->user['id'] == 42608) {
        //     $data = array(
        //         'picType'=>'00',
        //         'picFile'=>curl_file_create($positive_idcard),
        //         'token'=>$ret['ysepay_merchant_register_token_get_response']['token'],
        //         'superUsercode'=>'yuanmeng'
        //         );
        //     $act = "https://uploadApi.ysepay.com:2443/yspay-upload-service?method=upload";
        //     // $header = array(
        //     //     'Content-Type:multipart/form-data'
        //     //     );
        //     $result = Common::httpRequest($act,'POST',$data);
        //     // var_dump($data);
        //     echo "<pre>";
        //     print_r($result);
        //     echo "<pre>";
        //     die;
        // }

        $this->model->table('district_promoter')->data(array('shop_type' => $type))->where('user_id=' . $this->user['id'])->update();

        $data = array(
            'user_id' => $this->user['id'],
            'type' => $type,
            'business_licence' => $business_licence,
            'positive_idcard' => $positive_idcard,
            'native_idcard' => $native_idcard,
            'positive_bankcard' => $positive_bankcard,
            'native_bankcard' => $native_bankcard,
            'account_picture'=>$account_picture,
            'account_card' => $account_card,
            'bank_name' => $bank_name,
            'shop_photo' => $shop_photo,
            'hand_idcard' => $hand_idcard,
            'status' => 0,
            'create_date' => date('Y-m-d H:i:s')
        );
        $shop_check = $this->model->table('shop_check')->fields('id,status')->where('user_id=' . $this->user['id'])->find();
        if (!$shop_check) {
            $this->model->table('shop_check')->data($data)->insert();
        } else {
            $this->model->table('shop_check')->data($data)->where('id=' . $shop_check['id'])->update();
        }
        $this->redirect("ucenter/offline_balance_withdraw", false, array('msg' => array("success", "提交成功！")));
    }

    public function sign_encrypt($input)
    {
        // $pfxpath = 'http://' . $_SERVER['HTTP_HOST'] . "/trunk/protected/classes/yinpay/certs/shanghu_test.pfx";
        $pfxpath = "./protected/classes/yinpay/certs/yuanmeng.pfx";
        $pfxpassword = 'lc008596';
        $return = array('success' => 0, 'msg' => '', 'check' => '');
        $pkcs12 = file_get_contents($pfxpath); //私钥
        if (openssl_pkcs12_read($pkcs12, $certs, $pfxpassword)) {
            $privateKey = $certs['pkey'];
            $publicKey = $certs['cert'];
            $signedMsg = "";
            if (openssl_sign($input['data'], $signedMsg, $privateKey, OPENSSL_ALGO_SHA1)) {
                $return['success'] = 1;
                $return['check'] = base64_encode($signedMsg);
                $return['msg'] = base64_encode($input['data']);

            }
        }

        return $return;
    }

    public function yinpay_upload_test()
    {
        $myParams = array();

        $myParams['method'] = 'ysepay.merchant.register.token.get';
        $myParams['partner_id'] = 'yuanmeng';
        // $myParams['partner_id'] = $this->user['id'];
        $myParams['timestamp'] = date('Y-m-d H:i:s', time());
        $myParams['charset'] = 'GBK';
        $myParams['notify_url'] = 'http://api.test.ysepay.net/atinterface/receive_return.htm';
        $myParams['sign_type'] = 'RSA';

        $myParams['version'] = '3.0';
        $biz_content_arr = array();

        $myParams['biz_content'] = '{}';
        ksort($myParams);

        $signStr = "";
        foreach ($myParams as $key => $val) {
            $signStr .= $key . '=' . $val . '&';
        }
        $signStr = rtrim($signStr, '&');
        $sign = $this->sign_encrypt(array('data' => $signStr));
        $myParams['sign'] = trim($sign['check']);
        $url = 'https://register.ysepay.com:2443/register_gateway/gateway.do';

        $ret = Common::httpRequest($url, 'POST', $myParams);
        $ret = json_decode($ret, true);

        if (isset($ret['ysepay_merchant_register_token_get_response']['token'])) {
            $this->assign('yin_token', $ret['ysepay_merchant_register_token_get_response']['token']);
        } else {
            $this->assign('yin_token', '');
        }
        $this->assign('seo_title', '上传图片测试');
        $this->redirect();
    }

    public function yinsheng_upload(){
        $data = array(
                'picType'=>'00',
                'picFile'=>$_POST['picFile'],
                'token'=>$_POST['token'],
                'superUsercode'=>'yuanmeng'
                );
        $act = "https://uploadApi.ysepay.com:2443/yspay-upload-service?method=upload";
        $header = array(
                'Content-Type:multipart/form-data'
                );
        $result = Common::httpRequest($act,'POST',$data,$header);
        exit(json_encode(array('status' => 'success', 'msg' => "成功",'content'=>json_decode($result,true))));

    }

    public function my_voucher() {
        $page = Filter::int(Req::args('p'));
        $list = $this->model->table('active_voucher')->where('user_id='.$this->user['id'])->order('id desc')->findPage($page,10);
        $this->assign('list',$list);
        $this->assign('seo_title', '我的卡券');
        $this->redirect();
    }


}
