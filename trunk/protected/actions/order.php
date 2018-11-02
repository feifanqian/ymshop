<?php

class OrderAction extends Controller {

    public $model = null;
    public $user = null;
    public $code = 1000;
    public $content = NULL;
    private $cart = array();
    private $selectcart = array();

    public function __construct() {
        $this->model = new Model();
        $selectids = Req::args('selectids');
        $cart = Cart::getCart();
        $this->cart = $cart->all();
        $this->selectcart = $this->cart;
        $this->assign("cart", $this->cart);
        //如果选择了商品,则只添加选中的商品
        if ($selectids) {
            $cart = array();
            foreach ($this->cart as $k => $v) {
                if (in_array($v['id'], $selectids)) {
                    $cart[] = $v;
                }
            }
            $this->assign("cart", $cart);
            $this->selectcart = $cart;
        }
    }

    //普通订单,确认订单
    public function confirm() {
        $type = Req::args('cart_type');
        $uid = Filter::int(Req::args("user_id"));
        $session_id = Req::args("session_id");
        $selectids = Req::args("selectids");
        //直接购买类
        if ($type == 'goods') {
            $cart = Cart::getCart('goods');
            // if($uid || $session_id) {    
            //   $this->cart = $cart->all($uid,$session_id);
            // }else {
            //   $this->cart = $cart->all();
            // }
            $this->cart = $cart->all();
            $this->selectcart = $this->cart;
        } else {
            $cart = Cart::getCart('cart');
            if(is_array($selectids)) {
                $product_ids = implode(',',$selectids);
            } else {
                $product_ids = substr($selectids,1,strlen($selectids)-2);
            }
            
            if($uid || $session_id) {    
              $this->cart = $cart->alls($uid,$session_id,$product_ids);
            }else {
              $this->cart = $cart->all();
            }
            $this->selectcart = $this->cart;
        }
        //如果选择的商品为空
        if (!$this->selectcart) {
            $this->code = 1041;
            return;
        }
        $cartlist = $this->selectcart;
        foreach ($cartlist as $k => &$v) {
            $v['spec'] = array_values($v['spec']);
        }
        $this->content = array();
        $this->content['cartlist'] = $cartlist;
        $this->parserOrder();
        $this->code = 0;
    }

    //非普通促销确认订单(促销抢购类订单)
    public function info() {
        $id = Filter::int(Req::args('id'));
        $product_id = Req::args('pid');
        $type = Req::args("type");
        if ($type == 'groupbuy') {
            $product_id = Filter::int($product_id);
            $model = new Model("groupbuy as gb");
            $item = $model->join("left join goods as go on gb.goods_id=go.id left join products as pr on pr.goods_id=gb.goods_id")->fields("*,pr.id as product_id,pr.store_nums")->where("gb.id=$id and pr.id=$product_id")->find();
            if ($item) {
                $start_diff = time() - strtotime($item['start_time']);
                $end_diff = time() - strtotime($item['end_time']);
                if ($item['is_end'] == 0 && $start_diff >= 0 && $end_diff < 0 && $item['store_nums'] > 0) {
                    $product = $this->packGroupbuyProducts($item);
                    $this->assign("product", $product);
                } else {
                    $this->code = 1054;
                    return;
                }
            } else {
                $this->code = 1051;
                return;
            }
        } else if ($type == 'flashbuy') {
            $model = new Model("flash_sale as fb");
            $product_id = Filter::int($product_id);
            $item = $model->join("left join goods as go on fb.goods_id=go.id left join products as pr on pr.goods_id=fb.goods_id")->fields("*,pr.id as product_id,pr.store_nums")->where("fb.id=$id and pr.id=$product_id")->find();
            if ($item) {
                $start_diff = time() - strtotime($item['start_time']);
                $end_diff = time() - strtotime($item['end_time']);
                if ($item['is_end'] == 0 && $start_diff >= 0 && $end_diff < 0 && $item['store_nums'] > 0) {
                    $product = $this->packFlashbuyProducts($item);
                    $this->assign("product", $product);
                } else {
                    $this->code = 1054;
                    return;
                }
            } else {
                $this->code = 1052;
                return;
            }
        } else if ($type == 'bundbuy') {
            //确认捆绑存在有效且所有的商品都在其中包括个数完全正确
            $product_id = trim($product_id, "-");
            $product_id_array = explode("-", $product_id);
            foreach ($product_id_array as $key => $val) {
                $product_id_array[$key] = Filter::int($val);
            }
            $product_ids = implode(',', $product_id_array);
            $product_id = implode('-', $product_id_array);
            $model = new Model("bundling");
            $bund = $model->where("id=$id")->find();
            if ($bund) {
                $goods_id_array = explode(',', $bund['goods_id']);

                $products = $model->table("goods as go")->join("left join products as pr on pr.goods_id=go.id")->where("pr.id in ($product_ids)")->fields("*,pr.id as product_id")->group("go.id")->findAll();
                //检测库存与防偷梁换柱
                foreach ($products as $value) {
                    if ($value['store_nums'] <= 0 || !in_array($value['goods_id'], $goods_id_array)) {
                        $this->code = 1054;
                        return;
                    }
                }
                if (count($goods_id_array) == count($products)) {
                    $product = $this->packBundbuyProducts($products);
                    $this->assign("product", $product);
                    $this->assign("bund", $bund);
                } else {
                    $this->code = 1054;
                    return;
                }
                $product_id = $product_id;
            } else {
                $this->code = 1053;
                return;
            }
        }else if($type=='pointbuy'){
            $model = new Model("point_sale as ps");
            $product_id = Filter::int($product_id);
            $item = $model->join("left join goods as go on ps.goods_id=go.id left join products as pr on pr.goods_id=ps.goods_id")->fields("*,pr.id as product_id,pr.store_nums")->where("ps.id=$id and pr.id=$product_id")->find();
            if ($item) {
               $order_products = $this->packPointbuyProducts($item);
            } else {
               $this->code = 1148;
               return;
            }
        }
        $this->assign("id", $id);
        $this->assign("order_type", $type);
        $this->assign("pid", $product_id);
        $this->parserOrder();
        $this->code = 0;
    }
    
