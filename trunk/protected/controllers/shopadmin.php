<?php

class ShopadminController extends Controller {

    public $layout = 'shopadmin';
    private $model = null;
    private $cookie = null;
    private $user = array();
    protected $needRightActions = array(
        'index' => true,
        'profile' => true,
        'login' => false,
        'order' => true,
        'order_ajax' => true,
        'order_detail' => true,
        'invoice' => true,
        'invoice_ajax' => true,
        'second' => false,
        'get_express_info' => true,
    );

    public function init() {
        $themes_mobile = Tiny::app()->setTheme("mobile");
        header("Content-type: text/html; charset=" . $this->encoding);
        $this->cookie = new Cookie();

        $this->cookie->setSafeCode("sa");
        $this->model = new Model("shop");
        $this->safebox = Safebox::getInstance();
        $this->user = $this->safebox->get('shopuser');
        if ($this->user == null) {
            $this->user = $this->checkLogin();
            $this->safebox->set('shopuser', $this->user);
        }
        $this->assign("user", $this->user);

        //配制中的站点信息
        $config = Config::getInstance();
        $site_config = $config->get("globals");
        $one = $this->model->query("select count(distinct og.order_id) as total from tiny_order_goods og LEFT JOIN tiny_order od ON og.order_id=od.id where og.shop_id='3' AND og.express_no='' AND od.status BETWEEN 3 AND 4 group by og.shop_id");
        $undeliverynums = $one && $one[0]['total'] ? $one[0]['total'] : 0;
        $this->assign("undeliverynums", $undeliverynums);
        $this->assign("invoicenums", 0);
        $this->assign("version", microtime(true));
        $this->assign("act", Req::args("act"));
    }

    public function index() {
        $this->redirect("shopadmin/index");
    }

