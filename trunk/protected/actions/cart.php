<?php

class CartAction extends Controller {

    public $model = null;
    public $user = null;
    public $code = 1000;
    public $content = NULL;

    public function __construct() {
        $this->model = new Model();
    }

    private function getCart() {
        $type = Req::args('cart_type');
        if ($type == 'goods') {
            return Cart::getCart('goods');
        } else {
            return Cart::getCart();
        }
    }

    public function add() {
        $id = Filter::int(Req::args("id"));
        $num = intval(Req::args("num"));
        $uid = Filter::int(Req::args("user_id"));
        $num = $num > 0 ? $num : 1;
        $cart = $this->getCart();
        if($uid){
           $cart->addItem($id, $num,$uid); 
       }else{
           $cart->addItem($id, $num);
       }
        $products = $cart->all();
        $this->code = 0;
        $this->content = array(
            'productlist' => array_values($products)
        );
    }

    public function del() {
        $id = Filter::int(Req::args("id"));
        $cart = $this->getCart();
        $cart->delItem($id);
        if (!$cart->hasItem($id)) {
            $this->code = 0;
        } else {
            $this->code = 1005;
        }
    }

    public function num() {
        $id = Filter::int(Req::args("id"));
        $num = intval(Req::args("num"));
        $session_id = Req::args("session_id");
        $uid = Filter::int(Req::args("user_id"));
        // var_dump($uid);die;
        // var_dump($session_id);die;
        $num = $num > 0 ? $num : 1;
        // $cart = $this->getCart();
        $cart = Cart::getCart();
       //  if($uid){
       //     $cart->modNum($id, $num,$uid); 
       // }else{
       //  $cart->modNum($id, $num);
       // }
       if($uid) {    
         $products = $cart->all($uid);
       }else {
         $products = $cart->all();
       }
        
        if($products){
             foreach ($products as $k =>$v){
            $products[$k]['spec'] =  array_values($products[$k]['spec']);
         }
        }
        $this->code = 0;
        $this->content = array(
            'productlist' => array_values($products)
        );
        
    }

}
