<?php
define('CLOUD_PATH', dirname(__FILE__) . '/100009001000.pem');
class SMS extends ClassConfig {

    /**
     *
     * @var SMS 
     */
    public $alias = AppConfig::ALIAS;
    public $path = CLOUD_PATH;
    public $pwd = AppConfig::PWD;
    public $serverAddress = AppConfig::ICLOD_URL;
    public $sysid = AppConfig::SYSID;
    public $signMethod = AppConfig::SIGN_METHOD;

    public function __construct()
    {
        $this->model = new Model();
    }

    private static $ins = null;
    public $errordict = array(
        '405' => '请求参数中的appkey为空',
        "406" => '非法的appkey',
        "456" => '请求参数中的手机号码或者国家代码为空',
        "457" => '手机号码格式错误',
        "458" => '手机号码在黑名单中',
        "463" => '手机号码超出当天发送短信的限额',
        "467" => '请求校验验证码频繁（5分钟校验超过3次）',
        "468" => '用户提交校验的验证码错误',
        "469" => '没有打开发送Http-api的开关',
        "470" => '账户短信余额不足',
        "471" => '请求ip和绑定ip不符',
        "477" => '当前手机号码在SMSSDK平台内每天最多可发送短信10条，包括客户端发送和WebApi发送',
        "478" => '当前手机号码在当前应用下12小时内最多可发送文本验证码5条.',
    );

    public static function getInstance() {
        if (!(self::$ins instanceof self)) {
            self::$ins = new self();
        }
        return self::$ins;
    }

    public static function config() {
        return array(
            array(
                'caption' => 'appKey',
                'field' => 'appKey',
            ),
            array(
                'caption' => '模板ID',
                'field' => 'templateCode',
            ),
        );
    }

    /**
     * 发送验证码
     * @param int $mobile 手机号
     * @param int $code 验证码
     * @return array
     */
    public function sendCode($mobile, $code) {
        // $params = array(
        //     // 'appKey' => $this->config['appKey'],
        //     'appKey' => '1f4d2d20dd266',
        //     // 'templateCode' => $this->config['templateCode'],
        //     'templateCode' => '9161448',
        //     'zone' => '86',
        //     'phone' => $mobile,
        //     'AppName'=>"圆梦共享网",
        //     'code' => $code,
        // );
        $params = array(
         'appkey' => '1f4d2d20dd266', 
         'zone' => '86',
         'phone' => $phone,
         );
        // $ret = $this->postRequest('https://webapi.sms.mob.com/custom/msg', $params);
        $ret = $this->postRequest('https://webapi.sms.mob.com/sms/sendmsg', $params);
        $json = json_decode($ret, TRUE);
        if (isset($json['status']) && $json['status'] == 200) {
            $time = time();
            $mobile_model = new Model('mobile_code');
            $mobile_model->data(array('mobile' => $mobile, 'code' => $code, 'send_time' => $time))->insert();
            return array('status' => 'success', 'message' => '发送成功');
        } else {
            return array('status' => 'fail', 'message' => isset($json['status']) && isset($this->errordict[$json['status']]) ? $this->errordict[$json['status']] : '发送失败');
        }
    }

    /**
     * 校验验证码
     * @param int $mobile
     * @param int $code
     * @return array
     */
    public function checkCode($mobile, $code) {
        $mobile_model = new Model('mobile_code');
        $time = time() - 120;
        $obj = $mobile_model->where("send_time > $time and mobile ='" . $mobile . "'")->find();
        if ($obj && $code == $obj['code']) {
            return array('status' => 'success', 'message' => '验证成功');
        } else {
            return array('status' => 'fail', 'message' => '验证失败');
        }
    }

    public function flushCode($mobile) {
        $mobile_model = new Model('mobile_code');
        $mobile_model->where("mobile='{$mobile}'")->delete();
        return array('status' => 'success', 'message' => '验证成功');
    }

