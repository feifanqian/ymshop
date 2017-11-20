<?php
class AllinpayDf{
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
                'REQ_SN' => $req_sn,
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
}
?>