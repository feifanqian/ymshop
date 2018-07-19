<?php
class CashierAction extends Controller
{
    public $model = null;
    public $user = null;
    public $code = 1000;
    public $content = NULL;

    public function __construct()
    {
        $this->model = new Model();
    }
    
    //邀请收银员
    public function add_cashier()
    {
    	$mobile = Filter::str(Req::args('mobile'));
    	if (!Validator::mobi($mobile)) {
            $this->code = 1024;
            return;
        }
        $job_no = Filter::str(Req::args('job_no'));
        if(!$job_no) {
        	$this->code = 1239;
            return;
        }
        $job_no_exist = $this->model->table('cashier')->where("job_no=".$job_no." and hire_user_id=".$this->user['id']." and status=1")->find();
        if($job_no_exist) {
        	$this->code = 1240;
            return;
        }
        $cashier = $this->model->table('customer')->fields('user_id')->where('status=1 and mobile='.$mobile)->find();
        if(!$cashier) {
        	$this->code = 1159;
            return;
        }
        if($cashier['user_id']==$this->user['id']) {
            $this->code = 1265;
            return;
        }
        $has_be = $this->model->table('cashier')->where("user_id=".$cashier['user_id']." and hire_user_id =".$this->user['id']." and status=1")->find();
        if($has_be) {
            $this->code = 1254;
            return;
        }
        $has_be_other = $this->model->table('cashier')->where("user_id=".$cashier['user_id']." and hire_user_id !=".$this->user['id']." and status=1")->find();
        if($has_be_other) {
            $this->code = 1243;
            return;
        }
        $invited = $this->model->table('cashier')->where("user_id=".$cashier['user_id']." and hire_user_id =".$this->user['id']." and status=0")->find();
        if($invited) {
        	$this->code = 1244;
            return;
        }
        $promoter = $this->model->table('district_promoter as dp')->fields('dp.id,dp.user_id,c.real_name')->join("customer AS c ON dp.user_id=c.user_id")->where('dp.user_id='.$this->user['id'])->find();
        if(!$promoter) {
        	$this->code = 1159;
            return;
        }
        $data = array(
        	'user_id'=>$cashier['user_id'],
        	'hire_promoter_id'=>$promoter['id'],
        	'hire_user_id'=>$this->user['id'],
        	'mobile'=>$mobile,
        	'job_no'=>$job_no,
        	'status'=>0,
            'create_time'=>date('Y-m-d H:i:s')
        	);
        $res = $this->model->table('cashier')->data($data)->insert();
        $type = 'cashier_invite';
        $content = "收到一条来自于商家{$promoter['real_name']}的成为收银员邀请";
        $platform = 'all';
        if (!$this->jpush) {
            $NoticeService = new NoticeService();
            $this->jpush = $NoticeService->getNotice('jpush');
        }
        $audience['alias'] = array($cashier['user_id']);
        $this->jpush->setPushData($platform, $audience, $content, $type, $res);
        $ret = $this->jpush->push();
        if(!$ret) {
        	$this->code = 1242;
            return;
        }
        $push_data = array(
            'to_id'=>$cashier['user_id'],
            'type'=>'be_cashier',
            'content'=>$content,
            'create_time'=>date('Y-m-d H:i:s'),
            'status'=>'unread',
            'value'=>$res
            );
        $this->model->table('push_message')->data($push_data)->insert();
        if($res) {
        	$this->code = 0;
            $this->content['jpush_type'] = 'cashier_invite';
            $this->content['jpush_id'] = $cashier['user_id'];
            $this->content['seller_id'] = $this->user['id'];
            $this->content['seller_name'] = $this->user['nickname'];
            return;
        } else {
            $this->code = 1241;
            return;
        } 
    }

    //接收or拒绝邀请
    public function cashier_operate()
    {
    	$id = Filter::int(Req::args('id'));
    	$status = Filter::int(Req::args('status'));
    	$res = $this->model->table('cashier')->data(array('status'=>$status))->where("id=".$id." and user_id=".$this->user['id']." and status=0")->update();
        if($status==1) {
            $this->model->table('customer')->data(array('is_cashier'=>1))->where("user_id=".$this->user['id'])->update();
        }
        $state = $status==1?'同意':'拒绝';
    	if($res) {
        	$this->code = 0;
            $this->content['state'] = $state;
            return;
        } else {
            $this->code = 1241;
            return;
        }
    }

