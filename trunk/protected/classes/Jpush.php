<?php

class Jpush{
    private $ins = null;
    private $app_key = 'feaaf7eaf74a3c1a161b779d';            //待发送的应用程序(appKey)，只能填一个。
    private $master_secret = 'c8914faebc51bef2c87a1ea1';      //主密码
    private $push_url = "https://api.jpush.cn/v3/push";            //推送的地址
    private $validate_url = "https://api.jpush.cn/v3/push/validate";
    private $schedule_url="https://api.jpush.cn/v3/schedules";
    public $push_content;
    public $schedule_content;
    
    //若实例化的时候传入相应的值则按新的相应值进行
    public function __construct($app_key=null, $master_secret=null) {
        if ($app_key) $this->app_key = $app_key;
        if ($master_secret) $this->master_secret = $master_secret;
    }
    /*
     * "audience": {
     *   "tag": [
     *      "深圳",
     *      "北京"
     *   ]
     * },
     * audience:1.all  发给所有
     *          2.按alias audience['alias']=array(100); 即推送给别名是100的用户
     *          2.按tag   audience['tag']=array('tag1','tag2');
     */      
    //设置立刻推送的内容
        public function setPushData($platform='all',$audience,$alert='',$type='',$type_value=''){
        
        $data = array();
        $data['platform'] = $platform;      //目标用户终端手机的平台类型android,ios,winphone
        $data['audience'] = $audience;      //目标用户    
       
        $data['notification'] = array(
            //安卓自定义
            "android"=>array(
                "alert"=>$alert,
                "builder_id"=>1,
                "extras"=>array(           //业务逻辑
                    'type'=>$type,       
                    'type_value'=>$type_value
                ),
            ),
            "ios"=>array(
                "alert"=>$alert,
                "sound"=>"default",
                "badge"=>"+1",
                "extras"=>array(           //业务逻辑
                    'type'=>$type,
                    "type_value"=>$type_value
                )
            )
        );
        $data['options'] = array(
                  // 'time_to_live'=>$time_to_live,
                  'apns_production'=>true
            );
        $this->push_content = json_encode($data,JSON_UNESCAPED_UNICODE);
    }
    public function setSingleScheduleData($schedule_name,$trigger_time,$platfrom,$audience,$type,$type_value,$alert,$time_to_live=60){
        $data = array();
        $data['name']=$schedule_name;
        $data['enabled']=true;
        $data['trigger']=array(
            'single'=>array(
                 'time'=>$trigger_time
                )
            );
        $data['push']=array(
            'platform'=>$platfrom,
            'audience'=>$audience,
            'notification'=> array(
                        "android"=>array(
                            "alert"=>$alert,
                            "builder_id"=>1,
                            "extras"=>array(           //业务逻辑
                                'type'=>$type,       
                                'type_value'=>$type_value
                             ),
                           ),
                        "ios"=>array(
                            "alert"=>$alert,
                            "sound"=>"default",
                            "badge"=>"+1",
                            "extras"=>array(           //业务逻辑
                                'type'=>$type,
                                "type_value"=>$type_value
                            )
                        )
                      ),
            'message'=>array(
                  'msg_content'=>$type                      
            ),
            'options'=>array(
                  'time_to_live'=>$time_to_live,
                  'apns_production'=>true
            )
        );
         $this->schedule_content = json_encode($data);
    }
    public function schedule(){
        if($this->schedule_content!=""){
            return $this->docurl($this->schedule_content,$this->schedule_url);
        }else{
            return false;
        }
    }
    //推送
    public function push(){
        if($this->push_content!=""){
            file_put_contents("jpush.txt", $this->push_content,FILE_APPEND);
            return $this->docurl($this->push_content,$this->push_url);
        }else{
            return false;
        }
    }
    //验证是否推送成功
    public function validate(){
        if($this->push_content!=""){
            return $this->docurl($this->push_content,$this->validate_url);
        }else{
            return false;            
        }
    }
    //Curl方法
    private function docurl($content,$url) {
        
        $pwd = "$this->app_key:$this->master_secret";
        $base64=base64_encode($pwd);
        $header=array("Authorization: Basic $base64");
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'JPush-API-PHP-Client');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);  // 连接建立最长耗时
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);  // 请求最长耗时
        // 设置SSL版本 1=CURL_SSLVERSION_TLSv1, 不指定使用默认值,curl会自动获取需要使用的CURL版本
        // curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        // 如果报证书相关失败,可以考虑取消注释掉该行,强制指定证书版本
        //curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST, 'TLSv1');
        // 设置Basic认证
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        // 设置Post参数
        
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
        $result = curl_exec($ch);                                 //运行curl
        curl_close($ch);
        return $result;
    }
}
