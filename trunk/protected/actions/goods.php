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
    public function tbk_dg_material_optional(){
        $q = Filter::str(Req::args("q")); //商品分类标题或分类id
        $page = Filter::int(Req::args("page"));
        $form = Filter::str(Req::args("form"));
        $type = Filter::int(Req::args("type"));
        $sort = Filter::str(Req::args("sort"));
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
            $AdzoneId = '513416107';
        } else { //ios
            $appkey = '24876667';
            $secretKey = 'a5f423bd8c6cf5e8518ff91e7c12dcd2';
            $AdzoneId = '582570496';
        }
        $c->appkey = $appkey;
        $c->secretKey = $secretKey;
        $c->sign_method = 'md5';
        $c->format = 'json';
        $c->v = '2.0';
        $req = new TbkDgItemCouponGetRequest;
        $req->setAdzoneId($AdzoneId);
        $req->setPlatform("2");
        $req->setPageSize(20);
        if($type==1){
            $req->setQ($q);
        } else {
            $req->setCat($q);
        }
        $req->setPageNo($page);
        $resp = $c->execute($req);

        $resp = Common::objectToArray($resp);
        
        if(isset($resp['results']['tbk_coupon'])) {
            if($resp['results']['tbk_coupon']) {
                foreach ($resp['results']['tbk_coupon'] as $k => $v) {
                    $resp['results']['tbk_coupon'][$k]['decrease_price'] = $this->cut('减','元',$v['coupon_info']);
                    $resp['results']['tbk_coupon'][$k]['final_price'] = $v['zk_final_price'] - $resp['results']['tbk_coupon'][$k]['decrease_price'];
                }
                if($sort) {
                    switch ($sort) {
                        case 'price_asc': 
                            array_multisort(array_column($resp['results']['tbk_coupon'],'final_price'),SORT_ASC,$resp['results']['tbk_coupon']);
                            break;
                        case 'price_desc':
                            array_multisort(array_column($resp['results']['tbk_coupon'],'final_price'),SORT_DESC,$resp['results']['tbk_coupon']);
                            break;
                        case 'volume_asc':
                            array_multisort(array_column($resp['results']['tbk_coupon'],'volume'),SORT_ASC,$resp['results']['tbk_coupon']);
                            break;
                        case 'volume_desc':
                            array_multisort(array_column($resp['results']['tbk_coupon'],'volume'),SORT_DESC,$resp['results']['tbk_coupon']);
                            break;        
                    }
                }
                array_multisort(array_column($resp['results']['tbk_coupon'],'decrease_price'),SORT_DESC,$resp['results']['tbk_coupon']);
                // $resp['results']['tbk_coupon'] = array_slice($resp['results']['tbk_coupon'], ($page-1)*10, 10);
                // $cache = CacheFactory::getInstance();
                // $tbk_coupon = $cache->get("_TbkCoupon");
                // if ($cache->get("_TbkCoupon") === null) {
                //     $tbk_coupon = $resp['results']['tbk_coupon'];
                //     $cache->set("_TbkCoupon", $tbk_coupon, 60*60);
                // }
                // $resp['results']['tbk_coupon'] = $tbk_coupon;
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
        $t1 = mb_strpos($str,$begin);
        $t2 = mb_strpos($str,$end);
        $ret = mb_substr($str,$t1+3,$t2-$t1);
        return $ret;
    }

    public function promoter_upload_goods() {
        $name = Filter::str(Req::args('name'));
        $img = Filter::str(Req::args('img'));
        
    }

    //通用物料搜索API（导购）
    public function tbk_item_coupon_get(){
        $q = Filter::str(Req::args("q")); //商品分类标题或分类id
        $page = Filter::int(Req::args("page"));
        $form = Filter::str(Req::args("form"));
        $type = Filter::int(Req::args("type"));
        $sort = Filter::str(Req::args("sort"));
        $startPrice = Req::args("startPrice");
        $endPrice = Req::args("endPrice");
        if(!$page) {
           $page = 1;
        }
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
            $AdzoneId = '513416107';
        } else { //ios
            $appkey = '24876667';
            $secretKey = 'a5f423bd8c6cf5e8518ff91e7c12dcd2';
            $AdzoneId = '582570496';
        }
        $c->appkey = $appkey;
        $c->secretKey = $secretKey;
        $c->sign_method = 'md5';
        $c->format = 'json';
        $c->v = '2.0';
        $req = new TbkDgMaterialOptionalRequest;
        $req->setAdzoneId($AdzoneId);
        $req->setPlatform("2");
//        $req->setStartDsr("10");
        $req->setPageSize("20");
        // $req->setEndTkRate("1234");
        // $req->setStartTkRate("1234");
        // $req->setEndPrice('200');
//        $req->setStartPrice('100');
        // if($endPrice) {
        //     $req->setEndPrice($endPrice);
        // } else {
        //     $req->setEndPrice('20');
        // }
        // if($startPrice) {
        //     $req->setStartPrice($startPrice);
        // }
//        $req->setIsOverseas("false");
//        $req->setIsTmall("false");
//        $req->setSort("tk_rate_des");
        // $req->setItemloc("杭州");
        $req->setHasCoupon("true");
        // $req->setIp("13.2.33.4");
        // $req->setNeedFreeShipment("true");
//        $req->setNeedPrepay("true");
//        $req->setIncludePayRate30("true");
//        $req->setIncludeGoodRate("true");
//        $req->setIncludeRfdRate("true");
//        $req->setNpxLevel("2");


//        $req->setQ('女装');
//         $req->setCat("16,18");
         if($type && $q) {
            if($type==1){
                 $req->setQ($q);
             } else if($type==2) {
                 $req->setCat($q);
             }
         }else{
             $req->setCat("21,11,122852001,5002372,16,30,14,1801,500027664");
         }
        $req->setPageNo($page);
        $resp = $c->execute($req);

        $resp = Common::objectToArray($resp);
        
        if(isset($resp['result_list']['map_data'])) {
            if($resp['result_list']['map_data']) {
                foreach ($resp['result_list']['map_data'] as $k => $v) {
                    $resp['result_list']['map_data'][$k]['decrease_price'] = $this->cut('减','元',$v['coupon_info']);
                    $resp['result_list']['map_data'][$k]['final_price'] = $v['zk_final_price'] - $resp['result_list']['map_data'][$k]['decrease_price'];
                    $resp['result_list']['map_data'][$k]['nick'] = $v['shop_title'];
                    $resp['result_list']['map_data'][$k]['coupon_click_url'] = strpos($v['coupon_share_url'],'http')==false?'https:'.$v['coupon_share_url']:$v['coupon_share_url'];
                    $resp['result_list']['map_data'][$k]['item_description'] = $v['coupon_info'];
                    $resp['result_list']['map_data'][$k]['category'] = 30;
                }
                if($sort) {
                    switch ($sort) {
                        case 'price_asc': 
                            array_multisort(array_column($resp['result_list']['map_data'],'final_price'),SORT_ASC,$resp['result_list']['map_data']);
                            break;
                        case 'price_desc':
                            array_multisort(array_column($resp['result_list']['map_data'],'final_price'),SORT_DESC,$resp['result_list']['map_data']);
                            break;
                        case 'volume_asc':
                            array_multisort(array_column($resp['result_list']['map_data'],'volume'),SORT_ASC,$resp['result_list']['map_data']);
                            break;
                        case 'volume_desc':
                            array_multisort(array_column($resp['result_list']['map_data'],'volume'),SORT_DESC,$resp['result_list']['map_data']);
                            break;        
                    }
                } else {
                    // array_multisort(array_column($resp['result_list']['map_data'],'decrease_price'),SORT_DESC,$resp['result_list']['map_data']);
                    array_multisort(array_column($resp['result_list']['map_data'],'decrease_price'),SORT_DESC,$resp['result_list']['map_data'],array_column($resp['result_list']['map_data'],'volume'),SORT_DESC,$resp['result_list']['map_data']);
                }       
                $resp['results']['tbk_coupon'] = $resp['result_list']['map_data'];
                unset($resp['result_list']);
                // $resp['result_list']['map_data'] = array_slice($resp['result_list']['map_data'], ($page-1)*10, 10);
                // $cache = CacheFactory::getInstance();
                // $map_data = $cache->get("_TbkCoupon");
                // if ($cache->get("_TbkCoupon") === null) {
                //     $map_data = $resp['result_list']['map_data'];
                //     $cache->set("_TbkCoupon", $map_data, 60*60);
                // }
                // $resp['result_list']['map_data'] = $map_data;
            }
        }           
        
        $this->code = 0;
        $this->content = $resp;
    }

    public function upload_goods() {
        $shop = $this->model->table('shop')->where('user_id='.$this->user['id'])->find();
        $promoter = $this->model->table('district_promoter')->where('user_id='.$this->user['id'])->find();
        if(!$promoter) {
            $this->code = 1264;
            return;
        }
        $salt = CHash::random(6);
        if(!$shop) {
            $shop_data = array(
                'name'=>$promoter['shop_name']!=''?$promoter['shop_name']:$this->user['nickname'],
                'user_id'=>$this->user['id'],
                'subtitle'=>'',
                'website'=>'',
                'address'=>'',
                'content'=>'',
                'seo_title'=>'',
                'seo_keywords'=>'',
                'seo_description'=>'',
                'sale_protection'=>'',
                'category_id'=>40,
                'freeshipping'=>Filter::int(Req::args('freeshipping')),
                'star'=>0,
                'img'=>$promoter['picture']!=null?$promoter['picture']:'http://www.ymlypt.com/themes/mobile/images/logo-new.png',
                'imgs'=>serialize(array()),
                'username'=>$promoter['shop_name']!=''?$promoter['shop_name']:$this->user['nickname'],
                'password'=>md5(md5('123456') . $salt),
                'salt'=>$salt,
                'create_time'=>date('Y-m-d H:i:s'),
                'up_time'=>date('Y-m-d H:i:s'),
                'state'=>0
                );
            $shop_id = $this->model->table('shop')->data($shop_data)->insert();
        } else {
            $shop_id = $shop['id'];
        }
        $goods_no = '000'.rand(1111,9999);
        $content_imgs = Filter::str(Req::args('content_imgs'));
        $imgs = Filter::str(Req::args('imgs'));
        $content_imgs_array = explode(',',$content_imgs);
        $content_imgs_str = '';
        if($content_imgs_array) {
            if(is_array($content_imgs_array)) {
                foreach ($content_imgs_array as $k=>$v) {
                    $content_imgs_str .= "<img src=".$v." />";
                }
            }
        }
        $imgs_array = explode(',',$imgs);
        $imgs_str = '';
        $img = '';
        if($imgs_array) {
            if(is_array($imgs_array)) {
                $img = $imgs_array[0];
                $imgs_str = serialize($imgs_array);
            }
        }
        $goods_data = array(
            'name'=>Filter::str(Req::args('name')),
            'subtitle'=>'',
            'category_id'=>Filter::int(Req::args('category_id')),
            'goods_no'=>$goods_no,
            'pro_no'=>$goods_no,
            'type_id'=>0,
            'shop_id'=>$shop_id,
            'brand_id'=>0,
            'unit'=>'件',
            'content'=>$content_imgs_str,
            'img'=>$img,
            'imgs'=> $imgs_str,
            'tag_ids'=>'',
            'sell_price'=>Filter::float(Req::args('sell_price')),
            'market_price'=>Filter::float(Req::args('sell_price')),
            'cost_price'=>Filter::float(Req::args('cost_price')),
            'create_time'=>date('Y-m-d H:i:s'),
            'store_nums'=>Filter::int(Req::args('store_nums')),
            'warning_line'=>2,
            'seo_title'=>'',
            'seo_keywords'=>'',
            'seo_description'=>'',
            'weight'=>Filter::int(Req::args('weight')),
            'point'=>0,
            'visit'=>0,
            'favorite'=>0,
            'sort'=>1,
            'specs'=>serialize(array()),
            'attrs'=>serialize(array()),
            'prom_id'=>0,
            'is_online'=>Filter::int(Req::args('is_online')),
            'sale_protection'=>'',
            'freeshipping'=>Filter::int(Req::args('freeshipping')),
            'personal_shop_id'=>0,
            'user_id'=>$this->user['id'],
            'type'=>2
            );
        $goods_id = $this->model->table('goods')->data($goods_data)->insert();
        $product = $this->model->table('products')->where('goods_id='.$goods_id)->find();
        if(!$product) {
            $product_data = array(
                'goods_id'=>$goods_id,         
                'pro_no'=>$goods_no,  
                'spec'=>serialize(array()),
                'store_nums'=>$goods_data['store_nums'],
                'warning_line'=>2,
                'market_price'=>$goods_data['market_price'],
                'sell_price'=>$goods_data['sell_price'],
                'cost_price'=>$goods_data['cost_price'],
                'weight'=>Filter::int(Req::args('weight')),
                'specs_key'=>''
                );
            $this->model->table('products')->data($product_data)->insert();
        }
        $this->code = 0;
        return; 
    }

    public function get_all_category() {
       $cache = CacheFactory::getInstance();  
       if ($cache->get("_GoodsAllCategory") === null) {
            $result = $this->model->table('goods_category')->fields('id,name')->where('id!=1')->order("sort desc")->findAll();
            $cache->set("_GoodsAllCategory", $result, 3600);
        }
       $result = $cache->get("_GoodsAllCategory");
       $this->code = 0;
       $this->content = $result;
       return; 
    }

    public function fare_list() {
        $fare = $this->model->table('fare')->where('id=1 or is_default=1')->findAll();
        foreach ($fare as $k => $v) {
            $zone = unserialize($v['zoning']);
            $fare[$k]['zone_name'] = $zone;   
            if($v['id']==1) {
                $fare[$k]['desc'] = '全国所有地区免邮费';
            } else {
                $fare[$k]['desc'] = "默认运费：".$v['first_weight']."(g)内,".$v['first_price']."元；每增加".$v['second_weight']."(g)，增加运费" .$v['second_price']."元";
            }
        }
        $this->code = 0;
        $this->content = $fare;
        return;
    }

    public function my_goods_list() {
        $is_online = Filter::int(Req::args('is_sale'));
        $sort = Filter::int(Req::args('sort'));
        $page = Filter::int(Req::args('page'));
        if(!$page) {
            $page = 1;
        }
        if(!$is_online) {
            $is_online = 0;
        }
        $where = "user_id=".$this->user['id']." and is_online=".$is_online;
        $sort = 'id desc';
        if($sort == 1) {
            $sort = 'create_time desc';
        }
        if($sort == 2) {
            $sort = 'create_time asc';
        }
        
        $list = $this->model->table('goods')->fields('id,name,category_id,img,sell_price,create_time,store_nums,is_online,base_sales_volume')->where($where)->order($sort)->findPage($page,10);
        if($list) {
            if(isset($list['data']) && $list['data']!=null) {
                foreach ($list['data'] as $k => $v) {
                    $sales_volume = $this->model->table("order_goods as og")->join("left join order as o on og.order_id = o.id")->where("og.goods_id = ".$v['id']." and o.status in (3,4)")->fields("SUM(og.goods_nums) as sell_volume")->find();
                    $sales_volume = $sales_volume['sell_volume']==NULL?0:$sales_volume['sell_volume'];
                    $list['data'][$k]['sales_volume'] = $v['base_sales_volume']+$sales_volume;
                    $list['data'][$k]['share_url'] = 'http://www.ymlypt.com/product-'.$v['id'].'.html';
                }
                if($sort==3) {
                    array_multisort(array_column($list['data'],'sales_volume'),SORT_DESC,$resp['data']);
                }
                if($sort==4) {
                    array_multisort(array_column($list['data'],'sales_volume'),SORT_ASC,$resp['data']);
                }
            }
            unset($list['html']);    
        }
        $this->code = 0;
        $this->content = $list;
        return;
    }

    public function manage_my_goods() {
        $type = Filter::int(Req::args('type'));
        $id = Filter::int(Req::args('id'));
        if($type==0 || $type==1) {
            $this->model->table('goods')->data(['is_online'=>$type])->where('id='.$id)->update();
        } else {
            $this->model->table('goods')->where('id='.$id)->delete();
            $this->model->table('product')->where('goods_id='.$id)->delete();
        }
        $this->code = 0;
        return;
    }

    public function goods_detail() {
        $id = Filter::int(Req::args('id'));
        $info = $this->model->table('goods')->fields('id,name,category_id,img,imgs,sell_price,create_time,store_nums,is_online,content,freeshipping,base_sales_volume')->where('id='.$id)->find();
        if($info) {
            $info['imgs'] = unserialize($info['imgs']);
            $sales_volume = $this->model->table("order_goods as og")->join("left join order as o on og.order_id = o.id")->where("og.goods_id = ".$info['id']." and o.status in (3,4)")->fields("SUM(og.goods_nums) as sell_volume")->find();
            $sales_volume = $sales_volume['sell_volume']==NULL?0:$sales_volume['sell_volume'];
            $info['sales_volume'] = $info['base_sales_volume']+$sales_volume;
        }
        $this->code = 0;
        $this->content = $info;
        return;
    }
}