    //收银员列表
    public function cashier_list()
    {
    	$list = $this->model->table('cashier as ca')->fields('cu.real_name,ca.name,ca.mobile,ca.job_no,ca.id,ca.status')->join('customer as cu on cu.user_id=ca.user_id')->where("ca.hire_user_id=".$this->user['id']." and ca.status in(1,2)")->findAll();
        $today = date('Y-m-d');
        if($list) {
            foreach ($list as $k=>$value) {
                if($list[$k]['name']==null) {
                    $list[$k]['name']='';
                }
                if($list[$k]['real_name']==null) {
                    $list[$k]['real_name']='';
                }
                $sign = $this->model->table('cashier_attendance')->where('cashier_id='.$value['id']." and `work_on_date` = '$today'")->order('id desc')->find();
                if($sign) {
                    if($sign['work_off_time']==null){
                        $list[$k]['status'] = '正在上班中';
                        $list[$k]['desk_no'] = $sign['desk_no'];
                    } else {
                        $list[$k]['status'] = '已下班';
                        $list[$k]['desk_no'] = '';
                    }
                } else {
                    $list[$k]['status'] = '未上班';
                    $list[$k]['desk_no'] = '';
                }
            }
        }
    	$this->code = 0;
    	$this->content = $list;
        return;
    }

    //收银员收款明细
    public function cashier_detail()
    {
    	$id = Filter::int(Req::args('id'));
    	$date = Filter::str(Req::args('date'));
        $start_time = $date.' 00:00:00';
        $end_time = $date.' 23:59:59';
    	if($date) {
    		$where = "cashier_id={$id} and pay_status=1 and pay_time between '{$start_time}' and '{$end_time}'";
    	} else {
            $where = "cashier_id={$id} and pay_status=1";
    	}
    	$list = $this->model->table('order_offline')->fields("pay_time as pay_date,payable_amount,remark, case dayofweek(pay_time)  when 1 then '星期日' when 2 then '星期一' when 3 then '星期二' when 4 then '星期三' when 5 then '星期四' when 6 then '星期五' when 7 then '星期六' end as  weekday")->where($where)->order('pay_time desc')->findAll();
        if($list) {
            foreach ($list as $k => $v) {
                if($list[$k]['remark']==null) {
                    $list[$k]['remark']='';
                }
            }
        }
        $this->code = 0;
    	$this->content = $list;
        return;
    }

    //添加收银台
    public function add_cashier_desk()
    {
    	$count = $this->model->table('cashier_desk')->where('hire_user_id='.$this->user['id'])->count();
    	if($count==6) {
    		$this->code = 1245;
            return;
    	}
    	switch ($count) {
    		case 0:
    			$desk_no = '01';
    			break;
    		case 1:
    			$desk_no = '02';
    			break;
    		case 2:
    			$desk_no = '03';
    			break;
    		case 3:
    			$desk_no = '04';
    			break;
    		case 4:
    			$desk_no = '05';
    			break;
    		case 5:
    			$desk_no = '06';
    			break;
    		default :
    		    $desk_no = '01';
    			break;					
    	}
    	$promoter = $this->model->table('district_promoter as dp')->fields('dp.id,dp.user_id,c.real_name')->join("customer AS c ON dp.user_id=c.user_id")->where('dp.user_id='.$this->user['id'])->find();
        if(!$promoter) {
        	$this->code = 1159;
            return;
        }
        $data = array(
        	'hire_promoter_id'=>$promoter['id'],
        	'hire_user_id'=>$this->user['id'],
        	'desk_no'=>$desk_no,
        	'status'=>1
        	);
        $res = $this->model->table('cashier_desk')->data($data)->insert();
        if($res) {
        	$this->code = 0;
            return;
        } else {
            $this->code = 1241;
            return;
        }
    }

    //收银台列表
    public function cashier_desk_list()
    {
    	$list = $this->model->table('cashier_desk')->fields('id,desk_no,cashier_id')->where('hire_user_id='.$this->user['id'])->findAll();
        $today = date('Y-m-d');
        if($list) {
            foreach ($list as $k => $v) {
                $desk_no = $v['desk_no'];
                $sign = $this->model->table('cashier_attendance as ca')->fields('ca.*,c.name,u.nickname')->join('left join cashier as c on ca.cashier_id=c.id left join user as u on ca.user_id=u.id')->where("ca.hire_user_id =".$this->user['id']." and ca.desk_no like '%$desk_no%' and `work_on_date` = '$today'")->order('ca.id desc')->find();
                if($sign) {
                    $name = $sign['name']!=null?$sign['name']:$sign['nickname'];
                    if($sign['work_off_time']==null) {
                        $list[$k]['status'] = '收银员'.$name.'正在上班';
                    } else {
                        // $list[$k]['status'] = '收银员'.$name.'已经下班';
                        $list[$k]['status'] = '无人上班';
                    }
                } else {
                    $list[$k]['status'] = '无人上班';
                }
            }
        }
    	$this->code = 0;
    	$this->content = $list;
        return;
    }

