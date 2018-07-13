<?php

class AddressAction extends Controller
{

    public $model = null;
    public $user = null;
    public $code = 1000;
    public $content = NULL;

    public function __construct()
    {
        $this->model = new Model();
    }

    public function save()
    {
        $rules = array('zip:zip:邮政编码格式不正确!', 'addr:required:内容不能为空！', 'accept_name:required:收货人姓名不能为空!,mobile:mobi:手机格式不正确!,phone:phone:电话格式不正确', 'province:[1-9]\d*:选择地区必需完成', 'city:[1-9]\d*:选择地区必需完成', 'county:[1-9]\d*:选择地区必需完成');
        $info = Validator::check($rules);

        if (!is_array($info) && $info == true) {
            Filter::form(array('sql' => 'accept_name|mobile|phone', 'txt' => 'addr', 'int' => 'province|city|county|zip|is_default|id'));
            $is_default = Filter::int(Req::args("is_default"));
            if ($is_default == 1) {
                $this->model->table("address")->where("user_id=" . $this->user['id'])->data(array('is_default' => 0))->update();
            } else {
                Req::args("is_default", "0");
            }
            Req::args("user_id", $this->user['id']);
            $id = Filter::int(Req::args('id'));
            if ($id) {
                $this->model->table("address")->where("id=$id and user_id=" . $this->user['id'])->update();
                $this->code = 0;
            } else {
                $obj = $this->model->table("address")->where('user_id=' . $this->user['id'])->fields("count(*) as total")->find();
                if ($obj && $obj['total'] >= 20) {
                    $this->code = 1050;
                    return;
                } else {
                    $address_id = $this->model->table("address")->insert();
                    $this->code = 0;
                }
            }
        } else {
            $this->code = 1000;
        }
    }

    public function info()
    {
        $id = Filter::int(Req::args("id"));
        if ($id) {
            $model = new Model("address");
            $data = $model->where("id = $id and user_id =" . $this->user['id'])->find();
            $areas = $this->model->table("area")->where("id in({$data['province']},{$data['city']},{$data['county']})")->findAll();
            $dictarr = array();
            foreach ($areas as $area) {
                $dictarr[$area['id']] = $area['name'];
            }
            $namearr = array();
            if (isset($dictarr[$data['province']])) {
                $namearr[] = $dictarr[$data['province']];
            }
            if (isset($dictarr[$data['city']])) {
                $namearr[] = $dictarr[$data['city']];
            }
            if (isset($dictarr[$data['county']])) {
                $namearr[] = $dictarr[$data['county']];
            }
            $data['address'] = implode(' ', $namearr);
            $this->code = 0;
            $this->content = array(
                'addressinfo' => $data,
                'addressdict' => $areas
            );
        } else {
            $this->code = 1000;
        }
    }

    public function del()
    {
        $id = Filter::int(Req::args("id"));
        $this->model->table("address")->where("id=$id and user_id=" . $this->user['id'])->delete();
        $this->code = 0;
    }

    public function lists()
    {
        $model = new Model("address");
        $addresslist = $model->where("user_id=" . $this->user['id'])->order("id desc")->findAll();
        $area_ids = array();
        foreach ($addresslist as $addr) {
            $area_ids[$addr['province']] = $addr['province'];
            $area_ids[$addr['city']] = $addr['city'];
            $area_ids[$addr['county']] = $addr['county'];
        }
        $area_ids = implode(',', $area_ids);
        $addressdict = array();
        if ($area_ids != '')
            $addressdict = $model->table("area")->where("id in ($area_ids)")->findAll();
        $dictarr = array();
        foreach ($addressdict as $area) {
            $dictarr[$area['id']] = $area['name'];
        }
        foreach ($addresslist as $k => &$v) {
            $namearr = array();
            if (isset($dictarr[$v['province']])) {
                $namearr[] = $dictarr[$v['province']];
            }
            if (isset($dictarr[$v['city']])) {
                $namearr[] = $dictarr[$v['city']];
            }
            if (isset($dictarr[$v['county']])) {
                $namearr[] = $dictarr[$v['county']];
            }
            $v['address'] = implode(' ', $namearr);
        }
        $this->code = 0;
        $this->content = array(
            'addresslist' => $addresslist,
            'addressdict' => $addressdict
        );
    }

