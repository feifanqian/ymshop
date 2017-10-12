<?php

class Common {

    //检测表达式
    private static function check_condition($condition) {
        return !!preg_match('/^(\S+--\S+--\S+--[\S ]+__)*(\S+--\S+--\S+--[\S ]+)$/', $condition);
    }

    //字符串转条件
    public static function str2where($condition) {
        if (self::check_condition($condition)) {
            $condition = preg_replace('/^(and|or)/i', '', $condition);
            $condition = str_replace(array('--', '__'), array(' ', "' "), $condition);
            $old_char = array(' ne ', ' eq ', ' lt ', ' gt ', ' le ', ' ge ', ' ct ', ' nct ');
            $new_char = array(" != '", " = '", " < '", " > '", " <= '", " >= '", " like '%", " not like '%");
            $condition = str_replace($old_char, $new_char, $condition);
            $condition = preg_replace("/\s+(like\s+'[^']+)('|$)/i", " $1%$2", $condition);
            if ($condition != '')
                $condition .= "'";
            return $condition;
        }
        return null;
    }

    //生成订单编号
    public static function createOrderNo() {
        return date('YmdHis') . rand(1000, 9999);
    }

    //发放代金券
    public static function paymentVoucher($voucherTemplate, $userID = null) {
        $model = new Model("voucher");
        do {
            $account = strtoupper(CHash::random(10, 'char'));
            $password = strtoupper(CHash::random(10, 'char'));
            $obj = $model->where("account = '$account'")->find();
        } while ($obj);
        $start_time = date('Y-m-d H:i:s');
        $end_time = date('Y-m-d H:i:s', strtotime('+' . $voucherTemplate['valid_days'] . ' days'));
        $model->data(array('account' => $account, 'password' => $password, 'name' => $voucherTemplate['name'], 'value' => $voucherTemplate['value'], 'start_time' => $start_time, 'end_time' => $end_time, 'money' => $voucherTemplate['money'], 'is_send' => 1, 'user_id' => $userID))->insert();
    }

    //分类的树状化数组
    public static function treeArray($datas) {
        $result = array();
        $I = array();
        foreach ($datas as $val) {
            $sort = intval($val['sort']);
            if ($val['parent_id'] == 0) {
                if (isset($result[$val['sort']]))
                    $i = count($result[$val['sort']]);
                else
                    $i = 0;
                $result[$sort][$i] = $val;
                $I[$val['id']] = &$result[$sort][$i];
                krsort($result);
            } else {
                if (isset($I[$val['parent_id']]['child'][$sort]))
                    $i = count($I[$val['parent_id']]['child'][$sort]);
                else
                    $i = 0;
                $I[$val['parent_id']]['child'][$sort][$i] = $val;
                krsort($I[$val['parent_id']]['child']);
                $I[$val['id']] = &$I[$val['parent_id']]['child'][$sort][$i];
            }
        }
        return self::parseTree($result);
    }

    //递归树状数组
    public static function parseTree($result, &$tree = array()) {
        foreach ($result as $items) {
            if (is_array($items)) {
                foreach ($items as $item) {
                    $tem = $item;
                    if (isset($item['child']))
                        unset($tem['child']);
                    $tree[] = $tem;
                    if (isset($item['child']))
                        self::parseTree($item['child'], $tree);
                }
            }
        }
        return $tree;
    }

    //价格区间计算
    static function priceRange($range) {
        $d0 = intval($range['min'], -2);
        $d1 = intval(($range['min'] + $range['avg']) / 2);
        $d2 = intval($range['avg']);
        $d3 = intval(($range['max'] + $range['avg']) / 2);
        $d4 = intval($range['max']);

        if ($d4 > $d3 && $d3 > $d2 && $d2 > $d1 && $d1 > $d0) {
            $d1 = self::formatInt($d1);
            $d2 = self::formatInt($d2);
            $d3 = self::formatInt($d3);
            $d4 = self::formatInt($d4);
            $price_range[0] = '0-' . $d1;
            if ($d2 - $d1 > 2)
                $price_range[1] = $d1 . '-' . ($d2 - 1);
            else
                $price_range[1] = $d1 . '-' . $d2;
            if ($d3 - $d2 > 2)
                $price_range[2] = $d2 . '-' . ($d3 - 1);
            else
                $price_range[2] = $d2 . '-' . $d3;
            if ($d4 - $d3 > 2)
                $price_range[3] = $d3 . '-' . ($d4 - 1);
            else
                $price_range[3] = $d3 . '-' . $d4;
            $price_range[4] = "$d4";
            return $price_range;
        }else {
            if ($d2 != 0) {
                $d2 = self::formatInt($d2);
                if ($d2 > 1)
                    return array(0 => ('0-' . ($d2 - 1)), 1 => "$d2");
                else
                    return array(0 => ('0-' . ($d2)), 1 => "$d2");
            }else if ($range['min'] != 0) {
                return array(0 => ('0-' . ($range['min'])), 1 => "$range[min]");
            } else
                return array();
        }
    }

    static function formatInt($value) {
        $len = strlen($value);
        switch ($len) {
            case 1:
                break;
            case 2:
                $value = round($value, -1);
                break;
            case 3:
            case 4:
                $value = round($value, -2);
                break;
            default:
                $value = round($value, 2 - $len);
                break;
        }
        return $value;
    }

