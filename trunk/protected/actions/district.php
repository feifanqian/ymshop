 <?php

class DistrictAction extends Controller {

    public $model = null;
    public $user = null;
    public $code = 1000;
    public $content = NULL;
    // public $hirer = NULL;

    public function __construct() {
        $this->model = new Model();
    }
    //调用需要实例化hirer的方法时
    //需要调用实例化hirer的方法需要设置为protected
    public function __call($name, $args = null) {
        if($this->setHirer()){
            return call_user_func_array([$this, $name], $args);
        }
        return false;
    }
    /*
     * 实例化并设置hirer
     */
    public function setHirer() {
        // $district_id = Filter::int(Req::args('district_id'));
        $district = $this->model->table('district_shop')->where('owner_id='.$this->user['id'])->find();
        $district_id = $district?$district['id']:1;
        $user_id = $this->user['id'];
        if ($district_id) {
            $district_shop = Hirer::getHirerInstance($user_id, $district_id);
            if (is_object($district_shop)) {
                $this->hirer = $district_shop;
                return true;
            } else {
                $this->code = 1132;
                return false;
            }
        } else {
            $this->code = 1133;
            return false;
        }
    }
    /*
     * 申请入驻小区接口
     */
    public function applyForDistrict() {
        $name = Filter::str(Req::args('name'));
        $location = Filter::sql(Req::args('location'));
        $linkman = Filter::str(Req::args('linkman'));
        $linkmobile = Filter::str(Req::args('linkmobile'));
        $reference = Filter::int(Req::args('reference'));

        if (strlen($name) > 20 || strlen($name) < 3) {
            $this->code = 1128;
            return;
        }
        if ($location == NULL || $linkman == NULL || $linkmobile = NULL) {
            $this->code = 1129;
            return;
        }
        if ($reference) {
            $isset = $this->model->table("district_shop")->where("id=$reference")->find();
            if ($isset) {
                $data['reference'] = $reference;
            } else {
                $this->code = 1130;
                return;
            }
        }
        $data['name'] = $name;
        $data['location'] = $location;
        $data['linkman'] = $linkman;
        $data['linkmobile'] = $linkmobile;
        $data['apply_time'] = date("Y-m-d H:i:s");
        $data['user_id'] = $this->user['id'];
        $data['pay_status'] = 0;
        $data['status'] = 0;
        $result = $this->model->table("district_apply")->data($data)->insert();
        if ($result) {
            $this->code = 0;
            $this->content['id'] = $result;
        } else {
            $this->code = 1005;
            return;
        }
    }

    /*
     * 获取小区列表，包括已开通小区，未支付小区，申请中小区
     */
    public function getDistrictList() {
        $district = $this->model->table("district_shop")->where("owner_id=" . $this->user['id'])->findAll();
        $apply_info = $this->model->table("district_apply")->where("user_id=" . $this->user['id'] . " and status != 1")->findAll();
        if (empty($district) && empty($apply_info)) {
            $this->code = 1131;
            return;
        } else {
            $this->code = 0;
            $this->content['district'] = $district;
            $this->content['pending_district'] = $apply_info;
        }
    }

    /*
     * 获取小区的收益统计
     */
    protected function getDistrictInfo() {
        $this->code = 0;
        $this->content = $this->hirer->getPropertys();
    }