    public function redbagList()
    {
        $lng = Req::args('lng');//经度
        $lat = Req::args('lat');//纬度
        $radius = 5000;//半径
        $where = "r.pay_status=1 and r.status!=2 and r.lng != '' and r.lat != ''";
        //搜索附近
        if($lng && $lat){
            if(Req::args('distance')!=''){
                $distance = Req::args('distance');//距离
                $dlng = 2 * asin(sin($distance / (2 * 6371)) / cos(deg2rad($lat)));
                $dlng = rad2deg($dlng);//rad2deg() 函数把弧度数转换为角度数

                $dlat = $distance / 6371;
                $dlat = rad2deg($dlat);//rad2deg() 函数把弧度数转换为角度数

                $squares = array(
                    'left-top' => array('lat' => $lat + $dlat, 'lng' => $lng - $dlng),
                    'right-top' => array('lat' => $lat + $dlat, 'lng' => $lng + $dlng),
                    'left-bottom' => array('lat' => $lat - $dlat, 'lng' => $lng - $dlng),
                    'right-bottom' => array('lat' => $lat - $dlat, 'lng' => $lng + $dlng)
                );
                $where.= " and lat>{$squares['right-bottom']['lat']} and lat<{$squares['left-top']['lat']} and lng>{$squares['left-top']['lng']} and lng<{$squares['right-bottom']['lng']}";
            }
        }
        $model = new Model();
        // $list = $model->table('redbag as r')->join('left join customer as c on r.user_id = c.user_id')->fields('r.*,c.real_name')->order('r.id desc')->findAll();
        // if($list){
        //     foreach($list as $k => $v){
        //          $promoter = $model->table('district_promoter')->fields('lng,lat')->where("lng != '' and lat != '' and user_id=".$v["user_id"])->find();
        //          if($promoter){
        //             // $list[$k]['lng'] = $promoter['lng']+$rand;
        //             // $list[$k]['lat'] = $promoter['lat']+$rand;
        //             if($list[$k]['lng']=='' && $list[$k]['lat']==''){
        //                 $this->model->table('redbag')->data(array('lng'=>$promoter['lng']+rand(-1111,1111)/1000000,'lat'=>$promoter['lat']+rand(-1111,1111)/1000000))->where('id='.$v['id'])->update();
        //             }     
        //          }  
        //     }  
        // }
        $new_list = $model->table('redbag as r')->join('left join customer as c on c.user_id=r.user_id left join district_promoter as dp on r.user_id=dp.user_id')->fields('r.*,c.real_name,dp.shop_name,dp.picture,dp.id as dp_id')->where($where)->order('r.id desc')->findAll();
        if($new_list){
            foreach($new_list as $k => $v){
                $new_list[$k]['bag_name'] = $v['shop_name']!=''? $v['shop_name'].'的红包' : $v['real_name'].'的红包';
                $new_list[$k]['avatar'] = $v['picture'];
                if($new_list[$k]['avatar']==null){
                    $new_list[$k]['avatar']='';
                }
                $config = Config::getInstance()->get("other");
                $available_distance = isset($config['available_distance'])?$config['available_distance']:'1000';
                $new_list[$k]['available_distance'] = $available_distance;
                if($lng && $lat && $radius){
                   $actual_distance = Common::getDistanceByLatLng($lat,$lng,$v['lat'],$v['lng']);
                   $new_list[$k]['dist'] = $actual_distance;
                   if($actual_distance>$radius){
                    unset($new_list[$k]);
                   }
                }
            }
            $new_list = array_values($new_list);
        }else{
            $new_list = array();
        }
        
        $this->code = 0;
        $this->content = $new_list;
    }

    public function myRedbag(){
        $page = Filter::int(Req::args('page'));
        $type = Filter::int(Req::args('type'));
        if (!$page) {
            $page = 1;
        }
        $model = new Model();
        $user_id = $this->user['id'];
        $total_get_money = 0;
        if($type==1){ // 我发出去的红包
            $list = $model->table('redbag as r')->join('left join customer as c on r.user_id = c.user_id left join user as u on r.user_id=u.id')->fields('r.*,c.real_name,u.avatar')->where('r.pay_status=1 and r.user_id='.$user_id)->order('r.id desc')->findPage($page, 10);
            $money = $model->table('redbag as r')->fields('sum(r.total_amount) as total_money')->where('r.pay_status=1 and r.user_id='.$user_id)->order('r.id desc')->findAll();
            if($list){
                foreach($list['data'] as $k=>$v){
                    $list['data'][$k]['total_get_money'] = sprintf('%.2f',$v['total_amount']-$v['amount']);
                    $list['data'][$k]['total_money'] = sprintf('%.2f',$v['total_amount']);
                }
            }
        }elseif($type==2){ // 我抢到的红包
            $list = $model->table('redbag as r')->join('left join customer as c on r.user_id = c.user_id left join redbag_get as rg on r.id = rg.redbag_id left join user as u on r.user_id=u.id')->fields('r.id,r.amount,r.type,c.real_name,rg.amount as get_money,rg.get_date,u.avatar')->where("r.status!=0 and rg.get_user_id=".$user_id)->order('rg.id desc')->findPage($page, 10);
            $money = $model->table('redbag as r')->join('left join redbag_get as rg on r.id = rg.redbag_id')->fields('sum(rg.amount) as total_money')->where("r.status!=0 and rg.get_user_id=".$user_id)->order('rg.id desc')->findAll();
        }else{
            $this->code = 1201;
            return;
        }
        
        if($list && $money){
           unset($list['html']);
           $total_money = $money[0]['total_money'];
        }else{
            $list = array(
                'data'=>array(),
                'page'=>array(
                    'total'=>'0',
                    'totalPage'=>0,
                    'pageSize'=>10,
                    'page'=>1
                    )
                );
            $total_money = '0.00';
        }
        
        $this->code = 0;
        $this->content = $list;
        $this->content['total_money'] = $total_money;
    }

