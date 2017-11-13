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
                'REQ_SN' => $merchantId.$params['withdraw_no'],
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
        return $result;
    }
}
?>