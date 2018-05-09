<?php
/**
 * TOP API: taobao.tbk.item.detail.get request
 *
 * @author auto create
 * @since 1.0, 2016.07.25
 */
class TbkItemCouponGetRequest
{
    /**
     * 链接形式：1：PC，2：无线，默认：１
     **/
    private $platform;
    
	/**
	 * 商品ID串，用','分割，从taobao.tbk.item.get接口获取num_iid字段,最大40个
	 **/
	private $numIids;
	
	/**
	 * 自定义输入串，英文和数字组成，长度不能大于12个字符，区分不同的推广渠道
	 **/
	private $pid;
	
	private $apiParas = array();
	
	public function setPlatform($platform)
	{
		$this->platform = $platform;
		$this->apiParas["platform"] = $platform;
	}

	public function getPlatform()
	{
		return $this->platform;
	}
	
	public function setNumIids($numIids)
	{
		$this->numIids = $numIids;
		$this->apiParas["num_iids"] = $numIids;
	}

	public function getNumIids()
	{
		return $this->numIids;
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

	public function getApiMethodName()
	{
		return "taobao.tbk.itemid.coupon.get";
	}
	
	public function getApiParas()
	{
		return $this->apiParas;
	}
	
	public function check()
	{
		RequestCheckUtil::checkNotNull($this->numIids,"numIids");
	    RequestCheckUtil::checkNotNull($this->pid,"pid");
	}
	
	public function putOtherTextParam($key, $value) {
		$this->apiParas[$key] = $value;
		$this->$key = $value;
	}
}
