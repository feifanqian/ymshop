<?php

class IndexAction extends Controller {

    public $model = null;
    public $user = null;
    public $code = 1000;
    public $content = NULL;

    public function __construct() {
        $this->model = new Model();
    }

    public function index() {
        $now  = date('Y-m-d H:i:s');
        $list1 = $this->model->table("pointflash_sale as gb")->fields("*,gb.id as id")->order("gb.is_end asc,gb.id desc")->join("left join goods as go on gb.goods_id = go.id")->limit(10)->findAll();
        $list2 = $this->model->table("flash_sale as gb")->fields("*,gb.id as id")->order("gb.is_end asc,gb.id desc")->join("left join goods as go on gb.goods_id = go.id")->limit(10)->findAll();
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
        $flashlist = array_merge($list1,$list2);
        if ($flashlist) {
            foreach ($flashlist as $k => &$v) {
                $v['imgs'] = array_values(unserialize($v['imgs']));
                unset($v['specs'], $v['attrs'], $v['content']);
            }
        }

        
        $this->code = 0;
        $this->content = array(
            'flashlist' => $flashlist,
        );
        if(isset($flashlist[0]['end_time'])){
            $this->content['end_time'] = $flashlist[0]['end_time'];
            $this->content['now'] = date('Y-m-d H:i:s');
        }else{
            $this->content['end_time'] = date('Y-m-d H:i:s');
            $this->content['now'] = date('Y-m-d H:i:s');
        } 
    }
    
    // public function index() {
    //     $now  = date('Y-m-d H:i:s');
    //     $items = $this->model->table("flash_sale as gb")->fields("*,gb.id as id")->where("gb.start_time<'$now'")->order("gb.is_end asc,gb.end_time asc")->join("left join goods as go on gb.goods_id = go.id")->findPage(1, 10);
        
    //     $flashlist = array();
    //     if(isset($items['data'])&&!empty($items['data'])){
    //         foreach ($items['data'] as $k => $v) {
    //             $v['imgs'] = unserialize($v['imgs']);
    //             unset($v['specs'], $v['content']);
    //             $flashlist[] = $v;
    //         }
    //     }
    //     $this->code = 0;
    //     $this->content = array(
    //         'flashlist' => $flashlist,
    //     );
    //     if(isset($flashlist[0]['end_time'])){
    //         $this->content['end_time'] = $flashlist[0]['end_time'];
    //         $this->content['now'] = date('Y-m-d H:i:s');
    //     }else{
    //         $this->content['end_time'] = date('Y-m-d H:i:s');
    //         $this->content['now'] = date('Y-m-d H:i:s');
    //     } 
    // }
    
    public function index_goods(){
        $page = Filter::int(Req::args('page'));
        if($page<1){
            $page = 1;
        }
        $randlist = array();
        $items = $this->model->table("goods")->where("is_online = 0")->order("sort desc")->findPage($page, 9);
        
        if(empty($items)){
            $this->code=0;
            $this->content=null;
            return;
        }
        foreach ($items['data'] as $k => $v) {
            $v['imgs'] = unserialize($v['imgs']);
            unset($v['specs'], $v['content']);
            $randlist[] = $v;
        }
        $page_info['page_count']=$items['page']['totalPage'];
        $page_info['current_page']=$page;
        $this->code=0;
        $this->content['goods']=$randlist;
        $this->content['page_info']=$page_info;
    }
    
