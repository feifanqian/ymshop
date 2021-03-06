<?php
header("Content-type: text/html; charset=utf-8");
define('ICLOD_USERID', '100009001000');//商户id
define('ICLOD_PATH', dirname(__FILE__) . '/100009001000.pem');
define('ICLOD_CERT_PATH', dirname(__FILE__) . '/private_rsa.pem'); //私钥文件
define('ICLOD_CERT_PUBLIC_PATH', dirname(__FILE__) . '/public_rsa.pem');//公钥文件
define('ICLOD_Server_URL', 'http://122.227.225.142:23661/service/soa');  //接口网关 测试环境
// define('ICLOD_Server_URL', 'https://yun.allinpay.com/service/soa');  //接口网关 生产环境

// define('NOTICE_URL', 'http://122.227.225.142:23661/service/soa'); //前台通知地址
define('NOTICE_URL', 'https://yun.allinpay.com/service/soaa'); //前台通知地址
// define('BACKURL', 'http://122.227.225.142:23661/service/soa');//后台通知地址
define('BACKURL', 'https://yun.allinpay.com/service/soa');//后台通知地址


/**
 * Iclod 云账户对接类
 * @category   ORG
 * @package  ORG
 * @subpackage  Net
 * @author    gyfbao
 */
class PaytonglianAction extends Controller
{
    public $model = null;
    public $user = null;
    public $code = 1000;
    public $content = NULL;
    public $date = '';
    public $version = '1.0';
    /*
     @param $serverAddress 服务地址
     @param $sysid 商户号
     @param $alias 证书名称
     @param $path 证书路径
     @param $pwd 证书密码
     @param $signMethod 签名验证方式
     */
    public $serverAddress = ICLOD_Server_URL;
    public $sysid = "100009001000";
    public $alias = "100009001000";
    public $path = ICLOD_PATH;
    public $pwd = "900724";
    public $signMethod = "SHA1WithRSA";

    public function __construct()
    {
        $this->model = new Model();
        $this->arrayXml = new ArrayAndXml();
    }

    /**
     * 创建会员
     * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
     * @param $source 手机 1 整型      PC 2 整型
     * @param $memberType   企业会员 2       个人会员 3
     * @param $extendParam   扩展参数
     */

