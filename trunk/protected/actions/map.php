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
        // 图片一
        $path_1 = 'http://www.ymlypt.com/static/images/0001.png';
        // $image_1 = imagecreatefrompng($path_1);
        $image_1 = imagecreatefromstring(file_get_contents($path_1));
        // 合成图片
        // imagecopymerge($image_1, $image_2, 0, 0, 0, 0, imagesx($image_2), imagesy($image_2), 100);
        $black = imagecolorallocate($image_1, 0, 0, 0);
        $font = '/static/fonts/Dejavusans_0.ttf';

        imagettftext($image_1, 16, 0, imagesx($image_1)-160, imagesy($image_1)-20, $black, $font, 'MKTK-HELOO');
        // 输出合成图片
        var_dump(imagepng($image_1, APP_ROOT.'static/images/temp/'.time().'.png'));
    }

}