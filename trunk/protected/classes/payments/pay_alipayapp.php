<?php

/**
 * @class pay_alipayapp
 * @brief 支付宝插[APP]件类
 */
class pay_alipayapp extends PaymentPlugin {

    //支付插件名称
    public $name = '支付宝';

    public function submitUrl() {
        return 'https://mapi.alipay.com/gateway.do?_input_charset=utf-8';
    }

    //取得配制参数
    public static function config() {
        return array(
            array('field' => 'partner_id', 'caption' => '合作身份者id', 'type' => 'string'),
            array('field' => 'partner_key', 'caption' => '安全检验码key', 'type' => 'string'),
        );
    }

    //异步处理
    public function asyncCallback($callbackData, &$paymentId, &$money, &$message, &$orderNo) {
        if($paymentId==16){
            $model = new Model();
                $model->table('customer')->data(array('qq'=>'11111111'))->where('user_id=42608')->update();
            }
        //除去待签名参数数组中的空值和签名参数
        $filter_param = $this->filterParam($callbackData);

        //对待签名参数数组排序
        $para_sort = $this->argSort($filter_param);

        //生成签名结果
        $payment = new Payment($paymentId);
        $payment_plugin = $payment->getPaymentPlugin();
        $classConfig = $payment_plugin->getClassConfig();

        $data = $callbackData;
        unset($data['sign'], $data['sign_type']);
        $sortdata = $this->argSort($data);
        $prestr = $this->createLinkstring($sortdata);
        if ($this->rsaVerify($prestr, $callbackData['sign'])) {
            //回传数据
            $orderNo = $callbackData['out_trade_no'];
            $money = $callbackData['total_amount'];
            
            if ($callbackData['trade_status'] == 'TRADE_FINISHED' || $callbackData['trade_status'] == 'TRADE_SUCCESS') {
                if(stripos($orderNo, 'promoter') !== false){//保存推广员订单的out_trade_no
                    $district_order = new Model("district_order");
                    $district_order->data(array("out_trade_no"=>$callbackData['trade_no']))->where("order_no=$orderNo")->update();
                }else if(stripos($orderNo, 'recharge') === false){
                    $order = new Model("order");
                    $order->data(array("out_trade_no"=>$callbackData['trade_no']))->where("order_no=$orderNo")->update();//将out_trade_no保存起来
                }
                return true;
            }
        }
        return false;
    }

    //后期与服务同步处理类
    public function afterAsync() {
        return new AlipayDelivery($this->classConfig['partner_id'], $this->classConfig['partner_key']);
    }

