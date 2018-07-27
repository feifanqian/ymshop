<?php

class MarketingController extends Controller {

    public $layout = 'admin';
    private $top = null;
    public $needRightActions = array('*' => true);

    public function init() {
        $menu = new Menu();
        $this->assign('mainMenu', $menu->getMenu());
        $menu_index = $menu->current_menu();
        $this->assign('menu_index', $menu_index);
        $this->assign('subMenu', $menu->getSubMenu($menu_index['menu']));
        $this->assign('menu', $menu);
        $nav_act = Req::get('act') == null ? $this->defaultAction : Req::get('act');
        $nav_act = preg_replace("/(_edit)$/", "_list", $nav_act);
        $this->assign('nav_link', '/' . Req::get('con') . '/' . $nav_act);
        $this->assign('node_index', $menu->currentNode());
        $this->safebox = Safebox::getInstance();
        $this->assign('manager', $this->safebox->get('manager'));
        $this->assign('upyuncfg', Config::getInstance()->get("upyun"));

        $currentNode = $menu->currentNode();
        if (isset($currentNode['name']))
            $this->assign('admin_title', $currentNode['name']);
    }

    public function noRight() {
        $this->redirect("admin/noright");
    }

    //编辑捆绑促销
    public function bundling_save() {
        $id = Req::args('id');
        $goods_id = Req::args("goods_id");
        if (count($goods_id) < 2) {
            $this->msg = array("warning", "捆绑促销商品数量至少2件！");
            $this->redirect('bundling_edit', false, Req::args());
            exit();
        }
        if (is_array($goods_id)) {
            $goods_id = array_unique($goods_id);
            $goods_id = implode(',', $goods_id);
        }
        Req::args("goods_id", $goods_id);
        $model = new Model('bundling');
        $model->save();
        if ($id) {
            Log::op($this->manager['id'], "修改捆绑促销", "管理员[" . $this->manager['name'] . "]:修改了捆绑促销 " . Req::args('title'));
        } else {
            Log::op($this->manager['id'], "添加捆绑促销", "管理员[" . $this->manager['name'] . "]:添加了捆绑促销 " . Req::args('title'));
        }
        $this->redirect("bundling_list");
    }

    //删除捆绑促销
    public function bundling_del() {
        $model = new Model("bundling");
        $id = Req::args("id");
        if ($id) {
            $obj = $model->where("id = $id")->find();
            $model->where("id = $id")->delete();
            if ($obj)
                Log::op($this->manager['id'], "删除捆绑促销", "管理员[" . $this->manager['name'] . "]:删除了捆绑促销 " . $obj['title']);
        }
        $this->redirect("bundling_list");
    }

    //选择捆绑促销的商品
    public function bundling_goods_select() {
        $this->layout = "blank";
        $s_type = Req::args("s_type");
        $s_content = Req::args("s_content");
        $where = "1=1";
        if ($s_content && $s_content != '') {
            if ($s_type == 1) {
                $where .= " and goods_no = '{$s_content}'";
            } else if ($s_type == 2) {
                $where .= " and name like '{$s_content}%' ";
            }
        }
        $this->assign("s_type", $s_type);
        $this->assign("s_content", $s_content);

        $goods_id = Req::args("goods_id");
        if (is_array($goods_id)) {
            $goods_id = implode(',', $goods_id);
        }
        if ($goods_id)
            $where .= " and id not in($goods_id)";
        $id = Req::args('id');
        if (!$id || $id == '')
            $id = 0;
        $this->assign('id', $id);
        $this->assign('goods_id', $goods_id);
        $this->assign("where", $where);
        $this->redirect();
    }

    public function radio_goods_select() {
        $this->layout = "blank";
        $s_type = Req::args("s_type");
        $s_content = Req::args("s_content");
        $where = "1=1";
        if ($s_content && $s_content != '') {
            
            if ($s_type == 1) {
                $where = "goods_no like '%{$s_content}%'";
            } else if ($s_type == 2) {
                $where = "name like '%{$s_content}%' ";
            }else if($s_type==0){
                 $where = "goods_no like '%{$s_content}%' or name like '%{$s_content}%'";
            }
        }
        $this->assign("s_type", $s_type);
        $this->assign("s_content", $s_content);

        $id = Req::args('id');
        if (!$id || $id == '')
            $id = 0;
        $this->assign('id', $id);
        $this->assign("where", $where);
        $this->redirect();
    }
    
     public function multi_product_select() {
        $this->layout = "blank";
        $s_type = Req::args("s_type");
        $s_content = Req::args("s_content");
        $where = "1=1";
        if ($s_content && $s_content != '') {
            
            if ($s_type == 1) {
                $where = "g.goods_no like '%{$s_content}%'";
            } else if ($s_type == 2) {
                $where = "g.name like '%{$s_content}%' ";
            }else if($s_type==0){
                 $where = "g.goods_no like '%{$s_content}%' or g.name like '%{$s_content}%'";
            }
        }
        $this->assign("s_type", $s_type);
        $this->assign("s_content", $s_content);

        $id = Req::args('id');
        if (!$id || $id == '')
            $id = 0;
        $this->assign('id', $id);
        $this->assign("where", $where);
        $this->redirect();
    }


