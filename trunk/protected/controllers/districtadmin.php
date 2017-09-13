<?php

class DistrictadminController extends Controller {

    public $layout = 'admin';
    private $top = null;
    private $manager = null;
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
        $this->manager = $this->safebox->get('manager');
        $this->assign('manager', $this->manager);
        $this->assign('upyuncfg', Config::getInstance()->get("upyun"));

        $currentNode = $menu->currentNode();
        if (isset($currentNode['name']))
            $this->assign('admin_title', $currentNode['name']);
    }

    public function noRight() {
        $this->layout = '';
        $this->redirect("admin/noright");
    }

    public function record_sale() {
        $condition = Req::args("condition");
        $condition_str = Common::str2where($condition);

        if ($condition_str) {
            $this->assign("where", $condition_str);
        } else {
            $this->assign("where", "1=1");
        }
        $this->assign("condition", $condition);
        $this->redirect();
    }

    public function record_income() {
        $this->model=new Model();
        $page = intval(Req::args("p"));
        $page_size = 10;
        $condition = Req::args("condition");
        $condition_str = Common::str2where($condition);

        if ($condition_str) {
            $where = $condition_str;
            $this->assign("where", $condition_str);
        } else {
            $where = "1=1";
            $this->assign("where", "1=1");
        }
        $this->assign("condition", $condition);
        // var_dump($where);die;
        // $where="c.real_name='练聪'";
        $list = $this->model->table('promote_income_log as p')->join('left join user as u on p.role_id=u.id left join customer as c on p.role_id=c.user_id left join district_shop as d on p.role_id=d.id')->fields("p.*,u.nickname,c.real_name,d.name as shopname")->where($where)->order("id desc")->findPage($page, $page_size);
        
        // var_dump($list);die;
        $this->assign("list", $list);
        $this->redirect();
    }

    public function list_hirer() {
        $this->model=new Model();
        $page = intval(Req::args("p"));
        $page_size = 10;
        $condition = Req::args("condition");
        $condition_str = Common::str2where($condition);

        if ($condition_str) {
            $where = $condition_str;
            $this->assign("where", $condition_str);
        } else {
            $where = "1=1";
            $this->assign("where", "1=1");
        }
        $this->assign("condition", $condition);
        $list = $this->model->table('district_shop as ds')->join('left join customer as c on ds.owner_id=c.user_id left join district_shop as d on ds.invite_shop_id=d.id')->fields("ds.*,c.real_name,d.name as invite_shop_name")->where($where)->order("ds.id desc")->findPage($page, $page_size);
        $this->assign("list", $list);
        $this->redirect();
    }

    public function view_achievement() {
        if ($this->is_ajax_request()) {
            $role_type = Filter::int(Req::args('role_type'));
            $role_id = Filter::int(Req::args('role_id'));
            $period = Filter::int(Req::args('period'));
            switch ($period) {
                case 1:$start_time = date("Y-m-d 00:00:00");
                    $end_time = date("Y-m-d 23:59:59");
                    break;
                case 2:$start_time = date("Y-m-d 00:00:00", strtotime("-1 days"));
                    $end_time = date("Y-m-d 23:59:59", strtotime("-1 days"));
                    break;
                case 3:$start_time = date("Y-m-d 00:00:00", strtotime("-6 days"));
                    $end_time = date("Y-m-d 23:59:59");
                    break;
                case 4:$start_time = date("Y-m-d 00:00:00", strtotime("-29 days"));
                    $end_time = date("Y-m-d 23:59:59");
                    break;
                default :
                    return array('stauts' => 'fail', 'msg' => '参数错误');
                    exit();
            }
            if ($role_type == 1) {
                $promoter = Promoter::getPromoterInstance($role_id);
                if (is_object($promoter)) {
                    $data = $promoter->getMyAchievementData($start_time, $end_time);
                }
            } else if ($role_type == 2) {
                $hirer = Hirer::getHirerInstance($role_id);
                if (is_object($hirer)) {
                    $data = $hirer->getMyAchievementData($start_time, $end_time);
                }
            }
            if (empty($data)) {
                echo json_encode(array('status' => 'fail', 'msg' => "数据不存在"));
                exit();
            }
            echo json_encode(array('status' => 'success', 'data' => $data));
            exit();
        } else {
            echo json_encode(array('status' => 'fail', 'msg' => 'bad request'));
        }
    }

    public function chart() {
        $this->layout = 'blank';
        if ($this->is_ajax_request()) {
            $role_type = Filter::int(Req::args('role_type'));
            $user_id = Filter::int(Req::args('user_id'));
            $period = Filter::int(Req::args('period'));
            $district_id = Filter::int(Req::args("district_id"));
            switch ($period) {
                case 1:$start_time = date("Y-m-d 00:00:00");
                    $end_time = date("Y-m-d 23:59:59");
                    break;
                case 2:$start_time = date("Y-m-d 00:00:00", strtotime("-1 days"));
                    $end_time = date("Y-m-d 23:59:59", strtotime("-1 days"));
                    break;
                case 3:$start_time = date("Y-m-d 00:00:00", strtotime("-6 days"));
                    $end_time = date("Y-m-d 23:59:59");
                    break;
                case 4:$start_time = date("Y-m-d 00:00:00", strtotime("-29 days"));
                    $end_time = date("Y-m-d 23:59:59");
                    break;
                default :
                    return array('stauts' => 'fail', 'msg' => '参数错误');
                    exit();
            }
            if ($role_type == 1) {
                $promoter = Promoter::getPromoterInstance($user_id);
                if (is_object($promoter)) {
                    $data = $promoter->getMyAchievementData($start_time, $end_time);
                }
            } else if ($role_type == 2) {
                $hirer = Hirer::getHirerInstance($user_id, $district_id);
                if (is_object($hirer)) {
                    $data = $hirer->getMyAchievementData($start_time, $end_time);
                }
            }
            if (empty($data)) {
                echo json_encode(array('status' => 'fail', 'msg' => "数据不存在"));
                exit();
            }
            echo json_encode(array('status' => 'success', 'data' => $data));
            exit();
        } else {
            $role_type = Filter::int(Req::args('role_type'));
            $user_id = Filter::int(Req::args('user_id'));
            $district_id = Filter::int(Req::args("district_id"));
            $period = 1;
            switch ($period) {
                case 1:$start_time = date("Y-m-d 00:00:00");
                    $end_time = date("Y-m-d 23:59:59");
                    break;
                case 2:$start_time = date("Y-m-d 00:00:00", strtotime("-1 days"));
                    $end_time = date("Y-m-d 23:59:59", strtotime("-1 days"));
                    break;
                case 3:$start_time = date("Y-m-d 00:00:00", strtotime("-6 days"));
                    $end_time = date("Y-m-d 23:59:59");
                    break;
                case 4:$start_time = date("Y-m-d 00:00:00", strtotime("-29 days"));
                    $end_time = date("Y-m-d 23:59:59");
                    break;
                default :
                    return array('stauts' => 'fail', 'msg' => '参数错误');
                    exit();
            }
            $data = array();
            if ($role_type == 1) {
                $promoter = Promoter::getPromoterInstance($user_id);
                if (is_object($promoter)) {
                    $data = $promoter->getMyAchievementData($start_time, $end_time);
                }
            } else if ($role_type == 2) {
                $hirer = Hirer::getHirerInstance($user_id, $district_id);
                if (is_object($hirer)) {
                    $data = $hirer->getMyAchievementData($start_time, $end_time);
                }
            }
            $this->assign('role_type', $role_type);
            $this->assign('user_id', $user_id);
            $this->assign('data', $data);
            $this->redirect();
        }
    }

    public function list_promoter() {
        $model=new Model();
      $data=$model->table('district_shop')->fields('id,owner_id')->where('id>1')->findAll();
      foreach($data as $k=>$v){
    $model->table('invite')->data(array('user_id'=>$v['owner_id'],'invite_user_id'=>1,'from'=>'web','district_id'=>1,'createtime'=>time()))->insert();
       }

        $condition = Req::args("condition");
        $condition_str = Common::str2where($condition);

        if ($condition_str) {
            $this->assign("where", $condition_str);
        } else {
            $this->assign("where", "1=1");
        }
        $this->assign("condition", $condition);
        $this->redirect();
    }

    public function apply_withdraw() {
        $condition = Req::args("condition");
        $condition_str = Common::str2where($condition);
        if ($condition_str) {
            $this->assign("where", $condition_str);
        } else {
            $this->assign("where", "1=1");
        }
        $this->assign("condition", $condition);
        $this->redirect();
    }

    public function apply_join() {
        $condition = Req::args("condition");
        $condition_str = Common::str2where($condition);
        if ($condition_str) {
            $this->assign("where", $condition_str);
        } else {
            $this->assign("where", "1=1");
        }
        $this->assign("condition", $condition);
        $this->redirect();
    }

    public function updateApplyStatus() {
        $id = Filter::sql(Req::args("id"));
        $status = Filter::sql(Req::args("status"));
        $reason = Filter::sql(Req::args("reason"));
        $model = new Model();
        if ($status == -1 || $status == 1) {
            $apply_info = $model->table('district_apply')->where("id=$id and status=0")->find();
            if (empty($apply_info)) {
                echo $json_encode(array('status' => 'fail', 'msg' => "操作失败，申请信息不存在"));
                exit();
            } else {
                if ($status == -1) {
                    if (trim($reason) != "") {//作废理由不能为空
                        $result = $model->query("update tiny_district_apply set status = -1,admin_handle_time='" . date("Y-m-d H:i:s") . "',refuse_reason ='$reason' where id = $id");
                        if ($result) {
                            echo json_encode(array("status" => 'success', 'msg' => '成功'));
                            exit();
                        } else {
                            echo json_encode(array("status" => 'fail', 'msg' => '作废失败，数据库错误'));
                            exit();
                        }
                    } else {
                        echo json_encode(array("status" => 'fail', 'msg' => '理由不能为空'));
                        exit();
                    }
                } else if ($status == 1) {
                    $data['name'] = $apply_info['name'];
                    $data['location'] = $apply_info['location'];
                    $data['asset'] = 1000;
                    $data['founder_id'] = $apply_info['user_id'];
                    $data['owner_id'] = $apply_info['user_id'];
                    $data['create_time'] = date("Y-m-d H:i:s");
                    $data['valid_period'] = date("Y-m-d H:i:s", strtotime("+3 years"));
                    $data['linkman'] = $apply_info['linkman'];
                    $data['link_mobile'] = $apply_info['linkmobile'];
                    $data['valid_income'] = $data['frezze_income'] = $data['settled_income'] = 0.00;
                    $data['status'] = 0;
                    $data['invite_shop_id'] = $apply_info['reference'] == "" ? NULL : $apply_info['reference'];
                    $isOk = $model->table('district_shop')->data($data)->insert();
                    if ($isOk) {
                        $shop_count = $model->table("district_shop")->where("owner_id =" . $apply_info['user_id'])->count();
                        if ($shop_count == 1) {//如果是第一次创建专区，就自动创建推广员，或者将推广员账号绑定到专区下
                            $promoter_info = $model->table("district_promoter")->where("user_id=" . $apply_info['user_id'])->find();
                            if (!$promoter_info) {
                                $insert_data['user_id'] = $apply_info['user_id'];
                                $insert_data['type'] = 3;
                                $insert_data['join_time'] = date("Y-m-d H:i:s");
                                $insert_data['hirer_id'] = $isOk;
                                $insert_data['create_time'] = date('Y-m-d H:i:s');
                                $insert_data['valid_income'] = $insert_data['frezze_income'] = $insert_data['settled_income'] = 0.00;
                                $insert_data['status'] = 0;
                                $model->table("district_promoter")->data($insert_data)->insert();
                            } else {
                                $model->table("district_promoter")->data(array("hirer_id" => $isOk))->where("user_id=" . $apply_info['user_id'])->update();
                            }
                        }
                        $config_all = Config::getInstance();
                        $set = $config_all->get('district_set');
                        if ($apply_info['free'] == 0) {
                            $result = $model->table("customer")->where("user_id=" . $apply_info['user_id'])->data(array("point_coin" => "`point_coin`+" . round($set['join_fee'], 2)))->update();
                            if ($result) {
                                Log::pointcoin_log(round($set['join_fee'], 2), $apply_info['user_id'], "", "经销商入驻赠送", 8);
                                $model->table("customer")->data(array('financial_coin' => "`financial_coin`+" .$set['join_fee'] ))->where("user_id=" . $apply_info['user_id'])->update();
                            }
                            if($data['invite_shop_id'] != ""){
                                //添加积分收益记录
                                $uinfo=$model->table("district_shop")->where("id=" . $data['invite_shop_id'])->find();
                                $uid=$uinfo['owner_id'];
                                if($uid){
                                    $model->table("customer")->data(array("point_coin"=>"`point_coin`+18000","financial_coin"=>"`financial_coin`+18000"))->where("user_id=" . $uid)->update();
                                    Log::pointcoin_log(18000, $uid, "", "经销商邀请经销商收益", 8);
                                }
                            }
                            // if ($data['invite_shop_id'] != "") {
                            //     //获取分配比例
                            //     if (isset($set['percentage2join_fee'])) {
                            //         $rate = round($set['percentage2join_fee'] / 100, 2);
                            //     } else {
                            //         $rate = 0.1;
                            //     }
                            //     if (isset($set['join_fee'])) {
                            //         $fee = round($set['join_fee'], 2);
                            //     } else {
                            //         $fee = 10000;
                            //     }
                            //     $income_amount = $fee*$rate;
                            //     Log::incomeLog($income_amount, 3, $data['invite_shop_id'], $apply_info['id'], 10);
                            // }
                        }
                        $result = $model->table('district_apply')->where("id=$id")->data(array('status' => 1, 'admin_handle_time' => date('Y-m-d H:i:s')))->update();
                        if ($result) {
                            $oauth_info = $model->table("oauth_user")->fields("open_id,open_name")->where("user_id=" . $apply_info['user_id'] . " and oauth_type='wechat'")->find();
                            if (!empty($oauth_info)) {
                                $wechatcfg = $model->table("oauth")->where("class_name='WechatOAuth'")->find();
                                $wechat = new WechatMenu($wechatcfg['app_key'], $wechatcfg['app_secret'], '');
                                $token = $wechat->getAccessToken();
                                $oauth_info['open_name'] = $oauth_info['open_name'] == "" ? "圆梦用户" : $oauth_info['open_name'];
                                $params = array(
                                    'touser' => $oauth_info['open_id'],
                                    'msgtype' => 'text',
                                    "text" => array(
                                        'content' => "亲爱的{$oauth_info['open_name']},恭喜您，申请入驻专区审核通过，正式成为专区经销商，更多权益请<a href=\"https://www.ymlypt.com/district/district\">点击查看>>></a>"
                                    )
                                );
                                $result = Http::curlPost("https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token={$token}", json_encode($params, JSON_UNESCAPED_UNICODE));
                                file_put_contents('wxmsg.txt', json_encode($result), FILE_APPEND);
                            }
                            $NoticeService = new NoticeService();
                            $jpush = $NoticeService->getNotice('jpush');
                            $audience['alias'] = array($apply_info['user_id']);
                            $jpush->setPushData('all', $audience, '恭喜您，申请入驻专区审核通过，正式成为专区经销商', 'district_join_success', '');
                            $result = $jpush->push();
                            file_put_contents('jpush.txt', json_encode($result), FILE_APPEND);
                            echo json_encode(array("status" => 'success', 'msg' => '成功'));
                            exit();
                        } else {
                            echo json_encode(array("status" => 'fail', 'msg' => '数据库更新失败2'));
                            exit();
                        }
                    } else {
                        echo json_encode(array("status" => 'fail', 'msg' => '数据库更新失败1'));
                        exit();
                    }
                }
            }
        } else {
            echo $json_encode(array('status' => 'fail', 'msg' => "操作失败，参数错误"));
            exit();
        }
    }

    public function updateWithdrawStatus() {
        $id = Filter::sql(Req::args("id"));
        $status = Filter::sql(Req::args("status"));
        $reason = Filter::sql(Req::args("reason"));
        $withdraw = new Model("district_withdraw");
        if ($status == 1 || $status == -1) {
            $withdraw_info = $withdraw->where("id = $id and status= 0")->find();
            if (empty($withdraw_info)) {
                $info = array('status' => 'fail', 'msg' => "操作失败，数据不存在");
            } else {
                if ($status == -1) {//如果是作废提现请求时
                    if (trim($reason) != "") {//作废理由不能为空
                        $result = $withdraw->query("update tiny_district_withdraw set status = -1,admin_handle_time='" . date("Y-m-d H:i:s") . "',admin_remark ='$reason' where id = $id");
                        if ($result) {
                            echo json_encode(array("status" => 'success', 'msg' => '成功'));
                            exit();
                        } else {
                            echo json_encode(array("status" => 'fail', 'msg' => '作废失败，数据库错误'));
                            exit();
                        }
                    } else {
                        echo json_encode(array("status" => 'fail', 'msg' => '理由不能为空'));
                        exit();
                    }
                } else if ($status == 1) {
                    if ($withdraw_info['withdraw_type'] == 2) {//提现到银行卡
                        $config = Config::getInstance();
                        $district_set = $config->get("district_set");
                        $ChinapayDf = new ChinapayDf();
                        $obj = unserialize($withdraw_info['card_info']);
                        if (!is_array($obj)) {
                            echo json_encode(array("status" => 'fail', 'msg' => '银行卡信息错误'));
                            exit();
                        }
                        //如果提现申请者为普通用户或者推广员
                        if ($withdraw_info['role_type'] == 1 || $withdraw_info['role_type'] == 2) {
                            //查询可用收益，防止溢出
                            $promoter = $withdraw->table("customer")->where('user_id=' . $withdraw_info['role_id'])->find();
                            if (empty($promoter)) {
                                echo json_encode(array("status" => 'fail', 'msg' => '推广者不存在'));
                                exit();
                            }
                            if ($promoter['valid_income'] < $withdraw_info['withdraw_amount']) {
                                echo json_encode(array("status" => 'fail', 'msg' => '提现金额超出账户可用余额'));
                                exit();
                            } else {
                                $params["merDate"] = date("Ymd");
                                $params["merSeqId"] = date("YmdHis") . rand(10, 99);
                                $params["cardNo"] = $obj['card_number'];
                                $params["usrName"] = $obj['bank_account_name'];
                                $params["openBank"] = $obj['bank_name'];
                                $params["prov"] = $obj['province'];
                                $params["city"] = $obj['city'];
                                $params["transAmt"] = round($withdraw_info['withdraw_amount'] * (100 - $district_set['withdraw_fee_rate'])); //转化成分，并减去手续费
                                if ($params["transAmt"] <= 0) {
                                    exit(json_encode(array('status' => 'fail', 'msg' => '代付金额小于或等于0')));
                                }
                                $params['purpose'] = "专区用户提现";
                                $result = $ChinapayDf->DfPay($params);
                                if ($result) {
                                    $isOk = Log::incomeLog($withdraw_info['withdraw_amount'], $withdraw_info['role_type'], $withdraw_info['role_id'], $withdraw_info['id'], 11, '提取收益到银行卡');
                                    if ($isOk) {
                                        $result = $withdraw->table("district_withdraw")->data(array("status" => 1, "admin_handle_time" => date("Y-m-d H:i:s")))->where("id = $id")->update();
                                        if ($result) {
                                            echo json_encode(array("status" => 'success', 'msg' => '成功'));
                                            exit();
                                        }
                                    }
                                } else {
                                    echo json_encode(array("status" => 'fail', 'msg' => '代付失败'));
                                    exit();
                                }
                            }
                        } else if ($withdraw_info['role_type'] == 3) {
                            //查询可用收益，防止溢出
                            $hirer = $withdraw->table("district_shop")->where('id=' . $withdraw_info['role_id'])->find();
                            if (empty($hirer)) {
                                echo json_encode(array("status" => 'fail', 'msg' => '商户不存在'));
                                exit();
                            }
                            if ($hirer['valid_income'] < $withdraw_info['withdraw_amount']) {
                                echo json_encode(array("status" => 'fail', 'msg' => '提现金额超出账户可用余额'));
                                exit();
                            } else {
                                $params["merDate"] = date("Ymd");
                                $params["merSeqId"] = date("YmdHis") . rand(10, 99);
                                $params["cardNo"] = $obj['card_number'];
                                $params["usrName"] = $obj['bank_account_name'];
                                $params["openBank"] = $obj['bank_name'];
                                $params["prov"] = $obj['province'];
                                $params["city"] = $obj['city'];
                                $params["transAmt"] = round($withdraw_info['withdraw_amount'] * (100 - $district_set['withdraw_fee_rate'])); //转化成分，并减去手续费
                                if ($params["transAmt"] <= 0) {
                                    exit(json_encode(array('status' => 'fail', 'msg' => '代付金额小于或等于0')));
                                }
                                $params['purpose'] = "专区用户提现";
                                $result = $ChinapayDf->DfPay($params);

                                if ($result) {
                                    $isOk = Log::incomeLog($withdraw_info['withdraw_amount'], $withdraw_info['role_type'], $withdraw_info['role_id'], $withdraw_info['id'], 11, '提取收益到银行卡');
                                    if ($isOk) {
                                        $result = $withdraw->table("district_withdraw")->data(array("status" => 1, "admin_handle_time" => date("Y-m-d H:i:s")))->where("id = $id")->update();
                                        if ($result) {
                                            echo json_encode(array("status" => 'success', 'msg' => '成功'));
                                            exit();
                                        }
                                    }
                                } else {
                                    echo json_encode(array("status" => 'fail', 'msg' => '代付失败'));
                                    exit();
                                }
                            }
                        }
                    } else if ($withdraw_info['withdraw_type'] == 1) {//提现到余额
                        if ($withdraw_info['role_type'] == 1 || $withdraw_info['role_type'] == 2) {
                            //查询可用收益，防止溢出
                            $promoter = $withdraw->table("customer")->where('user_id=' . $withdraw_info['role_id'])->find();
                            if (empty($promoter)) {
                                echo json_encode(array("status" => 'fail', 'msg' => '推广者不存在'));
                                exit();
                            }
                            if ($promoter['valid_income'] < $withdraw_info['withdraw_amount']) {
                                echo json_encode(array("status" => 'fail', 'msg' => '提现金额超出账户可用余额'));
                                exit();
                            } else {
                                $isOk1 = Log::incomeLog($withdraw_info['withdraw_amount'], $withdraw_info['role_type'], $withdraw_info['role_id'], $withdraw_info['id'], 11, '提取收益到账户余额');
                                if ($isOk1) {
                                    $isOk2 = $withdraw->query("update tiny_customer set balance = balance + {$withdraw_info['withdraw_amount']} where user_id =" . $promoter['user_id']);
                                    Log::balance($withdraw_info['withdraw_amount'], $promoter['user_id'], $withdraw_info['withdraw_no'], '推广收益', 6);
                                    $result = $withdraw->table("district_withdraw")->data(array("status" => 1, "admin_handle_time" => date("Y-m-d H:i:s")))->where("id = $id")->update();
                                    if ($result) {
                                        echo json_encode(array("status" => 'success', 'msg' => '成功'));
                                        exit();
                                    }
                                }else{
                                     echo json_encode(array("status" => 'fail', 'msg' => '收益日志记录失败'));
                                     exit();
                                }
                            }
                        } else if ($withdraw_info['role_type'] == 3) {
                            //查询可用收益，防止溢出
                            $hirer = $withdraw->table("district_shop")->where('id=' . $withdraw_info['role_id'])->find();
                            if (empty($hirer)) {
                                echo json_encode(array("status" => 'fail', 'msg' => '商户不存在'));
                                exit();
                            }
                            if ($hirer['valid_income'] < $withdraw_info['withdraw_amount']) {
                                echo json_encode(array("status" => 'fail', 'msg' => '提现金额超出账户可用余额'));
                                exit();
                            } else {
                                $isOk1 = Log::incomeLog($withdraw_info['withdraw_amount'], $withdraw_info['role_type'], $withdraw_info['role_id'], $withdraw_info['id'], 11, '提取收益到账户余额');
                                $isOk2 = $withdraw->query("update tiny_customer set balance = balance + {$withdraw_info['withdraw_amount']} where user_id =" . $hirer['owner_id']);

                                if ($isOk1 && $isOk2) {
                                    Log::balance($withdraw_info['withdraw_amount'], $hirer['owner_id'], $withdraw_info['withdraw_no'], '推广收益', 6);
                                    $result = $withdraw->table("district_withdraw")->data(array("status" => 1, "admin_handle_time" => date("Y-m-d H:i:s")))->where("id = $id")->update();
                                    if ($result) {
                                        echo json_encode(array("status" => 'success', 'msg' => '成功'));
                                        exit();
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } else {
            $info = array('status' => 'fail', 'msg' => "操作失败，参数错误");
        }
        echo JSON::encode($info);
        exit();
    }

    public function set() {
        $group = "district_set";
        $config = Config::getInstance();
        if (Req::args('submit') != null) {
            if (Req::args('beneficiary_one') >= Req::args('beneficiary_two')) {
                $this->assign('error', '普通用户提成不能大于推广员！');
            } else {
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
                    Log::op($this->manager['id'], "修改专区配置", "管理员[" . $this->manager['name'] . "]:修改了佣金配置 ");
                }
            }
        }
        $this->assign('data', $config->get($group));
        $this->redirect();
    }

    public function payset() {
        $model = new Model();
        $params = $model->table("mdpay_params")->where("id =1")->find();
        $this->assign('params', $params);
        $this->redirect();
    }

    public function payset_save() {
        $data = Req::args();
        $model = new Model();
        $result = $model->table("mdpay_params")->data($data)->where("id=1")->update();
        if ($result) {
            echo json_encode(array('status' => 'success', 'msg' => '成功'));
            exit();
        } else {
            echo json_encode(array('status' => 'fail', 'msg' => '数据库更新失败'));
            exit();
        }
    }

    /*
     * 秒到支付报件
     */

    public function quote() {
        $data = Req::args();
        unset($data['con']);
        unset($data['act']);
        $model = new Model();
        $params = $model->table("mdpay_params")->where("id =1")->find();
        if (!$params) {
            exit(json_encode(array("status" => 'fail', "msg" => 'pid和pkey不存在，请先设置参数并提交')));
        }
        $data['dpPid'] = $params['pid'];
        $data['pkey'] = $params['pkey'];
        $input = new MdPayQuoteData();
        $input->SetAccountName($data['accountname']);
        $input->SetAccountNo($data['accountno']);
        $input->SetBankName($data['bankname']);
        $input->SetContact($data['contact']);
        $input->SetFeerate($data['feerate']);
        $input->SetIdentitycard($data['identitycard']);
        // $input ->SetMerchId("99800001066");
        $input->SetMerchAddr($data['merchaddr']);
        $input->SetMerchName($data['merchname']);
        $input->SetRatemodel($data['ratemodel']);
        $input->SetTelephone($data['telephone']);
        $input->SetDpPid($data['dpPid']);
        $input->SetPkey($data['pkey']);

        list($t1, $t2) = explode(' ', microtime());
        $timestamp = round((floatval($t1) + floatval($t2)) * 1000);
        $input->SetTimestamp("$timestamp");
        $input->SetSecret();
        $result = MdPayApi::quote($input);
        if ($result['code'] == '0000') {
            $model->table("mdpay_params")->data(array("mid" => $result['rightMerchId'], "quote" => serialize($data)))->where("id =1")->update();
            exit(json_encode(array("status" => 'success', "msg" => '成功')));
        } else {
            exit(json_encode(array("status" => 'fail', "msg" => $result['codedesc'])));
        }
    }

    /*
     * 添加官方推广员
     */

    public function addPromoter() {
        if ($this->is_ajax_request()) {
            $user_id = Req::args("user_id");
            $hirer_id = Req::args("hirer_id");
            $pointcoin = Req::args("pointcoin")!=null?Req::args("pointcoin"):0;
            $financialcoin = Req::args("financialcoin")!=null?Req::args("financialcoin"):0;
            $ds_promoter = Req::args("ds_promoter");
            if (!$user_id) {
                exit(json_encode(array("status" => 'fail', 'msg' => "参数错误")));
            }
            // $promoter = Promoter::getPromoterInstance($user_id);
            $model = new Model();
            //赠送积分和分红点
            if($pointcoin>0 || $financialcoin>0){
               $model->table('customer')->where('user_id='.$user_id)->data(array('point_coin'=>"`point_coin`+({$pointcoin})","financial_coin"=>"`financial_coin`+({$financialcoin})"))->update();
               Log::pointcoin_log($pointcoin,$user_id,"","代理商入驻赠送",5);
            }
            $promoter=$model->table('district_promoter')->where('user_id='.$user_id)->find();
            // var_dump($promoter);die;
            if ($promoter) {
                exit(json_encode(array("status" => 'fail', 'msg' => "该用户已经有雇佣关系了")));
            } else {
                if(isset($hirer_id) && $hirer_id!='') {   //经销商推代理商
                    $isset = $model->table("district_shop")->where("id=$hirer_id")->find();
                    if (!$isset) {
                        exit(json_encode(array("status" => 'fail', 'msg' => "经销商不存在")));
                    }
                    $data['user_id'] = $user_id;
                    $data['type'] = 4;
                    $data['join_time'] = date("Y-m-d H:i:s");
                    $data['hirer_id'] = $hirer_id;
                    $data['create_time'] = date('Y-m-d H:i:s');
                    $data['valid_income'] = $data['frezze_income'] = $data['settled_income'] = 0.00;
                    $data['status'] = 0;
                    $result = $model->table("district_promoter")->data($data)->insert();
                    if ($result) {
                        $logic = DistrictLogic::getInstance();
                        if (strip_tags(Url::getHost(), 'ymlypt')) {
                            $this->sendMessage($user_id, "恭喜您，成为经销商【{$isset['name']}】的官方代理商，快来看看吧>>>", "https://www.ymlypt.com/ucenter/index?first=1", "promoter_join_success");
                        }
                        exit(json_encode(array("status" => 'success', 'msg' => "成功")));
                    } else {
                        exit(json_encode(array("status" => 'fail', 'msg' => "数据库错误")));
                    }
                }elseif(isset($ds_promoter) && $ds_promoter!=''){     //代理商代理商
                    $isset = $model->table("district_promoter")->where("user_id=$ds_promoter")->find();
                    if (!$isset) {
                        exit(json_encode(array("status" => 'fail', 'msg' => "代理商不存在")));
                    }
                    //添加邀请关系
                    $model->table('invite')->data(array('user_id'=>$ds_promoter,'invite_user_id'=>$user_id,'from'=>'web','district_id'=>1,'createtime'=>time()))->insert();
                    
                    $data['user_id'] = $user_id;
                    $data['type'] = 2;
                    $data['join_time'] = date("Y-m-d H:i:s");
                    $data['invitor_id'] = $ds_promoter;
                    $data['create_time'] = date('Y-m-d H:i:s');
                    $data['valid_income'] = $data['frezze_income'] = $data['settled_income'] = 0.00;
                    $data['status'] = 0;
                    $result = $model->table("district_promoter")->data($data)->insert();
                    if ($result) {
                    $logic = DistrictLogic::getInstance();
                    $customer=$model->table('customer as c')->join('left join user as u on c.user_id=u.id')->where('c.user_id='.$ds_promoter)->fields('c.real_name,u.nickname')->find();
                    $name=$customer['real_name']!=''?$customer['real_name']:$customer['nickname'];
                    if (strip_tags(Url::getHost(), 'ymlypt')) {
                        $this->sendMessage($user_id, "恭喜您，成为代理商【{$name}】下的官方代理商，快来看看吧>>>", "https://www.ymlypt.com/ucenter/index?first=1", "promoter_join_success");
                    }
                    exit(json_encode(array("status" => 'success', 'msg' => "成功")));
                } else {
                    exit(json_encode(array("status" => 'fail', 'msg' => "数据库错误")));
                }
              }
            }
            
        }
    }

    public function addPromoters() {
        if ($this->is_ajax_request()) {
            $user_id = Req::args("user_id");
            $hirer_id = Req::args("hirer_id");
            $pointcoin = Req::args("pointcoin")!=null?Req::args("pointcoin"):0;
            $financialcoin = Req::args("financialcoin")!=null?Req::args("financialcoin"):0;
            $district_name = Req::args("district_name");
            $linkman = Req::args("linkman");
            $link_mobile = Req::args("link_mobile");
            if (!$user_id || !$hirer_id) {
                exit(json_encode(array("status" => 'fail', 'msg' => "参数错误")));
            }
            // $promoter = Promoter::getPromoterInstance($user_id);
            $model = new Model();
            //赠送积分和分红点
            if($pointcoin>0 && $financialcoin>0){
                $model->table('customer')->where('user_id='.$user_id)->data(array('point_coin'=>"`point_coin`+({$pointcoin})","financial_coin"=>"`financial_coin`+({$financialcoin})"))->update();
                Log::pointcoin_log($pointcoin,$user_id,"","经销商入驻赠送",8);
            }
            $promoter=$model->table('district_shop')->where('owner_id='.$user_id)->find();
            
            if ($promoter) {
                exit(json_encode(array("status" => 'fail', 'msg' => "该用户已经有雇佣关系了")));
            } else {
                
                $isset = $model->table("district_shop")->where("id=$hirer_id")->find();
                if (!$isset) {
                    exit(json_encode(array("status" => 'fail', 'msg' => "经销商不存在")));
                }
                $customer=$model->table('customer as c')->join('left join user as u on c.user_id=u.id')->where('c.user_id='.$user_id)->fields('c.real_name,c.mobile,c.city,u.nickname')->find();
                $data['name'] = $district_name;
                $data['asset'] = 1000;
                $data['founder_id'] = $user_id;
                $data['owner_id'] = $user_id;
                $data['invite_shop_id'] = $hirer_id;
                $data['create_time'] = date('Y-m-d H:i:s');
                $data['valid_period'] = date("Y-m-d H:i:s", strtotime("+3 years"));
                $data['valid_income'] = $data['frezze_income'] = $data['settled_income'] = 0.00;
                $data['status'] = 0;
                $data['linkman'] = $linkman;
                $data['link_mobile'] = $link_mobile;
                $data['location'] = $customer['city'];
                $result = $model->table("district_shop")->data($data)->insert();
                
                $owner=$model->table('district_shop')->fields('id,owner_id')->where('id='.$hirer_id)->find();
                $model->table('invite')->data(array('user_id'=>$owner['owner_id'],'invite_user_id'=>$user_id,'from'=>'web','district_id'=>$owner['id'],'createtime'=>time()))->insert();
                $datas['user_id'] = $user_id;
                $datas['type'] = 4;
                $datas['join_time'] = date("Y-m-d H:i:s");
                $datas['hirer_id'] = $result;
                $datas['create_time'] = date('Y-m-d H:i:s');
                $datas['valid_income'] = $data['frezze_income'] = $data['settled_income'] = 0.00;
                $datas['status'] = 0;
                $res = $model->table("district_promoter")->data($datas)->insert();
                if ($result && $res) {
                    $logic = DistrictLogic::getInstance();
                    if (strip_tags(Url::getHost(), 'ymlypt')) {
                        $this->sendMessage($user_id, "恭喜您，成为官方经销商，快来看看吧>>>", "https://www.ymlypt.com/ucenter/index?first=1", "promoter_join_success");
                    }
                    exit(json_encode(array("status" => 'success', 'msg' => "成功")));
                } else {
                    exit(json_encode(array("status" => 'fail', 'msg' => "数据库错误")));
                }
            }
            
        }
    }

    /*
     * 获取用户列表
     */

    public function radio_customer_select() {
        $this->layout = "blank";
        $s_type = Req::args("s_type");
        $s_content = Req::args("s_content");
        $hirer_id = Req::args("hirer_id");
        $where = "1=1";
        if ($s_content && $s_content != '') {

            if ($s_type == 1) {
                $where = "mobile = $s_content";
            } else if ($s_type == 2) {
                $where = "real_name like '%{$s_content}%' ";
            } else if ($s_type == 0) {
                $where = "user_id = $s_content";
            }
        }
        $this->assign("s_type", $s_type);
        $this->assign("s_content", $s_content);
        $this->assign("hirer_id", $hirer_id);

        $this->assign("where", $where);
        $this->redirect();
    }

    public function radio_customer_selects() {
        $this->layout = "blank";
        $s_type = Req::args("s_type");
        $s_content = Req::args("s_content");
        $hirer_id = Req::args("hirer_id");
        $where = "1=1";
        if ($s_content && $s_content != '') {

            if ($s_type == 1) {
                $where = "mobile = $s_content";
            } else if ($s_type == 2) {
                $where = "real_name like '%{$s_content}%' ";
            } else if ($s_type == 0) {
                $where = "user_id = $s_content";
            }
        }
        $this->assign("s_type", $s_type);
        $this->assign("s_content", $s_content);
        $this->assign("hirer_id", $hirer_id);

        $this->assign("where", $where);
        $this->redirect();
    }

    public function sendMsg() {
        $user_id = Filter::int(Req::args("user_id"));
        $content = Filter::str(Req::args("content"));
        $url = Filter::str(Req::args("url"));
        $model = new Model();
        $oauth_info = $model->table("oauth_user")->fields("open_id,open_name")->where("user_id=" . $user_id . " and oauth_type='wechat'")->find();
        if (!empty($oauth_info)) {
            $wechatcfg = $model->table("oauth")->where("class_name='WechatOAuth'")->find();
            $wechat = new WechatMenu($wechatcfg['app_key'], $wechatcfg['app_secret'], '');
            $token = $wechat->getAccessToken();
            $params = array(
                'touser' => $oauth_info['open_id'],
                'msgtype' => 'text',
            );
            if ($url) {
                $params['text'] = array(
                    'content' => "<a href=\"$url\">$content</a>"
                );
            } else {
                $params['text'] = array(
                    'content' => "$content"
                );
            }
            $result = Http::curlPost("https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=" . $token, json_encode($params, JSON_UNESCAPED_UNICODE));
            $result = json_decode($result, true);
            if ($result['errcode'] == 0) {
                exit(json_encode(array("status" => 'success', 'msg' => "发送成功")));
            } else {
                exit(json_encode(array("status" => 'fail', 'msg' => "发送失败：" + $result['errmsg'])));
            }
        } else {
            exit(json_encode(array("status" => 'fail', 'msg' => "用户微信信息不存在")));
        }
    }

    /*
     * 发送微信通知
     * @params 
     */
    public function sendMessage($user_id,$content,$url,$type){
        $need_weixin = true;
        $need_jpush = true;
        if($type=="promoter_join_success"){
            if(!$this->client_type||$this->client_type=='unknow'){
                return false;
            }
            if($this->client_type=='ios'||$this->client_type=='android'){
                $need_weixin = false;
            }else if($this->client_type=='weixin'){
                $need_jpush = false;
            }else{
                return false;
            }
        }
        if($need_weixin){
            if($this->token==NULL){
                $wechatcfg = $this->model->table("oauth")->where("class_name='WechatOAuth'")->find();
                $wechat = new WechatMenu($wechatcfg['app_key'], $wechatcfg['app_secret'], '');
                $this->token = $wechat->getAccessToken();
            }
            $oauth_info = $this->model->table("oauth_user")->fields("open_id,open_name")->where("user_id=".$user_id." and oauth_type='wechat'")->find();
            if(!empty($oauth_info)){
                $oauth_info['open_name'] = $oauth_info['open_name']==""?"圆梦用户":$oauth_info['open_name'];
                $params = array(
                    'touser'=>$oauth_info['open_id'],
                    'msgtype'=>'text',
                    "text"=>array(
                        'content'=>"<a href=\"$url\">亲爱的{$oauth_info['open_name']},$content</a>"
                    )
                );
                Http::curlPost("https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=".$this->token, json_encode($params,JSON_UNESCAPED_UNICODE));
            }
        }
        if($need_jpush){
            if(!$this->jpush){
                $NoticeService = new NoticeService();
                $this->jpush = $NoticeService->getNotice('jpush');
            }
            $audience['alias']=array($user_id);
            $this->jpush->setPushData('all', $audience, $content, $type, "");
            $this->jpush->push();
        }
    }

}
