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

    //淘宝客商品查询
    public function tbk_item_get(){
        $q = Filter::str(Req::args("q"));
        $page = Filter::int(Req::args("page"));
        $form = Filter::str(Req::args("form"));
        if(!$page) {
            $page = 1;
        }
        if(!$form) {
            $form = 'android';
        }
        $c = new TopClient;  
        if($form=='android') { //百川安卓
            $appkey = '24878644';
            $secretKey = '453423588409212afb30d32be37df832';
        } else { //百川ios
            $appkey = '24878695';
            $secretKey = '7a579c1d21ce8e610da1a80cd839427a';
        }
        $c->appkey = $appkey;
        $c->secretKey = $secretKey;
        $c->sign_method = 'md5';
        $c->format = 'json';
        $c->v = '2.0';
        $req = new TbkItemGetRequest;
        $req->setFields("num_iid,title,pict_url,small_images,reserve_price,zk_final_price,user_type,provcity,item_url,seller_id,volume,nick");
        $req->setQ($q);
        $req->setCat("16,18");
        // $req->setItemloc("杭州");
        $req->setSort("tk_rate_des");
        $req->setIsTmall("false");
        $req->setIsOverseas("false");
        $req->setStartPrice("10");
        $req->setEndPrice("500");
        // $req->setStartTkRate("123");
        // $req->setEndTkRate("123");
        $req->setPlatform("1");
        $req->setPageNo($page);
        $req->setPageSize("10");
        $resp = $c->execute($req);
        $this->code = 0;
        $this->content = $resp;
    }
    
    //淘宝客好券清单API【导购】
    public function tbk_item_coupon_get(){
        $q = Filter::str(Req::args("q"));
        $page = Filter::int(Req::args("page"));
        $form = Filter::str(Req::args("form"));
        if(!$page) {
            $page = 1;
        }
        if(!$form) {
            $form = 'android';
        }
        $c = new TopClient;  
        if($form=='android') { //安卓
            $appkey = '24875594';
            $secretKey = '8aac26323a65d4e887697db01ad7e7a8';
        } else { //ios
            $appkey = '24876667';
            $secretKey = 'a5f423bd8c6cf5e8518ff91e7c12dcd2';
        }
        $c->appkey = $appkey;
        $c->secretKey = $secretKey;
        $c->sign_method = 'md5';
        $c->format = 'json';
        $c->v = '2.0';
        $req = new TbkDgItemCouponGetRequest;
        $req->setAdzoneId("513416107");
        $req->setPlatform("1");
        // $req->setCat("16,18");
        $req->setPageSize("10");
        $req->setQ($q);
        $req->setPageNo($page);
        $resp = $c->execute($req);
        $this->code = 0;
        $this->content = $resp;
    }

    public function taobao_item_detail_get(){
        $form = Filter::str(Req::args("form"));
        $item_id = Filter::int(Req::args("item_id"));
        if(!$form) {
            $form = 'android';
        }
        if($form=='android') { //安卓
            $appkey = '24875594';
            $secretKey = '8aac26323a65d4e887697db01ad7e7a8';
        } else { //ios
            $appkey = '24876667';
            $secretKey = 'a5f423bd8c6cf5e8518ff91e7c12dcd2';
        }
        $c = new TopClient;
        $c->appkey = $appkey;
        $c->secretKey = $secretKey;
        $req = new ItemDetailGetRequest;
        // $req->setParams("areaId");
        $req->setItemId($item_id);
        $req->setFields("item,price,delivery,skuBase,skuCore,trade,feature,props,debug");
        $resp = $c->execute($req);
        $this->code = 0;
        $this->content = $resp;
    }

}
