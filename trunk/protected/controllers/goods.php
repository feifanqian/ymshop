<?php

class GoodsController extends Controller {

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

    //商品上下架
    public function set_online() {
        $id = Req::args("id");
        if (is_array($id)) {
            $id = implode(',', $id);
        }
        $status = Filter::int(Req::args('status'));
        if ($status != 0 && $status != 1)
            $status = 0;
        $model = new Model('goods');
        $model->data(array('is_online' => $status))->where("id in($id)")->update();
        $this->redirect("goods_list");
    }

    //商品加入移除微商区
    // public function set_weishang() {
    //     $id = Req::args('id');
    //     $status = Req::args('status');
    //     $model = new Model('goods');
    //     $res=$model->data(array('is_weishang' =>$status))->where('id = '.$id)->update();
    //     $this->redirect("goods_list");
    // }

    function goods_type_save() {
        $attr_id = Req::args('attr_id');
        $attr_name = Req::args('attr_name');
        $attr_type = Req::args('attr_type');
        $attr_value = Req::args('attr_value');
        $brand = Req::args('brand');
        //spec 处理部分开始
        $spec = Req::args('spec');
        $specs_array = array();
        if ($spec) {
            $spec_ids = $spec['id'];
            if (is_array($spec_ids))
                $spec_ids = implode(',', $spec_ids);
            $model = new Model('goods_spec');
            $specs = $model->where('id in(' . $spec_ids . ')')->order("find_in_set(id,'" . $spec_ids . "')")->findAll();
            $spec_value = new Model('spec_value');
            foreach ($specs as $k => $row) {
                $result = $spec_value->where('spec_id=' . $row['id'])->findAll();
                $row['spec'] = $result;
                $row['show_type'] = $spec['show_type'][$k];
                $specs_array[] = $row;
            }
        }
        Req::args('spec', serialize($specs_array));
        //spec 处理结束

        $values = array();
        if (is_array($brand))
            $brand = implode(',', $brand);
        Req::args('brand', $brand);
        $goods_type = new Model("goods_type");
        $id = Req::args('id');
        if ($id == null) {
            $result = $goods_type->insert();
            $lastid = $result;
            Log::op($this->manager['id'], "添加商品类型", "管理员[" . $this->manager['name'] . "]:添加了商品类型 " . Req::args('name'));
        } else {
            $result = $goods_type->where("id=" . $id)->update();
            $lastid = $id;
            Log::op($this->manager['id'], "修改商品类型", "管理员[" . $this->manager['name'] . "]:修改了商品类型 " . Req::args('name'));
        }
        $goods_attr = new Model('goods_attr');
        $attr_value_model = new Model("attr_value");
        $attr_ids = '';
        if (is_array($attr_id)) {
            foreach ($attr_id as $v) {
                if ($v != 0)
                    $attr_ids .=$v . ',';
            }
            $attr_ids = rtrim($attr_ids, ',');
            $goods_attr->where('type_id = ' . $lastid . ' and id not in(' . $attr_ids . ')')->delete();

            foreach ($attr_id as $k => $v) {
                if ($v == '0') {
                    $attr_last_id = $goods_attr->data(array('name' => $attr_name[$k], 'type_id' => $lastid, 'show_type' => $attr_type[$k], 'sort' => $k))->insert();
                    $this->update_attr_value($attr_value_model, $attr_last_id, $attr_value[$k]);
                } else {
                    $goods_attr->data(array('name' => $attr_name[$k], 'type_id' => $lastid, 'show_type' => $attr_type[$k], 'sort' => $k))->where('id=' . $attr_id[$k])->update();
                    $this->update_attr_value($attr_value_model, $attr_id[$k], $attr_value[$k]);
                }
            }
            $goods_attrs = $goods_attr->where('type_id=' . $lastid)->order("sort")->findAll();
            foreach ($goods_attrs as $key => $row) {
                $row['values'] = $attr_value_model->where('attr_id = ' . $row['id'])->order('sort')->findAll();
                $goods_attrs[$key] = $row;
            }
            $goods_type->data(array('attr' => serialize($goods_attrs)))->where('id=' . $lastid)->update();
        } else {

            $dbinfo = DBFactory::getDbInfo();
            $table_pre = $dbinfo['tablePre'];
            $attr_value_model->where("attr_id  in (select id from {$table_pre}goods_attr where type_id = " . $lastid . ")")->delete();
            $goods_attr->where('type_id = ' . $lastid)->delete();
        }

        $this->redirect('goods_type_list');
    }

