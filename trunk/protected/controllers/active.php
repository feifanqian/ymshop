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
            $customer = $this->model->table("customer as cu")->fields("cu.*,u.avatar")->join("left join user as u on cu.user_id = u.id")->where("cu.user_id = $user_id")->find();
            $this->assign("user", $customer);
            $list = $this->model->table("invite as i")->fields("FROM_UNIXTIME(i.createtime) as create_time,u.nickname,u.avatar,cu.real_name")->join("left join user as u on i.invite_user_id = u.id LEFT JOIN customer AS cu ON i.invite_user_id=cu.user_id")->where("i.from='active' and i.user_id=".$user_id)->limit(4)->findAll();
            // $invite_num = count($list);
            $sign_up = $this->model->table("invite_active")->where("user_id = ".$user_id)->find();
            $invite_num = empty($sign_up)?0:$sign_up['invite_num'];
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
            } else {
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
        }
        $chance = floor($invite_num/3);
        $status = array('0'=>'未达成','1'=>'可领取','2'=>'已领取');
        
        $this->assign("status1", $status1);
        $this->assign("status2", $status2);
        $this->assign("status3", $status3);
        $this->assign("status", $status);
        $this->assign("chance", $chance);
        $this->assign("signed", $signed);
        $this->assign("invite_num", $invite_num);
        $this->assign("num", $num);
        $this->assign("list", $list);
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
                    if($inviter) {
                        Common::buildInviteShip($inviter, $obj['id'], 'active');    
                    } 
                    if ($redirectURL=='recruit'){
                        $this->redirect("/active/recruit");
                    } elseif ($redirectURL=='sign_up'){
                        $this->redirect("/active/recruit");
                    } else {
                        $url = Cookie::get('url');
                        $url = $url!=NULL?$url:'/ucenter/index';
                        if(strpos($url, '/')!==0){
                            $url = "/".$url;
                        }
                        header("Location:$url");
                        exit;
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
        if($user_id) {
            $customer = $this->model->table("customer as cu")->fields("cu.*,u.avatar")->join("left join user as u on cu.user_id = u.id")->where("cu.user_id = $user_id")->find();
            $this->assign("user", $customer);
            $sign_up = $this->model->table("invite_active")->where("user_id = ".$user_id)->find();
            $signed = $sign_up?1:0;
        } else {
            $signed = 0;
        } 
        
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
        } else {
            $invite_num = 0;
            $user_id = 0;
        }
        $this->assign("user_id", $user_id);
        $status = $invite_num>=38?1:0;
        $this->assign("status", $status);
        $this->redirect();
    }

    public function watch_detail() {
        $user_id = $this->user['id'];
        if($user_id) {
            $sign_up = $this->model->table("invite_active")->where("user_id = ".$user_id)->find();
            $invite_num = empty($sign_up)?0:$sign_up['invite_num'];     
        } else {
            $invite_num = 0;
            $user_id = 0;
        }
        $this->assign("user_id", $user_id);
        $status = $invite_num>=800?1:0;
        $this->assign("status", $status);
        $this->redirect();
    }

    public function open_redbag() {
        $user_id = Filter::int(Req::args("user_id"));
        $active = $this->model->table('invite_active')->where('user_id='.$user_id)->find();
        $this->model->table('invite_active')->data(['invite_num'=>$active['invite_num']-3])->where('user_id='.$user_id)->update();
        $data = array(
            'user_id'=>$user_id,
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
        $data = array(
            'user_id'=>$user_id,
            'type'=>$type,
            'amount'=>0.00,
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
}
?>