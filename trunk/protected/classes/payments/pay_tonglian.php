<?php
header("Content-type: text/html; charset=utf-8");
define('ICLOD_USERID','100009001000');//商户id 分配系统的编号
// define('ICLOD_CERT_PATH',dirname(__FILE__).'/100009001000_private_rsa.pem' ); //私钥文件
define('ICLOD_CERT_PATH',dirname(__FILE__).'/private_rsa.pem' ); //私钥文件
// define('ICLOD_CERT_PUBLIC_PATH',dirname(__FILE__).'/100009001000_public_rsa.pem' );//公钥文件
define('ICLOD_CERT_PUBLIC_PATH',dirname(__FILE__).'/public_rsa.pem' );//公钥文件
define('ICLOD_Server_URL','http://122.227.225.142:23661/service/soa');  //接口网关

define('NOTICE_URL','http://122.227.225.142:23661/service/soa'); //前台通知地址
define('BACKURL','http://122.227.225.142:23661/service/soa');//后台通知地址

    
/**
 * Iclod 云账户对接类
 * 
 * @category   ORG
 * @package  ORG
 * @subpackage  Net
 * @author    gyfbao 
 */
class Pay_tonglian{

    public $date='';
    public $version='1.0';
    public $bizUserId='666688912';
    
	/**
	 * 创建会员 
	 * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
	 * @param $source 手机 1 整型      PC 2 整型
	 * @param $memberType   企业会员 2       个人会员 3
	 * @param $extendParam   扩展参数     
	 */

   public function actionCreateMember (){

        
      $bizUserId='666688912'; //分配的系统编号
      $source=2;  //访问终端类型 1表示手机 2表示PC
      $memberType=2; //会员类型 2是企业人员 3表示个人会员
 
      
      $req=array(
           'param' =>array(
               'bizUserId' => $bizUserId,
               'memberType' =>$memberType,//企业会员 2 整型          个人会员 3 整型
               'source' =>$source,//手机 1 整型      PC 2 整型 
           ),
           'service' => urlencode('MemberService'), //服务对象
           'method' => urlencode('createMember')    //调用方法
       );

      $result=$this->sendgate($req);
      echo $result;
        
    }
    /**
     * 发送短信验证码 
     * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
     * @param $phone 手机号码
     * @param $verificationCodeType   验证码类型  解绑手机 6   绑定手机 9 
     * @param $extendParam 其他信息，用于生成短信验证码内容。
     */
    
    public function actionSendVerificationCode(){
    
        
        $phone='15821953549';
        $verificationCodeType=9;
        $extendParam=new stdClass();
        $extendParam->msg='22号';//如果没有参数，去掉
      
        $req=array(
            'param' =>array(
                'bizUserId' => $this->bizUserId,
                'phone' =>$phone,//
                'verificationCodeType' =>$verificationCodeType,  //只能为整型
                'extendParam' => $extendParam,//有疑问
            ),
            'service' => urlencode('MemberService'), //服务对象
            'method' => urlencode('sendVerificationCode')    //调用方法
        );
    
    
        $result=$this->sendgate($req);
        echo $result;
    
    }
    
    
    /**
     * 验证短信验证码
     * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
     * @param $phone 手机号码
     * @param $verificationCodeType   验证码类型  解绑手机 6       绑定手机 9
     * @param $verificationCode 验证码
     */
    
    public function actionCheckVerificationCode(){
    
     
        $phone='15522151235';
        $verificationCodeType=9;
        $verificationCode='719521';
        $req=array(
            'param' =>array(
                'bizUserId' => $this->bizUserId,
                'phone' =>$phone,
                'verificationCodeType' =>$verificationCodeType,//手机 1 整型      PC 2 整型
                'verificationCode' => $verificationCode,
            ),
            'service' => urlencode('MemberService'), //服务对象
            'method' => urlencode('checkVerificationCode')    //调用方法
        );
    
    
        $result=$this->sendgate($req);
        echo $result;
    
    }
    
    /**
     * 个人实名认证
     * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
     * @param $isAuth  是否由云账户进行认证  true/false  默认为true   目前必须通过云账户认证
     * @param $name   姓名  
     * @param $identityType 证件类型     身份证 1  护照 2   港澳通行证 3    目前只支持身份证。
     * @param $identityNo 证件号码      RSA加密
     */
    
    public function actionSetRealName(){
    
      
        
        $name="郑飞";
        $identityType=1;
        $identityNo=$this->rsa('330227198805284412');
        $req=array(
            'param' =>array(
                'bizUserId' => $this->bizUserId,
                'isAuth' =>true,//默认为true  目前必须通过云账户认证
                'name' =>$name,
                'identityType' =>$identityType,//只能为整型
                'identityNo' =>$identityNo
            ),
            'service' => urlencode('MemberService'), //服务对象
            'method' => urlencode('setRealName')    //调用方法
        );
    
    
        $result=$this->sendgate($req);
         
        echo $result;
    }
    
    
    /**
     * 绑定手机
     * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
     * @param $phone  手机号码
     * @param $verificationCode   验证码
     */
    