    //更新属性值
    private function update_attr_value($attr_value_model, $attr_id, $attr_values) {

        $attr_values = explode(',', $attr_values);
        $value_ids = '';
        foreach ($attr_values as $key => $value) {
            $value_array = explode(":=:", $value);
            if (count($value_array) > 1) {
                if ($value_array[0] == 0) {
                    $new_id = $attr_value_model->data(array('attr_id' => $attr_id, 'name' => $value_array[1], 'sort' => $key))->insert();
                    $value_ids .= $new_id . ',';
                } else {
                    $attr_value_model->data(array('attr_id' => $attr_id, 'name' => $value_array[1], 'sort' => $key))->where('id=' . $value_array[0])->update();
                    $value_ids .= $value_array[0] . ',';
                }
            }
        }
        $value_ids = rtrim($value_ids, ',');
        if ($value_ids == '')
            $attr_value_model->where('attr_id = ' . $attr_id)->delete();
        else
            $attr_value_model->where('attr_id = ' . $attr_id . ' and id not in(' . $value_ids . ')')->delete();
    }

    function attr_values() {
        $this->layout = '';
        $this->redirect();
    }

    function goods_type_del() {
        $id = Req::args('id');
        $dbinfo = DBFactory::getDbInfo();
        $table_pre = $dbinfo['tablePre'];
        if ($id) {
            $model = new Model();
            if (is_array($id)) {
                $ids = implode(',', $id);
                $goods_types = $model->table('goods_type')->where('id in(' . $ids . ')')->findAll();
                $model->table('goods_type')->where('id in(' . $ids . ')')->delete();
                $model->table('attr_value')->where("attr_id in (select id from {$table_pre}goods_attr where type_id in ({$ids}))")->delete();
                $model->table('goods_attr')->where('type_id in(' . $ids . ')')->delete();
            } else {
                $goods_types = $model->table('goods_type')->where('id in(' . $id . ')')->findAll();
                $model->table('goods_type')->where('id=' . $id)->delete();
                $model->table('attr_value')->where("attr_id in (select id from {$table_pre}goods_attr where type_id ={$id})")->delete();
                $model->table('goods_attr')->where('type_id =' . $id)->delete();
            }
            $str = '';
            foreach ($goods_types as $key => $value) {
                $str .= $value['name'] . '、';
            }
            Log::op($this->manager['id'], "删除商品类型", "管理员[" . $this->manager['name'] . "]:删除了商品类型 " . $str);

            $this->redirect('goods_type_list');
        } else {
            $this->msg = array("warning", "未选择项目，无法删除！");
            $this->redirect('goods_type_list', false);
        }
    }

    function goods_spec_show() {
        $this->layout = '';
        $this->redirect();
    }

    function goods_spec_save() {
        $id = Req::args('id');
        $value = Req::args('value');
        $img = Req::args('img');
        $value_id = Req::args('value_id');
        $name = Req::args("name");
        $values = array();
        $goods_spec = new Model("goods_spec");
        if ($id) {
            $goods_spec->save();
            $lastid = $id;
            Log::op($this->manager['id'], "修改商品规格", "管理员[" . $this->manager['name'] . "]:修改了规格 " . $name);
        } else {
            $lastid = $goods_spec->save();
            Log::op($this->manager['id'], "添加商品规格", "管理员[" . $this->manager['name'] . "]:添加了规格 " . $name);
        }
        $spec_value = new Model('spec_value');
        $value_ids = '';
        if (is_array($value_id)) {
            foreach ($value_id as $v) {
                if ($v != 0)
                    $value_ids .=$v . ',';
            }
            $value_ids = rtrim($value_ids, ',');
            $spec_value->where('spec_id = ' . $lastid . ' and id not in(' . $value_ids . ')')->delete();
            foreach ($value_id as $k => $v) {
                if ($v == '0') {
                    $spec_value->data(array('name' => $value[$k], 'spec_id' => $lastid, 'sort' => $k, 'img' => is_array($img) ? $img[$k] : ''))->insert();
                } else
                    $spec_value->data(array('name' => $value[$k], 'spec_id' => $lastid, 'sort' => $k, 'img' => is_array($img) ? $img[$k] : ''))->where('id=' . $value_id[$k])->update();
            }
            $spec_values = $spec_value->where('spec_id = ' . $lastid)->findAll();
            $goods_spec->data(array('value' => serialize($spec_values)))->where('id=' . $lastid)->update();
        }
        else {
            $spec_value->where('spec_id = ' . $lastid)->delete();
        }
        $this->redirect('goods_spec_list');
    }

