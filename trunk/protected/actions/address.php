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
        // $rand1 = rand(-111,111)/100000;
        // $rand2 = rand(-111,111)/100000;
        $model = new Model();
        $list = $model->table('redbag as r')->join('left join customer as c on r.user_id = c.user_id')->fields('r.*,c.real_name')->order('r.id desc')->findAll();
        if($list){
           if($list){
            foreach($list as $k => $v){
                 $promoter = $model->table('district_promoter')->fields('lng,lat')->where("lng != '' and lat != '' and user_id=".$v["user_id"])->find();
                 if($promoter){
                    // $list[$k]['lng'] = $promoter['lng']+$rand;
                    // $list[$k]['lat'] = $promoter['lat']+$rand;
                    if($list[$k]['lng']=='' && $list[$k]['lat']==''){
                        $this->model->table('redbag')->data(array('lng'=>$promoter['lng']+rand(-1111,1111)/1000000,'lat'=>$promoter['lat']+rand(-1111,1111)/1000000))->where('id='.$v['id'])->update();
                    }     
                 }  
            }
         } 
        }
        $new_list = $model->table('redbag as r')->join('left join customer as c on r.user_id = c.user_id')->fields('r.*,c.real_name')->where("r.lng != '' and r.lat != ''")->order('r.id desc')->findAll();
        foreach($new_list as $k => $v){
            $new_list[$k]['bag_name'] = $v['real_name'].'的红包';
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
        if($type==1){
            $list = $model->table('redbag as r')->join('left join customer as c on r.user_id = c.user_id')->fields('r.*,c.real_name')->where('r.user_id='.$user_id)->order('r.id desc')->findPage($page, 10);
        }elseif($type==2){
            $list = $model->table('redbag as r')->join('left join customer as c on r.user_id = c.user_id')->fields('r.*,c.real_name')->where("r.status=1 and r.owner_id like '%$user_id%'")->order('r.id desc')->findPage($page, 10);
        }
        
        if($list){
           unset($list['html']); 
        }
        
        $this->code = 0;
        $this->content = $list;
    }

    public function redbagMake(){
        $amount = Filter::float(Req::args('amount'));
        $info = Filter::text(Req::args('info'));
        $distance = Filter::int(Req::args('distance'));
        $range = Req::args('range');
        $num = Filter::int(Req::args('num'));
        $promoter = $this->model->table('district_promoter')->fields('lng,lat')->where('user_id='.$this->user['id'])->find();
        if(!$promoter){
            $this->code = 1166;
            return;
        }
        if($promoter['lng'] == '' || $promoter['lat'] == ''){
            $this->code = 1170;
            return;
        }
        switch ($range) {
            case '0.5':
                $rand1 = rand(-45,45)/10000;
                if($rand1>0){
                    $rand2 = 0.0045-$rand1;
                }else{
                    $rand2 = 0-(0.0045-abs($rand1));
                }
                $lng = $promoter['lng']+$rand1;
                $lat = $promoter['lat']+$rand2;
                break;
            case '1':
                $rand1 = rand(-9,9)/1000;
                if($rand1>0){
                    $rand2 = 0.009-$rand1;
                }else{
                    $rand2 = 0-(0.009-abs($rand1));
                }
                $lng = $promoter['lng']+$rand1;
                $lat = $promoter['lat']+$rand2;
                break;
            case '3':
                $rand1 = rand(-27,27)/1000;
                if($rand1>0){
                    $rand2 = 0.027-$rand1;
                }else{
                    $rand2 = 0-(0.027-abs($rand1));
                }
                $lng = $promoter['lng']+$rand1;
                $lat = $promoter['lat']+$rand2;
                break;
            case '5':
                $rand1 = rand(-45,45)/1000;
                if($rand1>0){
                    $rand2 = 0.045-$rand1;
                }else{
                    $rand2 = 0-(0.045-abs($rand1));
                }
                $lng = $promoter['lng']+$rand1;
                $lat = $promoter['lat']+$rand2;
                break;
            case '10':
                $rand1 = rand(-90,90)/1000;
                if($rand1>0){
                    $rand2 = 0.09-$rand1;
                }else{
                    $rand2 = 0-(0.09-abs($rand1));
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
        $data = array(
             'amount'=>$amount,
             'info'=>$info,
             'lng'=>$lng,
             'lat'=>$lat,
             'user_id'=>$this->user['id'],
             'distance'=>$distance,
             'range'=>$range,
             'create_time'=>date('Y-m-d H:i:s'),
             'type'=>2,
             'num'=>$num
            );
        $result = $this->model->table('redbag')->data($data)->insert();
        if($result){
            $this->code = 0;
            $this->content = $this->model->table('redbag')->where('id='.$result)->find();
        }else{
            $this->code = 1169;
            return;
        }
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
        $model = new Model();
        
        $list = $model->table('district_promoter as d')->join('left join customer as c on d.user_id = c.user_id')->fields('d.*,c.real_name')->where('id=' . $id)->find();
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
            $list['picture'] = '';
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
        $count = $this->model->table('promoter_collect')->where('promoter_id='.$id)->count();
        $list['attention_num'] = $count;
        $district = $this->model->table('district_shop')->where('owner_id='.$list['user_id'])->find();
        if($district){
            $list['is_district'] = 1;
        }else{
            $list['is_district'] = 0;
        }
        $this->code = 0;
        $this->content = $list;
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
        $info = Filter::text(Req::args('info'));
        $location = Filter::text(Req::args('location'));
        $lng = Filter::sql(Req::args('lng'));
        $lat = Filter::sql(Req::args('lat'));

        $upfile_path = Tiny::getPath("uploads") . "/head/";
        $upfile_url = preg_replace("|" . APP_URL . "|", '', Tiny::getPath("uploads_url") . "head/", 1);
        //$upfile_url = strtr(Tiny::getPath("uploads_url")."head/",APP_URL,'');
        $upfile = new UploadFile('picture', $upfile_path, '500k', '', 'hash', $this->user['id']);
        $upfile->save();
        $info = $upfile->getInfo();

        if ($info[0]['status'] == 1) {
            $image_url = $upfile_url . $info[0]['path'];
            $image = new Image();
            $image->suffix = '';
            $image->thumb(APP_ROOT . $image_url, 100, 100);
            $picture = "http://" . $_SERVER['HTTP_HOST'] . '/' . $image_url;
            $result = $model->table('district_promoter')->data(array('picture' => $picture))->where("user_id=" . $this->user['id'])->update();
        }
        if ($name) {
            $result = $model->table('customer')->data(array('real_name' => $name))->where("user_id=" . $this->user['id'])->update();
        }
        if ($info) {
            $result = $model->table('district_promoter')->data(array('info' => $info))->where("user_id=" . $this->user['id'])->update();
        }
        if ($location) {
            $result = $model->table('district_promoter')->data(array('location' => $location))->where("user_id=" . $this->user['id'])->update();
        }
        if ($lng) {
            $result = $model->table('district_promoter')->data(array('lng' => $lng))->where("user_id=" . $this->user['id'])->update();
        }
        if ($lat) {
            $result = $model->table('district_promoter')->data(array('lat' => $lat))->where("user_id=" . $this->user['id'])->update();
        }

        if ($result) {
            $this->code = 0;
        } else {
            $this->code = 1099;
        }
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
        $customer = Req::args('customer');//商家or代理vip

        $where = "lat<>0";
        //区域
        if ($region_id) {
            $where.= " and region_id=$region_id";     
        }
        //搜索附近
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
            $where.= " and lat>{$squares['right-bottom']['lat']}and lat<{$squares['left-top']['lat']} and lng>{$squares['left-top']['lng']} and lng<{$squares['right-bottom']['lng']}";
        }else{
            $distance = 50;
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
            $where.= " and lat>{$squares['right-bottom']['lat']}and lat<{$squares['left-top']['lat']} and lng>{$squares['left-top']['lng']} and lng<{$squares['right-bottom']['lng']}";
        }
        //按关键词搜索
        if(!empty($keyword)){
            $where.=" and shop_name like '%$keyword%'";
        }
        //筛选商家分类
        if (!empty($classify_id)) {
            $where.= " and classify_id = $classify_id";
        }
         
        //街道
        if ($tourist_id) {
            $where.=" and tourist_id=$tourist_id";
        }
        //地铁线路
        if ($line_number) {
            $where.="line_number=$line_number and which_station=" . $which_station;
        }
        
        $info_sql = $this->model->table('district_promoter')->where($where)->findAll();
        if(!$info_sql){
            $this->code = 0;
            $this->content = [];
        }
        //两点之间的距离
        /*
         *param deg2rad()函数将角度转换为弧度
         *asin 反正弦定理
         * sin 正弦定理
         * pow pow（num1,num2）作用，计算出num1得num2次方。
         * */
        foreach ($info_sql as $key => $value) {
            $radLat1 = deg2rad($lat);//deg2rad()函数将角度转换为弧度
            $radLat2 = deg2rad($value['lat']);

            $radLng1 = deg2rad($lng);
            $radLng2 = deg2rad($value['lng']);

            $a = $radLat1 - $radLat2;
            $b = $radLng1 - $radLng2;

            $s = 2 * asin(sqrt(pow(sin($a / 2), 2) + cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2))) * 6371;
            $d = round($s, 2);//保留小数点后两位
            $info_sql[$key]['dist'] = $d;
        }
        //距离离我最近
        $arr = array();
        foreach ($info_sql as $val) {
            $arr[] = $val['dist'];
        }
        if ($distance_asc) {
            array_multisort($arr, SORT_ASC, $info_sql);
        }
        //人气
        $hots = array();
        foreach ($info_sql as $val) {
            $hots[] = $val['hot'];
        }
        if ($hot) {
            array_multisort($hots, SORT_DESC, $info_sql);
        }
        //评价
        $evaluates = array();
        foreach ($info_sql as $val) {
            $evaluates[] = $val['evaluate'];
        }
        if ($evaluate) {
            array_multisort($evaluates, SORT_DESC, $info_sql);
        }
         //口味
        $tastes = array();
        foreach ($info_sql as $val) {
            $tastes[] = $val['taste'];
        }
        if ($taste) {
            array_multisort($tastes, SORT_DESC, $info_sql);
        }
        //环境
         $environments = array();
        foreach ($info_sql as $val) {
            $environments[] = $val['environment'];
        }
        if ($environment) {
            array_multisort($environments, SORT_DESC, $info_sql);
        }
        //服务
         $quality_services = array();
        foreach ($info_sql as $val) {
            $quality_services[] = $val['quality_service'];
        }
        if ($quality_service) {
            array_multisort($quality_services, SORT_DESC, $info_sql);
        }
        //价格
         $prices = array();
        foreach ($info_sql as $val) {
            $prices[] = $val['price'];
        }
        if ($price==1) {//1表示正序排，2表示倒序排
            array_multisort($prices, SORT_ASC, $info_sql);
        }elseif ($price==2) {
             array_multisort($prices, SORT_DESC, $info_sql);
        }
        if($info_sql){
            foreach($info_sql as $k => $v){
                if($info_sql[$k]['picture']==null){
                    $info_sql[$k]['picture'] = '';
                }
                if($info_sql[$k]['tourist_id']==null){
                    $info_sql[$k]['tourist_id'] = 0;
                }
                if($info_sql[$k]['line_number']==null){
                    $info_sql[$k]['line_number'] = '';
                }
                if($info_sql[$k]['which_station']==null){
                    $info_sql[$k]['which_station'] = '';
                }
                if($info_sql[$k]['distance_asc']==null){
                    $info_sql[$k]['distance_asc'] = '';
                }
                if($info_sql[$k]['distance_asc']==null){
                    $info_sql[$k]['distance_asc'] = '';
                }
                if($info_sql[$k]['hot']==null){
                    $info_sql[$k]['hot'] = '';
                }
                if($info_sql[$k]['taste']==null){
                    $info_sql[$k]['taste'] = '';
                }
                if($info_sql[$k]['environment']==null){
                    $info_sql[$k]['environment'] = '';
                }
                if($info_sql[$k]['quality_service']==null){
                    $info_sql[$k]['quality_service'] = 5;
                }
                if($info_sql[$k]['price']==null){
                    $info_sql[$k]['price'] = '';
                }
                if($info_sql[$k]['classify_id']==null || $info_sql[$k]['classify_id']==0){
                    $info_sql[$k]['classify_id'] = 1;
                }
                if($info_sql[$k]['evaluate']==null){
                    $info_sql[$k]['evaluate'] = '';
                }
                $count = $this->model->table('order_offline')->where('shop_ids='.$v['user_id'])->group('user_id')->findAll();
                if($count){
                    $consume_num = count($count);
                }else{
                    $consume_num = 0;
                }
                // $count = $this->model->table('order_offline')->where('shop_ids=17216')->group('user_id')->count();
                // $count = $this->model->query("SELECT COUNT( id ) AS count FROM  `tiny_order_offline` WHERE shop_ids =1314 GROUP BY user_id");
                
                $info_sql[$k]['consume_num'] = $consume_num;
                $shop_type = $this->model->table('promoter_type')->where('id='.$v['classify_id'])->find();
                $district = $this->model->table('district_shop')->where('owner_id='.$v['user_id'])->find();
                if($district){
                    $is_district = 1;
                }else{
                    $is_district = 0;
                }
                $info_sql[$k]['shop_type'] = $shop_type['name'];
                $info_sql[$k]['is_district'] = $is_district;
                if($info_sql[$k]['shop_name']==''){
                    $user = $this->model->table('customer')->fields('real_name')->where('user_id='.$v['user_id'])->find();
                    $this->model->table('district_promoter')->data(array('shop_name'=>$user['real_name'].'的店铺'))->where('user_id='.$v['user_id'])->update();
                }
                if($customer==1){
                    if($info_sql[$k]['is_district']==0){
                        unset($info_sql[$k]);
                    }
                }
            }
        }
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
        $area = $this->model->table('areas')->where("name like '%$city%' or short_name like '%$city%'")->find();
        if(!$area){
            $this->code = 1168;
            return;
        }
        $pid = $area['id'];
        
        $county = $this->model->table('areas')->where('parent_id='.$pid)->order('sort asc')->findAll();
        if($county){
            // $street = array();
            foreach($county as $k => $v){
               $county[$k]['child'] = $this->model->table('areas')->where('parent_id='.$v['id'])->order('sort asc')->findAll();
         }
        }
        
        $this->code = 0;
        $this->content = $county;
    }

    public function getLnglat(){
        $address = Filter::text(Req::args('address'));
        $url = "http://restapi.amap.com/v3/geocode/geo?address=".$address."&output=JSON&key=30e9de56560b226c08a389ee23550f68";
        $result = file_get_contents($url);
        $return = json_decode($result,true);
        $location = $return['geocodes'][0]['location'];
        $str = explode(',',$location);
        $lng = $str[0];
        $lat = $str[1];
        $this->code = 0;
        // $this->content['lng'] = $lng;
        // $this->content['lat'] = $lat;
        $this->content = $return;
    }

}