    public function actionBindPhone(){
    
       
        $phone='15821953549';
        $verificationCode='1234';

        $req=array(
            'param' =>array(
                'bizUserId' => $this->bizUserId,
                'phone' =>$phone,
                'verificationCode' =>$verificationCode,
            ),
            'service' => urlencode('MemberService'), //服务对象
            'method' => urlencode('bindPhone')    //调用方法
        );
    
    
        $result=$this->sendgate($req);
        echo $result;
    
    }
    
    
    /**
     * 设置企业会员信息
     * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
     * @param $companyBasicInfo  企业基本信息   companyName企业名称      companyAddress企业地址      businessLicense营业执照号       organizationCode组织机构代码   
     *                                    telephone联系电话      legalName法人姓名       identityType法人证件类型        legalIds法人证件号码(RSA加密)
     *                                    legalPhone法人手机号码           accountNo企业对公账户账号(RSA加密)       parentBankName开户银行名称
     * @param $companyExtendInfo   企业扩展信息       目前不需要传
     */
    
    public function actionSetCompanyInfo(){
    
       
       
        $companyBasicInfo=new stdClass();
        $companyBasicInfo->companyName='龙头企业';//企业名称      
        $companyBasicInfo->companyAddress='龙头企业地址';//企业地址      
        $companyBasicInfo->businessLicense='24561CXv315';//营业执照号      
        $companyBasicInfo->organizationCode='32121132';//组织机构代码
        $companyBasicInfo->telephone='15821953549';//联系电话     
        $companyBasicInfo->legalName='白鸽';//法人姓名       
        $companyBasicInfo->identityType=1;//法人证件类型       
        $companyBasicInfo->legalIds=$this->rsa('330227198805284412');//法人证件号码(RSA加密)
                                           
        $companyBasicInfo->legalPhone='15821953549';//法人手机号码          
        $companyBasicInfo->accountNo=$this->rsa('330227198805284412');//企业对公账户账号(RSA加密)       
        $companyBasicInfo->parentBankName='中国银行';//'开户银行名称';
        $companyBasicInfo->bankCityNo='777777';//'开户银行名称'
        $companyBasicInfo->bankName='宁波支行';//'开户银行名称'
        $companyBasicInfo->parentBankName='龙头企业银行';//'开户银行名称'
      
        	
        $companyExtendInfo=new stdClass();
        $req=array(
            'param' =>array(
                'bizUserId' => $this->bizUserId,
                'companyBasicInfo' =>$companyBasicInfo,
               'companyExtendInfo' =>$companyExtendInfo,
            ),
            'service' => urlencode('MemberService'), //服务对象
            'method' => urlencode('setCompanyInfo')    //调用方法
        );
    
    
        $result=$this->sendgate($req);
        echo $result;
    
    }
    
    
    /**
     * 设置个人会员信息
     * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
     * @param $userInfo  基本信息   name名称      country国家      province省份       area县市     address地址
     */
    
    public function actionSetMemberInfo(){

   

        $userInfo=new stdClass();
        $userInfo->name='白鸽';
        $userInfo->country='中国';
        $userInfo->province='江苏省';
        $userInfo->area='南京市';
        $userInfo->address='解放路'; 
 
        $req=array(
            'param' =>array(
                'bizUserId'=> $this->bizUserId,
                'userInfo'=>$userInfo,//
            ),
            'service' => urlencode('MemberService'), //服务对象
            'method' => urlencode('setMemberInfo')    //调用方法
        );
        $result=$this->sendgate($req);
        echo $result;
    }
    
    /**
     * 获取会员信息（个人和企业）
     * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
     */
    
    public function actionGetMemberInfo(){
    
      
        $req=array(
            'param' =>array(
                'bizUserId' => $this->bizUserId,
            ),
            'service' => urlencode('MemberService'), //服务对象
            'method' => urlencode('getMemberInfo')    //调用方法
        );
        $result=$this->sendgate($req);
        echo $result;
    }
    
    
    /**
     * 查询卡bin
     * @param $cardNo 银行卡号   RSA加密
     */
    