    function goods_spec_del() {
        $id = Req::args('id');
        if ($id) {
            $model = new Model();
            if (is_array($id)) {
                $ids = implode(',', $id);
                $specs = $model->table('goods_spec')->where('id in(' . $ids . ')')->findAll();
                $model->table('goods_spec')->where('id in(' . $ids . ')')->delete();
                $model->table('spec_value')->where('spec_id in(' . $ids . ')')->delete();
            } else {
                $specs = $model->table('goods_spec')->where('id=' . $id)->findAll();
                $model->table('goods_spec')->where('id=' . $id)->delete();
                $model->table('spec_value')->where('spec_id =' . $id)->delete();
            }
            $str = '';
            foreach ($specs as $key => $value) {
                $str .= $value['name'] . '、';
            }
            Log::op($this->manager['id'], "删除商品规格", "管理员[" . $this->manager['name'] . "]:删除了规格 " . $str);
            $this->redirect('goods_spec_list');
        } else {
            $this->msg = array("warning", "未选择项目，无法删除！");
            $this->redirect('goods_spec_list', false);
        }
    }

    //商品分类
    function goods_category_save() {
        $goods_category = new Model("goods_category");
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
        $adimg = Filter::sql(Req::args("adimg"));
        $ad_img = Filter::sql(Req::args("ad_img"));
        $title_img = Filter::sql(Req::args("title_img"));
        $ad_position = Filter::int(Req::args("ad_position"));
        $adurl = Filter::sql(Req::args("adurl"));
        $imgs = array();
        
        foreach ($imgs_array as $key => $value) {
            $imgs[] = array('img' => $value, 'link' => $links[$key]);
        }


        $item = $goods_category->where("id != $id and ((name = '$name' and parent_id =$parent_id ) or alias = '$alias' )")->find();
        if ($item) {
            if ($alias == $item['alias'])
                $this->msg = array("warning", "别名要求唯一,方便url美化,操作失败！");
            else
                $this->msg = array("error", "同一级别下已经在在相同分类！");
            unset($item['id']);
            $this->redirect("goods_category_edit", false, Req::args());
        }else {
            //最得父节点的信息
            $parent_node = $goods_category->where("id = $parent_id")->find();
            $parent_path = "";
            if ($parent_node) {
                $parent_path = $parent_node['path'];
            }
            $current_node = $goods_category->where("id = $id")->find();
            //更新节点
            if ($current_node) {
                $current_path = $current_node['path'];
                if (strpos($parent_path, $current_path) === false) {

                    if ($parent_path != '')
                        $new_path = $parent_path . $current_node['id'] . ",";
                    else
                        $new_path = ',' . $current_node['id'] . ',';

                    $goods_category->data(array('path' => "replace(`path`,'$current_path','$new_path')"))->where("path like '$current_path%'")->update();
                    $goods_category->data(array('parent_id' => $parent_id, 'id' => $id, 'sort' => $sort, 'name' => $name, 'alias' => $alias, 'nav_show' => $nav_show, 'list_show' => $list_show,'recommend'=>$recommend ,'type_id' => $type_id, 'seo_title' => $seo_title, 'seo_keywords' => $seo_keywords, 'seo_description' => $seo_description, 'img' => $img, 'imgs' => serialize($imgs),'adimg'=>$adimg,'ad_img'=>$ad_img,'title_img'=>$title_img,'ad_position'=>$ad_position))->update();
                    Log::op($this->manager['id'], "更新商品分类", "管理员[" . $this->manager['name'] . "]:更新了商品分类 " . Req::args('name'));
                    $this->redirect("goods_category_list");
                }else {
                    $this->msg = array("warning", "此节点不能放到自己的子节点上,操作失败！");
                    $this->redirect("goods_category_edit", false, Req::args());
                }
            } else {
                //插件节点
                $lastid = $goods_category->insert();
                if ($parent_path != '')
                    $new_path = $parent_path . "$lastid,";
                else
                    $new_path = ",$lastid,";
                $goods_category->data(array('path' => "$new_path", 'id' => $lastid, 'sort' => $sort, 'nav_show' => $nav_show, 'list_show' => $list_show,'recommend'=>$recommend , 'type_id' => $type_id, 'seo_title' => $seo_title, 'seo_keywords' => $seo_keywords, 'seo_description' => $seo_description, 'img' => $img, 'imgs' => serialize($imgs),'adimg'=>$adimg,'ad_img'=>$ad_img,'title_img'=>$title_img,'ad_position'=>$ad_position))->update();

                Log::op($this->manager['id'], "添加商品分类", "管理员[" . $this->manager['name'] . "]:添加商品分类 " . Req::args('name'));
                $this->redirect("goods_category_list");
            }
            $cache = CacheFactory::getInstance();
            $cache->delete("_GoodsCategory");
        }
    }

