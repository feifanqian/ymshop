<?php

class ProductAction extends Controller {

    public $model = null;
    public $user = null;
    public $code = 1000;
    public $content = NULL;

    public function __construct() {
        $this->model = new Model();
    }

    public function info() {
        $parse = array('直接打折', '减价优惠', '固定金额', '买就赠优惠券', '买M件送N件');
        $id = Filter::int(Req::args('id'));
        $id = is_numeric($id) ? $id : 0;
        $goods = $this->model->table("goods as go")->join("left join tiny_goods_category as gc on go.category_id = gc.id")->fields("go.*,gc.path")->where("go.id=$id and go.is_online=0")->find();
        if ($goods) {

            $skumap = array();
            $products = $this->model->table("products")->fields("sell_price,market_price,store_nums,specs_key,pro_no,id")->where("goods_id = $id")->findAll();
            if ($products) {
                foreach ($products as $product) {
                    $skumap[$product['specs_key']] = $product;
                }
            }

            $path = trim($goods['path'], ',');
            $category = Category::getInstance();
            $childCategory = $category->getCategoryChild($path);
            $category = $this->model->table("goods_category")->where("id in ($path)")->order("field(`id`,$path)")->findAll();
            $time = "'" . date('Y-m-d H:i:s') . "'";

            $prom = $this->model->table("prom_goods")->where("id=" . $goods['prom_id'] . " and is_close=0 and $time >=start_time and $time <= end_time")->find();


            $attr_array = unserialize($goods['attrs']);
            $goods_attrs = array();
            if ($attr_array) {
                $rows = $this->model->fields("ga.*,av.name as vname,av.id as vid")->table("goods_attr as ga")->join("left join attr_value as av on ga.id=av.attr_id")->where("ga.type_id = $goods[type_id]")->findAll();
                $attrs = $_attrs = array();
                foreach ($rows as $row) {
                    $attrs[$row['id'] . '-' . $row['vid']] = $row;
                    $_attrs[$row['id']] = $row;
                }
                foreach ($attr_array as $key => $value) {
                    if (isset($attrs[$key . '-' . $value]))
                        $goods_attrs[] = $attrs[$key . '-' . $value];
                    else {
                        $_attrs[$key]['vname'] = $value;
                        $goods_attrs[] = $_attrs[$key];
                    }
                }
                unset($attrs, $_attrs);
            }
            //评论
            $comment = array();
            $review = array('1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0);
            $rows = $this->model->table("review")->fields("count(id) as num,point")->where("status=1 and goods_id = $id")->group("point")->findAll();
            foreach ($rows as $row) {
                $review[$row['point']] = intval($row['num']);
            }
            $a = ($review[4] + $review[5]);
            $b = ($review[3]);
            $c = ($review[1] + $review[2]);
            $total = $a + $b + $c;
            $comment['total'] = $total;
            if ($total == 0)
                $total = 1;
            $comment['a'] = array('num' => $a, 'percent' => round((100 * $a / $total)));
            $comment['b'] = array('num' => $b, 'percent' => round((100 * $b / $total)));
            $comment['c'] = array('num' => $c, 'percent' => round((100 * $c / $total)));

            $where = "re.status=1 and re.goods_id = $id";
            $lastreview = $this->model->table("review as re")->join("left join user as us on re.user_id = us.id")->fields("re.*,re.id as id,us.name as uname,us.avatar")->where($where)->order("re.id desc")->limit("0,1")->find();
            $comment['last'] = $lastreview ? $lastreview : NULL;

            if ($goods['seo_title'] != '')
                $seo_title = $goods['seo_title'];
            else if ($goods['name'] != '')
                $seo_title = $goods['name'];

            if ($goods['seo_keywords'] != '')
                $seo_keywords = $goods['seo_keywords'];
            if ($goods['seo_description'] != '')
                $seo_description = $goods['seo_description'];

            $proms = new Prom();
            $goods['goods_nums'] = PHP_INT_MAX;
            if (!empty($prom))
                $prom['parse'] = $proms->prom_goods($goods);
            //售后保障
            $sale_protection = $this->model->table('help')->where("title='售后保障'")->find();
            if ($sale_protection) {
                $sale_protection = $sale_protection['content'];
            }

            if ($this->user) {
                $area_ids = array();
                $address = $this->model->table("address")->where("user_id=" . $this->user['id'])->order("is_default desc")->findAll();
                foreach ($address as $add) {
                    $area_ids[$add['province']] = $add['province'];
                    $area_ids[$add['city']] = $add['city'];
                    $area_ids[$add['county']] = $add['county'];
                }
                $area_ids = implode(",", $area_ids);
                $areas = array();
                if ($area_ids != '')
                    $areas = $this->model->table("area")->where("id in($area_ids )")->findAll();
                $parse_area = array();
                foreach ($areas as $area) {
                    $parse_area[$area['id']] = $area['name'];
                }
                $province = $area_ids ? $area_ids[0] : 0;
                $city = $area_ids ? $area_ids[1] : 0;
                $county = $area_ids ? $area_ids[2] : 0;
                $address = implode(' ', $parse_area);
            } else {
                $province = 110000;
                $city = 110100;
                $county = 120100;
                $address = "北京市 北京市 市辖区";
            }

            $skumap = array_values($skumap);
            $goods['imgs'] = array_values(unserialize($goods['imgs']));
            $goods['specs'] = array_values(unserialize($goods['specs']));
            
            //替换网址
            //$goods['content'] = str_replace("/data/uploads", "http://img.buy-d.cn/"."data/uploads", $goods['content']);
            $html = '<!DOCTYPE html><html><head><title></title><meta charset="UTF-8">';
            $html.='<meta name="viewport" content="width=device-width, initial-scale=1.0"></head>';
            $html.='<body><div>'.$goods['content'].'</div></body></html>';
            $goods['content']= $html;
            $url = "http://".$_SERVER['HTTP_HOST']."/product-".$id.".html";
            $goods['shareurl']=$url;
            
            
            foreach ($goods['specs'] as $k => &$v) {
                $v['value'] = array_values($v['value']);
            }
            $goods['attrs'] = unserialize($goods['attrs']);
            
            //销量计算
            $sales_volume = $this->model->table("order_goods as og")->join("left join order as o on og.order_id = o.id")->where("og.goods_id = $id and o.status in (3,4)")->fields("SUM(og.goods_nums) as sell_volume")->find();
            $sales_volume = $sales_volume['sell_volume']==NULL?0:$sales_volume['sell_volume'];
            $sales_volume = $goods['base_sales_volume']+$sales_volume;
            $goods['sales_volume']=$sales_volume;
            
            $this->code = 0;
            $this->content = array(
                "province" => $province,
                "city" => $city,
                "county" => $county,
                "address" => $address,
                "child_category" => $childCategory,
                "prom" => $prom,
                "goods" => $goods,
                "goods_attrs" => $goods_attrs,
                "category_nav" => $category,
                "skumap" => $skumap,
                "comment" => $comment,
            );
        } else {
            $this->code = 1040;
        }
    }

    public function category() {
        $page = intval(Req::args("p"));
        $page_size = 36;
        $sort = Filter::int(Req::args("sort"));
        $sort = $sort == null ? 0 : $sort;
        $cid = Filter::int(Req::args("cid"));
        $cid = $cid == null ? 0 : $cid;
        $brand = Filter::int(Req::args("brand"));
        $price = Req::args("price"); //下面已进行拆分过滤

        $keyword = Req::args('keyword');
        if ($keyword != null) {
            $keyword = urldecode($keyword);
            $keyword = Filter::text($keyword);
            $keyword = Filter::commonChar($keyword);
        }

        //初始化数据
        $attrs = $specs = $spec_attr = $category_child = $spec_attr_selected = $selected = $has_category = $category = $current_category = array();
        $where = $spec_attr_where = $url = "";
        $condition_num = 0;

        $model = $this->model;
        //基本条件的建立
        //关于搜索的处理
        $action = strtolower(Req::args("act"));
        if ($action == 'search') {
            //关于类型的处理
            ////提取商品下的类型
            $seo_title = $seo_keywords = $keyword;
            $where = "name like '%$keyword%'";
            $rows = $model->table("goods")->fields("category_id,count(id) as num")->where($where)->group("category_id")->findAll();
            $category_ids = "";
            $category_count = array();
            foreach ($rows as $row) {
                $category_ids .= $row['category_id'] . ',';
                $category_count[$row['category_id']] = $row['num'];
            }
            $category_ids = trim($category_ids, ",");
            $has_category = array();

            $seo_description = '';

            if ($category_ids) {

                //搜索到内容且真正的点击搜索时进行统计
                $keyword = urldecode(Req::args('keyword'));
                $keyword = Filter::sql($keyword);
                $keyword = trim($keyword);
                $len = TString::strlen($keyword);
                if ($len >= 2 && $len <= 8) {
                    $model = new Model("tags");
                    $obj = $model->where("name='$keyword'")->find();
                    if ($obj) {
                        $model->data(array('num' => "`num`+1"))->where("id=" . $obj['id'])->update();
                    } else {
                        $model->data(array('name' => $keyword))->insert();
                    }
                }

                $rows = $model->table("goods_category")->where("id in ($category_ids)")->findAll();
                foreach ($rows as $row) {
                    $path = trim($row['path'], ',');
                    $paths = explode(',', $path);
                    $root = 0;
                    if (is_array($paths))
                        $root = $paths[0];
                    $row['num'] = $category_count[$row['id']];
                    $has_category[$root][] = $row;
                    $seo_description .= $row['name'] . ',';
                }
            }
            if ($cid != 0) {
                $where = "category_id=$cid and name like '%$keyword%'";
                $category = $model->table("goods_category as gc ")->join("left join goods_type as gt on gc.type_id = gt.id")->where("gc.id=$cid")->find();
                if ($category) {
                    $attrs = unserialize($category['attr']);
                    $specs = unserialize($category['spec']);

                    if ($category['seo_title'] != '')
                        $seo_title = $category['seo_title'];
                    else
                        $seo_title = $category['name'];
                    if ($category['seo_keywords'] != '')
                        $seo_keywords = $category['seo_keywords'];
                    if ($category['seo_description'] != '')
                        $seo_description = $category['seo_description'];
                }
            }

            //关于分类检索的处理
        }else if ($action == 'category') {
            $seo_title = "分类检索";
            $seo_keywords = "全部分类";
            $seo_description = "所有分类商品";
            //取得商品的子分类
            $category_ids = "";
            $categ = Category::getInstance();
            if ($cid == 0) {
                $category_child = $categ->getCategoryChild(0, 1);
            } else {
                $current_category = $this->model->table("goods_category as gc")->fields("gc.*,gt.name as gname,gt.attr,gt.spec,gc.seo_title,gc.seo_keywords,gc.seo_description")->join("left join goods_type as gt on gc.type_id = gt.id")->where("gc.id = $cid")->find();
                if ($current_category) {
                    $path = trim($current_category['path'], ',');
                    $rows = $this->model->table("goods_category")->where("path like '$current_category[path]%'")->order("field(`id`,$path)")->findAll();
                    $category = $this->model->table("goods_category")->where("id in ($path)")->order("field(`id`,$path)")->findAll();

                    foreach ($rows as $row) {
                        $category_ids .= $row['id'] . ',';
                    }
                    $category_ids = trim($category_ids, ",");
                    $category_child = $categ->getCategoryChild($path, 1);

                    $attrs = unserialize($current_category['attr']);
                    $specs = unserialize($current_category['spec']);
                    $attrs = is_array($attrs) ? $attrs : array();
                    $specs = is_array($specs) ? $specs : array();
                }
            }
            $seo_category = $model->table('goods_category')->where("id=$cid")->find();
            if ($seo_category) {
                if ($seo_category['seo_title'] != '')
                    $seo_title = $seo_category['seo_title'];
                else
                    $seo_title = $seo_category['name'];
                if ($seo_category['seo_keywords'] != '')
                    $seo_keywords = $seo_category['name'] . ',' . $seo_category['seo_keywords'];
                else
                    $seo_keywords = $seo_category['name'];
                if ($seo_category['seo_description'] != '')
                    $seo_description = $seo_category['seo_description'];
                else
                    $seo_description = $seo_category['name'];
            }
            if ($category_ids != "")
                $where = "go.category_id in ($category_ids)";
            else
                $where = "1=1";
        }
        //品牌筛选
        $rows = $model->table("goods as go")->fields("brand_id,count(id) as num")->where($where)->group("brand_id")->findAll();
        $brand_ids = '';
        $brand_num = $has_brand = array();
        foreach ($rows as $row) {
            $brand_ids .= $row['brand_id'] . ',';
            $brand_num[$row['brand_id']] = $row['num'];
        }
        $brand_ids = trim($brand_ids, ',');

        //价格区间
        $prices = $model->table("goods as go")->fields("max(sell_price) as max,min(sell_price) as min,avg(sell_price) as avg")->where($where)->find();
        $price_range = Common::priceRange($prices);

        if ($brand_ids) {
            $has_brand = $model->table("brand")->where("id in ($brand_ids)")->findAll();
        }
        //var_dump($price_range);exit();
        if (!empty($price_range))
            $has_price = array_flip($price_range);
        else
            $has_price = array();
        if ($price) {
            $prices = explode('-', $price);
            if (count($prices) == 2)
                $where .= " and sell_price>=" . Filter::int($prices[0]) . " and sell_price <=" . Filter::int($prices[1]);
            else
                $where .= " and sell_price>=" . Filter::int($prices[0]);
            $url .= "/price/$price";
        }


        if ($brand && isset($brand_num[$brand])) {
            $url .= "/brand/$brand";
            $where .= " and brand_id = $brand ";
        }
        //规格与属性的处理
        if(!empty($attrs)){
            foreach ($attrs as $attr) {
            if ($attr['show_type'] == 1) {
                $spec_attr[$attr['id']] = $attr;
            }
             }
        }    
        
        if(!empty($specs)){
            foreach ($specs as $spec) {
            $spec['values'] = unserialize($spec['value']);
            unset($spec['value'], $spec['spec']);
            $spec_attr[$spec['id']] = $spec;
        }
        }
        if(!empty($selected)){
          foreach ($selected as $key => $value) {
            if (isset($spec_attr[$key])) {
                $spec_attr_selected[$key] = $spec_attr[$key];
                foreach ($spec_attr_selected[$key]['values'] as $k => $v) {
                    if ($value == $v['id']) {
                        $spec_attr_selected[$key]['values'] = $v;
                        break;
                    }
                }
            }
         }
  
        }
        

        //规格处属性的筛选
        $args = Req::args();
        unset($args['con'], $args['act'], $args['p'], $args['sort'], $args['brand'], $args['price']);
        foreach ($args as $key => $value) {
            if (is_numeric($key) && is_numeric($value)) {
                if (isset($spec_attr[$key])) {
                    $spec_attr_where .= "or (`key`=$key and `value` = $value) ";
                    $condition_num++;
                    $url .= '/' . $key . '/' . $value;
                }
            }
            $selected[$key] = $value;
        }
        $selected['price'] = $price;
        $selected['brand'] = $brand;

        $spec_attr_where = trim($spec_attr_where, "or");
        $where .= ' and go.is_online =0';
        if ($condition_num > 0) {
            $where .= " and go.id in (select goods_id from tiny_spec_attr where $spec_attr_where group by goods_id having count(goods_id) >= $condition_num)";
        }

        //排序的处理
        switch ($sort) {
            case '1':
                $goods_model = $model->table("goods as go")->join("left join tiny_order_goods as og on go.id = og.goods_id")->fields("go.*,sum(og.goods_nums) as sell_num")->order("sell_num desc")->group("go.id");
                break;
            case '2':
                $goods_model = $model->table("goods as go")->join("left join tiny_review as re on go.id = re.goods_id")->fields("go.*,count(re.goods_id) as renum")->group("go.id")->order("renum desc");
                break;
            case '3':
                $goods_model = $model->table("goods as go")->order("sell_price desc");
                break;
            case '4':
                $goods_model = $model->table("goods as go")->order("sell_price");
                break;
            case '5':
                $goods_model = $model->table("goods as go")->order("id desc");
                break;
            default:
                $goods_model = $model->table("goods as go")->order("sort desc");
                break;
        }
        //var_dump($where);exit;
        //提取商品
        $goods = $goods_model->where($where)->findPage($page, $page_size);

        //品牌处理
        preg_match_all('!(<(a|span)[^>]+>(上一页|下一页)</\2>)!', $goods['html'], $matches);
        $topPageBar = "";
        if (count($matches[0]) > 0)
            $topPageBar = implode("", $matches[0]);
        $keyword = str_replace('|', '', $keyword);


        $this->code = 0;
        $this->content = array(
            "topPageBar" => $topPageBar,
            'seo_title' => $seo_title,
            'seo_keywords' => $seo_keywords,
            'seo_description' => '对应的商品共有' . $goods['page']['total'] . '件商品,包括以下分类：' . $seo_description,
            "keyword" => $keyword,
            "sort" => $sort,
            "has_brand" => $has_brand,
            "brand_num" => $brand_num,
            "current_category" => $current_category,
            "goods" => $goods,
            "selected" => $selected,
            "spec_attr" => $spec_attr,
            "spec_attr_selected" => $spec_attr_selected,
            "category_child" => $category_child,
            "price_range" => $price_range,
            "category_nav" => $category,
            "has_category" => $has_category,
            "cid" => $cid,
        );
    }

    // public function flash() {
    //     $page = Filter::int(Req::args("page"));
    //     $page = $page < 0 ? 1 : $page;
    //     $page_size = 10;
    //     $now  = date('Y-m-d H:i:s');
    //     //更新状态
    //     $result = $this->model->table('flash_sale')->data(array('is_end'=>1))->where("is_end=0 and end_time < '$now'")->update();
        
    //     $first = $this->model->table("flash_sale as gb")->fields("*,gb.id as id")->order("gb.is_end asc,gb.end_time asc")->join("left join goods as go on gb.goods_id = go.id")->findPage(1,1);
    //     $list = $this->model->table("flash_sale as gb")->fields("*,gb.id as id")->order("gb.is_end asc,gb.id desc")->join("left join goods as go on gb.goods_id = go.id")->findPage($page, $page_size);
    //     unset($list['html']);
    //     if ($list['data']) {
    //         foreach ($list['data'] as $k => &$v) {
    //             $v['imgs'] = array_values(unserialize($v['imgs']));
    //             unset($v['specs'], $v['attrs'], $v['content']);
    //         }
    //     }
    //     $this->code = 0;
    //     $this->content = array(
    //         'flashlist' => $list,
    //         );
    //     if(isset($first['data'][0]['end_time'])){
    //         $this->content['end_time'] = $first['data'][0]['end_time'];
    //         $this->content['now'] = date('Y-m-d H:i:s');
    //     }else{
    //         $this->content['end_time'] = date('Y-m-d H:i:s');
    //         $this->content['now'] = date('Y-m-d H:i:s');
    //     }
        
    // }

    public function flash(){
        $page = Filter::int(Req::args("page"));
        if(!$page){
            $page = 1;
        }
        $page = $page < 0 ? 1 : $page;
        $now  = date('Y-m-d H:i:s');
        //更新状态
        $result1 = $this->model->table('flash_sale')->data(array('is_end'=>1))->where("is_end=0 and end_time < '$now'")->update();
        $result2 = $this->model->table('pointflash_sale')->data(array('is_end'=>1))->where("is_end=0 and end_date < '$now'")->update();
        
        $first = $this->model->table("flash_sale as gb")->fields("*,gb.id as id")->order("gb.is_end asc,gb.end_time asc")->join("left join goods as go on gb.goods_id = go.id")->findPage(1,1);
        // $list1 = $this->model->table("pointflash_sale as gb")->fields("*,gb.id as id")->order("gb.is_end asc,gb.id desc")->join("left join goods as go on gb.goods_id = go.id")->where("gb.start_date<'$now' and gb.end_date>'$now'")->findAll();
        // $list2 = $this->model->table("flash_sale as gb")->fields("*,gb.id as id")->order("gb.is_end asc,gb.id desc")->join("left join goods as go on gb.goods_id = go.id")->where("gb.start_time<'$now' and gb.end_time>'$now'")->findAll();
        $list1 = $this->model->table("pointflash_sale as gb")->fields("*,gb.id as id")->order("gb.is_end asc,gb.id desc")->join("left join goods as go on gb.goods_id = go.id")->findAll();
        $list2 = $this->model->table("flash_sale as gb")->fields("*,gb.id as id")->order("gb.is_end asc,gb.id desc")->join("left join goods as go on gb.goods_id = go.id")->findAll();
        if($list1){
            foreach($list1 as $k=>$v){
                $list1[$k]['tag'] = $v['title'];
                $list1[$k]['max_num'] = $v['max_sell_count'];
                $list1[$k]['start_time'] = $v['start_date'];
                $list1[$k]['end_time'] = $v['end_date'];
                $list1[$k]['order_num'] = $v['order_count'];
                $list1[$k]['quota_num'] = $v['quota_count'];
                $set = current(unserialize($v['price_set']));
                $list1[$k]['price'] = sprintf("%.2f",$set['cash']);
                $list1[$k]['send_point'] = '0.00';
                $list1[$k]['description'] = '';
                $list1[$k]['goods_num'] = $v['max_sell_count'];
                $list1[$k]['wants'] = '';
                $list1[$k]['wants_num'] = '0';
                $list1[$k]['cost_point'] = $set['point'];
                $list1[$k]['flash_type'] = 'point';
                unset($list1[$k]['max_sell_count']);
                unset($list1[$k]['start_date']);
                unset($list1[$k]['end_date']);
                unset($list1[$k]['order_count']);
                unset($list1[$k]['quota_count']);
                unset($list1[$k]['price_set']);
            }
        }
        if($list2){
            foreach($list2 as $k=>$v){
                $list2[$k]['cost_point'] = '0.00';
                $list2[$k]['flash_type'] = 'cash';
            }
        }
        $list = array_merge($list1,$list2);
        $total=count($list);//总条数  
        $num=10;//每页显示条数  
        
        $list = array_slice($list, ($page-1)*$num, $num);
        
        if ($list) {
            foreach ($list as $k => &$v) {
                $v['imgs'] = array_values(unserialize($v['imgs']));
                unset($v['specs'], $v['attrs'], $v['content']);
            }
        }
        $newpage = array(
            'total'=>$total,
            'totalPage'=>ceil($total/$num),
            'pageSize'=>$num,
            'page'=>$page
            );
        $this->code = 0;
        $this->content = array(
            'flashlist' => array(
                 'data'=>$list,
                 'page'=>$newpage
                ),
            );
        if(isset($first['data'][0]['end_time'])){
            $this->content['end_time'] = $first['data'][0]['end_time'];
            $this->content['now'] = date('Y-m-d H:i:s');
        }else{
            $this->content['end_time'] = date('Y-m-d H:i:s');
            $this->content['now'] = date('Y-m-d H:i:s');
        }
    }

    public function wei() {
        $page = Filter::int(Req::args("page"));
        $page = $page < 0 ? 1 : $page;
        $page_size = 10;
        
        $list = $this->model->table("pointwei_sale as ps")->fields("ps.*,go.name,go.img,go.imgs,go.sell_price,go.subtitle")->join("left join goods as go on ps.goods_id = go.id")->where("go.is_online = 0 and go.is_weishang = 1 and ps.status = 1 and go.store_nums > 0")->order("ps.listorder asc")->findPage($page, $page_size);
        if($list){
            unset($list['html']);
            if ($list['data']) {
                foreach ($list['data'] as $k => &$v) {
                    $v['imgs'] = array_values(unserialize($v['imgs']));
                    $v['price_set']=  array_values(unserialize($v['price_set']));
                }
            }
            $this->code = 0;
            $this->content['weishang_data'] = $list['data'];
            $this->content['page'] = $list['page'];
        }else{
            $this->code = 0;
            $this->content['weishang_data'] = array();
        } 
    }

    public function group() {
        $page = Filter::int(Req::args("page"));
        $page = $page < 0 ? 1 : $page;
        $page_size = 10;
        $list = $this->model->table("groupbuy as gb")->fields("*,gb.id as id")->order("is_end,goods_num desc")->join("left join goods as go on gb.goods_id = go.id")->findPage($page, $page_size);
        unset($list['html']);
        if ($list['data']) {
            foreach ($list['data'] as $k => &$v) {
                $v['imgs'] = array_values(unserialize($v['imgs']));
                unset($v['specs'], $v['attrs'], $v['content']);
            }
        }
        $this->code = 0;
        $this->content = array(
            'grouplist' => $list
        );
    }

    //团购
    public function groupbuy() {
        $id = Filter::int(Req::args("id"));
        $goods = $this->model->table("groupbuy as gb")->join("left join goods as go on gb.goods_id = go.id")->where("gb.id=$id")->find();
        if (isset($goods['id'])) {
            //检测团购是否结束
            if ($goods['store_nums'] <= 0 || $goods['goods_num'] >= $goods['max_num'] || time() >= strtotime($goods['end_time'])) {
                $this->model->table('groupbuy')->data(array('is_end' => 1))->where("id=$id")->update();
                $goods['is_end'] = 1;
            }
            $skumap = array();
            $products = $this->model->table("products")->fields("sell_price,market_price,store_nums,specs_key,pro_no,id")->where("goods_id = $goods[id]")->findAll();
            if ($products) {
                foreach ($products as $product) {
                    $skumap[$product['specs_key']] = $product;
                }
            }
            $attr_array = unserialize($goods['attrs']);
            $goods_attrs = array();
            if ($attr_array) {
                $rows = $this->model->fields("ga.*,av.name as vname,av.id as vid")->table("goods_attr as ga")->join("left join attr_value as av on ga.id=av.attr_id")->where("ga.type_id = $goods[type_id]")->findAll();
                $attrs = $_attrs = array();
                foreach ($rows as $row) {
                    $attrs[$row['id'] . '-' . $row['vid']] = $row;
                    $_attrs[$row['id']] = $row;
                }
                foreach ($attr_array as $key => $value) {
                    if (isset($attrs[$key . '-' . $value]))
                        $goods_attrs[] = $attrs[$key . '-' . $value];
                    else {
                        $_attrs[$key]['vname'] = $value;
                        $goods_attrs[] = $_attrs[$key];
                    }
                }
                unset($attrs, $_attrs);
            }
            $skumap = array_values($skumap);
            $goods['imgs'] = array_values(unserialize($goods['imgs']));
            $goods['specs'] = array_values(unserialize($goods['specs']));
            foreach ($goods['specs'] as $k => &$v) {
                $v['value'] = array_values($v['value']);
            }
            $goods['attrs'] = unserialize($goods['attrs']);
          
            
            $this->code = 0;
            $this->content = array(
                'seo_title' => $goods['title'],
                'id' => $id,
                'skumap' => $skumap,
                'attr_array' => $attr_array,
                'goods_attrs' => $goods_attrs,
                'goods' => $goods,
                
            );
        } else {
            $this->code = 1040;
        }
    }

    //抢购
    public function flashbuy() {
        $id = Filter::int(Req::args("id"));
        $goods = $this->model->table("flash_sale as gb")->join("left join goods as go on gb.goods_id = go.id")->where("gb.id=$id")->find();
        if ($goods) {
            //检测抢购是否结束
            if ($goods['store_nums'] <= 0 || $goods['goods_num'] >= $goods['max_num'] || time() >= strtotime($goods['end_time'])) {
                $this->model->table('flash_sale')->data(array('is_end' => 1))->where("id=$id")->update();
                $goods['is_end'] = 1;
            }
            $skumap = array();
            $products = $this->model->table("products")->fields("id,sell_price,market_price,store_nums,specs_key,pro_no,id")->where("goods_id = $goods[id]")->findAll();
            if ($products) {
                foreach ($products as $product) {
                    $skumap[$product['specs_key']] = $product;
                }
            }
            $attr_array = unserialize($goods['attrs']);
            $goods_attrs = array();
            if ($attr_array) {
                $rows = $this->model->fields("ga.*,av.name as vname,av.id as vid")->table("goods_attr as ga")->join("left join attr_value as av on ga.id=av.attr_id")->where("ga.type_id = $goods[type_id]")->findAll();
                $attrs = $_attrs = array();
                foreach ($rows as $row) {
                    $attrs[$row['id'] . '-' . $row['vid']] = $row;
                    $_attrs[$row['id']] = $row;
                }
                foreach ($attr_array as $key => $value) {
                    if (isset($attrs[$key . '-' . $value]))
                        $goods_attrs[] = $attrs[$key . '-' . $value];
                    else {
                        $_attrs[$key]['vname'] = $value;
                        $goods_attrs[] = $_attrs[$key];
                    }
                }
                unset($attrs, $_attrs);
            }
            $skumap = array_values($skumap);
            $goods['imgs'] = array_values(unserialize($goods['imgs']));
            $goods['specs'] = array_values(unserialize($goods['specs']));
            foreach ($goods['specs'] as $k => &$v) {
                $v['value'] = array_values($v['value']);
            }
            $goods['now'] = date('Y-m-d H:i:s');
            
            $goods['attrs'] = unserialize($goods['attrs']);
             //替换网址
            //$goods['content'] = str_replace("/data/uploads", "http://".$_SERVER['HTTP_HOST']."/data/uploads", $goods['content']);
            $html = '<!DOCTYPE html><html><head><title></title><meta charset="UTF-8">';
            $html.='<meta name="viewport" content="width=device-width, initial-scale=1.0"></head>';
            $html.='<body><div>'.$goods['content'].'</div></body></html>';
            $goods['content']= $html;
            $url = "http://".$_SERVER['HTTP_HOST']."/flashbuy-".$id.".html";
            $goods['shareurl']=$url;
            
            if ($this->user) {
                $area_ids = array();
                $address = $this->model->table("address")->where("user_id=" . $this->user['id'])->order("is_default desc")->findAll();
                foreach ($address as $add) {
                    $area_ids[$add['province']] = $add['province'];
                    $area_ids[$add['city']] = $add['city'];
                    $area_ids[$add['county']] = $add['county'];
                }
                $area_ids = implode(",", $area_ids);
                $areas = array();
                if ($area_ids != '')
                    $areas = $this->model->table("area")->where("id in($area_ids )")->findAll();
                $parse_area = array();
                foreach ($areas as $area) {
                    $parse_area[$area['id']] = $area['name'];
                }
                $province = $area_ids ? $area_ids[0] : 0;
                $city = $area_ids ? $area_ids[1] : 0;
                $county = $area_ids ? $area_ids[2] : 0;
                $address = implode(' ', $parse_area);
            } else {
                $province = 110000;
                $city = 110100;
                $county = 120100;
                $address = "北京市 北京市 市辖区";
            }
               //评论
            $comment = array();
            $review = array('1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0);
            $rows = $this->model->table("review")->fields("count(id) as num,point")->where("status=1 and goods_id =".$goods['id'])->group("point")->findAll();
            foreach ($rows as $row) {
                $review[$row['point']] = intval($row['num']);
            }
            $a = ($review[4] + $review[5]);
            $b = ($review[3]);
            $c = ($review[1] + $review[2]);
            $total = $a + $b + $c;
            $comment['total'] = $total;
            if ($total == 0)
                $total = 1;
            $comment['a'] = array('num' => $a, 'percent' => round((100 * $a / $total)));
            $comment['b'] = array('num' => $b, 'percent' => round((100 * $b / $total)));
            $comment['c'] = array('num' => $c, 'percent' => round((100 * $c / $total)));

            $where = "re.status=1 and re.goods_id = $id";
            $lastreview = $this->model->table("review as re")->join("left join user as us on re.user_id = us.id")->fields("re.*,re.id as id,us.name as uname,us.avatar")->where($where)->order("re.id desc")->limit("0,1")->find();
            $comment['last'] = $lastreview ? $lastreview : NULL;
            
            $this->code = 0;
            $this->content = array(
                'id' => $id,
                'skumap' => $skumap,
                'attr_array' => $attr_array,
                'goods_attrs' => $goods_attrs,
                'goods' => $goods,
                "province" => $province,
                "city" => $city,
                "county" => $county,
                "address" => $address,
                "comment" => $comment,
            );
        } else {
            $this->code = 1040;
        }
    }

    public function pointflash(){
        $id = Filter::int(Req::args("id"));
        $goods = $this->model->table("pointflash_sale as ps")->join("left join goods as go on ps.goods_id = go.id")->where("ps.id=$id")->find();
        if ($goods) {
            //检测抢购是否结束
            if ($goods['store_nums'] <= 0 || $goods['order_count'] >= $goods['max_sell_count'] || time() >= strtotime($goods['end_date'])) {
                $this->model->table('pointflash_sale')->data(array('is_end' => 1))->where("id=$id")->update();
                $goods['is_end'] = 1;
            }
            $skumap = array();
            $set = current(unserialize($goods['price_set']));
            $price_set = unserialize($goods['price_set']);
            $products = $this->model->table("products")->fields("sell_price,market_price,store_nums,specs_key,pro_no,id")->where("goods_id = $goods[id]")->findAll();
            if ($products) {
                foreach ($products as $product) {
                    $product['sell_price']=$price_set[$product['id']]['cash']."+".$price_set[$product['id']]['point']."积分";
                    $skumap[$product['specs_key']] = $product;
                }
            }
            $attr_array = unserialize($goods['attrs']);
            $goods_attrs = array();
            if ($attr_array) {
                $rows = $this->model->fields("ga.*,av.name as vname,av.id as vid")->table("goods_attr as ga")->join("left join attr_value as av on ga.id=av.attr_id")->where("ga.type_id = $goods[type_id]")->findAll();
                $attrs = $_attrs = array();
                foreach ($rows as $row) {
                    $attrs[$row['id'] . '-' . $row['vid']] = $row;
                    $_attrs[$row['id']] = $row;
                }
                foreach ($attr_array as $key => $value) {
                    if (isset($attrs[$key . '-' . $value]))
                        $goods_attrs[] = $attrs[$key . '-' . $value];
                    else {
                        $_attrs[$key]['vname'] = $value;
                        $goods_attrs[] = $_attrs[$key];
                    }
                }
                unset($attrs, $_attrs);
            }
            $skumap = array_values($skumap);
            $goods['max_num'] = $goods['max_sell_count'];
            unset($goods['max_sell_count']);
            $goods['quota_num'] = $goods['quota_count'];
            unset($goods['quota_count']);
            $goods['order_num'] = $goods['order_count'];
            $goods['send_point'] = '0.00';
            $goods['price'] = sprintf("%.2f",$set['cash']);
            $goods['description'] = '';
            $goods['start_time'] = $goods['start_date'];
            unset($goods['start_date']);
            $goods['end_time'] = $goods['end_date'];
            unset($goods['end_date']);
            $goods['goods_num'] = $goods['order_count'];
            unset($goods['order_count']);
            $goods['wants'] = '';
            $goods['wants_num'] = '0';
            $goods['tag'] = $goods['title'];
            $goods['imgs'] = array_values(unserialize($goods['imgs']));
            $goods['specs'] = array_values(unserialize($goods['specs']));
            foreach ($goods['specs'] as $k => &$v) {
                $v['value'] = array_values($v['value']);
            }
            $goods['now'] = date('Y-m-d H:i:s');
            
            $goods['attrs'] = unserialize($goods['attrs']);
             //替换网址
            //$goods['content'] = str_replace("/data/uploads", "http://".$_SERVER['HTTP_HOST']."/data/uploads", $goods['content']);
            $html = '<!DOCTYPE html><html><head><title></title><meta charset="UTF-8">';
            $html.='<meta name="viewport" content="width=device-width, initial-scale=1.0"></head>';
            $html.='<body><div>'.$goods['content'].'</div></body></html>';
            $goods['content']= $html;
            $url = "http://".$_SERVER['HTTP_HOST']."/pointflash-".$id.".html";
            $goods['shareurl']=$url;
            if ($this->user) {
                $area_ids = array();
                $address = $this->model->table("address")->where("user_id=" . $this->user['id'])->order("is_default desc")->findAll();
                foreach ($address as $add) {
                    $area_ids[$add['province']] = $add['province'];
                    $area_ids[$add['city']] = $add['city'];
                    $area_ids[$add['county']] = $add['county'];
                }
                $area_ids = implode(",", $area_ids);
                $areas = array();
                if ($area_ids != '')
                    $areas = $this->model->table("area")->where("id in($area_ids )")->findAll();
                $parse_area = array();
                foreach ($areas as $area) {
                    $parse_area[$area['id']] = $area['name'];
                }
                $province = $area_ids ? $area_ids[0] : 0;
                $city = $area_ids ? $area_ids[1] : 0;
                $county = $area_ids ? $area_ids[2] : 0;
                $address = implode(' ', $parse_area);
            } else {
                $province = 110000;
                $city = 110100;
                $county = 120100;
                $address = "北京市 北京市 市辖区";
            }
            //评论
            $comment = array();
            $review = array('1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0);
            $rows = $this->model->table("review")->fields("count(id) as num,point")->where("status=1 and goods_id =".$goods['id'])->group("point")->findAll();
            foreach ($rows as $row) {
                $review[$row['point']] = intval($row['num']);
            }
            $a = ($review[4] + $review[5]);
            $b = ($review[3]);
            $c = ($review[1] + $review[2]);
            $total = $a + $b + $c;
            $comment['total'] = $total;
            if ($total == 0)
                $total = 1;
            $comment['a'] = array('num' => $a, 'percent' => round((100 * $a / $total)));
            $comment['b'] = array('num' => $b, 'percent' => round((100 * $b / $total)));
            $comment['c'] = array('num' => $c, 'percent' => round((100 * $c / $total)));

            $where = "re.status=1 and re.goods_id = $id";
            $lastreview = $this->model->table("review as re")->join("left join user as us on re.user_id = us.id")->fields("re.*,re.id as id,us.name as uname,us.avatar")->where($where)->order("re.id desc")->limit("0,1")->find();
            $comment['last'] = $lastreview ? $lastreview : NULL;
            $this->code = 0;
            $this->content = array(
                'id' => $id,
                'skumap' => $skumap,
                'price'=> sprintf("%.2f",$set['cash']),
                'cost_point'=>$set['point'],
                'attr_array' => $attr_array,
                'goods_attrs' => $goods_attrs,
                'goods' => $goods,
                "province" => $province,
                "city" => $city,
                "county" => $county,
                "address" => $address,
                "comment" => $comment,
            );
            
        } else {
            $this->code = 1040;
        }
    }

    //捆绑
    public function bundbuy() {
        $id = Filter::int(Req::args("id"));
        $bund = $this->model->table("bundling")->where("id=$id and status = 1")->find();
        if ($bund) {
            $goods = $this->model->table("goods")->where("id in ({$bund['goods_id']})")->findAll();
            $gids = array();
            $goods_price = 0.00;
            foreach ($goods as $go) {
                $gids[] = $go['id'];
                $goods_price += $go['sell_price'];
            }
            $gids = implode(',', $gids);
            $skumap = array();
            $products = $this->model->table("products")->fields("id,sell_price,market_price,store_nums,specs_key,pro_no,id,goods_id")->where("goods_id in ($gids)")->findAll();
            if ($products) {
                foreach ($products as $product) {
                    if ($product['specs_key'])
                        $skumap[$product['specs_key'] . $product['goods_id']] = $product;
                    else
                        $skumap[$product['goods_id']] = $product;
                }
            }
            $this->code = 0;
            $this->content = array(
                'id' => $id,
                'skumap' => $skumap,
                'goods_price' => $goods_price,
                'attr_array' => $attr_array,
                'goods_attrs' => $goods_attrs,
                'goods' => $goods,
            );
        }else {
            $this->code = 1040;
        }
    }

    //取得咨询
    public function get_ask() {
        $page = Filter::int(Req::args("page"));
        $id = Filter::int(Req::args("id"));
        $list = array();
        $asks = $this->model->table("ask as ak")->fields("ak.*,ak.id as id,us.name as uname,us.avatar")->join("left join user as us on ak.user_id = us.id")->where("ak.goods_id = $id and ak.status!=2")->order('ak.id desc')->findPage($page, 10, 1, true);
        foreach ($asks['data'] as $key => $value) {
            $list[$key]['avatar'] = $value['avatar'] != '' ? Url::urlFormat("@") . $value['avatar'] : Url::urlFormat("#images/no-img.png");
            $list[$key]['uname'] = TString::msubstr($value['uname'], 0, 3, 'utf-8', '***');
        }
        $this->code = 0;
        $this->content = array(
            'asklist' => $list
        );
    }

    //取得评价
    public function get_review() {
        $page = Filter::int(Req::args("page"));
        $id = Filter::int(Req::args("id"));
        $pagetype = Filter::int(Req::args("pagetype"));
        $score = Req::args("score");
        $where = "re.status=1 and re.goods_id = $id";
        switch ($score) {
            case 'a':
                $where .= " and re.point > 3";
                break;
            case 'b':
                $where .= " and re.point = 3";
                break;
            case 'c':
                $where .= " and re.point < 3";
                break;
            default:
                break;
        }
//        $review = $this->model->table("review as re")->join("left join user as us on re.user_id = us.id left join order as od on re.order_no = od.order_no left join order_goods as og on og.order_id=od.id")->fields("re.*,re.id as id,us.name as uname,us.avatar,og.spec")->where($where)->order("re.id desc")->findPage($page, 10, $pagetype, true);
//        if($review ==null){
//            $this->code = 0;
//            $this->content = null;
//            return;
//        }
//        $list = $review['data'];
//        foreach ($list as $key => $value) {
//            $list[$key]['point'] = round($list[$key]['point'] / 5, 2) * 100;
//            $list[$key]['avatar'] = $value['avatar'] != '' ? Url::urlFormat("@") . $value['avatar'] : Url::urlFormat("#images/no-img.png");
//            $list[$key]['uname'] = TString::msubstr($value['uname'], 0, 3, 'utf-8', '***');
//            $list[$key]['spec'] = unserialize($list[$key]['spec']);
//        }
        $review = $this->model->table("review as re")
                        ->join("left join user as us on re.user_id = us.id left join order as od on re.order_no = od.order_no left join order_goods as og on (og.order_id = od.id AND og.goods_id=re.goods_id)")
                        ->fields("re.*,re.id as id,us.name as uname,us.avatar,og.spec")
                        ->where($where)->order("re.id desc")->findPage($page, 10, $pagetype, true);
        $data = $review['data'];
        if($data!=null && is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key]['point'] = round($data[$key]['point'] / 5, 2) * 100;
                $data[$key]['avatar'] = $value['avatar'] != '' ? (substr($value['avatar'], 0, 4) == 'http' ? $value['avatar'] : Url::urlFormat("@" . $value['avatar'])) : "https://unsplash.it/200/200?image=".$value['id'];
                $data[$key]['uname'] = $value['uname']!=''?TString::msubstr($value['uname'], 0, 3, 'utf-8', '***'):"u00****";
                $spec = unserialize($value['spec']);
                $speclist = array();
                if (is_array($spec)) {  
                    foreach ($spec as $sp) {
                        $speclist[] = $sp['value'][2];
                    }
                }
                $data[$key]['spec'] = implode(' / ', $speclist);
            }
        }   

        $this->code = 0;
        $this->content = array(
            'reviewlist' => $data
        );
    }
    //ghot
    public function get_hot(){
        $hot = $this->model->query("select name from tiny_tags order by num desc limit 0,30");
        $list = array();
        if(!empty($hot)){
           foreach ($hot as $k => $v){
            $list[] = $v['name'];
          } 
        }
        $this->code = 0;
        $this->content=$list;
    }
    public function guess(){
        $num   = Filter::int(Req::args('num'));
        if($num =='' || $num>50){
            $num=5;
        }
        $like   =  $this->model->query("select id,name,img,sell_price,market_price from tiny_goods where is_online=0 order by rand() limit $num");
        $this->code=0;
        $this->content =$like;
    }
    
    public function next_flash(){
        $page = Filter::int(Req::args("page"));
        $page = $page < 0 ? 1 : $page;
        $page_size = 10;
        $next = date('Y-m-d 00:00:00',  strtotime('+1 day'));
        $now  = date('Y-m-d H:i:s');
        $list = $this->model->table("flash_sale as gb")->fields("*,gb.id as id")->order("gb.start_time asc")->join("left join goods as go on gb.goods_id = go.id")->where("gb.start_time between '$now' and '$next'")->findPage($page, $page_size);
        unset($list['html']);
        if ($list['data']) {
            foreach ($list['data'] as $k => &$v) {
                $v['imgs'] = array_values(unserialize($v['imgs']));
                unset($v['specs'], $v['attrs'], $v['content']);
            }
        }
        $this->code = 0;
        $this->content = array(
            'flashlist' => $list
        );
        if(isset($list['data'][0]['start_time'])){
            $this->content['start_time'] = $list['data'][0]['start_time'];
            $this->content['now'] = date('Y-m-d H:i:s');
        }else{
            $this->content['start_time'] = date('Y-m-d H:i:s');
            $this->content['now'] = date('Y-m-d H:i:s');
        }

        // $page = Filter::int(Req::args("page"));
        // if(!$page){
        //     $page = 1;
        // }
        // $page = $page < 0 ? 1 : $page;
        // $now  = date('Y-m-d H:i:s');
        // //更新状态
        // $result1 = $this->model->table('flash_sale')->data(array('is_end'=>1))->where("is_end=0 and end_time < '$now'")->update();
        // $result2 = $this->model->table('pointflash_sale')->data(array('is_end'=>1))->where("is_end=0 and end_date < '$now'")->update();
        
        // $first = $this->model->table("flash_sale as gb")->fields("*,gb.id as id")->order("gb.is_end asc,gb.end_time asc")->join("left join goods as go on gb.goods_id = go.id")->findPage(1,1);
        // $list1 = $this->model->table("pointflash_sale as gb")->fields("*,gb.id as id")->order("gb.is_end asc,gb.id desc")->join("left join goods as go on gb.goods_id = go.id")->where("gb.start_date>'$now'")->findAll();
        // $list2 = $this->model->table("flash_sale as gb")->fields("*,gb.id as id")->order("gb.is_end asc,gb.id desc")->join("left join goods as go on gb.goods_id = go.id")->where("gb.start_time>'$now'")->findAll();
        // if($list1){
        //     foreach($list1 as $k=>$v){
        //         $list1[$k]['tag'] = $v['title'];
        //         $list1[$k]['max_num'] = $v['max_sell_count'];
        //         $list1[$k]['start_time'] = $v['start_date'];
        //         $list1[$k]['end_time'] = $v['end_date'];
        //         $list1[$k]['order_num'] = $v['order_count'];
        //         $list1[$k]['quota_num'] = $v['quota_count'];
        //         $set = current(unserialize($v['price_set']));
        //         $list1[$k]['price'] = sprintf("%.2f",$set['cash']);
        //         $list1[$k]['send_point'] = '0.00';
        //         $list1[$k]['description'] = '';
        //         $list1[$k]['goods_num'] = $v['max_sell_count'];
        //         $list1[$k]['wants'] = '';
        //         $list1[$k]['wants_num'] = '0';
        //         $list1[$k]['cost_point'] = $set['point'];
        //         $list1[$k]['flash_type'] = 'point';
        //         unset($list1[$k]['max_sell_count']);
        //         unset($list1[$k]['start_date']);
        //         unset($list1[$k]['end_date']);
        //         unset($list1[$k]['order_count']);
        //         unset($list1[$k]['quota_count']);
        //         unset($list1[$k]['price_set']);
        //     }
        // }
        // if($list2){
        //     foreach($list2 as $k=>$v){
        //         $list2[$k]['cost_point'] = '0.00';
        //         $list2[$k]['flash_type'] = 'cash';
        //     }
        // }
        // $list = array_merge($list1,$list2);
        // $total=count($list);//总条数  
        // $num=10;//每页显示条数  
        
        // $list = array_slice($list, ($page-1)*$num, $num);
        
        // if ($list) {
        //     foreach ($list as $k => &$v) {
        //         $v['imgs'] = array_values(unserialize($v['imgs']));
        //         unset($v['specs'], $v['attrs'], $v['content']);
        //     }
        // }
        // $newpage = array(
        //     'total'=>$total,
        //     'totalPage'=>ceil($total/$num),
        //     'pageSize'=>$num,
        //     'page'=>$page
        //     );
        // $this->code = 0;
        // $this->content = array(
        //     'flashlist' => array(
        //          'data'=>$list,
        //          'page'=>$newpage
        //         ),
        //     );
        // if(isset($first['data'][0]['start_time'])){
        //     $this->content['start_time'] = $first['data'][0]['start_time'];
        //     $this->content['now'] = date('Y-m-d H:i:s');
        // }else{
        //     $this->content['start_time'] = date('Y-m-d H:i:s');
        //     $this->content['now'] = date('Y-m-d H:i:s');
        // }
    }
    public function want(){
        $id   = Filter::int(Req::args("id"));
        $wants = $this->model->query("select * from tiny_flash_sale where id =$id and is_end=0");
        if(!empty($wants)){
             if($wants[0]['wants']==''){
                 $this->code =0;
                 $this->model->query("update tiny_flash_sale set wants = '".$this->user['id'].",',wants_num = wants_num +1 where id =$id" );
                 $this->content['wants_num'] =1;
              }else{
                $wants_arr = explode(',', trim($wants[0]['wants'],','));
                if(in_array($this->user['id'], $wants_arr)){
                    $this->code = 1095;
                }else{
                $this->model->query("update tiny_flash_sale set wants = CONCAT(wants,'".$this->user['id'].",'),wants_num = wants_num +1");
                $this->code =0;
                $this->content['wants_num'] = count($wants_arr)+1;
            }
          }
          if($this->code == 0){
              $notice = new NoticeService();
              $jpush  = $notice->getNotice('jpush');
              $audience['alias']=array($this->user['id']);
              if(strlen($wants[0]['title'])>35){
                  $title=mb_substr($wants[0]['title'],0,15);
              }else{
                  $title=$wants[0]['title'];
              }
              $jpush->setSingleScheduleData('flash_notify',date('Y-m-d H:i:s',  (strtotime($wants[0]['start_time'])-60*5)), 'all', $audience, 'want_flash',$id, "亲，您想要的秒杀-->".$title."<--马上就要开抢咯！~");
              $jpush->schedule();
          }
        }     
    }
    
    public function huabiPageRecommend(){
        $page = Filter::sql(Req::args('page'));
        
        $result = $this->model->table("goods")->where("is_online =0 and is_huabipay = 1 and huabipay_sort >0")->fields("id,name,img,sell_price,market_price,store_nums,huabipay_set")->order('huabipay_sort desc')->findPage($page,10);
        if(isset($result['html'])){
            unset($result['html']);
        }
        $other = Config::getInstance()->get("other");
        $rmb2huabi = $other['rmb2huabi'];
        if(isset($result['data'])&&!empty($result['data'])){
            foreach ($result['data'] as $k=>$v){
                if($v['huabipay_set']!=""){
                    $set = unserialize($v['huabipay_set']);
                    if(!is_array($set)){
                        unset($result['data'][$k]);
                        break; 
                    }else{
                           //取第一条设置
                           usort($set, function($a,$b){
                            $atype = $a['type'];
                            $btype = $b['type'];
                            if($atype==$btype){
                               if($atype=='rate'){
                                   if($a['value']==$b['value']){
                                       return 0;
                                   }
                                   return ($a['value']>$b['value'])?-1:1;
                               }else if($atype=='fixed'){
                                   if($a['value']['huadian']==$b['value']['huadian']){
                                       return 0;
                                   }
                                    return ($a['value']['huadian']>$b['value']['huadian'])?-1:1;
                               }
                            }else{
                                if($atype=='fixed'){
                                    return 1;
                                }else{
                                    return -1;
                                }
                            }
                        });
                        $first = current($set);
                        if(isset($first['type'])){
                             if($first['type']=='rate'){
                                 $huabipay_amount = round($v['sell_price']*$first['value']/100*$rmb2huabi);
                                 $stillpay = round($v['sell_price']*(100-$first['value'])/100,2);
                                 $stillpay = ($stillpay >=0)? $stillpay : 0; 
                             }else if($first['type']=='fixed'){
                                 $huabipay_amount = round($first['value']['huadian']);
                                 $stillpay = round($first['value']['rmb'],2);
                                 $stillpay = ($stillpay >=0)? $stillpay : 0;
                             }
                        }else{
                             unset($result['data'][$k]);
                             break;
                        }
                        $result['data'][$k]['huabipay_amount'] = $huabipay_amount;
                        $result['data'][$k]['still_pay'] = $stillpay;
                        //unset($result['data'][$k]['huabipay_set']);
                    }
                }else{
                    unset($result['data'][$k]);
                    break;
                }
           }
        }
        $this->code =0;
        $this->content = $result;
        
    }
    
    public function huabiPageGoods(){
        $page = Filter::sql(Req::args("page"));
        $result = $this->model->table("goods")->where("is_online = 0 and is_huabipay = 1 and huabipay_sort =0 ")->fields("id,name,img,sell_price,market_price,store_nums,huabipay_set")->findPage($page,10);
        if(isset($result['html'])){
            unset($result['html']);
        }
        $other = Config::getInstance()->get("other");
        $rmb2huabi = $other['rmb2huabi'];
        if(isset($result['data'])&&!empty($result['data'])){
            foreach ($result['data'] as $k=>$v){
               if($v['huabipay_set']!=""){
                    $set = unserialize($v['huabipay_set']);
                    if(!is_array($set)){
                        unset($result['data'][$k]);
                        break; 
                    }else{
                           //取第一条设置
                           usort($set, function($a,$b){
                            $atype = $a['type'];
                            $btype = $b['type'];
                            if($atype==$btype){
                               if($atype=='rate'){
                                   if($a['value']==$b['value']){
                                       return 0;
                                   }
                                   return ($a['value']>$b['value'])?-1:1;
                               }else if($atype=='fixed'){
                                   if($a['value']['huadian']==$b['value']['huadian']){
                                       return 0;
                                   }
                                    return ($a['value']['huadian']>$b['value']['huadian'])?-1:1;
                               }
                            }else{
                                if($atype=='fixed'){
                                    return 1;
                                }else{
                                    return -1;
                                }
                            }
                        });
                        $first = current($set);
                        if(isset($first['type'])){
                             if($first['type']=='rate'){
                                 $huabipay_amount = round($v['sell_price']*$first['value']/100*$rmb2huabi);
                                 $stillpay = round($v['sell_price']*(100-$first['value'])/100,2);
                                 $stillpay = ($stillpay >=0)? $stillpay : 0; 
                             }else if($first['type']=='fixed'){
                                 $huabipay_amount = round($first['value']['huadian']);
                                 $stillpay = round($first['value']['rmb'],2);
                                 $stillpay = ($stillpay >=0)? $stillpay : 0;
                             }
                        }else{
                             unset($result['data'][$k]);
                             break;
                        }
                        $result['data'][$k]['huabipay_amount'] = $huabipay_amount;
                        $result['data'][$k]['still_pay'] = $stillpay;
                        //unset($result['data'][$k]['huabipay_set']);
                    }
                }else{
                    unset($result['data'][$k]);
                    break;
                }
           }
        }
        $this->code =0;
        $this->content = $result;
        
    }
    
    public function point_sale(){
        $page = Filter::int(Req::args('page'));
        $point_sale = $this->model->table("point_sale as ps")
                ->join("goods as g on ps.goods_id = g.id")
                ->where("ps.status = 1 and g.store_nums >0")
                ->fields("ps.id,ps.price_set,ps.is_adjustable,ps.listorder,ps.goods_id,g.name,g.img,g.sell_price,g.subtitle")
                ->order("ps.listorder")
                ->findPage($page,10);
        if(isset($point_sale['html'])){
            unset($point_sale['html']);
        }
        if(!empty($point_sale['data'])){
            foreach($point_sale['data'] as $k=>$v){
                $point_sale['data'][$k]['price_set']=  array_values(unserialize($v['price_set']));
            }
        }
        $this->code = 0;
        $this->content = $point_sale;
    }
    //抢购
    public function pointbuy() {
        $id = Filter::int(Req::args("id"));
        $goods = $this->model->table("point_sale as ps")->fields("ps.price_set,go.*")->join("left join goods as go on ps.goods_id = go.id")->where("ps.id=$id and ps.status=1")->find();
        if ($goods) {
            $skumap = array();
            $products = $this->model->table("products")->fields("id,sell_price,market_price,store_nums,specs_key,pro_no,id")->where("goods_id = $goods[id]")->findAll();
            if ($products) {
                $price_set = unserialize($goods['price_set']);
                foreach ($products as $product) {
                    $product['cash'] = $price_set[$product['id']]['cash'];
                    $product['point'] =$price_set[$product['id']]['point'];
                    $skumap[$product['specs_key']] = $product;
                }
            }
            $attr_array = unserialize($goods['attrs']);
            $goods_attrs = array();
            if ($attr_array) {
                $rows = $this->model->fields("ga.*,av.name as vname,av.id as vid")->table("goods_attr as ga")->join("left join attr_value as av on ga.id=av.attr_id")->where("ga.type_id = $goods[type_id]")->findAll();
                $attrs = $_attrs = array();
                foreach ($rows as $row) {
                    $attrs[$row['id'] . '-' . $row['vid']] = $row;
                    $_attrs[$row['id']] = $row;
                }
                foreach ($attr_array as $key => $value) {
                    if (isset($attrs[$key . '-' . $value]))
                        $goods_attrs[] = $attrs[$key . '-' . $value];
                    else {
                        $_attrs[$key]['vname'] = $value;
                        $goods_attrs[] = $_attrs[$key];
                    }
                }
                unset($attrs, $_attrs);
            }
            $skumap = array_values($skumap);
            $goods['imgs'] = array_values(unserialize($goods['imgs']));
            $goods['specs'] = array_values(unserialize($goods['specs']));
            foreach ($goods['specs'] as $k => &$v) {
                $v['value'] = array_values($v['value']);
            }
            $goods['now'] = date('Y-m-d H:i:s');
            
            $goods['attrs'] = unserialize($goods['attrs']);
            //替换网址
            //$goods['content'] = str_replace("/data/uploads", "http://".$_SERVER['HTTP_HOST']."/data/uploads", $goods['content']);
            $html = '<!DOCTYPE html><html><head><title></title><meta charset="UTF-8">';
            $html.='<meta name="viewport" content="width=device-width, initial-scale=1.0"></head>';
            $html.='<body><div>'.$goods['content'].'</div></body></html>';
            $goods['content']= $html;
            $url = "http://".$_SERVER['HTTP_HOST']."/pointbuy-".$id.".html";
            $goods['shareurl']=$url;
            
            if ($this->user) {
                $area_ids = array();
                $address = $this->model->table("address")->where("user_id=" . $this->user['id'])->order("is_default desc")->findAll();
                foreach ($address as $add) {
                    $area_ids[$add['province']] = $add['province'];
                    $area_ids[$add['city']] = $add['city'];
                    $area_ids[$add['county']] = $add['county'];
                }
                $area_ids = implode(",", $area_ids);
                $areas = array();
                if ($area_ids != '')
                    $areas = $this->model->table("area")->where("id in($area_ids )")->findAll();
                $parse_area = array();
                foreach ($areas as $area) {
                    $parse_area[$area['id']] = $area['name'];
                }
                $province = $area_ids ? $area_ids[0] : 0;
                $city = $area_ids ? $area_ids[1] : 0;
                $county = $area_ids ? $area_ids[2] : 0;
                $address = implode(' ', $parse_area);
            } else {
                $province = 110000;
                $city = 110100;
                $county = 120100;
                $address = "北京市 北京市 市辖区";
            }
               //评论
            $comment = array();
            $review = array('1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0);
            $rows = $this->model->table("review")->fields("count(id) as num,point")->where("status=1 and goods_id =".$goods['id'])->group("point")->findAll();
            foreach ($rows as $row) {
                $review[$row['point']] = intval($row['num']);
            }
            $a = ($review[4] + $review[5]);
            $b = ($review[3]);
            $c = ($review[1] + $review[2]);
            $total = $a + $b + $c;
            $comment['total'] = $total;
            if ($total == 0)
                $total = 1;
            $comment['a'] = array('num' => $a, 'percent' => round((100 * $a / $total)));
            $comment['b'] = array('num' => $b, 'percent' => round((100 * $b / $total)));
            $comment['c'] = array('num' => $c, 'percent' => round((100 * $c / $total)));

            $where = "re.status=1 and re.goods_id = $id";
            $lastreview = $this->model->table("review as re")->join("left join user as us on re.user_id = us.id")->fields("re.*,re.id as id,us.name as uname,us.avatar")->where($where)->order("re.id desc")->limit("0,1")->find();
            $comment['last'] = $lastreview ? $lastreview : NULL;
            
            $this->code = 0;
            $this->content = array(
                'id' => $id,
                'skumap' => $skumap,
                'attr_array' => $attr_array,
                'goods_attrs' => $goods_attrs,
                'goods' => $goods,
                "province" => $province,
                "city" => $city,
                "county" => $county,
                "address" => $address,
                "comment" => $comment,
            );
        } else {
            $this->code = 1148;
        }
    }
    
}
