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
        if($start_date=='请选择日期') {
            $start_date = '';
        }
        if($end_date=='请选择日期') {
            $end_date = '';
        }
        if($start_date) {
            $start_date .= " 00:00:01";
        }
        if($end_date) {
            $end_date .= " 23:59:59";
        }
        $page = Filter::int(Req::args('p'));
        // if(!$start_date) {
        //     $start_date = date('Y-m-d', strtotime('-30 days'));
        // }
        // if(!$end_date) {
        //     $end_date = date('Y-m-d');
        // }
        if(!$page) {
            $page = 1;
        }
        $user = $this->getAllChildUserIds($user_id,$start_date,$end_date);

        $shopids = $user['shopids'];
        $promoter_id_arr = array();
        $promoter_ids = '';//商家和经销商id
        $pure_promoter_ids = '';//商家id
        $shop_num = 0;
        $promoter_num = 0;
        if($shopids!='') {
            $where8 = "dp.hirer_id in ($shopids) and dp.user_id!=".$user_id;
            if($start_date && $end_date) {
                $where8 .= " and dp.create_time between '{$start_date}' and '{$end_date}'";
            }
            $nums = $this->model->table('district_promoter as dp')->join('left join user as u on dp.user_id= u.id')->fields('u.id')->where($where8)->findAll();
            if($nums) {
                foreach($nums as $k=>$v){
                    if($v['id']==null){
                        unset($nums[$k]);
                    }else{
                        $promoter_id_arr[] = $v['id'];
                    }
                }
            }
        }else{
            $promoter_id_arr[] = $user_id;
        }
        
        $user_ids_arr = $user['user_ids_arr'];
        $pure_promoter_ids = $promoter_id_arr!=null?implode(',', $promoter_id_arr):''; 
        $promoter_id_arr1 = array();
        if($user_ids_arr!=null) {
            $promoter_id_arr1 = array_merge($promoter_id_arr, $user_ids_arr);
        }
        $promoter_ids = $promoter_id_arr1!=null?implode(',', $promoter_id_arr1):''; //商家和经销商id
        
        if($promoter_ids!='') {
            $where9 = "dp.user_id in ($promoter_ids) and dp.user_id!=".$user_id;
            if($start_date && $end_date) {
                $where9 .= " and dp.create_time between '{$start_date}' and '{$end_date}'";
            }
            $list = $this->model->table('district_promoter as dp')->join('left join customer as c on dp.user_id=c.user_id left join user as u on c.user_id= u.id')->fields('c.real_name,c.realname,c.mobile,u.id,u.nickname,u.avatar,dp.create_time')->where($where9)->order('dp.id desc')->findPage($page,10);
            if($list['data']){
                unset($list['html']);
                $total = count($list['data']);
                foreach($list['data'] as $k=>$v){
                    if($v['id']==null){
                        unset($list['data'][$k]);
                        $total = $total-1;
                    }else{
                        $shop = $this->model->table('district_shop')->where('owner_id='.$v['id'])->find();
                        if(in_array($v['id'],$user_ids_arr) && $shop) {
                            $list['data'][$k]['role_type'] = 2; //经销商   
                        }else{
                            $list['data'][$k]['role_type'] = 1; //商家
                        }
                    }
                    if($v['avatar']=='/0.png') {
                         $list['data'][$k]['avatar'] = '0.png';
                    }
                }
                $list['data'] = array_values($list['data']); 
            } else {
                $list['data'] = [];
            }
            $num = $this->model->table('district_promoter as dp')->join('left join user as u on dp.user_id= u.id')->fields('u.id')->where($where9)->findAll();
            if($num) {
                foreach($num as $k=>$v){
                    if($v['id']==null){
                        unset($num[$k]);
                    }else{
                        $shop = $this->model->table('district_shop')->where('owner_id='.$v['id'])->find();
                        if(in_array($v['id'],$user_ids_arr) && $shop) {
                            $shop_num = $shop_num+1;  
                        }else{
                            $promoter_num = $promoter_num+1;
                        }
                    }
                }
            }
        } else {
            $list['data'] = [];
        }
        
        
        if($user['user_ids']) {
            // $ids = $user['user_ids'];
            $ids_arr = $user['ids'];
            if($promoter_id_arr) {
                $ids_arr = array_merge($ids_arr,$promoter_id_arr);
            }
            $ids = $ids_arr!=null?implode(',', $ids_arr):''; 
            $where1 = "user_id in ($ids) and pay_status=1 and status=4";
            if($start_date && $end_date) {
                $where1.=" and pay_time between '{$start_date}' and '{$end_date}'";
            }
            $order_num = $this->model->table('order')->where($where1)->count();
            $order_total = $this->model->table('order')->fields('sum(order_amount) as sum')->where($where1)->query();
            $order_sum = $order_total[0]['sum']!=null?$order_total[0]['sum']:0.00;        
            
            $where3 = "user_id in ($ids) and type=21";
            if($start_date && $end_date) {
                $where3 .=" and time between '{$start_date}' and '{$end_date}'"; 
            }
            $benefit_total = $this->model->table('balance_log')->fields('sum(amount) as sum')->where($where3)->query();
            $benefit_sum = $benefit_total[0]['sum']!=null?$benefit_total[0]['sum']:0.00;
            $where4 = "user_id in ($ids) and type=8";
            if($start_date && $end_date) {
                $where4 .=" and time between '{$start_date}' and '{$end_date}'"; 
            }
            $crossover_total = $this->model->table('balance_log')->fields('sum(amount) as sum')->where($where4)->query();
            $crossover_sum = $crossover_total[0]['sum']!=null?$crossover_total[0]['sum']:0.00;
            $where5 = "user_id in ($ids) and type in (1,2)";
            if($start_date && $end_date) {
                $where5 .=" and order_time between '{$start_date}' and '{$end_date}'"; 
            }
            $taoke_num = $this->model->table('benefit_log')->where($where5)->count(); 
            
            $where7 = "user_id in ($ids) and type=5";
            if($start_date && $end_date) {
                $where7 .=" and time between '{$start_date}' and '{$end_date}'"; 
            }
            $order_benefit_total = $this->model->table('balance_log')->fields('sum(amount) as sum')->where($where7)->query();
            $order_benefit = $order_benefit_total[0]['sum']!=null?$order_benefit_total[0]['sum']:0.00;
        } else {
            $order_num = 0;
            $order_sum = 0.00;
            $benefit_sum = 0.00;
            $crossover_sum = 0.00;
            $shop_num = 0;
            $promoter_num = 0;
            $order_benefit = 0.00;
            $taoke_num = 0;
        }

        $user_ids = $user['user_ids'];
        if($pure_promoter_ids!='') {
            $where2 = "shop_ids in ($pure_promoter_ids) and pay_status = 1";
            if($start_date && $end_date) {
                $where2.=" and pay_time between '{$start_date}' and '{$end_date}'";
            }
            $offline_order_num = $this->model->table('order_offline')->where($where2)->count();

            $offline_order_total = $this->model->table('order_offline')->fields('sum(order_amount) as sum')->where($where2)->query();
            $offline_order_sum = $offline_order_total[0]['sum']!=null?$offline_order_total[0]['sum']:0.00;
        } else {
            $offline_order_num = 0;
            $offline_order_sum = 0.00;
        }
        
        
        $result = array();
        $result['order_num'] = $order_num; //线上总订单数
        $result['offline_order_num'] = $offline_order_num; //扫码总订单数
        $result['shop_num'] = $shop_num; //经销商数量
        $result['promoter_num'] = $promoter_num; //商家数量
        $result['user_num'] = $user['num']; //会员数量
        $result['order_sum'] = $order_sum; //线上订单总金额     
        $result['offline_order_sum'] = $offline_order_sum; //扫码订单总金额
        $result['order_benefit'] = $order_benefit; //线上订单跨界收益
        $result['crossover_sum'] = $crossover_sum; //扫码订单跨界收益
        $result['benefit_sum'] = $benefit_sum; // 优惠购收益
        $result['taoke_num'] = $taoke_num; // 优惠购订单数
        
        $this->assign('result',$result);
        $this->assign('list',$list);
        $this->assign('user_id',$user_id);
        $this->assign('page',$page);
        $this->redirect();
    }

    public function ajax_operation_center()
    {
        $user_id = Filter::int(Req::args('user_id'));
        $start_date = Filter::str(Req::args('start_date'));
        $end_date = Filter::str(Req::args('end_date'));
        if($start_date=='请选择日期') {
            $start_date = '';
        }
        if($end_date=='请选择日期') {
            $end_date = '';
        }
        if($start_date) {
            $start_date .= " 00:00:01";
        }
        if($end_date) {
            $end_date .= " 23:59:59";
        }
        $page = Filter::int(Req::args('page'));
        $user = $this->getAllChildUserIds($user_id,$start_date,$end_date);
        $idstr = $user['user_ids'];
        $shopids = $user['shopids'];
        $promoter_id_arr = array();
        if($shopids!='') {
            $where8 = "dp.hirer_id in ($shopids) and dp.user_id!=".$user_id;
            if($start_date && $end_date) {
                $where8 .= " and dp.create_time between '{$start_date}' and '{$end_date}'";
            }
            $nums = $this->model->table('district_promoter as dp')->join('left join user as u on dp.user_id= u.id')->fields('u.id')->where($where8)->findAll();
            if($nums) {
                foreach($nums as $k=>$v){
                    if($v['id']==null){
                        unset($nums[$k]);
                    }else{
                        $promoter_id_arr[] = $v['id'];
                    }
                }
            }
        }
        $user_ids_arr = $user['user_ids_arr'];
        if($user_ids_arr!=null) {
            $promoter_id_arr = array_merge($promoter_id_arr,$user_ids_arr);
        }
        $promoter_ids = $promoter_id_arr!=null?implode(',', $promoter_id_arr):''; //商家id
        if($promoter_ids!='') {
            $where9 = "dp.user_id in ($promoter_ids) and c.status=1 and dp.user_id!=".$user_id;
            if($start_date && $end_date) {
                $where9 .= " and dp.create_time between '{$start_date}' and '{$end_date}'";
            }
            $list = $this->model->table('district_promoter as dp')->join('left join customer as c on dp.user_id=c.user_id left join user as u on c.user_id= u.id')->fields('c.real_name,c.realname,c.mobile,u.id,u.nickname,u.avatar,dp.create_time')->where($where9)->order('dp.id desc')->findPage($page,10);
            if($list['data']){
                unset($list['html']);
                $total = count($list['data']);
                foreach($list['data'] as $k=>$v){
                    if($v['id']==null){
                        unset($list['data'][$k]);
                        $total = $total-1;
                    }else{
                        $shop = $this->model->table('district_shop')->where('owner_id='.$v['id'])->find();
                        if(in_array($v['id'],$user_ids_arr) && $shop) {
                            $list['data'][$k]['role_type'] = 2; //经销商     
                        }else{
                            $list['data'][$k]['role_type'] = 1; //商家     
                        }
                        if(strpos($v['avatar'],'https') !== false || strpos($v['avatar'],'http') !== false){
                            $list['data'][$k]['avatar'] = $v['avatar'];
                        } else {
                            $list['data'][$k]['avatar'] = 'https://ymlypt.b0.upaiyun.com'.$v['avatar'];
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
        echo JSON::encode($list['data']);
    }

    public function ajax_operation_order()
    {
        $user_id = Filter::int(Req::args('user_id'));
        $start_date = Filter::str(Req::args('start_date'));
        $end_date = Filter::str(Req::args('end_date'));
        if($start_date=='请选择日期') {
            $start_date = '';
        }
        if($end_date=='请选择日期') {
            $end_date = '';
        }
        if($start_date) {
            $start_date .= " 00:00:01";
        }
        if($end_date) {
            $end_date .= " 23:59:59";
        }
        $page = Filter::int(Req::args('p'));
        // if(!$start_date) {
        //     $start_date = date('Y-m-d', strtotime('-30 days'));
        // }
        // if(!$end_date) {
        //     $end_date = date('Y-m-d');
        // }
        if(!$page) {
            $page = 1;
        }
        $user = $this->getAllChildUserIds($user_id,$start_date,$end_date);

        $shopids = $user['shopids'];
        $promoter_id_arr = array();
        $promoter_ids = '';//商家和经销商id
        $pure_promoter_ids = '';//商家id
        $shop_num = 0;
        $promoter_num = 0;
        if($shopids!='') {
            $where8 = "dp.hirer_id in ($shopids) and dp.user_id!=".$user_id;
            if($start_date && $end_date) {
                $where8 .= " and dp.create_time between '{$start_date}' and '{$end_date}'";
            }
            $nums = $this->model->table('district_promoter as dp')->join('left join user as u on dp.user_id= u.id')->fields('u.id')->where($where8)->findAll();
            if($nums) {
                foreach($nums as $k=>$v){
                    if($v['id']==null){
                        unset($nums[$k]);
                    }else{
                        $promoter_id_arr[] = $v['id'];
                    }
                }
            }
        }else{
            $promoter_id_arr[] = $user_id;
        }
        
        $user_ids_arr = $user['user_ids_arr'];
        $pure_promoter_ids = $promoter_id_arr!=null?implode(',', $promoter_id_arr):''; 
        $promoter_id_arr1 = array();
        if($user_ids_arr!=null) {
            $promoter_id_arr1 = array_merge($promoter_id_arr, $user_ids_arr);
        }
        $promoter_ids = $promoter_id_arr1!=null?implode(',', $promoter_id_arr1):''; //商家id
        
        if($promoter_ids!='') {
            $where9 = "dp.user_id in ($promoter_ids) and dp.user_id!=".$user_id;
            if($start_date && $end_date) {
                $where9 .= " and dp.create_time between '{$start_date}' and '{$end_date}'";
            }
            $list = $this->model->table('district_promoter as dp')->join('left join customer as c on dp.user_id=c.user_id left join user as u on c.user_id= u.id')->fields('c.real_name,c.realname,c.mobile,u.id,u.nickname,u.avatar,dp.create_time')->where($where9)->order('dp.id desc')->findPage($page,10);
            if($list['data']){
                unset($list['html']);
                $total = count($list['data']);
                foreach($list['data'] as $k=>$v){
                    if($v['id']==null){
                        unset($list['data'][$k]);
                        $total = $total-1;
                    }else{
                        $shop = $this->model->table('district_shop')->where('owner_id='.$v['id'])->find();
                        if(in_array($v['id'],$user_ids_arr) && $shop) {
                            $list['data'][$k]['role_type'] = 2; //经销商   
                        }else{
                            $list['data'][$k]['role_type'] = 1; //商家
                        }
                    }
                    if($v['avatar']=='/0.png') {
                         $list['data'][$k]['avatar'] = '0.png';
                    }
                }
                $list['data'] = array_values($list['data']); 
            } else {
                $list['data'] = [];
            }
            $num = $this->model->table('district_promoter as dp')->join('left join user as u on dp.user_id= u.id')->fields('u.id')->where($where9)->findAll();
            if($num) {
                foreach($num as $k=>$v){
                    if($v['id']==null){
                        unset($num[$k]);
                    }else{
                        $shop = $this->model->table('district_shop')->where('owner_id='.$v['id'])->find();
                        if(in_array($v['id'],$user_ids_arr) && $shop) {
                            $shop_num = $shop_num+1;  
                        }else{
                            $promoter_num = $promoter_num+1;
                        }
                    }
                }
            }
        } else {
            $list['data'] = [];
        }
        
        
        if($user['user_ids']) {
            // $ids = $user['user_ids'];
            $ids_arr = $user['ids'];
            if($promoter_id_arr) {
                $ids_arr = array_merge($ids_arr,$promoter_id_arr);
            }
            $ids = $ids_arr!=null?implode(',', $ids_arr):'';
            $where1 = "user_id in ($ids) and pay_status=1 and status=4";
            if($start_date && $end_date) {
                $where1.=" and pay_time between '{$start_date}' and '{$end_date}'";
            }
            $order_num = $this->model->table('order')->where($where1)->count();
            $order_total = $this->model->table('order')->fields('sum(order_amount) as sum')->where($where1)->query();
            $order_sum = $order_total[0]['sum']!=null?$order_total[0]['sum']:0.00;        
            
            $where3 = "user_id in ($ids) and type=21";
            if($start_date && $end_date) {
                $where3 .=" and time between '{$start_date}' and '{$end_date}'"; 
            }
            $benefit_total = $this->model->table('balance_log')->fields('sum(amount) as sum')->where($where3)->query();
            $benefit_sum = $benefit_total[0]['sum']!=null?$benefit_total[0]['sum']:0.00;
            $where4 = "user_id in ($ids) and type=8";
            if($start_date && $end_date) {
                $where4 .=" and time between '{$start_date}' and '{$end_date}'"; 
            }
            $crossover_total = $this->model->table('balance_log')->fields('sum(amount) as sum')->where($where4)->query();
            $crossover_sum = $crossover_total[0]['sum']!=null?$crossover_total[0]['sum']:0.00;
            $where5 = "user_id in ($ids) and type in (1,2)";
            if($start_date && $end_date) {
                $where5 .=" and order_time between '{$start_date}' and '{$end_date}'"; 
            }
            $taoke_num = $this->model->table('benefit_log')->where($where5)->count(); 
            
            $where7 = "user_id in ($ids) and type=5";
            if($start_date && $end_date) {
                $where7 .=" and time between '{$start_date}' and '{$end_date}'"; 
            }
            $order_benefit_total = $this->model->table('balance_log')->fields('sum(amount) as sum')->where($where7)->query();
            $order_benefit = $order_benefit_total[0]['sum']!=null?$order_benefit_total[0]['sum']:0.00;
        } else {
            $order_num = 0;
            $order_sum = 0.00;
            $benefit_sum = 0.00;
            $crossover_sum = 0.00;
            $shop_num = 0;
            $promoter_num = 0;
            $order_benefit = 0.00;
            $taoke_num = 0;
        }

        $user_ids = $user['user_ids'];
        if($pure_promoter_ids!='') {
            $where2 = "shop_ids in ($pure_promoter_ids) and pay_status = 1";
            if($start_date && $end_date) {
                $where2 .= " AND (pay_time BETWEEN  '{$start_date}' AND  '{$end_date}' )";
            }
            
            $offline_order_num = $this->model->table('order_offline')->where($where2)->count();
      
            $offline_order_total = $this->model->table('order_offline')->fields('sum(order_amount) as sum')->where($where2)->query();
            $offline_order_sum = $offline_order_total[0]['sum']!=null?$offline_order_total[0]['sum']:0.00;
        } else {
            $offline_order_num = 0;
            $offline_order_sum = 0.00;
        }
        
        $result = array();
        $result['order_num'] = $order_num; //线上总订单数
        $result['offline_order_num'] = $offline_order_num; //扫码总订单数
        $result['shop_num'] = $shop_num; //经销商数量
        $result['promoter_num'] = $promoter_num; //商家数量
        $result['user_num'] = $user['num']; //会员数量
        $result['order_sum'] = $order_sum; //线上订单总金额     
        $result['offline_order_sum'] = $offline_order_sum; //扫码订单总金额
        $result['order_benefit'] = $order_benefit; //线上订单跨界收益
        $result['crossover_sum'] = $crossover_sum; //扫码订单跨界收益
        $result['benefit_sum'] = $benefit_sum; // 优惠购收益
        $result['taoke_num'] = $taoke_num; // 优惠购订单数

        echo JSON::encode($result);
    }

    public function shop_info()
    {
        $user_id = Filter::int(Req::args('user_id'));
        $start_date = Filter::str(Req::args('start_date'));
        $end_date = Filter::str(Req::args('end_date'));
        if($start_date=='请选择日期') {
            $start_date = '';
        }
        if($end_date=='请选择日期') {
            $end_date = '';
        }
        if($start_date) {
            $start_date .= " 00:00:01";
        }
        if($end_date) {
            $end_date .= " 23:59:59";
        }
        $page = Filter::int(Req::args('p'));
        // if(!$start_date) {
        //     $start_date = date('Y-m-d', strtotime('-30 days'));
        // }
        // if(!$end_date) {
        //     $end_date = date('Y-m-d');
        // }
        if(!$page) {
            $page = 1;
        }
        $user = $this->getAllChildUserIds($user_id,$start_date,$end_date);

        $shopids = $user['shopids'];
        $promoter_id_arr = array();
        $promoter_ids = '';//商家和经销商id
        $pure_promoter_ids = '';//商家id
        $shop_num = 0;
        $promoter_num = 0;
        if($shopids!='') {
            $where8 = "dp.hirer_id in ($shopids) and dp.user_id!=".$user_id;
            if($start_date && $end_date) {
                $where8 .= " and dp.create_time between '{$start_date}' and '{$end_date}'";
            }
            $nums = $this->model->table('district_promoter as dp')->join('left join user as u on dp.user_id= u.id')->fields('u.id')->where($where8)->findAll();
            if($nums) {
                foreach($nums as $k=>$v){
                    if($v['id']==null){
                        unset($nums[$k]);
                    }else{
                        $promoter_id_arr[] = $v['id'];
                    }
                }
            }
        }else{
            $promoter_id_arr[] = $user_id;
        }
        
        $user_ids_arr = $user['user_ids_arr'];
        $pure_promoter_ids = $promoter_id_arr!=null?implode(',', $promoter_id_arr):''; 
        $promoter_id_arr1 = array();
        if($user_ids_arr!=null) {
            $promoter_id_arr1 = array_merge($promoter_id_arr,$user_ids_arr);
        }
        $promoter_ids = $promoter_id_arr1!=null?implode(',', $promoter_id_arr1):''; //商家id
        
        if($promoter_ids!='') {
            $where9 = "dp.user_id in ($promoter_ids) and dp.user_id!=".$user_id;
            if($start_date && $end_date) {
                $where9 .= " and dp.create_time between '{$start_date}' and '{$end_date}'";
            }
            $list = $this->model->table('district_promoter as dp')->join('left join customer as c on dp.user_id=c.user_id left join user as u on c.user_id= u.id')->fields('c.real_name,c.realname,c.mobile,u.id,u.nickname,u.avatar,dp.create_time')->where($where9)->order('dp.id desc')->findPage($page,10);
            if($list['data']){
                unset($list['html']);
                $total = count($list['data']);
                foreach($list['data'] as $k=>$v){
                    if($v['id']==null){
                        unset($list['data'][$k]);
                        $total = $total-1;
                    }else{
                        $shop = $this->model->table('district_shop')->where('owner_id='.$v['id'])->find();
                        if(in_array($v['id'],$user_ids_arr) && $shop) {
                            $list['data'][$k]['role_type'] = 2; //经销商 
                        }else{
                            $list['data'][$k]['role_type'] = 1; //商家
                        }
                    }
                    if($v['avatar']=='/0.png') {
                         $list['data'][$k]['avatar'] = '0.png';
                    }
                }
                $list['data'] = array_values($list['data']); 
            } else {
                $list['data'] = [];
            }
            $num = $this->model->table('district_promoter as dp')->join('left join user as u on dp.user_id= u.id')->fields('u.id')->where($where9)->findAll();
            if($num) {
                foreach($num as $k=>$v){
                    if($v['id']==null){
                        unset($num[$k]);
                    }else{
                        $shop = $this->model->table('district_shop')->where('owner_id='.$v['id'])->find();
                        if(in_array($v['id'],$user_ids_arr) && $shop) {
                            $shop_num = $shop_num+1;  
                        }else{
                            $promoter_num = $promoter_num+1;
                        }
                    }
                }
            }
        } else {
            $list['data'] = [];
        }
        
        
        if($user['user_ids']) {
            // $ids = $user['user_ids'];
            $ids_arr = $user['ids'];
            if($promoter_id_arr) {
                $ids_arr = array_merge($ids_arr,$promoter_id_arr);
            }
            $ids = $ids_arr!=null?implode(',', $ids_arr):'';
            $where1 = "user_id in ($ids) and pay_status=1 and status=4";
            if($start_date && $end_date) {
                $where1.=" and pay_time between '{$start_date}' and '{$end_date}'";
            }
            $order_num = $this->model->table('order')->where($where1)->count();
            $order_total = $this->model->table('order')->fields('sum(order_amount) as sum')->where($where1)->query();
            $order_sum = $order_total[0]['sum']!=null?$order_total[0]['sum']:0.00;        
            
            $where3 = "user_id in ($ids) and type=21";
            if($start_date && $end_date) {
                $where3 .=" and time between '{$start_date}' and '{$end_date}'"; 
            }
            $benefit_total = $this->model->table('balance_log')->fields('sum(amount) as sum')->where($where3)->query();
            $benefit_sum = $benefit_total[0]['sum']!=null?$benefit_total[0]['sum']:0.00;
            $where4 = "user_id in ($ids) and type=8";
            if($start_date && $end_date) {
                $where4 .=" and time between '{$start_date}' and '{$end_date}'"; 
            }
            $crossover_total = $this->model->table('balance_log')->fields('sum(amount) as sum')->where($where4)->query();
            $crossover_sum = $crossover_total[0]['sum']!=null?$crossover_total[0]['sum']:0.00;
            $where5 = "user_id in ($ids) and type in (1,2)";
            if($start_date && $end_date) {
                $where5 .=" and order_time between '{$start_date}' and '{$end_date}'"; 
            }
            $taoke_num = $this->model->table('benefit_log')->where($where5)->count(); 
            
            $where7 = "user_id in ($ids) and type=5";
            if($start_date && $end_date) {
                $where7 .=" and time between '{$start_date}' and '{$end_date}'"; 
            }
            $order_benefit_total = $this->model->table('balance_log')->fields('sum(amount) as sum')->where($where7)->query();
            $order_benefit = $order_benefit_total[0]['sum']!=null?$order_benefit_total[0]['sum']:0.00;
        } else {
            $order_num = 0;
            $order_sum = 0.00;
            $benefit_sum = 0.00;
            $crossover_sum = 0.00;
            $shop_num = 0;
            $promoter_num = 0;
            $order_benefit = 0.00;
            $taoke_num = 0;
        }

        $user_ids = $user['user_ids'];
        if($pure_promoter_ids!='') {
            $where2 = "shop_ids in ($pure_promoter_ids) and pay_status = 1";
            if($start_date && $end_date) {
                $where2 .= " AND (pay_time BETWEEN  '{$start_date}' AND  '{$end_date}' )";
            }
            $offline_order_num = $this->model->table('order_offline')->where($where2)->count();

            $offline_order_total = $this->model->table('order_offline')->fields('sum(order_amount) as sum')->where($where2)->query();
            $offline_order_sum = $offline_order_total[0]['sum']!=null?$offline_order_total[0]['sum']:0.00;
        } else {
            $offline_order_num = 0;
            $offline_order_sum = 0.00;
        }
        
        $customer = $this->model->table('customer as c')->fields('c.real_name,c.mobile,u.nickname,u.avatar')->join('left join user as u on c.user_id=u.id')->where('c.user_id='.$user_id)->find();
        $shop = $this->model->table('district_shop')->fields('create_time')->where('owner_id='.$user_id)->find();
        $promoter = $this->model->table('district_promoter')->fields('create_time')->where('user_id='.$user_id)->find();
        $seo_title = !empty($shop)?'经销商':'商家';
        
        if($customer['avatar'] == '/0.png') {
           $customer['avatar'] == '0.png';
        }
        $result = array();
        $result['order_num'] = $order_num; //线上总订单数
        $result['offline_order_num'] = $offline_order_num; //扫码总订单数
        $result['shop_num'] = $shop_num; //经销商数量
        $result['promoter_num'] = $promoter_num; //商家数量
        $result['user_num'] = $user['num']; //会员数量
        $result['order_sum'] = $order_sum; //线上订单总金额     
        $result['offline_order_sum'] = $offline_order_sum; //扫码订单总金额
        $result['order_benefit'] = $order_benefit; //线上订单跨界收益
        $result['crossover_sum'] = $crossover_sum; //扫码订单跨界收益
        $result['benefit_sum'] = $benefit_sum; // 优惠购收益
        $result['taoke_num'] = $taoke_num; // 优惠购订单数
        $result['real_name'] = $customer['real_name'];
        $result['nickname'] = $customer['nickname'];
        $result['mobile'] = $customer['mobile'];
        $result['avatar'] = $customer['avatar'];
        $result['create_time'] = !empty($shop)?$shop['create_time']:$promoter['create_time'];
        
        $this->assign('result',$result);
        $this->assign('user_id',$user_id);
        $this->assign('page',$page);
        $this->assign("seo_title", $seo_title);
        $this->assign('list',$list);
        $this->redirect();
    }

    // public function getAllChildUserIds($user_id,$start_date='',$end_date='')
    // {
    //    $model = new Model();
    //    $is_break = false;
    //    $num = 0;
    //    $now_user_id = $user_id;
    //    $idstr = '';
    //    $ids = array();
    //    while(!$is_break) {
    //       $where = "i.user_id=".$now_user_id;
    //       if($start_date && $end_date) {
    //         $where.=" and c.reg_time between '{$start_date}' and '{$end_date}'";
    //       }
    //       $inviter_info = $model->table("invite as i")->join('left join customer as c on i.invite_user_id=c.user_id')->fields('i.invite_user_id')->where($where)->findAll();
    //       if($inviter_info) {
    //         foreach($inviter_info as $k =>$v) {
    //            $customer = $model->table('customer')->fields('user_id')->where('user_id='.$v['invite_user_id'])->find(); 
    //            if($customer) {
    //              $ids[] = $v['invite_user_id'];
    //            }
    //            $num = $num+1;
    //            $now_user_id = $v['invite_user_id'];
    //         }
    //       } else {
    //         $is_break = true;
    //       }
    //       array_push($ids, $user_id);
    //       $idstr = $ids!=null?implode(',', $ids):'';
    //    }
    //    $result['user_ids'] = $idstr;
    //    $result['num'] = $num;
    //    return $result;
    // }
    
    public function getAllChildUserIds($user_id,$start_date='',$end_date='')
    {
       $model = new Model();
        $shop = $model->table("district_shop")->fields('id,owner_id')->where("owner_id=".$user_id)->find();
        if($shop) {
            $idstr = Common::getAllChildShops($user_id);
            $shopids = $idstr['shop_ids']==''?$shop['id']:$shop['id'].','.$idstr['shop_ids'];
            $where = "district_id in ($shopids)";
            if($start_date && $end_date) {
                $t1 = strtotime($start_date);
                $t2 = strtotime($end_date);
                $where.=" and createtime >=".$t1." and createtime <=".$t2;
            }
            $inviter_info = $model->table("invite")->fields('invite_user_id')->where($where)->findAll();
            $ids = array();
            if($inviter_info) {
                foreach($inviter_info as $k =>$v) {
                   $ids[] = $v['invite_user_id'];
                }
            }
            array_push($ids, $user_id);
            $user_ids = $ids!=null?implode(',', $ids):'';
            $result['user_ids'] = $user_ids;
            $result['ids'] = $ids;
            $result['shopids'] = $shopids;
            $result['shop_ids_arr'] = $idstr['shop_ids_arr'];
            $result['user_ids_arr'] = $idstr['user_ids_arr'];
            $result['num'] = count($inviter_info);
        } else {
            $promoter = $model->table('district_promoter')->fields('hirer_id')->where('user_id='.$user_id)->find();
            $is_break = false;
            $num = 0;
            $now_user_id = $user_id;
            $idstr = '';
            $ids = array();
            while(!$is_break) {
               $where = "i.user_id=".$now_user_id." and i.district_id=".$promoter['hirer_id'];
               if($start_date && $end_date) {
                 $where.=" and c.reg_time between '{$start_date}' and '{$end_date}'";
               }
               $inviter_info = $model->table("invite as i")->join('inner join customer as c on i.invite_user_id=c.user_id')->fields('i.invite_user_id')->where($where)->findAll();
               if($inviter_info) {
                 foreach($inviter_info as $k =>$v) {
                    $ids[] = $v['invite_user_id'];
                    $num = $num+1;
                    $now_user_id = $v['invite_user_id'];
                 }
               } else {
                 $is_break = true;
               }
               array_push($ids, $user_id);
               $idstr = $ids!=null?implode(',', $ids):'';
            }
            $result['user_ids'] = $idstr;
            $result['ids'] = $ids;
            $result['shopids'] = '';
            $result['shop_ids_arr'] = null;
            $result['user_ids_arr'] = null;
            $result['num'] = $num;
        }
        
        return $result;
    }

}    