<?php

/**
 * Weixin 第三方登录Oauth
 *
 * @package WeixinOAuth
 */
class WechatOAuth extends OAuth2 {

    /**
     * requestCodeURL 地址
     *
     * @access protected
     * @var string
     */
    protected $requestCodeURL = 'https://open.weixin.qq.com/connect/oauth2/authorize';

    /**
     * access_token的URL地址
     *
     * @access protected
     * @var string
     */
    protected $accessTokenURL = 'https://api.weixin.qq.com/sns/oauth2/access_token';

    /**
     * request_code的额外参数,URL查询字符串格式
     *
     * @access protected
     * @var string
     */
    protected $authorize = 'scope=snsapi_login';

    /**
     * API根路径URL
     *
     * @access protected
     * @var string
     */
    protected $apiBase = 'https://api.weixin.qq.com/';

    public function getRequestCodeURL() {
        //Oauth 标准参数
        $params = array(
            'appid' => $this->appKey,
            'redirect_uri' => $this->callBack,
            'response_type' => $this->responseType,
        );
        //在微信里
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {

            //$obj = new Wechat($this->appKey, $this->appSecret, $this->token);
            $params['appid'] = $this->appKey;
            $this->appKey = $this->appKey;
            $this->requestCodeURL = 'https://open.weixin.qq.com/connect/oauth2/authorize';
            $this->authorize = 'scope=snsapi_userinfo';
            // $this->authorize = 'scope=snsapi_base';
        }

        //获取额外参数
        if ($this->authorize) {
            parse_str($this->authorize, $_param);
            if (is_array($_param)) {
                $params = array_merge($params, $_param);
            } else {
                throw new Exception('authorize配置不正确！');
            }
        }
        return $this->requestCodeURL . '?' . http_build_query($params) . "&state=1#wechat_redirect";
    }

    /**
     * 组装接口调用参数 并调用接口
     *
     * @access protected
     * @param mixed $api 第三方开方的API
     * @param string $param  请求参数
     * @param string $method 请求的方式 get/post
     * @param bool $multi
     * @return mixed
     */
    public function call($api, $param = '', $method = 'GET', $multi = false) {
        $params = array(
            'access_token' => $this->token['access_token'],
            'openid' => $this->openid(),
            'lang' => 'zh_CN'
        );

        $data = $this->http($this->url($api), $this->param($params, $param), $method);
        return json_decode($data, true);
    }

    public function getAccessToken($code, $extend = null) {
        $params = array(
            'appid' => $this->appKey,
            'secret' => $this->appSecret,
            'grant_type' => $this->grantType,
            'code' => $code
        );
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
            //$obj = new Wechat($this->appKey, $this->appSecret, $this->token);
            $params['appid'] = $this->appKey;
            $params['secret'] = $this->appSecret;
        }
        $data = $this->http($this->accessTokenURL, $params, 'post');
        $this->token = $this->parsetoken($data, $extend);
        return $this->token;
    }

    /**
     * 解析access_token方法请求后的返回值
     *
     * @access protected
     * @param mixed $result token返回值
     * @param mixed $extend 扩展参数
     * @return array
     */
    protected function parsetoken($result, $extend) {
        $data = JSON::decode($result);
        if (isset($data['access_token']) && $data['access_token'] && $data['expires_in']) {
            $this->token = $data;
            $data['openid'] = $this->openid();
            return $data;
        } else {
            header("Location:/");
            exit;
            //throw new Exception("获取微信 ACCESS_token 出错：{$result}");
        }
    }

    /**
     *  用户的openID
     *
     * @access public
     * @return string
     */
    public function openid() {
        $data = $this->token;
        if (isset($data['openid']))
            return $data['openid'];
        elseif ($data['access_token']) {
            $data = $this->http($this->url('oauth2.0/me'), array('access_token' => $data['access_token']));
            $data = json_decode(trim(substr($data, 9), " );\n"), true);
            if (isset($data['openid']))
                return $data['openid'];
            else
                throw new Exception("获取用户openid出错：{$data['error_description']}");
        } else {
            throw new Exception('没有获取到openid！');
        }
    }

    /**
     * 获取用户信息
     *
     * @access public
     * @return array
     */
    public function getUserInfo() {
        $data = $this->call('sns/userinfo');
        $userInfo = array();
        if (!isset($data['ret']) || $data['ret'] == 0) {
            $userInfo['type'] = 'Wechat';
            $userInfo['name'] = $data['nickname'];
            $userInfo['open_name'] = $data['nickname'];
            $userInfo['head'] = $data['headimgurl'];
            return $userInfo;
        } else {
            throw_exception("获取微信用户信息失败：{$data['msg']}");
        }
    }

    public function getUserInfos() {
        $data = $this->call('sns/userinfo');
        $userInfo = array();
        if (!isset($data['ret']) || $data['ret'] == 0) {
            $userInfo['type'] = 'Wechat';
            $userInfo['name'] = $data['nickname'];
            $userInfo['open_name'] = $data['nickname'];
            $userInfo['head'] = $data['headimgurl'];
            $userInfo['unionid'] = $data['unionid'];
            return $userInfo;
        } else {
            throw_exception("获取微信用户信息失败：{$data['msg']}");
        }
    }

    public function getCode($id) {
        $params = array(
            'appid' => $this->appKey,
            'redirect_uri' => "http://www.ymlypt.com/travel/pay/id/{$id}",
            'response_type' => "code",
            'scope' => "snsapi_userinfo"
        );
        
        $url = "https://open.weixin.qq.com/connect/oauth2/authorize".'?' . http_build_query($params) . "&state=1#wechat_redirect";
        // $ret = file_get_contents($url);
        // var_dump($ret);die;
        // $result = json_decode($ret,true);

        return $url;
    }

    public function getCodes($redirect_uri) {
        $params = array(
            'appid' => $this->appKey,
            'redirect_uri' => $redirect_uri,
            'response_type' => "code",
            'scope' => "snsapi_userinfo"
        );
        
        $url = "https://open.weixin.qq.com/connect/oauth2/authorize".'?' . http_build_query($params) . "&state=1#wechat_redirect";
        // $ret = file_get_contents($url);
        // var_dump($ret);die;
        // $result = json_decode($ret,true);

        return $url;
    }

    public function getOpenid($code) {
       $params = array(
            'appid' => $this->appKey,
            'secret' => $this->appSecret,
            'grant_type' => $this->grantType,
            'code' => $code
        );
        $data = $this->http($this->accessTokenURL, $params, 'post');
        $data = JSON::decode($data);
        if (isset($data['openid'])) {
            return $data['openid'];
        } else {
            throw new Exception("获取用户openid出错：{$data['error_description']}");
        }
    }

    // public function getUserInfos($access_token,$openid){
    //   $userinfo = array();  
    //   $url = "https://api.weixin.qq.com/sns/userinfo?access_token=".$access_token."&openid=".$openid."&lang=zh_CN";
    //   $result = json_decode(file_get_contents($url),true);
      
    //   if($result){
    //     $userinfo['open_name']=$result['nickname']; 
    //     $userinfo['head']=$result['headimgurl']; 
    //     $userinfo['type'] = 'Weixin';
    //     $userinfo['name'] = $userinfo['open_name'];
    //     return $userinfo;
    //   }else {
    //         throw_exception("获取微信用户信息失败!");
    //     }    
    // }

}
