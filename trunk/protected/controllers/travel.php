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
        $list = $this->model->table('travel_way')->where('status=1')->findPage($page,10);

        $this->assign('list',$list);
        $this->redirect();
    }

    public function way_detail() {
        $id = Filter::int(Req::args("id"));
        $info = $this->model->table('travel_way')->where('id='.$id)->find();
        if($this->user['id']) {
            $sign = $this->model->table('travel_sign')->where('user_id='.$this->user['id'].' and way_id='.$id)->find();
            $sign_status = empty($sign)?0:1;
        } else {
            $sign_status = 0;
        }
        
        $this->assign('info',$info);
        $this->assign('sign_status',$sign_status);
        $this->redirect();
    }
}    