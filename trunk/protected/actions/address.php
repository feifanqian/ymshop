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
        $page = Filter::int(Req::args('page'));
        if (!$page) {
            $page = 1;
        }
        $model = new Model();
        $list = $model->table('redbag as r')->join('left join customer as c on r.user_id = c.user_id')->fields('r.*,c.real_name')->order('r.id desc')->findPage($page, 10);
        unset($list['html']);
        $this->code = 0;
        $this->content = $list;
    }

    public function myRedbag(){
        $page = Filter::int(Req::args('page'));
        if (!$page) {
            $page = 1;
        }
        $model = new Model();
        $list = $model->table('redbag as r')->join('left join customer as c on r.user_id = c.user_id')->fields('r.*,c.real_name')->where('user_id='.$this->user['id'])->order('r.id desc')->findPage($page, 10);
        unset($list['html']);
        $this->code = 0;
        $this->content = $list;
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
        $describe = Filter::text(Req::args('describe'));
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
        if ($describe) {
            $result = $model->table('district_promoter')->data(array('describe' => $describe))->where("user_id=" . $this->user['id'])->update();
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
        $distance = Req::args('distance');//距离
        $classify_id = Filter::int(Req::args('classify_id'));//商家分类
        $distance_asc = Req::args('distance_asc'); //距离离我最近

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
        //筛选商家分类
        if (!empty($classify_id)) {
            $info_sql = $this->model->query("select * from tiny_district_promoter where lat<>0 and lat>{$squares['right-bottom']['lat']}and lat<{$squares['left-top']['lat']} and lng>{$squares['left-top']['lng']} and lng<{$squares['right-bottom']['lng']} and classify_id = $classify_id");
        } else {
            $info_sql = $this->model->query("select * from tiny_district_promoter where lat<>0 and lat>{$squares['right-bottom']['lat']}and lat<{$squares['left-top']['lat']} and lng>{$squares['left-top']['lng']} and lng<{$squares['right-bottom']['lng']}");
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
        if ($info_sql) {
            $this->code = 0;
            $this->content = $info_sql;
        } else {
            $this->code = 1166;
        }
    }

    //按区域查找商家
    public function getArea()
    {
        $region_id = Filter::int(Req::args('region_id')); //所在区域
        $classify_id = Filter::int(Req::args('classify_id')); //分类
        $tourist_id = Filter::int(Req::args('tourist_id'));//区域下的著名的景点或街道
        $class_id = '';
        $region = '';
        $tourist = '';
        if (!empty($classify_id)) {
            $class_id = $classify_id;
        }
        if (!empty($region_id)) {
            $region = $region_id;
        }
        if (!empty($tourist_id)) {
            $tourist = $tourist_id;
        }
        $result = $this->model->table('district_promoter')->where("classify_id=$classify_id and region_id=$region_id and tourist_id=" . $tourist)->findAll();
        if ($result) {
            $this->code = 0;
            $this->content = $result;
        } else {
            $this->code = 1166;
        }
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
            $this->code = 0;
            $this->content = $result;
        } else {
            $this->code = 1166;
        }
    }

}
