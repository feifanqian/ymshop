<?php
class ActiveController extends Controller
{
    public $layout = 'recruit';
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

    public function recruit() {
        $user_id = $this->user['id'];
        if($user_id) {
            $this->assign("token", $this->user['token']);
            // if($user_id==42608){
            //     var_dump($this->user);die;
            // }
            $customer = $this->model->table("customer as cu")->fields("cu.*,u.avatar")->join("left join user as u on cu.user_id = u.id")->where("cu.user_id = $user_id")->find();
            $this->assign("user", $customer);
            $list = $this->model->table("invite as i")->fields("FROM_UNIXTIME(i.createtime) as create_time,u.nickname,u.avatar,cu.real_name")->join("left join user as u on i.invite_user_id = u.id LEFT JOIN customer AS cu ON i.invite_user_id=cu.user_id")->where("i.from='active' and i.user_id=".$user_id)->limit(4)->findAll();
            // $invite_num = count($list);
            $sign_up = $this->model->table("invite_active")->where("user_id = ".$user_id)->find();
            $invite = $this->model->table("invite")->fields('count(id) as num')->where("from = 'active' and user_id = ".$user_id)->findAll();
            // $invite_num = empty($sign_up)?0:$sign_up['invite_num'];
            $invite_num = $invite[0]['num'];
            $signed = $sign_up?1:0;
            if($sign_up) {
               if($invite_num>=800) {
                    $status1 = $sign_up['status1']==0?1:2;
                    $status2 = $sign_up['status2']==0?1:2;
                    $status3 = $sign_up['status3']==0?1:2;
                    $num = 1000;
                } elseif($invite_num>200 && $invite_num<800) {
                    $status1 = $sign_up['status1']==0?1:2;
                    $status2 = $sign_up['status2']==0?1:2;
                    $status3 = 0;
                    $num = 800;
                } elseif($invite_num>38 && $invite_num<200) {
                    $status1 = $sign_up['status1']==0?1:2;
                    $status2 = 0;
                    $status3 = 0;
                    $num = 200;
                } else {
                    $status1 = 0;
                    $status2 = 0;
                    $status3 = 0;
                    $num = 38;
                }
                $end_time = $sign_up['end_time'];
                 
            } else {
                $end_time = date('Y-m-d H:i:s',strtotime('+1 day'));
                $this->redirect('/active/sign_up');
                $status1 = 0;
                $status2 = 0;
                $status3 = 0;
                $num = 38;
            }
            
        } else {
            $this->redirect('/active/sign_up');
            $invite_num = 0;
            $list = [];
            $signed = 0;
            $status1 = 0;
            $status2 = 0;
            $status3 = 0;
            $num = 38;
            $end_time = date('Y-m-d H:i:s',strtotime('+1 day'));
        }
        $chance = floor($invite_num/3);
        $status = array('0'=>'未达成','1'=>'可领取','2'=>'已领取');
        
        //判断设备
        $agent = strtolower($_SERVER['HTTP_USER_AGENT']);
         $type ='other';
         //分别进行判断
         if(strpos($agent,'iphone') || strpos($agent,'ipad'))
        {
         $type ='ios';
         }
          
         if(strpos($agent,'android'))
        {
         $type ='android';
         }
        $out_time = (strtotime($end_time)-time())*1000;
        $rest_num = 3-$invite_num%3; 
        $this->assign("type", $type);
        $this->assign("status1", $status1);
        $this->assign("status2", $status2);
        $this->assign("status3", $status3);
        $this->assign("status", $status);
        $this->assign("chance", $chance);
        $this->assign("signed", $signed);
        $this->assign("invite_num", $invite_num);
        $this->assign("num", $num);
        $this->assign("list", $list);
        $this->assign("out_time", $out_time);
        $this->assign("rest_num", $rest_num);
    	$this->redirect();
    }

    public function login() {
        $redirectURL = Filter::str(Req::args("redirect"));
        $inviter = Filter::int(Req::args("inviter"));
        $this->assign("inviter", $inviter);
        $this->assign("redirectURL", $redirectURL);
        $this->safebox->clear('user');
        $cookie = new Cookie();
        $cookie->setSafeCode(Tiny::app()->getSafeCode());
        $cookie->set('autologin', null, 0);
    	$this->redirect();
    }

