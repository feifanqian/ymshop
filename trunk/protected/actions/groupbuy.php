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
        $join_id = Filter::int(Req::args('join_id'));
        $type = Filter::int(Req::args('type')); // 1开团 2参团
        $groupbuy = $this->model->table('groupbuy')->where('id='.$groupbuy_id)->find();
        if(!$groupbuy_id) {
            $this->code = 1275;
            return;
        }
        $remain_time = strtotime($groupbuy['end_time'])-time();
        // $now_num = $this->model->table('groupbuy_join')->where('groupbuy_id='.$groupbuy_id)->count();
        $exist = $this->model->table('groupbuy_join')->where('groupbuy_id='.$groupbuy_id.' and user_id='.$this->user['id'].' and status in (0,1)')->find();
        if($exist) {
            $this->code = 1276;
            return;
        }
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
            'need_num'    => $groupbuy['min_num']-1,
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
                $data = array(
                    'user_id'  => $groupbuy_join['user_id'].','.$this->user['id'],
                    'need_num' => $groupbuy_join['need_num']-1
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
        $this->model->table('groupbuy_log')->data($log)->insert();
        
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
        $info['join_num'] = $this->model->table('groupbuy_log')->where('groupbuy_id='.$groupbuy_id)->count();
        $info['groupbuy_join_list'] = $this->model->table('groupbuy_join')->fields('id as join_id,user_id,need_num,end_time')->where('groupbuy_id='.$groupbuy_id.' and status in (0,1)')->findAll();
        if($info['groupbuy_join_list']) {
            foreach ($info['groupbuy_join_list'] as $k => $v) {
                // $user_id = explode(',',$v['user_id']);
                $info['groupbuy_join_list'][$k]['users'] = $this->model->table('user')->fields('nickname,avatar')->where("id in (".$v['user_id'].")")->findAll();
                $info['groupbuy_join_list'][$k]['remain_time'] = $this->timediff(time(),strtotime($v['end_time']));
                unset($info['groupbuy_join_list'][$k]['end_time']);
            }
        }
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
        $join_id = Filter::int(Req::args('join_id'));
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
        $info['had_join_num'] = $this->model->table('groupbuy_log')->where('groupbuy_id='.$groupbuy_id.' and join_id='.$join_id)->count();
        $info['start_time'] = $first['join_time'];
        $info['need_num'] = $info['min_num'] - $info['had_join_num'];
        $info['end_time'] = $first['end_time'];
        $info['groupbuy_join_list'] = $this->model->table('groupbuy_join')->fields('id as join_id,user_id,need_num,end_time')->where('groupbuy_id='.$groupbuy_id.' and id='.$join_id.' and status in (0,1)')->find();
        if($info['groupbuy_join_list']) {
            // foreach ($info['groupbuy_join_list'] as $k => $v) {
            //     $info['groupbuy_join_list'][$k]['users'] = $this->model->table('user')->fields('nickname,avatar')->where("id in (".$v['user_id'].")")->findAll();
            //     $info['groupbuy_join_list'][$k]['remain_time'] = $this->timediff(time(),strtotime($v['end_time']));
            //     unset($info['groupbuy_join_list'][$k]['end_time']);
            // }

            $info['groupbuy_join_list']['users'] = $this->model->table('user')->fields('nickname,avatar')->where("id in (".$info['groupbuy_join_list']['user_id'].")")->findAll();
            $info['groupbuy_join_list']['remain_time'] = $this->timediff(time(),strtotime($info['groupbuy_join_list']['end_time']));
            unset($info['groupbuy_join_list']['end_time']);
            
        }
        $joined = $this->model->table('groupbuy_log')->where('groupbuy_id='.$groupbuy_id.' and join_id='.$join_id.' and user_id='.$this->user['id'])->find();
        if($joined && $info['had_join_num']>=$info['min_num']) {
            $info['status'] = '拼团成功';
        } elseif ($joined && $info['had_join_num']<$info['min_num'] && time()>=strtotime($info['end_time'])) {
            $info['status'] = '拼团失败';
        } elseif ($joined && $info['had_join_num']<$info['min_num'] && time()<strtotime($info['end_time'])) {
            $info['status'] = '邀请好友';
        } elseif ($joined==null && $info['had_join_num']>=$info['min_num'] && time()>=strtotime($info['end_time'])) {
            $info['status'] = '活动已结束';
        } elseif ($joined==null && $info['had_join_num']>=$info['min_num'] && time()<strtotime($info['end_time'])) {
            $info['status'] = '拼团人数已满';
        } elseif ($joined==null && $info['had_join_num']<$info['min_num'] && time()<strtotime($info['end_time'])) {
            $info['status'] = '我要参团';
        } else {
            $info['status'] = '拼团中';
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
        $log = $this->model->table('groupbuy_log')->fields('join_id,groupbuy_id')->where('user_id='.$this->user['id'])->findAll();
        $ids = array();
        $idss = array();
        if($log) {
            foreach($log as $k =>$v) {
               $ids[] = $v['groupbuy_id'];
               $idss[] = $v['join_id'];
            }
        }
        $groupbuy_ids = $ids!=null?implode(',', $ids):'';
        $join_ids = $idss!=null?implode(',', $idss):'';
        var_dump($groupbuy_ids);die;
        if($groupbuy_ids) {
            $list = $this->model->table('groupbuy as g')->fields('g.id,gj.id as join_id,go.name,go.img,g.min_num,g.price,g.end_time,gj.status')->join('left join goods as go on g.goods_id=go.id')->join('left join groupbuy_join as gj on g.id=gj.groupbuy_id')->where('g.id in ('.$groupbuy_ids.')')->findPage($page,10);
            if($list) {
                if($list['data']) {
                    foreach ($list['data'] as $k => $v) {
                        switch ($v['status']) {
                            case -1:
                                $list['data'][$k]['join_status'] = '拼团失败';
                                break;
                            case 0:
                                $list['data'][$k]['join_status'] = '拼团中';
                                break;
                            case 1:
                                $list['data'][$k]['join_status'] = '拼团成功';
                                break;
                            case 2:
                                $list['data'][$k]['join_status'] = '拼团失败';
                                break;    
                            default:
                                $list['data'][$k]['join_status'] = '拼团中';
                                break;
                        }
                        
                    }
                    unset($list['html']);
                }
            }
        } else {
            $list = [];
        }
        
        $this->code = 0;
        $this->content = $list;
    }
}       