    public function redbagMake(){
        $payment_id = Filter::int(Req::args('payment_id'));
        $amount = round(Filter::float(Req::args('amount')),2);
        $info = Filter::text(Req::args('info'));
        $distance = 10; // 设置距离红包打开距离，默认10米，没用上
        $range = Filter::int(Req::args('range')); // 单位百米
        $num = Filter::int(Req::args('num'));
        $type = Filter::int(Req::args('type'));
        $redbag_type = Filter::int(Req::args('redbag_type'));
        if(!$type){
            $type = 2;
        }
        if(!$redbag_type){
            $redbag_type = 1;
        }
        $promoter = $this->model->table('district_promoter')->fields('lng,lat')->where('user_id='.$this->user['id'])->find();
        if(!$promoter){
            $this->code = 1166;
            return;
        }
        if($promoter['lng'] == '' || $promoter['lat'] == ''){
            $this->code = 1170;
            return;
        }
        if($amount*100<$num){
            $this->code = 1186;
            return;
        }
        if(!$payment_id){
         $this->code = 1157;
         return;
        }
        switch ($range) {
            case 1:
                $rand1 = rand(-9,9)/10000;
                if($rand1>0){
                    $rand2 = 0.0009-$rand1;
                }else{
                    $rand2 = 0-(0.0009-abs($rand1));
                }
                $lng = $promoter['lng']+$rand1;
                $lat = $promoter['lat']+$rand2;
                break;
            case 2:
                $rand1 = rand(-18,18)/10000;
                if($rand1>0){
                    $rand2 = 0.0018-$rand1;
                }else{
                    $rand2 = 0-(0.0018-abs($rand1));
                }
                $lng = $promoter['lng']+$rand1;
                $lat = $promoter['lat']+$rand2;
                break;
            case 3:
                $rand1 = rand(-27,27)/10000;
                if($rand1>0){
                    $rand2 = 0.0027-$rand1;
                }else{
                    $rand2 = 0-(0.0027-abs($rand1));
                }
                $lng = $promoter['lng']+$rand1;
                $lat = $promoter['lat']+$rand2;
                break;
            case 4:
                $rand1 = rand(-36,36)/10000;
                if($rand1>0){
                    $rand2 = 0.0036-$rand1;
                }else{
                    $rand2 = 0-(0.0036-abs($rand1));
                }
                $lng = $promoter['lng']+$rand1;
                $lat = $promoter['lat']+$rand2;
                break;            
            case 5:
                $rand1 = rand(-45,45)/10000;
                if($rand1>0){
                    $rand2 = 0.0045-$rand1;
                }else{
                    $rand2 = 0-(0.0045-abs($rand1));
                }
                $lng = $promoter['lng']+$rand1;
                $lat = $promoter['lat']+$rand2;
                break;
            case 6:
                $rand1 = rand(-54,54)/10000;
                if($rand1>0){
                    $rand2 = 0.0054-$rand1;
                }else{
                    $rand2 = 0-(0.0054-abs($rand1));
                }
                $lng = $promoter['lng']+$rand1;
                $lat = $promoter['lat']+$rand2;
                break;
            case 7:
                $rand1 = rand(-63,63)/10000;
                if($rand1>0){
                    $rand2 = 0.0063-$rand1;
                }else{
                    $rand2 = 0-(0.0063-abs($rand1));
                }
                $lng = $promoter['lng']+$rand1;
                $lat = $promoter['lat']+$rand2;
                break;
            case 8:
                $rand1 = rand(-72,72)/10000;
                if($rand1>0){
                    $rand2 = 0.0072-$rand1;
                }else{
                    $rand2 = 0-(0.0072-abs($rand1));
                }
                $lng = $promoter['lng']+$rand1;
                $lat = $promoter['lat']+$rand2;
                break;
            case 9:
                $rand1 = rand(-81,81)/10000;
                if($rand1>0){
                    $rand2 = 0.0081-$rand1;
                }else{
                    $rand2 = 0-(0.0081-abs($rand1));
                }
                $lng = $promoter['lng']+$rand1;
                $lat = $promoter['lat']+$rand2;
                break;                
            case 10:
                $rand1 = rand(-90,90)/10000;
                if($rand1>0){
                    $rand2 = 0.009-$rand1;
                }else{
                    $rand2 = 0-(0.009-abs($rand1));
                }
                $lng = $promoter['lng']+$rand1;
                $lat = $promoter['lat']+$rand2;
                break;           
            default:
                $rand1 = rand(-9,9)/1000;
                if($rand1>0){
                    $rand2 = 0.009-$rand1;
                }else{
                    $rand2 = 0-(0.009-abs($rand1));
                }
                $lng = $promoter['lng']+$rand1;
                $lat = $promoter['lat']+$rand2;
                break;
        }
        $order_no = Common::createOrderNo();
        $total_amount = $amount;
        $data = array(
             'order_no'=>'redbag'.$order_no,
             'amount'=>$total_amount,
             'total_amount'=>$total_amount,
             'info'=>$info,
             'lng'=>$lng,
             'lat'=>$lat,
             'user_id'=>$this->user['id'],
             'distance'=>$distance,
             'range'=>$range,
             'create_time'=>date('Y-m-d H:i:s'),
             'type'=>$type,
             'redbag_type'=>$redbag_type,
             'num'=>$num,
             'pay_status'=>0
            );
        $result = $this->model->table('redbag')->data($data)->insert();
        $payment = new Payment($payment_id);
        $paymentPlugin = $payment->getPaymentPlugin();
        
        if($result){
            $packData = $payment->getPaymentInfo('redbag', $result);
            $sendData = $paymentPlugin->packData($packData);

            $this->code = 0;
            $this->content['redbag'] = $this->model->table('redbag')->where('id='.$result)->find();
            $this->content['payment_id'] = $payment_id;
            $this->content['senddata'] = $sendData;
        }else{
            $this->code = 1169;
            return;
        }
    }

