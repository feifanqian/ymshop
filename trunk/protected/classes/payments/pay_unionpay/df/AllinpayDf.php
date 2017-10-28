<?php
class AllinpayDf{
	public function DFAllinpay($params){
	header('Content-Type: text/html; Charset=UTF-8');
     $tools=new PhpTools();
        $merchantId=AppConfig::MERCHANT_ID;
        // 源数组
        $data = array(
            'INFO' => array(
                'TRX_CODE' => '100014',
                'VERSION' => '03',
                'DATA_TYPE' => '2',
                'LEVEL' => '6',
                'USER_NAME' => '20058400001550504',
                'USER_PASS' => '111111',
                'REQ_SN' => $merchantId.date('YmdHis').rand(1000,9999),
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
        $result = $tools->send($data);
        if($result!=FALSE){
            echo  '验签通过，请对返回信息进行处理';
            return true;
            //下面商户自定义处理逻辑，此处返回一个数组
        }else{
            return false;
                print_r("验签结果：验签失败，请检查通联公钥证书是否正确");
        }
    }
}
?>