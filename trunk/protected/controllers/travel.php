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
        $list = $this->model->table('travel_way')->where('status=1')->findPage($page,5);

        $this->assign('list',$list);
        $this->redirect();
    }

    public function way_detail() {
        $id = Filter::int(Req::args("id"));
        $info = $this->model->table('travel_way')->where('id='.$id)->find();
        if($this->user['id']) {
            $sign = $this->model->table('travel_order')->where('user_id='.$this->user['id'].' and way_id='.$id)->find();
            $sign_status = empty($sign)?0:1;
        } else {
            $sign_status = 0;
        }
        
        $this->assign('info',$info);
        $this->assign('sign_status',$sign_status);
        $this->redirect();
    }

    public function fill_info()
    {
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
            $this->assign('user_id',$this->user['id']);
            $this->assign('secret', md5('ym123456'));
            $this->assign('policy', $policy);
            $this->assign('way',$way);
            $this->redirect();
        } else {
            if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
                $redirect = "http://www.ymlypt.com/travel/fill_info";
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
                        } else { //已注册
                            $this->model->table("customer")->data(array('login_time' => date('Y-m-d H:i:s')))->where('user_id='.$oauth_user['user_id'])->update();
                            $obj = $this->model->table("user as us")->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.mobile,cu.login_time,cu.real_name")->where("us.id=".$oauth_user['user_id'])->find();
                            $this->safebox->set('user', $obj, 31622400);
                        }

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
                        $this->assign('user_id',$this->user['id']);
                        $this->assign('secret', md5('ym123456'));
                        $this->assign('policy', $policy);
                        $this->assign('way',$way);
                        $this->redirect();   
                    }
                    // return true;
                } else {
                    header("Location: {$url}"); 
                    // $this->redirect($url);
                }        
            } else {
               $this->redirect('/active/login/redirect/fill_info');
            } 
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
            if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
                $redirect = "http://www.ymlypt.com/travel/order_list";
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
                            $obj['open_id'] = $token['open_id'];
                            $this->safebox->set('user', $obj, 31622400);
                            $this->user['id'] = $oauth_user['user_id'];
                        } else { //已注册
                            $this->model->table("customer")->data(array('login_time' => date('Y-m-d H:i:s')))->where('user_id='.$oauth_user['user_id'])->update();
                            $obj = $this->model->table("user as us")->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.mobile,cu.login_time,cu.real_name")->where("us.id=".$oauth_user['user_id'])->find();
                            $this->safebox->set('user', $obj, 31622400);
                            $this->user['id'] = $oauth_user['user_id'];
                        }

                        $page = Filter::int(Req::args('p'));
                        if(!$page) {
                            $page = 1;
                        }
                        $list = $this->model->table('travel_order as t')->fields('t.id,t.order_no,tw.name,tw.city,tw.desc,t.order_amount,tw.img')->join('left join travel_way as tw on t.way_id=tw.id')->where('t.user_id='.$this->user['id'])->order('t.id desc')->findPage($page,5);
                        $this->assign('user_id',$this->user['id']);
                        $this->assign('page',$page);
                        $this->assign('list',$list); 
                        $this->redirect();   
                    }
                    // return true;
                } else {
                    header("Location: {$url}"); 
                    // $this->redirect($url);
                }        
            } else {
               $this->redirect('/active/login/redirect/order_list');
            }
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
                $this->model->table('travel_order')->data(array('pay_status'=>1))->where('order_no='.$order_no)->update();
            }
            echo "success";
            exit();
        }else{
            echo "fail";
            exit();
        }
    }

    public function autologin($redirect) {
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
                    $obj = $model->table("user as us")->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.login_time,cu.mobile,cu.real_name")->where("us.id='$user_id'")->find();
                    $obj['open_id'] = $token['open_id'];
                    $this->safebox->set('user', $obj, 1800);
                } else { //已注册
                    $this->model->table("customer")->data(array('login_time' => date('Y-m-d H:i:s')))->where('user_id='.$oauth_user['user_id'])->update();
                    $obj = $this->model->table("user as us")->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.mobile,cu.login_time,cu.real_name")->where("us.id=".$oauth_user['user_id'])->find();
                    $this->safebox->set('user', $obj, 31622400);
                }   
            }
            return true;
        } else {
            $this->redirect($url);
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
        $user_id = Filter::int(Req::args("user_id"));
        if(!$page) {
                $page = 1;
            }
        $list = $this->model->table('travel_way')->where('status=1')->findPage($page,5);
        $result = !empty($list)?$list['data']:[];
        echo JSON::encode($result);
    } 
}    