    public function redbagOpen(){
        $id = Filter::int(Req::args('redbag_id'));
        $type = Filter::int(Req::args('type')); // 1抢 2看
        $total_get_money = 0;
        if(!$type){
            $type = 1; 
        }
        $redbag = $this->model->table('redbag')->where('id='.$id)->find();
        if(!$redbag){
            $this->code = 1187;
            return;
        }
        if($type==1){  // 抢红包
            if($redbag['status']==1 && $redbag['open_num']==$redbag['num']){ // 红包没了
                $this->model->table('redbag')->data(array('status'=>2))->where('id='.$id)->update();
                $redbag_get = $this->model->table('redbag_get')->where('redbag_id='.$id.' and get_user_id='.$this->user['id'])->find();
                if($redbag_get){  //红包已被抢光了自己参与了
                   $result = $this->newredbag($id);
                    $this->code = 0;
                    $this->content['redbag'] = $result['newredbag'];
                    $this->content['get_money'] = $result['get_money'];
                    $this->content['list'] = $result['list'];
                   return; 
                }else{  //没抢到
                   $this->code = 1188;
                   return; 
                }  
            }
            //计算剩余可领取红包人数
            $num = $redbag['num']-$redbag['open_num'];
            //按人数随机分配红包金额
            if($num>0){ // 开始抢红包    
                if($num>1){
                   if($redbag['redbag_type']==1){ //拼手气红包随机分配金额
                       //计算理论可领取最大红包金额，以分为最小单位
                       $max_money = ($redbag['amount']-$num*0.01)*100; //单位分
                       //随机分配红包金额
                       $get_money = rand(1,$max_money)/100; // 单位元
                   }else{ //普通红包每人等额
                      $get_money = round($redbag['total_amount']/$redbag['num'],2);
                   }    
                }else{
                   $get_money = $redbag['amount']; // 单位元
                }
               
               $exist = $this->model->table('redbag_get')->where('redbag_id='.$id.' and get_user_id='.$this->user['id'])->find();
               if($exist){ //已领取过该红包 
                    $result = $this->newredbag($id);
                    $this->code = 0;
                    $this->content['redbag'] = $result['newredbag'];
                    $this->content['get_money'] = $result['get_money'];
                    $this->content['list'] = $result['list'];
                    return;
                 // $this->code = 1198;
                 // return;
               }else{ // 抢到红包了
                 $this->model->table('redbag')->data(array('status'=>1,'amount'=>"`amount`-({$get_money})",'open_time'=>date('Y-m-d H:i:s'),'open_num'=>"`open_num`+1"))->where('id='.$id)->update();
                 $this->model->table('redbag_get')->data(array('redbag_id'=>$id,'get_user_id'=>$this->user['id'],'amount'=>$get_money,'get_date'=>date('Y-m-d H:i:s')))->insert();
               
                 $this->model->table('customer')->data(array('balance'=>"`balance`+({$get_money})"))->where('user_id='.$this->user['id'])->update();
                 Log::balance($get_money,$this->user['id'],$redbag['order_id'],'抢红包收益',14);
               
                 $result = $this->newredbag($id);
                $this->code = 0;
                $this->content['redbag'] = $result['newredbag'];
                $this->content['get_money'] = $get_money;
                $this->content['list'] = $result['list'];
                return;
              }
            }else{  // 手慢红包无
               $this->model->table('redbag')->data(array('status'=>2))->where('id='.$id)->update();
               $this->code = 1188;
               return; 
            }
        }else{ // 只查看红包领取详情不抢
            if($redbag['status']==1 && $redbag['open_num']==$redbag['num']){
                $this->model->table('redbag')->data(array('status'=>2))->where('id='.$id)->update();
            }
            $result = $this->newredbag($id);
            $this->code = 0;
            $this->content['redbag'] = $result['newredbag'];
            $this->content['get_money'] = $result['get_money'];
            $this->content['list'] = $result['list'];
            return;
        }
        
    }

    public function newredbag($id){
        $total_get_money = 0;
        $newredbag = $this->model->table('redbag as r')->join('left join user as u on u.id=r.user_id left join district_promoter as dp on r.user_id=dp.user_id left join customer as c on r.user_id=c.user_id')->fields('r.*,u.avatar,dp.picture,dp.shop_name,c.real_name')->where('r.id='.$id)->find();
        if(!$newredbag){
            $this->code = 1200;
            return;
        }
        if($newredbag['open_num']==$newredbag['num']){
            $this->model->table('redbag')->data(array('status'=>2))->where('id='.$id)->update();
        }
        $newredbag['real_name'] = $newredbag['shop_name']==null?'':$newredbag['shop_name'];
        $newredbag['avatar'] = $newredbag['picture']==null?'':$newredbag['picture'];
        $newredbag['picture'] = $newredbag['picture']==null?'':$newredbag['picture'];
        $newredbag['real_name'] = $newredbag['real_name']==null?'':$newredbag['real_name'];
        $newredbag['shop_name'] = $newredbag['shop_name']==null?'':$newredbag['shop_name'];
        $list = $this->model->table('redbag_get as rg')->join('left join redbag as r on rg.redbag_id=r.id left join customer as c on rg.get_user_id=c.user_id left join user as u on rg.get_user_id=u.id')->fields('r.id,c.real_name,u.avatar,rg.amount,rg.get_date')->where('rg.redbag_id='.$id)->order('rg.id desc')->findAll();
        // if($list){
        //     foreach($list as $k=>$v){
        //         $total_get_money+=$v['amount'];
        //     }
        // }else{
        //     $list = array(); 
        // }
        if(!$list){
            $list = array();
        }else{
            foreach($list as $k =>$v){
                $list[$k]['real_name'] = $v['real_name']==null?'':$v['real_name'];
                $list[$k]['avatar'] = $v['avatar']==null?'':$v['avatar'];
            }
        }
        $newredbag['total_get_money'] = sprintf('%.2f',$newredbag['total_amount']-$newredbag['amount']);
        $newredbag['total_money'] = sprintf('%.2f',$newredbag['total_amount']);
        $redbag_get = $this->model->table('redbag_get')->where('redbag_id='.$id.' and get_user_id='.$this->user['id'])->find();
        if($redbag_get){
            $get_money = $redbag_get['amount']; 
        }else{
            $get_money = 0.00;
        }
        return array(
             'newredbag'=>$newredbag,
             'get_money'=>sprintf('%.2f',$get_money),
             'list'=>$list
            );
    }

    public function redbagHadOpened(){
        $id = Filter::int(Req::args('id'));
        $redbag = $this->model->table('redbag')->where('id='.$id)->find();
        if(!$redbag){
            $this->code = 1200;
            return;
        }
        if($redbag['amount']=='0.00' && $redbag['open_num']==$redbag['num']){
            $redbag_get1 = $this->model->table('redbag_get')->where('redbag_id='.$id.' and get_user_id='.$this->user['id'])->find();
            if($redbag_get1){
                $had_opened = 2; //曾经抢到过但现在没有了
                $info = '红包没了，您已经领取过该红包';
            }else{
                $had_opened = 0; // 来晚了
                $info = '手慢了，红包没了';
            }
        }else{
            $redbag_get = $this->model->table('redbag_get')->where('redbag_id='.$id.' and get_user_id='.$this->user['id'])->find();
            if($redbag_get){
                $had_opened = 2; // 抢到过
                $info = '您已经领取过该红包';
            }else{
                $had_opened = 1; // 没抢过
                $info = '可领取的红包';
            }
        }
        $this->code = 0;
        $this->content['had_opened'] = $had_opened;
        $this->content['info'] = $info;
    }

