<?php
class ChinapayDf {
    //测试环境
    protected $pay_url="http://sfj-test.chinapay.com/dac/SinPayServletGBK";//代付支付
    protected $query_url="http://sfj-test.chinapay.com/dac/SinPayQueryServletGBK";//代付订单查询
    protected $balance_query_url="http://sfj-test.chinapay.com/dac/BalanceQueryGBK";//备用金查询
    protected $balance_detail_url="http://sfj-test.chinapay.com/dac/DepositDetailQueryGBK";//备用金明细
    //生产环境
//    protected $pay_url="http://sfj.chinapay.com/dac/SinPayServletGBK";//代付支付
//    protected $query_url="http://sfj.chinapay.com/dac/SinPayQueryServletGBK";//代付订单查询
  //protected $balance_query_url="http://sfj.chinapay.com/dac/BalanceQueryGBK";//备用金查询
//    protected $balance_detail_url="http://sfj.chinapay.com/dac/DepositDetailQueryGBK";//备用金明细
    protected $pri_key_path;
    protected $pub_key_path;
    protected $cilent;
    protected $merId;
    protected $responseCode=array(
      "0000"  =>"接收成功",
      "0100"  =>"商户提交的字段长度、格式错误",
      "0101"  =>"商户验签错误",
      "0102"  =>"手续费计算出错",
      "0103"  =>"商户备付金帐户金额不足",
      "0104"  =>"操作拒绝",
      "0105"  =>"重复交易"
    );
    protected $pay_status=array(
        "s"   =>"交易成功",
        "2"   =>"交易已接受，处理中",
        "3"   =>"财务已确认，处理中",
        "4"   =>"财务处理中",
        "5"   =>"已发往银行",
        "6"   =>"失败，银行已退单",
        "7"   =>"重汇已提交，处理中",
        "8"   =>"重汇已发送，处理中",
        "9"   =>"重汇已退单"
    );
    public function __construct() {
        $this->pri_key_path=dirname(__FILE__)."/certs/MerPrK_808080211880712_20170120171802.key";
        $this->pub_key_path=dirname(__FILE__)."/certs/PgPubk.key";
        $this->client = new netpayclient();
	//导入私钥文件, 返回值即为您的商户号，长度15位
	$this->merId = $this->client->buildKey($this->pri_key_path);
        if(!$this->merId){
            exit(json_encode(array("status"=>'fail','msg'=>"导入私钥文件失败！")));
        }
    }
    public function DfPay($params){
        return true;
        $merDate = $params["merDate"];
	$merSeqId = $params["merSeqId"];
        if(($merSeqId=='')&&($merDate=='')){
            exit(json_encode(array("status"=>'fail','msg'=>"请填写订单号和日期！")));
        }
	$cardNo = $params["cardNo"];
	$usrName = $params["usrName"];
	$openBank = $params["openBank"];
	$prov = $params["prov"];
	$city = $params["city"];
	$transAmt = $params["transAmt"];
	$purpose = isset($params['purpose'])?$params['purpose']:"用户提现";
	$subBank = isset($params['subBank'])?$params['subBank']:"";
	$flag = "00";
	$version = "20160530";
        $signFlag = "1";
	$termType = "08";  
        $payMode = "1";       
	//按次序组合报文信息为待签名串
        $plain = $this->merId . $merDate. $merSeqId .$cardNo .$usrName. $openBank . $prov .$city .$transAmt.$purpose.$subBank.$flag.$version.$termType.$payMode;
        //进行Base64编码
        $data = base64_encode(mb_convert_encoding($plain,'GBK','UTF-8'));
	//生成签名值，必填
	$chkValue =$this->client->sign($data);
	if (!$chkValue) {
		exit(json_encode(array("status"=>'fail','msg'=>"签名失败！")));
	}		
        $usrName = urlencode(iconv('UTF-8', 'GB2312', $usrName));  
	$openBank = urlencode(iconv('UTF-8', 'GB2312', $openBank));
	$prov = urlencode(iconv('UTF-8', 'GB2312', $prov));    
	$city = urlencode(iconv('UTF-8', 'GB2312', $city));         
	$purpose = urlencode(iconv('UTF-8', 'GB2312', $purpose)); 
	$subBank = urlencode(iconv('UTF-8', 'GB2312', $subBank));
        $post_data = "merId=$this->merId&merDate=$merDate&merSeqId=$merSeqId&cardNo=$cardNo&usrName=$usrName&openBank=$openBank&prov=$prov&city=$city&transAmt=$transAmt&purpose=$purpose&subBank=$subBank&flag=$flag&version=$version&termType=$termType&signFlag=$signFlag&payMode=$payMode&chkValue=$chkValue";
        $post_data=mb_convert_encoding( $post_data, 'GBK','UTF-8');
        $output = $this->httpPost($post_data, $this->pay_url);
        if($output){
            $output = trim(strip_tags($output));
            $datas = explode("&",$output);
            $dex = strripos($output,"&");
	    $plain = substr($output,0,$dex);
            $plaindata = base64_encode($plain);	
            $resp_code = $data[0];
            $chkValue = substr($output,$dex+ 10);
            $flag = $this->client->buildKey($this->pub_key_path);
            if(!$flag){
                 exit(json_encode(array("status"=>'fail','msg'=>"导入公钥文件失败！")));
            }else{
                $flag  =  $this->client->verify($plaindata, $chkValue);
                if($flag) {
                      parse_str($plain,$result);
                      if($result['responseCode']=="0000"){
                          return true;
                      }else{
                          exit(json_encode(array("status"=>'fail','msg'=>$this->responseCode[$result['responseCode']])));
                      }
                } else {
                      exit(json_encode(array("status"=>'fail','msg'=>"签名验证失败！")));
                }
            }
        }else{
            exit(json_encode(array("status"=>'fail','msg'=>"HTTP 请求失败！")));
        }
    }
    
