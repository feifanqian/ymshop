View Raw Code?
<?php
/**
 * TOP API: taobao.taobaoke.items.detail.get request
 * 
 * @author auto create
 * @since 1.0, 2012-08-08 16:40:51
 */
class ItemDetailGetRequest
{
    /** 
     * 闇€杩斿洖鐨勫瓧娈靛垪琛?鍙€夊€?TaobaokeItemDetail娣樺疂瀹㈠晢鍝佺粨鏋勪綋涓殑鎵€鏈夊瓧娈?瀛楁涔嬮棿鐢?,"鍒嗛殧銆俰tem_detail闇€瑕佽缃埌Item妯″瀷涓嬬殑瀛楁,濡傝缃?num_iid,detail_url绛? 鍙缃甶tem_detail,鍒欎笉杩斿洖鐨処tem涓嬬殑鎵€鏈変俊鎭?娉細item缁撴瀯涓殑skus銆乿ideos銆乸rops_name涓嶈繑鍥?
     **/
    private $fields;
    /** 
     * 鏍囪瘑涓€涓簲鐢ㄦ槸鍚︽潵鍦ㄦ棤绾挎垨鑰呮墜鏈哄簲鐢?濡傛灉鏄痶rue鍒欎細浣跨敤鍏朵粬瑙勫垯鍔犲瘑鐐瑰嚮涓?濡傛灉涓嶇┛鍊?鍒欓粯璁ゆ槸false.
     **/
    private $isMobile;
    /** 
     * 娣樺疂鐢ㄦ埛鏄电О锛屾敞锛氭寚鐨勬槸娣樺疂鐨勪細鍛樼櫥褰曞悕.濡傛灉鏄电О閿欒,閭ｄ箞瀹㈡埛灏辨敹涓嶅埌浣ｉ噾.姣忎釜娣樺疂鏄电О閮藉搴斾簬涓€涓猵id锛屽湪杩欓噷杈撳叆瑕佺粨绠椾剑閲戠殑娣樺疂鏄电О锛屽綋鎺ㄥ箍鐨勫晢鍝佹垚鍔熷悗锛屼剑閲戜細鎵撳叆姝よ緭鍏ョ殑娣樺疂鏄电О鐨勮处鎴枫€傚叿浣撶殑淇℃伅鍙互鐧诲叆闃块噷濡堝鐨勭綉绔欐煡鐪?
     **/
    private $nick;
    /** 
     * 娣樺疂瀹㈠晢鍝佹暟瀛梚d涓?鏈€澶ц緭鍏?0涓?鏍煎紡濡?"value1,value2,value3" 鐢? , "鍙峰垎闅斿晢鍝乮d.
     **/
    private $numIids;
    /** 
     * 鑷畾涔夎緭鍏ヤ覆.鏍煎紡:鑻辨枃鍜屾暟瀛楃粍鎴?闀垮害涓嶈兘澶т簬12涓瓧绗?鍖哄垎涓嶅悓鐨勬帹骞挎笭閬?濡?bbs,琛ㄧずbbs涓烘帹骞挎笭閬?blog,琛ㄧずblog涓烘帹骞挎笭閬?
     **/
    private $outerCode;
    /** 
     * 鐢ㄦ埛鐨刾id,蹇呴』鏄痬m_xxxx_0_0杩欑鏍煎紡涓棿鐨?xxxx". 娉ㄦ剰nick鍜宲id鑷冲皯闇€瑕佷紶閫掍竴涓?濡傛灉2涓兘浼犱簡,灏嗕互pid涓哄噯,涓攑id鐨勬渶澶ч暱搴︽槸20
     **/
    private $pid;
    /** 
     * 鍟嗗搧track_iid涓诧紙甯︽湁杩借釜鏁堟灉鐨勫晢鍝乮d),鏈€澶ц緭鍏?0涓?涓巒um_iids蹇呭～鍏朵竴
     **/
    private $trackIids;
    private $apiParas = array();
    public function setFields($fields)
    {
        $this->fields = $fields;
        $this->apiParas["fields"] = $fields;
    }
    public function getFields()
    {
        return $this->fields;
    }
    public function setIsMobile($isMobile)
    {
        $this->isMobile = $isMobile;
        $this->apiParas["is_mobile"] = $isMobile;
    }
    public function getIsMobile()
    {
        return $this->isMobile;
    }
    public function setNick($nick)
    {
        $this->nick = $nick;
        $this->apiParas["nick"] = $nick;
    }
    public function getNick()
    {
        return $this->nick;
    }
    public function setItemId($numIids)
    {
        $this->numIids = $numIids;
        $this->apiParas["num_iids"] = $numIids;
    }
    public function getNumIids()
    {
        return $this->numIids;
    }
    public function setOuterCode($outerCode)
    {
        $this->outerCode = $outerCode;
        $this->apiParas["outer_code"] = $outerCode;
    }
    public function getOuterCode()
    {
        return $this->outerCode;
    }
    public function setPid($pid)
    {
        $this->pid = $pid;
        $this->apiParas["pid"] = $pid;
    }
    public function getPid()
    {
        return $this->pid;
    }
    public function setTrackIids($trackIids)
    {
        $this->trackIids = $trackIids;
        $this->apiParas["track_iids"] = $trackIids;
    }
    public function getTrackIids()
    {
        return $this->trackIids;
    }
    public function getApiMethodName()
    {
        return "taobao.taobaoke.items.detail.get";
    }
    public function getApiParas()
    {
        return $this->apiParas;
    }
    public function check()
    {
        RequestCheckUtil::checkNotNull($this->fields,"fields");
        RequestCheckUtil::checkMaxListSize($this->numIids,10,"numIids");
        RequestCheckUtil::checkMaxLength($this->outerCode,12,"outerCode");
        RequestCheckUtil::checkMaxListSize($this->trackIids,10,"trackIids");
    }
}