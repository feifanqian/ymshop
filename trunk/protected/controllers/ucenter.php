<?php

class UcenterController extends Controller {

    public $layout = 'index';
    public $safebox = null;
    private $model = null;
    private $category = array();
    private $cookie_time = 31622400;
    public $sidebar = array(
//        '交易管理' => array(
//            '我的订单' => 'order',
//            '退款申请' => 'refund',
//            '我的关注' => 'attention',
//        ),
        '客户服务' => array(
//            '商品咨询' => 'consult',
            '商品评价' => 'review',
            '我的消息' => 'message',
        ),
        '账户管理' => array(
            '个人资料' => 'info',
            '收货地址' => 'address',
//            '我的优惠券' => 'voucher',
//            '账户金额' => 'account',
            '账户安全' => 'safety',
//            '我的积分' => 'point',
        )
    );
    

    public function init() {
        header("Content-type: text/html; charset=" . $this->encoding);
        $this->model = new Model();
        $this->safebox = Safebox::getInstance();
        $this->user = $this->safebox->get('user');
        if ($this->user == null) {
            $this->user = Common::autoLoginUserInfo();
            $this->safebox->set('user', $this->user);
        }
        $category = Category::getInstance();
        $this->category = $category->getCategory();
        $cart = Cart::getCart();
        $action = Req::args("act");
        switch ($action) {
            case 'order_detail':
                $action = 'order';
                break;
            case 'refund_detail':
                $action = 'refund';
            case 'check_identity':
                $action = 'safety';
            case 'update_obj':
                $action = 'safety';
            case 'update_obj_success':
                $action = 'safety';
                break;
        }
        $config = Config::getInstance();
        $site_config = $config->get("globals");
        $this->assign('site_title', $site_config['site_name']);
        $this->assign("actionId", $action);
        $this->assign("cart", $cart->all());
        $this->assign("sidebar", $this->sidebar);
        $this->assign("category", $this->category);
        $this->assign("url_index", '');
        $this->assign("seo_title", "用户中心");
    }

    public function checkRight($actionId) {
        if (isset($this->user['name']) && $this->user['name'] != null){
            if($this->user['mobile']==""&&$actionId!='firstbind'){
                $this->redirect('firstbind');
                exit();
            }
            return true;
        }else{
            return false;
        }
    }

    public function noRight() {
        Cookie::set("url", Url::requestUri());
        if (Common::checkInWechat()) {
            $wechat = new WechatOAuth();
            $url = $wechat->getRequestCodeURL();
            $this->redirect($url);
            exit;
        }
        $this->redirect("/simple/login");
    }

    //生成邀请码
    public function buildinvite() {
        $user_id = Filter::int(Req::args('uid'));
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
            $wechatcfg = $this->model->table("oauth")->where("class_name='WechatOAuth'")->find();
            $wechat = new WechatMenu($wechatcfg['app_key'], $wechatcfg['app_secret'], '');
            $token = $wechat->getAccessToken();
            $params = array(
                "action_name" => "QR_LIMIT_STR_SCENE",
                "action_info" => array("scene" => array("scene_str" => "invite-{$user_id}"))
            );
            $ret = Http::curlPost("https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token={$token}", json_encode($params));
            $ret = json_decode($ret, TRUE);
            if (isset($ret['ticket'])) {
                $this->redirect("https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket={$ret['ticket']}");
                exit;
            }
        }
        $url = Url::fullUrlFormat("/index/invite") . "?inviter_id=" . $this->user['id'];
        $qrCode = new QrCode();
        $qrCode
                ->setText($url)
                ->setSize(300)
                ->setPadding(10)
                ->setErrorCorrection('medium')
                ->setForegroundColor(array('r' => 0, 'g' => 0, 'b' => 0, 'a' => 0))
                ->setBackgroundColor(array('r' => 255, 'g' => 255, 'b' => 255, 'a' => 0))
                //->setLabel('扫描添加为好友')
                ->setLabelFontSize(16)
                ->setImageType(QrCode::IMAGE_TYPE_PNG);

