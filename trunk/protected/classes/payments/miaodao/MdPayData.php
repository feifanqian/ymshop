<?php

class MdPayDataBase{
    protected $values = array();
    protected $pkey ;
    public function SetPkey($value){
        $this->pkey=$value;
    }
    public function GetPkey(){
        return $this->pkey;
    }
    /**
     * 获取设置的值
     */
    public function GetValues() {
        return $this->values;
    }
    
    public function SetValues($values){
        $this->values = $values;
    }
}

class MdPayQuoteData extends MdPayDataBase{
    public function SetDpPid($value) {
       $this->values['dpPid'] = $value;            
    }
    public function GetDpPid(){
        return $this->values['dpPid'];
    }
     public function SetTimestamp($value) {
       $this->values['timestamp'] = $value;            
    }
    public function GetTimestamp(){
        return $this->values['timestamp'];
    }
     public function SetDeclareMerch($value) {
       $this->values['declareMerch'] = $value;            
    }
    public function GetDeclareMerch(){
        return $this->values['declareMerch'];
    }
     public function SetDeclareAcc($value) {
       $this->values['declareAcc'] = $value;            
    }
    public function GetDeclareAcc(){
        return $this->values['declareAcc'];
    }
    public function SetMerchAddr($value){
        $this->values['declareMerch']['merchaddr']=$value;
    }
    public function GetMerchAddr(){
        return $this->values['declareMerch']['merchaddr'];
    }
     public function SetMerchName($value){
        $this->values['declareMerch']['merchname']=$value;
    }
    public function GetMerchName(){
        return $this->values['declareMerch']['merchname'];
    }
     public function SetContact($value){
        $this->values['declareMerch']['contact']=$value;
    }
    public function GetContact(){
        return $this->values['declareMerch']['contact'];
    }
     public function SetTelephone($value){
        $this->values['declareMerch']['telephone']=$value;
    }
    public function GetTelephone(){
        return $this->values['declareMerch']['telephone'];
    }
    public function SetIdentitycard($value) {
        $this->values['declareMerch']['identitycard']=$value;
    }
    public function GetIdentitycard(){
        return $this->values['declareMerch']['identitycard'];
    }
    public function SetRatemodel($value){
        $this->values['declareMerch']['ratemodel']=$value;
    }
    public function GetRatemodel(){
        return $this->values['declareMerch']['ratemodel'];
    }
    public function SetFeerate($value){
         $this->values['declareMerch']['feerate']=$value;
    }
    public function GetFeerate(){
        return  $this->values['declareMerch']['feerate'];
    }
    //可选
    public function SetMerchId($value){
         $this->values['declareMerch']['merchId']=$value;
    }
    public function GetMerchId(){
        return  $this->values['declareMerch']['merchId'];
    }
    public function SetAccountName($value){
         $this->values['declareAcc']['accountname']=$value;
    }
    public function GetAccountName(){
        return  $this->values['declareAcc']['accountname'];
    }
    public function SetAccountNo($value){
         $this->values['declareAcc']['accountno']=$value;
    }
    public function GetAccountNo(){
        return  $this->values['declareAcc']['accountno'];
    }
    public function SetBankName($value){
         $this->values['declareAcc']['bankname']=$value;
    }
    public function GetBankName(){
        return  $this->values['declareAcc']['bankname'];
    }
    public function SetSecret(){
        $this->values['secret']=MD5($this->values['dpPid'].$this->pkey.$this->values['timestamp']);
    }
    public function GetSecret(){
        return $this->values['secret'];
    }
}

class MdPayPreOrderData extends MdPayDataBase{
    public function SetPid($value){
        $this->values['pid']=$value;
    }
    public function GetPid(){
        return $this->values['pid'];
    }
    public function SetMid($value){
        $this->values['mid']=$value;
    }
    public function GetMid(){
        return $this->values['mid'];
    }
    public function SetAmount($value){
        $this->values['amount']=$value;
    }
    public function GetAmount(){
        return $this->values['amount'];
    }
    public function SetNo($value){
        $this->values['no']=$value;
    }
    public function GetNo(){
        return $this->values['no'];
    }
    public function SetState($value){
        $this->values['state']=$value;
    }
    public function GetState(){
        return $this->values['state'];
    }
     /**
     * 设置签名，详见签名生成算法
     * @param string $value 
     * */
    public function SetSign() {
        $sign = $this->MakeSign();
        $this->values['sign'] = $sign;
        return $sign;
    }

    /**
     * 获取签名，详见签名生成算法的值
     * @return 值
     * */
    public function GetSign() {
        return $this->values['sign'];
    }
    /**
     * 判断签名，详见签名生成算法是否存在
     * @return true 或 false
     * */
    public function IsSignSet() {
        return array_key_exists('sign', $this->values);
    }

