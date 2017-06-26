<?php

class WxmanagerController extends Controller {

    public $layout = 'admin';
    public $needRightActions = array('*' => true);
    private $wechat;

    public function init() {
        $menu = new Menu();
        $this->assign('mainMenu', $menu->getMenu());
        $menu_index = $menu->current_menu();
        $this->assign('menu_index', $menu_index);
        $this->assign('subMenu', $menu->getSubMenu($menu_index['menu']));
        $this->assign('menu', $menu);
        $nav_act = Req::get('act') == null ? $this->defaultAction : Req::get('act');
        $nav_act = preg_replace("/(_edit)$/", "_list", $nav_act);
        if ($nav_act == 'menu')
            $nav_act = 'wx_public_list';
        $this->assign('nav_link', '/' . Req::get('con') . '/' . $nav_act);
        $this->assign('node_index', $menu->currentNode());
        $this->safebox = Safebox::getInstance();
        $this->manager = $this->safebox->get('manager');
        $this->assign('manager', $this->safebox->get('manager'));
        $this->assign('upyuncfg', Config::getInstance()->get("upyun"));
        
        $currentNode = $menu->currentNode();
        if (isset($currentNode['name']))
            $this->assign('admin_title', $currentNode['name']);
    }

    public function index() {
        $wx = $this->wechat;
        $echostr = Req::args('echostr');
        if ($echostr)
            $wx->checkSign();
    }

    //菜单管理
    public function menu() {
        $id = Filter::int(Req::args('id'));
        if ($id > 0) {
            $model = new Model('wx_public');
            $obj = $model->where('id=' . $id)->find();
            if ($obj) {
                if ($obj['menus'] != null) {
                    $menus = $obj['menus'];
                } else {
                    $menus = '{"button":[]}';
                }
                $this->assign('id', $id);
                $this->assign('menus', $menus);
                $this->assign('name', $obj['name']);
                $responselist = array();
                $model = new Model('wx_response');
                $all = $model->fields("title,event_key")->findAll();
                foreach ($all as $k => $v) {
                    $responselist[$v['event_key']] = $v['title'];
                }
                $this->assign('responselist', json_encode($responselist));
                $this->redirect();
                exit;
            }
        }
        $this->redirect('wx_public_list');
    }

    //自动保存微信公众号、服务号
    public function menu_update() {
        $id = Filter::int(Req::args('id'));
        $json = Req::args('json');
        $info = array('status' => 'error', '更新失败！');
        if ($id > 0) {
            $model = new Model('wx_public');
            $model->data(array('menus' => $json))->where('id=' . $id)->update();
            $info = array('status' => 'success', '更新更新！');
        }
        echo JSON::encode($info);
    }

    //同步更新微信菜单
    public function menu_syn() {
        $info = array('status' => 'error', 'msg' => '同步的菜单不存在！');
        $id = Filter::int(Req::args('id'));
        $wx_model = new Model('wx_public');
        $wx_obj = $wx_model->where("id=$id")->find();
        if ($wx_obj) {
            $wechatMenu = new WechatMenu($wx_obj['app_id'], $wx_obj['app_secret'], $wx_obj['token']);
            if ($id > 0) {
                $model = new Model('wx_public');
                $obj = $model->where('id=' . $id)->find();
                if ($obj) {
                    if ($obj['menus'] != null) {
                        $menus = $obj['menus'];
                    } else {
                        $menus = '{"button":[]}';
                    }
                    $info = $wechatMenu->commitMenu($menus);
                }
            }
        }
        echo JSON::encode($info);
    }

    public function wx_public_validator() {
        $token = Filter::sql(Req::args('token'));
        $id = Filter::int(Req::args('id'));
        $model = new Model('wx_public');
        $obj = $model->where("token='$token'")->find();
        if ($obj) {
            if ($id != $obj['id'])
                return array('msg' => '请保证各公众号的token唯一性');
        }
        return null;
    }

    public function wx_response_list() {
        $this->redirect();
    }

    public function wx_response_validator() {

        $event_key = Req::args('event_key');
        $type = Req::args('type');
        if ($event_key == "") {
            $event_key = CHash::random(20, 'char');
            Req::args('event_key', $event_key);
        }
        $content = array();
        if ($type == 'text') {
            $content = Req::args('content');
            $content = array('content' => $content);
        } else if ($type == 'app') {
            $app = Req::args('app');
            $appConfig = WeixinService::appConfig();
            $appFields = $appConfig[$app]['config'];
            $content['app'] = $app;
            foreach ($appFields as $item) {
                if ($item['type'] == 'checkbox') {
                    $content[$item['field'] . '[]'] = implode(',', Req::args($item['field']));
                } else {
                    $content[$item['field']] = Req::args($item['field']);
                }
            }
        }
        $content = serialize($content);
        Req::args('content', $content);
        return null;
    }

    public function select_resources() {
        $this->layout = "blank";
        $condition = Req::args('condition');
        $condition_str = Common::str2where($condition);
        if ($condition_str)
            $this->assign("where", $condition_str);
        else
            $this->assign("where", "1=1");
        $this->assign("condition", $condition);
        $this->redirect();
    }

}
