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

    public function addItem($id, $num = 1,$uid = 0) {
        if ($this->hasItem($id)) {
            $this->incNum($id, $num);
            return;
        }
        $this->items[$id] = $num;
        $this->uid = $uid;
        $model = new Model();
        $model->table('cart')->data(array('user_id'=>$uid,'goods_id'=>$id,'num'=>$num))->insert();
    }

    public function hasItem($id) {
        return isset($this->items[$id]);
    }

    public function delItem($id) {
        unset($this->items[$id]);
    }

    public function modNum($id, $num = 1) {
        if (!$this->hasItem($id)) {
            return false;
        }
        $this->items[$id] = $num;
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

    public function all() {
        $products = array();
        if ($this->getCnt() > 0) {
            $model = new Model("products as pr");
            $ids = array_keys($this->items);
            $ids = trim(implode(",", $ids), ',');
            $uid = $this->uid;
            if ($ids != '') {
                $prom = new Prom();
              //   if($uid==42608){
              //     $items = $model->fields("pr.*,go.img,go.name,go.prom_id,go.point,go.freeshipping,go.shop_id")->join("left join goods as go on pr.goods_id = go.id left join cart as c on pr.goods_id=c.goods_id")->where("pr.id in($ids) and c.user_id = $uid")->findAll();  
              // }else{
                $items = $model->fields("pr.*,go.img,go.name,go.prom_id,go.point,go.freeshipping,go.shop_id")->join("left join goods as go on pr.goods_id = go.id ")->where("pr.id in($ids)")->findAll();
              // }
                
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

    public function clear() {
        $this->items = array();
    }

}