    public function actionCreateMember()
    {

        $bizUserId = date('YmdHis').$this->user['id'];
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
            // $this->code = 0;
            // $this->content = '创建会员成功';
            $this->model->table('customer')->data(array('bizuserid'=>$bizUserId))->where('user_id='.$this->user['id'])->update();
            return true;
        } else {
            return false;
        }
    }

    /**
     * 发送短信验证码
     * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
     * @param $phone 手机号码
     * @param $verificationCodeType   验证码类型  解绑手机 6   绑定手机 9
     * @param $extendParam 其他信息，用于生成短信验证码内容。
     */

    public function actionSendVerificationCode()
    {
        $result1 = $this->actionCreateMember();
        if($result1){
            $customer = $this->model->table('customer')->fields('bizuserid')->where('user_id='.$this->user['id'])->find();
            $bizUserId = $customer['bizuserid'];
            $phone = Req::args('phone');
            $verificationCodeType = Req::args('verificationCodeType');
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
                $this->code = 0;
                $this->content = '发送短信验证码成功';
            }else {
                $this->code = $result['errorCode'];
                $this->content = $result['message'];
            }
        }else{
            $this->code = 1032;
        }
    }


    /**
     * 验证短信验证码
     * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
     * @param $phone 手机号码
     * @param $verificationCodeType   验证码类型  解绑手机 6       绑定手机 9
     * @param $verificationCode 验证码
     */

    public function actionCheckVerificationCode()
    {
        $customer = $this->model->table('customer')->fields('bizuserid')->where('user_id='.$this->user['id'])->find();
        $bizUserId = $customer['bizuserid'];
        $phone = Req::args('phone');
        $verificationCodeType = Req::args('verificationCodeType');
        $verificationCode = Req::args('verificationCode');
        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);
        $param["bizUserId"] = $bizUserId;      //商户系统用户标识，商户系统中唯一编号
        $param["phone"] = $phone;    //手机号码
        $param["verificationCodeType"] = $verificationCodeType;        //绑定手机
        $param["verificationCode"] = $verificationCode; //短信验证码
        $result = $client->request("MemberService", "checkVerificationCode", $param);
        if ($result['status'] == 'OK') {
            $this->code = 0;
        } else {
            print_r($result);
        }

    }

    /**
     * 个人实名认证
     * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
     * @param $isAuth  是否由云账户进行认证  true/false  默认为true   目前必须通过云账户认证
     * @param $name   姓名
     * @param $identityType 证件类型     身份证 1  护照 2   港澳通行证 3    目前只支持身份证。
     * @param $identityNo 证件号码      RSA加密
     */

    public function actionSetRealName()
    {
        $customer = $this->model->table('customer')->fields('bizuserid')->where('user_id='.$this->user['id'])->find();
        $bizUserId = $customer['bizuserid'];
        $name = Req::args('name');
        $identityType = 1;
        $identityNo = Req::args('identityNo');
        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);

        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);
        $param["bizUserId"] = $bizUserId;    //商户系统用户标识，商户系统中唯一编号
        $param["isAuth"] = true;
        $param["name"] = $name;
        $param["identityType"] = $identityType;
        $param["identityNo"] = $this->rsaEncrypt($identityNo, $publicKey, $privateKey);
        $result = $client->request("MemberService", "setRealName", $param);
        if ($result['status'] == 'OK') {
            $this->code = 0;
        } else {
            print_r($result);
            $this->code = 1163;
        }
    }

    /**
     * 个人创建会员+实名认证
     * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
     * @param $isAuth  是否由云账户进行认证  true/false  默认为true   目前必须通过云账户认证
     * @param $name   姓名
     * @param $identityType 证件类型     身份证 1  护照 2   港澳通行证 3    目前只支持身份证。
     * @param $identityNo 证件号码      RSA加密
     */

    public function realNameVerify()
    {
        $user = $this->model->table('customer')->fields('realname_verified')->where('user_id=' . $this->user['id'])->find();
        if (!$user) {
            $this->code = 1159;
            return;
        }
        if ($user['realname_verified'] == 1) {
            $this->code = 1164;
            return;
        }

        $name = Req::args('name');
        $bizUserId = date('YmdHis') . $this->user['id'];

        $identityType = Req::args('identityType');
        $identityNo = Req::args('identityNo');

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
        $result1 = $client->request("MemberService", "createMember", $param);

        // $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        // $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);

        // $client->setServerAddress($this->serverAddress);
        // $client->setSignKey($privateKey);
        // $client->setPublicKey($publicKey);
        // $client->setSysId($this->sysid);
        // $client->setSignMethod($this->signMethod);
        $params["bizUserId"] = $bizUserId;    //商户系统用户标识，商户系统中唯一编号
        $params["isAuth"] = true;
        $params["name"] = $name;
        $params["identityType"] = $identityType;
        $params["identityNo"] = $this->rsaEncrypt($identityNo, $publicKey, $privateKey);
        $result2 = $client->request("MemberService", "setRealName", $params);
        if ($result1['status'] == 'OK' && $result2['status'] == 'OK') { //通过验证
            $this->model->table('customer')->data(array('realname_verified' => 1,'bizuserid'=>$bizUserId,'realname'=>$name,'id_no'=>$identityNo))->where('user_id=' . $this->user['id'])->update();
            $this->code = 0;
            $this->content['verified'] = 1;
            $this->content['bizUserId'] = $bizUserId;
            $this->content['extends'] = array_merge($result1, $result2);
        }elseif($result1['status'] == 'OK' && $result2['status'] != 'OK'){ //未通过验证
            $this->model->table('customer')->data(array('realname_verified' => -1,'bizuserid'=>$bizUserId))->where('user_id=' . $this->user['id'])->update();
            $this->code = 0;
            $this->content['verified'] = -1;
        } else {
            $this->code = 0;
            $this->content['verified'] = 0;
        }
    }


    /**
     * 绑定手机
     * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
     * @param $phone  手机号码
     * @param $verificationCode   验证码
     */

    public function actionBindPhone()
    {
        $customer = $this->model->table('customer')->fields('bizuserid')->where('user_id='.$this->user['id'])->find();
        $bizUserId = $customer['bizuserid'];
        $phone = Req::args('phone');
        $verificationCode = Req::args('verificationCode');
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
            $this->code = 0;
        } else {
             $this->code = $result['errorCode'];
             $this->content = $result['message'];
        }
    }


    /**
     * 设置企业会员信息
     * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
     * @param $companyBasicInfo  企业基本信息   companyName企业名称      companyAddress企业地址      businessLicense营业执照号       organizationCode组织机构代码
     * telephone联系电话      legalName法人姓名       identityType法人证件类型        legalIds法人证件号码(RSA加密)
     *  legalPhone法人手机号码           accountNo企业对公账户账号(RSA加密)       parentBankName开户银行名称
     * @param $companyExtendInfo   企业扩展信息       目前不需要传
     */

    public function actionSetCompanyInfo()
    {
        //签名时间戳
        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);

        //请求的参数
        $companyBasicInfo = new stdClass();
        $companyBasicInfo->companyName = Req::args('companyName');//企业名称
        $companyBasicInfo->companyAddress = Req::args('companyAddress');//企业地址
        $companyBasicInfo->businessLicense = Req::args('businessLicense');//营业执照号
        $companyBasicInfo->organizationCode = Req::args('organizationCode');//组织机构代码
        $companyBasicInfo->telephone = Req::args('telephone');//联系电话
        $companyBasicInfo->legalName = Req::args('legalName');//法人姓名
        $companyBasicInfo->identityType = Filter::int(Req::args('identityType'));//法人证件类型
        $companyBasicInfo->legalIds = $this->rsaEncrypt(Req::args('legalIds'), $publicKey, $privateKey);//法人证件号码(RSA加密)
        $companyBasicInfo->legalPhone = Req::args('legalPhone');//法人手机号码
        $companyBasicInfo->accountNo = $this->rsaEncrypt(Req::args('accountNo'), $publicKey, $privateKey);//企业对公账户账号(RSA加密)
        $companyBasicInfo->parentBankName = Req::args('parentBankName');//'开户银行名称';
        $companyBasicInfo->bankCityNo = Req::args('bankCityNo');//'开户银行名称'
        $companyBasicInfo->bankName = Req::args('bankName');//'开户银行名称'
        $companyBasicInfo->parentBankName = Req::args('parentBankName');//'开户银行名称'

        $customer = $this->model->table('customer')->fields('bizuserid')->where('user_id='.$this->user['id'])->find();
        $bizUserId = $customer['bizuserid'];
        $backUrl = Req::args('backUrl');
        $companyExtendInfo = new stdClass(); //扩展参数
        $param = array(
            'bizUserId' => $bizUserId,
            'backUrl' => $backUrl,
            'companyBasicInfo' => $companyBasicInfo,
            'companyExtendInfo' => $companyExtendInfo,
        );
        $result = $client->request('MemberService', 'setCompanyInfo', $param);
        print_r($result);
        die();

    }


    /**
     * 设置个人会员信息
     * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
     * @param $userInfo  基本信息   name名称      country国家      province省份       area县市     address地址
     */

    public function actionSetMemberInfo()
    {
        //签名时间戳
        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);
        //参数
        $userInfo = new stdClass();
        $userInfo->name = Req::args('name');
        $userInfo->country = Req::args('country');
        $userInfo->province = Req::args('province');
        $userInfo->area = Req::args('area');
        $userInfo->address = Req::args('address');
        $customer = $this->model->table('customer')->fields('bizuserid')->where('user_id='.$this->user['id'])->find();
        $bizUserId = $customer['bizuserid'];
        $param = array(
            'bizUserId' => $bizUserId,
            'userInfo' => $userInfo,//个人基本信息
        );
        $result = $client->request('MemberService', 'setMemberInfo', $param);
        print_r($result);
        die();
    }

    /**
     * 获取会员信息（个人和企业）
     * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
     */

    public function actionGetMemberInfo()
    {
        //签名时间戳
        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);
        //请求参数
        $customer = $this->model->table('customer')->fields('bizuserid')->where('user_id='.$this->user['id'])->find();
        $bizUserId = $customer['bizuserid'];
        $param = array(
            'bizUserId' => $bizUserId,
        );
        $result = $client->request('MemberService', 'getMemberInfo', $param);
        print_r($result);
        die();
    }


    /**
     * 查询卡bin
     * @param $cardNo 银行卡号   RSA加密
     */

    public function actionGetBankCardBin()
    {
        $user_id = $this->user['id'];
        $customer = $this->model->table('customer')->fields('bizuserid')->where('user_id='.$user_id)->find();
        $bizUserId = $customer['bizuserid'];
        $cardNo = Req::args('cardNo');
        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);
        $param["bizUserId"] = $bizUserId;
        $param["cardNo"] = $this->rsaEncrypt($cardNo, $publicKey, $privateKey); //银行卡号
        $result = $client->request("MemberService", "getBankCardBin", $param);
        if ($result['status'] == 'OK') {
            $this->code = 0;
            $signedValue = json_decode($result['signedValue'], true);
            $bankCode = $signedValue['cardBinInfo']['bankCode'];
            $model = new Model();
            $this->model->table("bankcode")->data(array('user_id' => $user_id, 'cardno' => $cardNo, 'bankcode' => $bankCode))->insert();

        } else {
            print_r($result);
        }

    }


    /**
     * 请求绑定银行卡
     * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
     * @param $cardNo 银行卡号   RSA加密
     * @param $phone 银行预留手机
     * @param $name 姓名
     * @param $cardType 卡类型      储蓄卡   1  信用卡    2
     * @param $bankCode 银行代码
     * @param $identityType 证件类型      身份证 1   护照 2   港澳通行证 3   目前只支持身份证。
     * @param $identityNo 证件号码
     * @param $validate 有效期    信用卡必填，格式为年月，如2103。RSA加密
     * @param $cvv2    CVV2   信用卡必填。RSA加密。
     * @param $isSafeCard 是否安全卡   信用卡时不能填写：  true:设置为安全卡，false:不设置。默认为false
     */

    public function actionApplyBindBankCard()
    {

        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);

        $user_id = $this->user['id'];
        
        $customer = $this->model->table('customer')->fields('bizuserid')->where('user_id='.$user_id)->find();
        $bizUserId = $customer['bizuserid'];

        $cardNos = Req::args('cardNo');
        $cardNo = $this->rsaEncrypt(Req::args('cardNo'), $publicKey, $privateKey);//必须rsa加密
        $phone = Req::args('phone');
        $name = Req::args('name');
        $ret = Common::getBankcardTpye($cardNos);
        if ($ret['retCode'] == 21401 || $ret['retCode'] != 200) {
            //不存在次卡号类型
            $this->code = 1184;
            return;              
        }
        $cardType = $ret['result']['cardType']=='借记卡'?1:2;  //卡类型   储蓄卡 1 整型         信用卡 2 整型
        $identityType = 1;          //证件类型 1是身份证 目前只支持身份证
        $identityNo = $this->rsaEncrypt(Req::args('identityNo'), $publicKey, $privateKey);//必须rsa加密 330227198805284412
        $validate = Req::args('validate');
        $cvv2 = Req::args('cvv2');
        $isSafeCard = false;  //信用卡时不能填写： true:设置为安全卡，false:不 设置。默认为 false
        $cardCheck = 2; //绑卡方式
        $unionBank = Req::args('unionBank');
        $verificationCode = Req::args('verificationCode');
        $province = Req::args('province');
        $city = Req::args('city');

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
            $this->code = 0;
            $signedValue = json_decode($result['signedValue'], true);
            $trancenum = $signedValue['tranceNum'];
            $transdate = $signedValue['transDate'];
            $exist = $this->model->table('bankcard')->where('user_id='.$this->user['id'].' and cardno='.$cardNos)->find();
            
            $this->model->table('bankcard')->data(array('user_id'=>$this->user['id'],'trancenum'=>$trancenum,'transdate'=>$transdate,'cardno'=>$cardNos,'province'=>$province,'city'=>$city))->insert();
            
            $this->code = 0;
            return;
        } else {
            print_r($result);
            $this->code = 1185;
            return;
        }

    }

    /**
     * 确认绑定银行卡
     * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
     * @param $tranceNum 流水号         请求绑定银行卡接口返回
     * @param $transDate 申请时间            请求绑定银行卡接口返回
     * @param $phone 银行预留手机
     * @param $verificationCode 短信验证码
     */

    public function actionBindBankCard()
    {
        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
        $cardNo = Req::args('cardNo');
        $user_id = $this->user['id'];
        $model = new Model();
        $obj = $this->model->table("bankcard")->fields("trancenum,transdate")->where("user_id='$user_id' AND cardno='$cardNo'")->order('id DESC')->find();

        $customer = $this->model->table('customer')->fields('bizuserid')->where('user_id='.$user_id)->find();
        $bizUserId = $customer['bizuserid'];

        $phone = Req::args('phone');
        $verificationCode = Req::args('verificationCode');
        
        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);
        $param["bizUserId"] = $bizUserId;
        $param["tranceNum"] = $obj['trancenum'];
        $param["transDate"] = $obj['transdate'];
        $param["phone"] = $phone;
        $param["verificationCode"] = $verificationCode;
        $result = $client->request("MemberService", "bindBankCard", $param);
        if ($result['status'] == 'OK') {
            $this->code = 0;
        } else {
            print_r($result);
        }

    }


    /**
     * 设置安全卡
     * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
     * @param $cardNo 银行卡号
     * @param $setSafeCard 是否设置为安全卡                        默认为true,目前不支持false
     */

    public function actionSetSafeCard()
    {

        $customer = $this->model->table('customer')->fields('bizuserid')->where('user_id='.$this->user['id'])->find();
        $bizUserId = $customer['bizuserid'];
        $setSafeCard = Req::args('setSafeCard');
        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);
        $cardNo = $this->rsaEncrypt(Req::args('cardNo'), $publicKey, $privateKey);//必须rsa加密
        $param["bizUserId"] = $bizUserId;
        $param["cardNo"] = $cardNo;
        $param["setSafeCard"] = $setSafeCard; //是否设置为安全卡
        $result = $client->request("MemberService", "setSafeCard", $param);
        if ($result['status'] == 'OK') {
            $this->code = 0;
        } else {
            $this->code = 1000;
        }

    }

    /**
     * 查询绑定银行卡
     * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
     * @param $cardNo 银行卡号。如为空，则返回用户所有绑定银行卡。(RSA加密)
     */

    public function actionQueryBankCard()
    {
        $customer = $this->model->table('customer')->fields('bizuserid')->where('user_id='.$this->user['id'])->find();
        $bizUserId = $customer['bizuserid'];
        $cardNo = Req::args('cardNo');
        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);
        $param["bizUserId"] = $bizUserId;
        $param["cardNo"] = $this->rsaEncrypt($cardNo, $publicKey, $privateKey);
        $result = $client->request("MemberService", "queryBankCard", $param);
        if ($result['status'] == 'OK') {
            $this->code = 0;
        } else {
            print_r($result);
        }

    }

    /**
     * 解绑绑定银行卡
     * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
     * @param $cardNo 银行卡号(RSA加密)
     */

    public function actionUnbindBankCard()
    {
        $customer = $this->model->table('customer')->fields('bizuserid')->where('user_id='.$this->user['id'])->find();
        $bizUserId = $customer['bizuserid'];
        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);
        $cardNo = $this->rsaEncrypt(Req::args('cardNo'), $publicKey, $privateKey);//必须rsa加密
        $param["bizUserId"] = $bizUserId;
        $param["cardNo"] = $cardNo;
        $result = $client->request("MemberService", "unbindBankCard", $param);
        if ($result['status'] == 'OK') {
            $this->code = 0;
            $this->content['success'] = '解除绑定银行卡成功';
        } else {
            print_r($result);
        }

    }


    /**
     * 更改绑定手机
     * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
     * @param $oldPhone 原手机号码
     * @param $oldVerificationCode 原手机验证码
     * @param $newPhone 新手机号码
     * @param $newVerificationCode 新手机验证码
     */

    public function actionChangeBindPhone()
    {
        //配置信息
        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);
        //请求参数
        $customer = $this->model->table('customer')->fields('bizuserid')->where('user_id='.$this->user['id'])->find();
        $bizUserId = $customer['bizuserid'];
        $oldPhone = Req::args('oldPhone');
        $newPhone = Req::args('newPhone');
        $newVerificationCode = Req::args('newVerificationCode');
        $param = array(
            'bizUserId' => $bizUserId,
            'oldPhone' => $oldPhone,
            'newPhone' => $newPhone,
            'newVerificationCode' => $newVerificationCode,
        );
        $result = $client->request('MemberService', 'changeBindPhone', $param);
        print_r($result);
        die();
    }

    /**
     * 锁定用户
     * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
     */

    public function actionLockMember()
    {
        //配置信息
        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);
        //请求参数
        $customer = $this->model->table('customer')->fields('bizuserid')->where('user_id='.$this->user['id'])->find();
        $bizUserId = $customer['bizuserid'];
        $param = array(
            'bizUserId' => $bizUserId,
        );
        $result = $client->request('MemberService', 'lockMember', $param);
        print_r($result);
        die();
    }

    /**
     * 解锁用户
     * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
     */

    public function actionUnlockMember()
    {
        //配置信息
        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);
        //请求参数
        $customer = $this->model->table('customer')->fields('bizuserid')->where('user_id='.$this->user['id'])->find();
        $bizUserId = $customer['bizuserid'];
        $param = array(
            'bizUserId' => $bizUserId,
        );
        $result = $client->request('MemberService', 'unlockMember', $param);
        print_r($result);
        die();
    }

    /**
     * 充值申请
     * @param $bizOrderNo 商户订单号
     * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
     * @param $accountSetNo 账户集编号
     * @param $amount 订单金额        单位：分，包含手续费
     * @param $fee 手续费          内扣，如果不存在，则填0。单位：分。如amount为100，fee为2，则实际到账为98。
     * @param $frontUrl 前台通知地址             前台交易时必填
     * @param $backUrl 后台通知地址
     * @param $ordErexpireDatetime 订单过期时间  yyyy-MM-dd HH:mm:ss订单最长时效为24小时。默认为最长时效。只在第一次提交订单时有效。
     * @param $payMethod 支付方式
     * @param $industryCode 行业代码
     * @param $industryName 行业名称
     * @param $source 访问终端类型                                             手机 1   PC 2
     * @param $summary 摘要                    交易内容最多20个字符
     * @param $extendInfo 扩展信息
     */

    public function actionDepositApply()
    {

        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);

        $user_id = $this->user['id'];
        $customer = $this->model->table('customer')->fields('bizuserid')->where('user_id='.$user_id)->find();
        $bizUserId = $customer['bizuserid'];
        $bizOrderNo = Req::args('bizOrderNo');
        $accountSetNo = '12985739202038';
        $amount = (round(Req::args('amount'),2))*100; //充值金额以分为单位
        // $amount = Req::args('amount');
        $fee = 0;//必须整形
        $validateType = Req::args('validateType');
        $ordErexpireDatetime = Req::args('ordErexpireDatetime');
        $payMethod = new  stdClass();
        $payMethodb = new  stdClass();

        if (Req::args('payMethod') == '1') {
            //快捷
            $payMethodb->bankCardNo = $this->rsaEncrypt(Req::args('bankCardNo'), $publicKey, $privateKey);
            $payMethodb->amount = $amount;
            $payMethod->QUICKPAY = $payMethodb; //快捷支付（需要先绑定银行 卡）
        } elseif (Req::args('payMethod') == '2') {
            //网关
            $payMethodb->bankCode = Req::args('bankCode'); //银行机构代码
            $payMethodb->payType = Req::args('payType'); //网关支付关系 B2C 个人网银（借记卡） 1  B2C 个人网银（信用卡） 11  B2B 企业网银 4
            $payMethodb->amount = Req::args('amount');//快捷支付（需要先绑定银行 卡）
            $payMethod->GATEWAY = $payMethodb;
        }
        $industryCode = '1910';
        $industryName = '其他';
        $source = 2;    //只能为整型
        $summary = '';
        $extendInfo = '';

        $param["bizUserId"] = $bizUserId;
        $param["bizOrderNo"] = $bizOrderNo;
        $param["accountSetNo"] = $accountSetNo;
        $param["amount"] = $amount;
        $param["fee"] = $fee;
        $param["validateType"] = $validateType;
        $param["frontUrl"] = '';
        $param["backUrl"] = 'http://www.ymlypt.com/payment/async_callbacks';
        $param["ordErexpireDatetime"] = $ordErexpireDatetime;
        $param["payMethod"] = $payMethod;
        $param["industryCode"] = $industryCode;
        $param["industryName"] = $industryName;
        $param["source"] = $source;
        $param["summary"] = $summary;
        $param["extendInfo"] = $extendInfo;
        $result = $client->request("OrderService", "depositApply", $param);
        if ($result['status'] == 'OK') {
            $signedValue = json_decode($result['signedValue'], true);//把json格式的数据转换成数组
            $tradeNo = $signedValue['tradeNo'];//交易编号 仅当快捷支付时有效
            if (!empty($tradeNo)) {
                $model = new Model();
                $this->model->table('tradeno')->data(array('user_id' => $user_id, 'biz_orderno' => $bizOrderNo, 'trade_no' => $tradeNo))->insert();
            }
            print_r($result);
        } else {
            print_r($result);
        }
    }

    /**
     * 提现申请
     * @param $bizOrderNo 商户订单号
     * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
     * @param $accountSetNo 账户集编号
     * @param $amount 订单金额        单位：分，包含手续费
     * @param $fee 手续费          内扣，如果不存在，则填0。单位：分。如amount为100，fee为2，则实际到账为98。
     * @param $backUrl 后台通知地址
     * @param $ordErexpireDatetime 订单过期时间                       yyyy-MM-dd HH:mm:ss订单最长时效为24小时。默认为最长时效。只在第一次提交订单时有效。
     * @param $bankCardNo 银行卡号/账号                 绑定的银行卡号/账号 (RAS加密)
     * @param $bankCardPro 银行卡/账户属性               0：个人银行卡         1：企业对公账户; 如果不传默认为0
     * @param $withdrawType 提现方式                   T0：T+0提现                   T1：T+1提现;默认为T0
     * @param $industryCode 行业代码
     * @param $industryName 行业名称
     * @param $source 访问终端类型                           手机 1   PC 2
     * @param $summary 摘要                    交易内容最多20个字符
     * @param $extendInfo 扩展信息
     */

    public function actionWithdrawApply()
    {

        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);

        $customer = $this->model->table('customer')->fields('bizuserid')->where('user_id='.$this->user['id'])->find();
        $bizUserId = $customer['bizuserid'];
        $bizOrderNo = Req::args('bizOrderNo');
        $accountSetNo = Req::args('accountSetNo');
        $amount = Req::args('amount');    //只能为整型
        $fee = Req::args('fee');    //只能为整型
        $industryCode = Req::args('industryCode');
        $industryName = Req::args('industryName');
        $source = Req::args('source');      //只能为整型
        $summary = Req::args('summary');
        $extendInfo = Req::args('extendInfo');
        $bankCardNo = $this->rsaEncrypt(Req::args('bankCardNo'), $publicKey, $privateKey);
        $bankCardPro = Req::args('bankCardPro');        //只能为整型
        $withdrawType = Req::args('withdrawType');
        $backUrl = BACKURL;
        $param["bizOrderNo"] = $bizOrderNo;
        $param["bizUserId"] = $bizUserId;
        $param["accountSetNo"] = $accountSetNo;
        $param["amount"] = $amount;
        $param["fee"] = $fee;
        $param["backUrl"] = $backUrl;
        $param["bankCardNo"] = $bankCardNo;
        $param["bankCardPro"] = $bankCardPro;
        $param["withdrawType"] = $withdrawType;
        $param["industryCode"] = $industryCode;
        $param["industryName"] = $industryName;
        $param["source"] = $source;
        $param["summary"] = $summary;
        $param["extendInfo"] = $extendInfo;
        $result = $client->request("OrderService", "withdrawApply", $param);
        if ($result['status'] == 'OK') {
            print_r($result);
        } else {
            print_r($result);
            die();
        }

    }


    /**
     * 消费申请
     * @param $payerId 商户系统用户标识，商户系统中唯一编号。付款方
     * @param $recieverId 商户系统用户标识，商户系统中唯一编号。 收款方
     * @param $bizOrderNo 商户订单号
     * @param $amount 订单金额        单位：分
     * @param $fee 手续费          内扣，如果不存在，则填0。单位：分。如amount为100，fee为2，则实际到账为98。
     * @param $splitRule 分账规则
     * @param $frontUrl 前台通知地址                      前台交易时必填
     * @param $backUrl 后台通知地址
     * @param $showUrl 订单详情地址
     * @param $ordErexpireDatetime 订单过期时间
     * @param $payMethod 支付方式
     * @param $goodsName 商品名称
     * @param $goodsDesc 商品描述
     * @param $industryCode 行业代码
     * @param $industryName 行业名称
     * @param $source 访问终端类型
     * @param $summary 摘要
     * @param $extendInfo 扩展参数
     */

    public function actionConsumeApply()
    {
        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);

        $user_id = Req::args('user_id');
        $payerId = Req::args('payerId');
        $recieverId = Req::args('recieverId');
        $bizOrderNo = Req::args('bizOrderNo');
        $amount = Req::args('amount'); //只能为整型
        $fee = Req::args('fee');  //只能为整型
        $splitRule = Req::args('splitRule');
        $showUrl = Req::args('showUrl');
        $ordErexpireDatetime = Req::args('ordErexpireDatetime');

        $payMethod = new  stdClass();
        $payMethodb = new  stdClass();

        //快捷
        if (Req::args('payMethod') == '1') {
            $payMethodb->bankCardNo = $this->rsaEncrypt(Req::args('bankCardNo'), $publicKey, $privateKey);
            $payMethodb->amount = $amount;
            $payMethod->QUICKPAY = $payMethodb; //快捷支付（需要先绑定银行 卡）
        } elseif (Req::args('payMethod') == '2') {
            //网关
            $payMethodb->bankCode = Req::args('bankCode');
            $payMethodb->payType = Req::args('payType');
            $payMethodb->amount = $amount;//快捷支付（需要先绑定银行 卡）
            $payMethod->GATEWAY = $payMethodb;
        }
        $goodsName = Req::args('goodsName');
        $goodsDesc = Req::args('goodsDesc');
        $industryCode = Req::args('industryCode');
        $industryName = Req::args('industryName');
        $source = Req::args('source');
        $summary = Req::args('summary');
        $extendInfo = Req::args('extendInfo');
        $param = array(
            'payerId' => $payerId,
            'recieverId' => $recieverId,
            'bizOrderNo' => $bizOrderNo,
            'amount' => $amount,
            'fee' => $fee,
            'frontUrl' => NOTICE_URL,
            'backUrl' => BACKURL,
            'showUrl' => $showUrl,
            'ordErexpireDatetime' => $ordErexpireDatetime,
            'payMethod' => $payMethod,
            'goodsName' => $goodsName,
            'goodsDesc' => $goodsDesc,
            'industryCode' => $industryCode,
            'industryName' => $industryName,
            'source' => $source,
            'summary' => $summary,
            'extendInfo' => $extendInfo,

        );
        $result = $client->request("OrderService", "consumeApply", $param);
        if ($result['status'] == 'OK') {
            $signedValue = json_decode($result['signedValue'], true);//把json格式的数据转换成数组
            $tradeNo = $signedValue['tradeNo'];//交易编号 仅当快捷支付时有效
            if (!empty($tradeNo)) {
                $model = new Model();
                $this->model->table('tradeno')->data(array('user_id' => $user_id, 'biz_orderno' => $bizOrderNo, 'trade_no' => $tradeNo))->insert();
                print_r($result);
            }
        } else {
            print_r($result);
            die();
        }
    }

    /**
     * 代收申请
     * @param $bizOrderNo 商户订单号
     * @param $payerId 商户系统用户标识，商户系统中唯一编号。付款人
     * @param $recieverList 收款列表            最多支持2000个;      bizUserId   商户系统用户标识，商户系统中唯一编号。        amount    金额，单位：分
     * @param $goodsType 商品类型             P2P标的  1    虚拟商品    2    实物商品   3     线下服务     4    营销活动     90   其他   99     如无商品类型，默认为0。
     * @param $goodsNo 商户系统商品编号          仅当商品类型!=0时必填。
     * @param $tradeCode 业务码
     * @param $amount 订单金额              单位：分   ;订单金额=收款列表+手续费
     * @param $fee 手续费              内扣，如果不存在，则填0。单位：分。如amount为100，fee为2，实际到账金额为98。如果不填，默认为0。
     * @param $frontUrl 前台通知地址                      前台交易时必填
     * @param $backUrl 后台通知地址
     * @param $showUrl 订单详情地址
     * @param $ordErexpireDatetime 订单过期时间
     * @param $payMethod 支付方式
     * @param $goodsName 商品名称
     * @param $goodsDesc 商品描述
     * @param $industryCode 行业代码
     * @param $industryName 行业名称
     * @param $source 访问终端类型
     * @param $summary 摘要
     * @param $extendInfo 扩展参数
     */

    public function actionAgentCollectApply()
    {
        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);

        $bizOrderNo = Req::args('bizOrderNo');
        $payerId = Req::args('payerId');
        $goodsType = Filter::int(Req::args('goodsType'));   //只能为整型
        $goodsNo = Req::args('goodsNo');
        $tradeCode = Req::args('tradeCode');
        $amount = Filter::int(Req::args('amount'));    //只能为整型
        $fee = Filter::int(Req::args('fee'));    //只能为整型
        $validateType = Filter::int(Req::args('validateType')); //只能为整型
        $showUrl = Req::args('showUrl');
        $ordErexpireDatetime = Req::args('ordErexpireDatetime');
        $recieverList = new stdClass();
        $recieverList->bizUserId = Req::args('payerId');
        $recieverList->amount = $amount;

        $payMethod = new  stdClass();
        $payMethodb = new  stdClass();

        //快捷支付
        if (Req::args('payMethod') == '1') {
            $payMethodb->amount = $amount;
            $payMethodb->bankCardNo = $this->rsaEncrypt(Req::args('bankCardNo'), $publicKey, $privateKey);
            $payMethod->QUICKPAY = $payMethodb;
        }
        //网关支付
        if (Req::args('payMethod') == '2') {
            $payMethodb->bankCode = Req::args('bankCode');
            $payMethodb->payType = Req::args('payType');
            $payMethodb->amount = $amount;//快捷支付（需要先绑定银行 卡）
            $payMethod->GATEWAY = $payMethodb;
        }

        $goodsName = Req::args('goodsName');
        $goodsDesc = Req::args('goodsDesc');
        $industryCode = Req::args('industryCode');
        $industryName = Req::args('industryName');
        $source = Filter::int(Req::args('source'));  //只能为整型
        $summary = Req::args('summary');
        $extendInfo = Req::args('extendInfo');
        $param = array(
            'bizOrderNo' => $bizOrderNo,
            'payerId' => $payerId,
            'recieverList' => array($recieverList),
            'goodsType' => $goodsType,
            'goodsNo' => $goodsNo,
            'tradeCode' => $tradeCode,
            'amount' => $amount,
            'fee' => $fee,
            'frontUrl' => NOTICE_URL,
            'backUrl' => BACKURL,
            'showUrl' => $showUrl,
            'ordErexpireDatetime' => $ordErexpireDatetime,
            'payMethod' => $payMethod,
            'goodsName' => $goodsName,
            'goodsDesc' => $goodsDesc,
            'industryCode' => $industryCode,
            'industryName' => $industryName,
            'source' => $source,
            'summary' => $summary,
            'extendInfo' => $extendInfo,
        );
        $result = $client->request('OrderService', 'agentCollectApplySimplify', $param);
        print_r(json_encode($param));//将数据格式的数据转换成json格式的数据
        print_r($result);
        die();

    }


    /**
     * 单笔代付
     * @param $bizOrderNo 商户订单号
     * @param $collectPayList 代收订单付款信息                 bizOrderNo  订单编号                    amount     金额，单位：分   ;部分代付时，可以少于或等于代收订单金额
     * @param $bizUserId 商户系统用户标识，商户系统中唯一编号。代收订单中指定的收款人。
     * @param $accountSetNo 收款人的账户集编号。
     * @param $backUrl 后台通知地址
     * @param $payToBankCardInfo 代付到银行卡的信息 ，如果是代付到银行卡，则必填          bankCardNo   银行卡号。只支持绑定的银行卡号。RSA加密。      amount 代付到银行卡中的金额      backUrl  后台通知地址，覆盖外面的backUrl
     * @param $amount 总金额
     * @param $fee 手续费
     * @param $splitRuleList 分账规则
     * @param $goodsType 商品类型       默认无商品类型，值为0。
     * @param $goodsNo 商户系统商品编号
     * @param $tradeCode 业务码
     * @param $summary 摘要
     * @param $extendInfo 扩展参数
     */

    public function actionSignalAgentPay()
    {
        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);

        //请求参数
        $customer = $this->model->table('customer')->fields('bizuserid')->where('user_id='.$this->user['id'])->find();
        $bizUserId = $customer['bizuserid'];
        $bizOrderNo = Req::args('bizOrderNo');
        $accountSetNo = Req::args('accountSetNo');
        $amount = Filter::int(Req::args('amount'));    //只能为整型
        $fee = Filter::int(Req::args('fee')); //只能为整型

        $collectPay = new stdClass();
        $collectPay->bizOrderNo = Req::args('bizOrderNo');
        $collectPay->amount = Filter::int(Req::args('amount'));
        $payToBankCardInfo = new stdClass();
        // 托管代付到银行账户信息
        if (Req::args('payToBankCardInfos') == '1') {
            $payToBankCardInfo->bankCardNo = $this->rsaEncrypt(Req::args('bankCardNo'), $publicKey, $privateKey);
            $payToBankCardInfo->amount = Filter::int(Req::args('amount'));
            $payToBankCardInfo->backUrl = BACKURL;
        } else {
            $payToBankCardInfo;
        }

        //分账规则
        $splistRule1 = new stdClass();
        if (Req::args('splitRuleLists') == '1') {
            $splistRule1->bizUserId = Req::args('bizUserIds');
            $splistRule1->accountSetNo = Req::args('accountSetNos');
            $splistRule1->amount = Filter::int(Req::args('amounts'));
            $splistRule1->fee = Filter::int(Req::args('fees'));
            $splistRule1->remark = Req::args('remark');
        } else {
            $splistRule1->bizUserId = Req::args('bizUserIds');
            $splistRule1->accountSetNo = Req::args('accountSetNos');
            $splistRule1->amount = Filter::int('0');
            $splistRule1->fee = Filter::int('0');
            $splistRule1->remark = Req::args('remark');
        }
        $goodsType = Filter::int(Req::args('goodsType')); //只能为整型
        $goodsNo = Req::args('goodsNo');
        $tradeCode = Req::args('tradeCode');
        $summary = Req::args('summary');
        $extendInfo = Req::args('extendInfo');
        $req = array(
            'bizOrderNo' => $bizOrderNo,
            'collectPayList' => array($collectPay),
            'bizUserId' => $bizUserId,
            'accountSetNo' => $accountSetNo,
            'backUrl' => BACKURL,
            'payToBankCardInfo' => $payToBankCardInfo,
            'amount' => $amount,
            'fee' => $fee,
            'splitRuleList' => array($splistRule1),
            'goodsType' => $goodsType,
            'goodsNo' => $goodsNo,
            'tradeCode' => $tradeCode,
            'summary' => $summary,
            'extendInfo' => $extendInfo,
        );
        $result = $client->request('OrderService', 'signalAgentPaySimplify', $req);
        print_r(json_encode($req));
        print_r($result);
        die();


    }

    /**
     * 批量代付
     * @param $bizBatchNo 商户批次号
     * @param $batchPayList 批量代付列表
     * @param $goodsType 商品类型             P2P标的  1    虚拟商品    2    实物商品   3     线下服务     4    营销活动     90   其他   99     如无商品类型，默认为0。
     * @param $goodsNo 商户系统商品编号          仅当商品类型!=0时必填。
     * @param $tradeCode 业务码
     */


    public function actionBatchAgentPay()
    {
        //接入前的准备工作
        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);

        $bizBatchNo = Req::args('bizBatchNo');
        $collectPay11 = new stdClass();
        $collectPay11->bizOrderNo = Req::args('bizOrderNo');
        $collectPay11->amount = Filter::int(Req::args('amount'));

        //批量托管代付列表
        $batchPay1 = new stdClass();
        $batchPay1->bizOrderNo = Req::args('bizOrderNo');
        $batchPay1->collectPayList = array($collectPay11);
        $batchPay1->bizUserId = Req::args('bizUserId');
        $batchPay1->accountSetNo = Req::args('accountSetNo');
        $batchPay1->backUrl = BACKURL;
        $batchPay1->amount = Filter::int(Req::args('amount'));
        $batchPay1->fee = Filter::int(Req::args('fee'));
        $batchPay1->summary = Req::args('summary');
        $batchPay1->extendInfo = Req::args('extendInfo');

        //参数
        $goodsType = Filter::int(Req::args('goodsType'));
        $goodsNo = Req::args('goodsNo');
        $tradeCode = Req::args('tradeCode');

        $param = array(
            'bizBatchNo' => $bizBatchNo,
            'batchPayList' => array($batchPay1),
            'goodsType' => $goodsType,
            'goodsNo' => $goodsNo,
            'tradeCode' => $tradeCode,
        );
        $result = $client->request('OrderService', 'batchAgentPaySimplify', $param);
        print_r(json_encode($result));
        print_r($result);
        die();

    }

    /**
     * 强实名认证
     * @param $bizOrderNo 商户订单号
     * @param $bizUserId 商户系统用户标识，商户系统中唯一编号。
     * @param $accountSetNo 充值的账户集
     * @param $bankCardNo 银行卡号       银行卡号，必须为已经绑定的借记卡。RSA加密。
     * @param $payType 认证支付方式         payType=27或28（27为移动认证支付，28为PC认证支付）
     * @param $bankCode 发卡机构，payType= 28时必填。
     * @param $ordErexpireDatetime 订单过期时间
     * @param $frontUrl 前台通知地址          payType=28时必填
     * @param $backUrl 后台通知地址
     * @param $summary 摘要         交易内容最多20个字符
     * @param $extendInfo 扩展信息
     */

    public function actionHigherCardAuthApply()
    {

        $bizOrderNo = '3212152';

        $accountSetNo = '333641';
        $bankCardNo = $this->rsa('6228480318051081871');
        $payType = 27;    //只能为整型
        $bankCode = '';
//         $ordErexpireDatetime='2016-08-05 21:12:00';
        $summary = '';
        $extendInfo = '';
        $req = array(
            'param' => array(
                'bizOrderNo' => $bizOrderNo,
                'bizUserId' => $this->bizUserId,
                'accountSetNo' => $accountSetNo,
                'bankCardNo' => $bankCardNo,
                'payType' => $payType,
                'bankCode' => $bankCode,

                // 'ordErexpireDatetime' => $ordErexpireDatetime,

                'frontUrl' => NOTICE_URL,
                'backUrl' => BACKURL,

                'summary' => $summary,
                'extendInfo' => $extendInfo,

            ),
            'service' => urlencode('OrderService'), //服务对象
            'method' => urlencode('higherCardAuthApply')    //调用方法
        );
        $result = $this->sendgate($req);
        echo $result;

    }


    /**
     * 确认支付（后台支付&前台支付）
     * @param $bizUserId 商户系统用户标识，商户系统中唯一编号。
     * @param $bizOrderNo 商户订单号
     * @param $tradeNo 交易编号            快捷支付必传       (前台支付不用传)
     * @param $verificationCode 短信验证码         (前台支付: 如有除网关之外的支付方式，则必传)
     * @param $consumerIp ip地址
     */

    public function actionPay()
    {
        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);
    
        $user_id = $this->user['id'];
        $customer = $this->model->table('customer')->fields('bizuserid')->where('user_id='.$user_id)->find();  
        $bizUserId = $customer['bizuserid'];
        $bizOrderNo = Req::args('bizOrderNo');
        $model = new Model();
        $obj = $this->model->table('tradeno')->fields('trade_no,biz_orderno')->where("user_id='$user_id'AND biz_orderno='$bizOrderNo'")->find();
        if (!empty($obj)) {
            $tradeNo = $obj['trade_no'];
        } else {
            $tradeNo = '';
        }
        $verificationCode = Req::args('verificationCode');
        $consumerIp = $_SERVER['REMOTE_ADDR'];
        $param = array(
            'bizUserId' => $bizUserId,
            'bizOrderNo' => $bizOrderNo,
            'tradeNo' => $tradeNo,
            'verificationCode' => $verificationCode,
            'consumerIp' => $consumerIp,
            'jumpUrl' => 'http://www.ymlypt.com/payment/async_callbacks',
        );
        $result = $client->request('OrderService', 'pay', $param);
        print_r($result);
        die();

    }

    /**
     * 商品录入
     * @param $bizUserId 商户系统用户标识，商户系统中唯一编号。
     * @param $goodsType 商品类型             P2P标的  1    虚拟商品    2    实物商品   3     线下服务     4    营销活动     90   其他   99     如无商品类型，默认为0。
     * @param $bizGoodsNo 商户系统中商品编号
     * @param $goodsName 商品名称
     * @param $goodsDetail 商品详细信息
     * @param $goodsParams 商品参数
     * @param $showUrl 商品详情URL
     * @param $extendInfo 扩展信息
     */

    public function actionEntryGoods()
    {
        //配置参数
        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);
        //请求数据
        $customer = $this->model->table('customer')->fields('bizuserid')->where('user_id='.$this->user['id'])->find();
        $bizUserId = $customer['bizuserid'];
        $goodsType = Filter::int(Req::args('goodsType'));   //只能为整型
        $bizGoodsNo = Req::args('bizGoodsNo');
        $goodsName = Req::args('goodsName');
        $goodsDetail = Req::args('goodsDetail');
        $showUrl = Req::args('showUrl');
        //商品参数必填
        $goodsParams = new stdClass();
        $goodsParams->amount = Filter::int(Req::args('amount'));
        $goodsParams->totalAmount = Filter::int(Req::args('totalAmount'));
        $goodsParams->highestAmount = Filter::int(Req::args('highestAmount'));
        $goodsParams->annualYield = Filter::float(Req::args('annualYield'));
        $goodsParams->investmentHorizon = Filter::int(Req::args('investmentHorizon'));
        $goodsParams->investmentHorizonScale = Filter::int(Req::args('investmentHorizonScale'));
        $goodsParams->beginDate = Req::args('beginDate');
        $goodsParams->endDate = Req::args('endDate');
        $goodsParams->repayType = Filter::int(Req::args('repayType'));
        $goodsParams->guaranteeType = Filter::int(Req::args('guaranteeType'));
        $goodsParams->repayPeriodNumber = Filter::int(Req::args('repayPeriodNumber'));
        $goodsParams->minimumAmountInvestment = Filter::int(Req::args('minimumAmountInvestment'));

        $param = array(
            'bizUserId' => $bizUserId,
            'goodsType' => $goodsType,
            'bizGoodsNo' => $bizGoodsNo,
            'goodsName' => $goodsName,
            'goodsDetail' => $goodsDetail,
            'goodsParams' => $goodsParams,
            'showUrl' => $showUrl,
        );
        $result = $client->request('OrderService', 'entryGoods', $param);
        print_r(json_encode($param));
        print_r($result);
        die();


    }

    /**
     * 查询、修改商品
     * @param $bizUserId 商户系统用户标识，商户系统中唯一编号。
     * @param $bizGoodsNo 商户系统中商品编号
     * @param $goodsType 商品类型             P2P标的  1    虚拟商品    2    实物商品   3     线下服务     4    营销活动     90   其他   99     如无商品类型，默认为0。
     * @param $beginDate  起息日,如不为空，则表示修改此字段。            yyyy-MM-dd
     * @param $endDate 到期日,如不为空，则表示修改此字段。                yyyy-MM-dd
     */

    public function actionQueryModifyGoods()
    {
        //配置参数
        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);
        //请求参数
        $customer = $this->model->table('customer')->fields('bizuserid')->where('user_id='.$this->user['id'])->find();
        $bizUserId = $customer['bizuserid'];
        $bizGoodsNo = Req::args('bizGoodsNo');
        $goodsType = Filter::int(Req::args('goodsType'));   //只能为整型
        $beginDate = Req::args('beginDate');
        $endDate = Req::args('endDate');
        $param = array(
            'bizUserId' => $bizUserId,
            'bizGoodsNo' => $bizGoodsNo,
            'goodsType' => $goodsType,
            'beginDate' => $beginDate,
            'endDate' => $endDate,
        );
        $result = $client->request('OrderService', 'queryModifyGoods', $param);
        print_r(json_encode($result));
        print_r($result);
        die();

    }

    /**
     * 冻结金额
     * @param $bizUserId 商户系统用户标识，商户系统中唯一编号。
     * @param $bizFreezenNo 商户冻结金额订单号
     * @param $accountSetNo 账户集编号
     * @param $amount  冻结金额
     */

    public function actionFreezeMoney()
    {
        //配置参数
        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);
        //参数
        $customer = $this->model->table('customer')->fields('bizuserid')->where('user_id='.$this->user['id'])->find();
        $bizUserId = $customer['bizuserid'];
        $bizFreezenNo = Req::args('bizFreezenNo');
        $accountSetNo = Req::args('accountSetNo');
        $amount = Filter::int(Req::args('amount'));    //只能为整型
        $param = array(
            'bizUserId' => $bizUserId,
            'bizFreezenNo' => $bizFreezenNo,
            'accountSetNo' => $accountSetNo,
            'amount' => $amount,
        );
        $result = $client->request('OrderService', 'freezeMoney', $param);
        print_r(json_encode($result));
        print_r($result);
        die();

    }

    /**
     * 解冻金额
     * @param $bizUserId 商户系统用户标识，商户系统中唯一编号。
     * @param $bizFreezenNo 商户冻结金额订单号           对应冻结金额时的订单号
     * @param $accountSetNo 账户集编号
     * @param $amount  冻结金额
     */

    public function actionUnfreezeMoney()
    {
        //配置参数
        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);
        //参数
        $customer = $this->model->table('customer')->fields('bizuserid')->where('user_id='.$this->user['id'])->find();
        $bizUserId = $customer['bizuserid'];
        $bizFreezenNo = Req::args('bizFreezenNo');
        $accountSetNo = Req::args('accountSetNo');
        $amount = Filter::int(Req::args('amount'));//只能为整型
        $param = array(
            'bizUserId' => $bizUserId,
            'bizFreezenNo' => $bizFreezenNo,
            'accountSetNo' => $accountSetNo,
            'amount' => $amount,
        );
        $result = $client->request('OrderService', 'unfreezeMoney', $param);
        print_r(json_encode($param));
        print_r($result);
        die();

    }

    /**
     * 退款
     * @param $bizOrderNo 商户订单编号
     * @param $oriBizOrderNo 商户原订单号                    需要退款的原交易订单号
     * @param $bizUserId 商户系统用户标识，商户系统中唯一编号。退款收款人。                   必须是原订单中的付款方
     * @param $refundList  代收订单中的收款人的退款金额         代收订单退款时必填。此字段总金额=amount- feeAmount。    bizUserId  商户系统用户标识，商户系统中唯一编号。    amount  金额，单位：分
     * @param $amount  本次退款总金额          单位：分。不得超过原订单金额。
     * @param $couponAmount  代金券退款金额         单位：分,不得超过退款总金额。如不填，则默认为0。如为0，则不退代金券。
     * @param $feeAmount  手续费退款金额         单位：分，不得超过退款总金额。如不填，则默认为0。如为0，则不退手续费。
     */

    public function actionRefund()
    {
        //配置参数
        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);
        //参数
        $bizOrderNo = Req::args('bizOrderNo');
        $oriBizOrderNo = Req::args('oriBizOrderNo');
        $customer = $this->model->table('customer')->fields('bizuserid')->where('user_id='.$this->user['id'])->find();
        $bizUserId = $customer['bizuserid'];
        $amount = Filter::int(Req::args('amount'));    //只能为整型
        $couponAmount = Filter::int(Req::args('couponAmount'));;    //只能为整型
        $feeAmount = Filter::int(Req::args('feeAmount'));;   //只能为整型
        $extendInfo = Req::args('extendInfo');

        $refund1 = new stdClass();
        $refund1->bizUserId = Req::args('bizUserId');
        $refund1->amount = Filter::int(Req::args('amount'));

        $param = array(
            'bizOrderNo' => $bizOrderNo,
            'oriBizOrderNo' => $oriBizOrderNo,
            'bizUserId' => $bizUserId,
            'refundList' => array($refund1),
            'backUrl' => BACKURL,
            'amount' => $amount,
            'couponAmount' => $couponAmount,
            'feeAmount' => $feeAmount,
            'extendInfo' => $extendInfo,
        );
        $result = $client->request('OrderService', 'refund', $param);
        print_r(json_encode($param));
        print_r($result);
        die();

    }

    /**
     * 流标专用退款
     * @param $bizBatchNo 商户批次号
     * @param $goodsType   商品类型             P2P标的  1    虚拟商品    2    实物商品   3     线下服务     4    营销活动     90   其他   99     如无商品类型，默认为0。
     * @param $goodsNo 商户系统商品编号
     * @param $batchRefundList  批量退款列表
     */

    public function actionFailureBidRefund()
    {

        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);

        $batchRefund1 = new stdClass();
        $batchRefund1->bizOrderNo = Req::args('bizOrderNo');
        $batchRefund1->oriBizOrderNo = Req::args('oriBizOrderNo');
        $batchRefund1->summary = Req::args('summary');
        $batchRefund1->extendInfo = Req::args('extendInfo');

        $bizBatchNo = Req::args('bizBatchNo');
        $goodsType = Filter::int(Req::args('goodsType'));   //只能为整型
        $goodsNo = Req::args('goodsNo');

        $batchRefundList = new stdClass();
        $batchRefundList->bizOrderNo = array(Req::args('bizOrderNo'));
        $batchRefundList->oriBizOrderNo = array(Req::args('oriBizOrderNo'));

        $param = array(
            'bizBatchNo' => $bizBatchNo,
            'goodsType' => $goodsType,
            'goodsNo' => $goodsNo,
            'batchRefundList' => array($batchRefund1),
        );
        $result = $client->request('OrderService', 'failureBidRefund', $param);
        print_r(json_encode($param));
        print_r($result);
        die();

    }

    /**
     * 平台转账
     * @param $bizTransferNo 商户系统转账编号,商户系统唯一
     * @param $sourceAccountSetNo   源账户集编号
     * @param $targetBizUserId 目标商户系统用户标识，商户系统中唯一编号。
     * @param $targetAccountSetNo  目标账户集编号
     * @param $amount 金额
     * @param $remark  备注
     * @param $extendInfo  扩展信息
     */

    public function actionApplicationTransfer()
    {
        //验证参数
        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);

        //传递的参数
        $bizTransferNo = Req::args('bizTransferNo');
        $sourceAccountSetNo = Req::args('sourceAccountSetNo');
        $targetBizUserId = Req::args('targetBizUserId');
        $targetAccountSetNo = Req::args('targetAccountSetNo');
        $amount = Filter::int(Req::args('amount'));//只能为整型
        $remark = Req::args('remark');
        $param = array(
            'bizTransferNo' => $bizTransferNo,
            'sourceAccountSetNo' => $sourceAccountSetNo,
            'targetBizUserId' => $targetBizUserId,
            'targetAccountSetNo' => $targetAccountSetNo,
            'amount' => $amount,
            'remark' => $remark,
        );
        $result = $client->request('OrderService', 'applicationTransfer', $param);
        print_r(json_encode($param));
        print_r($result);
        die();

    }

    /**
     * 查询余额
     * @param $bizUserId 商户系统用户标识，商户系统中唯一编号。
     * @param $accountSetNo   账户集编号
     */

    public function actionQueryBalance()
    {
        //验证参数
        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);
        //参数
        $customer = $this->model->table('customer')->fields('bizuserid')->where('user_id='.$this->user['id'])->find();
        $bizUserId = $customer['bizuserid'];
        $accountSetNo = Req::args('accountSetNo');
        $param = array(
            'bizUserId' => $bizUserId,
            'accountSetNo' => $accountSetNo,
        );
        $result = $client->request('OrderService', 'queryBalance', $param);
        print_r(json_encode($param));
        print_r($result);
        die();
    }

    /**
     * 查询订单状态
     * @param $bizUserId 商户系统用户标识，商户系统中唯一编号。
     * @param $bizOrderNo  商户订单号
     */

    public function actionGetOrderDetail()
    {
        //验证参数
        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);
        //参数
        $customer = $this->model->table('customer')->fields('bizuserid')->where('user_id='.$this->user['id'])->find();
        $bizUserId = $customer['bizuserid'];
        $bizOrderNo = Req::args('bizOrderNo');
        $param = array(
            'bizUserId' => $bizUserId,
            'bizOrderNo' => $bizOrderNo,
        );
        $result = $client->request('OrderService', 'getOrderDetail', $param);
        print_r(json_encode($param));
        print_r($result);
        die();

    }

    /**
     * 查询订单支付详情
     * @param $bizUserId 商户系统用户标识，商户系统中唯一编号。
     * @param $bizOrderNo  商户订单编号
     */

    public function actionQueryOrderPayDetail()
    {


        $bizOrderNo = '32';
        $req = array(
            'param' => array(
                'bizUserId' => $this->bizUserId,
                'bizOrderNo' => $bizOrderNo,

            ),
            'service' => urlencode('OrderService'), //服务对象
            'method' => urlencode('queryOrderPayDetail')    //调用方法
        );
        $result = $this->sendgate($req);
        echo $result;

    }


    /**
     * 查询账户收支明细
     * @param $bizUserId 商户系统用户标识，商户系统中唯一编号。
     * @param $accountSetNo  账户集编号                如果不传，则查询该用户下所有现金账户的收支明细。
     * @param $dateStart  开始日期            yyyy-MM-dd
     * @param $dateEnd  结束日期          yyyy-MM-dd
     * @param $startPosition  起始位置              eg：查询第11条到20条的记录（start =11）
     * @param $queryNum  查询条数                eg：查询第11条到20条的记录（queryNum =10）
     */

    public function actionQueryInExpDetail()
    {
        //验证参数
        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);
        //请求参数
        $customer = $this->model->table('customer')->fields('bizuserid')->where('user_id='.$this->user['id'])->find();
        $bizUserId = $customer['bizuserid'];
        $accountSetNo = Req::args('accountSetNo');
        $dateStart = Req::args('dateStart');
        $dateEnd = Req::args('dateEnd');
        $startPosition = Filter::int(Req::args('startPosition'));  //只能为整型
        $queryNum = Filter::int(Req::args('queryNum'));   //只能为整型
        $param = array(
            'bizUserId' => $bizUserId,
            'accountSetNo' => $accountSetNo,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
            'startPosition' => $startPosition,
            'queryNum' => $queryNum,
        );
        $result = $client->request('OrderService', 'queryInExpDetail', $param);
        print_r(json_encode($param));
        print_r($result);
        die();

    }

    /* 其他辅助类接口
     * 通联通头寸查询
     * @param sysid 分配的系统编号 String
     * */
    public function actionTlSearch()
    {
        //验证参数
        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);
        //请求参数
        $sysid = Req::args('sysid');
        $param = array(
            'sysid' => $sysid,
        );
        $result = $client->request('MerchantService', 'queryReserveFundBalance', $param);
        print_r(json_encode($param));
        print_r($result);
        die();
    }

    /*
     * 平台集合对账下载
     * @param date 对账文件日期 String yyyyMMdd
     * */
    public function platformDownload()
    {
        //验证参数
        $client = new SOAClient();
        $privateKey = RSAUtil::loadPrivateKey($this->alias, $this->path, $this->pwd);
        $publicKey = RSAUtil::loadPublicKey($this->alias, $this->path, $this->pwd);
        $client->setServerAddress($this->serverAddress);
        $client->setSignKey($privateKey);
        $client->setPublicKey($publicKey);
        $client->setSysId($this->sysid);
        $client->setSignMethod($this->signMethod);
        //请求参数
        $date = Req::args('date');
        $param = array(
            'date' => $date,
        );
        $result = $client->request('MerchantService', 'getCheckAccountFile', $param);
        print_r(json_encode($param));
        print_r($result);
        die();
    }


    function sendgate($req = '')
    {


        if (!$req) return false;
        $params_str = ICLOD_USERID . json_encode($req) . date('Y-m-d H:i:s');
        $sign = $this->sign($params_str);

        $paramer = 'sysid=' . urlencode(ICLOD_USERID) . '&sign=' . urlencode($sign) . '&timestamp=' . urlencode(date('Y-m-d H:i:s')) . '&v=' . urlencode($this->version) . '&req=' . urlencode(json_encode($req));
        // $array=array(
        //      'sysid'=>urlencode(ICLOD_USERID),
        //      'sign'=>urlencode($sign),
        //      'timestamp'=>date('Y-m-d H:i:s'),
        //      'v'=>urlencode($this->version),
        //      'req'=>$req
        //     );
        // var_dump($this->arrayXml->toXmlGBK($array,'AIPG'));
        // die(); 
        $obj = $this->curl_post($paramer);
        return $obj;
    }


    /*
     *签名数据：
     *data：utf-8编码的订单原文，
     *返回：base64转码的签名数据
     */
    function sign($data)
    {
        $priKey = file_get_contents(ICLOD_CERT_PATH);
        $res = openssl_get_privatekey($priKey);
        openssl_sign($data, $sign, $res);
        openssl_free_key($res);
        //base64编码
        $sign = base64_encode($sign);
        return $sign;
    }

    /*
     *curl请求： 
     *data：utf-8编码的请求参数 
     *返回：array()
    */

    function curl_post($data = null)
    {
        //Log::write("allinpay".$data);
        file_put_contents(dirname(__FILE__) . '/log' . date('ymd') . '.txt', $data . "#\r\n", FILE_APPEND);;
        $ch = curl_init();// 启动一个CURL会话
        curl_setopt($ch, CURLOPT_URL, ICLOD_Server_URL);// 要访问的地址
        curl_setopt($ch, CURLOPT_POST, 1);// 发送一个常规的Post请求
        curl_setopt($ch, CURLOPT_HEADER, 0);// 显示返回的Header区域内容
        curl_setopt($ch, CURLOPT_TIMEOUT, 50); // 设置超时限制防止死循环
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);// 获取的信息以文件流的形式返回
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);//Post提交的数据包

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        file_put_contents(dirname(__FILE__) . '/log' . date('ymd') . '.txt', $response . "#\r\n\r\n\r\n", FILE_APPEND);;
        return $response;

        echo $response;
        exit;
        if ($httpCode == '200') {
            $result = json_decode($response, true);
            //var_dump($result);
            return $result;
            if ($this->verify($result)) {
                return $result;
            } else {
                return array("status" => "error", "errorCode" => "签名失败");
            }

        } else {
            return array("status" => "error", "errorCode" => "请求失败");
        }
    }

    /*
     *验签： 
     *data：utf-8编码的订单原文， 
     *返回：boolean 
    */
    function verify($data)
    {
        $publickey = file_get_contents(ICLOD_CERT_PATH);
        $res = openssl_get_publickey($publickey);
        $result = (bool)openssl_verify($data['signedValue'], base64_decode($data['sign']), $res);
        openssl_free_key($res);
        return $result;
    }

    //加密
    function rsaEncrypt($str, $publicKey, $privateKey)
    {
        $rsaUtil = new RSAUtil($publicKey, $privateKey);
        $encryptStr = $rsaUtil->encrypt($str);
        return $encryptStr;
    }

    //解密
    function rsaDecrypt($str, $publicKey, $privateKey)
    {
        $rsaUtil = new RSAUtil($publicKey, $privateKey);
        $encryptStr = $rsaUtil->decrypt($str);
        return $encryptStr;
    }


}//类定义结束




