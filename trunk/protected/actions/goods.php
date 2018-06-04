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
        $q = Filter::str(Req::args("q")); //商品分类标题或分类id
        $page = Filter::int(Req::args("page"));
        $form = Filter::str(Req::args("form"));
        $type = Filter::int(Req::args("type"));
        // if(!$page) {
        //     $page = 1;
        // }
        if(!$type) {
            $type = 1;
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
        $req->setPageSize(100);
        if($type==1){
            $req->setQ($q);
        } else {
            $req->setCat($q);
        }
        $req->setPageNo($page);
        $resp = $c->execute($req);

        $resp = Common::objectToArray($resp);
        if(isset($resp['results']['tbk_coupon'])) {
            $resp['results']['tbk_coupon'] = array_slice($resp['results']['tbk_coupon'], ($page-1)*10, 10);
        }
        // $cache = CacheFactory::getInstance();
        // $tbk_coupon = $cache->get("_TbkCoupon");
        // if ($cache->get("_TbkCoupon") === null) {
        //     $tbk_coupon = $resp['results']['tbk_coupon'];
        //     $cache->set("_TbkCoupon", $items, 60*60);
        // }
        // $resp['results']['tbk_coupon'] = $tbk_coupon;
        if($resp['results']['tbk_coupon']) {
            foreach ($resp['results']['tbk_coupon'] as $k => $v) {
                $resp['results']['tbk_coupon'][$k]['decrease_price'] = $this->cut('减','元',$v['coupon_info']);
            }
        }
        $this->code = 0;
        $this->content = $resp;
    }

    public function taobao_item_detail_get(){
        $form = Filter::str(Req::args("form"));
        $item_id = Filter::int(Req::args("item_id"));
        if(!$form) {
            $form = 'android';
        }
        if($form=='android') { //百川安卓
            $appkey = '24878644';
            $secretKey = '453423588409212afb30d32be37df832';
        } else { //百川ios
            $appkey = '24878695';
            $secretKey = '7a579c1d21ce8e610da1a80cd839427a';
        }
        // if($form=='android') { //安卓
        //     $appkey = '24875594';
        //     $secretKey = '8aac26323a65d4e887697db01ad7e7a8';
        // } else { //ios
        //     $appkey = '24876667';
        //     $secretKey = 'a5f423bd8c6cf5e8518ff91e7c12dcd2';
        // }
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

    public function taobao_rebate_order_get(){
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
        $req = new TbkRebateOrderGetRequest;
        $req->setFields("tb_trade_parent_id,tb_trade_id,num_iid,item_title,item_num,price,pay_price,seller_nick,seller_shop_title,commission,commission_rate,unid,create_time,earning_time");
        $req->setStartTime(date('Y-m-d H:i:s','-3 days'));
        $req->setSpan("600");
        $req->setPageNo("1");
        $req->setPageSize("10");
        $resp = $c->execute($req);
        $this->code = 0;
        $this->content = $resp;
    }

    public function tbk_item_guess_like(){
        $page = Filter::int(Req::args("page"));
        $form = Filter::str(Req::args("form"));
        if(!$page) {
            $page = 1;
        }
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
        $c->sign_method = 'md5';
        $c->format = 'json';
        $c->v = '2.0';
        $req = new TbkItemGuessLikeRequest;
        $req->setAdzoneId("513416107");
        // $req->setUserNick("abc");
        // $req->setUserId("123456");
        $req->setOs($form);
        // $req->setIdfa("65A509BA-227C-49AC-91EC-DE6817E63B10");
        // $req->setImei("641221321098757");
        // $req->setImeiMd5("115d1f360c48b490c3f02fc3e7111111");
        $req->setIp($_SERVER['REMOTE_ADDR']);
        $req->setUa($_SERVER['HTTP_USER_AGENT']);
        // $req->setApnm("com.xxx");
        $req->setNet("wifi");
        // $req->setMn("iPhone7%2C2");
        $req->setPageNo($page);
        $req->setPageSize("10");
        $resp = $c->execute($req);
        
        $this->code = 0;
        $this->content = $resp;
    }

    public function tbk_index_banner(){
        $banner = $this->model->table('ad')->fields('id,name,content,width,height')->where('id=80')->find();
        $banner['content'] = unserialize($banner['content']);
        foreach ($banner['content'] as $k=>$v){
            if($banner['content'][$k]['url']!=''){
                $banner['content'][$k]['url'] = json_decode($banner['content'][$k]['url'],true);
            }else{
                $banner['content'][$k]['url'] =array('type'=>'','type_value'=>'');
            }
        }
        $this->code = 0;
        $this->content = $banner;
    }

    public function tbk_cat_nav(){
        $list = $this->model->table('tbk_cat_nav')->where('status=1')->order('sort desc')->findAll();
        $cache = CacheFactory::getInstance();
        $items = $cache->get("_CatNav");
        if ($cache->get("_CatNav") === null) {
            $items = $list;
            $cache->set("_CatNav", $items, 86400);
        }
        $this->code = 0;
        $this->content = $items;
    }

    public function tbk_cat_title_to_id($title){
       $item = $this->model->table('tbk_cat_nav')->where("title like '%{$title}%'")->find();
       return $item?$item['cat_id']:0;
    }

    public function make_shop_qrcode_no(){
        $list = $this->model->table('district_promoter')->fields('id,qrcode_no')->findAll();
        foreach ($list as $k => $v) {
            $no = '0000'.$v['id'].rand(1000,9999);
            $res = $this->model->table('district_promoter')->data(array('qrcode_no'=>$no))->where('id='.$v['id'])->update();
        }
        $this->code = 0;
        return;
    }
    
    public function tbk_tpwd_create(){
        $form = Filter::str(Req::args("form"));
        $text = Filter::text(Req::args("text"));
        $url = Filter::str(Req::args("url"));
        $logo = Filter::str(Req::args("logo"));
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
        if(!$text) {
            $this->code = 1248;
            return;
        }
        if(!$url) {
            $this->code = 1249;
            return;
        }
        $c = new TopClient;
        $c->appkey = $appkey;
        $c->secretKey = $secretKey;
        $c->format = 'json';
        $req = new TbkTpwdCreateRequest;
        // $req->setUserId("123");
        $req->setText($text);
        $req->setUrl($url);
        if($logo) {
            $req->setLogo($logo);
        }
        $req->setExt("{}");
        $resp = $c->execute($req);
        $this->code = 0;
        $this->content = $resp;
    }

    public function cut($begin,$end,$str){
        $t1 = mb_strpos($str,$begin)+1;
        $t2 = mb_strpos($str,$end);
        return mb_substr($str,$t1,$t2-$t1);
    }
}