    public function flashStatus($prom_id,$quota_num,$user_id){
        $model = new Model();
        // $history =  $model->table("order")->where("type = 2 and prom_id = $prom_id and pay_status=0 and status not in (5,6) and is_del != 1 and user_id =".$user_id)->count();
        // if($history>0){
        //    $this->code = 1108;
        //    return;
        // }
        $flash_sale = $model->table('flash_sale')->where('id='.$prom_id)->find();
        if($flash_sale){
            if($flash_sale['is_end'] == 1){
                $this->code = 1203;
                return;
            }
            $start_time = $flash_sale['start_time'];
            $end_time = $flash_sale['end_time'];
            $had_bought = $model->table('order')->where("pay_time between '{$start_time}' and '{$end_time}' and type=2 and pay_status=1 and prom_id=".$prom_id." and user_id=".$user_id)->count();
            if($flash_sale['is_limit']==1){
                if($had_bought>=$flash_sale['quota_num']){
                     $this->code = 1204;
                     return;       
                }
            }
            $sum1 = $model->query("select SUM(og.goods_nums) as sum from tiny_order as od left join tiny_order_goods as og on od.id = og.order_id where od.prom_id = $prom_id and od.type = 2 and od.pay_status = 1 and od.status !=6");
            if($sum1[0]['sum']>= $flash_sale['max_num']){
                $this->code = 1206;
                return;
            }
            $five_minutes = strtotime('-5 minutes');
            $sum2 = $model->query("select SUM(og.goods_nums) as sum from tiny_order as od left join tiny_order_goods as og on od.id = og.order_id where od.prom_id = $prom_id and od.type = 2 and UNIX_TIMESTAMP(od.create_time)>".$five_minutes);
            if($sum2[0]['sum']>= $flash_sale['max_num']){
                $this->code = 1207;
                return;         
            }
        }
        
        $sum = $model->query("select SUM(og.goods_nums) as sum from tiny_order as od left join tiny_order_goods as og on od.id = og.order_id where od.prom_id = $prom_id and od.type = 2 and od.pay_status = 1 and od.status !=6 and od.user_id = $user_id");
        if($sum[0]['sum']>= $quota_num){
            $this->code = 1109;
            return;
        }
              
    }
    