    public function login_act() {
        $redirectURL = Filter::str(Req::args("redirect"));
        $inviter = Filter::int(Req::args("inviter"));
        if(!$inviter) {
            $inviter = Cookie::get('active_inviter');
            Cookie::clear('active_inviter');
        }
        $this->assign("redirectURL", $redirectURL);
        $account = Filter::str(Req::args('account'));
        $passWord = Filter::str(Req::args('password'));
        $autologin = Req::args("autologin");
        if ($autologin == null)
            $autologin = 0;
        $model = $this->model->table("user as us");
        $obj = $model->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.group_id,cu.user_id,cu.login_time,cu.mobile,cu.real_name")->where("cu.mobile='$account' and cu.status=1")->find();
        if ($obj) {
            if ($obj['status'] == 1) {
                $token = CHash::random(32, 'char');
                if ($obj['password'] == CHash::md5($passWord, $obj['validcode'])) {
                    $cookie = new Cookie();
                    $cookie->setSafeCode(Tiny::app()->getSafeCode());
                    $obj['token'] = $token;
                    if ($autologin == 1) {
                        $this->safebox->set('user', $obj, $this->cookie_time);
                        $cookie->set('autologin', array('account' => $account, 'password' => $obj['password']), $this->cookie_time);
                    } else {
                        $cookie->set('autologin', null, 0);
                        $this->safebox->set('user', $obj, 1800);
                    }
                    $this->model->table("customer")->data(array('login_time' => date('Y-m-d H:i:s')))->where('user_id=' . $obj['id'])->update();

                    $this->model->table("user")->data(array('token' => $token, 'expire_time' => date('Y-m-d H:i:s', strtotime('+1 day'))))->where('id=' . $obj['id'])->update();
                    if($inviter) {
                        Common::buildInviteShip($inviter, $obj['id'], 'active');    
                    } 
                    if ($redirectURL=='recruit'){
                        $this->redirect("/active/recruit");
                    } elseif ($redirectURL=='sign_up'){
                        $this->redirect("/active/recruit");
                    } elseif ($redirectURL=='fill_info'){
                        $this->redirect("/travel/fill_info");
                    } elseif ($redirectURL=='order_list'){
                        $this->redirect("/travel/order_list");
                    } elseif ($redirectURL=='pay'){
                        $id = Filter::int(Req::args("id"));
                        $this->redirect("/travel/pay/id/{$id}");
                    }else {
                        $this->redirect("/active/recruit");
                        // $url = Cookie::get('url');
                        // $url = $url!=NULL?$url:'/active/recruit';
                        // if(strpos($url, '/')!==0){
                        //     $url = "/".$url;
                        // }
                        // header("Location:$url");
                        // exit;
                    }    
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

    public function sign_up() {
        $user_id = $this->user['id'];
        $inviter = Filter::int(Req::args("inviter"));
        if($inviter) {
            Cookie::set('active_inviter',$inviter);
        } else {
            $inviter = 0;
        }
        if($user_id) {
            $customer = $this->model->table("customer as cu")->fields("cu.*,u.avatar")->join("left join user as u on cu.user_id = u.id")->where("cu.user_id = $user_id")->find();
            $this->assign("user", $customer);
            $sign_up = $this->model->table("invite_active")->where("user_id = ".$user_id)->find();
            $signed = $sign_up?1:0;
        } else {
            $signed = 0;
        } 
        $this->assign("inviter", $inviter);
        $this->assign("signed", $signed);
        $this->redirect();
    }

    public function sign_up_act() {
        $user_id = Filter::int(Req::args("user_id"));
        $data = array(
            'user_id'=>$user_id,
            'invite_num'=>0,
            'sign_time'=>date('Y-m-d H:i:s'),
            'end_time'=>date("Y-m-d",strtotime('+ 30 days'))
            );
        $this->model->table('invite_active')->data($data)->insert();
        echo JSON::encode(array('status' => 'success'));
    }

    public function inviteregist() {
        $user_id = $this->user['id'];
        if($user_id) {
            $list = $this->model->table("invite as i")->fields("FROM_UNIXTIME(i.createtime) as create_time,u.nickname,u.avatar,cu.real_name")->join("left join user as u on i.invite_user_id = u.id LEFT JOIN customer AS cu ON i.invite_user_id=cu.user_id")->where("i.from='active' and i.user_id=".$user_id)->findAll();
            $this->assign("user_id", $user_id);
        } else {
            $list = [];
            $this->redirect('/active/login/redirect/recruit');
        }
        $wechatcfg = $this->model->table("oauth")->where("class_name='WechatOAuth'")->find();
        $wechat = new WechatMenu($wechatcfg['app_key'], $wechatcfg['app_secret'], '');
        $token = $wechat->getAccessToken();

        $jssdk = new JSSDK($wechatcfg['app_key'], $wechatcfg['app_secret']);
        $signPackage = $jssdk->GetSignPackage();
        $is_weixin = Common::checkInWechat();
        $this->assign("is_weixin", $is_weixin);
        $this->assign("list", $list);
        $this->assign("signPackage", $signPackage);
        $this->redirect();
    }

    public function travel_detail() {
        $user_id = $this->user['id'];
        if($user_id) {
            $sign_up = $this->model->table("invite_active")->where("user_id = ".$user_id)->find();
            $invite_num = empty($sign_up)?0:$sign_up['invite_num'];
            $get_status = empty($sign_up)?0:$sign_up['status3'];
        } else {
            $invite_num = 0;
            $user_id = 0;
            $get_status = 0;
        }
        $this->assign("user_id", $user_id);
        $status = $invite_num>=38?1:0;
        $this->assign("status", $status);
        $this->assign("get_status", $get_status);
        $this->redirect();
    }

    public function watch_detail() {
        $user_id = $this->user['id'];
        if($user_id) {
            $sign_up = $this->model->table("invite_active")->where("user_id = ".$user_id)->find();
            $invite_num = empty($sign_up)?0:$sign_up['invite_num'];
            $get_status = empty($sign_up)?0:$sign_up['status1'];     
        } else {
            $invite_num = 0;
            $user_id = 0;
            $get_status = 0;
        }
        $this->assign("user_id", $user_id);
        $status = $invite_num>=800?1:0;
        $this->assign("status", $status);
        $this->assign("get_status", $get_status);
        $this->redirect();
    }

    public function six_detail() {
        $user_id = $this->user['id'];
        if($user_id) {
            $sign_up = $this->model->table("invite_active")->where("user_id = ".$user_id)->find();
            $invite_num = empty($sign_up)?0:$sign_up['invite_num'];
            $get_status = empty($sign_up)?0:$sign_up['status2'];     
        } else {
            $invite_num = 0;
            $user_id = 0;
            $get_status = 0;
        }
        $this->assign("user_id", $user_id);
        $status = $invite_num>=200?1:0;
        $this->assign("status", $status);
        $this->assign("get_status", $get_status);
        $this->redirect();
    }

    public function open_redbag() {
        $user_id = Filter::int(Req::args("user_id"));
        $active = $this->model->table('invite_active')->where('user_id='.$user_id)->find();
        $this->model->table('invite_active')->data(['invite_num'=>$active['invite_num']-3])->where('user_id='.$user_id)->update();
        $data = array(
            'user_id'=>$user_id,
            'title'=>'拉新活动积分奖',
            'type'=>1,
            'amount'=>12.00,
            'create_time'=>date('Y-m-d H:i:s'),
            'end_time'=>date("Y-m-d",strtotime('+ 30 days')),
            'status'=>1
            );
        $this->model->table('active_voucher')->data($data)->insert();
        echo JSON::encode(array('status' => 'success'));
    }

    public function get_voucher() {
        $user_id = Filter::int(Req::args("user_id"));
        $type = Filter::int(Req::args("type"));
        switch ($type) {
            case 1:
                $title = '积分券';
                $amount = 12;
                break;
            case 2:
                $title = '现金券';
                $amount = 600;
                break;
            case 3:
                $title = '港澳游';
                $amount = 3988;
                break;
            case 4:
                $title = '商品券';
                $amount = 2680;
                break;  
            default:
                $title = '积分券';
                $amount = 12;
                break;
        }
        
        $data = array(
            'user_id'=>$user_id,
            'title'=>$title,
            'type'=>$type,
            'amount'=>$amount,
            'create_time'=>date('Y-m-d H:i:s'),
            'end_time'=>date("Y-m-d",strtotime('+ 30 days')),
            'status'=>1
            );
        $this->model->table('active_voucher')->data($data)->insert();
        if($type==3) {
            $this->model->table('invite_active')->data(['status3'=>1])->where('user_id='.$user_id)->update();
        }
        if($type==4) {
            $this->model->table('invite_active')->data(['status1'=>1])->where('user_id='.$user_id)->update();
        }
        if($type==2) {
            $this->model->table('invite_active')->data(['status2'=>1])->where('user_id='.$user_id)->update();
        }
        //清零邀请人数
        $this->model->table('invite_active')->data(['invite_num'=>0])->where('user_id='.$user_id)->update();
        echo JSON::encode(array('status' => 'success'));
    }

    public function user_voucher() {
        var_dump(111);die;
        $id = Filter::int(Req::args("id"));
        $voucher = $this->model->table('active_voucher')->where('id='.$id)->find();
        if($voucher['type']==1) {
            $point = $voucher['amount'];
            $this->model->table('customer')->data(array('point_coin'=>"`point_coin`+({$point})"))->where('user_id='.$voucher['user_id'])->update();
            Log::pointcoin_log($point,$voucher['user_id'], '', "积分卡券兑换", 13);
        } elseif($voucher['type']==2) {
            $point = $voucher['amount'];
            $this->model->table('customer')->data(array('balance'=>"`balance`+({$point})"))->where('user_id='.$voucher['user_id'])->update();
            Log::balance($point,$voucher['user_id'], '', "余额卡券兑换", 16);
        }
        $ret = $this->model->table('active_voucher')->data(array('status'=>0))->where('id='.$id)->update();
        var_dump($ret);die;
        echo JSON::encode(array('status' => 'success'));
    }

    public function address() {
        $id = Filter::int(Req::args("id"));
        $this->assign("id", $id);
        $this->redirect();
    }

    public function address_save() {
        $id = Filter::int(Req::args("id"));
        $this->model->table('active_voucher')->data(array('status'=>0))->where('id='.$id)->update();
        $data = array(
            'user_id'=>$this->user['id'],
            'accept_name'=>Filter::str(Req::args('accept_name')),
            'mobile'=>Filter::str(Req::args('mobile')),
            'province'=>Filter::int(Req::args('province')),
            'city'=>Filter::int(Req::args('city')),
            'county'=>Filter::int(Req::args('county')),
            'addr'=>Filter::str(Req::args('addr')),
            'is_default'=>0
            );
        $address_id = $this->model->table("address")->data($data)->insert();
        $gift_product = 2729;
        $gift_num = 1;
        $product = $this->model->table('products as p')->where("p.id = $gift_product")->join("left join goods as g on p.goods_id = g.id")->fields("p.*,g.shop_id")->find();

        $datas['type']=0;
        $datas['order_no'] = Common::createOrderNo();
        $datas['user_id'] = $this->user['id'];
        $datas['payment'] = 1;
        $datas['status'] = 3; 
        $datas['pay_status'] = 1;
        $datas['accept_name'] = $data['accept_name'];
        $datas['phone'] = $data['mobile'];
        $datas['mobile'] = $data['mobile'];
        $datas['province'] = $data['province'];
        $datas['city'] = $data['city'];
        $datas['county'] = $data['county'];
        $datas['addr'] = Filter::text($data['addr']);
        $datas['zip'] = '';
        $datas['payable_amount'] = $product['sell_price']*$gift_num;
        $datas['payable_freight'] = 0;
        $datas['real_freight'] = 0;
        $datas['create_time'] = date('Y-m-d H:i:s');
        $datas['pay_time'] = date("Y-m-d H:i:s");
        $datas['is_invoice'] = 0;
        $datas['handling_fee'] = 0;
        $datas['invoice_title'] = '';
        $datas['taxes'] = 0;
        $datas['discount_amount'] = 0;
        $datas['order_amount'] = $product['sell_price']*$gift_num;
        $datas['real_amount'] = $product['sell_price']*$gift_num;
        $datas['point'] = 0;
        $datas['voucher_id'] = 0;
        $datas['voucher'] = serialize(array());
        $datas['prom_id']=0;
        $datas['admin_remark']="自动创建订单，来自于拉新活动奖励";
        $datas['shop_ids']=$product['shop_id'];
        $order_id =$this->model->table('order')->data($datas)->insert();

        $tem_data['order_id'] = $order_id;
        $tem_data['goods_id'] = $product['goods_id'];
        $tem_data['product_id'] = $product['id'];
        $tem_data['shop_id'] = $product['shop_id'];
        $tem_data['goods_price'] = $product['sell_price'];
        $tem_data['real_price'] = $product['sell_price'];
        $tem_data['goods_nums'] = $gift_num;
        $tem_data['goods_weight'] = $product['weight'];
        $tem_data['prom_goods'] = serialize(array());
        $tem_data['spec'] = serialize($product['spec']);
        $this->model->table("order_goods")->data($tem_data)->insert();

        $this->model->table("products")->where("id=" . $gift_product)->data(array('store_nums' => "`store_nums`-" . $gift_num))->update();//更新库存
        $this->model->table('goods')->data(array('store_nums' => "`store_nums`-" . $gift_num))->where('id=' . $product['goods_id'])->update();

        $this->redirect("/ucenter/order/status/undelivery");
    }

    public function auto_check_token() {
        $user_id = Filter::int(Req::args("user_id"));
        $token = Filter::str(Req::args("token"));
        $user = $this->model->table('user')->fields('token')->where('id='.$user_id)->find();
        if($user['token']==$token) {
            $status = 'success';
        } else {
            $status = 'error';
        }
        echo JSON::encode(array('status' => $status,'token'=>$token,'tokens'=>$user['token']));
    }

    public function find_pwd()
    {
        $this->redirect();
    }

    public function find_password()
    {
        $mobile = Filter::str(Req::args("mobile"));
        $code = Filter::str(Req::args("code"));
        $password = Filter::str(Req::args("password"));
        $repassword = Filter::str(Req::args("repassword"));
        if($password!=$repassword) {
            $info = array('field' => 'password', 'msg' => '两次密码输入不一致！');
        }
        $verifiedInfos = Session::get("verifiedInfos");
        if (isset($verifiedInfos['code']) && $code == $verifiedInfos['code']) {
        // $pass = $this->sms_verify($code, $mobile);
        // if($pass) {
            $customer = $this->model->table('customer')->where('status=1 and mobile='.$mobile)->find();
            if(!$customer) {
               $info = array('field' => 'mobile', 'msg' => '手机号错误！');
            } else {
                $validcode = CHash::random(8);
                $this->model->table('user')->data(array('password' => CHash::md5($password, $validcode), 'validcode' => $validcode))->where('id=' . $customer['user_id'])->update();
                $info = array('field' => '', 'msg' => 'success');
            }  
            // $this->redirect('/active/login');
        } else {
            $info = array('field' => 'code', 'msg' => '验证码错误！');
        }
        $this->assign("invalid", $info);
        $this->redirect("find_pwd", false, Req::args());
    }

    public function send_code()
    {
        $mobile = Filter::str(Req::args("mobile"));
        $code = CHash::random(4, 'int');
        $info = array('status' => 'fail', 'msg' => '');
        $random = CHash::random(20, 'char');
        $verifiedInfos = Session::get('verifiedInfos');
        $sendAble = true;
        $haveTime = 120;

        if (isset($verifiedInfos['time'])) {
            $time = $verifiedInfos['time'];
            $haveTime = time() - $time;
            if ($haveTime <= 120) {
                $sendAble = false;
            }
        }
        if ($sendAble) {
            $verifiedInfos = array('code' => $code, 'time' => time(), 'random' => $random);
            $sms = SMS::getInstance();
            $result = $sms->sendCode($mobile, $code);
            if ($result['status'] == 'success') {
                $info = array('status' => 'success', 'msg' => $result['message']);
                Session::set('verifiedInfos', $verifiedInfos);
                $info = array('status' => 'success');
            } else {
                $info = array('status' => 'fail', 'msg' => $result['message']);
            }
        }
        
        $info['haveTime'] = (120 - $haveTime);
        echo JSON::encode($info);
    }

    public function sms_verify($code, $mobile) {
        $url = "https://webapi.sms.mob.com/sms/verify";
        $appkey = "1f4d2d20dd266";
        $return = $this->postRequest($url, array('appkey' => $appkey,
            'phone' => $mobile,
            'zone' => '86',
            'code' => $code,
        ));
        $flag = json_decode($return, true);
        if ($flag['status'] == 200) {
            return true;
        } else {
            var_dump($flag);die;
            return false;
        }
    }

    public function postRequest($api, array $params = array(), $timeout = 30) {
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
}
?>