    //thumb
    static function thumb($image_url, $w = 200, $h = 200, $type = "fwfh") {
        //外链图片
        if (preg_match('@http://@i', $image_url)) {
            if (stripos($image_url, "!/{$type}/") === false) {
                $image_url = $image_url . "!/{$type}/{$w}x{$h}";
            }
            return $image_url;
        }
        //加后缀
        if (substr(ltrim($image_url, "/"), 0, 12) == "data/uploads") {
            return Url::urlFormat('@' . $image_url . "!/{$type}/{$w}x{$h}");
        }
        $access_image_size = array(
            '220_220' => true,
            '100_100' => true,
            '367_367' => true
        );
        $theme_config = Tiny::app()->getTheme()->getConfigInfo();
        if ($theme_config != null && isset($theme_config['access_image_size'])) {
            $access_image_size = array_merge($access_image_size, $theme_config['access_image_size']);
        }
        if (func_num_args() == 2)
            $h = $w;
        if ($image_url == '')
            return '';

        if (isset($access_image_size[$w . '_' . $h])) {

            $ext = strtolower(strrchr($image_url, '.'));
            $result_url = $image_url . '__' . $w . '_' . $h . $ext;
            if (!file_exists(APP_ROOT . $result_url)) {
                $image = new Image();
                $image->suffix = 'f_w_h';
                $result_url = $image->thumb(APP_ROOT . $image_url, $w, $h);
                $result_url = str_replace(APP_ROOT, '', $result_url);
            }
            return Url::urlFormat('@' . $result_url);
        } else {
            return Url::urlFormat('@' . $image_url);
        }
    }

    static function spec($spec) {
        $spec = is_array($spec) ? $spec : unserialize($spec);
        $speclist = array();
        if (is_array($spec)) {
            foreach ($spec as $sp) {
                $speclist[] = $sp['value'][2];
            }
        }
        return $speclist;
    }

    //自动登录时的用户信息
    static function autoLoginUserInfo() {
        $cookie = new Cookie();
        $cookie->setSafeCode(Tiny::app()->getSafeCode());
        $autologin = $cookie->get('autologin');
        $obj = null;
        if ($autologin != null) {
            $account = Filter::sql($autologin['account']);
            $password = $autologin['password'];
            $model = new Model("user as us");
            $obj = $model->join("left join customer as cu on us.id = cu.user_id")->fields("us.*,cu.group_id,cu.user_id,cu.login_time,cu.mobile")->where("us.email='$account' or us.name='$account' or cu.mobile='$account'")->find();
            if ($obj['password'] != $password) {
                $obj = null;
            }
        }
        return $obj;
    }

    //取得支付方式信息
    static function getPaymentInfo($id) {
        $model = new Model('payment as pa');
        $payment = $model->join('left join pay_plugin as pp on pa.plugin_id = pp.id')->where("pa.id = " . $id)->find();
        return $payment;
    }

