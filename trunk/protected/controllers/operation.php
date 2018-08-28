<?php
class OperationController extends Controller
{
    public $layout = 'operation';
    public $safebox = null;
    private $user;
    private $model = null;
    private $cookie_time = 31622400;
    private $cart = array();
    private $selectcart = array();

    public function init() {
        header("Content-type: text/html; charset=" . $this->encoding);
        $this->model = new Model();
        $this->safebox = Safebox::getInstance();
        $this->user = $this->safebox->get('user');
        // if ($this->user == null) {
        //     $this->user = Common::autoLoginUserInfo();
        //     $this->safebox->set('user', $this->user);
        // }
        $config = Config::getInstance();
        $site_config = $config->get("globals");
        $this->assign('seo_title', $site_config['site_name']);
        $this->assign('site_title', $site_config['site_name']);
    }

    public function operation_center()
    {
        $user_id = Filter::int(Req::args('user_id'));
        $date = Filter::str(Req::args('date'));
        $page = Filter::int(Req::args('page'));
        if(!$page) {
            $page = 1;
        }
        $user = $this->getAllChildUserIds($user_id,$date);
        $total_user = $this->getAllChildUserIds($user_id);
        if($total_user['user_ids']) {
            $ids = $total_user['user_ids'];
            $where1 = "user_id in ($ids) and pay_status=1 and status=4";
            if($date) {
                $where1.=" and pay_time >= '{$date}'";
            }
            $order_num = $this->model->table('order')->where($where1)->count();
            $order_total = $this->model->table('order')->fields('sum(order_amount) as sum')->where($where1)->query();
            $order_sum = $order_total[0]['sum']!=null?$order_total[0]['sum']:0.00;

            $offline_order_num = $this->model->table('order_offline')->where($where1)->count();
            $offline_order_total = $this->model->table('order_offline')->fields('sum(order_amount) as sum')->where($where1)->query();
            $offline_order_sum = $offline_order_total[0]['sum']!=null?$offline_order_total[0]['sum']:0.00;
            $where2 = "user_id in ($ids) and type=21";
            if($date) {
                $where2 .=" and time>= '{$date}'"; 
            }
            $benefit_total = $this->model->table('balance_log')->fields('sum(amount) as sum')->where($where2)->query();
            $benefit_sum = $benefit_total[0]['sum']!=null?$benefit_total[0]['sum']:0.00;
        } else {
            $order_num = 0;
            $order_sum = 0.00;
            $offline_order_num = 0;
            $offline_order_sum = 0.00;
            $benefit_sum = 0.00;
        }
        $idstr = $user['user_ids'];
        if($idstr!='') {
            $where3 = "c.user_id in ($idstr) and c.status=1";
            if($date) {
                $where3 .= " and c.reg_time>='{$date}'";
            }
            $list = $this->model->table('customer as c')->join('left join user as u on c.user_id= u.id')->fields('c.real_name,c.mobile,u.id,u.nickname,u.avatar')->where($where3)->findPage($page,10);
            if($list['data']){
                foreach($list['data'] as $k=>$v){
                    if($v['id']==null){
                        unset($list['data'][$k]);
                        $total = $total-1;
                    }else{
                        $shop = $this->model->table('district_shop')->where('owner_id='.$v['id'])->find();
                        $promoter = $this->model->table('district_promoter')->where('user_id='.$v['id'])->find();
                        if($shop && $promoter){
                            $list['data'][$k]['role_type'] = 2;      
                        }elseif(!$shop && $promoter){
                            $list['data'][$k]['role_type'] = 1;  
                        }else{
                            $list['data'][$k]['role_type'] = 0;      
                        }
                    }
                }
                $list['data'] = array_values($list['data']); 
            } else {
                $list['data'] = [];
            }
        } else {
            $list['data'] = [];
        }
        
        $result = array();
        $result['user_num'] = $user['num'];
        $result['order_num'] = $order_num;
        $result['order_sum'] = $order_sum;
        $result['offline_order_num'] = $offline_order_num;
        $result['offline_order_sum'] = $offline_order_sum;
        $result['benefit_sum'] = $benefit_sum;
        $result['user_list'] = $list;
        var_dump($result);die;
        $this->assign('result',$result);
        $this->redirect();
    }

    public function getAllChildUserIds($user_id,$date='')
    {
       // if(!$date) {
       //  $date = date('Y-m-d');
       // } 
       $model = new Model();
       $is_break = false;
       $num = 0;
       $now_user_id = $user_id;
       $idstr = '';
       $ids = array();
       while(!$is_break) {
          $where = "i.user_id=".$now_user_id;
          if($date) {
            $where.=" and c.reg_time>='{$date}'";
          }
          $inviter_info = $model->table("invite as i")->join('left join customer as c on i.invite_user_id=c.user_id')->fields('i.invite_user_id')->where($where)->findAll();
          if($inviter_info) {
            foreach($inviter_info as $k =>$v) {
               $ids[] = $v['invite_user_id'];
               $num = $num+1;
               $now_user_id = $v['invite_user_id'];
            }
          } else {
            $is_break = true;
          }
          $idstr = $ids!=null?implode(',', $ids):'';
       }
       $result['user_ids'] = $idstr;
       $result['num'] = $num;
       return $result;
    }
}    