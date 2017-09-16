<?php

class WechatApi extends Wechat {

    /**
     * 初始化
     * @param string $appid
     * @param string $appsecret
     * @param token $token
     * @param boolean $debug
     */
    public function __construct($appid, $appsecret, $token, $debug = FALSE) {
        parent::__construct($appid, $appsecret, $token, $debug);
    }

    /**
     * 用户关注时触发，用于子类重写
     *
     * @return void
     */
    protected function onSubscribe() {
        if (substr($this->getRequest('eventkey'), 0, 7) == 'qrscene') {
            $this->onScan();
        }
        $items = array();
        $items[] = new NewsResponseItem("欢迎光临圆梦商城", "", "http://img.buy-d.cn/data/uploads/2016/09/07/b4f135e20157b7928bf8c892500f8f3c.png", "http://www.ymlypt.com/");
        $items[] = new NewsResponseItem("柚皮王", "", Common::thumb("data/uploads/2016/08/06/ee00da59407af68dbabe51a78ffc15fe.jpg", 120, 120), "http://www.ymlypt.com/?con=index&act=search&tiny_token_=k2earyxvl9ohpz8gl7wyhba3bzidqm6j&keyword=%E6%9F%9A%E7%9A%AE%E7%8E%8B");
        $items[] = new NewsResponseItem("益天下", "", Common::thumb("data/uploads/2016/06/24/76156d180d43556554faecba7e5d6564.jpg", 120, 120), "http://www.ymlypt.com/?con=index&act=search&tiny_token_=yk0bw5y2ndlmkv8twhjxog5edmnd3hci&keyword=%E7%9B%8A%E5%A4%A9%E4%B8%8B");
        $this->responseNews($items);
        $this->responseText("你可以直接发送关键字,搜索你想要的商品,赶快试试吧");
    }

    /**
     * 用户取消关注时触发，用于子类重写
     *
     * @return void
     */
    protected function onUnsubscribe() {
        $items = array();
        $items[] = new NewsResponseItem("再见", "这里是一段描述信息", "http://img.buy-d.cn/data/uploads/2016/06/16/1f0e4218cefb1eb78a54a9e565f66464.jpg", "http://www.ymlypt.com/");
        $this->responseNews($items);
    }

    /**
     * 收到文本消息时触发，用于子类重写
     *
     * @return void
     */
    protected function onText() {
        $keyword = $this->getRequest('content');
        if ($keyword != null) {
            $keyword = urldecode($keyword);
            $keyword = Filter::text($keyword);
            $keyword = Filter::commonChar($keyword);
        }
        $where = "name like '%$keyword%'";
        $model = new Model();
        $goods_model = $model->table("goods as go")->where($where)->fields("id,name,subtitle,sell_price,img")->order("sort desc")->limit("0,6");
        $goods = $goods_model->where($where)->findAll();
        $list = array();
        foreach ($goods as $k => $v) {
            if ($k == 0) {
                $imgurl = Url::fullUrlFormat('@' . Common::thumb($v['img'], 360, 220));
            } else {
                $imgurl = Url::fullUrlFormat('@' . Common::thumb($v['img'], 220, 220));
            }
            $list[] = new NewsResponseItem($v['name'], $v['subtitle'], $imgurl, Url::fullUrlFormat('/index/product/id/' . $v['id']));
        }
        if ($list) {
            $this->responseNews($list);
        } else {
            $this->responseText("未找到与「{$keyword}」相关的商品");
        }
    }

    /**
     * 收到图片消息时触发，用于子类重写
     *
     * @return void
     */
    protected function onImage() {
        
    }

    /**
     * 收到地理位置消息时触发，用于子类重写
     *
     * @return void
     */
    protected function onLocation() {
        
    }

    /**
     * 收到链接消息时触发，用于子类重写
     *
     * @return void
     */
    protected function onLink() {
        
    }

