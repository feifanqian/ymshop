<?php

/**
 * 
 * 秒到支付API异常类
 * @author widyhu
 *
 */
class MdPayException extends Exception {

    public function errorMessage() {
        return $this->getMessage();
    }

}
