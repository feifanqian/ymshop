<?php

class Cart {

    private static $ins = null;
    private $items = array();
    private $uid = null;
    final protected function __construct() {
        
    }

    final protected function __clone() {
        
    }

    protected static function getIns() {
        if (!(self::$ins instanceof self)) {
            self::$ins = new self();
        }
        return self::$ins;
    }

    public static function getCart($type = 'cart') {
        if ($type == 'cart') {
            if (Session::get("tiny_cart") == null || !(Session::get("tiny_cart") instanceof self)) {
                Session::set("tiny_cart", self::getIns());
            }
            return Session::get("tiny_cart");
        } else {
            if (Session::get("tiny_goods") == null || !(Session::get("tiny_goods") instanceof self)) {
                Session::set("tiny_goods", self::getIns());
            }
            return Session::get("tiny_goods");
        }
    }

    public function addItem($id, $num = 1,$uid = 0,$session_id='') {
        // if ($this->hasItem($id)) {
        //     $this->incNum($id, $num);
        //     return;
        // }
        // $this->items[$id] = $num;
        // $this->uid = $uid;
        $model = new Model();
        if($uid) {
           $exist = $model->table('cart')->where('goods_id='.$id.' and user_id='.$uid)->find();
            if($exist){
                $model->table('cart')->data(array('num'=>"`num`+({$num})"))->where('goods_id='.$id.' and user_id='.$uid)->update();
            }else{
                $model->table('cart')->data(array('user_id'=>$uid,'goods_id'=>$id,'num'=>$num))->insert();
            } 
        }
        if($session_id) {
           $exist = $model->table('cart')->where("goods_id=".$id." and session_id='".$session_id."'")->find();
            if($exist){
                $model->table('cart')->data(array('num'=>"`num`+({$num})"))->where("goods_id=".$id." and session_id='".$session_id."'")->update();
            }else{
                $model->table('cart')->data(array('session_id'=>$session_id,'goods_id'=>$id,'num'=>$num))->insert();
            } 
        } 
    }

    public function hasItem($id) {
        return isset($this->items[$id]);
    }

    public function delItem($id,$uid = 0,$session_id='') {
        $model = new Model();
        if($uid) {
            $model->table('cart')->where('goods_id='.$id.' and user_id='.$uid)->delete();
        }
        if($session_id) {
            $model->table('cart')->where("goods_id=".$id." and session_id='{$session_id}'")->delete();
        }
        unset($this->items[$id]);
    }

    public function modNum($id, $num = 1,$uid = 0,$session_id='') {
        if (!$this->hasItem($id)) {
            return false;
        }
        if($uid){
            $this->uid = $uid;
        }
        $this->items[$id] = $num;
        $model = new Model();
        if($uid) {
            $model->table('cart')->data(array('num'=>$num))->where('goods_id='.$id.' and user_id='.$uid)->update();
        }
        if($session_id) {
            $model->table('cart')->data(array('num'=>$num))->where("goods_id=".$id." and session_id='{$session_id}'")->update();
        }
    }

    public function incNum($id, $num = 1) {
        if ($this->hasItem($id)) {
            $this->items[$id] += $num;
        }
    }

    public function decNum($id, $num = 1) {
        if ($this->hasItem($id)) {
            $this->items[$id] -= $num;
        }
        if ($this->items[$id] < 1) {
            $this->delItem($id);
        }
    }

    public function getCnt() {
        return count($this->items);
    }

    public function getNum() {
        if ($this->getCnt() == 0) {
            return 0;
        }

        $sum = 0;
        foreach ($this->items as $item) {
            $sum += $item;
        }
        return $sum;
    }