    public function promoterList()
    {
        $page = Filter::int(Req::args('page'));
        if (!$page) {
            $page = 1;
        }
        $model = new Model();
        $list = $model->table('district_promoter as d')->join('left join customer as c on d.user_id = c.user_id')->fields('d.*,c.real_name')->order('d.id desc')->findPage($page, 10);
        $this->code = 0;
        $this->content = $list;
    }

    public function promoterInfo()
    {
        $id = Filter::int(Req::args('id'));
        if (!$id) {
            $this->code = 1000;
            return;
        }
        $page = Filter::int(Req::args('page'));
        if (!$page) {
            $page = 1;
        }
        $model = new Model();
        
        $list = $model->table('district_promoter as d')->join('left join customer as c on d.user_id = c.user_id left join user as u on d.user_id=u.id')->fields('d.*,c.real_name,u.avatar')->where('d.id=' . $id)->find();
        if($list){
            //增加访问量
            $this->model->table('district_promoter')->data(array('hot'=>$list['hot']+1))->where('id='.$id)->update();
        }
        if($list['invitor_id']==null){
            $list['invitor_id'] = 0;
        }
        if($list['location']==null){
            $list['location'] = '';
        }
        if($list['road']==null){
            $list['road'] = '';
        }
        if($list['picture']==null){
            $list['picture'] = 'http://www.ymlypt.com/themes/mobile/images/logo-new.png';
        }
        if($list['info']==null){
            $list['info'] = '';
        }
        if($list['line_number']==null){
            $list['line_number'] = '';
        }
        if($list['which_station']==null){
            $list['which_station'] = '';
        }
        if($list['distance_asc']==null){
            $list['distance_asc'] = 0;
        }
        if($list['hot']==null){
            $list['hot'] = 0;
        }
        if($list['evaluate']==null){
            $list['evaluate'] = 0;
        }
        if($list['taste']==null){
            $list['taste'] = 0;
        }
        if($list['environment']==null){
            $list['environment'] = 0;
        }
        if($list['quality_service']==null){
            $list['quality_service'] = 0;
        }
        if($list['price']==null){
            $list['price'] = '';
        }
        if($list['avatar']==null){
            $list['avatar'] = '';
        }
        if($list['shop_name']==null){
            $user = $this->model->table('customer as c')->fields('c.real_name,u.nickname')->join("left join user as u on c.user_id = u.id")->where('c.user_id='.$list['user_id'])->find();
            $list['shop_name'] = empty($user['real_name'])?$user['nickname']:$user['real_name'];  
        }
        $count = $this->model->table('promoter_collect')->where('promoter_id='.$id)->count();
        $list['attention_num'] = $count;
        $district = $this->model->table('district_shop')->where('owner_id='.$list['user_id'])->find();
        if($district){
            $list['is_district'] = 1;
        }else{
            $list['is_district'] = 0;
        }
        $goods_list = $this->model->table('goods')->fields('id,name,category_id,img,sell_price,create_time,store_nums,is_online,base_sales_volume')->where('user_id='.$list['user_id'].' and is_online=0')->order('id desc')->findPage($page, 10);
        $list['goods_list'] = isset($goods_list['data']) && $goods_list['data']!=null?$goods_list['data']:[];
        $this->code = 0;
        $this->content = $list;
    }

    public function myPromoterDetail(){
        $model = new Model();
        $promoter = $model->table('district_promoter')->fields('shop_name,picture,province_id,city_id,region_id,tourist_id,location,road,info')->where('user_id=' . $this->user['id'])->find();
        $customer = $model->table('customer')->fields('real_name')->where('user_id=' . $this->user['id'])->find();
        if(!$promoter || !$customer){
            $this->code = 1166;
            return;
        }
        if($promoter['shop_name']==null){
            $promoter['shop_name'] = $customer['real_name'];
        }
        if($promoter['picture']==null){
            $promoter['picture'] = 'http://www.ymlypt.com/themes/mobile/images/logo-new.png';
        }
        if($promoter['province_id']==null){
            $promoter['province_id'] = 0;
        }
        if($promoter['city_id']==null){
            $promoter['city_id'] = 0;
        }
        if($promoter['region_id']==null){
            $promoter['region_id'] = 0;
        }
        if($promoter['tourist_id']==null){
            $promoter['tourist_id'] = 0;
        }
        if($promoter['location']==null){
            $promoter['location'] = '';
        }
        if($promoter['road']==null){
            $promoter['road'] = '';
        }
        if($promoter['info']==null){
            $promoter['info'] = '';
        }
        $this->code = 0;
        $this->content = $promoter;
    }

    public function promoterAttention(){
        $promoter_id = Filter::int(Req::args('promoter_id'));
        if(!$promoter_id){
            $this->code=1000;
            return; 
        }
        $exist = $this->model->table('promoter_collect')->where('user_id='.$this->user['id'].' and promoter_id='.$promoter_id)->find();
        if($exist){
            $this->code = 1167;
            return;
        }
        $this->model->table('promoter_collect')->data(array('user_id'=>$this->user['id'],'promoter_id'=>$promoter_id,'add_time'=>date('Y-m-d H:i:s')))->insert();
        $this->code = 0;
    }

