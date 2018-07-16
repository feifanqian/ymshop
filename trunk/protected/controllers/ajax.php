<?php

class AjaxController extends Controller {

    public $layout = '';
    public $model = null;
    public $needRightActions = array('*' => false);

    public function init() {
        header("Content-type: text/html; charset=" . $this->encoding);
        $this->model = new Model();
    }

    public function addr() {
        $model = new Model("area");
        $this->list = $model->order('sort')->findAll();
        $result = $this->addrinner(0);
        echo json_encode($result);
    }

    public function addrinner($id) {
        $arealist = array();
        foreach ($this->list as $k => $v) {
            if ($v['parent_id'] == $id) {
                $data = array(
                    "id" => $v['id'],
                    "name" => $v['name'],
                );
                $sonlist = $this->addrinner($v['id']);
                if ($sonlist) {
                    $data['sub'] = $sonlist;
                }
                $arealist[] = $data;
            }
        }
        return $arealist;
    }

    public function upload() {
//        print_r($_POST);
        $upfile_path = Tiny::getPath("uploads");
        $upfile = new UploadFile('file', $upfile_path, '100m', "stl");
        $upfile->save();
        print_r($_FILES);
        print_r($upfile->getInfo());
    }

    //团购结束更新
    public function groupbuy_end() {

        $id = Filter::int(Req::args('id'));
        if ($id) {
            $item = $this->model->table("groupbuy")->where("id=$id")->find();
            $end_diff = time() - strtotime($item['end_time']);
            if ($end_diff > 0) {
                $this->model->table("groupbuy")->where("id=$id")->data(array('is_end' => 1))->update();
            }
        }
    }

    //抢购结束更新
    public function flashbuy_end() {
        $id = Filter::int(Req::args('id'));
        if ($id) {
            $item = $this->model->table("flash_sale")->where("id=$id")->find();
            $end_diff = time() - strtotime($item['end_time']);
            if ($end_diff > 0) {
                $this->model->table("flash_sale")->where("id=$id")->data(array('is_end' => 1))->update();
            }
        }
    }

    //订单是否已经付款
    public function isOrderPayment() {
        $order_no = Req::args('order_no');
        if (stripos($order_no, 'recharge') !== false) {
            $model = new Model('recharge');
            $recharge_no = substr($order_no, 8);
            $obj = $model->where("recharge_no='" . Filter::int($recharge_no) . "' and status = 1")->find();
        } else {
            $model = new Model('order');
            $obj = $model->where("order_no='" . Filter::int($order_no) . "' and pay_status = 1")->find();
        }
        $info = array('status' => 'fail');
        if ($obj) {
            $info = array('status' => 'success');
        }
        echo JSON::encode($info);
    }

