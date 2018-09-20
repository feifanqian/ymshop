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
        $list = $this->model->table('groupbuy as g')->fields('g.id,g.goods_id,o.name,o.img,o.sell_price,g.price,g.min_num,o.store_nums,g.end_time')->join('left join goods as o on g.goods_id = o.id')->where('g.is_end = 0')->order('g.id desc')->findPage($page,10);
        if($list) {
            if($list['data']) {
                foreach ($list['data'] as $key => $value) {
                    if ($value['store_nums'] <= 0 || time() >= strtotime($value['end_time'])) {
                        $this->model->table('groupbuy')->data(array('is_end' => 1))->where("id=".$value['id'])->update();
                        unset($list['data'][$key]);
                    }
                }
            }
            unset($list['html']);
        }
        $this->code = 0;
        $this->content = $list;
    }

    //开团
    public function groupbuy_join()
    {
        $groupbuy_id = Filter::int(Req::args('groupbuy_id'));
        $join_id = Filter::int(Req::args('join_id'));
        $type = Filter::int(Req::args('type')); // 1开团 2参团
        $groupbuy = $this->model->table('groupbuy')->where('id='.$groupbuy_id)->find();
        if(!$groupbuy_id) {
            $this->code = 1275;
            return;
        }
        if(time()>strtotime($groupbuy['end_time'])) {
            $this->code = 1284;
            return;
        }
        if(time()<strtotime($groupbuy['start_time'])) {
            $this->code = 1286;
            return;
        }
        $remain_time = strtotime($groupbuy['end_time'])-time();
        // $now_num = $this->model->table('groupbuy_join')->where('groupbuy_id='.$groupbuy_id)->count();
        // $exist = $this->model->table('groupbuy_join')->where('groupbuy_id='.$groupbuy_id.' and user_id='.$this->user['id'].' and status in (0,1)')->find();
        // if($exist) {
        //     $this->code = 1276;
        //     return;
        // }
        // if($now_num>=$groupbuy['min_num']) {
        //     $this->code = 1275;
        //     return;
        // }
        // $join = $this->model->table('groupbuy_join')->where('groupbuy_id='.$groupbuy_id.' and status in (0,1)')->find();
        
        if($type==1) { //第一个开启拼团
             $data = array(
            'groupbuy_id' => $groupbuy_id,
            'user_id'     => $this->user['id'],
            'goods_id'    => $groupbuy['goods_id'],
            'join_time'   => date('Y-m-d H:i:s'),
            'end_time'    => date('Y-m-d H:i:s',strtotime('+1 day')),
            'need_num'    => $groupbuy['min_num'],
            'remain_time' => $remain_time,
            'status'      => 0
            );
        
           $last_id = $this->model->table('groupbuy_join')->data($data)->insert();
           $log = array(
            'join_id'     => $last_id,
            'groupbuy_id' => $groupbuy_id,
            'user_id'     => $this->user['id'],
            'join_time'   => date('Y-m-d H:i:s')
            );
        } else {  //拼单
            if(!$join_id) {
                $this->code = 1280;
                return;
            } else {
                $groupbuy_join = $this->model->table('groupbuy_join')->where('id='.$join_id)->find();
                if($groupbuy_join['need_num'] == 0) {
                    $this->code = 1293; //人数已凑满
                    return;
                }
                $joined = $this->model->table('groupbuy_log')->where('join_id='.$join_id.' and user_id='.$this->user['id'].' and pay_status=1')->find();
                if($joined) {
                    $this->code = 1282;
                    return;
                }
                if(time()>strtotime($groupbuy_join['end_time'])) {
                    $this->code = 1281;
                    return;
                }
                $data = array(
                    'user_id'  => $groupbuy_join['user_id'].','.$this->user['id'],
                    // 'need_num' => $groupbuy_join['need_num']
                    );
                $this->model->table('groupbuy_join')->data($data)->where('id='.$join_id)->update();
                $log = array(
                    'join_id'     => $join_id,
                    'groupbuy_id' => $groupbuy_id,
                    'user_id'     => $this->user['id'],
                    'join_time'   => date('Y-m-d H:i:s')
                    );
            }
        }
        $log_id = $this->model->table('groupbuy_log')->data($log)->insert();
        
        $this->code = 0;
        $this->content['log_id'] = $log_id;
        $this->content['join_id'] = !empty($join_id)?$join_id:$last_id;
        $this->content['groupbuy_id'] = $groupbuy_id;
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
        $goods = $this->model->table('goods as g')->fields('g.id,g.name,g.imgs,g.sell_price,g.content,g.specs,p.id as product_id,g.store_nums')->join('left join products as p on g.id = p.goods_id')->where('g.id='.$groupbuy['goods_id'])->find();
        if(!$goods) {
            $this->code = 1040;
            return;
        }
        $info['groupbuy_id'] = $groupbuy_id;
        $info['goods_id'] = $groupbuy['goods_id'];
        $info['product_id'] = $goods['product_id'];
        $info['name'] = $goods['name'];
        $info['imgs'] = unserialize($goods['imgs']);
        $info['sell_price'] = $goods['sell_price'];
        $info['price'] = $groupbuy['price'];
        $info['store_nums'] = $goods['store_nums'];
        $info['start_time'] = $groupbuy['start_time'];
        $info['end_time'] = $groupbuy['end_time'];
        $info['current_time'] = date('Y-m-d H:i:s');
        $info['join_num'] = $this->model->table('groupbuy_log')->where('groupbuy_id='.$groupbuy_id.' and pay_status=1')->count();
        
        $now = time();
        
        $groupbuy_join_list = $this->model->table('groupbuy_log as gl')->fields('gl.join_id,gj.user_id,gj.end_time')->join('left join groupbuy_join as gj on gl.join_id=gj.id left join groupbuy as g on gj.groupbuy_id=g.id left join order as o on o.join_id=gl.id')->where('gl.groupbuy_id='.$groupbuy_id.' and gl.pay_status=1 and gj.need_num>0 and o.pay_status=1 and UNIX_TIMESTAMP(g.start_time)<='.$now.' and UNIX_TIMESTAMP(gj.end_time)>'.$now)->findAll();
        
        if($groupbuy_join_list) {
            $info['groupbuy_join_list'] = $this->super_unique($groupbuy_join_list);
            foreach ($info['groupbuy_join_list'] as $k => $v) {
                $user_ids = explode(',',$v['user_id']);
                $user_id = $user_ids[0];
                $info['groupbuy_join_list'][$k]['users'] = $users = $this->model->table('user')->fields('nickname,avatar')->where('id='.$user_id)->find();
                $info['groupbuy_join_list'][$k]['remain_time'] = $this->timediff(time(),strtotime($v['end_time']));
                $info['groupbuy_join_list'][$k]['remain_seconds'] = strtotime($v['end_time'])-time();
                $had_join_num = $this->model->table('groupbuy_log')->where('groupbuy_id='.$groupbuy_id.' and join_id='.$v['join_id'].' and pay_status=1')->count();
                $info['groupbuy_join_list'][$k]['need_num'] = $groupbuy['min_num']-$had_join_num;
                if($info['groupbuy_join_list'][$k]['need_num']<=0) {
                    unset($info['groupbuy_join_list'][$k]);
                }
                unset($info['groupbuy_join_list'][$k]['end_time']);
            }
            $info['groupbuy_join_list'] = array_values($info['groupbuy_join_list']);
        } else {
            $info['groupbuy_join_list'] = [];
        }
        $info['specs'] = array_values(unserialize($goods['specs']));
        if($info['specs']!=null && is_array($info['specs'])) {
            foreach ($info['specs'] as $k => &$v) {
                $v['value'] = array_values($v['value']);
            }
        }
        $skumap = array();
        $products = $this->model->table("products")->fields("sell_price,market_price,store_nums,specs_key,pro_no,id")->where("goods_id = ".$groupbuy['goods_id'])->findAll();
        if ($products) {
            foreach ($products as $product) {
                $skumap[$product['specs_key']] = $product;
            }
        }
        $comment = array();
        $review = array('1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0);
        $rows = $this->model->table("review")->fields("count(id) as num,point")->where("status=1 and goods_id = ".$groupbuy['goods_id'])->group("point")->findAll();
        foreach ($rows as $row) {
            $review[$row['point']] = intval($row['num']);
        }
        $a = ($review[4] + $review[5]);
        $b = ($review[3]);
        $c = ($review[1] + $review[2]);
        $total = $a + $b + $c;
        if ($total == 0)
            $total = 1;
        $comment['a'] = array('num' => $a, 'percent' => round((100 * $a / $total)));
        $comment['b'] = array('num' => $b, 'percent' => round((100 * $b / $total)));
        $comment['c'] = array('num' => $c, 'percent' => round((100 * $c / $total)));
        $info['skumap'] = array_values($skumap);
        $info['comment_list'] = $this->model->table("review as re")
                        ->join("left join user as us on re.user_id = us.id")
                        ->fields("re.id,us.nickname,us.avatar,re.content,re.comment_time")
                        ->where('re.status=1 and re.goods_id = '.$groupbuy['goods_id'])->order("re.id desc")->findAll();
        $info['comment_num'] = count($info['comment_list']);
        $info['comment'] = $comment;
        $info['content'] = $goods['content'];                
        $info['share_url'] = 'http://www.ymlypt.com/index/groupbuy/id/'.$groupbuy_id;
        $this->code = 0;
        $this->content = $info;
        return; 
    }

    //拼团详情
    public function  groupbuy_join_detail()
    {
        $groupbuy_id = Filter::int(Req::args('groupbuy_id'));
        $join_id = Filter::int(Req::args('join_id'));
        $groupbuy = $this->model->table('groupbuy')->where('id='.$groupbuy_id)->find();
        if(!$groupbuy_id) {
            $this->code = 1275;
            return;
        }
        $goods = $this->model->table('goods as g')->fields('g.id,g.name,g.img,g.imgs,g.sell_price,g.content,g.specs,p.id as product_id,g.store_nums')->join('left join products as p on g.id = p.goods_id')->where('g.id='.$groupbuy['goods_id'])->find();
        $first = $this->model->table('groupbuy_log')->fields('join_time')->where('groupbuy_id='.$groupbuy_id.' and join_id='.$join_id.' and pay_status in (1,3)')->order('id asc')->find();
        $info['groupbuy_id'] = $groupbuy_id;
        $info['goods_id'] = $groupbuy['goods_id'];
        $info['product_id'] = $goods['product_id'];
        $info['name'] = $goods['name'];
        $info['img'] = $goods['img'];
        $info['price'] = $groupbuy['price'];
        $info['store_nums'] = $goods['store_nums'];
        $info['specs'] = array_values(unserialize($goods['specs']));
        if($info['specs']!=null && is_array($info['specs'])) {
            foreach ($info['specs'] as $k => &$v) {
                $v['value'] = array_values($v['value']);
            }
        }
        $skumap = array();
        $products = $this->model->table("products")->fields("sell_price,market_price,store_nums,specs_key,pro_no,id")->where("goods_id = ".$groupbuy['goods_id'])->findAll();
        if ($products) {
            foreach ($products as $product) {
                $skumap[$product['specs_key']] = $product;
            }
        }
        $info['skumap'] = array_values($skumap);
        $info['min_num'] = $groupbuy['min_num'];
        $info['had_join_num'] = $this->model->table('groupbuy_log')->where('groupbuy_id='.$groupbuy_id.' and join_id='.$join_id.' and pay_status=1')->count();
        $info['start_time'] = $first['join_time'];
        $info['need_num'] = $info['min_num'] - $info['had_join_num'];
        $info['end_time'] = date("Y-m-d H:i:s",strtotime('+1 day',strtotime($first['join_time'])));
        $info['current_time'] = date('Y-m-d H:i:s');
        // $groupbuy_join_list = $this->model->table('groupbuy_log')->fields('user_id')->where('groupbuy_id='.$groupbuy_id.' and join_id='.$join_id.' and pay_status=1')->findAll();
        // $ids = array();
        // if($groupbuy_join_list) {
        //     foreach($groupbuy_join_list as $k =>$v) {
        //        $ids[] = $v['user_id'];
        //     }
        // }
        // $user_ids = $ids!=null?implode(',', $ids):'';
        $info['groupbuy_join_list']['join_id'] = $join_id;
        $info['groupbuy_join_list']['need_num'] = $info['min_num'] - $info['had_join_num'];
        // if($user_ids!='') {
        //     $users = $this->model->table('groupbuy_log as gl')->join('left join user as u on gl.user_id=u.id')->fields('u.nickname,u.avatar')->where('gl.groupbuy_id='.$groupbuy_id.' and gl.join_id='.$join_id.' and gl.pay_status=1')->order('gl.join_time asc')->findAll();
        // } else {
        //     $users = [];
        // }
        $users = $this->model->table('groupbuy_log as gl')->join('left join user as u on gl.user_id=u.id')->fields('u.nickname,u.avatar')->where('gl.groupbuy_id='.$groupbuy_id.' and gl.join_id='.$join_id.' and gl.pay_status=1')->order('gl.join_time asc')->findAll();
        $info['groupbuy_join_list']['users'] = $users;
        $info['groupbuy_join_list']['remain_time'] = $this->timediff(time(),strtotime($info['end_time']));
        $info['groupbuy_join_list']['remain_seconds'] = strtotime($info['end_time'])-time();
        
        $joined = $this->model->table('groupbuy_log')->where('groupbuy_id='.$groupbuy_id.' and join_id='.$join_id.' and user_id='.$this->user['id'].' and pay_status in (1,3)')->find();
        if($joined && $info['had_join_num']>=$info['min_num']) {
            $info['status'] = '拼团成功';
        } elseif ($joined && $info['had_join_num']<$info['min_num'] && time()>=strtotime($info['end_time'])) {
            $info['status'] = '拼团失败';
        } elseif ($joined && $info['had_join_num']<$info['min_num'] && time()<strtotime($info['end_time'])) {
            $info['status'] = '邀请好友';
        } elseif ($joined && $joined['pay_status']==3) {
            $info['status'] = '已退款';
        } elseif ($joined==null && $info['had_join_num']>=$info['min_num'] && time()>=strtotime($info['end_time'])) {
            $info['status'] = '活动已结束';
        } elseif ($joined==null && $info['had_join_num']>=$info['min_num'] && time()<strtotime($info['end_time'])) {
            $info['status'] = '拼团人数已满';
        } elseif ($joined==null && $info['had_join_num']<$info['min_num'] && time()<strtotime($info['end_time'])) {
            $info['status'] = '我要参团';
        } else {
            $info['status'] = '拼团中';
        }
        $info['share_url'] = 'http://www.ymlypt.com/index/groupbuy_join_detail/groupbuy_id/'.$groupbuy_id.'/join_id/'.$join_id.'/inviter_id/'.$this->user['id'];
        if($joined) {
            $order = $this->model->table('order')->fields('id')->where('join_id='.$joined['id'])->find();
            $info['order_id'] = $order['id'];
        }
        
        $this->code = 0;
        $this->content = $info;
    }

    public function timediff($begin_time,$end_time)
    {
        if($begin_time < $end_time){
        $starttime = $begin_time;
        $endtime = $end_time;
        }else{
        $starttime = $end_time;
        $endtime = $begin_time;
        }

        //计算天数
        $timediff = $endtime-$starttime;
        $days = intval($timediff/86400);
        //计算小时数
        $remain = $timediff%86400;
        $hours = intval($remain/3600);
        //计算分钟数
        $remain = $remain%3600;
        $mins = intval($remain/60);
        //计算秒数
        $secs = $remain%60;
        // $res = array("day" => $days,"hour" => $hours,"min" => $mins,"sec" => $secs);
        $res = $hours.':'.$mins.':'.$secs;
        return $res;
    }

    public function myGroupbuyActive()
    {
        $page = Filter::int(Req::args('page'));
        if(!$page) {
            $page = 1;
        }
        // $log = $this->model->table('groupbuy_log')->where('user_id='.$this->user['id'].' and pay_status in (1,3)')->findPage($page,10);
        // if($log) {
        //     if($log['data']) {
        //         foreach ($log['data'] as $k => $v) {
                    
        //         }
        //     }
        // }

        $list = $this->model->table('order as o')->fields('gl.id as log_id,gl.join_id,gl.groupbuy_id as id,go.name,go.img,g.min_num,g.price,gj.end_time,gj.status,o.id as order_id,og.product_id,gl.join_time,gl.pay_status')->join('left join groupbuy_log as gl on o.join_id=gl.id left join order_goods as og on o.id=og.order_id left join groupbuy as g on gl.groupbuy_id=g.id left join goods as go on g.goods_id=go.id left join groupbuy_join as gj on gl.join_id=gj.id')->where('gl.user_id='.$this->user['id'].' and gl.pay_status in (1,3) and o.pay_status in (1,3)')->order('id desc')->findPage($page,10);
        if($list) {
            if($list['data']!=null) {
                foreach ($list['data'] as $k => $v) {
                    $had_join_num = $this->model->table('groupbuy_log')->where('join_id='.$v['join_id'].' and pay_status=1')->count();
                        if($had_join_num>=$v['min_num']) {
                            $list['data'][$k]['join_status'] = '拼团成功';
                        } elseif ($had_join_num<$v['min_num'] && time()>=strtotime($v['end_time'])) {
                            $list['data'][$k]['join_status'] = '拼团失败';
                        } elseif ($had_join_num<$v['min_num'] && time()<strtotime($v['end_time'])) {
                            $list['data'][$k]['join_status'] = '拼团中';
                        } elseif($v['pay_status']==3){
                            $list['data'][$k]['join_status'] = '已退款';  
                        } else {
                            $list['data'][$k]['join_status'] = '拼团中';
                        }
                        $list['data'][$k]['current_time'] = date('Y-m-d H:i:s');
                        $list['data'][$k]['share_url'] = 'http://www.ymlypt.com/index/groupbuy_join_detail/groupbuy_id/'.$v['id'].'/join_id/'.$v['join_id'];
                }
            }
            unset($list['html']);
        }
        
        $this->code = 0;
        $this->content = $list;
    }

    public function super_unique($array, $recursion = false){
        // 序列化数组元素,去除重复
        $result = array_map('unserialize', array_unique(array_map('serialize', $array)));
        // 递归调用
        if ($recursion) {
            foreach ($result as $key => $value) {
                if (is_array($value)) {
                    $result[ $key ] = super_unique($value);
                }
            }
        }
        return $result;
    }
}       