    public function prom_goods_save() {
        $group = Req::args("group");
        $id = Req::args('id');
        if (is_array($group)) {
            $group = implode(',', $group);
            Req::args("group", $group);
        } else {
            Req::args("group", '');
        }
        $model = new Model('prom_goods');
        if ($id) {
            $model->where("id=$id")->update();
            $last_id = $id;
            Log::op($this->manager['id'], "修改商品促销", "管理员[" . $this->manager['name'] . "]:修改了商品促销 " . Req::args('name'));
        } else {
            $last_id = $model->insert();
            Log::op($this->manager['id'], "添加商品促销", "管理员[" . $this->manager['name'] . "]:添加了商品促销 " . Req::args('name'));
        }
        $goods_id = Req::args("goods_id");

        $model->table("goods")->data(array('prom_id' => 0))->where("prom_id = $last_id")->update();
        if (is_array($goods_id)) {
            $goods_id = implode(',', $goods_id);
            $where = " id in($goods_id)";
            $model->table("goods")->data(array('prom_id' => $last_id))->where($where)->update();
        }
        $this->redirect("prom_goods_list");
    }

    public function prom_order_save() {
        $group = Req::args("group");
        if (is_array($group)) {
            $group = implode(',', $group);
            Req::args("group", $group);
        } else {
            Req::args("group", '');
        }
        $id = Req::args("id");
        $model = new Model('prom_order');
        if ($id) {
            $model->where("id=$id")->update();
            Log::op($this->manager['id'], "修改订单促销", "管理员[" . $this->manager['name'] . "]:修改了订单促销 " . Req::args('name'));
        } else {
            $model->where("id=$id")->insert();
            Log::op($this->manager['id'], "添加订单促销", "管理员[" . $this->manager['name'] . "]:添加了订单促销 " . Req::args('name'));
        }
        $this->redirect("prom_order_list");
    }

    public function prom_goods_list() {
        $parse_type = array('0' => '直接打折', '1' => '减价优惠', '2' => '固定金额出售', '3' => '买就赠优惠券', '4' => '买M件送N件');
        $this->assign("parse_type", $parse_type);
        $model = new Model('grade');
        $rows = $model->findAll();
        $grades = array(0 => '默认会员');
        foreach ($rows as $row) {
            $grades[$row['id']] = $row['name'];
        }
        $this->assign("grades", $grades);
        $this->redirect();
    }

    public function prom_goods_del() {
        $model = new Model();
        $id = Req::args("id");
        if ($id) {
            $model->table("goods")->data(array('prom_id' => 0))->where("prom_id = $id")->update();
            $obj = $model->table('prom_goods')->where("id = $id")->find();
            $model->table('prom_goods')->where("id = $id")->delete();
            if ($obj)
                Log::op($this->manager['id'], "删除商品促销", "管理员[" . $this->manager['name'] . "]:删除了商品促销 " . $obj['name']);
        }
        $this->redirect("prom_goods_list");
    }

    public function prom_order_del() {
        $model = new Model("prom_order");
        $id = Req::args("id");
        if ($id) {
            $obj = $model->where("id = $id")->find();
            $model->where("id = $id")->delete();
            if ($obj)
                Log::op($this->manager['id'], "删除订单促销", "管理员[" . $this->manager['name'] . "]:删除了订单促销 " . $obj['name']);
        }
        $this->redirect("prom_order_list");
    }
    
    public function prom_order_list() {
        $parse_type = array('0' => '满额打折', '1' => '满额优惠金额', '2' => '满额送倍数积分', '3' => '满额送优惠券', '4' => '满额免运费');
        $this->assign("parse_type", $parse_type);
        $model = new Model('grade');
        $rows = $model->findAll();
        $grades = array(0 => '默认会员');
        foreach ($rows as $row) {
            $grades[$row['id']] = $row['name'];
        }
        $this->assign("grades", $grades);
        $this->redirect();
    }

    public function goods_select() {
        $this->layout = "blank";
        $s_type = Req::args("s_type");
        $s_content = Req::args("s_content");
        $where = "";
        if ($s_content && $s_content != '') {
            if ($s_type == 1) {
                $where = " and goods_no = '{$s_content}'";
            } else if ($s_type == 2) {
                $where = " and name like '{$s_content}%' ";
            }
        }
        $this->assign("s_type", $s_type);
        $this->assign("s_content", $s_content);

        $goods_id = Req::args("goods_id");
        if (is_array($goods_id)) {
            $goods_id = implode(',', $goods_id);
            $where .= " and id not in($goods_id)";
        } else {
            $where .= "";
        }
        $id = Req::args('id');
        if (!$id || $id == '')
            $id = 0;
        $this->assign('id', $id);
        $this->assign("where", $where);
        $this->redirect();
    }

    public function goods_show() {
        $this->layout = "blank";
        $id = Req::args('id');
        $this->assign("id", $id);
        $this->redirect();
    }

    public function voucher_list() {
        $condition = Req::args('condition');
        $condition_str = Common::str2where($condition);
        if ($condition_str)
            $this->assign("where", $condition_str);
        else
            $this->assign("where", "1=1");
        $this->assign("condition", $condition);
        $parse_status = array(0 => '<b class="green">未使用</b>', 1 => '<b>已使用</b>', 2 => '<b class="red">临时锁定</b>', 3 => '<s class="red"><b>禁用</b></s>');
        $this->assign("parse_status", $parse_status);
        $this->redirect();
    }

