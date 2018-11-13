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
            if($way=='active') {
                // $active = $model->table("invite as i")->fields("i.id")->where("i.from='active' and i.user_id={$inviter_id}")->findAll();
                // $num = count($active);
                $active = $model->table("invite_active")->fields("invite_num")->where("user_id={$inviter_id}")->find();
                $num = $active==null?0:$active['invite_num'];
                $num = $num+1;
                $model->table('invite_active')->data(['invite_num'=>$num])->where('user_id='.$inviter_id)->update();
            }
            $start_time = '2018-10-18 00:00:01';
            $invite_num = $model->table('invite as i')->join('left join customer as c on i.invite_user_id=c.user_id')->where("i.user_id=".$inviter_id." and c.mobile_verified=1 and c.checkin_time>'{$start_time}'")->count();
            $vip = $model->table('user')->fields('is_vip')->where('id='.$inviter_id)->find();
            if($invite_num>=2 && $vip['is_vip']==0) { 
                $type = 'upgrade_vip';
                $content = "您有5位或以上粉丝成功注册圆梦用户，恭喜您成功获得VIP资格";
                $platform = 'all';
                $NoticeService = new NoticeService();
                $jpush = $NoticeService->getNotice('jpush');
                $audience['alias'] = array($inviter_id);
                $jpush->setPushData($platform, $audience, $content, $type, '');
                $ret = $jpush->push();
                $model->table('user')->data(['is_vip'=>1])->where('id='.$inviter_id)->update();
            }
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
         $role_type = !empty($is_district_promoter)?2:1;
         $is_district_hirer = $model->table("district_shop")->where("owner_id=$user_id")->order("id asc")->find();
         $role_type = !empty($is_district_hirer) ? 3:$role_type;
         $result = array();
         $district_id = 1;
         if($role_type==1){
             $inviter_info = $model->table("invite")->where("invite_user_id=".$user_id)->find();
             if($inviter_info){//有邀请关系
                $inviter_promoter_info = $model->table('district_promoter')->where("user_id=".$inviter_info['user_id'])->fields("type,hirer_id")->find();
                $inviter_role = !empty($inviter_promoter_info) ? 2:1;
                $inviter_hirer_info = $model->table("district_shop")->where("owner_id=".$inviter_info['user_id'])->find();
                $inviter_role = !empty($inviter_hirer_info) ? 3:$role_type;
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
             $first_promoter_user_id = self::getFirstPromoter($order['user_id']);
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

     static function setIncomeByInviteShipEachGoods($order_id){
         $model = new Model();
         //通过订单id获取商品id
         $order = $model->table('order')->where('id='.$order_id)->find();
         $order_goods = $model->table('order_goods')->fields('goods_id')->where('order_id='.$order['id'])->find();
         $goods = $model->table('goods')->fields('inviter_rate,promoter_rate,districter_rate')->where('id='.$order_goods['goods_id'])->find();

         $inviter_info = $model->table("invite")->where("invite_user_id=".$order['user_id'])->find();
         if($inviter_info){
                $config = Config::getInstance()->get("district_set");
                $base_balance = round($order['order_amount']*($goods['inviter_rate']-$config['handling_rate'])/100,2);
                
                $promoter_rate = $config['promoter_rate1'];
                $district_rate = $config['district_rate1'];
                $promoter2_rate = $config['promoter2_rate1'];

                $plat_rate = $config['plat_rate1'];
                $encourage = $config['encourage'];
                $reward1 = $config['reward3'];
                $reward2 = $config['reward4'];
                $ready_rate = $config['ready_rate1'];

                $balance1 = round($base_balance*$promoter_rate/100,2);
                $balance2 = round($base_balance*$district_rate/100,2);
                $balance3 = round($base_balance*$promoter2_rate/100,2);
                $balance4 = round($base_balance*$plat_rate/100,2);
                
                $balance5 = round($base_balance*$encourage/100,2); //10%激励金
                $balance6 = round($base_balance*$reward1/100,2); //2%奖金池
                $balance7 = round($base_balance*$reward2/100,2); //3%奖金池
                $balance8 = round($base_balance*$ready_rate/100,2); //5%预备金

                $district = $model->table('district_shop')->fields('owner_id')->where('id='.$inviter_info['district_id'])->find();
                 if($district) {
                    $district_id = $district['owner_id'];
                 } else {
                    $district_id = 0;
                 }

             if($balance1>0) {
                   // Log::incomeLog($balance1, 1, $inviter_info['user_id'], $order['id'], 0,"下级消费分成(上级邀请者)");
                   $model->table('customer')->where('user_id='.$inviter_info['user_id'])->data(array("balance"=>"`balance`+({$balance1})"))->update();
                   Log::balance($balance1, $inviter_info['user_id'], $order['order_no'],'线上消费收益(上级邀请者)', 5); 
             }
             // var_dump(111);
             // 获取上级超级vip及B级粉丝当月总订单数
             $first_vip = self::getFirstVip($order['user_id']);
             $vip_rate = $config['vip_rate']; //6%超级vip池
             if($first_vip) {
                $inviter_infos = $model->table("invite")->fields('invite_user_id')->where('user_id='.$first_vip)->findAll();
                $ids = array();
                if($inviter_infos) {
                    foreach($inviter_infos as $k =>$v) {
                       $ids[] = $v['invite_user_id'];
                    }
                }
                $user_ids = $ids!=null?implode(',', $ids):'';
                if($user_ids!='') {                  
                   $last= strtotime("-1 month", time());
                   $last_lastday = date("Y-m-t", $last);//上个月最后一天
                   $last_firstday = date('Y-m-01', $last);//上个月第一天
                   $order_num = $model->table('order')->where("pay_status=1 and status in (3,4) and user_id in ($user_ids) and create_time between '{$last_firstday}' and '{$last_lastday}'")->count(); 
               } else {
                   $order_num = 0;
               }
               if($order_num>=100) {
                 $balance9 = round($base_balance*($vip_rate+$encourage)/100,2); //超级vip6%+10%激励金分润
               } else {
                 $balance9 = round($base_balance*($vip_rate)/100,2); //超级vip6%分润
               }
               if($balance9>0) {
                    $model->table('customer')->where('user_id='.$first_vip)->data(array("balance"=>"`balance`+({$balance9})"))->update();
                    Log::balance($balance9, $first_vip, $order['order_no'],'线上消费收益(上级第一个超级VIP)', 5);
                }
             }
             $first_promoter_user_id = self::getFirstPromoter($order['user_id']);
             // var_dump(222);die;
             if($first_promoter_user_id){   
                if($balance2>0) {
                    // Log::incomeLog($balance2, 2, $first_promoter_user_id, $order['id'], 0,"下级消费分成(上级第一个代理商)");
                    $model->table('customer')->where('user_id='.$first_promoter_user_id)->data(array("balance"=>"`balance`+({$balance2})"))->update();
                    Log::balance($balance2, $first_promoter_user_id, $order['order_no'],'线上消费收益(上级第一个代理商)', 5);
                }
             }
             
             if($balance3>0) {
                // Log::incomeLog($balance3, 3, $inviter_info['district_id'], $order['id'], 0,"下级消费分成(所属专区)");
                if($district_id) {
                    $model->table('customer')->where('user_id='.$district_id)->data(array("balance"=>"`balance`+({$balance3})"))->update();
                    Log::balance($balance3, $district_id, $order['order_no'],'线上消费收益(所属专区)', 5);
                }
             }

             if($balance4>0) {
                $model->table('customer')->where('user_id=1')->data(array("balance"=>"`balance`+({$balance4})"))->update();
                Log::balance($balance4, 1, $order['order_no'],'线上消费收益(平台)', 5);
             }

             if($balance5>0) {
                $model->table("reward")->data(array("encourage_amount"=>"`encourage_amount`+({$balance5})"))->where("id=1")->update();
             }

             if($balance6>0) {
                $model->table("reward")->data(array("reward3"=>"`reward3`+({$balance6})"))->where("id=1")->update();
             }

             if($balance7>0) {
                $model->table("reward")->data(array("reward4"=>"`reward4`+({$balance7})"))->where("id=1")->update();
             }

             if($balance8>0) {
                $model->table("reward")->data(array("ready_amounts"=>"`ready_amounts`+({$balance8})"))->where("id=1")->update();
             } 
         }else{
             return false;
         }
     }

     static function backIncomeByInviteShip($order){ //退款收回收益
         $model = new Model();
         $goods = $model->table('goods')->fields('inviter_rate,promoter_rate,districter_rate')->where('id='.$order['goods_id'])->find();
         $inviter_info = $model->table("invite")->where("invite_user_id=".$order['user_id'])->find();
         if($inviter_info){
             $config = Config::getInstance()->get("district_set");
             $base_balance = round($order['order_amount']*($goods['inviter_rate']-$config['handling_rate'])/100,2);
                $income1 = round($base_balance*$config['promoter_rate1']/100,2);
                if($income1>0) {
                    // Log::incomeLog($income1, 1, $inviter_info['user_id'], $order['id'], 15,"下级消费分成(上级邀请者)退款收回收益");
                    $model->table('customer')->where('user_id='.$inviter_info['user_id'])->data(array("balance"=>"`balance`-({$income1})"))->update();
                    Log::balance(-$income1, $inviter_info['user_id'], $order['order_no'],'下级消费分成(上级邀请者)退款收回收益', 4);
                }
             $first_promoter_user_id = self::getFirstPromoter($order['user_id']);
             if($first_promoter_user_id){
                $income2 = round($base_balance*$config['district_rate1']/100,2);
                if($income2>0) {
                    // Log::incomeLog($income2, 2, $first_promoter_user_id, $order['id'], 15,"下级消费分成(上级第一个代理商)退款收回收益");
                    $model->table('customer')->where('user_id='.$first_promoter_user_id)->data(array("balance"=>"`balance`-({$income2})"))->update();
                    Log::balance(-$income2, $first_promoter_user_id, $order['order_no'],'下级消费分成(上级第一个代理商)退款收回收益', 4);
                }  
             }
             $income3 = round($base_balance*$config['promoter2_rate1']/100,2);
             if($income3>0) {
                // Log::incomeLog($income3, 3, $inviter_info['district_id'], $order['id'], 15,"下级消费分成(所属专区)退款收回收益");
                $district = $model->table('district_shop')->fields('owner_id')->where('id='.$inviter_info['district_id'])->find();
                 if($district) {
                    $district_id = $district['owner_id'];
                    $model->table('customer')->where('user_id='.$district_id)->data(array("balance"=>"`balance`-({$income3})"))->update();
                    Log::balance(-$income3, $district_id, $order['order_no'],'下级消费分成(所属专区)退款收回收益', 4);
                 } 
             }
             $income4 = round($base_balance*$config['plat_rate1']/100,2);
             if($income4>0) {
                $model->table('customer')->where('user_id=1')->data(array("balance"=>"`balance`-({$income4})"))->update();
                Log::balance(-$income4, 1, $order['order_no'],'下级消费分成(平台)退款收回收益', 4);
             }
             $where = "note='线上消费收益(上级第一个超级VIP)' and order_no=".$order['order_no'];
             $vip_log = $model->table('balance_log')->where($where)->find();
             if($vip_log) {
                $first_vip = $vip_log['user_id'];
                $income5 = $vip_log['amount'];
                $model->table('customer')->where('user_id='.$first_vip)->data(array("balance"=>"`balance`-({$income5})"))->update();
                Log::balance(-$income5, $first_vip, $order['order_no'],'下级消费分成(第一个超级VIP)退款收回收益', 4);
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
                $income1 = round($order['order_amount']*$config['promoter_rate1']/100,2);
             }
             Log::incomeLog($income1, 1, $inviter_info['user_id'], $order['id'], 0,"下级消费分成(上级邀请者)");
             $first_promoter_user_id = self::getFirstPromoter($order['user_id']);
             if($first_promoter_user_id){
                $income2 = round($order['order_amount']*$config['district_rate1']/100,2);
                Log::incomeLog($income2, 2, $first_promoter_user_id, $order['id'], 0,"下级消费分成(上级第一个代理商)");
             }
             $income3 = round($order['order_amount']*$config['promoter2_rate1']/100,2);
             Log::incomeLog($income3, 3, $inviter_info['district_id'], $order['id'], 0,"下级消费分成(所属专区)");
             // $district_info = $model->table("district_shop")->where("id=".$inviter_info['district_id'])->find();
             // if($district_info&&$district_info['invite_shop_id']!=""){
             //    $income4 = round($order['order_amount']*$config['income4']/100,2);
             //    Log::incomeLog($income4, 3, $district_info['invite_shop_id'], $order['id'], 6,"专区邀请者分成");
             // }
         }
     }

     static function getFirstPromoter($user_id){
        $model = new Model();
        // $is_promoter = $model->table("district_promoter")->where("user_id=".$user_id)->find();
        
            //根据邀请关系找到上级第一个推广者（代理商）
            $is_break = false;
            $now_user_id = $user_id;
            $promoter_user_id = 1;
            $user_info = $model->table("invite")->where("invite_user_id=".$user_id)->find();
            if(!$user_info) {
                return $promoter_user_id;
            }
            $is_shop = $model->table("district_shop")->where("id=".$user_info['district_id'])->find();
            while(!$is_break){
                $inviter_info = $model->table("invite")->where("invite_user_id=".$now_user_id)->find();
                if($inviter_info){
                    $is_promoter = $model->table("district_promoter")->where("user_id=".$inviter_info['user_id'])->find();
                    if(!empty($is_promoter)){       
                            if($is_promoter['hirer_id']==$user_info['district_id'] || $is_shop['owner_id']==$inviter_info['user_id']) {
                                $promoter_user_id = $inviter_info['user_id'];
                                $is_break = true;
                            }else{
                                $now_user_id = $inviter_info['user_id'];
                            }
                    }else{
                        $now_user_id = $inviter_info['user_id'];
                    }
                }else{
                    $is_break = true;
                }
            }
            return $promoter_user_id;
        
     }

     static function getFirstVip($user_id){
        $model = new Model();
        // $is_break = false;
        // $now_user_id = $user_id;
        // $first_vip = 1;
        // $user_info = $model->table("invite")->where("invite_user_id=".$user_id)->find();
        // if(!$user_info) {
        //     return $first_vip;
        // }
        // while(!$is_break){
        //     $inviter_info = $model->table("invite")->where("invite_user_id=".$now_user_id)->find();
        //     if($inviter_info){
        //         $user = $model->table("user")->where("id=".$inviter_info['user_id'])->find();
        //         if(!empty($user) && $user['is_vip']==1) {       
        //             $first_vip = $inviter_info['user_id'];
        //             $is_break = true;    
        //         }else{
        //             $now_user_id = $inviter_info['user_id'];
        //         }
        //     }else{
        //         $is_break = true;
        //     }
        // }
        // return $first_vip; 
        
        $user_info = $model->table("invite")->where("invite_user_id=".$user_id)->find();
        if($user_info) {
            $inviter1 = $user_info['user_id']; //第一个邀请人
            $user_info1 = $model->table("invite")->where("invite_user_id=".$inviter1)->find();
            if($user_info1) {
                $inviter2 = $user_info1['user_id']; //第二个邀请人
                $user2 = $model->table("user")->fields('is_vip')->where("id=".$inviter2)->find();
                if($user2['is_vip']==1) {
                    $first_vip = $inviter2;
                } else {
                    $user1 = $model->table("user")->fields('is_vip')->where("id=".$inviter1)->find();
                    if($user1['is_vip']==1) {
                        $first_vip = $inviter1;
                    } else {
                        $first_vip = 1; //分给平台
                    }
                }
            }
            return $first_vip;
        } else {
            return false;
        }
     }

     static function getFirstPromoters($user_id){
        $model = new Model();
        // $is_promoter = $model->table("district_promoter")->where("user_id=".$user_id)->find();
        
            //根据邀请关系找到上级第一个推广者（代理商）
            $is_break = false;
            $now_user_id = $user_id;
            $promoter_user_id = 1;
            $user_info = $model->table("invite")->where("invite_user_id=".$user_id)->find();
            if(!$user_info) {
                return $promoter_user_id;
            }
            while(!$is_break){
                $inviter_info = $model->table("invite")->where("invite_user_id=".$now_user_id)->find();
                if($inviter_info){
                    $now_user_id = $inviter_info['user_id'];
                }else{
                    $is_break = true;
                }
            }
            return $promoter_user_id;
        
     }

     static function getAllChildPromoters($user_id){
        $model = new Model();
        $is_break = false;
        $now_user_id = $user_id;
        $result = [];
        while (!$is_break) {
            $invite = $model->table("invite")->fields('invite_user_id')->where("user_id=".$now_user_id)->findAll();
            if($invite){
                foreach ($invite as $k => $v) {
                    $shop = $model->table('district_shop')->where('owner_id='.$v['invite_user_id'])->find();
                    $promoter = $model->table('district_promoter')->where('user_id='.$v['invite_user_id'])->find();
                    if($shop && $promoter){
                        array_push($result, $v['invite_user_id']);
                        $now_user_id = $v['invite_user_id'];
                        $is_break = false;
                    }elseif($shop && !$promoter){
                        array_push($result, $v['invite_user_id']);
                        $is_break = true; 
                    }else{
                       $is_break = true; 
                    }      
                }
                $is_break = false;
            }else{
                $is_break = true;
                $result = [];
            }
        }
        return $result;
     }

     static function getFirstPromoterName($user_id){
        $model = new Model();
        // $is_promoter = $model->table("district_promoter")->where("user_id=".$user_id)->find();
        
            //根据邀请关系找到上级第一个推广者（代理商）
            $is_break = false;
            $now_user_id = $user_id;
            $promoter_user_id = 1;
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
           $promoter_user=$model->table('customer')->fields('real_name')->where('user_id='.$promoter_user_id)->find();
           return $promoter_user['real_name'];
     }

     static function getInviterId($user_id){
         $model=new Model();
         $invite=$model->table('invite')->where('invite_user_id='.$user_id)->find();
            if($invite){
                $invite_id=$invite['user_id'];
            }else{
                $invite_id=1;
            }
        return $invite_id;  
     }

     static function getInviterName($user_id){
        $model=new Model();
        $invite=$model->table('invite')->where('invite_user_id='.$user_id)->find();
            if($invite){
                $invite_id=$invite['user_id'];
            }else{
                $invite_id=1;
            }
        $user=$model->table('customer')->fields('real_name')->where('user_id='.$invite_id)->find();
        return $user['real_name'];
     }

     static function getFirstDistricter($user_id){
        $model=new Model();
        $invite=$model->table('invite')->where('invite_user_id='.$user_id)->find();
        if($invite){
            $district=$model->table('district_shop')->fields('name,owner_id')->where('id='.$invite['district_id'])->find();
            if($district){
                $district_name=$district['name'];
            }else{
                $district_name='圆梦商城';
            }
        }else{
            $district_name='圆梦商城';
        }  
        return $district_name;
     }

     static function offlineBeneficial($order_no,$invite_id,$seller_id){//线下分账到余额
         // var_dump($invite_id);die;
         $model = new Model();
         $order = $model->table('order_offline')->where('order_no='.$order_no)->find();
         $amount = $order['order_amount'];
         $config = Config::getInstance()->get("district_set");
         // $base_balance = round($amount*$config['offline_base_rate']/100,2);
         $promoter = $model->table('district_promoter')->fields('base_rate')->where('user_id='.$seller_id)->find();
         if($promoter){
            $base_balance = round($amount*($promoter['base_rate']-$config['handling_rate'])/100,2); //每个商家都有自己的分账比例
         }else{
            $base_balance = round($amount*($config['offline_base_rate']-$config['handling_rate'])/100,2);  //默认比例
         }
         $balance1 = round($base_balance*$config['promoter_rate']/100,2);
         $balance2 = round($base_balance*$config['district_rate']/100,2);
         $balance3 = round($base_balance*$config['promoter2_rate']/100,2);
         $balance4 = round($base_balance*$config['plat_rate']/100,2);
         $balance5 = round($base_balance*$config['redbag_rate']/100,2); //红包金额
         $balance6 = round($base_balance*$config['reward1']/100,2); //2%奖金池
         $balance7 = round($base_balance*$config['reward2']/100,2); //3%奖金池
         $balance8 = round($base_balance*$config['ready_rate']/100,2); //5%预备金
         $user_id = $order['user_id']; 
         $promoter_id = self::getFirstPromoter($user_id);
         
         // $district = $model->table('district_shop')->where('owner_id='.$invite_id)->find();

         $invite = $model->table('invite')->fields('district_id')->where("invite_user_id=".$user_id)->find();
         if($invite){
            $district1 = $model->table('district_shop')->fields('owner_id')->where('id='.$invite['district_id'])->find();
         }
         if($district1){
            $district_id = $district1['owner_id'];
        }else{
            $district_id = 1;
        }

        $wechatcfg = $model->table("oauth")->where("class_name='WechatOAuth'")->find();
        $wechat = new WechatMenu($wechatcfg['app_key'], $wechatcfg['app_secret'], '');
        $token = $wechat->getAccessToken();
         
         if($balance1>0){
            $model->table('customer')->where('user_id='.$invite_id)->data(array("balance"=>"`balance`+({$balance1})"))->update();//上级邀请人提成
            Log::balance($balance1, $invite_id, $order_no,'线下消费上级邀请人提成', 8);
         }         
         
         if($balance2>0){
            if($promoter_id){
                $model->table('customer')->where('user_id='.$promoter_id)->data(array("balance"=>"`balance`+({$balance2})"))->update();//上级代理商提成
                Log::balance($balance2, $promoter_id, $order_no,'线下消费上级代理商提成', 8);
             }else{
                $model->table('customer')->where('user_id=1')->data(array("balance"=>"`balance`+({$balance2})"))->update();//上级代理商提成,默认为官方平台
                Log::balance($balance2, 1, $order_no,'线下消费上级代理商提成', 8);
             }
         }
         
         if($balance3>0){
            if(isset($district1) && $district1!=null){
                $exist=$model->table('customer')->where('user_id='.$district_id)->find();
                if($exist){
                    $model->table('customer')->where('user_id='.$district_id)->data(array("balance"=>"`balance`+({$balance3})"))->update();//上级经销商提成
                    Log::balance($balance3, $district_id, $order_no,'线下消费上级经销商提成', 8);
                }  
             }else{
                    $model->table('customer')->where('user_id=1')->data(array("balance"=>"`balance`+({$balance3})"))->update();//上级经销商提成,默认为官方平台
                    Log::balance($balance3, 1, $order_no,'线下消费上级经销商提成', 8);
             }
         }
         
         if($balance4>0){
            $model->table('customer')->where('user_id=1')->data(array("balance"=>"`balance`+({$balance4})"))->update();//平台收益提成
            Log::balance($balance4, 1, $order_no,'线下会员消费平台收益', 8);
         }   
         
         if($balance5>0){
            $seller = $model->table('district_promoter')->fields('location,lng,lat')->where('user_id='.$seller_id)->find();
            // $rand = rand(-111,111)/100000;
            if($seller){
                if($seller['lng'] == '' && $seller['lat'] == ''){
                   $model->table('redbag')->data(array('order_no'=>$order_no,'amount'=>$balance5,'total_amount'=>$balance5,'order_id'=>$order['id'],'user_id'=>$seller_id,'create_time'=>date('Y-m-d H:i:s'),'location'=>$seller['location'],'pay_status'=>1))->insert(); 
               }else{
                $rand1 = rand(-90,90)/10000;
                if($rand1>0){
                    $rand2 = 0.009-$rand1;
                }else{
                    $rand2 = 0-(0.009-abs($rand1));
                }
                if($balance5>=0.01 && $balance5<=0.02) { //红包数量1
                   $model->table('redbag')->data(array('order_no'=>$order_no,'amount'=>$balance5,'total_amount'=>$balance5,'order_id'=>$order['id'],'user_id'=>$seller_id,'create_time'=>date('Y-m-d H:i:s'),'location'=>$seller['location'],'lng'=>$seller['lng']+$rand1,'lat'=>$seller['lat']+$rand2,'pay_status'=>1))->insert();
                } elseif($balance5>0.02 && $balance5<=0.05) { //红包数量2
                    $max_money = $balance5*100-1;
                    $redbag_money = rand(1,$max_money)/100;    
                    $model->table('redbag')->data(array('order_no'=>$order_no,'amount'=>$redbag_money,'total_amount'=>$redbag_money,'order_id'=>$order['id'],'user_id'=>$seller_id,'create_time'=>date('Y-m-d H:i:s'),'location'=>$seller['location'],'lng'=>$seller['lng']+$rand1,'lat'=>$seller['lat']+$rand2,'pay_status'=>1))->insert();
                    $rand3 = rand(-90,90)/10000;
                    if($rand3>0){
                        $rand4 = 0.009-$rand3;
                    }else{
                        $rand4 = 0-(0.009-abs($rand3));
                    }
                    $redbag_money2 = $balance5 - $redbag_money;
                    $model->table('redbag')->data(array('order_no'=>$order_no,'amount'=>$redbag_money2,'total_amount'=>$redbag_money2,'order_id'=>$order['id'],'user_id'=>$seller_id,'create_time'=>date('Y-m-d H:i:s'),'location'=>$seller['location'],'lng'=>$seller['lng']+$rand3,'lat'=>$seller['lat']+$rand4,'pay_status'=>1))->insert();
                } elseif($balance5>0.05 && $balance5<=0.1) { //红包数量3~5
                    $num = rand(3,5);
                    $redbag_money = sprintf('%.2f',$balance5/$num);
                    for($i=1;$i<=$num;$i++) {
                        $rand3 = rand(-90,90)/10000;
                        if($rand3>0){
                            $rand4 = 0.009-$rand3;
                        }else{
                            $rand4 = 0-(0.009-abs($rand3));
                        }
                        $model->table('redbag')->data(array('order_no'=>$order_no,'amount'=>$redbag_money,'total_amount'=>$redbag_money,'order_id'=>$order['id'],'user_id'=>$seller_id,'create_time'=>date('Y-m-d H:i:s'),'location'=>$seller['location'],'lng'=>$seller['lng']+$rand3,'lat'=>$seller['lat']+$rand4,'pay_status'=>1))->insert();
                    }
                } elseif($balance5>0.1 && $balance5<=5) { //红包数量5~10
                    $num = rand(5,10);
                    $redbag_money = sprintf('%.2f',$balance5/$num); 
                    for($i=1;$i<=$num;$i++) {
                        $rand3 = rand(-90,90)/10000;
                        if($rand3>0){
                            $rand4 = 0.009-$rand3;
                        }else{
                            $rand4 = 0-(0.009-abs($rand3));
                        }
                        $model->table('redbag')->data(array('order_no'=>$order_no,'amount'=>$redbag_money,'total_amount'=>$redbag_money,'order_id'=>$order['id'],'user_id'=>$seller_id,'create_time'=>date('Y-m-d H:i:s'),'location'=>$seller['location'],'lng'=>$seller['lng']+$rand3,'lat'=>$seller['lat']+$rand4,'pay_status'=>1))->insert();
                    }
                } elseif($balance5>5 && $balance5<=10) { //红包数量10~20
                    $num = rand(10,20);
                    $redbag_money = sprintf('%.2f',$balance5/$num); 
                    for($i=1;$i<=$num;$i++) {
                        $rand3 = rand(-90,90)/10000;
                        if($rand3>0){
                            $rand4 = 0.009-$rand3;
                        }else{
                            $rand4 = 0-(0.009-abs($rand3));
                        }
                        $model->table('redbag')->data(array('order_no'=>$order_no,'amount'=>$redbag_money,'total_amount'=>$redbag_money,'order_id'=>$order['id'],'user_id'=>$seller_id,'create_time'=>date('Y-m-d H:i:s'),'location'=>$seller['location'],'lng'=>$seller['lng']+$rand3,'lat'=>$seller['lat']+$rand4,'pay_status'=>1))->insert();
                    }
                } else { //红包数量20~50
                    $num = rand(20,50);
                    $redbag_money = sprintf('%.2f',$balance5/$num); 
                    for($i=1;$i<=$num;$i++) {
                        $rand3 = rand(-90,90)/10000;
                        if($rand3>0){
                            $rand4 = 0.009-$rand3;
                        }else{
                            $rand4 = 0-(0.009-abs($rand3));
                        }
                        $model->table('redbag')->data(array('order_no'=>$order_no,'amount'=>$redbag_money,'total_amount'=>$redbag_money,'order_id'=>$order['id'],'user_id'=>$seller_id,'create_time'=>date('Y-m-d H:i:s'),'location'=>$seller['location'],'lng'=>$seller['lng']+$rand3,'lat'=>$seller['lat']+$rand4,'pay_status'=>1))->insert();
                    }
                }
               }   
            }
         }

         if($balance6>0) {
            $model->table("reward")->data(array("reward1"=>"`reward1`+({$balance6})"))->where("id=1")->update();
         }

         if($balance7>0) {
            $model->table("reward")->data(array("reward2"=>"`reward2`+({$balance7})"))->where("id=1")->update();
         }

         if($balance8>0) {
            $model->table("reward")->data(array("ready_amount"=>"`ready_amount`+({$balance8})"))->where("id=1")->update();
         }
     }

     static function testWxpayPay(){
         $tools=new PhpTools();
         $account1 = $model->table("balance_withdraw")->where("user_id=".$invite_id)->find();
         $account2 = $model->table("balance_withdraw")->where("user_id=".$promoter_id)->find();
         $account3 = $model->table("balance_withdraw")->where("user_id=".$district_id)->find();
         $account4 = $model->table("balance_withdraw")->where("user_id=1")->find();  

         // $ACCOUNT_NO = $account['card_no'];
         // $MOBILE = $account['mobile'];
         // $AMOUNT = $balance1;
         // $BATCHID = $account['mer_seq_id'];
         // // $SETTDAY = $_GET['SETTDAY'];
         // // $FINTIME = $_GET['FINTIME'];
         // $SUBMITTIME = date('YmdHis');
         // $SN = $account['id'];
         // $POUNDAGE = 0;
         // $USERCODE = $account['user_id'];
         // $SIGN = $_GET['SIGN'];//签名后的字符串

         // $orgstr=$ACCOUNT_NO."|".$MOBILE."|".$AMOUNT."|".$BATCHID."|".$SN."|".$POUNDAGE;
         // $signture=$SIGN;

         // $result=$tools->verifyStr($orgstr,$signture);
         
         $params = array(
             'INFO' => array(
                 'TRX_CODE' => '100001',
                 'VERSION' => '03',
                 'DATA_TYPE' => '2',
                 'LEVEL' => '6',
                 'USER_NAME' => '20060400000044502',
                 'USER_PASS' => '111111',
                 'REQ_SN' => '200604000000445-dtdrtert452352543',
             ),
             'BODY' => array(
                 'TRANS_SUM' => array(
                     'BUSINESS_CODE' => '10600',
                     'MERCHANT_ID' => '200604000000445',
                     'SUBMIT_TIME' => date('YmdHis'),
                     'TOTAL_ITEM' => '4',
                     'TOTAL_SUM' => $balance1+$balance2+$balance3+$balance4,
                     'SETTDAY' => '',
                  ),
                 'TRANS_DETAILS'=> array(
                       'TRANS_DETAIL'=> array(
                             'SN' => $account1['id'],
                             'E_USER_CODE'=> $account1['id'],
                             'BANK_CODE'=> '',
                             'ACCOUNT_TYPE'=> '00',
                             'ACCOUNT_NO'=> $account1['card_no'],
                             'ACCOUNT_NAME'=> $account1['name'],
                             'PROVINCE'=> '',
                             'CITY'=> '',
                             'BANK_NAME'=> '',
                             'ACCOUNT_PROP'=> '0',
                             'AMOUNT'=> $balance1,
                             'CURRENCY'=> 'CNY',
                             'PROTOCOL'=> '',
                             'PROTOCOL_USERID'=> '',
                             'ID_TYPE'=> '',
                             'ID'=> '',
                             'TEL'=> $account1['mobile'],
                             'CUST_USERID'=> '用户自定义号',
                             'REMARK'=> '备注信息1',
                             'SETTACCT'=> '',
                             'SETTGROUPFLAG'=> '',
                             'SUMMARY'=> '',
                             'UNION_BANK'=> '010538987654',
                          ),
                       'TRANS_DETAIL2'=> array(
                             'SN' => $account2['id'],
                             'E_USER_CODE'=> $account2['id'],
                             'BANK_CODE'=> '',
                             'ACCOUNT_TYPE'=> '00',
                             'ACCOUNT_NO'=> $account2['card_no'],
                             'ACCOUNT_NAME'=> $account2['name'],
                             'PROVINCE'=> '',
                             'CITY'=> '',
                             'BANK_NAME'=> '',
                             'ACCOUNT_PROP'=> '0',
                             'AMOUNT'=> $balance2,
                             'CURRENCY'=> 'CNY',
                             'PROTOCOL'=> '',
                             'PROTOCOL_USERID'=> '',
                             'ID_TYPE'=> '',
                             'ID'=> '',
                             'TEL'=> $account2['mobile'],
                             'CUST_USERID'=> '用户自定义号',
                             'REMARK'=> '备注信息1',
                             'SETTACCT'=> '',
                             'SETTGROUPFLAG'=> '',
                             'SUMMARY'=> '',
                             'UNION_BANK'=> '010538987654',
                          ),
                       'TRANS_DETAIL2'=> array(
                             'SN' => $account3['id'],
                             'E_USER_CODE'=> $account3['id'],
                             'BANK_CODE'=> '',
                             'ACCOUNT_TYPE'=> '00',
                             'ACCOUNT_NO'=> $account3['card_no'],
                             'ACCOUNT_NAME'=> $account3['name'],
                             'PROVINCE'=> '',
                             'CITY'=> '',
                             'BANK_NAME'=> '',
                             'ACCOUNT_PROP'=> '0',
                             'AMOUNT'=> $balance3,
                             'CURRENCY'=> 'CNY',
                             'PROTOCOL'=> '',
                             'PROTOCOL_USERID'=> '',
                             'ID_TYPE'=> '',
                             'ID'=> '',
                             'TEL'=> $account3['mobile'],
                             'CUST_USERID'=> '用户自定义号',
                             'REMARK'=> '备注信息1',
                             'SETTACCT'=> '',
                             'SETTGROUPFLAG'=> '',
                             'SUMMARY'=> '',
                             'UNION_BANK'=> '010538987654',
                          ),
                       'TRANS_DETAIL2'=> array(
                             'SN' => $account4['id'],
                             'E_USER_CODE'=> $account4['id'],
                             'BANK_CODE'=> '',
                             'ACCOUNT_TYPE'=> '00',
                             'ACCOUNT_NO'=> $account4['card_no'],
                             'ACCOUNT_NAME'=> $account4['name'],
                             'PROVINCE'=> '',
                             'CITY'=> '',
                             'BANK_NAME'=> '',
                             'ACCOUNT_PROP'=> '0',
                             'AMOUNT'=> $balance4,
                             'CURRENCY'=> 'CNY',
                             'PROTOCOL'=> '',
                             'PROTOCOL_USERID'=> '',
                             'ID_TYPE'=> '',
                             'ID'=> '',
                             'TEL'=> $account4['mobile'],
                             'CUST_USERID'=> '用户自定义号',
                             'REMARK'=> '备注信息1',
                             'SETTACCT'=> '',
                             'SETTGROUPFLAG'=> '',
                             'SUMMARY'=> '',
                             'UNION_BANK'=> '010538987654',
                          )
                  )
             ),
         );
         //发起请求
         $result = $tools->send( $params);
         if($result!=FALSE){
             echo  '验签通过，请对返回信息进行处理';
             //下面商户自定义处理逻辑，此处返回一个数组
         }else{
                 print_r("验签结果：验签失败，请检查通联公钥证书是否正确");
         }       
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

     //发送请求操作仅供参考,不为最佳实践
    static function request($url,$params){
        $ch = curl_init();
        $this_header = array("content-type: application/x-www-form-urlencoded;charset=UTF-8");
        curl_setopt($ch,CURLOPT_HTTPHEADER,$this_header);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
         
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);//如果不加验证,就设false,商户自行处理
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
         
        $output = curl_exec($ch);
        curl_close($ch);
        return  $output;
    }

    //验签
    static function validSign($array){
        if("SUCCESS"==$array["retcode"]){
            $signRsp = strtolower($array["sign"]);
            $array["sign"] = "";
            $sign =  strtolower(AppUtil::SignArray($array, AppConfig::APPKEY));
            if($sign==$signRsp){
                return TRUE;
            }
            else {
                echo "验签失败:".$signRsp."--".$sign;
            }
        }
        else{
            echo $array["retmsg"];
        }
        
        return FALSE;
    }

    static function xmlToArray($xml) {

    //禁止引用外部xml实体
    libxml_disable_entity_loader(true);
    $values = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
    return $values;
    }

    //通联单笔代付
    static function allinpayDf(){
        $tools=new PhpTools();
        $merchantId=AppConfig::MERCHANT_ID;
        // 源数组
        $data = array(
            'INFO' => array(
                'TRX_CODE' => '100014',
                'VERSION' => '03',
                'DATA_TYPE' => '2',
                'LEVEL' => '6',
                'USER_NAME' => '20060400000044502',
                'USER_PASS' => '111111',
                'REQ_SN' => $merchantId.date('YmdHis').rand(1000,9999),
            ),
            'TRANS' => array(
                'BUSINESS_CODE' => '09400',
                'MERCHANT_ID' => $merchantId,
                'SUBMIT_TIME' => date('YmdHis'),
                'E_USER_CODE' => '10101328',
                'BANK_CODE' => '',
                'ACCOUNT_TYPE' => '00',
                'ACCOUNT_NO' => '6227002021490888887',
                'ACCOUNT_NAME' => '潜非凡',
                'ACCOUNT_PROP' => '0',
                'AMOUNT' => '1',
                'CURRENCY' => 'CNY',
                'ID_TYPE' => '0',
                'CUST_USERID' => '2901347',
                'SUMMARY' => '春风贷提现',
                'REMARK' => '',
            ),
        );

        //发起请求
        $result = $tools->send($data);
        if($result!=FALSE){
            echo  '验签通过，请对返回信息进行处理';
            return true;
            //下面商户自定义处理逻辑，此处返回一个数组
        }else{
            return false;
                print_r("验签结果：验签失败，请检查通联公钥证书是否正确");
        }
    }

    /**
     * 导出excel
     * @param $strTable 表格内容
     * @param $filename 文件名
     */
    static function downloadExcel($strTable,$filename)
    {
        header("Content-type: application/vnd.ms-excel");
        header("Content-Type: application/force-download");
        header("Content-Disposition: attachment; filename=".$filename."_".date('Y-m-d').".xls");
        header('Expires:0');
        header('Pragma:public');
        echo '<html><meta http-equiv="Content-Type" content="text/html; charset=utf-8" />'.$strTable.'</html>';
    }

    /**
     * 生成代理商激活码
     * @param 
     * @param 
     */
    static function makePromoterCode($namespace = null)
    {
        static $guid = '';  
        $uid = uniqid ( "", true );  
          
        $data = $namespace;  
        $data .= $_SERVER ['REQUEST_TIME'];     // 请求那一刻的时间戳  
        $data .= $_SERVER ['HTTP_USER_AGENT'];  // 获取访问者在用什么操作系统  
        $data .= $_SERVER ['SERVER_ADDR'];      // 服务器IP  
        $data .= $_SERVER ['SERVER_PORT'];      // 端口号  
        $data .= $_SERVER ['REMOTE_ADDR'];      // 远程IP  
        $data .= $_SERVER ['REMOTE_PORT'];      // 端口信息  
          
        $hash = strtoupper ( hash ( 'ripemd128', $uid . $guid . md5 ( $data ) ) );  
        $guid = substr ( $hash, 0, 8 ) . '-' . substr ( $hash, 8, 4 ) . '-' . substr ( $hash, 12, 4 ) . '-' . substr ( $hash, 16, 4 ) . '-' . substr ( $hash, 20, 12 );  
          
        return $guid; 
    }

    static function getLnglat($address){
        $url = "http://restapi.amap.com/v3/geocode/geo?address=".$address."&output=JSON&key=12303bfdb8d40d67fa696d5bbfdcf595";
        $result = file_get_contents($url);
        $return = json_decode($result,true);
        if($return['status']==1){
            $location = $return['geocodes'][0]['location'];
            $str = explode(',',$location);
            $lng = $str[0];
            $lat = $str[1];
        }else{
            $lng = 0;
            $lat = 0;
        }
        
        $array = array(
            'lng'=>$lng,
            'lat'=>$lat  
            );
        return $array;
    }

    static function getBankcardTpyeCode($bank_num) {
        $url = 'https://ccdcapi.alipay.com/validateAndCacheCardInfo.json?_input_charset=utf-8&cardNo=' . $bank_num.'&cardBinCheck=true';
        $card = self::httpRequest($url,'GET');
        $card = json_decode($card,true);
        return $card;
    }

    static function getBankcardTpye($bank_num) {
        $url = 'http://apicloud.mob.com/appstore/bank/card/query?key=1f4d2d20dd266&card='.$bank_num;
        $return = self::httpRequest($url, 'GET');
        $re = json_decode($return, TRUE);
        return $re;
    }

     /**
     * CURL请求
     * @param $url 请求url地址
     * @param $method 请求方法 get post
     * @param null $postfields post数据数组
     * @param array $headers 请求header信息
     * @param bool|false $debug  调试开启 默认false
     * @return mixed
     */
    static function httpRequest($url, $method="GET", $postfields = null, $headers = array(), $debug = false) {
        $method = strtoupper($method);
        $ci = curl_init();
        /* Curl settings */
        curl_setopt($ci, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        curl_setopt($ci, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.2; WOW64; rv:34.0) Gecko/20100101 Firefox/34.0");
        curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, 60); /* 在发起连接前等待的时间，如果设置为0，则无限等待 */
        curl_setopt($ci, CURLOPT_TIMEOUT, 7); /* 设置cURL允许执行的最长秒数 */
        curl_setopt($ci, CURLOPT_RETURNTRANSFER, true);
        switch ($method) {
            case "POST":
                curl_setopt($ci, CURLOPT_POST, true);
                if (!empty($postfields)) {
                    $tmpdatastr = is_array($postfields) ? http_build_query($postfields) : $postfields;
                    curl_setopt($ci, CURLOPT_POSTFIELDS, $tmpdatastr);
                }
                break;
            default:
                curl_setopt($ci, CURLOPT_CUSTOMREQUEST, $method); /* //设置请求方式 */
                break;
        }
        $ssl = preg_match('/^https:\/\//i',$url) ? TRUE : FALSE;
        curl_setopt($ci, CURLOPT_URL, $url);
        if($ssl){
            curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, FALSE); // https请求 不验证证书和hosts
            curl_setopt($ci, CURLOPT_SSL_VERIFYHOST, FALSE); // 不从证书中检查SSL加密算法是否存在
        }
        //curl_setopt($ci, CURLOPT_HEADER, true); /*启用时会将头文件的信息作为数据流输出*/
        curl_setopt($ci, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ci, CURLOPT_MAXREDIRS, 2);/*指定最多的HTTP重定向的数量，这个选项是和CURLOPT_FOLLOWLOCATION一起使用的*/
        curl_setopt($ci, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ci, CURLINFO_HEADER_OUT, true);
        /*curl_setopt($ci, CURLOPT_COOKIE, $Cookiestr); * *COOKIE带过去** */
        $response = curl_exec($ci);
        $requestinfo = curl_getinfo($ci);
        $http_code = curl_getinfo($ci, CURLINFO_HTTP_CODE);
        if ($debug) {
            echo "=====post data======\r\n";
            var_dump($postfields);
            echo "=====info===== \r\n";
            print_r($requestinfo);
            echo "=====response=====\r\n";
            print_r($response);
        }
        curl_close($ci);
        return $response;
        //return array($http_code, $response,$requestinfo);
    }

    static function getDistanceByLatLng($lat1, $lng1, $lat2, $lng2){   
        $earthRadius = 6367000; //approximate radius of earth in meters   
        $lat1 = ($lat1 * pi() ) / 180;   
        $lng1 = ($lng1 * pi() ) / 180;   
        $lat2 = ($lat2 * pi() ) / 180;   
        $lng2 = ($lng2 * pi() ) / 180;   
        $calcLongitude = $lng2 - $lng1;   
        $calcLatitude = $lat2 - $lat1;   
        $stepOne = pow(sin($calcLatitude / 2), 2) + cos($lat1) * cos($lat2) * pow(sin($calcLongitude / 2), 2);   
        $stepTwo = 2 * asin(min(1, sqrt($stepOne)));   
        $calculatedDistance = $earthRadius * $stepTwo;   
        return round($calculatedDistance);                     
    }

    static function getIp(){
        if (getenv("HTTP_CLIENT_IP") && strcasecmp(getenv("HTTP_CLIENT_IP"), "unknown"))
        $ip = getenv("HTTP_CLIENT_IP");
        else if (getenv("HTTP_X_FORWARDED_FOR") && strcasecmp(getenv("HTTP_X_FORWARDED_FOR"), "unknown"))
        $ip = getenv("HTTP_X_FORWARDED_FOR");
        else if (getenv("REMOTE_ADDR") && strcasecmp(getenv("REMOTE_ADDR"), "unknown"))
        $ip = getenv("REMOTE_ADDR");
        else if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], "unknown"))
        $ip = $_SERVER['REMOTE_ADDR'];
        else
        $ip = "unknown";
        return $ip;
    }

    static function getPreviousLevel($id=0){
       $model = new Model('goods_category');
       $category = $model->where('id='.$id)->find();
       if($category){
         if($category['parent_id']!=0){
            $pid = $category['parent_id'];
           }else{
             $pid = $id;
           }
       }else{
        $pid = $id;
       }
       
       return $pid; 
    }

    static function arraySequence($array, $field, $sort = 'SORT_DESC')
    {
        $arrSort = array();
        foreach ($array as $uniqid => $row) {
            foreach ($row as $key => $value) {
                $arrSort[$key][$uniqid] = $value;
            }
        }
        array_multisort($arrSort[$field], constant($sort), $array);
        return $array;
    } 

    static function objectToArray($obj)
    {
        $obj = (array)$obj;  
        foreach ($obj as $k => $v) {  
            if (gettype($v) == 'resource') {  
                return;  
            }  
            if (gettype($v) == 'object' || gettype($v) == 'array') {  
                $obj[$k] = (array)self::objectToArray($v);  
            }  
        }  
       
        return $obj;
    }

    static function getAllChildShops($user_id)
    {
        $model = new Model();
        //根据所属上级关系找到下级所有经销商
        $is_break = false; //false继续 true停止
        $shop_ids = '';
        $user_ids = '';
        $shop_ids_arr = array();
        $user_ids_arr = array();
        $num = 0;
        $shop = $model->table("district_shop")->fields('id,owner_id')->where("owner_id=".$user_id)->find();
        if($shop) {
            $now_user_id = $shop['id'];
            while(!$is_break){
                $inviter_info = $model->table("district_shop")->fields('id,owner_id,is_oc')->where("invite_shop_id in (".$now_user_id.")")->findAll();
                if($inviter_info){
                    $now_user_id = '';
                    foreach ($inviter_info as $k => $v) {
                        if($v['is_oc'] == 0){//不是运营中心
                            $shop_ids_arr[] = $v['id'];
                            $user_ids_arr[] = $v['owner_id'];
                            $shop_ids = $shop_ids_arr!=null?implode(',', $shop_ids_arr):'';
                            $user_ids = $user_ids_arr!=null?implode(',', $user_ids_arr):'';
                            $num = $num+1;
                            $now_user_id = $now_user_id==''?$v['id']:$now_user_id.','.$v['id'];
                        }
                    }    
                    if($now_user_id == ''){
                        $is_break = true;
                    }
                }else{
                    $is_break = true;
                }
            }
        }
        
        $result = array();
        $result['shop_ids'] = $shop_ids;
        $result['user_ids'] = $user_ids;
        $result['shop_ids_arr'] = $shop_ids_arr;
        $result['user_ids_arr'] = $user_ids_arr;
        $result['num'] = $num;
        return $result;
    }

    static function getAllChildPromotersIds($user_id)
    {
        $model = new Model();
        //根据所属上级关系找到下级所有代理商
        $is_break = false; //false继续 true停止
        $promoter_user_id = '';
        $num = 0;
        $shop = $model->table("district_shop")->fields('id,owner_id')->where("owner_id=".$user_id)->find();
        $idstr = self::getAllChildShops($user_id);
        $now_user_id = $idstr['shop_ids']==''?$shop['id']:$shop['id'].','.$idstr['shop_ids'];
        $inviter_info = $model->table("district_promoter")->fields('id,user_id')->where("hirer_id in (".$now_user_id.")")->findAll();
        $ids = array();
        if($inviter_info) {
            foreach($inviter_info as $k =>$v) {
               $ids[] = $v['user_id'];
            }
        }
        $promoter_ids = $ids!=null?implode(',', $ids):'';
        $result = array();
        $result['user_ids'] = $promoter_ids;
        $result['num'] = count($inviter_info);
        return $result;    
    }

    static function getAllChildUserIds($user_id)
    {
       $model = new Model();
       $is_break = false;
       $num = 0;
       $now_user_id = $user_id;
       $idstr = '';
       $ids = array();
       while(!$is_break) {
          $inviter_info = $model->table("invite")->where("user_id=".$now_user_id)->findAll();
          if($inviter_info) {
            foreach($inviter_info as $k =>$v) {
               $ids[] = $v['invite_user_id'];
               $num = $num+1;
               $now_user_id = $v['invite_user_id'];
            }
          } else {
            $is_break = true;
          }
          $idstr = $ids!=null?implode(',', $ids):'';
       }
       $result['user_ids'] = $idstr;
       $result['num'] = $num;
       return $result;
    }

    static function getFirstDistrictId($user_id)
    {
        $model = new Model();
        $inviter_info = $model->table("invite")->where("invite_user_id=".$user_id)->find();

        if($inviter_info) {
            $district = $model->table('district_shop')->fields('owner_id')->where('id='.$inviter_info['district_id'])->find();
            if($district) {
                $district_id = $district['owner_id'];
            } else {
                $district_id = 1;
            }
        } else {
            $district_id = 1;
        }
        return $district_id;
    }

    static function getMyAllInviters($user_id)
    {
        //获取所有邀请的会员
        $model = new Model();
        $invite = $model->table('invite')->fields('invite_user_id')->where('user_id='.$user_id)->findAll();
        $num = $model->table('invite')->fields('count(id) as num')->where('user_id='.$user_id)->findAll();
        $idstr = '';
        $ids = array();
        if($invite) {
            foreach($invite as $k =>$v) {
               $ids[] = $v['invite_user_id'];
            }
        }
        $idstr = $ids!=null?implode(',', $ids):'';
        $result['num'] = $num[0]['num'];
        $result['idstr'] = $idstr;
        return $result;
    }

    static function getMyAllPromoter($user_id)
    {
        //获取所有邀请的会员
        $model = new Model();
        
        $district = $model->table('district_shop')->fields('id')->where('owner_id='.$user_id)->find();
       
        $invite = $model->table('district_promoter')->fields('user_id')->where('hirer_id='.$district['id'])->findAll();
        
        $num = $model->table('district_promoter')->fields('count(id) as num')->where('hirer_id='.$district['id'])->findAll();
        
        $idstr = '';
        $ids = array();
        if($invite) {
            foreach($invite as $k =>$v) {
               $ids[] = $v['user_id'];
            }
        }
        $idstr = $ids!=null?implode(',', $ids):'';
        $result['num'] = $num[0]['num'];
        $result['idstr'] = $idstr;
        return $result;
    }

    static function replace_specialChar($str)
    {
        $str = str_replace('`', '', $str);
        $str = str_replace('·', '', $str);
        $str = str_replace('~', '', $str);
        $str = str_replace('!', '', $str);
        $str = str_replace('！','', $str);
        $str = str_replace('@', '', $str);
        $str = str_replace('#', '', $str);
        $str = str_replace('$', '', $str);
        $str = str_replace('￥', '', $str);
        $str = str_replace('%', '', $str);
        $str = str_replace('^', '', $str);
        $str = str_replace('……', '', $str);
        $str = str_replace('&', '', $str);
        $str = str_replace('*', '', $str);
        $str = str_replace('(', '', $str);
        $str = str_replace(')', '', $str);
        $str = str_replace('（', '', $str);
        $str = str_replace('）', '', $str);
        $str = str_replace('-', '', $str);
        $str = str_replace('_', '', $str);
        $str = str_replace('——', '', $str);
        $str = str_replace('+', '', $str);
        $str = str_replace('=', '', $str);
        $str = str_replace('|', '', $str);
        $str = str_replace('\\', '', $str);
        $str = str_replace('[', '', $str);
        $str = str_replace(']', '', $str);
        $str = str_replace('【', '', $str);
        $str = str_replace('】', '', $str);
        $str = str_replace('{', '', $str);
        $str = str_replace('}', '', $str);
        $str = str_replace(';', '', $str);
        $str = str_replace('；', '', $str);
        $str = str_replace(':', '', $str);
        $str = str_replace('：', '', $str);
        $str = str_replace('\'', '', $str);
        $str = str_replace('"', '', $str);
        $str = str_replace('“', '', $str);
        $str = str_replace('”', '', $str);
        $str = str_replace(',', '', $str);
        $str = str_replace('，', '', $str);
        $str = str_replace('<', '', $str);
        $str = str_replace('>', '', $str);
        $str = str_replace('《', '', $str);
        $str = str_replace('》', '', $str);
        $str = str_replace('.', '', $str);
        $str = str_replace('。', '', $str);
        $str = str_replace('/', '', $str);
        $str = str_replace('、', '', $str);
        $str = str_replace('?', '', $str);
        $str = str_replace('？', '', $str);
        return trim($str);
    }

}
