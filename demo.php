<?php

/**
 * 说明：
 * 以下代码只是为了方便商户测试而提供的样例代码，商户可以根据自己网站的需要，按照技术文档编写,并非一定要使用该代码。
 * 该代码仅供学习和研究银盛支付接口使用，只是提供一个参考。
 */
class demo
{
    /**
     * 构造函数
     *
     * @access  public
     * @param
     * @return void
     */
    function __construct()
    {
        $this->demo();
        date_default_timezone_set('PRC');
        define('BASE_PATH', str_replace('\\', '/', realpath(dirname(__FILE__) . '/')) . "/");
    }

    /**
     * 实例化固定参数值
     * 验证公钥 商户号 商户名 订单地址 订单查询地址 代付地址 代付查询地址 代收地址 代收查询地址 证书密码 异步地址 同步地址
     */
    function demo()
    {

        $this->param = array();
        $this->param['businessgatecerpath'] = 'http://' . $_SERVER['HTTP_HOST'] . "/pay/certs/businessgate.cer";
        $this->param['seller_id'] = 'shanghu_test';
        $this->param['seller_name'] = '银盛支付商户测试公司';
        $this->param['order_url'] = 'https://mertest.ysepay.com/openapi_gateway/gateway.do';
        $this->param['order_query_url'] = 'https://mertest.ysepay.com/openapi_gateway/gateway.do';
        $this->param['df_url'] = 'https://mertest.ysepay.com/openapidsf_gateway/gateway.do';
        $this->param['df_query_url'] = 'https://mertest.ysepay.com/openapidsf_gateway/gateway.do';
        $this->param['ds_url'] = 'https://mertest.ysepay.com/openapidsf_gateway/gateway.do';
        $this->param['ds_query_url'] = 'https://mertest.ysepay.com/openapidsf_gateway/gateway.do';


    }

