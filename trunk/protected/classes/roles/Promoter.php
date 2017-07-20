<?php

class Promoter extends Object {

    protected $properties = array(); //私有属性
    protected $model;

    private function __construct($user_id) {
        $this->model = new Model();
        
        $base_info = $this->model->table("customer")->fields('user_id,valid_income,frezze_income,settled_income')->where("user_id=$user_id")->find();
        $pay_promoter   = $this->model->table('district_promoter')->where("user_id=$user_id")->fields("id,hirer_id,type")->find();
        $role_type = $pay_promoter ? 2 : 1;
        $base_info['role_type']=$role_type;
        $base_info['can_withdraw']=true;
        if($pay_promoter){
            $base_info['promoter_id']=$pay_promoter['id'];
            $base_info['can_withdraw']=$pay_promoter['type']==4?false:true;
        }
        if (!empty($base_info)) {
            $this->properties = $base_info;
        }
    }

    public static function getPromoterInstance($user_id) {
        $promoterObject = new Promoter($user_id);
        if ($promoterObject->isVailid()) {
            return $promoterObject;
        } else {
            return null;
        }
    }

    public function isVailid() {
        if (empty($this->properties)) {
            return false;
        } else {
            return true;
        }
    }

    public function getIncomeStatistics() {//获取我的收入统计数据
        if ($this->isVailid()) {
            return array('valid_income' => $this->valid_income, 'frezze_income' => $this->frezze_income, 'settled_income' => $this->settled_income);
        } else {
            return array();
        }
    }

     public function getMyIncomeRecord($page=1){
        $log = $this->model->table("promote_income_log")->where("role_id=".$this->user_id." and role_type in (1,2)")->findPage($page,10);
        if(isset($log['html'])){
            unset($log['html']);
        }
        if(empty($log)){
            return array();
        }
        return $log;
    }
 
