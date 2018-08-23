<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/12/4
 * Time: 9:42
 */
//define(EARTH_RADIUS, 6371);//地球半径，平均半径为6371km

/**
 *计算某个经纬度的周围某段距离的正方形的四个点
 *
 * @param lng float 经度
 * @param lat float 纬度
 * @param distance float 该点所在圆的半径，该圆与此正方形内切，默认值为0.5千米
 * @return array 正方形的四个点的经纬度坐标
 */
class MapAction extends Controller
{
    public $model = null;
    public $code = 1000;
    public $content = null;
    public $user = null;

    public function __construct()
    {
        $this->model = new Model();
    }

    //使用此函数计算得到结果后，带入sql查询。
    public function getMaps()
    {
        $lng = Req::args('lng');//经度
        $lat = Req::args('lat');//纬度
        $distance = Req::args('distance');//距离
        if(!$distance){
            $distance = 10; //默认10公里
        }
        $region_id = Req::args('region_id');//区域，例如福田区

        $dlng = 2 * asin(sin($distance / (2 * 6371)) / cos(deg2rad($lat)));
        $dlng = rad2deg($dlng);//转成弧度

        $dlat = $distance / 6371;
        $dlat = rad2deg($dlat);//转成弧度
        $squares = array(
            'left-top' => array('lat' => $lat + $dlat, 'lng' => $lng - $dlng),
            'right-top' => array('lat' => $lat + $dlat, 'lng' => $lng + $dlng),
            'left-bottom' => array('lat' => $lat - $dlat, 'lng' => $lng - $dlng),
            'right-bottom' => array('lat' => $lat - $dlat, 'lng' => $lng + $dlng)
        );
        $info_sql = $this->model->table('district_promoter')->where('lat>'.$squares['right-bottom']['lat'].' and lat<'.$squares['left-top']['lat'].' and lng>'.$squares['left-top']['lng'].' and lng<'.$squares['right-bottom']['lng'].' and region_id='.$region_id)->findAll();
        // var_dump($info_sql);die;
        // $info_sql = $this->model->query("select id,location,lat,lng,picture,describe from tiny_district_promoter where lat<>0 and lat>{$squares['right-bottom']['lat']}and lat<{$squares['left-top']['lat']} and lng>{$squares['left-top']['lng']} and lng<{$squares['right-bottom']['lng']} AND region_id = {$region_id}");
        
        $this->code = 0;
        $this->content = $info_sql;
    }

    public function promoterType(){
        $list = $this->model->table('promoter_type')->order('id asc')->findAll();
        $this->code = 0;
        $this->content = $list;
    }