    public function hasAttentioned(){
        $promoter_id = Filter::int(Req::args('promoter_id'));
        if(!$promoter_id){
            $this->code=1000;
            return; 
        }
        $exist = $this->model->table('promoter_collect')->where('user_id='.$this->user['id'].' and promoter_id='.$promoter_id)->find();
        if($exist){
            $attention = 1;
        }else{
            $attention = 0;
        }

        $this->code = 0;
        $this->content = $attention;
    }

    public function promoterEdit(){
        $model = new Model();
        $is_promoter = $model->table('district_promoter')->where('user_id=' . $this->user['id'])->find();
        if (!$is_promoter) {
            $this->code = 1133;
        }
        $name = Filter::str(Req::args('name'));
        $infos = Filter::text(Req::args('info'));
        $location = Filter::text(Req::args('location'));
        $province_id = Filter::int(Req::args('province_id'));
        $city_id = Filter::int(Req::args('city_id'));
        $region_id = Filter::int(Req::args('region_id'));
        $tourist_id = Filter::int(Req::args('tourist_id'));
        $classify_id = Filter::int(Req::args('classify_id'));
        $road = Filter::text(Req::args('road'));
        $picture = Req::args('picture');
        
        if($picture){
            $picture='https://ymlypt.b0.upaiyun.com'.$picture;
        }

        $data = array();
        $data['shop_name'] = $name;
        $data['location'] = $location;
        // if($data['shop_name']==''){
        //     $this->code = 1235;
        //     return;
        // }
        // if($data['location']==''){
        //     $this->code = 1236;
        //     return;
        // }
        if($location!=''){
           $lnglat = Common::getLnglat($location);
            $lng = $lnglat['lng'];
            $lat = $lnglat['lat'];
            if($lng) {
                $data['lng'] = $lng;
            }
            if($lat) {
                $data['lat'] = $lat;
            } 
        }  
        if($province_id) {
            $data['province_id'] = $province_id;
        }
        if($city_id) {
            $data['city_id'] = $city_id;
        }
        if($region_id) {
            $data['region_id'] = $region_id;
        }
        if($tourist_id) {
            $data['tourist_id'] = $tourist_id;
        }
        if($classify_id) {
            $data['classify_id'] = $classify_id;
        }
        if($road) {
            $data['road'] = $road;
        }
        if($picture) {
            $data['picture'] = $picture;
        }
        if($infos) {
            $data['info'] = $infos;
        }

        $model->table('district_promoter')->data($data)->where("user_id=" . $this->user['id'])->update();  

        $this->code = 0;
        
    }