    public function voucher_csv() {
        $fields_array = array(
            'id' => 'ID编号',
            'name' => '名称',
            'account' => '账号',
            'password' => '密码',
            'value' => '面值',
            'start_time' => '开始时间',
            'end_time' => '到期时间',
            'status' => '状态',
            'is_send' => '发放情况'
        );
        $heading = array();
        $condition = Req::args('condition');
        $fields = Req::args('fields');
        $fields_key = array();
        if (is_array($fields)) {
            foreach ($fields as $fied) {
                if (isset($fields_array[$fied])) {
                    $heading[] = $fields_array[$fied];
                    $fields_key[] = $fied;
                }
            }
        }
        $condition_str = Common::str2where($condition);
        if ($condition_str == null)
            $condition_str = " 1=1 ";
        if (empty($fields_key)) {
            $fields_key = array_keys($fields_array);
            $heading = array_values($fields_array);
        }
        $model = new Model('voucher');
        $fields_str = implode(',', $fields_key);
        $vouchers = $model->fields($fields_str)->where($condition_str)->findAll();
        Http::exportCSV($heading, $vouchers, "vouchers_" . date("Y_m_d"));
    }

    public function voucher_disabled() {
        $id = Req::args("id");
        $model = new Model('voucher');
        if (is_array($id)) {
            $ids = implode(',', $id);
            $model->data(array('status' => 3))->where("id in($ids)")->update();
        } elseif ($id) {
            $model->data(array('status' => 3))->where("id = $id")->update();
        }
        $this->redirect("voucher_list");
    }

    public function voucher_send() {
        $id = Req::args("id");
        $model = new Model('voucher');
        if (is_array($id)) {
            $ids = implode(',', $id);
            $model->data(array('is_send' => 1))->where("id in($ids)")->update();
        } elseif ($id) {
            $model->data(array('is_send' => 1))->where("id = $id")->update();
        }
        $this->redirect("voucher_list");
    }

    public function voucher_create() {
        $id = Req::args("id");
        $start_time = Req::args("start_time");
        $start_time = $start_time == null ? date("Y-m-d") : $start_time;
        $end_time = Req::args("end_time");
        $end_time = $end_time == null ? date("Y-m-d 23:59:59", strtotime("+30 days")) : date("Y-m-d 23:59:59", strtotime($end_time));

        $model = new Model('voucher_template');
        $voucher_template = $model->where("id = $id")->find();
        if ($voucher_template) {
            $voucher_model = new Model('voucher');
            $num = Req::args('num');
            $i = 0;
            while ($i < $num) {
                do {
                    $account = strtoupper(CHash::random(10, 'char'));
                    $password = strtoupper(CHash::random(10, 'char'));
                    $voucher_template['account'] = $account;
                    $voucher_template['password'] = $password;
                    $voucher_template['start_time'] = $start_time;
                    $voucher_template['end_time'] = $end_time;
                    $obj = $voucher_model->where("account = '$account'")->find();
                } while ($obj);
                unset($voucher_template['id'], $voucher_template['point']);
                $voucher_model->data($voucher_template)->insert();
                $i++;
            }
        }
        echo JSON::encode(array('status' => 'success', 'msg' => '已成功生成[' . $voucher_template['name'] . ']代金券(' . $num . ')张'));
    }