    /**
     * PC收银台接口 测试环境仅需使用pc收银台->网银支付,作为商户测试环境校验.
     */
    function get_code($order)
    {

        $myParams = array();
        $myParams['business_code'] = '01000010';
        $myParams['charset'] = 'utf-8';
        $myParams['method'] = 'ysepay.online.directpay.createbyuser';
        $myParams['notify_url'] = $this->param['notify_url'];
        $myParams['out_trade_no'] = $order;
        $myParams['partner_id'] = $this->param['seller_id'];
        $myParams['return_url'] = $this->param['return_url'];
        $myParams['seller_id'] = $this->param['seller_id'];
        $myParams['seller_name'] = $this->param['seller_name'];
        $myParams['sign_type'] = 'RSA';
        $myParams['subject'] = '支付测试';
        $myParams['timeout_express'] = '1d';
        $myParams['timestamp'] = date('Y-m-d H:i:s', time());
        $myParams['total_amount'] = '30';
        $myParams['version'] = '3.0';
        /* wap快捷直连需添加以下参数 */
//        $myParams['bank_account_no']           = '9558800200135073266';
//        $myParams['fast_pay_name']           = '银盛支付';
//        $myParams['pay_mode']           = 'fastpay';
//        $myParams['fast_pay_id_no']           = '530523198803220894';
        /* 信用卡必填*/
//        $myParams['bank_account_no']           = '6282886282888888';
//        $myParams['fast_pay_validity']           = '1001';
//        $myParams['fast_pay_cvv2']           = '123';
        /* 收银台快捷会自动根据卡bin判断，以下的值无需传入*/
//        $myParams['bank_type']           = '1041000';
//        $myParams['support_card_type']           = 'debit';
//        $myParams['bank_account_type']           = 'personal';

//        网银直连需添加以下参数
//        $myParams['pay_mode']           = 'internetbank';
//        $myParams['bank_type']           = '1021000';
//        $myParams['bank_account_type']           = 'personal';
//        $myParams['support_card_type']           = 'debit';

        ksort($myParams);
        $data = $myParams;
        $signStr = "";
        foreach ($myParams as $key => $val) {
            $signStr .= $key . '=' . $val . '&';
        }
        $signStr = rtrim($signStr, '&');
        $sign = $this->sign_encrypt(array('data' => $signStr));
        $myParams['sign'] = trim($sign['check']);
        var_dump($myParams);
        $action = $this->param['order_url'];
        $def_url = "<br /><form style='text-align:center;' id='Pay' method=post action='" . $action . "' target='_blank'>";
        while ($param = each($myParams)) {
            $def_url .= "<input type = 'hidden' id='" . $param['key'] . "' name='" . $param['key'] . "' value='" . $param['value'] . "' />";
        }
        $def_url .= "<input type=submit id=Pay value='点击提交' " . @$GLOBALS['_LANG']['pay_button'] . "'>";
//        $def_url .= '<script>window.onload= function(){document.getElementById("Pay").submit();}</script>';
        $def_url .= "</form>";

        return $def_url;
    }
    /**
     * 说明 余额查询接口
     */
    function get_money()
    {
        $myParams = array();
        $myParams['charset'] = 'utf-8';
        $myParams['method'] = 'ysepay.online.user.account.get';
        $myParams['partner_id'] = $this->param['seller_id'];
        $myParams['sign_type'] = 'RSA';
        $myParams['timestamp'] = date('Y-m-d H:i:s', time());
//        $myParams['notify_url'] =$this->param['notify_url'];
        $myParams['version'] = '3.0';

        $biz_content_arr = array(
            "user_code" =>  $this->param['seller_id'],
            "user_name" =>  $this->param['seller_name']
//              "merchant_usercode"=>"YS_test",
        );
        $myParams['biz_content'] = json_encode($biz_content_arr, JSON_UNESCAPED_UNICODE);//构造字符串
        ksort($myParams);
        $signStr = "";
        foreach ($myParams as $key => $val) {
            $signStr .= $key . '=' . $val . '&';
        }
        $signStr = rtrim($signStr, '&');
        $sign = $this->sign_encrypt(array('data' => $signStr));
        $myParams['sign'] = trim($sign['check']);
        var_dump($myParams);
        $ch = curl_init($this->param['order_url']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($myParams));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            var_dump($ch);
        } else {
            $response = json_decode($response, true);
            var_dump($response);
            $sign = $response['sign'];
            echo $sign;
            $data = json_encode($response['ysepay_online_user_account_get_response'], JSON_UNESCAPED_UNICODE);
            var_dump($data);
            /* 验证签名 仅作基础验证*/
            if ($this->sign_check($sign, $data) == true) {
                echo "验证签名成功!";
            } else {
                echo '验证签名失败!';
            }
        }

    }
    /**
     * 说明 订单查询接口
     */
    function get_order($order,$order_no)
    {
        $myParams = array();
        $myParams['charset'] = 'utf-8';
        $myParams['method'] = 'ysepay.online.trade.query';
        $myParams['partner_id'] = $this->param['seller_id'];
        $myParams['sign_type'] = 'RSA';
        $myParams['timestamp'] = date('Y-m-d H:i:s', time());
        $myParams['version'] = '3.0';

        $biz_content_arr = array(
            "out_trade_no" => $order,
            "trade_no"=>$order_no
        );
        $myParams['biz_content'] = json_encode($biz_content_arr, JSON_UNESCAPED_UNICODE);//构造字符串
        ksort($myParams);
        $signStr = "";
        foreach ($myParams as $key => $val) {
            $signStr .= $key . '=' . $val . '&';
        }
        $signStr = rtrim($signStr, '&');
        $sign = $this->sign_encrypt(array('data' => $signStr));
        $myParams['sign'] = trim($sign['check']);
        var_dump($myParams);
        $ch = curl_init($this->param['order_url']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($myParams));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            var_dump($ch);
        } else {
            $response = json_decode($response, true);
            var_dump($response);
            $sign = $response['sign'];
            echo $sign;
            $data = json_encode($response['ysepay_online_trade_query_response'], JSON_UNESCAPED_UNICODE);
            var_dump($data);
            /* 验证签名 仅作基础验证*/
            if ($this->sign_check($sign, $data) == true) {
                echo "验证签名成功!";
            } else {
                echo '验证签名失败!';
            }
        }

    }
    /**
     * 说明:单笔代付加急接口
     */
    function get_dfjj($order)
    {
        $myParams = array();
        $myParams['charset'] = 'utf-8';
        $myParams['method'] = 'ysepay.df.single.quick.accept';
        $myParams['notify_url'] = $this->param['notify_url'];
        $myParams['partner_id'] = $this->param['seller_id'];
        $myParams['sign_type'] = 'RSA';
        $myParams['timestamp'] = date('Y-m-d H:i:s', time());
        $myParams['version'] = '3.0';
        $myParams['extra_common_param'] = '同步参数测试';
        $biz_content_arr = array(
            "out_trade_no" => "$order",
//            "business_code" => "21000009",
            "business_code" => "01000009",
            "currency" => "CNY",
            "total_amount" => "10",
            "subject" => "测试",
            "bank_name" => "工商银行深圳支行",
            "bank_city" => "深圳市",
            "bank_account_no" => "9000101782233747700",
            "bank_account_name" => "工行",
            "bank_account_type" => "personal",
            "bank_card_type" => "debit"
        );
        $myParams['biz_content'] = json_encode($biz_content_arr, JSON_UNESCAPED_UNICODE);//构造字符串
        var_dump($myParams);
        ksort($myParams);
        $signStr = "";
        foreach ($myParams as $key => $val) {
            $signStr .= $key . '=' . $val . '&';
        }
        $signStr = rtrim($signStr, '&');
        var_dump($signStr);
        $sign = $this->sign_encrypt(array('data' => $signStr));
        $myParams['sign'] = trim($sign['check']);
        var_dump($myParams);
        return $myParams;
    }
    /**
     *  支付宝二维码接口 测试环境无法模拟真实场景 仅作同步验签 商户自行修改商户号 商户名等参数
     */
    function get_alipay($order)
    {
        $myParams = array();
        $myParams['charset'] = 'utf-8';
        $myParams['method'] = 'ysepay.online.qrcodepay';
        $myParams['partner_id'] = $this->param['seller_id'];
        $myParams['sign_type'] = 'RSA';
        $myParams['timestamp'] = date('Y-m-d H:i:s', time());
        $myParams['version'] = '3.0';
        $myParams['return_url'] = $this->param['return_url'];
        $myParams['notify_url'] = $this->param['notify_url'];
        $biz_content_arr = array(
            "out_trade_no" => "$order",
            "subject" => "测试扫码",
            "total_amount" => "1.0",
            "seller_id" => "js_test",
            "seller_name" => $this->param['seller_name'],
            "timeout_express" => "24h",
            "business_code" => "01000010",
            "bank_type" => "1902000",
        );
        $myParams['biz_content'] = json_encode($biz_content_arr, JSON_UNESCAPED_UNICODE);//构造字符串
        ksort($myParams);
        var_dump($myParams);
        $signStr = "";
        foreach ($myParams as $key => $val) {
            $signStr .= $key . '=' . $val . '&';
        }
        $signStr = rtrim($signStr, '&');
        var_dump($signStr);
        $sign = $this->sign_encrypt(array('data' => $signStr));
        $myParams['sign'] = trim($sign['check']);
        var_dump($myParams['sign']);
        var_dump($myParams);
        return $myParams;
    }
    /**
     * 银贷通绑卡接口
     */
    function get_ydt()
    {
        $myParams = array();
        $myParams['charset'] = 'utf-8';
        $myParams['interface_name'] = 'pay.binding.single.acept';
        $myParams['merchant_code'] = $this->param['merchant_code'];
        $myParams['sign_type'] = 'RSA';
        $myParams['quest_time'] = date('Y-m-d H:i:s', time());
        $myParams['version'] = '3.0';
        $myParams['notify_url'] = $this->param['notify_url'];
        $myParams["quest_no"] = $this->datetime2string(date('Y-m-d H:i:s:ss:s'));
        $myParams["userid"] = mt_rand();
        $myParams["user_name"] = "取个名字好难";
        $myParams["idcard_no"] = "370705197804099954";
        $myParams["bank_name"] ="工商银行深圳支行";
        $myParams["card_type"] = "debit";
        $myParams["card_no"] = "900000782233747701";
        $myParams["mobile"] = "15173143940";
        $myParams["subject"] = "personal";
        $myParams["bank_province"] = "广东省";
        $myParams["bank_city"] = "深圳市";
        $myParams["bank_type"] = "1021000";

        ksort($myParams);
        var_dump($myParams);
        $signStr = "";
        foreach ($myParams as $key => $val) {
            $signStr .= $key . '=' . $val . '&';
        }
        $signStr = rtrim($signStr, '&');
        var_dump($signStr);
        $sign = $this->sign_encrypt(array('data' => $signStr));
        $myParams['sign'] = trim($sign['check']);
        var_dump($myParams['sign']);
        var_dump($myParams);
        $ch = curl_init($this->param['ydt_url']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($myParams));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $response = curl_exec($ch);
        var_dump($response);
        if (curl_errno($ch)) {
            var_dump($ch);
        } else {
            $response = json_decode($response, true);
            var_dump($response);
            $sign = $response['sign'];
            unset($response['sign']);
            $data = json_encode($response, 320);
            var_dump('data:'.$data);
            var_dump('sign:'.$sign);
            /* 验证签名 仅作基础验证*/
            if ($this->sign_check($sign, $data) == true) {
                echo "验证签名成功!";
            } else {
                echo '验证签名失败!';
            }
        }
    }
    /**
     * 银贷通付款接口
     */
    function get_ydt_df()
    {
        $myParams = array();
        $myParams['interface_name'] = 'pay.remittransfer.single.accept';
        $myParams['merchant_code'] = $this->param['merchant_code'];
        $myParams['quest_time'] = date('Y-m-d H:i:s', time());
        $myParams['sign_type'] = 'RSA';
        $myParams['notify_url'] = $this->param['notify_url'];
        $myParams["quest_no"] = $this->datetime2string(date('Y-m-d H:i:s:ss:s'));
        $myParams["bind_card_id"] = "20180104094521130104193050771541";
        $myParams["userid"] = 978836843;
        $myParams["order_amount"] = "1.00";
        $myParams['subject'] = 'utf-8';
        $myParams['principal_interest'] = '10.00';
        $myParams['principal'] = 100.00;
        $myParams['Periods'] = 1;
        $myParams['agreement_no'] = $this->datetime2string(date('Y-m-d H:i:s:ss:s'));;
        ksort($myParams);
        var_dump($myParams);
        $signStr = "";
        foreach ($myParams as $key => $val) {
            $signStr .= $key . '=' . $val . '&';
        }
        $signStr = rtrim($signStr, '&');
        var_dump($signStr);
        $sign = $this->sign_encrypt(array('data' => $signStr));
        $myParams['sign'] = trim($sign['check']);
        var_dump($myParams['sign']);
        var_dump($myParams);
        $ch = curl_init($this->param['ydt_url_df']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($myParams));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $response = curl_exec($ch);
        var_dump($response);
        $file = BASE_PATH . "log/ok.txt";
        file_put_contents($file, "\r\n", FILE_APPEND);
        file_put_contents($file, "|notify|:" . $response , FILE_APPEND);
        if (curl_errno($ch)) {
            var_dump($ch);
        } else {
            $response = json_decode($response, true);
            var_dump($response);
            $sign = $response['sign'];
            unset($response['sign']);
            $data = json_encode($response, 320);
            var_dump('data:'.$data);
            var_dump('sign:'.$sign);
            /* 验证签名 仅作基础验证*/
            if ($this->sign_check($sign, $data) == true) {
                echo "验证签名成功!";
            } else {
                echo '验证签名失败!';
            }
        }
    }
    /**
     * wap端h5唤起支付宝支付
     */
    function get_h5($order)
    {
        $myParams = array();
        $myParams['business_code'] = '01000010';
        $myParams['charset'] = 'utf-8';
        $myParams['method'] = 'ysepay.online.wap.directpay.createbyuser';
        $myParams['notify_url'] = $this->param['notify_url'];
        $myParams['out_trade_no'] = $order;
        $myParams['partner_id'] =$this->param['seller_id'];
        $myParams['return_url'] = $this->param['return_url'];
        $myParams['seller_id'] = $this->param['seller_id'];
        $myParams['seller_name'] = $this->param['seller_name'];
        $myParams['sign_type'] = 'RSA';
        $myParams['subject'] = '支付测试';
        $myParams['timeout_express'] = '1d';
        $myParams['timestamp'] = date('Y-m-d H:i:s', time());
        $myParams['total_amount'] = '0.20';
        $myParams['version'] = '3.0';
        $myParams['pay_mode'] = 'native';
        $myParams['bank_type'] = '1902000';
        ksort($myParams);
        $data = $myParams;
        $signStr = "";
        foreach ($myParams as $key => $val) {
            $signStr .= $key . '=' . $val . '&';
        }
        $signStr = rtrim($signStr, '&');
        $sign = $this->sign_encrypt(array('data' => $signStr));
        $myParams['sign'] = trim($sign['check']);
        var_dump($myParams);
        $action = $this->param['order_url'];
        var_dump('提交地址：' . $action);
        $def_url = "<br /><form style='text-align:center;' method=post action='" . $action . "' target='_blank'>";
        while ($param = each($myParams)) {
            $def_url .= "<input type = 'hidden' id='Pay" . $param['key'] . "' name='" . $param['key'] . "' value='" . $param['value'] . "' />";
        }
        $def_url .= "<input type=submit value='点击提交' " . @$GLOBALS['_LANG']['pay_button'] . "'>";
        $def_url .= "</form>";
        return $def_url;
    }
    /**
     * 批量代付接口（银行卡）
     */
    function get_batch($order)
    {
        $myParams = array();
        $myParams['charset'] = 'utf-8';
        $myParams['method'] = 'ysepay.df.batch.normal.accept';
        $myParams['partner_id'] = $this->param['seller_id'];
        $myParams['sign_type'] = 'RSA';
        $myParams['timestamp'] = date('Y-m-d H:i:s', time());
        $myParams['version'] = '3.0';
        $myParams['notify_url'] = $this->param['notify_url'];
        $biz_content = array(
            "out_batch_no" => "F" . $this->datetime2string(date('Y-m-d H:i:s')) . "Y",
            "shopdate" => $this->datetime2string(date('Ymd')),
            "total_num" => "1",
            "total_amount" => "1.5",
            "business_code" => "01000009",
            "currency" => "CNY",
            "detail_data" => array([
                "out_trade_no" => "$order",
                "amount" => "1.5",
                "subject" => "订单说明",
                "bank_name" => "中国银行深圳民治支行",
                "bank_province" => "广东省",
                "bank_city" => "深圳市",
                "bank_account_no" => "1111111111111111",
                "bank_account_name" => "李四",
                "bank_account_type" => "personal",
                "bank_card_type" => "credit",
            ])
        );
        $myParams['biz_content'] = json_encode($biz_content, 320);//构造字符串
        ksort($myParams);
        var_dump($myParams);
        $signStr = "";
        foreach ($myParams as $key => $val) {
            $signStr .= $key . '=' . $val . '&';
        }
        $signStr = rtrim($signStr, '&');
        var_dump($signStr);
        $sign = $this->sign_encrypt(array('data' => $signStr));
        $myParams['sign'] = trim($sign['check']);
        var_dump($myParams['sign']);
        var_dump($myParams);
        $ch = curl_init("https://batchdf.ysepay.com/gateway.do");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($myParams));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            var_dump($ch);
        } else {
            $response = json_decode($response, true);
            var_dump($response);
            $sign = $response['sign'];
            echo $sign;
            $data = json_encode($response['ysepay_df_batch_normal_accept_response'], JSON_UNESCAPED_UNICODE);
            $data = $this->arrayToString($data);
            var_dump($data);
            /* 验证签名 仅作基础验证*/
            if ($this->sign_check($sign, $data) == true) {
                echo "验证签名成功!";
            } else {
                echo '验证签名失败!';
            }
        }
    }
    /**
     * 批量代付明细接口（银行卡）
     */
    function get_batch_query()
    {
        $myParams = array();
        $myParams['charset'] = 'utf-8';
        $myParams['method'] = 'ysepay.df.batch.detail.query';
        $myParams['partner_id'] = $this->param['seller_id'];
        $myParams['sign_type'] = 'RSA';
        $myParams['timestamp'] = date('Y-m-d H:i:s', time());
        $myParams['version'] = '3.0';
        $biz_content = array(
            "out_batch_no" => "F20170920204652Y",
            "shopdate" => "20170920",
            "out_trade_no" => "20170920204652"
        );
        $myParams['biz_content'] = json_encode($biz_content, 320);//构造字符串
        ksort($myParams);
        var_dump($myParams);
        $signStr = "";
        foreach ($myParams as $key => $val) {
            $signStr .= $key . '=' . $val . '&';
        }
        $signStr = rtrim($signStr, '&');
        var_dump($signStr);
        $sign = $this->sign_encrypt(array('data' => $signStr));
        $myParams['sign'] = trim($sign['check']);
        var_dump($myParams['sign']);
        var_dump($myParams);
        return $myParams;
    }
    /**
     * 批量代收接口（银行卡）
     */
    function get_batchds($order,$no)
    {
        $myParams = array();
        $myParams['charset'] = 'utf-8';
        $myParams['method'] = 'ysepay.ds.batch.normal.accept';
        $myParams['partner_id'] = $this->param['seller_id'];
        $myParams['sign_type'] = 'RSA';
        $myParams['timestamp'] = date('Y-m-d H:i:s', time());
        $myParams['version'] = '3.0';
        $myParams['notify_url'] = $this->param['notify_url'];
        $biz_content = array(
            "out_batch_no" => "S" . $this->datetime2string(date('Y-m-d H:i:s')) . "Y",
            "shopdate" => $this->datetime2string(date('Ymd')),
            "total_num" => "1",
            "total_amount" => "1.5",
            "business_code" => "1010015",
            "currency" => "CNY",
            "detail_data" => array([
                "out_trade_no" => "$order",
                "amount" => "1.5",
                "subject" => "订单说明",
                "bank_name" => "中国银行深圳民治支行",
                "bank_province" => "广东省",
                "bank_city" => "深圳市",
                "bank_account_no" => "1111111111111111",
                "bank_account_name" => "李四",
                "bank_account_type" => "personal",
                "bank_card_type" => "credit",
                "bank_telephone_no"=>"18620222011",
                "cert_type"=> '00',
                "cert_no" => $no
            ])
        );
        $myParams['biz_content'] = json_encode($biz_content, 320);//构造字符串
        ksort($myParams);
        var_dump($myParams);
        $signStr = "";
        foreach ($myParams as $key => $val) {
            $signStr .= $key . '=' . $val . '&';
        }
        $signStr = rtrim($signStr, '&');
        var_dump($signStr);
        $sign = $this->sign_encrypt(array('data' => $signStr));
        $myParams['sign'] = trim($sign['check']);
        var_dump($myParams['sign']);
        var_dump($myParams);
        return $myParams;
    }
    /**
     * 银行卡 三 四要素实名认证
     */
    function get_authen($no)
    {
        $myParams = array();
        $myParams['charset'] = 'utf-8';
        $myParams['method'] = 'ysepay.authenticate.four.key.element';
        $myParams['partner_id'] = $this->param['seller_id'];
        $myParams['sign_type'] = 'RSA';
        $myParams['timestamp'] = date('Y-m-d H:i:s', time());
        $myParams['version'] = '3.0';
        $biz_content = array(
            "out_trade_no" => $no,
            "bank_account_name" =>"姓名",
            "bank_account_no" =>"卡号",
            "id_card" =>"身份证"
        );
        $myParams['biz_content'] = json_encode($biz_content, 320);//构造字符串
        ksort($myParams);
        var_dump($myParams);
        $signStr = "";
        foreach ($myParams as $key => $val) {
            $signStr .= $key . '=' . $val . '&';
        }
        $signStr = rtrim($signStr, '&');
        var_dump($signStr);
        $sign = $this->sign_encrypt(array('data' => $signStr));
        $myParams['sign'] = trim($sign['check']);
        var_dump($myParams['sign']);
        var_dump($myParams);
        return $myParams;
    }
    /**
     * 运营商 三要素实名认证
     */
    function get_authen_mobile($no)
    {
        $myParams = array();
        $myParams['charset'] = 'utf-8';
        $myParams['method'] = 'ysepay.authenticate.mobile.operators.three.key.element';
        $myParams['partner_id'] = $this->param['seller_id'];
        $myParams['sign_type'] = 'RSA';
        $myParams['timestamp'] = date('Y-m-d H:i:s', time());
        $myParams['version'] = '3.0';
        $biz_content = array(
            "out_trade_no" => $no,
            "name" =>'姓名',
            "phone" =>"手机号",
            "id_card" => $this->ECBEncrypt("身份证号", ' js_test')
        );
        $myParams['biz_content'] = json_encode($biz_content, 320);//构造字符串
        ksort($myParams);
        var_dump($myParams);
        $signStr = "";
        foreach ($myParams as $key => $val) {
            $signStr .= $key . '=' . $val . '&';
        }
        $signStr = rtrim($signStr, '&');
        var_dump($signStr);
        $sign = $this->sign_encrypt(array('data' => $signStr));
        $myParams['sign'] = trim($sign['check']);
        var_dump($myParams['sign']);
        var_dump($myParams);
        $ch = curl_init($this->param['order_url']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($myParams));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            var_dump($ch);
        } else {
            $response = json_decode($response, true);
            var_dump($response);
            $sign = $response['sign'];
            echo $sign;
            $data = json_encode($response['ysepay_authenticate_mobile_operators_three_key_element_response'], 320);
            $data = $this->arrayToString($data);
            var_dump($data);
            $file = BASE_PATH . "log/ok.txt";
            if ($this->sign_check($sign, $data)==true) {
                file_put_contents($file, "\r\n", FILE_APPEND);
                file_put_contents($file, "Verify success!|notify|:" . $data . "|sign:" . $sign, FILE_APPEND);
                echo '验证签名成功';
            } else {
                file_put_contents($file, "\r\n", FILE_APPEND);
                file_put_contents($file, "Validation failure!|notify|:" . $data . "|sign:" . $sign, FILE_APPEND);
            }
        }
    }
    /**
     * 二要素(返照)实名认证
     */
    function get_authen_two($no,$id)
    {
        $myParams = array();
        $myParams['method'] = 'ysepay.authenticate.id.card.img';
        $myParams['partner_id'] = $this->param['seller_id'];
        $myParams['timestamp'] = date('Y-m-d H:i:s', time());
        $myParams['charset'] = 'utf-8';
        $myParams['sign_type'] = 'RSA';
        $myParams['version'] = '3.0';
        $biz_content = array(
            "out_trade_no" => $no,
            "name" =>"姓名",
            "id_card" => $id
        );
        $myParams['biz_content'] = json_encode($biz_content, 320);//构造字符串
        ksort($myParams);
        var_dump($myParams);
        $signStr = "";
        foreach ($myParams as $key => $val) {
            $signStr .= $key . '=' . $val . '&';
        }
        $signStr = rtrim($signStr, '&');
        var_dump($signStr);
        $sign = $this->sign_encrypt(array('data' => $signStr));
        $myParams['sign'] = trim($sign['check']);
        var_dump($myParams['sign']);
        var_dump($myParams);
        $ch = curl_init($this->param['order_url']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($myParams));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            var_dump($ch);
        } else {
            $response = json_decode($response, true);
            var_dump($response);
            $sign = $response['sign'];
            echo $sign;
            $data = json_encode($response['ysepay_authenticate_id_card_img_response'], 320);
            $this->base64toimages($data['archive_img']);
            var_dump($data);
            $file = BASE_PATH . "log/ok.txt";
            if ($this->sign_check($sign, $data)==true) {
                file_put_contents($file, "\r\n", FILE_APPEND);
                file_put_contents($file, "Verify success!|notify|:" . $data . "|sign:" . $sign, FILE_APPEND);
                echo '验证签名成功';
            } else {
                file_put_contents($file, "\r\n", FILE_APPEND);
                file_put_contents($file, "Validation failure!|notify|:" . $data . "|sign:" . $sign, FILE_APPEND);
                echo '验证签名失败';
            }
        }

    }
   
    /**
     * 对账单下载
     */
    function get_down()
    {
        $myParams = array();
        $myParams['charset'] = 'utf-8';
        $myParams['method'] = 'ysepay.online.bill.downloadurl.get';
        $myParams['partner_id'] = $this->param['seller_id'];
        $myParams['sign_type'] = 'RSA';
        $myParams['timestamp'] = date('Y-m-d H:i:s', time());
        $myParams['version'] = '3.0';
        $biz_content = array(
            "account_date" => "2017-10-23"
        );
        $myParams['biz_content'] = json_encode($biz_content, 320);//构造字符串
        ksort($myParams);
        var_dump($myParams);
        $signStr = "";
        foreach ($myParams as $key => $val) {
            $signStr .= $key . '=' . $val . '&';
        }
        $signStr = rtrim($signStr, '&');
        var_dump($signStr);
        $sign = $this->sign_encrypt(array('data' => $signStr));
        $myParams['sign'] = trim($sign['check']);
        var_dump($myParams['sign']);
        var_dump($myParams);
        return $myParams;
    }
    /**
     *  微信SDK下单接口,测试环境仅作同步验签即可
     */
    function get_wxapp($order)
    {
        $myParams = array();
        $myParams['charset'] = 'utf-8';
        $myParams['method'] = 'ysepay.online.sdkpay';
        $myParams['partner_id'] =$this->param['seller_id'];
        $myParams['sign_type'] = 'RSA';
        $myParams['timestamp'] = date('Y-m-d H:i:s', time());
        $myParams['version'] = '3.0';
        $myParams['notify_url'] = $this->param['notify_url'];
        $biz_content_arr = array(
            "out_trade_no" => "$order",
            "subject" => "微信APP下单接口",
            "total_amount" => "10",
            "currency"=>"CNY",
            "seller_id" => $this->param['seller_id'],
            "seller_name" => $this->param['seller_name'],
            "timeout_express" => "24h",
            "business_code" => "01000010",
            "bank_type" => "1902000",
            "appid"=>"wx123456789123"
        );
        $myParams['biz_content'] = json_encode($biz_content_arr, JSON_UNESCAPED_UNICODE);//构造字符串
        ksort($myParams);
        var_dump($myParams);
        $signStr = "";
        foreach ($myParams as $key => $val) {
            $signStr .= $key . '=' . $val . '&';
        }
        $signStr = rtrim($signStr, '&');
        var_dump($signStr);
        $sign = $this->sign_encrypt(array('data' => $signStr));
        $myParams['sign'] = trim($sign['check']);
        var_dump($myParams['sign']);
        var_dump($myParams);
        $ch = curl_init("https://mertest.ysepay.com/openapi_gateway/gateway.do");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($myParams));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $response = curl_exec($ch);
        var_dump($response);
        if (curl_errno($ch)) {
            var_dump($ch);
        } else {
            $response = json_decode($response, true);
            var_dump($response);
            $sign = $response['sign'];
            echo $sign;
            $data = json_encode($response['ysepay_online_sdkpay_response'], JSON_UNESCAPED_UNICODE);
            $data = $this->arrayToString($data);
            var_dump($data);
            /* 验证签名 仅作基础验证*/
            if ($this->sign_check($sign, $data) == true) {
                echo "验证签名成功!";
            } else {
                echo '验证签名失败!';
            }
        }
    }
    /**
     *  微信公众号下单接口,测试环境仅作同步验签即可
     */
    function get_wxPublic($order)
    {
        $myParams = array();
        $myParams['charset'] = 'utf-8';
        $myParams['method'] = 'ysepay.online.jsapi.pay';
        $myParams['partner_id'] =$this->param['seller_id'];
        $myParams['sign_type'] = 'RSA';
        $myParams['timestamp'] = date('Y-m-d H:i:s', time());
        $myParams['version'] = '3.0';
        $myParams['notify_url'] = $this->param['notify_url'];
        $biz_content_arr = array(
            "out_trade_no" => "$order",
            "subject" => "微信公众号下单接口",
            "total_amount" => "10",
            "currency"=>"CNY",
            "seller_id" => $this->param['seller_id'],
            "seller_name" =>$this->param['seller_name'],
            "timeout_express" => "24h",
            "business_code" => "01000010",
            "sub_openid"=>"wx123456789123"
        );
        $myParams['biz_content'] = json_encode($biz_content_arr, JSON_UNESCAPED_UNICODE);//构造字符串
        ksort($myParams);
        var_dump($myParams);
        $signStr = "";
        foreach ($myParams as $key => $val) {
            $signStr .= $key . '=' . $val . '&';
        }
        $signStr = rtrim($signStr, '&');
        var_dump($signStr);
        $sign = $this->sign_encrypt(array('data' => $signStr));
        $myParams['sign'] = trim($sign['check']);
        var_dump($myParams['sign']);
        var_dump($myParams);
        $ch = curl_init($this->param['order_url']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($myParams));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $response = curl_exec($ch);
        var_dump($response);
        if (curl_errno($ch)) {
            var_dump($ch);
        } else {
            $response = json_decode($response, true);
            var_dump($response);
            $sign = $response['sign'];
            echo $sign;
            $data = json_encode($response['ysepay_online_jsapi_pay_response'], JSON_UNESCAPED_UNICODE);
            $data = $this->arrayToString($data);
            var_dump($data);
            /* 验证签名 仅作基础验证*/
            if ($this->sign_check($sign, $data) == true) {
                echo "验证签名成功!";
            } else {
                echo '验证签名失败!';
            }
        }
    }
    /**
     * 通用订单查询接口
     */
    function get_order_query()
    {
        $myParams = array();
        $myParams['charset'] = 'utf-8';
        $myParams['method'] = 'ysepay.online.trade.query';
        $myParams['partner_id'] =$this->param['seller_id'];
        $myParams['sign_type'] = 'RSA';
        $myParams['timestamp'] = date('Y-m-d H:i:s', time());
        $myParams['version'] = '3.0';
        $biz_content = array(
            "out_trade_no" => "20170921113125"
        );
        $myParams['biz_content'] = json_encode($biz_content, 320);//构造字符串
        ksort($myParams);
        var_dump($myParams);
        $signStr = "";
        foreach ($myParams as $key => $val) {
            $signStr .= $key . '=' . $val . '&';
        }
        $signStr = rtrim($signStr, '&');
        var_dump($signStr);
        $sign = $this->sign_encrypt(array('data' => $signStr));
        $myParams['sign'] = trim($sign['check']);
        var_dump($myParams['sign']);
        var_dump($myParams);
        $ch = curl_init($this->param['order_query_url']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($myParams));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            var_dump($ch);
        } else {
            $response = json_decode($response, true);
            var_dump($response);
            $sign = $response['sign'];
            echo $sign;
            $data = json_encode($response['ysepay_online_trade_query_response'], JSON_PRESERVE_ZERO_FRACTION);
            $data = $this->arrayToString($data);
            var_dump($data);
            /* 验证签名 仅作基础验证*/
            if ($this->sign_check($sign, $data) == true) {
                echo "验证签名成功!";
            } else {
                echo '验证签名失败!';
            }
        }
    }
    /**
     * 分账接口*/
    function get_division()
    {
        $myParams = array();
        $myParams['charset'] = 'utf-8';
        $myParams['method'] = 'ysepay.single.division.online.accept';
        $myParams['partner_id'] = $this->param['seller_id'];
        $myParams['sign_type'] = 'RSA';
        $myParams['timestamp'] = date('Y-m-d H:i:s', time());
        $myParams['version'] = '3.0';
        $biz_content = array(
            "out_batch_no"=>"F". date('YmdHis', time()),
            "out_trade_no"=>"F". date('YmdHis', time()),
            "org_no"=>"银盛支付测试体验",
            "division_mode"=>"02",
            "total_amount"=>"1.00",
            "is_divistion"=>"01",
            "is_again_division"=>"Y",
            "div_list" => array([
               "division_mer_usercode" =>"YS_test",
                "is_chargeFee"=>"02"
            ])
        );
        $myParams['biz_content'] = json_encode($biz_content, JSON_PRESERVE_ZERO_FRACTION);//构造字符串
        ksort($myParams);
        $data = $myParams;
        $signStr = "";
        foreach ($myParams as $key => $val) {
            $signStr .= $key . '=' . $val . '&';
        }
        $signStr = rtrim($signStr, '&');
        $sign = $this->sign_encrypt(array('data' => $signStr));
        $myParams['sign'] = trim($sign['check']);
        var_dump($myParams);
        $ch = curl_init($this->param['order_common']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($myParams));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            var_dump($ch);
        } else {
            $response = json_decode($response, true);
            var_dump($response);
            $sign = $response['sign'];
            echo $sign;
            $data = json_encode($response['ysepay_single_division_online_accept_response'], JSON_UNESCAPED_UNICODE);
            $data = $this->arrayToString($data);
            var_dump($data);
            /* 验证签名 仅作基础验证*/
            if ($this->sign_check($sign, $data) == true) {
                echo "验证签名成功!";
            } else {
                echo '验证签名失败!';
            }
        }
    }
    /**
     * 同步响应操作
     */
    function respond()
    {

        //返回的数据处理
        @$sign = trim($_GET['sign']);
        $result = $_GET;
        unset($result['sign']);
        ksort($result);
        $url = "";
        foreach ($result as $key => $val) {
            if ($val) $url .= $key . '=' . $val . '&';
        }
        $data = trim($url, '&');
        var_dump($data);
        /*写入日志*/
        $file = BASE_PATH . "log/respond.txt";
        file_put_contents($file, "\r\n", FILE_APPEND);
        file_put_contents($file, "return|data:" . $data . "|sign:" . $sign, FILE_APPEND);
        /* 验证签名 仅作基础验证*/
        var_dump('data:'.$data);
        var_dump('sign:'.$sign);
        if ($this->sign_check($sign, $data) == true) {
            echo "验证签名成功!";
        } else {
            echo '验证签名失败!';
        }


    }

    /**
     * 异步响应操作
     */
    function respond_notify()
    {
        //返回的数据处理
        @$sign = trim($_POST['sign']);
        $result = $_POST;
        unset($result['sign']);
        ksort($result);
        $url = "";
        foreach ($result as $key => $val) {
            if ($val) $url .= $key . '=' . $val . '&';
        }
        $data = trim($url, '&');
        /* 验证签名 仅作基础验证*/
        /*写入日志*/
        $file = BASE_PATH . "log/notify.txt";
        if ($this->sign_check($sign, $data)==true) {
            file_put_contents($file, "\r\n", FILE_APPEND);
            file_put_contents($file, "Verify success!|notify|:" . $data . "|sign:" . $sign, FILE_APPEND);
        } else {
            file_put_contents($file, "\r\n", FILE_APPEND);
            file_put_contents($file, "Validation failure!|notify|:" . $data . "|sign:" . $sign, FILE_APPEND);
        }
        /*
           开发须知:
           收到异步通知后,必须响应success给银盛,用于告诉银盛已成功接收到异步消息,
           多次不返回success的商户银盛将不会往商户异步地址发送异步消息(并拉黑商户异步地址)
         */
        echo 'success';
        exit;


    }

    /**
     * 异步响应操作
     */
    function ydt_respond_notify()
    {
        //返回的数据处理
        $sign = trim($_POST['sign']);
        $result = $_POST;
        unset($result['sign']);
        ksort($result);
        $url = "";
        foreach ($result as $key => $val) {
            if ($val) $url .= $key . '=' . $val . '&';
        }
        $data = trim($url, '&');
        /* 验证签名 仅作基础验证*/
        /*写入日志*/
        $file = BASE_PATH . "log/notify.txt";
        if ($this->sign_check($sign, $data)==true) {
            file_put_contents($file, "\r\n", FILE_APPEND);
            file_put_contents($file, "Verify success!|notify|:" . $data . "|sign:" . $sign, FILE_APPEND);
        } else {
            file_put_contents($file, "\r\n", FILE_APPEND);
            file_put_contents($file, "Validation failure!|notify|:" . $data . "|sign:" . $sign, FILE_APPEND);
        }
        /*
           开发须知:
           收到异步通知后,必须响应success给银盛,用于告诉银盛已成功接收到异步消息,
           多次不返回success的商户银盛将不会往商户异步地址发送异步消息(并拉黑商户异步地址,拉黑多个地址后拉黑商户号 无法解除)
         */
        echo 'success';
        exit;


    }

    /**
     *日期转字符
     *输入参数：yyyy-MM-dd HH:mm:ss
     *输出参数：yyyyMMddHHmmss
     */
    function datetime2string($datetime)
    {

        return preg_replace('/\-*\:*\s*/', '', $datetime);
    }

    /**
     * 验签转明码
     * @param input check
     * @param input msg
     * @return data
     * @return success
     */
    function sign_check($sign, $data)
    {

        $publickeyFile = $this->param['businessgatecerpath']; //公钥
        $certificateCAcerContent = file_get_contents($publickeyFile);
        $certificateCApemContent = '-----BEGIN CERTIFICATE-----' . PHP_EOL . chunk_split(base64_encode($certificateCAcerContent), 64, PHP_EOL) . '-----END CERTIFICATE-----' . PHP_EOL;
        print_r("验签密钥".$certificateCApemContent);
        // 签名验证
        $success = openssl_verify($data, base64_decode($sign), openssl_get_publickey($certificateCApemContent), OPENSSL_ALGO_SHA1);
        var_dump($success);
        return $success;
    }


    /**
     * 签名加密
     * @param input data
     * @return success
     * @return check
     * @return msg
     */
    function sign_encrypt($input)
    {

        $return = array('success' => 0, 'msg' => '', 'check' => '');
        $pkcs12 = file_get_contents("C:/wamp64/www/pay/certs/shanghu_test.pfx"); //私钥
        if (openssl_pkcs12_read($pkcs12, $certs, $this->param['pfxpassword'])) {
            var_dump('证书,密码,正确读取');
            $privateKey = $certs['pkey'];
            $publicKey = $certs['cert'];
            $signedMsg = "";
            print_r("加密密钥".$privateKey);
            if (openssl_sign($input['data'], $signedMsg, $privateKey, OPENSSL_ALGO_SHA1)) {
                var_dump('签名正确生成');
                $return['success'] = 1;
                $return['check'] = base64_encode($signedMsg);
                $return['msg'] = base64_encode($input['data']);
            }
        }

        return $return;
    }


    /**
     * 数组转字符串
     */
    function arrayToString($arr)
    {
        if (is_array($arr)) {
            return implode(',', array_map('arrayToString', $arr));
        }
        return $arr;
    }

    /**
     * DES加密方法
     * @param $data 传入需要加密的证件号码
     * @return string 返回加密后的字符串
     */
    function ECBEncrypt($data, $key)
    {
        $encrypted = openssl_encrypt($data, 'DES-ECB', $key, 1);
        return base64_encode($encrypted);
    }

    /**
     * DES解密方法
     * @param $data 传入需要解密的字符串
     * @return string 返回解密后的证件号码
     */
    function doECBDecrypt($data, $key)
    {
        $encrypted = base64_decode($data);
        $decrypted = openssl_decrypt($encrypted, 'DES-ECB', $key, 1);
        return $decrypted;
    }

    function base64toimages($data){
        $img = base64_decode($data);
        Header( "Content-type: image/jpeg");//直接输出显示jpg格式图片
        echo $img;
    }


    /** curl 获取 https 请求 单笔代付加急
     * @param qa 要发送的数据
     */
    function curl_https_df($qa)
    {
        $ch = curl_init($this->param['df_url']);
        var_dump('加急代付提交地址: ' . $this->param['df_url']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($qa));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $response = curl_exec($ch);
        var_dump($response);
        if (curl_errno($ch)) {
            var_dump($ch);
        } else {
            $response = json_decode($response, true);
            var_dump($response);
            $sign = $response['sign'];
            echo $sign;
            $data = json_encode($response['ysepay_df_single_quick_accept_response'], JSON_UNESCAPED_UNICODE);
            $data = $this->arrayToString($data);
            var_dump($data);
            /* 验证签名 仅作基础验证*/
            if ($this->sign_check($sign, $data) == true) {
                echo "验证签名成功!";
            } else {
                echo '验证签名失败!';
            }
        }
    }

    /** curl 获取 https 请求 支付宝等二维码接口 仅在正式环境下有效.
     * @param qa 要发送的数据
     */
    function curl_https_alipay($qa)
    {
        $ch = curl_init($this->param['order_url']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($qa));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $response = curl_exec($ch);
        var_dump($response);
        if (curl_errno($ch)) {
            var_dump($ch);
        } else {
            $response = json_decode($response, true);
            var_dump($response);
            $sign = $response['sign'];
            echo $sign;
            $data = json_encode($response['ysepay_online_qrcodepay_response'], 320);
            var_dump($data);
            /* 验证签名 仅作基础验证*/
            if ($this->sign_check($sign, $data) == true) {
                echo "验证签名成功!";
            } else {
                echo '验证签名失败!';
            }
        }
    }


    /** curl 获取 https 请求 批量代付
     * @param qa 要发送的数据
     */
    function curl_batch($qa)
    {
        $ch = curl_init("https://batchdf.ysepay.com/gateway.do");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($qa));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            var_dump($ch);
        } else {
            $response = json_decode($response, true);
            var_dump($response);
            $sign = $response['sign'];
            echo $sign;
            $data = json_encode($response['ysepay_df_batch_normal_accept_response'], JSON_UNESCAPED_UNICODE);
            $data = $this->arrayToString($data);
            var_dump($data);
            /* 验证签名 仅作基础验证*/
            if ($this->sign_check($sign, $data) == true) {
                echo "验证签名成功!";
            } else {
                echo '验证签名失败!';
            }
        }
    }

    /** curl 获取 https 请求 批量代付(银行卡)查询
     * @param qa 要发送的数据
     */
    function curl_https_batch_query($qa)
    {
        $ch = curl_init("https://searchdf.ysepay.com/gateway.do");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($qa));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            var_dump($ch);
        } else {
            $response = json_decode($response, true);
            var_dump($response);
            $sign = $response['sign'];
            echo $sign;
            $data = json_encode($response['ysepay_df_batch_detail_query_response'], JSON_UNESCAPED_UNICODE);
            $data = $this->arrayToString($data);
            var_dump($data);
            /* 验证签名 仅作基础验证*/
            if ($this->sign_check($sign, $data) == true) {
                echo "验证签名成功!";
            } else {
                echo '验证签名失败!';
            }
        }
    }

    /** curl 获取 https 请求 批量代收
     * @param qa 要发送的数据
     */
    function curl_batchds($qa)
    {
        $ch = curl_init("https://batchds.ysepay.com/gateway.do");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($qa));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            var_dump($ch);
        } else {
            $response = json_decode($response, true);
            var_dump($response);
            $sign = $response['sign'];
            echo $sign;
            $data = json_encode($response['ysepay_ds_batch_normal_accept_response'], JSON_UNESCAPED_UNICODE);
            $data = $this->arrayToString($data);
            var_dump($data);
            /* 验证签名 仅作基础验证*/
            if ($this->sign_check($sign, $data) == true) {
                echo "验证签名成功!";
            } else {
                echo '验证签名失败!';
            }
        }
    }

    /** curl 获取 https 请求 三要素实名认证
     * @param qa 要发送的数据
     */
    function curl_authen($qa)
    {
        $ch = curl_init($this->param['order_url']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($qa));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            var_dump($ch);
        } else {
            $response = json_decode($response, true);
            var_dump($response);
            $sign = $response['sign'];
            echo $sign;
            $data = json_encode($response['ysepay_authenticate_four_key_element_response'], JSON_UNESCAPED_UNICODE);
            $data = $this->arrayToString($data);
            var_dump($data);
            /* 验证签名 仅作基础验证*/
            if ($this->sign_check($sign, $data) == true) {
                echo "验证签名成功!";
            } else {
                echo '验证签名失败!';
            }
        }
    }

    /** curl 获取 https 请求 平台内单笔代付
     * @param qa 要发送的数据
     */
    function curl_inner_df($qa)
    {
        $ch = curl_init($this->param['df_url']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($qa));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            var_dump($ch);
        } else {
            $response = json_decode($response, true);
            var_dump($response);
            $sign = $response['sign'];
            echo $sign;
            $data = json_encode($response['ysepay_df_single_quick_inner_accept_response'], JSON_UNESCAPED_UNICODE);
            $data = $this->arrayToString($data);
            var_dump($data);
            /* 验证签名 仅作基础验证*/
            if ($this->sign_check($sign, $data) == true) {
                echo "验证签名成功!";
            } else {
                echo '验证签名失败!';
            }
        }
    }

    /** curl 获取 https 请求 平台内单笔代付(银行卡)
     * @param qa 要发送的数据
     */
    function curl_inner_df_car($qa)
    {
        $ch = curl_init($this->param['df_url']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($qa));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            var_dump($ch);
        } else {
            $response = json_decode($response, true);
            var_dump($response);
            $sign = $response['sign'];
            echo $sign;
            $data = json_encode($response['ysepay_df_single_quick_accept_response'], JSON_UNESCAPED_UNICODE);
            $data = $this->arrayToString($data);
            var_dump($data);
            /* 验证签名 仅作基础验证*/
            if ($this->sign_check($sign, $data) == true) {
                echo "验证签名成功!";
            } else {
                echo '验证签名失败!';
            }
        }
    }

    /** curl 获取 https 请求 平台内单笔代付查询
     * @param qa 要发送的数据
     */
    function curl_https_inner_df_query($qa)
    {
        $ch = curl_init($this->param['df_query_url']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($qa));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            var_dump($ch);
        } else {
            $response = json_decode($response, true);
            var_dump($response);
            $sign = $response['sign'];
            echo $sign;
            $data = json_encode($response['ysepay_df_single_query_response'], JSON_UNESCAPED_UNICODE);
            var_dump($data);
            /* 验证签名 仅作基础验证*/
            if ($this->sign_check($sign, $data) == true) {
                echo "验证签名成功!";
            } else {
                echo '验证签名失败!';
            }
        }
    }

    /** curl 获取 https 请求 平台内批量代收
     * @param qa 要发送的数据
     */
    function curl_inner_batchds($qa)
    {
        $ch = curl_init("https://batchds.ysepay.com/gateway.do");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($qa));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            var_dump($ch);
        } else {
            $response = json_decode($response, true);
            var_dump($response);
            $sign = $response['sign'];
            echo $sign;
            $data = json_encode($response['ysepay_ds_batch_normal_inner_accept_response'], JSON_UNESCAPED_UNICODE);
            $data = $this->arrayToString($data);
            var_dump($data);
            /* 验证签名 仅作基础验证*/
            if ($this->sign_check($sign, $data) == true) {
                echo "验证签名成功!";
            } else {
                echo '验证签名失败!';
            }
        }
    }
    /** curl 获取 https 请求 订单对账单下载
     * @param qa 要发送的数据
     */
    function curl_down($qa)
    {
        $ch = curl_init($this->param['order_url']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($qa));
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            var_dump($ch);
        } else {
            $response = json_decode($response, true);
            var_dump($response);
            $sign = $response['sign'];
            echo $sign;
            $data = json_encode($response['ysepay_online_bill_downloadurl_get_response'], JSON_UNESCAPED_SLASHES);
            var_dump($data);
            /* 验证签名 仅作基础验证*/
            if ($this->sign_check($sign, $data) == true) {
                echo "验证签名成功!";
            } else {
                echo '验证签名失败!';
            }
        }
    }
}
/**
 * 测试接口
 */
$s = new demo();