    //附近商家接口
    public function getMap()
    {
        $lng = Req::args('lng');//经度
        $lat = Req::args('lat');//纬度   
        
        $keyword = Filter::text(Req::args('keyword'));
        $classify_id = Filter::int(Req::args('classify_id'));//商家分类
        $distance_asc = Req::args('distance_asc'); //距离离我最近
        $hot = Filter::int(Req::args('hot'));//人气
        $evaluate = Filter::int(Req::args('evaluate'));//评价
        $taste = Filter::int(Req::args('taste'));//口味
        $environment = Filter::int(Req::args('environment'));//环境
        $quality_service  = Filter::int(Req::args('quality_service '));//服务
        $price = Filter::int(Req::args('price')); //价格
        $region_id = Req::args('region_id'); //区域
        $tourist_id = Req::args('tourist_id'); //街道或景点
        $line_number = Filter::int(Req::args('line_number'));//几号线
        $which_station = Filter::int(Req::args('which_station'));//哪个站
        $customer = Req::args('customer');//等于1筛选出经销商
        $distance = Req::args('distance');//距离

        $page = Filter::int(Req::args('page'));
        if(!$page){
            $page = 1;
        }
        $radius = 5; //默认5公里
        
        $where = "lat<>0";
        //区域
        // if ($region_id) {
        //     $where.= " and region_id=$region_id";     
        // }
        //搜索附近
        if($lng && $lat){
            if(Req::args('distance')!=''){
                
                $dlng = 2 * asin(sin($distance / (2 * 6371)) / cos(deg2rad($lat)));
                $dlng = rad2deg($dlng);//rad2deg() 函数把弧度数转换为角度数

                $dlat = $distance / 6371;
                $dlat = rad2deg($dlat);//rad2deg() 函数把弧度数转换为角度数

                $squares = array(
                    'left-top' => array('lat' => $lat + $dlat, 'lng' => $lng - $dlng),
                    'right-top' => array('lat' => $lat + $dlat, 'lng' => $lng + $dlng),
                    'left-bottom' => array('lat' => $lat - $dlat, 'lng' => $lng - $dlng),
                    'right-bottom' => array('lat' => $lat - $dlat, 'lng' => $lng + $dlng)
                );
                $where.= " and lat>{$squares['right-bottom']['lat']} and lat<{$squares['left-top']['lat']} and lng>{$squares['left-top']['lng']} and lng<{$squares['right-bottom']['lng']}";
            }
        }
        
        //按关键词搜索
        if(!empty($keyword)){
            $where.=" and shop_name like '%$keyword%'";
        }

        //筛选商家分类
        if (!empty($classify_id)) {
            $where.= " and classify_id = $classify_id";
        }
         
        //地区
        if ($tourist_id) {
            $where.=" and region_id=$tourist_id";
        }

        //地铁线路
        if ($line_number) {
            $where.=" and line_number=$line_number and which_station=" . $which_station;
        }
        // if($distance){
        //     $where.=' and dist<{$radius}';
        // }

        // if(empty($tourist_id) && empty($distance)){
        //     $where.=' and dist<'.$radius;
        // }
        
        $order = 'id desc';
        
        //人气
        if ($hot) {
            $order = 'hot desc';
        }

        //评价
        if ($evaluate) {
            $order = 'evaluate desc';
        }

         //口味
        if ($taste) {
            $order = 'taste desc';
        }
        //环境
        if ($environment) {
            $order = 'environment desc';
        }
        //服务
        if ($quality_service) {
            $order = 'quality_service desc';
        }
        //价格
        if ($price==1) {//1表示正序排，2表示倒序排
            $order = 'price asc';
        }elseif ($price==2) {
            $order = 'price desc';
        }

        // if($distance || $distance_asc){
        //     $order = 'dist asc';
        // }

        $info_sql = $this->model->table('district_promoter')->fields("id,user_id,shop_name,type,status,base_rate,location,province_id,city_id,region_id,road,lng,lat,picture,info,classify_id,hot,evaluate,taste,environment,quality_service,price,shop_type,(6378.138 * 2 * asin(sqrt(pow(sin((lat * pi() / 180 - ".$lat." * pi() / 180) / 2),2) + cos(lat * pi() / 180) * cos(".$lat." * pi() / 180) * pow(sin((lng * pi() / 180 - ".$lng." * pi() / 180) / 2),2)))) as dist")->where($where)->order($order)->findPage($page, 10);
        if(!$info_sql){
            $this->code = 0;
            $this->content = [];
            return;
        }
        //两点之间的距离
        /*
         *param deg2rad()函数将角度转换为弧度
         *asin 反正弦定理
         * sin 正弦定理
         * pow pow（num1,num2）作用，计算出num1得num2次方。
         * */
        $arr = array();
        $info_sql = $info_sql['data'];
        foreach ($info_sql as $key => $value) {
            if($info_sql[$key]['picture']==null){
                    $info_sql[$key]['picture'] = 'http://www.ymlypt.com/themes/mobile/images/logo-new.png';
                }else{
                    $info_sql[$key]['picture'].='?date='.time();
                }
                // if($info_sql[$key]['tourist_id']==null){
                //     $info_sql[$key]['tourist_id'] = 0;
                // }
                // if($info_sql[$key]['line_number']==null){
                //     $info_sql[$key]['line_number'] = '';
                // }
                // if($info_sql[$key]['which_station']==null){
                //     $info_sql[$key]['which_station'] = '';
                // }
                // if($info_sql[$key]['distance_asc']==null){
                //     $info_sql[$key]['distance_asc'] = '';
                // }
                // if($info_sql[$key]['taste']==null){
                //     $info_sql[$key]['taste'] = '';
                // }
                // if($info_sql[$key]['environment']==null){
                //     $info_sql[$key]['environment'] = '';
                // }
                // if($info_sql[$key]['quality_service']==null){
                //     $info_sql[$key]['quality_service'] = 5;
                // }
                // if($info_sql[$key]['price']==null){
                //     $info_sql[$key]['price'] = '';
                // }
                // if($info_sql[$key]['classify_id']==null || $info_sql[$key]['classify_id']==0){
                //     $info_sql[$key]['classify_id'] = 1;
                // }
                // if($info_sql[$key]['evaluate']==null){
                //     $info_sql[$key]['evaluate'] = '';
                // }
                $count = $this->model->table('order_offline')->fields('id')->where('shop_ids='.$value['user_id'])->group('user_id')->findAll();
                if($count){
                    $consume_num = count($count);
                }else{
                    $consume_num = 0;
                }
                $info_sql[$key]['consume_num'] = $consume_num;
                $shop_type = $this->model->table('promoter_type')->where('id='.$value['classify_id'])->find();
                $district = $this->model->table('district_shop')->where('owner_id='.$value['user_id'])->find();
                if($district){
                    $is_district = 1;
                }else{
                    $is_district = 0;
                }
                if($shop_type){
                    $type_name = $shop_type['name'];
                }else{
                    $type_name = '其它';
                }
                $info_sql[$key]['shop_type'] = $type_name;
                $info_sql[$key]['is_district'] = $is_district;
                if($info_sql[$key]['shop_name']==null){
                    $user = $this->model->table('customer as c')->fields('c.real_name,u.nickname')->join("left join user as u on c.user_id = u.id")->where('c.user_id='.$value['user_id'])->find();
                    if($user) {
                        $info_sql[$key]['shop_name'] = empty($user['real_name'])?$user['nickname']:$user['real_name'];           
                    } else {
                      unset($info_sql[$key]);
                    }
                }
                $info_sql[$key]['dist'] = sprintf('%.3f',$value['dist']);
                if($distance && $info_sql[$key]['dist']>$distance){
                    unset($info_sql[$key]);
                }
                if($customer==1){
                    if($info_sql[$key]['is_district']==0){
                        unset($info_sql[$key]);
                    }
                }
                
            // $info_sql[$key]['dist'] = Common::getDistanceByLatLng($lat,$lng,$value['lat'],$value['lng'])/1000;
            // $arr[] = $info_sql[$key]['dist'];
            
            // if($info_sql[$key]['dist']>$radius && empty($tourist_id) && empty($distance)){
            //     unset($info_sql[$key]);
            // }
            
        }
        //距离离我最近
        // if ($distance_asc || $distance) {
        //     array_multisort($arr, SORT_ASC, $info_sql);
        // }
        // if($distance){
        //     $info_sql = Common::arraySequence($info_sql,'dist','SORT_ASC');
        // }
        array_multisort(array_column($info_sql,'dist'),SORT_ASC,$info_sql);
        // $info_sql = array_values($info_sql);
        // $info_sql = array_slice($info_sql, ($page-1)*10, 10);
        $this->code = 0;
        $this->content = $info_sql; 
    }

    //按地铁线查找
    public function getSubway()
    {
        $line_number = Filter::int(Req::args('line_number'));//几号线
        $which_station = Filter::int(Req::args('which_station'));//哪个站
        $result = $this->model->table('district_promoter')->where("line_number=$line_number and which_station=" . $which_station)->findAll();
        if ($result) {
            $this->code = 0;
            $this->content = $result;
        } else {
            $this->code = 1166;
        }

    }