    public function voucher_template_validator() {
        $rules = array('name:required:模板名称不能为空!', 'value:float:面值必需是数据型格式!', 'point:int:积分必需为整数！', 'money:int:需满足的消费金额必需为整数！');
        $info = Validator::check($rules);
        return $info;
    }
    public function recharge_activity_list(){
        $this->redirect();
    }
    public function recharge_activity_edit(){
        $model = new Model("recharge_activity");
        $info = $model->where("id=1")->find();
        $this->assign("activity",$info);
        $this->redirect();
    }
    public function recharge_activity_save(){
        
        $recharge_type = Req::args("recharge_type");
        $start_time = Req::args("start_time");
        $end_time = Req::args("end_time");
        $accept_end_time =Req::args("accept_end_time");
        $limit_money = Req::args("limit_money");
        $send_present = Req::args("send_present");
        $set = array();
        if(is_array($send_present)&&  is_array($limit_money)){
            $arr = array();
            foreach($limit_money as $k =>$v){
                if(is_numeric($v)){
                    $arr['recharge_amount_limit']=$v;
                    $arr['present']=$send_present[$k];
                }else{
                     $msg = array('fail', '保存失败，提交数据错误');
                    break;
                }
                $set[]=$arr;
            }
            if(strtotime($start_time)<strtotime($end_time) && strtotime($end_time)>time() && !empty($set)){
            $model = new Model('recharge_activity');
            $result = $model->data(array("recharge_type"=>$recharge_type,'start_time'=>$start_time,'end_time'=>$end_time,'accept_end_time'=>$accept_end_time,"set"=>  serialize($set),'status'=>0))
                    ->where("id=1")->update();
            if($result){
                $msg = array('success', '保存成功');
            }else{
                $msg = array('fail', '保存失败');
            }
        }else{
            $msg = array('fail', '保存失败，提交数据错误');
        }
        }else{
            $msg = array('fail', '保存失败，提交数据错误');
        }
        
        $this->redirect("recharge_activity_edit",true,array('msg' => $msg));
    }
    public function recharge_activity_status(){
        $status = Req::args('status');
        $model= new Model("recharge_activity");
        $result = $model->data(array('status'=>$status))->where("id=1")->update();
        if($result){
            exit(array("status"=>'success','msg'=>'success'));
        }else{
            exit(array("status"=>'fail','msg'=>'数据库错误，更新失败'));
        }
    }
    public function recharge_activity_customer(){
        $this->layout="blank";
        $this->redirect();
    }
    public function recharge_activity_send(){
        $id = Req::args('id');
        //删除
        if (is_array($id)) {
            $ids = implode(",", $id);
        } else {
            $ids = $id;
        }
        $model = new Model();
        $result = $model->table("recharge_presentlog")->where("id in ($ids)")->data(array("status"=>2))->update();
        if($result){
            exit(json_encode(array("status"=>'success','msg'=>"成功")));
        }else{
             exit(json_encode(array("status"=>'fail','msg'=>"状态改变失败")));
        }
    }
    public function recharge_package_set(){
        $group = "recharge_package_set";
        $config = Config::getInstance();
        if (Req::args('submit') != null) {
            $configService = new ConfigService($config);
            if (method_exists($configService, $group)) {
                $result = $configService->$group();
                if (is_array($result)) {
                    $this->assign('message', $result['msg']);
                } else if ($result == true) {
                    $this->assign('message', '信息保存成功！');
                }
                //清除opcache缓存
                if (extension_loaded('opcache')) {
                    opcache_reset();
                }
                Log::op($this->manager['id'], "修改充值套餐配置", "管理员[" . $this->manager['name'] . "]:修改了充值套餐配置 ");
            }
        }
        $this->assign('package', $config->get($group));
        $this->redirect();
    }
    /*
     * 充银点送银点活动配置
     */
    public function recharge_activity_2_set(){
        $start_time = Filter::str(Req::args('start_time'));
        $end_time = Filter::str(Req::args('end_time'));
        $min_amount = Filter::float(Req::args('min_amount'));
        $max_amount = Filter::float(Req::args('max_amount'));
        $limit = Filter::int(Req::args("limit"));
        if(strtotime($start_time)>  strtotime($end_time)||$min_amount>$max_amount){
            exit(json_encode(array('status'=>'fail','msg'=>'配置信息错误，请重新配置')));
        }else if(strtotime($end_time)>time()){
            $data['status']=0;
        }else if(strtotime($end_time)<time()){
            $data['status']=1;
        }
        $model = new Model("recharge_activity");
        $data['start_time']=$start_time;
        $data['end_time']=$end_time;
        $data['set']=  serialize(array('min'=>$min_amount,'max'=>$max_amount,'limit'=>$limit));
        $result = $model->data($data)->where("id = 2 or name = '充银点送银点'")->update();
        if($result){
            exit(json_encode(array('status'=>'success','msg'=>'配置成功')));
        }else{
            exit(json_encode(array('status'=>'fail','msg'=>'操作失败，请重新配置')));
        }
    }
    
