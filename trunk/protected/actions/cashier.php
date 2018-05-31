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
        $cashier = $this->model->table('customer')->fields('user_id')->where('mobile='.$mobile)->find();
        if(!$cashier) {
        	$this->code = 1159;
            return;
        }
        $exist = $this->model->table('cashier')->where("user_id=".$cashier['user_id']." and hire_user_id !=".$this->user['id']." and status=1")->find();
        if($exist) {
        	$this->code = 1243;
            return;
        }
        $has_be = $this->model->table('cashier')->where("user_id=".$cashier['user_id']." and hire_user_id =".$this->user['id']." and status=1")->find();
        if($has_be) {
            $this->code = 1254;
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
    	if($res) {
        	$this->code = 0;
            return;
        } else {
            $this->code = 1241;
            return;
        }
    }

    //收银员列表
    public function cashier_list()
    {
    	$list = $this->model->table('cashier as ca')->fields('cu.real_name,ca.name,ca.mobile,ca.job_no,ca.id')->join('customer as cu on cu.user_id=ca.user_id')->where("ca.hire_user_id=".$this->user['id']." and ca.status=1")->findAll();
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
    	$list = $this->model->table('order_offline')->fields("pay_time as pay_date,payable_amount, case dayofweek(pay_time)  when 1 then '星期日' when 2 then '星期一' when 3 then '星期二' when 4 then '星期三' when 5 then '星期四' when 6 then '星期五' when 7 then '星期六' end as  weekday")->where($where)->order('pay_time desc')->findAll();

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
        $exist1 = $this->model->table('cashier_attendance')->where("user_id=".$this->user['id']." and work_on_date='{$today}' and status=1")->find();
        $exist2 = $this->model->table('cashier_attendance')->where("user_id=".$this->user['id']." and work_off_date='{$today}' and status=1")->find();
        if(!$exist1 && !$exist2) {
            $type = 1; //上班
            if(!$desk_no) {
                $this->code = 1251;
                return;
            }
            $exist = $this->model->table('cashier_attendance')->where("user_id=".$this->user['id']." and work_on_date='{$today}' and status=1")->find();
            if($exist) {
                $this->code = 1247;
                return;
            }
            $desk = $this->model->table('cashier_desk')->fields('id')->where("hire_user_id=".$cashier['hire_user_id']." and desk_no=".$desk_no)->find();
            if(!$desk) {
                $this->code = 1252;
                return;
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
        } elseif($exist1 && empty($exist2)) {
            $type = 2; //下班
            $exist = $this->model->table('cashier_attendance')->where("user_id=".$this->user['id']." and work_off_date='{$today}' and status=1")->find();
            if($exist) {
                $this->code = 1247;
                return;
            }
            $data = array(
            'work_off_date'=>date('Y-m-d'),
            'work_off_time'=>date('H:i:s'),
            );
            $res = $this->model->table('cashier_attendance')->data($data)->where('id='.$exist1['id'])->update();
            $sign_time = $data['work_off_time'];
        } else {
            $this->code = 1247;
            return;
        }
        
        if($res) {
            $this->code = 0;
            $this->content['sign_time'] = $sign_time;
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
        $list = $this->model->table('cashier_desk')->fields('id,desk_no,cashier_id')->where('hire_user_id='.$cashier['hire_user_id'])->findAll();
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
                    $log[$k]['work_hours'] = 8;
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
        $log = $this->model->table('cashier_attendance')->fields('work_on_date,work_off_date,work_on_time,work_off_time')->where("user_id=".$this->user['id']." and work_on_date ='{$today}'")->find();
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
        $list = $this->model->table('order_offline')->fields("pay_time as pay_date,payable_amount, case dayofweek(pay_time)  when 1 then '星期日' when 2 then '星期一' when 3 then '星期二' when 4 then '星期三' when 5 then '星期四' when 6 then '星期五' when 7 then '星期六' end as  weekday")->where($where)->order('pay_time desc')->findAll();

        $this->code = 0;
        $this->content = $list;
    }
}
?>