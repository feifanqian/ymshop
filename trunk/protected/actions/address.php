<?php

class AddressAction extends Controller {

    public $model = null;
    public $user = null;
    public $code = 1000;
    public $content = NULL;

    public function __construct() {
        $this->model = new Model();
    }

    public function save() {
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

    public function info() {
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

    public function del() {
        $id = Filter::int(Req::args("id"));
        $this->model->table("address")->where("id=$id and user_id=" . $this->user['id'])->delete();
        $this->code = 0;
    }

    public function lists() {
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

    public function redbagList(){
        $page = Filter::int(Req::args('page'));
        if(!$page){
            $page = 1; 
        }
        $model = new Model();
        $list = $model->table('redbag as r')->join('left join customer as c on r.user_id = c.user_id')->fields('r.*,c.real_name')->order('r.id desc')->findPage($page,10);
        $this->code = 0;
        $this->content = $list;
    }

    public function promoterList(){
        $page = Filter::int(Req::args('page'));
        if(!$page){
            $page = 1; 
        }
        $model = new Model();
        $list = $model->table('district_promoter as d')->join('left join customer as c on d.user_id = c.user_id')->fields('d.*,c.real_name')->order('d.id desc')->findPage($page,10);
        $this->code = 0;
        $this->content = $list;
    }

    public function promoterInfo(){
        $id = Filter::int(Req::args('id'));
        if(!$id){
            $this->code=1000;
            return; 
        }
        $model = new Model();
        $list = $model->table('district_promoter as d')->join('left join customer as c on d.user_id = c.user_id')->fields('d.*,c.real_name')->where('id='.$id)->find();
        $this->code = 0;
        $this->content = $list;
    }

}
