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
    
    static function getExpress($com,$num){
        $post_data = array();
        $post_data["customer"] = '440B82938FC63D49F59D8048D7481D90';
        $key= 'fLnyAwGu1227' ;
        $post_data["param"] = '{"com":"'.$com.'","num":"'.$num.'"}';

        $url='http://poll.kuaidi100.com/poll/query.do';
        $post_data["sign"] = md5($post_data["param"].$key.$post_data["customer"]);
        $post_data["sign"] = strtoupper($post_data["sign"]);
        $o=""; 
        foreach ($post_data as $k=>$v)
        {
            $o.= "$k=".urlencode($v)."&";		//默认UTF-8编码格式
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
    /*
     * 自动为新用户创建优惠券
     */
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
                            $v['open_name'] = $v['open_name']==""?"买一点用户":$v['open_name'];
                            $params = array(
                                'touser'=>$v['open_id'],
                                'msgtype'=>'news',
                                'news'=>array(
                                    'articles'=>array('0'=>array(
                                        'title'=>'买一点温馨提示',
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
    /*
     * 格式化时间显示
     */    
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
    /*
     * 格式化数据到echart中显示
     */   
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
     * 解析华币订单
     */
    static function parserHuadianOrder($goods_arr,$product_num,$product_amount){
            
            if(empty($goods_arr)||empty($product_num)||empty($product_amount)){
                return false;
            }
            $goods_ids = implode(',', $goods_arr);
            $goods = new Model('goods');
            $huabipay_result = $goods->where("id in ($goods_ids) and is_huabipay = 1")->fields('id,huabipay_set')->findAll();
            if(empty($huabipay_result)){
                return false;
            }else{
                $pay_set = array();
                foreach ($huabipay_result as $k=>$v){
                    if($v['huabipay_set']!=""){
                        $set = unserialize($v['huabipay_set']);
                        if(is_array($set)){
                            foreach($set as $k=>$v){
                                $pay_set[$k]=$v;
                            }
                        }
                    }
                }
                if(empty($pay_set)){
                    return false;
                }else{
                    $config = Config::getInstance();
                    $other = $config->get('other');
                    if(!isset($other['rmb2huabi'])){
                        return false;
                    }else{
                        $rmb2huadian = $other['rmb2huabi'];
                    }
                    $huadian = 0;
                    $rmb = 0;
                    foreach ($product_num as $k =>$v){
                        if(isset($pay_set[$k])){
                            if($pay_set[$k]['type']=='rate'){
                                   $huadian += round($pay_set[$k]['value']*$product_amount[$k]/100*$rmb2huadian);
                                   $rmb += round((100 - $pay_set[$k]['value'])*$product_amount[$k]/100,2);
                                   
                                   if($huadian<0){
                                       return false;
                                   }
                            }else if($pay_set[$k]['type']=='fixed'){
                                   $huadian += round($pay_set[$k]['value']['huadian']*$v);
                                   $rmb += round($pay_set[$k]['value']['rmb']*$v,2);
                                   if($huadian<0){
                                       return false;
                                   }
                            }
                        }
                    }
                    if($rmb<0){
                        $rmb =0;
                    }
                    return array('huadian'=>$huadian,'rmb'=>$rmb,'rmb2huadian'=>$rmb2huadian);
                }
            }
     }
     
     /*
      * 获取可提现的金点
      * 规则：直豆可以随时提现，也不受提现有效期限制。续豆需要锁定7天，而且受有效期限制。
      */
    static function getCanWithdrawAmount4GoldCoin($user_id){
            $model = new Model();
            $all_gold = $model->table('customer')->where('user_id='.$user_id)->fields("balance,withdraw_deadline")->find();
            if($all_gold){
                //如果总提现期未过期
                //可提现=总-还在锁定的续豆
                if(strtotime($all_gold['withdraw_deadline'])>=time()){ 
                    //未过提现期
                    //查询未解锁（未超过7天）的未使用完的续豆
                    $timelock_xudou = $model
                            ->table('platform_return')
                            ->fields("SUM(`loss_amount`) as all_loss")
                            ->where('user_id='.$user_id." and return_type =2 and is_all_used =0 and can_withdraw_date>'".date("Y-m-d H:i:s")."'")
                            ->find();
                    if(isset($timelock_xudou['all_loss'])){
                        $timelock_xudou_amount = $timelock_xudou['all_loss']==NULL?0.00:$timelock_xudou['all_loss'];
                        $can_withdraw_amount = $all_gold['balance']-$timelock_xudou_amount;
                        return $can_withdraw_amount;
                    }else{
                        return $all_gold['balance'];
                    }
                }else{
                   //过了提现期
                   //查询所有未使用的续豆
                    $xudou = $model
                            ->table('platform_return')
                            ->fields("SUM(`loss_amount`) as all_loss")
                            ->where('user_id='.$user_id." and return_type =2 and is_all_used =0")
                            ->find();
                    
                    if(isset($xudou['all_loss'])){
                        $xudou_amount =  $xudou['all_loss']==NULL?0.00:$xudou['all_loss'];
                        $can_withdraw_amount = $all_gold['balance']-$xudou_amount;
                        return $can_withdraw_amount;
                    }else{
                        return $all_gold['balance'];
                    }
                }
            }else{
                return false;
            }
            
     }
     /*
      * 记录金点使用（仅记录外平台返回的金点）（已进行扣除操作）
      * 规则：根据 充值金点->直豆->续豆 的使用的顺序记录
      * 例如，账户上有500金点，300充值金点，100直豆，100续豆，此时使用了420
      * 则依次添加使用记录 100 直豆 20续豆
      * 而且将 100 直豆 20续豆根据获取情况记录到platform_return 和platform_usedetail表
      */
    static function recordUseDetail4GoldCoin($user_id,$type,$amount,$origin_id){
         $model = new Model();
         $all_gold = $model->table('customer')->where('user_id='.$user_id)->fields("balance")->find();
         if($all_gold&&$all_gold['balance']>$amount){
           $all_gold['balance']+=$amount;
           $platform_return = $model
                            ->table('platform_return')
                            ->where("user_id=".$user_id." and is_all_used =0")
                            ->fields("SUM(`loss_amount`) as all_loss")
                            ->find();
           if(isset($platform_return['all_loss'])&&$platform_return['all_loss']!=NULL){
               //判断使用的金点的成分
               $not_platform = $all_gold['balance'] - $platform_return['all_loss'];
               if($amount>$not_platform){
                   $use_platform_amount = $amount-$not_platform;
                   //开始记录了
                   //1：查找外平台返回记录
                   $record = $model->table('platform_return')->where("user_id=".$user_id." and is_all_used = 0")->order("return_type asc")->findAll();
                   if($record){
                       $record_amount =0.00;
                       $need_record = $use_platform_amount;
                       foreach ($record as $v){
                           if($record_amount==$use_platform_amount||$need_record==0.00){
                               break;
                           }
                           if($need_record>=$v['loss_amount']){
                               $record_amount+=$v['loss_amount'];
                               $need_record -=$v['loss_amount'];
                               $update['is_all_used']=1;
                               $update['loss_amount']=0.00;
                               $update['used_amount']=$v['return_amount'];
                               $insert['use_amount']=$v['loss_amount'];
                           }else{
                               $record_amount+=$need_record;
                               $update['is_all_used']=0;
                               $update['loss_amount']=$v['loss_amount']-$need_record;
                               $update['used_amount']=$v['used_amount']+$need_record;
                               $insert['use_amount']=$need_record;
                               $need_record =0.00;
                           }
                           $insert['platform_record_id']=$v['id'];
                           $insert['coin_type'] =$v['return_type'];//直豆还是续豆
                           $insert['origin_type']= $type; // 1:提现 2：转银点 3：消费
                           $insert['origin_id']= $origin_id;
                           $insert['sync_status']=$type==1? 0 : 9;//是否需要同步到外平台
                           $model->table("platform_return")->where("id=".$v['id'])->data($update)->update();
                           $model->table('platform_usedetail')->data($insert)->insert();
                       }
                        return true;
                   }
               }else{
                   //如果未使用到平台返回的金点
                   return true;
               }
           }else{//没有使用到外平台返回的金点
               return true;
           }
         }else{
             return false;
         }
     }
     /*
      * 当前按照要求，银点分为三类，
      * 1：充值来源（无限制）   
      * 2：充值套餐赠送（有时效|无时效）【只能消费套餐区商品】 【有时间限制的银点到期时会自动减扣，在定时任务中】
      * 3：充值活动赠送【有限制|无限制(华点订单)】
      */
    static function getSilverCoinComponent($user_id){
         $model = new Model();
         $silver = $model->table('customer')->where("user_id=".$user_id)->fields("silver_coin")->find();
         if($silver){
              $all = round($silver['silver_coin'],2);
              //查询套餐赠送的剩余银点
              $package_limit_silver = $model
                      ->table('silver_limit')
                      ->fields("SUM(`loss_amount`) as loss")
                      ->where("user_id =".$user_id." and is_dead =0 and is_all_used =0")
                      ->find();
              //查询充值活动赠送的不能用于华点支付的银点
              $send_silver_limit = $model 
                      ->table("recharge_sendsilver")
                      ->fields("SUM(`loss_amount`) as loss")
                      ->where("user_id = $user_id and is_all_used =0 and huadian_limit = 1")
                      ->find();
              //查询充值活动赠送没有限制的银点
              $send_silver_no_limit = $model 
                      ->table("recharge_sendsilver")
                      ->fields("SUM(`loss_amount`) as loss")
                      ->where("user_id = $user_id and is_all_used =0 and huadian_limit = 0")
                      ->find();
              
              $package_limit_silver = (empty($package_limit_silver)||$package_limit_silver['loss']==NULL)?0.00:round($package_limit_silver['loss'],2);
              $send_silver_limit  = (empty($send_silver_limit)||$send_silver_limit['loss']==NULL)?0.00:round($send_silver_limit['loss'],2);
              $send_silver_no_limit  = (empty($send_silver_no_limit)||$send_silver_no_limit['loss']==NULL)?0.00:round($send_silver_no_limit['loss'],2);
              $other_silver = round($all - $package_limit_silver - $send_silver_limit-$send_silver_no_limit,2);
              return array('all'=>$all,'package_limit_silver'=>$package_limit_silver,'send_silver_limit'=>$send_silver_limit,'send_silver_no_limit'=>$send_silver_no_limit,'other_silver'=>$other_silver);
         }else{
             return false;
         }
     }
     /*
      * 获取订单中套餐区商品价格总和
      */
    static function getPackageAreaGoodsAmount($order_no){
         $model = new Model();
         $order = $model->table("order")->where("order_no='".$order_no."'")->fields("order_amount,id")->find();
         if($order){
             $order_goods_nums = $model->table("order_goods")->where("order_id ={$order['id']}")->count();
             $package_goods = $model->table("order_goods as og")->join("goods as g on og.goods_id = g.id")->where("og.order_id = {$order['id']} and g.is_package =1")
             ->fields("og.real_price,og.goods_price,goods_nums")->findAll();
             if(!empty($package_goods)){
                 if($order_goods_nums==count($package_goods)){
                     return array("all"=>$order['order_amount'],'package_amount'=>$order['order_amount'],'other_amount'=>0.00,'order_id'=>$order['id']);
                 }
                 $package_amount = 0.00;
                 foreach($package_goods as $v){
                     $package_amount += $v['real_price']*$v['goods_nums'];
                 }
                 if($package_amount>=$order['order_amount']){
                     $package_amount = $order['order_amount'];
                 }
                 return array("all"=>$order['order_amount'],'package_amount'=>$package_amount,'other_amount'=>round($order['order_amount']-$package_amount,2),'order_id'=>$order['id']);
             }else{
                 return array("all"=>$order['order_amount'],"package_amount"=>0.00,'other_amount'=>$order['order_amount'],'order_id'=>$order['id']);
             }
         }else{
             return false;
         }
     }
     /*
      * 记录定向银点支出记录
      */
     static function recordUseDetail4LimitSilverCoin($user_id,$use_amount,$order_id){
         if($use_amount>0){
             $model = new Model();
             $limit_silver = $model->table('silver_limit')->where('is_all_used = 0 and is_dead =0 and user_id ='.$user_id)->order("timelimit desc,id asc")->findAll();
             if(!empty($limit_silver)){
                 $record_amount =0.00;
                 $need_record = $use_amount;
                 foreach($limit_silver as $v){
                           if($record_amount==$use_amount||$need_record==0.00){
                               break;
                           }
                           if($need_record>=$v['loss_amount']){
                               $record_amount+=$v['loss_amount'];
                               $need_record -=$v['loss_amount'];
                               $update['is_all_used']=1;
                               $update['loss_amount']=0.00;
                               $update['used_amount']=$v['recharge_amount'];
                               $insert['use_amount']=$v['loss_amount'];
                           }else{
                               $record_amount+=$need_record;
                               $update['is_all_used']=0;
                               $update['loss_amount']=$v['loss_amount']-$need_record;
                               $update['used_amount']=$v['used_amount']+$need_record;
                               $insert['use_amount']=$need_record;
                               $need_record =0.00;
                           }
                           $insert['limit_record_id']=$v['id'];
                           $insert['order_id']= $order_id;
                           $insert['status']=0;
                           $model->table("silver_limit")->where("id=".$v['id'])->data($update)->update();
                           $model->table('silver_limitusedetail')->data($insert)->insert();
                }
                 return $record_amount;
             }else{
                 return 0.00;
             }
         }else{
             return false;
         }
     }
     
    static function getSilverDetail($user_id){
         $model = new Model();
         $silver = $model->table('customer')->where("user_id=".$user_id)->fields("silver_coin")->find();
         if($silver){
              $all = round($silver['silver_coin'],2);
              $limit_silver = $model
                      ->table('silver_limit')
                      ->fields("loss_amount,timelimit,dead_line")
                      ->where("user_id =".$user_id." and is_dead =0 and is_all_used =0")
                      ->findAll();
              //查询充值活动赠送的不能用于华点支付的银点
              $send_silver_limit = $model 
                      ->table("recharge_sendsilver")
                      ->fields("SUM(`loss_amount`) as loss")
                      ->where("user_id = $user_id and is_all_used =0 and huadian_limit = 1")
                      ->find();
              //查询充值活动赠送没有限制的银点
              $send_silver_no_limit = $model 
                      ->table("recharge_sendsilver")
                      ->fields("SUM(`loss_amount`) as loss")
                      ->where("user_id = $user_id and is_all_used =0 and huadian_limit = 0")
                      ->find();
              $direct_silver = 0.00;
              $direct_silver_valid =array();
              $direct_silver_valid[0] = array('value'=>0.00,'name'=>'永久有效');
              if(!empty($limit_silver)){
                  foreach($limit_silver as $v){
                      $direct_silver += $v['loss_amount'];
                      if($v['timelimit']==0){
                          $direct_silver_valid[0]['value']+=$v['loss_amount'];
                      }else{
                          $direct_silver_valid[]=array('value'=>$v['loss_amount'],'name'=>$v['dead_line']."过期");
                      }
                  }
                  if($direct_silver_valid[0]['value']==0){
                      unset($direct_silver_valid[0]);
                  }
              }else{
                  $direct_silver_valid =array();
              }
              $return['all']=$all;
              $return['send_silver_huadianlimit']= (empty($send_silver_limit)||$send_silver_limit['loss']==NULL)?0.00:$send_silver_limit['loss'];
              $return['send_silver_nohuadianlimit']= (empty($send_silver_no_limit)||$send_silver_no_limit['loss']==NULL)?0.00:$send_silver_no_limit['loss'];
              $return['package_limit_silver']=round($direct_silver,2);//定向套餐银点
              $return['other_silver']=round($all-$direct_silver-$return['send_silver_huadianlimit']-$return['send_silver_nohuadianlimit'],2);//普通银点
              $return['package_limit_silver_detail']=$direct_silver_valid;//定向套餐银点详情
              return $return;
         }else{
             return false;
         }
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
                      $income['type']=3;
                      $income['amount']=$fee*$rate;
                      $income['role_type']=2;
                      $income['role_id']=$data['invite_shop_id'];
                      $income['record_time']=date("Y-m-d H:i:s");
                      $income['origin']=$isOk;
                      $income['type_info']="拓展小区加盟费分成";
                      $income['status']=0;
                      $model->table("district_incomelog")->data($income)->insert();
                      $model->table("district_shop")->data(array("frezze_income"=>"`frezze_income`+".$income['amount']))->where("id=".$data['invite_shop_id'])->update();
                  }
                  $result = $model->table('district_apply')->where("id=$apply_id")->data(array('status'=>1))->update();
                  if($result){
                    $oauth_info = $model->table("oauth_user")->fields("open_id,open_name")->where("user_id=".$apply_info['user_id']." and oauth_type='wechat'")->find();
                    if(!empty($oauth_info)){
                        $wechatcfg = $model->table("oauth")->where("class_name='WechatOAuth'")->find();
                        $wechat = new WechatMenu($wechatcfg['app_key'], $wechatcfg['app_secret'], '');
                        $token = $wechat->getAccessToken();
                        $oauth_info['open_name'] = $oauth_info['open_name']==""?"买一点用户":$oauth_info['open_name'];
                        $params = array(
                            'touser'=>$oauth_info['open_id'],
                            'msgtype'=>'news',
                            'news'=>array(
                                'articles'=>array('0'=>array(
                                    'title'=>'买一点温馨提示',
                                    'description'=>"亲爱的{$oauth_info['open_name']},恭喜您，入驻小区申请通过了！快来看看吧>>>",
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
     * 记录赠送银点的使用情况
     * $type=>1:有限制的 0：无限制的
     */
    static function recordUseDetail4SendSilverCoin($user_id,$use_amount,$order_id,$type=1){
         if($use_amount>0){
             $model = new Model();
             $limit_silver = $model->table('recharge_sendsilver')->where("is_all_used = 0 and huadian_limit = $type and user_id =$user_id")->order("id asc")->findAll();
             if(!empty($limit_silver)){
                 $record_amount =0.00;
                 $need_record = $use_amount;
                 foreach($limit_silver as $v){
                           if($record_amount==$use_amount||$need_record==0.00){
                               break;
                           }
                           if($need_record>=$v['loss_amount']){
                               $record_amount+=$v['loss_amount'];
                               $need_record -=$v['loss_amount'];
                               $update['is_all_used']=1;
                               $update['loss_amount']=0.00;
                               $update['used_amount']=$v['send_silver'];
                               $insert['use_amount']=$v['loss_amount'];
                           }else{
                               $record_amount+=$need_record;
                               $update['is_all_used']=0;
                               $update['loss_amount']=$v['loss_amount']-$need_record;
                               $update['used_amount']=$v['used_amount']+$need_record;
                               $insert['use_amount']=$need_record;
                               $need_record =0.00;
                           }
                           $insert['send_record_id']=$v['id'];
                           $insert['order_id']= $order_id;
                           $insert['status']=0;
                           $insert['user_id']=$user_id;
                           $model->table("recharge_sendsilver")->where("id=".$v['id'])->data($update)->update();
                           $model->table('recharge_sendusedetail')->data($insert)->insert();
                }
                 return $record_amount;
             }else{
                 return 0.00;
             }
         }else{
             return false;
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
}
