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
        $start_date = Filter::str(Req::args('start_date'));
        $end_date = Filter::str(Req::args('end_date'));
        $page = Filter::int(Req::args('p'));
        if($start_date && !$end_date) {
            $end_date = date('Y-m-d');
        }
        if(!$page) {
            $page = 1;
        }
        $user = $this->getAllChildUserIds($user_id,$start_date,$end_date);
        $total_user = $this->getAllChildUserIds($user_id);
        if($total_user['user_ids']) {
            $ids = $total_user['user_ids'];
            $where1 = "user_id in ($ids) and pay_status=1 and status=4";
            if($start_date || $end_date) {
                $where1.=" and pay_time between '{$start_date}' and '{$end_date}'";
            }
            $order_num = $this->model->table('order')->where($where1)->count();
            $order_total = $this->model->table('order')->fields('sum(order_amount) as sum')->where($where1)->query();
            $order_sum = $order_total[0]['sum']!=null?$order_total[0]['sum']:0.00;

            $where2 = "shop_ids = ".$user_id." and pay_status=1";
            if($start_date || $end_date) {
                $where2.=" and pay_time between '{$start_date}' and '{$end_date}'";
            }
            $offline_order_num = $this->model->table('order_offline')->where($where2)->count();
            $offline_order_total = $this->model->table('order_offline')->fields('sum(order_amount) as sum')->where($where2)->query();
            $offline_order_sum = $offline_order_total[0]['sum']!=null?$offline_order_total[0]['sum']:0.00;
            $where3 = "user_id in ($ids) and type=21";
            if($start_date || $end_date) {
                $where3 .=" and time between '{$start_date}' and '{$end_date}'"; 
            }
            $benefit_total = $this->model->table('balance_log')->fields('sum(amount) as sum')->where($where3)->query();
            $benefit_sum = $benefit_total[0]['sum']!=null?$benefit_total[0]['sum']:0.00;
            $where4 = "user_id in ($ids) and type=8";
            if($start_date || $end_date) {
                $where4 .=" and time between '{$start_date}' and '{$end_date}'"; 
            }
            $crossover_total = $this->model->table('balance_log')->fields('sum(amount) as sum')->where($where4)->query();
            $crossover_sum = $crossover_total[0]['sum']!=null?$crossover_total[0]['sum']:0.00;
            $where5 = "owner_id in ($ids)";
            if($start_date || $end_date) {
                $where5 .=" and create_time between '{$start_date}' and '{$end_date}'"; 
            }
            $where6 = "user_id in ($ids)";
            if($start_date || $end_date) {
                $where6 .=" and create_time between '{$start_date}' and '{$end_date}'"; 
            }
            $shop_num = $this->model->table('district_shop')->where($where5)->count();
            $promoter_num = $this->model->table('district_promoter')->where($where6)->count();
            $where7 = "user_id in ($ids) and type=5";
            if($start_date || $end_date) {
                $where7 .=" and time between '{$start_date}' and '{$end_date}'"; 
            }
            $order_benefit_total = $this->model->table('balance_log')->fields('sum(amount) as sum')->where($where7)->query();
            $order_benefit = $order_benefit_total[0]['sum']!=null?$order_benefit_total[0]['sum']:0.00;
        } else {
            $order_num = 0;
            $order_sum = 0.00;
            $offline_order_num = 0;
            $offline_order_sum = 0.00;
            $benefit_sum = 0.00;
            $crossover_sum = 0.00;
            $shop_num = 0;
            $promoter_num = 0;
            $order_benefit = 0.00;
        }
        $idstr = $user['user_ids'];
        if($idstr!='') {
            $where8 = "c.user_id in ($idstr) and c.status=1";
            if($start_date || $end_date) {
                $where8 .= " and c.reg_time between '{$start_date}' and '{$end_date}'";
            }
            $list = $this->model->table('district_promoter as dp')->join('left join customer as c on dp.user_id=c.user_id left join user as u on c.user_id= u.id')->fields('c.real_name,c.realname,c.mobile,u.id,u.nickname,u.avatar')->where($where8)->findPage($page,10);
            if($list['data']){
                unset($list['html']);
                $total = count($list['data']);
                foreach($list['data'] as $k=>$v){
                    if($v['id']==null){
                        unset($list['data'][$k]);
                        $total = $total-1;
                    }else{
                        $shop = $this->model->table('district_shop')->where('owner_id='.$v['id'])->find();
                        if($shop){
                            $list['data'][$k]['role_type'] = 2; //经销商     
                        }else{
                            $list['data'][$k]['role_type'] = 1; //商家     
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
        $result['order_sum'] = $order_sum; //线上总订单数
        $result['offline_order_num'] = $offline_order_num; //扫码总订单数
        $result['shop_num'] = $shop_num; //经销商数量
        $result['promoter_num'] = $promoter_num; //商家数量
        $result['user_num'] = $user['num']; //会员数量
        $result['order_num'] = $order_num; //线上订单总金额     
        $result['offline_order_sum'] = $offline_order_sum; //扫码订单总金额
        $result['order_benefit'] = $order_benefit; //线上订单跨界收益
        $result['crossover_sum'] = $crossover_sum; //扫码订单跨界收益
        $result['benefit_sum'] = $benefit_sum; // 优惠购收益
        $result['user_list'] = $list; //用户列表
        var_dump($result);die;
        $this->assign('result',$result);
        $this->redirect();
    }

    public function getAllChildUserIds($user_id,$start_date='',$end_date='')
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
            $where.=" and c.reg_time between '{$start_date}' and '{$end_date}'";
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