    //提交订单
    public function submit() {
        $address_id = Filter::int(Req::args('address_id'));
        $payment_id = Filter::int(Req::args('payment_id'));
        $prom_id = Filter::int(Req::args('prom_id'));
        $is_invoice = Filter::int(Req::args('is_invoice'));
        $invoice_type = Filter::int(Req::args('invoice_type'));
        $invoice_title = Filter::text(Req::args('invoice_title'));
        $user_remark = Filter::txt(Req::args('user_remark'));
        $voucher_id = Filter::int(Req::args('voucher'));
        $join_id = Filter::int(Req::args('join_id'));
        $cart_type = Req::args('cart_type');
        $selectids = Req::args('selectids');
        //非普通促销信息
        $type = Req::args("type");
        $id = Filter::int(Req::args('id'));
        $product_id = Req::args('product_id');
        $buy_num = Req::args('buy_num');
        $uid = Filter::int(Req::args('user_id'));
        $session_id = Req::args('session_id');
        if(!$buy_num) {
            $buy_num = Req::args('buynums');
        }
        if (!$address_id || !$payment_id || ($is_invoice == 1 && $invoice_title == '')) {

            if (is_array($product_id)) {
                foreach ($product_id as $key => $val) {
                    $product_id[$key] = Filter::int($val);
                }
                $product_id = implode('-', $product_id);
            } else {
                $product_id = Filter::int($product_id);
            }
            $data = Req::args();
            $data['is_invoice'] = $is_invoice;
            if (!$address_id){
                $this->code = 1055;
                return;
            }elseif (!$payment_id){
                $this->code = 1056;
                return;
            }else{
                $this->code = 1057;
                return;
            }  
        }
        //地址信息
        $address_model = new Model('address');
        $address = $address_model->where("id=$address_id and user_id=" . $this->user['id'])->find();
        if (!$address) {
            $this->code = 1055;
            return;
        }
        //if(!$payment_id)$this->redirect("order",false,Req::args());
        //订单类型: 0普通订单 1团购订单 2限时抢购 3捆绑促销
        $order_type = 0;
        $model = new Model('');
        //团购处理
        if ($type == "groupbuy") {
            if(is_array($product_id)) {
                $product_id = Filter::int($product_id[0]);
            } else {
                $product_id = substr($product_id,1,strlen($product_id)-2);
                $product_id = Filter::int($product_id);
            }
            
            $num = isset($buy_num[0])?Filter::int($buy_num[0]):1;
            if ($num < 1)
                $num = 1;
            
            // $item = $model->table("groupbuy as gb")->join("left join goods as go on gb.goods_id=go.id left join products as pr on pr.id=$product_id")->fields("gb.*,go.name,go.store_nums,go.img,go.sell_price,go.weight,go.shop_id,go.point,pr.id as product_id,pr.spec,go.freeshipping")->where("gb.id=$id")->find();
            $item = $model->table("goods as go")->join("left join groupbuy as gb on gb.goods_id=go.id left join products as pr on pr.goods_id=go.id")->fields("gb.*,go.name,go.store_nums,go.img,go.sell_price,go.weight,go.shop_id,go.point,pr.id as product_id,pr.spec,go.freeshipping")->where("gb.id=$id and pr.id=$product_id")->find();
            $order_products = $this->packGroupbuyProducts($item, $num);
            $groupbuy = $model->table("groupbuy")->where("id=$id")->find();
            unset($groupbuy['description']);
            $data['prom'] = serialize($groupbuy);
            $data['prom_id'] = $id;
            $data['join_id'] = $join_id;
            $order_type = 1;
        }else if ($type == "flashbuy") {//抢购处理
            $product_id = Filter::int($product_id[0]);
            $num = isset($buy_num[0])?Filter::int($buy_num[0]):1;
            if ($num < 1)
                $num = 1;
            $item = $model->table("flash_sale as fb")->join("left join goods as go on fb.goods_id=go.id left join products as pr on pr.id=$product_id")->fields("fb.*,go.name,go.sell_price,go.img,go.freeshipping,go.point,go.shop_id,go.weight,go.store_nums,pr.id as product_id,pr.spec")->where("fb.id=$id")->find();
            $this->flashStatus($id, $item['quota_num'], $this->user['id']);
            $order_products = $this->packFlashbuyProducts($item, $num);
            $flashbuy = $model->table("flash_sale")->where("id=$id")->find();
            unset($flashbuy['description']);
            $data['prom'] = serialize($flashbuy);
            $data['prom_id'] = $id;
            $data['point']=$item['send_point']*$num;
            $order_type = 2;
        }else if ($type == "bundbuy") {//捆绑销售处理
            if (is_array($product_id)) {
                foreach ($product_id as $key => $val) {
                    $product_id[$key] = Filter::int($val);
                }
            } else {
                $product_id = Filter::int($product_id);
            }

            $product_ids = implode(',', $product_id);
            $num = isset($buy_num[0])?Filter::int($buy_num[0]):1;

            $model = new Model("bundling");
            $bund = $model->where("id=$id")->find();

            if ($bund) {
                $goods_id = $bund['goods_id'];
                $products = $model->table("goods as go")->join("left join products as pr on pr.goods_id=go.id")->where("pr.id in ($product_ids)")->fields("*,pr.id as product_id,pr.spec")->group("go.id")->findAll();
                $order_products = $this->packBundbuyProducts($products, $num);
            }

            $bundbuy = $model->table("bundling")->where("id=$id")->find();
            unset($bundbuy['description']);
            $data['prom'] = serialize($bundbuy);
            $data['prom_id'] = $id;
            $current = current($order_products);
            $bundbuy_amount = sprintf("%01.2f", $bund['price']) * $current['num'];

            $order_type = 3;
        }else if($type=='pointbuy'){
            $product_id = Filter::int($product_id[0]);
            $num = isset($buy_num[0])?Filter::int($buy_num[0]):1;
            if ($num < 1)
                $num = 1;
            $pointbuy = $model->table("point_sale as ps")
                    ->join("left join goods as go on ps.goods_id=go.id left join products as pr on pr.goods_id=ps.goods_id")
                    ->fields("*,pr.id as product_id,pr.spec")
                    ->where("ps.id=$id and pr.id = $product_id")
                    ->find();
            if(empty($pointbuy)){
               $this->code = 1148;
               return;
            }
            $order_products = $this->packPointbuyProducts($pointbuy, $num);
            $user_point_coin = $model->table("customer")->where("user_id=".$this->user['id'])->fields('point_coin')->find();
            if(empty($user_point_coin)||!isset($user_point_coin['point_coin'])){
               $this->code = 1005;
               return;
            }else{
                if($user_point_coin['point_coin']<$order_products[$product_id]['point']){
                    $this->code = 1149;
                    return;
                }else{
                    $office_point_coin = Common::getOfficialPromoterPointCoin($this->user['id']);
                    if($user_point_coin['point_coin']-$office_point_coin<$order_products[$product_id]['point']){
                        $this->code = 1151;
                        return;
                    }
                }
            }
            $data['pay_point'] = $order_products[$product_id]['point'];
            $data['prom'] = serialize($pointbuy);
            $data['prom_id'] = $id;
            $order_type = 5;
        }else if($type == 'weibuy' || $type == 'pointwei'){
                        $product_id = Filter::int($product_id[0]);
                        $num = Filter::int($buy_num[0]);
                        if ($num < 1)
                            $num = 1;
                        $item = $model->table("pointwei_sale as ps")
                                ->join("left join goods as go on ps.goods_id=go.id left join products as pr on pr.goods_id=ps.goods_id")
                                ->fields("*,pr.id as product_id,pr.spec")
                                ->where("ps.id=$id and pr.id = $product_id")
                                ->find();
                        $weibuy = $model->table("pointwei_sale")->where("id=$id")->find();
                        if(empty($weibuy)||empty($item)){
                            $this->code = 1208;
                            return;
                        }
                        $order_products = $this->packWeibuyProducts($item, $num);
                        $user_point_coin = $model->table("customer")->where("user_id=".$this->user['id'])->fields('point_coin')->find();
                        if(empty($user_point_coin)||!isset($user_point_coin['point_coin'])){
                            $this->code = 1211;
                            return;
                        }else{
                            if($user_point_coin['point_coin']<$order_products[$product_id]['point']){
                                $this->code = 1212;
                                return;
                            }else{
                                $office_point_coin = Common::getOfficialPromoterPointCoin($this->user['id']);
                                if($user_point_coin['point_coin']-$office_point_coin<$order_products[$product_id]['point']){
                                    $this->code = 1213;
                                    return;
                                }
                            }
                        }
                        $data['pay_point'] = $order_products[$product_id]['point'];
                        $data['prom'] = serialize($weibuy);
                        $data['prom_id'] = $id;
                        $order_type = 7;
                }else if($type=="pointflash"){
                        $product_id = Filter::int($product_id[0]);
                        $num = isset($buy_num[0])?Filter::int($buy_num[0]):1;
                        if ($num < 1)
                            $num = 1;
                        $item = $model->table("pointflash_sale as ps")
                                ->join("left join goods as go on ps.goods_id=go.id left join products as pr on pr.goods_id=ps.goods_id")
                                ->fields("*,go.id as goods_id,pr.id as product_id,pr.spec")
                                ->where("ps.id=$id and pr.id = $product_id")
                                ->find();
                        $pointflash = $model->table("pointflash_sale")->where("id=$id")->find();
                        if(empty($pointflash)||empty($item)){
                            $this->code = 1214;
                            return;
                        }
                        $start_diff = time() - strtotime($pointflash['start_date']);
                        $end_diff = time() - strtotime($pointflash['end_date']);
                        if ($pointflash['is_end'] == 0 && $start_diff >= 0 && $end_diff < 0) {
                            $this->pointflashStatus($id,$pointflash['quota_count'],$this->user['id'],true);
                            $order_products = $this->packPointFlashProducts($item);
                            $user_point_coin = $model->table("customer")->where("user_id=".$this->user['id'])->fields('point_coin')->find();
                            if(empty($user_point_coin)||!isset($user_point_coin['point_coin'])){
                                $this->code = 1215;
                                return;
                            }else{
                                if($user_point_coin['point_coin']<$order_products[$product_id]['point']){
                                    $this->code = 1212;
                                    return;
                                }else{
                                    $office_point_coin = Common::getOfficialPromoterPointCoin($this->user['id']);
                                    if($user_point_coin['point_coin']-$office_point_coin<$order_products[$product_id]['point']){
                                        $this->code = 1213;
                                        return;
                                    }
                                }
                            }
                            $data['pay_point'] = $order_products[$product_id]['point'];
                            $data['prom'] = serialize($pointflash);
                            $data['prom_id'] = $id;
                            $order_type = 6;
                        } else {
                            $this->code = 1209;
                            return;
                        }
                }
        if ($order_type == 0) {
            if ($cart_type == 'goods') {
                $cart = Cart::getCart('goods');
                $order_products = $cart->all();
            } else {
                // $cart = Cart::getCart();
                // $order_products = $this->selectcart;
                if(!$selectids) {
                    $this->code = 1287;
                    return;
                }
                $cart = Cart::getCart('cart');
                if(is_array($selectids)) {
                    $product_ids = implode(',',$selectids);
                } else {
                    $product_ids = substr($selectids,1,strlen($selectids)-2);
                }
                if($uid || $session_id) {    
                  $order_products = $cart->alls($uid,$session_id,$product_ids);
                }else {
                  $order_products = $cart->all();
                }
            }
            $data['prom_id'] = $prom_id;
        }

        //检测products 是否还有数据
        if (empty($order_products)) {
            $this->code = 1058;
            return;
        }
         //=================限购处理==============
                foreach ($order_products as $v){
                    $buy_goods_id = $v['goods_id'];
                    $buy_goods_num = $v['num'];
                    //查询限购数量
                    $limit_info = $model->table("goods")->where("id=$buy_goods_id")->fields("limit_buy_num,name,type")->find();
                    if($limit_info['limit_buy_num']<=0){
                        break;
                    }
                    if($limit_info['type']==2) {
                        $this->code = 1300;
                        return;
                    }
                    
                    //查询用户购买此商品的数量
                    $buyed = $model->table("order as o")
                            ->fields("SUM(`goods_nums`) as buyed_num")
                            ->join("order_goods as og on og.order_id = o.id")
                            ->where("o.user_id =".$this->user['id']." and o.status!=5 and o.status!=6 and o.create_time>'2017-03-09 00:00:00' and og.goods_id =$buy_goods_id")
                            ->find();     
                    $buyed_num = $buyed['buyed_num']==NULL?0:$buyed['buyed_num'];
                    if($limit_info['limit_buy_num']<($buy_goods_num+$buyed_num)){
                        $this->code=1117;
                        return;
                    }
                }
        
        //多物流商品总金额,重量,积分计算
        $payable_amount = 0.00;
        $real_amount = 0.00;
        $weight = 0;
        $point = 0;
        $productarr = array();
        foreach ($order_products as $item) {
            $payable_amount+=$item['sell_total'];
            $real_amount+=$item['amount'];
            if(isset($item['freeshipping'])){
                if ($item['freeshipping']==0) {
                    $weight += $item['weight'] * $item['num'];
                } else {
                    $weight += 0;
                }
            }
            $point += $item['point'] * $item['num'];
            $productarr[$item['id']] = $item['num'];
        }
        if ($order_type == 3)
            $real_amount = $bundbuy_amount;

        //计算运费
        $fare = new Fare($weight);
        $payable_freight = $fare->calculate($address_id, $productarr);
        if($weight==0){
            $payable_freight = '0.00';
        }
        $real_freight = $payable_freight;
        //计算订单优惠
        $prom_order = array();
        $discount_amount = 0;
        if ($order_type == 0) {
            if ($prom_id) {
                $prom = new Prom($real_amount);
                $prom_order = $model->table("prom_order")->where("id=$prom_id")->find();

                //防止非法会员使用订单优惠
                $user = $this->user;
                $group_id = ',0,';
                if (isset($user['group_id']))
                    $group_id = ',' . $user['group_id'] . ',';

                if (stripos(',' . $prom_order['group'] . ',', $group_id) !== false) {
                    $prom_parse = $prom->parsePorm($prom_order);
                    $discount_amount = $prom_parse['value'];
                    if ($prom_order['type'] == 4)
                        $discount_amount = $payable_freight;
                    else if ($prom_order['type'] == 2) {
                        $multiple = intval($prom_order['expression']);
                        $multiple = $multiple == 0 ? 1 : $multiple;
                        $point = $point * $multiple;
                    }
                    $data['prom'] = serialize($prom_order);
                } else
                    $data['prom'] = serialize(array());
            }
        }
        //税计算
        $tax_fee = 0;
        $config = Config::getInstance();
        $config_other = $config->get('other');
        $open_invoice = isset($config_other['other_is_invoice']) ? !!$config_other['other_is_invoice'] : false;
        $tax = isset($config_other['other_tax']) ? intval($config_other['other_tax']) : 0;
        if ($open_invoice && $is_invoice) {
            $tax_fee = $real_amount * $tax / 100;
        }

        //代金券处理
        $voucher_value = 0;
        $voucher = array();
        if ($voucher_id) {
            $voucher = $model->table("voucher")->where("id=$voucher_id and is_send=1 and user_id=" . $this->user['id'] . " and status = 0 and '" . date("Y-m-d H:i:s") . "' <=end_time and '" . date("Y-m-d H:i:s") . "' >=start_time and money<=" . $real_amount)->find();
            if ($voucher) {
                $voucher_value = $voucher['value'];
                if ($voucher_value > $real_amount)
                    $voucher_value = $real_amount;
            }
        }
        //计算订单总金额
        $order_amount = $real_amount + $payable_freight + $tax_fee - $discount_amount - $voucher_value;

        foreach ($order_products as $item) {
            $info = $model->table("goods")->where("id=".$item['goods_id'])->fields("type")->find();
            if($info['type']==2) {
                $this->code = 1300;
                return;
            }
        }
        //填写订单
        $data['order_no'] = Common::createOrderNo();
        $data['user_id'] = $this->user['id'];
        $data['payment'] = $payment_id;
        $data['status'] = 2;
        $data['pay_status'] = 0;
        $data['accept_name'] = Filter::text($address['accept_name']);
        $data['phone'] = $address['phone'];
        $data['mobile'] = $address['mobile'];
        $data['province'] = $address['province'];
        $data['city'] = $address['city'];
        $data['county'] = $address['county'];
        $data['addr'] = Filter::text($address['addr']);
        $data['zip'] = $address['zip'];
        $data['payable_amount'] = $payable_amount;
        $data['payable_freight'] = $payable_freight;
        $data['real_freight'] = $real_freight;
        $data['create_time'] = date('Y-m-d H:i:s');
        $data['user_remark'] = $user_remark;
        $data['is_invoice'] = $is_invoice;
        if ($is_invoice == 1) {
            $data['invoice_title'] = $invoice_type . ':' . $invoice_title;
        } else {
            $data['invoice_title'] = '';
        }

        $data['taxes'] = $tax_fee;

        $data['discount_amount'] = $discount_amount;

        $data['order_amount'] = $order_amount;
        $data['real_amount'] = $real_amount;

        if(!isset($data['point'])){
            $data['point'] = $point;
        }
        $data['type'] = $order_type;
        $data['voucher_id'] = $voucher_id;
        $data['voucher'] = serialize($voucher);
        
        //将所属商家信息加入订单数据中
        $shop_ids = array();
        foreach ($order_products as $k => $v) {
                    $shop_ids[] = $v['shop_id'];
        }
        $data['shop_ids'] = implode(',', array_unique($shop_ids));
        //==================小区推广标示====================
        $flag =Filter::str(Req::args('flag'));
        if($flag!=NULL){
            $flag = trim($flag,',');
            $flag_arr = explode(',', $flag);
            $ids = implode(',', array_unique($flag_arr));
            $data['qr_flag']=$ids;
        }
        //===============================================
        //写入订单数据
        $order_id = $model->table("order")->data($data)->insert();
        //扣除使用的积分
        if($order_type==5&&$data['pay_point']>0){
            $model->table("customer")->data(array("point_coin"=>"`point_coin`-{$data['pay_point']}"))->where("user_id =".$this->user['id'])->update();
            Log::pointcoin_log($data['pay_point'],$this->user['id'], $data['order_no'], "积分购下单", 0);
        }
        //写入订单商品
        $tem_data = array();

        foreach ($order_products as $item) {
            $tem_data['order_id'] = $order_id;
            $tem_data['goods_id'] = $item['goods_id'];
            $tem_data['shop_id'] = $item['shop_id'];
            $tem_data['product_id'] = $item['id'];
            $tem_data['goods_price'] = $item['sell_price'];
            $tem_data['real_price'] = $item['real_price'];
            $tem_data['goods_nums'] = $item['num'];
            $tem_data['goods_weight'] = $item['weight'];
            $tem_data['prom_goods'] = serialize($item['prom_goods']);
            $tem_data['spec'] = serialize($item['spec']);
            $model->table("order_goods")->data($tem_data)->insert();
        }
        //发送提醒
        $NoticeService = new NoticeService();
        $data['user'] = $this->user['name'];
        $NoticeService->send('create_order', $data);
        //优惠券锁死
        if (!empty($voucher)) {
            $model->table("voucher")->where("id=$voucher_id and user_id=" . $this->user['id'])->data(array('status' => 2))->update();
        }
        //清空购物车与表单缓存
        if ($order_type == 0) {
            if ($cart_type == 'goods') {
                $cart->clear();
            } else {
                foreach ($this->selectcart as $k => $v) {
                    $cart->delItem($v['id']);
                }
            }
            Session::clear("order_status");
        }
        $this->code = 0;
        $this->content = $order_id;
    }