    //收银台收易明细
    public function cashier_desk_income()
    {
    	$id = Filter::int(Req::args('id'));
    	if(!$id) {
    		$this->code = 1246;
            return;
    	}
    	$date = Filter::str(Req::args('date'));
        $start_time = $date.' 00:00:00';
        $end_time = $date.' 23:59:59';
    	if($date) {
    		$where = "o.desk_id={$id} and o.pay_status=1 and o.pay_time between '{$start_time}' and '{$end_time}'";
    	} else {
            $where = "o.desk_id={$id} and o.pay_status=1";
    	}
    	
    	$list = $this->model->table('order_offline as o')->fields('o.id,o.payment,o.pay_time,o.payable_amount,c.desk_no,o.remark')->join('cashier_desk as c on o.desk_id=c.id')->where($where)->order('pay_time desc')->findAll();
    	if($list) {
    		foreach ($list as $k => $v) {
    			if($v['payment']==6 || $v['payment']==7 || $v['payment']==18) {
    				$list[$k]['payment_name'] = '微信支付';
    			} else {
    				$list[$k]['payment_name'] = '支付宝支付';
    			}
                if($list[$k]['remark']==null) {
                   $list[$k]['remark'] = ''; 
                }
    		}
    	}
    	$sum = $this->model->table('order_offline as o')->fields('SUM(o.payable_amount) as account')->join('cashier_desk as c on o.desk_id=c.id')->where($where)->find();
        $account = $sum['account']==null?'0.00':$sum['account'];
        $this->code = 0;
    	$this->content['list'] = $list;
    	$this->content['sum'] = $account;
        return;
    }

    //商家修改收银员昵称
    public function cashier_edit_name()
    {
        $name = Filter::str(Req::args('name'));
        $id = Filter::int(Req::args('id'));
        if(!$id) {
            $this->code = 1255;
            return;
        }
        $res = $this->model->table('cashier')->data(array('name'=>$name))->where('id='.$id)->update();
        if($res) {
            $this->code = 0;
            return;
        } else {
            $this->code = 1241;
            return;
        }
    }

    //收银员上下班打卡
    public function cashier_sign_in()
    {
        $desk_no = Filter::str(Req::args('desk_no'));

        $cashier = $this->model->table('cashier')->where("user_id=".$this->user['id']." and status=1")->find();
        if(!$cashier) {
            $this->code = 1250;
            return;
        }
        $today = date('Y-m-d');
        $exist1 = $this->model->table('cashier_attendance')->where("user_id=".$this->user['id']." and work_on_date='{$today}' and status=1")->order('id desc')->find();
        // $exist2 = $this->model->table('cashier_attendance')->where("user_id=".$this->user['id']." and work_off_date='{$today}' and status=1")->order('id desc')->findAll();
        if(!$exist1 ) {
            // $type = 1; //上班
            if(!$desk_no) {
                $this->code = 1251;
                return;
            }
            // $exist = $this->model->table('cashier_attendance')->where("user_id=".$this->user['id']." and work_on_date='{$today}' and status=1")->find();
            // if($exist) {
            //     $this->code = 1247;
            //     return;
            // }
            $desk = $this->model->table('cashier_desk')->fields('id')->where("hire_user_id=".$cashier['hire_user_id']." and desk_no like '%$desk_no%'")->find();
            if(!$desk) {
                $this->code = 1252;
                return;
            }
            // $had_used = $this->model->table('cashier_attendance')->where("desk_no like '%$desk_no%' and hire_user_id=".$cashier['hire_user_id']." and work_on_date='$today'")->find();
            $had_used = $this->model->table('cashier_attendance')->where("desk_id = ".$desk['id']." and hire_user_id=".$cashier['hire_user_id']." and work_on_date='$today'")->order('id desc')->find();
            if($had_used) {
                if($had_used['work_off_time']==null) {
                    $this->code = 1261;
                    return;
                }
            }
            $data = array(
            'cashier_id'=>$cashier['id'],
            'user_id'=>$this->user['id'],
            'hire_user_id'=>$cashier['hire_user_id'],
            'desk_no'=>$desk_no,
            'desk_id'=>$desk['id'],
            'work_on_date'=>date('Y-m-d'),
            'work_on_time'=>date('H:i:s'),
            'status'=>1
            );

            $res = $this->model->table('cashier_attendance')->data($data)->insert();
            $sign_time = $data['work_on_time'];
            $type = 'on';
        } else {
            if($exist1['work_off_date']=='') {
                // $type = 2; //下班
                // $exist = $this->model->table('cashier_attendance')->where("user_id=".$this->user['id']." and work_off_date='{$today}' and status=1")->find();
                // if($exist) {
                //     $this->code = 1247;
                //     return;
                // }
                $data = array(
                'work_off_date'=>date('Y-m-d'),
                'work_off_time'=>date('H:i:s'),
                );
                $res = $this->model->table('cashier_attendance')->data($data)->where('id='.$exist1['id'])->update();
                $sign_time = $data['work_off_time'];
                $type = 'off';
            } else { // 第n次上班
                $desk = $this->model->table('cashier_desk')->fields('id')->where("hire_user_id=".$cashier['hire_user_id']." and desk_no like '%$desk_no%'")->find();
                if(!$desk) {
                    $this->code = 1252;
                    return;
                }
                $had_used = $this->model->table('cashier_attendance')->where("desk_id = ".$desk['id']." and hire_user_id=".$cashier['hire_user_id']." and work_on_date='$today'")->order('id desc')->find();
                if($had_used) {
                    if($had_used['work_off_time']==null) {
                        $this->code = 1261;
                        return;
                    }
                }
                $data = array(
                'cashier_id'=>$cashier['id'],
                'user_id'=>$this->user['id'],
                'hire_user_id'=>$cashier['hire_user_id'],
                'desk_no'=>$desk_no,
                'desk_id'=>$desk['id'],
                'work_on_date'=>date('Y-m-d'),
                'work_on_time'=>date('H:i:s'),
                'status'=>1
                );
                $res = $this->model->table('cashier_attendance')->data($data)->insert();
                $sign_time = $data['work_on_time'];
                $type = 'on';
            }
            
        }
        
        if($res) {
            $this->code = 0;
            $this->content['sign_time'] = $sign_time;
            $this->content['on'] = $type;
            return;
        } else {
            $this->code = 1241;
            return;
        }
    }