    public function order() {
        $this->assign("title", "订单管理");
        $status = Filter::str(Req::args("status"));
        $config = Config::getInstance();
        $config_other = $config->get('other');
        $valid_time = array();
        $valid_time[0] = isset($config_other['other_order_delay']) ? intval($config_other['other_order_delay']) : 0;
        $valid_time[1] = isset($config_other['other_order_delay_group']) ? intval($config_other['other_order_delay_group']) : 120;
        $valid_time[2] = isset($config_other['other_order_delay_flash']) ? intval($config_other['other_order_delay_flash']) : 120;
        $valid_time[3] = isset($config_other['other_order_delay_bund']) ? intval($config_other['other_order_delay_bund']) : 0;

        $query = new Query('order');
        $where = array("FIND_IN_SET('{$this->user['id']}',shop_ids)");
        switch ($status) {
            case "unpay":
                $where[] = "status <= '2'";
                break;
            case "undelivery":
                $where[] = "status = '3'";
                $where[] = "delivery_status = '0'";
                break;
            case "unreceived":
                $where[] = "status = '3'";
                $where[] = "delivery_status = '1'";
            case "uncomment":

                break;
        }
        $where[] = "status BETWEEN 3 AND 4";
        $where[] = "pay_status ='1'";
        $where[] = "is_del = 0";
        $where[] = "is_robot = 0";
        if ($where) {
            $where = implode(' AND ', $where);
        }
        $page = Filter::int(Req::args("p"));
        $page = $page > 0 ? $page : 1;
        $query->where = $where;
        $query->order = "id desc";
        $query->page = $page;
        $orders = $query->find();
        $order_id = array();
        $now = time();
        $writelist = array();
        foreach ($orders as &$order) {
            $writelist[$order['id']] = 0;
        }
        $orders = $query->find();
        // $orders = $this->model->table('order')->where($where)->order('id desc')->findPage($page,10);
        
        $imglist = array();
        if ($writelist) {
            $goodslist = $this->model->table("order_goods AS og")->fields("og.product_id,og.order_id,og.goods_id,og.express_no,og.express_company_id,go.img,go.imgs,go.name")->join("goods AS go ON og.goods_id=go.id")->where("order_id IN (" . implode(',', array_keys($writelist)) . ") AND og.shop_id = '{$this->user['id']}'")->findAll();
            foreach ($goodslist as $k => $v) {
                $imglist[$v['order_id']][] = $v;
                $writelist[$v['order_id']] += ($v['express_no'] ? 1 : 0);
            }
        }
        $orders_list = $this->model->table("order AS o")->fields("o.id,go.img,o.accept_name,o.pay_time,o.delivery_status,og.goods_nums")->join("left join goods AS go ON og.goods_id=go.id left join order_goods as og on og.order_id=o.id")->where("FIND_IN_SET('{$this->user['id']}',o.shop_ids) and o.status BETWEEN 1 AND 4 and o.pay_status=1 and o.is_del=0 and o.is_robot=0 and og.shop_id = '{$this->user['id']}'")->order('o.id desc')->findPage($page,10);
        foreach ($orders as $k => &$v) {
            $v['imglist'] = isset($imglist[$v['id']]) ? $imglist[$v['id']] : array();
            $v['express_status'] = $writelist[$v['id']] == count($v['imglist']) ? 'finished' : 'inprogress';
            $v['img'] = isset($v['imglist'][0]['img']) ? $v['imglist'][0]['img'] : '';
            $orders[$k]['goods_count'] = count($v['imglist']);
        }
        unset($v);
        //处理过期订单状态
        if (count($order_id) > 0) {
            $ids = implode(',', $order_id);
            $order_model = new Model('order');
            $data = array("status" => 6);
            $order_model->where("id in (" . $ids . ")")->data($data)->update();
        }
        $pagelist = $query->pageBar(2);
        
        $this->assign("status", $status);
        $this->assign("where", $where);
        $this->assign("page", $page);
        $this->assign("orderlist", $orders);
        $this->assign("pagelist", $pagelist);
        // var_dump($where);die;
        if ($this->is_ajax_request()) {
            Req::args('act', 'order_ajax');
            ob_start();
            $this->redirect("shopadmin/order_ajax", true, $this->datas);
            $content = ob_get_contents();
            ob_clean();
            // echo json_encode(array('contentlist' => $content, 'pagelist' => $pagelist));
            echo json_encode(array('contentlist' => $orders, 'pagelist' => $pagelist));
            exit;
        } else {
            $this->redirect("shopadmin/order");
        }
    }
    public function update_order_expressno(){
        $id = Filter::int(Req::args("id"));
        $orderinfo = $this->model->table("order")->where("id='{$id}'")->find();
        if (!$orderinfo) {
           echo json_encode(array('status' => 'fail', 'msg' => '订单不存在'));
           exit;
        }
        $express_company_id = Filter::sql(Req::args("express_company_id"));
        $express_no = Filter::sql(Req::args("express_no"));

        $totalshops = is_numeric($orderinfo['shop_ids']) ? 1 : count(explode(',', $orderinfo['shop_ids']));
        $isExpress = $this->model->table("order_goods")->where("order_id = $id and shop_id =".$this->user['id']." and express_no !='' and express_company_id !=''")->count();
        if($isExpress){
            
            //变更单个商家订单的物流信息
            $result1= $this->model->table("order_goods")->data(array('express_no' => $express_no, 'express_company_id' => $express_company_id))
                        ->where("order_id='{$orderinfo['id']}' AND shop_id='{$this->user['id']}'")
                        ->update();
            //变更发货单
            $isWriteInvoice = $this->model->table("doc_invoice")->where("order_id = $id and admin ='".$this->user['username']."'")->count();
            if($isWriteInvoice){
               $result2 =$this->model->table("doc_invoice")->data(array("express_no"=>$express_no,"express_company_id"=>$express_company_id))
                    ->where("order_id = $id and admin = '".$this->user['username']."'")
                    ->update();
            }else{
                $data["admin"] = $this->user['username'];
                $data["create_time"] = date('Y-m-d H:i:s');
                $data["invoice_no"] = date('YmdHis') . rand(100, 999);
                $data["order_id"] = $orderinfo['id'];
                $data["order_no"] = $orderinfo['order_no'];
                $data["accept_name"] = $orderinfo['accept_name'];
                $data["province"] = $orderinfo['province'];
                $data["city"] = $orderinfo['city'];
                $data["county"] = $orderinfo['county'];
                $data["addr"] = $orderinfo['addr'];
                $data["zip"] = $orderinfo['zip'];
                $data["mobile"] = $orderinfo['mobile'];
                $data["phone"] = $orderinfo['phone'];
                $data['express_no'] = $express_no;
                $data['express_company_id'] = $express_company_id;
                $result2 = $this->model->table("doc_invoice")->data($data)->insert();
            }
            if($result1 && $result2){
                 Log::op("", "修改快递单", "商户[" . $this->user['username'] . "]:修改了快递单，订单号 " . $orderinfo['order_no']);
                 echo json_encode(array('status' => 'success', 'msg' => '操作成功'));
                 exit;
            }else{
                 echo json_encode(array('status' => 'fail', 'msg' => '数据库更新异常'));
                  exit;
            }
        }else{
            echo json_encode(array('status' => 'fail', 'msg' => '订单未填写过快递信息'));
            exit;     
        }
        
    }
    