    //APP端banner
    public function banner(){
        $banner = $this->model->query("select * from tiny_ad where id =56  and is_open = 1");
        $banner[0]['content'] = unserialize($banner[0]['content']);
        foreach ($banner[0]['content'] as $k=>$v){
            if($banner[0]['content'][$k]['url']!=''){
                $banner[0]['content'][$k]['url'] = json_decode($banner[0]['content'][$k]['url'],true);
            }else{
                $banner[0]['content'][$k]['url'] =array('type'=>'','type_value'=>'');
            }
        }
        return $banner[0];
    }
    //积分页banner
    public function bp_banner(){
        $banner = $this->model->query("select * from tiny_ad where id =55  and is_open = 1");
        $banner[0]['content'] = unserialize($banner[0]['content']);
        foreach ($banner[0]['content'] as $k=>$v){
            if($banner[0]['content'][$k]['url']!=''){
                $banner[0]['content'][$k]['url'] = json_decode($banner[0]['content'][$k]['url'],true);
            }else{
                $banner[0]['content'][$k]['url'] =array('type'=>'','type_value'=>'');
            }
        }
       $this->code = 0;
       $this->content = $banner[0];
    }
    public function category_ad(){
        $info = $this->model->query("select * from tiny_ad where id between 34 and  39 and is_open = 1");
        foreach ($info as $k=>$v){
            $match = array();
            preg_match_all('/-(\d+)/', $v['name'], $match);
            $info[$k]['category_id'] = $match[1][0];
            $c = $this->model->query("select name,alias from tiny_goods_category where id =".$match[1][0]);
            $info[$k]['category_name']=$c[0]['name'];
            $info[$k]['content']=  unserialize($info[$k]['content']);
            foreach ($info[$k]['content'] as $kk=>$vv){
          }
        }
        $this->code =0;
        $this->content = $info;
    }
     public function get_category() {
         $id = Filter::int(Req::args('id'));
         if($id==''){
             $id=0;
         }
         $cache = CacheFactory::getInstance();
         $items = $cache->get("_GoodsCategory".$id);
         if ($cache->get("_GoodsCategory".$id) === null) {
            $items = $this->_CategoryInit($id);
            $cache->set("_GoodsCategory".$id, $items, 315360000);
         }
         $this->code = 0;
         $this->content=$items;
    }
     private function _CategoryInit($id=0, $level = '0') {
        $result = $this->model->table('goods_category')->where("parent_id=" . $id)->order("sort desc")->findAll();
        $list = array();
        if ($result) {
            foreach ($result as $key => $value) {
                $id = $value['id'];
                $list[$key]['id'] = $value['id'];
                $list[$key]['pid'] = $value['parent_id'];
                $list[$key]['title'] = $value['name'];
                $list[$key]['level'] = $level;
                $list[$key]['path'] = $value['path'];
                $list[$key]['img'] = $value['img'];
                $list[$key]['imgs'] = $value['imgs'];
                $list[$key]['alias'] = $value['alias'];
                $list[$key]['nav_show'] = $value['nav_show'];
                $list[$key]['list_show'] = $value['list_show'];
                $list[$key]['apptitle'] = $value['apptitle'];
                $list[$key]['adimg'] = $value['adimg'];
                if($value['id']==1){
                    $list[$key]['child'] = $this->get_push();
                }else{
                    $list[$key]['child'] = $this->_CategoryInit($value['id'], $level + 1);
                }
                
            } 
        }
        return $list;
    }
    
    public function get_recommend(){
         $ad = $this->model->query("select content as imgs from tiny_ad where id = 54 and is_open =1");
          foreach ($ad as $kk => $vv){
             $ad[$kk]['imgs'] = unserialize($ad[$kk]['imgs']);
            foreach ($ad[$kk]['imgs'] as $k=>$v){
                if($ad[$kk]['imgs'][$k]['url']!=''){
                    $ad[$kk]['imgs'][$k]['url']=  json_decode($ad[$kk]['imgs'][$k]['url'],true);
                }else{
                    $ad[$kk]['imgs'][$k]['url']=array('type'=>'','type_value'=>'');
                }
            }
        }
        return $ad[0];
    }
     //APP端广告
    public function ad(){
        $ad = $this->model->query("select content from tiny_ad where id in (52,53,81)  and is_open = 1");
        $arr = array();
        foreach ($ad as $kk => $vv){
             $ad[$kk]['content'] = unserialize($ad[$kk]['content']);
            foreach ($ad[$kk]['content'] as $k=>$v){
                if($ad[$kk]['content'][$k]['url']!=''){
                    $ad[$kk]['content'][$k]['url'] = json_decode($ad[$kk]['content'][$k]['url'],true);
                }else{
                    $ad[$kk]['content'][$k]['url']=array('type'=>'','type_value'=>'');
                }
                
                $arr['imgs'][]= $ad[$kk]['content'][$k];
            }
        }
       
        return $arr;
    }
    public function get_index_ad(){
        $this->content['banner']= $this->banner();
        $this->content['ad']=$this->ad();
        $this->content['recommend']=$this->get_recommend();
        $this->code =0;
    }
    public function get_push(){
        $push = $this->model->query("select * from tiny_goods_category where recommend =1");
        if(!empty($push)){
            $level = 1;
            foreach($push as $key =>$value){
            $push[$key]['id'] = $value['id'];
            $push[$key]['pid'] = $value['parent_id'];
            $push[$key]['title'] = $value['name'];
            $push[$key]['level'] = $level;
            $push[$key]['path'] = $value['path'];
            $push[$key]['img'] = $value['img'];
            $push[$key]['imgs'] = $value['imgs'];
            $push[$key]['alias'] = $value['alias'];
            $push[$key]['nav_show'] = $value['nav_show'];
            $push[$key]['list_show'] = $value['list_show'];
            $push[$key]['apptitle'] = $value['apptitle'];
            $push[$key]['adimg'] = $value['adimg'];
            $push[$key]['child'] = $this->_CategoryInit($value['id'], $level + 1);
            }
            return $push;
        }
        return null;
    }
    