        // now we can directly output the qrcode
        header('Content-Type: ' . $qrCode->getContentType());
        $qrCode->render();
        return;
    }

    //新的提现操作
    public function balance_withdraw() {
        if ($this->is_ajax_request()) {
            Filter::form();
            $open_name = Filter::str(Req::args('name'));
            $open_bank = Filter::str(Req::args('bank'));
            $prov = Filter::str(Req::args('province'));
            $city = Filter::str(Req::args("city"));
            $card_no = str_replace(' ', '', Filter::str(Req::args('card_no')));
            $amount = Filter::float(Req::args('amount'));
            $amount = round($amount, 2);
            $customer = $this->model->table("customer")->where("user_id =".$this->user['id'])->fields('balance')->find();
            $can_withdraw_amount =$customer?$customer['balance']:0;
            if ($can_withdraw_amount < $amount) {//提现金额中包含 暂时不能提现部分 
                exit(json_encode(array('status' => 'fail', 'msg' => '提现金额超出的账户可提现余额')));
            }
            $config = Config::getInstance();
            $other = $config->get("other");
            if ($amount < $other['min_withdraw_amount']) {
                exit(json_encode(array('status' => 'fail', 'msg' => "提现金额少于" . $other['min_withdraw_amount'])));
            }
            $isset = $this->model->table("balance_withdraw")->where("user_id =" . $this->user['id'] . " and status =0")->find();
            if ($isset) {
                exit(json_encode(array('status' => 'fail', 'msg' => '申请失败，还有未处理完的提现申请')));
            }
            $withdraw_no = "BW" . date("YmdHis") . rand(100, 999);
            $data = array("withdraw_no" => $withdraw_no, "user_id" => $this->user['id'], "amount" => $amount, 'open_name' => $open_name, "open_bank" => $open_bank, 'province' => $prov, "city" => $city, 'card_no' => $card_no, 'apply_date' => date("Y-m-d H:i:s"), 'status' => 0);
            $result = $this->model->table('balance_withdraw')->data($data)->insert();
            if ($result) {
                exit(json_encode(array('status' => 'success', 'msg' => "申请提交成功")));
            } else {
                exit(json_encode(array('status' => 'fail', 'msg' => '申请提交失败，数据库错误')));
            }
        } else {
            $config = Config::getInstance();
            $other = $config->get("other");
            $info = $this->model->table("customer")->where("user_id=" . $this->user['id'])->find();
            $this->assign("goldcoin", $info['balance']);
            $this->assign("gold2silver", $other['gold2silver']);
            $this->assign("withdraw_fee_rate", $other['withdraw_fee_rate']);
            $this->assign('min_withdraw_amount', $other['min_withdraw_amount']);
            $this->assign('seo_title', '余额提现');
            $this->redirect();
        }
    }

    public function point() {
        $customer = $this->model->table("customer")->where("user_id=" . $this->user['id'])->find();
        $this->assign("customer", $customer);
        $this->redirect();
    }

    public function point_exchange() {
        $id = Filter::int(Req::args('id'));
        $voucher = $this->model->table("voucher_template")->where("id=$id")->find();
        if ($voucher) {
            $use_point = 0 - $voucher['point'];
            $result = Pointlog::write($this->user['id'], $use_point, '积分兑换代金券，扣除了' . $use_point . '积分');
            if (true === $result) {
                Common::paymentVoucher($voucher, $this->user['id']);
                $info = array('status' => 'success');
            } else {
                $info = array('status' => 'fail', 'msg' => $result['msg']);
            }
            echo JSON::encode($info);
        } else {
            $info = array('status' => 'fail', 'msg' => '你要兑换的代金券，不存在！');
            echo JSON::encode($info);
        }
    }

    public function upload_head() {
        $upfile_path = Tiny::getPath("uploads") . "head/";
        $upfile_url = preg_replace("|" . APP_URL . "|", '', Tiny::getPath("uploads_url") . "head/", 1);
        echo $upfile_path . "+" . $upfile_url;
        die;
        //$upfile_url = strtr(Tiny::getPath("uploads_url")."head/",APP_URL,'');
        $upfile = new UploadFile('imgFile', $upfile_path, '500k', '', 'hash', $this->user['id']);
        $upfile->save();
        $info = $upfile->getInfo();
        $result = array();
        if ($info[0]['status'] == 1) {
            $result = array('error' => 0, 'url' => $upfile_url . $info[0]['path']);
            $image_url = $upfile_url . $info[0]['path'];
            $image = new Image();
            $image->suffix = '';
            $image->thumb(APP_ROOT . $image_url, 100, 100);
            $model = new Model('user');
            $model->data(array('avatar' => $image_url))->where("id=" . $this->user['id'])->update();

            $safebox = Safebox::getInstance();
            $user = $this->user;
            $user['avatar'] = $image_url;
            $safebox->set('user', $user);
        } else {
            $result = array('error' => 1, 'message' => $info[0]['msg']);
        }
        echo JSON::encode($result);
    }

    public function account() {
        $customer = $this->model->table("customer")->where("user_id=" . $this->user['id'])->find();
        $this->assign("customer", $customer);
        $client_type = Chips::clientType();
        $client_type = ($client_type == "desktop") ? 0 : ($client_type == "wechat" ? 2 : 1);
        $model = new Model("payment as pa");
        $paytypelist = $model->fields("pa.*,pp.logo,pp.class_name")->join("left join pay_plugin as pp on pa.plugin_id = pp.id")
                        ->where("pa.status = 0 and pa.plugin_id not in(1,12,19,20) and pa.client_type = $client_type")->order("pa.sort desc")->findAll();
        $paytypeone = reset($paytypelist);
        $this->assign("paytypelist", $paytypelist);
        //充值套餐的地址
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
        $this->assign("parse_area", $parse_area);
        $this->assign('address', $address);
        $config = Config::getInstance();
        $other = $config->get("other");
        $package_set = $config->get("recharge_package_set");
        if (is_array($package_set)) {
            if(isset($package_set[4]['gift'])&&$package_set[4]['gift']!=''){
                $where = implode(',', array_reverse(explode("|", $package_set[4]['gift'])));
                $select4 = $this->model->table("products as p")->join("goods as g on p.goods_id=g.id")->where("p.id in ({$where})")->fields("p.id,g.img,g.name")->order("field(p.id,$where)")->findAll();
                $this->assign("select4", $select4);
            }
        }
        $this->assign("withdraw_fee_rate", $other['withdraw_fee_rate']);
        $this->assign('min_withdraw_amount', $other['min_withdraw_amount']);
        $package = Filter::int(Req::args('package'));
        //套餐充值
        $pid = Filter::int(Req::args('pid'));
        $this->assign('package_set',$package_set);
        if ($package && $pid) {
            $this->assign("package", $package);
            $this->assign("pid", $pid);
        }
        $this->redirect();
    }

    public function refund_act() {
        $order_no = Filter::sql(Req::args('order_no'));
        $order = $this->model->table("order")->where("order_no='$order_no' and user_id = " . $this->user['id'])->find();
        if ($order) {
            if ($order['pay_status'] == 1) {
                $refund = $this->model->table("doc_refund")->where("order_no='$order_no' and user_id = " . $this->user['id'])->find();
                if ($refund) {
                    $this->redirect("refund", false, array('msg' => array("warning", "不能重复申请退款！")));
                } else {
                    Filter::form(array('text' => 'account_name|refund_account|account_bank|content', 'int' => 'order_no|refund_type'));
                    $data = array(
                        'account_name' => Req::args('account_name'),
                        'refund_account' => Req::args('refund_account'),
                        'account_bank' => Req::args('account_bank'),
                        'order_no' => Req::args('order_no'),
                        'refund_type' => Req::args('refund_type'),
                        'create_time' => date('Y-m-d H:i:s'),
                        'user_id' => $this->user['id'],
                        'order_id' => $order['id'],
                        'content' => Req::args('content'),
                        'pay_status' => 0
                    );
                    $this->model->table("doc_refund")->data($data)->insert();
                    $this->redirect("refund", false, array('msg' => array("success", "申请已经成功提交,请等候处理！")));
                }
            } else {
                $this->redirect("refund", false, array('msg' => array("warning", "此订单还未支付，无法申请退款！")));
            }
        } else {
            $this->redirect("refund", false, array('msg' => array("warning", "此订单编号不存在！")));
        }
    }

    public function refund_detail() {
        $id = Filter::int(Req::args('id'));
        $refund = $this->model->table("doc_refund")->where("id=$id and user_id=" . $this->user['id'])->find();
        if ($refund) {
            $this->assign("refund", $refund);
            $this->redirect();
        } else {
            Tiny::Msg($this, 404);
        }
    }

    public function refund_del() {
        $order_no = Filter::sql(Req::args('order_no'));
        $obj = $this->model->table("doc_refund")->where("order_no='$order_no' and  pay_status=0 and user_id = " . $this->user['id'])->delete();
        $this->redirect("refund");
    }

    public function voucher_activated() {
        if (!Tiny::app()->checkToken())
            $this->redirect("voucher");
        $rules = array('account:required:账号不能为空!', 'password:required:密码不能为空！');
        $info = Validator::check($rules);
        if (!is_array($info) && $info == true) {
            Filter::form(array('sql' => 'account'));
            $account = Filter::sql(Req::args("account"));
            $voucher = $this->model->table("voucher")->where("account='$account' and is_send = 0")->find();
            if ($voucher && $voucher['password'] == Req::args("password")) {
                if (strtotime($voucher['end_time']) > time()) {
                    if ($voucher['status'] == 0) {
                        $this->model->table("voucher")->data(array('user_id' => $this->user['id'], 'is_send' => 1, 'status' => 0))->where("account='$account'")->update();
                        $this->redirect("voucher", false, array('msg' => array("success", "优惠券成功激活！")));
                    } else {
                        $this->redirect("voucher", false, array('msg' => array("warning", "此优惠券已使用过！")));
                    }
                } else {
                    //过期
                    $this->redirect("voucher", false, array('msg' => array("warning", "优惠券已过期！")));
                }
            } else {
                //不存在此优惠券
                $this->redirect("voucher", false, array('msg' => array("error", "优惠券账号或密码错误！")));
            }
        } else {
            //输入信息有误
            $this->redirect("voucher", false, array('msg' => array("info", "输入的信息不格式不正确")));
        }
    }

    public function get_consult() {
        $page = Filter::int(Req::args("page"));
        $type = Filter::int(Req::args("type"));
        $status = Req::args("status");
        $where = "ak.user_id = " . $this->user['id'];
        switch ($status) {
            case 'n':
                $where .= " and ak.status = 0";
                break;
            case 'y':
                $where .= " and ak.status = 1";
                break;
            default:
                break;
        }
        $ask = $this->model->table("ask as ak")->join("left join goods as go on ak.goods_id = go.id")->fields("ak.*,go.name,go.id as gid,go.img,go.sell_price")->where($where)->order("ak.id desc")->findPage($page, 10, $type, true);
        foreach ($ask['data'] as $key => $value) {
            $ask['data'][$key]['img'] = Common::thumb($value['img'], 100, 100);
        }
        $ask['status'] = "success";
        echo JSON::encode($ask);
    }

    //获取商品评价
    public function get_review() {
        $page = Filter::int(Req::args("page"));
        $type = Filter::int(Req::args("type"));
        $status = Req::args("status");
        $where = "re.user_id = " . $this->user['id'];
        switch ($status) {
            case 'n':
                $where .= " and re.status = 0";
                break;
            case 'y':
                $where .= " and re.status = 1";
                break;
            default:
                break;
        }
        $review = $this->model->table("review as re")->join("left join goods as go on re.goods_id = go.id left join order as rd on rd.order_no = re.order_no")->fields("re.*,go.name,rd.accept_name,rd.id as order_id,go.id as gid,go.img as img,go.sell_price")->where($where)->order("re.id desc")->findPage($page, 10, $type, true);
        $data = $review['data'];
        if (empty($data)) {
            echo JSON::encode(array('status' => 'fail'));
            exit();
        }
        foreach ($data as $key => $value) {
            $value['img'] = Url::urlFormat("@" . $value['img']);
            $value['point'] = ($value['point'] / 5) * 100;
            $data[$key] = $value;
        }
        $review['status'] = "success";
        $review['data'] = $data;
        echo JSON::encode($review);
    }

    //获取商品评价
    public function get_message() {
        $page = Filter::int(Req::args("page"));
        $type = Filter::int(Req::args("type"));
        $status = Req::args("status");
        $where = "";
        $customer = $this->model->table("customer")->where("user_id=" . $this->user['id'])->find();
        $message_ids = '';
        if ($customer) {
            $message_ids = ',' . $customer['message_ids'] . ',';
            switch ($status) {
                case 'y':
                    $message_ids = preg_replace('/,\d+,/i', ',', $message_ids);
                    $message_ids = preg_replace('/-/i', '', $message_ids);
                    break;
                case 'n':
                    $message_ids = preg_replace('/,-\d+,/i', ',', $message_ids);
                    break;
                default:
                    break;
            }
            $message_ids = trim($message_ids, ',');
        }

        $message = array();
        if ($message_ids != '') {
            $message = $this->model->table("message")->where("id in ($message_ids)")->order("id desc")->findPage($page, 10, $type, true);
        }
        $message['status'] = "success";
        echo JSON::encode($message);
    }

    public function message_read() {
        $id = Filter::int(Req::args("id"));
        $customer = $this->model->table("customer")->where("user_id=" . $this->user['id'])->find();
        $message_ids = ',' . $customer['message_ids'] . ',';
        $message_ids = str_replace(",$id,", ',-' . $id . ',', $message_ids);
        $message_ids = trim($message_ids, ',');
        $this->model->table("customer")->where("user_id=" . $this->user['id'])->data(array('message_ids' => $message_ids))->update();
        echo JSON::encode(array("status" => 'success'));
    }

    public function message_del() {
        $id = Filter::int(Req::args("id"));
        $customer = $this->model->table("customer")->where("user_id=" . $this->user['id'])->find();
        $message_ids = ',' . $customer['message_ids'] . ',';
        $message_ids = str_replace(",-$id,", ',', $message_ids);
        $message_ids = rtrim($message_ids, ',');
        $this->model->table("customer")->where("user_id=" . $this->user['id'])->data(array('message_ids' => $message_ids))->update();
        echo JSON::encode(array("status" => 'success'));
    }

    public function get_voucher() {
        $page = Filter::int(Req::args("page"));
        $pagetype = Filter::int(Req::args("pagetype"));
        $status = Req::args("status");
        $where = "user_id = " . $this->user['id'] . " and is_send = 1";
        switch ($status) {
            case 'n':
                $where .= " and status = 0 and '" . date("Y-m-d H:i:s") . "' <=end_time";
                break;
            case 'u':
                $where .= " and status = 1";
                break;
            case 'p':
                $where .= " and status = 0 and '" . date("Y-m-d H:i:s") . "' > end_time";
                break;
            default:
                break;
        }
        $voucher = $this->model->table("voucher")->where($where)->order("id desc")->findPage($page, 10, $pagetype, true);
        $data = $voucher['data'];
        foreach ($data as $key => $value) {
            $value['start_time'] = substr($value['start_time'], 0, 10);
            $value['end_time'] = substr($value['end_time'], 0, 10);
            $data[$key] = $value;
        }
        $voucher['data'] = $data;
        $voucher['status'] = "success";
        echo JSON::encode($voucher);
    }

    public function get_express_info() {
        $id = Filter::int(Req::args("id"));
        $number = Req::args("number");
        $ret = array('status' => "fail", 'data' => NULL);
        if ($id && $number) {
            $companyinfo = $this->model->table("express_company")->where("id='{$id}'")->find();
            if ($companyinfo) {
                $data = Common::getExpress($companyinfo['alias'], $number);
                if ($data['message'] == 'ok' && $data['status']) {
                    $ret['status'] = 'success';
                    $ret['data']['content'] = $data['data'];
                }
            }
        }
        echo json_encode($ret);
    }

    public function info() {
        $info = $this->model->table("customer as cu ")->fields("cu.*,us.email,us.name,us.nickname,us.avatar,gr.name as gname")->join("left join user as us on cu.user_id = us.id left join grade as gr on cu.group_id = gr.id")->where("cu.user_id = " . $this->user['id'])->find();
        if ($info) {
            $this->assign("info", $info);
            $info = array_merge($info, Req::args());
            $this->redirect("info", false, $info);
        } else
            Tiny::Msg($this, 404);
    }

    public function info_save() {
        $rules = array('nickname:required:昵称不能为空!', 'real_name:required:真实姓名不能为空!', 'sex:int:性别必需选择！', 'birthday:date:生日日期格式不正确！', 'province:[1-9]\d*:选择地区必需完成', 'city:[1-9]\d*:选择地区必需完成', 'county:[1-9]\d*:选择地区必需完成');
        $info = Validator::check($rules);
        if (is_array($info)) {
            $this->redirect("info", false, array('msg' => array("info", $info['msg'])));
        } else {
            $data = array(
                'nickname' => Filter::txt(Req::args('nickname')),
                'real_name' => Filter::text(Req::args('real_name')),
                'sex' => Filter::int(Req::args('sex')),
                'birthday' => Filter::sql(Req::args('birthday')),
                'phone' => Filter::sql(Req::args('phone')),
                'province' => Filter::int(Req::args('province')),
                'city' => Filter::int(Req::args('city')),
                'county' => Filter::int(Req::args('county')),
                'addr' => Filter::text(Req::args('addr'))
            );

//            //如果用户之前没有绑定过手机号码，则执行这一步
//            if ($this->user['mobile'] == '') {
//                $mobile = Filter::int(Req::args('mobile'));
//                $obj = $this->model->table("customer")->where("mobile='$mobile'")->find();
//                $data['mobile'] = $mobile;
//                if ($obj) {
//                    $this->redirect("info", false, array('msg' => array("info", '此手机号已经存在！')));
//                    exit;
//                }
//            }
            if ($this->user['email'] == $this->user['mobile'] . '@no.com') {
                $email = Req::args('email');
                if (Validator::email($email)) {
                    $userData['email'] = $email;
                    $obj = $this->model->table("user")->where("email='$email'")->find();
                    if ($obj) {
                        $this->redirect("info", false, array('msg' => array("info", '此邮箱号已存在')));
                        exit;
                    }
                }
            }

            $id = $this->user['id'];
            $this->model->table("user")->data($data)->where("id=$id")->update();
            $this->model->table("customer")->data($data)->where("user_id=$id")->update();
            $obj = $this->model->table("user as us")->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.group_id,cu.login_time,cu.mobile")->where("us.id=$id")->find();
            $this->safebox->set('user', $obj, $this->cookie_time);
            $this->redirect("info", false, array('msg' => array("success", "保存成功！")));
        }
    }

    public function firstbind() {
        $info = $this->model->table("customer as cu ")->fields("cu.*,us.email,us.name,us.nickname,us.avatar,gr.name as gname")->join("left join user as us on cu.user_id = us.id left join grade as gr on cu.group_id = gr.id")->where("cu.user_id = " . $this->user['id'])->find();
        if ($info) {
            if($info['mobile']!=''){
                $user = $this->user;
                $user['mobile']=$info['mobile'];
                $user['real_name']=$info['real_name'];
                $this->safebox->set('user', $user);
                $this->redirect("index");
                exit();
            }
            $this->assign("info", $info);
            $info = array_merge($info, Req::args());
            if ($this->is_ajax_request()) {
                $realname = Filter::sql(Req::post('realname'));
                $mobile = Filter::sql(Req::post('mobile'));
                $validatecode = Filter::sql(Req::post('validatecode'));
                if($realname && $mobile && $validatecode){
                    $exist = $this->model->table("customer")->where("mobile='{$mobile}'")->find();
                    if (!$exist) {
                        $ret = SMS::getInstance()->checkCode($mobile, $validatecode);
                        if ($ret['status'] == 'success') {
                            $data = array(
                                'mobile' => $mobile,
                                'real_name'=>$realname,
                                'mobile_verified'=>1
                            );
                            $this->model->table("customer")->data($data)->where("user_id={$this->user['id']}")->update();
                            //默认密码
                            $passWord = $mobile;
                            $validcode = CHash::random(8);
                            $this->model->table('user')->data(array('password' => CHash::md5($passWord, $validcode),'validcode' => $validcode))->where("id={$this->user['id']}")->update();
                
                            SMS::getInstance()->flushCode($mobile);
                            $user = $this->user;
                            $user['mobile']=$mobile;
                            $user['real_name']=$realname;
                            $this->safebox->set('user', $user);
                            Common::sendPointCoinToNewComsumer($this->user['id']);
                            $ret["message"] = "验证成功";
                        } else {
                            $ret['message'] = "验证码不正确";
                        }
                    } else {
                        $ret['status'] = "fail";
                        $ret['message'] = "该手机号已经绑定过了";
                    }
                }else{
                    $ret['status'] = "fail";
                    $ret['message'] = "参数错误";
                }
                echo json_encode($ret);
                exit;
            }
            $this->layout = "";
            $this->redirect("firstbind");
        } else {
            Tiny::Msg($this, 404);
        }
    }

    public function invite() {
        $page = Filter::int(Req::args('p'));
        $invite = $this->model->table("invite as i")
                ->fields("i.*,u.nickname,u.avatar,cu.real_name")
                ->join("left join user as u on i.invite_user_id = u.id LEFT JOIN customer AS cu ON i.invite_user_id=cu.user_id")
                ->where("i.user_id = " . $this->user['id'])
                ->findPage($page,10,4);
        $this->assign("invite", $invite);
        $this->assign("seo_title", "我的邀请");
        $this->redirect();
    }

    public function attention() {
        $page = Filter::int(Req::args('p'));
        $attention = $this->model->table("attention as at")->fields("at.*,go.name,go.store_nums,go.img,go.sell_price,go.id as gid")->join("left join goods as go on at.goods_id = go.id")->where("at.user_id = " . $this->user['id'])->findPage($page);
        $this->assign("attention", $attention);
        $this->assign("seo_title", "我的关注");
        $this->redirect();
    }

    public function attention_del() {
        $id = Filter::int(Req::args("id"));
        if (is_array($id)) {
            $ids = implode(",", $id);
        } else {
            $ids = $id;
        }
        $this->model->table("attention")->where("id in($ids) and user_id=" . $this->user['id'])->delete();
        $this->redirect("attention");
    }

    public function attention_addcart() {
        $ids = Req::args("ids");
        $ids = array_filter(explode(',', $ids));
        if ($ids) {
            $cart = Cart::getCart();
            foreach ($ids as $key => $v) {
                $cart->addItem($v, 1);
            }
            echo JSON::encode(array('status' => 'success'));
        } else {
            echo JSON::encode(array('status' => 'fail'));
        }
    }

    public function attention_cancelattention() {
        $ids = Req::args("ids");
        $ids = array_filter(explode(',', $ids));
        if ($ids) {
            $this->model->table("attention")->where("id in(" . implode(',', $ids) . ") and user_id=" . $this->user['id'])->delete();
            echo JSON::encode(array('status' => 'success'));
        } else {
            echo JSON::encode(array('status' => 'fail'));
        }
    }

    //商品展示与商品状态修改
    public function order() {
        $status = Filter::str(Req::args("status"));
        $config = Config::getInstance();
        $config_other = $config->get('other');
        $valid_time = array();
        $valid_time[0] = isset($config_other['other_order_delay']) ? intval($config_other['other_order_delay']) : 0;
        $valid_time[1] = isset($config_other['other_order_delay_group']) ? intval($config_other['other_order_delay_group']) : 120;
        $valid_time[2] = isset($config_other['other_order_delay_flash']) ? intval($config_other['other_order_delay_flash']) : 120;
        $valid_time[3] = isset($config_other['other_order_delay_bund']) ? intval($config_other['other_order_delay_bund']) : 0;
        $valid_time[5] = isset($config_other['other_order_delay_point']) ? intval($config_other['other_order_delay_point']) : 0;
        $valid_time[6] = isset($config_other['other_order_delay_pointflash']) ? intval($config_other['other_order_delay_pointflash']) : 0;
        $query = new Query('order');
        $where = array("user_id = " . $this->user['id'], 'is_del = 0');
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
        if ($where) {
            $where = implode(' AND ', $where);
        }
        $query->where = $where;
        $query->order = "id desc";
        $query->page = 1;
        $orders = $query->find();
        $order_id = array();
        $now = time();
        $ids = array();
        foreach ($orders as $order) {
            if ($order['pay_status'] == 0 && $order['status'] <= 3) {
                if (isset($valid_time[$order['type']])) {
                    $time = $valid_time[$order['type']] * 60;
                    if ($time && $now - strtotime($order['create_time']) >= $time) {
                        $order_id[] = $order['id'];
                    }
                }
            }
            $ids[] = $order['id'];
        }
        $orders = $query->find();
        $goodslist = array();
        if ($ids) {
            $list = $this->model->table("order_goods AS og")
                    ->fields("og.product_id,og.spec,og.order_id,og.goods_id,og.goods_nums,go.img,go.imgs,go.name")
                    ->join("goods AS go ON og.goods_id=go.id")
                    ->where("order_id IN (" . implode(',', $ids) . ")")
                    ->findAll();
            foreach ($list as $k => $v) {
                $v['speclist'] = implode(' / ', Common::spec($v['spec']));
                $goodslist[$v['order_id']][] = $v;
            }
        }
        foreach ($orders as $k => &$v) {
            $v['goodslist'] = isset($goodslist[$v['id']]) ? $goodslist[$v['id']] : array();
        }
        unset($v);
        //处理过期订单状态
        if (count($order_id) > 0) {
            $ids = implode(',', $order_id);
            $order_model = new Model('order');
            $data = array("status" => 6);
            $order_model->where("id in (" . $ids . ")")->data($data)->update();
            $point_order = $order_model->where("id in (" . $ids . ") and type in (5,6)")->findAll();
            if($point_order){
                foreach ($point_order as $v){
                    if($v['pay_point']>0){
                        $this->model->table("customer")->where("user_id=" . $v['user_id'])->data(array("point_coin" => "`point_coin`+" . $v['pay_point']))->update();
                        Log::pointcoin_log($v['pay_point'], $v['user_id'], $v['order_no'], "取消订单，退回积分", 2);
                    }
                }
            }
        }
        $this->assign("status", $status);
        $this->assign("where", $where);
        $this->assign("orderlist", $orders);
        $this->assign("pagelist", $query->pageBar(2));
        $this->assign("seo_title", "订单管理");
        $this->redirect();
    }

    protected function order_status($item) {
        $status = $item['status'];
        $pay_status = $item['pay_status'];
        $delivery_status = $item['delivery_status'];
        $order_type = $item['type'];
        $str = '';
        $btn = '';
        //status:1等待付款 2待审核(待付款) 3已付款 4已完成 5已取消 6已作废
        switch ($status) {
            case '1':
                if ($order_type == 4) {
                    if ($huabipay_status == '1' && $otherpay_status == '0') {
                        $str = '<span class="text-danger">已收到华点付款，等待支付剩余货款</span>';
                        $btn = '<a href="' . Url::urlFormat("/simple/order_status/order_id/$item[id]") . '" class="btn btn-main btn-mini">支付剩余货款</a>';
                    } else if ($huabipay_status == '0' && $otherpay_status == '1') {
                        $str = '<span class="text-danger">已收到在线付款，等待后台人工确认华点到账</span>';
                    } else if ($huabipay_status == '0' && $otherpay_status == '0') {
                        $str = '<span class="text-danger">未付款</span>';
                        $btn = '<a href="' . Url::urlFormat("/simple/order_status/order_id/$item[id]") . '" class="btn btn-main btn-mini">立即付款</a>';
                    }
                } else {
                    $str = '<span class="text-danger">等待付款</span>';
                    $btn = '<a href="' . Url::urlFormat("/simple/order_status/order_id/$item[id]") . '" class="btn btn-main btn-mini">立即付款</a><a href="javascript:;" class="btn btn-gray btn-mini " onclick="order_delete(' . $item['id'] . ')">删除订单</a>';
                }
                break;
            case '2':
                if ($pay_status == 1)
                    $str = '<span class="text-warning">等待审核</span>';
                else {
                    //关闭货到付款的检测
                    //$payment_info = Common::getPaymentInfo($item['payment']);
                    if (FALSE && $payment_info['class_name'] == 'received') {
                        $str = '<span class="text-warning">等待审核</span>';
                        $btn = '<a href="' . Url::urlFormat("/simple/order_status/order_id/$item[id]") . '" class="btn btn-main btn-mini">另选支付</a>';
                    } else {
                        if ($order_type == 4) {
                            if ($is_new == 0) {
                                if ($huabipay_status == '1' && $otherpay_status == '0') {
                                    $str = '<span class="text-danger">请支付剩余货款</span>';
                                    $btn = '<a href="' . Url::urlFormat("/simple/order_status/order_id/$item[id]") . '" class="btn btn-main btn-mini">支付剩余货款</a>';
                                } else if ($huabipay_status == '0' && $otherpay_status == '1') {
                                    $str = '<span class="text-danger">等待确认华点</span>';
                                } else if ($huabipay_status == '0' && $otherpay_status == '0') {
                                    $str = '<span class="text-danger">未付款</span>';
                                    $btn = '<a href="javascript:;" class="btn  btn-gray btn-mini " onclick="order_delete(' . $item['id'] . ')">删除订单</a>&nbsp;<a href="' . Url::urlFormat("/simple/order_status/order_id/$item[id]") . '" class="btn btn-main btn-mini">立即付款</a>';
                                }
                            } else {
                                $str = '<span class="text-danger">等待付款</span>';
                                $btn = '<a href="javascript:;" class="btn  btn-gray btn-mini " onclick="order_delete(' . $item['id'] . ')">删除订单</a>&nbsp;<a href="' . Url::urlFormat("/simple/order_status/order_id/$item[id]") . '" class="btn btn-main btn-mini">立即付款</a>';
                            }
                        } else {
                            $str = '<span class="text-danger">等待付款</span>';
                            $btn = '<a href="javascript:;" class="btn  btn-gray btn-mini " onclick="order_delete(' . $item['id'] . ')">删除订单</a>&nbsp;<a href="' . Url::urlFormat("/simple/order_status/order_id/$item[id]") . '" class="btn btn-main btn-mini">立即付款</a>';
                        }
                    }
                }
                break;
            case '3':
                if ($delivery_status == 0) {
                    $str = '<span class="text-info">等待发货</span>';
                } else if ($delivery_status == 1) {
                    $str = '<span class="text-info">已发货</span>';
                    $btn = '<a href="javascript:;" class="btn btn-main btn-mini" onclick="order_sign(' . $item['id'] . ')">确认收货</a>';
                }
                if ($pay_status == 3)
                    $str = '<span class="text-success">已退款</span>';
                break;
            case '4':
                $str = '<span class="text-success"><b>已完成</b></span>';
                if ($pay_status == 3)
                    $str = '<span class="text-success">已退款</span>';
                break;
            case '5':
                $str = '<span class="text-gray"><s>已取消</s></span>';
                if ($pay_status == 3)
                    $str = '<span class="text-success">已退款</span>';
                $btn = '<a href="javascript:;" class="btn btn-gray btn-mini" onclick="order_delete(' . $item['id'] . ')">删除订单</a>';
                break;
            case '6':
                $str = '<span class="text-gray"><s>已作废</s></span>';
                $btn = '<a href="javascript:;" class="btn btn-gray btn-mini " onclick="order_delete(' . $item['id'] . ')">删除订单</a>';
                break;
            default:
                # code...
                break;
        }
        return array($str, $btn);
    }

    public function order_detail() {
        $id = Filter::int(Req::args("id"));
        $order = $this->model->table("order as od")->fields("od.*,pa.pay_name")->join("left join payment as pa on od.payment = pa.id")->where("od.id = $id and od.user_id=" . $this->user['id'])->find();
        if ($order) {
            $invoice = $this->model->table("doc_invoice as di")->fields("di.*,ec.code as ec_code,ec.name as ec_name,ec.alias as ec_alias")->join("left join express_company as ec on di.express_company_id = ec.id")->where("di.order_id=" . $id)->findAll();
            $order_goods = $this->model->table("order_goods as og ")->fields("og.*,og.id as order_goods_id,go.*,pr.*")->join("left join goods as go on og.goods_id = go.id left join products as pr on og.product_id = pr.id")->where("og.order_id=" . $id)->findAll();
            $area_ids = $order['province'] . ',' . $order['city'] . ',' . $order['county'];
            if ($area_ids != '')
                $areas = $this->model->table("area")->where("id in ($area_ids)")->findAll();
            $parse_area = array();
            foreach ($areas as $area) {
                $parse_area[$area['id']] = $area['name'];
            }
            $shopgoods = array();
            $express_ids = array();
            foreach ($order_goods as $k => $v) {
                $express_ids[] = $v['express_company_id'];
                $v['speclist'] = implode(' / ', Common::spec($v['spec']));
                $shopgoods[$v['shop_id']] = isset($shopgoods[$v['shop_id']]) ? $shopgoods[$v['shop_id']] : array();
                $shopgoods[$v['shop_id']][] = $v;
            }
            //查询物流公司名称
            $expresslist = array();
            if ($express_ids) {
                $tmplist = $this->model->table("express_company")->where("id IN (" . implode(',', $express_ids) . ")")->findAll();
                foreach ($tmplist as $k => $v) {
                    $expresslist[$v['id']] = $v;
                }
            }
            $this->assign("expresslist", $expresslist);
            $this->assign("shopgoods", $shopgoods);
            $this->assign("parse_area", $parse_area);
            $this->assign("order_goods", $order_goods);
            $this->assign("invoice", $invoice);
            $this->assign("order", $order);
            $this->redirect();
        } else {
            Tiny::Msg($this, 404);
        }
    }

    //订单签收
    public function order_sign() {
        $id = Filter::int(Req::args("id"));
        $info = array('status' => 'fail');
        $result = $this->model->table('order')->where("id=$id and user_id=" . $this->user['id'] . " and status=3 and pay_status=1 and delivery_status=1")->data(array('delivery_status' => 2, 'status' => 4, 'completion_time' => date('Y-m-d H:i:s')))->update();
        if ($result) {
            $info = array('status' => 'success');
            //提取购买商品信息
            $products = $this->model->table('order as od')->join('left join order_goods as og on od.id=og.order_id')->where('od.id=' . $id)->findAll();
            foreach ($products as $product) {
                $data = array('goods_id' => $product['goods_id'], 'user_id' => $this->user['id'], 'order_id' => $product['order_id'], 'order_no' => $product['order_no'], 'buy_time' => $product['create_time']);
                $this->model->table('review')->data($data)->insert();
            }
        }
        echo JSON::encode($info);
    }

    //地址列表
    public function address() {
        $model = new Model("address");
        $address = $model->where("user_id=" . $this->user['id'])->order("id desc")->findAll();
        $area_ids = array();
        foreach ($address as $addr) {
            $area_ids[$addr['province']] = $addr['province'];
            $area_ids[$addr['city']] = $addr['city'];
            $area_ids[$addr['county']] = $addr['county'];
        }
        $area_ids = implode(',', $area_ids);
        $areas = array();
        if ($area_ids != '')
            $areas = $model->table("area")->where("id in ($area_ids)")->findAll();
        $parse_area = array();
        foreach ($areas as $area) {
            $parse_area[$area['id']] = $area['name'];
        }
        $this->assign("address", $address);
        $this->assign("parse_area", $parse_area);
        $this->assign("seo_title", "地址管理");
        $this->redirect();
    }

    //编辑地址
    public function address_other() {
        Session::set("order_status", Req::args());
        $id = Filter::int(Req::args("id"));
        $url = Req::args("url");
        $this->assign("url", $url);
        $this->assign("seo_title", $id ? "修改地址" : '添加地址');
        if ($id) {
            $model = new Model("address");
            $data = $model->where("id = $id and user_id =" . $this->user['id'])->find();
            $areas = $this->model->table("area")->where("id in({$data['province']},{$data['city']},{$data['county']})")->findAll();
            $parse_area = array();
            foreach ($areas as $area) {
                $parse_area[$area['id']] = $area['name'];
            }
            $this->assign("address", implode(' ', $parse_area));

            $this->redirect("address_other", false, $data);
        } else
            $this->redirect();
    }

    //保存地址
    public function address_save() {
        $rules = array('zip:zip:邮政编码格式不正确!', 'addr:required:内容不能为空！', 'accept_name:required:收货人姓名不能为空!,mobile:mobi:手机格式不正确!,phone:phone:电话格式不正确', 'province:[1-9]\d*:选择地区必需完成', 'city:[1-9]\d*:选择地区必需完成', 'county:[1-9]\d*:选择地区必需完成');
        $info = Validator::check($rules);

        if (!is_array($info) && $info == true) {
            Filter::form(array('sql' => 'accept_name|mobile|phone', 'txt' => 'addr', 'int' => 'province|city|county|zip|is_default|id'));
            $is_default = Filter::int(Req::args("is_default"));
            if ($is_default == 1) {
                $this->model->table("address")->where("user_id=" . $this->user['id'])->data(array('is_default' => 0))->update();
            } else {
                Req::args("is_default", "0");
            }

            Req::args("user_id", $this->user['id']);
            $id = Filter::int(Req::args('id'));
            if ($id) {
                $this->model->table("address")->where("id=$id and user_id=" . $this->user['id'])->update();
            } else {
                $obj = $this->model->table("address")->where('user_id=' . $this->user['id'])->fields("count(*) as total")->find();
                if ($obj && $obj['total'] >= 20) {
                    $this->assign("msg", array("error", '地址最大允许添加20个'));
                    $this->redirect("address_other", false, Req::args());
                    exit();
                } else {
                    $address_id = $this->model->table("address")->insert();
                    $order_status = Session::get("order_status");
                    $order_status['address_id'] = $address_id;
                    Session::set("order_status", $order_status);
                }
            }

            $this->assign("msg", array("success", "地址编辑成功!"));
            //$this->redirect("address_other",false);
            $url = Req::args("url");
            $url = $url ? $url : "address";
            $this->redirect($url);
        } else {
            $this->assign("msg", array("error", $info['msg']));
            $this->redirect("address_other", false, Req::args());
        }
    }

    public function address_del() {
        $id = Filter::int(Req::args("id"));
        $this->model->table("address")->where("id=$id and user_id=" . $this->user['id'])->delete();
        $url = Req::args("url");
        $url = $url ? $url : "address";
        $this->redirect($url);
        $this->redirect("address");
    }

    public function address_wechat() {
        $code = -1;
        $content = NULL;
        $one = $this->model->table("address")->where("user_id=" . $this->user['id'])->find();

        if (!$one) {
            $username = Filter::sql($_POST['userName']);
            $mobile = Filter::sql($_POST['telNumber']);
            $zip = Filter::sql($_POST['addressPostalCode']);
            $addr = Filter::sql($_POST['addressDetailInfo']);
            $nationalcode = Filter::sql($_POST['nationalCode']);
            $countyone = $cityone = $provinceone = null;
            $province = Filter::sql($_POST['proviceFirstStageName']);
            $city = Filter::sql($_POST['addressCitySecondStageName']);
            $county = Filter::sql($_POST['addressCountiesThirdStageName']);
            $province = substr($province, 0, strpos($province, "市"));
            // $countyone = $this->model->table("area")->where("id='{$nationalcode}'")->find();
            // if ($countyone) {
            //     $cityone = $this->model->table("area")->where("id='{$countyone['parent_id']}'")->find();
            //     if ($cityone) {
            //         $provinceone = $this->model->table("area")->where("id='{$cityone['parent_id']}'")->find();
            //     }
            // }

            $area_info = $this->model->table("area as a1")
                            ->join("left join area as a2 on a1.id = a2.parent_id left join area as a3 on a2.id=a3.parent_id")
                            ->where("a1.name like '{$province}%' and a1.parent_id = 0 and a2.name = '{$city}' and a3.name = '{$county}'")
                            ->fields("a1.id as province,a2.id as city,a3.id as county")->find();

            if ($area_info) {
                $data = array(
                    'user_id' => $this->user['id'],
                    'accept_name' => $username,
                    'mobile' => $mobile,
                    'phone' => '',
                    'province' => $area_info['province'],
                    'city' => $area_info['city'],
                    'county' => $area_info['county'],
                    'zip' => $zip,
                    'addr' => $addr,
                    'is_default' => 1
                );
                $address_id = $this->model->table("address")->data($data)->insert();
                $code = 0;
                $content = $data;
                $content['id'] = $address_id;
            }
        }
        echo json_encode(array('code' => $code, 'conent' => $content));
        exit;
    }

    public function index() {
        $id = $this->user['id'];
        $customer = $this->model->table("customer as cu")->fields("cu.*,gr.name as gname")->join("left join grade as gr on cu.group_id = gr.id")->where("cu.user_id = $id")->find();
        $orders = $this->model->table("order")->where("user_id = $id and is_del = 0")->findAll();
        $order = array('amount' => 0, 'todayamount' => 0, 'pending' => 0, 'undelivery' => 0, 'unreceived' => 0, 'uncomment' => 0);
        foreach ($orders as $obj) {
            if ($obj['status'] < 5 && ($obj['payment'] == 1 || $obj['payment'] == 12 || $obj['payment'] == 13 || $obj['payment'] == 15 )) {
                if ($obj['type'] == 4) {
                    $obj['order_amount'] = $obj['otherpay_amount'];
                }
                $order['amount'] += $obj['order_amount'];
                if (strtotime($obj['pay_time']) >= strtotime('today')) {
                    $order['todayamount'] += $obj['order_amount'];
                }
            }
            if ($obj['status'] == 4) {
                
            } else if ($obj['status'] < 3) {
                $order['pending'] ++;
            } else if ($obj['status'] == 3) {
                if ($obj['delivery_status'] == 0) {
                    $order['undelivery'] ++;
                } else if ($obj['delivery_status'] == 1) {
                    $order['unreceived'] ++;
                }
            }
        }
        $comment = $this->model->table("review")->fields("count(*) as num")->where("user_id = $id and status=0")->find();
        $this->assign("comment", $comment);
        $where = "user_id = " . $this->user['id'] . " and is_send = 1";
        $where .= " and status = 0 and '" . date("Y-m-d H:i:s") . "' <=end_time";
        $voucherlist = $this->model->table("voucher")->where($where)->order("id desc")->limit("0,2")->findAll();
        //upyun设置
        $upyun = Config::getInstance()->get("upyun");

        $options = array(
            'bucket' => $upyun['upyun_bucket'],
            'save-key' => "/data/uploads/head/" . $this->user['id'] . "{.suffix}",
            'allow-file-type' => 'jpg,gif,png', // 文件类型限制，如：jpg,gif,png
            'expiration' => time() + $upyun['upyun_expiration'],
            'notify-url' => $upyun['upyun_notify-url'],
            'ext-param' => "avatar:{$id}",
        );
        $policy = base64_encode(json_encode($options));
        $signature = md5($policy . '&' . $upyun['upyun_formkey']);

        $options['policy'] = $policy;
        $options['signature'] = $signature;
        $options['action'] = $upyun['upyun_uploadurl'];
        $options['img_host'] = $upyun['upyun_cdnurl'];

        $change_info = $this->model->table("customer as c")->join("left join oauth_user as o on c.user_id = o.user_id")->fields("c.user_id,c.mobile,o.other_user_id")->where("o.oauth_type='wechat' and c.user_id=" .$this->user['id'])->find();
        if(empty($change_info)){
            
        }else if ($change_info['mobile'] && $change_info['other_user_id']) {
            $this->assign("open_change", true);
        } else if (!$change_info['mobile'] && $change_info['other_user_id']) {
            $this->assign("open_change", true);
        } else if (!$change_info['mobile'] && !$change_info['other_user_id']) {
            $this->assign('open_bind', true);
        }
        $is_promoter = false;
        $promoter = Promoter::getPromoterInstance($this->user['id']);
        if (is_object($promoter)) {
            $is_promoter = true;
        }
        $is_hirer = false;
        $hirer = $this->model->table("district_shop")->where("owner_id=" . $this->user['id'])->count();
        if ($hirer > 0) {
            $is_hirer = true;
        }
        
        //签到
        $sign_in_set = Config::getInstance()->get('sign_in_set');
        $this->assign("sign_in_open",$sign_in_set['open']);
        
        $this->assign('is_hirer', $is_hirer);
        $this->assign('is_promoter', $is_promoter);
        $this->assign("option", $options);
        $this->assign("voucherlist", $voucherlist);
        $this->assign("order", $order);
        $this->assign("customer", $customer);
        //$this->assign('id', $index);
        $this->redirect();
    }

    public function refreshinfo() {
        $id = $this->user['id'];
        $obj = $this->model->table("user as us")->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.group_id,cu.login_time,cu.mobile")->where("us.id=$id")->find();
        $this->safebox->set('user', $obj, $this->cookie_time);

        echo json_encode(array('status' => 'success'));
        exit;
    }

    //移动端的钱袋页
    public function asset() {
        $id = $this->user['id'];
        $customer = $this->model->table("customer as cu")->fields("cu.*,gr.name as gname")->join("left join grade as gr on cu.group_id = gr.id")->where("cu.user_id = $id")->find();
        
        //只记录余额支付的消费统计
        $orders = $this->model->table("order as o")->join("payment as p on o.payment = p.id ")->where("o.user_id = $id and p.plugin_id in(1,20)")->findAll();
        $order = array('amount' => 0, 'todayamount' => 0, 'pending' => 0, 'undelivery' => 0, 'unreceived' => 0, 'uncomment' => 0);
        foreach ($orders as $obj) {
            if ($obj['status'] < 5 && $obj['pay_status'] == 1) {
                $order['amount'] += $obj['order_amount'];
                if (strtotime($obj['pay_time']) >= strtotime('today')) {
                    $order['todayamount'] += $obj['order_amount'];
                }
            }
        }
        
        //充值礼品判断
        $info = $this->model->table("recharge_presentlog")->where("user_id =" . $this->user['id'] . " and status=0")->find();
        if ($info) {
            $activity = $this->model->table("recharge_activity")->where("id = 1")->fields("accept_end_time")->find();
            if (strtotime($activity['accept_end_time']) > time() && $info['status'] == 0) {
                $this->assign("show_message", true);
                $this->assign("present", $info['present']);
            } else if (strtotime($activity['accept_end_time']) < time() && $info['status'] == 0) {
                $this->model->query("update tiny_recharge_presentlog set status='-1' where user_id =" . $this->user['id']);
            }
        }
        
        $this->assign("order", $order);
        $this->assign("customer", $customer);
        $this->assign("seo_title", "钱袋");
        $this->redirect();
    }
    
    //充值中心
    public function recharge_center() {
        $package = Filter::int(Req::args('package'));
        $pid = Filter::int(Req::args('pid'));
        if ($package && $pid) {
            $this->assign("package", $package);
            $this->assign("pid", $pid);
        }
        //地址
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
        $this->assign("parse_area", $parse_area);
        $this->assign('address', $address);
        //支付列表
        $paytypelist = Common::getValidPayList();
        $paytypeone = reset($paytypelist);
        $this->assign("paytypeone", $paytypeone);
        $this->assign("paytypelist", $paytypelist);
        
        $config = Config::getInstance();
        $package_set = $config->get("recharge_package_set");
        if (is_array($package_set)) {
            if(isset($package_set[4]['gift'])&&$package_set[4]['gift']!=''){
                $where = implode(',', array_reverse(explode("|", $package_set[4]['gift'])));
                $select4 = $this->model->table("products as p")->join("goods as g on p.goods_id=g.id")->where("p.id in ({$where})")->fields("p.id,g.img,g.name")->order("field(p.id,$where)")->findAll();
                $this->assign("select4", $select4);
            }
        }
        $this->assign('package_set',$package_set);
        $this->assign("seo_title", '充值中心');
        $this->redirect();
    }
    
    //余额记录
    public function balance_log() {
        $customer = $this->model->table("customer")->where("user_id=" . $this->user['id'])->find();
        $this->assign("customer", $customer);
        $this->assign('seo_title', '余额记录');
        $this->redirect();
    }

    public function check_identity() {
        $verified = $this->verifiedType();
        $this->redirect();
    }

    public function verified() {
        $code = Req::args('code');
        $type = Req::args('type');
        $obj = Req::args('obj');
        $obj = $this->updateObj($obj); //默认是修改登陆密码
        $verifiedInfo = Session::get("verifiedInfo");
        if ($code == $verifiedInfo['code']) {
            $verifiedInfo['obj'] = $obj;
            Session::set("verifiedInfo", $verifiedInfo);
            $this->redirect("/ucenter/update_obj/r/" . $verifiedInfo['random']);
        } else {
            $customer = $this->model->table('customer')->where("user_id=" . $this->user['id'])->find();
            if ($customer['pay_password'] == CHash::md5($code, $customer['pay_validcode'])) {
                $random = CHash::random(20, 'char');
                $verifiedInfo = array('code' => $code, 'time' => time(), 'type' => 'paypwd', 'obj' => $obj, 'random' => $random);
                Session::set("verifiedInfo", $verifiedInfo);
                $this->redirect("/ucenter/update_obj/r/" . $verifiedInfo['random']);
            } else {
                $info = array('field' => 'code', 'msg' => '验证码错误！');
                if ($type == 'paypwd') {
                    $info = array('field' => 'code', 'msg' => '支付密码错误！');
                }
                $this->assign("invalid", $info);
                $this->redirect("/ucenter/check_identity/obj/" . $obj . "/type/" . $type, false);
            }
        }
    }

    public function update_obj() {
        $r = Req::args('r');
        $verifiedInfo = Session::get("verifiedInfo");

        if ($r == $verifiedInfo['random'] && $r != null) {
            $this->assign("obj", $verifiedInfo['obj']);
            $this->redirect();
        } else {
            $this->redirect("/ucenter/check_identity");
        }
    }

    public function activate_obj() {
        $obj = Req::args('obj');
        $obj = $this->updateObj($obj);
        $model = new Model('user as us');
        $userInfo = $model->join('left join customer as cu on us.id = cu.user_id')->where('cu.user_id = ' . $this->user['id'])->find();
        $random = CHash::random(20, 'char');
        $verifiedInfo = array('obj' => $obj, 'random' => $random);
        if ($obj == 'email' && $userInfo['email_verified'] == 0) {
            Session::set('verifiedInfo', $verifiedInfo);
            $this->redirect("/ucenter/update_obj/r/" . $verifiedInfo['random']);
        } elseif ($obj == 'mobile' && $userInfo['mobile_verified'] == 0) {
            Session::set('verifiedInfo', $verifiedInfo);
            $this->redirect("/ucenter/update_obj/r/" . $verifiedInfo['random']);
        } elseif ($obj == 'paypwd' && $userInfo['pay_password_open'] == 0) {
            Session::set('verifiedInfo', $verifiedInfo);
            $this->redirect("/ucenter/update_obj/r/" . $verifiedInfo['random']);
        } else {
            $this->redirect('/ucenter/safety');
        }
    }

    public function update_obj_act() {
        $verifiedInfo = Session::get("verifiedInfo");
        $obj = $verifiedInfo['obj'];
        $info = array();
        if ($obj == 'password' || $obj == 'paypwd') {
            $password = Req::args('password');
            $repassword = Req::args('repassword');
            if ($password == $repassword) {
                if ($obj == 'password') {
                    $validcode = CHash::random(8);
                    $this->model->table('user')->data(array('password' => CHash::md5($password, $validcode), 'validcode' => $validcode))->where('id=' . $this->user['id'])->update();
                    Session::clear('verifiedInfo');
                    $this->redirect('/ucenter/update_obj_success/obj/' . $obj);
                    exit;
                } elseif ($obj == 'paypwd') {
                    $validcode = CHash::random(8);
                    $this->model->table('customer')->data(array('pay_password' => CHash::md5($password, $validcode), 'pay_validcode' => $validcode, 'pay_password_open' => 1))->where('user_id=' . $this->user['id'])->update();
                    Session::clear('verifiedInfo');
                    $this->redirect('/ucenter/update_obj_success/obj/' . $obj);
                    exit;
                }
            } else {
                $info = array('field' => 'repassword', 'msg' => '两次密码不一致。');
            }
        } else if ($obj == 'mobile' || $obj == 'email') {
            $code = Req::args('code');
            $account = Req::args('account');
            $activateObj = Session::get('activateObj');
            $newCode = $activateObj['code'];
            $newAccount = $activateObj['obj'];
            if ($code == $newCode && $account == $newAccount) {
                if ($obj == 'email' && Validator::email($account)) {
                    $result = $this->model->table('user')->where("email='" . $account . "' and id != " . $this->user['id'])->find();
                    if (!$result) {
                        $this->model->table('user')->data(array('email' => $account))->where('id=' . $this->user['id'])->update();
                        $this->model->table('customer')->data(array('email_verified' => 1))->where('user_id=' . $this->user['id'])->update();
                        Session::clear('verifiedInfo');
                        Session::clear('activateObj');
                        $this->redirect('/ucenter/update_obj_success/obj/' . $obj);
                        exit;
                    } else {
                        $info = array('field' => 'account', 'msg' => '此邮箱已被其它用户占用，无法修改为此邮箱。');
                    }
                } elseif ($obj == 'mobile' && Validator::mobi($account)) {
                    $result = $this->model->table('customer')->where("mobile ='" . $account . "'" . '  and user_id!=' . $this->user['id'])->find();
                    if (!$result) {
                        $this->model->table('customer')->data(array('mobile' => $account, 'mobile_verified' => 1))->where('user_id=' . $this->user['id'])->update();
                        Session::clear('verifiedInfo');
                        Session::clear('activateObj');
                        $this->redirect('/ucenter/update_obj_success/obj/' . $obj);
                        exit;
                    } else {
                        $info = array('field' => 'account', 'msg' => '此手机号已被其它用户占用，无法修改为此手机号。');
                    }
                }
            } else {
                $info = array('field' => 'account', 'msg' => '账号或验证码不正确。');
            }
        }
        $this->redirect("/ucenter/update_obj/r/" . $verifiedInfo['random'], true, array('invalid' => $info, 'account' => $account));
    }

    public function update_obj_success() {
        $obj = Req::args('obj');
        if ($obj != null) {
            $this->redirect();
        } else {
            $this->redirect('/ucenter/safety');
        }
    }

    public function send_objcode() {
        $account = Req::args('account');
        $activateObj = Session::get('activateObj');
        $sendAble = true;
        $haveTime = 120;
        if (isset($activateObj['time'])) {
            $time = $activateObj['time'];
            $haveTime = time() - $time;
            if ($haveTime <= 120) {
                $sendAble = false;
            }
        }

        if ($sendAble) {
            $code = CHash::random(6, 'int');
            if (Validator::email($account)) {
                $mail = new Mail();
                $flag = $mail->send_email($account, '您的邮箱身份核实验证码', "核实邮箱验证码：" . $code);
                if (!$flag) {
                    $info = array('status' => 'fail', 'msg' => '邮件发送失败请联系管理人员');
                } else {
                    $activateObj = array('time' => time(), 'code' => $code, 'obj' => $account);
                    Session::set('activateObj', $activateObj);
                    $info = array('status' => 'success');
                }
            } else if (Validator::mobi($account)) {
                $sms = SMS::getInstance();
                if ($sms->getStatus()) {
                    $result = $sms->sendCode($account, $code);
                    if ($result['status'] == 'success') {
                        $info = array('status' => 'success', 'msg' => $result['message']);
                        $activateObj = array('time' => time(), 'code' => $code, 'obj' => $account);
                        Session::set('activateObj', $activateObj);
                        $info = array('status' => 'success');
                    } else {
                        $info = array('status' => 'fail', 'msg' => $result['message']);
                    }
                } else {
                    $info = array('status' => 'fail', 'msg' => '系统没有开启手机验证功能!');
                }
            } else {
                $info = array('status' => 'fail', 'msg' => '除邮箱及手机号外，不支持发送!');
            }
        } else {
            $info = array('status' => 'fail', 'msg' => '还有' . (120 - $haveTime) . '秒后可发送！');
        }
        $info['haveTime'] = (120 - $haveTime);
        echo JSON::encode($info);
    }

    public function send_code() {
        $info = array('status' => 'fail', 'msg' => '');
        $type = Req::args('type');
        $code = CHash::random(6, 'int');
        $obj = Req::args('obj');
        $verifiedInfo = Session::get('verifiedInfo');
        $sendAble = true;
        $haveTime = 120;

        if (isset($verifiedInfo['time']) && $type == $verifiedInfo['type']) {
            $time = $verifiedInfo['time'];
            $haveTime = time() - $time;
            if ($haveTime <= 120) {
                $sendAble = false;
            }
        }

        if ($sendAble) {

            $obj = $this->updateObj($obj);
            $random = CHash::random(20, 'char');
            $verifiedInfo = array('code' => $code, 'time' => time(), 'type' => $type, 'obj' => $obj, 'random' => $random);
            if ($type == 'email') {
                $mail = new Mail();
                $flag = $mail->send_email($this->user['email'], '您的验证身份验证码', "身份验证码：" . $code);
                if (!$flag) {
                    $info = array('status' => 'fail', 'msg' => '邮件发送失败请联系管理人员');
                } else {
                    Session::set('verifiedInfo', $verifiedInfo);
                    $info = array('status' => 'success');
                }
            } else if ($type == 'mobile') {
                $sms = SMS::getInstance();
                if ($sms->getStatus()) {
                    $result = $sms->sendCode($this->user['mobile'], $code);
                    if ($result['status'] == 'success') {
                        $info = array('status' => 'success', 'msg' => $result['message']);
                        Session::set('verifiedInfo', $verifiedInfo);
                        $info = array('status' => 'success');
                    } else {
                        $info = array('status' => 'fail', 'msg' => $result['message']);
                    }
                } else {
                    $info = array('status' => 'fail', 'msg' => '系统没有开启手机验证功能!');
                }
            }
        } else {
            $info = array('status' => 'fail', 'msg' => '还有' . (120 - $haveTime) . '秒后可发送！');
        }
        $info['haveTime'] = (120 - $haveTime);

        echo JSON::encode($info);
    }

    public function safety() {
        $verified = $this->verifiedType();
        $this->redirect();
    }

    private function verifiedType() {
        $verified_type = array(
            'mobile' => "已验证手机",
            'email' => "已验证邮箱",
            'paypwd' => "支付密码"
        );

        $model = new Model('user as us');
        $userInfo = $model->join('left join customer as cu on us.id = cu.user_id')->where('cu.user_id = ' . $this->user['id'])->find();
        if ($userInfo) {
            //用户如果没有绑定手机或者邮箱时
            if ($userInfo['email_verified'] != 1)
                unset($verified_type['email']);
            if ($userInfo['mobile_verified'] != 1)
                unset($verified_type['mobile']);
            if ($userInfo['pay_password_open'] != 1)
                unset($verified_type['paypwd']);
            //隐藏敏感信息
            $userInfo['email'] = preg_replace("/^(\w{1}).*(\w{1}@.+)$/i", "$1*****$2", $userInfo['email']);
            $userInfo['mobile'] = preg_replace("/^(\d{3})\d+(\d{3})$/i", "$1*****$2", $userInfo['mobile']);
        }
        $type = Req::args('type');
        $obj = Req::args('obj');
        $obj = $this->updateObj($obj);

        $type = $type == null ? 'mobile' : $type;
        //跟前端显示有关
        if (isset($verified_type[$type])) {
            unset($verified_type[$type]);
        } else {
            if (count($verified_type) > 0) {
                $keys = array_keys($verified_type);
                $type = current($keys);
                unset($verified_type[$type]);
            } else {
                $type = null;
            }
        }
        $this->assign("userInfo", $userInfo);
        $this->assign("obj", $obj);
        $this->assign("verified", $verified_type);
        $this->assign("type", $type);
    }

    private function updateObj($obj) {
        $objs = array('email' => true, 'mobile' => true, 'password' => true, 'paypwd' => true);
        if (!isset($objs[$obj])) {
            $obj = 'password';
        }
        return $obj;
    }

    //检测用户是否在线
    private function checkOnline() {
        if (isset($this->user) && $this->user['name'] != null)
            return true;
        else
            return false;
    }

    public function commission() {
        $uid = $this->user['id'];
        $commission = $this->model->table("commission")->where('user_id=' . $uid)->find();
        if (empty($commission)) {
            $commission['commission_available'] = "0.00";
            $commission['commission_possess_now'] = "0.00";
            $commission['commission_withdrew'] = "0.00";
        } else {
            //更新可用状态
            $commission_set = Config::getInstance()->get("commission_set");
            $lockdays = $commission_set['commission_locktime'];
            $lockdays = is_int($lockdays) ? $lockdays : (int) $lockdays;
            $available_time = date('Y-m-d H:i:s', strtotime("-$lockdays days"));
            $result = $this->model->table('commission_log')->where("user_id = $uid and status = 0 and time < '$available_time'")->data(array('status' => 1))->update();
            if ($result > 0) {
                $available_commission = $this->model->query("select SUM(commission_get) as count from tiny_commission_log where user_id=$uid and status =1");
                $this->model->table('commission')->data(array('commission_available' => $available_commission[0]['count']))->where('user_id=' . $uid)->update();
                $commission = $this->model->table("commission")->where('user_id=' . $uid)->find();
            }
        }
        $this->assign("commission", $commission);
        $this->assign("seo_title", "我的佣金");
        $this->redirect();
    }

    public function commission_log() {
        $uid = $this->user['id'];
        $this->assign('uid', $uid);
        $this->assign("seo_title", "佣金记录");
        $this->redirect();
    }

    public function change_account() {
        $mobile = Filter::sql(Req::args('mobile'));
        $validatecode = Filter::sql(Req::args('validatecode'));
        if ($mobile != "" && $validatecode != "") {
            $ret = SMS::getInstance()->checkCode($mobile, $validatecode);
            SMS::getInstance()->flushCode($mobile);
            if ($ret['status'] == 'success') {
                //查询当前微信公众号绑定的user_id
                $account_info_all = $this->model->table("oauth_user")->where("user_id =" . $this->user['id'] . " and oauth_type ='wechat'")->fields("id,user_id,other_user_id")->findAll();
                if (empty($account_info_all) || count($account_info_all) > 1) {
                    $ret['status'] = "fail";
                    $ret['message'] = "切换失败,oauth信息错误";
                } else {
                    $account_info = $account_info_all[0];
                    if ($account_info['other_user_id'] == 0 || $account_info['other_user_id'] == "") {//如果另一个账号信息不存在
                        //查询手机号绑定的账号
                        $other_account = $this->model->table('customer')->where("mobile='" . $mobile . "'")->fields('user_id,mobile')->find();
                        if (empty($other_account) && $other_account['user_id'] == 0) {
                            $ret['status'] = "fail";
                            $ret['message'] = "切换失败,该手机号绑定的账号不存在";
                            echo json_encode($ret);
                            exit;
                        } else {//查询成功
                            if ($other_account['user_id'] == $account_info['user_id']) {//绑定的就是本账号
                                $ret['status'] = "fail";
                                $ret['message'] = "切换失败,不存在另一个账号";
                                echo json_encode($ret);
                                exit;
                            }
                            //判断该账号是否已经绑定过微信公众号登陆
                            $isOk1 = $this->model->table("oauth_user")->where("user_id =" . $other_account['user_id'] . " and oauth_type ='wechat'")->fields("id,user_id,other_user_id")->findAll();
                            $isOk2 = $this->model->table("oauth_user")->where("other_user_id =" . $other_account['user_id'] . " and oauth_type ='wechat'")->fields("id,user_id,other_user_id")->findAll();
                            if (!empty($isOk1) && !empty($isOk2)) {
                                $ret['status'] = "fail";
                                $ret['message'] = "绑定失败，该手机号对应账号已经绑定了其他微信账号";
                                echo json_encode($ret);
                                exit;
                            }
                            $result = $this->model->table("oauth_user")->data(array('user_id' => $other_account['user_id'], 'other_user_id' => $account_info['user_id']))->where("id =" . $account_info['id'])->update();
                            if ($result) {
                                $this->safebox->clear('user');
                                $cookie = new Cookie();
                                $cookie->setSafeCode(Tiny::app()->getSafeCode());
                                $cookie->set('autologin', null, 0);
                                $ret['status'] = "success";
                                $ret['message'] = "绑定并切换成功";
                                echo json_encode($ret);
                                exit;
                            } else {
                                $ret['status'] = "fail";
                                $ret['message'] = "切换失败,数据库错误1";
                                echo json_encode($ret);
                                exit;
                            }
                        }
                        $ret['status'] = "fail";
                        $ret['message'] = "切换失败";
                        echo json_encode($ret);
                        exit;
                    } else {//存在另一个user_id
                        $ids = $account_info['other_user_id'] . "," . $account_info['user_id'];
                        //验证手机号
                        $isOk = $this->model->table("customer")->where("user_id in ($ids) and mobile='$mobile'")->find();
                        if (!$isOk) {
                            $ret['status'] = "fail";
                            $ret['message'] = "切换失败,对应手机号码错误";
                            echo json_encode($ret);
                            exit;
                        }
                        //查询另一个账号是否真实存在没被禁用或删除
                        $other_account = $this->model->table('user')->where("id=" . $account_info['other_user_id'] . " and status = 1")->find();
                        if (empty($other_account)) {
                            $ret['status'] = "fail";
                            $ret['message'] = "切换失败,绑定的另一个账号信息为空";
                            echo json_encode($ret);
                            exit;
                        } else {
                            $result = $this->model->table("oauth_user")->data(array('user_id' => $other_account['id'], 'other_user_id' => $account_info['user_id']))->where("id =" . $account_info['id'])->update();
                            if ($result) {
                                $this->safebox->clear('user');
                                $cookie = new Cookie();
                                $cookie->setSafeCode(Tiny::app()->getSafeCode());
                                $cookie->set('autologin', null, 0);
                                $ret['status'] = "success";
                                $ret['message'] = "切换成功";
                                echo json_encode($ret);
                                exit;
                            } else {
                                $ret['status'] = "fail";
                                $ret['message'] = "切换失败,数据库错误2";
                                echo json_encode($ret);
                                exit;
                            }
                        }
                    }
                }
            } else {
                $ret['status'] = "fail";
                $ret['message'] = "验证码错误，请重新获取";
                echo json_encode($ret);
                exit;
            }
        } else {
            $account_info = $this->model->table("oauth_user")->where("user_id =" . $this->user['id'] . " and oauth_type ='wechat'")->fields("id,user_id,other_user_id")->find();
            if (isset($account_info['other_user_id'])) {
                $nameinfo = $this->model->table("user")->where("id=" . $account_info['other_user_id'])->fields("nickname,name,avatar")->find();
                if (!empty($nameinfo)) {
                    $this->assign("other_account", $nameinfo);
                }
            }
            $this->assign("seo_title", "切换账号");
            $this->redirect("change_account");
        }
    }

    private function _isCanApplyRefund($order_id) {
        $isset = $this->model->table("refund")->where("order_id =$order_id and user_id =" . $this->user['id'])->find();
        if ($isset) {
            return false;
        }
        $orderInfo = $this->model->table("order")->where("id = $order_id and user_id =" . $this->user['id'])->find();
        if (empty($orderInfo)) {
            return false;
        } else {
            if ($orderInfo['order_amount'] <= 0) {
                return false;
            }
            if ($orderInfo['type'] == 4) {//华币订单
                if ($orderInfo['is_new'] == 0) {
                    if ($orderInfo['otherpay_status'] == 1 || $orderInfo['pay_status'] == 1) {
                        if ($orderInfo['otherpay_amount'] > 0) {
                            return array("otherpay_status" => $orderInfo['otherpay_status'], "pay_status" => $orderInfo['pay_status'], "order_type" => 4, "order_id" => $order_id, "order_no" => $orderInfo['order_no'], "payment" => $orderInfo['payment'], "refund_amount" => $orderInfo['otherpay_amount']);
                        } else {
                            return false;
                        }
                    } else {
                        return false;
                    }
                } else if ($orderInfo['is_new'] == 1) {
                    if ($orderInfo['pay_status'] == 1) {
                        if ($orderInfo['is_return'] == 1) {
                            $refund_amount = $orderInfo['otherpay_amount'];
                            if ($refund_amount > 0) {
                                return array("order_type" => $orderInfo['type'], "order_id" => $order_id, "order_no" => $orderInfo['order_no'], "payment" => $orderInfo['payment'], "refund_amount" => $refund_amount);
                            } else {
                                return false;
                            }
                        } else {
                            $refund_amount = $orderInfo['order_amount'];
                            if ($refund_amount > 0) {
                                return array("order_type" => $orderInfo['type'], "order_id" => $order_id, "order_no" => $orderInfo['order_no'], "payment" => $orderInfo['payment'], "refund_amount" => $refund_amount);
                            } else {
                                return false;
                            }
                        }
                    } else {
                        return false;
                    }
                }
            } else {
                if ($orderInfo['pay_status'] == 1) {
                    return array("order_type" => $orderInfo['type'], "order_id" => $order_id, "order_no" => $orderInfo['order_no'], "payment" => $orderInfo['payment'], "refund_amount" => $orderInfo['order_amount']);
                } else {
                    return false;
                }
            }
        }
    }

    public function refund_apply() {
        $order_id = Filter::int(Req::args("order_id"));
        $info = $this->_isCanApplyRefund($order_id);
        if ($info == false || empty($info)) {
            $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '操作失败', "content" => "该订单没有申请退款权限或已经申请过退款操作"));
            exit();
        }
        $reason = array(
            array('title' => "发货太慢", "value" => "0"),
            array('title' => "我不喜欢了", "value" => "1"),
            array('title' => "拍错了，重拍", "value" => "2"),
            array('title' => "质量问题", "value" => "3"),
            array('title' => "其他原因", "value" => "4"),
            array('title' => "无理由", "value" => "5"),
        );
        $this->assign("reason", $reason);
        $this->assign('refund_amount', $info['refund_amount']);
        $this->assign("order_no", $info['order_no']);
        $this->assign("order_id", $info['order_id']);
        $this->assign("seo_title", "退款申请");
        $this->redirect();
    }

    public function refund_apply_submit() {
        $order_id = Filter::int(Req::args("order_id"));
        $reason = Filter::sql(Req::args("reason"));
        $reason_desc = Filter::sql(Req::args("reason_desc"));
        $return = $this->_isCanApplyRefund($order_id);
        if ($return == false || empty($return)) {
            $result = array('status' => 'fail', 'msg' => '该订单没有申请退款权限或已经申请过了');
            echo json_encode($result);
            exit();
        } else {
            $data['order_id'] = $return['order_id'];
            $data['order_no'] = $return['order_no'];
            $data['payment'] = $return['payment'];
            $data['user_id'] = $this->user['id'];
            $data['refund_amount'] = $return['refund_amount'];
            $data['apply_reason'] = $reason . ($reason_desc == "" ? "" : ":" . $reason_desc);
            $data['apply_time'] = date("Y-m-d H:i:s");
            $data['refund_progress'] = 0;
            $id = $this->model->table("refund")->data($data)->insert();
            if ($id) {
                //锁定订单，禁止发货
                if ($return['order_type'] == 4) {//华币订单
                    $isOk = $this->model->table("order")->data(array("pay_status" => '2'))->where("id = $order_id")->update();
                } else {
                    $isOk = $this->model->table("order")->data(array("pay_status" => '2'))->where("id = $order_id")->update();
                }
                if ($isOk) {
                    $result = array('status' => 'success', 'msg' => '申请成功');
                    echo json_encode($result);
                    exit();
                } else {
                    $result = array('status' => 'fail', 'msg' => '申请失败，数据库错误');
                    echo json_encode($result);
                    exit();
                }
            } else {
                $result = array('status' => 'fail', 'msg' => '申请失败，数据库错误');
                echo json_encode($result);
                exit();
            }
        }
    }

    public function refund_progress() {
        $order_id = Filter::sql(Req::args("order_id"));
        $refund_info = $this->model->table("refund as r")
                ->join("left join payment as p on r.payment = p.id")
                ->fields("r.*,p.pay_name,plugin_id")
                ->where("order_id = $order_id and user_id = " . $this->user['id'])
                ->find();
        if ($refund_info) {
            $this->assign("refund", $refund_info);
            $this->redirect();
        } else {
            $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '操作失败', 'content' => "退款信息不存在"));
            exit();
        }
    }
    public function pointcoin_log(){
        $this->assign("user_id",$this->user['id']);
        $this->assign("seo_title",'积分记录');
        $this->redirect();
    }
    public function order_delete() {
        $id = Filter::int(Req::args('id'));
        $isset = $this->model->table("order")->where("id=$id and user_id =" . $this->user['id'] . " and status in(1,2,5,6)")->find();
        if (empty($isset)) {
            echo json_encode(array('status' => 'fail', 'msg' => '失败'));
            exit();
        }
        $result = $this->model->table("order")->where("id = $id and user_id = " . $this->user['id'] . ' and status in (1,2,5,6)')->data(array('is_del' => '1'))->update();
        if ($result) {
            if($isset['status']!=6){
                if (($isset['type'] == 5||$isset['type']==6) && $isset['pay_point'] > 0) {
                    $this->model->table("customer")->where("user_id=" . $this->user['id'])->data(array("point_coin" => "`point_coin`+" . $isset['pay_point']))->update();
                    Log::pointcoin_log($isset['pay_point'], $this->user['id'], $isset['order_no'], "取消订单，退回积分", 2);
                }
            }
            echo json_encode(array('status' => 'success', 'msg' => '成功'));
            exit();
        } else {
            echo json_encode(array('status' => 'fail', 'msg' => '失败'));
            exit();
        }
    }

    public function promoter_home() {
        $promoter = Promoter::getPromoterInstance($this->user['id']);
        if (!is_object($promoter)) {
            exit();
        }
        $data = $promoter->getIncomeStatistics();
        $this->assign('data', $data);
        $this->assign('seo_title', '我的收益');
        $this->redirect();
    }
    
    public function promoter_income() {
        $promoter = Promoter::getPromoterInstance($this->user['id']);
        if (!is_object($promoter)) {
            $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '操作失败', "content" => "抱歉，您还不是推广者"));
            exit();
        }
        if ($this->is_ajax_request()) {
            $page = Filter::int(Req::args('p'));
            $data = $promoter->getMyIncomeRecord($page);
            if (empty($data)) {
                echo json_encode(array('status' => 'fail', 'msg' => "数据为空"));
                exit();
            }
            echo json_encode(array('status' => 'success', 'data' => $data['data']));
            exit();
        } else {
            $this->assign('data', $promoter->getMyIncomeRecord(1));
            $this->assign("seo_title", "收益记录");
            $this->redirect();
        }
    }

    public function promoter_sale() {
        $promoter = Promoter::getPromoterInstance($this->user['id']);
        if (!is_object($promoter)) {
            $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '操作失败', "content" => "抱歉，您还不是推广者"));
            exit();
        }
        if ($this->is_ajax_request()) {
            $page = Filter::int(Req::args('p'));
            $data = $promoter->getMySaleRecord($page);
            if (empty($data)) {
                echo json_encode(array('status' => 'fail', 'msg' => "数据为空"));
                exit();
            }
            echo json_encode(array('status' => 'success', 'data' => $data['data']));
            exit();
        } else {
            $this->assign('data', $promoter->getMySaleRecord(1));
            $this->assign("seo_title", "销售记录");
            $this->redirect();
        }
    }

    public function promoter_withdraw() {
        $config = Config::getInstance();
        $other = $config->get("district_set");
        $withdraw_fee_rate = isset($other['withdraw_fee_rate']) ? $other['withdraw_fee_rate'] : 0.5;
        $min_withdraw_amount = isset($other['min_withdraw_amount']) ? $other['min_withdraw_amount'] : 0.1;
        $this->assign('withdraw_fee_rate', $withdraw_fee_rate);
        $this->assign('min_withdraw_amount', $min_withdraw_amount);
        $this->assign('seo_title', '收益提现');
        $this->redirect();
    }

    public function promoter_withdraw_submit() {
        $promoter = Promoter::getPromoterInstance($this->user['id']);
        if (!is_object($promoter)) {
            exit();
        }
        if ($this->is_ajax_request()) {
            $data = Req::args();
            unset($data['con']);
            unset($data['act']);
            echo json_encode($promoter->applyDoSettle($data));
        } else {
            echo json_encode(array('status' => 'fail', 'msg' => 'bad request'));
        }
    }

    public function promoter_withdraw_list() {
        $promoter = Promoter::getPromoterInstance($this->user['id']);
        if (!is_object($promoter)) {
            $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '操作失败', "content" => "抱歉，您还不是推广者"));
            exit();
        }
        if ($this->is_ajax_request()) {
            $page = Filter::int(Req::args('p'));
            $data = $promoter->getSettledHistory($page);
            if (empty($data)) {
                echo json_encode(array('status' => 'fail', 'msg' => "数据为空"));
                exit();
            }
            echo json_encode(array('status' => 'success', 'data' => $data['data']));
            exit();
        } else {
            $this->assign('data', $promoter->getSettledHistory(1));
            $this->assign("seo_title", "提现记录");
            $this->redirect();
        }
    }

    public function promoter_getqrcode() {
        $promoter = Promoter::getPromoterInstance($this->user['id']);
        if (!is_object($promoter)) {
            $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '操作失败', "content" => "抱歉，您还不是推广者"));
            exit();
        }
        $goods_id = Req::args('goods_id');
        $goods_id = substr($goods_id, 0, strpos($goods_id, '.png'));
        $promoter->getQrcodeByGoodsId($goods_id);
    }
    
    //申请创建小区
    public function apply_for_district() {
        if ($this->is_ajax_request()) {
            $data = Filter::inputFilter(Req::args());
            if ($data['name'] == NULL || $data['location'] == NULL || $data['linkman'] == NULL || $data['linkmobile'] == NULL) {
                echo json_encode(array('status' => 'fail', 'msg' => '请完善申请信息'));
                exit();
            }
            unset($data['con']);
            unset($data['act']);
            if (!empty($data)) {
                $data['user_id'] = $this->user['id'];
                $data['status'] = 0;
                $data['apply_time'] = date("Y-m-d H:i:s");
                if ($data['free'] == 1) {
                    $promoter = Promoter::getPromoterInstance($this->user['id']);
                    if (is_object($promoter)) {
                        $invite_count = $this->model->table("district_order")->where("pay_status=1 and invitor_role='promoter' and invitor_id=$promoter->id")->count();
                        $config = Config::getInstance()->get("district_set");
                        if ($invite_count < $config['invite_promoter_num']) {
                            exit(json_encode(array('status'=>'fail',"msg"=>"您没有免费申请权限")));
                        }
                        $hirer = $this->model->table("district_shop")->where("owner_id =" . $this->user['id'])->find();
                        if ($hirer) {
                            exit(json_encode(array('status'=>'fail',"msg"=>"您已经拥有小区了")));
                        }
                        $apply = $this->model->table("district_apply")->where("free = 1 and status = 0 and user_id = ".$this->user['id'])->find();
                        if($apply){
                             exit(json_encode(array('status'=>'fail',"msg"=>"已经申请过了，请勿重复申请")));
                        }
                        $data['pay_status']=1; //将免费入驻标记为已经支付
                    } else {
                         exit(json_encode(array('status'=>'fail',"msg"=>"您不是推广员")));;
                    }
                } else {
                    unset($data['free']);
                }
                if ($data['reference'] == '') {
                    unset($data['reference']);
                }
                $id = $this->model->table('district_apply')->data($data)->insert();
                if ($id) {
                    Cookie::clear("district_id");
                    Cookie::clear("test");
                    echo json_encode(array('status' => 'success', 'msg' => '申请提交成功', 'id' => $id));
                    exit();
                }
            } else {
                echo json_encode(array('status' => 'fail', 'msg' => '申请信息错误'));
                exit();
            }
        } else {
            $free = Filter::int(Req::args('free'));
            if ($free == 1) {
                $promoter = Promoter::getPromoterInstance($this->user['id']);
                if (is_object($promoter)) {
                    $invite_count = $this->model->table("district_order")->where("pay_status=1 and invitor_role='promoter' and invitor_id=$promoter->id")->count();
                    $config = Config::getInstance()->get("district_set");
                    if ($invite_count < $config['invite_promoter_num']) {
                        $this->redirect("/index/msg", false, array('type' => "info", "msg" => '抱歉', "content" => "您没有免费申请权限"));
                        exit();
                    }
                    $hirer = $this->model->table("district_shop")->where("owner_id =" . $this->user['id'])->find();
                    if ($hirer) {
                        $this->redirect("/index/msg", false, array('type' => "info", "msg" => '抱歉', "content" => "您已经拥有小区了"));
                        exit();
                    }
                    $this->assign('seo_title', "免费入驻申请");
                    $this->assign("free", 1);
                    $this->redirect();
                    exit();
                } else {
                    $this->redirect("/index/msg", false, array('type' => "info", "msg" => '抱歉', "content" => "您还不是推广员"));
                    exit();
                }
            }
            //推荐人
            $reference = Filter::int(Req::args('reference'));
            if ($reference != "") {
                $this->assign("reference", $reference);
            }
            $this->assign("free", 0);
            $this->assign('seo_title', '申请入驻');
            $this->redirect();
        }
    }

    //成为小区推广者
    public function becomepromoter() {
        if ($this->is_ajax_request()) {
            echo json_encode(array('status' => 'fail', 'msg' => '抱歉，接口关闭了'));
            exit();
        } else {
            $reference = Filter::int(Req::args('reference'));
            $invitor_role = Filter::str(Req::args('invitor_role'));
            $invitor_role = $invitor_role == NULL ? "shop" : $invitor_role; //默认是shop

            $promoter = Promoter::getPromoterInstance($this->user['id']);
            if (is_object($promoter)) {
                $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '操作失败', "content" => "抱歉，您已经有雇佣关系了，暂时不能加入其他小区"));
                exit();
            }
            if ($reference == null) {
                $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '操作失败', "content" => "抱歉，还没有接收到邀请哦"));
                exit();
            } else {
                if ($invitor_role == 'shop') {
                    $district_info = $this->model->table("district_shop")->where("id = $reference")->find();
                } else if ($invitor_role == 'promoter') {
                    $district_info = $this->model
                            ->table("district_promoter as dp")->join("left join district_shop as ds on dp.hirer_id = ds.id")
                            ->where("dp.id = $reference")
                            ->find();
                } else {
                    $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '抱歉', "content" => "role信息错误"));
                    exit();
                }
                if (!isset($district_info) || !$district_info) {
                    $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '抱歉', "content" => "小区信息错误"));
                    exit();
                }
                //礼品地址
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
                $this->assign("parse_area", $parse_area);
                $this->assign('address', $address);

                $config = Config::getInstance()->get("district_set");
                //礼品
                if (isset($config['join_send_gift']) && $config['join_send_gift'] != "") {
                    $gift = implode(",", explode("|", $config['join_send_gift']));
                } else {
                    $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '抱歉', "content" => "小区配置信息错误"));
                    exit();
                }
                $gift_list = $this->model->table("products as p")->join("goods as g on p.goods_id=g.id")->where("p.id in ({$gift})")->fields("p.id,g.img,g.name")->findAll();
                $this->assign("gift_list", $gift_list);
                //支付方式
                $client_type = Chips::clientType();
                $client_type = ($client_type == "desktop") ? 0 : ($client_type == "wechat" ? 2 : 1);
                $paytypelist = $this->model->table("payment as pa")->fields("pa.*,pp.logo,pp.class_name")->join("left join pay_plugin as pp on pa.plugin_id = pp.id")
                                ->where("pa.status = 0 and pa.plugin_id not in(1,12,19,20) and pa.client_type = $client_type")->order("pa.sort desc")->findAll();
                $this->assign("config", $config);
                $this->assign('paytypelist', $paytypelist);
                $this->assign('data', $district_info);
                $this->assign("reference", $reference);
                $this->assign("invitor_role", $invitor_role);
                $this->assign('seo_title', "成为推广者");
                $this->redirect();
            }
        }
    }

    //领取充值活动奖励 
    public function accept_present() {
        $activity = $this->model->table("recharge_activity")->where("id = 1")->fields("accept_end_time")->find();
        if ($this->is_ajax_request()) {
            $user_id = $this->user['id'];
            $accept_name = Filter::sql(Req::args("accept_name"));
            $mobile = Filter::sql(Req::args("mobile"));
            $address = Filter::sql(Req::args("address"));
            $addr = Filter::sql(Req::args("addr"));
            if ($accept_name == "" || $mobile == "" || $addr == "" || $address == "") {
                exit(json_encode(array("status" => 'fail', 'msg' => "抱歉，数据不完善")));
            }
            if (strtotime($activity['accept_end_time']) < time()) {
                $this->model->query("update tiny_recharge_presentlog set status='-1' where user_id = $user_id");
                exit(json_encode(array("status" => 'fail', 'msg' => "抱歉，已过领取时间")));
            }
            $info = $this->model->table("recharge_presentlog")->where("user_id =$user_id and status=0")->find();
            if ($info) {
                $result = $this->model->table("recharge_presentlog")->data(array("contact_man" => $accept_name, "contact_mobile" => $mobile, 'addr' => $address . " " . $addr, 'status' => 1))->where("user_id = $user_id and status=0")->update();
                if ($result) {
                    exit(json_encode(array("status" => 'success', 'msg' => "成功")));
                } else {
                    exit(json_encode(array("status" => 'fail', 'msg' => "抱歉，数据库错误")));
                }
            } else {
                exit(json_encode(array("status" => 'fail', 'msg' => "抱歉，不具备领取资格或已经领取过了")));
            }
        } else {

            if (strtotime($activity['accept_end_time']) > time()) {
                $this->assign('seo_title', "领取充值奖励");
                $this->redirect();
            } else {
                $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '抱歉', "content" => "礼品领取时间已过"));
                exit();
            }
        }
    }


    public function district_pay() {
        $id = Filter::int(Req::args('id'));
        $model = new Model();
        $district_info = $model->table("district_apply")->where("id={$id}")->find();
        if (!empty($district_info)) {
            if ($district_info['pay_status'] == 0) {
                $this->redirect("/index/msg", false, array('type' => "info", "msg" => '温馨提示', "content" => "不支持在线支付，请您与我司联系，洽谈相关事宜。谢谢"));
                exit();
                $config_all = Config::getInstance();
                $set = $config_all->get('district_set');
                if (isset($set['join_fee'])) {
                    $this->assign("join_fee", $set['join_fee']);
                } else {
                    $this->assign("join_fee", "10000");
                }
                $client_type = Chips::clientType();
                $client_type = ($client_type == "desktop") ? 0 : ($client_type == "wechat" ? 2 : 1);
                $paytypelist = $model->table("payment as pa")->fields("pa.*,pp.logo,pp.class_name")->join("left join pay_plugin as pp on pa.plugin_id = pp.id")
                                ->where("pa.status = 0 and pa.plugin_id not in(1,4,12,19,20) and pa.client_type = $client_type")->order("pa.sort desc")->findAll();
                $this->assign('paytypelist', $paytypelist);
                $this->assign("district", $district_info);
                $this->redirect();
            } else if ($district_info['pay_status'] == 1) {
                $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '抱歉', "content" => "已经支付过了"));
                exit();
            }
        } else {
            $this->redirect("/index/msg", false, array('type' => "fail", "msg" => '抱歉', "content" => "信息不存在"));
            exit();
        }
    }
    
    //推广商品二维码页
    public function showQR() {
        $goods_id = Filter::int(Req::args("goods_id"));
        $goods_info = $this->model->table("goods")->where("id = $goods_id")->find();
        if ($goods_info) {
            $result = Common::getQrcodeFlag($goods_id, $this->user['id']);
            if ($result['status'] == 'success') {
                $this->assign("flag", $result['flag']);
                $this->assign("url", $result['url']);
            } else {
                $this->redirect("/index/msg", false, array('type' => "info", "msg" => $result['msg']));
                exit();
            }
            $this->layout = "none";
            $this->assign("img_url", $goods_info['img']);
            $this->assign("goods_name", $goods_info['name']);
            $this->assign("goods_tags", $goods_info['tag_ids']);
            $this->assign("goods_subtitle", $goods_info['subtitle']);
            $this->redirect();
            exit();
        }else{
            $this->redirect("/index/msg", false, array('type' => "info", "msg" => "商品信息未找到"));
            exit();
        }
    }
    
    //获取推广员邀请信息
    public function promoter_invite() {
        if ($this->is_ajax_request()) {
            $page = Filter::int(Req::args('page'));
            $promoter = Promoter::getPromoterInstance($this->user['id']);
            if (is_object($promoter)) {
                if($promoter->role_type==1){
                    echo json_encode(array('status' => 'fail', 'msg' => "你还不是付费推广员"));
                    exit();
                }
                $data = $promoter->getMyInviteList($page);
                if (empty($data)) {
                    echo json_encode(array('status' => 'fail', 'msg' => "数据为空"));
                    exit();
                }
                echo json_encode(array('status' => 'success', 'data' => $data['data']));
                exit();
            } else {
                exit("您还不是推广员");
            }
        } else {
            $this->assign("seo_title", "邀请入驻");
            $promoter = Promoter::getPromoterInstance($this->user['id']);
            if (is_object($promoter)) {
                if($promoter->role_type==1){
                    $this->redirect("/index/msg", false, array('type' => "info", "msg" => '抱歉', "content" => "您还不是付费推广员，暂时没有邀请权限"));
                exit();
                }
                $data = $promoter->getMyInviteList(1);
                $this->assign("data", $data);
                $invite_count = $this->model->table("district_order")->where("pay_status=1 and invitor_role='promoter' and invitor_id=$promoter->id")->count();
                $this->assign("invite_count", $invite_count);
                $config = Config::getInstance()->get("district_set");
                $this->assign("invite_promoter_num", $config['invite_promoter_num']);
                $hirer = $this->model->table("district_shop")->where("owner_id =" . $this->user['id'])->find();
                if ($hirer) {
                    $this->assign("has_district_shop", 1);
                } else {
                    $this->assign("has_district_shop", 0);
                }
            } else {
                $this->redirect("/index/msg", false, array('type' => "info", "msg" => '抱歉', "content" => "您还不是推广员"));
                exit();
            }
            $this->redirect();
        }
    }
    
    //获取推广员推荐二维码
    public function getPromoterInviteQR() {
        $promoter = Promoter::getPromoterInstance($this->user['id']);
        if (is_object($promoter)) {
            $promoter->getInviteQR4Promoter();
        } else {
            exit("您还不是推广员");
        }
    }
    
    //签到
    public function sign_in(){
        if($this->is_ajax_request()){
            $action = Filter::str(Req::args('action'));
            if($action=='sign'){
                $config = Config::getInstance();
                $set = $config->get('sign_in_set');
                if($set['open']==0){
                     exit(json_encode(array('status'=>'fail','msg'=>"系统关闭了签到功能"))); 
                }
                //判断今天是否签到过
                $date = date("Y-m-d");
                $is_signed = $this->model->table("sign_in")->where("date='$date'")->find();
                if($is_signed){
                    exit(json_encode(array('status'=>'fail','msg'=>"今天已经签到过了")));
                }else{
                    $last_sign = $this->model->table("sign_in")->order('date desc')->where("user_id=".$this->user['id'])->find();
                    if($last_sign){
                            //判断上次签到和这次签到中间是否有缺
                            $yesterday = date("Y-m-d",strtotime("-1 day"));
                            if($yesterday==$last_sign['date']){
                                $data['serial_day']=$last_sign['serial_day']+1;
                                $data['sign_in_count']=$last_sign['sign_in_count']+1;
                            }else{
                                $data['serial_day']=1;
                                $data['sign_in_count']=$last_sign['sign_in_count']+1;
                            }
                    }else{
                         $data['serial_day']=1;
                         $data['sign_in_count']=1;
                    }
                    $data['date']=$date;
                    $data['user_id']=$this->user['id'];
                    //读取签到送积分规则
                    $data['send_point']=Common::getSignInSendPointAmount($data['serial_day']);
                    $result = $this->model->table("sign_in")->data($data)->insert();
                    if($result){
                       $this->model->table("customer")->data(array('point_coin'=>"`point_coin`+".$data['send_point']))->where("user_id=".$this->user['id'])->update();
                       Log::pointcoin_log($data['send_point'], $this->user['id'], "", "每日签到赠送", 10);
                       exit(json_encode(array('status'=>'success','msg'=>"签到成功",'send_point'=>$data['send_point'])));
                    }else{
                       exit(json_encode(array('status'=>'fail','msg'=>"签到失败了"))); 
                    }
                }
            }else if($action=='data'){
                $year = Filter::int(Req::args("year"));
                $month = Filter::int(Req::args("month"));
                exit(json_encode(array("status"=>'success','data'=>  Common::getSignInDataByUserID($year, $month, $this->user['id']))));
            }
        }else{
            $today = $this->model->table("sign_in")->where("date='".date("Y-m-d")."'")->find();
            if($today){
                $this->assign('serial_day',$today['serial_day']);
                $this->assign("is_signed",true);
            }else{
                $yesterday = $this->model->table("sign_in")->where("date='".date("Y-m-d",strtotime("-1 day"))."' and user_id=".$this->user['id'])->find();
                if($yesterday){
                    $this->assign('serial_day',$yesterday['serial_day']);
                }else{
                    $this->assign('serial_day',0);
                }
                $this->assign("is_signed",false);
            }
            $config = Config::getInstance();
            $this->assign('sign_in_set', $config->get('sign_in_set'));
            $this->assign('sign_data',Common::getSignInDataByUserID(date("Y"),date("m"),$this->user['id']));
            $this->assign('year',date("Y"));
            $this->assign("month",date("m"));
            $this->assign('seo_title',"每日签到");
            $this->redirect();
        }    
    }
    
    
}
