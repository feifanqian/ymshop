<?php 
 
class PhpTools{

	const certFile ="allinpay-pds.pem";//通联公钥证书
	const privateKeyFile = "20060400000044502.pem";//商户私钥证书
	const password = '111111';//商户私钥密码以及用户密码
//	const  apiUrl = 'http://172.16.1.11:8080/aipg/ProcessServlet';//通联系统对接请求地址（内网）
	public $arrayXml ;
    // const apiUrl = 'http://113.108.182.3:8083/aipg/ProcessServlet';//通联系统对接请求地址（外网,商户测试时使用）
	const apiUrl = 'https://tlt.allinpay.com/aipg/ProcessServlet';//（生产环境地址，上线时打开该注释）
	
	 public function __construct() {
        $this->certFile=dirname(__FILE__)."/certs/allinpay-pds.pem";
        
        $this->privateKeyFile=dirname(__FILE__)."/certs/20058400001550504.pem";
        
        $this->arrayXml = new ArrayAndXml();
    }   
	
	/**
	 * PHP版本低于 5.4.1 的在通联返回的是 GBK编码环境使用
	 * 但是本地文件编码是 UTF-8
	 *
	 * @param string $hexstr
	 * @return binary string
	 */
	public function hextobin($hexstr) {
	    $n = strlen($hexstr);
	    $sbin = "";
	    $i = 0;
	
	    while($i < $n) {
	        $a = substr($hexstr, $i, 2);
	        $c = pack("H*",$a);
	        if ($i==0) {
	            $sbin = $c;
	        } else {
	            $sbin .= $c;
	        }
	
	        $i+=2;
	    }
	
	    return $sbin;
	}
	
	/**
	 * 验签
	 */
	public function verifyXml($xmlResponse , $req_sn){
		$ChinapayDf = new AllinpayDf();	
		// 本地反馈结果验证签名开始
		$signature = '';
		if (preg_match('/<SIGNED_MSG>(.*)<\/SIGNED_MSG>/i', $xmlResponse, $matches)) {
		    $signature = $matches[1];
		}
		
		$xmlResponseSrc = preg_replace('/<SIGNED_MSG>.*<\/SIGNED_MSG>/i', '', $xmlResponse);
		$xmlResponseSrc1 = mb_convert_encoding(str_replace('<','&lt;',$xmlResponseSrc), "UTF-8", "GBK");
		// $xmlResponseSrc1 = mb_convert_encoding($xmlResponseSrc, "UTF-8", "GBK");
		// $xmlResponseSrc2 = Common::xmlToArray($xmlResponseSrc1);
		// print_r ('验签原文');
		// var_dump ($xmlResponseSrc2);
		$pubKeyId = openssl_get_publickey(file_get_contents($this->certFile));
		$flag = (bool) openssl_verify($xmlResponseSrc, hex2bin($signature), $pubKeyId);
		openssl_free_key($pubKeyId);
	    //echo '<br/>'+$flag;
	    // 变成数组，做自己相关业务逻辑
		$xmlResponse = mb_convert_encoding(str_replace('<?xml version="1.0" encoding="GBK"?>', '<?xml version="1.0" encoding="UTF-8"?>', $xmlResponseSrc), 'UTF-8', 'GBK');

		$results = $this->arrayXml->parseString( $xmlResponse , TRUE);
		
			if(isset($results['AIPG']['TRANSRET'])){
				if($results['AIPG']['TRANSRET']['RET_CODE']==0000){
			    	$return['status']=1; //成功
			    	$return['msg'] = $results['AIPG']['TRANSRET']['ERR_MSG'];
			    }else{
			    	$code = $results['AIPG']['TRANSRET']['RET_CODE'];
			    	$code_arr = array('2000','2001','2003','2005','2007','2008'); //中间状态码
			    	if(in_array($code,$code_arr)){
			    		$return['status']=4;
			    		$return['msg']="正在处理";
			    		#需要调用查询接口返回最终结果
			    		// $ret = $ChinapayDf->DFquery($req_sn);
			    		// if($ret['code']==1){
			    		// 	$return['status']=1;
			    		// 	$return['msg']='处理成功';
			    		// }else{
			    		// 	$return['status']=0;
			    		// 	$return['msg']=$ret['msg'];
			    		// }
			    	}else{
			    		$return['status']=0; //失败
			    	    $return['msg'] = 'CODE:'.$code.$results['AIPG']['TRANSRET']['ERR_MSG'];
			    	}	
			    }
			}else{
				if(isset($results['AIPG']['INFO'])){
					$code = $results['AIPG']['INFO']['RET_CODE'];
					$code_arr = array('2000','2001','2003','2005','2007','2008'); //中间状态码
					if($code==0000){
						$return['status']=1;  //成功
                        $return['msg']=$results['AIPG']['INFO']['ERR_MSG'];
					}elseif(in_array($code,$code_arr)){
						$return['status']=4;
			    		$return['msg']="正在处理";
                        #需要调用查询接口返回最终结果
			    		// $ret = $ChinapayDf->DFquery($req_sn);
			    		// if($ret['code']==1){
			    		// 	$return['status']=1;
			    		// 	$return['msg']='处理成功';
			    		// }else{
			    		// 	$return['status']=0; //失败
			    		// 	$return['msg']=$ret['msg'];
			    		// }
					}else{
						$return['status']=0; //失败
						$return['msg'] = 'CODE:'.$code.$results['AIPG']['INFO']['ERR_MSG'];
					}
					
				}else{
					$return['status']=0; //失败
					$return['msg'] = '未知错误';
				}
			}    	        
		
		return $return;
	}