    public function DfQuery($params){
        $merSeqId = $params['merSeqId'];
        $merDate = $params['merDate'];// 仅允许查询最近一个月的交易订单
        if(strtotime(date("Y-m-d",strtotime("-1 month")))>strtotime($merDate)){
            exit(json_encode(array("status"=>'fail','msg'=>"仅允许查询最近一个月的交易订单")));
        }
        $version = "20090501";
        $signFlag = "1";
        $plain = $this->merId . $merDate  . $merSeqId . $version;
    	//进行Base64编码
    	$data = base64_encode($plain);
    	//生成签名值，必填
    	$chkValue = $this->client->sign($data);
    	if (!$chkValue) {
    		exit(json_encode(array("status"=>'fail','msg'=>"签名失败！")));
    	}
            $post_data = "merId=$this->merId&merDate=$merDate&merSeqId=$merSeqId&version=$version&signFlag=$signFlag&chkValue=$chkValue";
            $output =$this->httpPost($post_data, $this->query_url); 
    	if($output){
                $output = trim(strip_tags($output));
                $dex = strripos($output,"|");
                $plain = substr($output,0,$dex + 1);
                $plaindata = base64_encode($plain);	
                $chkValue = substr($output,$dex + 1);
                $flag = $this->client->buildKey($this->pub_key_path);
                if(!$flag) {
                         exit(json_encode(array("status"=>'fail','msg'=>"导入公钥文件失败！")));
                } else {
                        $flag  =  $this->client->verify($plaindata, $chkValue);
                        if($flag) {
                               $result = explode("|", $plain);
                               if($result[0]=="000"){
                                   exit(json_encode(array("status"=>'success','msg'=>"查询成功","pay_status"=>$this->pay_status[$result[14]])));
                               }else if($result[0]=="001"){
                                   exit(json_encode(array("status"=>'fail','msg'=>"记录不存在")));
                               }else if($result[0]=='002'){
                                   exit(json_encode(array("status"=>'fail','msg'=>"查询失败！")));
                               }else{
                                   exit(json_encode(array("status"=>'fail','msg'=>"查询失败！未知错误")));
                               }
                        } else {
                                exit(json_encode(array("status"=>'fail','msg'=>"验证签名失败！")));
                        }
               }
    	} else {
    		exit(json_encode(array("status"=>'fail','msg'=>"HTTP 请求失败！")));
    	}
    }
    