    //发送短信验证码
    public function send_sms() {
        $mobile = Filter::sql(Req::args('mobile'));
        if (Validator::mobi($mobile)) {
            $model = new Model('mobile_code');
            $time = time() - 120;
            $obj = $model->where("send_time < $time")->delete();
            $obj = $model->where("mobile='" . $mobile . "'")->find();
            if ($obj) {
                $info = array('status' => 'fail', 'msg' => '120秒内仅能获取一次短信验证码,请稍后重试!');
            } else {
                $sms = SMS::getInstance();
                // if ($sms->getStatus()) {
                    $code = CHash::random('6', 'int');
                    $result = $sms->sendCode($mobile, $code);
                    if ($result['status'] == 'success') {
                        $info = array('status' => 'success', 'msg' => $result['message']);
                        $model->data(array('mobile' => $mobile, 'code' => $code, 'send_time' => time()))->insert();
                    } else {
                        $info = array('status' => 'fail', 'msg' => $result['message']);
                    }
                // } else {
                //     $info = array('status' => 'fail', 'msg' => '请开启手机验证功能!');
                // }
            }
        }
        echo JSON::encode($info);
    }
    //后台短信验证码
    public function getSmsCode() {
        $name = Filter::sql(Req::args('name'));
        $model = new Model('manager');
        $mobile_info = $model->where("name ='$name'")->fields('mobile,name')->find();
        if(empty($mobile_info)||$mobile_info['mobile']==""){
            $info = array('status' => 'fail', 'msg' => "未找到绑定信息!");
        }else{
            if (Validator::mobi($mobile_info['mobile'])) {
                $model = new Model('mobile_code');
                $time = time() - 120;
                $obj = $model->where("send_time < $time")->delete();
                $obj = $model->where("mobile='" . $mobile_info['mobile'] . "'")->find();
                if ($obj) {
                    $info = array('status' => 'fail', 'msg' => '120秒内仅能获取一次短信验证码,请稍后重试!');
                } else {
                    $sms = SMS::getInstance();
                    // if ($sms->getStatus()) {
                        $code = CHash::random('6', 'int');
                        $result = $sms->sendCode($mobile_info['mobile'], $code);
                        if ($result['status'] == 'success') {
                            $info = array('status' => 'success', 'msg' => $result['message']);
                            $model->data(array('mobile' => $mobile_info['mobile'], 'code' => $code, 'send_time' => time()))->insert();
                        } else {
                            $info = array('status' => 'fail', 'msg' => $result['message']);
                        }
                    // } else {
                    //     $info = array('status' => 'fail', 'msg' => '请开启手机验证功能!');
                    // }
                }
            }
        }
        echo JSON::encode($info);
    }
    //计算运费
    public function calculate_fare() {
        $weight = Filter::int(Req::args('weight'));
        $id = Filter::int(Req::args('id'));
        $product = Req::args('product');
        $fare = new Fare($weight);
        $fee = $fare->calculate($id, $product);
        echo JSON::encode(array('status' => "success", 'fee' => $fee));
    }

    public function email() {
        $email = Filter::sql(Req::args('email'));
        $info = array('status' => false, 'msg' => '此邮箱已经注册');
        $model = new Model('user');
        $obj = $model->where("email='$email'")->find();
        if (!$obj)
            $info = array('status' => true, 'msg' => '');
        echo JSON::encode($info);
    }

    public function name() {
        $name = Filter::sql(Req::args('name'));
        $info = array('status' => false, 'msg' => '此用户已经注册');
        $model = new Model('user');
        $obj = $model->where("name='$name'")->find();
        if (!$obj)
            $info = array('status' => true, 'msg' => '');
        echo JSON::encode($info);
    }

    public function mobile() {
        $mobile = Filter::sql(Req::args('mobile'));
        $info = array('status' => false, 'msg' => '此手机号已经注册');
        $model = new Model('customer');
        $obj = $model->where("mobile='$mobile'")->find();
        if (!$obj)
            $info = array('status' => true, 'msg' => '');
        echo JSON::encode($info);
    }

    public function account() {
        $account = Req::args('account');
        if (Validator::email($account)) {
            Req::args('email', $account);
            $this->email();
        } elseif (Validator::mobi($account)) {
            Req::args('mobile', $account);
            $this->mobile();
        } elseif (Validator::name($account)) {
            $info = array('status' => false, 'msg' => '此用户名已存在');
            $model = new Model('user');
            $obj = $model->where("name='$account'")->find();
            if (!$obj)
                $info = array('status' => true, 'msg' => '');
            echo JSON::encode($info);
        }else {
            $info = array('status' => true, 'msg' => '');
            echo JSON::encode($info);
        }
    }

    public function accounts() {
        $account = Req::args('account');
        if (Validator::mobi($account)!=true) {
            $info = array('status' => false, 'msg' => '手机号格式错误');
            echo JSON::encode($info);
        } 
        $model = new Model('customer');
        $obj = $model->where("mobile='$account'")->find();
        if ($obj) {
            $info = array('status' => true, 'msg' => 'success');
        } else {
            $info = array('status' => false, 'msg' => '此账号不存在');
        }
        echo JSON::encode($info);
    }

