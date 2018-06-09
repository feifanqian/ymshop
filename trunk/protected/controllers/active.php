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
        }
    	$this->redirect();
    }

    public function login() {
        $redirectURL = Filter::str(Req::args("redirect"));
        $this->assign("redirectURL", $redirectURL);
        $this->safebox->clear('user');
        $cookie = new Cookie();
        $cookie->setSafeCode(Tiny::app()->getSafeCode());
        $cookie->set('autologin', null, 0);
    	$this->redirect();
    }

    public function login_act() {
        $redirectURL = Filter::str(Req::args("redirect"));
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

                    if ($redirectURL=='recruit'){
                        $this->redirect("/active/recruit");
                    } elseif ($redirectURL=='sign_up'){
                        $this->redirect("/active/sign_up");
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

    
}
?>