    public function image_merge_test()
    {
        $name = Filter::str(Req::args('name')); //乙方名字
        $mobile = Filter::str(Req::args('mobile')); //乙方电话
        $id_no = Filter::str(Req::args('id_no')); //乙方证件号
        $address = Filter::str(Req::args('address')); //乙方地址
        $rate = Filter::float(Req::args('rate')); //甲方收取服务费比例
         
        // $path_1 = 'http://www.ymlypt.com/static/images/0001.png'; // 图片一
        // $path_2 = 'http://www.ymlypt.com/static/images/0002.png'; // 图片二
        // $path_3 = 'http://www.ymlypt.com/static/images/0003.png'; // 图片三
         
        $path_1 = "http://www.ymlypt.com/static/images/0004.png";

        $font = '/var/www/shop/static/fonts/simhei.ttf'; //中文字体
        //一、合成乙方签名
        $image_1 = imagecreatefromstring(file_get_contents($path_1));
        
        $black1 = imagecolorallocate($image_1, 0, 0, 0);
        
        $str1 = mb_convert_encoding($name, "html-entities", "utf-8");
        imagettftext($image_1, 18, 0, imagesx($image_1)-887, imagesy($image_1)-4030, $black1, $font, $str1);
        
        // 输出合成图片
        $time = time();
        imagepng($image_1, APP_ROOT.'static/images/temp/'.$time.'1.png');
        
        $url1 = 'http://www.ymlypt.com/static/images/temp/'.$time.'1.png'; 
        
        //二、合成乙方手机号
        $image_2 = imagecreatefromstring(file_get_contents($url1));
        
        $black2 = imagecolorallocate($image_2, 0, 0, 0);
        
        $str2 = $mobile;
        imagettftext($image_2, 18, 0, imagesx($image_2)-854, imagesy($image_2)-3900, $black2, $font, $str2);
        
        // 输出合成图片
        imagepng($image_2, APP_ROOT.'static/images/temp/'.$time.'2.png');

        $url2 = 'http://www.ymlypt.com/static/images/temp/'.$time.'2.png';

        //三、合成乙方证件号
        $image_3 = imagecreatefromstring(file_get_contents($url2));
        
        $black3 = imagecolorallocate($image_3, 0, 0, 0);
        
        $str3 = $id_no;
        imagettftext($image_3, 18, 0, imagesx($image_3)-872, imagesy($image_3)-3985, $black3, $font, $str3);
        
        // 输出合成图片
        imagepng($image_3, APP_ROOT.'static/images/temp/'.$time.'3.png');

        $url3 = 'http://www.ymlypt.com/static/images/temp/'.$time.'3.png';

        //四、合成乙方地址
        $image_4 = imagecreatefromstring(file_get_contents($url3));
        
        $black4 = imagecolorallocate($image_4, 0, 0, 0);
        
        $str4 = $address;
        imagettftext($image_4, 18, 0, imagesx($image_4)-887, imagesy($image_4)-3941, $black4, $font, $str4);
        
        // 输出合成图片
        imagepng($image_4, APP_ROOT.'static/images/temp/'.$time.'4.png');

        $url4 = 'http://www.ymlypt.com/static/images/temp/'.$time.'4.png';
        
        //五、合成服务费比例
        $image_5 = imagecreatefromstring(file_get_contents($url4));
        
        $black5 = imagecolorallocate($image_5, 0, 0, 0);
        
        $str5 = $rate.'%';
        imagettftext($image_5, 18, 0, imagesx($image_5)-411, imagesy($image_5)-342, $black5, $font, $str5);
        
        // 输出合成图片
        imagepng($image_5, APP_ROOT.'static/images/temp/'.$time.'5.png');

        $url5 = 'http://www.ymlypt.com/static/images/temp/'.$time.'5.png';
        
        //六、合成签约日期
        $image_6 = imagecreatefromstring(file_get_contents($url5));
        
        $black6 = imagecolorallocate($image_6, 0, 0, 0);
        
        $str6 = date('Y.m.d');
        imagettftext($image_6, 22, 0, imagesx($image_6)-837, imagesy($image_6)-640, $black6, $font, $str6);
        
        // 输出合成图片
        imagepng($image_6, APP_ROOT.'static/images/temp/'.$time.'6.png');

        $url6 = 'http://www.ymlypt.com/static/images/temp/'.$time.'6.png';
        
        //保存至数据库
        $contract = $this->model->table('promoter_contract')->where('user_id='.$this->user['id'])->find();
        $data = array(
            'user_id' => $this->user['id'],
            'url3'    => $url6
            );
        if(!$contract) {
            $this->model->table('promoter_contract')->data($data)->insert();
        } else {
            $this->model->table('promoter_contract')->data($data)->where('id='.$contract['id'])->update();
        }

        $this->code = 0;
        $this->content['url'] = $url6;
    }

    public function save_contract_image()
    {
        if(!isset($_FILES['picture'])) {
            $this->code = 1294;
            return;
        }
        $upfile_path = Tiny::getPath("uploads") . "/contract/";
        $upfile_url = preg_replace("|" . APP_URL . "|", '', Tiny::getPath("uploads_url") . "contract/", 1);
        $upfile = new UploadFile('picture', $upfile_path, '4000k', '', 'hash', $this->user['id']);
        $upfile->save();
        $info = $upfile->getInfo();
        $result = array();
        $picture = "";

        if ($info[0]['status'] == 1) {
            $result = array('error' => 0, 'url' => $upfile_url . $info[0]['path']);
            $image_url = $upfile_url . $info[0]['path'];
            $image = new Image();
            $image->suffix = '';
            $image->thumb(APP_ROOT . $image_url, 1080, 4574);
            $picture = "http://" . $_SERVER['HTTP_HOST'] . '/' . $image_url;
        }
        // var_dump($picture);die;
        // $url = Filter::str(Req::args('url'));
        $data = array(
            'url4'    => $picture
            );
        $this->model->table('promoter_contract')->data($data)->where('user_id='.$this->user['id'])->update();
        $this->code = 0;
        $this->content['picture'] = $picture;
    }
    
    //商圈列表
    public function business_center_list()
    {
        $list = $this->model->table('business_center')->findAll();
        if($list) {
            foreach ($list as $key => $value) {
                $list[$key]['dynamic_num'] = $this->model->table('center_dynamic')->where('center_id='.$value['id'].' and report_num < 5')->count();
            }
        }
        $this->code = 0;
        $this->content['list'] = $list;
    }