    /**
     * 收到自定义菜单消息时触发，用于子类重写
     *
     * @return void
     */
    protected function onClick() {
        $model=new Model();
        $menu=$model->table('wx_public')->fields('menus')->where('id=6')->find();
        $s=json_decode($menu['menus'],true);
        $key=$s['button'][2]['sub_button'][3]['key'];
        $res = $model->table("wx_response")->where("event_key='{$key}'")->find();
        $st=$res['content'];
        $str=unserialize($st);
        $this->responseText('{$str}');
    }

    /**
     * 收到地理位置事件消息时触发，用于子类重写
     *
     * @return void
     */
    protected function onEventLocation() {
        
    }

    /**
     * 收到语音消息时触发，用于子类重写
     *
     * @return void
     */
    protected function onVoice() {
        
    }

    /**
     * 扫描二维码时触发，用于子类重写
     *
     * @return void
     */
    protected function onScan() {
        $msgtype = $this->getRequest('msgtype');
        $eventkey = $this->getRequest('eventkey');
        $tousername = $this->getRequest('tousername');
        $fromusername = $this->getRequest('fromusername');
        if ($eventkey) {
            $is_first = substr($eventkey, 0, 7) == "qrscene" ? true : false;//判断是第一次关注
            $eventkey = substr($eventkey, 0, 7) == "qrscene" ? substr($eventkey, 8) : $eventkey;//获取真正的key，不管是关注时的还是其他
            if(is_numeric($eventkey)){
                $district_qrcodeinfo = new Model('district_qrcodeinfo as dq');
                $result = $district_qrcodeinfo->join("left join goods as g on dq.goods_id = g.id")->fields("dq.*,g.name,g.subtitle,g.img")->where("dq.id=$eventkey and dq.status=0")->find();
                if(empty($result)){
                     $this->responseText("对不起，您要查看的信息不存在或已经过期了！");
                }else{
                     $district_qrcodeinfo->data(array('visit_count'=>$result['visit_count']+1))->where('id='.$result['id'])->update();
                     $news = array();
                     if($is_first){
                        $news[] = new NewsResponseItem("欢迎光临圆梦商城", "", "http://img.buy-d.cn/data/uploads/2016/09/07/b4f135e20157b7928bf8c892500f8f3c.png", "http://www.ymlypt.com/"); 
                        $news[]=new NewsResponseItem($result['name'],$result['subtitle'],Url::fullUrlFormat('@' . Common::thumb($result['img'], 220, 220)),Url::fullUrlFormat('/index/product/id/' . $result['goods_id']."/flag/".$result['id']));
                     }else{
                         $news[]=new NewsResponseItem($result['name'],$result['subtitle'],Url::fullUrlFormat('@'.$result['img'])."!/both/360x200/force/true/fxfn/360x200",Url::fullUrlFormat('/index/product/id/' . $result['goods_id']."/flag/".$result['id']));
                     }
                     $this->responseNews($news);
                }
            }else{//扫描用户邀请二维码
                //已经授权的不再做为下线
                //$model = new Model("oauth_user");
                //$one = $model->where("oauth_type='wechat' AND open_id='{$fromusername}'")->find();
                //首次关注时 场景值：qrscene_invite-{uid}
                //关注以后的 场景值：invite-{uid}
                $user_id = substr($eventkey, 0, 6) == "invite" ? substr($eventkey, 7) : NULL;
                if($user_id){
                    $model = new Model("invite_wechat");
                    //邀请以最后一个扫码的为准
                    $one = $model->where("invite_openid='{$fromusername}'")->find();
                    if ($one) {
                        if ($one['openid'] != $tousername) {
                            $model->data(array('user_id' => $user_id, 'openid' => $tousername))->where("invite_openid='{$fromusername}'")->update();
                        }
                    } else {
                        $model->data(array('user_id' => $user_id, 'openid' => $tousername, 'invite_openid' => $fromusername, 'createtime' => time()))->insert();
                    }
                }
           }
        }
    }

    /**
     * 收到未知类型消息时触发，用于子类重写
     *
     * @return void
     */
    protected function onUnknown() {
        
    }

}
