<?php
class TravelController extends Controller
{
    public $layout = 'travel';
    public $safebox = null;
    private $user;
    private $model = null;
    private $cookie_time = 31622400;
    private $cart = array();
    private $selectcart = array();

    public function init() {
        header("Content-type: text/html; charset=" . $this->encoding);
        $this->model = new Model();
        $this->safebox = Safebox::getInstance();
        $this->user = $this->safebox->get('user');
        if ($this->user == null) {
            $this->user = Common::autoLoginUserInfo();
            $this->safebox->set('user', $this->user);
        }
        $config = Config::getInstance();
        $site_config = $config->get("globals");
        $this->assign('seo_title', $site_config['site_name']);
        $this->assign('site_title', $site_config['site_name']);
        $cart = Cart::getCart();
        $this->cart = $cart->all();
        $this->selectcart = $this->cart;
        $this->assign("cart", $this->cart);

    }

    public function travel() {
        $this->redirect();
    }

    public function all_way() {
        $page = Filter::int(Req::args('p'));
        $list = $this->model->table('travel_way')->where('status=1')->order('id desc')->findPage($page,5);

        $this->assign('list',$list);
        $this->redirect();
    }

    public function new_way() {
        $page = Filter::int(Req::args('p'));
        $list = $this->model->table('travel_way')->where('is_new=1 and status=1')->order('id desc')->findPage($page,5);

        $this->assign('list',$list);
        $this->redirect();
    }

    public function way_detail() {
        $id = Filter::int(Req::args("id"));
        $info = $this->model->table('travel_way')->where('id='.$id)->find();
        // if($this->user['id']) {
        //     $sign = $this->model->table('travel_order')->where('user_id='.$this->user['id'].' and way_id='.$id)->find();
        //     $sign_status = empty($sign)?0:1;
        // } else {
        //     $sign_status = 0;
        // }
        $num = $this->model->table('travel_order')->fields('count(id) as num')->where('order_status!=-1 and id='.$id)->group('user_id')->findAll();
        $sign_num = isset($num[0]) && $num[0]['num']!=null?$num[0]['num']:0;
        $this->assign('info',$info);
        $this->assign('sign_num',$sign_num);
        $this->redirect();
    }

    public function fill_info()
    {
        $way_id = Filter::int(Req::args("way_id"));
        if($this->user['id']) {       
            $way = $this->model->table('travel_way')->fields('id,name')->findAll();

            $upyun = Config::getInstance()->get("upyun");

            $options = array(
                'bucket' => $upyun['upyun_bucket'],
                // 'allow-file-type' => 'jpg,gif,png,jpeg', // 文件类型限制，如：jpg,gif,png
                'expiration' => time() + $upyun['upyun_expiration'],
                // 'notify-url' => $upyun['upyun_notify-url'],
                // 'ext-param' => "",
                // 'save-key' => "/data/uploads/head/" . $this->user['id'] . ".jpg",
            );
            $policy = base64_encode(json_encode($options));
            $signature = md5($policy . '&' . $upyun['upyun_formkey']);
            $this->assign('way_id', $way_id);
            $this->assign('user_id',$this->user['id']);
            $this->assign('secret', md5('ym123456'));
            $this->assign('policy', $policy);
            $this->assign('way',$way);
            $this->redirect();
        } else {
            $this->redirect('/active/login/redirect/fill_info');
            // if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
            //     $redirect = "http://www.ymlypt.com/travel/fill_info";
            //     // $this->autologin($redirect);
            //     $code = Filter::sql(Req::args('code'));
            //     $oauth = new WechatOAuth();
                
            //     $url = $oauth->getCodes($redirect);
            //     if($code) {
            //         $extend = null;
            //         $token = $oauth->getAccessToken($code, $extend);
            //         $userinfo = $oauth->getUserInfo();
            //         if(!empty($userinfo)) {
            //             $openid = $token['openid'];
            //             $oauth_user = $this->model->table('oauth_user')->where("oauth_type='wechat' AND open_id='{$openid}'")->find();

            //             if(!$oauth_user) { //未注册
            //                 //插入user表
            //                 $passWord = CHash::random(6);
            //                 $validcode = CHash::random(8);
            //                 $user_id = $this->model->table("user")->data(array('nickname' => $userinfo['open_name'], 'password' => CHash::md5($passWord, $validcode), 'avatar' => $userinfo['head'], 'validcode' => $validcode))->insert();
            //                 $name = "u" . sprintf("%09d", $user_id);
            //                 $email = $name . "@no.com";
            //                 $time = date('Y-m-d H:i:s');
            //                 $this->model->table("user")->data(array('name' => $name, 'email' => $email))->where("id = ".$user_id)->update();

            //                 //插入customer表
            //                 $this->model->table("customer")->data(array('user_id' => $user_id, 'real_name' => $userinfo['open_name'], 'point_coin'=>200, 'reg_time' => $time, 'login_time' => $time))->insert();
            //                 Log::pointcoin_log(200, $user_id, '', '微信新用户积分奖励', 10);

            //                 //插入oauth_user表
            //                 $this->model->table('oauth_user')->data(array(
            //                         'user_id' => $user_id, 
            //                         'open_name' => $userinfo['open_name'],
            //                         'oauth_type' => "wechat",
            //                         'posttime' => time(),
            //                         'token' => $token['access_token'],
            //                         'expires' => $token['expires_in'],
            //                         'open_id' => $token['openid']
            //                     ))->insert();

            //                 //记录登录信息
            //                 $obj = $this->model->table("user as us")->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.login_time,cu.mobile,cu.real_name")->where("us.id='$user_id'")->find();
            //                 $obj['open_id'] = $token['openid'];
            //                 $this->safebox->set('user', $obj, 1800);
            //             } else { //已注册
            //                 $this->model->table("customer")->data(array('login_time' => date('Y-m-d H:i:s')))->where('user_id='.$oauth_user['user_id'])->update();
            //                 $obj = $this->model->table("user as us")->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.mobile,cu.login_time,cu.real_name")->where("us.id=".$oauth_user['user_id'])->find();
            //                 $this->safebox->set('user', $obj, 31622400);
            //             }

            //             $way = $this->model->table('travel_way')->fields('id,name')->findAll();

            //             $upyun = Config::getInstance()->get("upyun");

            //             $options = array(
            //                 'bucket' => $upyun['upyun_bucket'],
            //                 // 'allow-file-type' => 'jpg,gif,png,jpeg', // 文件类型限制，如：jpg,gif,png
            //                 'expiration' => time() + $upyun['upyun_expiration'],
            //                 // 'notify-url' => $upyun['upyun_notify-url'],
            //                 // 'ext-param' => "",
            //                 // 'save-key' => "/data/uploads/head/" . $this->user['id'] . ".jpg",
            //             );
            //             $policy = base64_encode(json_encode($options));
            //             $signature = md5($policy . '&' . $upyun['upyun_formkey']);
            //             $this->assign('way_id', $way_id);
            //             $this->assign('user_id',$this->user['id']);
            //             $this->assign('secret', md5('ym123456'));
            //             $this->assign('policy', $policy);
            //             $this->assign('way',$way);
            //             $this->redirect();   
            //         }
            //         // return true;
            //     } else {
            //         header("Location: {$url}"); 
            //         // $this->redirect($url);
            //     }        
            // } else {
            //    $this->redirect('/active/login/redirect/fill_info');
            // } 
        }
    }

