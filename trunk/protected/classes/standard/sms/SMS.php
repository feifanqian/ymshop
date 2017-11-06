<?php

class SMS extends ClassConfig {

    /**
     *
     * @var SMS 
     */
    private static $ins = null;
    public $errordict = array(
        '405' => '请求参数中的appkey为空',
        "406" => '非法的appkey',
        "456" => '请求参数中的手机号码或者国家代码为空',
        "457" => '手机号码格式错误',
        "458" => '手机号码在黑名单中',
        "463" => '手机号码超出当天发送短信的限额',
        "467" => '请求校验验证码频繁（5分钟校验超过3次）',
        "468" => '用户提交校验的验证码错误',
        "469" => '没有打开发送Http-api的开关',
        "470" => '账户短信余额不足',
        "471" => '请求ip和绑定ip不符',
        "477" => '当前手机号码在SMSSDK平台内每天最多可发送短信10条，包括客户端发送和WebApi发送',
        "478" => '当前手机号码在当前应用下12小时内最多可发送文本验证码5条.',
    );

    public static function getInstance() {
        if (!(self::$ins instanceof self)) {
            self::$ins = new self();
        }
        return self::$ins;
    }

    public static function config() {
        return array(
            array(
                'caption' => 'appKey',
                'field' => 'appKey',
            ),
            array(
                'caption' => '模板ID',
                'field' => 'templateCode',
            ),
        );
    }

    /**
     * 发送验证码
     * @param int $mobile 手机号
     * @param int $code 验证码
     * @return array
     */
    public function sendCode($mobile, $code) {
        $params = array(
            'appKey' => $this->config['appKey'],
            'templateCode' => $this->config['templateCode'],
            'zone' => '86',
            'phone' => $mobile,
            'AppName'=>"圆梦购物网",
            'code' => $code,
        );

        $ret = $this->postRequest('https://webapi.sms.mob.com/custom/msg', $params);
        $json = json_decode($ret, TRUE);
        if (isset($json['status']) && $json['status'] == 200) {
            $time = time();
            $mobile_model = new Model('mobile_code');
            $mobile_model->data(array('mobile' => $mobile, 'code' => $code, 'send_time' => $time))->insert();
            return array('status' => 'success', 'message' => '发送成功');
        } else {
            return array('status' => 'fail', 'message' => isset($json['status']) && isset($this->errordict[$json['status']]) ? $this->errordict[$json['status']] : '发送失败');
        }
    }

    /**
     * 校验验证码
     * @param int $mobile
     * @param int $code
     * @return array
     */
    public function checkCode($mobile, $code) {
        $mobile_model = new Model('mobile_code');
        $time = time() - 120;
        $obj = $mobile_model->where("send_time > $time and mobile ='" . $mobile . "'")->find();
        if ($obj && $code == $obj['code']) {
            return array('status' => 'success', 'message' => '验证成功');
        } else {
            return array('status' => 'fail', 'message' => '验证失败');
        }
    }

    public function flushCode($mobile) {
        $mobile_model = new Model('mobile_code');
        $mobile_model->where("mobile='{$mobile}'")->delete();
        return array('status' => 'success', 'message' => '验证成功');
    }

    public function postRequest($api, array $params = array(), $timeout = 30) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api);
        // 以返回的形式接收信息
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // 设置为POST方式
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        // 不验证https证书
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
            'Accept: application/json',
        ));
        // 发送数据
        $response = curl_exec($ch);
        // 不要忘记释放资源
        curl_close($ch);
        return $response;
    }

}
