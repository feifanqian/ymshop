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
    	$this->redirect();
    }

    public function login() {
    	$this->redirect();
    }

    public function login_act() {
        $redirectURL = '/active/recruit';
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
                var_dump(111);
                if ($obj['password'] == CHash::md5($passWord, $obj['validcode'])) {
                    var_dump(222);die;
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
                    // $redirectURL = Req::args("redirectURL");

                    if ($redirectURL != ''){
                        var_dump(123);die;
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
                    var_dump(333);die;
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