    public function all($uid=0,$session_id='') {
        $products = array();
        if ($this->getCnt() > 0) {
            $model = new Model("products as pr");
            $ids = array_keys($this->items);
            $ids = trim(implode(",", $ids), ',');
            if ($ids != '') {
                $prom = new Prom();
                $items = $model->fields("pr.*,go.img,go.name,go.prom_id,go.point,go.freeshipping,go.shop_id")->join("left join goods as go on pr.goods_id = go.id ")->where("pr.id in($ids)")->findAll();
                foreach ($items as $item) {
                    $num = $this->items[$item['id']];
                    if ($num > $item['store_nums']) {
                        $num = $item['store_nums'];
                        $this->modNum($item['id'], $num);
                    }

                    if ($num <= 0) {
                        $this->delItem($item['id']);
                    } else {
                        $item['goods_nums'] = $num;
                        $prom_goods = $prom->prom_goods($item);
                        $amount = sprintf("%01.2f", $prom_goods['real_price'] * $num);
                        $sell_total = $item['sell_price'] * $num;
                        $products[$item['id']] = array('id' => $item['id'], 'goods_id' => $item['goods_id'], 'shop_id' => $item['shop_id'], 'name' => $item['name'], 'img' => $item['img'], 'num' => $num, 'store_nums' => $item['store_nums'], 'price' => $item['sell_price'], 'freeshipping'=>$item['freeshipping'], 'prom_id' => $item['prom_id'], 'real_price' => $prom_goods['real_price'], 'sell_price' => $item['sell_price'], 'spec' => unserialize($item['spec']), 'amount' => $amount, 'prom' => $prom_goods['note'], 'weight' => $item['weight'], 'point' => $item['point'], 'sell_total' => $sell_total, "prom_goods" => $prom_goods);
                    }
                }
            }
        }
        return $products;
    }

    public function alls($uid=0,$session_id='') {
        $products = array();
        if($uid || $session_id) {
            $model = new Model();
            $ids = array_keys($this->items);
            $ids = trim(implode(",", $ids), ',');
            // $uid = $this->uid;
            $cart_model = new Model('cart');
            if($uid) {
                $idarr = $cart_model->fields('goods_id')->where('user_id='.$uid)->findAll();
                $where = "c.user_id=".$uid;
            }
            if($session_id) {
                $idarr = $cart_model->fields('goods_id')->where("session_id='{$session_id}'")->findAll();
                $where = "c.session_id='{$session_id}'";
            }
            
            $idstr = '';
            if($idarr){
                foreach ($idarr as $key => $v) {
                    $areaid[$key] = $v['goods_id'];
                }
                $idstr = implode(',', $areaid);
            }
            if ($idstr != '') {
                $prom = new Prom();
                $items = $model->table('cart as c')->fields("pr.id,pr.goods_id,pr.store_nums,pr.spec,go.weight,go.point,go.sell_price,go.img,go.name,go.prom_id,go.point,go.freeshipping,go.shop_id,c.num as cart_num")->join("left join products as pr on c.goods_id=pr.id left join goods as go on pr.goods_id = go.id")->where($where)->order('c.id desc')->findAll();
                foreach ($items as $k => $item) { 
                    $item['goods_nums'] = $item['cart_num'];
                    $prom_goods = $prom->prom_goods($item);
                    $amount = sprintf("%01.2f", $prom_goods['real_price'] * $item['cart_num']);
                    $sell_total = $item['sell_price'] * $item['cart_num'];
                    $products[$k] = array(
                        'id' => $item['id'], 
                        'goods_id' => $item['goods_id'], 
                        'shop_id' => $item['shop_id'], 
                        'name' => $item['name'], 
                        'img' => $item['img'], 
                        'num' => $item['cart_num'], 
                        'store_nums' => $item['store_nums'], 
                        'price' => $item['sell_price'], 
                        'freeshipping'=>$item['freeshipping'],
                        'prom_id' => $item['prom_id'], 
                        'real_price' => $prom_goods['real_price'], 
                        'sell_price' => $item['sell_price'], 
                        'spec' => is_array($item['spec'])?array_values(unserialize($item['spec'])):[], 
                        'amount' => $amount, 
                        'prom' => $prom_goods['note'], 
                        'weight' => $item['weight'], 
                        'point' => $item['point'],
                        'sell_total' => $sell_total,
                        "prom_goods" => $prom_goods
                          );
                }  
            } else {
                $products = [];
            } 
        }
        return $products;
    }

    public function clear() {
        $this->items = array();
    }

}
