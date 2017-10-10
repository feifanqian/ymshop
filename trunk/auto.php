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
                     $v['open_name'] = $v['open_name']==""?"圆梦用户":$v['open_name'];
                     $params = array(
                         'touser'=>$v['open_id'],
                         'msgtype'=>'news',
                         'news'=>array(
                             'articles'=>array('0'=>array(
                                 'title'=>'圆梦温馨提示',
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
                     $v['open_name'] = $v['open_name']==""?"圆梦用户":$v['open_name'];
                     $params = array(
                         'touser'=>$v['open_id'],
                         'msgtype'=>'news',
                         'news'=>array(
                             'articles'=>array('0'=>array(
                                 'title'=>'圆梦温馨提示',
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
                     $v['open_name'] = $v['open_name']==""?"圆梦用户":$v['open_name'];
                     $params = array(
                         'touser'=>$v['open_id'],
                         'msgtype'=>'news',
                         'news'=>array(
                             'articles'=>array('0'=>array(
                                 'title'=>'圆梦温馨提示',
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
                     $v['open_name'] = $v['open_name']==""?"圆梦用户":$v['open_name'];
                     $params = array(
                         'touser'=>$v['open_id'],
                         'msgtype'=>'news',
                         'news'=>array(
                             'articles'=>array('0'=>array(
                                 'title'=>'圆梦温馨提示',
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

    public function autoUpdateFinancial(){
        $customer=$this->model->table('customer')->fields('user_id,real_name,financial_coin,financial_stock')->where('financial_coin>0')->findAll();
        if($customer){
            foreach($customer as $k => $v){
                if($v['financial_coin']>=5000){
                    $stock=intval($v['financial_coin']/5000);
                    $data=array(
                          'financial_coin'=>"`financial_coin`-5000*({$stock})",
                          'financial_stock'=>"`financial_stock`+({$stock})",
                        );
                    $ret=$this->model->table('customer')->data($data)->where('user_id='.$v['user_id'])->update();//自动分配分红股
                    if($ret){
                        echo date("Y-m-d H:i:s")."=={$v['real_name']}==自动分配{$stock}个分红股成功==\n";
                    }            
                    $current_date = date('Y-m-d',time());
                    ignore_user_abort();//关掉浏览器，PHP脚本也可以继续执行.
                    set_time_limit(0);    
                    if(time()>strtotime("$current_date + 360 days")){ //360天后自动清除该分红股
                        $res=$this->model->table('customer')->data(array('financial_stock'=>"`financial_stock`-({$stock})"))->where('user_id='.$v['user_id'])->update();
                        if($res){
                            echo date("Y-m-d H:i:s")."success=={$v['real_name']}==360天后自动清除该分红股==\n";
                        }else{
                            echo date("Y-m-d H:i:s")."error";
                        }
                    }
                }
            }
        }
    }

    public function autoUnlocked(){
        $config = Config::getInstance()->get("district_set");
        $income=$this->model->table('promote_income_log')->where('frezze_income_change>0')->findAll();
        if($income){
           foreach ($income as $k => $v) {
           $t1=strtotime($v['date']);
           $t2=$t1+$config['income_lockdays']*24*60*60;
           if(time()>$t2){
            $ret=$this->model->table('promote_income_log')->data(array('valid_income_change'=>"`valid_income_change`+({$v['frezze_income_change']})",'frezze_income_change'=>0,'current_valid_income'=>"`current_valid_income`+({$v['frezze_income_change']})",'current_frezze_income'=>"`current_frezze_income`-({$v['frezze_income_change']})"))->where('id='.$v['id'])->update();
            if($ret){
               echo date("Y-m-d H:i:s")."自动解锁锁定收益\n";
            }else{
                echo date("Y-m-d H:i:s")."解锁失败";
            }
           }
         }
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