    public function pointflashStatus($prom_id,$quota_num,$user_id){
        $model = new Model();
        $history =  $model->table("order")->where("type = 6 and prom_id = $prom_id and pay_status=0 and status not in (5,6) and is_del != 1 and user_id =".$user_id)->count();
        if($history>0){
            $this->code = 1217;
            return;    
        }
        $flash_sale = $model->table('pointflash_sale')->where('id='.$prom_id)->find();
        if($flash_sale){
            if($flash_sale['is_end'] == 1 || $flash_sale['order_count']>=$flash_sale['max_sell_count']){
                $this->code = 1209;
                return;
            }
            $start_time = $flash_sale['start_date'];
            $end_time = $flash_sale['end_date'];
            $had_booght = $model->table('order')->where("type=6 and pay_status=1 and user_id=".$user_id." and pay_time>'{$start_time}' and pay_time<'{$end_time}'")->count();
            if($had_booght>=$flash_sale['quota_count']){
                $this->code = 1204;
                return;      
            } 
            $sum1 = $model->query("select SUM(og.goods_nums) as sum from tiny_order as od left join tiny_order_goods as og on od.id = og.order_id where od.prom_id = $prom_id and od.type = 6 and od.pay_status = 1 and od.status !=6 and od.pay_time>'{$start_time}' and od.pay_time<'{$end_time}'");
            if($sum1[0]['sum']>= $flash_sale['max_sell_count']){
                $this->code = 1206;
                return;
            }
            $five_minutes = strtotime('-5 minutes');
            $sum2 = $model->query("select SUM(og.goods_nums) as sum from tiny_order as od left join tiny_order_goods as og on od.id = og.order_id where od.prom_id = $prom_id and od.type = 6 and UNIX_TIMESTAMP(od.create_time)>".$five_minutes);
            if($sum2[0]['sum']>= $flash_sale['max_sell_count']){
                $this->code = 1207;
                return;
            }
        }

        $sum = $model->query("select SUM(og.goods_nums) as sum from tiny_order as od left join tiny_order_goods as og on od.id = og.order_id where od.prom_id = $prom_id and od.type = 6 and od.pay_status = 1 and od.status !=6 and od.user_id = $user_id");
        if($sum[0]['sum']>= $quota_num){
            $this->code = 1218;
            return;     
        }
               
    }

