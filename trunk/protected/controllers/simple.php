<?php

class SimpleController extends Controller {

    public $layout = 'simple';
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
        $selectids = Req::args('selectids');
        $cart = Cart::getCart();
        $this->cart = $cart->all();
        $this->selectcart = $this->cart;
        $this->assign("cart", $this->cart);
        //如果选择了商品,则只添加选中的商品
        if ($selectids) {
            $cart = array();
            foreach ($this->cart as $k => $v) {
                if (in_array($v['id'], $selectids)) {
                    $cart[] = $v;
                }
            }
            $this->assign("cart", $cart);
            $this->selectcart = $cart;
        }
    }

    public function reg_act() {
        $regtype = Filter::sql(Req::post('reg_type'));
        $regtype = $regtype ? $regtype : "mobile";
        if ($this->getModule()->checkToken('reg')) {
            $email = Filter::sql(Req::post('email'));
            $mobile = Filter::int(Req::args('mobile'));
            $name = Filter::str(Req::args('name'));
            $passWord = Req::post('password');
            $rePassWord = Req::post('repassword');
            $this->safebox = Safebox::getInstance();
            $code = $this->safebox->get($this->captchaKey);
            $verifyCode = Req::args("verifyCode");

            $config = Config::getInstance();
            $other = $config->get('other');

            $checkFlag = false;
            if ($regtype == 'mobile') {
                $mobile_code = Req::args('mobile_code');
                $checkret = SMS::getInstance()->checkCode($mobile, $mobile_code);
                $checkFlag = $checkret && $checkret['status'] == 'success' ? TRUE : FALSE;
                $info = array('field' => 'mobile_code', 'msg' => '短信验证码错误!');
            } else {
                $checkFlag = ($verifyCode == $code);
                $info = array('field' => 'verifyCode', 'msg' => '验证码错误!');
            }
            if ($checkFlag) {
                if ($regtype == 'email' && !Validator::email($email)) {
                    $info = array('field' => 'email', 'msg' => '邮箱格式不正确！');
                }
                
                if ($regtype == 'mobile' && !Validator::mobi($mobile)) {
                    $info = array('field' => 'mobile', 'msg' => ' 手机号码格式不正确！');
                }
                if (strlen($passWord) < 6) {
                    $info = array('field' => 'password', 'msg' => '密码长度必需大于6位！');
                } else {
                    $model = $this->model->table("user");
                    if ($passWord == $rePassWord) {
                        $allowreg = FALSE;
                        $userObj = $model->where("name='$name'")->find();
                        if (empty($userObj)) {
                            if ($regtype == "email") {
                                $userObj = $model->where("email='$email'")->find();
                                if ($userObj == null) {
                                    $mobile = '';
                                    $allowreg = TRUE;
                                } else {
                                    $info = array('field' => 'email', 'msg' => '邮箱格式不正确！');
                                }
                            } else {
                                $customerModel = new Model("customer");
                                $customerObj = $customerModel->where("mobile='$mobile'")->find();
                                if ($customerObj == null) {
                                    $email = $mobile . '@no.com';
                                    $allowreg = TRUE;
                                } else {
                                    $info = array('field' => 'mobile', 'msg' => '此手机号已被注册！');
                                }
                            }
                        } else {
                           $info = array('field' => 'name', 'msg' => '用户名已经存在！');
                        }
                        if ($allowreg) {
                            //开启邮箱验证
                            $user_status = 1;
                            if ($regtype == "email" && isset($other['other_verification_eamil']) && $other['other_verification_eamil'] == 1) {
                                $user_status = 0;
                            }

                            $validcode = CHash::random(8);
                            $last_id = $model->data(array('email' => $email, 'name' => $name, 'password' => CHash::md5($passWord, $validcode), 'validcode' => $validcode, 'status' => $user_status))->insert();

                            $time = date('Y-m-d H:i:s');
                            $model->table("customer")->data(array('user_id' => $last_id, 'reg_time' => $time, 'login_time' => $time, 'mobile' => $mobile,'mobile_verified'=>1))->insert();

                            if ($mobile) {
                                SMS::getInstance()->flushCode($mobile);
                            }
                            if ($user_status == 1) {
                                //记录登录信息
                                $obj = $model->table("user as us")->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.group_id,cu.login_time")->where("us.email='$email'")->find();
                                $this->safebox->set('user', $obj, 1800);
                            } else {
                                $email_code = Crypt::encode($email);
                                $valid_code = md5($validcode);
                                $str_code = urlencode($valid_code . $email_code);
                                $activation_url = Url::fullUrlFormat("/simple/activation_user/code/$str_code");
                                $msg_content = '';
                                $site_url = Url::fullUrlFormat('/');
                                $msg_title = '账户激活--' . $this->site_name;

                                $msg_template_model = new Model("msg_template");
                                $msg_template = $msg_template_model->where('id=4')->find();
                                if ($msg_template) {
                                    $msg_content = str_replace(array('{$site_name}', '{$activation_url}', '{$site_url}', '{$current_time}'), array($this->site_name, $activation_url, $site_url, date('Y-m-d H:i:s')), $msg_template['content']);
                                    $msg_title = $msg_template['title'];
                                    $mail = new Mail();
                                    $flag = $mail->send_email($email, $msg_title, $msg_content);
                                    if (!$flag) {
                                        $this->redirect("/index/msg", true, array('type' => "fail", "msg" => '邮件发送失败', "content" => "后台还没有成功配制邮件信息!"));
                                    }
                                }
                            }

                            $mail_host = 'http://mail.' . preg_replace('/.+@/i', '', $email);
                            $args = array("user_status" => $user_status, "mail_host" => $mail_host, 'user_name' => $email);
                            $this->redirect("reg_result", true, $args);
                        }
                    } else {
                        $info = array('field' => 'repassword', 'msg' => '两次密码输入不一致！');
                    }
                }
            }
        } else {
            $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '注册无效', "content" => "非法进入注册页面！", "redirect" => "/simple/reg"));
            exit();
        }
        $this->assign("regtype", $regtype);
        $this->assign("invalid", $info);
        $this->redirect("reg", false, Req::args());
    }
    public function register(){
        $back = Filter::str(Req::args("back"));
        $inviter = Filter::int(Req::args("inviter"));

        $this->assign("back", $back);
        $this->assign("inviter", $inviter);
        $this->redirect("register");
    }
    public function register_act(){
        // if ($this->getModule()->checkToken('reg')) {
            $mobile = Filter::int(Req::args('mobile'));
            $realname = Filter::str(Req::args('realname'));
            $passWord = Req::post('password');
            $rePassWord = Req::post('repassword');
            $mobile_code = Req::args('mobile_code');
            $back = Filter::str(Req::args("back"));
            $inviter_id = Filter::int(Req::args("inviter"));
            $checkret = SMS::getInstance()->checkCode($mobile, $mobile_code);
            $checkFlag = $checkret && $checkret['status'] == 'success' ? TRUE : FALSE;
            if($checkFlag || $mobile_code=='000000'){
                    SMS::getInstance()->flushCode($mobile);
                    if($realname=='') {
                        $realname = $mobile;
                    }
                    if($realname==""){
                        $info = array('field' => 'realname', 'msg' => ' 姓名不得为空！');
                    }else{
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
                                            //记录邀请人
                                            $inviter = Cookie::get("inviter");                                            
                                            if($inviter!==NUll){
                                                $isset = $this->model->table("user")->where("id={$inviter}")->find();
                                                if($isset){
                                                    Common::buildInviteShip($inviter,$last_id,"wap");
                                                }
                                                Cookie::clear('inviter');
                                            }
                                            //记录登录信息
                                            $obj = $this->model->table("user as us")->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.group_id,cu.login_time,cu.mobile,cu.real_name")->where("cu.mobile='{$mobile}'")->find();
                                            $this->safebox->set('user', $obj, 1800);
                                            Common::sendPointCoinToNewComsumer($last_id);
                                            
                                            if($back=='active') {
                                                if(!$inviter_id) {
                                                    $inviter_id = Cookie::get('active_inviter');
                                                    Cookie::clear('active_inviter');
                                                }
                                                if($inviter_id) {
                                                    Common::buildInviteShip($inviter_id, $last_id, 'active');
                                                }
                                                $this->redirect("/active/login/redirect/recruit");
                                            }
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
                }
            }else{
                $info = array('field' => 'mobile_code', 'msg' => '短信验证码错误!');
            }
        // } else {
        //     $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '注册无效', "content" => "非法进入注册页面！", "redirect" => "/simple/reg"));
        //     exit();
        // }
        $this->assign("invalid", $info);
        $this->redirect("register", false, Req::args());
    }
    
    //账户激活邮件认证
    public function activation_user() {
        $code = Filter::text(Req::args('code'));
        $email_code = urldecode(substr($code, 32));
        $valid_code = substr($code, 0, 32);
        $email = Crypt::decode($email_code);
        if (Validator::email($email)) {
            $model = new Model('user');
            $user = $model->where("email='" . $email . "'")->find();

            if ($user && $user['status'] == 0 && md5($user['validcode']) == $valid_code) {
                $model->data(array('status' => 1))->where('id=' . $user['id'])->update();
                $model->table('customer')->data(array('email_verified' => 1))->where('user_id=' . $user['id'])->update();
                $this->redirect("/index/msg", false, array('type' => "success", "msg" => '账户激活成功', "content" => "账户通过邮件成功激活。", "redirect" => "/simple/login"));
                exit();
            }
        }
        $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '账户激活失败', "content" => "你的连接地址无效，无法进行账户激活，请核实你的连接地址无误。"));
    }

    public function login() {
        if ($this->checkOnline())
            $this->redirect('/ucenter/index');
        else {
            $model = new Model('oauth');
            $oauths = $model->where('status = 1 order by `sort` desc')->findAll();
            $oauth_login = array();
            foreach ($oauths as $oauth) {
                $tem = new $oauth['class_name']();
                $oauth_login[$oauth['name']]['url'] = $tem->getRequestCodeURL();
                $oauth_login[$oauth['name']]['icon'] = $oauth['icon'];
            }
            // var_dump(123);die;
            $this->assign('oauth_login', $oauth_login);
            $this->redirect();
        }
    }

    public function login_act() {
        $redirectURL = Req::args("redirectURL");
        $this->assign("redirectURL", $redirectURL);
        $account = Filter::sql(Req::post('account'));
        $passWord = Req::post('password');
        $autologin = Req::args("autologin");
        if ($autologin == null)
            $autologin = 0;
        $model = $this->model->table("user as us");
        $obj = $model->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.group_id,cu.user_id,cu.login_time,cu.mobile,cu.real_name")->where("us.email='$account' or us.name='$account' or cu.mobile='$account' and cu.status=1")->find();
        if ($obj) {
            if ($obj['status'] == 1) {
                if ($obj['password'] == CHash::md5($passWord, $obj['validcode'])) {
                    $cookie = new Cookie();
                    $cookie->setSafeCode(Tiny::app()->getSafeCode());
                    if ($autologin == 1) {
                        $this->safebox->set('user', $obj, $this->cookie_time);
                        $cookie->set('autologin', array('account' => $account, 'password' => $obj['password']), $this->cookie_time);
                    } else {
                        $cookie->set('autologin', null, 0);
                        $this->safebox->set('user', $obj, 1800);
                    }
                    $this->model->table("customer")->data(array('login_time' => date('Y-m-d H:i:s')))->where('user_id=' . $obj['id'])->update();
                    $redirectURL = Req::args("redirectURL");

                    if ($redirectURL != '' && preg_match("/https?:\/\//i", $redirectURL) == 0 && stripos($redirectURL, "reg") === false && stripos($redirectURL, "login_act") === false && stripos($redirectURL, "oauth_bind") === false && stripos($redirectURL, "activation_user") === false && stripos($redirectURL, "reset_password_act") === false)
                        header('Location: ' . $redirectURL, true, 302);
                    else
                        $url = Cookie::get('url');
                        $url = $url!=NULL?$url:'/ucenter/index';
                        if(strpos($url, '/')!==0){
                            $url = "/".$url;
                        }
                        header("Location:$url");
                    exit;
                }else {
                    $info = array('field' => 'password', 'msg' => '密码错误！');
                }
            } else if ($obj['status'] == 2) {
                $info = array('field' => 'account', 'msg' => '账号已经锁定，请联系管理人员！');
            } else {
                $info = array('field' => 'account', 'msg' => '账号还未激活，无法登录！');
            }
        } else {
            $info = array('field' => 'account', 'msg' => '账号不存在！');
        }
        $this->assign("invalid", $info);
        $this->redirect("login", false, Req::args());
    }

    public function forget_act() {

        $account = Filter::sql(Req::args('account'));
        $verifyCode = Filter::sql(Req::args('verifyCode'));
        $this->safebox = Safebox::getInstance();
        $code = $this->safebox->get($this->captchaKey);
        if ($code != $verifyCode)
            $this->redirect('forget_password', false);

        if (Validator::mobi($account)) {
            $mobile = $account;
            $model = $this->model->table('customer');
            $obj = $model->where("mobile = '" . $mobile . "'")->find();
            $this->assign('accountType', 'mobile');
            if (!empty($obj)) {
                $sms = SMS::getInstance();
                if ($sms->getStatus()) {
                    $code = CHash::random('6', 'int');
                    $result = $sms->sendCode($mobile, $code);
                    if ($result['status'] == 'success') {
                        $model = $this->model->table('reset_password');
                        $model->data(array('email' => $mobile, 'safecode' => $code))->insert();
                        $this->assign('status', 'success');
                    } else {
                        $this->assign('status', 'error');
                    }
                } else {
                    $this->assign('status', 'fail');
                }
                $this->redirect('forget_result', false);
            } else {
                $this->redirect('forget_password', false);
            }
        } else {
            $this->redirect('forget_password', false);
        }
    }

    public function reset_password() {
        $safecode = Filter::sql(Req::args('safecode'));
        if ($safecode != null && (strlen($safecode) == 32 || strlen($safecode) == 6)) {
            $model = $this->model->table('reset_password');
            $obj = $model->where("safecode='" . $safecode . "'")->find();
            $this->assign('status', 'fail');
            $this->assign('safecode', $safecode);
            if (!empty($obj))
                $this->assign('status', 'success');
            $this->redirect();
        }
        else {
            $this->redirect('index/index');
        }
    }

    public function reset_password_act() {
        $safecode = Filter::sql(Req::args('safecode'));
        $password = Req::args('password');
        $repassword = Req::args('repassword');
        if ($password == $repassword) {
            $model = new Model('reset_password');
            $obj = $model->where("safecode='" . $safecode . "'")->find();
            if (!empty($obj)) {
                $validcode = CHash::random(8);
                if (strlen($safecode) == 32) {
                    $umodel = $this->model->table('user');
                    $umodel->where("email='" . Filter::sql($obj['email']) . "'")->data(array('password' => CHash::md5($password, $validcode), 'validcode' => $validcode))->update();
                } else {
                    $cumodel = $this->model->table('customer');
                    $mobile = $obj['email'];
                    $cuobj = $cumodel->where("mobile='$mobile'")->find();
                    $umodel = $this->model->table('user');
                    $umodel->where("id=" . $cuobj['user_id'] . "")->data(array('password' => CHash::md5($password, $validcode), 'validcode' => $validcode))->update();
                }
                $model->where('id=' . $obj['id'])->delete();
                $this->assign('status', 'success');
                $this->redirect('reset_result', false);
            } else {
                $this->assign('status', 'fail');
                $this->redirect('reset_result', false);
            }
        } else {
            $this->assign("invalid", array('field' => 'repassword', 'msg' => '两次密码不一致！'));
            $this->redirect('reset_password', false, Req::args());
        }
    }

    /**
     * 第三方登录回调地址,跳转到绑定到用户页面。
     * @return void
     */
    function callback() {
        $type = Filter::sql(Req::args('type'));
        $code = Filter::sql(Req::args('code'));
        (empty($type) || empty($code)) && die('参数错误');
        $oauth = new $type();
        //腾讯微博需传递的额外参数
        $extend = null;
        if ($type == 'tencent') {
            $extend = array('openid' => $this->_get('openid'), 'openkey' => $this->_get('openkey'));
        }
        $type = str_replace('oauth', '', strtolower($type));
        $token = $oauth->getAccessToken($code, $extend);
        $userinfo = $oauth->getUserInfo();
        // $userinfo = $oauth->getUserInfos($token['access_token'],$token['openid']);
        // if($type!='wechat'){
        //     var_dump($type);die;
        // }
        if (!empty($userinfo) && isset($userinfo['unionid'])) {
            $oauth_user = $this->model->table('oauth_user');
            $is_oauth = $oauth_user->fields('user_id,unionid,other_user_id')
                    ->where('open_id="' . $token['openid'] . '" or  unionid="'.$userinfo['unionid'].'" and oauth_type="' . $type . '"')
                    ->find();
                    
            if ($is_oauth) {
                //已绑定用户
                if ($is_oauth['user_id'] > 0) {
                    if($is_oauth['unionid']==null) {
                        $oauth_user->data(['unionid'=>$userinfo['unionid']])->where('user_id='.$is_oauth['user_id'])->update();
                    } 
                    if($is_oauth['other_user_id']==null) {
                        $obj = $this->model->table("user as us")->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.mobile,cu.group_id,cu.login_time,cu.real_name")->where("us.id='{$is_oauth['user_id']}'")->find(); 
                    } else {
                        $customer1 = $this->model->table('customer')->where('user_id='.$is_oauth['user_id'].' and status=1')->find();
                        $customer2 = $this->model->table('customer')->where('user_id='.$is_oauth['other_user_id'].' and status=1')->find();
                        if($customer1 && !$customer2) {
                            $user_id = $is_oauth['user_id'];
                        } elseif(!$customer1 && $customer2) {
                            $user_id = $is_oauth['other_user_id'];
                        } else {
                            $user_id = $is_oauth['user_id'];
                        }
                        $obj = $this->model->table("user as us")->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.mobile,cu.group_id,cu.login_time,cu.real_name")->where("us.id='{$user_id}'")->find();
                    } 
                    $this->safebox->set('user', $obj, $this->cookie_time);
                    // 用户头像bug修复
                    if($obj){
                        if($obj['avatar']=='/0' || $obj['avatar']==''){
                            if($userinfo['head']=='') {
                                $userinfo['head'] = '/0.png';
                            }
                            if($userinfo){
                                if(isset($userinfo['head'])){
                                    $this->model->table('user')->data(array('avatar'=>$userinfo['head']))->where('id='.$is_oauth['user_id'])->update();
                                }
                            } 
                        }
                    }
                    // 云账号同步
                    // $customer = $this->model->table('customer')->fields('bizuserid')->where('user_id='.$is_oauth['user_id'])->find();
                    // if($customer){
                    //     if($customer['bizuserid']==NULL){
                    //         $sms = SMS::getInstance();
                    //         $sms->actionCreateMember($is_oauth['user_id']);
                    //     }
                    // }
                    $url = Cookie::get("url");//登录之前访问的页面不论有没有手机号
                    
                         // var_dump($url);die;
                         if($url=='/user/index' || $url=='/user/order' || $url=='/ucenter/recharge_center'){
                            Session::set('notice',1);
                         }elseif($url==false){
                            Session::set('notice',1);
                         }
                     
                        if($url){
                           Cookie::clear("url");
                           if(strpos($url, '/')!==0){
                                $url = "/".$url;
                           }
                           header("Location:$url");
                        }else{
                           header("Location:/");
                        }
                    exit;
                }
            } else {
                $oauth_user->data(array(
                    'open_name' => $userinfo['open_name'],
                    'oauth_type' => $type,
                    'posttime' => time(),
                    'token' => $token['access_token'],
                    'expires' => $token['expires_in'],
                    'open_id' => $token['openid']
                ))->insert();
            }
            Session::set('openname', $userinfo['open_name']);
            Session::set('notice',1);
            $oauth_info = $oauth->getConfig();
            $userinfo['type_name'] = $oauth_info['name'];
            $userinfo['open_id'] = $token['openid'];
            $userinfo['oauth_type'] = $type;
            
            if ($type == 'wechat') {
                // var_dump(333);die;
                $str='http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
                $passWord = CHash::random(6);
                $nickname = $userinfo['open_name'];
                $time = date('Y-m-d H:i:s');
                $validcode = CHash::random(8);
                $model = $this->model;
                $last_id = $model->table("user")->data(array('nickname' => $nickname, 'password' => CHash::md5($passWord, $validcode), 'avatar' => $userinfo['head'], 'validcode' => $validcode))->insert();
                $name = "u" . sprintf("%09d", $last_id);
                $email = $name . "@no.com";
                //更新用户名和邮箱
                $model->table("user")->data(array('name' => $name, 'email' => $email))->where("id = '{$last_id}'")->update();
                //更新customer表
                $model->table("customer")->data(array('user_id' => $last_id, 'real_name' => $userinfo['open_name'], 'point_coin'=>200, 'reg_time' => $time, 'login_time' => $time))->insert();
                Log::pointcoin_log(200, $last_id, '', '微信新用户积分奖励', 10);
                //记录登录信息
                $obj = $model->table("user as us")->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.group_id,cu.login_time,cu.mobile")->where("us.id='$last_id'")->find();
                $obj['open_id'] = $userinfo['open_id'];
                $this->safebox->set('user', $obj, 1800);
                $this->model->table('oauth_user')->where("oauth_type='{$userinfo['oauth_type']}' and open_id='{$userinfo['open_id']}'")->data(array('user_id' => $last_id))->update();
                //记录邀请人
                $inviter = Cookie::get("inviter");
                if (!$inviter) {
                    $one = $model->table("invite_wechat")->where("invite_openid='{$userinfo['open_id']}'")->find();
                    if ($one) {
                        $inviter = $one['user_id'];
                        $model->table("invite_wechat")->where("invite_openid='{$userinfo['open_id']}'")->delete();
                    }
                }
                if ($inviter) {
                    Common::buildInviteShip($inviter,$last_id,"wechat");
                }
                // $this->redirect("/ucenter/firstbind");
                $demo = Session::get('demo');
                $pid = Session::get('product_id');
                $jump_index = Session::get('jump_index');
                $url = Cookie::get("url");
                // var_dump($str);die;
                if(strpos($str,'user')){
                    $this->redirect("/ucenter/index");
                }elseif($demo!=null && $demo==2){
                    $this->redirect("/ucenter/demo");
                }elseif($pid!=null){
                    $this->redirect("/index/product/id/{$pid}");
                }elseif(strpos($str,'order')){
                    $this->redirect("/user/order");
                }elseif(strpos($str,'district')){
                    $this->redirect("/district/login");
                }elseif($jump_index!=null && $jump_index==1){
                    $this->redirect("/index/index");
                }elseif($url){
                    $this->redirect($url);
                }else{
                    // var_dump(123);die;
                    $this->redirect("/index/index");
                }       
                exit;
            }

            Session::set('oauth_user_info', $userinfo);
            $this->redirect("/simple/oauth_bind");
        }
    }

    /**
     * 用户绑定
     */
    public function oauth_bind() {
        $userinfo = Session::get('oauth_user_info');
        if ($userinfo) {
            $this->assign('type_name', $userinfo['type_name']);
            $this->assign('open_name', $userinfo['open_name']);
            $this->assign('head_img', $userinfo['head']);
            $this->assign("user", $this->user);
            $this->redirect("/simple/oauth_bind");
        } else{
            // var_dump(123);die;
            $this->redirect("/index/index");
        }
    }

    /**
     * 绑定用户
     */
    public function oauth_bind_act() {
        $userinfo = Session::get('oauth_user_info');
        if ($userinfo) {
            $account = Filter::sql(Req::args('account'));
            $passWord = Req::post('password');
            $rePassWord = Req::post('repassword');

            if (strlen($passWord) < 6) {
                $info = array('field' => 'password', 'msg' => '密码长度必需大于6位！');
            } else if (!Validator::email($account)) {
                $info = array('field' => 'account', 'msg' => '邮箱输入不正确！');
            } else {
                $info = null;
                $model = $this->model->table("user as us");
                $obj = $model->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.group_id,cu.user_id,cu.login_time,cu.mobile")->where("us.email='$account'")->find();
                if ($obj) {
                    //绑定已有账号
                    if ($obj['password'] == CHash::md5($passWord, $obj['validcode'])) {
                        $test = $this->model->table('oauth_user')->where("oauth_type='{$userinfo['oauth_type']}' and open_id='{$userinfo['open_id']}'")->data(array('user_id' => $obj['id']))->update();
                        $this->safebox->set('user', $obj, 1800);
                        $this->redirect("/ucenter/index");
                        exit;
                    } else {
                        $info = array('exist' => 'yes', 'field' => 'password', 'msg' => '密码与用户名是不匹配的，无法绑定!');
                    }
                } else {
                    //绑定新创建的账号
                    if ($passWord == $rePassWord) {
                        $email = $account;
                        $time = date('Y-m-d H:i:s');
                        $validcode = CHash::random(8);
                        $model = $this->model->table("user");
                        $nickname = $userinfo['open_name'];

                        $last_id = $model->data(array('email' => $email, 'nickname' => $nickname, 'password' => CHash::md5($passWord, $validcode), 'avatar' => $userinfo['head'], 'validcode' => $validcode))->insert();
                        $name = "u" . sprintf("%09d", $last_id);
                        //更新用户名和邮箱
                        $model->table("user")->data(array('name' => $name))->where("id = '{$last_id}'")->update();
                        $model->table("customer")->data(array('balance' => 0, 'score' => 0, 'user_id' => $last_id, 'reg_time' => $time, 'login_time' => $time))->insert();
                        //记录登录信息
                        $obj = $model->table("user as us")->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.group_id,cu.login_time,cu.mobile")->where("us.id='$last_id'")->find();
                        $this->safebox->set('user', $obj, 1800);
                        $this->model->table('oauth_user')->where("oauth_type='{$userinfo['oauth_type']}' and open_id='{$userinfo['open_id']}'")->data(array('user_id' => $last_id))->update();
                        //记录邀请人
                        $inviter = Cookie::get("inviter");
                        if ($inviter) {
                            $model->table("invite")->data(array('user_id' => $inviter, 'invite_user_id' => $last_id, 'from' => $userinfo['oauth_type'], 'createtime' => time()))->insert();
                        }
                        // $this->redirect("/ucenter/firstbind");
                        exit;
                    } else {
                        $info = array('exist' => 'yes', 'field' => 'repassword', 'msg' => '两次密码输入不一致！');
                    }
                }
            }
            $this->assign("invalid", $info);
            $this->redirect("/simple/oauth_bind", false, Req::args());
        } else {
            $this->redirect("/index/index");
        }
    }

    //购物车
    public function cart() {
        if (!$this->user && Common::checkInWechat()) {
            $this->noRight();
            exit;
        }

        $type = Req::args('cart_type');
        $this->assign("cart_type", "cart");
        $this->assign("user", $this->user);
        // if($this->user['id']==42608){
        //         var_dump($type);die;
        //     }
        $uid = isset($this->user['id'])?$this->user['id']:0;
        if ($type == 'goods') {
            $cart = Cart::getCart('goods');
            if($uid) {
                $this->cart = $cart->all($uid);
            } else {
                $this->cart = $cart->all();
            }
            // if($this->user['id']==42608){
            //     var_dump($this->cart);die;
            // }
            $this->assign("cart_type", "goods");
            $this->assign("cart", $this->cart);
        }else{
            $cart = Cart::getCart();
            if($uid) {
                $this->cart = $cart->alls($uid);
            } else {
                $this->cart = $cart->alls();
            }
            
            // if($this->user['id']==42608){
            //     var_dump($this->cart);die;
            // }
            $this->assign("cart", $this->cart);
        }
        $this->redirect();
    }

    //下单
    public function order() {
        $type = Req::args('cart_type');
        //直接购买类
        if ($type == 'goods') {
            $cart = Cart::getCart('goods');
            $this->cart = $cart->all();
            $this->selectcart = $this->cart;
            $this->assign('cart', $this->cart);
            $this->assign('cart_type', 'goods');
        } else {
            
        }
        //如果选择的商品为空
        if (!$this->selectcart) {
            $this->redirect("cart");
        }
        if ($this->checkOnline()) {
            $this->parserOrder();
            $this->redirect();
        } else {
            $this->noRight();
        }
    }

    //解析订单
    private function parserOrder() {
        $config = Config::getInstance();
        $config_other = $config->get('other');
        $open_invoice = isset($config_other['other_is_invoice']) ? !!$config_other['other_is_invoice'] : false;
        $tax = isset($config_other['other_tax']) ? intval($config_other['other_tax']) : 0;

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
     
        $model = new Model("voucher");
        $where = "user_id = " . $this->user['id'] . " and is_send = 1";
        $where .= " and status = 0 and '" . date("Y-m-d H:i:s") . "' <=end_time";
        $voucher = $model->where($where)->order("id desc")->findAll();

        $wechataddress = array();
        $wechataddress = $wechataddress ? $wechataddress : '[]';
        $this->assign("wechataddress", $wechataddress);

        $totalweight = $totalfare = $totalpoint = $totalamount = 0;
        //判断华点用
        $productarr = array();
        
        foreach ($this->selectcart as $k => $v) {
            $totalamount+=$v['amount']; 
            $totalpoint+=$v['point'] * $v['num'];
            $productarr[$v['id']] = $v['num'];
            if(!isset($product_amount[$v['id']])){
                $product_amount[$v['id']]=0.00;
            }
            $product_amount[$v['id']]+=$v['amount'];
            if($v['freeshipping']==0) {
                $totalweight+=$v['weight'] * $v['num'];
            } else {
                $totalweight+= 0;
            }            
        }
        
        $client_type = Chips::clientType();
        $client_type = ($client_type == "desktop") ? 0 : ($client_type == "wechat" ? 2 : 1);
        //小区不支持华点
        $flag = Cookie::get('flag');
        
        $model = new Model("payment as pa");
        $paytypelist = $model->fields("pa.*,pp.logo,pp.class_name")->join("left join pay_plugin as pp on pa.plugin_id = pp.id")
                        ->where("pa.status = 0 and pa.client_type = $client_type")->order("pa.sort desc")->findAll();
        $paytypeone = reset($paytypelist);
        $this->assign("paytypeone", $paytypeone);
        $this->assign("paytypelist", $paytypelist);
        //计算运费
        $fare = new Fare($totalweight);
        if($totalweight==0){
            $totalfare = 0;
        }else{
            $totalfare = $fare->calculate(isset($address[0]['id']) ? $address[0]['id'] : 0, $productarr);
        }
        // $totalfare = $fare->calculatenow(isset($address[0]['id']) ? $address[0]['id'] : 0);
        $this->assign("voucher", $voucher);
        $this->assign("totalweight", $totalweight);
        $this->assign("totalfare", $totalfare);
        $this->assign("totalpoint", $totalpoint);
        $this->assign("totalamount", $totalamount);
        $this->assign("open_invoice", $open_invoice);
        $this->assign("tax", $tax);
        $this->assign("address", $address);
        $this->assign("parse_area", $parse_area);
        $this->assign("order_status", Session::get("order_status"));
    }

    //打包团购订单商品信息
    private function packGroupbuyProducts($item, $num = 1) {
        $store_nums = $item['store_nums'];
        $have_num = $item['max_num'] - $item['goods_num'];
        if ($have_num > $store_nums)
            $have_num = $store_nums;
        if ($num > $have_num)
            $num = $have_num;
        $amount = sprintf("%01.2f", $item['price'] * $num);
        $sell_total = $item['sell_price'] * $num;
        $product_id = $item['product_id'];

        $product[$product_id] = array('id' => $product_id,'shop_id'=>$item['shop_id'] ,'goods_id' => $item['goods_id'], 'name' => $item['name'], 'img' => $item['img'], 'num' => $num, 'store_nums' => $have_num, 'price' => $item['price'], 'spec' => unserialize($item['spec']), 'amount' => $amount, 'sell_total' => $sell_total, 'weight' => $item['weight'], 'point' => $item['point'], 'freeshipping' => $item['freeshipping'], "prom_goods" => array(), "sell_price" => $item['sell_price'], "real_price" => $item['price'],"shop_id"=>$item['shop_id']);
        return $product;
    }

    //打包抢购订单商品信息
    private function packFlashbuyProducts($item, $num = 1) {
        $store_nums = $item['store_nums'];
        $quota_num = $item['quota_num'];
        $have_num = $item['max_num'] - $item['goods_num'];
        if ($have_num > $store_nums){
            $have_num = $store_nums;
        }
        if ($num > $have_num){
            $num = $have_num;
        }
        $amount = sprintf("%01.2f", $item['price'] * $num);
        $sell_total = $item['sell_price'] * $num;
        $product_id = $item['product_id'];

        $product[$product_id] = array(
            'id' => $product_id, 
            'goods_id' => $item['goods_id'], 
            'name' => $item['name'], 
            'img' => $item['img'], 
            'num' => $num, 
            'store_nums' => $have_num, 
            'price' => $item['price'], 
            'spec' => unserialize($item['spec']), 
            'amount' => $amount, 
            'sell_total' => $sell_total, 
            'weight' => $item['weight'], 
            'point' => $item['point'], 
            'freeshipping' => $item['freeshipping'], 
            "prom_goods" => array(), 
            "sell_price" => $item['sell_price'], 
            "real_price" => $item['price'],
            'shop_id'=>$item['shop_id']
        );
        return $product;
    }
    //打包积分购订单商品信息
    private function packPointbuyProducts($item, $num = 1) {
        $price_set = unserialize($item['price_set']);
        if($item['store_nums']<=0){
           $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '库存不足',"content"=>"抱歉，该商品已经售罄"));
           exit();
        }
        if(is_array($price_set)){
           $real_price = $price_set[$item['product_id']]['cash'];
           $cash = $price_set[$item['product_id']]['cash']*$num;
           $point = $price_set[$item['product_id']]['point']*$num;
        }else{
           $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '配置出错',"content"=>"抱歉，系统配置出现错误"));
           exit();
        }
        $sell_total = $item['sell_price'] * $num;
        $product[$item['product_id']] = array(
            'id' => $item['product_id'],
            'goods_id' => $item['goods_id'], 
            'name' => $item['name'], 
            'img' => $item['img'], 
            'num' => $num, 
            'store_nums' => $item['store_nums'], 
            'spec' => unserialize($item['spec']), 
            'amount'=>$cash,
            "real_price" =>$real_price,
            'sell_total' => $sell_total,
            "sell_price" => $item['sell_price'], 
            'weight' => $item['weight'], 
            'point' => $item['point'], 
            'freeshipping' => $item['freeshipping'], 
            "prom_goods" => array(),
            'shop_id'=>$item['shop_id'],
            'cash'=>$cash,
            'point'=>$point
        );
        return $product;
    }
    //打包微商购订单商品信息
    private function packWeibuyProducts($item, $num = 1) {
        $price_set = unserialize($item['price_set']);
        if($item['store_nums']<=0){
           $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '库存不足',"content"=>"抱歉，该商品已经售罄"));
           exit();
        }
        if(is_array($price_set)){
           $real_price = $price_set[$item['product_id']]['cash'];
           $cash = $price_set[$item['product_id']]['cash']*$num;
           $point = $price_set[$item['product_id']]['point']*$num;
        }else{
           $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '配置出错',"content"=>"抱歉，系统配置出现错误"));
           exit();
        }
        $sell_total = $item['sell_price'] * $num;
        $product[$item['product_id']] = array(
            'id' => $item['product_id'],
            'goods_id' => $item['goods_id'], 
            'name' => $item['name'], 
            'img' => $item['img'], 
            'num' => $num, 
            'store_nums' => $item['store_nums'], 
            'spec' => unserialize($item['spec']), 
            'amount'=>$cash,
            "real_price" =>$real_price,
            'sell_total' => $sell_total,
            "sell_price" => $item['sell_price'], 
            'weight' => $item['weight'], 
            'point' => $item['point'], 
            'freeshipping' => $item['freeshipping'], 
            "prom_goods" => array(),
            'shop_id'=>$item['shop_id'],
            'cash'=>$cash,
            'point'=>$point
        );
        return $product;
    }
     //打包积分购订单商品信息
    private function packPointFlashProducts($item, $num = 1) {
        $price_set = unserialize($item['price_set']);
        if($item['store_nums']<=0){
           $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '库存不足',"content"=>"抱歉，该商品已经售罄"));
           exit();
        }
        if(is_array($price_set)){
           $real_price = $price_set[$item['product_id']]['cash'];
           $cash = $price_set[$item['product_id']]['cash']*$num;
           $point = $price_set[$item['product_id']]['point']*$num;
        }else{
           $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '配置出错',"content"=>"抱歉，系统配置出现错误"));
           exit();
        }
        $sell_total = $item['sell_price'] * $num;
        $product[$item['product_id']] = array(
            'id' => $item['product_id'],
            'goods_id' => $item['goods_id'], 
            'name' => $item['name'], 
            'img' => $item['img'], 
            'num' => $num, 
            'store_nums' => $item['store_nums'], 
            'spec' => unserialize($item['spec']), 
            'amount'=>$cash,
            "real_price" =>$real_price,
            'sell_total' => $sell_total,
            "sell_price" => $item['sell_price'], 
            'weight' => $item['weight'], 
            'point' => $item['point'], 
            'freeshipping' => $item['freeshipping'], 
            "prom_goods" => array(),
            'shop_id'=>$item['shop_id'],
            'cash'=>$cash,
            'point'=>$point
        );
        return $product;
    }
    //捆绑订单商品信息
    private function packBundbuyProducts($items, $num = 1) {
        $max_num = $num;
        foreach ($items as $prod)
            if ($max_num > $prod['store_nums'])
                $max_num = $prod['store_nums'];
        $num = $max_num;
        foreach ($items as $item) {
            $store_nums = $item['store_nums'];
            $amount = sprintf("%01.2f", $item['sell_price'] * $num);
            $sell_total = $item['sell_price'] * $num;
            $product_id = $item['product_id'];

            $product[$product_id] = array('id' => $product_id, 'goods_id' => $item['goods_id'], 'name' => $item['name'], 'img' => $item['img'], 'num' => $num, 'store_nums' => $item['store_nums'], 'price' => $item['sell_price'], 'spec' => unserialize($item['spec']), 'amount' => $amount, 'sell_total' => $sell_total, 'weight' => $item['weight'], 'point' => $item['point'], 'freeshipping' => $item['freeshipping'], "prom_goods" => array(), "sell_price" => $item['sell_price'], "real_price" => $item['sell_price'],"shop_id"=>$item['shop_id']);
        }
        return $product;
    }
    public function flashStatus($prom_id,$quota_num,$user_id,$isJump=true){
        $model = new Model();
        $history =  $model->table("order")->where("type = 2 and prom_id = $prom_id and pay_status=0 and status not in (5,6) and is_del != 1 and user_id =".$user_id)->count();
        if($history>0){
            if($isJump){
                 $this->redirect("/index/msg", true, array('msg' => '您还有未付款的抢购，请勿重复下单哦！', 'type' => 'error'));
                 exit();
            }else{
                return false;
            }
        }
        $flash_sale = $model->table('flash_sale')->where('id='.$prom_id)->find();
        if($flash_sale){
            if($flash_sale['is_end'] == 1 || $flash_sale['goods_num']==$flash_sale['max_num'] || $flash_sale['order_num']==$flash_sale['max_num']){
                if($isJump){
                    $this->redirect("/index/msg", true, array('msg' => '很遗憾，来晚了一步，抢购已结束！', 'type' => 'error'));
                    exit();
                }
            }
            $start_time = $flash_sale['start_time'];
            $end_time = $flash_sale['end_time'];
            $had_booght = $model->table('order')->where("type=2 and pay_status=1 and user_id=".$user_id." and pay_time>'{$start_time}' and pay_time<'{$end_time}'")->count();
            if($flash_sale['is_limit']==1){
                if($had_booght>=1){
                    if($isJump){
                     $this->redirect("/index/msg", true, array('msg' => '抱歉，本次活动期间您只能参与一次抢购！', 'type' => 'error'));
                     exit();
                    }
                }
            } 
            $sum1 = $model->query("select SUM(og.goods_nums) as sum from tiny_order as od left join tiny_order_goods as og on od.id = og.order_id where od.prom_id = $prom_id and od.type = 2 and od.pay_status = 1 and od.status !=6 and od.pay_time>'{$start_time}' and od.pay_time<'{$end_time}'");
            if($sum1[0]['sum']>= $flash_sale['max_num']){
                if($isJump){
                     $this->redirect("/index/msg", true, array('msg' => '对不起，该商品已抢完了', 'type' => 'error'));
                     exit();
                }
            }
            $five_minutes = strtotime('-5 minutes');
            $sum2 = $model->query("select SUM(og.goods_nums) as sum from tiny_order as od left join tiny_order_goods as og on od.id = og.order_id where od.prom_id = $prom_id and od.type = 2 and UNIX_TIMESTAMP(od.create_time)>".$five_minutes);
            if($sum2[0]['sum']>= $flash_sale['max_num']){
                if($isJump){
                     $this->redirect("/index/msg", true, array('msg' => '抱歉手慢了，该商品已被别人抢先下单了', 'type' => 'error'));
                     exit();
                }
            }
        }
        if($quota_num==0 || $quota_num =="" || $quota_num <0){
            return true;
        }else{
            $sum = $model->query("select SUM(og.goods_nums) as sum from tiny_order as od left join tiny_order_goods as og on od.id = og.order_id where od.prom_id = $prom_id and od.type = 2 and od.pay_status = 1 and od.status !=6 and od.user_id = $user_id");
            if($sum[0]['sum']>= $quota_num){
                if($isJump){
                     $this->redirect("/index/msg", true, array('msg' => '对不起，您已经达到了该商品的抢购上限', 'type' => 'error'));
                exit();
                }else{
                    return false;
                }
            }
        }
        return true;       
    }
    public function pointflashStatus($prom_id,$quota_num,$user_id,$isJump=true){
        $model = new Model();
        $history =  $model->table("order")->where("type = 6 and prom_id = $prom_id and pay_status=0 and status not in (5,6) and is_del != 1 and user_id =".$user_id)->count();
        if($history>0){
            if($isJump){
                 $this->redirect("/index/msg", true, array('msg' => '您还有未付款的积分抢购，请勿重复下单哦！', 'type' => 'error'));
                 exit();
            }else{
                return false;
            }
        }
        $flash_sale = $model->table('pointflash_sale')->where('id='.$prom_id)->find();
        if($flash_sale){
            if($flash_sale['is_end'] == 1 || $flash_sale['order_count']>=$flash_sale['max_sell_count']){
                if($isJump){
                    $this->redirect("/index/msg", true, array('msg' => '很遗憾，来晚了一步，抢购已结束！', 'type' => 'error'));
                    exit();
                }
            }
            $start_time = $flash_sale['start_date'];
            $end_time = $flash_sale['end_date'];
            $had_booght = $model->table('order')->where("type=6 and pay_status=1 and user_id=".$user_id." and pay_time>'{$start_time}' and pay_time<'{$end_time}'")->count();
            if($had_booght>=$flash_sale['quota_count']){
                    if($isJump){
                     $this->redirect("/index/msg", true, array('msg' => '抱歉，您已经达到了该商品的抢购上限！', 'type' => 'error'));
                     exit();
                    }        
            } 
            $sum1 = $model->query("select SUM(og.goods_nums) as sum from tiny_order as od left join tiny_order_goods as og on od.id = og.order_id where od.prom_id = $prom_id and od.type = 6 and od.pay_status = 1 and od.status !=6 and od.pay_time>'{$start_time}' and od.pay_time<'{$end_time}'");
            if($sum1[0]['sum']>= $flash_sale['max_sell_count']){
                if($isJump){
                     $this->redirect("/index/msg", true, array('msg' => '对不起，该商品已抢完了', 'type' => 'error'));
                     exit();
                }
            }
            $five_minutes = strtotime('-5 minutes');
            $sum2 = $model->query("select SUM(og.goods_nums) as sum from tiny_order as od left join tiny_order_goods as og on od.id = og.order_id where od.prom_id = $prom_id and od.type = 6 and UNIX_TIMESTAMP(od.create_time)>".$five_minutes);
            if($sum2[0]['sum']>= $flash_sale['max_sell_count']){
                if($isJump){
                     $this->redirect("/index/msg", true, array('msg' => '抱歉手慢了，该商品已被别人抢先下单了', 'type' => 'error'));
                     exit();
                }
            }
        }
        if($quota_num==0 || $quota_num =="" || $quota_num <0){
            return true;
        }else{
            $sum = $model->query("select SUM(og.goods_nums) as sum from tiny_order as od left join tiny_order_goods as og on od.id = og.order_id where od.prom_id = $prom_id and od.type = 6 and od.pay_status = 1 and od.status !=6 and od.user_id = $user_id");
            if($sum[0]['sum']>= $quota_num){
                if($isJump){
                    $this->redirect("/index/msg", true, array('msg' => '对不起，您已经达到了该商品的抢购上限', 'type' => 'error'));
                    exit();
                }else{
                    return false;
                }
            }
        }
        return true;       
    }
    //非普通促销确认订单
    public function order_info() {
        $id = Filter::int(Req::args('id'));
        $product_id = Req::args('pid');
        $type = Req::args("type");
        $target = Filter::int(Req::args('target'));
        $join_id = Filter::int(Req::args('join_id'));
        if(!$target) {
            $target = 0;
        }
        if(!$join_id) {
            $join_id = 0;
        }
        if ($this->checkOnline()) {
            if ($type == 'groupbuy') {
                $product_id = Filter::int($product_id);
                $model = new Model("groupbuy as gb");
                $item = $model->join("left join goods as go on gb.goods_id=go.id left join products as pr on pr.goods_id=gb.goods_id")->fields("*,pr.id as product_id,pr.store_nums")->where("gb.id=$id and pr.id=$product_id")->find();
                if ($item) {
                    $start_diff = time() - strtotime($item['start_time']);
                    $end_diff = time() - strtotime($item['end_time']);
                    if ($item['is_end'] == 0 && $start_diff >= 0 && $end_diff < 0 && $item['store_nums'] > 0) {
                        
                        if($target==1) {
                            $item['price'] = $item['sell_price']; //原价
                        }
                        $product = $this->packGroupbuyProducts($item);
                        $this->assign("product", $product);
                    } else {
                        $this->redirect("/index/groupbuy/id/$id");
                    }
                } else {
                    Tiny::Msg($this, "你提交的团购不存在！", 404);
                    exit;
                }
            } else if ($type == 'flashbuy') {
                $model = new Model("flash_sale as fb");
                $product_id = Filter::int($product_id);
                $item = $model->join("left join goods as go on fb.goods_id=go.id left join products as pr on pr.goods_id=fb.goods_id")->fields("*,pr.id as product_id,pr.store_nums")->where("fb.id=$id and pr.id=$product_id")->find();
                if ($item) {
                    $start_diff = time() - strtotime($item['start_time']);
                    $end_diff = time() - strtotime($item['end_time']);
                    if ($item['is_end'] == 0 && $start_diff >= 0 && $end_diff < 0 && $item['store_nums'] > 0) {
                        $this->flashStatus($id,$item['quota_num'],$this->user['id'],true);
                        $product = $this->packFlashbuyProducts($item);
                        $this->assign("product", $product);
                    } else {
                        $this->redirect("/index/flashbuy/id/$id");
                    }
                } else {
                    Tiny::Msg($this, "你提交的抢购不存在！", 404);
                    exit;
                }
            } else if ($type == 'bundbuy') {
                //确认捆绑存在有效且所有的商品都在其中包括个数完全正确
                $product_id = trim($product_id, "-");
                $product_id_array = explode("-", $product_id);
                foreach ($product_id_array as $key => $val) {
                    $product_id_array[$key] = Filter::int($val);
                }
                $product_ids = implode(',', $product_id_array);
                $product_id = implode('-', $product_id_array);
                $model = new Model("bundling");
                $bund = $model->where("id=$id")->find();
                if ($bund) {
                    $goods_id_array = explode(',', $bund['goods_id']);

                    $products = $model->table("goods as go")->join("left join products as pr on pr.goods_id=go.id")->where("pr.id in ($product_ids)")->fields("*,pr.id as product_id")->group("go.id")->findAll();
                    //检测库存与防偷梁换柱
                    foreach ($products as $value) {
                        if ($value['store_nums'] <= 0 || !in_array($value['goods_id'], $goods_id_array)) {
                            $this->redirect("/index/bundbuy/id/$id");
                        }
                    }
                    if (count($goods_id_array) == count($products)) {
                        $product = $this->packBundbuyProducts($products);
                        $this->assign("product", $product);
                        $this->assign("bund", $bund);
                    } else {
                        $this->redirect("/index/bundbuy/id/$id");
                    }
                    $product_id = $product_id;
                } else {
                    $this->redirect("/index/msg", true, array('msg' => '你提交的套餐不存在！', 'type' => 'error'));
                }
            }else if($type == 'pointbuy'){
                $model = new Model("point_sale as ps");
                $product_id = Filter::int($product_id);
                $item = $model->join("left join goods as go on ps.goods_id=go.id left join products as pr on pr.goods_id=ps.goods_id")->fields("*,pr.id as product_id,pr.store_nums")->where("ps.id=$id and pr.id=$product_id")->find();
                if ($item) {
                    if ($item['status'] == 1 && $item['store_nums'] > 0) {
                        //$this->flashStatus($id,$item['quota_num'],$this->user['id'],true);
                        $product = $this->packPointbuyProducts($item);
                        $this->assign("product", $product);
                    } else {
                        $this->redirect("/index/pointbuy/id/$id");
                    }
                } else {
                    Tiny::Msg($this, "你提交的积分购不存在！", 404);
                    exit;
                }
            }else if($type == 'weibuy' || $type == 'pointwei'){
                $model = new Model("pointwei_sale as ps");
                $product_id = Filter::int($product_id);
                $item = $model->join("left join goods as go on ps.goods_id=go.id left join products as pr on pr.goods_id=ps.goods_id")->fields("*,pr.id as product_id,pr.store_nums")->where("ps.id=$id and pr.id=$product_id")->find();
                if ($item) {
                    if ($item['status'] == 1 && $item['store_nums'] > 0) {
                        //$this->flashStatus($id,$item['quota_num'],$this->user['id'],true);
                        $product = $this->packPointbuyProducts($item);
                        $this->assign("product", $product);
                    } else {
                        $this->redirect("/index/weibuy/id/$id");
                    }
                } else {
                    Tiny::Msg($this, "你提交的积分购不存在！", 404);
                    exit;
                }
            }else if($type=="pointflash"){
                $model = new Model("pointflash_sale as ps");
                $product_id = Filter::int($product_id);
                $item = $model->join("left join goods as go on ps.goods_id=go.id left join products as pr on pr.goods_id=ps.goods_id")->fields("*,pr.id as product_id,pr.store_nums")->where("ps.id=$id and pr.id=$product_id")->find();
                
                $start_diff = time() - strtotime($item['start_date']);
                $end_diff = time() - strtotime($item['end_date']);
                if ($item['is_end'] == 0 && $start_diff >= 0 && $end_diff < 0 && $item['store_nums'] > 0) {
                    $this->pointflashStatus($id,$item['quota_count'],$this->user['id'],true);
                    $product = $this->packPointFlashProducts($item);
                    $this->assign("product", $product);
                } else {
                    $this->redirect("/index/pintflash/id/$id");
                }
            }

            $client_type = Chips::clientType();
            $client_type = ($client_type == "desktop") ? 0 : ($client_type == "wechat" ? 2 : 1);
            
            $model = new Model("payment as pa");
            $paytypelist = $model->fields("pa.*,pp.logo,pp.class_name")->join("left join pay_plugin as pp on pa.plugin_id = pp.id")
                            ->where("pa.status = 0 and pa.client_type = $client_type")->order("pa.sort desc")->findAll();
            
            $paytypeone = reset($paytypelist);
            $this->assign("paytypeone", $paytypeone);
            $this->assign("paytypelist", $paytypelist);
            $this->assign("id", $id);
            $this->assign("order_type", $type);
            $this->assign("target", $target);
            $this->assign("join_id", $join_id);
            $this->assign("pid", $product_id);
            $this->parserOrder();
            $this->redirect();
        } else {
            $this->noRight();
        }
    }

    //团购商品数量
    public function groupbuy_num() {
        $id = Filter::int(Req::args('id'));
        $num = Filter::int(Req::args('num'));
        $address_id = Filter::int(Req::args('address_id'));
        if ($num <= 0)
            $num = 1;
        $product_id = Filter::int(Req::args('pid'));
        $model = new Model("groupbuy as gb");
        $item = $model->join("left join goods as go on gb.goods_id=go.id left join products as pr on pr.id=$product_id")->fields("*,pr.id as product_id")->where("gb.id=$id")->find();
        $product = $this->packGroupbuyProducts($item, $num);
        $weight = $product[$product_id]['weight'] * $num;
        $fare = new Fare($weight);
        $fee = $fare->calculate($address_id);
        $product[$product_id]['freight'] = $fee;
        $product[$product_id]['totalWeight'] = $weight;
        echo JSON::encode($product);
    }

    //抢购商品数量
    public function flashbuy_num() {
        $id = Filter::int(Req::args('id'));
        $num = Filter::int(Req::args('num'));
        $address_id = Filter::int(Req::args('address_id'));
        if ($num <= 0)
            $num = 1;
        $product_id = Filter::int(Req::args('pid'));
        $model = new Model("flash_sale as fb");
        $item = $model->join("left join goods as go on fb.goods_id=go.id left join products as pr on pr.id=$product_id")->fields("*,pr.id as product_id")->where("fb.id=$id")->find();
        $product = $this->packFlashbuyProducts($item, $num);
        $weight = $product[$product_id]['weight'] * $num;
        $fare = new Fare($weight);
        $fee = $fare->calculate($address_id, $item['goods_id']);
        $product[$product_id]['freight'] = $fee;
        $product[$product_id]['totalWeight'] = $weight;
        echo JSON::encode($product);
    }

    //捆绑商品数量
    public function bundbuy_num() {
        $id = Filter::int(Req::args('id'));
        $num = Filter::int(Req::args('num'));
        $address_id = Filter::int(Req::args('address_id'));
        if ($num <= 0)
            $num = 1;
        $product_id = Req::args('pid');
        $id_arrary = explode('-', $product_id);
        foreach ($id_arrary as $key => $value) {
            $id_arrary[$key] = Filter::int($value);
        }
        $product_ids = implode(',', $id_arrary);
        $model = new Model("bundling");
        $bund = $model->where("id=$id")->find();
        if ($bund) {
            $goods_id = $bund['goods_id'];
            $products = $model->table("goods as go")->join("left join products as pr on pr.goods_id=go.id")->where("pr.id in ($product_ids)")->fields("*,pr.id as product_id")->group("go.id")->findAll();
            $products = $this->packBundbuyProducts($products);
        }
        $weight = 0;
        $max_num = $num;
        foreach ($products as $prod) {
            $weight += $prod['weight'];
            if ($max_num > $prod['store_nums'])
                $max_num = $prod['store_nums'];
        }
        $num = $max_num;
        $amount = sprintf("%01.2f", $bund['price'] * $num);
        $product[$product_id] = array('id' => $product_ids, 'goods_id' => '', 'name' => '', 'img' => '', 'num' => $num, 'store_nums' => $num, 'price' => $bund['price'], 'spec' => array(), 'amount' => $amount, 'sell_total' => $amount, 'weight' => $weight, 'point' => '', "prom_goods" => array(), "sell_price" => $bund['price'], "real_price" => $bund['price']);

        $weight = $weight * $num;
        $fare = new Fare($weight);
        $fee = $fare->calculate($address_id);
        $product[$product_id]['freight'] = $fee;
        $product[$product_id]['totalWeight'] = $weight;

        echo JSON::encode($product);
    }

    //提交订单处理
    public function order_act() {
        if ($this->checkOnline()) {
            if($this->user['id']==126935 || $this->user['id']==126676 || $this->user['id']==126663 || $this->user['id']==126243 || $this->user['id']==126002){
                $this->redirect("/index/msg", false, array('type' => 'fail', 'msg' => '账号已被冻结，请联系官方客服！'));
                exit;
            }
            $address_id = Filter::int(Req::args('address_id'));
            $payment_id = Filter::int(Req::args('payment_id'));
            $prom_id = Filter::int(Req::args('prom_id'));
            $is_invoice = Filter::int(Req::args('is_invoice'));
            $invoice_type = Filter::int(Req::args('invoice_type'));
            $invoice_title = Filter::text(Req::args('invoice_title'));
            $user_remark = Filter::txt(Req::args('user_remark'));
            $voucher_id = Filter::int(Req::args('voucher'));
            $cart_type = Req::args('cart_type');
            $target = Filter::int(Req::args('target'));
            //非普通促销信息
            $type = Req::args("type");
            $id = Filter::int(Req::args('id'));
            $product_id = Req::args('product_id');
            $buy_num = Req::args('buy_num');
            $log_id = 0;
            if (!$address_id || !$payment_id || ($is_invoice == 1 && $invoice_title == '')) {
                if (is_array($product_id)) {
                    foreach ($product_id as $key => $val) {
                        $product_id[$key] = Filter::int($val);
                    }
                    $product_id = implode('-', $product_id);
                } else
                    $product_id = Filter::int($product_id);
                $data = Req::args();
                $data['is_invoice'] = $is_invoice;
                if (!$address_id)
                    $data['msg'] = array('fail', "必需选择收货地址，才能确认订单。");
                else if (!$payment_id)
                    $data['msg'] = array('fail', "必需选择支付方式，才能确认订单。");
                else
                    $data['msg'] = array('fail', "索要发票，必需写明发票抬头。");
                if ($type == null)
                    $this->redirect("order", false, $data);
                else {
                    unset($data['act']);
                    Req::args('pid', $product_id);
                    Req::args('id', $id);
                    unset($_GET['act']);
                    Req::args('type', $type);
                    Req::args('msg', $data['msg']);
                    $this->redirect("/simple/order_info", true, Req::args());
                }
                exit;
            }
            //地址信息
            $address_model = new Model('address');
            $address = $address_model->where("id=$address_id and user_id=" . $this->user['id'])->find();
            if (!$address) {
                $data = Req::args();
                $data['msg'] = array('fail', "选择的地址信息不正确！");
                $this->redirect("order", false, $data);
                exit;
            }
            if ($this->getModule()->checkToken('order')) {
                //订单类型: 0普通订单 1团 
                $model = new Model('');
                $order_type = 0;
                //团购处理
                if ($type == "groupbuy") {
                    $product_id = Filter::int($product_id[0]);
                    $num = Filter::int($buy_num[0]);
                    if ($num < 1)
                        $num = 1;
                    $item = $model->table("groupbuy as gb")->join("left join goods as go on gb.goods_id=go.id left join products as pr on pr.id=$product_id")->fields("*,pr.id as product_id,pr.spec")->where("gb.id=$id")->find();
                    $order_products = $this->packGroupbuyProducts($item, $num);
                    $groupbuy = $model->table("groupbuy")->where("id=$id")->find();
                    // if($this->user['id']==42608){
                    //     var_dump($groupbuy);die;
                    // }
                    unset($groupbuy['description']);
                    $data['prom'] = serialize($groupbuy);
                    $data['prom_id'] = $id;
                    $order_type = 1;
                    if($target==2) {
                        //开团
                        $remain_time = strtotime($groupbuy['end_time'])-time();
                        $data = array(
                        'groupbuy_id' => $id,
                        'user_id'     => $this->user['id'],
                        'goods_id'    => $groupbuy['goods_id'],
                        'join_time'   => date('Y-m-d H:i:s'),
                        'end_time'    => date('Y-m-d H:i:s',strtotime('+1 day')),
                        'need_num'    => $groupbuy['min_num']-1,
                        'remain_time' => $remain_time,
                        'status'      => 0
                        );
                    
                       $last_id = $model->table('groupbuy_join')->data($data)->insert();
                       $log = array(
                        'join_id'     => $last_id,
                        'groupbuy_id' => $id,
                        'user_id'     => $this->user['id'],
                        'join_time'   => date('Y-m-d H:i:s')
                        );
                       $log_id = $this->model->table('groupbuy_log')->data($log)->insert();
                    }
                    if($target==3) {
                        //参团
                        $remain_time = strtotime($groupbuy['end_time'])-time();
                        $join_id = Filter::int(Req::args('join_id'));
                        if(!$join_id) {
                            $this->redirect("/index/msg", true, array('msg' => '缺少拼单人信息', 'type' => 'error'));
                            exit();
                        } else {
                            $groupbuy_join = $this->model->table('groupbuy_join')->where('id='.$join_id)->find();
                            $joined = $this->model->table('groupbuy_log')->where('join_id='.$join_id.' and user_id='.$this->user['id'].' and pay_status=1')->find();
                            if($joined) {
                                $this->redirect("/index/msg", true, array('msg' => '您已经参加过该拼团了', 'type' => 'error'));
                                exit();
                            }
                            if(time()>strtotime($groupbuy_join['end_time'])) {
                                $this->redirect("/index/msg", true, array('msg' => '您来晚了，拼图时间已结束', 'type' => 'error'));
                                exit();
                            }
                            $data = array(
                                'user_id'  => $groupbuy_join['user_id'].','.$this->user['id'],
                                'need_num' => $groupbuy_join['need_num']
                                );
                            $this->model->table('groupbuy_join')->data($data)->where('id='.$join_id)->update();
                            $log = array(
                                'join_id'     => $join_id,
                                'groupbuy_id' => $id,
                                'user_id'     => $this->user['id'],
                                'join_time'   => date('Y-m-d H:i:s')
                                );
                        }
                       $log_id = $this->model->table('groupbuy_log')->data($log)->insert();
                    }

                }else if ($type == "flashbuy") {//抢购处理
                    $product_id = Filter::int($product_id[0]);
                    $num = Filter::int($buy_num[0]);
                    if ($num < 1)
                        $num = 1;
                    $item = $model->table("flash_sale as fb")->join("left join goods as go on fb.goods_id=go.id left join products as pr on pr.id=$product_id")->fields("*,pr.id as product_id,pr.spec")->where("fb.id=$id")->find();
                    $this->flashStatus($id, $item['quota_num'], $this->user['id'],true);
                    $order_products = $this->packFlashbuyProducts($item, $num);
                    $flashbuy = $model->table("flash_sale")->where("id=$id")->find();
                    unset($flashbuy['description']);
                    $data['prom'] = serialize($flashbuy);
                    $data['prom_id'] = $id;
                    $data['point']=$item['send_point']*$num;
                    $order_type = 2;
                }else if ($type == "bundbuy") {//捆绑销售处理
                    if (is_array($product_id)) {
                        foreach ($product_id as $key => $val) {
                            $product_id[$key] = Filter::int($val);
                        }
                    } else
                        $product_id = Filter::int($product_id);

                    $product_ids = implode(',', $product_id);
                    $num = Filter::int($buy_num[0]);

                    $model = new Model("bundling");
                    $bund = $model->where("id=$id")->find();

                    if ($bund) {
                        $goods_id = $bund['goods_id'];
                        $products = $model->table("goods as go")->join("left join products as pr on pr.goods_id=go.id")->where("pr.id in ($product_ids)")->fields("*,pr.id as product_id,pr.spec")->group("go.id")->findAll();
                        $order_products = $this->packBundbuyProducts($products, $num);
                    }

                    $bundbuy = $model->table("bundling")->where("id=$id")->find();
                    unset($bundbuy['description']);
                    $data['prom'] = serialize($bundbuy);
                    $data['prom_id'] = $id;
                    $current = current($order_products);
                    $bundbuy_amount = sprintf("%01.2f", $bund['price']) * $current['num'];

                    $order_type = 3;
                }else if($type == 'pointbuy'){
                        $product_id = Filter::int($product_id[0]);
                        $num = Filter::int($buy_num[0]);
                        if ($num < 1)
                            $num = 1;
                        $item = $model->table("point_sale as ps")
                                ->join("left join goods as go on ps.goods_id=go.id left join products as pr on pr.goods_id=ps.goods_id")
                                ->fields("*,pr.id as product_id,pr.spec")
                                ->where("ps.id=$id and pr.id = $product_id")
                                ->find();
                        $pointbuy = $model->table("point_sale")->where("id=$id")->find();
                        if(empty($pointbuy)||empty($item)){
                            $this->redirect("/index/msg", true, array('msg' => '你提交的积分购不存在！', 'type' => 'error'));
                            exit();
                        }
                        $order_products = $this->packPointbuyProducts($item, $num);
                        $user_point_coin = $model->table("customer")->where("user_id=".$this->user['id'])->fields('point_coin')->find();
                        if(empty($user_point_coin)||!isset($user_point_coin['point_coin'])){
                            $this->redirect("/index/msg", true, array('msg' => '查询失败','content'=>'查询用户积分失败', 'type' => 'error'));
                            exit();
                        }else{
                            if($user_point_coin['point_coin']<$order_products[$product_id]['point']){
                                $this->redirect("/index/msg", true, array('msg' => '积分不足','content'=>'用户积分不足，无法购买', 'type' => 'error'));
                                exit();
                            }else{
                                $office_point_coin = Common::getOfficialPromoterPointCoin($this->user['id']);
                                if($user_point_coin['point_coin']-$office_point_coin<$order_products[$product_id]['point']){
                                    $this->redirect("/index/msg", true, array('msg' => '可用积分不足','content'=>'您的积分中含有不可用的返利积分', 'type' => 'error'));
                                    exit();
                                }
                            }
                        }
                        $data['pay_point'] = $order_products[$product_id]['point'];
                        $data['prom'] = serialize($pointbuy);
                        $data['prom_id'] = $id;
                        $order_type = 5;
                }else if($type == 'weibuy' || $type == 'pointwei'){
                        $product_id = Filter::int($product_id[0]);
                        $num = Filter::int($buy_num[0]);
                        if ($num < 1)
                            $num = 1;
                        $item = $model->table("pointwei_sale as ps")
                                ->join("left join goods as go on ps.goods_id=go.id left join products as pr on pr.goods_id=ps.goods_id")
                                ->fields("*,pr.id as product_id,pr.spec")
                                ->where("ps.id=$id and pr.id = $product_id")
                                ->find();
                        $weibuy = $model->table("pointwei_sale")->where("id=$id")->find();
                        if(empty($weibuy)||empty($item)){
                            $this->redirect("/index/msg", true, array('msg' => '你提交的微商购不存在！', 'type' => 'error'));
                            exit();
                        }
                        $order_products = $this->packWeibuyProducts($item, $num);
                        $user_point_coin = $model->table("customer")->where("user_id=".$this->user['id'])->fields('point_coin')->find();
                        if(empty($user_point_coin)||!isset($user_point_coin['point_coin'])){
                            $this->redirect("/index/msg", true, array('msg' => '查询失败','content'=>'查询用户积分失败', 'type' => 'error'));
                            exit();
                        }else{
                            if($user_point_coin['point_coin']<$order_products[$product_id]['point']){
                                $this->redirect("/index/msg", true, array('msg' => '积分不足','content'=>'用户积分不足，无法购买', 'type' => 'error'));
                                exit();
                            }else{
                                $office_point_coin = Common::getOfficialPromoterPointCoin($this->user['id']);
                                if($user_point_coin['point_coin']-$office_point_coin<$order_products[$product_id]['point']){
                                    $this->redirect("/index/msg", true, array('msg' => '可用积分不足','content'=>'您的积分中含有不可用的返利积分', 'type' => 'error'));
                                    exit();
                                }
                            }
                        }
                        $data['pay_point'] = $order_products[$product_id]['point'];
                        $data['prom'] = serialize($weibuy);
                        $data['prom_id'] = $id;
                        $order_type = 7;
                }else if($type=="pointflash"){
                        $product_id = Filter::int($product_id[0]);
                        $num = Filter::int($buy_num[0]);
                        if ($num < 1)
                            $num = 1;
                        $item = $model->table("pointflash_sale as ps")
                                ->join("left join goods as go on ps.goods_id=go.id left join products as pr on pr.goods_id=ps.goods_id")
                                ->fields("*,go.id as goods_id,pr.id as product_id,pr.spec")
                                ->where("ps.id=$id and pr.id = $product_id")
                                ->find();
                        $pointflash = $model->table("pointflash_sale")->where("id=$id")->find();
                        if(empty($pointflash)||empty($item)){
                            $this->redirect("/index/msg", true, array('msg' => '你提交的积分抢购不存在！', 'type' => 'error'));
                            exit();
                        }
                        $start_diff = time() - strtotime($pointflash['start_date']);
                        $end_diff = time() - strtotime($pointflash['end_date']);
                        if ($pointflash['is_end'] == 0 && $start_diff >= 0 && $end_diff < 0) {
                            $this->pointflashStatus($id,$pointflash['quota_count'],$this->user['id'],true);
                            $order_products = $this->packPointFlashProducts($item);
                            $user_point_coin = $model->table("customer")->where("user_id=".$this->user['id'])->fields('point_coin')->find();
                            if(empty($user_point_coin)||!isset($user_point_coin['point_coin'])){
                                $this->redirect("/index/msg", true, array('msg' => '查询失败','content'=>'查询用户积分失败', 'type' => 'error'));
                                exit();
                            }else{
                                if($user_point_coin['point_coin']<$order_products[$product_id]['point']){
                                    $this->redirect("/index/msg", true, array('msg' => '积分不足','content'=>'用户积分不足，无法购买', 'type' => 'error'));
                                    exit();
                                }else{
                                    $office_point_coin = Common::getOfficialPromoterPointCoin($this->user['id']);
                                    if($user_point_coin['point_coin']-$office_point_coin<$order_products[$product_id]['point']){
                                        $this->redirect("/index/msg", true, array('msg' => '可用积分不足','content'=>'您的积分中含有不可用的返利积分', 'type' => 'error'));
                                        exit();
                                    }
                                }
                            }
                            $data['pay_point'] = $order_products[$product_id]['point'];
                            $data['prom'] = serialize($pointflash);
                            $data['prom_id'] = $id;
                            $order_type = 6;
                        } else {
                            $this->redirect("/index/pintflash/id/$id");
                        }
                }
                if ($order_type == 0) {
                    if ($cart_type == 'goods') {
                        $cart = Cart::getCart('goods');
                        $order_products = $cart->all();
                    } else {
                        $cart = Cart::getCart();
                        $order_products = $this->selectcart;
                    }
                    $data['prom_id'] = $prom_id;
                }
               
                //检测products 是否还有数据
                if (empty($order_products)) {
                    $msg = array('type' => 'fail', 'msg' => '非法提交订单！');
                    $this->redirect('/index/msg', false, $msg);
                    return;
                }
                //=================限购处理==============
                foreach ($order_products as $v){
                    $buy_goods_id = $v['goods_id'];
                    $buy_goods_num = $v['num'];
                    //查询限购数量
                    $limit_info = $this->model->table("goods")->where("id=$buy_goods_id")->fields("limit_buy_num,name,type")->find();
                    if($limit_info['limit_buy_num']<=0){
                        break;
                    }
                    if($limit_info['type']==2) {
                        $msg = array('type' => 'fail', 'msg' => '该商品暂未开售，请耐心等候');
                        $this->redirect('/index/msg', false, $msg);
                        return;
                    }
                    //查询用户购买此商品的数量
                    $buyed = $this->model->table("order as o")
                            ->fields("SUM(`goods_nums`) as buyed_num")
                            ->join("order_goods as og on og.order_id = o.id")
                            ->where("o.user_id =".$this->user['id']." and o.status!=5 and o.status!=6 and o.create_time>'2017-03-09 00:00:00' and og.goods_id =$buy_goods_id")
                            ->find();
                    $buyed_num = $buyed['buyed_num']==NULL?0:$buyed['buyed_num'];
                    if($limit_info['limit_buy_num']<($buy_goods_num+$buyed_num)){
                        $this->redirect("/index/msg", false, array('type' => "info", "msg" => '商品限购',"content"=>"您超过了商品【{$limit_info['name']}】的购买限制","redirect_url"=>Url::urlFormat("/index/index"),'url_name'=>'返回商城首页'));
                        return;
                    }
                }
                //======================================

                //商品总金额,重量,积分计算
                $payable_amount = 0.00;
                $real_amount = 0.00;
                $weight = 0;
                $point = 0;
                $productarr = array();
                 //判断华点用
                $goods_arr = array();
                $product_amount=array();
                
                foreach ($order_products as $item) {
                    $payable_amount+=$item['sell_total'];
                    $real_amount+=$item['amount'];
                    if (!$item['freeshipping']) {
                        $weight += $item['weight'] * $item['num'];
                    }
                    $point += $item['point'] * $item['num'];
                    $productarr[$item['id']] = $item['num'];
                    $goods_arr[]=$item['goods_id'];
                    if(!isset($product_amount[$item['id']])){
                         $product_amount[$item['id']]=0.00;
                    }
                    $product_amount[$item['id']]+=$item['amount'];
                }
               
                if ($order_type == 3)
                    $real_amount = $bundbuy_amount;

                //计算运费
                $fare = new Fare($weight);
                $payable_freight = $fare->calculate($address_id, $productarr);
                $real_freight = $payable_freight;

                //计算订单优惠
                $prom_order = array();
                $discount_amount = 0;
                if ($order_type == 0){
                    if ($prom_id) {
                        $prom = new Prom($real_amount);
                        $prom_order = $model->table("prom_order")->where("id=$prom_id")->find();

                        //防止非法会员使用订单优惠
                        $user = $this->user;
                        $group_id = ',0,';
                        if (isset($user['group_id']))
                            $group_id = ',' . $user['group_id'] . ',';

                        if (stripos(',' . $prom_order['group'] . ',', $group_id) !== false) {
                            $prom_parse = $prom->parsePorm($prom_order);
                            $discount_amount = $prom_parse['value'];
                            if ($prom_order['type'] == 4)
                                $discount_amount = $payable_freight;
                            else if ($prom_order['type'] == 2) {
                                $multiple = intval($prom_order['expression']);
                                $multiple = $multiple == 0 ? 1 : $multiple;
                                $point = $point * $multiple;
                            }
                            $data['prom'] = serialize($prom_order);
                        } else
                            $data['prom'] = serialize(array());
                    }
                }
                //税计算
                $tax_fee = 0;
                $config = Config::getInstance();
                $config_other = $config->get('other');
                $open_invoice = isset($config_other['other_is_invoice']) ? !!$config_other['other_is_invoice'] : false;
                $tax = isset($config_other['other_tax']) ? intval($config_other['other_tax']) : 0;
                if ($open_invoice && $is_invoice) {
                    $tax_fee = $real_amount * $tax / 100;
                }

                //代金券处理
                $voucher_value = 0;
                $voucher = array();
                if ($voucher_id) {
                    $voucher = $model->table("voucher")->where("id=$voucher_id and is_send=1 and user_id=" . $this->user['id'] . " and status = 0 and '" . date("Y-m-d H:i:s") . "' <=end_time and '" . date("Y-m-d H:i:s") . "' >=start_time and money<=" . $real_amount)->find();
                    if ($voucher) {
                        $voucher_value = $voucher['value'];
                        if ($voucher_value > $real_amount)
                            $voucher_value = $real_amount;
                    }
                }
                //计算订单总金额
                $order_amount = $real_amount + $payable_freight + $tax_fee - $discount_amount - $voucher_value;
                
                //填写订单
                $data['order_no'] = Common::createOrderNo();
                $data['user_id'] = $this->user['id'];
                $data['payment'] = $payment_id;
                $data['status'] = 2;
                $data['pay_status'] = 0;
                $data['accept_name'] = Filter::text($address['accept_name']);
                $data['phone'] = $address['phone'];
                $data['mobile'] = $address['mobile'];
                $data['province'] = $address['province'];
                $data['city'] = $address['city'];
                $data['county'] = $address['county'];
                $data['addr'] = Filter::text($address['addr']);
                $data['zip'] = $address['zip'];
                $data['payable_amount'] = $payable_amount;
                $data['payable_freight'] = $payable_freight;
                $data['real_freight'] = $real_freight;
                $data['create_time'] = date('Y-m-d H:i:s');
                $data['user_remark'] = $user_remark;
                $data['is_invoice'] = $is_invoice;
                if ($is_invoice == 1) {
                    $data['invoice_title'] = $invoice_type . ':' . $invoice_title;
                } else {
                    $data['invoice_title'] = '';
                }

                $data['taxes'] = $tax_fee;


                $data['discount_amount'] = $discount_amount;

                $data['order_amount'] = $order_amount;
                $data['real_amount'] = $real_amount;

                if(!isset($data['point'])){
                    $data['point'] = $point;
                }
                $data['type'] = $order_type;
                $data['voucher_id'] = $voucher_id;
                $data['voucher'] = serialize($voucher);
                $shop_ids = array();
                foreach ($order_products as $k => $v) {
                    $shop_ids[] = $v['shop_id'];
                }
                $data['shop_ids'] = implode(',', array_filter($shop_ids));
                //==================小区推广标示====================
                $flag = Cookie::get('flag');
                if($flag!=NULL||!empty($flag)){
                    if(is_array($flag)){
                        $ids = implode(',', $flag);
                    }else{
                        $ids = $flag;
                    }
                    $data['qr_flag']=$ids;
                    Cookie::clear('flag');
                }
                //===============================================
                $data['join_id'] = $log_id;
                //写入订单数据
                $order_id = $model->table("order")->data($data)->insert();
                //扣除能使用的积分
                if(($order_type==5||$order_type==6)&&$data['pay_point']>0){
                    $model->table("customer")->data(array("point_coin"=>"`point_coin`-{$data['pay_point']}"))->where("user_id =".$this->user['id'])->update();
                    $tips = $order_type==5?"积分购下单":"积分抢购下单";
                    Log::pointcoin_log($data['pay_point'],$this->user['id'], $data['order_no'], $tips, 0);
                }
                //写入订单商品
                $tem_data = array();
                
                foreach ($order_products as $item) {
                    $tem_data['order_id'] = $order_id;
                    $tem_data['goods_id'] = $item['goods_id'];
                    $tem_data['product_id'] = $item['id'];
                    $tem_data['shop_id'] = $item['shop_id'];
                    $tem_data['goods_price'] = $item['sell_price'];
                    $tem_data['real_price'] = $item['real_price'];
                    $tem_data['goods_nums'] = $item['num'];
                    $tem_data['goods_weight'] = $item['weight'];
                    $tem_data['prom_goods'] = serialize($item['prom_goods']);
                    $tem_data['spec'] = serialize($item['spec']);
                    $model->table("order_goods")->data($tem_data)->insert();
                }
                //发送提醒
                $NoticeService = new NoticeService();
                $data['user'] = $this->user['name'];
                $NoticeService->send('create_order', $data);
                //优惠券锁死
                if (!empty($voucher)) {
                    $model->table("voucher")->where("id=$voucher_id and user_id=" . $this->user['id'])->data(array('status' => 2))->update();
                }
                //清空购物车与表单缓存
                if ($order_type == 0 || $order_type ==4) {
                    if ($cart_type == 'goods') {
                        $cart->clear();
                    } else {
                        foreach ($this->selectcart as $k => $v) {
                            $cart->delItem($v['id']);
                        }
                    }
                    Session::clear("order_status");
                }
                //0元订单自动处理
                if($order_amount<=0){
                    if(Order::updateStatus($data['order_no'])){
                        $this->redirect("/simple/order_completed/order_id/$order_id");
                        exit();
                    }else{
                        $msg = array('type' => 'fail', 'msg' => '抱歉，系统出错啦！');
                        $this->redirect('/index/msg', false, $msg);
                    }
                }
                if($order_type==4 && $data['otherpay_status']==1){
                    $this->redirect("/simple/order_completed/order_id/$order_id");
                    exit();
                }
                $payment = new Payment($payment_id);
                $payment_plugin = $payment->getPaymentPlugin();
                if ($payment_plugin->isOnlinePay()) {
                    $this->redirect("/simple/order_status/order_id/$order_id");
                } else {
                    $this->redirect("/simple/order_completed/order_id/$order_id");
                }
            } else {
                $msg = array('type' => 'fail', 'msg' => '非法提交订单！');
                $this->redirect('/index/msg', false, $msg);
            }
        } else {
            $this->noRight();
        }
    }
    public function order_status() {
        if ($this->checkOnline()) {
            $order_id = Filter::int(Req::get("order_id"));
            if ($order_id) {
                $order = $this->model->table("order as od")->join("left join payment as pa on od.payment= pa.id")->fields("od.id,od.order_no,od.payment,od.pay_status,od.order_amount,od.create_time,pa.pay_name as payname,od.type,od.status")->where("od.id=$order_id and od.status<4 and od.user_id = " . $this->user['id'])->find();
                if ($order) {
                    if ($order['pay_status'] == 0) {
                        $payment_plugin = Common::getPaymentInfo($order['payment']);
                        if ($payment_plugin != null && $payment_plugin['class_name'] == 'received' && $order['status'] == 3) {
                            $this->redirect("/simple/order_completed/order_id/$order_id");
                            exit();
                        }
                        if($order['type']==4&&$order['otherpay_status']==1){
                             $this->redirect("/simple/order_completed/order_id/$order_id");
                             exit();
                        }
                        $client_type = Chips::clientType();
                        $client_type = ($client_type == "desktop") ? 0 : ($client_type == "wechat" ? 2 : 1);
                        $model = new Model("payment as pa");
                        if($order['type']!=0){
                            $paytypelist = $model->fields("pa.*,pp.logo,pp.class_name")->join("left join pay_plugin as pp on pa.plugin_id = pp.id")
                                        ->where("pa.status = 0 and pa.plugin_id not in(12,19) and pa.client_type = $client_type")->order("pa.sort desc")->findAll();
                        }else{
                            //小区不支持使用华点
                            $flag = Cookie::get('flag');
                            if($flag==NULL||empty($flag)){
                                $plugin_ids = "12";
                            }else{
                                $plugin_ids = "12,19";
                            }
                            $paytypelist = $model->fields("pa.*,pp.logo,pp.class_name")->join("left join pay_plugin as pp on pa.plugin_id = pp.id")
                                        ->where("pa.status = 0 and pa.plugin_id not in($plugin_ids) and pa.client_type = $client_type")->order("pa.sort desc")->findAll();
                        }
                        //防止跨平台支付方式错乱
                        $in = false;
                        foreach ($paytypelist as $key => $value) {
                            if($value['id']==$order['payment']){
                                $in = true;
                                break;
                            }
                        }
                        if(!$in){
                            $order['payment'] = $paytypelist[0]['id'];
                            $order['payname'] = $paytypelist[0]['pay_name'];
                        }
                        $paytypeone = reset($paytypelist);
                        $this->assign("paytypeone", $paytypeone);
                        $this->assign("paytypelist", $paytypelist);
                        $this->assign("order", $order);
                        $this->assign("user", $this->user);
                        /*******************智付支付**********************/
                        $merchant_private_key='MIICeAIBADANBgkqhkiG9w0BAQEFAASCAmIwggJeAgEAAoGBAKwJnd8sHJojXIFxuf4Ibsdtc2cJHPlN2d/IKMBw5cuoRknNeMCTlR89MxEqfuPqYR7o1dGgOiehswR9T4vWByzhJlrLEFcgOcJFnDINzU9iZW4RcRKf187sLXYL8b5Vf5WjEfudXjnxSGt8HXPe+V0VimUVaIAQSWvBCWgHkFV/AgMBAAECgYBivF40EJAV0serrwatCk/x+xopf2x2lLy/l5Pz5pesS9aTUu7Dr6/9LtWZO4d57TFyWPUmi0v1JPOmVvkJa3vPz6HhZIzg5M4jd23Kj8fl94PaTSyGM3NEMRJDLPxWEB9ydR60VtRlieCf2lyH0JSKa5YMS09A6ks13W4SVNRqaQJBAOF22itr0KonXZaQxNIOrnGifCvBA11cKV1SMxT5iLOuYu5j2VOZNExC5oD4j1fkT/7kEq+7OSTEOhZwgcNkcGUCQQDDVmOlmKHBjUpMmv0xfc789Zj7PLoKO9WpYkDTbl7xPdc/Yb0OeeZlS123ZlplXLMVPpOQTpFcrbk9nhShaSYTAkEAhnrPsqqCMZt9VPtQikI7hof2LFrZ2OvJuGH5Gf+krBfN5ocj75sn+HzG5BJd3XzOwifjhXHUqbtpMk00+QiFiQJBAIv2JGQM3yn+ANSu4OhLSrp5h2nM80hN4yQA4I4eMS0NsGMbtwjeUzUVMUstrWufZjm8oqLtiL4tQ+Ngl0uoOb0CQQCuOR315Fwm/BW3QXjaASDwN8sahQxfNAtUyh7oGJfieKWYEjd3VYfaWXyful7FWW/Ry8H1pOSbIJZo07gLVTvA';
       
                        $merchant_public_key='MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCsCZ3fLByaI1yBcbn+CG7HbXNnCRz5TdnfyCjAcOXLqEZJzXjAk5UfPTMRKn7j6mEe6NXRoDonobMEfU+L1gcs4SZayxBXIDnCRZwyDc1PYmVuEXESn9fO7C12C/G+VX+VoxH7nV458UhrfB1z3vldFYplFWiAEElrwQloB5BVfwIDAQAB';

                        $dinpay_public_key='MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCOglLSDWk8iIHH5zFvAg9n++I4iew5Zj4M/8J8TLRj7UShJ3roroNgCkH1Iyw65xIddlCfJK8wkszpZ4OvPRiCDUBaEMENF/TQmscL2M+Ly7XEQ34RTQ1WVcpkZb7KJuiK3XIByYM0fETM1RVhQGJsnC7QpDaorjkWjpuLcR6bDwIDAQAB ';
                           
                        $merchant_code = "4000038801";
                        
                        $service_type ="direct_pay";
                       //  if($order['payment']==6){
                       //      $service_type = 'wxpub_pay';
                       //  }elseif($order['payment']==12){
                       //     $service_type ="direct_pay"; 
                       // }else{
                       //     $service_type = 'wxpub_pay';
                       // }                    

                        $interface_version ="V3.0";

                        $sign_type ="RSA-S";

                        $input_charset = "UTF-8";
                        
                        $notify_url ="http://www.ymlypt.com/payment/dinpay_callback";       
                        
                        $order_no = $order['order_no']; 

                        $order_time = $order['create_time'];    

                        $order_amount = $order['order_amount'];  

                        $product_name ="testpay";

                        $order_id = $order['id'];   
                     
                        $return_url ="http://www.ymlypt.com/ucenter/order_detail/id/{$order_id}";    
                        
                        $pay_type = "";
                        
                        $redo_flag = "";    
                        
                        $product_code = ""; 

                        $product_desc = ""; 

                        $product_num = "";

                        $show_url = ""; 

                        $client_ip ="" ;    

                        $bank_code = "";    

                        $extend_param = "";

                        $extra_return_param = "";   
                        
                        $signStr= "";
                        
                        if($bank_code != ""){
                            $signStr = $signStr."bank_code=".$bank_code."&";
                        }
                        if($client_ip != ""){
                            $signStr = $signStr."client_ip=".$client_ip."&";
                        }
                        if($extend_param != ""){
                            $signStr = $signStr."extend_param=".$extend_param."&";
                        }
                        if($extra_return_param != ""){
                            $signStr = $signStr."extra_return_param=".$extra_return_param."&";
                        }
                        
                        $signStr = $signStr."input_charset=".$input_charset."&";    
                        $signStr = $signStr."interface_version=".$interface_version."&";    
                        $signStr = $signStr."merchant_code=".$merchant_code."&";    
                        $signStr = $signStr."notify_url=".$notify_url."&";      
                        $signStr = $signStr."order_amount=".$order_amount."&";      
                        $signStr = $signStr."order_no=".$order_no."&";      
                        $signStr = $signStr."order_time=".$order_time."&";  

                        if($pay_type != ""){
                            $signStr = $signStr."pay_type=".$pay_type."&";
                        }

                        if($product_code != ""){
                            $signStr = $signStr."product_code=".$product_code."&";
                        }   
                        if($product_desc != ""){
                            $signStr = $signStr."product_desc=".$product_desc."&";
                        }
                        
                        $signStr = $signStr."product_name=".$product_name."&";

                        if($product_num != ""){
                            $signStr = $signStr."product_num=".$product_num."&";
                        }   
                        if($redo_flag != ""){
                            $signStr = $signStr."redo_flag=".$redo_flag."&";
                        }
                        if($return_url != ""){
                            $signStr = $signStr."return_url=".$return_url."&";
                        }       
                        
                        $signStr = $signStr."service_type=".$service_type;

                        if($show_url != ""){    
                            
                            $signStr = $signStr."&show_url=".$show_url;
                        }
                            
                        $merchant_private_key = "-----BEGIN PRIVATE KEY-----"."\r\n".wordwrap(trim($merchant_private_key),64,"\r\n",true)."\r\n"."-----END PRIVATE KEY-----";
                        
                        $merchant_private_key= openssl_get_privatekey($merchant_private_key);
                        
                        openssl_sign($signStr,$sign_info,$merchant_private_key,OPENSSL_ALGO_MD5);
                        
                        $sign = base64_encode($sign_info);

                          $this->assign('sign',$sign);
                          $this->assign('merchant_code',$merchant_code);
                          $this->assign('service_type',$service_type);
                          $this->assign('interface_version',$interface_version);
                          $this->assign('sign_type',$sign_type);
                          $this->assign('input_charset',$input_charset);
                          $this->assign('notify_url',$notify_url);
                          $this->assign('order_no',$order_no);
                          $this->assign('order_time',$order_time);
                          $this->assign('order_amount',$order_amount);
                          $this->assign('client_ip',$client_ip);
                          $this->assign('extend_param',$extend_param);
                          $this->assign('extra_return_param',$extra_return_param);
                          $this->assign('pay_type',$pay_type);
                          $this->assign('product_code',$product_code);
                          $this->assign('product_name',$product_name);
                          $this->assign('product_desc',$product_desc);
                          $this->assign('product_num',$product_num);
                          $this->assign('return_url',$return_url);
                          $this->assign('show_url',$show_url);
                          $this->assign('redo_flag',$redo_flag);
                          /*******************智付支付**********************/
                          $third_pay = 0;
                          $third_payment = $this->model->table('third_payment')->where('id=1')->find();
                          if($third_payment){
                             $third_pay = $third_payment['third_payment'];
                          }
                          $this->assign('third_pay',$third_pay);
                        $this->redirect();
                    } else if ($order['pay_status'] == 1) {
                        $this->redirect("/simple/order_completed/order_id/$order_id");
                    }
                } else {
                    Tiny::Msg($this, 404);
                }
            } else {
                Tiny::Msg($this, 404);
            }
        } else {
            $this->noRight();
        }
    }
    
    public function offline_order_status() {
        if ($this->checkOnline()) {
            $order_id = Filter::int(Req::get("order_id"));
            if ($order_id) {
                $order = $this->model->table("order_offline as od")->join("left join payment as pa on od.payment= pa.id")->fields("od.id,od.order_no,od.payment,od.pay_status,od.order_amount,pa.pay_name as payname,od.type,od.status,od.pay_time,od.shop_ids")->where("od.id=$order_id and od.status<4 and od.user_id = " . $this->user['id'])->find();
                if ($order) {
                    
                        $payment_plugin = Common::getPaymentInfo($order['payment']);
                        if ($payment_plugin != null && $payment_plugin['class_name'] == 'received' && $order['status'] == 3) {
                            $this->redirect("/simple/order_completed/order_id/$order_id");
                            exit();
                        }
                        
                        $client_type = Chips::clientType();
                        $client_type = ($client_type == "desktop") ? 0 : ($client_type == "wechat" ? 2 : 1);
                        $model = new Model("payment as pa");
                        
                        $paytypelist = $model->fields("pa.*,pp.logo,pp.class_name")->join("left join pay_plugin as pp on pa.plugin_id = pp.id")
                                        ->where("pa.status = 0 and pa.plugin_id not in(12,19) and pa.client_type = $client_type")->order("pa.sort desc")->findAll();
                        
                        //防止跨平台支付方式错乱
                        $in = false;
                        foreach ($paytypelist as $key => $value) {
                            if($value['id']==$order['payment']){
                                $in = true;
                                break;
                            }
                        }
                        if(!$in){
                            $order['payment'] = $paytypelist[0]['id'];
                            $order['payname'] = $paytypelist[0]['pay_name'];
                        }
                        $paytypeone = reset($paytypelist);
                        $shop = $this->model->table('customer')->fields('real_name')->where('user_id=' . $order['shop_ids'])->find();
                        if ($shop) {
                            $shopname = $shop['real_name'];
                        } else {
                            $shopname = '未知商家';
                        }
                        $this->assign('shopname', $shopname);
                        $this->assign("paytypeone", $paytypeone);
                        $this->assign("paytypelist", $paytypelist);
                        $this->assign("order", $order);
                        $this->assign("user", $this->user);
                        $this->redirect();
                    
                    //  else if ($order['pay_status'] == 1) {
                    //     $this->redirect("/ucenter/order_details/id/{$order_id}");
                    // }
                } else {
                    Tiny::Msg($this, 404);
                }
            } else {
                Tiny::Msg($this, 404);
            }
        } else {
            $this->noRight();
        }
    }

    public function order_completed() {
        if ($this->checkOnline()) {
            $order_id = Filter::int(Req::args("order_id"));
            if ($order_id) {
                $order = $this->model->table("order as od")->join("left join payment as pa on od.payment= pa.id")->fields("od.id,od.order_no,od.payment,od.pay_status,od.order_amount,pa.pay_name as payname,od.type,od.status")->where("od.id=$order_id and od.status<4 and od.user_id = " . $this->user['id'])->find();
                if ($order) {
                    if ($order['pay_status'] == 1) {
                        $this->assign("order", $order);
                        $this->redirect();
                    }else{
                        if($order['type']==4 && $order['otherpay_status']==1){
                            $this->assign("order", $order);
                            $this->redirect();
                            exit();
                        }
                        $payment_plugin = Common::getPaymentInfo($order['payment']);
                        if ($payment_plugin != null && $payment_plugin['class_name'] == 'received') {
                            $this->assign("payment_type", "received");
                            $this->assign("order", $order);
                            $this->redirect();
                        } else {
                            $this->redirect("/simple/order_status/order_id/$order_id");
                        }
                    }
                } else {
                    Tiny::Msg($this, 404);
                }
            } else {
                Tiny::Msg($this, 404);
            }
        } else {
            $this->noRight();
        }
    }

    public function get_voucher() {
        $page = Filter::int(Req::args("page"));
        $amount = Filter::int(Req::args("amount"));
        $where = "user_id = " . $this->user['id'] . " and is_send = 1";
        $where .= " and status = 0 and '" . date("Y-m-d H:i:s") . "' <=end_time and '" . date("Y-m-d H:i:s") . "' >=start_time and money<=" . $amount;
        $voucher = $this->model->table("voucher")->where($where)->order("end_time")->findPage($page, 10, 1, true);
        $data = $voucher['data'];
        $voucher['data'] = $data;
        $voucher['status'] = "success";
        echo JSON::encode($voucher);
    }

    public function reg_result() {
        $this->assign("user", $this->user);
        $this->redirect();
    }

    public function address_from_ucenter() {
        $this->address_save("/ucenter/address");
    }

    //生成二维码
    public function qrcode() {
        $url = urldecode(Req::args("data"));
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
    }

    public function logout() {
        $this->safebox->clear('user');
        $cookie = new Cookie();
        $cookie->setSafeCode(Tiny::app()->getSafeCode());
        $cookie->set('autologin', null, 0);
        $this->redirect('login');
    }

    public function wei_openid() {
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
            $weixin_openid = Cookie::get('weixin_openid');
            if (class_exists('pay_weixin') && ($weixin_openid == null || $weixin_openid == false)) {
                $pay_weixin = new pay_weixin();
                $payment = new Payment('weixin');
                $payment_info = $payment->getPayment();
                if ($payment_info['status'] == 0) {
                    $payment_weixin = $payment->getPaymentPlugin();
                    WxPayConfig::setConfig($payment_weixin->getClassConfig());
                    $tools = new JsApiPay();
                    $openId = $tools->GetOpenid();
                    Cookie::set('weixin_openid', $openId);
                }
            }
            echo ('fffffffffff' . $weixin_openid);
        }
        echo ($weixin_openid);
    }

    //检测用户是否在线
    private function checkOnline() {
        if (isset($this->user) && $this->user['name'] != null) {
//            if($this->user['mobile']==""){
//                $this->redirect('/ucenter/firstbind');
//                exit();
//            }
            $this->assign("user", $this->user);
            return true;
        } else {
            return false;
        }
    }

    public function noRight() {
        Cookie::set("url", Url::pathinfo());
        if (Common::checkInWechat()) {
            $wechat = new WechatOAuth();
            $url = $wechat->getRequestCodeURL();
            $this->redirect($url);
            exit;
        }
        // var_dump(123);die;
        $this->redirect("/simple/login");
    }

}
