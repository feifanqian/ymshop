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
        $rate = Filter::float(Req::args('rate')); //甲方收取服务费比例
         
        $path_1 = 'http://www.ymlypt.com/static/images/0001.png'; // 图片一
        $path_2 = 'http://www.ymlypt.com/static/images/0002.png'; // 图片二
        $path_3 = 'http://www.ymlypt.com/static/images/0003.png'; // 图片三
        $font = '/var/www/shop/static/fonts/simhei.ttf'; //中文字体
        //一、合成乙方签名
        $image_1 = imagecreatefromstring(file_get_contents($path_1));
        
        $black1 = imagecolorallocate($image_1, 0, 0, 0);
        
        $str1 = mb_convert_encoding($name, "html-entities", "utf-8");
        imagettftext($image_1, 18, 0, imagesx($image_1)-882, imagesy($image_1)-980, $black1, $font, $str1);
        
        // 输出合成图片
        $time = time();
        imagepng($image_1, APP_ROOT.'static/images/temp/'.$time.'1.png');
        
        $url1 = 'http://www.ymlypt.com/static/images/temp/'.$time.'1.png'; 
        
        //二、合成乙方手机号
        $image_2 = imagecreatefromstring(file_get_contents($url1));
        
        $black2 = imagecolorallocate($image_2, 0, 0, 0);
        
        $str2 = $mobile;
        imagettftext($image_2, 18, 0, imagesx($image_2)-854, imagesy($image_2)-940, $black2, $font, $str2);
        
        // 输出合成图片
        imagepng($image_2, APP_ROOT.'static/images/temp/'.$time.'2.png');

        $url2 = 'http://www.ymlypt.com/static/images/temp/'.$time.'2.png';
        
        //三、合成服务费比例
        $image_3 = imagecreatefromstring(file_get_contents($path_3));
        
        $black3 = imagecolorallocate($image_3, 0, 0, 0);
        
        $str3 = $rate.'%';
        imagettftext($image_3, 18, 0, imagesx($image_3)-424, imagesy($image_3)-434, $black3, $font, $str3);
        
        // 输出合成图片
        imagepng($image_3, APP_ROOT.'static/images/temp/'.$time.'3.png');

        $url3 = 'http://www.ymlypt.com/static/images/temp/'.$time.'3.png';
        
        //四、合成签约日期
        $image_4 = imagecreatefromstring(file_get_contents($url3));
        
        $black4 = imagecolorallocate($image_4, 0, 0, 0);
        
        $str4 = date('Y.m.d');
        imagettftext($image_4, 22, 0, imagesx($image_4)-800, imagesy($image_4)-730, $black4, $font, $str4);
        
        // 输出合成图片
        imagepng($image_4, APP_ROOT.'static/images/temp/'.$time.'4.png');

        $url4 = 'http://www.ymlypt.com/static/images/temp/'.$time.'4.png';
        
        //保存至数据库
        $contract = $this->model->table('promoter_contract')->where('user_id='.$this->user['id'])->find();
        $data = array(
            'user_id' => $this->user['id'],
            'url1'    => $url2,
            'url3'    => $url4
            );
        if(!$contract) {
            $this->model->table('promoter_contract')->data($data)->insert();
        } else {
            $this->model->table('promoter_contract')->data($data)->where('id='.$contract['id'])->update();
        }

        $this->code = 0;
        $this->content['url1'] = $url2;
        $this->content['url2'] = $path_2;
        $this->content['url3'] = $url4;
    }

    public function save_contract_image()
    {
        if(!isset($_FILES['picture'])) {
            $this->code = 1294;
            return;
        }
        $upfile_path = Tiny::getPath("uploads") . "/head/";
        $upfile_url = preg_replace("|" . APP_URL . "|", '', Tiny::getPath("uploads_url") . "head/", 1);
        $upfile = new UploadFile('picture', $upfile_path, '500k', '', 'hash', $this->user['id']);
        $upfile->save();
        $info = $upfile->getInfo();
        $result = array();
        $picture = "";

        if ($info[0]['status'] == 1) {
            $result = array('error' => 0, 'url' => $upfile_url . $info[0]['path']);
            $image_url = $upfile_url . $info[0]['path'];
            $image = new Image();
            $image->suffix = '';
            $image->thumb(APP_ROOT . $image_url, 1080, 1527);
            $picture = "http://" . $_SERVER['HTTP_HOST'] . '/' . $image_url;
        }
        // var_dump($picture);die;
        // $url = Filter::str(Req::args('url'));
        $data = array(
            'url4'    => $picture
            );
        $this->model->table('promoter_contract')->data($data)->where('user_id='.$this->user['id'])->update();
        $this->code = 0;
    }

    public function business_center_list()
    {
        $list = $this->model->table('business_center')->findAll();
        if($list) {
            foreach ($list as $key => $value) {
                $list[$key]['dynamic'] = $this->model->table('center_share')->where('center_id='.$value['id'])->count();
            }
        }
        $this->code = 0;
        $this->content['list'] = $list;
    }

}