    //解析订单
    private function parserOrder() {
        $config = Config::getInstance();
        $config_other = $config->get('other');
        $open_invoice = isset($config_other['other_is_invoice']) ? !!$config_other['other_is_invoice'] : false;
        $tax = isset($config_other['other_tax']) ? intval($config_other['other_tax']) : 0;

        $area_ids = array();
        $addresslist = $this->model->table("address")->where("user_id=" . $this->user['id'])->order("is_default desc")->findAll();
        foreach ($addresslist as $add) {
            $area_ids[$add['province']] = $add['province'];
            $area_ids[$add['city']] = $add['city'];
            $area_ids[$add['county']] = $add['county'];
        }
        $area_ids = implode(",", $area_ids);
        $addressdict = array();
        if ($area_ids != '')
            $addressdict = $this->model->table("area")->where("id in($area_ids )")->findAll();
        $dictarr = array();
        foreach ($addressdict as $area) {
            $dictarr[$area['id']] = $area['name'];
        }

        foreach ($addresslist as $k => &$v) {
            $namearr = array();
            if (isset($dictarr[$v['province']])) {
                $namearr[] = $dictarr[$v['province']];
            }
            if (isset($dictarr[$v['city']])) {
                $namearr[] = $dictarr[$v['city']];
            }
            if (isset($dictarr[$v['county']])) {
                $namearr[] = $dictarr[$v['county']];
            }
            $v['address'] = implode(' ', $namearr);
        }
        unset($v);

        $model = new Model("voucher");
        $where = "user_id = " . $this->user['id'] . " and is_send = 1";
        $where .= " and status = 0 and '" . date("Y-m-d H:i:s") . "' <=end_time";
        $voucher = $model->where($where)->order("id desc")->findAll();

        $this->content["voucher"] = $voucher;
        $this->content["open_invoice"] = $open_invoice;
        $this->content["tax"] = $tax;
        $this->content["addresslist"] = $addresslist;
        $this->content["addressdict"] = $addressdict;
        $this->content["order_status"] = Session::get("order_status");
    }

