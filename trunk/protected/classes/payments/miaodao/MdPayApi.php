<?php
/* 
 *秒到支付的API
 */
class MdPayApi{
     protected $host = "http://wxgzh.qimiaolife.com";
     //protected $host = "http://sitboss.ditiy.com";  //测试
    //进件上报
     public static function quote($obj) {
         //$url = "http://sitboss.ditiy.com"."/downStream/quote";
         $url = "http://wxgzh.qimiaolife.com"."/downStream/quote";
         if($obj instanceof MdPayQuoteData){
            $params = $obj ->GetValues();
            $data_string= json_encode($params,JSON_UNESCAPED_UNICODE);
            $result = self::doPost($url, $data_string);
            return json_decode($result, TRUE);
         }else{
              throw new MdPayException("进件参数错误");
         }
     }
     //预下单
     public static function preOrder($obj){
//         $url = "http://sitboss.ditiy.com"."/downStream/preOrder";
         $url = "http://wxgzh.qimiaolife.com"."/downStream/preOrder";
          if($obj instanceof MdPayPreOrderData){
             $params = $obj ->GetValues();
//             print_r($params);die;
             $data_string= json_encode($params,JSON_UNESCAPED_UNICODE);
             $result = self::doPost($url, $data_string);
             return json_decode($result, TRUE);
         }else{
              throw new MdPayException("参数错误");
         }
     }
     //支付
     public static function qrPayUrl($obj){
//         $url = "http://sitboss.ditiy.com"."/downStream/QRpay";
         $url = "http://wxgzh.qimiaolife.com"."/downStream/QRpay";
          if($obj instanceof MdPayQrPayData){
             $params = $obj ->GetValues();
             return $url."?pay=".json_encode($params,JSON_UNESCAPED_UNICODE);
            // $result = self::doGet($url."?pay=".json_encode($params,JSON_UNESCAPED_UNICODE));
            // return json_decode($result, TRUE);
         }else{
              throw new MdPayException("参数错误");
         }
     }
     //查询
     public static function query($obj){
//         $url = "http://sitboss.ditiy.com"."/downStream/queryPay";
         $url = "http://wxgzh.qimiaolife.com"."/downStream/queryPay";
          if($obj instanceof MdPayQueryData){
             $params = $obj ->GetValues();
             $result = self::doGet($url."?dpMid={$params['dpMid']}&dpNo={$params['dpNo']}");
             return json_decode($result, TRUE);
         }else{
              throw new MdPayException("参数错误");
         }
     }
   public static function doPost($url,$data_string,$timeout=10){
        $ch = curl_init();  
        curl_setopt($ch, CURLOPT_POST, 1);  
        curl_setopt($ch, CURLOPT_URL, $url);  
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);  
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(  
            'Content-Type: application/json; charset=utf-8',  
            'Content-Length: ' . strlen($data_string))  
        ); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        $result = curl_exec($ch);
        if ($result) {
            curl_close($ch);
            return $result;
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
            exit(json_encode(array("status"=>'fail','msg'=>"curl出错，错误码:$error")));
            throw new MdPayException("curl出错，错误码:$error");
        }
   }
   
   //通过curl get数据
    static public function doGet($url, $timeout = 10, $header = "") {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        $result = curl_exec($ch);
        if ($result) {
            curl_close($ch);
            return $result;
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
            exit(json_encode(array("status"=>'fail','msg'=>"curl出错，错误码:$error")));
            throw new MdPayException("curl出错，错误码:$error");
        }
    }
}
