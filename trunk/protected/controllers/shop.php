<?php

class ShopController extends Controller {

    public $layout = 'admin';
    private $top = null;
    public $needRightActions = array('*' => true);
    private $manager;

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
        $this->assign('manager', $this->safebox->get('manager'));
        $this->assign('upyuncfg', Config::getInstance()->get("upyun"));

        $currentNode = $menu->currentNode();
        if (isset($currentNode['name']))
            $this->assign('admin_title', $currentNode['name']);
    }

    public function noRight() {
        $this->redirect("/admin/noright");
    }

    //商家分类
    function shop_category_save() {
        $shop_category = new Model("shop_category");
        $name = Req::args("name");
        $alias = Req::args("alias");
        $parent_id = Req::args("parent_id");
        $sort = intval(Req::args("sort"));
        $id = Req::args("id") == null ? 0 : Req::args("id");
        $type_id = Req::args('type_id');
        $nav_show = Filter::int(Req::args('nav_show'));
        $list_show = Filter::int(Req::args('list_show'));
        $recommend = Filter::int(Req::args('recommend'));
        $seo_title = Req::args('seo_title');
        $seo_keywords = Req::args("seo_keywords");
        $seo_description = Req::args('seo_description');
        $img = Filter::sql(Req::args("img"));
        $imgs_array = is_array(Req::args("imgs")) ? Req::args("imgs") : array();
        $links = is_array(Req::args("links")) ? Req::args("links") : array();
        $imgs = array();
        foreach ($imgs_array as $key => $value) {
            $imgs[] = array('img' => $value, 'link' => $links[$key]);
        }


        $item = $shop_category->where("id != $id and ((name = '$name' and parent_id =$parent_id ) or alias = '$alias' )")->find();
        if ($item) {
            if ($alias == $item['alias'])
                $this->msg = array("warning", "别名要求唯一,方便url美化,操作失败！");
            else
                $this->msg = array("error", "同一级别下已经在在相同分类！");
            unset($item['id']);
            $this->redirect("shop_category_edit", false, Req::args());
        }else {
            //最得父节点的信息
            $parent_node = $shop_category->where("id = $parent_id")->find();
            $parent_path = "";
            if ($parent_node) {
                $parent_path = $parent_node['path'];
            }
            $current_node = $shop_category->where("id = $id")->find();
            //更新节点
            if ($current_node) {
                $current_path = $current_node['path'];
                if (strpos($parent_path, $current_path) === false) {

                    if ($parent_path != '')
                        $new_path = $parent_path . $current_node['id'] . ",";
                    else
                        $new_path = ',' . $current_node['id'] . ',';

                    $shop_category->data(array('path' => "replace(`path`,'$current_path','$new_path')"))->where("path like '$current_path%'")->update();
                    $shop_category->data(array('parent_id' => $parent_id, 'id' => $id, 'sort' => $sort, 'name' => $name, 'alias' => $alias, 'nav_show' => $nav_show, 'list_show' => $list_show, 'recommend' => $recommend, 'type_id' => $type_id, 'seo_title' => $seo_title, 'seo_keywords' => $seo_keywords, 'seo_description' => $seo_description, 'img' => $img, 'imgs' => serialize($imgs)))->update();
                    Log::op($this->manager['id'], "更新商家分类", "管理员[" . $this->manager['name'] . "]:更新了商家分类 " . Req::args('name'));
                    $this->redirect("shop_category_list");
                }else {
                    $this->msg = array("warning", "此节点不能放到自己的子节点上,操作失败！");
                    $this->redirect("shop_category_edit", false, Req::args());
                }
            } else {
                //插件节点
                $lastid = $shop_category->insert();
                if ($parent_path != '')
                    $new_path = $parent_path . "$lastid,";
                else
                    $new_path = ",$lastid,";
                $shop_category->data(array('path' => "$new_path", 'id' => $lastid, 'sort' => $sort, 'nav_show' => $nav_show, 'list_show' => $list_show, 'recommend' => $recommend, 'type_id' => $type_id, 'seo_title' => $seo_title, 'seo_keywords' => $seo_keywords, 'seo_description' => $seo_description, 'img' => $img, 'imgs' => serialize($imgs)))->update();

                Log::op($this->manager['id'], "添加商家分类", "管理员[" . $this->manager['name'] . "]:添加商家分类 " . Req::args('name'));
                $this->redirect("shop_category_list");
            }
            $cache = CacheFactory::getInstance();
            $cache->delete("_GoodsCategory");
        }
    }

    //商家分类删除
    function shop_category_del() {
        $id = Req::args('id');
        $category = new Model("shop_category");
        $child = $category->where("parent_id = $id")->find();
        if ($child) {
            $this->msg = array("warning", "由于存在子分类，此分类不能删除，操作失败！");
            $this->redirect("shop_category_list", false);
        } else {
            $shop = new Model("shop");
            $row = $shop->where('category_id = ' . $id)->find();
            if ($row) {
                $this->msg = array("warning", "此分类下还有商家，无法删除！");
                $this->redirect("shop_category_list", false);
            } else {
                $obj = $category->where("id=$id")->find();
                $category->where("id=$id")->delete();
                if ($obj)
                    Log::op($this->manager['id'], "删除商家分类", "管理员[" . $this->manager['name'] . "]:删除了商家分类 " . $obj['name']);
                $cache = CacheFactory::getInstance();
                $cache->delete("_GoodsCategory");

                $this->redirect("shop_category_list");
            }
        }
    }

    function shop_save() {
        //商家处理
        $shop = new Model('shop');
        $imgs = is_array(Req::args("imgs")) ? Req::args("imgs") : array();
        Req::args('imgs', serialize($imgs));
        Req::args('up_time', date("Y-m-d H:i:s"));

        $id = intval(Req::args("id"));

        $gdata = Req::args();
        $gdata['name'] = Filter::sql($gdata['name']);
        if ($id) {
            $shopinfo = $shop->where("id='{$id}'")->find();
            if ($shopinfo['username'] != $gdata['username']) {
                $userinfo = $shop->where("username='{$gdata['username']}' AND id!='{$id}'")->find();
                if ($userinfo) {
                    $this->msg = array("warning", "用户名已经存在！添加失败!");
                    $this->redirect("shop_edit", false, Req::args());
                    exit;
                }
            }
            if ($gdata['password']) {
                $gdata['salt'] = CHash::random(6);
                $gdata['password'] = md5(md5($gdata['password']) . $gdata['salt']);
            } else {
                unset($gdata['password']);
            }
        } else {
            $userinfo = $shop->where("username='{$gdata['username']}'")->find();
            if ($userinfo) {
                $this->msg = array("warning", "用户名已经存在！添加失败!");
                $this->redirect("shop_edit", false, Req::args());
                exit;
            }
            $gdata['salt'] = CHash::random(6);
            $gdata['password'] = md5(md5($gdata['password']) . $gdata['salt']);
        }
        if ($id ==null) {
            $gdata['create_time'] = date("Y-m-d H:i:s");
            $shop_id = $shop->data($gdata)->save();
            Log::op($this->manager['id'], "添加商家", "管理员[" . $this->manager['name'] . "]:添加了商家 " . Req::args('name'));
        } else {
            unset($gdata['id']);
            $result = $shop->data($gdata)->where("id=" . $id)->update();
            Log::op($this->manager['id'], "修改商家", "管理员[" . $this->manager['name'] . "]:修改了商家 " . Req::args('name'));
        }

        $this->redirect("shop_list");
    }

    function shop_del() {
        $id = Req::args("id");
        $model = new Model();
        $str = '';
        if (is_array($id)) {
            $id = implode(',', $id);
            $model->table("spec_attr")->where("shop_id in($id)")->delete();
            $model->table("products")->where("shop_id in($id)")->delete();
            $shop = $model->table("shop")->where("id in ($id)")->findAll();
            $model->table("shop")->where("id in ($id)")->delete();
        } else if (is_numeric($id)) {
            $model->table("spec_attr")->where("shop_id = $id")->delete();
            $model->table("products")->where("shop_id = $id")->delete();
            $shop = $model->table("shop")->where("id = $id ")->findAll();
            $model->table("shop")->where("id = $id ")->delete();
        }
        foreach ($shop as $gd) {
            $str .= $gd['name'] . '、';
        }
        $str = trim($str, '、');
        Log::op($this->manager['id'], "删除商家", "管理员[" . $this->manager['name'] . "]:删除了商家 " . $str);
        $msg = array('success', "成功删除商家 " . $str);
        $this->redirect("shop_list", false, array('msg' => $msg));
    }

    function shop_list() {

        $page = intval(Req::args("p"));

        $page_size = 10;

        $condition = Req::args("condition");
        $condition_str = Common::str2where($condition);
        if ($condition_str) {
            $where = $condition_str;
        } else {
            $where = "1=1";
        }
        $this->assign("condition", $condition);

        $category_id = intval(Req::args("category_id"));
        if ($category_id) {
            $where .= " AND category_id = '{$category_id}'";
        }

        $shop_model = new Model("shop");
        $shoplist = $shop_model->where($where)->order("id desc")->findPage($page, $page_size);
        $shop_ids = $category_ids = array();
        foreach ($shoplist['data'] as $k => $v) {
            $category_ids[] = $v['category_id'];
        }
        $category_model = new Model("shop_category");
        $categorylist = $category_model->where("1=1")->findAll();
        $categoryidlist = array();
        foreach ($categorylist as $k => $v) {
            $categoryidlist[$v['id']] = $v['name'];
        }
        foreach ($shoplist['data'] as $k => &$v) {
            $v['category_name'] = isset($categoryidlist[$v['category_id']]) ? $categoryidlist[$v['category_id']] : '未知';
        }
        $this->assign("categorylist", $categorylist);
        $this->assign("category_id", $category_id);
        $this->assign("shoplist", $shoplist);
        $this->assign("where", $where);
        $this->redirect();
    }

    function photoshop() {
        $this->layout = '';
        $this->redirect();
    }
    function shop_edit(){
        $id = Req::args("id");
        if($id){
            $shop_model = new Model('shop');
            $shop  = $shop_model->where("id =$id")->find();
            $this->assign('shop',$shop);
        }
        $this->redirect();
    }
    function shop_category_list(){
        $this->redirect();
    }
    function shop_category_edit(){
        $this->redirect();
    }
}
