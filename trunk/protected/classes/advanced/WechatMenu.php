<?php

class WechatMenu {

    private $appid;
    private $appsecret;
    private $token;

    public function __construct($appid, $appsecret, $token) {
        $this->appid = $appid;
        $this->appsecret = $appsecret;
        $this->token = $token;
    }

    /*
     * 获得access_token
     */

    public function getAccessToken() {
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$this->appid&secret=$this->appsecret";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        $jsoninfo = json_decode($output, true);
        $access_token = $jsoninfo["access_token"];
        return $access_token;
    }

    private function https_request($url, $data = null) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }

    /*
     * 获得thumb_media_id
     */

    public function getThumbMediaId() {
        $access_token = $this->getAccessToken();
        $url = "http://file.api.weixin.qq.com/cgi-bin/media/upload?access_token=$access_token&type=thumb";
        $filepath = './thumb.jpg';
        $data = array("media" => "@" . $filepath);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $thumb_media_id = curl_exec($ch);
        curl_close($ch);
        return $thumb_media_id;
    }

    /*
     * 创建菜单
     */

    public function commitMenu($menu) {
        $url = "https://api.weixin.qq.com/cgi-bin/menu/create?access_token=" . $this->getAccessToken();
        $result = $this->https_request($url, $menu);
        $result = json_decode($result, TRUE);
        if ($result && $result['errcode'] == 0) {
            $info = array('status' => 'success', 'msg' => '同步成功！如果未刷新,请取消重新关注!');
        } else {
            $info = array('status' => 'error', 'msg' => $result['errmsg']);
        }
        return $info;
    }

    /*
     * 查询菜单
     */

    public function getMenu() {
        $url = "https://api.weixin.qq.com/cgi-bin/menu/get?access_token=" . $this->getAccessToken();
        $result = $this->https_request($url);
        return $result;
    }

    /*
     * 删除菜单
     */

    public function delMenu() {
        $url = "https://api.weixin.qq.com/cgi-bin/menu/delete?access_token=" . $this->getAccessToken();
        $result = $this->https_request($url);
        return $result;
    }

    /*
     * 日志记录
     */

    private function logger($log_content) {
        if (isset($_SERVER['HTTP_APPNAME'])) {   //SAE
            sae_set_display_errors(false);
            sae_debug($log_content);
            sae_set_display_errors(true);
        } else if ($_SERVER['REMOTE_ADDR'] != "127.0.0.1") { //LOCAL
            $max_size = 10000;
            $log_filename = "log.xml";
            if (file_exists($log_filename) and ( abs(filesize($log_filename)) > $max_size)) {
                unlink($log_filename);
            }
            file_put_contents($log_filename, date('H:i:s') . " " . $log_content . "\r\n", FILE_APPEND);
        }
    }

}
