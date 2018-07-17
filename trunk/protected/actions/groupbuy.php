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
    
    //团购专区
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

    //开团
    public function groupbuy_join()
    {
        $groupbuy_id = Filter::int(Req::args('groupbuy_id'));
        $groupbuy = $this->model->table('groupbuy')->where('id='.$groupbuy_id)->find();
        if(!$groupbuy_id) {
            $this->code = 1275;
            return;
        }
        $remain_time = strtotime($groupbuy['end_time'])-time();
        $now_num = $this->model->table('groupbuy_join')->where('groupbuy_id='.$groupbuy_id.' and status in (0,1)')->count();
        // if($now_num>=$groupbuy['min_num']) {
        //     $this->code = 1275;
        //     return;
        // }
        $join = $this->model->table('groupbuy_join')->where('groupbuy_id='.$groupbuy_id.' and status in (0,1)')->find();
        $data = array(
            'groupbuy_id' => $groupbuy_id,
            'user_id'     => $this->user['id'],
            'goods_id'    => $groupbuy['goods_id'],
            'join_time'   => date('Y-m-d H:i:s'),
            'need_num'    => $groupbuy-$now_num,
            'remain_time' => $remain_time,
            'status'      => 0
            );
        $exist = $this->model->table('groupbuy_join')->where('groupbuy_id='.$groupbuy_id.' and user_id='.$this->user['id'].' and status in (0,1)')->find();
        if($exist) {
            $this->code = 1276;
            return;
        }
        $this->model->table('groupbuy_join')->data($data)->insert();
        $this->code = 0;
        $this->content = null;
        return;
    }

    //拼团详细页面
    public function groupbuy_detail()
    {
        $groupbuy_id = Filter::int(Req::args('groupbuy_id'));
        $groupbuy = $this->model->table('groupbuy')->where('id='.$groupbuy_id)->find();
        if(!$groupbuy_id) {
            $this->code = 1275;
            return;
        }
        $goods = $this->model->table('goods')->fields('id,name,imgs,sell_price,content')->where('id='.$groupbuy['goods_id'])->find();
        if(!$goods) {
            $this->code = 1040;
            return;
        }
        $info['groupbuy_id'] = $groupbuy_id;
        $info['goods_id'] = $groupbuy['goods_id'];
        $info['name'] = $goods['name'];
        $info['imgs'] = unserialize($goods['imgs']);
        $info['sell_price'] = $goods['sell_price'];
        $info['price'] = $groupbuy['price'];
        $info['end_time'] = $groupbuy['end_time'];
        $info['join_num'] = $this->model->table('groupbuy_join')->where('groupbuy_id='.$groupbuy_id.' and status in (0,1)')->count();
        $info['groupbuy_join_list'] = $this->model->table('groupbuy_join as gj')->fields('gj.id as join_id,gj.groupbuy_id,gj.remain_time,gj.need_num,u.nickname,u.avatar')->where('groupbuy_id='.$groupbuy_id.' and status in (0,1)')->findAll();
        $info['comment_list'] = $this->model->table("review as re")
                        ->join("left join user as us on re.user_id = us.id")
                        ->fields("re.id,us.nickname,us.avatar,re.content,re.comment_time")
                        ->where('re.status=1 and re.goods_id = '.$groupbuy['goods_id'])->order("re.id desc")->findAll();
        $info['comment_num'] = count($info['comment_list']);
        $info['content'] = $goods['content'];                

        $this->code = 0;
        $this->content = $info;
        return; 
    }

    //拼团详情
    public function  groupbuy_join_detail()
    {
        $groupbuy_id = Filter::int(Req::args('groupbuy_id'));
        $groupbuy = $this->model->table('groupbuy')->where('id='.$groupbuy_id)->find();
        if(!$groupbuy_id) {
            $this->code = 1275;
            return;
        }
        $goods = $this->model->table('goods')->fields('id,name,img,sell_price,content')->where('id='.$groupbuy['goods_id'])->find();
        $first = $this->model->table('groupbuy_join')->where('groupbuy_id='.$groupbuy_id.' and status in (0,1)')->order('id asc')->find();
        $info['groupbuy_id'] = $groupbuy_id;
        $info['name'] = $goods['name'];
        $info['img'] = $goods['img'];
        $info['price'] = $groupbuy['price'];
        $info['min_num'] = $groupbuy['min_num'];
        $info['had_join_num'] = $this->model->table('groupbuy_join')->where('groupbuy_id='.$groupbuy_id.' and status in (0,1)')->count();
        $info['start_time'] = $first['join_time'];
        $info['need_num'] = $info['min_num'] - $info['had_join_num'];
        $info['end_time'] = $groupbuy['end_time'];
        $info['groupbuy_join_list'] = $this->model->table('groupbuy_join as gj')->fields('gj.groupbuy_id,u.nickname,u.avatar')->where('groupbuy_id='.$groupbuy_id.' and status in (0,1)')->findAll();

        $this->code = 0;
        $this->content = $info;
    }
}       