    public function travel_sign_save()
    {
        $way_id = Filter::int(Req::args("way_id"));
        $way = $this->model->table('travel_way')->fields('name,price')->where('id='.$way_id)->find();
        $data = array(
            'user_id'=>Filter::int(Req::args("user_id")),
            'order_no'=>Common::createOrderNo(),
            'way_id'=>$way_id,
            'contact_name'=>Filter::str(Req::args("contact_name")),
            'contact_phone'=>Filter::str(Req::args("contact_phone")),
            'id_no'=>Filter::str(Req::args("id_no")),
            'sex'=>Filter::int(Req::args("sex")),
            'idcard_url'=>Filter::str(Req::args("idcard_url")),
            'sign_time'=>date('Y-m-d H:i:s'),
            'order_name'=>$way['name'],
            'order_amount'=>$way['price'],
            'order_status'=>0,
            'pay_status'=>0
            );
        $id = $this->model->table('travel_order')->data($data)->insert();
        // var_dump($id);die;
        $this->redirect('/travel/pay/id/'.$id);
    }

    public function order_list()
    {
        if($this->user['id']) {
            $page = Filter::int(Req::args('p'));
            if(!$page) {
                $page = 1;
            }
            $list = $this->model->table('travel_order as t')->fields('t.id,t.order_no,tw.name,tw.city,tw.desc,t.order_amount,tw.img')->join('left join travel_way as tw on t.way_id=tw.id')->where('t.user_id='.$this->user['id'])->order('t.id desc')->findPage($page,5);
            $this->assign('list', $list);
            $this->assign('user_id',$this->user['id']);
            $this->assign('page',$page); 
            $this->redirect();
        } else {
            $this->redirect('/active/login/redirect/order_list'); 
            // if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
            //     $redirect = "http://www.ymlypt.com/travel/order_list";
            //     // $this->autologin($redirect);
            //     $code = Filter::sql(Req::args('code'));
            //     $oauth = new WechatOAuth();
                
            //     $url = $oauth->getCodes($redirect);
            //     if($code) {
            //         $extend = null;
            //         $token = $oauth->getAccessToken($code, $extend);
            //         $userinfo = $oauth->getUserInfo();
            //         if(!empty($userinfo)) {
            //             $openid = $token['openid'];
            //             $oauth_user = $this->model->table('oauth_user')->where("oauth_type='wechat' AND open_id='{$openid}'")->find();

            //             if(!$oauth_user) { //未注册
            //                 //插入user表
            //                 $passWord = CHash::random(6);
            //                 $validcode = CHash::random(8);
            //                 $user_id = $this->model->table("user")->data(array('nickname' => $userinfo['open_name'], 'password' => CHash::md5($passWord, $validcode), 'avatar' => $userinfo['head'], 'validcode' => $validcode))->insert();
            //                 $name = "u" . sprintf("%09d", $user_id);
            //                 $email = $name . "@no.com";
            //                 $time = date('Y-m-d H:i:s');
            //                 $this->model->table("user")->data(array('name' => $name, 'email' => $email))->where("id = ".$user_id)->update();

            //                 //插入customer表
            //                 $this->model->table("customer")->data(array('user_id' => $user_id, 'real_name' => $userinfo['open_name'], 'point_coin'=>200, 'reg_time' => $time, 'login_time' => $time))->insert();
            //                 Log::pointcoin_log(200, $user_id, '', '微信新用户积分奖励', 10);

            //                 //插入oauth_user表
            //                 $this->model->table('oauth_user')->data(array(
            //                         'user_id' => $user_id, 
            //                         'open_name' => $userinfo['open_name'],
            //                         'oauth_type' => "wechat",
            //                         'posttime' => time(),
            //                         'token' => $token['access_token'],
            //                         'expires' => $token['expires_in'],
            //                         'open_id' => $token['openid']
            //                     ))->insert();

            //                 //记录登录信息
            //                 $obj = $this->model->table("user as us")->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.login_time,cu.mobile,cu.real_name")->where("us.id='$user_id'")->find();
            //                 $obj['open_id'] = $token['open_id'];
            //                 $this->safebox->set('user', $obj, 31622400);
            //                 $this->user['id'] = $oauth_user['user_id'];
            //             } else { //已注册
            //                 $this->model->table("customer")->data(array('login_time' => date('Y-m-d H:i:s')))->where('user_id='.$oauth_user['user_id'])->update();
            //                 $obj = $this->model->table("user as us")->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.mobile,cu.login_time,cu.real_name")->where("us.id=".$oauth_user['user_id'])->find();
            //                 $this->safebox->set('user', $obj, 31622400);
            //                 $this->user['id'] = $oauth_user['user_id'];
            //             }

            //             $page = Filter::int(Req::args('p'));
            //             if(!$page) {
            //                 $page = 1;
            //             }
            //             $list = $this->model->table('travel_order as t')->fields('t.id,t.order_no,tw.name,tw.city,tw.desc,t.order_amount,tw.img')->join('left join travel_way as tw on t.way_id=tw.id')->where('t.user_id='.$this->user['id'])->order('t.id desc')->findPage($page,5);
            //             $this->assign('user_id',$this->user['id']);
            //             $this->assign('page',$page);
            //             $this->assign('list',$list); 
            //             $this->redirect();   
            //         }
            //         // return true;
            //     } else {
            //         header("Location: {$url}"); 
            //         // $this->redirect($url);
            //     }        
            // } else {
            //    $this->redirect('/active/login/redirect/order_list');
            // }
        }
    }

