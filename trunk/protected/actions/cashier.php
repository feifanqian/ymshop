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
        $job_no_exist = $this->model->table('cashier')->where('job_no='.$job_no.' and hire_user_id='.$this->user['id'])->find();
        if($job_no_exist) {
        	$this->code = 1240;
            return;
        }
        $cashier = $this->model->table('customer')->fields('user_id')->where('mobile='.$mobile)->find();
        if(!$cashier) {
        	$this->code = 1159;
            return;
        }
        $exist = $this->model->table('cashier')->where("user_id=".$cashier['user_id']." and status=1")->find();
        if($exist) {
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
        	'status'=>0
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
    	$list = $this->model->table('cashier as ca')->fields('cu.real_name,ca.mobile,ca.job_no,ca.id')->join('customer as cu')->where("ca.hire_user_id=".$this->user['id']." and ca.status=1")->findAll();
    	$this->code = 0;
    	$this->content = $list;
        return;
    }

    //收银员收款明细
    public function cashier_detail()
    {
    	$id = Filter::int(Req::args('id'));
    	$date = Filter::str(Req::args('date'));
    	if($date) {
    		$where = "cashier_id={$id} and pay_status=1 and DATE_FORMAT(FROM_UNIXTIME(pay_time),'%Y-%m-%d') = DATE_FORMAT({$date},'%Y-%m-%d')";
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
    	if($date) {
    		$where = "o.desk_id={$id} and o.pay_status=1 and DATE_FORMAT(FROM_UNIXTIME(o.pay_time),'%Y-%m-%d') = DATE_FORMAT({$date},'%Y-%m-%d')";
    	} else {
            $where = "o.desk_id={$id} and o.pay_status=1";
    	}
    	
    	$list = $this->model->table('order_offline as o')->fields('o.payment,o.pay_time,o.payable_amount,c.desk_no')->join('cashier_desk as c on o.desk_id=c.id')->where($where)->order('pay_time desc')->findAll();
    	$sum = $this->model->table('order_offline as o')->fields('SUM(o.payable_amount) as account')->join('cashier_desk as c on o.desk_id=c.id')->where($where)->find();
        $account = empty($sum)?'0.00':$sum['account'];
        $this->code = 0;
    	$this->content['list'] = $list;
    	$this->content['sum'] = $account;
        return;
    }
}
?>