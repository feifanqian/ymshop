<?php
class AppUtil{
	/**
	 * 将参数数组签名
	 */
	public static function SignArray(array $array,$appkey){
		$array['key'] = $appkey;// 将key放到数组中一起进行排序和组装
		ksort($array);
		$blankStr = AppUtil::ToUrlParams($array);
		$sign = md5($blankStr);
		return $sign;
	}
	
	public static function ToUrlParams(array $array)
	{
		$buff = "";
		foreach ($array as $k => $v)
		{
			if($v != "" && !is_array($v)){
				$buff .= $k . "=" . $v . "&";
			}
		}
		
		$buff = trim($buff, "&");
		return $buff;
	}
	
/**
	 * 校验签名
	 * @param array 参数
	 * @param unknown_type appkey
	 */
	public static function ValidSign(array $array,$appkey){
		$sign = $array['sign'];
		unset($array['sign']);
		$array['key'] = $appkey;
		$mySign = AppUtil::SignArray($array, $appkey);
		return strtolower($sign) == strtolower($mySign);
	}

	// //发送请求操作仅供参考,不为最佳实践
    public static function Request($url,$params){
        $ch = curl_init();
        $this_header = array("content-type: application/x-www-form-urlencoded;charset=UTF-8");
        curl_setopt($ch,CURLOPT_HTTPHEADER,$this_header);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
         
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);//如果不加验证,就设false,商户自行处理
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
         
        $output = curl_exec($ch);
        curl_close($ch);
        return  $output;
    }

    // public function Request($url, $data = null) {
    //     $curl = curl_init();
    //     curl_setopt($curl, CURLOPT_URL, $url);
    //     curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
    //     curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
    //     if (!empty($data)) {
    //         curl_setopt($curl, CURLOPT_POST, 1);
    //         curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    //     }
    //     curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    //     $output = curl_exec($curl);
    //     curl_close($curl);
    //     return $output;
    // }

    //验签
    public static function ValidSigns($array){
        if("SUCCESS"==$array["retcode"]){
            $signRsp = strtolower($array["sign"]);
            $array["sign"] = "";
            $sign =  strtolower(AppUtil::SignArray($array, AppConfig::APPKEY));
            if($sign==$signRsp){
                return TRUE;
            }
            else {
                echo "验签失败:".$signRsp."--".$sign;
            }
        }
        else{
            echo $array["retmsg"];
        }
        
        return FALSE;
    }
	
	
}
?>