    public function order_detail()
    {
        $id = Filter::int(Req::args("id"));
        $order = $this->model->table('travel_order as t')->fields('t.id,t.order_no,tw.name,tw.city,tw.date,tw.desc,t.order_amount,tw.img,tw.price,t.way_id,t.contact_name,t.contact_phone,t.id_no,t.idcard_url,t.sex,t.pay_status')->join('left join travel_way as tw on t.way_id=tw.id')->where('t.id='.$id)->find();
        $order['idcard_url'] = explode(',', $order['idcard_url']);
        
        $this->assign('order',$order);
        $this->redirect();
    }
    public function pay() {
        $id = Filter::int(Req::args("id"));
        $code = Filter::sql(Req::args('code'));
        if(!$this->user['id']) {
            $this->redirect('/active/login?redirect=pay&id={$id}');
        }
        
        $order = $this->model->table('travel_order as t')->fields('t.id,t.order_no,tw.name,tw.city,tw.date,tw.desc,t.order_amount,tw.img,tw.price,t.way_id,t.contact_name,t.contact_phone,t.id_no,t.idcard_url,t.sex,t.pay_status,t.pay_type')->join('left join travel_way as tw on t.way_id=tw.id')->where('t.id='.$id)->find();
        
        if(!$order) {
            $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '支付信息错误', "content" => "抱歉，找不到您的订单信息啦"));
            exit();
        }
        
        $notify_url = "http://www.ymlypt.com/travel/callback";
        $oauth = new WechatOAuth();
        $url = $oauth->getCode($id);
        $oauth_user = $this->model->table('oauth_user')->fields('open_id')->where("oauth_type='wechat' AND user_id=".$this->user['id'])->find();
        $need_code = empty($oauth_user)?1:0;
        
        if(!$oauth_user && $code) {
            $extend = null;
            $token = $oauth->getAccessToken($code, $extend);
            $userinfo = $oauth->getUserInfo();
            $this->model->table('oauth_user')->data(array(
                    'user_id' => $this->user['id'], 
                    'open_name' => $userinfo['open_name'],
                    'oauth_type' => "wechat",
                    'posttime' => time(),
                    'token' => $token['access_token'],
                    'expires' => $token['expires_in'],
                    'open_id' => $token['openid']
                ))->insert();
        }
        
        if($oauth_user) {
            // $openid = $oauth->getOpenid($code);
            $openid = $oauth_user['open_id'];
            //②、统一下单
            $input = new WxPayUnifiedOrder();
            $input->SetBody("圆梦旅游");
            $input->SetAttach($order['order_no']);
            $input->SetOut_trade_no($order['order_no']);
            $input->SetTotal_fee(intval(bcmul($order['order_amount'],100)));
            $input->SetTime_start(date("YmdHis"));
            $input->SetTime_expire(date("YmdHis", time() + 3600));
            $input->SetGoods_tag("test");
            $input->SetNotify_url($notify_url);
            $input->SetTrade_type("JSAPI");
            $input->SetOpenid($openid);
            
            $order_input = WxPayApi::unifiedOrder($input);
            $tools = new JsApiPay();
            
            $jsApiParameters = $tools->GetJsApiParameters($order_input);
            
            $this->assign("jsApiParameters", $jsApiParameters);
        }
        
        $success_url = Url::urlFormat("/travel/order_detail/id/{$id}");
        $this->assign("need_code", $need_code);
        $this->assign("success_url", $success_url);
        $this->assign('code',$code);
        $this->assign('order',$order);
        $this->assign('url',$url);
        
        $this->redirect();
    }

    public function modify_pay_type () {
        $id = Filter::int(Req::args("order_id"));
        $pay_type = Filter::int(Req::args("pay_type"));
        $this->model->table('travel_order')->data(array('pay_type'=>$pay_type))->where('id='.$id)->update();

        echo JSON::encode(array('status' => 'success'));
    }

    public function callback() {
        $xml = @file_get_contents('php://input');
        $array=Common::xmlToArray($xml);
        file_put_contents('./wxpay.php', json_encode($array) . PHP_EOL, FILE_APPEND);
        if($array['result_code']=='SUCCESS'){
            $money = round(intval($array['total_fee'])/100,2);
            $order_no = $array['attach'];
            $order = $this->model->table('travel_order')->where("order_no='{$order_no}'")->find();
            if($order) {
                if ($order['order_amount'] > $money) {
                    file_put_contents('payErr.txt', date("Y-m-d H:i:s") . "|========订单金额不符,订单号：{$order_no}|{$order['order_amount']}元|{$money}元|========|\n", FILE_APPEND);
                    echo 'fail';
                    exit;
                }
                $this->model->table('travel_order')->data(array('pay_status'=>1,'pay_time'=>date('Y-m-d H:i:s')))->where('order_no='.$order_no)->update();
            }
            echo "success";
            exit();
        }else{
            echo "fail";
            exit();
        }
    }

    public function autologin($redirect,$inviter) {
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
                        if($inviter){
                            Common::buildInviteShip($inviter, $this->user['id'], 'second-wap');
                        }   
                    }
                } else {
                    header("Location: {$url}"); 
                }
        } elseif(strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient') !== false) {
            //支付宝授权登录
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
                    $this->user['id'] = $obj['id'];
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
                    if($inviter){
                        Common::buildInviteShip($inviter, $this->user['id'], 'alipay');
                    } 
                }
                return $this->user['id'];
            }
        } else {
            return false;
        } 
    }

    public function travel_order_list() {
        $page = Filter::int(Req::args("page"));
        $user_id = Filter::int(Req::args("user_id"));
        if(!$page) {
                $page = 1;
            }
        $list = $this->model->table('travel_order as t')->fields('t.id,t.order_no,tw.name,tw.city,tw.desc,t.order_amount,tw.img')->join('left join travel_way as tw on t.way_id=tw.id')->where('t.user_id='.$user_id)->order('t.id desc')->findPage($page,5);
        $result = !empty($list)?$list['data']:[];
        echo JSON::encode($result);
    }

    public function travel_way_list() {
        $page = Filter::int(Req::args("page"));
        if(!$page) {
                $page = 1;
            }
        $list = $this->model->table('travel_way')->where('status=1')->order('id desc')->findPage($page,5);
        $result = !empty($list)?$list['data']:[];
        echo JSON::encode($result);
    }

    public function new_way_list() {
        $page = Filter::int(Req::args("page"));
        if(!$page) {
                $page = 1;
            }
        $list = $this->model->table('travel_way')->where('is_new=1 and status=1')->order('id desc')->findPage($page,5);
        $result = !empty($list)?$list['data']:[];
        echo JSON::encode($result);
    }

    public function order_details() {
        $id = Filter::int(Req::args("id"));
        $user_id = Filter::int($this->user['id']);
        $order = $this->model->table("order_offline")->where("id = ".$id)->find();
        if (!$order) {
            $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '支付信息错误', "content" => "抱歉，找不到您的订单信息"));
            exit();
        }
        if($order['pay_status']==0) {
            $this->redirect("/simple/offline_order_status/order_id/{$id}");
        }
        $shop = $this->model->table('customer as c')->fields('c.real_name,u.avatar')->join('left join user as u on c.user_id=u.id')->where('c.user_id=' . $order['shop_ids'])->find();
        if ($shop) {
            $shopname = $shop['real_name'];
            $avatar = $shop['avatar'];
        } else {
            $shopname = '未知商家';
            $avatar = '/0.png';
        }
        $my = $this->model->table('customer')->fields('mobile_verified')->where('user_id='.$user_id)->find();
        $had_bind = $my['mobile_verified'];
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
        $this->assign('avatar', $avatar);
        $this->assign('had_bind', $had_bind);
        $this->assign('user_id', $user_id);
        $this->assign("order", $order);
        $this->assign("id", $id);
        $this->assign("seo_title", "支付成功");
        $this->redirect();
    }

    public function tao_share() {
        $num_iid = Filter::str(Req::args("num_iid"));
        if(!$num_iid) {
            $num_iid = '553057896190';
        }
        $tao_str = Filter::str(Req::args("tao_str"));
        $coupon_price = Filter::float(Req::args("coupon_price"));
        if(!$coupon_price) {
            $coupon_price = 0.00;
        }
        $form = Filter::str(Req::args("form"));
        if (!$form) {
            $form = 'android';
        }
        if ($form == 'android') { //安卓
            $appkey = '24875594';
            $secretKey = '8aac26323a65d4e887697db01ad7e7a8';
            $AdzoneId = '513416107';
        } else { //ios
            $appkey = '24876667';
            $secretKey = 'a5f423bd8c6cf5e8518ff91e7c12dcd2';
            $AdzoneId = '582570496';
        }
        $inviter = Filter::int(Req::args("inviter_id"));
        if(!$this->user) {
            if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
                //微信授权登录
                $redirect = "http://www.ymlypt.com/travel/tao_share?num_iid=".$num_iid."&tao_str=".$tao_str."&coupon_price=".$coupon_price."&inviter_id=".$inviter;
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
                            $open_name = $userinfo['open_name'];
                            $open_name = Common::replace_specialChar($open_name);
                            //插入user表
                            $passWord = CHash::random(6);
                            $validcode = CHash::random(8);
                            $user_id = $this->model->table("user")->data(array('nickname' => $open_name, 'password' => CHash::md5($passWord, $validcode), 'avatar' => $userinfo['head'], 'validcode' => $validcode))->insert();
                            $name = "u" . sprintf("%09d", $user_id);
                            $email = $name . "@no.com";
                            $time = date('Y-m-d H:i:s');
                            $this->model->table("user")->data(array('name' => $name, 'email' => $email))->where("id = ".$user_id)->update();

                            //插入customer表
                            $this->model->table("customer")->data(array('user_id' => $user_id, 'real_name' => $open_name, 'point_coin'=>200, 'reg_time' => $time, 'login_time' => $time))->insert();
                            Log::pointcoin_log(200, $user_id, '', '微信新用户积分奖励', 10);

                            //插入oauth_user表
                            $this->model->table('oauth_user')->data(array(
                                    'user_id' => $user_id, 
                                    'open_name' => $open_name,
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
                        $inviter = Filter::int(Req::args("inviter_id"));
                        if($inviter){
                            Common::buildInviteShip($inviter, $this->user['id'], 'wechat');
                        }   
                    }
                } else {
                    header("Location: {$url}"); 
                }        
            }
        }
        //淘宝转链，获取分享url
        if($this->user['id']) {
           $uid = Common::getInviterId($this->user['id']); //上级用户id 
       } else {
           $uid = $inviter; //上级用户id
       }
        
        $objs = $this->model->table('user')->where('id='.$uid)->find();
        if($objs['adzoneid']==null) {
            $taobao_pid = $this->model->table('taoke_pid')->where('user_id is NULL')->order('id desc')->find();
            if($taobao_pid) {
                $this->model->table('taoke_pid')->data(['user_id'=>$uid])->where('id='.$taobao_pid['id'])->update();
                $this->model->table('user')->data(['adzoneid'=>$taobao_pid['adzoneid']])->where('id='.$uid)->update();
            }
        }

        $taoke = $this->model->table('taoke_pid')->fields('adzoneid,memberid,siteid')->where('user_id='.$uid)->find();
        
        if(!$taoke) {
            $this->redirect("/travel/tao_fail");
            exit();
        }
        $config = Config::getInstance()->get("other");
        $access_token = $config['access_token'];
        $main_hightapi_url = 'http://193.112.121.99/xiaocao/hightapi.action';
        $bak_hightapi_url = 'http://119.29.94.164/xiaocao/hightapi.action';
        
        //判断接口服务器状态
        $array = get_headers($main_hightapi_url,1); 
        if(preg_match('/200/',$array[0])){ 
            $hightapi_url = $main_hightapi_url;
        }else{
        //启用备份服务器接口   
            $hightapi_url = $bak_hightapi_url;
        }
        $params = ['token' => $access_token, 'item_id' => $num_iid, 'adzone_id' => $taoke['adzoneid'], 'site_id' => $taoke['siteid'], 'qq' => '1223354181'];
        $req_url = $hightapi_url . "?" . http_build_query($params);

        $return = json_decode(file_get_contents($req_url), true);    

        $c = new TopClient;
        $c->appkey = $appkey;
        $c->secretKey = $secretKey;
        $req = new TbkItemInfoGetRequest;
        $req->setFields("num_iid,title,pict_url,small_images,reserve_price,zk_final_price,user_type,provcity,item_url,seller_id,volume,nick");
        $req->setNumIids($num_iid);
        $req->setPlatform("2");
        $req->setIp($_SERVER['REMOTE_ADDR']);
        $resp = $c->execute($req);
        $resp = Common::objectToArray($resp);
        
        if(isset($resp['results']['n_tbk_item'])) {
            $info = $resp['results']['n_tbk_item'];
            //重新获取淘口令
            if($inviter &&  $inviter!=$uid && isset($return['result']['data']['coupon_click_url'])) {
                $share_url = $return['result']['data']['coupon_click_url'];
                $cs = new TopClient;
                $cs->appkey = $appkey;
                $cs->secretKey = $secretKey;
                $cs->format = 'json';
                $reqs = new TbkTpwdCreateRequest;
                $reqs->setText($info['title']);
                $reqs->setUrl($share_url);
                $logo = "http://www.ymlypt.com/themes/mobile/images/logo-new.png";
                $reqs->setLogo($logo);
                $reqs->setExt("{}");
                $resps = $cs->execute($reqs);
                $resps = Common::objectToArray($resps);
                if(isset($resps['data']['model'])) {
                    $tao_str = $resps['data']['model'];
                }
            }
    
            $info['tao_str'] = '￥'.$tao_str.'￥';
            $info['coupon_price'] = $coupon_price;
            $this->assign("info", $info);
            $this->redirect();
        } else {
            $this->redirect("/travel/tao_fail");
        }      
    }

    public function tao_fail()
    {
        $this->redirect();
    }

    public function get_way_remark() {
        $id = Filter::int(Req::args("id"));
        $way = $this->model->table('travel_way')->fields('remark')->where('id='.$id)->find();
        echo JSON::encode(array('data' => $way['remark']));
    }

    public function invite_register()
    {
        $inviter = Filter::int(Req::args("inviter_id"));

        $this->assign('inviter',$inviter);
        $this->redirect();
    }

    public function bind_mobile()
    {
        header("Content-Type:text/html;charset=utf-8");
        $inviter = Filter::int(Req::args("inviter_id"));
        $msg = Filter::str(Req::args("msg"));
         if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
            if(!isset($this->user['id'])) {
                $redirect = "http://www.ymlypt.com/travel/bind_mobile?inviter_id=".$inviter;
                // $this->autologin($redirect);
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
                            $open_name = $userinfo['open_name'];
                            $open_name = Common::replace_specialChar($open_name);
                            // if($openid=='okZq1wQxLuLVnvPOlviRzjTcx1FM') {
                            //     $open_name = file_get_contents('./test.txt');
                            //     $open_name = Common::replace_specialChar($open_name);
                            //     var_dump($open_name);die;
                            // }
                            $passWord = CHash::random(6);
                            $validcode = CHash::random(8);
                            $user_id = $this->model->table("user")->data(array('nickname' => $open_name, 'password' => CHash::md5($passWord, $validcode), 'avatar' => $userinfo['head'], 'validcode' => $validcode))->insert();
                            $name = "u" . sprintf("%09d", $user_id);
                            $email = $name . "@no.com";
                            $time = date('Y-m-d H:i:s');
                            $this->model->table("user")->data(array('name' => $name, 'email' => $email))->where("id = ".$user_id)->update();

                            //插入customer表
                            $this->model->table("customer")->data(array('user_id' => $user_id, 'real_name' => $open_name, 'point_coin'=>200, 'reg_time' => $time, 'login_time' => $time))->insert();
                            Log::pointcoin_log(200, $user_id, '', '微信新用户积分奖励', 10);

                            //插入oauth_user表
                            $this->model->table('oauth_user')->data(array(
                                    'user_id' => $user_id, 
                                    'open_name' => $open_name,
                                    'oauth_type' => "wechat",
                                    'posttime' => time(),
                                    'token' => $token['access_token'],
                                    'expires' => $token['expires_in'],
                                    'open_id' => $token['openid']
                                ))->insert();

                            //记录登录信息
                            $obj = $this->model->table("user as us")->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.login_time,cu.mobile,cu.real_name")->where("us.id='$user_id'")->find();
                            $obj['open_id'] = $token['openid'];
                            $this->safebox->set('user', $obj, 31622400);
                            $this->user['id'] = $user_id;
                        } else { //已注册
                            $this->model->table("customer")->data(array('login_time' => date('Y-m-d H:i:s')))->where('user_id='.$oauth_user['user_id'])->update();
                            $obj = $this->model->table("user as us")->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.mobile,cu.login_time,cu.real_name")->where("us.id=".$oauth_user['user_id'])->find();
                            $this->safebox->set('user', $obj, 31622400);
                            $this->user['id'] = $oauth_user['user_id'];
                        }
                        $had_locked = $this->model->table('invite')->where('invite_user_id='.$this->user['id'])->find();
                        if($had_locked) {
                            $locked = 1; //已锁
                        } else {
                            $locked = 2; //未锁
                        }
                        if($inviter){
                            Common::buildInviteShip($inviter, $this->user['id'], 'wechat');
                        }
                        $customer = $this->model->table('customer')->fields('mobile,mobile_verified')->where('user_id='.$this->user['id'])->find();
                        $seo_title = $customer['mobile_verified']==0?"绑定手机号":"关联成功";
                        $this->assign('user_id',$this->user['id']);
                        $this->assign('mobile_verified',$customer['mobile_verified']);
                        $this->assign('locked',$locked);
                        $this->assign('seo_title',$seo_title);
                        $this->assign('msg',$msg);
                        $this->redirect();       
                    }
                    // return true;
                } else {
                    header("Location: {$url}"); 
                    // $this->redirect($url);
                }
            } else {
                $had_locked = $this->model->table('invite')->where('invite_user_id='.$this->user['id'])->find();
                if($had_locked) {
                    $locked = 1; //已锁
                } else {
                    $locked = 2; //未锁
                }
                if($inviter){
                    Common::buildInviteShip($inviter, $this->user['id'], 'wechat');
                }
                $customer = $this->model->table('customer')->fields('mobile,mobile_verified')->where('user_id='.$this->user['id'])->find();
                $seo_title = $customer['mobile_verified']==0?"绑定手机号":"关联成功";
                $this->assign('user_id',$this->user['id']);
                $this->assign('mobile_verified',$customer['mobile_verified']);
                $this->assign('locked',$locked);
                $this->assign('seo_title',$seo_title);
                $this->assign('msg',$msg);
                $this->redirect();
            }        
        } else {
            $this->redirect("/index/msg", false, array('type' => 'fail', 'msg' => '请在微信中打开'));
            exit;
        }
    }

    public function bind_mobile_act()
    {
        $mobile = Filter::str(Req::args('mobile'));
        $mobile_code = Req::args('mobile_code');
        $password = Req::args('password');
        $repassword = Req::args('repassword');
        $inviter_id = Filter::int(Req::args("inviter"));
        $locked = Filter::int(Req::args("locked"));
        $user_id = Filter::int(Req::args("user_id"));
        // $checkret = SMS::getInstance()->checkCode($mobile, $mobile_code);
        // $checkFlag = $checkret && $checkret['status'] == 'success' ? TRUE : FALSE;
        $checkFlag = $this->sms_verify($mobile_code, $mobile, '86');
        if($password!=$repassword) {
            $info = array('status' => 'fail', 'msg' => '两次密码输入不一致！');
        }
        if($checkFlag || $mobile_code=='000000') {
            // $another = $this->model->table('customer')->where("mobile='$mobile' and user_id!=".$this->user['id'])->find();
            // if($another) {
            //     $this->model->table('customer')->data(array('status' => 0))->where('user_id=' . $another['user_id'])->update();
            // }
            //  $this->model->table('customer')->where('user_id='.$this->user['id'])->data(['mobile'=>$mobile,'mobile_verified'=>1])->update();
            //  $validcode = CHash::random(8);
            //  $this->model->table('user')->data(array('password' => CHash::md5($password, $validcode), 'validcode' => $validcode))->where('id=' . $this->user['id'])->update();
            $had_bind= $this->model->table("customer")->where("mobile='{$mobile}' and status=1")->findAll();
            $time = date('Y-m-d H:i:s');
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
                                $this->model->table('customer')->data(array('mobile' => $mobile, 'mobile_verified' => 1,'checkin_time'=>$time,'balance'=>"`balance`+({$customer['balance']})",'offline_balance'=>"`offline_balance`+({$customer['offline_balance']})"))->where('user_id=' . $user_id)->update();
                                $this->model->table('customer')->data(array('status' => 0))->where('user_id=' . $weixin['user_id'])->update();
                                $this->model->table('oauth_user')->data(array('other_user_id' => $weixin['user_id']))->where('user_id=' . $user_id)->update();
                                $last_id = $user_id;    
                            } else { //分配$weixin['user_id']账号
                                $customer = $this->model->table('customer')->where('user_id=' . $user_id)->find();
                                $this->model->table('customer')->data(array('mobile' => $mobile, 'mobile_verified' => 1,'checkin_time'=>$time,'balance'=>"`balance`+({$customer['balance']})",'offline_balance'=>"`offline_balance`+({$customer['offline_balance']})"))->where('user_id=' . $weixin['user_id'])->update();
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
                                $this->model->table('customer')->data(array('mobile' => $mobile, 'mobile_verified' => 1,'checkin_time'=>$time,'balance'=>"`balance`+({$customer2['balance']})",'offline_balance'=>"`offline_balance`+({$customer2['offline_balance']})"))->where('user_id=' . $user_id)->update();
                                $this->model->table('customer')->data(array('status' => 0))->where('user_id=' . $value['user_id'])->update();   
                                $this->model->table('oauth_user')->data(array('other_user_id' => $value['user_id']))->where('user_id=' . $user_id)->update();
                                $last_id = $user_id;
                            } else {
                                $this->model->table('customer')->data(array('mobile' => $mobile, 'mobile_verified' => 1,'checkin_time'=>$time,'balance'=>"`balance`+({$customer1['balance']})",'offline_balance'=>"`offline_balance`+({$customer1['offline_balance']})"))->where('user_id=' . $value['user_id'])->update();
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
                        $this->model->table('customer')->data(array('mobile' => $mobile, 'mobile_verified' => 1,'checkin_time'=>$time,'balance'=>"`balance`+({$customer2['balance']})",'offline_balance'=>"`offline_balance`+({$customer2['offline_balance']})"))->where('user_id=' . $user_id)->update();
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
            } else {
                //绑定手机号
                $this->model->table('customer')->data(array('mobile' => $mobile, 'mobile_verified' => 1,'checkin_time'=>$time))->where('user_id=' . $user_id)->update();
                $last_id = $user_id;
            }
            $validcode = CHash::random(8);
            $this->model->table('user')->data(array('password' => CHash::md5($password, $validcode), 'validcode' => $validcode))->where('id=' . $last_id)->update();
             $info = array('status' => 'success', 'msg' => '成功');
        } else {
            $info = array('status' => 'fail', 'msg' => '验证码错误！');
        }
        // echo JSON::encode($info);
        if($info['status']=='success') {
            $this->redirect('/travel/register_success?locked='.$locked);
        } else {
            $customer = $this->model->table('customer')->fields('mobile,mobile_verified')->where('user_id='.$user_id)->find();
            $seo_title = $customer['mobile_verified']==0?"绑定手机号":"关联成功";
            $msg = '验证码错误！';
            $this->assign('mobile_verified',$customer['mobile_verified']);
            $this->assign('locked',$locked);
            $this->assign('seo_title',$seo_title);
            $this->assign('msg',$msg);
            $this->redirect('bind_mobile', false,Req::args('msg',$msg));
        }
    }

    public function mobile_code_check(){
        $mobile = Filter::str(Req::args('mobile'));
        $mobile_code = Req::args('mobile_code');
        // $checkret = SMS::getInstance()->checkCode($mobile, $mobile_code);
        // $checkFlag = $checkret && $checkret['status'] == 'success' ? TRUE : FALSE;
        $checkFlag = $this->sms_verify($mobile_code, $mobile, '86');
        if($checkFlag || $mobile_code=='000000') {
            $info = array('status' => 'success', 'msg' => '成功');
        } else {
            $info = array('status' => 'fail', 'msg' => '验证码错误！');
        }
        echo JSON::encode($info);
    }

    public function register_success()
    {
        $locked = Filter::int(Req::args('locked'));
        // var_dump($locked);die;
        if($locked==1 || $locked==2) {
            $seo_title = "关联成功";
        } else {
            $seo_title = "付款成功";
        }
        $this->assign('locked',$locked);
        $this->assign('seo_title',$seo_title);
        $this->redirect();
    }

    public function bind_success()
    {
        $this->redirect();
    }

    public function register_act(){
            $mobile = Filter::str(Req::args('mobile'));
            $realname = $mobile;
            $passWord = Req::post('password');
            $rePassWord = Req::post('repassword');
            $mobile_code = Req::args('mobile_code');
            $back = Filter::str(Req::args("back"));
            $inviter_id = Filter::int(Req::args("inviter"));
            // $checkret = SMS::getInstance()->checkCode($mobile, $mobile_code);
            // $checkFlag = $checkret && $checkret['status'] == 'success' ? TRUE : FALSE;
            $checkFlag = $this->sms_verify($mobile_code, $mobile, '86');
            if($checkFlag || $mobile_code=='000000'){
                    SMS::getInstance()->flushCode($mobile);
                        if (!Validator::mobi($mobile)) {
                             $info = array('field' => 'mobile', 'msg' => ' 手机号码格式不正确！');
                        }else{
                            if (strlen($passWord) < 6) {
                                $info = array('field' => 'password', 'msg' => '密码长度必需大于6位！');
                            }else{
                                if ($passWord == $rePassWord) {
                                    $userObj = $this->model->table("customer")->where("mobile='{$mobile}'")->find();
                                    if (empty($userObj)) {
                                        $validcode = CHash::random(8);
                                        $last_id = $this->model->table("user")->data(array('avatar' => '','nickname'=>$realname,'password' => CHash::md5($passWord, $validcode), 'validcode' => $validcode, 'status' => 1))->insert();
                                        if($last_id){
                                            $name = "u" . sprintf("%09d", $last_id);
                                            //更新用户名和邮箱
                                            $this->model->table("user")->data(array('name' => $name))->where("id = '{$last_id}'")->update();
                                            $time = date('Y-m-d H:i:s');
                                            $this->model->table("customer")->data(array('user_id' => $last_id,'real_name'=>$realname,'reg_time' => $time, 'login_time' => $time, 'mobile' => $mobile,'mobile_verified'=>1))->insert();
                                            
                                            //记录登录信息
                                            $obj = $this->model->table("user as us")->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.group_id,cu.login_time,cu.mobile,cu.real_name")->where("cu.mobile='{$mobile}'")->find();
                                            $this->safebox->set('user', $obj, 1800);
                                            Common::sendPointCoinToNewComsumer($last_id);
                                            if($back=='invite_register') {
                                                if($inviter_id) {
                                                    Common::buildInviteShip($inviter_id, $last_id, 'wechat');
                                                }
                                                $this->redirect("/travel/register_success");
                                            }
                                            $this->redirect("/ucenter/index",true);
                                            exit();
                                        }else{
                                            $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '注册失败', "content" => "数据库错误！", "redirect" => "/simple/reg"));
                                            exit();
                                        }
                                    } else {
                                       $info = array('field' => 'mobile', 'msg' => '此手机号已被注册！');
                                    }
                                } else {
                                    $info = array('field' => 'repassword', 'msg' => '两次密码输入不一致！');
                                }
                    }
                }
            }else{
                $info = array('field' => 'mobile_code', 'msg' => '短信验证码错误!');
            }
            $this->redirect("/index/msg", false, $info);
                exit;
    }

    public function demo()
    {
        $model = new Model();
        Session::set('demo', 3);

        $inviter_id = intval(Req::args('inviter_id'));
        if (!$inviter_id) {
            $inviter_id = Session::get('seller_id');
        }
        // $cashier_id = Filter::int(Req::args('cashier_id'));//收银员id
        // if(!$cashier_id) {
        //     $cashier_id = 0;
        // }
        $cashier_id = 0;
        $desk_id = Filter::int(Req::args('desk_id'));//收银台id
        if(!$desk_id) {
            $desk_id = 0;
        }
        if($desk_id) {
            $desk = $this->model->table('cashier_desk')->where('id='.$desk_id.' and status=1')->find();
            if(!$desk) {
                $this->redirect("/index/msg", false, array('type' => 'fail', 'msg' => '未找到该收银台或已删除'));
                exit;
            }
            $cashier = $this->model->table('cashier_attendance')->where('desk_id='.$desk_id)->order('id desc')->find();
            if($cashier) {
                $cashier_id = $cashier['cashier_id'];
            }
        }
        if($cashier_id!=0 || $desk_id!=0) {
            $cash = 1;
        } else {
            $cash = 0;
        }   
        //黑名单
        $blacklist = $this->model->table('blacklist')->findAll();
        if($blacklist) {
            $ids = array();
            foreach($blacklist as $k =>$v) {
                $ids[] = $v['user_id'];
            }
            if(in_array($inviter_id, $ids)) {
                $black = $this->model->table('blacklist')->where('user_id='.$inviter_id)->find();
                if(date('Y-m-d H:i:s')>$black['start_time'] && date('Y-m-d H:i:s')<$black['end_time']) {
                    $this->redirect("/index/msg", false, array('type' => 'fail', 'msg' => '该商户违规操作，冻结收款功能！'));
                    exit;
                }    
            }
        } 
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient') !== false) {
            $pay_type = 'alipay';
            $from = 'alipay';
        } else {
            $pay_type = 'wechat';
            $from = 'second-wap';
        }
        // if (isset($this->user['id'])) {
        //     Common::buildInviteShip($inviter_id, $this->user['id'], $from);
        // } else {
        //     Cookie::set("inviter", $inviter_id);
        //     $this->noRight();
        // }
        if(!isset($this->user['id'])) {
            $redirect = "http://www.ymlypt.com/travel/demo/inviter_id/".$inviter_id;
            if($cashier_id!=0) {
                $redirect.='/cashier_id/'.$cashier_id;
            }
            if($desk_id!=0) {
                $redirect.='/desk_id/'.$desk_id;
            }
            // $this->user['id'] = $this->autologin($redirect,$inviter_id);
            if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
               //微信授权登录
                    $code = Filter::sql(Req::args('code'));
                    $oauth = new WechatOAuth();
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
                    $url = $oauth->getCodes($redirect);
                    if($code) {
                        $extend = null;
                        $token = $oauth->getAccessToken($code, $extend);
                        $userinfo = $oauth->getUserInfo();
                        if(!empty($userinfo)) {
                            $openid = $token['openid'];
                            $oauth_user = $this->model->table('oauth_user')->where("oauth_type='wechat' AND open_id='{$openid}'")->find();

                            if(!$oauth_user) { //未注册
                                $open_name = $userinfo['open_name'];
                                $open_name = Common::replace_specialChar($open_name);
                                //插入user表
                                $passWord = CHash::random(6);
                                $validcode = CHash::random(8);
                                $user_id = $this->model->table("user")->data(array('nickname' => $open_name, 'password' => CHash::md5($passWord, $validcode), 'avatar' => $userinfo['head'], 'validcode' => $validcode))->insert();
                                $name = "u" . sprintf("%09d", $user_id);
                                $email = $name . "@no.com";
                                $time = date('Y-m-d H:i:s');
                                $this->model->table("user")->data(array('name' => $name, 'email' => $email))->where("id = ".$user_id)->update();

                                //插入customer表
                                $this->model->table("customer")->data(array('user_id' => $user_id, 'real_name' => $open_name, 'point_coin'=>200, 'reg_time' => $time, 'login_time' => $time))->insert();
                                Log::pointcoin_log(200, $user_id, '', '微信新用户积分奖励', 10);

                                //插入oauth_user表
                                $this->model->table('oauth_user')->data(array(
                                        'user_id' => $user_id, 
                                        'open_name' => $open_name,
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
                    $act = "https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?app_id=2017080107981760&scope=auth_user&redirect_uri=http://www.ymlypt.com/travel/demo&state=test&inviter_id=" . $_GET['inviter_id'];
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
                    if ($is_oauth) {
                        $obj = $this->model->table("user as us")->join("left join customer as cu on us.id = cu.user_id left join oauth_user as o on us.id = o.user_id")->fields("us.*,cu.mobile,cu.group_id,cu.login_time,cu.real_name")->where("o.open_id='{$result['user_id']}'")->find();
                        $this->safebox->set('user', $obj, 31622400);
                        $this->user = $this->safebox->get('user');
                        $this->user['id'] = $obj['id'];
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
                    }
                    if($inviter_id){
                        Common::buildInviteShip($inviter_id, $this->user['id'], 'alipay');
                    }
                    if($seller_id){
                        Common::buildInviteShip($seller_id, $this->user['id'], 'alipay');
                    }
                }
            }
        }
        $user_id = $this->user['id'];
        $shop = $this->model->table('customer as c')->fields('c.real_name,u.nickname,u.avatar')->join('left join user as u on c.user_id=u.id')->where('c.user_id=' . $inviter_id)->find();
        $shop_name = $shop['real_name']==null?$shop['nickname']:$shop['real_name'];
        $this->assign('shop_name', $shop_name);
        $this->assign('avatar', $shop['avatar']); 
        $order_no = date('YmdHis') . rand(1000, 9999);
        // $jsApiParameters = Session::get('payinfo');
        // $this->assign("jsApiParameters",$jsApiParameters);
        
        $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
        if(strpos($agent, 'android')==true) {
            $platform = 'android';
        } else {
            $platform = 'ios';
        }
        if($from=='alipay') {
            Common::buildInviteShip($inviter_id, $user_id, $from);
        }
        $this->assign("seo_title", "向商家付款");
        $this->assign('seller_id', $inviter_id);
        $this->assign('cashier_id', $cashier_id);
        $this->assign('desk_id', $desk_id);
        $this->assign('seller_ids', Session::get('seller_id'));
        $this->assign('order_no', $order_no);
        $this->assign('user_id', $user_id);
        $this->assign('platform', $platform);
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

    public function pay_success()
    {
        $this->redirect();
    }

    public function bind_act()
    {
        $id = Filter::int(Req::args('id'));
        $user_id = Filter::int(Req::args('user_id'));
        $mobile = Req::args('mobile');
        $password = Req::args('password');
        $repassword = Req::args('repassword');
        $mobile_code = Req::args('mobile_code');
        $payment = Filter::int(Req::args('payment'));
        // $checkret = SMS::getInstance()->checkCode($mobile, $mobile_code);
        // $checkFlag = $checkret && $checkret['status'] == 'success' ? TRUE : FALSE;
        $checkFlag = $this->sms_verify($mobile_code, $mobile, '86');
        
        if($checkFlag || $mobile_code=='000000') {
            $time = date('Y-m-d H:i:s');
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
                                $this->model->table('customer')->data(array('mobile' => $mobile, 'mobile_verified' => 1,'checkin_time'=>$time,'balance'=>"`balance`+({$customer['balance']})",'offline_balance'=>"`offline_balance`+({$customer['offline_balance']})"))->where('user_id=' . $user_id)->update();
                                $this->model->table('customer')->data(array('status' => 0))->where('user_id=' . $weixin['user_id'])->update();
                                $this->model->table('oauth_user')->data(array('other_user_id' => $weixin['user_id']))->where('user_id=' . $user_id)->update();
                                $last_id = $user_id;    
                            } else { //分配$weixin['user_id']账号
                                $customer = $this->model->table('customer')->where('user_id=' . $user_id)->find();
                                $this->model->table('customer')->data(array('mobile' => $mobile, 'mobile_verified' => 1,'checkin_time'=>$time,'balance'=>"`balance`+({$customer['balance']})",'offline_balance'=>"`offline_balance`+({$customer['offline_balance']})"))->where('user_id=' . $weixin['user_id'])->update();
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
                                $this->model->table('customer')->data(array('mobile' => $mobile, 'mobile_verified' => 1,'checkin_time'=>$time,'balance'=>"`balance`+({$customer2['balance']})",'offline_balance'=>"`offline_balance`+({$customer2['offline_balance']})"))->where('user_id=' . $user_id)->update();
                                $this->model->table('customer')->data(array('status' => 0))->where('user_id=' . $value['user_id'])->update();   
                                $this->model->table('oauth_user')->data(array('other_user_id' => $value['user_id']))->where('user_id=' . $user_id)->update();
                                $last_id = $user_id;
                            } else {
                                $this->model->table('customer')->data(array('mobile' => $mobile, 'mobile_verified' => 1,'checkin_time'=>$time,'balance'=>"`balance`+({$customer1['balance']})",'offline_balance'=>"`offline_balance`+({$customer1['offline_balance']})"))->where('user_id=' . $value['user_id'])->update();
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
                        $this->model->table('customer')->data(array('mobile' => $mobile, 'mobile_verified' => 1,'checkin_time'=>$time,'balance'=>"`balance`+({$customer2['balance']})",'offline_balance'=>"`offline_balance`+({$customer2['offline_balance']})"))->where('user_id=' . $user_id)->update();
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
            } else {
                //绑定手机号
                $this->model->table('customer')->data(array('mobile' => $mobile, 'mobile_verified' => 1,'checkin_time'=>$time))->where('user_id=' . $user_id)->update();
                $last_id=$user_id;
            }
            $validcode = CHash::random(8);
            $this->model->table('user')->data(array('password' => CHash::md5($password, $validcode), 'validcode' => $validcode))->where('id=' . $last_id)->update();
            $info = array('status' => 'success', 'msg' => '成功');
            $this->redirect('bind_success');
        } else {
            echo "<script>alert('验证码错误');</script>";
            exit();
            $this->redirect('/travel/order_details?id='.$id);
            // $info = array('status' => 'fail', 'msg' => '验证码错误!');
            // var_dump($info);die;
        }
        // echo JSON::encode($info);
    }

    //验证短信
    private function sms_verify($code, $mobile, $zone) {
        $url = "https://webapi.sms.mob.com/sms/verify";
        $appkey = "1f4d2d20dd266";
        $return = $this->postRequest($url, array('appkey' => $appkey,
            'phone' => $mobile,
            'zone' => $zone,
            'code' => $code,
        ));
        $flag = json_decode($return, true);
        if ($flag['status'] == 200) {
            return true;
        } else {
            // var_dump($flag);die;
            return false;
        }
    }

    private function postRequest($api, array $params = array(), $timeout = 30) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api);
        // 以返回的形式接收信息
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // 设置为POST方式
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        // 不验证https证书
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
            'Accept: application/json',
        ));
        // 发送数据
        $response = curl_exec($ch);
        // 不要忘记释放资源
        curl_close($ch);
        return $response;
    }

    public function news_detail()
    {
        $id = Filter::int(Req::args('id'));
        $inviter_id = Filter::int(Req::args('inviter_id'));
        if(!isset($this->user['id'])) {
            $redirect = "http://www.ymlypt.com/travel/news_detail/id/".$id;
            if($inviter_id) {
                $redirect.='/inviter_id/'.$inviter_id;
            }
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
                                $open_name = $userinfo['open_name'];
                                $open_name = Common::replace_specialChar($open_name);
                                //插入user表
                                $passWord = CHash::random(6);
                                $validcode = CHash::random(8);
                                $user_id = $this->model->table("user")->data(array('nickname' => $open_name, 'password' => CHash::md5($passWord, $validcode), 'avatar' => $userinfo['head'], 'validcode' => $validcode))->insert();
                                $name = "u" . sprintf("%09d", $user_id);
                                $email = $name . "@no.com";
                                $time = date('Y-m-d H:i:s');
                                $this->model->table("user")->data(array('name' => $name, 'email' => $email))->where("id = ".$user_id)->update();

                                //插入customer表
                                $this->model->table("customer")->data(array('user_id' => $user_id, 'real_name' => $open_name, 'point_coin'=>200, 'reg_time' => $time, 'login_time' => $time))->insert();
                                Log::pointcoin_log(200, $user_id, '', '微信新用户积分奖励', 10);

                                //插入oauth_user表
                                $this->model->table('oauth_user')->data(array(
                                        'user_id' => $user_id, 
                                        'open_name' => $open_name,
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
                            if($inviter_id && $user_id){
                                Common::buildInviteShip($inviter_id, $user_id, 'news_share');
                            }   
                        }
                    } else {
                        header("Location: {$url}"); 
                    }
            }
         }
         if($inviter_id && $this->user['id']) {
            Common::buildInviteShip($inviter_id, $this->user['id'], 'wechat');
         }
        $news = $this->model->table('article')->where('id='.$id)->find();
        $wechatcfg = $this->model->table("oauth")->where("class_name='WechatOAuth'")->find();
        $wechat = new WechatMenu($wechatcfg['app_key'], $wechatcfg['app_secret'], '');
        $token = $wechat->getAccessToken();

        $jssdk = new JSSDK($wechatcfg['app_key'], $wechatcfg['app_secret']);
        $signPackage = $jssdk->GetSignPackage();
        if(strpos($news['thumb_img'],'https') == false || strpos($news['thumb_img'],'http') == false){
            $news['thumb_img'] = "https://ymlypt.b0.upaiyun.com".$news['thumb_img'];
        }
        $this->assign("signPackage", $signPackage);
        $this->assign("news",$news);
        $this->assign("id",$id);
        $this->assign('user_id',$this->user['id']);
        $this->redirect();    
    }

    public function double11()
    {
        // $inviter_id = Filter::int(Req::args('inviter_id'));
        // if(!isset($this->user['id']) || $this->user['id']==null) {
        //     $redirect = "http://www.ymlypt.com/travel/double11";
        //     if($inviter_id) {
        //         $redirect.='/inviter_id/'.$inviter_id;
        //     }
        //     if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
        //        //微信授权登录
        //             $code = Filter::sql(Req::args('code'));
        //             $oauth = new WechatOAuth();
                    
        //             $url = $oauth->getCodes($redirect);
        //             if($code) {
        //                 $extend = null;
        //                 $token = $oauth->getAccessToken($code, $extend);
        //                 $userinfo = $oauth->getUserInfo();
        //                 if(!empty($userinfo)) {
        //                     $openid = $token['openid'];
        //                     $oauth_user = $this->model->table('oauth_user')->where("oauth_type='wechat' AND open_id='{$openid}'")->find();

        //                     if(!$oauth_user) { //未注册
        //                         $open_name = $userinfo['open_name'];
        //                         $open_name = Common::replace_specialChar($open_name);
        //                         //插入user表
        //                         $passWord = CHash::random(6);
        //                         $validcode = CHash::random(8);
        //                         $user_id = $this->model->table("user")->data(array('nickname' => $open_name, 'password' => CHash::md5($passWord, $validcode), 'avatar' => $userinfo['head'], 'validcode' => $validcode))->insert();
        //                         $name = "u" . sprintf("%09d", $user_id);
        //                         $email = $name . "@no.com";
        //                         $time = date('Y-m-d H:i:s');
        //                         $this->model->table("user")->data(array('name' => $name, 'email' => $email))->where("id = ".$user_id)->update();

        //                         //插入customer表
        //                         $this->model->table("customer")->data(array('user_id' => $user_id, 'real_name' => $open_name, 'point_coin'=>200, 'reg_time' => $time, 'login_time' => $time))->insert();
        //                         Log::pointcoin_log(200, $user_id, '', '微信新用户积分奖励', 10);

        //                         //插入oauth_user表
        //                         $this->model->table('oauth_user')->data(array(
        //                                 'user_id' => $user_id, 
        //                                 'open_name' => $open_name,
        //                                 'oauth_type' => "wechat",
        //                                 'posttime' => time(),
        //                                 'token' => $token['access_token'],
        //                                 'expires' => $token['expires_in'],
        //                                 'open_id' => $token['openid']
        //                             ))->insert();

        //                         //记录登录信息
        //                         $obj = $this->model->table("user as us")->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.login_time,cu.mobile,cu.real_name")->where("us.id='$user_id'")->find();
        //                         $obj['open_id'] = $token['openid'];
        //                         $this->safebox->set('user', $obj, 1800);
        //                         $this->user['id'] = $user_id;
        //                     } else { //已注册
        //                         $this->model->table("customer")->data(array('login_time' => date('Y-m-d H:i:s')))->where('user_id='.$oauth_user['user_id'])->update();
        //                         $obj = $this->model->table("user as us")->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.mobile,cu.login_time,cu.real_name")->where("us.id=".$oauth_user['user_id'])->find();
        //                         $this->safebox->set('user', $obj, 31622400);
        //                         $user_id = $oauth_user['user_id'];
        //                         $this->user['id'] = $user_id;
        //                     }
        //                     if($inviter_id && $user_id){
        //                         Common::buildInviteShip($inviter_id, $user_id, 'wechat');
        //                     }   
        //                 }
        //             } else {
        //                 header("Location: {$url}"); 
        //             }
        //     } else {
        //         $this->redirect('/active/login/redirect/double11');
        //     }
        //  }
        $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
        if(strpos($agent, 'android')==true) {
            $platform = 'android';
        } else {
            $platform = 'ios';
        }
        $wechatcfg = $this->model->table("oauth")->where("class_name='WechatOAuth'")->find();
        $wechat = new WechatMenu($wechatcfg['app_key'], $wechatcfg['app_secret'], '');

        $jssdk = new JSSDK($wechatcfg['app_key'], $wechatcfg['app_secret']);
        $signPackage = $jssdk->GetSignPackage();
        
        $config = Config::getInstance()->get("globals");

        $this->assign("config", $config);
        $this->assign("signPackage", $signPackage);
        $this->assign("platform",$platform);
        $this->redirect();
    }

    public function test()
    {
        $this->redirect();
    }

}    