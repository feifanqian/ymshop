<?php
class LinuxCliTask{
    public $model;
    public function __construct($configPath,$argc,$argv) {
        if(strtolower(PHP_SAPI)!="cli"){
            exit("访问被拒绝");
        }else if($argc <3){
            exit("未设置参数");
        }else{
            if(is_string($configPath)){
                $config = require($configPath);
                if(!is_array($config)){
                    exit("加载配置失败");
                }else{
                    DBFactory::setDbInfo($config['db']);
                    if (isset($config['classes']))
                    Tiny::setClasses($config['classes']);
                    $this->model = new Model();
                }
            }
            $token = $argv[1];
            if($token !="token12345"){
                exit("非法访问");
            }
            $action = $argv[2];
            $this->$action();
        }
    }
    public function voucherNotice(){
             $yesterday   = date('Y-m-d 23:59:59',strtotime("-1 days"));//昨天过期的时间
             $threedays   = date('Y-m-d 23:59:59',strtotime("+3 days"));
             $tendays     = date('Y-m-d 23:59:59',strtotime("+10 days"));
             $fifteendays = date('Y-m-d 23:59:59',strtotime("+15 days"));
             
             $voucher_list1 = $this->model->table('voucher as v')->join("left join oauth_user as o on v.user_id = o.user_id")
                     ->where("v.end_time ='{$yesterday}' and v.status =0 and v.is_send=1 and v.user_id is not NULL and o.oauth_type='wechat'")
                     ->fields("v.account,v.value,o.user_id,o.open_id,o.open_name")
                     ->findAll();
             $voucher_list2 = $this->model->table('voucher as v')->join("left join oauth_user as o on v.user_id = o.user_id")
                     ->where("v.end_time ='{$threedays}' and v.status =0 and v.is_send=1 and v.user_id is not NULL and o.oauth_type='wechat'")
                     ->fields("v.account,v.value,o.user_id,o.open_id,o.open_name")
                     ->findAll();
             $voucher_list3 = $this->model->table('voucher as v')->join("left join oauth_user as o on v.user_id = o.user_id")
                     ->where("v.end_time ='{$tendays}' and v.status =0 and v.is_send=1 and v.user_id is not NULL and o.oauth_type='wechat'")
                     ->fields("v.account,v.value,o.user_id,o.open_id,o.open_name")
                     ->findAll();
             $voucher_list4 = $this->model->table('voucher as v')->join("left join oauth_user as o on v.user_id = o.user_id")
                     ->where("v.end_time ='{$fifteendays}' and v.status =0 and v.is_send=1 and v.user_id is not NULL and o.oauth_type='wechat'")
                     ->fields("v.account,v.value,o.user_id,o.open_id,o.open_name")
                     ->findAll();        
             if(empty($voucher_list1) && empty($voucher_list2) && empty($voucher_list3) && empty($voucher_list4)){
                 echo date("Y-m-d H:i:s").":"."none need to notify\r\n";die;
             }
             //获取token
              $wechatcfg = $this->model->table("oauth")->where("class_name='WechatOAuth'")->find();
              $wechat = new WechatMenu($wechatcfg['app_key'], $wechatcfg['app_secret'], '');
              $token = $wechat->getAccessToken();
              if($token==''){
                  echo date("Y-m-d H:i:s").":"."get access_token fail\r\n";die;
              }
             if(!empty($voucher_list1)){
                 foreach($voucher_list1 as $k => $v){
                     $v['open_name'] = $v['open_name']==""?"买一点用户":$v['open_name'];
                     $params = array(
                         'touser'=>$v['open_id'],
                         'msgtype'=>'news',
                         'news'=>array(
                             'articles'=>array('0'=>array(
                                 'title'=>'买一点温馨提示',
                                 'description'=>"亲爱的{$v['open_name']},您有一张价值{$v['value']}元的优惠券已经过期了。",
                                 'url'=>'www.buy-d.cn',
                                 'picurl'=>'http://img.buy-d.cn/data/uploads/2017/02/13/0da1a14455a4b1b63bd09eaa2209f809.png'
                             )
                            )
                         )
                     );
                    //print_r($params);
                    Http::curlPost("https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token={$token}", json_encode($params,JSON_UNESCAPED_UNICODE));
                 }
             }
             if(!empty($voucher_list2)){
                 foreach($voucher_list2 as $k => $v){
                     $v['open_name'] = $v['open_name']==""?"买一点用户":$v['open_name'];
                     $params = array(
                         'touser'=>$v['open_id'],
                         'msgtype'=>'news',
                         'news'=>array(
                             'articles'=>array('0'=>array(
                                 'title'=>'买一点温馨提示',
                                 'description'=>"亲爱的{$v['open_name']},您有一张价值{$v['value']}元的优惠券将于三日后过期，赶快去使用吧>>>",
                                 'url'=>'www.buy-d.cn',
                                 'picurl'=>'http://img.buy-d.cn/data/uploads/2017/02/13/0da1a14455a4b1b63bd09eaa2209f809.png'
                             )
                             )
                         )
                     );
                     //print_r($params);
                   Http::curlPost("https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token={$token}", json_encode($params,JSON_UNESCAPED_UNICODE));
                 }
             }
             if(!empty($voucher_list3)){
                 foreach($voucher_list3 as $k => $v){
                     $v['open_name'] = $v['open_name']==""?"买一点用户":$v['open_name'];
                     $params = array(
                         'touser'=>$v['open_id'],
                         'msgtype'=>'news',
                         'news'=>array(
                             'articles'=>array('0'=>array(
                                 'title'=>'买一点温馨提示',
                                 'description'=>"亲爱的{$v['open_name']},您有一张价值{$v['value']}元的优惠券将于十日后过期，赶快去使用吧>>>",
                                 'url'=>'www.buy-d.cn',
                                 'picurl'=>'http://img.buy-d.cn/data/uploads/2017/02/13/0da1a14455a4b1b63bd09eaa2209f809.png'
                             )
                             )
                         )
                     );
                    // print_r($params);
                   Http::curlPost("https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token={$token}", json_encode($params,JSON_UNESCAPED_UNICODE));
                 }
             }
             if(!empty($voucher_list4)){
                 foreach($voucher_list4 as $k => $v){
                     $v['open_name'] = $v['open_name']==""?"买一点用户":$v['open_name'];
                     $params = array(
                         'touser'=>$v['open_id'],
                         'msgtype'=>'news',
                         'news'=>array(
                             'articles'=>array('0'=>array(
                                 'title'=>'买一点温馨提示',
                                 'description'=>"亲爱的{$v['open_name']},您有一张价值{$v['value']}元的优惠券还有十五天就要过期啦，赶快去使用吧>>>",
                                 'url'=>'www.buy-d.cn',
                                 'picurl'=>'http://img.buy-d.cn/data/uploads/2017/02/13/0da1a14455a4b1b63bd09eaa2209f809.png'
                             )
                             )
                         )
                     );
                    //print_r($params);
                    Http::curlPost("https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token={$token}", json_encode($params,JSON_UNESCAPED_UNICODE));
                 }
             }
            echo date("Y-m-d H:i:s").":"."notify success\r\n";die;
     }
    public function updateSilver(){//每天0点00更新
        $dead_record = $this->model->table('silver_limit')->where("is_dead = 0 and timelimit = 1 and dead_line<='".date("Y-m-d H:i:s")."'")->findAll();
        if(!empty($dead_record)){
            $dead_id=array();
            foreach ($dead_record as $k =>$v){
                $dead_id[] = $v['id'];
                $dead_amount = $v['recharge_amount']-$v['used_amount'];
                $result = $this->model->table('silver_limit')->data(array('dead_amount'=>$dead_amount,'is_dead'=>1))->where("id=".$v['id'])->update();
                if($dead_amount>0 && $result){
                     $this->model->table('customer')->data(array("silver_coin"=>'`silver_coin`-'.$dead_amount))->where("user_id=".$v['user_id'])->update();
                     Log::silver_log((0-$dead_amount),$v['user_id'],$v['recharge_no'],"过期减扣",5);
                }
            }
            exit(date("Y-m-d H:i:s").":过期了".count($dead_record)."条记录:Id".  implode(',', $dead_id)."\n");
        }else{
            exit(date("Y-m-d H:i:s").":没有过期的银点\r\n");
        }
    }
    public function syncUserToOtherPlatform(){
           $count     = $this->model->table('user')->where("is_sync=0")->count();
           $pageSize  = 20;
           $pageCount = ceil($count/$pageSize);
           $success = $fail =0;
           for($i=1;$i<=$pageCount;$i++){
               $offset = ($i-1) * $pageSize;
               $userInfo = $this->model->table('user as u')->join("left join customer as c on u.id = c.user_id")->where('u.is_sync =0')->fields('u.id,u.name,u.avatar,c.mobile')->limit("$offset,$pageSize")->findAll();
               if(!empty($userInfo)){
                   foreach ($userInfo as $v){
                       $data['UserId']=$v['id'];
                       $data['Password']='123456';
                       $data['UserName']=$v['name'];
                       $data['Photo']= isset($v['avatar'])? (strpos($v['avatar'], 'http')===false?$v['avatar']:"/images/member.jpg"):"/images/member.jpg";
                       $data['Mobile']=isset($v['mobile'])? $v['mobile']:"";
                       $data['Email']="";
                       $result = $this->doCurl('http://yancxiong2-002-site1.site4future.com/api/ReUsers/ReUsersIncrease', $data,30);
                       $result = json_decode($result, true);
                       if($result['result']['code']=='0001'){
                           $this->model->table('user')->data(array('is_sync'=>1))->where("id=".$v['id'])->update();
                           $success++;
                           echo $v['id']."->success\r\n";
                       }else{
                           $fail++;
                           echo $v['id']."->fail[{$result['result']['message']}]\r\n";
                           echo "===================\r\n";
                           var_dump($data);
                           echo "===================\r\n";
                       }
                   }
               }
           }
           exit(date("Y-m-d H:i:s").":同步完成，共同步{$count}条数据,成功{$success}条，失败{$fail}条。\n");
    }
    public function addRechargePackageToOtherPlatfrom(){//添加数据到外平台
        $new = $this->model->table('recharge_gift')->where("status=1")->findAll();
        if($new){
            $money=array("1"=>600,'2'=>3600,'3'=>10800,'4'=>18000);
            foreach ($new as $v){
                if($v['package']==1&&$v['is_first']==1){//如果是第一次购买600，看做是3600的分期
                    $data['Tcsum']=3600;
                    $data['Category']=1;
                    $data['PmtCategory']=2;
                    $data['TcRmb']=600;
                }else{
                    $data['Tcsum']=$money[$v['package']];
                    $data['Category']=$v['is_first'];
                    $data['PmtCategory']=1;
                    $data['TcRmb']=0;
                }
                $data['OnfId']=$v['user_id'];
                $user = $this->model->table('user')->fields('name')->where("id=".$v['user_id'])->find();
                $data['CName']=$user['name'];
                $data['PId']=$v['recommend']==null?0:$v['recommend'];
                $data['State']=0;
                $result = $this->doCurl('http://yancxiong2-002-site1.site4future.com/api/UserData/UserDataIncrease', $data,30);
                $result = json_decode($result, true);
                if($result['result']['code']=='0001'){
                    $this->model->table('recharge_gift')->where("id =".$v['id']." and status=1")->data(array('status'=>2))->update();
                    echo "==添加成功==".date("Y-m-d H:i:s")."==数据：".json_encode($data)."===\n";
                }else{
                    echo "==添加失败==".date("Y-m-d H:i:s")."==数据".json_encode($data)."===返回:".$result['result']['message']."===\n";
                }
            }
        }else{
        }
    }
    public function getOtherPlatformReturnZhiDou(){//获取外平台数据返回直豆
        $data['where']="";
        $result = $this->doCurl('http://yancxiong2-002-site1.site4future.com/api/TJSum/PaginationList', $data,30);
        $result = json_decode($result, true);
        if($result['result']['code']=='0001'){
            if(!empty($result['data']['list'])){
                echo date("Y-m-d H:i:s")."==获取到回传数据==".json_encode($result['data']['list'])."==\n";
                $delete_id = array();
                foreach ($result['data']['list'] as $v){
                    if($v['Amtsum']>0){
                        //加金点
                        $result1 = $this->model->table('customer')->data(array('balance'=>"`balance`+".$v['Amtsum']))->where('user_id='.$v['UserId'])->update();
                        if($result1){
                            $delete_id[]=$v['Id'];
                            $data['user_id']=$v['UserId'];
                            $data['return_id']=$v['Id'];
                            $data['return_type']=$v['Category'];
                            $data['loss_amount']=$data['return_amount']=$v['Amtsum'];
                            $data['package']=$v['Tcsum'];
                            $data['create_date']=date("Y-m-d H:i:s");
                            $data['can_withdraw_date']=date("Y-m-d H:i:s",strtotime("+7 day"));
                            $data['used_amount']=0.00;
                            $data['is_all_used']=0;
                            $id = $this->model->table("platform_return")->data($data)->insert();
                            Log::balance($v['Amtsum'], $v['UserId'], "PR".sprintf("09d%",$id),'平台奖励', 8);
                            echo date("Y-m-d H:i:s")."==添加回传数据到商城用户{$v['UserId']}成功==\n";
                        }
                    }
                }
                if(!empty($delete_id)){
                    $delete_result = $this->doCurl('http://yancxiong2-002-site1.site4future.com/api/TJSum/UserDataStrike', array("DIds"=>implode(',', $delete_id)),30);
                    $delete_result = json_decode($result, true);
                    if($delete_result['result']['code']=='0001'){
                        exit(date("Y-m-d H:i:s")."==删除回传数据成功==".implode(',', $delete_id)."\n");
                    }else{
                        exit(date("Y-m-d H:i:s")."==删除回传数据失败==".implode(',', $delete_id)."==".$delete_result['result']['message']."===\n");
                    }
                }
            }else{
            }
        }else{
            exit(date ("Y-m-d H:i:s")."==获取数据失败==".$result['result']['message']."===\n");
        }
    }
    public function getOtherPlatformReturnXuDou(){//获取外平台数据返回续豆
        $data['where']="";
        $result = $this->doCurl('http://yancxiong2-002-site1.site4future.com/api/TJSumTow/PaginationList', $data,30);
        $result = json_decode($result, true);
        if($result['result']['code']=='0001'){
            if(!empty($result['data']['list'])){
                echo date("Y-m-d H:i:s")."==获取到续豆回传数据==".json_encode($result['data']['list'])."==\n";
                $delete_id = array();
                foreach ($result['data']['list'] as $v){
                    if($v['Amtsum']>0){
                        //加金点
                        $result1 = $this->model->table('customer')->data(array('balance'=>"`balance`+".$v['Amtsum']))->where('user_id='.$v['UserId'])->update();
                        if($result1){
                            $delete_id[]=$v['Id'];
                            $data['user_id']=$v['UserId'];
                            $data['return_id']=$v['Id'];
                            $data['return_type']=$v['Category'];
                            $data['loss_amount']=$data['return_amount']=$v['Amtsum'];
                            $data['package']="";
                            $data['create_date']=date("Y-m-d H:i:s");
                            $data['can_withdraw_date']=date("Y-m-d H:i:s",strtotime("+7 day"));
                            $data['used_amount']=0.00;
                            $data['is_all_used']=0;
                            $id = $this->model->table("platform_return")->data($data)->insert();
                            Log::balance($v['Amtsum'], $v['UserId'], "PR".sprintf("09d%",$id),'平台奖励', 8);
                            echo date("Y-m-d H:i:s")."==添加续豆回传数据到商城用户{$v['UserId']}成功==\n";
                        }
                    }
                }
                if(!empty($delete_id)){
                    $delete_result = $this->doCurl('http://yancxiong2-002-site1.site4future.com/api/TJSumTow/UserDataStrike', array("DIds"=>implode(',', $delete_id)),30);
                    $delete_result = json_decode($result, true);
                    if($delete_result['result']['code']=='0001'){
                        exit(date("Y-m-d H:i:s")."==删除续豆回传数据成功==".implode(',', $delete_id)."\n");
                    }else{
                        exit(date("Y-m-d H:i:s")."==删除续豆回传数据失败==".implode(',', $delete_id)."==".$delete_result['result']['message']."===\n");
                    }
                }
            }else{
            }
        }else{
            exit(date ("Y-m-d H:i:s")."==获取数据失败==".$result['result']['message']."===\n");
        }
    }
    private function doCurl($url,$post_data,$time_out =30){
        $post_data = is_array($post_data)?http_build_query($post_data):$post_data;
        $curl = curl_init();
        curl_setopt_array($curl, array(
          CURLOPT_URL => $url,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => $time_out,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_POSTFIELDS => $post_data,
          CURLOPT_HTTPHEADER => array(
            "cache-control: no-cache",
            "content-type: application/x-www-form-urlencoded",
          ),
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            echo "cURL Error #:" . $err;
            exit();
         } else {
              return $response;
       }
    }
}

//应用目录，为了程序的更好应用与开发。
define("APP_ROOT", dirname(__file__) . DIRECTORY_SEPARATOR);
//引入框架文件
include("framework/tiny.php");
$configPath = "protected/config/config.php";
new LinuxCliTask($configPath,$argc,$argv); 