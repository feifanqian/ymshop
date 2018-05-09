<?php

class GoodsAction extends Controller {

    public $model = null;
    public $user = null;
    public $code = 1000;
    public $content = NULL;

    public function __construct() {
        $this->model = new Model();
    }

    public function add() {
        $cart = Cart::getCart('goods');
        $cart->clear();
        $id = Filter::int(Req::args("id"));
        $num = intval(Req::args("num"));
        $num = $num > 0 ? $num : 1;
        $result = $cart->addItem($id, $num);
        $cartlist = $cart->all();
        foreach ($cartlist as $k => &$v) {
            $v['spec'] = array_values($v['spec']);
        }
        $this->code = 0;
        $this->content = array(
            'cartlist' => $cartlist
        );
    }

    public function sellNumCount(){
        $order_list = $this->model->table('order')->where('status=3 and pay_status=1')->findall();
    }

    public function tbk_item_get(){
        $c = new TopClient;
        $c->appkey = '24874156';
        $c->secretKey = 'a5e3998f3225cc0c673a5025845acd51';
        $req = new TbkItemGetRequest;
        $req->setFields("num_iid,title,pict_url,small_images,reserve_price,zk_final_price,user_type,provcity,item_url,seller_id,volume,nick");
        $req->setQ("女装");
        $req->setCat("16,18");
        $req->setItemloc("杭州");
        $req->setSort("tk_rate_des");
        $req->setIsTmall("false");
        $req->setIsOverseas("false");
        $req->setStartPrice("10");
        $req->setEndPrice("10");
        $req->setStartTkRate("123");
        $req->setEndTkRate("123");
        $req->setPlatform("1");
        $req->setPageNo("123");
        $req->setPageSize("20");
        $resp = $c->execute($req);
        $this->code = 0;
        $this->content = json_decode($resp,true);
    }

}