    public function getWithdrawSet(){
        $type = Filter::str(Req::args('type'));
        if($type=='balance'){
            $config = Config::getInstance();
            $other = $config->get("other");
            $this->code = 0;
            $this->content['withdraw_fee_rate'] = $other['withdraw_fee_rate'];
            $this->content['min_withdraw_amount'] = $other['min_withdraw_amount'];
        }else if($type=='district'){
            $config = Config::getInstance();
            $other = $config->get("district_set");
            $this->code = 0;
            $this->content['withdraw_fee_rate'] = $other['withdraw_fee_rate'];
            $this->content['min_withdraw_amount'] = $other['min_withdraw_amount'];
        }else{
            $this->code =1000;
        }
    }
    
    public function package_info(){
        $pid = Filter::int(Req::args('pid'));
        $config = Config::getInstance();
        $other = $config->get("other");
        $package_set = $config->get("recharge_package_set"); 
        if(in_array($pid, explode("|", $package_set[1]['gift']))){
            $this->content['package']=1;
        }else if(in_array($pid, explode("|", $package_set[2]['gift']))){
            $this->content['package']=2;
        }else{
            $this->code = 1000;
            return;
        }
        $product_infos= $this->model->table("products")->where("id=$pid")->fields("goods_id")->find();
        $id = $product_infos['goods_id'];
        $goods = $this->model->table("goods as go")->join("left join tiny_goods_category as gc on go.category_id = gc.id")->fields("go.*,gc.path")->where("go.id=$id and go.is_online=0")->find();
        if ($goods) {
            if(isset($goods['imgs'])){ 
                $goods['imgs']=  unserialize($goods['imgs']);
            }
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
            
            if ($goods['seo_title'] != '')
                $seo_title = $goods['seo_title'];
            else if ($goods['name'] != '')
                $seo_title = $goods['name'];
            $this->content['pid']=$pid;
            $this->content['goods']=$goods;
            $this->content['goods_attrs']=$goods_attrs;
            $this->code =0;
        } else {
            $this->code = 1000;
        }
    }

    //获取套餐设置
    public function recharge_package_set(){
       $config = Config::getInstance();
       $package_set = $config->get('recharge_package_set');
       if($package_set){
           if(isset($package_set[4]['gift'])&&$package_set[4]['gift']!=''){
                $where = implode(',', array_reverse(explode("|", $package_set[4]['gift'])));
                $select4 = $this->model->table("products as p")->join("goods as g on p.goods_id=g.id")->where("p.id in ({$where})")->fields("p.id,g.img,g.name")->order("field(p.id,$where)")->findAll();
                $package_set['gift']=$select4;
            }
           $this->code =0;
           $this->content = $package_set;
       }else{
           $this->code= 1005;
       }
    }

    //ios版本更新检测
    public function version_update(){
        $current_version = Req::args("current_version");
        if(!$current_version){
            $this->code = 1216;
            return;
        }
        $str = str_replace('.', '', $current_version);
        $version = $this->model->table("version")->fields('enforce,newversion,packagesize,downloadurl,content')->where("binary platform ='ios'")->order("id desc")->find();
        if($version){
            $newversion_str = str_replace('.', '', $version['newversion']);
            if($str>=$newversion_str){
                $version['enforce'] = 0;
            }    
        }
        $this->code =0;
        $this->content = $version;
    }

