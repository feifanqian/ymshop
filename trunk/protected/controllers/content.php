<?php

class ContentController extends Controller {

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
        $this->redirect("/admin/noright");
    }

    function uploads_list() {
        $this->redirect("uploads_list");
    }

    function uploads_del() {
        $id = Req::args('id');
        if (is_array($id)) {
            $ids = implode(',', $id);
        } else
            $ids = $id;
        $model = new Model("gallery");
        $uploads = $model->where("id in ($ids)")->findAll();
        $str = '';
        foreach ($uploads as $file) {
            $str .= $file['url'] . '、';
        }
        $str = trim($str, '、');
        $model->where("id in ($ids)")->delete();
        if ($uploads) {
            $upyun = new Upyun();
            foreach ($uploads as $k => $v) {
                $upyun->request("DELETE", $v['url'], array('x-upyun-async' => true));
            }
            Log::op($this->manager['id'], "删除文件", "管理员[" . $this->manager['name'] . "]:删除了文件 " . $str);
            $msg = array('success', "成功删除文件 " . $str);
            $this->redirect("uploads_list", false, array('msg' => $msg));
        } else {
            $this->redirect("uploads_list");
        }
    }

    //对应article的保存
    function article_save() {
        $rules = array('title:required:标题不能为空!', 'content:required:内容不能为空！');
        $info = Validator::check($rules);
        if ($info == true) {
            //Filter::form(array('sql' => 'title', 'text' => 'content'));
            if (Req::args('id') == null)
                Req::args('publish_time', date('Y-m-d H:i:s'));
            $id = Req::args('id');
            $model = new Model("article");
            if ($id) {
                $model->where("id=$id")->update();
                Log::op($this->manager['id'], "修改文章", "管理员[" . $this->manager['name'] . "]:修改了文章 " . Req::args('title'));
            } else {
                $model->insert();
                Log::op($this->manager['id'], "添加文章", "管理员[" . $this->manager['name'] . "]:添加了文章 " . Req::args('title'));
            }
        } else if (is_array($info)) {
            $data = Req::args() + array('validator' => $info);
            $this->redirect('article_edit', false, $data);
            exit;
        }
        $this->redirect("article_list");
    }

    //删除文章
    public function article_del() {
        $id = Req::args('id');
        if (is_array($id)) {
            $ids = implode(',', $id);
        } else
            $ids = $id;
        $model = new Model("article");
        $articles = $model->where("id in ($ids)")->findAll();
        $str = '';
        foreach ($articles as $article) {
            $str .= $article['title'] . '、';
        }
        $str = trim($str, '、');
        $model->where("id in ($ids)")->delete();
        if ($articles) {
            Log::op($this->manager['id'], "删除文章", "管理员[" . $this->manager['name'] . "]:删除了文章 " . $str);
            $msg = array('success', "成功删除文章 " . $str);
            $this->redirect("article_list", false, array('msg' => $msg));
        } else {
            $this->redirect("article_list");
        }
    }

    public function category_list() {
        $model = new Model('category');
        $datas = $model->order('path,sort desc')->findAll();
        $result = Common::treeArray($datas);
        $this->assign("category", $result);
        $this->redirect();
    }

    function article_list() {
        $category = new Model('category');
        $rows = $category->findAll();
        $categorys = array(0 => '默认分类');
        foreach ($rows as $row) {
            $categorys[$row['id']] = $row['name'];
        }
        $where = 'category_id!=4';
        $this->assign("where", $where);
        $this->categorys = $categorys;
        $this->redirect('article_list');
    }

    public function dynamic_list()
    {
        $status = array('-1' => '已删除', '1' => '正常', '2' => '被举报');
        $this->assign('status',$status);
        $this->redirect();
    }

    public function dynamic_detail()
    {
        $id = Req::args('id');
        $model = new Model();
        $info = $model->table('center_dynamic as cd')->join('left join user as u on cd.user_id=u.id left join district_promoter as dp on cd.user_id=dp.user_id')->fields('u.nickname,u.avatar,dp.id as promoter_id,dp.shop_type,cd.*')->where('cd.id = '.$id)->find();
        $this->assign('info',$info);
        $this->redirect();
    }

    public function del_dynamic()
    {
        $id = Req::args('id');
        $model = new Model();
        $model->table('center_dynamic')->data(['status'=>-1])->where('id='.$id)->update();
        $info = array('status' => 'success', 'msg' => '成功');
        echo JSON::encode($info);
    }

    //文章分类
    function category_save() {
        $category = new Model("category");
        $name = Req::args("name");
        $alias = Req::args("alias");
        $parent_id = Req::args("parent_id");
        $sort = intval(Req::args("sort"));
        $id = Req::args("id") == null ? 0 : Req::args("id");

        $item = $category->where("id != $id and ((name = '$name' and parent_id =$parent_id ) or alias = '$alias' )")->find();
        if ($item) {
            if ($alias == $item['alias'])
                $this->msg = array("warning", "别名要求唯一,方便url美化,操作失败！");
            else
                $this->msg = array("error", "同一级别下已经在在相同分类！");
            unset($item['id']);
            $this->redirect("category_edit", true, Req::args());
        }else {
            //最得父节点的信息
            $parent_node = $category->where("id = $parent_id")->find();
            $parent_path = "";
            if ($parent_node) {
                $parent_path = $parent_node['path'];
            }
            $current_node = $category->where("id = $id")->find();
            //更新节点
            if ($current_node) {
                $current_path = $current_node['path'];
                if (strpos($parent_path, $current_path) === false) {

                    if ($parent_path != '')
                        $new_path = $parent_path . $current_node['id'] . ",";
                    else
                        $new_path = ',' . $current_node['id'] . ',';

                    $category->data(array('path' => "replace(`path`,'$current_path','$new_path')"))->where("path like '$current_path%'")->update();
                    $category->data(array('parent_id' => $parent_id, 'id' => $id, 'sort' => $sort, 'name' => $name, 'alias' => $alias))->update();
                    Log::op($this->manager['id'], "修改文章分类", "管理员[" . $this->manager['name'] . "]:修改了文章分类 " . Req::args('name'));
                    $this->redirect("category_list");
                }else {
                    $this->msg = array("warning", "此节点不能放到自己的子节点上,操作失败！");
                    $this->redirect("category_edit", true, Req::args());
                }
            } else {
                //插件节点
                $lastid = $category->insert();
                if ($parent_path != '')
                    $new_path = $parent_path . "$lastid,";
                else
                    $new_path = ",$lastid,";
                $category->data(array('path' => "$new_path", 'id' => $lastid, 'sort' => $sort))->update();
                Log::op($this->manager['id'], "添加文章分类", "管理员[" . $this->manager['name'] . "]:添加了文章分类 " . Req::args('name'));
                $this->redirect("category_list");
            }
        }
    }

    //文章分类删除操作
    function category_del() {
        $id = Req::args('id');
        $category = new Model("category");
        $child = $category->where("parent_id = $id")->find();
        if ($child) {
            $this->msg = array("warning", "由于存在子分类，此分类不能删除，操作失败！");
            $this->redirect("category_list", false);
        } else {
            $article = new Model("article");
            $row = $article->where('category_id = ' . $id)->find();
            if ($row) {
                $this->msg = array("warning", "此分类下还有文章，无法删除！");
                $this->redirect("category_list", false);
            } else {
                $cate = $category->where("id=$id ")->find();
                $category->where("id=$id")->delete();
                Log::op($this->manager['id'], "删除文章分类", "管理员[" . $this->manager['name'] . "]:删除了文章分类 " . $cate['name']);
                $this->redirect("category_list");
            }
        }
    }

    //对应help保存
    function help_save() {
        $rules = array('title:required:标题不能为空!', 'content:required:内容不能为空！');
        $info = Validator::check($rules);
        if ($info == true) {
            Filter::form(array('sql' => 'title', 'text' => 'content'));
            if (Req::args('id') == null)
                Req::args('publish_time', date('Y-m-d H:i:s'));
            $id = Req::args('id');
            $model = new Model("help");
            if ($id) {
                $model->where("id=$id")->update();
                Log::op($this->manager['id'], "修改帮助", "管理员[" . $this->manager['name'] . "]:修改了帮助 " . Req::args('title'));
            } else {
                $model->insert();
                Log::op($this->manager['id'], "添加帮助", "管理员[" . $this->manager['name'] . "]:添加了帮助 " . Req::args('title'));
            }
        } else if (is_array($info)) {
            $data = Req::args() + array('validator' => $info);
            $this->redirect('help_edit', false, $data);
            exit;
        }
        $this->redirect("help_list");
    }

    //删除帮助
    public function help_del() {
        $id = Req::args('id');
        if (is_array($id)) {
            $ids = implode(',', $id);
        } else
            $ids = $id;
        $model = new Model("help");
        $helps = $model->where("id in ($ids)")->findAll();
        $str = '';
        foreach ($helps as $help) {
            $str .= $help['title'] . '、';
        }
        $str = trim($str, '、');
        $model->where("id in ($ids)")->delete();
        if ($helps) {
            Log::op($this->manager['id'], "删除帮助", "管理员[" . $this->manager['name'] . "]:删除了帮助 " . $str);
            $msg = array('success', "成功删除帮助 " . $str);
            $this->redirect("help_list", false, array('msg' => $msg));
        } else {
            $this->redirect("help_list");
        }
    }

    public function help_category_list() {
        $model = new Model('help_category');
        $datas = $model->order('path,sort desc')->findAll();
        $result = Common::treeArray($datas);
        $this->assign("help_category", $result);
        $this->redirect();
    }

    function help_list() {
        $help_category = new Model('help_category');
        $rows = $help_category->findAll();
        $categorys = array(0 => '默认分类');
        foreach ($rows as $row) {
            $categorys[$row['id']] = $row['name'];
        }
        $this->categorys = $categorys;
        $this->redirect('help_list');
    }

    //帮助分类
    function help_category_save() {
        $help_category = new Model("help_category");
        $name = Req::args("name");
        $alias = Req::args("alias");
        $parent_id = Req::args("parent_id");
        $sort = intval(Req::args("sort"));
        $id = Req::args("id") == null ? 0 : Req::args("id");

        $item = $help_category->where("id != $id and ((name = '$name' and parent_id =$parent_id ) or alias = '$alias' )")->find();
        if ($item) {
            if ($alias == $item['alias'])
                $this->msg = array("warning", "别名要求唯一,方便url美化,操作失败！");
            else
                $this->msg = array("error", "同一级别下已经在在相同分类！");
            unset($item['id']);
            $this->redirect("help_category_edit", false, Req::args());
        }else {
            //最得父节点的信息
            $parent_node = $help_category->where("id = $parent_id")->find();
            $parent_path = "";
            if ($parent_node) {
                $parent_path = $parent_node['path'];
            }
            $current_node = $help_category->where("id = $id")->find();
            //更新节点
            if ($current_node) {
                $current_path = $current_node['path'];
                if (strpos($parent_path, $current_path) === false) {

                    if ($parent_path != '')
                        $new_path = $parent_path . $current_node['id'] . ",";
                    else
                        $new_path = ',' . $current_node['id'] . ',';

                    $help_category->data(array('path' => "replace(`path`,'$current_path','$new_path')"))->where("path like '$current_path%'")->update();
                    $help_category->data(array('parent_id' => $parent_id, 'id' => $id, 'sort' => $sort, 'name' => $name, 'alias' => $alias))->update();
                    Log::op($this->manager['id'], "修改帮助分类", "管理员[" . $this->manager['name'] . "]:修改了帮助分类 " . Req::args('name'));
                    $this->redirect("help_category_list");
                }else {
                    $this->msg = array("warning", "此节点不能放到自己的子节点上,操作失败！");
                    $this->redirect("help_category_edit", false, Req::args());
                }
            } else {
                //插件节点
                $lastid = $help_category->insert();
                if ($parent_path != '')
                    $new_path = $parent_path . "$lastid,";
                else
                    $new_path = ",$lastid,";
                $help_category->data(array('path' => "$new_path", 'id' => $lastid, 'sort' => $sort))->update();
                Log::op($this->manager['id'], "添加帮助分类", "管理员[" . $this->manager['name'] . "]:添加了帮助分类 " . Req::args('name'));
                $msg = array('success', "成功添加帮助分类 " . Req::args('name'));
                $this->redirect("help_category_list", false, array('msg' => $msg));
            }
        }
    }

    //帮助分类删除操作
    function help_category_del() {
        $id = Req::args('id');
        $help_category = new Model("help_category");
        $child = $help_category->where("parent_id = $id")->find();
        if ($child) {
            $this->msg = array("warning", "由于存在子分类，此分类不能删除，操作失败！");
            $this->redirect("help_category_list", false);
        } else {
            $help = new Model("help");
            $row = $help->where('category_id = ' . $id)->find();
            if ($row) {
                $this->msg = array("warning", "此分类下还有文章，无法删除！");
                $this->redirect("help_category_list", false);
            } else {
                $help = $help_category->where("id=$id")->find();
                $help_category->where("id=$id")->delete();
                if ($help) {
                    Log::op($this->manager['id'], "删除帮助分类", "管理员[" . $this->manager['name'] . "]:删除了帮助分类 " . $help['name']);
                    $msg = array('success', "成功删除帮助分类 " . $help['name']);
                    $this->redirect("help_category_list", false, array('msg' => $msg));
                } else {
                    $this->redirect("help_category_list");
                }
            }
        }
    }

    //标签列表
    public function tags_list() {
        $condition = Req::args("condition");
        $condition_str = Common::str2where($condition);
        if ($condition_str)
            $this->assign("where", $condition_str);
        else
            $this->assign("where", "1=1");
        $this->assign("condition", $condition);
        $this->redirect();
    }

    //修改标签属性
    public function tags_update() {
        $id = Filter::int(Req::args('id'));
        $status = Req::args('status');
        $sort = Req::args('sort');
        $model = new Model('tags');
        if ($status != null) {
            if ($status != 0 && $status != 1)
                $status = 0;
            $model->data(array('is_hot' => $status))->where("id=$id")->update();
        }
        if ($sort != null) {
            $sort = Filter::int($sort);
            $model->data(array('sort' => $sort))->where("id=$id")->update();
        }
        echo JSON::encode(array('status' => 'success'));
    }

    public function ad_list() {
        $parse_type = array('1' => '普通广告', '2' => '多图轮播', '3' => '文字', '4' => '悬浮', '5' => '代码');
        $this->assign("parse_type", $parse_type);
        $this->redirect();
    }

    public function ad_validator() {
        // var_dump(Req::args());exit;
        $type = Req::args('type');
        $is_open = Req::args("is_open");
        if (!$is_open)
            Req::args("is_open", 0);
        if (!Req::args('id')) {
            $number = CHash::random(32, 'char');
            $number = preg_replace("/(\w{8})\w(\w{4})\w(\w{4})\w(\w{4})\w(\w{8})/i", "$1-$2-$3-$4-$5", $number);
            Req::args('number', $number);
        }

        if ($type == 1 || $type == 2 || $type == 4) {
            $path = Req::args('path');
            $url = Req::args('url');
            $title = Req::args('title');
            $width = Req::args('width');
            $height = Req::args('height');
            $x_coor = Req::args('x_coor');
            $y_coor = Req::args('y_coor');
            $align_center = Req::args('align_center');
            $update_time = Req::args('update_time');
            $end_time = Req::args('end_time');
            $content = array();
            if ($type == 2) {
                $id = Req::args("id");
                if($id==85) {
                    foreach ($path as $key => $value) {
                        $content[$key] = array('path' => $value, 'url' => $url[$key], 'title' => $title[$key],'width' => $width[$key],'height' => $height[$key],'x_coor' => $x_coor[$key],'y_coor' => $y_coor[$key],'align_center' => isset($align_center[$key])?1:0,'update_time' => $update_time[$key],'end_time' => $end_time[$key]);
                    }
                } else {
                    foreach ($path as $key => $value) {
                        $content[$key] = array('path' => $value, 'url' => $url[$key], 'title' => $title[$key]);
                    }
                }
            } else {
                $content[0] = array('path' => $path[0], 'url' => $url[0], 'title' => $title[0]);
                if ($type == 4) {
                    $content[0]['position'] = Req::args("position");
                    $content[0]['is_close'] = Req::args('is_close') ? 1 : 0;
                }
            }

            Req::args('content', serialize($content));
        } elseif ($type == 3) {
            $title = Req::args("font_title");
            $url = Req::args("font_url");
            $color = Req::args("font_color");
            $content = array('title' => $title, 'url' => $url, 'color' => $color);
            Req::args('content', serialize($content));
        } else {
            $content = Req::args("content");
            Req::args('content', Filter::sql($content));
        }
    }

    public function ad_show() {
        $this->layout = "blank";
        $id = Req::args("id");
        $model = new Model("ad");
        $ad = $model->where("id = $id")->find();
        if ($ad['type'] != 5) {
            $ad['content'] = unserialize($ad['content']);
        }
        $this->redirect("ad_show", false, $ad);
    }

    public function change_open() {
        $id = Req::args("id");
        $is_open = Req::args("is_open");
        $model = new Model("ad");
        $model->data(array('is_open' => $is_open))->where("id=$id")->update();
        echo JSON::encode(array('status' => 'success'));
    }
    public function addTag(){
        $keyword = Filter::sql(Req::args('keyword'));
        $keyword = trim($keyword);
        $len = TString::strlen($keyword);
        if ($len >= 2 && $len <= 8) {
            $model = new Model("tags");
            $obj = $model->where("name='$keyword'")->find();
            if ($obj) {
               echo json_encode(array("status"=>'fail',"msg"=>"标签已存在"));
               exit();
            } else {
               $result = $model->data(array('name' => $keyword))->insert();
               if($result){
                   echo json_encode(array("status"=>'success',"msg"=>"成功"));
                   exit();
               } else{
                   echo json_encode(array("status"=>'fail',"msg"=>"数据库错误"));
                    exit();
               }
            }
        }else{
            echo json_encode(array("status"=>'fail',"msg"=>"标签长度应在2-8之间"));
            exit();
        }
    }

    public function ad_preset()
    { 
        $model = new Model();
        $ad = $model->table('ad')->where("id=85 and is_open = 1")->find();
        $arr = array();
        $ads = unserialize($ad['content']);
        $set = array();
        foreach ($ads as $k => $v){
             $arr[$k] = $v['path'];
             $list = $model->table('ad_preset as ap')->join('left join ad as a on ap.ad_id=a.id')->fields('ap.*,a.name')->where("ad_id=85 and k=".$k)->find();
             $set[$k]['name'] = $list['name'];
             $set[$k]['path'] = $v['path'];
             $set[$k]['update_time'] = $list['update_time'];
             $set[$k]['width'] = $list['width'];
             $set[$k]['height'] = $list['height'];
             $set[$k]['x_coor'] = $list['x_coor'];
             $set[$k]['y_coor'] = $list['y_coor'];
             $set[$k]['align_center'] = $list['align_center'];
             $set[$k]['top_distance'] = $list['top_distance'];
             $set[$k]['id'] = $list['id'];      
        }
        $set = array_values($set);
        // var_dump($set);die;
        // $list = $this->model->table('ad_preset as ap')->join('left join ad as a on ap.ad_id=a.id')->fields('ap.*,a.name')->where("ap.ad_id=85")->findAll();
        $this->assign('list',$set);
        $this->redirect();
    }

    public function ad_preset_edit()
    {
        $id = Filter::int(Req::args("id"));
        $model = new Model();
        $info = $model->table('ad_preset as ap')->join('left join ad as a on ap.ad_id=a.id')->fields('ap.*,a.name')->where("ap.id=".$id)->find();
        $ad = $model->table('ad')->where("id=85 and is_open = 1")->find();
        $ads = unserialize($ad['content']);
        $path = $ads[$info['k']];
        $info['path'] = $path['path'];
        // var_dump($info);die;
        $this->assign('info',$info);
        $this->redirect(); 
    }

    public function ad_preset_save()
    {
        $id = Filter::int(Req::args("id"));
        $model = new Model();
        $data = array(
            'update_time'=>Req::args("update_time"),
            'width'=>Req::args("width"),
            'height'=>Req::args("height"),
            'x_coor'=>Req::args("x_coor"),
            'y_coor'=>Req::args("y_coor"),
            'align_center'=>Req::args("align_center"),
            'top_distance'=>Req::args("top_distance"),
            );
        $model->table('ad_preset')->data($data)->where("id=".$id)->update();
        $this->redirect('ad_preset');
    }

    public function center_list()
    {
        $this->redirect();
    }

    public function center_add()
    {
        $this->redirect();
    }

    public function center_edit()
    {
        $id = Filter::int(Req::args("id"));
        $model = new Model();
        $info = $model->table('business_center')->where("id=".$id)->find();
        $this->assign('info',$info);
        $this->redirect();
    }

    public function center_save()
    {
        $id = Filter::int(Req::args("id"));
        $model = new Model();

        if($id) {
            $data = array(
                'name'=>Filter::str(Req::args("name")),
                'avatar'=>Req::args("avatar"),
                'level'=>Filter::int(Req::args("level")),
                'province_id'=>Filter::int(Req::args("province")),
                'region_id'=>Filter::int(Req::args("city")),
                );
            $model->table('business_center')->data($data)->where("id=".$id)->update();
        } else {
            $region_id = Filter::int(Req::args("region_id"));

            $province = Filter::int(Req::args("province"));
            if($province) {
                $province_area = $model->table('area')->where('id='.$province)->find();
                $province_name = substr($province_area['name'], 0, -1);
            }
            $city = Filter::int(Req::args("city"));
            if($city) {
                $city_area = $model->table('area')->where('id='.$city)->find();
                $city_name = substr($city_area['name'], 0, -1);
            }
            $name = $province_name.$city_name.'圈';
            $data = array(
                'name'=>$name,
                'avatar'=>Req::args("avatar"),
                'level'=>0,
                'hot'=>0,
                'province_id'=>$province,
                'region_id'=>$region_id,
                );
            $exist = $model->table('business_center')->where('region_id='.$region_id)->find();
            if(!$exist) {
                $model->table('business_center')->data($data)->insert();
            }
        }
        $this->redirect('center_list');
    }

    public function center_del()
    {
        $id = Filter::int(Req::args("id"));
        $model = new Model();
        $model->table('business_center')->where('id='.$id)->delete();
        $this->redirect('center_list');
    }

    public function index_ad()
    {
        $model = new Model();
        $ad = $model->table('index_ad')->where('id=1')->find();
        $upyun = Config::getInstance()->get("upyun");
        $year = date('Y');
        $mon = date('m');
        $day = date('d');
        $save_key =  "/data/uploads/".$year."/".$mon."/".$day."/" +time()+ ".jpg";
        var_dump($save_key);die;
        $options = array(
                'bucket' => $upyun['upyun_bucket'],
                // 'allow-file-type' => 'jpg,gif,png,jpeg', // 文件类型限制，如：jpg,gif,png
                'expiration' => time() + $upyun['upyun_expiration'],
                // 'notify-url' => $upyun['upyun_notify-url'],
                // 'ext-param' => "",
                'save-key' => $save_key
        );
        $policy = base64_encode(json_encode($options));
        $signature = md5($policy . '&' . $upyun['upyun_formkey']);
        $this->assign('secret', md5('ym123456'));
        $this->assign('policy', $policy);
        $this->assign('signature', $signature);
        $this->assign('save_key', $save_key);
        $this->assign('ad',$ad);
        $this->redirect();
    }

    public function index_ad_save()
    {
        $model = new Model();
        $image_url = Filter::int(Req::args("image_url"));
        $jump_link = Filter::int(Req::args("jump_link"));
        $status = Filter::int(Req::args("status"));
        $model->table('index_ad')->data(['image_url'=>$image_url,'jump_link'=>$jump_link])->where('id=1')->update();
        $this->redirect('index_ad');
    }
}