    public function verifyCode() {
        $info = array('status' => false, 'msg' => '验证码错误！');
        $this->safebox = Safebox::getInstance();
        $code = $this->safebox->get($this->captchaKey);
        $verifyCode = Req::args("verifyCode");
        if ($code == $verifyCode)
            $info = array('status' => true, 'msg' => '');
        echo JSON::encode($info);
    }

    public function category_type() {
        $id = Filter::int(Req::args('id'));
        $json_array = array('type_id' => "-1");
        if ($id) {
            $model = new Model("goods_category");
            $category = $model->where("id=" . $id)->find();
            if ($category)
                $json_array = array('type_id' => $category['type_id']);
        }
        echo JSON::encode($json_array);
    }

    public function type_attr() {
        $id = Filter::int(Req::args('id'));
        $json_array = array();
        if ($id) {
            $model = new Model("goods_type");
            $type = $model->where("id=" . $id)->find();
            if ($type)
                $json_array = unserialize($type['attr']);
        }
        echo JSON::encode($json_array);
    }

    public function area() {
        $id = Filter::int(Req::args('id'));
        $json_array = array();
        if ($id) {
            $model = new Model("area");
            $area = $model->where("parent_id=" . $id)->order('sort')->findAll();
            if ($area)
                $json_array = $area;
        }
        echo JSON::encode($json_array);
    }

    private function _AreaInit($id, $level = '0') {
        $result = $this->model->table('area')->where("parent_id=" . $id)->order('sort')->findAll();
        $list = array();
        if ($result) {

            foreach ($result as $key => $value) {
                $id = "o_" . $value['id'];
                //$list["$id"]['i'] = $value['id'];
                //$list["$id"]['pid'] = $value['parent_id'];
                $list["$id"]['t'] = $value['name'];
                //$list["$id"]['level'] = $level;
                if ($level < 2)
                    $list[$id]['c'] = $this->_AreaInit($value['id'], $level + 1);
            }
        }
        return $list;
    }

    public function areas() {
        $cache = CacheFactory::getInstance();
        $items = $cache->get("_AreaData");
        if ($items == null) {
            $items = JSON::encode($this->_AreaInit(0));
            $cache->set("_AreaData", $items, 315360000);
        }
        return $items;
    }

    public function area_data() {
        $result = $this->areas();
        echo ($result);
    }

    private function _AreaInits($id, $level = '0') {
        // $result = $this->model->table('areas')->where("parent_id=" . $id)->order('sort')->findAll();
        $result = $this->model->table('region')->where("parent_id=" . $id)->order('id desc')->findAll();
        $list = array();
        if ($result) {

            foreach ($result as $key => $value) {
                $id = "o_" . $value['id'];
                //$list["$id"]['i'] = $value['id'];
                //$list["$id"]['pid'] = $value['parent_id'];
                $list["$id"]['t'] = $value['name'];
                //$list["$id"]['level'] = $level;
                if ($level <= 2)
                    $list[$id]['c'] = $this->_AreaInits($value['id'], $level + 1);
            }
        }
        return $list;
    }

    public function areass() {
        $cache = CacheFactory::getInstance();
        $items = $cache->get("_AreaDatas");
        if ($items == null) {
            $items = JSON::encode($this->_AreaInits(0));
            $cache->set("_AreaDatas", $items, 315360000);
        }
        return $items;
    }

    public function area_datas() {
        $result = $this->areass();
        echo ($result);
    }

    public function test() {
        $codebar = "BCGcode128"; //$_REQUEST['codebar'];
        $color_black = new BCGColor(0, 0, 0);
        $color_white = new BCGColor(255, 255, 255);
        $drawException = null;
        try {
            $code = new $codebar(); //实例化对应的编码格式
            $code->setScale(2); // Resolution
            $code->setThickness(23); // Thickness
            $code->setForegroundColor($color_black); // Color of bars
            $code->setBackgroundColor($color_white); // Color of spaces
            $text = Req::args('code'); //条形码将要数据的内容
            $code->parse($text);
        } catch (Exception $exception) {
            $drawException = $exception;
        }
        $drawing = new BCGDrawing('', $color_white);
        if ($drawException) {
            $drawing->drawException($drawException);
        } else {
            $drawing->setBarcode($code);
            $drawing->draw();
        }
        header('Content-Type: image/png');
        $drawing->finish(BCGDrawing::IMG_FORMAT_PNG);
    }