    //判断是否在微信中
    static function checkInWechat() {
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
            return TRUE;
        } else {
            return FALSE;
        }
    }
    
    //获取快递信息
    static function getExpress($com,$num){
        $post_data = array();
        $post_data["customer"] = '4AAEB1391202A6CBECCD643E35DD17E6';
        $key= 'qxzjfZVP3082' ;
        $post_data["param"] = '{"com":"'.$com.'","num":"'.$num.'"}';

        $url='http://poll.kuaidi100.com/poll/query.do';
        $post_data["sign"] = md5($post_data["param"].$key.$post_data["customer"]);
        $post_data["sign"] = strtoupper($post_data["sign"]);
        $o=""; 
        foreach ($post_data as $k=>$v)
        {
            $o.= "$k=".urlencode($v)."&";       //默认UTF-8编码格式
        }
        $post_data=substr($o,0,-1);
        $ch = curl_init();
    curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_URL,$url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        
    $result = curl_exec($ch);
        curl_close($ch);
    $data = str_replace("\&quot;",'"',$result );
    $data = json_decode($data,true);
        return $data;
    }
    
    //获取快递信息（已废弃）
    static function express($company, $number) {//废弃
        $ret = Http::curlGet("http://m.kuaidi100.com/autonumber/auto?num={$number}");
        $json = json_decode($ret, TRUE);
        $content = NULL;
        if (isset($json[0]['comCode'])) {
            $code = $json[0]['comCode'];
        } else {
            $config_path = APP_CODE_ROOT . 'config/express.php';
            $config = require($config_path);

            $code = "";
            foreach ($config as $k => $v) {
                if ($company == $v['code'] || $company == $v['shortname'] || stripos($v['companyname'], $company) !== FALSE || stripos($company, $v['shortname']) !== FALSE) {
                    $code = $v['code'];
                    break;
                }
            }
        }
        if (!$code) {
            $status = "fail";
        } else {
            print_r($code);
            print_r($number);
            //$ret = Http::curlGet("http://m.kuaidi100.com/query?type={$code}&postid={$number}&id=1&valicode=&temp=0." . CHash::random(16, 'int'));
            $ret = Http::curlGet("http://api.kuaidi100.com/api?com={$code}&nu={$number}&id=16dab18b1d0e6328&valicode=&temp=0." . CHash::random(16, 'int'));
            $json = json_decode($ret, TRUE);
            print_r($json);
            if (isset($json['message']) && $json['message'] == 'ok') {
                $status = 'success';
                $content = $json['data'];
            } else {
                $status = 'fail';
            }
        }
        $data = array(
            'status' => $status,
            'content' => $content
        );
        return $data;
    }
    
    //创建优惠券给新用户
    static function voucherCreateForNewUser($user_id){
        $id = 3;//新用户专享id
        $start_time = date("Y-m-d");
        $end_time = date("Y-m-d 23:59:59", strtotime("+90 days"));
        $model = new Model('voucher_template');
        $voucher_template = $model->where("id = $id")->find();
       if ($voucher_template) {
           while (true) {
                    $voucher_model = new Model('voucher');
                    $account = strtoupper(CHash::random(10, 'char'));
                    $password = strtoupper(CHash::random(10, 'char'));
                    $voucher_template['account'] = $account;
                    $voucher_template['password'] = $password;
                    $voucher_template['start_time'] = $start_time;
                    $voucher_template['end_time'] = $end_time;
                    $voucher_template['user_id'] = $user_id;
                    $voucher_template['is_send'] = 1;
                    $obj = $voucher_model->where("account = '$account'")->find();
                    if(empty($obj)){
                        unset($voucher_template['id'], $voucher_template['point']);
                        $id = $voucher_model->data($voucher_template)->insert();
                        if($id){
                            $oauth_info = new Model('oauth_user');
                            $v = $oauth_info->where("oauth_type='wechat' and user_id = {$user_id}")->find();
                            if(empty($v)){
                                exit();
                            }
                            $wechatcfg = $model->table("oauth")->where("class_name='WechatOAuth'")->find();
                            $wechat = new WechatMenu($wechatcfg['app_key'], $wechatcfg['app_secret'], '');
                            $token = $wechat->getAccessToken();
                            if($token==''){
                                echo date("Y-m-d H:i:s").":"."get access_token fail\r\n";die;
                            }
                            $v['open_name'] = $v['open_name']==""?"圆梦用户":$v['open_name'];
                            $params = array(
                                'touser'=>$v['open_id'],
                                'msgtype'=>'news',
                                'news'=>array(
                                    'articles'=>array('0'=>array(
                                        'title'=>'圆梦温馨提示',
                                        'description'=>"亲爱的{$v['open_name']},恭喜您获得新用户专享优惠券，下单时即可使用哦，立享满减优惠！赶快使用吧>>>",
                                        'url'=>'www.buy-d.cn',
                                        'picurl'=>'http://img.buy-d.cn/data/uploads/2016/12/19/eb9497e6ffc065ea583af0de429bfdc7.png'
                                    )
                                   )
                                )
                            );
                            Http::curlPost("https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token={$token}", json_encode($params,JSON_UNESCAPED_UNICODE));
                            return true;
                        }
                    }
                } 
           }
        }
     
    //格式化时间显示
    static function formatTimeToShow($dateTime){
            $record_time = strtotime($dateTime);
            $time = time() - $record_time;
            if ($time < 60*5){
                $str = '刚 刚';
            }elseif ($time < 60 * 10){
                $min = floor($time/60);
                $str = $min.'分钟';
            }elseif ($record_time < strtotime(date("Y-m-d 23:59:59"))&&$record_time > strtotime(date("Y-m-d 00:00:00"))){
                $str = '今天';
            }else {
               $weekarray=array("日","一","二","三","四","五","六");
               $str = '周 '.$weekarray[date('w',$record_time)];
            }
            return $str;
        }
    
    //格式化数据到echart中显示
    static function formatDataToShowInChart($start,$end,&$data,$group_by="day"){
                if(!isset($data[0]['time'])||!isset($data[0]['amount'])){
                   if($group_by=='hour'){
                       for($i=0;$i<=23;$i++){
                        $result[sprintf("%02d", $i).":00"]=0.00;
                      }
                   }else if($group_by=='day'){
                       $dt_start = strtotime($start);
                        $dt_end   = strtotime($end);
                        do { 
                            $result[date("m-d",$dt_start)]=0.00;
                        } while (($dt_start += 86400) <= $dt_end); 
                   }
                }else{
                $result = array();
                if($group_by=='hour'){
                    for($i=0;$i<=23;$i++){
                        $result[sprintf("%02d", $i).":00"]=0.00;
                    }
                    foreach ($data as $k=>$v){
                            $result[date('H:00',strtotime($v['time']))] +=$v['amount'];
                    }
                }else if($group_by=='day'){
                    $dt_start = strtotime($start);
                    $dt_end   = strtotime($end);
                    do { 
                        $result[date("m-d",$dt_start)]=0.00;
                    } while (($dt_start += 86400) <= $dt_end); 
                    foreach ($data as $k=>$v){
                        if(isset($result[date('m-d',strtotime($v['time']))])){
                            $result[date('m-d',strtotime($v['time']))] +=$v['amount'];
                        }
                    }
                }
            }
            $data =array();
            $data['x']=  array_keys($result);
            $data['y']=  array_values($result);
        }
   
    /*
     * 支付后自动审核通过小区申请，创建小区并分配分成
     */
    static  function  autoPassDistrictApply($apply_id){
        $model = new Model();
        $config_all = Config::getInstance();
        $set = $config_all->get('district_set');
        if(!isset($set['auto_pass'])||$set['auto_pass']==0||$set['auto_pass']==NULL){
            return false;
        }else{
              $apply_info = $model->table("district_apply")->where("id=$apply_id and status =0 and pay_status = 1")->find();
              if(empty($apply_info)){
                  return false;
              }
              $data['name'] = $apply_info['name'];
              $data['location'] = $apply_info['location'];
              $data['asset']=1000;
              $data['founder_id']=$apply_info['user_id'];
              $data['owner_id']=$apply_info['user_id'];
              $data['create_time']=date("Y-m-d H:i:s");
              $data['valid_period']=date("Y-m-d H:i:s",  strtotime("+3 years"));
              $data['linkman']=$apply_info['linkman'];
              $data['link_mobile']=$apply_info['linkmobile'];
              $data['valid_income']=$data['frezze_income']=$data['settled_income']=0.00;
              $data['status']=0;
              $data['invite_shop_id']= $apply_info['reference']==""? NULL :$apply_info['reference'];
              $isOk = $model->table('district_shop')->data($data)->insert();
              if($isOk){
                  if($data['invite_shop_id']!=""){
                      //获取分配比例
                      $config_all = Config::getInstance();
                      $set = $config_all->get('district_set');
                      if(isset($set['percentage2join_fee'])){
                          $rate = round($set['percentage2join_fee']/100,2);
                      }else{
                          $rate = 0.1;
                      }
                      if(isset($set['join_fee'])){
                          $fee = round($set['join_fee'],2);
                      }else{
                          $fee = 10000;
                      }
                      $income_amount = $fee*$rate;
                      Log::incomeLog($income_amount, 3, $data['invite_shop_id'], $apply_info['id'], 10);
                  }
                  $result = $model->table('district_apply')->where("id=$apply_id")->data(array('status'=>1))->update();
                  if($result){
                    $oauth_info = $model->table("oauth_user")->fields("open_id,open_name")->where("user_id=".$apply_info['user_id']." and oauth_type='wechat'")->find();
                    if(!empty($oauth_info)){
                        $wechatcfg = $model->table("oauth")->where("class_name='WechatOAuth'")->find();
                        $wechat = new WechatMenu($wechatcfg['app_key'], $wechatcfg['app_secret'], '');
                        $token = $wechat->getAccessToken();
                        $oauth_info['open_name'] = $oauth_info['open_name']==""?"圆梦用户":$oauth_info['open_name'];
                        $params = array(
                            'touser'=>$oauth_info['open_id'],
                            'msgtype'=>'news',
                            'news'=>array(
                                'articles'=>array('0'=>array(
                                    'title'=>'圆梦温馨提示',
                                    'description'=>"亲爱的{$oauth_info['open_name']},恭喜您，入驻专区申请通过了！快来看看吧>>>",
                                    'url'=>'www.buy-d.cn/district/district',
                                    'picurl'=>'http://img.buy-d.cn/data/uploads/2017/03/18/2e87d2ec1e5a600832482853c5c71a84.jpg'
                                )
                               )
                            )
                        );
                        Http::curlPost("https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token={$token}", json_encode($params,JSON_UNESCAPED_UNICODE));
                    }
                    return true;
                  }else{
                      return false;
                  } 
              }
        }
    }
    
   
     /*
      *获取消费统计金额 
      */
    static function getTotalAmount4Consumption($user_id){
         $model = new Model("order");
         $amount = $model->fields("SUM(`order_amount`) as amount")
                   ->where("user_id = $user_id and pay_status =1")
                   ->find();
         if(empty($amount)||!isset($amount['amount'])||$amount['amount']==NULL){
             return 0;
         }else{
             return $amount['amount'];
         }
     }
     /*
      * 获取推广员的销售业绩总额
      */
     static function getPromoteAchievementAmount($prmoter_id){
         $model = new Model("district_sales");
         $amount = $model->where("promoter_id = $prmoter_id and status =0")
                 ->fields("SUM(`amount`) as amount")
                 ->find();
         if(empty($amount)||!isset($amount['amount'])||$amount['amount']==NULL){
             return 0.00;
         }else{
             return (float) $amount['amount'];
         }
     }
     /*
      * 获取推广员的销售分成率
      */
     static function getPromoterSaleSplit($prmoter_id,$set){
         $amount = self::getPromoteAchievementAmount($prmoter_id);
         $rate = 10;
         if(!isset($set['level_line_1'])||!isset($set['level_line_2'])){
             return $rate;
         }else{
             if($amount<=$set['level_line_1']){
                 $rate = $set['percentage_lv1'];
             }else if($amount <=$set['level_line_2']){
                 $rate = $set['percentage_lv2'];
             }else if($amount >$set['level_line_2']){
                 $rate = $set['percentage_lv3'];
             }
             return $rate;
         }
     }
     /*
      * 获取官方推广员的推广积分
      */
     static function getOfficialPromoterPointCoin($user_id){
         $model = new Model();
         $promoter = Promoter::getPromoterInstance($user_id);
         if(is_object($promoter)){
             if($promoter->type==4){
                 $count = $model->table("pointcoin_log")->where("user_id=$user_id and type in (5,6,7,9)")->fields("SUM(`amount`) as amount")->find();
                 if(!isset($count['amount'])||$count['amount']==NULL){
                     $amount = 0.00;
                 }else{
                     $amount =$count['amount'];
                 }
                 return $amount;
             }else{
                 return 0.00;
             }
         }else{
             return 0.00;
         }
     }
     
     /*
      * 判断支付方式所属端
      */
     static function  getPayClientByPaymentID($payment_id){
         $model = new Model("payment");
         $result = $model->where("id=$payment_id")->fields("client_type")->find();
         $clent_type = "unknow";
         if($result){
             switch ($result['client_type']){
                 case 0 : $clent_type='pc';break;
                 case 1 : $clent_type='wap';break;
                 case 2 : $clent_type='weixin';break;
                 case 3 : $clent_type='android';break;
                 case 4 : $clent_type='ios';break;
                 default :break;
             }
         }
         return $clent_type;
     }
     
     //获取签到赠送积分数量 serial_day 为连续签到天数
     static function getSignInSendPointAmount($serial_day){
         $config = Config::getInstance();
         $set =$config->get('sign_in_set');
         if($set['type']==1){
             return $set['value'];
         }else if($set['type']==2){
             $rule = preg_replace("/\{serial_day\}/",$serial_day,$set['value']);
             $return =  eval("return $rule;");
             if(isset($set['max_sent'])){
                 if($set['max_sent']<=0){
                     return $return;
                 }else{
                     return $return-$set['max_sent']>0?$set['max_sent']:$return;
                 }
             }
         }
         return 10;
     }
     
     //获取用户指定月签到数据
     static function getSignInDataByUserID($year,$month,$user_id){
         $model  = new Model("sign_in");
         $days   = cal_days_in_month(CAL_GREGORIAN,$month,$year);
         
         //判断今天是否在该月中
         $now = time();
         $now_day = intval(date('d'));
         $ym_start_time = strtotime($year."-".$month."-"."1");
         $ym_end_time  =  strtotime($year."-".$month."-".$days);
         
         $return =array();
         if($now>$ym_end_time){
             for($i=1;$i<=$days;$i++){
                 $return[$i]['sign']="-1";
             }
         }else if($now>=$ym_start_time&&$now<$ym_end_time){
             for($i=1;$i<=$days;$i++){
                 if($i<$now_day){
                    $return[$i]['sign']="-1";
                 }else{
                     $return[$i]['sign']="0";
                 }
             }
         }else{
             for($i=1;$i<=$days;$i++){
                 $return[$i]['sign']="0";
             }
         }
         
         $sign_data = $model->where("date >= '$year-$month-01' and date <= '$year-$month-$days' and user_id=$user_id")->fields("date,send_point")->findAll();
         if($sign_data){
             foreach($sign_data as $v){
                 $day = intval(date("d",  strtotime($v['date'])));
                 $return[$day]['sign']=1;
                 $return[$day]['send_point']=$v['send_point'];
             }
             return $return;
         }else{
             return $return;
         }
     }
     
     //根据端获取支付方式
     static function getValidPayList(){
        $client_type = Chips::clientType();
        $client_type = ($client_type == "desktop") ? 0 : ($client_type == "wechat" ? 2 : 1);
        $model = new Model("payment as pa");
        $paytypelist = $model->fields("pa.*,pp.logo,pp.class_name")->join("left join pay_plugin as pp on pa.plugin_id = pp.id")
                        ->where("pa.status = 0 and pa.plugin_id not in(1,12,19,20) and pa.client_type = $client_type")->order("pa.sort desc")->findAll();
        return $paytypelist;
     }
     
     static function autoCreatePersonalShop($user_id , $goods_ids){
         $config = Config::getInstance();
         $set =$config->get('personal_shop_set');
         if($set&&isset($set['open'])&&$set['open']==1){
             if(in_array($set['goods_id'], $goods_ids)){
                $model = new Model();
                $isset = $model->table("personal_shop")->where("user_id=$user_id")->find();
                if($isset){
                    return false;
                }else{
                    $data['user_id']=$user_id;
                    $data['create_date']=date("Y-m-d H:i:s");
                    $result = $model->table("personal_shop")->data($data)->insert();
                    if($result){
                        return true;
                    }else{
                        return false;
                    }
                }
             }else{
                 return false;
             }
         }
    }
     
    static function getPersonalShopData($personal_shop_id){
        $model = new Model();
        $all_goods_num = $model->table("goods")->where("personal_shop_id = $personal_shop_id and is_online =0")->count();
        $all_sell_num = $model->table("order_goods as og")->join("left join order as o on og.order_id = o.id left join goods as g on og.goods_id =g.id")->where("g.personal_shop_id = $personal_shop_id and o.status in (3,4)")->fields("SUM(og.goods_nums) as all_sell_num")->find();
        $all_sell_num = $all_sell_num['all_sell_num']==NULL?0:$all_sell_num['all_sell_num'];
        return array("all_goods_num"=>$all_goods_num,"all_sell_num"=>$all_sell_num);
    }
    
    //绑定邀请关系
    static function buildInviteShip($inviter_id ,$new_user_id ,$way="wap"){
        // var_dump($new_user_id);die;
        if($inviter_id==$new_user_id){
            return array('status'=>'fail','msg'=>"inviter can't be youself");
        }
        $model  = new Model();
        $isset  = $model->table("user")->where("id={$inviter_id}")->find();
        $notset = $model->table('invite')->where("invite_user_id={$new_user_id}")->find();
        
        if($isset && empty($notset)){
            $inviter_info = self::getMyPromoteInfo($inviter_id);
            $result = $model->table("invite")->data(array('user_id'=>$inviter_id,'invite_user_id'=>$new_user_id,'from'=>$way,'district_id'=>$inviter_info['district_id'],'createtime'=>time()))->insert();
            // var_dump($result);die;
            if($result){
                return true;
            }else{
                return array('status'=>'fail','msg'=>'db error');
            }
        }else{
            return array('status'=>'fail','msg'=>'inviter is null or ship is builded');
        }
    }
    
    
     //为新用户赠送积分
     static function sendPointCoinToNewComsumer($user_id){
         $model = new Model("customer");
         $result = $model->where("user_id = $user_id")->data(array('point_coin'=>200))->update();
         if($result){
             Log::pointcoin_log(200, $user_id, '', '新用户积分奖励', 10);
             return TRUE;
         }else{
             return FALSE;
         }
     }
     
     //获取推广二维码flag
     static function getQrcodeFlag($goods_id,$user_id){
        $model = new Model();
        $goods_info = $model->table("goods")->where("id=$goods_id and is_online = 0")->fields('id,img')->find();
        if (empty($goods_info)) {
              return array('status' => 'fail', 'msg'=>"商品不存在",'msg_code' => 1000);
        }
        $info = $model->table('promote_qrcode')->where("user_id ={$user_id} and goods_id = {$goods_id}")->fields('id')->find();
        if (empty($info)) {
            $id = $model->table('promote_qrcode')->data(array('user_id' => $user_id,  'goods_id' => $goods_id, 'scan_times' => 0, 'sell_count' => 0, 'create_date' => date("Y-m-d H:i:s"),'update_date' => date("Y-m-d H:i:s")))->insert();
        } else {
            $id = $info['id'];
            $model->table("promote_qrcode")->where("user_id ={$user_id} and goods_id = {$goods_id}")->data(array('update_date'=>date("Y-m-d H:i:s")))->update();
        }
        $url = Url::fullUrlFormat("/index/product/id/$goods_id/flag/" . $id);
        return array('status' => 'success', 'flag' => $id,'url'=>$url, 'goods_id' => $goods_id);
     }
     
     //获取我的推广信息，包括上级邀请者及所属小区
     static function getMyPromoteInfo($user_id){
         $model = new Model();
         $is_district_promoter = $model->table('district_promoter')->where("user_id=$user_id")->fields("type,hirer_id")->find();
         $role_type = $is_district_promoter ? 2:1;
         $is_district_hirer = $model->table("district_shop")->where("owner_id=$user_id")->order("id asc")->find();
         $role_type = $is_district_hirer ? 3:$role_type;
         $result = array();
         $district_id = 1;
         if($role_type==1){
             $inviter_info = $model->table("invite")->where("invite_user_id=".$user_id)->find();
             if($inviter_info){//有邀请关系
                $inviter_promoter_info = $model->table('district_promoter')->where("user_id=".$inviter_info['user_id'])->fields("type,hirer_id")->find();
                $inviter_role = $inviter_promoter_info ? 2:1;
                $inviter_hirer_info = $model->table("district_shop")->where("owner_id=".$inviter_info['user_id'])->find();
                $inviter_role = $inviter_hirer_info ? 3:$role_type;
                $result['inviter_user_id']=$inviter_info['user_id'];//邀请者的id、
                $result['inviter_role']=$inviter_role;//邀请者的身份
                $district_id= $inviter_info['district_id'];//普通会员所属小区以邀请关系中的小区为准，没有邀请关系固定为官方的。
             }
         }else{
            $district_id = $role_type==2 ? $is_district_promoter['hirer_id']:$is_district_hirer['id'];
         }
         $result['district_id']=$district_id;
         $result['my_role']=$role_type;
         $district_info = $model->table("district_shop")->where("id=".$result['district_id'])->find();
         $result['district_user_id']=$district_info?$district_info['owner_id']:NULL;
         if($district_info['invite_shop_id']!=""){
             $result['superior_district_id'] =$district_info['invite_shop_id'];
             $district_inviter_info = $model->table("district_shop")->where("id=". $result['superior_district_id'])->find();
             $result['superior_district_user_id'] = $district_inviter_info?$district_inviter_info['owner_id']:NULL;
         }
         return $result;
     }
     
     static function updateDistrictIdInInviteShip($user_id,$district_id){
          $model = new Model();
          $result = $model->table("invite")->where("invite_user_id=".$user_id)->find();
          if($result){
              $result = $model->table("invite")->where("invite_user_id=".$user_id)->data(array("district_id"=>$district_id))->update();
              if($result){
                  return TRUE;
              }else{
                  return FALSE;
              }
          }else{
              return FALSE;
          }
     }
     
     static function setIncomeByInviteShip($order){
         $model = new Model();
         $inviter_info = $model->table("invite")->where("invite_user_id=".$order['user_id'])->find();
         if($inviter_info){
             $config = Config::getInstance()->get("district_set");
                $income1 = round($order['order_amount']*$config['income1']/100,2);
             Log::incomeLog($income1, 1, $inviter_info['user_id'], $order['id'], 0,"下级消费分成(上级邀请者)");
             $first_promoter_user_id = self::getFirstPromoter($inviter_info['user_id']);
             if($first_promoter_user_id){
                $income2 = round($order['order_amount']*$config['income2']/100,2);
                Log::incomeLog($income2, 2, $first_promoter_user_id, $order['id'], 0,"下级消费分成(上级第一个代理商)");
             }
             $income3 = round($order['order_amount']*$config['income3']/100,2);
             Log::incomeLog($income3, 3, $inviter_info['district_id'], $order['id'], 0,"下级消费分成(所属专区)");
             $district_info = $model->table("district_shop")->where("id=".$inviter_info['district_id'])->find();
             if($district_info&&$district_info['invite_shop_id']!=""){
                $income4 = round($order['order_amount']*$config['income4']/100,2);
                Log::incomeLog($income4, 3, $district_info['invite_shop_id'], $order['id'], 6,"专区邀请者分成");
             }
         }else{
             return false;
         }
     }

     static function backIncomeByInviteShip($order){ //退款收回收益
         $model = new Model();
         $inviter_info = $model->table("invite")->where("invite_user_id=".$order['user_id'])->find();
         if($inviter_info){
             $config = Config::getInstance()->get("district_set");
                $income1 = round($order['order_amount']*$config['income1']/100,2);
             Log::incomeLog($income1, 1, $inviter_info['user_id'], $order['id'], 15,"下级消费分成(上级邀请者)退款收回收益");
             $first_promoter_user_id = self::getFirstPromoter($inviter_info['user_id']);
             if($first_promoter_user_id){
                $income2 = round($order['order_amount']*$config['income2']/100,2);
                Log::incomeLog($income2, 2, $first_promoter_user_id, $order['id'], 15,"下级消费分成(上级第一个代理商)退款收回收益");
             }
             $income3 = round($order['order_amount']*$config['income3']/100,2);
             Log::incomeLog($income3, 3, $inviter_info['district_id'], $order['id'], 15,"下级消费分成(所属专区)退款收回收益");
             $district_info = $model->table("district_shop")->where("id=".$inviter_info['district_id'])->find();
             if($district_info&&$district_info['invite_shop_id']!=""){
                $income4 = round($order['order_amount']*$config['income4']/100,2);
                Log::incomeLog($income4, 3, $district_info['invite_shop_id'], $order['id'], 15,"专区邀请者分成退款收回收益");
             }
         }else{
             return false;
         }
     }

     static function setIncomeByInviteShip1($order){
         $model = new Model();
         $inviter_info = $model->table("invite")->where("invite_user_id=".$order['user_id'])->find();
         if($inviter_info){
             $config = Config::getInstance()->get("district_set");
             if($order['type']==7){
                $income1 = round($order['order_amount']*50/100,2); //如果订单产品属于微商专区商品，则收益为50%
             }else{
                $income1 = round($order['order_amount']*$config['income1']/100,2);
             }
             Log::incomeLog($income1, 1, $inviter_info['user_id'], $order['id'], 0,"下级消费分成(上级邀请者)");
             $first_promoter_user_id = self::getFirstPromoter($inviter_info['user_id']);
             if($first_promoter_user_id){
                $income2 = round($order['order_amount']*$config['income2']/100,2);
                Log::incomeLog($income2, 2, $first_promoter_user_id, $order['id'], 0,"下级消费分成(上级第一个代理商)");
             }
             $income3 = round($order['order_amount']*$config['income3']/100,2);
             Log::incomeLog($income3, 3, $inviter_info['district_id'], $order['id'], 0,"下级消费分成(所属专区)");
             $district_info = $model->table("district_shop")->where("id=".$inviter_info['district_id'])->find();
             if($district_info&&$district_info['invite_shop_id']!=""){
                $income4 = round($order['order_amount']*$config['income4']/100,2);
                Log::incomeLog($income4, 3, $district_info['invite_shop_id'], $order['id'], 6,"专区邀请者分成");
             }
         }
     }

     static function getFirstPromoter($user_id){
        $model = new Model();
        $is_promoter = $model->table("district_promoter")->where("user_id=".$user_id)->find();
        if($is_promoter){
            return $user_id;
        }else{
            //根据邀请关系找到上级第一个推广者（代理商）
            $is_break = false;
            $now_user_id = $user_id;
            $promoter_user_id = NULL;
            while(!$is_break){
                $inviter_info = $model->table("invite")->where("invite_user_id=".$now_user_id)->find();
                if($inviter_info){
                    $is_promoter = $model->table("district_promoter")->where("user_id=".$inviter_info['user_id'])->find();
                    if(!empty($is_promoter)){
                        $promoter_user_id = $inviter_info['user_id'];
                        $is_break = true;
                    }else{
                        $now_user_id = $inviter_info['user_id'];
                    }
                }else{
                    $is_break = true;
                }
            }
            return $promoter_user_id;
        }
     }

     static function offlineBeneficial($order_no,$invite_id){//线下分账
         // var_dump($invite_id);die;
         $model = new Model();
         $order = $model->table('order')->where('order_no='.$order_no)->find();
         $amount = $order['order_amount'];
         $config = Config::getInstance()->get("district_set");
         // $base_balance = round($amount*$config['offline_base_rate']/100,2);
         $promoter = $model->table('district_promoter')->fields('base_rate')->where('user_id='.$invite_id)->find();
         if($promoter){
            $base_balance = round($amount*$promoter['base_rate']/100,2); //每个商家都有自己的分账比例
         }else{
            $base_balance = round($amount*$config['offline_base_rate']/100,2);  //默认比例
         }
         $balance1 = round($base_balance*$config['promoter_rate']/100,2);
         $balance2 = round($base_balance*$config['district_rate']/100,2);
         $balance3 = round($base_balance*$config['promoter2_rate']/100,2);
         $balance4 = round($base_balance*$config['plat_rate']/100,2);
         
         $user_id = $order['user_id']; 
         $promoter_id = self::getFirstPromoter($user_id);
         
         $district = $model->table('district_shop')->where('owner_id='.$invite_id)->find();
         $invite = $model->table('invite')->fields('district_id')->where("invite_user_id=".$user_id)->find();
         $district1 = $model->table('district_shop')->fields('owner_id')->where('id='.$invite['district_id'])->find();

         $model->table('customer')->where('user_id='.$invite_id)->data(array("balance"=>"`balance`+({$balance1})"))->update();//上级邀请人提成
         Log::balance($balance1, $promoter_id, $order_no,'线下消费上级邀请人提成', 8);
         
        $model->table('customer')->where('user_id='.$promoter_id)->data(array("balance"=>"`balance`+({$balance2})"))->update();//上级代理商提成
        Log::balance($balance2, $promoter_id, $order_no,'线下消费上级代理商提成', 8);
         
         
         // //上级邀请人是经销商
         // if($district){
         //    $model->table('customer')->where('user_id='.$invite_id)->data(array("balance"=>"`balance`+({$balance2})"))->update();//上级经销商提成
         //    Log::balance($balance2, $invite_id, $order_no,'线下消费上级经销商提成', 8);
         // }
         
         if($district1){
            var_dump(111);die;
            $exist=$model->table('customer')->where('user_id='.$district1['owner_id'])->find();
            if($exist){
                $model->table('customer')->where('user_id='.$district1['owner_id'])->data(array("balance"=>"`balance`+({$balance3})"))->update();//上级经销商提成
                Log::balance($balance3, $invite_id, $order_no,'线下消费上级经销商提成', 8);
            }  
         }

         // $invite2 = $model->table('invite')->where("invite_user_id=".$promoter_id)->find();
         // if($invite2){
         //    $model->table('customer')->where('user_id='.$invite2['user_id'])->data(array("balance"=>"`balance`+({$balance3})"))->update();//代理商上级邀请人提成
         //    Log::balance($balance3, $invite2['user_id'], $order_no,'线下消费代理商上级邀请人提成', 8);
         // }
         
            $model->table('customer')->where('user_id=1')->data(array("balance"=>"`balance`+({$balance4})"))->update();//平台收益提成
            Log::balance($balance4, 1, $order_no,'线下会员消费平台收益', 8);
         
     }

     static function testAlipay($order_no){
         $model = new Model();
         $order = $model->table('order')->where('order_no='.$order_no)->find();
         $amount = $order['order_amount'];
        // include_once 'request/AlipayFundTransToaccountTransferRequest.php';
        // include_once ('AopClient.php');  
        $aop = new \Alipay\aop\AopClient();
        // $aop = new AopClient();
        $aop->appId = '2017072607901626';
        $aop->rsaPrivateKey = 'MIIEowIBAAKCAQEAscWm/XwJyMw538Cwfqcf+qhTqHHa2dJiJbfDgypVuAI2dpcA/KWRcRut25+E1kUpLqsZ3cgrgqCiUDZk4iLslkWq03YfB/uyzX+ktan9H9STDognRFd5XpjcHnHpwqJAjobsgVa+EKp7AUlCHGJKwmJEtghLcSQ858xErccCLe7bppfkNvurmCbRUQ2u0OzZ4VbNbUIyA6HlyvJo9zSvwzIh2Ggx7fAMKMpX7mfrq+sbea6G92Ci2npgNRezWq6iUueXOhgqqbNSFzK8x7QL+Ka0dBDl0xYOC8+HQh5GdyAS58fBXPlq628LjaQvkwfkScDEoq1t3wbcp+pF83qqjQIDAQABAoIBAG1Re0ATv7yQAeLbjm1D/oFYc6F46jjai+pf18XYCcBO9Aj3EO9MLWUdvUr6DGjrPMjrBMwCZOc+OrIS0PTSvyQlkUfaMnjpSene3X2tG/Av+4KLLYJ0PDl0zJ+YM0SyG/rJc7SRj+2VuHBxCUuFEi342gIKlcHso9tzHKS0ZV2yp1XjD/AgFyvaeFSazdZEpX3jg6GfAytY34vJFSM3d3koK4H1qx+S3U/qMtWNClaDQfh3kGXqERpnwITwPmwfEMIUM56Itq6QU1uZnYQDkbZxo3kVlT20C53irtq5PDJ162ls730pGpxaOqAq6iUcI8taKIloaXhPX3ReqlDiL2ECgYEA1m8BDwq34dRojGIjsRMubsRhUjyAtIZr3FknVyw5A7dELf6Z51NxLWftZPxy5w2JefqL6ZH5FMlkgHyh+zRGrPmh7Ftu3lraiWKnAk5KIBqxo25Mg1Dyk+AghkZyHVJ4TIRtoFcucfL4Eg6DcwT/ZfYl54btjKbQEPlXPZS1WFUCgYEA1DtdwMF4r/f8awOj8VJ6PeUPPG+rltHD0zSKlRwqrihtgCAnwvnkm5FLaLQtKNQ85jxLQc6bUAP01Irqg9MbScF+MPXEvPtG+EIfsiE9A1ab1nG90IbRfmORf+DfO5+om5918iFM68Lt7rBhIHZJbr9z8ClRcpKH1qdgZRvcIVkCgYBQFmNh19H3wVpO3DSSZSSZcDUc/sXfJrlQMegUkcq1jZQkTYvzruF9YOx0JClSDGdFLINm+AL8dX9Y0bO527ttzUphuYB+AZbPaw4POWhL90xTStW+0dPX0QS0wcjLFMsjYO6EzSrmmiV2sP79TWeKEFX11BoSxxa80DN6J3lXhQKBgC2g3dU1Q0dB36j6TWLywolQF+h8cb2pN5rO7wSD28E5u+ESCLpok3fG0xmdsx/WEYnGaL+rNcUMNLUFcMoKtxEyYnkQPc4LkASL4tifQMjY9AQ0zARrF9s+eOevZw8gklVzAR6ffjQp4pGwphEenUcMLlbx6yrgygeiUJ0sUjVxAoGBALkhFsGdI8A9z57jm6L4Mg+15DAk1Cx6ynj/PoHiVrFWboTZfPY0CKsQehb2G2ij+g3cU7DxCG8rsJj9dmyBw2WEyT+eLiVQFQ+JdkAzIGPK9BkPcMGF+adNuGCvIDSoIQILHTpJqcN2nTCNRKQIMG3PGouDxylRfklnIG7IwkNs';
        $aop->alipayrsaPublicKey='MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAscWm/XwJyMw538Cwfqcf+qhTqHHa2dJiJbfDgypVuAI2dpcA/KWRcRut25+E1kUpLqsZ3cgrgqCiUDZk4iLslkWq03YfB/uyzX+ktan9H9STDognRFd5XpjcHnHpwqJAjobsgVa+EKp7AUlCHGJKwmJEtghLcSQ858xErccCLe7bppfkNvurmCbRUQ2u0OzZ4VbNbUIyA6HlyvJo9zSvwzIh2Ggx7fAMKMpX7mfrq+sbea6G92Ci2npgNRezWq6iUueXOhgqqbNSFzK8x7QL+Ka0dBDl0xYOC8+HQh5GdyAS58fBXPlq628LjaQvkwfkScDEoq1t3wbcp+pF83qqjQIDAQAB';
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
        $aop->postCharset='utf-8';
        $aop->format='json';
        $request = new \Alipay\aop\request\AlipayFundTransToaccountTransferRequest();
        $request->setBizContent("{" .
        "\"out_biz_no\":\"{$order_no}\"," . 
        "\"payee_type\":\"ALIPAY_LOGONID\"," .
        "\"payee_account\":\"18070146273\"," .
        "\"amount\":\"{$amount}\"," .
        "\"remark\":\"单笔转账测试\"" .
        "}");
        $result = $aop->execute ($request); 
        var_dump($result);die;
     }
}