    /*
     * 积分购
     */
    public function point_sale_list(){
        $condition = Req::args("condition");
        $condition_str = Common::str2where($condition);
        if ($condition_str)
            $this->assign("where", $condition_str);
        else {
            $this->assign("where", "1=1");
        }
        
        $shop_model = new Model("shop");
        $shoplist = $shop_model->where("1=1")->findAll();
        $category_model = new Model("goods_category");
        $categorylist = $category_model->where("1=1")->findAll();
        $shopidlist = $categoryidlist = array();
        foreach ($shoplist as $k => $v) {
            $shopidlist[$v['id']] = $v['name'];
        }
        foreach ($categorylist as $k => $v) {
            $categoryidlist[$v['id']] = $v['name'];
        }
        $this->assign("condition",$condition);
        $this->assign("shoplist", $shoplist);
        $this->assign("categorylist", $categorylist);
        $this->redirect();
    }
    /*
     * 积分购编辑
     */
    public function point_sale_set(){
        $this->layout="blank";
        $id = Req::args("id");
        $model = new Model();
        $point_sale_info  = $model->table('point_sale')->where("id = $id")->find();
        if(isset($point_sale_info['price_set'])&&$point_sale_info['price_set']!=NULL){
            $set = unserialize($point_sale_info['price_set']);
            if(is_array($set)){
                $point_sale_info['price_set'] = $set;
            }else{
                $point_sale_info['price_set'] = array();
            }
        }
        $products = $model->table('products')->fields("sell_price,market_price,cost_price,store_nums,pro_no,spec,id")->where("goods_id = ".$point_sale_info['goods_id'])->findAll();
        foreach ($products as $k => $v){
            $spec = unserialize($v['spec']);
            if(!empty($spec)){
                $spec_str = "";
                foreach($spec as $kk=>$vv){
                    $spec_str .= $vv['name'].":".$vv['value'][1]." ";
                }
                $products[$k]['spec']= $spec_str;
            }else{
                 $products[$k]['spec']= "无规格信息";
            }
        }
        
        $this->assign('products',$products);
        $this->assign('point_sale_info',$point_sale_info);
        $this->redirect();
    }
    /*
     * 积分购保存
     */
    public function point_sale_save(){
        $id = Filter::int(Req::args('id'));
        $cash = Req::args('cash');
        $point = Req::args('point');
        $model = new Model();
        $point_sale_info = $model->table("point_sale")->where("id=$id")->find();
        if($point_sale_info){
            $set = array();
            $flag = false;
            if(is_array($cash)&&  is_array($point)){
                foreach ($cash as $k=>$v){
                    if(!(preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $v))){
                        $flag=true;
                    }
                    $set[$k]=array("cash"=>$v,"point"=>$point[$k]);
                }
                foreach ($point as $k=>$v){
                     if(!(preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $v))){
                        $flag=true;
                    }
                }
            }
            if($flag){
                  exit(json_encode(array('status'=>'fail','msg'=>'参数错误，必须是数字')));
            }
            if($set){
                $result= $model->table("point_sale")->where("id=$id")->data(array("price_set"=>  serialize($set),"status"=>1))->update();
                if($result){
                     exit(json_encode(array('status'=>'success','msg'=>'配置成功')));
                }else{
                     exit(json_encode(array('status'=>'fail','msg'=>'配置失败咯')));
                }
            }
        }else{
             exit(json_encode(array('status'=>'fail','msg'=>'参数错误')));
        }
    }
    public function point_sale_add(){
        $goods_id = Filter::int(Req::args('goods_id'));
        $model =new Model();
        $isset = $model->table('point_sale')->where("goods_id=".$goods_id)->find();
        if($isset){
            exit(json_encode(array('status'=>'fail','msg'=>'该商品已经添加了积分购')));
        }else{
           $id = $model->table("point_sale")->data(array("goods_id"=>$goods_id,"status"=>0,'is_adjustable'=>0,'listorder'=>0))->insert();
           if($id){
               exit(json_encode(array('status'=>'success','id'=>$id)));
           }else{
               exit(json_encode(array('status'=>'fail','msg'=>'操作失败，请重试')));
           }
        }
    }
    public function point_sale_edit(){
        $id = Filter::int(Req::args('id'));
        $action = Filter::str(Req::args('action'));
        $value = Filter::int(Req::args("value"));
        $model =new Model("point_sale");
        $isset = $model->where("id=".$id)->find();
        if($isset){
            if($action=='delete'){
                $result = $model->where("id=$id")->delete();
                if($result){
                    exit(json_encode(array('status'=>'success')));
                }else{
                    exit(json_encode(array('status'=>'fail','msg'=>'操作失败，请重试')));
                }
            }else if($action=="list"){
                $result = $model->where("id=$id")->data(array("listorder"=>$value))->update();
                if($result){
                    exit(json_encode(array('status'=>'success')));
                }else{
                    exit(json_encode(array('status'=>'fail','msg'=>'操作失败，请重试')));
                }
            }
        }
          exit(json_encode(array('status'=>'fail','msg'=>'操作失败，请重试')));
    }
    
    public function pointflash_sale_list(){
        $this->redirect();
    }
    
    /*
     * 积分购抢购添加
     */
    public function pointflash_sale_set(){
        $this->layout="blank";
        $id = Filter::int(Req::args("id"));
        $type = Filter::str(Req::args("type"));
        
        $model = new Model();
        if($type=='add'){
            $products = $model->table('products')->fields("sell_price,market_price,cost_price,store_nums,pro_no,spec,id")->where("goods_id = ".$id)->findAll();
        }else if($type=="edit"){
            $pointflash = $model->table("pointflash_sale")->where("id=$id")->find();
            if($pointflash){
                $products = $model->table('products')->fields("sell_price,market_price,cost_price,store_nums,pro_no,spec,id")->where("goods_id = ".$pointflash['goods_id'])->findAll();
                if(isset($pointflash['price_set'])&&$pointflash['price_set']!=NULL){
                    $set = unserialize($pointflash['price_set']);
                    if(is_array($set)){
                        $pointflash['price_set'] = $set;
                    }else{
                        $pointflash['price_set'] = array();
                    }
                }
                $this->assign("pointflash",$pointflash);
            }else{
                exit();
            }
        }
        foreach ($products as $k => $v){
            $spec = unserialize($v['spec']);
            if(!empty($spec)){
                $spec_str = "";
                foreach($spec as $kk=>$vv){
                    $spec_str .= $vv['name'].":".$vv['value'][1]." ";
                }
                $products[$k]['spec']= $spec_str;
            }else{
                 $products[$k]['spec']= "无规格信息";
            }
        }
        $this->assign("id",$id);
        $this->assign("type",$type);
        $this->assign('products',$products);
        $this->redirect();
    }
    
    
    public function pointflash_sale_save(){
        $id = Filter::int(Req::args("id"));
        $type = Filter::str(Req::args("type"));
        $title = Filter::str(Req::args('title'));
        $start_date = Filter::str(Req::args("start_date"));
        $end_date = Filter::str(Req::args("end_date"));
        $max_sell_count = Filter::int(Req::args("max_sell_count"));
        $quota_count = Filter::int(Req::args("quota_count"));
        $cash = Req::args('cash');
        $point = Req::args('point');
        
        if(strtotime($start_date)>strtotime($end_date)){
            exit(json_encode(array("status"=>'fail','msg'=>"结束时间小于开始时间")));
        }else if($max_sell_count<1){
            exit(json_encode(array("status"=>'fail','msg'=>"参加抢购活动商品数量不能小于1")));
        }
        $model = new Model();
        $set = array();
        $flag = false;
        if(is_array($cash)&&  is_array($point)){
            foreach ($cash as $k=>$v){
                if(!(preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $v))){
                    $flag=true;
                }
                $set[$k]=array("cash"=>$v,"point"=>$point[$k]);
            }
            foreach ($point as $k=>$v){
                 if(!(preg_match('/^[0-9]+(.[0-9]{1,2})?$/', $v))){
                    $flag=true;
                }
            }
        }
        if($flag){
              exit(json_encode(array('status'=>'fail','msg'=>'配置参数错误，必须是数字')));
        }
        if($set){
            $data['title']=$title;
            $data['max_sell_count']=$max_sell_count;
            $data['quota_count']=$quota_count;
            $data['start_date']=$start_date;
            $data['end_date']=$end_date;
            if(time()>  strtotime($end_date)){
                $data['is_end']=1;
            }
            $data['price_set']=  serialize($set);
            if($type=="add"){
               $isset = $model->table("pointflash_sale")->where("goods_id=".$id." and is_end=0")->find();
               if($isset){
                   exit(json_encode(array('status'=>'fail','msg'=>'该商品正在抢购中')));
               }
               if(!isset($data['is_end'])){
                   $data['is_end']=0;
               }
               $data['order_count']=0;
               $data['goods_id']=$id;
               $result = $model->table("pointflash_sale")->data($data)->insert();
                if($result){
                     exit(json_encode(array('status'=>'success','msg'=>'新增成功')));
                }else{
                     exit(json_encode(array('status'=>'fail','msg'=>'新增失败')));
                }
            }else if($type=="edit"){
                $isset = $model->table("pointflash_sale")->where("id=$id")->find();
                if($isset){
                    if($isset['is_end']==1){
                         exit(json_encode(array('status'=>'fail','msg'=>'此抢购已经结束，不能再编辑'))); 
                    }else{
                        $result = $model->table("pointflash_sale")->data($data)->where("id=$id")->update();
                        if($result){
                             exit(json_encode(array('status'=>'success','msg'=>'修改成功')));
                        }else{
                             exit(json_encode(array('status'=>'fail','msg'=>'修改失败')));
                        }
                    }
                }else{
                    exit(json_encode(array('status'=>'fail','msg'=>'编辑的抢购不存在'))); 
                }
            }
        }else{
            exit(json_encode(array('status'=>'fail','msg'=>'参数错误'))); 
        }
    }
    
    //签到设置
    public function sign_in_set(){
        $group = "sign_in_set";
        $config = Config::getInstance();
        if (Req::args('submit') != null) {
                $configService = new ConfigService($config);
                if (method_exists($configService, $group)) {
                    $result = $configService->$group();
                    if (is_array($result)) {
                        $this->assign('message', $result['msg']);
                    } else if ($result == true) {
                        $this->assign('message', '信息保存成功！');
                    }
                    //清除opcache缓存
                    if (extension_loaded('opcache')) {
                        opcache_reset();
                    }
                    Log::op($this->manager['id'], "修改签到配置", "管理员[" . $this->manager['name'] . "]:修改了签到配置 ");
                }
        }
        $this->assign('data', $config->get($group));
        $this->redirect();
    }
    
    //分红
    public function bonus(){
        
        $this->redirect();
    }
    
    public function redbag_list(){
        $this->assign("open_status", array('0' => '未开启', '1' => '已发放', '2' => '已打开'));
        $this->redirect();
    }

    //提交分红
    public function post_bonus(){
        $bonus = Filter::float(Req::args('bonus'));
        $explanation = Filter::str(Req::args('explanation'));
        // $beneficiary_num = Filter::str(Req::args('beneficiary_num'));
        $bonus_max = Filter::str(Req::args('bonus_max'));
        $bonus_min = Filter::str(Req::args('bonus_min'));
        if($bonus<=0){
            exit(json_encode(array("status"=>'fail','msg'=>"分红金额错误")));
        }
        $model = new Model();
        $financial_coin_count = $model->table("customer")->fields("SUM(`financial_stock`) as count")->where("financial_stock > 0")->findAll();
        $financial_coin_count = $financial_coin_count[0]['count']==NULL?0:$financial_coin_count[0]['count'];
        if($financial_coin_count==0){
            exit(json_encode(array("status"=>'fail','msg'=>"没有人拥有分红股")));
        }
        $bonus_data = $model->table("customer")->where("financial_stock > 0")->fields("financial_coin,user_id,financial_stock")->findAll();
        if($bonus_data){
            $beneficiary_num = 0;
            $has_bonus_count = 0;
            $order_no = "B".date("YmdHis").rand(100, 999);
            foreach ($bonus_data as $k=>$v){
                 $need_add = round($bonus*$v['financial_stock']/$financial_coin_count,4);
                 // var_dump($need_add);die;
                 $need_add = sprintf("%.2f",substr(sprintf("%.4f", $need_add), 0, -2));
                 if($need_add<=0){
                     continue;
                 }
                 // var_dump($need_add);die;
                 $result = $model->table('customer')->data(array('balance'=>"`balance`+{$need_add}"))->where("user_id =".$v['user_id'])->update();
                 if($result){
                     $beneficiary_num ++;
                     $has_bonus_count += $need_add;
                     Log::balance($need_add, $v['user_id'], $order_no, $explanation,7,$this->manager['id']);
                 }
            }
            if($beneficiary_num>0&&$has_bonus_count>0){
                // $max_bonus = $model->table("balance_log")->fields("amount as max")->where("order_no ='{$order_no}' and type =9 ")->order("amount desc")->find();
                // $min_bonus = $model->table("balance_log")->fields("amount as min")->where("order_no ='{$order_no}' and type =9 ")->order("amount asc")->find();
                $model->table("bonus")->data(array("explanation"=>$explanation,"bonus"=>$bonus,'date'=>date("Y-m-d H:i:s"),'real_bonus'=>$has_bonus_count,'beneficiary_num'=>$beneficiary_num,'max_bonus'=>$bonus_max,'min_bonus'=>$bonus_min))
                      ->insert();
                exit(json_encode(array("status"=>'success','msg'=>"成功")));
            }else{
                exit(json_encode(array("status"=>'fail','msg'=>"分红失败，请跳转分红金额")));
            }
        }
    }
    
    //获取预估数据
    public function getCalculateData(){
        if($this->is_ajax_request()){
            $model = new Model();
            $beneficiary_num = $model->table("customer")->where("financial_coin > 0")->count();
            $financial_coin_count = $model->table("customer")->fields("SUM(`financial_coin`) as count")->where("financial_coin > 0")->findAll();
            $financial_coin_count = $financial_coin_count[0]['count']==NULL?0:$financial_coin_count[0]['count'];
            $financial_coin_max = $model->table("customer")->fields("financial_coin as max")->where("financial_coin > 0")->order("financial_coin desc")->find();
            $financial_coin_min = $model->table("customer")->fields("financial_coin as min")->where("financial_coin > 0")->order("financial_coin asc")->find();
            $financial_coin_max = empty($financial_coin_max)?0:$financial_coin_max['max'];
            $financial_coin_min = empty($financial_coin_min)?0:$financial_coin_min['min'];
            exit(json_encode(array('status'=>'success','beneficiary_num'=>$beneficiary_num,'count'=>(int)$financial_coin_count,'max'=>$financial_coin_max,'min'=>$financial_coin_min)));
        }
    }

    public function redbag_edit(){
         $id = Req::args("id");

        $promoter = Req::args();
        if ($id) {
            $model = new Model("redbag as d");
            $redbag = $model->join("customer as c on c.user_id = d.user_id")->fields('d.*,c.real_name')->where("d.id=" . $id)->find();
        }
        $this->assign('redbag',$redbag);
        $this->redirect();
    }

    public function redbag_save(){
        $id = Req::args("id");
        $location = Req::args("location");
        $info = Req::args("info");
        $distance = Req::args("distance");
        $model = new Model("redbag");
        $lnglat = Common::getLnglat($location);
        $lng = $lnglat['lng'];
        $lat = $lnglat['lat'];
        if($id){
            $redbag=$model->where('id='.$id)->find();
            if($redbag){
                
                    $model->data(array('location'=>$location,'info'=>$info,'distance'=>$distance,'lng'=>$lng,'lat'=>$lat))->where('id='.$id)->update();
                    Log::op($this->manager['id'], "修改红包", "管理员[" . $this->manager['name'] . "]:修改了红包[id] " . $id . " 的信息");
                
            }
        }
        $this->redirect('redbag_list');
    }

    public function index_notice(){
        $model = new Model('index_notice');
        $notice = $model->where('id=1')->find();
        $this->assign('notice', $notice);
        $this->redirect();
    }

    public function index_notice_save(){
        $title = Req::args('title');
        $content = Req::args('content');
        $is_disply = Req::args('is_disply');
        $model = new Model('index_notice');
        $model->data(array('title'=>$title,'content'=>$content,'is_disply'=>$is_disply,'date'=>date('Y-m-d H:i:s')))->where('id=1')->update();
        $this->redirect('index_notice');
    }
    //优惠券
    public function discount_edit()
    {
        $model = new Model("discount");
        $id = Req::args("id");
        if ($id){
            $res = $model->where("id=".$id)->find();
            $this->assign('list',$res);
        }
        $this->redirect();
    }

    //优惠券列表
    public function discount_list()
    {
        $this->redirect();
    }
    //优惠券操作
    public function discount_save()
    {
        $id = Req::args('id');
        $discount_name = Req::args('discount_name');
        $face_value = Req::args('face_value');
        $monetary = Req::args('monetary');
        $start_time = Req::args('start_time');
        $end_time = Req::args('end_time');
        $suit_person = Req::args('suit_person');
        $model = new Model('discount');
        if ($id) {
            $result = $model->data(array('discount_name' => $discount_name, 'face_value' => $face_value, 'monetary' => $monetary, 'start_time' => $start_time, 'end_time' => $end_time, 'update_time' => time()))->where("id=" . $id)->update();
            if ($result) {
                $msg = array('status' => 'fail', 'msg' => '更新成功');
            } else {
                $msg = array('status' => 'fail', 'msg' => '参数错误');
            }
        } else {
            $result = $model->data(array('discount_name' => $discount_name, 'face_value' => $face_value, 'monetary' => $monetary, 'start_time' => strtotime($start_time), 'end_time' => strtotime($end_time), 'suit_person' => $suit_person, 'inputtime' => time()))->insert();
            if ($result) {
                $msg = array('status' => 'success', 'msg' => '写入成功');
            } else {
                $msg = array('status' => 'fail', 'msg' => '参数错误');
            }
        }
        $this->redirect("discount_list", true, array('msg' => $msg));
    }
    //删除优惠券
    public function discount_del()
    {
        $model = new Model("discount");
        $id = Req::args("id");
        if (is_array($id)) {
            $where = 'id in (' . implode(",", $id) . ')';
        } else {
            $where = 'id='.$id;
        }
        $model->where($where)->delete();
        $this->redirect("discount_list");
    }
    //发放优惠券
    public function discount_put()
    {
        $model = new Model("discount");
        $id = Req::args("id");
        if ($id) {
            $model->where("id=".$id)->data(array("is_put_out"=>1))->update();
        }
        $this->redirect("discount_list");
    }

    public function travel_way()
    {
        $this->redirect();
    }

    public function way_edit()
    {
        $id = Req::args("id");

        if ($id) {
            $model = new Model("travel_way");
            $travel_way = $model->fields('*')->where("id=" . $id)->find();
            $this->assign('travel_way',$travel_way);
        }
        
        $this->redirect();
    }

    public function travel_way_save() {
        $id = Req::args("id");
        $model = new Model('travel_way');
        if ($id) {
            $model->where("id=$id")->update();
            Log::op($this->manager['id'], "修改旅游路线", "管理员[" . $this->manager['name'] . "]:修改了旅游路线 " . Req::args('name'));
        } else {
            $model->where("id=$id")->insert();
            Log::op($this->manager['id'], "添加旅游路线", "管理员[" . $this->manager['name'] . "]:添加了旅游路线 " . Req::args('name'));
        }
        $this->redirect("travel_way");
    }

    public function travel_order()
    {
        $this->redirect();
    }

    public function travel_order_detail()
    {
        $this->layout = "blank";
        $id = Req::args("id");
        $model = new Model("travel_order");
        if($id){
            $order = $model->where("id=$id")->find();
        }
        
        if ($order) {
            $this->assign("id", $order['id']);
            $this->assign("way_id", $order['way_id']);
            $this->redirect();
        }
    }

    public function active_voucher()
    {
        $this->redirect();
    }

    public function voucher_del() {
        $model = new Model("active_voucher");
        $id = Req::args("id");
        if (is_array($id)) {
            $where = 'id in (' . implode(",", $id) . ')';
        } else {
            $where = 'id='.$id;
        }
        $model->where($where)->delete();
        $this->redirect("active_voucher");
    }

    public function change_voucher_status()
    {
        $id = Req::args("id");
        $model = new Model();
        $model->table('active_voucher')->data(['status'=>0])->where('id='.$id)->update();
        exit(json_encode(array('status' => 'success', 'msg' => '成功')));
    }

    public function groupbuy_active_list()
    {
        $page = Filter::int(Req::args('page'));
        if(!$page) {
            $page = 1;
        }
        $model = new Model();
        $list = $model->table('order as o')->fields('gl.id as log_id,gl.join_id,gl.groupbuy_id as id,go.name,go.img,g.min_num,g.price,gj.end_time,gj.status,o.id as order_id,og.product_id,gl.status as gl_status,u.nickname,g.title,gl.join_time')->join('left join groupbuy_log as gl on o.join_id=gl.id left join order_goods as og on o.id=og.order_id left join groupbuy as g on gl.groupbuy_id=g.id left join goods as go on g.goods_id=go.id left join groupbuy_join as gj on gl.join_id=gj.id left join user as u on o.user_id=u.id')->where('gl.pay_status in (1,3) and o.pay_status in (1,3)')->order('id desc')->findPage($page,10);
        if($list) {
            if($list['data']!=null) {
                foreach ($list['data'] as $k => $v) {
                    $had_join_num = $model->table('groupbuy_log')->where('join_id='.$v['join_id'].' and pay_status=1')->count();
                        if($had_join_num>=$v['min_num']) {
                            $list['data'][$k]['join_status'] = '拼团成功';
                        } elseif ($had_join_num<$v['min_num'] && time()>=strtotime($v['end_time'])) {
                            $list['data'][$k]['join_status'] = '拼团失败';
                        } elseif ($had_join_num<$v['min_num'] && time()<strtotime($v['end_time'])) {
                            $list['data'][$k]['join_status'] = '拼团中';
                        } elseif ($v['gl_status']==3 && time()>strtotime($v['end_time'])) {
                            $list['data'][$k]['join_status'] = '已退款';
                        } else {
                            $list['data'][$k]['join_status'] = '拼团中';
                        }
                }
            }
        }
        $this->assign('list',$list);
        $this->redirect();
    }
}