    /**
     * 格式化参数格式化成url参数
     */
    public function ToUrlParams() {
        $buff = "";
        foreach ($this->values as $k => $v) {
            if ($k != "sign" && $v != "" && !is_array($v)) {
                $buff .= $k . "=" . $v . "&";
            }
        }
        $buff = trim($buff, "&");
        return $buff;
    }
    /**
     * 生成签名
     * @return 签名，本函数不覆盖sign成员变量，如要设置签名需要调用SetSign方法赋值
     */
    public function MakeSign() {
        //签名步骤一：按字典序排序参数
        ksort($this->values);
        $string = $this->ToUrlParams();
        //签名步骤二：在string后加入KEY
        $string = $string . "&key=" . $this->pkey;
        //签名步骤三：MD5加密
        $string = md5($string);
        //签名步骤四：所有字符转为大写
        $result = strtoupper($string);
        return $result;
    }
}

class MdPayQrPayData extends MdPayDataBase{
    public function SetChannelType($value){
        $this->values['channelType']=$value;
    }
    public function GetChannelType(){
        return $this->values['channelType'];
    }
    public function SetDpPid($value){
        $this->values['dpPid']=$value;
    }
    public function GetDpPid(){
        return $this->values['dpPid'];
    }
    public function SetDpMid($value){
        $this->values['dpMid']=$value;
    }
    public function GetDpMid(){
        return $this->values['dpMid'];
    }
    public function SetAmount($value){
        $this->values['amount']=$value;
    }
    public function GetAmount(){
        return $this->values['amount'];
    }
    public function SetNo($value){
        $this->values['no']=$value;
    }
    public function GetNo(){
        return $this->values['no'];
    }
    public function SetMtoken($value){
        $this->values['mtoken']=$value;
    }
    public function GetMtoken(){
        return $this->values['mtoken'];
    }
    /**
     * 设置签名，详见签名生成算法
     * @param string $value 
     * */
    public function SetSign($value) {
        if($value){
            $this->values['sign'] = $value; 
        }else{
            $sign = $this->MakeSign();
            $this->values['sign'] = $sign;
        }
    }

    /**
     * 获取签名，详见签名生成算法的值
     * @return 值
     * */
    public function GetSign() {
        return $this->values['sign'];
    }
    /**
     * 判断签名，详见签名生成算法是否存在
     * @return true 或 false
     * */
    public function IsSignSet() {
        return array_key_exists('sign', $this->values);
    }

    /**
     * 生成签名
     * @return 签名，本函数不覆盖sign成员变量，如要设置签名需要调用SetSign方法赋值
     */
    public function MakeSign() {
        $result = strtolower(MD5($this->values['pid'].$this->values['mid']."0".$this->values['amount'].$this->values['no'].$this->pkey));
        return $result;
    }
}

class MdPayQueryData extends MdPayDataBase{
    public function SetDpNo($value){
        $this->values['dpNo']=$value;
    }
    public function GetDpNo(){
        return $this->values['dpNo'];
    }
    public function SetDpMid($value){
        $this->values['dpMid']=$value;
    }
    public function GetDpMid(){
        return $this->values['dpMid'];
    }
}

class MdPayResultData extends MdPayDataBase{
    public function SetPid($value){
        $this->values['pid'] = $value;
    }
    public function GetPid(){
        return $this->values['pid'];
    }
    public function SetMerchId($value){
        $this->values['merchId'] = $value;
    }
    public function GetMerchId(){
        return $this->values['merchId'];
    }
    public function SetResult($value){
        $this->values['result'] = $value;
    }
    public function GetResult(){
        return $this->values['result'];
    }
    public function SetErrstr($value){
        $this->values['errStr'] = $value;
    }
    public function GetErrStr(){
        return $this->values['errStr'];
    }
    public function SetRid($value){
        $this->values['rid'] = $value;
    }
    public function GetRid(){
        return $this->values['rid'];
    }
    public function SetMrid($value){
        $this->values['mrid'] = $value;
    }
    public function GetMrid(){
        return $this->values['mrid'];
    }
    public function SetOid($value){
        $this->values['oid'] = $value;
    }
    public function GetOid(){
        return $this->values['oid'];
    }
    public function SetMoid($value){
        $this->values['moid'] = $value;
    }
    public function GetMoid(){
        return $this->values['moid'];
    }
    public function SetToid($value){
        $this->values['toid'] = $value;
    }
    public function GetToid(){
        return $this->values['toid'];
    }
    public function SetTranAmount($value){
        $this->values['tranAmount'] = $value;
    }
    public function GetTranAmount(){
        return $this->values['tranAmount'];
    }
    public function SetPayAmount($value){
        $this->values['payAmount'] = $value;
    }
    public function GetPayAmount(){
        return $this->values['payAmount'];
    }
    public function SetFeeRate($value){
        $this->values['feeRate'];
    }
    public function GetFeeRate(){
        return $this->values['feeRate'];
    }
    public function SetTradeType($value){
        $this->values['tradeType']=$value;
    }
    public function GetTradeType(){
        return $this->values['tradeType'];
    }
    public function SetTradeTime($value){
        $this->values['tradeTime']=$value;
    }
    public function GetTradeTime(){
        return $this->values['tradeTime'];
    }
    public function SetSignType($value){
        $this->values['signType']=$value;
    }
    public function GetSignType(){
        return $this->values['signType'];
    }
    public function SetSign($value){
        $this->values['sign']=$value;
    }
    public function getSign(){
        return $this->values['sign'];
    }
}