    public function order_detail() {
        $id = Filter::int(Req::args("id"));
        $orderinfo = $this->model->table("order")->where("id='{$id}'")->find();
        if (!$orderinfo) {
            $msg = array('status' => 'warn', 'msg' => '信息未找到');
            $this->redirect("shopadmin/msg", TRUE, $msg);
        }
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $express_company_id = Filter::sql(Req::args("express_company_id"));
            $express_no = Filter::sql(Req::args("express_no"));
            if($express_company_id==""||$express_no==""){
                echo json_encode(array('status' => 'fail', 'msg' => '上传的发货信息为空'));
                exit(); 
            }
            //判断该订单是否已经全部发货了
            if($orderinfo['delivery_status']==1 || $orderinfo['delivery_status']==2){
                echo json_encode(array('status' => 'fail', 'msg' => '该订单已经全部发货了'));
                exit(); 
            }
            //更新发货信息，在order_goods表中。默认同一商家的商品通过一个物流单发送
            $result1 = $this->model->table("order_goods")->data(array('express_time' => date("Y-m-d H:i:s"), 'express_no' => $express_no, 'express_company_id' => $express_company_id))
                    ->where("order_id='{$orderinfo['id']}' AND shop_id='{$this->user['id']}'")
                    ->update();
            if(!$result1){
                 echo json_encode(array('status' => 'fail', 'msg' => '更新商家物流失败'));
                 exit();
            }  
            //多物流可以生成多个快递单
            //判断是否生成过快递单,防止重复
            $isWriteInvoice = $this->model->table("doc_invoice")->where("order_id = $id and admin ='".$this->user['username']."'")->count();
            if($isWriteInvoice==0){
                $data["admin"] = $this->user['username'];
                $data["create_time"] = date('Y-m-d H:i:s');
                $data["invoice_no"] = "S".date('YmdHis') . rand(100, 999);
                $data["order_id"] = $orderinfo['id'];
                $data["order_no"] = $orderinfo['order_no'];
                $data["accept_name"] = $orderinfo['accept_name'];
                $data["province"] = $orderinfo['province'];
                $data["city"] = $orderinfo['city'];
                $data["county"] = $orderinfo['county'];
                $data["addr"] = $orderinfo['addr'];
                $data["zip"] = $orderinfo['zip'];
                $data["mobile"] = $orderinfo['mobile'];
                $data["phone"] = $orderinfo['phone'];
                $data['express_no'] = $express_no;
                $data['express_company_id'] = $express_company_id;
                $result2 = $this->model->table("doc_invoice")->data($data)->insert();
                if(!$result2){
                     echo json_encode(array('status' => 'fail', 'msg' => '生成快递单失败'));
                     exit();
                 } 
            }
            //如果最后一个商家发货了，就改变发货状态
            //查询未发货的记录，并且将订单中同商家的商品视作一起发货
            $undeliveryCount = $this->model->query("SELECT COUNT(DISTINCT shop_id) AS nums FROM tiny_order_goods WHERE order_id = '{$id}' AND (express_no IS NULL OR express_no ='')");
            if($undeliveryCount[0]['nums']==0){//商品已经全部发货了
                $result3 = $this->model->table("order")->data(array('delivery_status' => 1))->where("id='{$id}'")->update();
                //同步物流信息，主要是支付宝
                $payment_id = $orderinfo['payment'];
                $payment = new Payment($payment_id);
                $payment_plugin = $payment->getPaymentPlugin();
                $express_company = $model->table('express_company')->where('id=' . $express_company_id)->find();
                if ($express_company)
                    $express = $express_company['name'];
                else
                    $express = $express_company_id;
                //处理同步发货
                $delivery = $payment_plugin->afterAsync();
                if ($delivery != null && method_exists($delivery, "send"))
                    $delivery->send($orderinfo['order_no'], $express, $express_no);
                
                if(!$result3){
                    echo json_encode(array('status' => 'fail', 'msg' => '更新总订单配送状态失败'));
                    file_put_contents("shopDeliveryError.txt", "更新总订单配送状态失败：\r\n"."订单id:{$orderinfo['id']} 订单号：{$orderinfo['order_no']} 出错时间：".date("Y-m-d H:i:s")."\r\n__________________________",FILE_APPEND);
                    exit();
                }
            }
            echo json_encode(array('status' => 'success', 'msg' => '操作成功'));
            exit;   
       }
        $goodslist = $this->model->table("order_goods as og")
                        ->join("goods as go on og.goods_id=go.id")
                        ->fields("og.*,go.name,go.img,go.goods_no,go.pro_no")
                        ->where("og.order_id='{$id}' AND og.shop_id='{$this->user['id']}'")->findAll();
        $area_ids = $orderinfo['province'] . ',' . $orderinfo['city'] . ',' . $orderinfo['county'];
        if ($area_ids != '')
            $areas = $this->model->table("area")->where("id in ($area_ids)")->findAll();
        $parse_area = array();
        foreach ($areas as $area) {
            $parse_area[$area['id']] = $area['name'];
        }
        $orderinfo['address'] = implode(' ', $parse_area);
        $totalamount = 0;
        $expressnums = 0;
        foreach ($goodslist as $k => &$v) {
            $v['spec'] = unserialize($v['spec']);
            $spec = $v['spec'];
            $speclist = array();
            if (is_array($spec)) {
                foreach ($spec as $sp) {
                    $speclist[] = $sp['value'][2];
                }
            }
            $v['speclist'] = implode(' / ', $speclist);
            $totalamount += $v['real_price'];
            $expressnums+=($v['express_no'] ? 1 : 0);
        }
        unset($v);
        $orderinfo['totalamount'] = $totalamount;
        $expressdata = $this->model->table("express_company")->findAll();
        $expresslist = array();
        foreach ($expressdata as $k => $v) {
            $expresslist[] = array('value' => $v['id'], 'title' => $v['name']);
        }
        $orderinfo['express_status'] = $expressnums == count($goodslist) ? 'finished' : 'inprogress';
        $expressinfo = array();
        $goodsone = reset($goodslist);
        if ($orderinfo['express_status'] && $goodsone) {
            $expressinfo = $this->model->table("express_company")->where("id='{$goodsone['express_company_id']}'")->find();
            if ($expressinfo) {
                $expressinfo['express_no'] = $goodsone['express_no'];
            }
        }
        $type = array(
            '0' => '普通',
            '1' => '团购',
            '2' => '抢购',
            '3' => '绑定', 
            '4' => '华币', 
            '5' => '积分', 
            '6' => '积分抢购'
            );
        $this->assign('type',$type);
        $this->assign("expressinfo", $expressinfo);
        $this->assign("orderinfo", $orderinfo);
        $this->assign("expresslist", $expresslist);
        $this->assign("goodslist", $goodslist);
        $this->assign("title", "订单详情");
        $this->redirect("shopadmin/order_detail");
    }

    public function invoice() {
        $this->assign("title", "发货管理");
        $status = Filter::str(Req::args("status"));
        $config = Config::getInstance();

        $where = array("og.shop_id='{$this->user['id']}'", "od.status BETWEEN 3 AND 4", "og.express_no!=''");
        if ($where) {
            $where = implode(' AND ', $where);
        }
        $page = Filter::int(Req::args("p"));
        $page = $page > 0 ? $page : 1;
        $invoicedata = $this->model->table("order_goods as og")
                        ->join("left join goods as go ON og.goods_id=go.id left join tiny_order as od ON og.order_id=od.id")
                        ->where($where)
                        ->group("order_id")
                        ->fields("COUNT(*) AS total,og.id,og.express_no,og.express_company_id,og.express_time,og.goods_nums,og.order_id,od.accept_name,go.name,go.img")
                        ->order("og.id DESC")->findPage($page,10, 5);

        $invoicelist = $invoicedata['data'];
        $pagelist = $invoicedata['html'];
        $express_ids = array();
        if(!empty($invoicelist)){
            foreach ($invoicelist as $k => $v) {
                $express_ids[] = $v['express_company_id'];
            }
        }
        //查询物流公司名称
        $expresslist = array();
        if ($express_ids) {
            $tmplist = $this->model->table("express_company")->where("id IN (" . implode(',', $express_ids) . ")")->findAll();
            foreach ($tmplist as $k => $v) {
                $expresslist[$v['id']] = $v;
            }
        }
        $expressdata = $this->model->table("express_company")->findAll();
        $expresslistall = array();
        foreach ($expressdata as $k => $v) {
            $expresslistall[] = array('value' => $v['id'], 'title' => $v['name']);
        }
        $this->assign("expresslistall", $expresslistall);
        $this->assign("expresslist", $expresslist);
        $this->assign("status", $status);
        $this->assign("where", $where);
        $this->assign("invoicelist", $invoicelist);
        $this->assign("pagelist", $pagelist);
        if ($this->is_ajax_request()) {
            Req::args('act', 'invoice_ajax');
            ob_start();
            $this->redirect("shopadmin/invoice_ajax", true, $this->datas);
            $content = ob_get_contents();
            ob_clean();
            echo json_encode(array('contentlist' => $content, 'pagelist' => $pagelist));
            exit;
        } else {
            $this->redirect("shopadmin/invoice");
        }
    }

    public function profile() {
        $tips = array();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $oldpassword = Filter::sql(Req::args("oldpassword"));
            $password = Filter::sql(Req::args("password"));
            $repassword = Filter::sql(Req::args("repassword"));
            if ($oldpassword && $password && $repassword) {
                if (md5(md5($oldpassword) . $this->user['salt']) == $this->user['password']) {
                    if ($password == $repassword) {
                        $salt = CHash::random(6);
                        $password = md5(md5($password) . $salt);
                        $this->model->data(array('password' => $password, 'salt' => $salt))->where("id='{$this->user['id']}'")->update();
                        $this->logout();
                        $tips = array('status' => 'success', 'msg' => "密码更新成功!");
                    } else {
                        $tips = array('status' => 'warn', 'msg' => "两次输入的密码不相同!");
                    }
                } else {
                    $tips = array('status' => 'warn', 'msg' => "旧密码错误!");
                }
            } else {
                $tips = array('status' => 'warn', 'msg' => "密码不能为空!");
            }
        }
        $this->assign("tips", $tips);
        $this->assign("title", "安全中心");
        $this->redirect("shopadmin/profile");
    }

    public function login() {
        $tips = array();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $account = Filter::sql(Req::args("username"));
            $password = Filter::sql(Req::args("password"));
            $obj = $this->model->table("shop")->where("username='$account'")->find();
            if ($obj && $obj['password'] == md5(md5($password) . $obj['salt'])) {
                $obj = array_intersect_key($obj, array_flip(array('id', 'username', 'password', 'name')));
                $this->cookie->set("shoplogin", $obj);
                $this->redirect("/shopadmin/index");
            } else {
                $tips = array('status' => 'warn', 'msg' => "用户名或密码错误");
            }
        }
        $this->assign("tips", $tips);
        $this->assign("title", "登录");
        $this->redirect("/shopadmin/login");
    }

    public function logout() {
        $this->cookie->clear("shoplogin");
        $this->safebox->clear('shopuser');
        $tips = array('status' => 'success', 'msg' => "登出成功!");
        $this->redirect("/shopadmin/login");
    }

    public function get_express_info() {
        $id = Filter::int(Req::args("id"));
        $number = Req::args("number");
        $data = NULL;
        $ret = array('status' => 'fail', 'data' => $data);
        if ($id && $number) {
            $companyinfo = $this->model->table("express_company")->where("id='{$id}'")->find();
            if ($companyinfo) {
               $data = Common::getExpress($companyinfo['alias'], $number);
                if($data['message']=='ok'&&$data['status']){
                    $ret['status']='success';
                    $ret['data']['content']=$data['data'];
                }
            }
        }
        echo json_encode($ret);
    }

    private function checkLogin() {
        $shoplogin = $this->cookie->get('shoplogin');
        $obj = null;
        if ($shoplogin != null) {
            $username = Filter::sql($shoplogin['username']);
            $password = $shoplogin['password'];
            $obj = $this->model->where("username='$username'")->find();
            if ($obj['password'] != $password) {
                $obj = null;
            }
        }
        return $obj;
    }

    public function checkRight($actionId) {
        if (isset($this->needRightActions[$actionId]) && $this->needRightActions[$actionId]) {
            if (isset($this->user['username']) && $this->user['username'] != null)
                return true;
            else
                return false;
        } else {
            return true;
        }
    }

    public function noRight() {
        if (Common::checkInWechat()) {
            Cookie::set("url", Url::pathinfo());
            $wechat = new WechatOAuth();
            $url = $wechat->getRequestCodeURL();
            $this->redirect($url);
            exit;
        }
        $this->redirect("/shopadmin/login");
    }

}