    //又拍云上传回调
    public function upyun() {
        $url = Filter::sql(Req::args('url'));
        $sign = Filter::sql(Req::args('sign'));
        $type = Filter::sql(Req::args('ext-param'));
        $size = Filter::sql(Req::args('file_size'));
        $mimetype = Filter::sql(Req::args('mimetype'));
        if ($url && $sign) {
            $typearr = explode(':', $type);
            $type_id = end($typearr);
            // 透传的数据
            if (count($typearr) > 1) {
                // 如果上传的是头像
                if ($typearr[0] == 'avatar') {
                    $this->model->table('user')->data(array('avatar' => $url))->where("id = '$type_id'")->update();
                }
            } else {
                $params = array('sign' => $sign, 'type' => $type, 'mimetype' => $mimetype, 'url' => $url, 'size' => $size, 'createtime' => date("Y-m-d H:i:s"));
                $this->model->table("gallery")->data($params)->insert();
            }
            echo "success";
        } else {
            echo "failure";
        }
    }
    
    public function fakeMobileCode(){
        $mobile = Filter::sql(Req::args('mobile'));
        $code   = Filter::sql(Req::args('code'));
        $code  = $code ? "123456":$code;
        $model = new Model("mobile_code");
        $result = $model->data(array('mobile'=>$mobile,'code'=>$code,'send_time'=>time()))->insert();
        var_dump($result);
    }

    public function uploadUpyun(){
        $upyun = new Upyun();
        $file = $_POST['file'];
        $user_id = $_POST['user_id'];
        $fh = fopen($file, 'rb');
        $newname = time().$user_id . '.jpg';
        $newfileurl = '/data/uploads/positive_idcard/' . $newname;
        $upinfo = $upyun->writeFile($newfileurl, $fh, True);   // 上传图片，自动创建目录
        fclose($fh);
        $path = 'https://ymlypt.b0.upaiyun.com' . $newfileurl;
        $this->model->table('shop_check')->data(['positive_idcard'=>$path])->where('user_id=1')->update();
        exit(json_encode(array('status' => 'success', 'msg' => '成功','path'=>$path)));
    }

    public function userVoucher() {
        $id = Filter::int(Req::args("id"));
        $voucher = $this->model->table('active_voucher')->where('id='.$id)->find();
        if($voucher['type']==1) {
            $point = $voucher['amount'];
            $this->model->table('customer')->data(array('point_coin'=>"`point_coin`+({$point})"))->where('user_id='.$voucher['user_id'])->update();
            Log::pointcoin_log($point,$voucher['user_id'], '', "积分卡券兑换", 13);
        } elseif($voucher['type']==2) {
            $travel = $this->model->table('active_voucher')->where('user_id='.$voucher['user_id'].' and type=3')->find();
            if($travel) {
                if($travel['status']==1) {
                    exit(json_encode(array('status' => 'error', 'msg' => '请先激活旅游券')));    
                } elseif($travel['status']==2) {
                    exit(json_encode(array('status' => 'error', 'msg' => '请先完成港澳游之旅')));
                }
            } else {
                exit(json_encode(array('status' => 'error', 'msg' => '请先领取旅游券')));
            }
            $point = $voucher['amount'];
            $this->model->table('customer')->data(array('balance'=>"`balance`+({$point})"))->where('user_id='.$voucher['user_id'])->update();
            Log::balance($point,$voucher['user_id'], '', "余额卡券兑换", 16);
        }
        if($voucher['type']==3) {
            $status = 2; //旅游券专用激活状态
        } else {
            $status = 0;
        }
        $ret = $this->model->table('active_voucher')->data(array('status'=>$status))->where('id='.$id)->update();
        exit(json_encode(array('status' => 'success', 'msg' => '成功')));
    }
}