    //收银员打卡时选择的收银台列表
    public function cashier_desk_sign_list()
    {
        $cashier = $this->model->table('cashier')->fields('hire_user_id')->where("user_id=".$this->user['id']." and status=1")->find();
        if(!$cashier) {
            $this->code = 1250;
            return;
        }
        $list = $this->model->table('cashier_desk')->fields('id,desk_no,cashier_id')->where('hire_user_id='.$cashier['hire_user_id'])->findAll();
        $today = date('Y-m-d');
        if($list) {
            foreach ($list as $k => $v) {
                $sign = $this->model->table('cashier_attendance')->where('hire_user_id='.$cashier['hire_user_id'].' and desk_no='.$v['desk_no']." and `work_on_date` = '$today'")->order('id desc')->find();
                if($sign) {
                    if($sign['work_off_time']==null) {
                        $list[$k]['status'] = '有人在上班';
                    } else {
                        $list[$k]['status'] = '无人在上班';
                    }  
                } else {
                        $list[$k]['status'] = '无人在上班';
                }
            }
        }
        $list = array_values($list);
        $this->code = 0;
        $this->content = $list;
        return;
    }

    //收银员上班记录
    public function cashier_work_log()
    {
        $today = date('Y-m-d');
        $log = $this->model->table('cashier_attendance')->fields('work_on_date,work_off_date,work_on_time,work_off_time')->where("user_id=".$this->user['id']." and work_on_date <'{$today}'")->order('work_on_date desc')->findAll();
        if($log) {
            foreach ($log as $k => $v) {
                if($v['work_off_time']=='') {
                    $hours = (strtotime('23:59:00')-strtotime($v['work_on_time']))/3600;
                    $log[$k]['work_hours'] = ($hours-floor($hours))>=0.5?floor($hours)+0.5:floor($hours);
                } else {
                    $hours = (strtotime($v['work_off_date'].' '.$v['work_off_time'])-strtotime($v['work_on_date'].' '.$v['work_on_time']))/3600;
                    $log[$k]['work_hours'] = ($hours-floor($hours))>=0.5?floor($hours)+0.5:floor($hours);
                }
                if($v['work_off_date']==null) {
                    $log[$k]['work_off_date'] = '';
                }
                if($v['work_off_time']==null) {
                    $log[$k]['work_off_time'] = '';
                }   
            }
        }
        $this->code = 0;
        $this->content = $log;
        return;
    }

    //收银台收款记录添加备注
    public function cashier_income_remark()
    {
        $id = Filter::int(Req::args('id'));
        $remark = Filter::str(Req::args('remark'));
        $res = $this->model->table('order_offline')->data(array('remark'=>$remark))->where('id='.$id)->update();
        if($res) {
            $this->code = 0;
            return;
        } else {
            $this->code = 1241;
            return;
        }
    }

    //收银员收款二维码扫码跳转地址
    public function cashier_qrcode_url()
    {
        $user_id = $this->user['id'];
        $cashier = $this->model->table('cashier')->where('user_id='.$user_id.' and status=1')->find();
        if(!$cashier) {
            $this->code = 1250;
            return;
        }
        $today = date('Y-m-d');
        $sign = $this->model->table('cashier_attendance')->fields('desk_id')->where("user_id=".$user_id." and work_on_date='{$today}'")->find();
        if(!$sign) {
            $this->code = 1253;
            return;
        }
        $url = Url::fullUrlFormat("/ucenter/demo/inviter_id/".$cashier['hire_user_id']."/cashier_id/".$cashier['id']."/desk_id/".$sign['desk_id']);
        $promoter = $this->model->table('district_promoter')->fields('id,user_id,qrcode_no')->where('user_id='.$cashier['hire_user_id'])->find();
        if($promoter['qrcode_no']=='') {
            $no = '0000'.$promoter['id'].rand(1000,9999);
            $this->model->table('district_promoter')->data(array('qrcode_no'=>$no))->where('id='.$promoter['id'])->update();
        }
        $promoter = $this->model->table('district_promoter')->fields('id,user_id,qrcode_no')->where('user_id='.$cashier['hire_user_id'])->find();
        $this->code = 0;
        $this->content['url'] = $url;
        $this->content['cashier_id'] = $cashier['id'];
        $this->content['desk_id'] = $sign['desk_id'];
        $this->content['qrcode_no'] = $promoter?$promoter['qrcode_no']:'0000';
    }