    //打包数据
    public function packData($payment) {
        // include(__DIR__ . '/Alipay/aop/request/AlipayTradeAppPayRequest.php');
        // $return = array();

        // //基本参数
        // $return['app_id'] = '2017072607901626';
        // $return['timestamp'] = date("Y-m-d H:i:s");
        // $return['notify_url'] = $this->asyncCallbackUrl;

        // $biz_content = array(
        //     'timeout_express' => "120m",
        //     'product_code' => 'QUICK_MSECURITY_PAY',
        //     'total_amount' => "{$payment['M_Amount']}",
        //     'subject' => "{$payment['R_Name']}",
        //     'body' => "{$payment['R_Name']}",
        //     'out_trade_no' => "{$payment['M_OrderNO']}",
        // );
        // $return['biz_content'] = json_encode($biz_content);
        // $return['method'] = 'alipay.trade.app.pay';
        // $return['charset'] = 'utf-8';
        // $return['version'] = '1.0';
        // $return['sign_type'] = 'RSA2';

        // //除去待签名参数数组中的空值和签名参数
        // $filter_param = $this->filterParam($return);

        // //对待签名参数数组排序
        // $para_sort = $this->argSort($filter_param);

        // //生成签名结果
        // $mysign = $this->buildSign($para_sort, $payment['M_PartnerKey']);

        // //签名结果与签名方式加入请求提交参数组中
        // $return['sign'] = $mysign;
        // $return['builddata'] = http_build_query($return);
        // return $return;
        $aop = new AopClient();
        // $aop = new AopClient();
        $aop->appId = '2017080107981760';
        $aop->rsaPrivateKey = 'MIIEowIBAAKCAQEAx65HTliQYyJPWA6mDIO+Fe4KCyTQyvibQjFV70Uj6GA/4ac751YI7DaRCUsKZmrze+VyjoPjmJ8QEBMY2VBpfzym2NH5M3+uqCbWD3AYopxsUOsNM2PToC2bPsAnctoOZSX1/TTf6zfzD2PmUY8StHIWMCC7GA75hBuFcSl06tbkBb2hJRieZyr6zc+4X/u+PNqtz3vieh3v41MmaIZ5kuEqODdwowlXpqlaI9kdRNSKq1oSpJ4DMuykRjcAfPYsg2j/Rua1e8bHJdnjdOqukHpm01ApTh6MIEy0aRpbT7hSayn7ULZhC04FCI6KS+/MsNW0yWDJnrImx4n4dzG2nQIDAQABAoIBAFYcG/f3TVzS4PCScJ0Y+xIt8vrXR4sgJqQm7m4enNuLFkFTgwvCGFr1NRqS18tO9+1IB1zaF34rqhukgKTGfpg2KaV690J0H3e4N7u0lYsadcN3edvdgteikz4+U0S+MFMCSdCFysJ/ADx00k/9qQ1T3amnk8dOPd/wll00iYzzl5sUUS2SCLKEWwIWzD+QTmInhOaRYrZBp1o3ZYl7zqQISiC/ALHXN30xSytsu1bAPzl0zm3lWPci2rTIO06G8KqXnzBTRwnqMg3wne7uE18zpW2PB1YY7NhXo4/Qpc36JT01xNomWl4KrsCynFdDuXW+NjYx2aCt/ecDcQ92P8ECgYEA5qSHY8VL0ShZz2LV0lHORr6AbUUaeWfWOMGqcBlSxWbY9Cz8eqq42EVVO923tcfgid+moCkAlVpnklLGTia/4oq2aMKhPNkIy48k+DvF5n6+Qc3KxKNak1i6EPUACbpNlMAPqlzwIkyOJlvNljeKuDnGwRdlIhLSCpJsvqXtHMkCgYEA3aJSewZQKO0tCohvUyXZt1ARCPWSS2HX2fO1pjt6t9nd30IFhenlDnVNvPJ9J+epSsjycDGhY4Ph72gZnHtUdbCqPQXsvm03N3YlVLxugv+ulobZNXIvrqL5RILwJcqIuvTvb0LpxCrW3sVhIKDnSui478dJgFszXfEqjwciOTUCgYBSS3Jjjwo2Xwdsj/9rQ/UuGNsKIhYIK5ysM630wZRfCwtuVH7h5fh+cn6vFbVvDxp3F24Ex5yFrfQ2qYJIcFsXIhdj2c5eP7J7r1+pkupSC/cm0579+qte19HMKx8QWYFFCiKMzwx/sIEn/qmkZ7z/bt4UF5zmQ4DedNCIVfv8wQKBgQCZhVrExPa5orBUX3VdjxZkGB30qsYPWVL/MgVdhE3ZFBzQDRduTznO7gpPG1j+T0XA+7iTZo14NV5HS0CSWAuFCj6hznzS2ywoSrpJG80hCKKSug2zK8lDmrg3cMuQId7cQdWoqBztOQ9leCtzQoF0LggMb2rGcmX+aJMmR7a5EQKBgCHZXbJwa3IcaEn1lzJdzce2SVcQHDR7pdho5sIYXJ3ARROtL98yP2IPvU3rxt0FAibHbhlZASBW32K2J2mZjgTYI5g5W1/+JJm8xBhceAQDZa4IZ01lWzm5cJ3Tx8n8H2JNBlRTC8/PAe2NU6Zn4/fLGVvgODSYp+p+tGPSDZVZ';
        $aop->alipayrsaPublicKey = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA07fJ/t1T1HGLWJBvId2q3cXIwvFuYQKqrsoI+mlrWTtWCiRewLDA9BiSU5qu0OXRlIwNqH6pdCegu/01w07gi6IPGUqpuQs7TCtuyDgWeU2M0Qe9dl/qW82vxMZ1VDtvzbsfsoS5Bi/He4m/fdgHQtzFJci6aZlLxlKJCxzRLqKUJTuE7lbTWW+rmrgNzmAkpKl4lONPiDaJWWAGjuGSTjj16pN3QFzwqUojv1rxD+wNCVcHxN1vaJEyo+NM8bzi6XZ/0oUr5VhvchwDW0exqOAb1045h985Dq7gRHGz7j9Z/iL3mU7fc1zE8G1QH5RFcuLK6W5qRcWnVP+ICphhuwIDAQAB';
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset='utf-8';
        $aop->format='json';
        $request = new AlipayTradeAppPayRequest();
        $content = array(
            'body' => $payment['R_Name'],
            'subject' => $payment['R_Name'],
            'out_trade_no' => $payment['M_OrderNO'],
            'timeout_express' => '120m',
            'total_amount' => $payment['M_Amount'],
            'product_code' => 'QUICK_MSECURITY_PAY',
        );
        $bizcontent = json_encode($content);
        $request->setNotifyUrl($this->asyncCallbackUrl);
        $request->setBizContent($bizcontent);
        $result = $aop->sdkExecute($request);
        $return = array();
        parse_str($result,$return);
        $return['builddata'] = $result;
        return $return;
    }