    public function DfBalanceQuery(){
        //接口版本号，境内支付为 20090501，必填
	$version = "20090501";	
	//签名标志，值固定，但不参与签名
	$signFlag = "1";

	//按次序组合报文信息为待签名串
	$plain = $this->merId . $version;
	//进行Base64编码
	$data = base64_encode($plain);
	//生成签名值，必填
	$chkvalue =$this->client->sign($data);
	if (!$chkvalue) {
	    exit(json_encode(array("status"=>'fail','msg'=>"签名失败！")));
	}
        $post_data = "merId={$this->merId}&version=$version&signFlag=$signFlag&chkValue=$chkvalue";
        $output = $this->httpPost($post_data, $this->balance_query_url);
        if($output){
            $output = trim(strip_tags($output));
	    $dex = strripos($output,"|");
	    $plain = substr($output,0,$dex + 1);
	    $plaindata = base64_encode($plain);
	    $chkValue = substr($output,$dex + 1);
				
	    //开始验证签名，首先导入公钥文件
	    $flag = $this->client->buildKey($this->pub_key_path);
	    if(!$flag) {
		    exit(json_encode(array("status"=>'fail','msg'=>"导入公钥文件失败！")));
	    } else {
		    $flag  =  $this->client->verify($plaindata, $chkValue);
		    if($flag) {
		        $result = explode("|", $plain);
                        if($result[0]=="000"){
                            exit(json_encode(array("status"=>'success','msg'=>"成功",'balance'=>$result[2])));
                        }else{
                            exit(json_encode(array("status"=>'fail','msg'=>"查询失败")));
                        }
		    } else {
			exit(json_encode(array("status"=>'fail','msg'=>"验证签名失败")));
		    }				
	    } 
	}else {
	    exit(json_encode(array("status"=>'fail','msg'=>"HTTP 请求失败！")));
	}
    }
    
    private function httpPost($post_data,$url){
        $http = curl_init();
	curl_setopt($http, CURLOPT_ENCODING, "gzip"); 
	curl_setopt($http, CURLOPT_TIMEOUT, 30); 
	curl_setopt($http, CURLOPT_POST, 1);
        curl_setopt($http, CURLOPT_POSTFIELDS, $post_data); // $post_data string or hash array
	$output = $this->curl_redir_exec($http, $url);
        curl_close($http); 
	return $output;
    }

    function curl_redir_exec($ch, $url){
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data =curl_exec($ch);
        $ret = $data;
        list($header, $data) = explode("\r\n\r\n", $data, 2);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http_code == 301 || $http_code == 302) {
            $matches = array();
            preg_match('/Location:(.*?)\n/', $header, $matches);
            $url = @parse_url(trim(array_pop($matches)));
            if (!$url)
            {
              //couldn't process the url to redirect to
              $curl_loops = 0;
              return $data;
            }
            $last_url = parse_url(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL));
            if (!$url['scheme'])
                $url['scheme'] = $last_url['scheme'];
            if (!$url['host'])
                $url['host'] = $last_url['host'];
            if (!$url['path'])
                $url['path'] = $last_url['path']; 
            $new_url = $url['scheme'] . '://' . $url['host'] . $url['path'] . (isset($url['query']) ? '?'.$url['query'] : '');
            return curl_redir_exec($ch, $new_url);
        } else if ($http_code == 200) {
            list($header, $data) = explode("\r\n\r\n", $ret, 2);
            return $data;
        } else {
              return false;
        }
    }

    
}