    //新导航分类列表
    public function category_nav(){
        $list = $this->model->table('goods_category')->fields('id,name,ad_img')->where('parent_id=0 and id!=1')->findAll();
        foreach($list as $k=>$v){
            $list[$k]['type'] = 'category';
            $list[$k]['adimg'] = $v['ad_img'];
            unset($list[$k]['ad_img']);
        }
        $new = array(
                'id'=>'0',
                'name'=>'全部分类',
                'adimg'=>'https://ymlypt.b0.upaiyun.com/data/uploads/2018/03/08/457bdda9eb7d103edfd21c0f523fa584.png',
                'type'=>'classify'
                );
        array_unshift($list, $new);
        $new1 = array(
                'id'=>'1',
                'name'=>'微商专区',
                'adimg'=>'https://ymlypt.b0.upaiyun.com/data/uploads/2018/03/08/23a77be36c12d55a996649ef96d01d70.png',
                'type'=>'mini_shop'
                );
        array_push($list, $new1);
        // $new2 = array(
        //         'id'=>'999',
        //         'name'=>'附近',
        //         'adimg'=>'https://ymlypt.b0.upaiyun.com/data/uploads/2018/03/08/457bdda9eb7d103edfd21c0f523fa584.png',
        //         'type'=>'nearby'
        //         );
        // array_push($list, $new2);
        $this->code = 0;
        $this->content = $list;

    }

    public function index_area(){
        $now  = date('Y-m-d H:i:s');
        // $list1 = $this->model->table("pointflash_sale as gb")->fields("*,gb.id as id")->order("gb.is_end asc,gb.id desc")->join("left join goods as go on gb.goods_id = go.id")->limit(10)->findAll();
        $list2 = $this->model->table("flash_sale as gb")->fields("*,gb.id as id")->order("gb.is_end asc,gb.id desc")->join("left join goods as go on gb.goods_id = go.id")->limit(10)->findAll();
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
        if($list2){
            foreach($list2 as $k=>$v){
                $list2[$k]['cost_point'] = '0.00';
                $list2[$k]['flash_type'] = 'cash';
            }
        }
        // $flashlist = array_merge($list1,$list2);
        $flashlist = $list2;
        if ($flashlist) {
            foreach ($flashlist as $k => &$v) {
                // if(!empty($v['imgs'])){
                    $v['imgs'] = array_values(unserialize($v['imgs']));
                // }else{
                //     $v['imgs'] = '';
                // }
                unset($v['specs'], $v['attrs'], $v['content']);
                // if($flashlist[$k]['id']==328){
                //    unset($flashlist[$k]);
                // }
            }
            array_pop($flashlist);
        }
        
        $ad1 = $this->model->table('ad')->fields('content')->where('id=52')->find();
        $imgs1 = unserialize($ad1['content']);
        $imgs1[0]['url'] = json_decode($imgs1[0]['url'],true);
        $ad2 = $this->model->table('ad')->fields('content')->where('id=53')->find();
        $imgs2 = unserialize($ad2['content']);
        $imgs2[0]['url'] = json_decode($imgs2[0]['url'],true);
        $ad3 = $this->model->table('ad')->fields('content')->where('id=81')->find();
        $imgs3 = unserialize($ad3['content']);
        $imgs3[0]['url'] = json_decode($imgs3[0]['url'],true);
        $flash=array(
            'imgs'=>$imgs1[0],
            'list'=>$flashlist,
            'end_time'=>isset($flashlist[0]['end_time'])?$flashlist[0]['end_time']:date('Y-m-d H:i:s'),
            'now'=>date('Y-m-d H:i:s')
            );

        $point_list = $this->model->table("point_sale as ps")
                ->join("goods as g on ps.goods_id = g.id")
                ->where("ps.status = 1 and g.store_nums >0")
                ->fields("ps.id,ps.price_set,ps.is_adjustable,ps.listorder,ps.goods_id,g.name,g.img,g.sell_price,g.subtitle")
                ->order("ps.listorder")
                ->limit(10)
                ->findAll();
        if(!empty($point_list)){
            foreach($point_list as $k=>$v){
                $point_list[$k]['price_set']=  array_values(unserialize($v['price_set']));
                $point_list[$k]['price']=  $point_list[$k]['price_set'][0]['cash'];
                $point_list[$k]['cost_point']=  $point_list[$k]['price_set'][0]['point'];
            }
        }
        $point = array(
            'imgs'=>$imgs2[0],
            'list'=>$point_list
            );
        $groupbuy = $this->model->table('groupbuy as g')->fields('g.id,g.goods_id,o.name,o.img,o.sell_price,g.price,g.min_num')->join('left join goods as o on g.goods_id = o.id')->where('g.is_end = 0')->order('g.id desc')->limit(10)->findAll();
        if($groupbuy) {
            foreach ($groupbuy as $k => $v) {
                $groupbuy[$k]['price_set'] = [];
                $groupbuy[$k]['is_adjustable'] = 0;
                $groupbuy[$k]['listorder'] = $k;
                $groupbuy[$k]['subtitle'] = '';
                $groupbuy[$k]['cost_point'] = 0;   
                // $groupbuy = array_values($groupbuy);
            }
        }
        $groupbuy=array(
            'imgs'=>$imgs3[0],
            'list'=>$groupbuy,
            'end_time'=>isset($groupbuy[0]['end_time'])?$groupbuy[0]['end_time']:date('Y-m-d H:i:s'),
            'now'=>date('Y-m-d H:i:s')
            );
        $content = array(
            'flash'=>$flash,
            'point'=>$point,
            'groupbuy'=>$groupbuy
            );
        $this->code = 0;
        $this->content = $content;
    }

