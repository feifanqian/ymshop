<?php

class DistrictadminController extends Controller
{

    public $layout = 'admin';
    private $top = null;
    private $manager = null;
    public $needRightActions = array('*' => true);

    public function init()
    {
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

    public function noRight()
    {
        $this->layout = '';
        $this->redirect("admin/noright");
    }

    public function record_sale()
    {
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

    public function record_income()
    {
        $this->model = new Model();
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

    public function list_hirer()
    {
        $this->model = new Model();
        $page = intval(Req::args("p"));
        $page_size = 10;
        $condition = Req::args("condition");
        $condition_str = Common::str2where($condition);

        if ($condition_str) {
            $where = 'ds.status=1 and ' . $condition_str;
            $this->assign("where", $condition_str);
        } else {
            $where = "ds.status=1";
            $this->assign("where", "status=1");
        }
        $this->assign("condition", $condition);
        $list = $this->model->table('district_shop as ds')->join('left join customer as c on ds.owner_id=c.user_id left join district_shop as d on ds.invite_shop_id=d.id')->fields("ds.*,c.real_name,d.name as invite_shop_name")->where($where)->order("ds.id desc")->findPage($page, $page_size);
        $this->assign("list", $list);
        $this->redirect();
    }

    public function view_achievement()
    {
        if ($this->is_ajax_request()) {
            $role_type = Filter::int(Req::args('role_type'));
            $role_id = Filter::int(Req::args('role_id'));
            $period = Filter::int(Req::args('period'));
            switch ($period) {
                case 1:
                    $start_time = date("Y-m-d 00:00:00");
                    $end_time = date("Y-m-d 23:59:59");
                    break;
                case 2:
                    $start_time = date("Y-m-d 00:00:00", strtotime("-1 days"));
                    $end_time = date("Y-m-d 23:59:59", strtotime("-1 days"));
                    break;
                case 3:
                    $start_time = date("Y-m-d 00:00:00", strtotime("-6 days"));
                    $end_time = date("Y-m-d 23:59:59");
                    break;
                case 4:
                    $start_time = date("Y-m-d 00:00:00", strtotime("-29 days"));
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

    public function chart()
    {
        $this->layout = 'blank';
        if ($this->is_ajax_request()) {
            $role_type = Filter::int(Req::args('role_type'));
            $user_id = Filter::int(Req::args('user_id'));
            $period = Filter::int(Req::args('period'));
            $district_id = Filter::int(Req::args("district_id"));
            switch ($period) {
                case 1:
                    $start_time = date("Y-m-d 00:00:00");
                    $end_time = date("Y-m-d 23:59:59");
                    break;
                case 2:
                    $start_time = date("Y-m-d 00:00:00", strtotime("-1 days"));
                    $end_time = date("Y-m-d 23:59:59", strtotime("-1 days"));
                    break;
                case 3:
                    $start_time = date("Y-m-d 00:00:00", strtotime("-6 days"));
                    $end_time = date("Y-m-d 23:59:59");
                    break;
                case 4:
                    $start_time = date("Y-m-d 00:00:00", strtotime("-29 days"));
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
                case 1:
                    $start_time = date("Y-m-d 00:00:00");
                    $end_time = date("Y-m-d 23:59:59");
                    break;
                case 2:
                    $start_time = date("Y-m-d 00:00:00", strtotime("-1 days"));
                    $end_time = date("Y-m-d 23:59:59", strtotime("-1 days"));
                    break;
                case 3:
                    $start_time = date("Y-m-d 00:00:00", strtotime("-6 days"));
                    $end_time = date("Y-m-d 23:59:59");
                    break;
                case 4:
                    $start_time = date("Y-m-d 00:00:00", strtotime("-29 days"));
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

    public function list_promoter()
    {

        $condition = Req::args("condition");
        $condition_str = Common::str2where($condition);

        if ($condition_str) {
            $where = 'dp.status=1 and ' . $condition_str;
            $this->assign("where", $where);
        } else {
            $this->assign("where", "dp.status=1");
        }
        $this->assign("condition", $condition);
        $this->redirect();
    }

    public function apply_withdraw()
    {
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

    public function apply_join()
    {
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

    public function qrcode_join()
    {
        $condition = Req::args("condition");
        $condition_str = Common::str2where($condition);
        if ($condition_str) {
            $this->assign("where", $condition_str);
        } else {
            // $this->assign("where", "da.unique_code=0");
            $this->assign("where", "da.create_time>'2017-11-2'");
        }
        $this->assign("condition", $condition);
        $this->redirect();
    }

    public function updateApplyStatus()
    {
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
                                // $model->table("customer")->data(array('financial_coin' => "`financial_coin`+" .$set['join_fee'] ))->where("user_id=" . $apply_info['user_id'])->update();
                            }
                            if ($data['invite_shop_id'] != "") {
                                //添加积分收益记录
                                $uinfo = $model->table("district_shop")->where("id=" . $data['invite_shop_id'])->find();
                                $uid = $uinfo['owner_id'];
                                if ($uid) {
                                    $model->table("customer")->data(array("point_coin" => "`point_coin`+18000"))->where("user_id=" . $uid)->update();
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

    public function updateQrcodeStatus()
    {
        $id = Filter::sql(Req::args("id"));
        $status = Filter::sql(Req::args("status"));
        $model = new Model();
        $ret = $model->table('district_promoter')->where('id=' . $id)->data(array('unique_code' => $status, 'join_time' => date('Y-m-d H:i:s')))->update();
        if ($ret) {
            echo json_encode(array("status" => 'success', 'msg' => '成功'));
            exit();
        } else {
            echo json_encode(array("status" => 'fail', 'msg' => '数据库更新失败'));
            exit();
        }
    }

    public function updateWithdrawStatus()
    {
        $id = Filter::sql(Req::args("id"));
        $status = Filter::sql(Req::args("status"));
        $reason = Filter::sql(Req::args("reason"));
        $withdraw = new Model("district_withdraw");
        $model = new Model();
        if ($status == 1 || $status == -1) {
            $withdraw_info = $withdraw->where("id = $id and status= 0")->find();
            if (empty($withdraw_info)) {
                $info = array('status' => 'fail', 'msg' => "操作失败，数据不存在");
            } else {
                if ($status == -1) {//如果是作废提现请求时
                    if (trim($reason) != "") {//作废理由不能为空
                        $result = $withdraw->query("update tiny_district_withdraw set status = -1,admin_handle_time='" . date("Y-m-d H:i:s") . "',admin_remark ='$reason' where id = $id");

                        if ($result) {
                            if ($withdraw_info['role_type'] == 1 || $withdraw_info['role_type'] == 2) {
                                $customer = $model->table('customer')->fields("valid_income,frezze_income,settled_income")->where('user_id=' . $withdraw_info['role_id'])->find();
                                $model->table('customer')->data(array('valid_income' => "`valid_income`+({$withdraw_info['withdraw_amount']})"))->where('user_id=' . $withdraw_info['role_id'])->update();
                            } else {
                                $customer = $model->table('district_shop')->fields("valid_income,frezze_income,settled_income")->where('id=' . $withdraw_info['role_id'])->find();
                                $model->table('district_shop')->data(array('valid_income' => "`valid_income`+({$withdraw_info['withdraw_amount']})"))->where('id=' . $withdraw_info['role_id'])->update();
                            }

                            $data['role_id'] = $withdraw_info['role_id'];
                            $data['role_type'] = $withdraw_info['role_type'];
                            $data['type'] = 12;
                            $data['record_id'] = $withdraw_info['id'];
                            $data['valid_income_change'] = $withdraw_info['withdraw_amount'];
                            $data['frezze_income_change'] = 0;
                            $data['settled_income_change'] = 0;
                            $data['current_valid_income'] = $customer['valid_income'] + $withdraw_info['withdraw_amount'];
                            $data['current_frezze_income'] = $customer['frezze_income'];
                            $data['current_settled_income'] = $customer['settled_income'];
                            $data['date'] = date("Y-m-d H:i:s");
                            $data['note'] = "提现拒绝收益撤回";
                            $model->table("promote_income_log")->data($data)->insert();
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
                        // $ChinapayDf = new ChinapayDf();
                        $ChinapayDf = new AllinpayDf();
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
                            $params["withdraw_no"] = $withdraw_info['withdraw_no'];
                            // $result = $ChinapayDf->DfPay($params);
                            $result = $ChinapayDf->DFAllinpay($params); //使用通联代付接口
                            if ($result) {
                                // $isOk = Log::incomeLog($withdraw_info['withdraw_amount'], $withdraw_info['role_type'], $withdraw_info['role_id'], $withdraw_info['id'], 11, '提取收益到银行卡');
                                $model->table('customer')->data(array('settled_income' => "`settled_income`+({$withdraw_info['withdraw_amount']})"))->where('user_id=' . $withdraw_info['role_id'])->update();
                                $result = $withdraw->table("district_withdraw")->data(array("status" => 1, "admin_handle_time" => date("Y-m-d H:i:s")))->where("id = $id")->update();
                                if ($result) {

                                    echo json_encode(array("status" => 'success', 'msg' => '成功'));
                                    exit();
                                }

                            } else {
                                // $customer = $model->table('customer')->fields("valid_income,frezze_income,settled_income")->where('user_id='.$withdraw_info['role_id'])->find();
                                // $model->table('customer')->data(array('valid_income'=>"`valid_income`+({$withdraw_info['withdraw_amount']})"))->where('user_id='.$withdraw_info['role_id'])->update();
                                // $data['role_id']=$withdraw_info['role_id'];
                                // $data['role_type']=$withdraw_info['role_type'];
                                // $data['type']=12;
                                // $data['record_id']=$withdraw_info['id'];
                                // $data['current_valid_income']=$customer['valid_income']+$withdraw_info['withdraw_amount'];
                                // $data['current_frezze_income']=$customer['frezze_income'];
                                // $data['current_settled_income']=$customer['settled_income']-$withdraw_info['withdraw_amount'];
                                // $data['date']=date("Y-m-d H:i:s");
                                // $data['note']="提现失败收益撤回";
                                // $model->table("promote_income_log")->data($data)->insert();
                                echo json_encode(array("status" => 'fail', 'msg' => '代付失败'));
                                exit();
                            }

                        } else if ($withdraw_info['role_type'] == 3) {
                            //查询可用收益，防止溢出
                            $hirer = $withdraw->table("district_shop")->where('id=' . $withdraw_info['role_id'])->find();
                            if (empty($hirer)) {
                                echo json_encode(array("status" => 'fail', 'msg' => '商户不存在'));
                                exit();
                            }

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
                            $params["withdraw_no"] = $withdraw_info['withdraw_no'];
                            // $result = $ChinapayDf->DfPay($params);
                            $result = $ChinapayDf->DFAllinpay($params); //使用通联代付接口
                            if ($result) {
                                // $isOk = Log::incomeLog($withdraw_info['withdraw_amount'], $withdraw_info['role_type'], $withdraw_info['role_id'], $withdraw_info['id'], 11, '提取收益到银行卡');
                                $model->table('district_shop')->data(array('settled_income' => "`settled_income`+({$withdraw_info['withdraw_amount']})"))->where('id=' . $withdraw_info['role_id'])->update();
                                $result = $withdraw->table("district_withdraw")->data(array("status" => 1, "admin_handle_time" => date("Y-m-d H:i:s")))->where("id = $id")->update();
                                if ($result) {
                                    echo json_encode(array("status" => 'success', 'msg' => '成功'));
                                    exit();
                                }

                            } else {
                                // $customer = $model->table('district_shop')->fields("valid_income,frezze_income,settled_income")->where('id='.$withdraw_info['role_id'])->find();
                                // $model->table('district_shop')->data(array('valid_income'=>"`valid_income`+({$withdraw_info['withdraw_amount']})"))->where('id='.$withdraw_info['role_id'])->update();
                                // $data['role_id']=$withdraw_info['role_id'];
                                // $data['role_type']=$withdraw_info['role_type'];
                                // $data['type']=12;
                                // $data['record_id']=$withdraw_info['id'];
                                // $data['current_valid_income']=$customer['valid_income']+$withdraw_info['withdraw_amount'];
                                // $data['current_frezze_income']=$customer['frezze_income'];
                                // $data['current_settled_income']=$customer['settled_income']-$withdraw_info['withdraw_amount'];
                                // $data['date']=date("Y-m-d H:i:s");
                                // $data['note']="提现失败收益撤回";
                                // $model->table("promote_income_log")->data($data)->insert();
                                echo json_encode(array("status" => 'fail', 'msg' => '代付失败'));
                                exit();
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

                            // $isOk1 = Log::incomeLog($withdraw_info['withdraw_amount'], $withdraw_info['role_type'], $withdraw_info['role_id'], $withdraw_info['id'], 11, '提取收益到账户余额');
                            $ret = $model->table('customer')->data(array('settled_income' => "`settled_income`+({$withdraw_info['withdraw_amount']})"))->where('user_id=' . $withdraw_info['role_id'])->update();
                            $isOk2 = $withdraw->query("update tiny_customer set balance = balance + {$withdraw_info['withdraw_amount']} where user_id =" . $promoter['user_id']);
                            Log::balance($withdraw_info['withdraw_amount'], $promoter['user_id'], $withdraw_info['withdraw_no'], '推广收益提现到余额', 6);
                            $result = $withdraw->table("district_withdraw")->data(array("status" => 1, "admin_handle_time" => date("Y-m-d H:i:s")))->where("id = $id")->update();
                            if ($result && $ret) {
                                echo json_encode(array("status" => 'success', 'msg' => '成功'));
                                exit();
                            }


                        } else if ($withdraw_info['role_type'] == 3) {
                            //查询可用收益，防止溢出
                            $hirer = $withdraw->table("district_shop")->where('id=' . $withdraw_info['role_id'])->find();
                            if (empty($hirer)) {
                                echo json_encode(array("status" => 'fail', 'msg' => '商户不存在'));
                                exit();
                            }

                            // $isOk1 = Log::incomeLog($withdraw_info['withdraw_amount'], $withdraw_info['role_type'], $withdraw_info['role_id'], $withdraw_info['id'], 11, '提取收益到账户余额');
                            $model->table('district_shop')->data(array('settled_income' => "`settled_income`+({$withdraw_info['withdraw_amount']})"))->where('id=' . $withdraw_info['role_id'])->update();
                            $isOk2 = $withdraw->query("update tiny_customer set balance = balance + {$withdraw_info['withdraw_amount']} where user_id =" . $hirer['owner_id']);

                            if ($isOk2) {
                                Log::balance($withdraw_info['withdraw_amount'], $hirer['owner_id'], $withdraw_info['withdraw_no'], '推广收益提现到余额', 6);
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
        } else {
            $info = array('status' => 'fail', 'msg' => "操作失败，参数错误");
        }
        echo JSON::encode($info);
        exit();
    }

    public function set()
    {
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

    public function payset()
    {
        $model = new Model();
        $params = $model->table("mdpay_params")->where("id =1")->find();
        $this->assign('params', $params);
        $this->redirect();
    }

    public function payset_save()
    {
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

    public function quote()
    {
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

    public function addPromoter()
    {
        if ($this->is_ajax_request()) {
            $user_id = Req::args("user_id");
            $hirer_id = Req::args("hirer_id");
            $pointcoin = Req::args("pointcoin") != null ? Req::args("pointcoin") : 0;
            // $financialcoin = Req::args("financialcoin")!=null?Req::args("financialcoin"):0;
            $ds_promoter = Req::args("ds_promoter");
            $classify_id = Req::args("classify_id");//商家类型
            $region_id = Req::args("region_id");//区县
            if (!$user_id) {
                exit(json_encode(array("status" => 'fail', 'msg' => "参数错误")));
            }
            if (!$hirer_id && !$ds_promoter) {
                exit(json_encode(array("status" => 'fail', 'msg' => "缺少上级经销商或代理商")));
            }
            // $promoter = Promoter::getPromoterInstance($user_id);
            $model = new Model();
            //赠送积分和分红点
            if ($pointcoin > 0) {
                $model->table('customer')->where('user_id=' . $user_id)->data(array('point_coin' => "`point_coin`+({$pointcoin})"))->update();
                Log::pointcoin_log($pointcoin, $user_id, "", "代理商入驻赠送", 5);
            }
            $promoter = $model->table('district_promoter')->where('status=1 and user_id=' . $user_id)->find();
            // var_dump($promoter);die;
            if ($promoter) {
                exit(json_encode(array("status" => 'fail', 'msg' => "该用户已经有雇佣关系了")));
            } else {
                $inviter_exist = $model->table('invite')->where('invite_user_id=' . $user_id)->find();
                if ($hirer_id) {   //经销商推代理商
                    $isset = $model->table("district_shop")->where("id=$hirer_id")->find();
                    if (!$isset) {
                        exit(json_encode(array("status" => 'fail', 'msg' => "经销商不存在")));
                    }
                    if (!$inviter_exist) {
                        //添加邀请关系    
                        $model->table('invite')->data(array('user_id' => $isset['owner_id'], 'invite_user_id' => $user_id, 'from' => 'admin', 'district_id' => $isset['id'], 'createtime' => time()))->insert();
                        $invite_id = $isset['owner_id'];
                    } else {
                        $invite_id = $inviter_exist['user_id'];
                    }
                    $data['user_id'] = $user_id;
                    $data['type'] = 3;
                    $data['join_time'] = date("Y-m-d H:i:s");
                    $data['invitor_id'] = $invite_id;
                    $data['hirer_id'] = $hirer_id;
                    $data['create_time'] = date('Y-m-d H:i:s');
                    $data['valid_income'] = $data['frezze_income'] = $data['settled_income'] = 0.00;
                    $data['status'] = 1;
                    $data['classify_id'] = $classify_id;
                    $data['region_id'] = $region_id;
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
                } elseif ($ds_promoter) {     //代理商代理商
                    $isset = $model->table("district_promoter")->where("user_id=$ds_promoter")->find();
                    if (!$isset) {
                        exit(json_encode(array("status" => 'fail', 'msg' => "代理商不存在")));
                    }

                    $district = $model->table('district_shop')->where('owner_id=' . $ds_promoter)->find();

                    if ($inviter_exist) {
                        $invite_id = $inviter_exist['user_id'];
                        $district_id = $inviter_exist['district_id'];
                    } else {
                        if ($district) {
                            $district_id = $district['id'];
                        } else {
                            $district_id = $isset[' hirer_id'];
                            // $invite = $model->table('invite')->where('invite_user_id='.$ds_promoter)->find();
                            // if($invite){
                            //     $district_id = $invite['district_id'];
                            // }else{
                            //     $district_id=1;
                            // }
                        }
                        //添加邀请关系    
                        $model->table('invite')->data(array('user_id' => $ds_promoter, 'invite_user_id' => $user_id, 'from' => 'admin', 'district_id' => $district_id, 'createtime' => time()))->insert();
                        $invite_id = $ds_promoter;
                    }

                    $data['user_id'] = $user_id;
                    $data['type'] = 3;
                    $data['join_time'] = date("Y-m-d H:i:s");
                    $data['invitor_id'] = $invite_id;
                    $data['hirer_id'] = $district_id;
                    $data['create_time'] = date('Y-m-d H:i:s');
                    $data['valid_income'] = $data['frezze_income'] = $data['settled_income'] = 0.00;
                    $data['status'] = 1;
                    $result = $model->table("district_promoter")->data($data)->insert();
                    if ($result) {
                        $logic = DistrictLogic::getInstance();
                        $customer = $model->table('customer as c')->join('left join user as u on c.user_id=u.id')->where('c.user_id=' . $ds_promoter)->fields('c.real_name,u.nickname')->find();
                        $name = $customer['real_name'] != '' ? $customer['real_name'] : $customer['nickname'];
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

    public function addPromoters()
    {
        if ($this->is_ajax_request()) {
            $user_id = Req::args("user_id");
            $hirer_id = Req::args("hirer_id");
            $pointcoin = Req::args("pointcoin") != null ? Req::args("pointcoin") : 0;
            // $financialcoin = Req::args("financialcoin")!=null?Req::args("financialcoin"):0;
            $district_name = Req::args("district_name");
            $linkman = Req::args("linkman");
            $link_mobile = Req::args("link_mobile");
            if (!$user_id || !$hirer_id) {
                exit(json_encode(array("status" => 'fail', 'msg' => "参数错误")));
            }
            $model = new Model();
            $owner = $model->table('district_shop')->fields('id,owner_id')->where('id=' . $hirer_id)->find();
            if (!$owner) {
                exit(json_encode(array("status" => 'fail', 'msg' => "经销商不存在")));
            }
            // $promoter = Promoter::getPromoterInstance($user_id);   
            //赠送积分和分红点
            if ($pointcoin > 0) {
                $model->table('customer')->where('user_id=' . $user_id)->data(array('point_coin' => "`point_coin`+({$pointcoin})"))->update();
                Log::pointcoin_log($pointcoin, $user_id, "", "经销商入驻赠送", 8);
            }
            $promoter = $model->table('district_shop')->where('owner_id=' . $user_id)->find();

            if ($promoter) {
                exit(json_encode(array("status" => 'fail', 'msg' => "该用户已经有雇佣关系了")));
            } else {

                $customer = $model->table('customer as c')->join('left join user as u on c.user_id=u.id')->where('c.user_id=' . $user_id)->fields('c.real_name,c.mobile,c.city,u.nickname')->find();
                $data['name'] = $district_name;
                $data['asset'] = 1000;
                $data['founder_id'] = $user_id;
                $data['owner_id'] = $user_id;
                $data['invite_shop_id'] = $hirer_id;
                $data['create_time'] = date('Y-m-d H:i:s');
                $data['valid_period'] = date("Y-m-d H:i:s", strtotime("+3 years"));
                $data['valid_income'] = $data['frezze_income'] = $data['settled_income'] = 0.00;
                $data['status'] = 1;
                $data['linkman'] = $linkman;
                $data['link_mobile'] = $link_mobile;
                $data['location'] = $customer['city'];
                $result = $model->table("district_shop")->data($data)->insert();


                $invite = $model->table('invite')->where('invite_user_id=' . $user_id)->find();
                if (!$invite) {
                    $model->table('invite')->data(array('user_id' => $owner['owner_id'], 'invite_user_id' => $user_id, 'from' => 'admin', 'district_id' => $owner['id'], 'createtime' => time()))->insert();
                }

                $datas['user_id'] = $user_id;
                $datas['type'] = 3;
                $datas['join_time'] = date("Y-m-d H:i:s");
                $datas['invitor_id'] = $owner['owner_id'];
                $datas['hirer_id'] = $result;
                $datas['create_time'] = date('Y-m-d H:i:s');
                $datas['valid_income'] = $data['frezze_income'] = $data['settled_income'] = 0.00;
                $datas['status'] = 1;
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

    public function radio_customer_select()
    {
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
        $s_shop = Req::args("s_shop");
        $s_promote = Req::args("s_promote");
        $pointcoin = Req::args("pointcoin");
        // $financialcoin = Req::args("financialcoin");
        if ($s_shop && $s_shop != '') {
            $where1 = "name like '%{$s_shop}%' ";
        } else {
            $where1 = "1=1";
        }
        if ($s_promote && $s_promote != '') {
            $where2 = "c.real_name like '%{$s_promote}%' or u.nickname like '%{$s_promote}%'";
        } else {
            $where2 = "1=1";
        }
        $this->assign("s_shop", $s_shop);
        $this->assign("s_promote", $s_promote);
        $this->assign("pointcoin", $pointcoin);
        // $this->assign("financialcoin", $financialcoin);
        $this->assign("s_type", $s_type);
        $this->assign("s_content", $s_content);
        $this->assign("hirer_id", $hirer_id);
        $this->assign("where1", $where1);
        $this->assign("where2", $where2);
        $this->assign("where", $where);
        $this->redirect();
    }

    public function radio_customer_selects()
    {
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
        $s_shop = Req::args("s_shop");
        $district_name = Req::args("district_name");
        $linkman = Req::args("linkman");
        $link_mobile = Req::args("link_mobile");
        $pointcoin = Req::args("pointcoin");
        // $financialcoin = Req::args("financialcoin");
        if ($s_shop && $s_shop != '') {
            $wheres = "name like '%{$s_shop}%' ";
        } else {
            $wheres = "1=1";
        }

        $this->assign("s_shop", $s_shop);
        $this->assign("district_name", $district_name);
        $this->assign("linkman", $linkman);
        $this->assign("link_mobile", $link_mobile);
        $this->assign("pointcoin", $pointcoin);
        // $this->assign("financialcoin", $financialcoin);
        $this->assign("wheres", $wheres);
        $this->assign("s_type", $s_type);
        $this->assign("s_content", $s_content);
        $this->assign("hirer_id", $hirer_id);

        $this->assign("where", $where);
        $this->redirect();
    }

    public function sendMsg()
    {
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
    public function sendMessage($user_id, $content, $url, $type)
    {
        $need_weixin = true;
        $need_jpush = true;
        if ($type == "promoter_join_success") {
            if (!$this->client_type || $this->client_type == 'unknow') {
                return false;
            }
            if ($this->client_type == 'ios' || $this->client_type == 'android') {
                $need_weixin = false;
            } else if ($this->client_type == 'weixin') {
                $need_jpush = false;
            } else {
                return false;
            }
        }
        if ($need_weixin) {
            if ($this->token == NULL) {
                $wechatcfg = $this->model->table("oauth")->where("class_name='WechatOAuth'")->find();
                $wechat = new WechatMenu($wechatcfg['app_key'], $wechatcfg['app_secret'], '');
                $this->token = $wechat->getAccessToken();
            }
            $oauth_info = $this->model->table("oauth_user")->fields("open_id,open_name")->where("user_id=" . $user_id . " and oauth_type='wechat'")->find();
            if (!empty($oauth_info)) {
                $oauth_info['open_name'] = $oauth_info['open_name'] == "" ? "圆梦用户" : $oauth_info['open_name'];
                $params = array(
                    'touser' => $oauth_info['open_id'],
                    'msgtype' => 'text',
                    "text" => array(
                        'content' => "<a href=\"$url\">亲爱的{$oauth_info['open_name']},$content</a>"
                    )
                );
                Http::curlPost("https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=" . $this->token, json_encode($params, JSON_UNESCAPED_UNICODE));
            }
        }
        if ($need_jpush) {
            if (!$this->jpush) {
                $NoticeService = new NoticeService();
                $this->jpush = $NoticeService->getNotice('jpush');
            }
            $audience['alias'] = array($user_id);
            $this->jpush->setPushData('all', $audience, $content, $type, "");
            $this->jpush->push();
        }
    }

    public function hirer_del()
    {
        $hirer_id = Req::args("hirer_id");
        $model = new Model();
        $exist = $model->table('district_shop')->where('invite_shop_id=' . $hirer_id)->find();
        if ($exist) {
            $msg = array('error', "移除失败，该经销商已有下级经销商");
        } else {
            $model->table('district_shop')->where('id=' . $hirer_id)->delete();
            $model->table('district_promoter')->where('hirer_id=' . $hirer_id)->delete();
            $msg = array('success', "成功移除经销商 ");
        }
        $this->redirect("list_hirer", false, array('msg' => $msg));
    }

    public function promoter_del()
    {
        $id = Req::args("id");
        $model = new Model();
        $promoter = $model->table('district_promoter')->fields('user_id')->where('id=' . $id)->find();
        $exist = $model->table('district_promoter')->where('invitor_id=' . $promoter['user_id'])->find();
        if ($exist) {
            $msg = array('error', "移除失败，该代理商已有下级代理商");
        } else {
            $model->table('district_promoter')->where('id=' . $id)->delete();
            $msg = array('success', "成功移除代理商 ");
        }
        $this->redirect("list_promoter", false, array('msg' => $msg));
    }

    public function selectShop()
    {
        $shop = Req::args("shop");
        if ($shop && $shop != '') {
            $wheres = "name like '%{$shop}%' ";
        } else {
            $wheres = "1=1";
        }
        $model = new Model();
        $shop = $model->table('district_shop')->where($wheres)->findAll();
        $this->assign("wheres", $wheres);
        $this->redirect('radio_customer_selects');
    }

    public function rate_edit()
    {
        $id = Req::args("id");

        $promoter = Req::args();
        if ($id) {
            $model = new Model("district_promoter as d");
            $promoter = $model->join("customer as c on c.user_id = d.user_id")->fields('d.id,d.base_rate,c.real_name')->where("d.id=" . $id)->find();
        }
        $this->redirect('rate_edit', false, $promoter);
    }

    public function rate_save()
    {
        $id = Req::args("id");
        $base_rate = Req::args("base_rate");
        $model = new Model("district_promoter");
        if ($id) {
            $promoter = $model->where('id=' . $id)->find();
            if ($promoter) {
                if ($base_rate) {
                    $model->data(array('base_rate' => $base_rate))->where('id=' . $id)->update();
                    $user_model = new Model('customer');
                    $user = $user_model->fields('real_name')->where('user_id=' . $promoter['user_id'])->find();
                    Log::op($this->manager['id'], "修改分账比例", "管理员[" . $this->manager['name'] . "]:修改了代理商 " . $user['real_name'] . " 的信息");
                }
            }
        }
        $this->redirect('list_promoter');
    }

    public function qrcode()
    {
        $id = Req::args("user_id");
        $uid = Filter::int($id);
        $this->assign('uid', $uid);
        $this->redirect();
    }

    public function invitepay()
    {
        // $id=$this->user['id'];
        $id = Req::args("user_id");
        $uid = Filter::int($id);
        $model = new Model();
        $user = $model->table('customer')->fields('real_name')->where('user_id=' . $uid)->find();
        $users = $model->table('user')->fields('avatar')->where('id=' . $uid)->find();
        if ($user) {
            $real_name = $user['real_name'];
        } else {
            $real_name = '未知商家';
        }
        if ($users) {
            if ($users['avatar'] == '' || $users['avatar'] == '/0') {
                $users['avatar'] = '/static/images/96.png';
            }
            $avatar = $users['avatar'];
        } else {
            $avatar = '';
        }
        Session::set('seller_id', $uid);
        $this->assign('real_name', $real_name);
        $this->assign('avatar', $avatar);
        $this->assign('uid', $uid);
        $this->redirect();
    }

    //生成邀请支付码
    public function buildinvitepay()
    {
        $user_id = Filter::int(Req::args('uid'));
        // if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
        //     $wechatcfg = $this->model->table("oauth")->where("class_name='WechatOAuth'")->find();
        //     $wechat = new WechatMenu($wechatcfg['app_key'], $wechatcfg['app_secret'], '');
        //     $token = $wechat->getAccessToken();
        //     $params = array(
        //         "action_name" => "QR_LIMIT_STR_SCENE",
        //         "action_info" => array("scene" => array("scene_str" => "invite-{$user_id}"))
        //     );
        //     $ret = Http::curlPost("https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token={$token}", json_encode($params));
        //     $ret = json_decode($ret, TRUE);
        //     if (isset($ret['ticket'])) {
        //         $this->redirect("https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket={$ret['ticket']}");
        //         exit;
        //     }
        // }
        // $model = new Model();

        // $user = $model->table('user')->fields('avatar')->where('id='.$user_id)->find();

        // if($user['avatar']){
        //     $avatar = $user['avatar'];
        //     $path = $qrCode->getImage($avatar,'static/images');
        //     $logo = APP_ROOT.$path;
        // }else{
        //     $logo = APP_ROOT."static/images/96.png";
        // }

        $url = Url::fullUrlFormat("/ucenter/demo/inviter_id/" . $user_id);
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

    //显示经销商专区的名称
    public function hirer_edit()
    {
        $id = Req::args("id");
        $uid = Filter::int($id);
        $model = new Model();
        $list = $model->table('district_shop')->fields('id,name,code_num')->where('id=' . $uid)->find();
        $this->assign('list', $list);
        $this->redirect('hirer_edit');
    }

    //编辑经销商专区名称
    public function hirer_save()
    {
        $id = Req::args('id');
        $model = new Model();
        $name = Req::args('name');
        $code_num = Filter::int(Req::args('code_num'));
        if ($name) {
            $model->table('district_shop')->data(array('name' => $name,'code_num'=>$code_num))->where("id=$id")->update();
        }
        $this->redirect("list_hirer");
    }

    //显示代理商专区的名称
    public function promoter_edit()
    {
        $id = Req::args("id");
        $uid = Filter::int($id); //
        $model = new Model();
        $promoter = $model->table('district_promoter')->fields('id,user_id,lng,lat,location,info')->where('id='.$uid)->find();
        $customer = $model->table('customer')->fields('user_id,real_name')->where('user_id=' . $promoter['user_id'])->find();
        $this->assign('customer', $customer);
        $this->assign('promoter',$promoter);
        $this->redirect('promoter_edit');
    }

    //编辑代理商专区名称
    public function promoter_save()
    {
        $id = Req::args('id');
        $model = new Model();
        // $name = Req::args('real_name');
        $lng = Req::args('lng'); //经度
        $lat = Req::args('lat'); //纬度
        $location = Req::args('location');
        $info = Req::args('info');
        if ($lng) {
            $model->table('district_promoter')->data(array('lng'=>$lng))->where('id='.$id)->update();
            }
        if ($lat) {
            $model->table('district_promoter')->data(array('lat'=>$lat))->where('id='.$id)->update();
            }
        if ($location) {
            $model->table('district_promoter')->data(array('location'=>$location))->where('id='.$id)->update();
        }
        if ($info) {
            $model->table('district_promoter')->data(array('info'=>$info))->where('id='.$id)->update();
        }
        $this->redirect("list_promoter");
    }

    public function shop_check(){
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

    public function shop_check_detail(){
       $id = Req::args("id");
        $id = Filter::int($id); //
        $model = new Model();
        $shop_check = $model->table('shop_check')->fields('*')->where('id='.$id)->find();
        
        
        $this->assign('shop_check',$shop_check);
        $this->redirect();
    }

    public function shop_check_do(){
      $id = Filter::int(Req::args("id"));
        $status = Filter::int(Req::args("status"));
        $reason = Req::args("reason");
        $model = new Model();
        if ($status == 2) {
            if (trim($reason) != "") {//作废理由不能为空
                $result = $model->table("shop_check")->data(array("status" =>2,'check_date'=> date("Y-m-d H:i:s"),'reason'=>$reason))->where("id=" . $id)->update();
                    echo json_encode(array("status" => 'success', 'msg' => '成功'));
                    exit();
                
            } else {
                echo json_encode(array("status" => 'fail', 'msg' => '理由不能为空'));
                exit();
            }
        }elseif($status==1){
          $model->table("shop_check")->data(array("status" =>1,'check_date'=> date("Y-m-d H:i:s")))->where("id=" . $id)->update();
          
          $shop_check = $model->table("shop_check")->where("id=" . $id)->find();
          if($shop_check['user_id']==42608) {
            //上传银盛
              $myParams = array();

              $myParams['method'] = 'ysepay.merchant.register.token.get';
              $myParams['partner_id'] = 'yuanmeng';
              // $myParams['partner_id'] = $this->user['id'];
              $myParams['timestamp'] = date('Y-m-d H:i:s', time());
              $myParams['charset'] = 'GBK';
              $myParams['notify_url'] = 'http://api.test.ysepay.net/atinterface/receive_return.htm';
              $myParams['sign_type'] = 'RSA';

              $myParams['version'] = '3.0';
              $biz_content_arr = array();

              $myParams['biz_content'] = '{}';
              ksort($myParams);

              $signStr = "";
              foreach ($myParams as $key => $val) {
                 $signStr .= $key . '=' . $val . '&';
              }
              $signStr = rtrim($signStr, '&');
              $sign = $this->sign_encrypt(array('data' => $signStr));
              $myParams['sign'] = trim($sign['check']);
              $url = 'https://register.ysepay.com:2443/register_gateway/gateway.do';

              $ret = Common::httpRequest($url, 'POST', $myParams);
              $ret = json_decode($ret, true);
              $sumbit_url = "https://uploadApi.ysepay.com:2443/yspay-upload-service?method=upload";
              $http_url="http://39.108.165.0";

              //身份证正面
              $file_name = time().$shop_check['user_id'].'positive_idcard';
              $file_ext = substr(strrchr($shop_check['positive_idcard'], '.'), 1);
              $save_path = dirname(dirname(dirname(__FILE__))).'/static/temp_path/'.$file_name.'.'.$file_ext;
              file_put_contents($save_path, file_get_contents($shop_check['positive_idcard']));
              $post_data = array (
                    // "name"=>'picFile',
                    "picType"=>'00',
                    "token"=>$ret['ysepay_merchant_register_token_get_response']['token'],
                    "superUsercode"=>'yuanmeng',
                    "upload" => new CURLFile($save_path),
                );


                $re = $this->curl_form($post_data,$sumbit_url,$http_url);
                unlink($save_path);
                exit();
                
                //身份证反面
                $file_name1 = time().$shop_check['user_id'].'native_idcard';
                $file_ext1 = substr(strrchr($shop_check['native_idcard'], '.'), 1);
                $save_path1 = dirname(dirname(dirname(__FILE__))).'/static/temp_path/'.$file_name1.'.'.$file_ext1;
                file_put_contents($save_path1, file_get_contents($shop_check['native_idcard']));
                $post_data1 = array (
                    // "name"=>'picFile',
                    "picType"=>'30',
                    "token"=>$ret['ysepay_merchant_register_token_get_response']['token'],
                    "superUsercode"=>'yuanmeng',
                    "upload" => new CURLFile($save_path1),
                );

                $re = $this->curl_form($post_data1,$sumbit_url,$http_url);
                unlink($save_path1);
                exit();

                if($shop_check['hand_idcard']!=null) {
                    //手持身份证正扫面照
                    $file_name2 = time().$shop_check['user_id'].'hand_idcard';
                    $file_ext2 = substr(strrchr($shop_check['hand_idcard'], '.'), 1);
                    $save_path2 = dirname(dirname(dirname(__FILE__))).'/static/temp_path/'.$file_name2.'.'.$file_ext2;
                    file_put_contents($save_path2, file_get_contents($shop_check['hand_idcard']));
                    $post_data2 = array (
                        // "name"=>'picFile',
                        "picType"=>'33',
                        "token"=>$ret['ysepay_merchant_register_token_get_response']['token'],
                        "superUsercode"=>'yuanmeng',
                        "upload" => new CURLFile($save_path2),
                    );

                    $re = $this->curl_form($post_data2,$sumbit_url,$http_url);
                    unlink($save_path2);
                    exit();
                }

                if($shop_check['type']==1) {
                    //营业执照
                    $file_name3 = time().$shop_check['user_id'].'business_licence';
                    $file_ext3 = substr(strrchr($shop_check['business_licence'], '.'), 1);
                    $save_path3 = dirname(dirname(dirname(__FILE__))).'/static/temp_path/'.$file_name3.'.'.$file_ext3;
                    file_put_contents($save_path3, file_get_contents($shop_check['business_licence']));
                    $post_data3 = array (
                        // "name"=>'picFile',
                        "picType"=>'19',
                        "token"=>$ret['ysepay_merchant_register_token_get_response']['token'],
                        "superUsercode"=>'yuanmeng',
                        "upload" => new CURLFile($save_path3),
                    );

                    $re = $this->curl_form($post_data3,$sumbit_url,$http_url);
                    unlink($save_path3);
                    exit();
                    
                    //门店照
                    $file_name4 = time().$shop_check['user_id'].'shop_photo';
                    $file_ext4 = substr(strrchr($shop_check['shop_photo'], '.'), 1);
                    $save_path4 = dirname(dirname(dirname(__FILE__))).'/static/temp_path/'.$file_name4.'.'.$file_ext4;
                    file_put_contents($save_path4, file_get_contents($shop_check['shop_photo']));
                    $post_data4 = array (
                        // "name"=>'picFile',
                        "picType"=>'34',
                        "token"=>$ret['ysepay_merchant_register_token_get_response']['token'],
                        "superUsercode"=>'yuanmeng',
                        "upload" => new CURLFile($save_path4),
                    );

                    $re = $this->curl_form($post_data4,$sumbit_url,$http_url);
                    unlink($save_path4);
                    exit();
                }
          }      

          echo json_encode(array("status" => 'success', 'msg' => '成功'));
          exit();
        }else{
            echo json_encode(array("status" => 'success', 'msg' => '成功'));
            exit();
        }
    }

    public function sign_encrypt($input)
    {
        // $pfxpath = 'http://' . $_SERVER['HTTP_HOST'] . "/trunk/protected/classes/yinpay/certs/shanghu_test.pfx";
        $pfxpath = "./protected/classes/yinpay/certs/yuanmeng.pfx";
        $pfxpassword = '008596';
        $return = array('success' => 0, 'msg' => '', 'check' => '');
        $pkcs12 = file_get_contents($pfxpath); //私钥
        if (openssl_pkcs12_read($pkcs12, $certs, $pfxpassword)) {
            $privateKey = $certs['pkey'];
            $publicKey = $certs['cert'];
            $signedMsg = "";
            if (openssl_sign($input['data'], $signedMsg, $privateKey, OPENSSL_ALGO_SHA1)) {
                $return['success'] = 1;
                $return['check'] = base64_encode($signedMsg);
                $return['msg'] = base64_encode($input['data']);

            }
        }

        return $return;
    }

    public function curl_form($post_data,$sumbit_url,$http_url){

        //初始化
        $ch = curl_init();
        //设置变量
        curl_setopt($ch, CURLOPT_URL, $sumbit_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 0);//执行结果是否被返回，0是返回，1是不返回
        curl_setopt($ch, CURLOPT_HEADER, 0);//参数设置，是否显示头部信息，1为显示，0为不显示
        curl_setopt($ch, CURLOPT_REFERER, $http_url);
        //表单数据，是正规的表单设置值为非0
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);//设置curl执行超时时间最大是多少
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        //使用数组提供post数据时，CURL组件大概是为了兼容@filename这种上传文件的写法，
        //默认把content_type设为了multipart/form-data。虽然对于大多数web服务器并
        //没有影响，但是还是有少部分服务器不兼容。本文得出的结论是，在没有需要上传文件的
        //情况下，尽量对post提交的数据进行http_build_query，然后发送出去，能实现更好的兼容性，更小的请求数据包。
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        //执行并获取结果
        $output = curl_exec($ch);

        if($output === FALSE) {
            echo "<br/>","cUrl Error:".curl_error($ch);
        }
        //    释放cURL句柄
        curl_close($ch);
        // return $output;
    }

    public function shop_check_dos(){
        $id = Filter::sql(Req::args("id"));
        $status = Filter::sql(Req::args("status"));
        $model = new Model();
        $model->table("shop_check")->data(array("status" =>1,'check_date'=> date("Y-m-d H:i:s")))->where("id=" . $id)->update();
        $shop_check = $model->table("shop_check")->where("id=" . $id)->find();
          if($shop_check['user_id']==42608) {
            //上传银盛
              $myParams = array();

              $myParams['method'] = 'ysepay.merchant.register.token.get';
              $myParams['partner_id'] = 'yuanmeng';
              // $myParams['partner_id'] = $this->user['id'];
              $myParams['timestamp'] = date('Y-m-d H:i:s', time());
              $myParams['charset'] = 'GBK';
              $myParams['notify_url'] = 'http://api.test.ysepay.net/atinterface/receive_return.htm';
              $myParams['sign_type'] = 'RSA';

              $myParams['version'] = '3.0';
              $biz_content_arr = array();

              $myParams['biz_content'] = '{}';
              ksort($myParams);

              $signStr = "";
              foreach ($myParams as $key => $val) {
                 $signStr .= $key . '=' . $val . '&';
              }
              $signStr = rtrim($signStr, '&');
              $sign = $this->sign_encrypt(array('data' => $signStr));
              $myParams['sign'] = trim($sign['check']);
              $url = 'https://register.ysepay.com:2443/register_gateway/gateway.do';

              $ret = Common::httpRequest($url, 'POST', $myParams);
              $ret = json_decode($ret, true);
              $sumbit_url = "https://uploadApi.ysepay.com:2443/yspay-upload-service?method=upload";
              $http_url="http://39.108.165.0";
              
              //身份证正面
              $file_name = time().$shop_check['user_id'].'positive_idcard';
              $file_ext = substr(strrchr($shop_check['positive_idcard'], '.'), 1);
              $save_path = dirname(dirname(dirname(__FILE__))).'/static/temp_path/'.$file_name.'.'.$file_ext;
              file_put_contents($save_path, file_get_contents($shop_check['positive_idcard']));
              $post_data = array (
                    // "name"=>'picFile',
                    "picType"=>'00',
                    "token"=>$ret['ysepay_merchant_register_token_get_response']['token'],
                    "superUsercode"=>'yuanmeng',
                    "upload" => new CURLFile($save_path),
                );


                $re = $this->curl_form($post_data,$sumbit_url,$http_url);
                unlink($save_path);
                exit();
                
                //身份证反面
                $file_name1 = time().$shop_check['user_id'].'native_idcard';
                $file_ext1 = substr(strrchr($shop_check['native_idcard'], '.'), 1);
                $save_path1 = dirname(dirname(dirname(__FILE__))).'/static/temp_path/'.$file_name1.'.'.$file_ext1;
                file_put_contents($save_path1, file_get_contents($shop_check['native_idcard']));
                $post_data1 = array (
                    // "name"=>'picFile',
                    "picType"=>'30',
                    "token"=>$ret['ysepay_merchant_register_token_get_response']['token'],
                    "superUsercode"=>'yuanmeng',
                    "upload" => new CURLFile($save_path1),
                );

                $re = $this->curl_form($post_data1,$sumbit_url,$http_url);
                unlink($save_path1);
                exit();

                if($shop_check['hand_idcard']!=null) {
                    //手持身份证正扫面照
                    $file_name2 = time().$shop_check['user_id'].'hand_idcard';
                    $file_ext2 = substr(strrchr($shop_check['hand_idcard'], '.'), 1);
                    $save_path2 = dirname(dirname(dirname(__FILE__))).'/static/temp_path/'.$file_name2.'.'.$file_ext2;
                    file_put_contents($save_path2, file_get_contents($shop_check['hand_idcard']));
                    $post_data2 = array (
                        // "name"=>'picFile',
                        "picType"=>'33',
                        "token"=>$ret['ysepay_merchant_register_token_get_response']['token'],
                        "superUsercode"=>'yuanmeng',
                        "upload" => new CURLFile($save_path2),
                    );

                    $re = $this->curl_form($post_data2,$sumbit_url,$http_url);
                    unlink($save_path2);
                    exit();
                }

                if($shop_check['type']==1) {
                    //营业执照
                    $file_name3 = time().$shop_check['user_id'].'business_licence';
                    $file_ext3 = substr(strrchr($shop_check['business_licence'], '.'), 1);
                    $save_path3 = dirname(dirname(dirname(__FILE__))).'/static/temp_path/'.$file_name3.'.'.$file_ext3;
                    file_put_contents($save_path3, file_get_contents($shop_check['business_licence']));
                    $post_data3 = array (
                        // "name"=>'picFile',
                        "picType"=>'19',
                        "token"=>$ret['ysepay_merchant_register_token_get_response']['token'],
                        "superUsercode"=>'yuanmeng',
                        "upload" => new CURLFile($save_path3),
                    );

                    $re = $this->curl_form($post_data3,$sumbit_url,$http_url);
                    unlink($save_path3);
                    exit();
                    
                    //门店照
                    $file_name4 = time().$shop_check['user_id'].'shop_photo';
                    $file_ext4 = substr(strrchr($shop_check['shop_photo'], '.'), 1);
                    $save_path4 = dirname(dirname(dirname(__FILE__))).'/static/temp_path/'.$file_name4.'.'.$file_ext4;
                    file_put_contents($save_path4, file_get_contents($shop_check['shop_photo']));
                    $post_data4 = array (
                        // "name"=>'picFile',
                        "picType"=>'34',
                        "token"=>$ret['ysepay_merchant_register_token_get_response']['token'],
                        "superUsercode"=>'yuanmeng',
                        "upload" => new CURLFile($save_path4),
                    );

                    $re = $this->curl_form($post_data4,$sumbit_url,$http_url);
                    unlink($save_path4);
                    exit();
                }


          }
        $this->redirect('shop_check');
    }

    public function shop_check_export(){
        $this->layout = '';
        $condition = Req::args("condition");
        $fields = Req::args("fields");
        $condition = Common::str2where($condition);
        $model = new Model();
        if ($condition) {
            $where = $condition;
        }else{
            $where = '1=1';
        } 
            $items = $model->table('shop_check as sc')->join("left join user as u on sc.user_id = u.id left join district_promoter as d on sc.user_id = d.user_id")->fields('sc.*,u.nickname,d.shop_name')->where($where)->findAll();
            if ($items) {
                header("Content-type:application/vnd.ms-excel");
                header("Content-Disposition:filename=doc_receiving_list.xls");
                $fields_array = array('nickname' => '用户名','shop_name' => '店铺名', 'shop_type' => '商家类型', 'positive_idcard' => '身份证正面照','native_idcard'=>'身份证反面照', 'business_licence' => '营业执照','account_picture' => '开户许可证','shop_photo' => '门店照','hand_idcard' => '手持身份证照','account_card' => '结算银行卡号');
                $str = "<table border=1><tr>";
                foreach($items as $k=>$v){
                    $items[$k]['shop_type'] = $v['type']==1?'实体商家':'个人微商';
                }
                foreach ($fields as $value) {
                    $str .= "<th>" . iconv("UTF-8", "GBK", $fields_array[$value]) . "</th>";
                }
                $str .= "</tr>";
                foreach ($items as $item) {
                    $str .= "<tr>";
                    foreach ($fields as $value) {
                        $str .= "<td>" . mb_convert_encoding($item[$value],"GBK", "UTF-8") . "</td>";
                    }
                    $str .= "</tr>";
                }
                $str .= "</table>";
                echo $str;
                exit;
            } else {
                $this->msg = array("warning", "没有符合该筛选条件的数据，请重新筛选！");
                $this->redirect("shop_check_list", false, Req::args());
            }
    }

    public function shop_check_delete(){
        $id = Filter::sql(Req::args("id"));
        $model = new Model();
        $model->table("shop_check")->where("id=" . $id)->delete();
        $this->redirect('shop_check');
    }

    public function cashier_list(){
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

    public function cashier_log(){
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

    public function shop_child_count() {
        $model = new Model();
        $id = Filter::sql(Req::args("id"));
        $idstr = Common::getAllChildShops($id);
        if($idstr['user_ids']!='') {
            $shop_amount = $model->table('order_offline')->fields('sum(order_amount) as sum')->where("pay_status=1 and shop_ids in (".$idstr['user_ids'].")")->query();
            $shop_sum = $shop_amount[0]['sum'];
        } else {
            $shop_sum = 0.00;
        }
            
        $idstr1 = Common::getAllChildPromotersIds($id);
        if($idstr1['user_ids']!='') {
            $promoter_amount = $model->table('order_offline')->fields('sum(order_amount) as sum')->where("pay_status=1 and shop_ids in (".$idstr1['user_ids'].")")->query();
            $promoter_sum = $promoter_amount[0]['sum'];
        } else {
            $promoter_sum = 0.00;
        }

        $info['shop_num'] = $idstr['num'];
        $info['shop_amount'] = $shop_sum;
        $info['promoter_num'] = $idstr1['num'];
        $info['promoter_amount'] = $promoter_sum;
        $this->assign("info", $info);
        $this->redirect();
        
    }

    public function shop_check_register() {
        $myParams = array();  
        
        $myParams['method'] = 'ysepay.merchant.register.token.get';
        $myParams['partner_id'] = 'yuanmeng';
        // $myParams['partner_id'] = $this->user['id'];
        $myParams['timestamp'] = date('Y-m-d H:i:s', time());
        $myParams['charset'] = 'GBK';
        $myParams['notify_url'] = 'http://api.test.ysepay.net/atinterface/receive_return.htm';      
        $myParams['sign_type'] = 'RSA';  
          
        $myParams['version'] = '3.0';
        $biz_content_arr = array(
        );
        // $myParams['biz_content'] = json_encode($biz_content_arr, JSON_UNESCAPED_UNICODE);//构造字符串
        $myParams['biz_content'] = '{}';
        ksort($myParams);
        
        $signStr = "";
        foreach ($myParams as $key => $val) {
            $signStr .= $key . '=' . $val . '&';
        }
        $signStr = rtrim($signStr, '&');
        $sign = $this->sign_encrypt(array('data' => $signStr));
        $myParams['sign'] = trim($sign['check']);
        $url = 'https://register.ysepay.com:2443/register_gateway/gateway.do';
        // echo "<pre>";
        // print_r($myParams);
        // echo "<pre>";
        $ret = Common::httpRequest($url,'POST',$myParams);
        $ret = json_decode($ret,true);
        
        $id = Filter::int(Req::args('id'));
        $model = new Model();
        $shop_check = $model->table('shop_check')->where('id='.$id)->find();
        $promoter = $model->table('district_promoter')->fields('shop_name,province_id,city_id,location')->where('user_id='.$shop_check['user_id'])->find();
        $customer = $model->table('customer as c')->fields('c.real_name,c.realname,c.mobile,u.nickname')->join('left join user as u on c.user_id=u.id')->where('c.user_id='.$shop_check['user_id'])->find();
        $name = $promoter['shop_name']!=null?$promoter['shop_name']:($customer['nickname']!=null?$customer['nickname']:$customer['real_name']);
        if($shop_check['type']==1) {
            $cust_type = 'C';
        } else {
            $cust_type = 'O';
        }
        if($promoter['province_id']==0 || $promoter['city_id']==0) {
            echo json_encode(array("status" => 'error', 'msg' => '请先完善省份城市信息'));
            exit();
        }
        if($promoter['location']==null) {
            echo json_encode(array("status" => 'error', 'msg' => '请先完善详细地址信息'));
            exit();
        }
        if($customer['realname']==null) {
            echo json_encode(array("status" => 'error', 'msg' => '请先完成实名认证'));
            exit();
        }
        $province = $model->table('area')->where('id='.$promoter['province_id'])->find();
        $city = $model->table('area')->where('id='.$promoter['city_id'])->find();

        $bankcard = $model->table('bankcard')->where('user_id='.$shop_check['user_id'])->find();
        $data = array(
            'merchant_no'=>'yuanmeng',
            'cust_type'=>$cust_type,
            'token'=>$ret['ysepay_merchant_register_token_get_response']['token'],
            'another_name'=>$name,
            'cust_name'=>$name,
            'mer_flag'=>'11',
            'industry'=>'58',
            'province'=>$province['name'],
            'city'=>$city['name'],
            'company_addr'=>$promoter['location'],
            'legal_name'=>$customer['realname'],
            'legal_tel'=>$customer['mobile'],
            'legal_cert_type'=>'00',
            'legal_cert_no'=>$this->des_encrypt($customer['id_no'],8),
            'notify_type'=>2,
            'settle_type'=>'1',
            'bank_account_no'=>$shop_check['account_card'],
            'bank_account_name'=>$bankcard['open_name'],
            'bank_account_type'=>'personal',
            'bank_card_type'=>'debit',
            'bank_name'=>$shop_check['bank_name'],
            'bank_type'=>$bankcard['bank_name'],
            'bank_province'=>$bankcard['province']!=null?$bankcard['province']:$province['name'],
            'bank_city'=>$bankcard['city']!=null?$bankcard['city']:$city['name'],
            'cert_type'=>'00',
            'cert_no'=>md5($customer['id_no']),
            'bank_telephone_no'=>$customer['mobile']
            );
        $url1 = 'https:// register.ysepay.com:2443/gateway.do';
        $res = Common::httpRequest($url1,'POST',$data);
        $res = json_decode($res,true);
        var_dump($res);die;
    }

  public function des_encrypt($str, $key) {
      $block = mcrypt_get_block_size('des', 'ecb');
      $pad = $block - (strlen($str) % $block);
      $str .= str_repeat(chr($pad), $pad);
      return mcrypt_encrypt(MCRYPT_DES, $key, $str, MCRYPT_MODE_ECB);
    }
}
