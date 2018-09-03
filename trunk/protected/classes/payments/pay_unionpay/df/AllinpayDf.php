<?php
class AllinpayDf{
    public function __construct() {
        $this->model = new Model();
    }

	public function DFAllinpay($params){
	header('Content-Type: text/html; Charset=UTF-8');
     $tools=new PhpTools();
        $merchantId=AppConfig::MERCHANT_ID;
        $req_sn = $merchantId.$params['withdraw_no'];
        // 源数组
        $data = array(
            'INFO' => array(
                'TRX_CODE' => '100014',
                'VERSION' => '03',
                'DATA_TYPE' => '2',
                'LEVEL' => '6',
                'USER_NAME' => '20058400001550504',
                'USER_PASS' => '111111',
                'REQ_SN' => $req_sn,
            ),
            'TRANS' => array(
                'BUSINESS_CODE' => '09900',
                'MERCHANT_ID' => $merchantId,
                'SUBMIT_TIME' => date('YmdHis'),
                'E_USER_CODE' => '10101328',
                'BANK_CODE' => '',
                'ACCOUNT_TYPE' => '00',
                'ACCOUNT_NO' => $params['cardNo'],
                'ACCOUNT_NAME' => $params['usrName'],
                'ACCOUNT_PROP' => '0',
                'AMOUNT' => $params["transAmt"],
                'CURRENCY' => 'CNY',
                'ID_TYPE' => '0',
                'CUST_USERID' => '2901347',
                'SUMMARY' => '',
                'REMARK' => $params['purpose'],
            ),
        );

        //发起请求
        $result = $tools->send($data,$req_sn);
        return $result;
    }

    public function DFquery($req_sn){
        header('Content-Type: text/html; Charset=UTF-8');
        $tools=new PhpTools();
        $merchantId=AppConfig::MERCHANT_ID; 
        // 源数组
        $params = array(
            'INFO' => array(
                'TRX_CODE' => '200004',
                'VERSION' => '03',
                'DATA_TYPE' => '2',
                'LEVEL' => '6',
                'USER_NAME' => '20058400001550504',
                'USER_PASS' => '111111',
                'REQ_SN' => $req_sn.rand(1000,9999),
            ),
            'QTRANSREQ' => array(
                'QUERY_SN' => $req_sn,
                'MERCHANT_ID' => $merchantId,
                'STATUS' => '2',
                'TYPE' => '1',
                'START_DAY' => '',
                'END_DAY' => ''
            ),
        );
        //发起请求
        $result = $tools->sends( $params);
        return $result;
    }
    
    //银盛代付通道
    public function DfYinsheng($params){
        $myParams = array();
        $myParams['charset'] = 'utf-8';
        $myParams['method'] = 'ysepay.df.single.quick.accept';
        $myParams['notify_url'] = 'http://yspay.ngrok.cc/pay/respond_notify.php';
        $myParams['partner_id'] = 'yuanmeng';
        $myParams['sign_type'] = 'RSA';
        $myParams['timestamp'] = date('Y-m-d H:i:s', time());
        $myParams['version'] = '3.0';
        $biz_content_arr = array(
            "out_trade_no" => substr($params['withdraw_no'],2),
            "business_code" => "2010002",
            "currency" => "CNY",
            "total_amount" => $params["transAmt"]/100,
            "subject" => "余额提现",
            "bank_name" => $params['openBank'],
            "bank_city" => $params['city'],
            "bank_account_no" => $params['cardNo'],
            "bank_account_name" => $params['usrName'],
            "bank_account_type" => "personal",
            "bank_card_type" => "debit",
            'shopdate'=>date('Ymd', time())
        );
        $myParams['biz_content'] = json_encode($biz_content_arr, JSON_UNESCAPED_UNICODE);//构造字符串
        ksort($myParams);
        $signStr = "";
        foreach ($myParams as $key => $val) {
            $signStr .= $key . '=' . $val . '&';
        }
        $signStr = rtrim($signStr, '&');
        // var_dump($signStr);
        $sign = $this->sign_encrypt(array('data' => $signStr));
        $myParams['sign'] = trim($sign['check']);
        // var_dump($myParams);
        $act = "https://df.ysepay.com/gateway.do";
        $result = Common::httpRequest($act,'POST',$myParams);
        $result = json_decode($result,true);
        // var_dump($result);die;
        if(isset($result['ysepay_df_single_quick_accept_response']['trade_status']) && ($result['ysepay_df_single_quick_accept_response']['trade_status']=='TRADE_ACCEPT_SUCCESS' || $result['ysepay_df_single_quick_accept_response']['trade_status']=='TRADE_SUCCESS')) {
           $return = array(
            'status'=>1,
            'msg'=>$result['ysepay_df_single_quick_accept_response']['trade_status_description']
            );
        } else {
            $return = array(
            'status'=>0,
            'msg'=>$result['ysepay_df_single_quick_accept_response']['sub_msg']
            );
        }
        return $return;
    }

