<?php

class UcenterAction extends Controller {

    public $model = null;
    public $user = null;
    public $code = 1000;
    public $content = NULL;
    public $hirer = NULL;

    public function __construct() {
        $this->model = new Model();
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
            return false;
        }
    }

    //app端注册
    public function app_signup() {
        $code = Filter::sql(Req::args('mobile_code'));
        $mobile = Filter::sql(Req::args('mobile'));
        // $zone = Filter::int(Req::args('zone'));
        $zone = "86";
        $password = Filter::str(Req::args('password'));
        $inviter_id = Filter::int(Req::args('inviter_id'));
        $realname = Filter::str(Req::args('realname'));
        
        if (!Validator::mobi($mobile)) {
            $this->code = 1024;
            return;
        }
        $verify_flag = $this->sms_verify($code, $mobile, $zone);
        // $verify_flag = true;
        if ($verify_flag) {
            $user = $this->model->query("select user_id from tiny_customer where mobile = $mobile");
            //如果手机号已经注册过了
            if ($user) {
                $this->code = 1021;
                return;
            } else {
                if (strlen($password) < 6) {
                    $this->code = 1023;
                    return;
                }
                $validcode = CHash::random(8);
                $token = CHash::random(32, 'char');
                $time = date('Y-m-d H:i:s');
                $last_id = $this->model->table("user")->data(array('token' => $token, 'expire_time' => date('Y-m-d H:i:s', strtotime('+1 day')), 'nickname' => $mobile, 'password' => CHash::md5($password, $validcode), 'avatar' => 'http://www.ymlypt.com/themes/mobile/images/logo-new.png', 'validcode' => $validcode))->insert();
                //更新用户名
                if($last_id){
                    $name = "u" . sprintf("%09d", $last_id);
                    $this->model->table("user")->data(array('name' => $name))->where("id = '{$last_id}'")->update();
                    $this->model->table("customer")->data(array('mobile' => $mobile, 'real_name'=>$realname, 'mobile_verified' => 1, 'balance' => 0, 'score' => 0, 'user_id' => $last_id, 'reg_time' => $time, 'login_time' => $time))->insert();
                    if($inviter_id){
                        Common::buildInviteShip($inviter_id, $last_id, 'wechat');
                    }
                    Common::sendPointCoinToNewComsumer($last_id);
                    $this->code = 0;
                    $this->content['user_id'] = $last_id;
                    $this->content['token'] = $token;
                } else {
                    $this->code = 1005;
                    return;
                }
            }
        } else {
            $this->code = 1025;
        }
    }
    //用户登录
    public function login() {
        $account = Filter::sql(Req::post('account'));
        $passWord = Req::post('password');
        $model = $this->model->table("customer as cu");
        $obj = $model->join("left join user as us on us.id = cu.user_id")->fields("us.*,cu.group_id,cu.user_id,cu.login_time,cu.mobile")->where("cu.mobile='$account' and cu.status=1")->find();
        if ($obj) {
            if ($obj['status'] == 1) {
                if ($obj['password'] == CHash::md5($passWord, $obj['validcode'])) {
                    $token = CHash::random(32, 'char');
                    $url = 'http://api.cn.ronghub.com/user/getToken.json';
                    /********************获取融云token**********************/
                    if($obj['rongyun_token']==''){
                        $rongyun_token = $this->rongyun_token($obj['id']);
                        if($rongyun_token){
                            $this->model->table("user")->data(array('rongyun_token' => $rongyun_token))->where('id=' . $obj['id'])->update();
                        }
                    }
                    /********************获取融云token**********************/
                    $this->model->table("customer")->data(array('login_time' => date('Y-m-d H:i:s')))->where('user_id=' . $obj['id'])->update();
                    $this->model->table("user")->data(array('token' => $token, 'expire_time' => date('Y-m-d H:i:s', strtotime('+7 days'))))->where('id=' . $obj['id'])->update();
                    //淘宝客分配广告位和用户id
                    if($obj['adzoneid']==null) {
                        $taobao_pid = $this->model->table('taoke_pid')->where('user_id is NULL')->order('id desc')->find();
                        if($taobao_pid) {
                            $this->model->table('taoke_pid')->data(['user_id'=>$obj['id']])->where('id='.$taobao_pid['id'])->update();
                            $this->model->table('user')->data(['adzoneid'=>$taobao_pid['adzoneid']])->where('id='.$obj['id'])->update();
                        }
                    }        
                    $this->code = 0;
                    $this->content = array(
                        'user_id' => $obj['id'],
                        'token' => $token
                    );
                } else {
                    $this->code = 1016;
                    return;
                }
            } else if ($obj['status'] == 2) {
                $this->code = 1017;
                return;
            } else {
                $this->code = 1018;
                return;
            }
        } else {
            $this->code = 1019;
        }
    }

    //读取个人信息
    public function info() {
        $promoter = $this->model->table('district_promoter')->where('user_id='.$this->user['id'])->find();
        $shop = $this->model->table('district_shop')->where('owner_id='.$this->user['id'])->find();
        $customer = $this->model->table('customer')->fields('realname_verified,is_cashier,pay_password')->where('user_id='.$this->user['id'])->find();
        if($promoter || $shop){
            $is_business = 1;
        }else{
            $is_business = 0;
        }
        $sign = $this->model->table('sign_in')->where("user_id=".$this->user['id']." and date='".date('Y-m-d')."'")->find();
        if($this->user['rongyun_token']==null){
            $this->user['rongyun_token'] = '';
        }
        if($this->user['avatar']=='') {
           $this->user['avatar']=='http://www.ymlypt.com/themes/mobile/images/logo-new.png'; 
        }
        $this->code = 0;
        $this->content['userinfo'] = $this->user;
        $this->content['userinfo']['is_business'] = $is_business;
        $this->content['userinfo']['verified'] = $customer['realname_verified'];
        $this->content['userinfo']['is_signed'] = $sign?1:0;
        $this->content['userinfo']['is_cashier'] = $customer['is_cashier'];
        $this->content['userinfo']['pay_password_open'] = $customer['pay_password']==null?0:1;
    }

    //设置昵称
    public function set_nickname() {
        $new_name = Filter::str(Req::args('new'));
        if (strlen($new_name) > 20) {
            $this->code = 1000;
            return;
        }
        $result = $this->model->query("update tiny_user set nickname = '$new_name' where id = " . $this->user['id']);
        if ($result) {
            $this->code = 0;
        }
    }

    //保存个人资料
    public function save_info() {
        $rules = array('name:required:昵称不能为空!', 'real_name:required:真实姓名不能为空!',
            'sex:int:性别必需选择！', 'birthday:date:生日日期格式不正确！',
            'province:[1-9]\d*:选择地区必需完成', 'city:[1-9]\d*:选择地区必需完成',
            'county:[1-9]\d*:选择地区必需完成');
        $info = Validator::check($rules);
        if (is_array($info)) {
            $this->code = 1000;
            return;
        } else {
            $data = array(
                'name' => Filter::txt(Req::args('name')),
                'real_name' => Filter::text(Req::args('real_name')),
                'sex' => Filter::int(Req::args('sex')),
                'birthday' => Filter::sql(Req::args('birthday')),
                'phone' => Filter::sql(Req::args('phone')),
                'province' => Filter::int(Req::args('province')),
                'city' => Filter::int(Req::args('city')),
                'county' => Filter::int(Req::args('county')),
                'addr' => Filter::text(Req::args('addr'))
            );

            //如果用户之前没有绑定过手机号码，则执行这一步
            if ($this->user['mobile'] == '') {
                $mobile = Filter::int(Req::args('mobile'));
                $obj = $this->model->table("customer")->where("mobile='$mobile'")->find();
                $data['mobile'] = $mobile;
                if ($obj) {
                    $this->code = 1044;
                    return;
                }
            }
            if ($this->user['email'] == $this->user['mobile'] . '@no.com') {
                $email = Req::args('email');
                if (Validator::email($email)) {
                    $userData['email'] = $email;
                    $obj = $this->model->table("user")->where("email='$email'")->find();
                    if ($obj) {
                        $this->code = 1045;
                        return;
                    }
                }
            }

            $userData['name'] = Filter::sql(Req::args("name"));
            $id = $this->user['id'];
            $this->model->table("user")->data($userData)->where("id=$id")->update();

            $this->model->table("customer")->data($data)->where("user_id=$id")->update();
            $obj = $this->model->table("user as us")->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.group_id,cu.login_time,cu.mobile")->where("us.id=$id")->find();
            //$this->safebox->set('user', $obj, $this->cookie_time);
            $this->code = 0;
        }
    }
    
    //退出登录
    public function logout() {
        Session::clearAll();
        $result = $this->model->table("user")->data(array('token' => '', 'expire_time' => ''))->where('id=' . $this->user['id'])->update();
        if ($result) {
            $this->code = 0;
        } else {
            $this->code = 1005;
        }
    }

    //通过旧密码重置登录密码
    public function reset_loginpwd() {

        $oldpassword = Filter::str(Req::args('oldpassword'));
        $newpassword1 = Filter::str(Req::args('newpassword1'));
        $newpassword2 = Filter::str(Req::args('newpassword2'));
        $user = $this->model->table('user')->where("id = ".$this->user['id'])->fields("password,validcode")->find();
        $validcode = $user['validcode'];
        if ($user['password'] != CHash::md5($oldpassword, $validcode)) {
            $this->code = 1016;
            return;
        } else if ($newpassword1 != $newpassword2) {
            $this->code = 1020;
            return;
        }
        if (strlen($newpassword1) < 6) {
            $this->code = 1023;
            return;
        }
        if ($oldpassword == $newpassword1) {
            $this->code = 1081;
            return;
        }
        $validcode = CHash::random(8);
        $this->model->table('user')->data(array('password' => CHash::md5($newpassword1, $validcode), 'validcode' => $validcode))->where('id=' . $this->user['id'])->update();
        $this->code = 0;
    }

    //通过手机验证修改登录密码
    public function forget_loginpwd() {
        $code = Filter::int(Req::args("code"));
        $mobile = Filter::int(Req::args("mobile"));
        $newpassword = Filter::str(Req::args("newpassword"));
        $zone = Filter::int(Req::args('zone'));

        //验证情况标识
        $pass = $this->sms_verify($code, $mobile, $zone);
        if ($pass) {
            $user = $this->model->query("select user_id from tiny_customer where mobile = $mobile and status=1");
            if (!$user) {
                $this->code = 1030;
                return;
            }
            if (strlen($newpassword) >= 6) {
                $validcode = CHash::random(8);
                $this->model->table('user')->data(array('password' => CHash::md5($newpassword, $validcode), 'validcode' => $validcode))->where('id=' . $user[0]['user_id'])->update();
                $this->code = 0;
            } else {
                $this->code = 1023;
            }
        } else {
            $this->code = 1026;
        }
    }
    
    //第三方登录    
    public function thirdlogin() {
        $platform = Filter::sql(Req::args('platform'));
        $openid = Filter::sql(Req::args('openid'));
        $token = Filter::sql(Req::args('token'));

        if ($platform && $openid && $token) {
            $model = $this->model->table("oauth_user as ou");
            // $obj = $model->join("left join user as us on us.id = ou.user_id")->fields("ou.*,us.adzoneid")->where("ou.oauth_type='$platform' and ou.open_id='{$openid}'")->find();
            $obj = $this->model->table('oauth_user')->where("oauth_type='{$platform}' and open_id='{$openid}' or unionid = '{$openid}'")->find();
            if ($obj) {
                $token = CHash::random(32, 'char');
                $this->model->table("customer")->data(array('login_time' => date('Y-m-d H:i:s')))->where('user_id=' . $obj['user_id'])->update();
                $this->model->table("user")->data(array('token' => $token, 'expire_time' => date('Y-m-d H:i:s', strtotime('+1 day'))))->where('id=' . $obj['user_id'])->update();
                $objs = $this->model->table('user')->where('id='.$obj['user_id'])->find();
                if($objs['adzoneid']==null) {
                        $taobao_pid = $this->model->table('taoke_pid')->where('user_id is NULL')->order('id desc')->find();
                        if($taobao_pid) {
                            $this->model->table('taoke_pid')->data(['user_id'=>$obj['user_id']])->where('id='.$taobao_pid['id'])->update();
                            $this->model->table('user')->data(['adzoneid'=>$taobao_pid['adzoneid']])->where('id='.$obj['user_id'])->update();
                        }
                    }
                $this->code = 0;
                $this->content = array(
                    'user_id' => $obj['user_id'],
                    'token' => $token
                );
            } else {
                $this->code = 0;
            }
        } else {
            $this->code = 1000;
        }
    }

    //第三方登录绑定
    public function thirdbind() {
        $platform = Filter::sql(Req::args('platform'));
        $openid = Filter::sql(Req::args('openid'));
        $token = Filter::sql(Req::args('token'));
        $mobile = Filter::str(Req::args('mobile'));
        $code = Filter::int(Req::args('code'));
        $zone = Filter::int(Req::args('zone'));
        $passWord = Filter::sql(Req::args('password'));

         if (!Validator::mobi($mobile)) {
            $this->code = 1024;
            return;
        }
        
        $nickname = Filter::sql(Req::args('nickname'));
        $head = Filter::str(Req::args('head'));
        
        $inviter_id = Filter::int(Req::args("inviter_id"));
        $oauthuser = $this->model->table('oauth_user')->where("oauth_type='{$platform}' and open_id='{$openid}' or unionid = '{$openid}'")->find();
        if ($oauthuser && $oauthuser['user_id']) {
            $this->code = 1059;
            return;
        } else {
            //如果已经绑定其它的账号
            $ext = $this->model->table("customer")->where("mobile='{$mobile}'")->find();
            if ($ext) {
                $last_id = $ext['user_id'];
                $cus = $this->model->table("oauth_user")->where("oauth_type='{$platform}' and user_id ='{$last_id}'")->find();
                if ($cus) {
                    $this->code = 1059;
                    return;
                }
            }
            //如果没有账号则需要新创建一个
            if (!$ext) {
                $email = $mobile . "@no.com";
                $time = date('Y-m-d H:i:s');
                $validcode = CHash::random(8);
                $model = $this->model->table("user");
                $last_id = $model->data(array('email' => $email, 'nickname' => $nickname, 'password' => CHash::md5($passWord, $validcode), 'avatar' => $head, 'validcode' => $validcode))->insert();
                if($last_id){   
                    $name = "u" . sprintf("%09d", $last_id);
                    //更新用户名和邮箱
                    $model->table("user")->data(array('name' => $name))->where("id = '{$last_id}'")->update();
                    $model->table("customer")->data(array('mobile' => $mobile, 'mobile_verified' => 1, 'balance' => 0, 'user_id' => $last_id, 'reg_time' => $time, 'login_time' => $time))->insert();
                    if($inviter_id){
                        Common::buildInviteShip($inviter_id, $last_id, $platform);
                    }
                }else{
                    $this->code= 1005;
                    return;
                }
            }
            // $token = CHash::random(32, 'char');
            $this->model->table("customer")->data(array('login_time' => date('Y-m-d H:i:s')))->where('user_id=' . $last_id)->update();
            $this->model->table("user")->data(array('token' => $token, 'expire_time' => date('Y-m-d H:i:s', strtotime('+1 day'))))->where('id=' . $last_id)->update();
            $openname = $nickname?$nickname:$mobile;
            $this->model->table("oauth_user")->data(array(
                'open_name' => $openname,
                'oauth_type' => $platform,
                'user_id' => $last_id,
                'posttime' => time(),
                'token' => $token,
                'expires' => 7200,
                'open_id' => $openid,
                'unionid' => $openid
            ))->insert();
            
            $this->code = 0;
            $this->content = array(
                'user_id' => $last_id,
                'token' => $token
            );
        }
    }

    //发送短信
    public function send_sms() {
        $mobile = Filter::sql(Req::args('mobile'));
        if (!Validator::mobi($mobile)) {
            $this->code = 1000;
            return;
        }
        $model = new Model('mobile_code');
        $time = time() - 120;
        $obj = $model->where("send_time < $time")->delete();
        $obj = $model->where("mobile='" . $mobile . "'")->find();
        if ($obj) {
            $this->code = 1036;
            return;
        }
        $sms = SMS::getInstance();
        if (!$sms->getStatus()) {
            $this->code = 1035;
            return;
        }
        $code = CHash::random('6', 'int');
        $result = $sms->sendCode($mobile, $code);
        if ($result['status'] != 'success') {
            $this->code = 1032;
            return;
        }
        $model->data(array('mobile' => $mobile, 'code' => $code, 'send_time' => time()))->insert();
        $this->code = 0;
    }

    //发送验证码
    public function send_code() {
        $type = Req::args('type');
        $code = CHash::random(6, 'int');
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
        $sendAble = true;
        if ($sendAble) {
            $random = CHash::random(20, 'char');
            $verifiedInfo = array('code' => $code, 'time' => time(), 'type' => $type, 'random' => $random);
            if ($type == 'email') {
                $mail = new Mail();
                $flag = $mail->send_email($this->user['email'], '您的验证身份验证码', "身份验证码：" . $code);
                if (!$flag) {
                    $this->code = 1034;
                    return;
                } else {
                    Session::set('verifiedInfo', $verifiedInfo);
                    $this->code = 0;
                }
            } else if ($type == 'mobile') {
                // var_dump($this->user['mobile']);die;
                $sms = SMS::getInstance();
                if ($sms->getStatus()) {
                    $result = $sms->sendCode($this->user['mobile'], $code);
                    if ($result['status'] == 'success') {
                        Session::set('verifiedInfo', $verifiedInfo);
                        $this->code = 0;
                    } else {
                        $this->code = 1032;
                        return;
                    }
                } else {
                    $this->code = 1035;
                    return;
                }
            } else {
                $this->code = 1000;
                return;
            }
        } else {
            $this->code = 1036;
        }
    }

    //发送复核验证码
    public function send_objcode() {
        $type = Req::args('type');
        $code = CHash::random(6, 'int');
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
            $activateObj = array('time' => time(), 'code' => $code, 'obj' => $type);
            if ($type == 'email') {
                $mail = new Mail();
                $flag = $mail->send_email($this->user['email'], '您的验证身份验证码', "身份验证码：" . $code);
                if (!$flag) {
                    $this->code = 1034;
                    return;
                } else {
                    Session::set('activateObj', $activateObj);
                    $this->code = 0;
                }
            } else if ($type == 'mobile') {
                $sms = SMS::getInstance();
                if ($sms->getStatus()) {
                    $result = $sms->sendCode($this->user['mobile'], $code);
                    if ($result['status'] == 'success') {
                        Session::set('activateObj', $activateObj);
                        $this->code = 0;
                    } else {
                        $this->code = 1032;
                        return;
                    }
                } else {
                    $this->code = 1035;
                    return;
                }
            } else {
                $this->code = 1000;
                return;
            }
        } else {
            $this->code = 1036;
        }
    }

    //订单查询   
    //status:1.all 查询所有订单 2.unpay 查询未支付订单 3.undelivery 查询未发货订单 4. unreceived 查询未收货订单
    //page： 分页页码（默认一页十条）
    public function order() {
        $status = Filter::str(Req::args("status"));
        $page = Filter::int(Req::args("page"));
        $page = (int) $page;
        $config = Config::getInstance();
        $config_other = $config->get('other');
        $valid_time = array();
        $valid_time[0] = isset($config_other['other_order_delay']) ? intval($config_other['other_order_delay']) : 0;
        $valid_time[1] = isset($config_other['other_order_delay_group']) ? intval($config_other['other_order_delay_group']) : 120;
        $valid_time[2] = isset($config_other['other_order_delay_flash']) ? intval($config_other['other_order_delay_flash']) : 120;
        $valid_time[3] = isset($config_other['other_order_delay_bund']) ? intval($config_other['other_order_delay_bund']) : 0;
        $valid_time[5] = isset($config_other['other_order_delay_point']) ? intval($config_other['other_order_delay_point']) : 0;
        $valid_time[6] = isset($config_other['other_order_delay_pointflash']) ? intval($config_other['other_order_delay_pointflash']) : 0;

        $where = array("user_id = " . $this->user['id'], 'is_del = 0');
        switch ($status) {
            case "all":
                break;
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
                break;   
            default:
                return;
                break;
        }
        if ($where) {
            $where = implode(' AND ', $where);
        }
        //计算记录数
        $count = $this->model->query("select count(id) as count from tiny_order where $where ");
        //计算分页数
        if ($count[0]['count'] == 0) {
            $this->code = 0;
            $this->content['order'] = null;
            $this->content['page_info'] = array('page_count' => 0, 'page_current' => 0);
            return;
        }
        $page_count = ceil($count[0]['count'] / 10);
        //分页数溢出时
        if ($page_count < $page) {
            $this->code = 0;
            $this->content = null;
            return;
        }
        //计算偏移量
        $offset = ($page - 1) * 10;
        $orders = $this->model->query("select * from tiny_order where $where order by id desc limit $offset,10");
        //$orders['page_count'] = $page_count;
        $order_id = array();
        $now = time();
        $ids = array();
        foreach ($orders as $k => $order) {
            if ($order['pay_status'] == 0 && $order['status'] <= 3) {
                if (isset($valid_time[$order['type']])) {
                    $time = $valid_time[$order['type']] * 60;
                    if ($time && $now - strtotime($order['create_time']) >= $time) {
                        $order_id[] = $order['id'];
                    }
                }
            }
            $orders[$k]['shop_huabi_account'] = "wlucky2101";
            $ids[] = $order['id'];
        }
        $imglist = array();
        if ($ids) {
            $goodslist = $this->model->table("order_goods AS og")->fields("og.id,og.product_id,og.order_id,og.goods_id,og.goods_nums,og.goods_price,og.spec,og.support_status,go.img,go.imgs,go.name")->join("goods AS go ON og.goods_id=go.id")->where("order_id IN (" . implode(',', $ids) . ")")->findAll();
            foreach ($goodslist as $k => $v) {
                $v['spec'] = unserialize($v['spec']);
                $imglist[$v['order_id']][] = $v;
            }
        }
        foreach ($orders as $k => &$v) {
            $v['imglist'] = isset($imglist[$v['id']]) ? $imglist[$v['id']] : array();
        }
        unset($v);
        //处理过期订单状态  
        if (count($order_id) > 0) {
            $ids = implode(',', $order_id);
            $order_model = new Model('order');
            $data = array("status" => 6);
            $order_model->where("id in (" . $ids . ")")->data($data)->update();
            $point_order = $order_model->where("id in (" . $ids . ") and type in (5,6)")->findAll();
            if($point_order){
                foreach ($point_order as $v){
                    if($v['pay_point']>0){
                        $this->model->table("customer")->where("user_id=" . $v['user_id'])->data(array("point_coin" => "`point_coin`+" . $v['pay_point']))->update();
                        Log::pointcoin_log($v['pay_point'], $v['user_id'], $v['order_no'], "取消订单，退回积分", 2);
                    }
                }
            }
        }
        $this->code = 0;
        $this->content['order'] = $orders;
        $this->content['page_info'] = array('page_count' => $page_count, 'page_current' => $page);
    }

    //查询单个订单的详细
    public function order_detail() {
        $id = Filter::int(Req::args("id"));
        $order = $this->model->table("order as od")->fields("od.*,pa.pay_name")->join("left join payment as pa on od.payment = pa.id")->where("od.id = $id and od.user_id=" . $this->user['id'])->find();
        if ($order) {
            //发票
            $invoice = $this->model->table("doc_invoice as di")->fields("di.*,ec.code as ec_code,ec.name as ec_name,ec.alias as ec_alias")->join("left join express_company as ec on di.express_company_id = ec.id")->where("di.order_id=" . $id)->find();
            $order_goods = $this->model->table("order_goods as og ")->join("left join goods as go on og.goods_id = go.id left join products as pr on og.product_id = pr.id")->where("og.order_id=" . $id)->findAll();
            foreach ($order_goods as $k => $v) {
                unset($order_goods[$k]['content']);
                $order_goods[$k]['specs_value'] = array_values(unserialize($v['specs']));
                if($order_goods[$k]['specs_value']!=null && is_array($order_goods[$k]['specs_value'])) {
                    foreach ($order_goods[$k]['specs_value'] as $key => &$value) {
                        $value['value'] = array_values($value['value']);
                    }
                }
            }
            $area_ids = $order['province'] . ',' . $order['city'] . ',' . $order['county'];
            if ($area_ids != '')
                $areas = $this->model->table("area")->where("id in ($area_ids)")->findAll();
            $parse_area = array();
            foreach ($areas as $area) {
                $parse_area[$area['id']] = $area['name'];
            }
            $content['parse_area'] = $parse_area;
            $content['area'] = array_values($parse_area);
            $content['order_goods'] = $order_goods;
            $content['invoice'] = $invoice;
            $content['order'] = $order;

            $this->code = 0;
            $this->content = $content;
        } else {
            $this->code = 1000;
        }
    }

    //查询单个订单的详细
    public function order_express_detail() {
        $id = Filter::int(Req::args("id"));
        $order_info = $this->model->table('order')->where("id =$id")->fields('order_amount,pay_time,real_freight,status')->find();
        $goods_info = $this->model->table('order_goods as og')->join('left join goods as g on og.goods_id = g.id left join express_company as ec on og.express_company_id = ec.id')
                        ->fields('og.id,og.shop_id,og.goods_nums,og.goods_price,og.real_price,og.spec,og.express_no,og.express_time,og.support_status,g.name,g.img,ec.name as express_name,ec.alias')
                        ->where("og.order_id=$id")->findAll();
        $package = array();
        foreach ($goods_info as $k => $v) {
            $id = $v['shop_id'];
            $package[$id]['express_info']['shop_id'] = $id;
            $package[$id]['express_info']['express_name'] = $v['express_name'];
            $package[$id]['express_info']['express_time'] = $v['express_time'];
            $package[$id]['express_info']['alias'] = $v['alias'];
            $package[$id]['express_info']['express_no'] = $v['express_no'];
            unset($v['express_name']);
            unset($v['express_time']);
            unset($v['alias']);
            unset($v['shop_id']);
            unset($v['express_no']);
            $package[$id]['goods'][] = $v;
        }
        $order_info['package'] = array_values($package);

        $this->code = 0;
        $this->content['order'] = $order_info;
    }

    //订单签收
    public function order_sign() {
        $id = Filter::int(Req::args("id"));
        $flag = $this->model->table('order')->where("id=$id and user_id=" . $this->user['id'] . " and status=4 ")->find();
        //$flag = $this->model->query("select * from tiny_order where id = $id and user_id=".$this->user['id']." and status = 4");
        if (!empty($flag)) {
            $this->code = 1043;
            return;
        } else {
            $result = $this->model->table('order')->where("id=$id and user_id=" . $this->user['id'] . " and status=3 and pay_status=1 and delivery_status=1")->data(array('delivery_status' => 2, 'status' => 4, 'completion_time' => date('Y-m-d H:i:s')))->update();
            if ($result) {
                //提取购买商品信息
                $products = $this->model->table('order as od')->join('left join order_goods as og on od.id=og.order_id')->where('od.id=' . $id)->findAll();
                foreach ($products as $product) {
                    $data = array('goods_id' => $product['goods_id'], 'user_id' => $this->user['id'], 'order_no' => $product['order_no'], 'buy_time' => $product['create_time']);
                    //订单签收之后生产评论
                    $this->model->table('review')->data($data)->insert();
                }
                $this->code = 0;
            } else {
                $this->code = 1042;
            }
        }
    }

    //我的评论
    public function my_review() {
        $status = Filter::str(Req::args('status'));
        $page = (int) Filter::int(Req::args('page'));

        $where = array("r.user_id = " . $this->user['id'], "o.order_no is not null");
        switch ($status) {
            case "unreview":
                $where[] = "r.status = '0'";
                break;
            case "reviewed":
                $where[] = "r.status = '1'";
                break;
            case "all":
                break;
            default :
                return;
        }
        if ($where) {
            $where = implode(' AND ', $where);
        }
        $count = $this->model->table("review as r")->join("left join order as o on o.order_no = r.order_no")->fields("count(r.id) as count")->where($where)->find();
        $page_count = ceil($count['count'] / 10);
        //分页溢出
        if ($page_count < $page) {
            $this->code = 0;
            $this->content = null;
            return;
        }
        $offset = ($page - 1) * 10;
        $order_no = $this->model->table("review as r")->join("order as o on o.order_no = r.order_no")->fields('r.order_no')->where($where)->order("r.id desc")->findAll();
        $all = array();
        foreach ($order_no as $k => $v) {
            $order['order_no'] = $v['order_no'];
            $total = $this->model->query("select order_amount from tiny_order where order_no =" . $v['order_no']);

            $order['order_amount'] = $total[0]['order_amount'];
            $one = $this->model->query("select re.id,re.goods_id,re.content,re.point,re.status,re.buy_time,re.comment_time,go.name,go.img as img,go.sell_price from tiny_review as re left join tiny_goods as go on re.goods_id = go.id where order_no = " . $v['order_no']);
            foreach ($one as $kk => $vv) {
                $goods_num = $this->model->query("select og.goods_nums from tiny_order_goods as og right join (select id from tiny_order where order_no = '" . $v['order_no'] . "') as o on og.order_id = o.id where og.goods_id =" . $vv['goods_id']);
                $one[$kk]['goods_num'] = $goods_num[0]['goods_nums'];
            }
            $order['list'] = $one;
            $all[] = $order;
        }
        $this->code = 0;
        $this->content['review'] = $all;
        $this->content['page_info'] = array('page_count' => $page_count, 'page_current' => $page);
    }

    //发表评论接口
    public function post_review() {
        $id = Filter::int(Req::args('id'));
        $goods_id = Filter::int(Req::args('goods_id'));
        $point = Filter::int(Req::args('point'));
        $content = Filter::txt(Req::args('content'));
        $content = TString::nl2br($content);
        if ($point > 5)
            $point = 5;
        if ($point < 1)
            $point = 1;
        $result = $this->model->table('review')->data(array('point' => $point, 'content' => $content, "status" => 1, 'comment_time' => date("Y-m-d")))->where("status =0 and id = $id and user_id = " . $this->user['id'])->update();
        if ($result) {
            $satisfaction = $this->model->table('review')->where("status =1 and goods_id = $goods_id and point >3")->count();
            $all_review_count = $this->model->table('review')->where("status =1 and goods_id = $goods_id")->count();
            if ($all_review_count > 0) {
                $rate = round($satisfaction / $all_review_count, 2);
                if ($rate > 1) {
                    $rate = 1.00;
                }
            }
            $this->model->table('goods')->where("id = $goods_id")->data(array("review_count" => $all_review_count, "satisfaction_rate" => $rate))->where("id = $goods_id")->update();
        } else {
            $this->code = 1005;
            return;
        }
        $this->code = 0;
    }

    //获取我的消息接口
    public function get_message() {
        $type = Req::args('type');
        $status = Req::args("status");
        $page = Req::args('page');

        $customer = $this->model->table("customer")->where("user_id=" . $this->user['id'])->find();

        $message_ids = null;
        if ($customer) {
            $str = ',' . $customer['message_ids'] . ',';
            switch ($status) {
                case 'readed':
                    preg_match_all('/,-(\d+)/', $str, $message_ids);
                    break;
                case 'unread':
                    preg_match_all('/,(\d+)/', $str, $message_ids);
                    break;
                case 'all':
                    preg_match_all('/,-?(\d+)/', $str, $message_ids);
                    break;
                default:
                    break;
            }
            if (!empty($message_ids)) {
                $ids = implode(',', $message_ids[1]);
            } else {
                $ids = '';
            }
        }

        if ($ids != '') {
            $offset = $page > 0 ? ($page - 1) * 10 : 0;
            if ($type == 'system') {
                //$message = $this->model->table("message")->where("id in ($ids) and only = 0")->order("id desc")->findAll();
                $message = $this->model->query("select * from tiny_message where id in ($ids) and only = 0 limit $offset,10");
            } else if ($type == 'only') {
                //$message = $this->model->table("message")->where("id in ($ids) and only =".$this->user['id'])->order("id desc")->findAll();
                $message = $this->model->query("select * from tiny_message where id in ($ids) and only =" . $this->user['id'] . " limit $offset,10");
            } else if ($type == 'all') {
                //$message = $this->model->table("message")->where("id in ($ids) ")->order("id desc")->findAll();
                $message = $this->model->query("select * from tiny_message where id in ($ids) limit $offset,10");
            } else {
                return;
            }
            $this->code = 0;
            $this->content = $message;
        } else {
            $this->code = 0;
            $this->content = null;
        }
    }

    public function push_message() {
        $list = $this->model->table("push_message as p")->fields('p.to_id,p.type,p.content,p.create_time,p.value,c.status')->join('left join cashier as c on p.value=c.id')->where("to_id=" . $this->user['id'])->findAll();
        $this->code = 0;
        $this->content = $list;
    }

    //将消息标为已读
    public function read_message() {
        $id = Filter::int(Req::args("id"));
        $customer = $this->model->table("customer")->where("user_id=" . $this->user['id'])->find();
        $message_ids = ',' . $customer['message_ids'] . ',';
        $message_ids = str_replace(",$id,", ',-' . $id . ',', $message_ids);
        $message_ids = trim($message_ids, ',');
        $result = $this->model->table("customer")->where("user_id=" . $this->user['id'])->data(array('message_ids' => $message_ids))->update();
        if ($result) {
            $this->code = 0;
        }
    }

    //签收全部消息
    public function sign_all_message() {
        $customer = $this->model->table("customer")->where("user_id=" . $this->user['id'])->find();
        $str = ',' . $customer['message_ids'] . ',';
        $signed = preg_replace('/,(\d+)/', ',-$1', $str);
        $signed = trim($signed, ',');
        $result = $this->model->table("customer")->where("user_id=" . $this->user['id'])->data(array('message_ids' => $signed))->update();
        $this->code = 0;
    }

    //删除消息接口
    public function del_message() {
        $id = Filter::int(Req::args("id"));
        $customer = $this->model->table("customer")->where("user_id=" . $this->user['id'])->find();
        $message_ids = ',' . $customer['message_ids'] . ',';
        $message_ids = str_replace(",-$id,", ',', $message_ids);
        $message_ids = rtrim($message_ids, ',');
        $result = $this->model->table("customer")->where("user_id=" . $this->user['id'])->data(array('message_ids' => $message_ids))->update();
        if ($result) {
            $this->code = 0;
        }
    }

    //钱袋页
    public function huabi() {
        $id = $this->user['id'];
        $customer = $this->model->table("customer as cu")->fields("cu.*,gr.name as gname")->join("left join grade as gr on cu.group_id = gr.id")->where("cu.user_id = $id")->find();
        $orders = $this->model->table("order as o")->join("payment as p on o.payment = p.id ")->where("o.user_id = $id and p.plugin_id in(1,20)")->findAll();
        $order = array('amount' => 0, 'todayamount' => 0, 'pending' => 0, 'undelivery' => 0, 'unreceived' => 0, 'uncomment' => 0);
        foreach ($orders as $obj) {
            if ($obj['status'] < 5) {
                $order['amount'] += $obj['order_amount'];
                if (strtotime($obj['pay_time']) >= strtotime('today')) {
                    $order['todayamount'] += $obj['order_amount'];
                }
            }
            if ($obj['status'] == 4) {
                
            } else if ($obj['status'] < 3) {
                $order['pending'] ++;
            } else if ($obj['status'] == 3) {
                if ($obj['delivery_status'] == 0) {
                    $order['undelivery'] ++;
                } else if ($obj['delivery_status'] == 1) {
                    $order['unreceived'] ++;
                }
            }
        }

        $comment = $this->model->table("review")->fields("count(*) as num")->where("user_id = $id and status=0")->find();

        $this->code = 0;
        $this->content['comment'] = $comment;
        $this->content['order'] = $order;
        $this->content['customer'] = $customer;
    }

    //余额记录
    public function balance_log() {
        $page = Filter::int(Req::args('page'));
        $type = Filter::str(Req::args('type'));
        $where = '';
        switch ($type) {
            case 'in':
                // $where = ' and type in(1,2,4,5,6,7,8,9,10,12,13,14,15,16,18,19,20,21)';
                $where = ' and amount>0';
                break;
            case 'out':
                // $where = ' and type in(0,3,11,17)';
                $where = ' and amount<0';
                break;
            case 'all':
                break;
            default:
                break;
        }
        //$customer = $this->model->table("customer")->where("user_id=" . $this->user['id'])->find();
        $count = $this->model->query('select count(id) as count from tiny_balance_log where user_id = ' . $this->user['id'] . $where);
        $page_count = ceil($count[0]['count'] / 10);
        if ($page_count == 0) {
            $this->code = 0;
            $this->content = null;
            return;
        }

        $offset = ($page - 1) * 10;
        if ($offset < 0) {
            $this->code = 0;
            $this->content = null;
            return;
        }
        $log = $this->model->query('select * from tiny_balance_log where user_id =' . $this->user['id'] . $where . " order by id desc limit $offset,10");
        if ($log) {
            foreach ($log as $k => $v) {
                $log[$k]['amount'] = $log[$k]['amount'] > 0 ? "+" . $log[$k]['amount'] : $log[$k]['amount'];
                if($log[$k]['order_no']==null){
                    $log[$k]['order_no'] = '';
                }
            }
        }

        $this->code = 0;
        $this->content = empty($log) ? null : $log;
    }

    //余额记录
    public function offlinebalance_log() {
        $page = Filter::int(Req::args('page'));
        $type = Filter::str(Req::args('type'));
        $where = '';
        switch ($type) {
            case 'in':
                $where = ' and type in(8,13,20)';
                break;
            case 'out':
                $where = ' and type = 11';
                break;
            case 'all':
                $where = ' and type in(8,11,13,20)';
                break;
            default:
                break;
        }
        //$customer = $this->model->table("customer")->where("user_id=" . $this->user['id'])->find();
        $count = $this->model->query('select count(id) as count from tiny_balance_log where user_id = ' . $this->user['id'] . $where);
        $page_count = ceil($count[0]['count'] / 10);
        if ($page_count == 0) {
            $this->code = 0;
            $this->content = null;
            return;
        }

        $offset = ($page - 1) * 10;
        if ($offset < 0) {
            $this->code = 0;
            $this->content = null;
            return;
        }
        $log = $this->model->query('select * from tiny_balance_log where user_id =' . $this->user['id'] . $where . " order by id desc limit $offset,10");
        if ($log) {
            foreach ($log as $k => $v) {
                $log[$k]['amount'] = $log[$k]['amount'] > 0 ? "+" . $log[$k]['amount'] : $log[$k]['amount'];
            }
        }

        $this->code = 0;
        $this->content = empty($log) ? null : $log;
    }

    //安全中心相关接口
    public function verify() {
        $type = Filter::str(Req::args('type'));
        $code = Filter::str(Req::args('code'));

        if ($code != '' && !empty($verifiedInfo) && $code == $verifiedInfo['code']) {
            //code验证通过时
            $this->code = 0;
        } else {
            $this->code = 1025;
            return;
        }
        //不需要验证码的
        if ($type == 'pay_password') {
            $pay_password = Filter::str(Req::args('pay_password'));
            $customer = $this->model->query("select pay_password_open,pay_password,pay_validcode from tiny_customer where user_id =" . $this->user['id']);
            if ($customer[0]['pay_password_open'] == 0) {
                //用户没有开启支付密码
                $this->code = 1000;
                return;
            } else if ($customer[0]['pay_password'] != CHash::md5($pay_password, $customer[0]['pay_validcode'])) {
                //支付密码不正确
                $this->code = 1016;
                return;
            } else {
                //密码验证通过
                $this->code = 0;
            }
        }
        if ($this->code == 0) {
            //验证通过时
            $random = CHash::random(20, 'char');
            Session::clear('verifiedInfo');
            Session::set('random', $random);
            $this->content['sign'] = $random;
        }
    }

    //修改操作
    public function update_obj() {
        $obj = Filter::str(Req::args('obj'));
        //修改邮箱和手机时需要code参数
        $sign = Filter::str(Req::args('sign'));
        $random = Session::get('random');
        if ($random == NULL) {
            $this->code = 1071;
            return;
        }
        if ($sign == $random) {
            //验证通过
            if ($obj == 'password' || $obj == 'pay_password') {
                //如果修改对象是密码，则不需要code验证
                $password = Req::args('password');
                $repassword = Req::args('repassword');
                if ($password == $repassword) {
                    if ($obj == 'password') {
                        $validcode = CHash::random(8);
                        $this->model->table('user')->data(array('password' => CHash::md5($password, $validcode), 'validcode' => $validcode))->where('id=' . $this->user['id'])->update();
                        Session::clear('random');
                        $this->code = 0;
                        return;
                    } else if ($obj == 'pay_password') {
                        $validcode = CHash::random(8);
                        $this->model->table('customer')->data(array('pay_password' => CHash::md5($password, $validcode), 'pay_validcode' => $validcode, 'pay_password_open' => 1))->where('user_id=' . $this->user['id'])->update();
                        Session::clear('random');
                        $this->code = 0;
                        return;
                    }
                } else {
                    $this->code = 1020;
                    return;
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
                            Session::clear('random');
                            Session::clear('activateObj');
                            $this->code = 0;
                            return;
                        } else {
                            $this->code = 1027;
                            return;
                        }
                    } elseif ($obj == 'mobile' && Validator::mobi($account)) {
                        $result = $this->model->table('customer')->where("mobile ='" . $account . "'" . '  and user_id!=' . $this->user['id'])->find();
                        if (!$result) {
                            $this->model->table('customer')->data(array('mobile' => $account, 'mobile_verified' => 1))->where('user_id=' . $this->user['id'])->update();
                            Session::clear('random');
                            Session::clear('activateObj');
                            $this->code = 0;
                            return;
                        } else {
                            $this->code = 1021;
                            return;
                        }
                    }
                }
            }
        } else {
            $this->code = 1001;
            return;
        }
    }

    public function check_verify() {
        $userInfo = $this->model->table('user as us')->join('left join customer as cu on us.id = cu.user_id')->fields('cu.mobile_verified,cu.mobile,cu.email_verified,us.email,cu.pay_password_open')->where('cu.user_id = ' . $this->user['id'])->find();
        //隐藏敏感信息
        $userInfo['email'] = preg_replace("/^(\w{3}).*(\w{2}@.+)$/i", "$1*****$2", $userInfo['email']);
        $userInfo['mobile'] = preg_replace("/^(\d{3})\d+(\d{4})$/i", "$1*****$2", $userInfo['mobile']);
        if (!empty($userInfo)) {
            $this->code = 0;
            $this->content = $userInfo;
        } else {
            
        }
    }

    //添加收藏
    public function add_collect() {
        $gid = Filter::int(Req::args('goods_id'));
        //判读是否已经收藏过
        $flag = $this->model->query("select * from tiny_attention where goods_id = $gid and user_id =" . $this->user['id']);
        if ($flag) {
            $this->code = 1092;
            $this->content = "";
            return;
        }
        $result = $this->model->query("insert into tiny_attention(`user_id`,`goods_id`,`time`) values(" . $this->user['id'] . ",$gid,'" . date('Y-m-d h:i:s') . "')");
        if ($result) {
            $this->code = 0;
            $this->content = "";
        }
    }

   //获得收藏列表
    public function get_collect() {
        $page = Filter::int(Req::args('page'));
        $count = $this->model->query("select count(*) as count from tiny_attention where user_id=" . $this->user['id']);
        if ($count[0]['count'] == 0) {
            $this->code = 0;
            $this->content = "";
            return;
        }
        $page_count = ceil($count[0]['count'] / 10);
        if ($page > $page_count) {
            $this->code = 0;
            $this->content = "";
            return;
        }
        $offset = ($page - 1) * 10;
        $collect = $this->model->query("select c.*,g.name,g.img,g.sell_price,g.market_price from tiny_attention as c left join tiny_goods as g on c.goods_id = g.id where c.user_id = " . $this->user['id']);
        if ($collect) {
            $this->code = 0;
            $this->content = $collect;
        }
    }

    //删除收藏
    public function del_collect() {
        $id = Filter::int(Req::args('goods_id'));
        $result = $this->model->query("delete from tiny_attention where goods_id = $id and user_id = " . $this->user['id']);
        if ($result) {
            $this->code = 0;
            $this->content = "";
        }
    }

    //判断是否收藏
    public function is_collected() {
        $goods_id = Filter::int(Req::args('goods_id'));
        $flag = $this->model->query("select * from tiny_attention where goods_id = $goods_id and user_id =" . $this->user['id']);
        if ($flag) {
            //如果在收藏夹中
            $this->code = 0;
            $this->content = "";
        } else {
            //如果不在收藏夹中
            $this->code = 1094;
            $this->content = "";
        }
    }
    
    //售后
    public function sale_support() {
        $id = Filter::sql(Req::args('id'));
        $type = Filter::int(Req::args('type'));
        $user_id = $this->user['id'];
        $num = Filter::int(Req::args('num'));
        $proof = Filter::int(Req::args('proof'));
        $desc = Filter::sql(Req::args('desc'));
        $province = Filter::sql(Req::args('province'));
        $city = Filter::sql(Req::args('city'));
        $county = Filter::sql(Req::args('county'));
        $receiver = Filter::sql(Req::args('receiver'));
        $mobile = Filter::sql(Req::args('mobile'));
        $addr = Filter::sql(Req::args('addr'));
        $time = date('Y-m-d H:i:s');
        $img = Req::args('imgs');

        //1.判断手机号
        if (!Validator::mobi($mobile)) {
            $this->code = 1024;
            return;
        }
        $order_info = $this->model->query("select * from tiny_order_goods where id=$id");
        if (empty($order_info)) {
            //订单不存在
            $this->code = 1097;
            return;
        } else {

            $order_id = $order_info[0]['order_id'];
            //验证订单是否属于自己
            $result = $this->model->query("select * from tiny_order where id = $order_id and user_id = $user_id");

            if (empty($result)) {
                $this->code = 1101;
                return;
            }
            //todo 检查订单是否超出时限

            $order_no = $result[0]['order_no'];
            if ($num == "" || $num > $order_info[0]['goods_nums']) {
                //商品数量错误
                $this->code = 1100;
                return;
            }

            //2.验证售后信息

            $sale_support = $this->model->query("select * from tiny_sale_support where order_no ='$order_no' and order_goods_id = $id");
            if (!empty($sale_support)) {
                //订单已经在售后
                $this->code = 1098;
                return;
            }
        }
        if (is_array($img) && !empty($img)) {
            $imgs = implode(',', $img);
        } else {
            $imgs = "";
        }
        //插入操作
        $result = $this->model->query("insert into tiny_sale_support(`id`,`order_no`,`order_goods_id`,`type`,`user_id`,`num`,`proof`,`desc`,`imgs`,`receiver`,`mobile`,`province`,`city`,`county`,`addr`,`time`,`status`) values('','$order_no',$id,$type,$user_id,$num,$proof,'$desc','$imgs','$receiver',$mobile,$province,$city,$county,'$addr','$time',0)");
        if ($result) {
            $this->model->query("update tiny_order_goods set support_status = 1 where id = $id");
            $this->code = 0;
        }
    }

    //售后信息
    public function support_info() {
        $id = Filter::sql(Req::args('id'));
        $info = $this->model->query("select * from tiny_sale_support where id=$id and user_id=" . $this->user['id']);
        $this->code = 0;
        $this->content = $info[0];
    }

    //角标初始化=========================================
    public function badge() {
        //1.未读系统消息数量
        //2.未读个人消息
        //3.待收货数量
        //4.代付款订单
        //5.待评价
        //6.购物车
        //7.售后进度
        $data = $this->getMessageCount();
        $this->code = 0;
        $this->content['sys'] = (int) $data['sys'];
        $this->content['only'] = (int) $data['only'];
        $this->content['unpay'] = (int) $this->getUnpayOrderCount();
        $this->content['delivery'] = (int) $this->getDeliveryOrderCount();
        $this->content['review'] = (int) $this->getUnreviewCount();
        $this->content['cart'] = (int) $this->getCartCount();
        $this->content['undelivery'] = (int) $this->getUndeliveryOrderCount();//待发货
    }

    private function getMessageCount() {
        $data = $this->model->query("select message_ids from tiny_customer where user_id=" . $this->user['id']);
        if ($data[0]['message_ids'] == "") {
            return array('sys' => 0, 'only' => 0);
        } else {
            $message_ids = array();
            $str = ',' . $data[0]['message_ids'] . ',';
            preg_match_all('/,(\d+)/', $str, $message_ids);
            if (empty($message_ids[1])) {
                return array('sys' => 0, 'only' => 0);
            }
            $ids = implode(',', $message_ids[1]);
            $sys = $this->model->query("select count(id) as count from tiny_message where id in ($ids) and only = 0");
            $only = $this->model->query("select count(id) as count from tiny_message where id in ($ids) and only =" . $this->user['id']);
            $data_sys = isset($sys[0]['count']) ? $sys[0]['count'] : 0;
            $data_only = isset($only[0]['count']) ? $only[0]['count'] : 0;
            return array('sys' => $data_sys, 'only' => $data_only);
        }
    }

    private function getUnpayOrderCount() {
        $data = $this->model->query("select count(id) as count from tiny_order where is_del = 0 and  pay_status = 0 and status = 2 and user_id=" . $this->user['id']);
        return isset($data[0]['count']) ? $data[0]['count'] : 0;
    }

    private function getDeliveryOrderCount() {
        $data = $this->model->query("select count(id) as count from tiny_order where is_del = 0 and delivery_status = 1 and status = 3 and user_id=" . $this->user['id']);
        return isset($data[0]['count']) ? $data[0]['count'] : 0;
    }

    private function getUndeliveryOrderCount() {
        $data = $this->model->query("select count(id) as count from tiny_order where is_del = 0 and delivery_status = 0 and status = 3 and pay_status=1 and user_id=" . $this->user['id']);
        return isset($data[0]['count']) ? $data[0]['count'] : 0;
    }

    private function getCartCount() {
        $cart = Cart::getCart();
        return $cart->getNum();
    }

    private function getUnreviewCount() {
        $data = $this->model->query("select count(id) as count from tiny_review where status =0 and user_id=" . $this->user['id']);
        return isset($data[0]['count']) ? $data[0]['count'] : 0;
    }
    //角标初始化end=======================================
    
    //投诉建议
    public function complaint() {
        $user_id = $this->user['id'];
        $type = Filter::int(Req::args('type'));
        $content = Filter::sql(Req::args('content'));
        $mobile = Filter::sql(Req::args('mobile'));
        $time = date('Y-m-d H:i:s');
        if ($mobile != "") {
            if (!Validator::mobi($mobile)) {
                $this->code = 1025;
                return;
            }
        }
        $result = $this->model->query("insert into tiny_complaint(`id`,`type`,`user_id`,`mobile`,`content`,`time`,`status`) values('',$type,$user_id,'$mobile','$content','$time',0)");
        if ($result) {
            $this->code = 0;
            $this->content = null;
            return;
        } else {
            
        }
    }

    //更新支付密码
    public function updatePayPassword() {
        $old_pwd = Filter::str(Req::args('old_pay_pwd'));
        $new_pwd = Filter::str(Req::args('new_pay_pwd'));

        $result = $this->model->table('customer')->where('user_id=' . $this->user['id'])->fields('pay_password_open,pay_password,pay_validcode')->find();
        if (!empty($result)) {
            if ($result['pay_password_open'] == 0) {
                $this->code = 1600;
                return;
            }
            if ($result['pay_password'] == CHash::md5($old_pwd, $result['pay_validcode'])) {
                $validcode = CHash::random(8);
                $this->model->table('customer')->data(array('pay_password' => CHash::md5($new_pwd, $validcode), 'pay_validcode' => $validcode))->where('user_id=' . $this->user['id'])->update();
                $this->code = 0;
            } else {
                $this->code = 1060;
                return;
            }
        }
    }

    //重置支付密码
    public function resetPayPasswordByMobile() {
        $pay_pwd = Filter::str(Req::args('pay_pwd'));
        $code = Filter::int(Req::args('code'));
        $zone = Filter::int(Req::args('zone'));

        $result = $this->model->table('customer')->where('user_id=' . $this->user['id'])->fields('mobile,mobile_verified,pay_password_open,pay_password,pay_validcode')->find();
        if (!empty($result)) {
            // if ($result['mobile_verified'] == 1) {
                if ($this->sms_verify($code, $result['mobile'], $zone)) {
                    $validcode = CHash::random(8);
                    $this->model->table('customer')->data(array('pay_password' => CHash::md5($pay_pwd, $validcode), 'pay_validcode' => $validcode, 'pay_password_open' => 1))->where('user_id=' . $this->user['id'])->update();
                    $this->code = 0;
                } else {
                    $this->code = 1026;
                }
            // } else {
            //     $this->code = 1030;
            // }
        }
    }

    //获取又拍云上传参数
    public function getUpyun() {
        $type = Req::args('type');
        $upyun = Config::getInstance()->get("upyun");

        $options = array(
            'bucket' => $upyun['upyun_bucket'],
            'allow-file-type' => 'jpg,gif,png,jpeg', // 文件类型限制，如：jpg,gif,png
            'expiration' => time() + $upyun['upyun_expiration'],
            'notify-url' => $upyun['upyun_notify-url'],
            'ext-param' => "",
        );
        if ($type == 'avatar') {
            $options['save-key'] = "/data/uploads/head/" . $this->user['id'] . "{.suffix}";
            $options['ext-param'] = "avatar:{$this->user['id']}";
        }elseif($type == 'support') {
            $options['save-key'] = "/data/uploads/support/{year}/{mon}/{day}/{filemd5}{.suffix}";
            $options['ext-param'] = "support";
        }elseif ($type == 'shop_picture') {
            $options['save-key'] = "/data/uploads/picture/" . $this->user['id'] . ".png";
            $options['ext-param'] = "shop_picture:{$this->user['id']}";
            $this->model->table('district_promoter')->data(array('picture'=>$options['save-key']))->where('user_id='.$this->user['id'])->update();
        }elseif ($type == 'business_licence') {
            $options['save-key'] = "/data/uploads/business_licence/" . $this->user['id'] . ".jpg";
            $options['ext-param'] = "business_licence:{$this->user['id']}";
        }elseif ($type == 'positive_idcard') {
            $options['save-key'] = "/data/uploads/positive_idcard/" . $this->user['id'] . ".jpg";
            $options['ext-param'] = "positive_idcard:{$this->user['id']}";
        }elseif ($type == 'native_idcard') {
            $options['save-key'] = "/data/uploads/native_idcard/" . $this->user['id'] . ".jpg";
            $options['ext-param'] = "native_idcard:{$this->user['id']}";
        }elseif ($type == 'account_picture') {
            $options['save-key'] = "/data/uploads/account_picture/" . $this->user['id'] . ".jpg";
            $options['ext-param'] = "account_picture:{$this->user['id']}";
        }elseif ($type == 'shop_photo') {
            $options['save-key'] = "/data/uploads/shop_photo/" . $this->user['id'] . ".jpg";
            $options['ext-param'] = "shop_photo:{$this->user['id']}";
        }elseif ($type == 'hand_idcard') {
            $options['save-key'] = "/data/uploads/hand_idcard/" . $this->user['id'] . ".jpg";
            $options['ext-param'] = "hand_idcard:{$this->user['id']}";
        } else {
            $this->code = 1000;
            return;
        }
        $policy = base64_encode(json_encode($options));
        $signature = md5($policy . '&' . $upyun['upyun_formkey']);

        $this->code = 0;
        $this->content['policy'] = $policy;
        $this->content['signature'] = $signature;
        $this->content['save_path'] = $options['save-key'];
    }

    public function myCommission() {
        $uid = $this->user['id'];
        $commission = $this->model->table("commission")->where('user_id=' . $uid)->find();
        if (empty($commission)) {
            $this->code = 1104;
            return;
        } else {
            //更新可用状态
            $commission_set = Config::getInstance()->get("commission_set");
            $lockdays = $commission_set['commission_locktime'];
            $lockdays = is_int($lockdays) ? $lockdays : (int) $lockdays;
            $available_time = date('Y-m-d H:i:s', strtotime("-$lockdays days"));
            $result = $this->model->table('commission_log')->where("user_id  = $uid and status = 0 and time < '$available_time'")->data(array('status' => 1))->update();
            if ($result > 0) {
                $available_commission = $this->model->query("select SUM(commission_get) as count from tiny_commission_log where user_id=$uid and status =1");
                $this->model->table('commission')->data(array('commission_available' => $available_commission[0]['count']))->where('user_id=' . $uid)->update();
                $commission = $this->model->table("commission")->where('user_id=' . $uid)->find();
            }
        }
        $this->code = 0;
        $this->content = $commission;
    }

    public function commissionLog() {
        $uid = $this->user['id'];
        $page = Req::args('page');
        $commission_log = $this->model->table("commission_log as c")->join("left join user as u on u.id = c.buyer_id")->fields('c.*,u.nickname,u.avatar')->where("user_id=$uid")->findPage($page, 10);
        if (isset($commission_log['html'])) {
            unset($commission_log['html']);
        }
        $this->code = 0;
        $this->content = $commission_log;
    }

    public function commissionWithdraw() {
        $uid = $this->user['id'];
        $withdraw_type = Filter::sql(Req::args("withdraw_type"));
        $account_name = Filter::sql(Req::args("account_name"));
        $account = Filter::sql(Req::args("account"));
        $bank = Filter::sql(Req::args("bank"));
        $withdraw_amount = Filter::sql(Req::args("withdraw_amount"));
        $time = time();
        $withdraw_no = 'CM' . date("YmdHis") . rand(1000, 9999); //20

        $record = $this->model->table('commission_withdraw')->where("user_id = $uid and status=0")->fields('id')->find();
        if (!empty($record)) {
            $this->code = 1106;
            return;
        }
        //查询可提现的金额
        $commission = $this->model->table("commission")->where('user_id=' . $uid)->find();
        if (empty($commission)) {
            $this->code = 1104;
            return;
        } else {
            $commission_set = Config::getInstance()->get("commission_set");
            if ($withdraw_amount < $commission_set['withdraw_min']) {
                $this->code = 1105;
                return;
            } else if ($withdraw_amount > $commission['commission_available']) {
                $this->code = 1107;
                return;
            }
            if ($withdraw_type == 1) {//提现至金点
                $isOk = $this->model->table('commission_withdraw')->data(array('user_id' => $uid, "withdraw_type" => 1, "withdraw_no" => $withdraw_no, "withdraw_amount" => $withdraw_amount, 'bank' => "", 'account_name' => "", 'account' => "", 'time' => date("Y-m-d H:i:s", $time), 'status' => 0))
                        ->insert();
            } else if ($withdraw_type == 2) {
                $isOk = $this->model->table('commission_withdraw')->data(array('user_id' => $uid, "withdraw_type" => 2, "withdraw_no" => $withdraw_no, "withdraw_amount" => $withdraw_amount, 'bank' => $bank, 'account_name' => $account_name, 'account' => $account, 'time' => date("Y-m-d H:i:s", $time), 'status' => 0))
                        ->insert();
            } else {
                $this->code = 1000;
            }
            if ($isOk) {
                $this->code = 0;
            }
        }
    }

    public function withdrawHistory() {
        $uid = $this->user['id'];
        $page = Filter::sql(Req::args('page'));
        $history = $this->model->table("commission_withdraw")->where("user_id = $uid")->findPage($page, 10);
        if (isset($history['html'])) {
            unset($history['html']);
        }
        $this->code = 0;
        $this->content = $history;
    }

    public function myInvite() {
        $uid = $this->user['id'];
        $page = Filter::int(Req::args('page'));
        $invite = $this->model->table("invite as at")->fields("at.*,go.nickname,go.avatar,cu.real_name")->join("left join user as go on at.invite_user_id = go.id LEFT JOIN customer AS cu ON at.invite_user_id=cu.user_id")->where("at.user_id = " . $uid)->findPage($page, 10);
        if (isset($invite['html'])) {
            unset($invite['html']);
        }
        if (!empty($invite['data'])) {
            foreach ($invite['data'] as $k => $v) {
                if ($v['createtime'] != '') {
                    $invite['data'][$k]['createtime'] = date("Y-m-d H:i:s", $v['createtime']);
                }
            }
        }
        $this->code = 0;
        $this->content = $invite;
    }

    //获取我的邀请二维码
    public function myInviteQRCodeUrl() {
        $uid = $this->user['id'];
        $url = Url::fullUrlFormat("/index/invite") . "?uid=" . $uid;
        // $url = Url::fullUrlFormat("/travel/invite_register") . "?uid=" . $uid;
        $this->code = 0;
        $this->content['url'] = $url;
    }
    
    //获取快递信息
    public function getExpress() {
        $com = Filter::sql(Req::args("com"));
        $num = Filter::sql(Req::args("num"));
        $data = Common::getExpress($com, $num);
        if ($data['message'] == 'ok' && $data['status'] == 200) {
            $this->code = 0;
            $this->content['status'] = $data['state'];
            $this->content['com'] = $data['com'];
            $this->content['num'] = $data['nu'];
            $this->content['pathdata'] = $data['data'];
            return;
        } else {
            $this->code = 1112;
        }
    }

    //订单删除接口
    public function order_delete() {
        $id  = Filter::int(Req::args('order_id'));
        $isset = $this->model->table("order")->where("id=$id and user_id =".$this->user['id']." and status in(1,2,5,6)")->find();
        if(empty($isset)){
            $this->code = 1005;
            return;
        }
        $result = $this->model->table("order")->where("id = $id and user_id = ".$this->user['id'].' and status in (1,2,5,6)')->data(array('is_del'=>'1'))->update();
        if($result){
            if($isset['status']!=6){
                if (($isset['type'] == 5||$isset['type']==6) && $isset['pay_point'] > 0) {
                    $this->model->table("customer")->where("user_id=" . $this->user['id'])->data(array("point_coin" => "`point_coin`+" . $isset['pay_point']))->update();
                    Log::pointcoin_log($isset['pay_point'], $this->user['id'], $isset['order_no'], "取消订单，退回积分", 2);
                }
            }
             $this->code = 0;
        }else{
            $this->code = 1005;
        }
    }
    
    //================================申请退款相关接口start========================
    //判断是否能够申请退款
    public function _isCanApplyRefund($order_id) {
       //  $isset = $this->model->table("refund")->where("order_id =$order_id and user_id =".$this->user['id'])->find();
       // if($isset){
       //   return false;
       //   $this->model->table("refund")->where("order_id =$order_id and user_id =".$this->user['id'])->delete();
       // }
       $orderInfo = $this->model->table("order")->where("id = $order_id and user_id =".$this->user['id'])->find();
       if(empty($orderInfo)){
          return false;
       }else{
          if($orderInfo['order_amount']<=0){
              return false;
          }
          if($orderInfo['type']==4){//华币订单
              if($orderInfo['is_new']==0){
               if($orderInfo['otherpay_status']==1 || $orderInfo['pay_status']==1){
                        if($orderInfo['otherpay_amount']>0){
                            return array("otherpay_status"=>$orderInfo['otherpay_status'],"pay_status"=>$orderInfo['pay_status'],"order_type"=>4,"order_id"=>$order_id,"order_no"=>$orderInfo['order_no'] ,"payment"=>$orderInfo['payment'],"refund_amount"=>$orderInfo['otherpay_amount']);
                        }else{
                            return false;
                        }
                   }else{
                        return false;
                   }
                }else if($orderInfo['is_new']==1){
                    if($orderInfo['pay_status']==1){
                        if($orderInfo['is_return']==1){
                            $refund_amount = $orderInfo['otherpay_amount'];
                            if($refund_amount>0){
                                 return array("order_type"=>$orderInfo['type'],"order_id"=>$order_id,"order_no"=>$orderInfo['order_no'] ,"payment"=>$orderInfo['payment'],"refund_amount"=>$refund_amount);
                            }else{
                               return false; 
                            }
                        }else{
                            $refund_amount = $orderInfo['order_amount'];
                            if($refund_amount>0){
                                 return array("order_type"=>$orderInfo['type'],"order_id"=>$order_id,"order_no"=>$orderInfo['order_no'] ,"payment"=>$orderInfo['payment'],"refund_amount"=>$refund_amount);
                            }else{
                               return false; 
                            }
                        }
                    }else{
                        return false;
                    }
                }
          }else{
             if($orderInfo['pay_status']==1){
                    return array("order_type"=>$orderInfo['type'],"order_id"=>$order_id,"order_no"=>$orderInfo['order_no'] ,"payment"=>$orderInfo['payment'],"refund_amount"=>$orderInfo['order_amount']);
             }else{
                    return false;
             }
          }
       }
    }
    
    //退款信息获取接口
    public function refund_apply_info() {
        $order_id = Filter::int(Req::args("order_id"));
        $info = $this->_isCanApplyRefund($order_id);
        if ($info == false || empty($info)) {
            $this->code = 1114;
            return;
        }
        $this->code = 0;
        $this->content = array('order_no' => $info['order_no'], 'order_id' => $info['order_id'], 'refund_amount' => $info['refund_amount']);
    }
    
    //申请退管提交接口
    public function refund_apply_submit() {
        $order_id = Filter::int(Req::args("order_id"));
        $reason = Filter::sql(Req::args("reason"));
        $reason_desc = Filter::sql(Req::args("reason_desc"));
        $return = $this->_isCanApplyRefund($order_id);
        if ($return == false || empty($return)) {
            $this->code = 1114;
            return;
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
                    if ($return['pay_status'] == 1) {
                        $isOk = $this->model->table("order")->data(array("pay_status" => '2'))->where("id = $order_id")->update();
                    } else if ($return['otherpay_status'] == 1) {
                        $isOk = $this->model->table("order")->data(array("pay_status" => '2'))->where("id = $order_id")->update();
                    }
                } else {
                    $isOk = $this->model->table("order")->data(array("pay_status" => '2'))->where("id = $order_id")->update();
                }
                if ($isOk) {
                    $this->code = 0;
                    $this->content['refund_id'] = $id;
                    return;
                }
            }
        }
        $this->code = 1005;
    }
    
    //退款进度
    public function refund_progress() {
        $order_id = Filter::sql(Req::args("order_id"));
        $refund_info = $this->model->table("refund as r")
                ->join("left join payment as p on r.payment = p.id")
                ->fields("r.*,p.pay_name,plugin_id")
                ->where("order_id = $order_id and user_id = " . $this->user['id'])
                ->find();
        if ($refund_info) {
            $this->code = 0;
            $this->content = $refund_info;
        } else {
            $this->code = 1115;
        }
    }
    //=================================申请退款相关接口end=========================
    
    //余额提现接口
    public function balance_withdraw() {
        Filter::form();
        $id = Filter::int(Req::args('id'));
        $bankcard = $this->model->table('bankcard')->where('id='.$id)->find();
        if(!$bankcard){
            $this->code = 1202;
            return;
        }
        $open_name = $bankcard['open_name'];
        $open_bank = $bankcard['bank_name'];
        $prov = $bankcard['province'];
        $city = $bankcard['city'];
        $card_no = $bankcard['cardno'];
        $amount = Filter::float(Req::args('amount'));
        $amount = round($amount, 2);
        $customer = $this->model->table('customer')->fields('balance,offline_balance')->where('user_id='.$this->user['id'])->find();
        // $can_withdraw_amount = Common::getCanWithdrawAmount4GoldCoin($this->user['id']);
        $can_withdraw_amount = $customer['balance'];
        if ($can_withdraw_amount < $amount) {//提现金额中包含 暂时不能提现部分 
            $this->code = 1134;
            $this->content['can_withdraw_amount'] = $can_withdraw_amount;
            return;
        }
        $config = Config::getInstance();
        $other = $config->get("other");
        $withdraw_fee_rate = $other['withdraw_fee_rate'];
        if ($amount < $other['min_withdraw_amount']) {
            $this->code = 1135;
            $this->content['min_withdraw_amount'] = $other['min_withdraw_amount'];
            return;
        }
        // $isset = $this->model->table("balance_withdraw")->where("user_id =" . $this->user['id'] . " and status =0")->find();
        // if ($isset) {
        //     $this->code = 1136;
        //     return;
        // }
        $withdraw_no = "BW" . date("YmdHis") . rand(100, 999);
        
        $data = array("withdraw_no" => $withdraw_no, "user_id" => $this->user['id'], "amount" => $amount, 'open_name' => $open_name, "open_bank" => $open_bank, 'province' => $prov, "city" => $city, 'card_no' => $card_no, 'apply_date' => date("Y-m-d H:i:s"), 'status' => 0);
        $result = $this->model->table('balance_withdraw')->data($data)->insert();
        if ($result) {
            $this->model->table('customer')->data(array('balance' => "`balance`-" . $amount))->where('user_id=' . $this->user['id'])->update();
            Log::balance(0-$amount, $this->user['id'],$withdraw_no,"余额提现申请", 3, 1);
            $this->code = 0;
            $this->content['id'] = $result;
            $this->content['hand_fee'] = $other['withdraw_fee_rate']*$amount /100;
            $this->content['bankname'] = $open_bank;
            $this->content['card_no'] = $card_no;
            $this->content['amount'] = $amount;
            $this->content['withdraw_fee_rate'] = round($amount*($withdraw_fee_rate/100),2);
        } else {
            $this->code = 1005;
            return;
        }
    }

    public function offline_balance_withdraw() {
            Filter::form();
            $id = Filter::int(Req::args('id'));
            $bankcard = $this->model->table('bankcard')->where('id='.$id)->find();
            if(!$bankcard){
                $this->code = 1202;
                return;
            }
            $open_name = $bankcard['open_name'];
            $open_bank = $bankcard['bank_name'];
            $prov = $bankcard['province'];
            $city = $bankcard['city'];
            $card_no = $bankcard['cardno'];
            $amount = Filter::float(Req::args('amount'));
            $amount = round($amount, 2);
            $customer = $this->model->table("customer")->where("user_id =".$this->user['id'])->fields('offline_balance')->find();
            $can_withdraw_amount =$customer?$customer['offline_balance']:0;
            if ($can_withdraw_amount < $amount) {//提现金额中包含 暂时不能提现部分 
                $this->code = 1134;
                $this->content['can_withdraw_amount'] = $can_withdraw_amount;
                return;
            }
            $config = Config::getInstance();
            $other = $config->get("other");
            $withdraw_fee_rate = $other['withdraw_fee_rate'];
            if ($amount < $other['min_withdraw_amount']) {
                $this->code = 1135;
                $this->content['min_withdraw_amount'] = $other['min_withdraw_amount'];
                return;
            }
            // $isset = $this->model->table("balance_withdraw")->where("user_id =" . $this->user['id'] . " and status =0")->find();
            // if ($isset) {
            //     exit(json_encode(array('status' => 'fail', 'msg' => '申请失败，还有未处理完的提现申请')));
            // }
            $withdraw_no = "BW" . date("YmdHis") . rand(100, 999);
            $data = array("withdraw_no" => $withdraw_no, "user_id" => $this->user['id'], "amount" => $amount, 'open_name' => $open_name, "open_bank" => $open_bank, 'province' => $prov, "city" => $city, 'card_no' => $card_no, 'apply_date' => date("Y-m-d H:i:s"), 'status' => 0,'type'=>1);
            $result = $this->model->table('balance_withdraw')->data($data)->insert();
            if ($result) {
                $this->model->table('customer')->data(array('offline_balance' => "`offline_balance`-" . $amount))->where('user_id=' . $this->user['id'])->update();
                Log::balance(0-$amount, $this->user['id'],$withdraw_no,"商家余额提现申请", 11, 1);
                $this->code = 0;
                $this->content['id'] = $result;
                $this->content['hand_fee'] = $other['withdraw_fee_rate']*$amount /100;
                $this->content['bankname'] = $open_bank;
                $this->content['card_no'] = $card_no;
                $this->content['amount'] = $amount;
                $this->content['withdraw_fee_rate'] = round($amount*($withdraw_fee_rate/100),2);
            } else {
                $this->code = 1005;
                return;
            }
        
    }
    
    //获取我的余额提现记录
    public function getMyGoldWithdrawRecord(){
        $type = Filter::int(Req::args('type'));  //0 可用余额记录 1商家余额记录
        $page = Filter::int(Req::args('page'));
        if($type==1){
          $withdraw_list = $this->model->table("balance_withdraw")->where("type in (1,2) and user_id = ".$this->user['id'])->order("id desc")->findPage($page,10);
        }else{
            $withdraw_list = $this->model->table("balance_withdraw")->where("type=0 and user_id = ".$this->user['id'])->order("id desc")->findPage($page,10);
        }
        
        if(isset($withdraw_list['html'])){
            unset($withdraw_list['html']);
        }
        if(empty($withdraw_list)){
            $this->code = 0;
            $this->content= NULL;
        }else{
            $this->code = 0;
            $this->content = $withdraw_list;
        }
    }

    //商家余额提现
    public function getMerchantBalance()
    {
            $amount = Req::args('amount');
            $amount = round($amount, 2);
            $customer = $this->model->table("customer")->where("user_id =" . $this->user['id'])->fields('balance,offline_balance')->find();
            $can_withdraw_amount = $customer ? $customer['offline_balance'] : 0;
            if ($can_withdraw_amount < $amount) {//提现金额中包含 暂时不能提现部分
                $this->code = 1180;
                return;
            }
            if($amount<=0.00){
                $this->code = 1238;
                return;
            }
            $config = Config::getInstance();
            $other = $config->get("other");
            if ($amount < $other['min_withdraw_amount']) {
                $this->code = 1181;
                $this->content = $other['min_withdraw_amount'];
                return;
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
                $this->code = 0;
                $this->content = NULL;
            } else {
                $this->code = 1182;
                return;
            }
    }
    
    
    //================================小区相关接口start==============================
    //判断是否是小区推广员
    public function isDistrictPromoter() {
        // $promoter = Promoter::getPromoterInstance($this->user['id']);
        // if (is_object($promoter)) {
        //     $this->content['is_promoter'] = 1;
        //     $this->content['promoter_role']=$promoter->role_type;
        //     $this->content['promoter_id'] =$promoter->role_type==1?NULL:$promoter->promoter_id;
        // } else {
        //     $this->content['is_promoter'] = 0;
        // }
        // $this->code = 0;
        $base_info = $this->model->table("customer")->fields('user_id,valid_income,frezze_income,settled_income')->where("user_id=".$this->user['id'])->find();
        if(!$base_info){
            $this->code = 1159;
            return;
        }
        $pay_promoter   = $this->model->table('district_promoter')->where("user_id=".$this->user['id'])->fields("id,hirer_id,type")->find();
        if($pay_promoter){
            $role_type = 2;
            $promoter_id = $pay_promoter['id'];
        }else{
            $role_type = 1;
            $promoter_id = 1;
        }
         $this->code = 0;
         $this->content['is_promoter'] = 1;
         $this->content['promoter_role']=$role_type;
         $this->content['promoter_id'] =$promoter_id;
    }
    
    //根据id获取小区信息
    public function getDistrictInfoById(){
        $district_id = Filter::int(Req::args('district_id'));
        if($district_id==NULL){
            $this->code = 1000;
            return;
        }
        $district_info = $this->model->table("district_shop")->where("id=$district_id")->fields("id,name,location")->find();
        if(empty($district_info)){
            $this->code = 1139;
            return;
        }else{
            $this->code = 0;
            $this->content = $district_info;
        }
    }
    
    // 成为推广员
    public function becomepromoter() {
            $reference = Filter::int(Req::args('reference'));
            $from = Filter::str(Req::args('platform'));
            
            if ($reference == NULL) {
                $this->code = 1126;
                return; 
            }
            if($reference == $this->user['id']){
                $this->code = 1196;
                return;
            }
            $exist = $this->model->table('invite')->where('invite_user_id='.$this->user['id'])->find();
            $invite = $this->model->table('invite')->where('invite_user_id='.$reference)->find();
            $district_id = $invite?$invite['district_id']:1;
            if($invite){
                if($invite['user_id'] == $this->user['id']){
                    $this->code = 1197;
                    return;
                }
            }
            if($exist){
                $this->code = 1140;
                return;
            }else{
                $this->model->table('invite')->data(array('user_id'=>$reference,'invite_user_id'=>$this->user['id'],'from'=>$from,'district_id'=>$district_id,'createtime'=>time()))->insert();
                $this->code = 0;
                $this->content = '成功绑定邀请关系';
                return;
            }
    }
    
    //获取我的收益统计
    public function getPromoterIncomeStatic(){
        $promoter = Promoter::getPromoterInstance($this->user['id']);
        if(!is_object($promoter)){
            $this->code = 1141;
            return;
        }else{
            $this->code = 0;
            $this->content = $promoter->getIncomeStatistics();
        }
    }
    
    //获取我的销售记录
    public function getPromoterSaleRecord(){
        $page = Filter::int(Req::args('page'));
        $promoter = Promoter::getPromoterInstance($this->user['id']);
        if(!is_object($promoter)){
            $this->code = 1141;
            return;
        }else{
            $this->code = 0;
            $this->content = $promoter->getMySaleRecord($page);
        }
    }
    
    //获取我的收益记录
    public function getPrmoterIncomeRecord(){
        $page = Filter::int(Req::args('page'));
        $promoter = Promoter::getPromoterInstance($this->user['id']);
        if(!is_object($promoter)){
            $this->code = 1141;
           return;
        }else{
            $this->code = 0;
            $this->content = $promoter->getMyIncomeRecord($page);
        }
    }
    
    //获取我的提现记录
    public function getPromoterSettledRecord(){
        $page = Filter::int(Req::args('page'));
        $promoter = Promoter::getPromoterInstance($this->user['id']);
        if(!is_object($promoter)){
            $this->code = 1141;
           return;
        }else{
            $this->code = 0;
            $this->content = $promoter->getSettledHistory($page);
        }
    }
    
    //获取我邀请的推广员列表
    public function getMyInvitePromoter(){
        $page = Filter::int(Req::args('page'));
        $start_time = Filter::str(Req::args('start_time'));
        $end_time = Filter::str(Req::args('end_time'));
        $from = Filter::str(Req::args('from'));
        $level = Filter::int(Req::args('level'));
        $date_sort = Filter::str(Req::args('date_sort'));
        if(!$level) {
            $level = 0;
        }
        $where = "do.user_id=".$this->user['id'];    
        if($start_time) {
            $start = strtotime($start_time);
            $where .= ' and createtime>'.$start;
        }
        if($end_time) {
            $end = strtotime($end_time);
            $where .= ' and createtime<'.$end;      
        }
        $sort = "do.id desc";
        if($date_sort=='desc') {
            $sort = "do.createtime desc";
        }
        if($date_sort=='asc') {
            $sort = "do.createtime asc";
        }
        if($from) {
           switch (strtoupper($from)) {
                case 'A':
                    $where.=" and `from` in ('second-wap')";
                    break;
                case 'B':
                    $where.=" and `from` in ('alipay')";
                    break;
                case 'C':
                    $where.=" and `from` in ('wechat','wap','android','ios')";
                    break;
                case 'D':
                    $where.=" and `from` in ('android_weixin','android_alipay','ios_weixin','ios_alipay')";
                    break;
                case 'E':
                    $where.=" and `from` in ('admin','web')";
                    break;
                case 'F':
                    $where.=" and `from` in ('jihuo')";
                    break;
                case 'G':
                    $where.=" and `from` in ('active')";
                    break;
                case 'H':
                    $where.=" and `from` in ('goods_qrcode')";
                    break;                        
                default:
                    $where.=" and `from` like '%$from%'";
                    break;
            } 
        } 
        // if($from) {
        //     $where.=" and `from` like '%$from%'";
        // }
        $sums=$this->model->table('invite as do')->join('left join user as u on do.invite_user_id = u.id')->fields('count(do.id) as total')->where("user_id=".$this->user['id'])->findAll();
        $sum = !empty($sums)?$sums[0]['total']:0;
        if(!$level) {
            $record = $this->model->table('invite as do')
                    ->join('left join user as u on do.invite_user_id = u.id left join customer as c on do.invite_user_id = c.user_id')
                    ->fields('u.id,u.avatar,u.nickname,FROM_UNIXTIME(do.createtime) as createtime,do.from,c.mobile,c.mobile_verified')
                    ->where($where)
                    ->order($sort)
                    ->findPage($page, 10);        
            if (isset($record['html'])) {
                unset($record['html']);
            }
            $list = $this->model->table('invite as do')->join('left join user as u on do.invite_user_id = u.id')->fields('count(do.id) as total')->where($where)->findAll();
            $total = $list!=null?$list[0]['total']:0;            
            if($record['data']){
                foreach($record['data'] as $k=>$v){
                    if($v['id']==null){
                        unset($record['data'][$k]);
                        $total = $total-1;
                    }else{
                        $shop = $this->model->table('district_shop')->where('owner_id='.$v['id'])->find();
                        $promoter = $this->model->table('district_promoter')->where('user_id='.$v['id'])->find();
                        if($shop && $promoter){
                            $record['data'][$k]['role_type'] = 2;
                            
                        }elseif(!$shop && $promoter){
                            $record['data'][$k]['role_type'] = 1;
                            
                        }else{
                            $record['data'][$k]['role_type'] = 0;
                            
                        }
                        if($v['nickname']=='') {
                            $record['data'][$k]['nickname'] = $v['mobile'];
                        }
                    }
                }
                $record['data'] = array_values($record['data']);
                $record['page']['current_num'] = $total;
                $record['page']['total'] = $sum;
            } else {
                $record['data'] = [];
                $record['page']['totalPage'] = 0;
                $record['page']['pageSize'] = 10;
                $record['page']['page'] = $page;
                $record['page']['current_num'] = 0;
                $record['page']['total'] = $sum;
            }
        } else {
            $list = $this->model->table('invite as do')
                    ->join('left join user as u on do.invite_user_id = u.id left join customer as c on do.invite_user_id = c.user_id')
                    ->fields('u.id,u.avatar,u.nickname,FROM_UNIXTIME(do.createtime) as createtime,do.from,c.mobile,c.mobile_verified')
                    ->where($where)
                    ->order($sort)
                    ->findAll();
            if($list) {
                foreach($list as $k=>$v){
                    if($v['id']==null){
                        unset($list[$k]);
                    }else{
                        $shop = $this->model->table('district_shop')->where('owner_id='.$v['id'])->find();
                        $promoter = $this->model->table('district_promoter')->where('user_id='.$v['id'])->find();
                        if($shop && $promoter){
                            $list[$k]['role_type'] = 2;
                            if($level==1 || $level ==2) {
                                unset($list[$k]);
                            }
                        }elseif(!$shop && $promoter){
                            $list[$k]['role_type'] = 1;
                            if($level==1 || $level ==3) {
                                unset($list[$k]);
                            }
                        }else{
                            $list[$k]['role_type'] = 0;
                            if($level==2 || $level ==3) {
                                unset($list[$k]);
                            }
                        }
                        if($v['nickname']=='') {
                            $list[$k]['nickname'] = $v['mobile'];
                        }
                    }
                }
                $total = count($list);
                $data = array_values($list);
                $record['data'] = array_slice($data, ($page - 1) * 10, 10);
                $record['page']['totalPage'] = ceil($sum / 10);
                $record['page']['pageSize'] = 10;
                $record['page']['page'] = $page;
                $record['page']['current_num'] = $total;
                $record['page']['total'] = $sum;
            } else {
                $record['data'] = [];
                $record['page']['totalPage'] = 0;
                $record['page']['pageSize'] = 10;
                $record['page']['page'] = $page;
                $record['page']['current_num'] = 0;
                $record['page']['total'] = 0;
            }        
        }  
        
        
        
        $this->code = 0;
        $this->content = $record;
        return;
    }
    
    //推广员申请结算提现
    public function promoterDoSettle(){
        $promoter = Promoter::getPromoterInstance($this->user['id']);
        if(!is_object($promoter)){
            $this->code = 1141;
            return;
        }else{
            $data = Req::args();
            if($data['type']==0){
                $data['type']=1;
            }elseif($data['type']==1){
                $data['type']=2;
            }
            unset($data['user_id']);
            unset($data['token']);
            $result = $promoter->applyDoSettle($data);
            if($result['status']=='success'){
                $this->code = 0;
            }else{
                $this->code = $result['msg_code'];
            }
        }
    }
    
    //根据商品获得商品的小区推广标示
    public function getQrcodeFlagByGoodsId(){
        $goods_id = Filter::int(Req::args('goods_id'));
        $promoter = Promoter::getPromoterInstance($this->user['id']);
        $user_id=$this->user['id'];
        if(!is_object($promoter)){
            $this->code = 1141;
           return;
        }else{
            $result = $promoter->getQrcodeByGoodsId1($user_id,$goods_id,false);
            if($result['status']=='success'){
                $this->code =0;
                $this->content['flag']=$result['flag'];
                $this->content['goods_id']=$result['goods_id'];
                $this->content['url']=$result['url'];
            }else{
                $this->code = $result['msg_code'];
            }
        }
    }
   
    //================================小区相关接口end==============================
    
    //积分币记录
    public function pointcoin_log(){
        $page = Filter::int(Req::args('page'));
        $type = Filter::str(Req::args('type'));
        $where = '';
        switch ($type) {
            case 'in':
                $where = ' and type !=0 ';
                break;
            case 'out':
                $where = ' and type =0 ';
                break;
            case 'all':
                break; 
            default:
                break;
        }
        $log = $this->model->table("pointcoin_log")->where("user_id = " . $this->user['id'] . $where)->order('id desc')->findPage($page, 10);
        if (isset($log['html'])) {
            unset($log['html']);
        }
        $this->code = 0;
        $this->content = $log;
    }
    
    
    //签到
    public function sign_in(){
        $config = Config::getInstance();
        $set = $config->get('sign_in_set');
        if($set['open']==0){
            $this->code = 1153;
            return;
        }
        //判断今天是否签到过
        $date = date("Y-m-d");
        $is_signed = $this->model->table("sign_in")->where("date='$date' and user_id=".$this->user['id'])->find();
        if($is_signed){
            $this->code = 1154;
            return;
        }else{
            $last_sign = $this->model->table("sign_in")->order('date desc')->where("user_id=".$this->user['id'])->find();
            if($last_sign){
                    //判断上次签到和这次签到中间是否有缺
                    $yesterday = date("Y-m-d",strtotime("-1 day"));
                    if($yesterday==$last_sign['date']){
                        $data['serial_day']=$last_sign['serial_day']+1;
                        $data['sign_in_count']=$last_sign['sign_in_count']+1;
                    }else{
                        $data['serial_day']=1;
                        $data['sign_in_count']=$last_sign['sign_in_count']+1;
                    }
            }else{
                 $data['serial_day']=1;
                 $data['sign_in_count']=1;
            }
            $data['date']=$date;
            $data['user_id']=$this->user['id'];
            //读取签到送积分规则
            $data['send_point']=Common::getSignInSendPointAmount($data['serial_day']);
            $result = $this->model->table("sign_in")->data($data)->insert();
            if($result){
               $this->model->table("customer")->data(array('point_coin'=>"`point_coin`+".$data['send_point']))->where("user_id=".$this->user['id'])->update();
               Log::pointcoin_log($data['send_point'], $this->user['id'], "", "每日签到赠送", 10);
               $this->code = 0;
               $this->content['send_point']=$data['send_point'];
               $this->content['serial_day']=strval($data['serial_day']);
               $this->content['sign_in_count']=strval($data['sign_in_count']);
            }else{
               $this->code = 1005;
               return; 
            }
        }
    }
    
    //根据年月获取签到数据
    public function getSignInDataByYm(){
        $year = Filter::int(Req::args('y'));
        $month= Filter::int(Req::args('m'));
        $this->code = 0;
        $data = Common::getSignInDataByUserID($year, $month, $this->user['id']);
        $this->content = empty($data)?NULL:$data;
    }

    // 配置头像
    public function set_avatar() {
        $upfile_path = Tiny::getPath("uploads") . "/head/";
        $upfile_url = preg_replace("|" . APP_URL . "|", '', Tiny::getPath("uploads_url") . "head/", 1);
        //$upfile_url = strtr(Tiny::getPath("uploads_url")."head/",APP_URL,'');
        $upfile = new UploadFile('imgfile', $upfile_path, '500k', '', 'hash', $this->user['id']);
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
            $avatar = "http://" . $_SERVER['HTTP_HOST'] . '/' . $image_url;
            // var_dump(123);die;
            $model->data(array('avatar' => $avatar))->where("id=" . $this->user['id'])->update();

            $safebox = Safebox::getInstance();
            $user = $this->user;
            $user['avatar'] = $avatar;
            $safebox->set('user', $user);
            $this->code = 0;
        } else {
            $this->code = 1099;
        }
    }

    /*
     * 获取推广员列表
     */
    public function getPromoterList(){   
        $page = Filter::int(Req::args('page'));
        // $page = $page==1?0:$page;
        if(!$page) {
            $page = 1;
        }
        $district = $this->model->table('district_shop')->where('owner_id='.$this->user['id'])->find();
        if(!$district){
            $this->code = 1131;
            return;
        }      
        
        $list = $this->model->table('district_promoter as dp')
                ->join('left join user as u on dp.user_id = u.id')
                ->fields('u.id,u.avatar,u.nickname,dp.create_time')
                ->where("dp.hirer_id=".$district['id'])
                ->order("dp.id desc")
                ->findAll();
        
        if($list) {
            foreach($list as $k=>$v){
                if($v['id']==null){
                    unset($list[$k]);
                }
            }    
            foreach($list as $k=>$v){
                $list[$k]['createtime'] = strtotime($v['create_time']);
                $getMyAllInviters = Common::getMyAllInviters($v['id']);
                $list[$k]['member_num'] = $getMyAllInviters['num'];
            }
        }        

        $count = $list!=null?count($list):0;
        $totalPage = ceil($count / 100) + 1;
        $pageSize = 10;

        if($list) {
            $list = array_slice($list,($page - 1) * $pageSize, $pageSize);
        }

        $record['data'] = $list;
        $record['page'] = array(
            'total'=>$count,
            'totalPage'=>$totalPage,
            'pageSize'=>$pageSize,
            'page'=>$page
            );        

        $this->code = 0;
        $this->content = $record;
    }

    /*
     * 获取拓展小区列表
     */
    public function getSubordinate(){
        // $this->code = 0;
        // $this->content = $this->hirer->getMySubordinate();
        $page = Filter::int(Req::args('page'));
        // if(!$page) {
        //     $page = 1;
        // }
        $page = $page==1?0:$page;
        $district = $this->model->table('district_shop')->where('owner_id='.$this->user['id'])->find();
        if(!$district){
            $this->code = 1131;
            return;
        }
        $record = $this->model->table('district_shop as ds')
                ->join('left join user as u on ds.owner_id = u.id')
                ->fields('u.id,u.avatar,u.nickname,ds.linkman,ds.create_time')
                ->where("ds.invite_shop_id=".$district['id'])
                ->order("ds.id desc")
                ->findPage($page, 10);

        if (isset($record['html'])) {
            unset($record['html']);
        }
        if($record) {
            foreach ($record['data'] as $k => $v) {
                // if($v['id']==null){
                //     unset($record['data'][$k]);
                // }
                $record['data'][$k]['createtime'] = strtotime($v['create_time']);
                $getMyAllInviters = Common::getMyAllInviters($v['id']);
                $record['data'][$k]['member_num'] = $getMyAllInviters['num'];
                $getMyAllPromoter = Common::getMyAllPromoter($v['id']);
                $record['data'][$k]['promoter_num'] = $getMyAllPromoter['num'];
            }
            $record['data'] = array_values($record['data']);
        } else {
            $record['data'] = [];
        }
        

        $this->code = 0;
        $this->content = $record;
    }

    /*
     * 判断用户是否实名认证过
     */
    public function name_verified(){
        $user = $this->model->table('customer')->fields('realname_verified,realname,id_no')->where('user_id='.$this->user['id'])->find();
        if(!$user){
            $this->code = 1159;
            return;
        }

        $realname = $user['realname'];
        $id_no = $user['id_no'];
        

        if($user['realname_verified']){
            $strlen = mb_strlen($user['realname'], 'utf-8');
            $lastStr = mb_substr($user['realname'], -1, 1, 'utf-8');
            $realname = str_repeat("*", $strlen - 1) . $lastStr;
        }

        if($user['id_no']){
           $id_no = substr($user['id_no'],0,1).'****************'.substr($user['id_no'],-1);
        }
        
        $this->code = 0;
        $this->content['verified'] = $user['realname_verified'];
        $this->content['realname'] = $realname;
        $this->content['id_no'] = $id_no;
    }

    /*
     * 商家余额提现到银行卡
     */
    public function offlineBalanceWithdraw()
    {
        $bankcard_id = Filter::int(Req::args('bankcard_id'));
        $bankcard = $this->model->table('bankcard')->where('id='.$bankcard_id)->find();
        if(!$bankcard){
            $this->code = 1183;
            return;
        }
        $shop_check = $this->model->table('shop_check')->where('user_id='.$this->user['id'])->find();
        if($shop_check){
            if($shop_check['status'] == 0){
                $this->code = 1233;
                return;
            } elseif($shop_check['status'] == 2){
                $this->code = 1234;
                return;
            }
        }
        $open_name = $bankcard['open_name'];
        $open_bank = $bankcard['bank_name'];
        $prov = $bankcard['province'];
        $city = $bankcard['city'];
        $card_no = $bankcard['cardno'];
        // $card_no = str_replace(' ', '', Filter::str(Req::args('card_no')));
        $amount = Filter::float(Req::args('amount'));
        $amount = round($amount, 2);
        $customer = $this->model->table("customer")->where("user_id =" . $this->user['id'])->fields('offline_balance')->find();
        $can_withdraw_amount = $customer ? $customer['offline_balance'] : 0;
        if ($can_withdraw_amount < $amount) {//提现金额中包含 暂时不能提现部分 
            $this->code = 1180;
            return;
        }
        $config = Config::getInstance();
        $other = $config->get("other");
        if ($amount < $other['min_withdraw_amount']) {
            $this->code = 1181;
            return;
        }
        $withdraw_no = "BW" . date("YmdHis") . rand(100, 999);
        $data = array("withdraw_no" => $withdraw_no, "user_id" => $this->user['id'], "amount" => $amount, 'open_name' => $open_name, "open_bank" => $open_bank, 'province' => $prov, "city" => $city, 'card_no' => $card_no, 'apply_date' => date("Y-m-d H:i:s"), 'status' => 0, 'type' => 1);
        $result = $this->model->table('balance_withdraw')->data($data)->insert();
        if ($result) {
            $this->model->table('customer')->data(array('offline_balance' => "`offline_balance`-" . $amount))->where('user_id=' . $this->user['id'])->update();
            Log::balance(0 - $amount, $this->user['id'], $withdraw_no, "商家余额提现申请", 11, 1);
            $this->code = 0;
            $this->content = '申请成功';
        } else {
            $this->code = 1182;
            return;
        }
    }

    /*
     * 银行卡列表
     */
    public function bankcardList(){
        $bankcard = $this->model->table('bankcard')->where('user_id='.$this->user['id'])->findAll();
        if($bankcard){ 
            foreach($bankcard as $k=>$v){  //设置银行英文字母代码  
                if($v['bank_code']==''){
                    $result = Common::getBankcardTpye($v['cardno']);
                    $code = Common::getBankcardTpyeCode($v['cardno']);
                    if ($result['retCode'] == 21401 || $result['retCode'] != 200) {
                        if ($code['validated'] == FALSE) {
                            //不存在次卡号类型
                            $this->code = 1184;
                            return;
                        }
                    }
                    $this->model->table('bankcard')->data(array('bank_code'=>$code['bank']))->where('id='.$v['id'])->update();
                }  
            }
        }
        $list = $this->model->table('bankcard')->fields('id,bank_name,open_name,cardno,type,bank_code,logo')->where('user_id='.$this->user['id'])->findAll();
        if($list){
            foreach($list as $k=>$v){  //设置银行logo
                if($v['logo']==''){
                    $logo = 'https://apimg.alipay.com/combo.png?d=cashier&t='.$v['bank_code'];
                    $this->model->table('bankcard')->data(array('logo'=>$logo))->where('id='.$v['id'])->update();
                }
            }
        }
        $newlist = $this->model->table('bankcard')->fields('id,cardno,type,logo')->where('user_id='.$this->user['id'])->findAll();
        if($newlist){
            foreach($newlist as $k=>$v){
                $card_type = $v['type']==1?' 储蓄卡':' 信用卡';
                $newlist[$k]['name'] = '尾号 '.substr($v['cardno'], -4).$card_type;
            }
        }
        $this->code = 0;
        $this->content = $newlist;
    }
    
    /*
     * 绑定银行卡临时接口
     */
    public function bindCardTemp(){
      $bankcard = Req::args('bankcard');
      // $idcard = Req::args('idcard');
      // $realname = Filter::str(Req::args('realname'));
      $province = Filter::str(Req::args('province'));
      $city = Filter::str(Req::args('city'));
      
      $customer = $this->model->table('customer')->fields('realname_verified,realname,id_no')->where('user_id='.$this->user['id'])->find();
      if($customer['realname_verified']==0){ //需要先实名认证
        $this->code = 1192;
        return;
      }

      // if($realname!=$customer['realname'] || $idcard!=$customer['id_no']){
      //   $this->code = 1230;
      //   return;
      // }
      $realname = $customer['realname'];
      $idcard = $customer['id_no']; 
      $url = "https://aliyun-bankcard-verify.apistore.cn/bank?Mobile=&bankcard=".$bankcard."&cardNo=".$idcard."&realName=".$realname;
      $header = array(
            'Authorization:APPCODE 8d41495e483346a5a683081fd046c0f2'
        );
     
      $ret = Common::httpRequest($url,'GET',NULL,$header);
      $result = json_decode($ret,true);
      if($result['error_code']==0){
        $has_bind = $this->model->table('bankcard')->where('cardno='.$bankcard)->find();
        if($has_bind){
            $this->code = 1191;
            return;
        }
        $bank_code = $result['result']['information']['abbreviation'];
        if($bank_code){
            $logo = 'https://apimg.alipay.com/combo.png?d=cashier&t='.$bank_code;
        }else{
            $logo = '';
        }
        $data = array(
            'user_id'=>$this->user['id'],
            'cardno'=>$bankcard,
            'bank_name'=>$result['result']['information']['bankname'],
            'open_name'=>$realname,
            'province'=>$province,
            'city'=>$city,
            'type'=>intval($result['result']['information']['iscreditcard']),
            'bank_code'=>$result['result']['information']['abbreviation'],
            'bind_date'=>date('Y-m-d H:i:s'),
            'logo'=>$logo
            );
        $this->model->table('bankcard')->data($data)->insert();
        $this->code = 0;
        $this->content = '绑定成功';
      }else{
        $this->code = 1190;
        return;
      }
    }

    /*
     * 实名认证临时接口
     */
    public function nameVerifiedTemp(){
      $idcard = Req::args('idcard');
      $realname = Filter::str(Req::args('realname'));
      
      $customer = $this->model->table('customer')->fields('realname_verified')->where('user_id='.$this->user['id'])->find();
      if($customer['realname_verified']==1){ //已认证
        $this->code = 1191;
        return;
      }

      $url = "https://aliyun-bankcard-verify.apistore.cn/bank?Mobile=&bankcard=&cardNo=".$idcard."&realName=".$realname;
      $header = array(
            'Authorization:APPCODE 8d41495e483346a5a683081fd046c0f2'
        );
     
      $ret = Common::httpRequest($url,'GET',NULL,$header);
      $result = json_decode($ret,true);
      if($result['error_code']==0){
        $this->model->table('customer')->data(array('realname_verified'=>1,'realname'=>$realname,'id_no'=>$idcard))->where('user_id='.$this->user['id'])->update();
        $this->code = 0;
        $this->content = '验证成功';
      }else{
        $this->code = 1232;
        return;
      }
    }

    public function unbindCardTemp(){
        $list_id = Filter::int(Req::args('list_id'));
        if(!$list_id){
            $this->code = 1193;
            return;
        }
        $result = $this->model->table('bankcard')->where('id='.$list_id.' and user_id='.$this->user['id'])->delete();
        if($result){
            $this->code = 0;
            $this->content = '解绑成功';
        }else{
            $this->code = 1194;
            return;
        }
    }

    public function rongyun_token($user_id){
        $url = 'http://api.cn.ronghub.com/user/getToken.json';
        $appSecret = 'snTBfamLUz3NM';
        $Nonce = rand(1000,9999);
        $Timestamp = time()*1000;
        $Signature = sha1($appSecret.$Nonce.$Timestamp);
        $customer = $this->model->table('customer as c')->join('left join user as u on c.user_id=u.id')->fields('c.real_name,u.avatar')->where('c.user_id='.$user_id)->find();
        if($customer){
            $data = array(
                'userId'=>$user_id,
                'name'=>$customer['real_name'],
                'portraitUri'=>$customer['avatar']!=null?$customer['avatar']:''
            );
            $header = array(
                'App-Key:n19jmcy5nsuh9',
                'Nonce:'.$Nonce,
                'Timestamp:'.$Timestamp,
                'Signature:'.$Signature,
                'Content-Type: application/x-www-form-urlencoded'
                );
            $return = Common::httpRequest($url,'POST',$data,$header);
            $ret = json_decode($return,true);
            if($ret['code']==200){
                return $ret['token'];
            }else{
                return FALSE;
            }
        }else{
            return FALSE;
            exit();
        } 
    }

    public function get_rongyun_token(){
        $user_id = $this->user['id'];
        $url = 'http://api.cn.ronghub.com/user/getToken.json';
        $appSecret = 'snTBfamLUz3NM';
        $Nonce = rand(1000,9999);
        $Timestamp = time()*1000;
        $Signature = sha1($appSecret.$Nonce.$Timestamp);
        $customer = $this->model->table('customer as c')->join('left join user as u on c.user_id=u.id')->fields('c.real_name,u.avatar,u.nickname')->where('c.user_id='.$user_id)->find();
        $data = array(
            'userId'=>$user_id,
            'name'=>$customer['real_name']!=''?$customer['real_name']:$customer['nickname'],
            'portraitUri'=>$customer['avatar']!=null?$customer['avatar']:''
            );
        $header = array(
            'App-Key:n19jmcy5nsuh9',
            'Nonce:'.$Nonce,
            'Timestamp:'.$Timestamp,
            'Signature:'.$Signature,
            'Content-Type: application/x-www-form-urlencoded'
            );
        $return = Common::httpRequest($url,'POST',$data,$header);
        $ret = json_decode($return,true);
        if($ret['code']==200){
            $this->model->table("user")->data(array('rongyun_token' => $ret['token']))->where('id=' . $user_id)->update();
            $this->code = 0;
            $this->content = $ret['token'];
            return;
        }else{
            $this->code = 0;
            $this->content = $ret;
            return;
        }
         
    }

    /*
     * 商家认证接口
     */
    public function shop_check(){
       $type = Filter::int(Req::args('type')); //1实体商家 2个人微商
       $business_licence = Req::args('business_licence'); //营业执照
       $positive_idcard = Req::args('positive_idcard'); //身份证正面照
       $native_idcard = Req::args('native_idcard'); //身份证反面照
       $account_picture = Req::args('account_picture'); //开户许可证照
       $account_card = Req::args('account_card'); //结算银行卡号
       $bank_name = Req::args('bank_name'); //银行卡信息
       $shop_photo = Req::args('shop_photo'); //门店照
       $hand_idcard = Req::args('hand_idcard'); //手持身份证照

       $shop = $this->model->table('district_promoter')->fields('id')->where('user_id='.$this->user['id'])->find();
       if(!$shop){
        $this->code = 1166;
        return;
       }
       if(!$positive_idcard){
        $this->code = 1220;
        return;
       }
       if(!$native_idcard){
        $this->code = 1221;
        return;
       }
       if(!$account_card){
        $this->code = 1223;
        return;
       }
       if($type==1){
            if(!$business_licence){
                $this->code = 1219;
                return;
            }
            // if(!$account_picture){
            //     $this->code = 1222;
            //     return;
            // }
            if(!$shop_photo){
                $this->code = 1224;
                return;
            }
       }elseif($type==2){
          if(!$hand_idcard){
                $this->code = 1231;
                return;
            }
       }else{
         $this->code = 1225;
         return;
       }
       $this->model->table('district_promoter')->data(array('shop_type'=>$type))->where('user_id='.$this->user['id'])->update();
       
       $data = array(
        'user_id'=>$this->user['id'],
        'type'=>$type,
        'business_licence'=>$business_licence,
        'positive_idcard'=>$positive_idcard,
        'native_idcard'=>$native_idcard,
        'account_picture'=>$account_picture,
        'account_card'=>$account_card,
        'bank_name'=>$bank_name,
        'shop_photo'=>$shop_photo,
        'hand_idcard'=>$hand_idcard,
        'status'=>0,
        'create_date'=>date('Y-m-d H:i:s')
        );
       $shop_check = $this->model->table('shop_check')->fields('status')->where('user_id='.$this->user['id'])->find();
       if($shop_check){
          if($shop_check['status']==0){
             $this->code = 1227;
             return;
          }elseif($shop_check['status']==1){
             $this->code = 1228;
             return;
          }else{
             $result = $this->model->table('shop_check')->data($data)->where('user_id='.$this->user['id'])->update();
          }
       }else{
        $result = $this->model->table('shop_check')->data($data)->insert();
       }
       
       if($result){
        $this->code = 0;
        return;
       }else{
         $this->code = 1226;
         return;
       }
    }

    public function shop_checked(){
        $shop = $this->model->table('district_promoter')->fields('id')->where('user_id='.$this->user['id'])->find();
       if(!$shop){
        $this->code = 1166;
        return;
       }
        $shop_check = $this->model->table('shop_check')->fields('id,status')->where('user_id='.$this->user['id'])->find();
        if(!$shop_check){
            $need_check = 0; //没认证
        }elseif($shop_check['status']==1){
          $need_check = 1; //通过认证
        }elseif($shop_check['status']==0){
          $need_check = 2; //认证审核中
        }else{
          $need_check = -1; //认证失败  
        }
        $this->code = 0;
        $this->content = $need_check;
    }

    public function shop_register(){

        //获取token
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
        // $myParams['biz_content'] = json_encode($biz_content_arr, JSON_UNESCAPED_UNICODE);//构造字符串
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
        // echo "<pre>";
        // print_r($myParams);
        // echo "<pre>";
        $ret = Common::httpRequest($url,'POST',$myParams);
        $ret = json_decode($ret,true);
        // var_dump($ret);die;
        if($ret['ysepay_merchant_register_token_get_response']['code'] == 10000 && $ret['ysepay_merchant_register_token_get_response']['msg'] =='Success'){
            // if($this->user['id']==42608){    
            //     $data = array(
            //         'picType'=>'00',
            //         'picFile'=>$_FILES['positive_idcard'],
            //         // 'picFile'=>json_encode($_FILES['positive_idcard'], JSON_UNESCAPED_UNICODE),
            //         'token'=>$ret['ysepay_merchant_register_token_get_response']['token'],
            //         'superUsercode'=>'yuanmeng'
            //         );
            //     $act = "https://uploadApi.ysepay.com:2443/yspay-upload-service?method=upload";
            //     $result = Common::httpRequest($act,'POST',$data);
            //     var_dump($data);
            //     print_r($result);die;
            // }
            $params = array();  
        
            $params['method'] = 'ysepay.merchant.register.accept';
            $params['partner_id'] = 'yuanmeng';
            // $params['partner_id'] = 'js_test';
            // $params['partner_id'] = $this->user['id'];
            $params['timestamp'] = date('Y-m-d H:i:s', time());
            // $params['timestamp'] = '2018-08-17 18:02:23';
            // $params['charset'] = 'GBK';
            $params['charset'] = 'utf-8';
            $params['notify_url'] = 'http://api.test.ysepay.net/atinterface/receive_return.htm';
            // $params['notify_url'] = 'http://127.0.0.1';      
            $params['sign_type'] = 'RSA';  
              
            $params['version'] = '3.0';
            $legal_cert_no = $this->des_encrypt($_POST['legal_cert_no'],'yuanmeng');
            $cert_no = $this->des_encrypt($_POST['cert_no'],'yuanmeng');
            // $legal_cert_no = str_replace('+','%2B',$legal_cert_no);
            // $cert_no = str_replace('+','%2B',$cert_no);
          $biz_content_arr = array(
            'merchant_no'=>'yuanmeng',
            // 'merchant_no'=>'test',
            'cust_type'=>$_POST['cust_type'],
            'token'=>$ret['ysepay_merchant_register_token_get_response']['token'],
            // 'token'=>'1',
            'another_name'=>$_POST['another_name'],
            'cust_name'=>$_POST['cust_name'],
            'mer_flag'=>'11',
            'industry'=>$_POST['industry'],
            'province'=>$_POST['province'],
            'city'=>$_POST['city'],
            'company_addr'=>$_POST['company_addr'],
            'legal_name'=>$_POST['legal_name'],
            'legal_tel'=>$_POST['legal_tel'],
            'legal_cert_type'=>'00',
            "legal_cert_expire"=>"20250825",
            'legal_cert_no'=>$legal_cert_no,
            // 'legal_cert_no'=>'CRZlyoFPZgcIffVvx04XBisuo9tvo60Z',  
            'settle_type'=>'1',
            'bank_account_no'=>$_POST['bank_account_no'],
            'bank_account_name'=>$_POST['bank_account_name'],
            'bank_account_type'=>'personal',
            'bank_card_type'=>'debit',
            'bank_name'=>$_POST['bank_name'],
            'bank_type'=>$_POST['bank_type'],
            'bank_province'=>$_POST['bank_province'],
            'bank_city'=>$_POST['bank_city'],
            'cert_type'=>'00',
            'cert_no'=>$cert_no,
            // 'cert_no'=>'CRZlyoFPZgcIffVvx04XBisuo9tvo60Z',
            'bank_telephone_no'=>$_POST['bank_telephone_no']
            );
            $params['biz_content'] = json_encode($biz_content_arr, JSON_UNESCAPED_UNICODE);//构造字符串
            ksort($params);
            $signStrs = "";
            foreach ($params as $key => $val) {
                $signStrs .= $key . '=' . $val . '&';
            }
            $signStrs = rtrim($signStrs, '&');
            $sign = $this->sign_encrypt(array('data' => $signStrs));
            $params['sign'] = trim($sign['check']);
            $url1 = 'https://register.ysepay.com:2443/register_gateway/gateway.do';
            // $params['biz_content'] = rawurlencode($params['biz_content']);
            // $params['sign'] = rawurlencode($params['sign']);
            $res = Common::httpRequest($url1,'POST',$params);
            var_dump($params);
            $res = json_decode($res,true);
            $ret = $this->sign_check($res['sign'], $signStrs);
            var_dump($res['sign']);
            var_dump($signStrs);
            var_dump($ret);
            var_dump($res);die;
            $this->code = 0;
            return;
        }else{
            $this->code = 1229;
            return;
        }
    }

    public function sign_check($sign, $data)
    {
        $businessgatecerpath = "./protected/classes/yinpay/certs/businessgate.cer";
        $publickeyFile = $businessgatecerpath; //公钥
        $certificateCAcerContent = file_get_contents($publickeyFile);
        $certificateCApemContent = '-----BEGIN CERTIFICATE-----' . PHP_EOL . chunk_split(base64_encode($certificateCAcerContent), 64, PHP_EOL) . '-----END CERTIFICATE-----' . PHP_EOL;
        // 签名验证
        $success = openssl_verify($data, base64_decode($sign), openssl_get_publickey($certificateCApemContent), OPENSSL_ALGO_SHA1);

        return $success;
    }

    public function des_encrypt($data, $key)
    {
        $encrypted = openssl_encrypt($data, 'DES-ECB', $key, 1);
        return base64_encode($encrypted);
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

    public function sign_encrypts($input)
    {
        // $pfxpath = 'http://' . $_SERVER['HTTP_HOST'] . "/trunk/protected/classes/yinpay/certs/shanghu_test.pfx";
        $pfxpath = "./protected/classes/yinpay/certs/js_test.pfx";
        $pfxpassword = '123456';
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

    public function yin_df_test(){
        $order_no = $_POST['order_no'];
        $order = $this->model->table('order_offline')->fields('order_amount,shop_ids')->where('order_no='.$order_no)->find();
        $myParams = array();
        $myParams['charset'] = 'utf-8';
        $myParams['method'] = 'ysepay.df.single.quick.accept';
        $myParams['notify_url'] = 'http://yspay.ngrok.cc/pay/respond_notify.php';
        $myParams['partner_id'] = 'yuanmeng';
        $myParams['sign_type'] = 'RSA';
        $myParams['timestamp'] = date('Y-m-d H:i:s', time());
        $myParams['version'] = '3.0';
        $biz_content_arr = array(
            "out_trade_no" => $order_no,
            "business_code" => "2010002",
            "currency" => "CNY",
            "total_amount" => $order['order_amount'],
            "subject" => "测试",
            "bank_name" => "中国建设银行江西分行昌北支行",
            "bank_city" => "南昌市",
            "bank_account_no" => "6227002021490888887",
            "bank_account_name" => "潜非凡",
            "bank_account_type" => "personal",
            "bank_card_type" => "debit",
            'shopdate'=>date('Ymd', time())
        );
        $myParams['biz_content'] = json_encode($biz_content_arr, JSON_UNESCAPED_UNICODE);//构造字符串
        // var_dump($myParams);
        ksort($myParams);
        $signStr = "";
        foreach ($myParams as $key => $val) {
            $signStr .= $key . '=' . $val . '&';
        }
        $signStr = rtrim($signStr, '&');
        // var_dump($signStr);
        $sign = $this->sign_encrypt(array('data' => $signStr));
        $myParams['sign'] = trim($sign['check']);
        // var_dump($myParams);
        $act = "https://df.ysepay.com/gateway.do";
        $result = Common::httpRequest($act,'POST',$myParams);
        $result = json_decode($result,true);
        var_dump($result);die;
        return $myParams;
    }

    public function yin_fz_test(){
        $order_no = $_POST['order_no'];
        $order = $this->model->table('order_offline')->fields('order_amount,shop_ids')->where('order_no='.$order_no)->find();
        $shop = $this->model->table('district_promoter')->fields('partner_id,base_rate')->where('user_id='.$order['shop_ids'])->find();
        $rate = 1.0;
        $myParams = array();
        $myParams['method'] = 'ysepay.single.division.online.accept';
        $myParams['partner_id'] = 'yuanmeng';
        $myParams['timestamp'] = date('Y-m-d H:i:s', time());
        $myParams['charset'] = 'GBK';
        $myParams['sign_type'] = 'RSA';
        $myParams['version'] = '3.0';
        $div_list = array();
        $div_list[0] = array(
                'division_mer_usercode'=>$shop['partner_id'],
                'div_amount'=>sprintf('%.2f',$order['order_amount']*(100-$shop['base_rate'])/100),
                'div_ratio'=>$rate,
                'is_chargeFee'=>'01'
                );
        $biz_content_arr = array(
            "out_batch_no" =>'S'.substr($order_no,0,15),
            "out_trade_no" => $order_no,
            'payee_usercode' => 'yuanmeng',
            // "org_no" => "6584000000",
            // "org_no" => "",
            "division_mode" => "01",
            "total_amount" => $order['order_amount'],
            "is_divistion" => "01",
            "is_again_division" => "N",
            "div_list" => $div_list
        );
        $myParams['biz_content'] = json_encode($biz_content_arr, JSON_UNESCAPED_UNICODE);//构造字符串
        // var_dump($myParams);
        ksort($myParams);
        $signStr = "";
        foreach ($myParams as $key => $val) {
            $signStr .= $key . '=' . $val . '&';
        }
        $signStr = rtrim($signStr, '&');
        // var_dump($signStr);
        $sign = $this->sign_encrypt(array('data' => $signStr));
        $myParams['sign'] = trim($sign['check']);
        // var_dump($myParams);
        $act = "https://commonapi.ysepay.com/gateway.do";
        $result = Common::httpRequest($act,'POST',$myParams);
        $result = json_decode($result,true);
        var_dump($result);die;
        return $myParams;
    }

    public function get_my_sign_info(){
        $date = date("Y-m-d");
        $yesterday = date("Y-m-d",strtotime("-1 day"));
        $signed = $this->model->table("sign_in")->where("date='$date' and user_id=".$this->user['id'])->find();
        $last_signed = $this->model->table("sign_in")->where("date='$yesterday' and user_id=".$this->user['id'])->find();
        if($signed && $last_signed){ //今天昨天都签到了
            $continue_sign_days = $signed['serial_day'];
        }elseif($signed && !$last_signed){ //今天签到了，昨天没签
            $continue_sign_days = 1; 
        }elseif(!$signed && $last_signed){ // 今天没签，昨天签了
            $continue_sign_days = $last_signed['serial_day'];
        }else{
            $continue_sign_days = 0; //今天昨天都没签
        }
        $this->code = 0;
        $this->content['is_signed'] = $signed?1:0;
        $this->content['continue_sign_days'] = $continue_sign_days;
    }

    public function mobile_exist(){
        $mobile = $_POST['mobile'];
        $exist = $this->model->table('customer')->fields('mobile')->where("mobile='$mobile'")->find();
        $this->code = 0;
        $this->content = $exist?1:0; 
    }

    public function payPwdValid() {
        $pay_password = Filter::str(Req::args('pay_password'));
        if(!$pay_password) {
            $this->code = 1260;
            return;
        }
        $result = $this->model->table('customer')->where('user_id=' . $this->user['id'])->fields('pay_password_open,pay_password,pay_validcode')->find();
        if ($result['pay_password'] == CHash::md5($pay_password, $result['pay_validcode'])) {
            $this->code = 0;
            $this->content = 'success';
            return;
        } else {
            $this->code = 1060;
            return;
        }
    }

    public function build_inviteship_qrcode() {
        $inviter_id = Filter::int(Req::args('inviter_id'));
        if(!$inviter_id) {
            $this->code = 1266;
            return;
        }
        $invite = $this->model->table('invite')->where('invite_user_id='.$this->user['id'])->find();
        if($invite) {
            $this->code = 1267;
            return;
        }
        if($inviter_id==$this->user['id']) {
            $this->code = 1268;
            return;
        }
        $invited = $this->model->table('invite')->where('user_id = '.$this->user['id'].' and invite_user_id='.$inviter_id)->find();
        if($invited) {
            $this->code = 1269;
            return;
        }
        Common::buildInviteShip($inviter_id, $this->user['id'], "wechat");
        $this->code = 0;
        return;
    }

    public function get_my_inviter() {
        $inviter_id = Common::getInviterId($this->user['id']);
        $inviter_name = Common::getInviterName($this->user['id']);
        $this->code = 0;
        $this->content['inviter_id'] = $inviter_id;
        $this->content['inviter_name'] = $inviter_name;
        return;
    }
}