    public function actionGetBankCardBin(){
    
        $cardNo=$this->rsa('6228480318051081101');  //银行卡rsa加密之后传递过来
        $req=array(
            'param' =>array(
                'cardNo' => $cardNo,
            ),
            'service' => urlencode('MemberService'), //服务对象
            'method' => urlencode('getBankCardBin')    //调用方法
        );
        $result=$this->sendgate($req);
        
        echo $result;
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
    
    public function actionApplyBindBankCard(){
    
     
        $cardNo=$this->rsa('6228480318051081101');//必须rsa加密
        $phone='15821953549';
        $name='白鸽';
        $cardType=1;  //卡类型   储蓄卡 1 整型         信用卡 2 整型
        $bankCode='01030000';//上一部获取的  GetBankCardBin返回 bankCode
        $identityType=1;          //只能为整型
        $identityNo=$this->rsa('330227198805284412');//必须rsa加密
        $validate='';
        $cvv2='';
        $isSafeCard=false;  //信用卡时不能填写： true:设置为安全卡，false:不 设置。默认为 false
        
        $req=array(
            'param' =>array(
                'bizUserId' => $this->bizUserId,
                'cardNo' => $cardNo,
                'phone' => $phone,
                'name' => $name,
                'cardType' => $cardType,
                'bankCode' => $bankCode,
                'identityType' => $identityType, //只能为整型
                'identityNo' => $identityNo,
             
                'isSafeCard' => $isSafeCard,
            ),
            'service' => urlencode('MemberService'), //服务对象
            'method' => urlencode('applyBindBankCard')    //调用方法
        );
        
        
        if ($cardType==2){
            // 信用卡    有下面的参数
            $req['param']['validate']=$validate;
            $req['param']['cvv2']=$cvv2;
        }else{
          
           $req['param']['isSafeCard']=$isSafeCard;
        }
       
        $result=$this->sendgate($req);
        echo $result;
    }
    
    
    /**
     * 确认绑定银行卡
     * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
     * @param $tranceNum 流水号         请求绑定银行卡接口返回
     * @param $transDate 申请时间            请求绑定银行卡接口返回
     * @param $phone 银行预留手机
     * @param $verificationCode 短信验证码
     */
    
    public function actionBindBankCard(){
    
       
        $tranceNum='D201605194156';//上一接口返回tranceNum 
        $transDate='20160519';//上一接口返回transDate 
        $phone='15821953549';  
        $verificationCode='1234';
        $req=array(
            'param' =>array(
                'bizUserId' => $this->bizUserId,
                'tranceNum' => $tranceNum,
                'transDate' => $transDate,
                'phone' => $phone,
                'verificationCode' => $verificationCode,
            ),
            'service' => urlencode('MemberService'), //服务对象
            'method' => urlencode('bindBankCard')    //调用方法
        );
        $result=$this->sendgate($req);
        echo $result;
    }
    
    
    
    /**
     * 设置安全卡
     * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
     * @param $cardNo 银行卡号
     * @param $setSafeCard 是否设置为安全卡                        默认为true,目前不支持false
     */
    
    public function actionSetSafeCard(){
    
        
        $cardNo=$this->rsa('6228480318051081101');//必须rsa加密
        $setSafeCard=true;
        $req=array(
            'param' =>array(
                'bizUserId' => $this->bizUserId,
                'cardNo' => $cardNo,
                'setSafeCard' => $setSafeCard,
            ),
            'service' => urlencode('MemberService'), //服务对象
            'method' => urlencode('setSafeCard')    //调用方法
        );
        $result=$this->sendgate($req);
        echo $result;
    }
    /**
     * 查询绑定银行卡
     * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
     * @param $cardNo 银行卡号。如为空，则返回用户所有绑定银行卡。(RSA加密)
     */
    
    public function actionQueryBankCard(){
    
      
        $cardNo=$this->rsa('6228480318051081101');//必须rsa加密
        $req=array(
            'param' =>array(
                'bizUserId' => $this->bizUserId,
                'cardNo' => $cardNo
            ),
            'service' => urlencode('MemberService'), //服务对象
            'method' => urlencode('queryBankCard')    //调用方法
        );
        $result=$this->sendgate($req);
        echo $result;
    }
    
    /**
     * 解绑绑定银行卡
     * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
     * @param $cardNo 银行卡号(RSA加密)
     */
    
    public function actionUnbindBankCard(){
    
        
        $cardNo=$this->rsa('6228480318051081101');//必须rsa加密
        $req=array(
            'param' =>array(
                'bizUserId' => $this->bizUserId,
                'cardNo' => $cardNo
            ),
            'service' => urlencode('MemberService'), //服务对象
            'method' => urlencode('unbindBankCard')    //调用方法
        );
        $result=$this->sendgate($req);
        echo $result;
    }
    
    
    /**
     * 更改绑定手机
     * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
     * @param $oldPhone 原手机号码
     * @param $oldVerificationCode 原手机验证码
     * @param $newPhone 新手机号码
     * @param $newVerificationCode 新手机验证码
     */
    
    public function actionChangeBindPhone(){
    
     
        $oldPhone='15821953549';
        $oldVerificationCode='1234';
        $newPhone='15821953599';
        $newVerificationCode='1234';
        $req=array(
            'param' =>array(
                'bizUserId' => $this->bizUserId,
                'oldPhone' => $oldPhone,
                'oldVerificationCode' => $oldVerificationCode,
                'newPhone' => $newPhone,
                'newVerificationCode' => $newVerificationCode
            ),
            'service' => urlencode('MemberService'), //服务对象
            'method' => urlencode('changeBindPhone')    //调用方法
        );
        $result=$this->sendgate($req);
        echo $result;
    }
    
    /**
     * 锁定用户
     * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
     */
    
    public function actionLockMember(){
    
        
        $req=array(
            'param' =>array(
                'bizUserId' => $this->bizUserId,
            ),
            'service' => urlencode('MemberService'), //服务对象
            'method' => urlencode('lockMember')    //调用方法
        );
        $result=$this->sendgate($req);
        echo $result;
    }
    
    /**
     * 解锁用户
     * @param $bizUserId 商户系统用户标识，商户 系统中唯一编号
     */
    
    public function actionUnlockMember(){
    
       
        $req=array(
            'param' =>array(
                'bizUserId' => $this->bizUserId,
            ),
            'service' => urlencode('MemberService'), //服务对象
            'method' => urlencode('unlockMember')    //调用方法
        );
        $result=$this->sendgate($req);
        echo $result;
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
     * @param $ordErexpireDatetime 订单过期时间                       yyyy-MM-dd HH:mm:ss订单最长时效为24小时。默认为最长时效。只在第一次提交订单时有效。
     * @param $payMethod 支付方式
     * @param $industryCode 行业代码
     * @param $industryName 行业名称
     * @param $source 访问终端类型                                             手机 1   PC 2  
     * @param $summary 摘要                    交易内容最多20个字符
     * @param $extendInfo 扩展信息
     */
    
    public function actionDepositApply(){
    
        $bizOrderNo='201605160001';
       
        $accountSetNo='200001';
        $amount=100;  //必须整形
        $fee=2;//必须整形
      
       // $ordErexpireDatetime='';
        $payMethod =new  stdClass();
       // $payMethod->BALANCE=array('accountSetNo'=>'','amount'=>100);//账户余额
        $payMethodb =new  stdClass();
       
     /*    //快捷
        $payMethodb->bankCardNo=$this->rsa('6228480318051081871');
        $payMethodb->amount=100;
        $payMethod->QUICKPAY=$payMethodb; //快捷支付（需要先绑定银行 卡） 
         */
        //网关
        $payMethodb =new  stdClass();
        $payMethodb->bankCode='cmb';
        $payMethodb->payType=1;
        $payMethodb->bankCardNo=$this->rsa('6228480318051081101');
        $payMethodb->amount=100 ;//快捷支付（需要先绑定银行 卡）
        $payMethod->GATEWAY =$payMethodb;
        
        
      
        $industryCode='1010';
        $industryName='保险代理';
        $source=1;    //只能为整型
        $summary='测试摘要';
        $extendInfo='扩展测试';
        $req=array(
            'param' =>array(
                'bizOrderNo' => $bizOrderNo,
                'bizUserId' => $this->bizUserId,
                'accountSetNo' => $accountSetNo,
                'amount' => $amount,
                'fee' => $fee,
                'frontUrl' => NOTICE_URL,
                'backUrl' => BACKURL,
                //'ordErexpireDatetime' => $ordErexpireDatetime,
                'payMethod' => $payMethod,
                'industryCode' => $industryCode,
                'industryName' => $industryName,
                'source' => $source,
                'summary' => $summary,
                'extendInfo' => $extendInfo
            ),
            'service' => urlencode('OrderService'), //服务对象
            'method' => urlencode('depositApply')    //调用方法
        );
       
        $result=$this->sendgate($req);
        echo $result;
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
    
    public function actionWithdrawApply(){
    
        $bizOrderNo='201605160001';
       
   
        $accountSetNo='200019';
        $amount=100;    //只能为整型
        $fee=2;    //只能为整型
        $industryCode='1010';
        $industryName='保险代理';
        $source=1;      //只能为整型
        $summary='测试摘要';
        $extendInfo='扩展测试';
       // $ordErexpireDatetime='';
        $bankCardNo=$this->rsa('6228480318051081101');
        $bankCardPro=0;        //只能为整型
        $withdrawType='T0';
       
        
        $req=array(
            'param' =>array(
                'bizOrderNo' => $bizOrderNo,
                'bizUserId' => $this->bizUserId,
                'accountSetNo' => $accountSetNo,
                'amount' => $amount,
                'fee' => $fee,
                'backUrl' => BACKURL,
               // 'ordErexpireDatetime' => $ordErexpireDatetime,
                'bankCardNo' => $bankCardNo,
                'bankCardPro' => $bankCardPro,
                'withdrawType' => $withdrawType,
                'industryCode' => $industryCode,
                'industryName' => $industryName,
                'source' => $source,          //手机 1 整型              PC 2 整型
                'summary' => $summary,
                'extendInfo' => $extendInfo
            ),
            'service' => urlencode('OrderService'), //服务对象
            'method' => urlencode('withdrawApply')    //调用方法
        );
        $result=$this->sendgate($req);
        echo $result;
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
    
    public function actionConsumeApply(){
    
        $payerId='288888888';
        $recieverId='28888888';
        $bizOrderNo='201605260002';
        $amount=2; //只能为整型
        $fee=1;  //只能为整型
        $splitRule='';
      
        $showUrl='';
        $ordErexpireDatetime='';
        $payMethod =new  stdClass();

        
      
       // $ordErexpireDatetime='';
        $payMethod =new  stdClass();
  
        $payMethodb =new  stdClass();
       
     /*    //快捷
        $payMethodb->bankCardNo=$this->rsa('6228480318051081871');
        $payMethodb->amount=100;
        $payMethod->QUICKPAY=$payMethodb; //快捷支付（需要先绑定银行 卡） 
         */
        //网关
        $payMethodb =new  stdClass();
        $payMethodb->bankCode='cmb';
        $payMethodb->payType=1;
        $payMethodb->bankCardNo=$this->rsa('6228480318051081101');
        $payMethodb->amount=100 ;//快捷支付（需要先绑定银行 卡）
        $payMethod->GATEWAY =$payMethodb;
        
        $goodsName='保养优惠产品';
        $goodsDesc='保养优惠产品介绍';
        $industryCode='1010';
        $industryName='保险代理';
        $source=1;
        $summary='测试摘要';
        $extendInfo='扩展测试';
        $req=array(
            'param' =>array(
                'payerId' => $payerId,
                'recieverId' => $recieverId,
                'bizOrderNo' => $bizOrderNo,
                'amount' => $amount,
                'fee' => $fee,
            
                'frontUrl' =>NOTICE_URL,
                'backUrl' => BACKURL,
               // 'showUrl' => $showUrl,
               // 'ordErexpireDatetime' => $ordErexpireDatetime,
                'payMethod' => $payMethod,
                'goodsName' => $goodsName,
                'goodsDesc' => $goodsDesc,
                'industryCode' => $industryCode,
                'industryName' => $industryName,
                'source' => $source,
                'summary' => $summary,
                'extendInfo' => $extendInfo,

            ),
            'service' => urlencode('OrderService'), //服务对象
            'method' => urlencode('consumeApply')    //调用方法
        );
        $result=$this->sendgate($req);
        echo $result;
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
    
    public function actionAgentCollectApply(){
    
        $bizOrderNo='201608041702';
        $payerId='230561';
        $reciever1=new stdClass();
        $reciever1->bizUserId='201582';
        $reciever1->amount=350;
        
        $goodsType=0;   //只能为整型
        $goodsNo='';
        $tradeCode='321552';
        $amount=370;    //只能为整型
        $fee=20;    //只能为整型
//         $frontUrl='';
//         $backUrl='';
        $showUrl='';
//         $ordErexpireDatetime='';

 /*        $payMethodb=new stdClass();
        $payMethodb->bankCardNo=$this->rsa('6228480318051081871');
        $payMethodb->amount=100;
        $payMethod=new  stdClass();
        $payMethod->QUICKPAY=$payMethodb; */

       

        $payMethod =new  stdClass();
        $payMethodb =new  stdClass();

        
        $payMethodb->amount=100;
        $payMethodb->bankCardNo=$this->rsa('6228480318051081871');
        $payMethod->QUICKPAY=$payMethodb;
        
        $goodsName='维达';
        $goodsDesc='4层纸巾';
        $industryCode='54646461';
        $industryName='纸';
        $source=1;  //只能为整型
        $summary='纸';
        $extendInfo='。';
        $req=array(
            'param' =>array(
                'bizOrderNo' => $bizOrderNo,
                'payerId' => $payerId,

                'recieverList' =>array($recieverList),

             

                'goodsType' => $goodsType,
                'goodsNo' => $goodsNo,
                'tradeCode' => $tradeCode,
                'amount' => $amount,
                'fee' => $fee,
                'frontUrl' => NOTICE_URL,
                'backUrl' => BACKURL,
                
//                 'frontUrl' => $frontUrl,
//                 'backUrl' => $backUrl,
                
                'showUrl' => $showUrl,
//                 'ordErexpireDatetime' => $ordErexpireDatetime,

                'payMethod' =>$payMethod,


                'goodsName' => $goodsName,
                'goodsDesc' => $goodsDesc,
                'industryCode' => $industryCode,
                'industryName' => $industryName,
                'source' => $source,
                'summary' => $summary,
                'extendInfo' => $extendInfo,
    
            ),
            'service' => urlencode('OrderService'), //服务对象
            'method' => urlencode('agentCollectApply')    //调用方法
        );
        $result=$this->sendgate($req);
        echo $result;
    
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
    
    public function actionSignalAgentPay(){
    
        $bizOrderNo='080415804';
        $collectPay=new stdClass();
        $collectPay->bizOrderNo='080415804';
        $collectPay->amount=350;    
        
       
        $accountSetNo='86441';
//         $backUrl='';
        $payToBankCardInfo=new stdClass();
        $payToBankCardInfo->bankCardNo=$this->rsa('6228480318051081871');
        $payToBankCardInfo->amount=321;
        $payToBankCardInfo->backUrl=BACKURL;
        
        $amount=321;    //只能为整型
        $fee=1; //只能为整型

        $splistRule1=new stdClass();

        $splistRule1->bizUserId= "#yunBizUserId_application#";
        $splistRule1->accountSetNo= "3000001";
        $splistRule1->amount= 50;
        $splistRule1->fee= 0;
        $splistRule1->remark= "aaaa";
        $goodsType=0;       //只能为整型
        $goodsNo='';
        $tradeCode='5415414';
        $summary='';
        $extendInfo='';
        $req=array(
            'param' =>array(
                'bizOrderNo' => $bizOrderNo,
                'collectPayList' => array($collectPay),
                'bizUserId' => $this->bizUserId,
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
    
            ),
            'service' => urlencode('OrderService'), //服务对象
            'method' => urlencode('signalAgentPay')    //调用方法
        );
        $result=$this->sendgate($req);
        echo $result;
    
    }
    
    /**
     * 批量代付
     * @param $bizBatchNo 商户批次号
     * @param $batchPayList 批量代付列表
     * @param $goodsType 商品类型             P2P标的  1    虚拟商品    2    实物商品   3     线下服务     4    营销活动     90   其他   99     如无商品类型，默认为0。
     * @param $goodsNo 商户系统商品编号          仅当商品类型!=0时必填。
     * @param $tradeCode 业务码
     */

   

    public function actionBatchAgentPay(){
    
        $bizBatchNo='6565';
        
        
       
   
        $collectPay11 = new stdClass();
        $collectPay11->bizOrderNo= "1464183807844ds";
        $collectPay11->amount= 1;
     
  
   
        
        
        $batchPay1=new stdClass();
        $batchPay1->bizOrderNo='1212132';
        $batchPay1->collectPayList=array($collectPay11);
        $batchPay1->bizUserId='5345';
        $batchPay1->accountSetNo='121';
        $batchPay1->backUrl=BACKURL;
        $batchPay1->amount=1;
        $batchPay1->fee=0;
        $batchPay1->summary='单笔代付1';
        $batchPay1->extendInfo='扩展信息';

        $batchPay2=new stdClass();
        $batchPay2->bizOrderNo='1212132';
        $batchPay2->collectPayList=array($collectPay11);
        $batchPay2->bizUserId='5345';
        $batchPay2->accountSetNo='121';
        $batchPay2->backUrl=BACKURL;
        $batchPay2->amount=1;    //只能为整型
        $batchPay2->fee=0;   //只能为整型
        $batchPay2->summary='单笔代付1';
        $batchPay2->extendInfo='扩展信息';
        $goodsType=1;
        $goodsNo='商品';
        $tradeCode='2001';

     

        $req=array(
            'param' =>array(
                'bizBatchNo' => $bizBatchNo,
                'batchPayList' =>array($batchPay1,$batchPay2),
                'goodsType' => $goodsType,
                'goodsNo' => $goodsNo,
                'tradeCode' => $tradeCode,
    
            ),
            'service' => urlencode('OrderService'), //服务对象
            'method' => urlencode('batchAgentPay')    //调用方法
        );
        $result=$this->sendgate($req);
        echo $result;
    
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
    
    public function actionHigherCardAuthApply(){
    
        $bizOrderNo='3212152';
      
        $accountSetNo='333641';
        $bankCardNo=$this->rsa('6228480318051081871');
        $payType=27;    //只能为整型
        $bankCode='';
//         $ordErexpireDatetime='2016-08-05 21:12:00';
        $summary='';
        $extendInfo='';
        $req=array(
            'param' =>array(
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
        $result=$this->sendgate($req);
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
    
    public function actionPay(){
    
       
        $bizOrderNo='9865';
        $tradeNo='';
        $verificationCode='1234';
        $consumerIp='127.0.0.2';
        $req=array(
            'param' =>array(
                'bizUserId' => $this->bizUserId,
                'bizOrderNo' => $bizOrderNo,
                'tradeNo' => $tradeNo,
                'verificationCode' => $verificationCode,
                'consumerIp' => $consumerIp,
    
            ),
            'service' => urlencode('OrderService'), //服务对象
            'method' => urlencode('pay')    //调用方法
        );
        $result=$this->sendgate($req);
        echo $result;
    
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
    
    public function actionEntryGoods(){
    

     
        $goodsType=3;   //只能为整型

        $bizGoodsNo='54521';
        $goodsName='百事';
        $goodsDetail='可乐';
        
        $goodsParams=new stdClass();
//         $goodsParams->amount=10;
        $goodsParams->totalAmount=1000;
//         $goodsParams->highestAmount=1000;
        $goodsParams->annualYield=0.1;
        $goodsParams->investmentHorizon=12;
//         $goodsParams->investmentHorizonScale=2;
        $goodsParams->beginDate="2015-11-16";
        $goodsParams->endDate="2015-11-17";
        $goodsParams->repayType=3;
        $goodsParams->guaranteeType=5;
        $goodsParams->repayPeriodNumber=12;
        
        $showUrl='';
        $extendInfo='';
        $req=array(
            'param' =>array(
                'bizUserId' => $this->bizUserId,
                'goodsType' => $goodsType,
                'bizGoodsNo' => $bizGoodsNo,
                'goodsName' => $goodsName,
                'goodsDetail' => $goodsDetail,
                'goodsParams' => $goodsParams,
                'showUrl' => $showUrl,
                'extendInfo' => $extendInfo,
    
            ),
            'service' => urlencode('OrderService'), //服务对象
            'method' => urlencode('entryGoods')    //调用方法
        );
        $result=$this->sendgate($req);
        echo $result;
    
    }
    
    /**
     * 查询、修改商品
     * @param $bizUserId 商户系统用户标识，商户系统中唯一编号。
     * @param $bizGoodsNo 商户系统中商品编号
     * @param $goodsType 商品类型             P2P标的  1    虚拟商品    2    实物商品   3     线下服务     4    营销活动     90   其他   99     如无商品类型，默认为0。
     * @param $beginDate  起息日,如不为空，则表示修改此字段。            yyyy-MM-dd
     * @param $endDate 到期日,如不为空，则表示修改此字段。                yyyy-MM-dd
     */
    
    public function actionQueryModifyGoods(){
    
      
        $bizGoodsNo='45654';
        $goodsType=3;   //只能为整型
        $beginDate='';
        $endDate='';
        $req=array(
            'param' =>array(
                'bizUserId' => $this->bizUserId,
                'bizGoodsNo' => $bizGoodsNo,
                'goodsType' => $goodsType,
                'beginDate' => $beginDate,
                'endDate' => $endDate,
    
            ),
            'service' => urlencode('OrderService'), //服务对象
            'method' => urlencode('queryModifyGoods')    //调用方法
        );
        $result=$this->sendgate($req);
        echo $result;
    
    }
    
    /**
     * 冻结金额
     * @param $bizUserId 商户系统用户标识，商户系统中唯一编号。
     * @param $bizFreezenNo 商户冻结金额订单号
     * @param $accountSetNo 账户集编号
     * @param $amount  冻结金额
     */
    
    public function actionFreezeMoney(){
    
     
        $bizFreezenNo='5646';
        $accountSetNo='544561';
        $amount=360;    //只能为整型
        $req=array(
            'param' =>array(
                'bizUserId' => $this->bizUserId,
                'bizFreezenNo' => $bizFreezenNo,
                'accountSetNo' => $accountSetNo,
                'amount' => $amount,
    
            ),
            'service' => urlencode('OrderService'), //服务对象
            'method' => urlencode('freezeMoney')    //调用方法
        );
        $result=$this->sendgate($req);
        echo $result;
    
    }
    
    /**
     * 解冻金额
     * @param $bizUserId 商户系统用户标识，商户系统中唯一编号。
     * @param $bizFreezenNo 商户冻结金额订单号           对应冻结金额时的订单号
     * @param $accountSetNo 账户集编号 
     * @param $amount  冻结金额
     */
    
    public function actionUnfreezeMoney(){
    
      
        $bizFreezenNo='5646';
        $accountSetNo='544561';
        $amount=360;    //只能为整型
        $req=array(
            'param' =>array(
                'bizUserId' => $this->bizUserId,
                'bizFreezenNo' => $bizFreezenNo,
                'accountSetNo' => $accountSetNo,
                'amount' => $amount,
    
            ),
            'service' => urlencode('OrderService'), //服务对象
            'method' => urlencode('unfreezeMoney')    //调用方法
        );
        $result=$this->sendgate($req);
        echo $result;
    
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
    
    public function actionRefund(){
    
        $bizOrderNo='5656';
        $oriBizOrderNo='5154';
      
        $refund1=new stdClass();
        $refund1->bizUserId='12552';
        $refund1->amount=320;
        

        
        $refund2=new stdClass();
        $refund2->bizUserId='12552';
        $refund2->amount=320;
        
    
    

        $amount=320;    //只能为整型
        $couponAmount=0;    //只能为整型
        $feeAmount=0;   //只能为整型

        $req=array(
            'param' =>array(
                'bizOrderNo' => $bizOrderNo,
                'oriBizOrderNo' => $oriBizOrderNo,
                'bizUserId' => $this->bizUserId,
                'refundList' => array($refund2,$refund1),
                'amount' => $amount,
                'couponAmount' => $couponAmount,
                'feeAmount' => $feeAmount,
    
            ),
            'service' => urlencode('OrderService'), //服务对象
            'method' => urlencode('refund')    //调用方法
        );
        $result=$this->sendgate($req);
        echo $result;
    
    }
    
    /**
     * 流标专用退款
     * @param $bizBatchNo 商户批次号
     * @param $goodsType   商品类型             P2P标的  1    虚拟商品    2    实物商品   3     线下服务     4    营销活动     90   其他   99     如无商品类型，默认为0。
     * @param $goodsNo 商户系统商品编号
     * @param $batchRefundList  批量退款列表
     */
    
    public function actionFailureBidRefund(){
    
        
        
        
        $batchRefund1 = new stdClass();
      
        $batchRefund1->bizOrderNo='2016051602';
        $batchRefund1->oriBizOrderNo='1457682253913ds';
        $batchRefund1->summary="aaaa";
        
        $batchRefund2 = new stdClass();
        
        $batchRefund2->bizOrderNo="2016051603";
        $batchRefund2->oriBizOrderNo="1451273070827ds";
      
        $batchRefund2->summary="bbbb";
     
        $bizBatchNo='54112';
        $goodsType=3;   //只能为整型
        $goodsNo='fadf';

      

        $batchRefundList=new stdClass();
        $batchRefundList->bizOrderNo=array('12345');
        $batchRefundList->oriBizOrderNo=array('123456');
        

        $req=array(
            'param' =>array(
                'bizBatchNo' => $bizBatchNo,
                'goodsType' => $goodsType,
                'goodsNo' => $goodsNo,
                'batchRefundList' =>array($batchRefund1,$batchRefund2),
    
            ),
            'service' => urlencode('OrderService'), //服务对象
            'method' => urlencode('failureBidRefund')    //调用方法
        );
        $result=$this->sendgate($req);
        echo $result;
    
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
    
    public function actionApplicationTransfer(){
    
        $bizTransferNo='35154451';
        $sourceAccountSetNo='2000000';
        $targetBizUserId='3000001';
        $targetAccountSetNo='5000001';
        $amount=350;    //只能为整型
        $remark='';
        $extendInfo='';
        $req=array(
            'param' =>array(
                'bizTransferNo' => $bizTransferNo,
                'sourceAccountSetNo' => $sourceAccountSetNo,
                'targetBizUserId' => $targetBizUserId,
                'targetAccountSetNo' => $targetAccountSetNo,
                'amount' => $amount,
                'remark' => $remark,
                'extendInfo' => $extendInfo,
    
            ),
            'service' => urlencode('OrderService'), //服务对象
            'method' => urlencode('applicationTransfer')    //调用方法
        );
        $result=$this->sendgate($req);
        echo $result;
    
    }
    
    /**
     * 查询余额
     * @param $bizUserId 商户系统用户标识，商户系统中唯一编号。
     * @param $accountSetNo   账户集编号
     */
    
    public function actionQueryBalance(){
    
      
        $accountSetNo='322236';
        $req=array(
            'param' =>array(
                'bizUserId' => $this->bizUserId,
                'accountSetNo' => $accountSetNo,
    
            ),
            'service' => urlencode('OrderService'), //服务对象
            'method' => urlencode('queryBalance')    //调用方法
        );
        $result=$this->sendgate($req);
        echo $result;
    
    }
    
    /**
     * 查询订单状态
     * @param $bizUserId 商户系统用户标识，商户系统中唯一编号。
     * @param $bizOrderNo  商户订单号
     */
    
    public function actionGetOrderDetail(){
    
     
        $bizOrderNo='2331';
        $req=array(
            'param' =>array(
                'bizUserId' => $this->bizUserId,
                'bizOrderNo' => $bizOrderNo,
    
            ),
            'service' => urlencode('OrderService'), //服务对象
            'method' => urlencode('getOrderDetail')    //调用方法
        );
        $result=$this->sendgate($req);
        echo $result;
    
    }
    
    /**
     * 查询订单支付详情
     * @param $bizUserId 商户系统用户标识，商户系统中唯一编号。
     * @param $bizOrderNo  商户订单编号
     */
    
    public function actionQueryOrderPayDetail(){
    
       
        $bizOrderNo='32';
        $req=array(
            'param' =>array(
                'bizUserId' => $this->bizUserId,
                'bizOrderNo' => $bizOrderNo,
    
            ),
            'service' => urlencode('OrderService'), //服务对象
            'method' => urlencode('queryOrderPayDetail')    //调用方法
        );
        $result=$this->sendgate($req);
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
    
    public function actionQueryInExpDetail(){
    
      
        $accountSetNo='6533515';
        $dateStart='2015-12-04 14:08:09';
        $dateEnd='2015-12-05 14:08:09';
        $startPosition=15;  //只能为整型
        $queryNum=20;   //只能为整型
        $req=array(
            'param' =>array(
                'bizUserId' => $this->bizUserId,
                'accountSetNo' => $accountSetNo,
                'dateStart' => $dateStart,
                'dateEnd' => $dateEnd,
                'startPosition' => $startPosition,
                'queryNum' => $queryNum,
    
            ),
            'service' => urlencode('OrderService'), //服务对象
            'method' => urlencode('queryInExpDetail')    //调用方法
        );
        $result=$this->sendgate($req);
        echo $result;
    
    }
    
    
    function sendgate($req='')
    {
        
      
        
        if (!$req)  return   false;
        $params_str =ICLOD_USERID.json_encode($req).date("Y-m-d H:i:s");
        $sign = $this->sign($params_str);
        
        $paramer='sysid='.urlencode(ICLOD_USERID).'&sign='.urlencode($sign).'&timestamp='.urlencode(date("Y-m-d H:i:s")).'&v='.urlencode($this->version).'&req='.urlencode(json_encode($req));
         
        $obj=$this->curl_post($paramer);
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
     *rsa加密数据：
     *data：原文，
     *返回：加密后数据
     */
    function rsa($data)
    {
        $encryptData="";
        $priKey = file_get_contents(ICLOD_CERT_PATH);
        $res=openssl_get_privatekey($priKey);
        $result =openssl_private_encrypt($data, $encryptData, $res,OPENSSL_PKCS1_PADDING);
        openssl_free_key($res);
        return base64_encode($encryptData);
    
    }
    /*
     *解密RSA数据：
     *data：加密后数据，
     *返回：原文
     */
    function decryptRSA($data){
        $decryptData ='';
        $publickey =file_get_contents(C('ICLOD_CERT_PATH'));
        $res=openssl_get_publickey($publickey);
        $result=openssl_public_decrypt(base64_decode($data), $decryptData, $res);
        openssl_free_key($res);
        return $decryptData;
    
    }
    
	
	/* 
     *curl请求： 
     *data：utf-8编码的请求参数 
     *返回：array()
    */  
	
	function curl_post($data = null)
	{
		//Log::write("allinpay".$data);
		
	    file_put_contents(dirname(__FILE__).'/log'.date('ymd').'.txt',$data."#\r\n",FILE_APPEND);;
		$ch = curl_init ();// 启动一个CURL会话
        curl_setopt ( $ch, CURLOPT_URL,ICLOD_Server_URL );// 要访问的地址
        curl_setopt ( $ch, CURLOPT_POST, 1 );// 发送一个常规的Post请求 
        curl_setopt ( $ch, CURLOPT_HEADER, 0 );// 显示返回的Header区域内容
	    curl_setopt ( $ch, CURLOPT_TIMEOUT, 50); // 设置超时限制防止死循环
        curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );// 获取的信息以文件流的形式返回
        curl_setopt ( $ch, CURLOPT_POSTFIELDS, $data );//Post提交的数据包

        $response = curl_exec ( $ch );
	    $httpCode = curl_getinfo($ch,CURLINFO_HTTP_CODE);
        curl_close ( $ch );
        file_put_contents(dirname(__FILE__).'/log'.date('ymd').'.txt',$response."#\r\n\r\n\r\n",FILE_APPEND);;
        return  $response;
        
	   echo $response;exit;
	    if($httpCode=='200'){
		    $result = json_decode($response,true);
		    //var_dump($result);
		    return $result;
		    if($this->verify($result)){
				return $result;
			}
		    else{
				return array("status"=>"error","errorCode"=>"签名失败");
			}
			 
	    }
        else{
	        return array("status"=>"error","errorCode"=>"请求失败");
	    }
	}
	
	/* 
     *验签： 
     *data：utf-8编码的订单原文， 
     *返回：boolean 
    */  
	function verify($data)  
    {  	 
	  $publickey =file_get_contents(ICLOD_CERT_PATH); 
      $res=openssl_get_publickey($publickey);  
      $result = (bool)openssl_verify($data['signedValue'], base64_decode($data['sign']), $res);  
      openssl_free_key($res);

      return $result;
    }
	
	

   
}//类定义结束




