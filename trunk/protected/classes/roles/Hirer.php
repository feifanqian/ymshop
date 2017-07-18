<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Hirer extends Object{
    protected $properties =array();//私有属性
    protected $model ;
    
    private function __construct($user_id,$district_id) {
          $this->model = new Model('');
          $result = $this->model->table("district_shop")->where("owner_id=$user_id and id=$district_id")->find();
          if(!empty($result)){
              $this->properties = $result;
          }
    }
    
    public static function getHirerInstance($user_id,$district_id){//获取一个雇主实例，即商户
        $promoterObject = new Hirer($user_id,$district_id);
        if($promoterObject->isVailid()){
            return $promoterObject;
        }else{
            return null;
        }
    }
    public function isVailid(){//判断是否有效
        if(empty($this->properties)){
            return false;
        }else{
            return true;
        }
    }
    public function getPropertys() {
        return $this->properties;
    }
    public function getIncomeStatistics(){//获取商户的收支统计
       if($this->isVailid()){
           return array('valid_income'=>$this->valid_income,'frezze_income'=>$this->frezze_income,'settled_income'=>$this->settled_income);
       }else{
           return array();
       }
    }
    
    public function getMyIncomeLog($page=1){//获取收入记录，收入应该包括：1-小区推广产品的营业额的3%，2-拓展推广小区的收入的1%，3-拓展一个小区直接加10000*10%
        $log = $this->model->table("promote_income_log")->where("role_id=".$this->id." and role_type =3")->findPage($page,10);
        if(isset($log['html'])){
            unset($log['html']);
        }
        if(empty($log)){
            return array();
        }
        return $log;
    }
   
    public function getMyPrmoter($page=1){//查看我的推广者
        $promoter_list = $this->model->table("district_promoter as dp")
                ->join("left join user as u on dp.user_id = u.id left join customer as c on dp.user_id = c.user_id")
                ->fields("dp.id,dp.user_id,dp.join_time,u.avatar,u.nickname,c.real_name,c.sex")
                ->where('hirer_id ='.$this->id)
                ->findPage($page,10);
        if(empty($promoter_list)){
          return array();
        }
        if(isset($promoter_list['html'])){
            unset($promoter_list['html']);
        }
        if(!empty($promoter_list)){
        foreach ($promoter_list['data'] as $k => $v){
                $line_data['id']=$v['id'];
                $lint_data['join_time']=$v['join_time'];
                if($v['avatar']==''){
                    $line_data['avatar']="http://errorpage.b0.upaiyun.com/buy-d-404";
                }else{
                    $line_data['avatar']=Url::urlFormat('@'.$v['avatar']);
                }
                if(isset($v['real_name'])&&$v['real_name']!=''){
                    $line_data['name']=$v['real_name'];
                }else if(isset($v['nickname'])&&$v['nickname']!=''){
                    $line_data['name']=$v['nickname'];
                }else{
                    $line_data['name']='未知推广者';
                }
                $line_data['sex']=$v['sex'];
                $promoter_list['data'][$k]=$line_data;
            }
        }
        return $promoter_list;        
    }
    
    public function getMySubordinate(){//我的下级小区信息
        $data = $this->model->table("district_shop")->where("invite_shop_id =".$this->id)->fields('id,name,location,linkman')->findAll();
        return $data;
    }
    
    public function getMyAchievementData($start,$end){//我的业绩 
        if(strtotime($start)>strtotime($end)){
            return false;
        }
        $record = $this->model->table("promote_sale_log as psl")
                ->where("record_date>='$start' and record_date<='$end' and beneficiary_three_id=".$this->id)
                ->fields('amount,record_date as time')
                ->order('record_date desc')
                ->findAll();
        if(date("Y-m-d",strtotime($start))==date("Y-m-d",strtotime($end))){
            Common::formatDataToShowInChart($start,$end,$record,'hour');
        }else{
            Common::formatDataToShowInChart($start,$end,$record,'day');
        }
        return $record;
    }
    public function getMyPromoterAchievementData($start,$end,$promoter_id){//我的推广者业绩 
        if(strtotime($start)>strtotime($end)){
            return false;
        }
        $record = $this->model->table("district_sales as ds")
                ->where("record_time>='$start' and record_time<='$end' and hirer_id=".$this->id." and promoter_id =$promoter_id")
                ->fields('amount,record_time as time')
                ->order('record_time desc')
                ->findAll();
        if(date("Y-m-d",strtotime($start))==date("Y-m-d",strtotime($end))){
            Common::formatDataToShowInChart($start,$end,$record,'hour');
        }else{
            Common::formatDataToShowInChart($start,$end,$record,'day');
        }
        return $record;
    }
    public function setPromoterJoinCode($code){
        if($code==''){
            return array('status'=>'fail','msg'=>'口令不能为空');
        }else{
            if($code==$this->unique_code){
                return array('status'=>'fail','msg'=>'新口令与旧口令相同');
            }
            $isset  = $this->model->table('district_shop')->where("unique_code ='{$code}'")->find();
            if(!empty($isset)){
                return array('status'=>'fail','msg'=>'口令已被占用');
            }else{
                $isOk = $this->model->table('district_shop')->data(array('unique_code'=>$code))->where('id='.$this->id)->update();
                if($isOk==1){
                   return array('status'=>'success','msg'=>'成功','code'=>$code);
                }else{
                   return array('status'=>'fail','msg'=>'数据库错误');
                }
            }
        }
    }
    public function getMySaleRecord($page=1){
      $record = $this->model->table('promote_sale_log as psl')
                ->join("left join goods as g on psl.goods_id = g.id")
                ->where("psl.beneficiary_three_id =" . $this->id)
                ->fields("psl.*,g.img,g.name")
                ->order("psl.record_date desc")
                ->findPage($page, 10);
      if(empty($record)){
          return array();
      }
      if(isset($record['html'])){
          unset($record['html']);
      }
      foreach($record['data'] as $k => $v){
          $line_data = array();
          $line_data['id']=$v['id'];
          $line_data['weekday']=Common::formatTimeToShow($v['record_date']);
          $line_data['month']=date('m-d',strtotime($v['record_date']));
          $line_data['img_url']=Url::urlFormat("@".$v['img']);
          $line_data['name']=$v['name'];
          $line_data['unit_price']=$v['unit_price'];
          $line_data['sell_num']=$v['goods_nums'];
          $line_data['amount']= $v['amount'];
          $line_data['income']=$v['beneficiary_three_income'];
          $record['data'][$k]=$line_data;
      }
      return $record;
          
    }
    public function getSettledHistory($page){
        $history = $this->model->table('district_withdraw')
                ->where('role_type = 3 and role_id = '.$this->id)
                ->order('apply_time desc')
                ->findPage($page,10);
        if(empty($history)){
          return array();
        }
        if(isset($history['html'])){
            unset($history['html']);
        }
        $line_data = array('id'=>1,'weekday'=>'周一','month'=>'12-03','status'=>'success','amount'=>'1.22','settle_type'=>'提现到账号余额','status_tips'=>'已转账');
        $status=array('-1'=>"info",'0'=>'waiting','1'=>'success');
        $status_tips=array('-1'=>'<span class="red">未通过</span>','0'=>'<span class="green">待处理</spam>','1'=>'已转账');
        $type=array('1'=>'提现至账户余额','2'=>'提现到银行卡');
        foreach($history['data'] as $k => $v){
          $line_data = array();
          $line_data['id']=$v['id'];
          $line_data['weekday']=Common::formatTimeToShow($v['apply_time']);
          $line_data['month']=date('m-d',strtotime($v['apply_time']));
          $line_data['status_icon']=$status["{$v['status']}"];
          $line_data['amount']= $v['withdraw_amount'];
          $line_data['settle_type']=$type["{$v['withdraw_type']}"];
          $line_data['settle_type_id']=$v['withdraw_type'];//给app用
          $line_data['status_tips_html']=$status_tips["{$v['status']}"];
          $line_data['status']=$v['status'];//给app用
          $history['data'][$k]=$line_data;
      }
      return $history;
    }
    
    public function applyDoSettle($data){//提交结算申请
        $count = $this->model->table('district_withdraw')->where('role_type=3 and role_id ='.$this->id." and status=0")->count();
        $count=0;
        if($count>0){
             return array('status'=>'fail','msg'=>'抱歉！您还有未处理完的提现请求，请等待系统处理完成后再提交','msg_code'=>1137);
        }
        $data = Filter::inputFilter($data);
        $config_all = Config::getInstance();
        $set = $config_all->get('district_set');
        $min_withdraw_amount = $set['min_withdraw_amount'];
        if(!isset($data['type'])){
            return array('status'=>'fail','msg'=>'提交的数据错误','msg_code'=>1000);
        }else if($data['type']==1){
            
            if(!isset($data['amount'])||$data['amount']<$min_withdraw_amount){
                 return array('status'=>'fail','msg'=>'提现金额不能小于'.$min_withdraw_amount,'msg_code'=>1135);
            }else if($data['amount']>$this->valid_income){
                 return array('status'=>'fail','msg'=>'提现金额大于可用收益','msg_code'=>1134);
            }
            $sql_data['withdraw_type']=1;
        }else if($data['type']==2){
            if(!isset($data['amount'])||$data['amount']<$min_withdraw_amount){
                 return array('status'=>'fail','msg'=>'提现金额不能小于'.$min_withdraw_amount,'msg_code'=>1135);
            }else if($data['amount']>$this->valid_income){
                 return array('status'=>'fail','msg'=>'提现金额大于可用收益','msg_code'=>1134);
            }
            if(!isset($data['bank_name'])||$data['bank_name']==''||!isset($data['card_number'])||$data['card_number']==''||!isset($data['bank_account_name'])||$data['bank_account_name']==''||!isset($data['province'])||$data['province']==''||!isset($data['city'])||$data['city']==''){
                return array('status'=>'fail','msg'=>'请完善银行卡信息','msg_code'=>1000);
            }
            $sql_data['withdraw_type']=2;
            $sql_data['card_info']=  serialize(array('bank_name'=>$data['bank_name'],'card_number'=>$data['card_number'],'bank_account_name'=>$data['bank_account_name'],'province'=>$data['province'],'city'=>$data['city']));
        }else{
            return array('status'=>'fail','msg'=>'提交的数据错误','msg_code'=>1000);
        }
        $sql_data['withdraw_no']="w".Common::createOrderNo();
        $sql_data['withdraw_amount']=$data['amount'];
        $sql_data['apply_time']=date("Y-m-d H:i:s");
        $sql_data['role_type']=3;
        $sql_data['role_id']=$this->id;
        $sql_data['status']=0;
        $id = $this->model->table('district_withdraw')->data($sql_data)->insert();
        if($id){
            return array('status'=>'success','msg'=>'成功');
        }else{
            return array('status'=>'fail','msg'=>'数据库错误','msg_code'=>1005);
        }
    }
    public function getInviteQrcode($type){
        if($type=='shop'){
            $url = Url::getHost()."/ucenter/apply_for_district/reference/".$this->id;
        }else if($type=='promoter'){
            $url = Url::getHost()."/ucenter/becomepromoter/reference/".$this->id." ";
        }
        $logo = APP_ROOT."static/images/logo1.png";
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
    }
}