    //收银员打卡状态
    public function cashier_ready_sign() {
        $cashier = $this->model->table('cashier')->where('user_id='.$this->user['id'].' and status=1')->find();
        if(!$cashier) {
            $this->code = 1250;
            return;
        }
        $promoter_user_id = $cashier['hire_user_id'];
        $promoter = $this->model->table('district_promoter')->fields('shop_name')->where('user_id='.$promoter_user_id)->find();
        $seller = $this->model->table('customer')->fields('real_name')->where('user_id='.$promoter_user_id)->find();
        $user = $this->model->table('user')->fields('nickname')->where('id='.$promoter_user_id)->find();
        if(!$promoter || !$seller || !$user){
          $this->code = 1159;
          return;
        }
      
        $shop_name = $promoter['shop_name']!=''?$promoter['shop_name']:($seller['real_name']!=''?$seller['real_name']:$user['nickname']);

        if($shop_name==null){
            $shop_name = "";
        }
        
        $today = date('Y-m-d');
        $log = $this->model->table('cashier_attendance')->fields('work_on_date,work_off_date,work_on_time,work_off_time')->where("user_id=".$this->user['id']." and work_on_date ='{$today}'")->order('id desc')->find();
        $this->code = 0;
        $this->content['shop_name'] = $shop_name;
        $this->content['cashier_name'] = $cashier['name']==null?'':$cashier['name'];
        $this->content['on_duty'] = empty($log)?0:1;
        $this->content['on_duty_time'] = empty($log)?'':$log['work_on_time'];
        $this->content['off_duty'] = empty($log)?0:($log['work_off_time']==''?0:1);
        $this->content['off_duty_time'] = empty($log)?'':($log['work_off_time']==''?'':$log['work_off_time']);
    }

    //收银员我的收款记录
    public function cashier_my_income_log() {
        $cashier = $this->model->table('cashier')->where('user_id='.$this->user['id'].' and status=1')->find();
        if(!$cashier) {
            $this->code = 1250;
            return;
        }
        $id = $cashier['id'];
        $date = Filter::str(Req::args('date'));
        $start_time = $date.' 00:00:00';
        $end_time = $date.' 23:59:59';
        if($date) {
            $where = "cashier_id={$id} and pay_status=1 and pay_time between '{$start_time}' and '{$end_time}'";
        } else {
            $where = "cashier_id={$id} and pay_status=1";
        }
        $list = $this->model->table('order_offline')->fields("id,pay_time as pay_date,payable_amount,remark, case dayofweek(pay_time)  when 1 then '星期日' when 2 then '星期一' when 3 then '星期二' when 4 then '星期三' when 5 then '星期四' when 6 then '星期五' when 7 then '星期六' end as  weekday")->where($where)->order('pay_time desc')->findAll();
         
         if($list) {
            foreach ($list as $k => $v) {
                if($list[$k]['remark']==null) {
                    $list[$k]['remark'] = '';
                }
            }
         }

        $this->code = 0;
        $this->content = $list;
    }

    //商家启用或删除收银员
    public function cashier_manage() {
        $id = Filter::int(Req::args('id'));
        $status = Filter::int(Req::args('status'));
        if(!$id) {
            $this->code = 1246;
            return;
        }
        if($status==1) { //启用
            $this->model->table('cashier')->data(array('status'=>1))->where('id='.$id)->update();
        } elseif($status==0) { //停用
            $this->model->table('cashier')->data(array('status'=>2))->where('id='.$id)->update();
        } else {
            $cashier = $this->model->table('cashier')->where('id='.$id)->find();
            $this->model->table('cashier')->where('user_id='.$cashier['user_id'])->delete();
            $this->model->table('push_message')->where('value='.$id)->delete();
            $this->model->table('customer')->data(array('is_cashier'=>0))->where('user_id='.$cashier['user_id'])->update();
        }
        switch ($status) {
            case 0:
                $state = '已停用';
                break;
            case 1:
                $state = '已启用';
                break;
            case -1:
                $state = '已删除';
                break;    
        }
        $this->code = 0;
        $this->content['state'] = $state;
        return;
    }

