<?php

class WeixinController extends Controller {

    //当前Key
    private $currentKey;
    //当前公众号ID
    private $publicId;
    //当前token
    private $token;

    public function init() {
        
    }

    function __call($func, $args = null) {
        $token = Filter::sql($func);
        $wx_model = new Model('wx_public');
        $wx_obj = $wx_model->where("token='$token'")->find();
        if ($wx_obj) {
            $this->publicId = $wx_obj['id'];
            $this->token = $wx_obj['token'];
            $wechatApi = new WechatApi($wx_obj['app_id'], $wx_obj['app_secret'], $wx_obj['token'], TRUE);
            $msg = $wechatApi->run();
            exit;
//            $echostr = Req::args('echostr');
//            if ($echostr) {
//                $result = $wechatApi->checkSign();
//                echo $result ? $echostr : 'fail';
//                exit;
//            } else {
            //$msg = $wechatApi->getMessage();
//                Debug::d($msg);
//                if (isset($msg->fromUserName)) {
//                    $response = $this->event();
//                    if ($response != null)
//                        $wechatApi->response($response);
//                    exit;
//                } else {
//                    Tiny::Msg($this, 404);
//                }
//            }
        }
    }

    private function event() {
        $object = $wechatApi->currentMessage();
        $response = new stdclass();
        $open_id = $object->fromUserName;

        if ($object->msgType == 'event') {
            switch ($object->event) {
                case "scancode_waitmsg":
                case "scancode_push":
                case "pic_sysphoto":
                case "pic_photo_or_album":
                case "pic_weixin":
                case "location_select":
                case "click":
                    $key = $object->eventKey;
                    break;
                default:
                    $key = $object->event;
                    break;
            }
            $model = new Model('wx_response');
            if ($key == 'subscribe' || $key == 'unsubscribe') {
                $obj = $model->where("event_key='" . $this->token . '-' . $key . "' or event_key='$key'")->find();
            } else {
                $obj = $model->where("event_key='$key'")->find();
            }

            if ($obj) {
                $content = unserialize($obj['content']);
                if ($obj['type'] == 'app') {
                    $weixinService = new WeixinService($wechatApi);
                    $response = $weixinService->response($content);

                    $context_model = new Model('wx_context');
                    $context = $context_model->where('public_id =' . $this->publicId . " and open_id='$open_id'")->find();
                    if ($context) {
                        $context_model->data(array('current_key' => $key, 'command' => ''))->where('id=' . $context['id'])->update();
                    } else {
                        $context_model->data(array('current_key' => $key, 'command' => '', 'public_id' => $this->publicId, 'open_id' => $open_id))->insert();
                    }
                } else {
                    $response->msgType = $obj['type'];
                    foreach ($content as $key => $value) {
                        $response->$key = $value;
                    }
                }
            } else {
                return null;
            }
        } else {
            $context_model = new Model('wx_context');
            $context = $context_model->where("public_id = {$this->publicId} and open_id='$open_id'")->find();
            if ($context) {
                $this->currentKey = $context['current_key'];
            } else {
                $this->currentKey = null;
            }
            if ($this->currentKey != null) {
                $model = new Model('wx_response');
                $obj = $model->where("event_key='$this->currentKey'")->find();
                if ($obj) {
                    $content = unserialize($obj['content']);
                    $weixinService = new WeixinService($wechatApi);
                    $response = $weixinService->command($content, $context);
                }
            } else {
                $weixinService = new WeixinService($wechatApi);
                $wechatApi->response($weixinService->searchGoods());
            }
        }
        return $response;
    }

}