    /*
     * 获取小区收益记录
     */
    protected function getDistrictIncomeRecord() {
        $page = Filter::int(Req::args("page"));
        $this->code =0;
        $this->content=$this->hirer->getMyIncomeLog($page);
    }
    /*
     * 获取小区销售记录
     */
    protected  function getDistrictSaleRecorde(){
        $page = Filter::int(Req::args("page"));
        $this->code =0;
        $this->content=$this->hirer->getMySaleRecord($page);
    }
    /*
     * 获取小区提现记录
     */
    protected function getDistrictWithdrawRecord(){
        $page = Filter::int(Req::args("page"));
        $this->code =0;
        $this->content=$this->hirer->getSettledHistory($page);
    }
    /*
     * 提交结算申请(提现)
     */
    protected function applyDoSettle(){
        $data = Filter::inputFilter(Req::args());
        $result = $this->hirer->applyDoSettle($data);
        if($result['status']=='success'){
            $this->code =0;
        }else{
            $this->code = $result['msg_code'];
        }
    }
    /*
     * 获取推广员列表
     */
    protected function getPromoterList(){
        $page = Filter::int(Req::args('page'));
        $this->code = 0;
        $this->content = $this->hirer->getMyPrmoter($page);
    }
    
    
    /*
     * 推广业绩图标数据
     */
    protected function districtAchievement(){
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
                    $this->code = 1000;
                    exit();
                    break;
            }
            $data = $this->hirer->getMyAchievementData($start_time, $end_time);
            if(empty($data)){
                $this->code = 1005;
                return;
            }else{
                $this->code = 0;
                $this->content['data']=$data;
            }
    }

    // 配置头像
    public function setPicture() {
        $upfile_path = Tiny::getPath("uploads") . "/head/";
        // $upfile_path = Tiny::getPath("uploads");
        $upfile_url = preg_replace("|" . APP_URL . "|", '', Tiny::getPath("uploads_url") . "head/", 1);
        // $upfile_url = preg_replace("|^" . APP_URL . "|", '', Tiny::getPath("uploads_url"));
        $upfile = new UploadFile('picture', $upfile_path, '500k', '', 'hash', $this->user['id']);
        $upfile->save();
        $info = $upfile->getInfo();
        $result = array();

        if ($info[0]['status'] == 1) {
            $result = array('error' => 0, 'url' => $upfile_url . $info[0]['path']);
            $image_url = $upfile_url . $info[0]['path'];
            $image = new Image();
            $image->suffix = '';
            $image->thumb(APP_ROOT . $image_url, 100, 100);
            $model = new Model('district_promoter');
            $picture = "http://" . $_SERVER['HTTP_HOST'] . '/' . $image_url;
            // $picture = "https://ymlypt.b0.upaiyun.com/" . $image_url;
            $model->data(array('picture' => $picture))->where("user_id=" . $this->user['id'])->update();
            $this->code = 0;
        } else {
            $this->code = 1099;
        }
    }

    //生成激活码
    public function makePromoterCode(){
        $code = Common::makePromoterCode();
        $district = $this->model->table('district_shop')->fields('id,code_num')->where('owner_id='.$this->user['id'])->find();
        if(!$district){
           $this->code = 1132;
           return; 
        }
        $data = $this->model->table("promoter_code")->where("user_id =".$this->user['id'])->findAll();
        if(count($data)>=$district['code_num']){  //默认每个经销商只有100条激活码
            $this->code = 1172;
            return;
        }
        $district_id = $district['id'];
        $result = $this->model->table("promoter_code")->data(array('user_id'=>$this->user['id'],'code'=>$code,'status'=>1,'start_date'=>date('Y-m-d H:i:s'),'end_date'=>date('Y-m-d H:i:s',strtotime("+90 days")),'district_id'=>$district_id))->insert();
        if($result){
            $return = $this->model->table('promoter_code')->where('id='.$result)->find();
            $this->code = 0;
            $this->content = $return;
        }else{
            $this->code = 1171;
            return;
        }
    }

    //输入激活码
    public function inputCode(){
        $code = Filter::str(Req::args('code'));
        $type = Filter::int(Req::args('type'));
        $rules = array('code:required:激活码不能为空!');
        $info = Validator::check($rules);
        if (!$code) {
            $this->code = 1173;
            return;
        }else{
            $exist = $this->model->table('district_promoter')->where('user_id='.$this->user['id'])->find();
            if($exist){
                $this->code = 1174;
                return;
            }
            $promoter_code = $this->model->table('promoter_code')->where("code ='{$code}'")->find();
            if(!$promoter_code){
                $this->code = 1175;
                return;
            }
            if(time()>strtotime($promoter_code['end_date'])){
                $this->code = 1176;
                return;
            }
            if($promoter_code['status']==0){
                $this->code = 1177;
                return;
            }
            $result = $this->model->table('district_promoter')->data(array('user_id'=>$this->user['id'],'type'=>1,'invitor_id'=>$promoter_code['user_id'],'create_time'=>date('Y-m-d H:i:s'),'join_time'=>date('Y-m-d H:i:s'),'hirer_id'=>$promoter_code['district_id'],'shop_type'=>$type))->insert();
            $point = 3600.00;
            $this->model->table('customer')->data(array('point_coin'=>"`point_coin`+({$point})"))->where('user_id='.$this->user['id'])->update();
            Log::pointcoin_log($point,$this->user['id'], '', "激活码激活升级为代理商积分赠送", 5);
            $invite = $this->model->table('invite')->where('invite_user_id='.$this->user['id'])->find();
            if(!$invite){
                $this->model->table('invite')->data(array('user_id'=>$promoter_code['user_id'],'invite_user_id'=>$this->user['id'],'from'=>'jihuo','district_id'=>$promoter_code['district_id'],'createtime'=>time()))->insert();
            }
            if($result){
                $this->model->table('promoter_code')->data(array('status'=>0))->where("code ='{$code}'")->update();
                $promoter = $this->model->table('district_promoter')->where('id='.$result)->find();
                $this->code = 0;
                $this->content = $promoter;
            }else{
                $this->code = 1178;
                return;
            }
        }
    }

    //激活码列表
    public function promoterCodeList(){
        $page = Filter::int(Req::args('page'));
        if(!$page){
            $page = 1;
        }
        $district = $this->model->table('district_shop')->where('owner_id='.$this->user['id'])->find();
        if(!$district){
            $this->code = 1131;
            return;
        }
        $code = $this->model->table('promoter_code')->where('status=1 and user_id='.$this->user['id'])->findAll();
        if($code){
            foreach($code as $k => $v){
                if(time()>strtotime($v['end_date'])){
                    $this->model->table('promoter_code')->data(array('status'=>-1))->where('id='.$v['id'])->update();
                }
            }
        }
        $list = $this->model->table('promoter_code')->where('user_id='.$this->user['id'])->order('id desc')->findPage($page,10);
        if($list){
            unset($list['html']);
        }else{
            $list = array(
                'data'=>array()
                );
        }
        
        $count1 = $district['code_num'];
        $count2 = $this->model->table('promoter_code')->where('user_id='.$this->user['id'])->count();
        $count3 = $this->model->table('promoter_code')->where('status=0 and user_id='.$this->user['id'])->count();
        $count4 = $this->model->table('promoter_code')->where('status=1 and user_id='.$this->user['id'])->count();
        $count5 = $this->model->table('promoter_code')->where('status=-1 and user_id='.$this->user['id'])->count();

        $count = array(
             'max_num'       => $count1, //允许生成激活码的数量,默认100
             'made_num'      => $count2, //已生成
             'remaining_num' => $count1-$count2, //剩余数量
             'used_num'      => $count3, //已使用
             'unused_num'    => $count4, //未使用
             'expired_num'   => $count5 //已过期
            );

        $this->code = 0;
        $this->content['list'] = $list;
        $this->content['count'] = $count;
    }

    public function getAllChildShopId(){
        $user_id = Filter::int(Req::args('user_id'));
        $idstr = Common::getAllChildShops($user_id);
        $this->code = 0;
        $this->content = $idstr;
    } 

    public function sendActiveCode() {
        $mobile = Filter::str(Req::args('mobile'));
        $num = Filter::int(Req::args('num'));
        // if($num>20) {
        //     $this->code = 0;
        //     return;
        // }
        $exist = $this->model->table('customer')->fields('user_id')->where('mobile='.$mobile.' and status=1')->find();
        if(!$exist) {
            $this->code = 1257;
            return;
        }
        if($exist['user_id']==$this->user['id']) {
            $this->code = 1283;
            return;
        }
        $is_shop = $this->model->table('district_shop')->where('owner_id='.$exist['user_id'])->find();
        if(!$is_shop) {
            $this->code = 1258;
            return;
        }
        $myself = $this->model->table('district_shop')->where('owner_id='.$this->user['id'])->find();
        if($myself['code_num']<$num) {
            $this->code = 1259;
            return;
        }
        $count2 = $this->model->table('promoter_code')->where('user_id='.$this->user['id'])->count();
        if($count2>=$myself['code_num']) {
            $this->code = 1278;
            return;
        }
        $num1 = $myself['code_num'] - $num;
        $num2 = $is_shop['code_num'] + $num;
        $this->model->table('district_shop')->where('owner_id='.$this->user['id'])->data(['code_num'=>$num1])->update();
        $this->model->table('district_shop')->where('owner_id='.$exist['user_id'])->data(['code_num'=>$num2])->update();

        $this->code = 0;
        $this->content['myself_code_num'] = $num1;
        $this->content['her_code_num'] = $num2;
    }

    public function getAllChildPromotersIds()
    {
        $user_id = Filter::int(Req::args('user_id'));
        $model = new Model();
        //根据所属上级关系找到下级所有经销商
        $is_break = false; //false继续 true停止
        $promoter_user_id = '';
        $num = 0;
        $shop = $model->table("district_shop")->fields('id,owner_id')->where("owner_id=".$user_id)->find();
        $idstr = Common::getAllChildShops($user_id);
        $now_user_id = $idstr['shop_ids']==''?$shop['id']:$shop['id'].','.$idstr['shop_ids'];
        $inviter_info = $model->table("district_promoter")->fields('id,user_id')->where("hirer_id in (".$now_user_id.")")->findAll();
        $ids = array();
        if($inviter_info) {
            foreach($inviter_info as $k =>$v) {
               $ids[] = $v['user_id'];
            }
        }
        $promoter_ids = $ids!=null?implode(',', $ids):'';    
        $this->code = 0;
        $this->content['promoter_ids'] = $promoter_ids;
        $this->content['num'] = count($inviter_info);
    }     
}