    //收银员我的详情
    public function cashier_my_info() {
        $user_id = $this->user['id'];
        $cashier = $this->model->table('cashier')->fields('id,job_no,create_time,hire_user_id')->where('status=1 and user_id='.$user_id)->find();
        if(!$cashier) {
            $this->code = 1250;
            return;
        }
        $promoter = $this->model->table('district_promoter')->fields('shop_name')->where('user_id='.$cashier['hire_user_id'])->find();
        $customer = $this->model->table('customer')->fields('real_name')->where('user_id='.$cashier['hire_user_id'])->find();
        $user = $this->model->table('user')->fields('nickname')->where('id='.$cashier['hire_user_id'])->find();
        $info['shop_name'] = $promoter['shop_name']!=null?$promoter['shop_name']:($customer['real_name']!=null?$customer['real_name']:$user['nickname']);
        $info['create_time'] = $cashier['create_time'];
        $info['job_no'] = $cashier['job_no'];
        $info['id'] = $cashier['id'];
        $this->code = 0;
        $this->content = $info;
    }

    public function voucher_list() {
        $user_id = $this->user['id'];
        $page = Filter::int(Req::args('p'));
        $list = $this->model->table('active_voucher')->where('status=1 and user_id='.$user_id)->order('create_time desc')->findPage($page,10);
        if($list) {
            if(isset($list['data']) && $list['data']!=null) {
                foreach ($list['data'] as $k => $v) {
                    switch ($v['type']) {
                        case 1:
                            $title = '积分券';
                            $amount = 12;
                            break;
                        case 2:
                            $title = '现金券';
                            $amount = 600;
                            break;
                        case 3:
                            $title = '港澳游';
                            $amount = 3988;
                            break;
                        case 4:
                            $title = '商品券';
                            $amount = 2680;
                            break;  
                        default:
                            $title = '积分券';
                            $amount = 12;
                            break;
                    }
                    if(date('Y-m-d',strtotime($v['end_time']))==date("Y-m-d",strtotime("+1 day"))) {
                        $list['data'][$k]['endline'] = '明天即将过期';
                    } else {
                        $list['data'][$k]['endline'] = '';
                    }
                    $list['data'][$k]['alias'] = $amount.'元'.$title;
                }
            }
            unset($list['html']);
        }
        $this->code = 0;
        $this->content = $list;
    }

    public function voucher_detail() {
        $id = Filter::int(Req::args("id"));
        $info = $this->model->table('active_voucher')->where('id='.$id)->find();
        if($info) {
            switch ($info['type']) {
                case 1:
                    $title = '积分券';
                    $amount = 12;
                    break;
                case 2:
                    $title = '现金券';
                    $amount = 600;
                    break;
                case 3:
                    $title = '港澳游';
                    $amount = 3988;
                    break;
                case 4:
                    $title = '商品券';
                    $amount = 2680;
                    break;  
                default:
                    $title = '积分券';
                    $amount = 12;
                    break;
            }
            $info['alias'] = $amount.'元'.$title;
        }
        $this->code = 0;
        $this->content = $info;
    }

    public function voucher_address() {
        $id = Filter::int(Req::args("id"));
        $voucher = $this->model->table('active_voucher')->where('id='.$id)->find();
        if(!$voucher) {
            $this->code = 1262;
            return;
        }
        if($voucher['status']==0) {
            $this->code = 1263;
            return;
        }
        $this->model->table('active_voucher')->data(array('status'=>0))->where('id='.$id)->update();
        $data = array(
            'user_id'=>$this->user['id'],
            'accept_name'=>Filter::str(Req::args('accept_name')),
            'mobile'=>Filter::str(Req::args('mobile')),
            'province'=>Filter::int(Req::args('province')),
            'city'=>Filter::int(Req::args('city')),
            'county'=>Filter::int(Req::args('county')),
            'addr'=>Filter::str(Req::args('addr')),
            'is_default'=>0
            );
        $address_id = $this->model->table("address")->data($data)->insert();
        $gift_product = 2729;
        $gift_num = 1;
        $product = $this->model->table('products as p')->where("p.id = $gift_product")->join("left join goods as g on p.goods_id = g.id")->fields("p.*,g.shop_id")->find();

        $datas['type']=0;
        $datas['order_no'] = Common::createOrderNo();
        $datas['user_id'] = $this->user['id'];
        $datas['payment'] = 1;
        $datas['status'] = 3; 
        $datas['pay_status'] = 1;
        $datas['accept_name'] = $data['accept_name'];
        $datas['phone'] = $data['mobile'];
        $datas['mobile'] = $data['mobile'];
        $datas['province'] = $data['province'];
        $datas['city'] = $data['city'];
        $datas['county'] = $data['county'];
        $datas['addr'] = Filter::text($data['addr']);
        $datas['zip'] = '';
        $datas['payable_amount'] = $product['sell_price']*$gift_num;
        $datas['payable_freight'] = 0;
        $datas['real_freight'] = 0;
        $datas['create_time'] = date('Y-m-d H:i:s');
        $datas['pay_time'] = date("Y-m-d H:i:s");
        $datas['is_invoice'] = 0;
        $datas['handling_fee'] = 0;
        $datas['invoice_title'] = '';
        $datas['taxes'] = 0;
        $datas['discount_amount'] = 0;
        $datas['order_amount'] = $product['sell_price']*$gift_num;
        $datas['real_amount'] = $product['sell_price']*$gift_num;
        $datas['point'] = 0;
        $datas['voucher_id'] = 0;
        $datas['voucher'] = serialize(array());
        $datas['prom_id']=0;
        $datas['admin_remark']="自动创建订单，来自于拉新活动奖励";
        $datas['shop_ids']=$product['shop_id'];
        $order_id =$this->model->table('order')->data($datas)->insert();

        $tem_data['order_id'] = $order_id;
        $tem_data['goods_id'] = $product['goods_id'];
        $tem_data['product_id'] = $product['id'];
        $tem_data['shop_id'] = $product['shop_id'];
        $tem_data['goods_price'] = $product['sell_price'];
        $tem_data['real_price'] = $product['sell_price'];
        $tem_data['goods_nums'] = $gift_num;
        $tem_data['goods_weight'] = $product['weight'];
        $tem_data['prom_goods'] = serialize(array());
        $tem_data['spec'] = serialize($product['spec']);
        $this->model->table("order_goods")->data($tem_data)->insert();

        $this->model->table("products")->where("id=" . $gift_product)->data(array('store_nums' => "`store_nums`-" . $gift_num))->update();//更新库存
        $this->model->table('goods')->data(array('store_nums' => "`store_nums`-" . $gift_num))->where('id=' . $product['goods_id'])->update();

        $order = $this->model->table('order_goods as og')->fields('og.order_id,g.id,g.name,og.goods_price,og.goods_nums,g.img')->join('left join goods as g on og.goods_id=g.id')->where('order_id='.$order_id)->find();
        if($order) {
            $order['img'] = 'https://ymlypt.b0.upaiyun.com'.$order['img'];
        }
        $this->code = 0;
        $this->content = $order;
    }