    //商家or会员
    public function businessMember()
    {
        $result = $this->model->table('district_promoter')->where("user_id=" . $this->user['id'])->find();
        if ($result) {
            $is_business = TRUE;
        }else{
            $is_business = FALSE;
        }
        $this->code = 0;
        $this->content['is_business'] = $is_business;
    }

    public function getAreaByCity(){
        $city = Req::args('city');
        // $level = Filter::int(Req::args('city'));
        // if(!$level){
        //     $level = 1;
        // }
        // $area = $this->model->table('areas')->where("name like '%$city%' or short_name like '%$city%'")->find();
        $area = $this->model->table('area')->where("name like '%$city%'")->find();
        if(!$area){
            $this->code = 1168;
            return;
        }
        // $pid = $area['id'];
        $pid = $area['parent_id'];
        $county = $this->model->table('area')->where('parent_id='.$pid)->order('sort asc')->findAll();
        if($county){
            // $street = array();
            foreach($county as $k => $v){
               $county[$k]['child'] = $this->model->table('area')->where('parent_id='.$v['id'])->order('sort asc')->findAll();
         }
        }
        
        $this->code = 0;
        $this->content = $county;
    }

    public function getLnglat(){
        $address = Filter::text(Req::args('address'));
        $url = "http://restapi.amap.com/v3/geocode/geo?address=".$address."&output=JSON&key=12303bfdb8d40d67fa696d5bbfdcf595";
        $result = file_get_contents($url);
        $return = json_decode($result,true);
        $location = $return['geocodes'][0]['location'];
        $str = explode(',',$location);
        $lng = $str[0];
        $lat = $str[1];
        $this->code = 0;
        $this->content['lng'] = $lng;
        $this->content['lat'] = $lat;
        // $pfxpath = 'http://' . $_SERVER['HTTP_HOST'] . "/trunk/protected/classes/yinpay/certs/shanghu_test.pfx";
        // $content = file_get_contents($pfxpath);
        // $this->content = $content;
    }

    public function caculateFare(){
        $weight = Filter::float(Req::args('weight'));
        $address_id = Filter::int(Req::args('address_id'));
        $product_id = Filter::int(Req::args('product_id'));
        $fare = new Fare($weight);
        $productarr = array(1346=>1);
        $product = $this->model->table('products')->fields('goods_id')->where('id='.$product_id)->find();
        if(!$product){
            $this->code = 1040;
            return;
        }
        $goods = $this->model->table('goods')->fields('freeshipping')->where('id='.$product['goods_id'])->find();
        if(!$goods){
            $this->code = 1040;
            return;
        }
        if($goods['freeshipping']==1){
            $totalfare = '0.00';
        } else {
            $totalfare = $fare->calculates($address_id, $productarr);
        }
        
        $this->code = 0;
        $this->content['totalfare'] = $totalfare;
    }

    public function get_all_child_promoters(){      
        $user_id = Filter::int(Req::args('user_id'));
        $shop = $this->model->table('district_shop')->fields('id')->where('owner_id='.$user_id)->find();
        $list = $this->model->table('district_promoter as dp')->fields('dp.user_id')->join('LEFT JOIN customer AS c ON dp.user_id = c.user_id LEFT JOIN district_shop AS ds ON dp.hirer_id = ds.id')->where('ds.invite_shop_id ='.$shop['id'])->findAll();
        $goods_type_array = '';
        foreach ($list as $k => $v) {
            $goods_type_array .= ','.$v['user_id'];
        }
        
        $items = $this->model->table('district_promoter as dp')->join("left join user as u on dp.user_id = u.id")->fields('u.user_id,u.real_name,u.offline_balance')->where("dp.user_id in ({$goods_type_array}) and bl1.note='线下会员消费卖家收益(不参与分账)' and ")->findAll();
        foreach ($items as $k => $v) {
            $sum1 = $this->model->table('balance_log')->fields('sum(amount) as sum1')->where("note='线下会员消费卖家收益(不参与分账)' and user_id=".$v['user_id'])->findAll();
            $sum2 = $this->model->table('balance_log')->fields('sum(amount) as sum1')->where("note='线下会员消费卖家收益' and user_id=".$v['user_id'])->findAll();
            $sum3 = $this->model->table('balance_log')->fields('sum(amount) as sum1')->where("note like '%线下会员消费卖家收益%' and user_id=".$v['user_id'])->findAll();
            $items[$k]['amount1'] = empty($sum1)?0:$sum1[0]['sum1'];
            $items[$k]['amount2'] = empty($sum1)?0:$sum1[0]['sum2'];
            $items[$k]['amount3'] = empty($sum1)?0:$sum1[0]['sum3'];
        }
            if ($items) {
                header("Content-type:application/vnd.ms-excel");
                header("Content-Disposition:filename=doc_receiving_list.xls");
                $fields_array = array('real_name' => '商家名', 'amount1' => '不让利入账金额',  'amount2' => '让利入账金额', 'amount3' => '入账总金额','offline_balance' => '未提现商家金额');
                $str = "<table border=1><tr>";
                foreach($items as $k=>$v){
                    $items[$k]['shop_type'] = $v['type']==1?'实体商家':'个人微商';
                }
                foreach ($fields_array as $value) {
                    $str .= "<th>" . iconv("UTF-8", "GBK", $fields_array[$value]) . "</th>";
                }
                $str .= "</tr>";
                foreach ($items as $item) {
                    $str .= "<tr>";
                    foreach ($fields as $value) {
                        $str .= "<td>" . mb_convert_encoding($item[$value],"GBK", "UTF-8") . "</td>";
                    }
                    $str .= "</tr>";
                }
                $str .= "</table>";
                echo $str;
                exit;
            }
        // var_dump($user_id);die;
        // $result = Common::getAllChildPromoters($user_id);
        // $this->code = 0;
        // $this->content = $goods_type_array;
    }
}
