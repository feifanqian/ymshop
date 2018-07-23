<?php

class IndexController extends Controller {

    public $layout = 'index';
    public $safebox = null;
    private $model = null;
    private $category = array();
    private $categorys = array();
    protected $needRightActions = array('review' => true, 'review_act' => true);

    public function init() {
        header("Content-type: text/html; charset=" . $this->encoding);
        $this->model = new Model();
        $this->safebox = Safebox::getInstance();
        $this->user = $this->safebox->get('user');
        $category = Category::getInstance();
        $categorys = Category::getInstance();
        if ($this->user == null) {
            $this->user = Common::autoLoginUserInfo();
            $this->safebox->set('user', $this->user);
        }

        $this->category = $category->getCategory();
        $this->categorys = $categorys->getCategorys();
        $cart = Cart::getCart();
        $this->assign("cart", $cart->all());
        $this->assign("category", $this->category);
        $this->assign("categorys", $this->categorys);
        $keyword = urldecode(Req::args('keyword'));
        if ($keyword != null)
            $this->assign("keyword", Filter::text($keyword));
        $url_index = '/' . Req::args('con') . '/' . Req::args('act');
        $url_index = Url::urlFormat($url_index);
        $this->assign("url_index", Url::requestUri());
        //配制中的站点信息
        $config = Config::getInstance();
        $site_config = $config->get("globals");
        $this->assign('seo_title', $site_config['site_name']);
        $this->assign('site_title', $site_config['site_name']);
        $this->assign('seo_keywords', $site_config['site_keywords']);
        $this->assign('seo_description', $site_config['site_description']);
    }

    /**
     * 下载APP
     */
    public function app() {
        $version = Req::args("v");
        $download = Req::args("dl");
        $ostag = stripos(strtolower($_SERVER["HTTP_USER_AGENT"]), 'mac os x') !== FALSE ? 'ios' : 'android';
        $config = Config::getInstance()->get("globals");
        
        if(stripos(strtolower($_SERVER["HTTP_USER_AGENT"]), 'micromessenger') !== FALSE){
//            $this->redirect("http://a.app.qq.com/o/simple.jsp?pkgname=com.yidu.wowecoin");
            $this->redirect("http://www.ymlypt.com");
            exit();
        }
        if ($download || $version) {
            $ostag = in_array($version, array('ios', 'android')) ? $version : $ostag;
            if ($config && isset($config["site_{$ostag}url"])) {
                $this->redirect($config["site_{$ostag}url"]);
                exit;
            } else {
                $this->msg("下载地址暂时不可用!");
            }
        } else {
            $this->layout = "";
            $this->assign("config", $config);
            $this->redirect("app");
        }
    }

    //邀请注册
    public function invite() {
        // var_dump(111);die;
        $inviter_id = Filter::int(Req::args('inviter_id'));
        Session::set('jump_index',1);
        if (isset($this->user['id'])) {
            // var_dump(123);die;
            Common::buildInviteShip($inviter_id, $this->user['id'], "wechat");
            $this->redirect('index');
        } else {
            Cookie::set("inviter", $inviter_id);
            $this->noRight();
        }
        return;
    }
    public function myinvite() {
        $uid = Filter::int(Req::args('uid'));
        $model = new Model();
        $model = new Model("user as us");
        $user = $model->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.group_id,cu.user_id,cu.login_time,cu.mobile")->where("us.id='$uid'")->find();
        $this->assign('user', $user);
        $this->redirect();
    }

    public function tx() {
        $model = new Model();
        $aa = $model->table("balance_log")->fields("sum(amount) AS nums")->where("note='通过虚拟币支付方式兑换金点'")->find();
        $this->assign("number", $aa ? intval($aa['nums']) : 0);
        $this->layout = '';
        $this->redirect();
    }

    public function attention() {
        $goods_id = Filter::int(Req::args('goods_id'));
        $info = array('status' => 0);
        if (isset($this->user['name'])) {
            $obj = $this->model->table("attention")->where("goods_id=$goods_id and user_id=" . $this->user['id'])->find();
            if ($obj)
                $info = array('status' => 2);
            else {
                $this->model->table("attention")->data(array('goods_id' => $goods_id, 'user_id' => $this->user['id'], 'time' => date('Y-m-d H:i:s')))->insert();
                $info = array('status' => 1);
            }
        }
        echo JSON::encode($info);
    }

    public function msg() {
        $type = $this->type == null ? 'fail' : $this->type;
        $msg = $this->msg == null ? '失败' : $this->msg;
        $content = $this->content == null ? '' : $this->content;
        $redirect = $this->redirect == null ? '' : $this->redirect;
        $code = Req::args('code');
        $sign = Req::args('sign');
        if ($code && $sign) {
            echo json_encode(array('code' => "1005", 'content' => NULL, 'message' => $msg));
            exit;
        } else {
            $this->assign("type", $type);
            $this->assign("msg", $msg);
            $this->assign("content", $content);
            $this->assign("redirect", $redirect);
            $this->redirect();
        }
    }

    public function img_upload() {
        $path = APP_ROOT;
        $upf = new UploadFile('imgFile', $path, '2m', 'jpg,jpeg,gif');
        $upf->save();
        $info = $upf->getInfo();
        if ($info[0]['status'] == 1) {
            echo JSON::encode(array('error' => 0, 'url' => $info[0]['path']));
            exit;
        }
    }

    private function getCart() {
        $type = Req::args('cart_type');
        if ($type == 'goods') {
            return Cart::getCart('goods');
        } else {
            return Cart::getCart();
        }
    }

    public function cart_add() {
        $id = Filter::int(Req::args("id"));
        $num = intval(Req::args("num"));
        $num = $num > 0 ? $num : 1;
        $cart = $this->getCart();
        if($this->user){
           $cart->addItem($id, $num,$this->user['id']); 
        }else{
            $cart->addItem($id, $num,0);
        }
        $products = $cart->all();
        echo JSON::encode($products);
    }

