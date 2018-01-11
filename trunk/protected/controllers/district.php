<?php

class DistrictController extends Controller {

    public $layout = 'district_layout';
    public $safebox = null;
    private $model = null;
    public $hirer = null;
    public $test = false;//判断是否是演示
    public function init() {
        header("Content-type: text/html; charset=" . $this->encoding);
        $this->model = new Model();
        $this->safebox = Safebox::getInstance();
        $this->user = $this->safebox->get('user');
        if ($this->user == null) {
            $this->user = Common::autoLoginUserInfo();
            $this->safebox->set('user', $this->user);
        }
        $action = Req::args("act");
        $list = explode('_', $action);
        $current = is_array($list)? $list[0]:NULL;
        $this->assign('current',$current);
    }
    public function login(){
        if($this->user['id']!=null){
            $district = $this->model->table("district_shop")->where("owner_id=".$this->user['id'])->findAll();
            $apply_info = $this->model->table("district_apply")->where("user_id = ".$this->user['id']." and status != 1")->findAll();
            if(empty($district)&&empty($apply_info)){
    //            $this->redirect("/index/msg", false, array('type' => "info", "msg" => '我的专区',"content"=>"您暂时还没有入驻专区","redirect_url"=>Url::urlFormat("/district_introduce.html"),'url_name'=>'了解什么是专区'));
    //           exit();
                $this->assign('seo_title',"专区登录");
                $this->assign('current',"district");
                $this->redirect();
            }else{
                $this->assign("district",$district);
                $this->assign("apply_info",$apply_info);
                $this->redirect('/district/district');
            }
        }
        $this->assign('seo_title',"专区登录");
        $this->assign('current',"district");
        $this->redirect();
    }
    public function login_act(){
        $district_id = Filter::int(Req::args("id"));
        if($district_id){
            Cookie::set("district_id",$district_id);
            $this->redirect("/district/district");
        }else{
            $this->redirect("/district/login");
        }
    }
    public function logout(){
        Cookie::clear("district_id");
        Cookie::clear("test");
        $this->redirect("/district/login");
    }
    public function checkRight($actionId) {
        $notcheckRight = array('login', 'logout', 'checkRight', 'noRight','login_act');
        if (in_array($actionId, $notcheckRight))
            return true;
        if (isset($this->user['name']) && $this->user['name'] != null){
                $district_id = Cookie::get('district_id');
                if($district_id){
                    $hirer = Hirer::getHirerInstance ($this->user['id'],$district_id);
                    if(is_object($hirer)){
                        $this->hirer = $hirer;
                        $this->assign("district_name",$this->hirer->name);
                        return true;
                    }else{
                        Cookie::clear('district_id');
                        $this->redirect("/district/login");
                        exit();
                    }
                }
                $test = Cookie::get('test');
                if($test||$this->test){
                    $test_action = array("district","income","record");
                    if(in_array($actionId, $test_action)){
                        $this->test = true;
                        return true;
                    }else{
                       $this->redirect("/index/msg", false, array('type' => "info", "msg" => '我的专区',"content"=>"您暂时还没有入驻专区,不能查看更多啦","redirect_url"=>Url::urlFormat("/ucenter/apply_for_district"),'url_name'=>'马上申请'));
                       exit();
                    }
                }else if(Req::args("test")){
                    Cookie::set('test',true);
                    $this->test = true;
                    echo "<script>window.location.reload();</script>";
                    exit();
                }
        }   
            return false;
    }

    public function noRight() {
        $district_id = Cookie::get('district_id');
        if(!isset($this->user['id'])||$this->user['id']==null){//未登录引发的权限不足
            Cookie::set("url", Url::pathinfo());
            if (Common::checkInWechat()) {
                $wechat = new WechatOAuth();
                $url = $wechat->getRequestCodeURL();
                $this->redirect($url);
                exit;
            }else{
                $this->redirect("/simple/login");
            }
        }else if(!$district_id){
                $this->layout="district_layout";
                $this->assign('seo_title','专区登录');
                $this->redirect("/district/login",false);
        }else{//权限问题
            
        }
    }
    public function income(){
        if($this->test){
              $this->assign('test',true);
        }
        if($this->test){
            $data['frezze_income']=0.00;
            $data['valid_income']=0.00;
            $data['settled_income']=0.00;
        }else{
            $data = $this->hirer->getIncomeStatistics();
        }
        $this->assign('data',$data);
        $this->assign('seo_title','专区收益');
        $this->redirect();
    }
    public function income_settle(){
        $config = Config::getInstance();
        $other = $config->get("district_set");
        $withdraw_fee_rate= isset($other['withdraw_fee_rate'])?$other['withdraw_fee_rate']:0.5;
        $min_withdraw_amount = isset($other['min_withdraw_amount'])?$other['min_withdraw_amount']:0.1;
        $this->assign('withdraw_fee_rate',$withdraw_fee_rate);
        $this->assign('min_withdraw_amount',$min_withdraw_amount);
        $this->assign('seo_title','提现申请');
        $this->redirect();
    }
    