    //发布动态
    public function publish_dynamic()
    {
        $region = Req::args("region");
        $goods_id = Filter::int(Req::args("goods_id"));
        $content = Filter::text(Req::args("content"));
        $imgs = Filter::str(Req::args("imgs"));
        $location = Filter::str(Req::args("location"));
        if(!$region) {
            $this->code = 1295;
            return;
        }
        if(is_numeric($region)) {
            $where = 'region_id = '.$region;
        } else {
            $area = $this->model->table('area')->where("name like '%{$region}%'")->find();
            if(!$area) {
                $this->code = 1298;
                return;
            }
            $where = 'region_id = '.$area['id'];
        }
        $center = $this->model->table('business_center')->where($where)->find();
        if(!$center) {
            $this->code = 1296;
            return;
        }
        if($goods_id) {
            $goods = $this->model->table('goods')->where('id = '.$goods_id)->find();
            if(!$goods) {
                $this->code = 1040;
                return;
            }
            $shop_id = $goods['shop_id'];
        } else {
            $shop_id = 0;
        }
        
        $data = array(
            'center_id'   => $center['id'],
            'user_id'     => $this->user['id'],
            'goods_id'    => $goods_id,
            'shop_id'     => $shop_id,
            'content'     => $content,
            'imgs'        => $imgs,
            'location'    => $location,
            'create_time' => date('Y-m-d H:i:s')
            );
        $this->model->table('center_dynamic')->data($data)->insert();
        $list = $this->model->table('center_dynamic')->where('center_id = '.$center['id'].' and report_num < 5')->findAll();
        if($list) {
            foreach ($list as $key => $value) {
                if($value['goods_id']) {
                    $goods = $this->model->table('goods')->fields('id,name,sell_price,img')->where('id = '.$value['goods_id'])->find();
                    $list[$key]['goods_id'] = $goods['id'];
                    $list[$key]['goods_name'] = $goods['name'];
                    $list[$key]['sell_price'] = $goods['sell_price'];
                    $list[$key]['goods_img'] = $goods['img'];
                } else {
                    $list[$key]['goods_id'] = 0;
                    $list[$key]['goods_name'] = '';
                    $list[$key]['sell_price'] = 0.00;
                    $list[$key]['goods_img'] = '';
                }
            }
        }
        $this->code = 0;
        $this->content['list'] = $list;
        return;
    }

    //推荐商品列表
    public function recommend_goods_list()
    {
        $list = $this->model->table('goods')->fields('id,name,sell_price,img')->where('user_id = '.$this->user['id'].' and is_online=0 and type = 2')->findAll();
        $this->code = 0;
        $this->content['list'] = $list;
        return;
    }

    //动态举报
    public function dynamic_report()
    {
        $id = Filter::int(Req::args("id"));
        $type = Filter::str(Req::args("type"));
        $content = Filter::str(Req::args("content"));
        if(!$type) {
            $this->code = 1297;
            return;
        }
        $this->model->table('center_dynamic')->data(['status'=>2,'report_num'=>"`report_num`+1"])->where('id = '.$id)->update();
        $dynamic = $this->model->table('center_dynamic')->where('id = '.$id)->find();
        if($dynamic['report_num']>=5) {
            $this->model->table('center_dynamic')->where('id = '.$id)->delete();
        }
        $this->model->table('dynamic_report')->data(['dynamic_id'=>$id,'type'=>$type,'content'=>$content])->insert();
        $this->code = 0;
    }

    //商圈动态列表
    public function center_dynamic_list()
    {  
        $center_id = Filter::int(Req::args("center_id"));
        $region_id = Filter::int(Req::args("region_id"));
        $where = '';
        if($center_id) {
            $where = 'id = '.$center_id;
        }
        if($region_id) {
            $where = 'region_id = '.$region_id;
        }
        $center = $this->model->table('business_center')->where($where)->find();
        $center['dynamic_num'] = $this->model->table('center_dynamic')->where('center_id='.$center_id.' and report_num < 5')->count();
        
        $list = $this->model->table('center_dynamic as cd')->join('left join user as u on cd.user_id=u.id left join district_promoter as dp on cd.user_id=dp.user_id')->fields('u.nickname,u.avatar,dp.id as promoter_id,dp.shop_type,cd.*')->where('cd.center_id = '.$center_id.' and cd.report_num < 5')->findAll();
        
        if($list) {
            foreach ($list as $key => $value) {
                if($value['goods_id']) {  
                    $goods = $this->model->table('goods')->fields('id as goods_id,name as goods_name,sell_price,img as goods_img')->where('id = '.$value['goods_id'])->find(); 
                } else {
                    $goods['goods_id'] = 0;
                    $goods['goods_name'] = '';
                    $goods['sell_price'] = 0;
                    $goods['goods_img'] = '';
                }
                $list[$key]['goods'] = $goods;
                if($value['imgs']!='') {
                    $list[$key]['imgs'] = explode(',',$value['imgs']);
                } else {
                    $list[$key]['imgs'] = [];
                }
                $had_laud = $this->model->table('dynamic_laud')->where('dynamic_id='.$value['id'].' and user_id='.$this->user['id'])->find();
                $list[$key]['had_laud'] = empty($had_laud)?0:1; //是否已点赞
                $list[$key]['comment_list'] = $this->model->table('dynamic_comment as dc')->join('left join user as u on dc.user_id=u.id')->fields('u.nickname,u.avatar,dc.*')->where('dc.dynamic_id='.$value['id'])->findAll();
                $list[$key]['comment_num'] = count($list[$key]['comment_list']);
            }
        }

        $this->code = 0;
        $this->content['center'] = $center;
        $this->content['list'] = $list;
        $this->content['current_time'] = date('Y-m-d H:i:s');
        return;
    }