    public function getQrcodeByGoodsId($goods_id, $show_img = true) {//根据推广商品获取二维码,返回一张图
        $result  = Common::getQrcodeFlag($promoter->user_id,$goods_id);
        if($result['status']=='success'){
            if ($show_img) {
                $qrCode = new QrCode();
                $qrCode->setText($result['url'])
                        ->setSize(300)
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
        }
        return $result;
    }

    public function getMyPromoterGoodsList() {//获取我的推广商品列表
    }

    public function getMyAchievementData($start, $end) {//我的业绩 
        if (strtotime($start) > strtotime($end)) {
            return false;
        }
        $record = $this->model->table("district_sales as ds")
                ->where("record_time>='$start' and record_time<='$end' and promoter_id=" . $this->id)
                ->fields('amount,record_time as time')
                ->order('record_time desc')
                ->findAll();
        if (date("Y-m-d", strtotime($start)) == date("Y-m-d", strtotime($end))) {
            Common::formatDataToShowInChart($start, $end, $record, 'hour');
        } else {
            Common::formatDataToShowInChart($start, $end, $record, 'day');
        }
        return $record;
    }

    public function getMyHirerInfo() {//获取我的雇主信息
    }

    public function getMySaleRecord($page = 1) {
        $record = $this->model->table('promote_sale_log as psl')
                ->join("left join goods as g on psl.goods_id = g.id")
                ->where("psl.contributor_user_id =" . $this->user_id)
                ->fields("psl.*,g.img,g.name")
                ->order("psl.record_date desc")
                ->findPage($page, 10);
        if (empty($record)) {
            return array();
        }
        if (isset($record['html'])) {
            unset($record['html']);
        }
        foreach ($record['data'] as $k => $v) {
            $line_data = array();
            $line_data['id'] = $v['id'];
            $line_data['weekday'] = Common::formatTimeToShow($v['record_date']);
            $line_data['month'] = date('m-d', strtotime($v['record_date']));
            $line_data['img_url'] = Url::urlFormat("@" . $v['img']);
            $line_data['name'] = $v['name'];
            $line_data['unit_price'] = $v['unit_price'];
            $line_data['sell_num'] = $v['goods_nums'];
            $line_data['amount'] = $v['amount'];
            $income = 0.00;
            if($v['beneficiary_one_user_id']==$this->user_id){
                $income =$v['beneficiary_one_income'];
            }else if($v['beneficiary_two_user_id']==$this->user_id){
                $income =$v['beneficiary_two_income'];
            }
            
            $line_data['income'] = $income;
            $record['data'][$k] = $line_data;
        }
        return $record;
    }

    public function getSettledHistory($page) {
        $history = $this->model->table('district_withdraw')
                ->where('role_type in (1,2) and role_id = ' . $this->user_id)
                ->order('apply_time desc')
                ->findPage($page, 10);
        if (empty($history)) {
            return array();
        }
        if (isset($history['html'])) {
            unset($history['html']);
        }
        $line_data = array('id' => 1, 'weekday' => '周一', 'month' => '12-03', 'status' => 'success', 'amount' => '1.22', 'settle_type' => '提现到金点账号', 'status_tips' => '已转账');
        $status = array('-1' => "info", '0' => 'waiting', '1' => 'success');
        $status_tips = array('-1' => '未通过', '0' => '待处理', '1' => '已转账');
        $type = array('1' => '提现至账户余额', '2' => '提现到银行卡');
        foreach ($history['data'] as $k => $v) {
            $line_data = array();
            $line_data['id'] = $v['id'];
            $line_data['weekday'] = Common::formatTimeToShow($v['apply_time']);
            $line_data['month'] = date('m-d', strtotime($v['apply_time']));
            $line_data['status_icon'] = $status["{$v['status']}"];
            $line_data['amount'] = $v['withdraw_amount'];
            $line_data['settle_type'] = $type["{$v['withdraw_type']}"];
            $line_data['settle_type_id'] = $v['withdraw_type'];
            $line_data['status_tips'] = $status_tips["{$v['status']}"];
            $line_data['status'] = $v['status'];
            $history['data'][$k] = $line_data;
        }
        return $history;
    }

    public function applyDoSettle($data) {//提交结算申请
        $count = $this->model->table('district_withdraw')->where('role_id=' . $this->user_id . " and status=0 and role_type in (1,2)")->count();
        if ($count > 0) {
            return array('status' => 'fail', 'msg' => '抱歉！您还有未处理完的提现请求，请等待系统处理完成后再提交', 'msg_code' => 1137);
        }
        if(!$this->can_withdraw){
             return array('status' => 'fail', 'msg' => '抱歉！您是官方推广员，不能申请提现哦', 'msg_code' => 1152);
        }
        $data = Filter::inputFilter($data);
        $config_all = Config::getInstance();
        $set = $config_all->get('district_set');
        $min_withdraw_amount = $set['min_withdraw_amount'];
        if (!isset($data['type'])) {
            return array('status' => 'fail', 'msg' => '提交的数据错误', 'msg_code' => 1000);
        } else if ($data['type'] == 1) {
            if (!isset($data['amount']) || $data['amount'] < $min_withdraw_amount) {
                return array('status' => 'fail', 'msg' => '提现金额不能小于' . $min_withdraw_amount, 'msg_code' => 1135);
            } else if ($data['amount'] > $this->valid_income) {
                return array('status' => 'fail', 'msg' => '提现金额大于可用收益', 'msg_code' => 1134);
            }
            $sql_data['withdraw_type'] = 1;
        } else if ($data['type'] == 2) {
            if (!isset($data['amount']) || $data['amount'] < $min_withdraw_amount) {
                return array('status' => 'fail', 'msg' => '提现金额不能小于' . $min_withdraw_amount, 'msg_code' => 1135);
            } else if ($data['amount'] > $this->valid_income) {
                return array('status' => 'fail', 'msg' => '提现金额大于可用收益', 'msg_code' => 1134);
            }
            if (!isset($data['bank_name']) || $data['bank_name'] == '' || !isset($data['card_number']) || $data['card_number'] == '' || !isset($data['bank_account_name']) || $data['bank_account_name'] == '' || !isset($data['province']) || $data['province'] == '' || !isset($data['city']) || $data['city'] == '') {
                return array('status' => 'fail', 'msg' => '请完善银行卡信息', 'msg_code' => 1000);
            }
            $sql_data['withdraw_type'] = 2;
            $sql_data['card_info'] = serialize(array('bank_name' => $data['bank_name'], 'card_number' => $data['card_number'], 'bank_account_name' => $data['bank_account_name'], 'province' => $data['province'], 'city' => $data['city']));
        }
        $sql_data['withdraw_no'] = "w" . Common::createOrderNo();
        $sql_data['withdraw_amount'] = $data['amount'];
        $sql_data['apply_time'] = date("Y-m-d H:i:s");
        $sql_data['role_type'] = $this->role_type;
        $sql_data['role_id'] = intval($this->user_id);
        $sql_data['status'] = 0;
        $id = $this->model->table('district_withdraw')->data($sql_data)->insert();
        if ($id) {
            return array('status' => 'success', 'msg' => '成功');
        } else {
            return array('status' => 'fail', 'msg' => '数据库错误', 'msg_code' => 1005);
        }
    }

    /*
     * 推广者邀请推广者二维码
     */
    public function getInviteQR4Promoter() {
        if($this->role_type==1){
            return false;
        }
        $url = Url::fullUrlFormat("/ucenter/becomepromoter/reference/{$this->promoter_id}/invitor_role/promoter");
        $qrCode = new QrCode();
        $logo = APP_ROOT."static/images/logo1.png";
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
    }
    
    /*
     * 获取我的邀请列表
     */
    public function getMyInviteList($page=1){
        $record = $this->model->table('district_order as do')
                ->join('left join user as u on do.user_id = u.id')
                ->fields('u.avatar,u.nickname,do.create_date')
                ->where("do.pay_status =1 and do.invitor_role = 'promoter' and do.invitor_id=".$this->id)
                ->order("do.pay_date desc")
                ->findPage($page, 10);
        if (empty($record)) {
            return array();
        }
        if (isset($record['html'])) {
            unset($record['html']);
        }
        return $record;
    }

}