    public function DfYinshengQuery($withdraw_no){
        $myParams = array();
        $myParams['charset'] = 'utf-8';
        $myParams['method'] = 'ysepay.df.single.query';
        // $myParams['notify_url'] = 'http://yspay.ngrok.cc/pay/respond_notify.php';
        $myParams['partner_id'] = 'yuanmeng';
        $myParams['sign_type'] = 'RSA';
        $myParams['timestamp'] = date('Y-m-d H:i:s', time());
        $myParams['version'] = '3.0';
        $biz_content_arr = array(
            "out_trade_no" => substr($withdraw_no,2),
            'shopdate'=>date('Ymd', time())
        );
        $myParams['biz_content'] = json_encode($biz_content_arr, JSON_UNESCAPED_UNICODE);//构造字符串
        ksort($myParams);
        $signStr = "";
        foreach ($myParams as $key => $val) {
            $signStr .= $key . '=' . $val . '&';
        }
        $signStr = rtrim($signStr, '&');
        // var_dump($signStr);
        $sign = $this->sign_encrypt(array('data' => $signStr));
        $myParams['sign'] = trim($sign['check']);
        // var_dump($myParams);
        $act = "https://searchdf.ysepay.com/gateway.do";
        $result = Common::httpRequest($act,'POST',$myParams);
        $result = json_decode($result,true);
        // var_dump($result);die;
        if(isset($result['ysepay_df_single_query_response']['trade_status'])) {
            if($result['ysepay_df_single_query_response']['trade_status']=='TRADE_ACCEPT_SUCCESS' || $result['ysepay_df_single_query_response']['trade_status']=='TRADE_SUCCESS') {
                $return['code']=1;
                $return['msg']=$result['ysepay_df_single_query_response']['trade_status_description'];
            } else {
                $return['code']=0;
                $return['msg']=$result['ysepay_df_single_query_response']['trade_status_description'];
            }
        } else {
            $return['code']=0;
            $return['msg']=$result['ysepay_df_single_query_response']['sub_msg'];
        }
        
        return $return;
    }

    public function sign_encrypt($input)
    {
        // $pfxpath = 'http://' . $_SERVER['HTTP_HOST'] . "/trunk/protected/classes/yinpay/certs/shanghu_test.pfx";
        $pfxpath = "./protected/classes/yinpay/certs/yuanmeng.pfx";
        $pfxpassword = 'lc008596';
        $return = array('success' => 0, 'msg' => '', 'check' => '');
        $pkcs12 = file_get_contents($pfxpath); //私钥
        if (openssl_pkcs12_read($pkcs12, $certs, $pfxpassword)) {
            $privateKey = $certs['pkey'];
            $publicKey = $certs['cert'];
            $signedMsg = "";
            if (openssl_sign($input['data'], $signedMsg, $privateKey, OPENSSL_ALGO_SHA1)) {
                $return['success'] = 1;
                $return['check'] = base64_encode($signedMsg);
                $return['msg'] = base64_encode($input['data']);

            }
        }

        return $return;
    }
}
?>