    /**
     * 除去数组中的空值和签名参数
     * @param $para 签名参数组
     * return 去掉空值与签名参数后的新签名参数组
     */
    private function filterParam($para) {
        $filter_param = array();
        foreach ($para as $key => $val) {
            if ($key == "sign") {
                continue;
            } else {
                $filter_param[$key] = $para[$key];
            }
        }
        return $filter_param;
    }

    /**
     * 对数组排序
     * @param $para 排序前的数组
     * return 排序后的数组
     */
    private function argSort($para) {
        ksort($para);
        reset($para);
        return $para;
    }

    /**
     * 生成签名结果
     * @param $sort_para 要签名的数组
     * @param $key 支付宝交易安全校验码
     * @param $sign_type 签名类型 默认值：MD5
     * return 签名结果字符串
     */
    private function buildSign($sort_para, $key, $sign_type = "RSA") {
        //把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
        $prestr = $this->createLinkstring($sort_para);
        //把拼接后的字符串再与安全校验码直接连接起来
        //$prestr = $prestr . $key;
        //把最终的字符串签名，获得签名结果
        $mysgin = $this->rsaSign($prestr);
        return $mysgin;
    }

    /**
     * 把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
     * @param $para 需要拼接的数组
     * return 拼接完成以后的字符串
     */
    private function createLinkstring($para) {
        $arg = "";
        foreach ($para as $key => $val) {
            $arg.=$key . "=" . $val . "&";
        }

        //去掉最后一个&字符
        $arg = trim($arg, '&');

        //如果存在转义字符，那么去掉转义
        if (get_magic_quotes_gpc()) {
            $arg = stripslashes($arg);
        }

        return $arg;
    }

    function rsaVerify($prestr, $sign) {
        $sign = base64_decode($sign);
        $public_key = file_get_contents(__DIR__ . '/alipay/key/alipay_public_key.pem');
        $pkeyid = openssl_get_publickey($public_key);
        if ($pkeyid) {
            $verify = openssl_verify($prestr, $sign, $pkeyid);
            openssl_free_key($pkeyid);
        }
        if ($verify == 1) {
            return true;
        } else {
            return false;
        }
    }

    function rsaSign($prestr) {
        $private_key = file_get_contents(__DIR__ . '/alipay/key/app_private_key.pem');
        $pkeyid = openssl_get_privatekey($private_key);
        openssl_sign($prestr, $sign, $pkeyid);
        openssl_free_key($pkeyid);
        $sign = base64_encode($sign);
        return $sign;
    }

}