    public function voucher_user() {
        $id = Filter::int(Req::args("id"));
        $voucher = $this->model->table('active_voucher')->where('id='.$id)->find();
        if(!$voucher) {
            $this->code = 1262;
            return;
        }
        if($voucher['type']==1) {
            $point = $voucher['amount'];
            $this->model->table('customer')->data(array('point_coin'=>"`point_coin`+({$point})"))->where('user_id='.$voucher['user_id'])->update();
            Log::pointcoin_log($point,$voucher['user_id'], '', "积分卡券兑换", 13);
        } elseif($voucher['type']==2) {
            $travel = $this->model->table('active_voucher')->where('user_id='.$voucher['user_id'].' and type=3')->find();
            if($travel) {
                if($travel['status']==1) {
                    $this->code = 1273;
                    return;
                } elseif($travel['status']==2) {
                    $this->code = 1274;
                    return;
                }
            } else {
                $this->code = 1272;
                return;
            }
            $point = $voucher['amount'];
            $this->model->table('customer')->data(array('balance'=>"`balance`+({$point})"))->where('user_id='.$voucher['user_id'])->update();
            Log::balance($point,$voucher['user_id'], '', "现金卡券兑换", 16);
        }
        if($voucher['type']==3) {
            $status = 2; //旅游券专用激活状态
        } else {
            $status = 0;
        }
        $this->model->table('active_voucher')->data(array('status'=>$status))->where('id='.$id)->update();
        $this->code = 0;
        return; 
    }

    public function cashier_off_duty() {
        $id = Filter::int(Req::args("id"));
        $today = date('Y-m-d');
        $cashier = $this->model->table('cashier')->where('id='.$id)->find();
        $sign = $this->model->table('cashier_attendance')->where('hire_user_id='.$this->user['id'].' and cashier_id='.$id." and `work_on_date` = '$today'")->order('id desc')->find();
        $data = array(
                'work_off_date'=>date('Y-m-d'),
                'work_off_time'=>date('H:i:s'),
                );
        $res = $this->model->table('cashier_attendance')->data($data)->where('id='.$sign['id'])->update();
        $this->code = 0;
        return; 
    }

    public function my_benefit_income() {
        $this_month = date('Y-m');
        $last_month = date('Y-m',strtotime('-1 month'));

        $log1 = $this->model->table('benefit_log')->fields('sum(amount) as total')->where("user_id=".$this->user['id']." and type=1")->findAll();
        $log2 = $this->model->table('benefit_log')->fields('sum(amount) as total')->where("user_id=".$this->user['id']." and type=1 and month = '{$last_month}'")->findAll();
        $log3 = $this->model->table('benefit_log')->fields('sum(amount) as total')->where("user_id=".$this->user['id']." and type in(0,2) and month = '{$this_month}'")->findAll();
        $log4 = $this->model->table('benefit_log')->fields('sum(amount) as total')->where("user_id=".$this->user['id']." and type in(0,2) and month = '{$last_month}'")->findAll();
        $log5 = $this->model->table('benefit_log')->fields('sum(amount) as total')->where("user_id=".$this->user['id']." and type=3 and month = '{$last_month}'")->findAll();
    
        $total_income             = $log1[0]['total']==null?0:$log1[0]['total'];
        $last_month_settle_income = $log2[0]['total']==null?0:$log2[0]['total'];
        $this_month_expect_income = $log3[0]['total']==null?0:$log3[0]['total'];
        $last_month_expect_income = $log4[0]['total']==null?0:$log4[0]['total'];
        $last_withdraw_income     = $log5[0]['total']==null?0:$log5[0]['total'];
        $user = $this->model->table('user')->fields('settle_income')->where('id='.$this->user['id'])->find();

        $total_income = $total_income - $user['settle_income'];
        $last_month_settle_income = $last_month_settle_income - $last_withdraw_income;

        $this->code = 0;
        $this->content['total_income'] = $total_income;
        $this->content['last_month_settle_income'] = $last_month_settle_income;
        $this->content['this_month_expect_income'] = $this_month_expect_income;
        $this->content['last_month_expect_income'] = $last_month_expect_income;
        return;
    }