    //商品分类删除
    function goods_category_del() {
        $id = Req::args('id');
        $category = new Model("goods_category");
        $child = $category->where("parent_id = $id")->find();
        if ($child) {
            $this->msg = array("warning", "由于存在子分类，此分类不能删除，操作失败！");
            $this->redirect("goods_category_list", false);
        } else {
            $goods = new Model("goods");
            $row = $goods->where('category_id = ' . $id)->find();
            if ($row) {
                $this->msg = array("warning", "此分类下还有商品，无法删除！");
                $this->redirect("goods_category_list", false);
            } else {
                $obj = $category->where("id=$id")->find();
                $category->where("id=$id")->delete();
                if ($obj)
                    Log::op($this->manager['id'], "删除商品分类", "管理员[" . $this->manager['name'] . "]:删除了商品分类 " . $obj['name']);
                $cache = CacheFactory::getInstance();
                $cache->delete("_GoodsCategory");

                $this->redirect("goods_category_list");
            }
        }
    }

    function goods_save() {
        $spec_items = Req::args('spec_items');
        $spec_item = Req::args('spec_item');
        $items = explode(",", $spec_items);
        $values_array = array();
        //货品中的一些变量
        $pro_no = Req::args("pro_no");
        $store_nums = Req::args("store_nums");
        $warning_line = Req::args("warning_line");
        $weight = Req::args("weight");
        $sell_price = Req::args("sell_price");
        $market_price = Req::args("market_price");
        $cost_price = Req::args("cost_price");
        $is_online = Req::args("is_online");
        $limit_buy_num = Req::args("limit_buy_num");
        $base_sales_volume = Req::args("base_sales_volume");
        $weishang = Req::args("weishang");
        if ($is_online == null)
            Req::args("is_online", 0);


        //values的笛卡尔积
        $values_dcr = array();
        $specs_new = array();
        if (is_array($spec_item)) {
            foreach ($spec_item as $item) {
                $values = explode(",", $item);

                foreach ($values as $value) {
                    $value_items = explode(":", $value);
                    $values_array[$value_items[0]] = $value_items;
                }
            }
            $value_ids = implode(",", array_keys($values_array));
            $values_model = new Model('spec_value');
            $spec_model = new Model('goods_spec');
            $specs = $spec_model->where("id in ({$spec_items})")->findAll();
            $values = $values_model->where("id in ({$value_ids})")->order('sort')->findAll();
            $values_new = array();
            foreach ($values as $k => $row) {
                $current = $values_array[$row['id']];
                if ($current[1] != $current[2])
                    $row['name'] = $current[2];
                if ($current[3] != '')
                    $row['img'] = $current[3];
                $values_new[$row['spec_id']][$row['id']] = $row;
            }

            foreach ($specs as $key => $value) {
                $value['value'] = isset($values_new[$value['id']]) ? $values_new[$value['id']] : null;
                $specs_new[$value['id']] = $value;
            }

            foreach ($spec_item as $item) {
                $values = explode(",", $item);
                $key_code = ';';
                foreach ($values as $k => $value) {
                    $value_items = explode(":", $value);
                    $key = $items[$k];
                    $tem[$key] = $specs_new[$key];
                    $tem[$key]['value'] = $values_array[$value_items[0]];
                    $key_code .= $key . ':' . $values_array[$value_items[0]][0] . ';';
                }
                $values_dcr[$key_code] = $tem;
            }
        }

        //商品处理
        $goods = new Model('goods');
        Req::args('specs', serialize($specs_new));
        $attrs = is_array(Req::args("attr")) ? Req::args("attr") : array();
        $imgs = is_array(Req::args("imgs")) ? Req::args("imgs") : array();
        Req::args('attrs', serialize($attrs));
        Req::args('imgs', serialize($imgs));
        Req::args('up_time', date("Y-m-d H:i:s"));

        $id = intval(Req::args("id"));
        $gdata = Req::args();
        $gdata['name'] = Filter::sql($gdata['name']);
        // $gdata['category_id'] = isset($_POST['category_type'])?$_POST['category_type']:$_POST['category_id'];
        $category_id = isset($_POST['category_id'])?$_POST['category_id']:0;
        $category_ids = isset($_POST['category_ids'])?$_POST['category_ids']:0;
        $category_idss = isset($_POST['category_idss'])?$_POST['category_idss']:0;
        $gdata['category_id'] = $category_idss!=0 ? $category_idss : ($category_ids!=0?$category_ids:$category_id);
        if($id = 1616){
            var_dump($category_id);
            var_dump($category_ids);
            var_dump($category_idss);
            var_dump($gdata['category_id']);die;
        }
        $gdata['shop_id'] = Req::args("shop_id");
        $shop_model = new Model();
        $shop = $shop_model->table('shop')->where('id='.$gdata['shop_id'])->find();
        if($shop['user_id']!=null) {
            $promoter = $shop_model->table('district_promoter')->fields('base_rate')->where('user_id='.$shop['user_id'])->find();
            $gdata['inviter_rate'] = $promoter['base_rate'];
        }
        if (is_array($gdata['pro_no']))
            $gdata['pro_no'] = $gdata['pro_no'][0];
        if ($id == 0) {
            $gdata['create_time'] = date("Y-m-d H:i:s");
            // var_dump($_POST['is_weishang']);die;
            $goods_id = $goods->data($gdata)->insert();
            Log::op($this->manager['id'], "添加商品", "管理员[" . $this->manager['name'] . "]:添加了商品 " . Req::args('name'));
        } else {
            $goods_id = $id;
            // var_dump($gdata);die;
            $goods->data($gdata)->where("id=" . $id)->update();
            Log::op($this->manager['id'], "修改商品", "管理员[" . $this->manager['name'] . "]:修改了商品 " . Req::args('name'));
        }
        //货品添加处理
        $g_store_nums = $g_warning_line = $g_weight = $g_sell_price = $g_market_price = $g_cost_price = 0;
        $products = new Model("products");
        $k = 0;
        $goods_id = $goods_id==false?0:$goods_id;
        foreach ($values_dcr as $key => $value) {
            $result = $products->where("goods_id = " . $goods_id . " and specs_key = '$key'")->find();

            $data = array('goods_id' => $goods_id, 'pro_no' => $pro_no[$k], 'store_nums' => $store_nums[$k], 'warning_line' => $warning_line[$k], 'weight' => $weight[$k], 'sell_price' => $sell_price[$k], 'market_price' => $market_price[$k], 'cost_price' => $cost_price[$k], 'specs_key' => $key, 'spec' => serialize($value));
            $g_store_nums += $data['store_nums'];
            if ($g_warning_line == 0)
                $g_warning_line = $data['warning_line'];
            else if ($g_warning_line > $data['warning_line'])
                $g_warning_line = $data['warning_line'];
            if ($g_weight == 0)
                $g_weight = $data['weight'];
            else if ($g_weight < $data['weight'])
                $g_weight = $data['weight'];
            if ($g_sell_price == 0)
                $g_sell_price = $data['sell_price'];
            else if ($g_sell_price > $data['sell_price'])
                $g_sell_price = $data['sell_price'];
            if ($g_market_price == 0)
                $g_market_price = $data['market_price'];
            else if ($g_market_price < $data['market_price'])
                $g_market_price = $data['market_price'];
            if ($g_cost_price == 0)
                $g_cost_price = $data['cost_price'];
            else if ($g_cost_price < $data['cost_price'])
                $g_cost_price = $data['cost_price'];

            if (!$result) {
                $products->data($data)->insert();
            } else {
                $products->data($data)->where("goods_id=" . $goods_id . " and specs_key='$key'")->update();
            }
            $k++;
        }
        //如果没有规格
        if ($k == 0) {
            $g_store_nums = $store_nums;
            $g_warning_line = $warning_line;
            $g_weight = $weight;
            $g_sell_price = $sell_price;
            $g_market_price = $market_price;
            $g_cost_price = $cost_price;
            $data = array('goods_id' => $goods_id, 'pro_no' => $pro_no, 'store_nums' => $store_nums, 'warning_line' => $warning_line, 'weight' => $weight, 'sell_price' => $sell_price, 'market_price' => $market_price, 'cost_price' => $cost_price, 'specs_key' => '', 'spec' => serialize(array()));
            $result = $products->where("goods_id = " . $goods_id)->find();
            if (!$result) {
                $products->data($data)->insert();
            } else {
                $products->data($data)->where("goods_id=" . $goods_id)->update();
            }
        }
        //更新商品相关货品的部分信息
        $goods->data(array('store_nums' => $g_store_nums, 'warning_line' => $g_warning_line, 'weight' => $g_weight, 'sell_price' => $g_sell_price, 'market_price' => $g_market_price, 'cost_price' => $g_cost_price,'limit_buy_num'=>$limit_buy_num,'base_sales_volume'=>$base_sales_volume))->where("id=" . $goods_id)->update();
        if($_POST['is_weishang']==1){
            $good=$goods->where('id='.$goods_id)->find();
            $products=new Model('products');
            $product=$products->where("goods_id = " . $goods_id)->find();
            $pointwei=new Model('pointwei_sale');
            $product_id=$product['id'];
            $sell_price=$good['sell_price'];
            $len=strlen($sell_price);
            $exist=$pointwei->where('goods_id='.$goods_id)->find();
            if($exist){
                $datas['price_set']='a:1:{i:'.$product_id.';a:2:{s:4:"cash";s:'.$len.':"'.$sell_price.'";s:5:"point";s:1:"0";}}';
                $datas['is_adjustable']=0;
                $datas['listorder']=0;
                $datas['status']=1;
                $pointwei->data($datas)->where("goods_id=" . $goods_id)->update();
            }else{
                $datas['goods_id']=$goods_id;
                $datas['price_set']='a:1:{i:'.$product_id.';a:2:{s:4:"cash";s:'.$len.':"'.$sell_price.'";s:5:"point";s:1:"0";}}';
                $datas['is_adjustable']=0;
                $datas['listorder']=0;
                $datas['status']=1;
                $pointwei->data($datas)->insert();
            }
        }
        $keys = array_keys($values_dcr);
        $keys = implode("','", $keys);
        //清理多余的货品
        $products->where("goods_id=" . $goods_id . " and specs_key not in('$keys')")->delete();

        //规格与属性表添加部分
        $spec_attr = new Model("spec_attr");
        //处理属性部分

        $value_str = '';
        if ($attrs) {
            foreach ($attrs as $key => $attr) {
                if (is_numeric($attr))
                    $value_str .= "($goods_id,$key,$attr),";
            }
        }
        foreach ($specs_new as $key => $spec) {
            if (isset($spec['value']))
                foreach ($spec['value'] as $k => $v)
                    $value_str .= "($goods_id,$key,$k),";
        }
        $value_str = rtrim($value_str, ',');
        //更新商品键值对表
        $spec_attr->where("goods_id = " . $goods_id)->delete();
        $dbinfo = DBFactory::getDbInfo();
        $spec_attr->query("insert into {$dbinfo['tablePre']}spec_attr values $value_str");
        $this->redirect("goods_list");
    }