    public function goods_add() {
        $cart = Cart::getCart('goods');
        $cart->clear();
        $id = Filter::int(Req::args("id"));
        $num = intval(Req::args("num"));
        $num = $num > 0 ? $num : 1;
        $result = $cart->addItem($id, $num);
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest') {
            $products = $cart->all();
            echo JSON::encode($products);
        } else {
            $this->redirect('/simple/cart/cart_type/goods');
        }
    }

    //清空购物车
    public function cart_truncate() {
        $cart = Cart::getCart();
        $cart->clear();
        echo JSON::encode(array('status' => 'success'));
    }

    //删除购物车商品
    public function cart_del() {
        $id = Filter::int(Req::args("id"));
        $cart = $this->getCart();
        if($this->user){
           $cart->delItem($id,$this->user['id']); 
        }else{
            $cart->delItem($id,0);
        }
        $info = array('status' => "fail");
        if (!$cart->hasItem($id))
            $info = array('status' => "success");
        echo JSON::encode($info);
    }

    //变更购物车商品数量
    public function cart_num() {
        $id = Filter::int(Req::args("id"));
        $num = intval(Req::args("num"));
        $num = $num > 0 ? $num : 1;
        $cart = $this->getCart();
        if($this->user){
           $cart->modNum($id, $num,$this->user['id']); 
        }else{
            $cart->modNum($id, $num,0);
        }
        // $cart->modNum($id, $num);
        $products = $cart->all();
        echo JSON::encode($products);
    }

    //删除指定的商品
    public function cart_multi() {
        $ids = Req::args("ids");
        $cart = $this->getCart();
        if (is_array($ids)) {
            foreach ($ids as $k => $v) {
                $cart->delItem($v);
            }
            $info = array('status' => "success");
        } else {
            $info = array('status' => "fail");
        }
        echo JSON::encode($info);
    }

    public function goods_consult() {
        $id = Filter::int(Req::args('id'));
        $content = Filter::txt(Req::args("content"));
        $content = TString::nl2br($content);
        $verifyCode = Req::args("verifyCode");
        $this->safebox = Safebox::getInstance();
        $code = $this->safebox->get($this->captchaKey);
        $info = array("status" => "fail", "msg" => "验证码错误！");
        if (isset($this->user['id']) && $this->user['name']) {
            if ($code == $verifyCode) {
                $this->model->table("ask")->data(array('question' => $content, 'user_id' => $this->user['id'], 'goods_id' => $id, 'ask_time' => date('Y-m-d H:i:s')))->insert();
                $info = array("status" => "success", "msg" => "咨询成功！");
                //发送用户咨询通知
                $NoticeService = new NoticeService();
                $template_data = array('user' => $this->user['name'],
                    'content' => $content
                );

                $NoticeService->send('user_ask', $template_data);
            }
        } else {
            $info = array("status" => "fail", "msg" => "登录后才能咨询。");
        }

        echo JSON::encode($info);
    }

    //帮助
    public function help() {
        $id = Filter::int(Req::args("id"));
        $help = $this->model->table('help')->where("id=$id")->find();
        if ($help) {
            $this->assign("id", $id);
            $this->assign('seo_title', $help['title']);
            $this->assign('help', $help);
            $this->redirect();
        } else {
            Tiny::Msg($this, '帮助文档不存在！');
        }
    }

    //文章
    public function article() {
        $id = Filter::int(Req::args("id"));
        $model = new Model('article');
        $article = $model->where("id = $id")->find();
        if ($article) {
            $this->assign('seo_title', $article['title']);
            $this->assign('article', $article);
            $this->assign("id", $id);
            $this->redirect();
        } else {
            Tiny::Msg($this, '文章不存在！');
        }
    }

    //文章列表
    public function article_list() {
        $id = Filter::int(Req::args("id"));
        $this->assign("id", $id);
        $where = "1=1";
        if ($id) {
            $where = 'category_id = ' . $id;
        }
        $this->assign("where", $where);
        $this->redirect();
    }

    public function group() {
        $this->assign('seo_title', '团购,优惠精选');
        $this->assign('seo_keywords', '团购,优惠促销精选');
        $this->redirect();
    }

    public function flash() {
        $page = Filter::sql(Req::args("p"));
        $page = $page ==NULL ? 1 : $page;
        $c1=$this->model->table("pointflash_sale as ps")->fields("*,ps.id as gid")->join("left join goods as go on ps.goods_id = go.id")->findAll();
        $c2=$this->model->table("flash_sale as gb")->join("left join goods as go on gb.goods_id = go.id")->fields("go.*,gb.is_end,gb.id as id,gb.order_num,gb.price")->order("gb.is_end asc,gb.id desc")->findAll();  
        $count1=count($c1);
        $count2=count($c2);
        $result=array_merge($c1,$c2);
        $total=count($result);//总条数  
        $num=5;//每页显示条数  
        
        $pagenum=ceil($total/$num);//总页数  
        $offset=($page-1)*$num;//开始去数据的位置   
        $start=$offset+1;//开始记录页  
        $end=($page==$pagenum)?$total : ($page*$num);//结束记录页  
        $next=($page==$pagenum)? $pagenum:($page+1);//下一页  
        $prev=($page==1)? 1:($page-1);//前一页 
        $html="<a href=/flash.html?p={$prev} >上一页</a>  <span class='current'>{$page}</span>
<a href=/flash.html?p={$next} >下一页</a> &nbsp;&nbsp;&nbsp;&nbsp;共 {$pagenum}页";

        $newarr = array_slice($result, ($page-1)*$num, $num);
        // var_dump($newarr);die;
        //PC端适用
        $num1=8;//每页显示条数  
        
        $pagenum1=ceil($total/$num1);//总页数  
        $offset1=($page-1)*$num1;//开始去数据的位置   
        $start1=$offset1+1;//开始记录页  
        $end1=($page==$pagenum1)?$total : ($page*$num1);//结束记录页  
        $next1=($page==$pagenum1)? $pagenum1:($page+1);//下一页  
        $prev1=($page==1)? 1:($page-1);//前一页 
        $html1="<a href=/flash.html?p={$prev1} >上一页</a>  <span class='current'>{$page}</span>
<a href=/flash.html?p={$next1} >下一页</a> &nbsp;&nbsp;&nbsp;&nbsp;共 {$pagenum1}页&nbsp;&nbsp;跳到第<input id='drumppage' style='width:24px;text-align:center' value='1'>页<a href='javascript:;' onclick='javascript:window.location.href=&quot;/flash.html?p=&quot;+document.getElementById(&quot;drumppage&quot;).value;'>确定</a>";

        $newarr1 = array_slice($result, ($page-1)*$num1, $num1);
        
        $this->assign('html',$html);
        $this->assign("newarr", $newarr);
        $this->assign('html1',$html1);
        $this->assign("newarr1", $newarr1);
        $this->assign('seo_title', '秒杀,优惠精选');
        $this->assign('seo_keywords', '抢购,优惠促销精选,限时抢购,更多优惠.');
        $this->redirect();
    }
    public function point() {
        $this->assign('seo_title', '积分,优惠精选');
        $this->assign('seo_keywords', '积分,优惠促销精选,更多优惠.');
        $this->redirect();
    }
    public function weishang() {
        $this->assign('seo_title', '积分,优惠精选');
        $this->assign('seo_keywords', '积分,优惠促销精选,更多优惠.');
        $this->redirect();
    }

    public function flashbuy1() {
        $id = Filter::int(Req::args("id"));
        $goods = $this->model->table("pointflash_sale as gb")->join("left join goods as go on gb.goods_id = go.id")->where("gb.id=$id")->find();
        if ($goods) {
            //检测抢购是否结束
            if ($goods['store_nums'] <= 0 || $goods['order_count'] >= $goods['max_sell_count'] || time() >= strtotime($goods['end_date'])) {
                $this->model->table('pointflash_sale')->data(array('is_end' => 1))->where("id=$id")->update();
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
            // var_dump($goods);die;
            $this->assign('id', $id);
            $this->assign("skumap", $skumap);
            $this->assign("attr_array", $attr_array);
            $this->assign("goods_attrs", $goods_attrs);
            $this->assign("goods", $goods);
            $this->redirect();
        }else {
            Tiny::Msg($this, "404");
        }
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
            $now = time();
        
            $groupbuy_join_list = $this->model->table('groupbuy_join as gj')->fields('gl.join_id,gj.user_id,gj.need_num,gj.end_time')->join('left join groupbuy_log as gl on gl.join_id=gj.id')->where('gl.groupbuy_id='.$id.' and gl.pay_status=1 and gj.need_num>0 and UNIX_TIMESTAMP(end_time)>'.$now)->findAll();
            
            if($groupbuy_join_list) {
                $groupbuy_join_list = $this->super_unique($groupbuy_join_list);
                foreach ($groupbuy_join_list as $k => $v) {
                    $user_ids = explode(',',$v['user_id']);
                    $user_id = $user_ids[0];
                    $groupbuy_join_list[$k]['users'] = $users = $this->model->table('user')->fields('nickname,avatar')->where('id='.$user_id)->find();
                }
                $groupbuy_join_list = array_values($groupbuy_join_list);
            }
            $this->assign('groupbuy_join_list', $groupbuy_join_list);
            $this->assign('seo_title', $goods['title']);
            $this->assign('id', $id);
            $this->assign("skumap", $skumap);
            $this->assign("attr_array", $attr_array);
            $this->assign("goods_attrs", $goods_attrs);
            $this->assign("goods", $goods);
            $this->redirect();
        } else {
            Tiny::Msg($this, "404");
        }
    }

    public function super_unique($array, $recursion = false){
        // 序列化数组元素,去除重复
        $result = array_map('unserialize', array_unique(array_map('serialize', $array)));
        // 递归调用
        if ($recursion) {
            foreach ($result as $key => $value) {
                if (is_array($value)) {
                    $result[ $key ] = super_unique($value);
                }
            }
        }
        return $result;
    }

    public function timediff($begin_time,$end_time)
    {
        if($begin_time < $end_time){
        $starttime = $begin_time;
        $endtime = $end_time;
        }else{
        $starttime = $end_time;
        $endtime = $begin_time;
        }

        //计算天数
        $timediff = $endtime-$starttime;
        $days = intval($timediff/86400);
        //计算小时数
        $remain = $timediff%86400;
        $hours = intval($remain/3600);
        //计算分钟数
        $remain = $remain%3600;
        $mins = intval($remain/60);
        //计算秒数
        $secs = $remain%60;
        // $res = array("day" => $days,"hour" => $hours,"min" => $mins,"sec" => $secs);
        $res = $hours.':'.$mins.':'.$secs;
        return $res;
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

            $this->assign('id', $id);
            $this->assign("skumap", $skumap);
            $this->assign("attr_array", $attr_array);
            $this->assign("goods_attrs", $goods_attrs);
            $this->assign("goods", $goods);
            $this->redirect();
        } else {
            Tiny::Msg($this, "404");
        }
    }
    
    public function wei(){
        $this->assign("seo_title","微商专区");
        $this->redirect();
    }
    //捆绑
    public function bundbuy() {
        $id = Filter::int(Req::args("id"));
        $bund = $this->model->table("bundling")->where("id=$id and status = 1")->find();
        if ($bund) {
            $goods = $this->model->table("goods")->where("id in ($bund[goods_id])")->findAll();
            $gids = array();
            $goods_price = 0.00;
            foreach ($goods as $go) {
                $gids[] = $go['id'];
                $goods_price += $go['sell_price'];
            }
            $gids = implode(',', $gids);
            $skumap = array();
            $products = $this->model->table("products")->fields("sell_price,market_price,store_nums,specs_key,pro_no,id,goods_id")->where("goods_id in ($gids)")->findAll();
            if ($products) {
                foreach ($products as $product) {
                    if ($product['specs_key'])
                        $skumap[$product['specs_key'] . $product['goods_id']] = $product;
                    else
                        $skumap[$product['goods_id']] = $product;
                }
            }
            $this->assign("bund", $bund);
            $this->assign("goods_price", $goods_price);
            $this->assign("skumap", $skumap);
            $this->assign("goods", $goods);
            $this->assign("id", $id);
            $this->redirect();
        }else {
            Tiny::msg($this, '', 404);
        }
    }
    //积分购
    public function pointbuy() {
        $id = Filter::int(Req::args("id"));
        $goods = $this->model->table("point_sale as ps")->fields("ps.price_set,go.*")->join("left join goods as go on ps.goods_id = go.id")->where("ps.id=$id and ps.status=1")->find();
        if ($goods) {
            //检测抢购是否结束
            if ($goods['store_nums'] <= 0) {
                $this->assign("store_empty",true);
            }
            $price_set = unserialize($goods['price_set']);
            $skumap = array();
            $products = $this->model->table("products")->fields("sell_price,market_price,store_nums,specs_key,pro_no,id")->where("goods_id = {$goods['id']}")->findAll();
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
             //评论
            $comment = array();
            $review = array('1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0);
            $rows = $this->model->table("review")->fields("count(id) as num,point")->where("status=1 and goods_id = {$goods['id']}")->group("point")->findAll();
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
            $this->assign("comment", $comment);
            $this->assign("price",  current($price_set));
            $this->assign('id', $id);
            $this->assign("skumap", $skumap);
            $this->assign("attr_array", $attr_array);
            $this->assign("goods_attrs", $goods_attrs);
            $this->assign("goods", $goods);
            $this->redirect();
        } else {
            Tiny::Msg($this, "404");
        }
    }

    //微商购
    public function pointwei() {
        if(isset($_SESSION['Tiny_user'])){
            $this->assign('is_login',1);
        }
        $id = Filter::int(Req::args("id"));
        // var_dump($id);die;
        $goods = $this->model->table("pointwei_sale as ps")->fields("ps.price_set,go.*")->join("left join goods as go on ps.goods_id = go.id")->where("ps.id=$id and ps.status=1")->find();
        if ($goods) {
            //检测抢购是否结束
            if ($goods['store_nums'] <= 0) {
                $this->assign("store_empty",true);
            }
            $price_set = unserialize($goods['price_set']);
            // var_dump($price_set);
            $skumap = array();
            $products = $this->model->table("products")->fields("sell_price,market_price,store_nums,specs_key,pro_no,id")->where("goods_id = {$goods['id']}")->findAll();
            if ($products) {
                // var_dump($products);die;
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
             //评论
            $comment = array();
            $review = array('1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0);
            $rows = $this->model->table("review")->fields("count(id) as num,point")->where("status=1 and goods_id = {$goods['id']}")->group("point")->findAll();
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
            $this->assign("comment", $comment);
            $this->assign("price",  current($price_set));
            $this->assign('id', $id);
            $this->assign("skumap", $skumap);
            $this->assign("attr_array", $attr_array);
            $this->assign("goods_attrs", $goods_attrs);
            $this->assign("goods", $goods);
            $this->assign("seo_title","微商专区");
            $this->redirect();
        } else {
            Tiny::Msg($this, "404");
        }
    }

    public function weibuy() {
        $id = Filter::int(Req::args("id"));
        $goods = $this->model->table("pointwei_sale as ps")->fields("ps.price_set,go.*")->join("left join goods as go on ps.goods_id = go.id")->where("ps.id=$id and ps.status=1")->find();
        if ($goods) {
            //检测抢购是否结束
            if ($goods['store_nums'] <= 0) {
                $this->assign("store_empty",true);
            }
            $price_set = unserialize($goods['price_set']);
            $skumap = array();
            $products = $this->model->table("products")->fields("sell_price,market_price,store_nums,specs_key,pro_no,id")->where("goods_id = {$goods['id']}")->findAll();
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
             //评论
            $comment = array();
            $review = array('1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0);
            $rows = $this->model->table("review")->fields("count(id) as num,point")->where("status=1 and goods_id = {$goods['id']}")->group("point")->findAll();
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
            $this->assign("comment", $comment);
            $this->assign("price",  current($price_set));
            $this->assign('id', $id);
            $this->assign("skumap", $skumap);
            $this->assign("attr_array", $attr_array);
            $this->assign("goods_attrs", $goods_attrs);
            $this->assign("goods", $goods);
            $this->redirect();
        } else {
            Tiny::Msg($this, "404");
        }
    }
    
    //积分抢购
    public function pointflash() {
        $id = Filter::int(Req::args("id"));
        $goods = $this->model->table("pointflash_sale as ps")->join("left join goods as go on ps.goods_id = go.id")->where("ps.id=$id")->find();
        if ($goods) {
            //检测抢购是否结束
            if ($goods['store_nums'] <= 0 || $goods['order_count'] >= $goods['max_sell_count'] || time() >= strtotime($goods['end_date'])) {
                $this->model->table('pointflash_sale')->data(array('is_end' => 1))->where("id=$id")->update();
                $goods['is_end'] = 1;
            }
            $skumap = array();
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

            $this->assign('id', $id);
            $this->assign("skumap", $skumap);
            $this->assign("price",  current($price_set));
            $this->assign("attr_array", $attr_array);
            $this->assign("goods_attrs", $goods_attrs);
            $this->assign("goods", $goods);
            $this->redirect();
        } else {
            Tiny::Msg($this, "404");
        }
    }
    //商品展示
    public function product() {
        $parse = array('直接打折', '减价优惠', '固定金额', '买就赠优惠券', '买M件送N件');
        $id = Filter::int(Req::args('id'));
        $flag =Req::args('flag');
        if($flag !='' && is_numeric($flag)){
            Session::set("product_id",$id);
           $flag_in_cookie = Cookie::get("flag");
           if($flag_in_cookie==NULL){
               Cookie::set("flag",$flag,3600);
           }else if(is_array($flag_in_cookie)){
               if(!in_array($flag, $flag_in_cookie)){
                   $flag_in_cookie[]=$flag;
                   Cookie::set('flag',$flag_in_cookie,3600);
               }
           }else if($flag!=$flag_in_cookie){
               Cookie::set('flag',array($flag,$flag_in_cookie,3600));
           }
           if(Common::checkInWechat()){
               $qr_info = $this->model->table("promote_qrcode")->where("id=$flag")->find();
               if($qr_info){
                   Cookie::set("inviter", $qr_info['user_id']);
                   if(!$this->user){ 
                        $this->noRight();
                   }else{
                       $inviter_id=$qr_info['user_id'];
                       Common::buildInviteShip($inviter_id, $this->user['id'], "goods_qrcode");
                       $this->model->query("update tiny_promote_qrcode set scan_times = scan_times + 1 where id = $flag");
                   }
               }
           }
        }
        $this->assign('id', $id);
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

            if ($goods['seo_title'] != '')
                $seo_title = $goods['seo_title'];
            else if ($goods['name'] != '')
                $seo_title = $goods['name'];

            if ($seo_title != '')
                $this->assign('seo_title', $seo_title);
            if ($goods['seo_keywords'] != '')
                $this->assign('seo_keywords', $goods['seo_keywords']);
            if ($goods['seo_description'] != '')
                $this->assign('seo_description', $goods['seo_description']);

            $proms = new Prom();
            $goods['goods_nums'] = PHP_INT_MAX;
            if (!empty($prom))
                $prom['parse'] = $proms->prom_goods($goods);

            //将内部地址替换成CDN地址
            //$goods['content'] = str_replace("/data/uploads", "/data/uploads", $goods['content']);
            //售后保障
            $sale_protection = $this->model->table('help')->where("title='售后保障'")->find();
            if ($sale_protection) {
                $this->assign("sale_protection", $sale_protection['content']);
            }
            $area_ids = array(1, 2, 3);
            if ($this->user) {
                //查询默认收货地址
                $one = $this->model->table("address")->where("user_id=" . $this->user['id'])->order("is_default desc")->find();
                if ($one) {
                    $area_ids = array($one['province'], $one['city'], $one['county']);
                    $areas = $this->model->table("area")->where("id in(" . implode(",", $area_ids) . " )")->findAll();
                    $parse_area = array();
                    foreach ($areas as $area) {
                        $parse_area[$area['id']] = $area['name'];
                    }
                    $address = implode(' ', $parse_area);
                }
                $this->assign('is_login',true);
            }
            $address = isset($address) ? $address : "北京 北京市 东城区";
            list($province, $city, $county) = $area_ids;
            $this->assign("province", $province);
            $this->assign("city", $city);
            $this->assign("county", $county);
            $this->assign("address", $address);
            //销量计算
            $sales_volume = $this->model->table("order_goods as og")->join("left join order as o on og.order_id = o.id")->where("og.goods_id = $id and o.status in (3,4)")->fields("SUM(og.goods_nums) as sell_volume")->find();
            $sales_volume = $sales_volume['sell_volume']==NULL?0:$sales_volume['sell_volume'];
            $sales_volume = $goods['base_sales_volume']+$sales_volume;
            $goods['sales_volume']=$sales_volume;
            $this->assign("child_category", $childCategory);
            $this->assign("prom", $prom);
            $this->assign("goods", $goods);
            $this->assign("goods_attrs", $goods_attrs);
            $this->assign("category_nav", $category);
            $this->assign("skumap", $skumap);
            $this->assign("comment", $comment);
            $this->redirect();
        } else {
            Tiny::Msg($this, "404");
        }
    }

    public function product_comment() {
        $page = Filter::int(Req::args("page"));
        $id = Filter::int(Req::args("gid"));
        $pagetype = Filter::int(Req::args("pagetype"));
        $score = Req::args("score");
        $this->redirect();
    }

    //搜索处理
    public function search() {
        $this->parseCondition();
    }

    public function category() {
        $this->parseCondition();
    }

    public function category_index() {
        $path = '';
        $category = Category::getInstance();
        $category = $category->getCateGory();
        $this->assign("category", $category);
        $this->redirect();
    }

    //搜索与分类的条件解析
    private function parseCondition() {
           
        $page = intval(Req::args("p"));
        $page_size = 35;
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
            $rows = $model->table("goods")->fields("category_id,count(id) as num")->where($where." and is_online = 0")->group("category_id")->findAll();
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
                if ($this->getModule()->checkToken()) {
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
        $rows = $model->table("goods as go")->fields("brand_id,count(id) as num")->where($where." and is_online=0 ")->group("brand_id")->findAll();
        $brand_ids = '';
        $brand_num = $has_brand = array();
        foreach ($rows as $row) {
            $brand_ids .= $row['brand_id'] . ',';
            $brand_num[$row['brand_id']] = $row['num'];
        }
        $brand_ids = trim($brand_ids, ',');

        //价格区间
        $prices = $model->table("goods as go")->fields("max(sell_price) as max,min(sell_price) as min,avg(sell_price) as avg")->where($where." and is_online=0 ")->find();
        $price_range = Common::priceRange($prices);

        if ($brand_ids) {
            $has_brand = $model->table("brand")->where("id in ($brand_ids)")->findAll();
        }
        //var_dump($price_range);exit();
        if (!empty($price_range))
            $has_price = array_flip($price_range);
        else
            $has_price = array();
        if ($price && isset($has_price[$price])) {
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
        if (!empty($attr)) {
            foreach ($attrs as $attr) {
                if ($attr['show_type'] == 1) {
                    $spec_attr[$attr['id']] = $attr;
                }
            }
        }

        if (!empty($specs)) {
            foreach ($specs as $spec) {
                $spec['values'] = unserialize($spec['value']);
                unset($spec['value'], $spec['spec']);
                $spec_attr[$spec['id']] = $spec;
            }
        }

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
        $this->assign("topPageBar", $topPageBar);

        //赋值处理
        $this->assign('seo_title', $seo_title);
        $this->assign('seo_keywords', $seo_keywords);
        $this->assign('seo_description', '对应的商品共有' . $goods['page']['total'] . '件商品,包括以下分类：' . $seo_description);

        $keyword = str_replace('|', '', $keyword);
        $this->assign("keyword", $keyword);
        $this->assign("sort", $sort);
        $this->assign("has_brand", $has_brand);
        $this->assign("brand_num", $brand_num);
        $this->assign("current_category", $current_category);
        $this->assign("goods", $goods);
        $this->assign("selected", $selected);
        $this->assign("spec_attr", $spec_attr);
        $this->assign("spec_attr_selected", $spec_attr_selected);
        $this->assign("category_child", $category_child);
        $this->assign("price_range", $price_range);
        $this->assign("category_nav", $category);
        $this->assign("has_category", $has_category);
        $this->assign("cid", $cid);
        
        if ($action == 'search')
            $this->assign("url", "/index/search/keyword/" . $keyword . "/cid/$cid/sort/$sort" . $url);
        else
            $this->assign("url", "/index/category/cid/" . $cid . "/sort/$sort" . $url);
        $this->redirect();
    }

    //取得咨询
    public function get_ask() {
        $page = Filter::int(Req::args("page"));
        $id = Filter::int(Req::args("id"));
        $asks = $this->model->table("ask as ak")->fields("ak.*,ak.id as id,us.name as uname,us.avatar")->join("left join user as us on ak.user_id = us.id")->where("ak.goods_id = $id and ak.status!=2")->order('ak.id desc')->findPage($page, 10, 1, true);
        foreach ($asks['data'] as $key => $value) {
            $asks['data'][$key]['avatar'] = $value['avatar'] != '' ? (substr($value['avatar'], 0, 4) == 'http' ? $value['avatar'] : Url::urlFormat("@". $value['avatar'])) : Url::urlFormat("#images/no-img.png");
            $asks['data'][$key]['uname'] = TString::msubstr($value['uname'], 0, 3, 'utf-8', '***');
        }
        $asks['status'] = "success";
        echo JSON::encode($asks);
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
        $review = $this->model->table("review as re")
                        ->join("left join user as us on re.user_id = us.id left join order as od on re.order_no = od.order_no left join order_goods as og on (og.order_id = od.id AND og.goods_id=re.goods_id)")
                        ->fields("re.*,re.id as id,us.name as uname,us.avatar,og.spec")
                        ->where($where)->order("re.id desc")->findPage($page, 10, $pagetype, true);
        $data = $review['data'];
        if(empty($data)){
            $review['status'] = "fail";
            echo JSON::encode($review);
            exit();
        }
        foreach ($data as $key => $value) {
            $data[$key]['star'] = $value['point'];
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
            $data[$key]['speclist'] = implode(' / ', $speclist);
            $data[$key]['photos']=$data[$key]['photos']==NULL?array():explode("|", $data[$key]['photos']);
        }
        $review['data'] = $data;
        $review['status'] = "success";
        echo JSON::encode($review);
    }

    function index() {
        if (!$this->user && Common::checkInWechat()) {
            $this->noRight();
        }
        $notice=Session::get('notice');
        Session::clear('notice');
        // if($this->user){
        //       if(isset($this->user['id'])){
        //         if($this->user['id']==42608){
        //             var_dump($notice);die;
        //       }
        //     }
        // }
        
        if(Common::checkInWechat()){
            $page_size = 10;
            $poin_sale_page_size = 20;
            
        }else{
            $page_size = 5;
            $poin_sale_page_size = 10;
        }
        $point_sale = $this->model->table("point_sale")->where("status=1")->count();
        $category_4   = $this->model->table('goods')->where("is_online = 0 and category_id in (select id from tiny_goods_category where path like ',4,%')")->count();
        $category_22  = $this->model->table('goods')->where("is_online = 0 and category_id in (select id from tiny_goods_category where path like ',22,%')")->count();
        $category_65  = $this->model->table('goods')->where("is_online = 0 and category_id in (select id from tiny_goods_category where path like ',65%')")->count();
        $category_77  = $this->model->table('goods')->where("is_online = 0 and category_id in (select id from tiny_goods_category where path like ',77,%')")->count();
        $category_98  = $this->model->table('goods')->where("is_online = 0 and category_id in (select id from tiny_goods_category where path like ',98,%')")->count();
        $category_135 = $this->model->table('goods')->where("is_online = 0 and category_id in (select id from tiny_goods_category where path like ',135,%')")->count();
        
        $index = Cookie::get('index');
        $index = NULL;
        Cookie::clear("index");
        if($index==NULL||!is_array($index)){
            $index = array(
                'point_sale'=>1,
                'category_4'=>1,
                'category_22'=>1,
                'category_65'=>1,
                'category_77'=>1,
                'category_98'=>1,
                'category_135'=>1,
                'category_161'=>1,
                'category_162'=>1,
            );
            Cookie::set('index',$index);
        }else{
            foreach($index as $k => $v){
                if($k=='point_sale'){
                    if($v+1<=ceil($point_sale/$poin_sale_page_size)){
                        $index[$k] = $v+1;
                    }else{
                        $index[$k] =1;
                    }
                }else if($v+1<=ceil(($$k)/$page_size)){
                    $index[$k] = $v+1;
                }else{
                    $index[$k] =1;
                }
            }
            Cookie::set('index',$index);
        }
        $pointflash = $this->model->table('pointflash_sale as ps')->join('left join goods as go on ps.goods_id=go.id')->fields('ps.*,go.name')->findAll();
        $pointflash_count = count($pointflash);
        $flash = $this->model->table('flash_sale as fs')->join('left join goods as go on fs.goods_id=go.id')->fields('fs.*,go.name')->findAll();
        $flash_count = count($flash);
        $index_notice = $this->model->table('index_notice')->where('id=1')->find();
        if($index_notice){
            $this->assign('index_notice', $index_notice);
        }
        $this->assign('pointflash_count',$pointflash_count);
        $this->assign('flash_count',$flash_count);
        $this->assign('index',$index);
        $this->assign('notice',$notice);
        $this->redirect();
    }

    public function login() {
        $this->layout = "simple";
        $this->redirect("login");
    }

    public function result() {
        $this->layout = "simple";
        $this->redirect();
    }

    public function reg() {
        $this->layout = "simple";
        $this->redirect("reg");
    }

    //订阅到货通知
    public function notify() {
        $rules = array('email:email:邮箱格式不正确!', 'mobile:mobi:手机格式不正确!');
        $validator_info = Validator::check($rules);
        if (is_array($validator_info)) {
            array('status' => 'fail', 'msg' => $validator_info['msg']);
            echo JSON::encode($info);
        } else {
            $goods_id = Filter::int(Req::args('goods_id'));
            $email = Filter::sql(Req::args('email'));
            $mobile = Filter::int(Req::args('mobile'));
            $model = new Model('notify');

            $register_time = Date('Y-m-d H:i:s');
            $info = array('status' => 'fail', 'msg' => '您还没有登录，无法订阅到货通知。');
            if (isset($this->user['id'])) {
                $time = date('Y-m-d H:i:s', strtotime('-3 day'));
                $obj = $model->where('user_id = ' . $this->user['id'] . ' and goods_id=' . $goods_id . ' and register_time >' . "'$time'")->find();
                if ($obj) {
                    $info = array('status' => 'warning', 'msg' => '您已订阅过了该商品的到货通知。');
                } else {
                    $data = array('user_id' => $this->user['id'], 'goods_id' => $goods_id, 'register_time' => $register_time, 'email' => $email, 'mobile' => $mobile);
                    $last_id = $model->data($data)->insert();
                    if ($last_id > 0)
                        $info = array('status' => 'success', 'msg' => '订阅成功。');
                    else
                        $info = array('status' => 'fail', 'msg' => '订阅失败。');
                }
            }
            echo JSON::encode($info);
        }
    }

    //用户商品评价
    public function review() {
        $id = Filter::int(Req::args('id'));
        $review = $this->model->table("review as re")->join("left join goods as go on re.goods_id = go.id")->fields("re.*,go.name,go.img,go.id as gid,go.sell_price")->where("re.id=$id and user_id = " . $this->user['id'] . " and status=0")->find();

        if ($review) {
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
                $this->assign("review", $review);
                $this->redirect();
        } else {
            $this->redirect("msg", false, array('type' => 'fail', 'msg' => '商品已经评论。'));
        }
    }

    public function review_act() {
        $id = Filter::int(Req::args('id'));
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
        $result = $this->model->table("review")->data(array('status' => 1, 'point' => $point, 'content' => $content, 'comment_time' => date('Y-m-d'),'photos'=>$photos))->where("id=$id and user_id=" . $this->user['id'])->update();
        if($result){
             //1.查询好评数 分数大于3为好评
             $row1 = $this->model->table("review")->fields("count(id) as num")->where("status=1 and goods_id = $gid and point>3")->find();
             $satisfaction_num = $row1['num'];
             //2.查询评论数 
             $row2 = $this->model->table("review")->fields("count(id) as num")->where("status =1 and goods_id = $gid")->find();
             $review_count = $row2['num'];
             $rate = 1;
             if ($review_count && $satisfaction_num) {
                    //计算好评率并将结果存入数据库
                    $rate = round($satisfaction_num / $review_count, 2); //保留两位小数
                    $this->model->table('goods')->data(array('satisfaction_rate'=>$rate,'review_count'=>$review_count))->where("id=$gid")->update();
             }
             if($this->is_ajax_request()){
                 exit(json_encode(array("status"=>'success')));
             }else{
                 $this->redirect("msg", false, array('type' => "success", 'msg' => "评价成功！", "redirect" => "/ucenter/review"));
             }
        }else{
            if($this->is_ajax_request()){
                 exit(json_encode(array("status"=>'fail','msg'=>'评论失败了...')));
             }else{
                 $this->redirect("msg", false, array('type' => "fail", 'msg' => "评价失败！", "redirect" => "/ucenter/review"));
             }
        }    
    }

    public function reg_act() {
        $email = Filter::sql(Req::post('email'));
        $passWord = Req::post('password');
        $rePassWord = Req::post('repassword');
        $this->safebox = Safebox::getInstance();
        $code = $this->safebox->get($this->captchaKey);
        $verifyCode = Req::args("verifyCode");
        $info = array('field' => 'verifyCode', 'msg' => '验证码错误!');
        if ($verifyCode == $code) {
            if ($passWord == $rePassWord) {
                $model = $this->model->table("user");
                $obj = $model->where("email='$email'")->find();
                if ($obj == null) {
                    $validcode = CHash::random(8);
                    $model->data(array('email' => $email, 'name' => $email, 'password' => CHash::md5($passWord, $validcode), 'validcode' => $validcode))->insert();
                    $this->redirect("index");
                } else {
                    $info = array('field' => 'email', 'msg' => '此用户已经被注册！');
                }
            } else {
                $info = array('field' => 'repassword', 'msg' => '两次密码输入不一致！');
            }
        }
        $this->assign("invalid", $info);
        $this->redirect("reg", false, Req::args());
    }

    public function js() {

        $id = Filter::sql(Req::args("id"));
        $model = new Model("ad");
        $time = date('Y-m-d');

        $ad = $model->where("number = '$id' and start_time<='$time' and end_time >='$time'")->find();
        if ($ad == null)
            return;
        if ($ad['is_open'] == 0)
            return;
        if ($ad['type'] != 5)
            $ad['content'] = unserialize($ad['content']);
        $str = '';
        $width = "";
        if ($ad['width'] == 0)
            $width = "width:100%;";
        else
            $width = "width:" . $ad['width'] . 'px;';
        if ($ad['type'] == 1) {
            foreach ($ad['content'] as $key => $item) {
                if ($item['url'])
                    $str = '<a href="' . $item['url'] . '" target="_blank"><img src="' . Url::fullUrlFormat('@' . $item['path']) . '" title="' . $item['title'] . '"></a>';
                else
                    $str = '<img src="' . Url::fullUrlFormat('@' . $item['path']) . '" title="' . $item['title'] . '">';
            }
        }
        else if ($ad['type'] == 2) {
            $str = '';

            foreach ($ad['content'] as $key => $item) {
                //$str .= '<a href="'.$item['url'].'" target="_blank" style="display:block;height:'.$ad['height'].'px;position:relative;width:100%;">fff</a>';
                if ($item['url'])
                    $str .= '<li  style="background-image: url(' . Url::fullUrlFormat('@' . $item['path']) . '); background-position: 50% 0%; background-repeat: no-repeat no-repeat; height: ' . $ad['height'] . 'px;' . $width . ';float:left;"><a href="' . $item['url'] . '" target="_blank"></a></li>';
                else
                    $str .= '<li  style="background-image: url(' . Url::fullUrlFormat('@' . $item['path']) . '); background-position: 50% 0%; background-repeat: no-repeat no-repeat; height: ' . $ad['height'] . 'px;' . $width . ';float:left;"></li>';
            }

            $str = '<div id="slider-' . $ad['number'] . '" class="slider" style="margin-top:10px; height: ' . $ad['height'] . 'px;' . $width . '"><ul class="items" style="white-space:nowrap;">' . $str . '</ul></div>';
        }
        else if ($ad['type'] == 3) {
            $content = $ad['content'];
            $str = '<a href="' . $content['url'] . '" style="color:' . $content['color'] . '" target="_blank">' . $content['title'] . '</a>';
        } else if ($ad['type'] == 4) {
            foreach ($ad['content'] as $key => $item) {
                if ($item['url'])
                    $str = '<a href="' . $item['url'] . '" target="_blank"><img src="' . Url::fullUrlFormat('@' . $item['path']) . '" title="' . $item['title'] . '"></a>';
                else
                    $str = '<img src="' . Url::fullUrlFormat('@' . $item['path']) . '" title="' . $item['title'] . '">';
            }
            //$str = '<div style="position: fixed;left:0;bottom:0">'.$str.'</div>';
        }
        else {
            $str = preg_replace('/(\n|\r)/i', ' ', $ad['content']);
            $str = preg_replace("/'/i", "\'", $str);
        }
        $css = '';
        if ($ad['type'] == 4) {
            $info = $ad['content'][0];

            switch ($info['position']) {
                case 0:
                    $css = "{left:0,top:0,right:'',bottom:'','z-index': 10000}";
                    break;
                case 1:
                    $css = "{left:w_middle,top:0,right:'',bottom:'','z-index': 10000}";
                    break;
                case 2:
                    $css = "{right:0,top:0,left:'',bottom:'','z-index': 10000}";
                    break;
                case 3:
                    $css = "{right:'',top:h_middle,left:0,bottom:'','z-index': 10000}";
                    break;
                case 4:
                    $css = "{right:'',top:h_middle,left:w_middle,bottom:'','z-index': 10000}";
                    break;
                case 5:
                    $css = "{right:0,top:h_middle,left:'',bottom:'','z-index': 10000}";
                    break;
                case 6:
                    $css = "{right:'',top:'',left:0,bottom:0,'z-index': 10000}";
                    break;
                case 7:
                    $css = "{right:'',top:'',left:w_middle,bottom:0,'z-index': 10000}";
                    break;
                case 8:
                    $css = "{right:0,top:'',left:'',bottom:0,'z-index': 10000}";
                    break;
            }

            if ($info['is_close'] == 1)
                $str .= '<a style="dispaly:block;border:red 1px solid;text-decoration: none; background: #f1f1f1; width:12px;height:12px; position:absolute;top:2px;right:2px;font-size:12px;font-family: serif;color:red;" href="javascript:$(\\\'#ad-' . $ad['number'] . '\\\').remove()">×</a>';

            $str = '<div id="ad-' . $ad['number'] . '" style="position: fixed;left:0;bottom:0;' . $width . 'height:' . $ad['height'] . 'px;overflow: hidden;">' . $str . '</div>';
        }
        else {
            $str = '<div id="ad-' . $ad['number'] . '" style="' . $width . 'height:' . $ad['height'] . 'px;overflow: hidden;">' . $str . '</div>';
        }
        if ($ad['type'] == 2)
            $str.='<script type="text/javascript">$("#slider-' . $ad['number'] . '").Slider();</script>';
        else if ($ad['type'] == 4) {
            $css = preg_replace('/\'/', '"', $css);
            $str.='<script type="text/javascript"> var w_middle = ($(window).width()-$("#ad-' . $ad['number'] . '").width())/2+"px";var h_middle = ($(window).height()-$("#ad-' . $ad['number'] . '").height())/2+"px";var css=' . $css . ';$("#ad-' . $ad['number'] . '").css(css);</script>';
        }
        header('Content-type: text/javascript');
        echo "document.write('" . $str . "');";
        // exit;
    }

    public function checkRight($actionId) {
        if (isset($this->needRightActions[$actionId]) && $this->needRightActions[$actionId]) {
            if (isset($this->user['name']) && $this->user['name'] != null)
                return true;
            else
                return false;
        } else
            return true;
    }

    public function noRight() {
        if (Common::checkInWechat()) {
            Cookie::set("url", Url::pathinfo());
            $wechat = new WechatOAuth();
            $url = $wechat->getRequestCodeURL();
            // var_dump($url);die;
            $this->redirect($url);
            exit;
            // $this->redirect("/index/index");
        }
        $this->redirect("/simple/login");
    }
    public function guide(){
        $this->redirect();
    }
//    //参与抽奖
//    public function joinLottery(){
//         if($this->is_ajax_request()){
//             $name = Filter::sql(Req::args("name"));
//             $mobile = Filter::sql(Req::args("mobile"));
//             if($name!=""&&$mobile!=""&&  is_numeric($mobile) &&strlen($mobile)==11){
//                 $model = new Model();
//                 $isset = $model->table('lottery')->where("mobile='$mobile'")->find();
//                 if(!$isset){
//                     $result = $model->table('lottery')->data(array("name"=>$name,"mobile"=>$mobile,'create_time'=>date("Y-m-d H:i:s"),'is_lottery'=>0))->insert();
//                     if($result){
//                         exit(json_encode(array('status'=>'success','msg'=>"成功")));
//                     }else{
//                         exit(json_encode(array('status'=>'fail','msg'=>"数据库插入失败")));
//                     }
//                 }else{
//                     exit(json_encode(array('status'=>'fail','msg'=>"该手机号码已经参加了抽奖活动")));
//                 }
//             }
//         }else{
//            $this->layout = '';
//            $this->redirect();
//         }
//    }
//    //开奖页面
//    public function lottery(){
//        if($this->is_ajax_request()){
//            $model = new Model();
//            $lottery = $model->table("lottery")-> where("is_lottery=0")->order(" rand()")->find();
//            if(!empty($lottery)){
//               $model->table('lottery')->data(array('is_lottery'=>1,'lottery_time'=>date("Y-m-d H:i:s")))->where('id='.$lottery['id'])->update();
//                exit(json_encode(array("status"=>'success','mobile'=>$lottery['mobile'],'name'=>$lottery['name'])));
//            }else{
//                exit(json_encode(array("status"=>'fail','msg'=>"数据库中获取不到新的获奖人信息")));
//            }
//        }else{
//            $this->layout="_blank";
//            $this->redirect();
//        }
//    }
//    
    
    //套餐专区
    public function package_area(){
        $config = Config::getInstance();
        $package_set = $config->get("recharge_package_set"); 
        if(is_array($package_set)){
            $where =implode(',', array_reverse(explode("|", $package_set[1]['gift'])));
            $select1 = $this->model->table("products as p")->join("goods as g on p.goods_id=g.id")->where("p.id in ({$where})")->fields("p.id,g.id as goods_id,g.img,g.name")->order("field(p.id,$where)")->findAll();
            $this->assign("select1",$select1);
            $where =implode(',', array_reverse(explode("|", $package_set[2]['gift'])));
            $select2 = $this->model->table("products as p")->join("goods as g on p.goods_id=g.id")->where("p.id in ({$where})")->fields("p.id,g.id as goods_id,g.img,g.name")->order("field(p.id,$where)")->findAll();
            $this->assign("select2",$select2);
        }
        $district_set = $config->get("district_set");
        if(is_array($district_set)){
            if(isset($district_set['join_send_gift'])){
                $where =implode(',', array_reverse(explode("|", $district_set['join_send_gift'])));
                $select3 = $this->model->table("products as p")->join("goods as g on p.goods_id=g.id")->where("p.id in ({$where})")->fields("p.id,g.id as goods_id,g.img,g.name")->order("field(p.id,$where)")->findAll();
                $this->assign("select3",$select3);
            }else{
                $this->assign("select3",array());
            }
        }
        $this->assign("seo_title","套餐专区");
        $this->redirect();
    }
    
    public function package_info(){
        $id = Filter::int(Req::args('gid'));
        $pid = Filter::int(Req::args('pid'));
        $type = Filter::int(Req::args("type"));
        $config = Config::getInstance();
        if($type==1){
            $package_set = $config->get("recharge_package_set"); 
            if(in_array($pid, explode("|", $package_set[1]['gift']))){
                $this->assign('package',1);
                $this->assign("pid",$pid);
            }else if(in_array($pid, explode("|", $package_set[2]['gift']))){
                $this->assign('package',2);
                $this->assign("pid",$pid);
            }else{
                Tiny::Msg($this, "404");
                exit();
            }
        }else if($type==2){
            $district_set = $config->get("district_set"); 
            if(in_array($pid, explode("|", $district_set['join_send_gift']))){
                $this->assign("promoter",true);
            }else{
                Tiny::Msg($this, "404");
                exit();
            }
        }
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

            if ($goods['seo_title'] != '')
                $seo_title = $goods['seo_title'];
            else if ($goods['name'] != '')
                $seo_title = $goods['name'];

            if ($seo_title != '')
                $this->assign('seo_title', $seo_title);
            if ($goods['seo_keywords'] != '')
                $this->assign('seo_keywords', $goods['seo_keywords']);
            if ($goods['seo_description'] != '')
                $this->assign('seo_description', $goods['seo_description']);

            $proms = new Prom();
            $goods['goods_nums'] = PHP_INT_MAX;
            if (!empty($prom))
                $prom['parse'] = $proms->prom_goods($goods);

            //将内部地址替换成CDN地址
            //$goods['content'] = str_replace("/data/uploads", "/data/uploads", $goods['content']);
            //售后保障
            $sale_protection = $this->model->table('help')->where("title='售后保障'")->find();
            if ($sale_protection) {
                $this->assign("sale_protection", $sale_protection['content']);
            }
            $area_ids = array(1, 2, 3);
            $is_promoter=false;
            if ($this->user) {
                //查询默认收货地址
                $one = $this->model->table("address")->where("user_id=" . $this->user['id'])->order("is_default desc")->find();
                if ($one) {
                    $area_ids = array($one['province'], $one['city'], $one['county']);
                    $areas = $this->model->table("area")->where("id in(" . implode(",", $area_ids) . " )")->findAll();
                    $parse_area = array();
                    foreach ($areas as $area) {
                        $parse_area[$area['id']] = $area['name'];
                    }
                    $address = implode(' ', $parse_area);
                }
            }
            $address = isset($address) ? $address : "北京 北京市 东城区";
            list($province, $city, $county) = $area_ids;
            $this->assign("province", $province);
            $this->assign("city", $city);
            $this->assign("county", $county);
            $this->assign("address", $address);

            $this->assign("child_category", $childCategory);
            $this->assign("prom", $prom);
            $this->assign("goods", $goods);
            $this->assign("goods_attrs", $goods_attrs);
            $this->assign("category_nav", $category);
            $this->assign("skumap", $skumap);
            $this->redirect();
        } else {
            Tiny::Msg($this, "404");
        }
    }
    
    public function personal_shop_list(){
        $this->assign("seo_title","会员专区");
        
        $this->redirect();
    }
    
    public function personal_shop_index(){
        $id = Filter::int(Req::args('id'));
        $list_type = Filter::int(Req::args('list_type'));
        $list_type = $list_type == NULL ? 1:$list_type;
        $model = new Model();
        $isset = $model->table('personal_shop')->where("id=$id")->find();
        if($isset){
            $personal_data = Common::getPersonalShopData($id);
            $this->assign("all_sell_num",$personal_data['all_sell_num']);
            $this->assign("all_goods_num",$personal_data['all_goods_num']);
            $this->assign("list_type",$list_type);
            $this->assign("id",$id);
            $this->layout="";
            $this->redirect();
        }else{
            Tiny::Msg($this, "404");
        }
    }

    public function invitepay(){
        // $id=$this->user['id'];
        $id = Req::args("user_id");
        $uid=Filter::int($id);
         
        $model=new Model();
        $user=$model->table('customer')->fields('real_name')->where('user_id='.$uid)->find();
        $users=$model->table('user')->fields('avatar')->where('id='.$uid)->find();
        if($user){
            $real_name = $user['real_name'];
        }else{
            $real_name = '未知商家';
        }
        if($users){
            if($users['avatar']=='' || $users['avatar']=='/0'){
                $users['avatar']='/static/images/96.png';
            }
            $avatar = $users['avatar'];
        }else{
            $avatar = '';
        }
        Session::set('seller_id',$uid);
        $this->assign('real_name',$real_name);
        $this->assign('avatar',$avatar);
        $this->assign('uid', $uid);
        $this->redirect();
    }

    public function demo(){
        $model = new Model();
       Session::set('demo', 1);
       // if($this->user['id']==null){
       //    Cookie::set("inviter", $inviter_id);
       //      $this->noRight();
       // }
       $inviter_id = intval(Req::args('inviter_id'));
        if (isset($this->user['id'])) {
            Common::buildInviteShip($inviter_id, $this->user['id'], "second-wap");      
        } else {
            Cookie::set("inviter", $inviter_id);
            $this->noRight();
        }
        // var_dump($inviter_id);
        $shop=$this->model->table('customer')->fields('real_name')->where('user_id='.$inviter_id)->find();
        // var_dump($shop);die;
        if($shop){
            $this->assign('shop_name',$shop['real_name']);
        }else{
            // $invite=$this->model->table('invite')->where('invite_user_id='.$this->user['id'])->find();
            // if($invite){
            //     $inviter_id=$invite['user_id'];
            //     $shop1=$this->model->table('customer')->fields('real_name')->where('user_id='.$inviter_id)->find();
            //     $this->assign('shop_name',$shop1['real_name']);
            // }else{
            //     $this->assign('shop_name','未知商家');
            // }
            $this->assign('shop_name','未知商家');
        }

        $order_no=date('YmdHis').rand(1000,9999);
        $this->assign("seo_title","向商家付款");
        $this->assign('seller_id',$inviter_id);
        $this->assign('seller_ids',Session::get('seller_id'));
        $this->assign('order_no',$order_no);
        $this->redirect();
    }

    public function download(){
       $this->assign("seo_title","圆梦共享网APP");
       $this->redirect(); 
    }

    public function downloadapp(){
        $url = "http://www.ymlypt.com/static/upload/app/app-release.apk";
        $logo = "http://www.ymlypt.com/themes/mobile/images/logo-new.png";
        ob_clean();
        $qrCode = new QrCode();
        $qrCode->setText($url)
                ->setSize(200)
                ->setLogo($logo)
                ->setPadding(10)
                ->setErrorCorrection('medium')
                ->setForegroundColor(array('r' => 0, 'g' => 0, 'b' => 0, 'a' => 0))
                ->setBackgroundColor(array('r' => 255, 'g' => 255, 'b' => 255, 'a' => 0))
                ->setLabelFontSize(16)
                ->setImageType(QrCode::IMAGE_TYPE_PNG);
        header('Content-Type: ' . $qrCode->getContentType());
        $qrCode->render();
        return;
    }

    public function beagent()
    {
        $this->assign('random', rand(1000, 9999));
        $this->assign('seo_title', "圆梦共享网");
        $this->redirect();
    }

    public function groupbuy_join_detail()
    {
        if($this->user['id']==null) {
            $this->redirect('/simple/login');
        }
        $groupbuy_id = Filter::int(Req::args('groupbuy_id'));
        $join_id = Filter::int(Req::args('join_id'));
        $groupbuy = $this->model->table('groupbuy')->where('id='.$groupbuy_id)->find();
        if(!$groupbuy_id) {
            $this->redirect("msg", false, array('type' => 'fail', 'msg' => '未找到该拼团商品'));
        }
        $goods = $this->model->table('goods as g')->fields('g.id,g.name,g.img,g.imgs,g.sell_price,g.content,g.specs,p.id as product_id,g.store_nums')->join('left join products as p on g.id = p.goods_id')->where('g.id='.$groupbuy['goods_id'])->find();
        $first = $this->model->table('groupbuy_log')->fields('join_time')->where('groupbuy_id='.$groupbuy_id.' and join_id='.$join_id.' and pay_status=1')->order('id asc')->find();
        $info['groupbuy_id'] = $groupbuy_id;
        $info['goods_id'] = $groupbuy['goods_id'];
        $info['product_id'] = $goods['product_id'];
        $info['name'] = $goods['name'];
        $info['img'] = $goods['img'];
        $info['price'] = $groupbuy['price'];
        $info['store_nums'] = $goods['store_nums'];
        $info['specs'] = array_values(unserialize($goods['specs']));
        if($info['specs']!=null && is_array($info['specs'])) {
            foreach ($info['specs'] as $k => &$v) {
                $v['value'] = array_values($v['value']);
            }
        }
        $skumap = array();
        $products = $this->model->table("products")->fields("sell_price,market_price,store_nums,specs_key,pro_no,id")->where("goods_id = ".$groupbuy['goods_id'])->findAll();
        if ($products) {
            foreach ($products as $product) {
                $skumap[$product['specs_key']] = $product;
            }
        }
        // $info['skumap'] = array_values($skumap);
        $info['min_num'] = $groupbuy['min_num'];
        $info['had_join_num'] = $this->model->table('groupbuy_log')->where('groupbuy_id='.$groupbuy_id.' and join_id='.$join_id.' and pay_status=1')->count();
        $info['start_time'] = $first['join_time'];
        $info['need_num'] = $info['min_num'] - $info['had_join_num'];
        $info['end_time'] = date("Y-m-d H:i:s",strtotime('+1 day',strtotime($first['join_time'])));
        $info['current_time'] = date('Y-m-d H:i:s');
        
        $info['groupbuy_join_list']['join_id'] = $join_id;
        $info['groupbuy_join_list']['need_num'] = $info['min_num'] - $info['had_join_num'];
        
        $users = $this->model->table('groupbuy_log as gl')->join('left join user as u on gl.user_id=u.id')->fields('u.nickname,u.avatar')->where('gl.groupbuy_id='.$groupbuy_id.' and gl.join_id='.$join_id.' and gl.pay_status=1')->order('gl.join_time asc')->findAll();
        $info['groupbuy_join_list']['users'] = $users;
        $info['groupbuy_join_list']['remain_time'] = $this->timediff(time(),strtotime($info['end_time']));
        
        $joined = $this->model->table('groupbuy_log')->where('groupbuy_id='.$groupbuy_id.' and join_id='.$join_id.' and user_id='.$this->user['id'].' and pay_status=1')->find();
        if($joined && $info['had_join_num']>=$info['min_num']) {
            $info['status'] = '拼团成功';
        } elseif ($joined && $info['had_join_num']<$info['min_num'] && time()>=strtotime($info['end_time'])) {
            $info['status'] = '拼团失败';
        } elseif ($joined && $info['had_join_num']<$info['min_num'] && time()<strtotime($info['end_time'])) {
            $info['status'] = '邀请好友';
        } elseif ($joined==null && $info['had_join_num']>=$info['min_num'] && time()>=strtotime($info['end_time'])) {
            $info['status'] = '活动已结束';
        } elseif ($joined==null && $info['had_join_num']>=$info['min_num'] && time()<strtotime($info['end_time'])) {
            $info['status'] = '拼团人数已满';
        } elseif ($joined==null && $info['had_join_num']<$info['min_num'] && time()<strtotime($info['end_time'])) {
            $info['status'] = '我要参团';
        } else {
            $info['status'] = '拼团中';
        }
        $this->assign('info', $info);
        $this->assign('skumap', $skumap);
        $this->assign('groupbuy_id', $groupbuy_id);
        $this->redirect();
    }
}
