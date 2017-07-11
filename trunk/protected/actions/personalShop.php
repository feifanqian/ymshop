<?php

class PersonalShopAction extends Controller {

    public $model = null;
    public $user = null;
    public $code = 1000;
    public $content = NULL;

    public function __construct() {
        $this->model = new Model();
    }
    
    public function shopList(){
        $p = Filter::int(Req::args('p'));
        $shop_list = $this->model->table("personal_shop as ps")
                ->join("left join user as u on ps.user_id = u.id left join customer as c on ps.user_id = c.user_id")
                ->fields("ps.*,u.nickname,u.avatar,c.real_name")
                ->order("ps.listorder asc")
                ->findPage($p,10);
        if(isset($shop_list['html'])){
            unset($shop_list['html']);
        }
        if(!empty($shop_list['data'])){
            foreach ($shop_list['data'] as $k=>$v){
                $shop_data = Common::getPersonalShopData($v['id']);
                $shop_list['data'][$k]['all_sell_num']=$shop_data['all_sell_num'];
                $shop_list['data'][$k]['all_goods_num']=$shop_data['all_goods_num'];
            }
        }
        $this->code = 0;;
        $this->content = $shop_list;
    }
    
    private function isPersonalShop($id){
        $isset = $this->model->table("personal_shop")->where("id=$id")->find();
        if($isset){
            return $isset;
        }else{
            return false;
        }
    }
    
    public function shopIndexGoods(){
        $id = Filter::int(Req::args('id'));
        if($this->isPersonalShop($id)){
            $goods = $this->model->table("goods as g")->join("left join order_goods as og on g.id = og.goods_id left join order as o on og.order_id = o.id ")
                    ->fields("g.name,g.img,g.market_price,g.sell_price,SUM(IF( o.status =4 || o.status =3, og.goods_nums, 0 )) as sell_num")
                    ->where("g.personal_shop_id = $id and g.is_online =0")
                    ->group("g.id")
                    ->limit(10)
                    ->findAll();
            $this->code = 0;
            $this->content = $goods;
        }else{
            $this->code = 1000;
            return;
        }
    }
    
    public function shopGoodsListByTime(){
        $id = Filter::int(Req::args('id'));
        $p  = Filter::int(Req::args('p'));
        if($this->isPersonalShop($id)){
            $goods = $this->model->table("goods as g")->join("left join order_goods as og on g.id = og.goods_id left join order as o on og.order_id = o.id ")
                    ->fields("g.name,g.img,g.market_price,g.sell_price,SUM(IF( o.status =4 || o.status =3, og.goods_nums, 0 )) as sell_num")
                    ->where("g.personal_shop_id = $id and g.is_online =0")
                    ->group("g.id")
                    ->findPage($p,10);
            unset($goods['html']);
            $this->code = 0;
            $this->content = $goods;
        }else{
            $this->code = 1000;
            return;
        }
    }
}