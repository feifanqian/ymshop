<?php
/**
 * 内联action
 * @class InlineAction
 */
class InlineAction extends BaseAction
{
	//Action运行入口
	public function run()
	{
		$controller=$this->getController();
		$methodName=$this->getId();
		$controller->$methodName();
	}
}