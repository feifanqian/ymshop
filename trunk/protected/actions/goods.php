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
        $session_id = Req::args("session_id");
        $uid = Filter::int(Req::args("user_id"));
        $num = $num > 0 ? $num : 1;
        // if($uid || $session_id){
        //    $result = $cart->addItem($id, $num,$uid,$session_id); 
        // }else{
        //    $result = $cart->addItem($id, $num);
        // }
        $result = $cart->addItems($id, $num);
        // if($uid || $session_id) {    
        //     $cartlist = $cart->all($uid,$session_id);
        // }else {
        //     $cartlist = $cart->all();
        // }
        $cartlist = $cart->all();
        foreach ($cartlist as $k => &$v) {
            $v['spec'] = array_values($v['spec']);
        }
        $this->code = 0;
        $this->content = array(
            'cartlist' => $cartlist
        );
    }

    public function sellNumCount() {
        $order_list = $this->model->table('order')->where('status=3 and pay_status=1')->findall();
    }

    //淘宝客商品查询
    public function tbk_item_get() {
        $q = Filter::str(Req::args("q"));
        $page = Filter::int(Req::args("page"));
        $form = Filter::str(Req::args("form"));
        if (!$page) {
            $page = 1;
        }
        if (!$form) {
            $form = 'android';
        }
        $c = new TopClient;
        if ($form == 'android') { //百川安卓
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
    public function tbk_dg_material_optional() {
        $q = Filter::str(Req::args("q")); //商品分类标题或分类id
        $page = Filter::int(Req::args("page"));
        $form = Filter::str(Req::args("form"));
        $type = Filter::int(Req::args("type"));
        $sort = Filter::str(Req::args("sort"));
        // if(!$page) {
        //     $page = 1;
        // }
        if (!$type) {
            $type = 1;
        }
        if (!$form) {
            $form = 'android';
        }
        $c = new TopClient;
        if ($form == 'android') { //安卓
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
        if ($type == 1) {
            $req->setQ($q);
        } else {
            $req->setCat($q);
        }
        $req->setPageNo($page);
        $resp = $c->execute($req);

        $resp = Common::objectToArray($resp);

        if (isset($resp['results']['tbk_coupon'])) {
            if ($resp['results']['tbk_coupon']) {
                foreach ($resp['results']['tbk_coupon'] as $k => $v) {
                    $resp['results']['tbk_coupon'][$k]['decrease_price'] = $this->cut('减', '元', $v['coupon_info']);
                    $resp['results']['tbk_coupon'][$k]['final_price'] = $v['zk_final_price'] - $resp['results']['tbk_coupon'][$k]['decrease_price'];
                }
                if ($sort) {
                    switch ($sort) {
                        case 'price_asc':
                            array_multisort(array_column($resp['results']['tbk_coupon'], 'final_price'), SORT_ASC, $resp['results']['tbk_coupon']);
                            break;
                        case 'price_desc':
                            array_multisort(array_column($resp['results']['tbk_coupon'], 'final_price'), SORT_DESC, $resp['results']['tbk_coupon']);
                            break;
                        case 'volume_asc':
                            array_multisort(array_column($resp['results']['tbk_coupon'], 'volume'), SORT_ASC, $resp['results']['tbk_coupon']);
                            break;
                        case 'volume_desc':
                            array_multisort(array_column($resp['results']['tbk_coupon'], 'volume'), SORT_DESC, $resp['results']['tbk_coupon']);
                            break;
                    }
                }
                array_multisort(array_column($resp['results']['tbk_coupon'], 'decrease_price'), SORT_DESC, $resp['results']['tbk_coupon']);
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

    public function taobao_item_detail_get() {
        $form = Filter::str(Req::args("form"));
        $item_id = Filter::int(Req::args("item_id"));
        if (!$form) {
            $form = 'android';
        }
        if ($form == 'android') { //百川安卓
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

    public function taobao_rebate_order_get() {
        $form = Filter::str(Req::args("form"));
        $item_id = Filter::int(Req::args("item_id"));
        if (!$form) {
            $form = 'android';
        }
        if ($form == 'android') { //安卓
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
        $req->setStartTime(date('Y-m-d H:i:s', '-3 days'));
        $req->setSpan("600");
        $req->setPageNo("1");
        $req->setPageSize("10");
        $resp = $c->execute($req);
        $this->code = 0;
        $this->content = $resp;
    }

    public function tbk_item_guess_like() {
        $page = Filter::int(Req::args("page"));
        $form = Filter::str(Req::args("form"));
        if (!$page) {
            $page = 1;
        }
        if (!$form) {
            $form = 'android';
        }
        if ($form == 'android') { //安卓
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

    public function tbk_index_banner() {
        $banner = $this->model->table('ad')->fields('id,name,content,width,height')->where('id=80')->find();
        $banner['content'] = unserialize($banner['content']);
        foreach ($banner['content'] as $k => $v) {
            if ($banner['content'][$k]['url'] != '') {
                $banner['content'][$k]['url'] = json_decode($banner['content'][$k]['url'], true);
            } else {
                $banner['content'][$k]['url'] = array('type' => '', 'type_value' => '');
            }
        }
        $this->code = 0;
        $this->content = $banner;
    }

    public function tbk_cat_nav() {
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

    public function tbk_cat_title_to_id($title) {
        $item = $this->model->table('tbk_cat_nav')->where("title like '%{$title}%'")->find();
        return $item ? $item['cat_id'] : 0;
    }

    public function make_shop_qrcode_no() {
        $list = $this->model->table('district_promoter')->fields('id,qrcode_no')->findAll();
        foreach ($list as $k => $v) {
            $no = '0000' . $v['id'] . rand(1000, 9999);
            $res = $this->model->table('district_promoter')->data(array('qrcode_no' => $no))->where('id=' . $v['id'])->update();
        }
        $this->code = 0;
        return;
    }

    public function tbk_tpwd_create() {
        $form = Filter::str(Req::args("form"));
        $text = Filter::text(Req::args("text"));
        $url = Filter::str(Req::args("url"));
        $logo = Filter::str(Req::args("logo"));
        if (!$form) {
            $form = 'android';
        }
        if ($form == 'android') { //安卓
            $appkey = '24875594';
            $secretKey = '8aac26323a65d4e887697db01ad7e7a8';
        } else { //ios
            $appkey = '24876667';
            $secretKey = 'a5f423bd8c6cf5e8518ff91e7c12dcd2';
        }
        if (!$text) {
            $this->code = 1248;
            return;
        }
        if (!$url) {
            $this->code = 1249;
            return;
        }
        if(!$logo) {
            $logo = 'http://www.ymlypt.com/themes/mobile/images/logo-new.png';
        }
        $c = new TopClient;
        $c->appkey = $appkey;
        $c->secretKey = $secretKey;
        $c->format = 'json';
        $req = new TbkTpwdCreateRequest;
        // $req->setUserId("123");
        $req->setText($text);
        $req->setUrl($url);
        if ($logo) {
            $req->setLogo($logo);
        }
        $req->setExt("{}");
        $resp = $c->execute($req);
        $this->code = 0;
        $this->content = $resp;
    }

    public function cut($begin, $end, $str) {
        $t1 = mb_strpos($str, $begin);
        $t2 = mb_strpos($str, $end);
        $ret = mb_substr($str, $t1 + 3, $t2 - $t1);
        return $ret;
    }

    public function promoter_upload_goods() {
        $name = Filter::str(Req::args('name'));
        $img = Filter::str(Req::args('img'));

    }

    //通用物料搜索API（导购）
    public function tbk_item_coupon_gets() {
        $q = Filter::str(Req::args("q")); //商品分类标题或分类id
        $page = Filter::int(Req::args("page"));
        $form = Filter::str(Req::args("form"));
        $type = Filter::int(Req::args("type"));
        $sort = Filter::str(Req::args("sort"));
        $startPrice = Req::args("startPrice");
        $endPrice = Req::args("endPrice");
        if (!$q) {
            $q = 0;
        }
        if (!$page) {
            $page = 1;
        }
        if (!$type) {
            $type = 1;
        }
        if (!$form) {
            $form = 'android';
        }

        $resp = $this->tbk_req_get($form, $q, $type, $page, '50', 'total_sales_des',false);

        $size = empty($size) ? 20 : $size;
        $save_data = [];
        if (isset($resp['result_list']['map_data'])) {
            if ($resp['result_list']['map_data']) {
                foreach ($resp['result_list']['map_data'] as $itm) {
                    if (!isset($save_data[$itm['coupon_id']])) {
                        if($itm['coupon_info']!='') {
                            $decrease_price = (float)$this->get_between($itm['coupon_info'], '减', '元');
                        } else {
                            $decrease_price = 0.00;
                            $itm['coupon_end_time'] = date('Y-m-d H:i:s');
                            $itm['coupon_share_url'] = $itm['item_url'];
                            $itm['coupon_start_time'] = date('Y-m-d H:i:s');
                        }
                        
                        // if ($decrease_price < 5 || ($itm['zk_final_price'] - $decrease_price>=5000)) {
                        //     continue;
                        // }
                        if(!isset($itm['small_images'])){
                            $itm['small_images']['string'] = array($itm['pict_url']);
                        }
                        $itm['decrease_price'] = $decrease_price;
                        $itm['final_price'] = $itm['zk_final_price'] - $itm['decrease_price'];
                        $itm['nick'] = $itm['shop_title'];
                        $itm['rate_price'] = $itm['final_price']*$itm['commission_rate'];
                        $itm['coupon_click_url'] = strpos($itm['coupon_share_url'], 'http') == false ? 'https:' . $itm['coupon_share_url'] : $itm['coupon_share_url'];
                        $itm['item_description'] = $itm['coupon_info'];
                        $itm['category'] = 30;
                        $itm['volume'] = isset($itm['volume'])?$itm['volume']:$itm['tk_total_sales'];
                        $save_data[$itm['coupon_id']] = $itm;
                    }
                }

                if ($sort) {
                    switch ($sort) {
                        case 'price_asc':
                            array_multisort(array_column($save_data, 'final_price'), SORT_ASC, $save_data);
                            break;
                        case 'price_desc':
                            array_multisort(array_column($save_data, 'final_price'), SORT_DESC, $save_data);
                            break;
                        case 'volume_asc':
                            array_multisort(array_column($save_data, 'volume'), SORT_ASC, $save_data);
                            break;
                        case 'volume_desc':
                            array_multisort(array_column($save_data, 'volume'), SORT_DESC, $save_data);
                            break;
                    }
                } else {
                    array_multisort(array_column($save_data, 'rate_price'), SORT_DESC, $save_data, array_column($save_data, 'decrease_price'), SORT_DESC, $save_data);
                }

                //使结果是偶数
                // $count = count($save_data);
                // if($count < $size){
                //     $size = $count - ($count % 2);
                // }

                $resp['results']['tbk_coupon'] = array_slice(array_values($save_data), 0, $size);
                unset($resp['result_list']);
            }
        }

        $this->code = 0;
        $this->content = $resp;
    }

//通用物料搜索API（导购）====add by dallon start============================================================================
    public function get_between($str, $start, $end) {
        $str1 = explode($start, $str);
        $str2 = explode($end, $str1[1]);
        return $str2[0];
    }

    public function tbk_req_get($form, $q, $type, $pageno, $pageSize = '100', $sort = 'total_sales_des',$has_coupon=true) {
        if (!$q) {
            $q = 0;
        }

        if (!$pageno) {
            $pageno = 1;
        }
        if (!$type) {
            $type = 1;
        }
        if (!$form) {
            $form = 'android';
        }

        $c = new TopClient;
        // if ($form == 'android') { //安卓
        //     $appkey = '24875594';
        //     $secretKey = '8aac26323a65d4e887697db01ad7e7a8';
        //     $AdzoneId = '513416107';
        // } else { //ios
        //     $appkey = '24876667';
        //     $secretKey = 'a5f423bd8c6cf5e8518ff91e7c12dcd2';
        //     $AdzoneId = '582570496';
        // }
        $appkey = '24874156';
        $secretKey = 'a5e3998f3225cc0c673a5025845acd51';
        $AdzoneId = '502162923';

        $c->appkey = $appkey;
        $c->secretKey = $secretKey;
        $c->sign_method = 'md5';
        $c->format = 'json';
        $c->v = '2.0';
        $req = new TbkDgMaterialOptionalRequest;
        $req->setAdzoneId($AdzoneId);
        // $req->setPlatform("2");
//        $req->setStartDsr("10");
        $req->setPageSize($pageSize);
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
        $req->setSort($sort);
        // $req->setItemloc("杭州");
        if($has_coupon) {
            $req->setHasCoupon("true");
        }  
        // $req->setIp("13.2.33.4");
        // $req->setNeedFreeShipment("true");
//        $req->setNeedPrepay("true");
//        $req->setIncludePayRate30("true");
//        $req->setIncludeGoodRate("true");
//        $req->setIncludeRfdRate("true");
//        $req->setNpxLevel("2");

        if ($type && $q) {
            if ($type == 1) {
                $req->setQ((string)$q);
            } else if ($type == 2) {
                $req->setCat((string)$q);
            }
        } else {
            $req->setCat("30,14,1801,50002766");
        }
        // $req->setPageNo($page);
        $req->setPageNo((string)$pageno);
//        var_dump($req);

        $resp = $c->execute($req);


        $result = Common::objectToArray($resp);

        return $result;
    }

    public function tbk_item_coupon_get2() {
        $q = Filter::str(Req::args("q")); //商品分类标题或分类id
        $page = Filter::int(Req::args("page"));
        $form = Filter::str(Req::args("form"));
        $type = Filter::int(Req::args("type"));
        $size = Filter::int(Req::args("size"));
        $sort = Filter::str(Req::args("sort"));
        $startPrice = Req::args("startPrice");
        $endPrice = Req::args("endPrice");
        if (!$q) {
            $q = 0;
        }
        if (!$page) {
            $page = 1;
        }
        if (!$type) {
            $type = 1;
        }
        if (!$form) {
            $form = 'android';
        }

        $size = empty($size) ? 20 : $size;
//        $redis = CacheFactory::getInstance('redis');
        $redis = new MyRedis();

        $redis_key = "cat_" . (empty($q) ? "all" : $q);
        $cache_data = json_decode($redis->get($redis_key), true);

        $save_data = [];
        $user_id = $this->user['id'] == null ? 0 : $this->user['id'];
        $request_count = 0;
        $request_filler_count = 0;
        $cache_count = 0;
        $request_type = 0;
        if (empty($cache_data)) {
            $tbk_data = $this->tbk_req_get($form, $q, $type, 1, '80', 'total_sales_des');
            if (isset($tbk_data['result_list']['map_data'])) {
                $request_count = count($tbk_data['result_list']['map_data']);
                foreach ($tbk_data['result_list']['map_data'] as $itm) {
                    if (!isset($save_data[$itm['coupon_id']])) {
                        $decrease_price = (float)$this->get_between($itm['coupon_info'], '减', '元'); 
                        if ($decrease_price < 5 || ($itm['zk_final_price'] - $decrease_price>=5000)) {
                            continue;
                        }
                        if(!isset($itm['small_images'])){
                            $itm['small_images']['string'] = array($itm['pict_url']);
                        }
                        $itm['decrease_price'] = $decrease_price;
                        $itm['final_price'] = $itm['zk_final_price'] - $itm['decrease_price'];
                        $itm['nick'] = $itm['shop_title'];
                        $itm['rate_price'] = $itm['final_price']*$itm['commission_rate'];
                        $itm['coupon_click_url'] = strpos($itm['coupon_share_url'], 'http') == false ? 'https:' . $itm['coupon_share_url'] : $itm['coupon_share_url'];
                        $itm['item_description'] = $itm['coupon_info'];
                        $itm['category'] = 30;
                        $itm['volume'] = isset($itm['volume'])?$itm['volume']:$itm['tk_total_sales'];
                        $save_data[$itm['coupon_id']] = $itm;
                    }
                }

                $request_type = 1;
                $request_filler_count = count($save_data);
                $redis->set($redis_key, json_encode($save_data), 600);
            } else {
                $request_type = 2;
                $resp['results']['tbk_coupon'] = [];
                $this->code = 0;
                $this->content = $resp;
                return;
            }
        } else {

            $count = count($cache_data);
            $cache_count = $count;

            if ($count < $page * $size) {
                $tbk_data = $this->tbk_req_get($form, $q, $type, $page, '80', 'total_sales_des');
                $new_data = [];
                if (isset($tbk_data['result_list']['map_data'])) {
                    $request_count = count($tbk_data['result_list']['map_data']);
                    foreach ($tbk_data['result_list']['map_data'] as $key => $itm) {
                        if (!isset($new_data[$itm['coupon_id']])) {
                            $decrease_price = (float)$this->get_between($itm['coupon_info'], '减', '元');   
                            if ($decrease_price < 5 || ($itm['zk_final_price'] - $decrease_price>=5000)) {
                                continue;
                            }
                            if(!isset($itm['small_images'])){
                                $itm['small_images']['string'] = array($itm['pict_url']);
                            }
                            $itm['decrease_price'] = $decrease_price;
                            $itm['final_price'] = $itm['zk_final_price'] - $itm['decrease_price'];
                            $itm['nick'] = $itm['shop_title'];
                            $itm['rate_price'] = $itm['final_price']*$itm['commission_rate'];
                            $itm['coupon_click_url'] = strpos($itm['coupon_share_url'], 'http') == false ? 'https:' . $itm['coupon_share_url'] : $itm['coupon_share_url'];
                            $itm['item_description'] = $itm['coupon_info'];
                            $itm['category'] = 30;
                            $itm['volume'] = isset($itm['volume'])?$itm['volume']:$itm['tk_total_sales'];
                            $new_data[$itm['coupon_id']] = $itm;
                        }
                    }

                    $request_type = 3;
                    $request_filler_count = count($new_data);
                    $save_data = array_merge($cache_data, $new_data);
                    $redis->set($redis_key, json_encode($save_data), 600);
                } else {
                    $request_type = 4;
                    $resp['results']['tbk_coupon'] = [];
                    $this->code = 0;
                    $this->content = $resp;
                    return;
                }

            } else {
                $request_type = 5;
                $save_data = $cache_data;
            }
        }

        if ($sort) {
            switch ($sort) {
                case 'price_asc':
                    array_multisort(array_column($save_data, 'final_price'), SORT_ASC, $save_data);
                    break;
                case 'price_desc':
                    array_multisort(array_column($save_data, 'final_price'), SORT_DESC, $save_data);
                    break;
                case 'volume_asc':
                    array_multisort(array_column($save_data, 'volume'), SORT_ASC, $save_data);
                    break;
                case 'volume_desc':
                    array_multisort(array_column($save_data, 'volume'), SORT_DESC, $save_data);
                    break;
            }
        } else {
            // array_multisort(array_column($resp['result_list']['map_data'],'decrease_price'),SORT_DESC,$resp['result_list']['map_data']);
            array_multisort(array_column($save_data, 'rate_price'), SORT_DESC, $save_data, array_column($save_data, 'decrease_price'), SORT_DESC, $save_data);
        }


        $resp['results']['tbk_coupon'] = array_slice(array_values($save_data), ($page - 1) * $size, $size);
//        $resp['results']['tbk_coupon'] = array_values($save_data);
        $resp['results']['request_count'] = $request_count;
        $resp['results']['request_filler_count'] = $request_filler_count;
        $resp['results']['cache_count'] = $cache_count;
        $resp['results']['now_count'] = count($save_data);
        $resp['results']['request_type'] = $request_type;
        $this->code = 0;
        $this->content = $resp;
    }

//通用物料搜索API（导购）====add by dallon end============================================================================


    public function upload_goods() {
        $this->code = 1291;
        return;
        $shop = $this->model->table('shop')->where('user_id=' . $this->user['id'])->find();
        $promoter = $this->model->table('district_promoter')->where('user_id=' . $this->user['id'])->find();
        if (!$promoter) {
            $this->code = 1264;
            return;
        }
        $salt = CHash::random(6);
        if (!$shop) {
            $shop_data = array(
                'name'            => $promoter['shop_name'] != '' ? $promoter['shop_name'] : $this->user['nickname'],
                'user_id'         => $this->user['id'],
                'subtitle'        => '',
                'website'         => '',
                'address'         => '',
                'content'         => '',
                'seo_title'       => '',
                'seo_keywords'    => '',
                'seo_description' => '',
                'sale_protection' => '',
                'category_id'     => 40,
                'freeshipping'    => Filter::int(Req::args('freeshipping')),
                'star'            => 0,
                'img'             => $promoter['picture'] != null ? $promoter['picture'] : 'http://www.ymlypt.com/themes/mobile/images/logo-new.png',
                'imgs'            => serialize(array()),
                'username'        => $promoter['shop_name'] != '' ? $promoter['shop_name'] : $this->user['nickname'],
                'password'        => md5(md5('123456') . $salt),
                'salt'            => $salt,
                'create_time'     => date('Y-m-d H:i:s'),
                'up_time'         => date('Y-m-d H:i:s'),
                'state'           => 0
            );
            $shop_id = $this->model->table('shop')->data($shop_data)->insert();
        } else {
            $shop_id = $shop['id'];
        }
        $goods_no = '000' . rand(1111, 9999);
        $content_imgs = Filter::str(Req::args('content_imgs'));
        $imgs = Filter::str(Req::args('imgs'));
        $content_imgs_array = explode(',', $content_imgs);
        $content_imgs_str = '';
        if ($content_imgs_array) {
            if (is_array($content_imgs_array)) {
                foreach ($content_imgs_array as $k => $v) {
                    $content_imgs_str .= "<img src=" . $v . " />";
                }
            }
        }
        $imgs_array = explode(',', $imgs);
        $imgs_str = '';
        $img = '';
        if ($imgs_array) {
            if (is_array($imgs_array)) {
                $img = $imgs_array[0];
                $imgs_str = serialize($imgs_array);
            }
        }
        $goods_data = array(
            'name'             => Filter::str(Req::args('name')),
            'subtitle'         => '',
            'category_id'      => Filter::int(Req::args('category_id')),
            'goods_no'         => $goods_no,
            'pro_no'           => $goods_no,
            'type_id'          => 0,
            'shop_id'          => $shop_id,
            'brand_id'         => 0,
            'unit'             => '件',
            'content'          => $content_imgs_str,
            'img'              => $img,
            'imgs'             => $imgs_str,
            'tag_ids'          => '',
            'sell_price'       => Filter::float(Req::args('sell_price')),
            'market_price'     => Filter::float(Req::args('sell_price')),
            'cost_price'       => Filter::float(Req::args('cost_price')),
            'create_time'      => date('Y-m-d H:i:s'),
            'store_nums'       => Filter::int(Req::args('store_nums')),
            'warning_line'     => 2,
            'seo_title'        => '',
            'seo_keywords'     => '',
            'seo_description'  => '',
            'weight'           => Filter::int(Req::args('weight')),
            'point'            => 0,
            'visit'            => 0,
            'favorite'         => 0,
            'sort'             => 1,
            'specs'            => serialize(array()),
            'attrs'            => serialize(array()),
            'prom_id'          => 0,
            'is_online'        => Filter::int(Req::args('is_online')),
            'sale_protection'  => '',
            'freeshipping'     => Filter::int(Req::args('freeshipping')),
            'personal_shop_id' => 0,
            'user_id'          => $this->user['id'],
            'type'             => 2
        );
        $goods_id = $this->model->table('goods')->data($goods_data)->insert();
        $product = $this->model->table('products')->where('goods_id=' . $goods_id)->find();
        if (!$product) {
            $product_data = array(
                'goods_id'     => $goods_id,
                'pro_no'       => $goods_no,
                'spec'         => serialize(array()),
                'store_nums'   => $goods_data['store_nums'],
                'warning_line' => 2,
                'market_price' => $goods_data['market_price'],
                'sell_price'   => $goods_data['sell_price'],
                'cost_price'   => $goods_data['cost_price'],
                'weight'       => Filter::int(Req::args('weight')),
                'specs_key'    => ''
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
        $ret = $result;
        $this->code = 0;
        $this->content = $ret;
        return;
    }

    public function fare_list() {
        $fare = $this->model->table('fare')->where('id=1 or is_default=1')->findAll();
        foreach ($fare as $k => $v) {
            $zone = unserialize($v['zoning']);
            $fare[$k]['zone_name'] = $zone;
            if ($v['id'] == 1) {
                $fare[$k]['desc'] = '全国所有地区免邮费';
            } else {
                $fare[$k]['desc'] = "默认运费：" . $v['first_weight'] . "(g)内," . $v['first_price'] . "元；每增加" . $v['second_weight'] . "(g)，增加运费" . $v['second_price'] . "元";
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
        if (!$page) {
            $page = 1;
        }
        if (!$is_online) {
            $is_online = 0;
        }
        $where = "user_id=" . $this->user['id'] . " and is_online=" . $is_online;
        $order = 'id desc';
        if ($sort == 1) {
            $order = 'create_time desc';
        }
        if ($sort == 2) {
            $order = 'create_time asc';
        }

        $list = $this->model->table('goods')->fields('id,name,category_id,img,sell_price,create_time,store_nums,is_online,base_sales_volume')->where($where)->order($order)->findPage($page, 10);
        if ($list) {
            if (isset($list['data']) && $list['data'] != null) {
                foreach ($list['data'] as $k => $v) {
                    $sales_volume = $this->model->table("order_goods as og")->join("left join order as o on og.order_id = o.id")->where("og.goods_id = " . $v['id'] . " and o.status in (3,4)")->fields("SUM(og.goods_nums) as sell_volume")->find();
                    $sales_volume = $sales_volume['sell_volume'] == NULL ? 0 : $sales_volume['sell_volume'];
                    $list['data'][$k]['sales_volume'] = $v['base_sales_volume'] + $sales_volume;
                    $list['data'][$k]['share_url'] = 'http://www.ymlypt.com/product-' . $v['id'] . '.html';
                }
                if ($sort == 3) {
                    array_multisort(array_column($list['data'], 'sales_volume'), SORT_DESC, $list['data']);
                }
                if ($sort == 4) {
                    array_multisort(array_column($list['data'], 'sales_volume'), SORT_ASC, $list['data']);
                }
            }    
            
            unset($list['html']);
        }
        $this->code = 0;
        $this->content = $list;
        return;
    }

    public function manage_my_goods() {
        $this->code = 1291;
        return;
        $type = Filter::int(Req::args('type'));
        $id = Filter::int(Req::args('id'));
        if ($type == 0 || $type == 1) {
            $this->model->table('goods')->data(['is_online' => $type])->where('id=' . $id)->update();
        } else {
            $this->model->table('goods')->where('id=' . $id)->delete();
            $this->model->table('product')->where('goods_id=' . $id)->delete();
        }
        $this->code = 0;
        return;
    }

    public function goods_detail() {
        $id = Filter::int(Req::args('id'));
        $info = $this->model->table('goods')->fields('id,name,category_id,img,imgs,sell_price,create_time,store_nums,is_online,content,freeshipping,base_sales_volume,weight')->where('id=' . $id)->find();
        if ($info) {
            $info['imgs'] = unserialize($info['imgs']);
            $sales_volume = $this->model->table("order_goods as og")->join("left join order as o on og.order_id = o.id")->where("og.goods_id = " . $info['id'] . " and o.status in (3,4)")->fields("SUM(og.goods_nums) as sell_volume")->find();
            $sales_volume = $sales_volume['sell_volume'] == NULL ? 0 : $sales_volume['sell_volume'];
            $info['sales_volume'] = $info['base_sales_volume'] + $sales_volume;
            $html = '<!DOCTYPE html><html><head><title></title><meta charset="UTF-8">';
            $html .= '<meta name="viewport" content="width=device-width, initial-scale=1.0"></head>';
            $html .= '<body><div>' . $info['content'] . '</div></body></html>';
            $info['content'] = $html;
        }
        $this->code = 0;
        $this->content = $info;
        return;
    }

    public function super_unique($array, $recursion = false) {
        // 序列化数组元素,去除重复
        $result = array_map('unserialize', array_unique(array_map('serialize', $array)));
        // 递归调用
        if ($recursion) {
            foreach ($result as $key => $value) {
                if (is_array($value)) {
                    $result[$key] = super_unique($value);
                }
            }
        }
        return $result;
    }

    public function build_inviteship_goods_qrcode() {
        $goods_id = Filter::int(Req::args('goods_id'));
        $flag = Filter::int(Req::args('flag'));
        if(!$goods_id || !$flag) {
            $this->code = 1270;
            return;
        }
        $qr_info = $this->model->table("promote_qrcode")->where("id=".$flag)->find();
        if($qr_info){
            $inviter_id=$qr_info['user_id'];
            Common::buildInviteShip($inviter_id, $this->user['id'], "goods_qrcode");
            $this->model->query("update tiny_promote_qrcode set scan_times = scan_times + 1 where id = $flag");
        }
        $this->code = 0;
        $this->content['goods_id'] = $goods_id;
        return;
    }

    public function tbk_get_height_url() {
        $item_id = Filter::str(Req::args('item_id'));
        $type = Filter::str(Req::args('type'));
        if(!$type) {
            $type = 2;
        }
        if($type == 1) {
            $user_id = Common::getInviterId($this->user['id']); //自己领券用上级id
        } else {
            $user_id = $this->user['id']; //分享给别人领券用自己id
        }
        $objs = $this->model->table('user')->where('id='.$user_id)->find();
        if($objs['adzoneid']==null) {
            $taobao_pid = $this->model->table('taoke_pid')->where('user_id is NULL')->order('id desc')->find();
            if($taobao_pid) {
                $this->model->table('taoke_pid')->data(['user_id'=>$user_id])->where('id='.$taobao_pid['id'])->update();
                $this->model->table('user')->data(['adzoneid'=>$taobao_pid['adzoneid']])->where('id='.$user_id)->update();
            }
        }

        $taoke = $this->model->table('taoke_pid')->fields('adzoneid,memberid,siteid')->where('user_id='.$user_id)->find();
        
        if(!$taoke) {
            $this->code = 1271;
            return;
        }
        $access_token = "7000210123803564aae498aa94b9ca1602de733fbf239a967511f36d3ae5e8e7ec3777f3870059548";
        $main_hightapi_url = 'http://193.112.121.99/xiaocao/hightapi.action';
        $bak_hightapi_url = 'http://119.29.94.164/xiaocao/hightapi.action';
  
        // $params = ['token' => $access_token, 'item_id' => $item_id, 'adzone_id' => $taoke['adzoneid'], 'site_id' => $taoke['siteid'], 'qq' => '2116177952'];
        $params = ['token' => $access_token, 'item_id' => $item_id, 'adzone_id' => $taoke['adzoneid'], 'site_id' => $taoke['siteid'], 'qq' => '1223354181'];
        $req_url = $main_hightapi_url . "?" . http_build_query($params);

        $return = json_decode(file_get_contents($req_url), true);
        if(!isset($return['result']['data']['coupon_info'])) {
            $coupon_click_url = $return['result']['data']['coupon_click_url'];
            
        }
        $return['e'] = end(explode('?', $return['result']['data']['coupon_click_url']));
        $this->code = 0;
        $this->content = $return;
        return;
    }

    public function parse_url_param($str)
    {
        $data = array();
        $parameter = explode('&', end(explode('?', $str)));
        foreach ($parameter as $val) {
            $tmp = explode('=', $val);
            $data[$tmp[0]] = $tmp[1];
        }
        return $data;
    }

    public function taobao_tpwd_share() {
        $num_iid = Filter::str(Req::args("num_iid"));
        if(!$num_iid) {
            $num_iid = '553057896190';
        }
        $tao_str = Filter::str(Req::args("tao_str"));
        $this->code = 0;
        $this->content['url'] = "http://www.ymlypt.com/travel/tao_share?num_iid={$num_iid}&tao_str={$tao_str}";
        return;
    }
}