    //商圈动态详情
    public function center_dynamic_detail()
    {
        $id = Filter::int(Req::args("id"));
        $info = $this->model->table('center_dynamic as cd')->join('left join user as u on cd.user_id=u.id left join district_promoter as dp on cd.user_id=dp.user_id')->fields('u.nickname,u.avatar,dp.id as promoter_id,dp.shop_type,cd.*')->where('cd.id = '.$id)->find();
        if(!$info) {
            $this->code = 1299;
            return;
        }
        if($info['goods_id']) {  
            $goods = $this->model->table('goods')->fields('id as goods_id,name as goods_name,sell_price,img as goods_img')->where('id = '.$info['goods_id'])->find(); 
        } else {
            $goods['goods_id'] = 0;
            $goods['goods_name'] = '';
            $goods['sell_price'] = 0;
            $goods['goods_img'] = '';
        }
        $info['goods'] = $goods;
        if($info['imgs']!='') {
            $info['imgs'] = explode(',',$info['imgs']);
        } else {
            $info['imgs'] = [];
        }
        $had_laud = $this->model->table('dynamic_laud')->where('dynamic_id='.$info['id'].' and user_id='.$this->user['id'])->find();
        $info['had_laud'] = empty($had_laud)?0:1; //是否已点赞
        $info['comment_list'] = $this->model->table('dynamic_comment as dc')->join('left join user as u on dc.user_id=u.id')->fields('u.nickname,u.avatar,dc.*')->where('dc.dynamic_id='.$info['id'])->findAll();
        $info['comment_num'] = count($info['comment_list']);
        $info['current_time'] = date('Y-m-d H:i:s');
        $this->code = 0;
        $this->content['detail'] = $info;
    }

    //商圈动态点赞
    public function dynamic_click_laud()
    {
        $id = Filter::int(Req::args("id"));
        $had_laud = $this->model->table('dynamic_laud')->where('dynamic_id='.$id.' and user_id='.$this->user['id'])->find();
        if(!$had_laud) {
            $this->model->table('center_dynamic')->data(['laud_num'=>"`laud_num`+1"])->where('id = '.$id)->update();
            $this->model->table('dynamic_laud')->data(['dynamic_id'=>$id,'user_id'=>$this->user['id']])->insert();
        } else {
            $this->model->table('center_dynamic')->data(['laud_num'=>"`laud_num`-1"])->where('id = '.$id)->update();
            $this->model->table('dynamic_laud')->where('id='.$had_laud['id'])->delete();
        }
        
        $this->code = 0;
    }

    //商圈动态发布评论
    public function dynamic_comment()
    {
        $id = Filter::int(Req::args("id"));
        $content = Filter::str(Req::args("content"));
        $imgs = Filter::str(Req::args("imgs"));
        $data = array(
            'dynamic_id'   => $id,
            'user_id'      => $this->user['id'],
            'content'      => $content,
            'imgs'         => $imgs,
            'comment_time' => date('Y-m-d H:i:s')
            );
        $this->model->table('dynamic_comment')->data($data)->insert();
        $this->code = 0;
    }

    //地区列表
    public function area_list()
    {
        $province = $this->model->table('area')->where('parent_id=0')->order('sort asc')->findAll();
        foreach ($province as $key => $value) {
            $province[$key]['children'] = $this->model->table('area')->where('parent_id='.$value['id'])->findAll();
        }
        $this->code = 0;
        $this->content['region'] = $province;
    }

}