    //打包团购订单商品信息
    private function packGroupbuyProducts($item, $num = 1) {
        $store_nums = isset($item['store_nums'])?$item['store_nums']:$item['max_num'];
        // $have_num = $item['max_num'] - $item['goods_num'];
        $have_num = $item['max_num'];
        if ($have_num > $store_nums)
            $have_num = $store_nums;
        if ($num > $have_num)
            $num = $have_num;
        $amount = sprintf("%01.2f", $item['price'] * $num);
        $sell_total = $item['sell_price'] * $num;
        $product_id = $item['product_id'];

        $product[$product_id] = array(
            'id' => $product_id, 
            'goods_id' => $item['goods_id'], 
            'name' => $item['name'], 
            'img' => $item['img'], 
            'num' => $num, 
            'store_nums' => $have_num, 
            'price' => $item['price'], 
            'spec' => unserialize($item['spec']), 
            'amount' => $amount, 
            'sell_total' => $sell_total, 
            'weight' => $item['weight'], 
            'point' => $item['point'],
            'freeshipping' => $item['freeshipping'], 
            "prom_goods" => array(), 
            "sell_price" => $item['sell_price'], 
            "real_price" => $item['price'],
            "shop_id"=>$item['shop_id']
            );
        return $product;
    }

    //打包抢购订单商品信息
    private function packFlashbuyProducts($item, $num = 1) {
        $store_nums = $item['store_nums'];
        $quota_num = $item['quota_num'];
        $have_num = $item['max_num'] - $item['goods_num'];
        if ($have_num > $store_nums)
            $have_num = $store_nums;
//        if ($have_num > $quota_num)
//            $have_num = $quota_num;
        if ($num > $have_num)
            $num = $have_num;
        $amount = sprintf("%01.2f", $item['price'] * $num);
        $sell_total = $item['sell_price'] * $num;
        $product_id = $item['product_id'];

        $product[$product_id] = array(
            'id' => $product_id, 
            'goods_id' => $item['goods_id'], 
            'name' => $item['name'], 
            'img' => $item['img'], 
            'num' => $num, 
            'store_nums' => $have_num, 
            'price' => $item['price'], 
            'spec' => unserialize($item['spec']), 
            'amount' => $amount, 
            'sell_total' => $sell_total, 
            'weight' => $item['weight'], 
            'point' => $item['point'], 
            'freeshipping' => $item['freeshipping'], 
            "prom_goods" => array(), 
            "sell_price" => $item['sell_price'], 
            "real_price" => $item['price'],
            'shop_id'=>$item['shop_id']
        );
        return $product;
    }

