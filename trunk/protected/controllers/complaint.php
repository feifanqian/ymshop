<?php

class ComplaintController extends Controller {

    public $layout = 'admin';
    private $top = null;
    private $manager = null;
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

    public function complaint_list() {
        $condition = Req::args("condition");
        $condition_str = Common::str2where($condition);
        if ($condition_str)
            $this->assign("where", $condition_str);
        else {
            $this->assign("where", "1=1");
        }
        $this->assign("condition", $condition);
        $this->assign("type", array('1' => '<span class="red">商品投诉</span>', '2' => '<span class="red">物流投诉</span>','2' => '<span class="red">其他</span>', ));
        $this->assign("status", array('0' => "<span style='color:green'>未处理</span>", '1' =>"<span style='color:blue'>受理中</span>",'2'=>"<span style='color:gray'>已完成整改</span>"));
        $this->redirect();
    }

    public function feedback_list() {
        $condition = Req::args("condition");
        $condition_str = Common::str2where($condition);
        if ($condition_str)
            $this->assign("where", $condition_str);
        else {
            $this->assign("where", "c.category_id=4");
        }
        $this->assign("condition", $condition);
        $this->assign("status", array('0' => "<span style='color:green'>已处理</span>", '1' =>"<span style='color:blue'>受理中</span>",'2'=>"<span style='color:gray'>已完成整改</span>"));
        $this->redirect();
    }

    public function change_status() {
        $id = Filter::int(Req::args("id"));
        $status = Req::args("status");
        $status_info = array('1'=>'受理中','2'=>'已完成整改');
        $model = new Model("complaint");
        $result = $model->where("id=".$id)->data(array('status'=>$status))->update();
        if($result){
            $info = array('status' => 'success', 'msg' => '状态更新');
            Log::op($this->manager['id'], "处理投诉", "管理员[" . $this->manager['name'] . "]:将id=$id的投诉标记为$status_info[$status]");
             echo JSON::encode($info);
        }else{
            $info = array('status' => 'fail', 'msg' => '状态更新');
             echo JSON::encode($info);
    }
  }
}