    public function index_category(){
        $list = $this->model->table('goods_category')->fields('id,name,title_img,font_color,ad_position')->where('parent_id=0 and id!=1')->order('sort asc')->findAll();


        $ad = $this->model->query("select content from tiny_ad where id>=64 and id<=71  and is_open = 1");
        foreach ($ad as $kk => $vv){
             $ad[$kk]['content'] = unserialize($ad[$kk]['content']);
            foreach ($ad[$kk]['content'] as $k=>$v){
                if($ad[$kk]['content'][$k]['url']!=''){
                    $ad[$kk]['content'][$k]['url'] = json_decode($ad[$kk]['content'][$k]['url'],true);
                }else{
                    $ad[$kk]['content'][$k]['url']=array('type'=>'','type_value'=>'');
                }
            }
        }

        $banner = $this->model->query("select content from tiny_ad where id>=72 and id<=79  and is_open = 1");
        foreach ($banner as $kk => $vv){
             $banner[$kk]['content'] = unserialize($banner[$kk]['content']);
             $banner[$kk]['content'] = $banner[$kk]['content'][0];
             if($banner[$kk]['content']['url']!=''){
                    $banner[$kk]['content']['url'] = json_decode($banner[$kk]['content']['url'],true);
                }else{
                    $banner[$kk]['content']['url']=array('type'=>'','type_value'=>'');
                }
        }
        
        foreach($list as $k=>$v){
               $list[$k]['imgs'] = $ad[$k]['content'];
               $list[$k]['banner_img'] = $banner[$k]['content']; 
        }
        foreach($list as $k => $v){
            $list[$k]['img_num'] = count($list[$k]['imgs']);
        }
        $this->code = 0;
        $this->content = $list;
    }

    public function alipaylogin(){
       if(!isset($_GET['auth_code'])){
            $act = "https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?app_id=2017080107981760&scope=auth_user&redirect_uri=http://www.ymlypt.com/index/alipaylogin&state=test";
            $this->redirect($act);
            exit;
        }else{
            $auth_code = $_GET['auth_code'];
            var_dump($auth_code);
            $pay_alipayapp = new pay_alipayapp();
            $result = $pay_alipayapp->alipayLogin($auth_code);
            var_dump($result);die;
            return $result;
        }
    }

    public function announcement() {
        $notice=Session::get('notice')==null?0:Session::get('notice');
        Session::clear('notice');
        $index_notice = $this->model->table('index_notice')->where('id=1')->find();
        $this->code = 0;
        $this->content['status'] = $notice;
        $this->content['index_notice'] = $index_notice;
    }

    //APP端邀请页banner
    public function invite_bg_banner(){
        $ad = $this->model->query("select content from tiny_ad where id in (82,83,84)  and is_open = 1");
        $arr = array();
        foreach ($ad as $k => $v){
             $content = unserialize($v['content']);
             $arr[$k] = $content['path'][0];
        }
       
        $this->code = 0;
        $this->content['path'] = $arr;
    }
}