    //捆绑订单商品信息
    private function packBundbuyProducts($items, $num = 1) {
        $max_num = $num;
        foreach ($items as $prod)
            if ($max_num > $prod['store_nums'])
                $max_num = $prod['store_nums'];
        $num = $max_num;
        foreach ($items as $item) {
            $store_nums = $item['store_nums'];
            $amount = sprintf("%01.2f", $item['sell_price'] * $num);
            $sell_total = $item['sell_price'] * $num;
            $product_id = $item['product_id'];

            $product[$product_id] = array('id' => $product_id, 'goods_id' => $item['goods_id'], 'name' => $item['name'], 'img' => $item['img'], 'num' => $num, 'store_nums' => $item['store_nums'], 'price' => $item['sell_price'], 'spec' => unserialize($item['spec']), 'amount' => $amount, 'sell_total' => $sell_total, 'weight' => $item['weight'], 'point' => $item['point'], "prom_goods" => array(), "sell_price" => $item['sell_price'], "real_price" => $item['sell_price'],"shop_id"=>$item['shop_id']);
        }
        return $product;
    }
    //打包积分购订单商品信息
      private function packPointbuyProducts($item, $num = 1) {
        $price_set = unserialize($item['price_set']);
        if($item['store_nums']<=0){
           $this->code = 1054;
           exit();
        }
        if(is_array($price_set)){
           $real_price = $price_set[$item['product_id']]['cash'];
           $cash = $price_set[$item['product_id']]['cash']*$num;
           $point = $price_set[$item['product_id']]['point']*$num;
        }else{
           $this->code = 1005;
           exit();
        }
        $sell_total = $item['sell_price'] * $num;
        $product[$item['product_id']] = array(
            'id' => $item['product_id'],
            'goods_id' => $item['goods_id'], 
            'name' => $item['name'], 
            'img' => $item['img'], 
            'num' => $num, 
            'store_nums' => $item['store_nums'], 
            'spec' => unserialize($item['spec']), 
            'amount'=>$cash,
            "real_price" =>$real_price,
            'sell_total' => $sell_total,
            "sell_price" => $item['sell_price'], 
            'weight' => $item['weight'], 
            'point' => $item['point'], 
            'freeshipping' => $item['freeshipping'], 
            "prom_goods" => array(),
            'shop_id'=>$item['shop_id'],
            'cash'=>$cash,
            'point'=>$point
        );
        return $product;
    }
    public function calculate_fare() {
        $weight = Filter::int(Req::args('weight'));
        $product_info = Req::args('product');
        $id = Filter::int(Req::args('id'));
        if (!$id) {
            $this->code = 1000;
            return;
        }
        $fare = new Fare($weight);
        if(is_array($product_info)) {
            $product_ids = array_filter(array_keys($product_info));
        } else {
            $product_info = substr($product_info,1);
            $product_info = substr($product_info,0,-1);
            $product_info = explode(',',$product_info);
            $product_ids = array_values($product_info);
            foreach ($product_ids as $key => $value) {
                $explode = explode('=',$value);
                $product_ids[$key] = $explode[0];
            }
            // $product_ids = implode(',',$product_info);
        }
        $product = $this->model->table('products')->fields('goods_id')->where("id IN (" . implode(',', $product_ids) . ")")->findAll();
        if(!$product){
            $this->code = 1040;
            return;
        }      
        $goods = $this->model->table('goods')->fields('freeshipping')->where('id='.$product[0]['goods_id'])->find();
        if(!$goods){
            $this->code = 1040;
            return;
        }
        if($goods['freeshipping']==1){
            $fee = 0.00;
        } else {
            $fee = $fare->calculate($id,$product_info);
        }
        // $fee = $fare->calculates($id,$product_info);
        $this->code = 0;
        $this->content = array(
            'fee' => $fee
        );
    }

    /**
     * 快递
     */
    public function express() {
        $com = Filter::sql(Req::args('type'));
        $no = Filter::sql(Req::args('no'));
        $data = Common::getExpress($com, $no);
        if($data['message']=='ok'&&$data['status']==200){
           $this->code =0;
           $this->content=array(
                'expressdata' => $data['data']
          );
          return;
       }else{
          $this->code = 1112;
      }
    }

    public function offlineorder_list(){
        $type = Filter::int(Req::args('type'));
        if(!$type){
            $type = 1;
        }
        $page = Filter::int(Req::args('page'));
        if(!$page){
            $page = 1;
        }
        if($type==1){
            $list = $this->model->table('order_offline')->where('type in (1,2,3) and pay_status=1 and user_id='.$this->user['id'])->order('pay_time desc')->findPage($page,10);
        }elseif($type==2){
            $list = $this->model->table('order_offline')->where('user_id!=1 and pay_status=1 and shop_ids='.$this->user['id'])->order('pay_time desc')->findPage($page,10);
        }
        if($list){
            foreach($list['data'] as $k => $v){
                if($type==2){
                    $list['data'][$k]['order_amount'] = $v['payable_amount'];
                    // $balance_log = $this->model->table('balance_log')->where('order_no='.$v['order_no'])->find();
                    // if($balance_log){
                    //     $list['data'][$k]['order_amount'] = $balance_log['amount'];
                    // }
                }
                if($type==1){
                   $user = $this->model->table('customer as c')->join('left join user as u on c.user_id=u.id')->fields('c.real_name,u.nickname')->where('c.user_id='.$v['shop_ids'])->find(); 
                }else{
                   $user = $this->model->table('customer as c')->join('left join user as u on c.user_id=u.id')->fields('c.real_name,u.nickname')->where('c.user_id='.$v['user_id'])->find();  
                }
                if(isset($user['real_name'])){
                    $list['data'][$k]['shop_name'] = $user['real_name'];
                }else{
                    $list['data'][$k]['shop_name'] = '';
                }
                
                if($v['payment']==6 || $v['payment']==7 || $v['payment']==18){
                    $list['data'][$k]['payment_name'] = '微信支付';
                }else{
                    $list['data'][$k]['payment_name'] = '支付宝支付';
                }
            }
            unset($list['html']);
        }
        $this->code = 0;
        $this->content = $list;
    }

    public function packWeibuyProducts($item, $num = 1){
        $price_set = unserialize($item['price_set']);
        if($item['store_nums']<=0){
           $this->code = 1209;
           return;
        }
        if(is_array($price_set)){
           $real_price = $price_set[$item['product_id']]['cash'];
           $cash = $price_set[$item['product_id']]['cash']*$num;
           $point = $price_set[$item['product_id']]['point']*$num;
        }else{
           $this->code = 1210;
           return;
        }
        $sell_total = $item['sell_price'] * $num;
        $product[$item['product_id']] = array(
            'id' => $item['product_id'],
            'goods_id' => $item['goods_id'], 
            'name' => $item['name'], 
            'img' => $item['img'], 
            'num' => $num, 
            'store_nums' => $item['store_nums'], 
            'spec' => unserialize($item['spec']), 
            'amount'=>$cash,
            "real_price" =>$real_price,
            'sell_total' => $sell_total,
            "sell_price" => $item['sell_price'], 
            'weight' => $item['weight'], 
            'point' => $item['point'], 
            'freeshipping' => $item['freeshipping'], 
            "prom_goods" => array(),
            'shop_id'=>$item['shop_id'],
            'cash'=>$cash,
            'point'=>$point
        );
        return $product;
    }