    public function income_settle_submit(){
         if($this->is_ajax_request()){
             $data = Req::args();
             unset($data['con']);
             unset($data['act']);
             echo json_encode($this->hirer->applyDoSettle($data));
         }else{
             echo json_encode(array('status'=>'fail','msg'=>'提交方法错误'));
         }
    }
    public function district(){
        if($this->test){
              $this->assign('test',true);
        }
        $this->assign("seo_title", "专区管理");
        $this->redirect();
    }
    public function district_info(){
        $this->assign("seo_title", "专区信息");
        $this->redirect();
    }
    public function district_info_save(){
        $this->redirect('district_info');
    }
    public function district_subordinate(){
        $data = $this->model->table("district_shop")->where("invite_shop_id =".$this->hirer->id)->fields('name,id,linkman')->findAll();
        if(!empty($data)){
            foreach ($data as $key => $v) {
                $count = $this->model->table("district_promoter")->fields("COUNT(*) as count,hirer_id")->group("hirer_id")->where("hirer_id={$v['id']}")->find();
                $data[$key]['member']=  isset($count['count'])&&$count['count']!=NULL?$count['count']:0;
            }
        }
        $this->assign('data',$data);
        $this->assign("seo_title", "经销商");
        $this->redirect();
    }
    public function district_promoter(){
        if($this->is_ajax_request()){
            $page = Filter::int(Req::args('p'));
            $data = $this->hirer->getMyPrmoter($page);
            if(empty($data)){
                echo json_encode(array('status'=>'fail','msg'=>"数据为空"));
                exit();
            }
            echo json_encode(array('status'=>'success','data'=>$data['data']));
            exit();
        }else{ 
            $this->assign('data',$this->hirer->getMyPrmoter(1));
            $this->assign("seo_title", "代理商");
            $this->redirect();
        }
    }
    public function district_promoter_fire(){
        if($this->is_ajax_request()){
            $id = Filter::int(Req::args('id'));
        }else{
            echo json_encode(array('status'=>'fail','msg'=>'bad request'));
        }
    }
    public function district_promoter_setcode(){
        if($this->is_ajax_request()){
            $code = Filter::sql(Req::args('code'));
            echo json_encode($this->hirer->setPromoterJoinCode($code));
        }else{
            echo json_encode(array('status'=>'fail','msg'=>'bad request'));
        }
        exit();
    }
    public function district_promoter_achievement(){
        if($this->is_ajax_request()){
            $type = Filter::int(Req::args('type'));
            $id = Filter::int(Req::args('id'));
            switch($type){
                case 1:$start_time = date("Y-m-d 00:00:00");
                       $end_time = date("Y-m-d 23:59:59");
                       break;
                case 2:$start_time = date("Y-m-d 00:00:00",strtotime("-1 days"));
                        $end_time = date("Y-m-d 23:59:59",strtotime("-1 days"));
                        break;
                case 3:$start_time = date("Y-m-d 00:00:00",strtotime("-6 days"));
                       $end_time = date("Y-m-d 23:59:59");
                        break;
                case 4:$start_time = date("Y-m-d 00:00:00",strtotime("-29 days"));
                        $end_time = date("Y-m-d 23:59:59");
                        break;
                default :
                    return array('stauts'=>'fail','msg'=>'参数错误');
                    exit();
            }
            $data = $this->hirer->getMyPromoterAchievementData($start_time, $end_time,$id);
            if(empty($data)){
                echo json_encode(array('status'=>'fail','msg'=>"数据不存在"));
                exit();
            }
            echo json_encode(array('status'=>'success','data'=>$data));
            exit();
        }else{
            echo json_encode(array('status'=>'fail','msg'=>'bad request'));
        }
    }            
    public function district_achievement(){
        if($this->is_ajax_request()){
            $type = Filter::int(Req::args('type'));
            switch($type){
                case 1:$start_time = date("Y-m-d 00:00:00");
                       $end_time = date("Y-m-d 23:59:59");
                       break;
                case 2:$start_time = date("Y-m-d 00:00:00",strtotime("-1 days"));
                        $end_time = date("Y-m-d 23:59:59",strtotime("-1 days"));
                        break;
                case 3:$start_time = date("Y-m-d 00:00:00",strtotime("-6 days"));
                       $end_time = date("Y-m-d 23:59:59");
                        break;
                case 4:$start_time = date("Y-m-d 00:00:00",strtotime("-29 days"));
                        $end_time = date("Y-m-d 23:59:59");
                        break;
                default :
                    return array('stauts'=>'fail','msg'=>'参数错误');
                    exit();
            }
            $data = $this->hirer->getMyAchievementData($start_time, $end_time);
            if(empty($data)){
                echo json_encode(array('status'=>'fail','msg'=>"数据不存在"));
                exit();
            }
            echo json_encode(array('status'=>'success','data'=>$data));
            exit();
        }else{ 
            $start_time = date("Y-m-d 00:00:00");
            $end_time = date("Y-m-d 23:59:59");
            $this->assign('data',$this->hirer->getMyAchievementData($start_time, $end_time));
            $this->assign("seo_title", "专区业绩");
            $this->redirect();
        }
    }
    public function record(){
        if($this->test){
              $this->assign('test',true);
        }
        $this->assign('seo_title','记录');
        $this->redirect();
    }
    public function record_income(){
        if($this->is_ajax_request()){
            $page = Filter::int(Req::args('p'));
            $data = $this->hirer->getMyIncomeLog($page);
            if(empty($data)){
                echo json_encode(array('status'=>'fail','msg'=>"数据为空"));
                exit();
            }
            echo json_encode(array('status'=>'success','data'=>$data['data']));
            exit();
        }else{ 
            $this->assign('data',$this->hirer->getMyIncomeLog(1));
            $this->assign("seo_title", "收益记录");
            $this->redirect();
        };
    }
    public function record_settled(){
        if($this->is_ajax_request()){
            $page = Filter::int(Req::args('p'));
            $data = $this->hirer->getSettledHistory($page);
            if(empty($data)){
                echo json_encode(array('status'=>'fail','msg'=>"数据为空"));
                exit();
            }
            echo json_encode(array('status'=>'success','data'=>$data['data']));
            exit();
        }else{ 
            $this->assign('data',$this->hirer->getSettledHistory(1));
            $this->assign("seo_title", "提现记录");
            $this->redirect();
        };
    }
    public function record_sell(){
        if($this->is_ajax_request()){
            $page = Filter::int(Req::args('p'));
            $data = $this->hirer->getMySaleRecord($page);
            if(empty($data)){
                echo json_encode(array('status'=>'fail','msg'=>"数据为空"));
                exit();
            }
            echo json_encode(array('status'=>'success','data'=>$data['data']));
            exit();
        }else{ 
            $this->assign('data',$this->hirer->getMySaleRecord(1));
            $this->assign("seo_title", "销售记录");
            $this->redirect();
        }
    }
    public function getInviteQrcode(){
        $type = Filter::str(Req::args('type'));
        $this->hirer->getInviteQrcode($type);
    }

    public function promoter_code(){
       $data = $this->model->table("promoter_code")->where("user_id =".$this->user['id'])->findAll();
        
       $this->assign('data',$data);
       $this->assign("seo_title", "激活码"); 
       $this->redirect(); 
    }

    public function makePromoterCode(){
        $district = $this->model->table('district_shop')->fields('id,code_num')->where('owner_id='.$this->user['id'])->find();

        $data = $this->model->table("promoter_code")->where("user_id =".$this->user['id'])->findAll();

        if(count($data)>$district['code_num']){
            exit(json_encode(array('status'=>'fail','msg'=>'您的代理商邀请码已用完')));
        }
        $code = Common::makePromoterCode();
        $result = $this->model->table("promoter_code")->data(array('user_id'=>$this->user['id'],'code'=>$code,'status'=>1,'start_date'=>date('Y-m-d H:i:s'),'end_date'=>date('Y-m-d H:i:s',strtotime("+30 days")),'district_id'=>$this->hirer->id))->insert();
        if($result){
            echo json_encode(array('status'=>'success','msg'=>'成功'));
            exit();
        }else{
            exit(json_encode(array('status'=>'fail','msg'=>'失败')));
        }
    }

}