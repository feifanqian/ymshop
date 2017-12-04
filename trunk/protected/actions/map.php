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
    public $code = null;
    public $content = null;
    public $user = null;

    public function __construct()
    {
        $this->model = new Model();
    }

    public function returnSquarePoint($lng, $lat, $distance)
    {

        $dlng = 2 * asin(sin($distance / (2 * 6371)) / cos(deg2rad($lat)));
        $dlng = rad2deg($dlng);//转成弧度

        $dlat = $distance / 6371;
        $dlat = rad2deg($dlat);//转成弧度

        return array(
            'left-top' => array('lat' => $lat + $dlat, 'lng' => $lng - $dlng),
            'right-top' => array('lat' => $lat + $dlat, 'lng' => $lng + $dlng),
            'left-bottom' => array('lat' => $lat - $dlat, 'lng' => $lng - $dlng),
            'right-bottom' => array('lat' => $lat - $dlat, 'lng' => $lng + $dlng)
        );
    }

    //使用此函数计算得到结果后，带入sql查询。
    public function getMap()
    {
        $lng = Req::args('lng');//经度
        $lat = Req::args('lat');//纬度
        $distance = Req::args('distance');
        $squares = $this->returnSquarePoint($lng, $lat,$distance);
        $info_sql = $this->model()->query("select id,location,lat,lng,picture,describe from 'tiny_district_promoter' where lat<>0 and lat>{$squares['right-bottom']['lat']}and lat<{$squares['left-top']['lat']} and lng>{$squares['left-top']['lng']} and lng<{$squares['right-bottom']['lng']}");
        print_r($info_sql);
        die;
    }

}