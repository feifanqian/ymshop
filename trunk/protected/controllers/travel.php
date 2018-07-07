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
            $this->redirect('/active/login/redirect/fill_info');
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
        $this->model->table('travel_order')->data($data)->insert();
        $this->redirect('pay');
    }

    public function order_list()
    {
        if($this->user['id']) {
            $page = Filter::int(Req::args('p'));
            if(!$page) {
                $page = 1;
            }
            $list = $this->model->table('travel_order as t')->fields('t.id,t.order_no,tw.name,tw.city,tw.desc,t.order_amount,tw.img')->join('left join travel_way as tw on t.way_id=tw.id')->where('t.user_id='.$this->user['id'])->findPage($page,10);
            $this->assign('list',$list); 
            $this->redirect();
        } else {
            $this->redirect('/active/login/redirect/order_list');
        }
    } 
}    