    function goods_del() {
        $id = Req::args("id");
        $model = new Model();
        $str = '';
        if (is_array($id)) {
            $id = implode(',', $id);
            $model->table("spec_attr")->where("goods_id in($id)")->delete();
            $model->table("products")->where("goods_id in($id)")->delete();
            $goods = $model->table("goods")->where("id in ($id)")->findAll();
            $model->table("goods")->where("id in ($id)")->delete();
        } else if (is_numeric($id)) {
            $model->table("spec_attr")->where("goods_id = $id")->delete();
            $model->table("products")->where("goods_id = $id")->delete();
            $goods = $model->table("goods")->where("id = $id ")->findAll();
            $model->table("goods")->where("id = $id ")->delete();
        }
        foreach ($goods as $gd) {
            $str .= $gd['name'] . '、';
        }
        $str = trim($str, '、');
        Log::op($this->manager['id'], "删除商品", "管理员[" . $this->manager['name'] . "]:删除了商品 " . $str);
        $msg = array('success', "成功删除商品 " . $str);
        $this->redirect("goods_list", false, array('msg' => $msg));
    }

    function goods_list() {
        
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
        
        $goods_model = new Model("goods");
        $goodslist = $goods_model->where($where)->order("is_online,id desc")->findPage($page, $page_size);
        $shop_ids = $category_ids = array();
        foreach ($goodslist['data'] as $k => $v) {
            $shop_ids[] = $v['shop_id'];
            $category_ids[] = $v['category_id'];
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
        foreach ($goodslist['data'] as $k => &$v) {
            $v['shop_name'] = isset($shopidlist[$v['shop_id']]) ? $shopidlist[$v['shop_id']] : '未知';
            $v['category_name'] = isset($categoryidlist[$v['category_id']]) ? $categoryidlist[$v['category_id']] : '未知';
        }
        $this->assign("shoplist", $shoplist);
        $this->assign("categorylist", $categorylist);
        $this->assign("goodslist", $goodslist);
        $this->assign("where", $where);
        $this->redirect();
    }

    function show_spec_select() {
        $this->layout = '';
        $this->redirect();
    }
    /*
     * 图片库
     */
    function photoshop() {
        $this->layout = '';
        $this->redirect();
    }
    /*
     * 开启商品分佣
     */
    function open_commission(){
        $this->layout = "blank";
        $id = Req::args("id");
        $goodsModel = new Model('goods');   
        $goods = $goodsModel->fields('name,img,id')->where("id = $id")->find();
        
        $productsModel = new Model('products');
        $products = $productsModel->fields("sell_price,market_price,cost_price,store_nums,pro_no,spec,id")->where("goods_id = $id")->findAll();
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
        $commission = new Model('commission_set');
        $data = $commission->where("goods_id =$id")->find();
        $setting = array();
        if(!empty($data)){
            $setting = unserialize($data['setting']);
            $this->assign('set',$setting);
        }
        $this->assign('products',$products);
        $this->assign('goods',$goods);
        $this->redirect();
    }
    /*
     * 关闭商品分佣
     */
    function close_commission(){
        $goods_id = Req::args("gid");
        $goods = new Model('goods');
        $goods->data(array('is_commission'=>0))->where("id = $goods_id")->update();
        $commission = new Model('commission_set');
        $commission->data(array('status'=>0))->where("goods_id = $goods_id")->update();
        $info = array('status' => 'success', 'msg' => '操作成功！已关闭佣金');
        Log::op($this->manager['id'], "修改商品分佣设置", "管理员[" . $this->manager['name'] . "]:关闭了商品的分佣" ."[goods_id:$goods_id]");
        echo JSON::encode($info);
    }
    /*
     * 保存商品分佣设置
     */
    public function save_setting(){
        $data = Req::args();
        unset($data['con']);
        unset($data['act']);
        if(!empty($data)){
          $setting = array(); 
          foreach($data['type'] as $k=>$v){
             $setting[$k]['type']=$v;
             $setting[$k]['type_value']=$data['type_value'][$k];
          }
          if(!empty($setting)){
              $commission = new Model('commission_set');
              $result = $commission->where('goods_id ='.$data['goods_id'])->find();
              if(empty($result)){
                  $time = date('Y-m-d H:i:s',time());
                  $commission->data(array('goods_id'=>$data['goods_id'],'setting'=>serialize($setting),'paid_commission'=>0,'unpaid_commission'=>0,'create_time'=>$time,'update_time'=>$time,'status'=>1))->insert();
              }else{
                  $commission->data(array('setting'=>serialize($setting),'update_time'=>date('Y-m-d H:i:s',time()),'status'=>1))->where('goods_id ='.$data['goods_id'])->update();
              }
              $flag = true;
              foreach ($setting as $k=>$v){
                  if($v['type']>0){
                      $flag = false;
                  }
              }
              $goods = new Model('goods');
              if($flag){
                   $commission->data(array('status'=>0))->where("goods_id=".$data['goods_id'])->update();
                   $goods->data(array('is_commission'=>0))->where("id =".$data['goods_id'])->update();
              }else{
                   $goods->data(array('is_commission'=>1))->where("id =".$data['goods_id'])->update();
              } 
                Log::op($this->manager['id'], "修改商品分佣设置", "管理员[" . $this->manager['name'] . "]:修改了商品的分佣设置 " ."[goods_id:".$data['goods_id']."|set:". json_encode($setting)."]");
                $info = array('status' =>'success', 'msg' => '设置成功');
            }else{
                $info = array('status' => 'fail', 'msg' => '设置失败，请重试');
            }
        }else{
          $info = array('status' => 'fail', 'msg' => '设置失败，请重试');
        }
         echo JSON::encode($info);
    }
    /*
     * 添加评论
     */
     public function add_review(){
        $goods_id = Filter::int(Req::args('goods_id'));
        $model = new Model();
        $goods_info = $model->table("goods")->where("id=$goods_id")->find();
        
        if ($goods_info) {
                //upyun设置
                $upyun = Config::getInstance()->get("upyun");
                $options = array(
                    'bucket' => $upyun['upyun_bucket'],
                    'save-key' => "/data/uploads/review/review_{filemd5}{.suffix}",
                    'allow-file-type' => 'jpg,gif,png', // 文件类型限制，如：jpg,gif,png
                    'expiration' => time() + $upyun['upyun_expiration'],
                    'notify-url' => $upyun['upyun_notify-url'],
                    'content-length-range' =>"0,4096000"	
                );
                $policy = base64_encode(json_encode($options));
                $signature = md5($policy . '&' . $upyun['upyun_formkey']);
                $options['policy'] = $policy;
                $options['signature'] = $signature;
                $options['action'] = $upyun['upyun_uploadurl'];
                $options['img_host'] = $upyun['upyun_cdnurl'];
                $this->assign("options",$options);
                $this->assign("goods_info", $goods_info);
                $this->layout = "blank";
                $this->redirect();
        }
    }
    /*
     * 评论添加提交
     */
    public function add_review_post(){
        $gid = Filter::int(Req::args('gid')); //获取gid
        $point = intval(Req::args('point'));
        if ($point > 5)
            $point = 5;
        elseif ($point < 1)
            $point = 1;

        $content = Filter::txt(Req::args('content'));
        $content = TString::nl2br($content);
        $photos = Req::args('review_img');
        if($photos&&is_array($photos)){
            $photos = Filter::sql(implode("|", $photos));
        }else{
            $photos = "";
        }
        $model = new Model();
        $result = $model->table("review")
                ->data(array('user_id'=>0,'goods_id'=>$gid,'status' => 1, 'point' => $point, 'content' => $content, 'comment_time' => date('Y-m-d'),'photos'=>$photos))
                ->insert();
        if($result){
             //1.查询好评数 分数大于3为好评
             $row1 = $model->table("review")->fields("count(id) as num")->where("status=1 and goods_id = $gid and point>3")->find();
             $satisfaction_num = $row1['num'];
             //2.查询评论数 
             $row2 = $model->table("review")->fields("count(id) as num")->where("status =1 and goods_id = $gid")->find();
             $review_count = $row2['num'];
             $rate = 1;
             if ($review_count && $satisfaction_num) {
                    //计算好评率并将结果存入数据库
                    $rate = round($satisfaction_num / $review_count, 2); //保留两位小数
                    $model->table('goods')->data(array('satisfaction_rate'=>$rate,'review_count'=>$review_count))->where("id=$gid")->update();
             }
             if($this->is_ajax_request()){
                 exit(json_encode(array("status"=>'success')));
             }
        }else{
            if($this->is_ajax_request()){
                 exit(json_encode(array("status"=>'fail','msg'=>'评论失败了...')));
             }
        }    
    }
    
    public function personal_shop_set(){
        $group = "personal_shop_set";
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
                    Log::op($this->manager['id'], "修改个人配置", "管理员[" . $this->manager['name'] . "]:修改个人配置 ");
                }
        }
        $this->assign('data', $config->get($group));
        $this->redirect();
    }
    
    public function personal_shop_list(){
        $this->redirect();
    }

    //分类的下拉框
    public function category_dropdowns(){
        $id = Req::args('id');
        $models = new Model();
        $data = $models->table('goods_category')->fields('id,name')->where("parent_id=$id")->findall();
        echo json_encode($data);
    }

    //上架审核状态
    public function set_online_status()
    {
        $id = Filter::int(Req::args('id'));
        $remark = Req::args('remark');
        $model = new Model();
        $result = $model->table('goods')->data(array('remark'=>$remark))->where("id=".$id)->update();
        if ($result){
            exit(json_encode(array('status'=>'success','msg'=>'上架审核')));
        }else{
            exit(json_encode(array('status'=>'fail','msg'=>'上架审核'))) ;
        }
    }
}