    public function my_order_list() {
        $status = Filter::int(Req::args("status"));
        $page = Filter::int(Req::args("page"));
        if(!$page) {
            $page = 1;
        }
        if(!$status) {
            $status = 1;
        }

        // $user = $this->model->table('user')->fields('adzoneid')->where('id='.$this->user['id'])->find();
        // $where = 'adv_id='.$user['adzoneid'];
        $where = 'user_id='.$this->user['id'];
        switch ($status) {
            case 1:
                $where.=" and type = 2";
                break;
            case 2:
                $where.=" and type in ('0,1')";
                break;
            case 3:
                $where.=" and type = -1";
                break;    
        }
        // var_dump($where);die;
        // $list = $this->model->table('taoke')->fields('id,order_sn,goods_name,order_amount,effect_prediction,create_time,order_status')->where($where)->findPage($page,10);
        $list = $this->model->table('benefit_log')->fields('order_id as id,order_sn,goods_name,price as order_amount,amount as effect_prediction,order_time as create_time')->where($where)->order('order_time desc')->findPage($page,10);
        if($list) {
            if(isset($list['data']) && $list['data']!=null) {
                foreach ($list['data'] as $key => $value) {
                    switch ($value['type']) {
                        case 2:
                            $list['data'][$k]['order_status'] = '订单付款';
                            break;
                        case 1:
                            $list['data'][$k]['order_status'] = '订单结算';
                            break;
                        case 0:
                            $list['data'][$k]['order_status'] = '订单结算';
                            break;
                        case -1:
                            $list['data'][$k]['order_status'] = '订单失效';
                            break;        
                    }
                }
                unset($list['html']);
            } else {
                $list['data'] = [];
            } 
        } else {
            $list['data'] = [];
        }
        $this->code = 0;
        $this->content = $list['data'];
        return;
    }

    public function income_withdraw_balance() {
        $amount = Filter::float(Req::args("amount"));
        // $log = $this->model->table('benefit_log')->fields('sum(amount) as total')->where("user_id=".$this->user['id']." and type=1")->findAll();
        // $total_income = $log[0]['total']==null?0:$log[0]['total'];
        $user = $this->model->table('user')->fields('settle_income')->where('id='.$this->user['id'])->find();
        $total_income = $user['settle_income'];
        // if($amount<100) {
        //     $this->code = 1181;
        //     return;
        // }
        if($amount<=0) {
            $this->code = 1238;
            return;
        }
        if($amount>$total_income) {
            $this->code = 1107;
            return;
        }
        $user = $this->model->table('user')->fields('adzoneid')->where('id='.$this->user['id'])->find();
        $benefit_data = array(
            'user_id'=>$this->user['id'],
            'order_sn'=>'',
            'amount'=>$amount,
            'create_time'=>date('Y-m-d H:i:s'),
            'month'=>date('Y-m'),
            'type'=>3,
            'adzoneid'=>$user['adzoneid']
            );
        $benefit_id = $this->model->table('benefit_log')->data($benefit_data)->insert();
        $this->model->table('customer')->data(array('balance'=>"`balance`+{$amount}"))->where('user_id='.$this->user['id'])->update();
        Log::balance($amount,$this->user['id'],$benefit_id,'结算佣金提现到余额',5);
        $this->model->table('user')->data(array('settle_income'=>"`settle_income`+{$amount}"))->where('id='.$this->user['id'])->update();
        $this->code = 0;
        return;  
    }

    public function my_withdraw_log() {
        $page = Filter::int(Req::args("page"));
        if(!$page) {
            $page = 1;
        }
        $log = $this->model->table('benefit_log')->fields('id,user_id,amount,create_time')->where('user_id='.$this->user['id'].' and type=3')->order('id desc')->findPage($page,10);
        if($log) {
            if(isset($log['data']) && $log['data']!=null) {
                unset($log['html']);
            }
        } else {
            $log['data'] = [];
        }
        $this->code = 0;
        $this->content = $log['data'];
    }
}
?>