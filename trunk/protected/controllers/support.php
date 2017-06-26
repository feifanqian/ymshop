<?php

class SupportController extends Controller {

    public $layout = 'admin';
    private $top = null;
    private $manager = null;
    private $parse_returns_status = array(0 => '<b class="red">等待审核</b>', 1 => '<b class="red">等待回寄货品</b>', 2 => '<b class="red">拒绝</b>', 3 => '货品回寄中', 4 => '已结束');
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
        $this->manager = $this->safebox->get('manager');
        $this->assign('manager', $this->manager);
        $this->assign('upyuncfg', Config::getInstance()->get("upyun"));

        $currentNode = $menu->currentNode();
        if (isset($currentNode['name']))
            $this->assign('admin_title', $currentNode['name']);
    }

    public function noRight() {
        $this->layout = '';
        $this->redirect("admin/noright");
    }


    public function apply_list() {
        $condition = Req::args("condition");
        $condition_str = Common::str2where($condition);
        if ($condition_str)
            $this->assign("where", $condition_str);
        else {
             $this->assign("where", "1=1");
        }
        $this->assign("condition", $condition);
        $this->assign("type", array('1' => '<span class="red">退货退款</span>', '2' => '<span style="color:blue">换货</span>','2' => '<span class="green">维修</span>', ));
        $this->assign("status", array('-1' => '<span class="red">已拒绝请求</span>', '0' => '<span class="green">未处理请求</span>','1' => '<span style="color:blue">已处理，等待后续工作</span>','2' => '<span class="gray">已完成售后</span>', ));
        $this->redirect();
    }



    public function detail_view() {
        $this->layout = "blank";
        $id = Req::args("id");
        $model = new Model("sale_support");
        $info = $model->where("id=$id")->find();
        
        if ($info) {
            $order = new Model('order');
            $orderinfo = $order->where("order_no='".$info['order_no']."'")->find();
            $this->assign("order_id",$orderinfo['id']);
            $this->assign("order_goods_id",$info['order_goods_id']);//出入需要售后的商品
            $this->assign("type", array('1' => '<span class="red">退货退款</span>', '2' => '<span class="red">换货</span>','2' => '<span class="red">维修</span>', ));
            $this->assign('province',$info['province']);
            $this->assign('city',$info['city']);
            $this->assign('county',$info['county']);
            $this->assign("id", $id);
            $this->redirect();
        }
    }


    public function support_status() {
        $id = Filter::int(Req::args("id"));
        $status = Req::args("status");
        $status_info =array('-1'=>'拒绝了售后请求','1'=>'通过了售后请求','2'=>'完成了售后请求');
        $model = new Model("sale_support");
        $result1 = $model->query("update tiny_sale_support set status='$status' where id=$id");
        $support_info = $model->where("id=$id")->find();
        $og = new Model("order_goods");
        switch($status){
            case '-1': $support_status =2;break;
            case  '1': $support_status =1;break;
            case  '2': $support_status =2;break;
            default :break;
        }
        $result2=$og->where("id=".$support_info['order_goods_id'])->data(array('support_status'=>$support_status))->update();
        if($result1!='' && $result2!=''){
             $info = array('status' => 'success', 'msg' => '状态更新');
             Log::op($this->manager['id'], "更新售后状态", "管理员[" . $this->manager['name'] . "]:".$status_info[$status]."[售后请求：$id]");
             echo JSON::encode($info);
        }else{
            $info = array('status' => 'fail', 'msg' => '状态更新');
             echo JSON::encode($info);
        }
}
  public function support_del() {
        $id = Req::args('id');
        //删除
        if (is_array($id)) {
            $ids = implode(",", $id);
        } else {
            $ids = $id;
}
        $model = new Model("sale_support");
        //删除售后信息
        $flag = $model->where("id in ($ids)")->delete();
        //记录操作日志
        if ($flag) {
            Log::op($this->manager['id'], "删除售后信息", "管理员[" . $this->manager['name'] . "]:删除了售后信息【id=$id】 ");
            $msg = array('success', '成功删除了售后信息');
            $this->redirect("apply_list", false, array('msg' => $msg));
        } else
            $this->redirect("apply_list", false);
    }
}