    public function packPointFlashProducts($item, $num = 1) {
        $price_set = unserialize($item['price_set']);
        if($item['store_nums']<=0){
            $this->code = 1209;
            return;
        }
        if(is_array($price_set)){
           $real_price = $price_set[$item['product_id']]['cash'];
           $cash = $price_set[$item['product_id']]['cash']*$num;
           $point = $price_set[$item['product_id']]['point']*$num;
        }else{
            $this->code = 1210;
            return;
        }
        $sell_total = $item['sell_price'] * $num;
        $product[$item['product_id']] = array(
            'id' => $item['product_id'],
            'goods_id' => $item['goods_id'], 
            'name' => $item['name'], 
            'img' => $item['img'], 
            'num' => $num, 
            'store_nums' => $item['store_nums'], 
            'spec' => unserialize($item['spec']), 
            'amount'=>$cash,
            "real_price" =>$real_price,
            'sell_total' => $sell_total,
            "sell_price" => $item['sell_price'], 
            'weight' => $item['weight'], 
            'point' => $item['point'], 
            'freeshipping' => $item['freeshipping'], 
            "prom_goods" => array(),
            'shop_id'=>$item['shop_id'],
            'cash'=>$cash,
            'point'=>$point
        );
        return $product;
    }

    public function shop_order_list()
    {
        $page = Filter::int(Req::args('page'));
        if(!$page){
            $page = 1;
        }
        $shop = $this->model->table('shop')->fields('id')->where('user_id='.$this->user['id'])->find();
        if(!$shop) {
           $this->code = 0;
           $this->content = [];
           return;
        }
        $order = $this->model->table('order_goods as og')->fields('o.order_no,u.avatar,u.nickname,og.order_id,og.goods_nums,g.name,g.img,g.specs,g.sell_price,o.create_time')->join('left join order as o on o.id=og.order_id left join user as u on o.user_id=u.id left join goods as g on og.goods_id=g.id')->where('o.shop_ids='.$shop['id'].' and o.pay_status>0')->findPage($page,10);
        if($order) {
            unset($order['html']);
            foreach ($order['data'] as $key => $value) {
                $order['data'][$key]['specs'] = array_values(unserialize($value['specs']));
                if($order['data'][$key]['specs']!=null && is_array($order['data'][$key]['specs'])) {
                    foreach ($order['data'][$key]['specs'] as $k => &$v) {
                        $v['value'] = array_values($v['value']);
                    }
                }
            }
            // $order['data'] = array_values($order['data']);
        }
        $this->code = 0;
        $this->content = $order;
        return;
    }

    public function order_send()
    {
        $order_id = Filter::int(Req::args("order_id"));
        $express_no = Filter::str(Req::args("express_no"));
        $express_company_id = Filter::int(Req::args('express_company_id'));
        $province_id = Filter::int(Req::args('province_id'));
        $city_id = Filter::int(Req::args('city_id'));
        $county_id = Filter::int(Req::args('county_id'));
        $mobile = Filter::sql(Req::args('mobile'));
        $addr = Filter::text(Req::args('addr'));
        $remark = Filter::text(Req::args('remark'));
        //$delivery_status = Req::args("delivery_status");
        //同步发货信息
        $order_info = $this->model->table("order")->where("id=$order_id")->find();
        if($order_info['type']==1) {
            //判断团购订单是否满足发货要求，需在规定时间满足达到最小组团人数
            $groupbuy_log = $this->model->table('groupbuy_log')->where('id='.$order_info['join_id'])->find();
            if($groupbuy_log) {
                $groupbuy_join = $this->model->table('groupbuy_join')->where('id='.$groupbuy_log['join_id'])->find();
                if($groupbuy_join) {
                    if($groupbuy_join['need_num']>0) {
                        $this->code = 1289;
                        return;
                    }
                }
            }
        }
        if ($order_info['delivery_status'] == 3 || $order_info['delivery_status']==0) {
            $invoice = array(
                'invoice_no'         => "A".date('YmdHis') . rand(100, 999),
                'order_id'           => $order_id,
                'order_no'           => $order_info['order_no'],
                'admin'              => $this->user['nickname'],
                'accept_name'        => $order_info['accept_name'],
                'province'           => !empty($province_id)?$province_id:$order_info['province'],
                'city'               => !empty($city_id)?$city_id:$order_info['city'],
                'county'             => !empty($county_id)?$county_id:$order_info['county'],
                'addr'               => !empty($addr)?$addr:$order_info['addr'],
                'phone'              => !empty($mobile)?$mobile:$order_info['mobile'],
                'mobile'             => !empty($mobile)?$mobile:$order_info['mobile'],
                'create_time'        => date('Y-m-d H:i:s'),
                'express_no'         => $express_no,
                'express_company_id' => $express_company_id,
                'remark'             => $remark
                );
            $this->model->table('doc_invoice')->where("order_id=$order_id")->data($invoice)->insert();
        } else {
            $invoice = [];
        }
        
        if ($order_info) {
            if ($order_info['trading_info'] != '') {
                $payment_id = $order_info['payment'];
                $payment = new Payment($payment_id);
                $payment_plugin = $payment->getPaymentPlugin();
                $express_company = $this->model->table('express_company')->where('id=' . $express_company_id)->find();
                if ($express_company) {
                    $express = $express_company['name'];
                }
                else{
                    $express = $express_company_id;
                }
                //处理同步发货
                $delivery = $payment_plugin->afterAsync();
                if ($delivery != null && method_exists($delivery, "send")) {
                    $delivery->send($order_info['order_no'], $express, $express_no);
                }
            }
        }
        $this->model->table("order")->where("id=$order_id")->data(array('delivery_status' => 1, 'send_time' => date('Y-m-d H:i:s')))->update();
        //变全部物流，防止商户重复发货
        $this->model->table('order_goods')->data(array('express_no' => $express_no, 'express_company_id' => $express_company_id,'express_time'=>date("Y-m-d H:i:s")))
                ->where("order_id='{$order_info['id']}' and (express_no IS NULL or express_no ='')")
                ->update();
        Log::op($this->user['id'], "订单发货", "商家[" . $this->user['nickname'] . "]:对订单进行系统发货，订单号： " . $order_info['order_no']);
        $this->code = 0;
        $this->content = $invoice;
        return;
    }

    public function express_company_list()
    {
        $list = $this->model->table('express_company')->findAll();
        $this->code = 0;
        $this->content = $list;
        return;
    }


}