	/**
	 * 验签
	 */
	public function verifyXmls($xmlResponse){
		$ChinapayDf = new AllinpayDf();	
		// 本地反馈结果验证签名开始
		$signature = '';
		if (preg_match('/<SIGNED_MSG>(.*)<\/SIGNED_MSG>/i', $xmlResponse, $matches)) {
		    $signature = $matches[1];
		}
		
		$xmlResponseSrc = preg_replace('/<SIGNED_MSG>.*<\/SIGNED_MSG>/i', '', $xmlResponse);
		$xmlResponseSrc1 = mb_convert_encoding(str_replace('<','&lt;',$xmlResponseSrc), "UTF-8", "GBK");
		// $xmlResponseSrc1 = mb_convert_encoding($xmlResponseSrc, "UTF-8", "GBK");
		// $xmlResponseSrc2 = Common::xmlToArray($xmlResponseSrc1);
		// print_r ('验签原文');
		// var_dump ($xmlResponseSrc2);
		$pubKeyId = openssl_get_publickey(file_get_contents($this->certFile));
		$flag = (bool) openssl_verify($xmlResponseSrc, hex2bin($signature), $pubKeyId);
		openssl_free_key($pubKeyId);
	    //echo '<br/>'+$flag;
	    // 变成数组，做自己相关业务逻辑
		$xmlResponse = mb_convert_encoding(str_replace('<?xml version="1.0" encoding="GBK"?>', '<?xml version="1.0" encoding="UTF-8"?>', $xmlResponseSrc), 'UTF-8', 'GBK');

		$results = $this->arrayXml->parseString( $xmlResponse , TRUE);
		// var_dump($results);
		if(isset($results['AIPG']['QTRANSRSP'])){
			if(isset($results['AIPG']['QTRANSRSP']['QTDETAIL'])){
				if($results['AIPG']['QTRANSRSP']['QTDETAIL']['RET_CODE']=="0000"){
					$return['code']=1;
					$return['msg']=$results['AIPG']['QTRANSRSP']['QTDETAIL']['ERR_MSG'];
				}else{
					$return['code']=0;
					$return['msg']=$results['AIPG']['QTRANSRSP']['QTDETAIL']['ERR_MSG'];
				}
			}else{
				$return['code']=0;
				$return['msg']='网络繁忙，请稍后再试';
			}	
		}else{
			$return['code']=0;
			$return['msg']='未处理';
		}
			    	        
		return $return;
	}
	
	/**
	 * 验签
	 */
	public function verifyStr($orgStr,$signature){
		echo '签名原文:'.$orgStr;
		$pubKeyId = openssl_get_publickey(file_get_contents($this->certFile));
		$flag = (bool) openssl_verify($orgStr, hex2bin($signature), $pubKeyId);
		openssl_free_key($pubKeyId);
		
		if ($flag) {
			echo '<br/>Verified: <font color=red>SUCC</font>.';
		    return TRUE;
		} else {
		    echo '<br/>Verified: <font color=red>Failed</font>.';
		    return FALSE;
		}
	}
	
	/**
	 * 签名
	 */
	public function signXml($params){
		 
		$xmlSignSrc = $this->arrayXml->toXmlGBK($params, 'AIPG');
		$xmlSignSrc=str_replace("TRANS_DETAIL2", "TRANS_DETAIL",$xmlSignSrc);
//		echo ($xmlSignSrc);
		$privateKey = file_get_contents($this->privateKeyFile);
		
		$pKeyId = openssl_pkey_get_private($privateKey, PhpTools::password);
		openssl_sign($xmlSignSrc, $signature, $pKeyId);
		openssl_free_key($pKeyId);
		
		$params['INFO']['SIGNED_MSG'] = bin2hex($signature);		
		$xmlSignPost = $this->arrayXml->toXmlGBK($params, 'AIPG');

		return  $xmlSignPost;
	}
	/**
	 * 发送请求
	 */
	public function send($params , $req_sn=''){
		header('Content-Type: text/html; Charset=UTF-8');
		$xmlSignPost=$this->signXml($params);
		$xmlSignPost=str_replace("TRANS_DETAIL2", "TRANS_DETAIL",$xmlSignPost);
		// var_dump($xmlSignPost);die;
		$response = cURL::factory()->post(PhpTools::apiUrl, $xmlSignPost);
	
		if (! isset($response['body'])) {
			if(isset($response['header'])){
				$msg = 'Bad Request:'.$response['header'];
			}else{
				$msg = 'Error: HTTPS REQUEST Bad.';
			}
			$result['status']=0;
			$result['msg'] = $msg;
			return $result;
		}
		//获取返回报文
		$xmlResponse = $response['body'];

		 //验证返回报文
		$result=$this->verifyXml($xmlResponse,$req_sn);
		return $result;
	}

	/**
	 * 发送请求
	 */
	public function sends($params){
		header('Content-Type: text/html; Charset=UTF-8');
		$xmlSignPost=$this->signXml($params);
		$xmlSignPost=str_replace("TRANS_DETAIL2", "TRANS_DETAIL",$xmlSignPost);
		// var_dump($xmlSignPost);die;
		$response = cURL::factory()->post(PhpTools::apiUrl, $xmlSignPost);
		if (! isset($response['body'])) {
			if(isset($response['header'])){
				$msg = 'Bad Request:'.$response['header'];
			}else{
				$msg = 'Error: HTTPS REQUEST Bad.';
			}
			$result['status']=0;
			$result['msg'] = $msg;
			return $result;
		}
		//获取返回报文
		$xmlResponse = $response['body'];
        var_dump($params);
	    var_dump(Common::xmlToArray($xmlResponse));die;
		 //验证返回报文
		$result=$this->verifyXmls($xmlResponse);
		return $result;
	}
}

?>