    public function postRequest($api, array $params = array(), $timeout = 30) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api);
        // 以返回的形式接收信息
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // 设置为POST方式
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        // 不验证https证书
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
            'Accept: application/json',
        ));
        // 发送数据
        $response = curl_exec($ch);
        // 不要忘记释放资源
        curl_close($ch);
        return $response;
    }

    public function actionCreateMember($user_id)
    {

        $bizUserId = date('YmdHis').$user_id;
        $memberType = 3;
        $source = 1;
        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);

        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);
        $param["bizUserId"] = $bizUserId;
        $param["memberType"] = $memberType;    //会员类型
        $param["source"] = $source;        //访问终端类型
        $result = $client->request("MemberService", "createMember", $param);
        if ($result['status'] == 'OK') {
            $this->model->table('customer')->data(array('bizuserid'=>$bizUserId))->where('user_id='.$user_id)->update();
            return true;
        } else {
            return false;
        }
    }

    public function actionSendVerificationCode($phone,$user_id)
    {
        $result1 = $this->actionCreateMember($user_id);
        if($result1){
            $customer = $this->model->table('customer')->fields('bizuserid')->where('user_id='.$user_id)->find();
            $bizUserId = $customer['bizuserid'];
            $verificationCodeType = 9;
            $client = new SOAClient();
            $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
            $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);

            $client->setServerAddress($this->serverAddress);
            $client->setSignKey($privateKey);
            $client->setPublicKey($publicKey);
            $client->setSysId($this->sysid);
            $client->setSignMethod($this->signMethod);
            $param["bizUserId"] = $bizUserId;    //商户系统用户标识，商户系统中唯一编号
            $param["phone"] = $phone;    //手机号码
            $param["verificationCodeType"] = $verificationCodeType;//绑定手机
            $result = $client->request("MemberService", "sendVerificationCode", $param);
            if ($result['status'] == 'OK') {
                return array('status' => 'success', 'message' => '发送成功');
            }else {
                return array('status' => 'fail', 'message' => $result['message']);
            }
        }else{
            return array('status' => 'fail', 'message' => '发送失败');
        }
    }

    public function actionBindPhone($phone,$verificationCode,$user_id)
    {
        $customer = $this->model->table('customer')->fields('bizuserid')->where('user_id='.$user_id)->find();
        $bizUserId = $customer['bizuserid'];
        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);

        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);
        $param["bizUserId"] = $bizUserId;     //商户系统用户标识，商户系统中唯一编号
        $param["phone"] = $phone;    //手机号码
        $param["verificationCode"] = $verificationCode; //短信验证码
        $result = $client->request("MemberService", "bindPhone", $param);
        if ($result['status'] == 'OK') {
            $this->model->table('customer')->data(array('mobile'=>$phone))->where('user_id='.$user_id)->update();
            return array('status' => 'success', 'message' => '绑定成功');
        } else {
             return array('status' => 'fail', 'message' => $result['message']);
        }
    }

    public function actionSendCode($phone,$user_id)
    {
            $customer = $this->model->table('customer')->fields('bizuserid')->where('user_id='.$user_id)->find();
            $bizUserId = $customer['bizuserid'];
            $verificationCodeType = 9; //绑定手机
            $client = new SOAClient();
            $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
            $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);

            $client->setServerAddress($this->serverAddress);
            $client->setSignKey($privateKey);
            $client->setPublicKey($publicKey);
            $client->setSysId($this->sysid);
            $client->setSignMethod($this->signMethod);
            $param["bizUserId"] = $bizUserId;    //商户系统用户标识，商户系统中唯一编号
            $param["phone"] = $phone;    //手机号码
            $param["verificationCodeType"] = $verificationCodeType;//绑定手机
            $result = $client->request("MemberService", "sendVerificationCode", $param);
            if ($result['status'] == 'OK') {
                return array('status' => 'success', 'message' => '发送短信验证码成功');
            }else {
                return array('status' => 'fail', 'message' => $result['message']);
            }
    }

    public function actionApplyBindBankCard($name,$cardNos,$user_id)
    {

        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);

        $customer = $this->model->table('customer')->fields('bizuserid,mobile,id_no')->where('user_id='.$user_id)->find();
        $bizUserId = $customer['bizuserid'];
        $cardNo = $this->rsaEncrypt($cardNos, $publicKey, $privateKey);//必须rsa加密
        $phone = $customer['mobile'];
        $cardType = 1;  //卡类型   储蓄卡 1 整型         信用卡 2 整型
        $identityType = 1;          //证件类型 1是身份证 目前只支持身份证
        $identityNo = $this->rsaEncrypt($customer['id_no'], $publicKey, $privateKey);//必须rsa加密 330227198805284412
        $validate = '';
        $cvv2 = '';
        $isSafeCard = false;  //信用卡时不能填写： true:设置为安全卡，false:不 设置。默认为 false
        $cardCheck = 2; //绑卡方式
        $unionBank = '';


        if ($cardType == 2) {
            // 信用卡    有下面的参数
            $param['validate'] = $validate;
            $param['cvv2'] = $cvv2;
        } else {
            $param['isSafeCard'] = $isSafeCard;
        }
        $param["bizUserId"] = $bizUserId;    //商户系统用户标识，商户系统中唯一编号
        $param["cardNo"] = $cardNo;  //银行卡号
        $param["phone"] = $phone;  //银行预留的手机卡号
        $param["name"] = $name; //用户的姓名
        $param["cardType"] = $cardType;
        $param["cardCheck"] = $cardCheck; //绑卡方式
        $param["identityType"] = $identityType;
        $param["identityNo"] = $identityNo;
        $param["unionBank"] = $unionBank;
        $result = $client->request("MemberService", "applyBindBankCard", $param);
        if ($result['status'] == 'OK') {
            return array('status' => 'success', 'message' => '绑定成功');
        } else {
            return array('status' => 'fail', 'message' => $result['message']);
        }

    }

}
