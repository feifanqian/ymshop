<?php
class GroupbuyAction extends Controller
{
    public $model = null;
    public $user = null;
    public $code = 1000;
    public $content = NULL;

    public function __construct()
    {
        $this->model = new Model();
    }
    
    //邀请收银员
    public function groupbuy_list()
    {
        $page = Filter::int(Req::args('page'));
        if(!$page) {
            $page = 1;
        }
        $list = $this->model->table('groupbuy as g')->fields('g.id,g.goods_id,o.name,o.img,o.sell_price,g.price,g.min_num')->join('left join goods as o on g.goods_id = o.id')->where('g.is_end = 0')->findPage($page,10);
        if($list) {
            unset($list['html']);
        }
        $this->code = 0;
        $this->content = $list;
    }
}       