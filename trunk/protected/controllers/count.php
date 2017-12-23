<?php

class CountController extends Controller
{

    public $layout = 'admin';
    private $top = null;
    public $needRightActions = array('*' => true);

    public function init()
    {
        $menu = new Menu();
        $this->assign('mainMenu', $menu->getMenu());
        $menu_index = $menu->current_menu();
        $this->assign('menu_index', $menu_index);
        $this->assign('subMenu', $menu->getSubMenu($menu_index['menu']));
        $this->assign('menu', $menu);
        $nav_act = Req::get('act') == null ? $this->defaultAction : Req::get('act');
        $nav_act = preg_replace("/(_edit)$/", "_list", $nav_act);
        $this->assign('nav_link', '/' . Req::get('con') . '/' . $nav_act);
        $this->assign('node_index', $menu->currentNode());
        $this->safebox = Safebox::getInstance();
        $this->assign('manager', $this->safebox->get('manager'));
        $this->assign('upyuncfg', Config::getInstance()->get("upyun"));

        $currentNode = $menu->currentNode();
        if (isset($currentNode['name']))
            $this->assign('admin_title', $currentNode['name']);
    }

    public function noRight()
    {
        $this->redirect("admin/noright");
    }

    public function index()
    {
        $model = new Model("order_goods as og");
        $cal = $this->calendar();
        $stime = $cal['start'];
        $etime = $cal['end'];
        $s_time = $cal['str'];
        $num = $cal['days'];

        $monthData = array();
        $realData = array();
        $model = new Model("order");

        if ($num <= 3) {
            $rows = $model->fields("sum(payable_amount) as amount,sum(order_amount) as order_amount,TIME_FORMAT(create_time, '%H:00') as day ")->where("create_time between '$stime' and '$etime' and pay_status=1")->group('hour(create_time)')->findAll();

            for ($i = 0; $i < 24; $i++) {
                $monthData[($i < 10 ? '0' . $i : $i) . ':00'] = 0.00;
                $realData[($i < 10 ? '0' . $i : $i) . ':00'] = 0.00;
            }
        } else {
            $rows = $model->fields("sum(payable_amount) as amount,sum(order_amount) as order_amount,date_format(create_time,'%m-%d') as day ")->where("create_time between '$stime' and '$etime' and pay_status=1")->group('day')->findAll();
            $month_day = null;
            for ($i = 0; $i < $num; $i++) {
                $month_day = date("m-d", strtotime($stime . '+' . $i . 'day'));
                $monthData[$month_day] = 0.00;
                $realData[$month_day] = 0.00;
            }
        }

        if ($rows) {
            foreach ($rows as $row) {
                $monthData[$row['day']] = $row['amount'];
                $realData[$row['day']] = $row['order_amount'];
            }
        }

        $month = implode("','", array_keys($monthData));
        $data = implode(",", $monthData);
        $realData = implode(",", $realData);
        $this->assign('s_time', $s_time);
        $this->assign("month", "'$month'");
        $this->assign("data", $data);
        $this->assign("real_data", $realData);
        $this->redirect();
    }

    public function user_reg()
    {
        $cal = $this->calendar();
        $stime = $cal['start'];
        $etime = $cal['end'];
        $s_time = $cal['str'];

        $model = new Model("customer as cu");
        $rows = $model->fields("count(*) as num, cu.province,ae.name as pro_name")->join("left join area as ae on cu.province = ae.id ")->where("cu.reg_time between '$stime' and '$etime'")->group("cu.province")->findAll();
        $mapdata = array();
        foreach ($rows as $row) {
            $mapdata[] = "'" . preg_replace("/(\s|省|市)/", '', $row['pro_name']) . "'" . ':' . $row['num'];
        }

        $this->assign('mapdata', implode(',', $mapdata));
        $this->assign('s_time', $s_time);

        $this->redirect();
    }

    public function area_buy()
    {

        $cal = $this->calendar();
        $stime = $cal['start'];
        $etime = $cal['end'];
        $s_time = $cal['str'];

        $model = new Model("order as od");
        $rows = $model->fields("count(*) as num, province,ae.name as pro_name")->join("left join area as ae on od.province = ae.id ")->where("pay_time between '$stime' and '$etime' and pay_status = 1")->group("od.province")->query();
        $mapdata = array();
        foreach ($rows as $row) {
            $mapdata[] = "'" . preg_replace("/(\s|省|市)/", '', $row['pro_name']) . "'" . ':' . $row['num'];
        }

        $this->assign('mapdata', implode(',', $mapdata));
        $this->assign('s_time', $s_time);

        $this->redirect();
    }

    public function hot()
    {
        $model = new Model("order_goods as og");
        $cal = $this->calendar();
        $stime = $cal['start'];
        $etime = $cal['end'];
        $s_time = $cal['str'];
        $days = $cal['days'];
        $monthData = array();
        $xdata = array();
        if ($days < 3) {
            $rows = $model->join("left join order as od on od.id = og.order_id")->fields("count(og.id) as num,og.goods_id,TIME_FORMAT(od.create_time, '%H:00') as day ,od.order_amount as amount")->where("od.create_time between '$stime' and '$etime' and od.pay_status=1")->order('num desc')->group("og.goods_id")->limit(3)->findAll();

            for ($i = 0; $i < 24; $i++)
                $xdata[($i < 10 ? '0' . $i : $i) . ':00'] = 0.00;
            foreach ($rows as $row)
                $monthData[$row['goods_id']] = $xdata;
        } else {
            $rows = $model->join("left join order as od on od.id = og.order_id")->fields("count(og.id) as num,og.goods_id,date_format(od.create_time,'%m-%d') as day ,od.order_amount as amount")->where("od.create_time between '$stime' and '$etime' and od.pay_status=1")->order('num desc')->group("og.goods_id")->limit(3)->findAll();

            $month_day = null;
            for ($i = 0; $i < $days; $i++) {
                $month_day = date("m-d", strtotime($stime . '+' . $i . 'day'));
                $xdata[$month_day] = 0.00;
            }
            foreach ($rows as $row)
                $monthData[$row['goods_id']] = $xdata;
        }
        if ($rows) {
            foreach ($rows as $row) {
                $monthData[$row['goods_id']][$row['day']] = $row['amount'];
            }
            foreach ($rows as $row) {
                $data[$row['goods_id']] = implode(",", $monthData[$row['goods_id']]);
            }
            $goods_id = implode(",", array_keys($data));
            $goods = $model->table("goods")->where("id in ($goods_id)")->findAll();
            $parse_goods = array();
            foreach ($goods as $v) {
                $parse_goods[$v['id']] = $v['name'];
            }
            $this->assign("parse_goods", $parse_goods);
        }
        $month = implode("','", array_keys($xdata));
        $this->assign("nodata", implode(",", array_values($xdata)));
        $this->assign("s_time", $s_time);
        $this->assign("month", "'$month'");
        $this->assign("data", isset($data) ? $data : array());

        $this->redirect();
    }

    private function calendar()
    {
        $cal = array();
        $s_time = Req::args("s_time");
        if (!$s_time) {
            $s_time = date("Y-m-d -- Y-m-d");
        }
        $date = explode(' -- ', $s_time);
        $stime = date('Y-m-d 00:00:00', strtotime($date[0]));
        $etime = date('Y-m-d 00:00:00', strtotime($date[1] . '+1day'));
        $cle = strtotime($etime) - strtotime($stime);
        $num = ceil($cle / 86400);
        $cal['start'] = $stime;
        $cal['end'] = $etime;
        $cal['days'] = $num;
        $cal['str'] = $s_time;
        return $cal;
    }

    public function output_excel()
    {

        $time = Req::args("s_time");
        if (!$time) {
            $time = date("Y-m-d%20--%20Y-m-d");
        }
        $date = explode('%20--%20', $time);
        $stime = date('Y-m-d 00:00:00', strtotime($date[0]));
        $etime = date('Y-m-d 00:00:00', strtotime($date[1] . '+1day'));
        $title = "圆梦销售订单[$stime - $etime]";
        $where = "'$stime'< o.pay_time and o.pay_time<'$etime'";
        // Create new PHPExcel object
        $objPHPExcel = new PHPExcel();

        // Set document properties
        $objPHPExcel->getProperties()
            ->setCreator("buy-d")
            ->setLastModifiedBy("buy-d")
            ->setTitle("test")
            ->setSubject("Office 2007 XLSX Test Document")
            ->setDescription("order of buy-d")
            ->setKeywords("order")
            ->setCategory("Test result file");
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('A')->setAutoSize(true);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('B')->setWidth(25);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('C')->setWidth(25);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('D')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('E')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('F')->setWidth(10);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('G')->setWidth(10);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('H')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('I')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('J')->setAutoSize(true);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('K')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('L')->setAutoSize(true);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('M')->setWidth(25);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('N')->setAutoSize(true);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('O')->setAutoSize(true);
        // Add some data
        $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A1', '订单号')
            ->setCellValue("B1", '创建时间')
            ->setCellValue("C1", '支付时间')
            ->setCellValue('D1', '订单类型')
            ->setCellValue('E1', '订单金额')
            ->setCellValue('F1', '配送运费')
            ->setCellValue('G1', '发票税费')
            ->setCellValue('H1', '支付状态')
            ->setCellValue('I1', '支付方式')
            ->setCellValue('J1', '会员账号')
            ->setCellValue('K1', '收货人')
            ->setCellValue('L1', '收货电话')
            ->setCellValue('M1', '收货区域')
            ->setCellValue('N1', '详细地址')
            ->setCellValue('O1', '订单商品信息');

        $order_type = array("0" => "普通订单", "1" => "团购订单", "2" => "抢购订单", "4" => "华点订单", "5" => "积分购订单", "6" => "积分抢购订单");
        $pay_status = array("0" => '未支付', "1" => '已支付', "2" => "申请退款", "3" => "已退款");

        $order = new Model("order as o");
        $order_goods = new Model("order_goods as og");
        $area = new Model("area");
        $result = $order->join("left join payment as p on o.payment = p.id left join user as us on o.user_id = us.id")
            ->fields("o.id,o.type,o.order_no,o.order_amount,o.pay_status,o.province,o.city,o.county,o.create_time,o.pay_time,o.taxes,o.real_freight,p.pay_name,us.name,o.accept_name,o.mobile,o.addr")
            ->where($where)
            ->order("id desc")
            ->findAll();
        if (!empty($result)) {
            foreach ($result as $k => $v) {
                $index = $k + 2;
                $objPHPExcel->setActiveSheetIndex(0)
                    ->setCellValueExplicit('A' . $index, $v['order_no'], PHPExcel_Cell_DataType::TYPE_STRING)
                    ->setCellValueExplicit('B' . $index, $v['create_time'], PHPExcel_Cell_DataType::TYPE_STRING)
                    ->setCellValueExplicit('C' . $index, $v['pay_time'], PHPExcel_Cell_DataType::TYPE_STRING)
                    ->setCellValue('D' . $index, $order_type[$v['type']])
                    //E
                    ->setCellValue('F' . $index, $v['real_freight'])
                    ->setCellValue('G' . $index, $v['taxes'])
                    ->setCellValue('H' . $index, $pay_status[$v['pay_status']])
                    ->setCellValue('I' . $index, $v['pay_name'])
                    ->setCellValue('J' . $index, $v['name'])
                    ->setCellValue('K' . $index, $v['accept_name'])
                    ->setCellValue('L' . $index, $v['mobile'])
                    //M
                    ->setCellValue('N' . $index, $v['addr']);
                if ($v['type'] == 4) {
                    $objPHPExcel->setActiveSheetIndex(0)
                        ->setCellValue('E' . $index, $v['order_amount'] . "\r\n(" . $v['huabipay_amount'] . "华点+￥" . $v['otherpay_amount'] . ")");
                } else {
                    $objPHPExcel->setActiveSheetIndex(0)
                        ->setCellValue('E' . $index, $v['order_amount']);
                }
                $goods_info = $order_goods
                    ->where("order_id =" . $v['id'])
                    ->join("left join goods as g on og.goods_id = g.id")
                    ->fields("g.name,og.goods_nums,og.real_price")
                    ->findAll();
                $goods = "";
                foreach ($goods_info as $kk => $vv) {
                    $goods .= $goods != "" ? "\r\n" . $vv['name'] . "[X" . $vv['goods_nums'] . "][单价:" . $vv['real_price'] . "元]" : $vv['name'] . "[X" . $vv['goods_nums'] . "][单价:" . $vv['real_price'] . "元]";
                }
                $province = $city = $county = "";
                $provinceData = $area->where("id=" . $v['province'] . " and parent_id = 0")->fields("id,name")->find();
                if ($provinceData) {
                    $province = $provinceData['name'];
                    $cityData = $area->where("id=" . $v['city'] . " and parent_id=" . $provinceData['id'])->fields("id,name")->find();
                    if ($cityData) {
                        $city = $cityData['name'];
                        $countyData = $area->where("id=" . $v['county'] . " and parent_id=" . $cityData['id'])->fields("id,name")->find();
                        if ($countyData) {
                            $county = $countyData['name'];
                        }
                    }
                }
                $areaInfo = $province . $city . $county;
                $objPHPExcel->setActiveSheetIndex(0)
                    ->setCellValueExplicit('M' . $index, $areaInfo, PHPExcel_Cell_DataType::TYPE_STRING);
                $objPHPExcel->setActiveSheetIndex(0)
                    ->setCellValueExplicit('O' . $index, $goods, PHPExcel_Cell_DataType::TYPE_STRING);
            }
            $length = count($result) + 1;
            $objPHPExcel->setActiveSheetIndex(0);
            $objPHPExcel->getActiveSheet()->freezePane('A2');
            $objPHPExcel->setActiveSheetIndex(0)->getStyle('A1:O' . $length)->getAlignment()->setWrapText(true)->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);

        }

        ob_end_clean(); //清空（擦除）缓冲区并关闭输出缓冲
        ob_start();//打开输出控制缓冲
        // Redirect output to a client’s web browser (Excel5)
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $title . '".xls"');
        header('Cache-Control: max-age=0');
        // If you're serving to IE 9, then the following may be needed
        header('Cache-Control: max-age=1');

        // If you're serving to IE over SSL, then the following may be needed
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
        header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header('Pragma: public'); // HTTP/1.0

        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        exit;
    }

    //货品进销存明细表
    public function inventory()
    {
        header("Content-type: text/html; charset=utf-8");
        $cal = $this->calendar();
        $stime = $cal['start']; //开始时间
        $etime = $cal['end']; //结束时间
        $s_time = $cal['str'];
        $num = $cal['days'];//天数

        $monthData = array();
        $realData = array();
        $models = new Model("order_goods as og");
        // $rows = $models->join("left join goods as gd on og.goods_id = gd.id left join order as od on og.order_id = od.id")->fields("gd.name as gdname,gd.sell_price as gdprice,sum(og.goods_nums) as nums")->where("od.pay_time between '$stime' and '$etime' and od.pay_status=1")->findAll();

        if ($num <= 3) {
            $rows = $models->join("left join order as o on og.order_id = o.id left join goods as gd on og.goods_id = gd.id")->fields("gd.name as gdname,gd.sell_price as gdprice,sum(og.goods_nums) as nums,TIME_FORMAT(o.pay_time,'%H:00') as day ")->where("o.pay_time between '$stime' and '$etime' and o.pay_status=1")->group('hour(o.pay_time)')->findAll();
            for ($i = 0; $i < 24; $i++) {
                $monthData[($i < 10 ? '0' . $i : $i) . ':00'] = 0.00;
                $realData[($i < 10 ? '0' . $i : $i) . ':00'] = 0.00;
            }
        } else {
            $rows = $models->join("left join order as o on og.order_id = o.id left join goods as gd on og.goods_id = gd.id")->fields("gd.name as gdname,gd.sell_price as gdprice,sum(og.goods_nums) as nums,date_format(o.pay_time,'%m-%d') as day ")->where("o.pay_time between '$stime' and '$etime' and o.pay_status=1")->group('day')->findAll();
            $month_day = null;
            for ($i = 0; $i < $num; $i++) {
                $month_day = date("m-d", strtotime($stime . '+' . $i . 'day'));
                $monthData[$month_day] = 0.00;
                $realData[$month_day] = 0.00;
            }
        }
        if ($rows) {
            foreach ($rows as $row) {
                $monthData[$row['day']] = $row['nums'];
                $realData[$row['day']] = $row['nums']*$row['gdprice'];
            }
        }

        $month = implode("','", array_keys($monthData)); //将数组转换成字符串
        $data = implode(",", $monthData);
        $realData = implode(",", $realData);
        $this->assign('s_time', $s_time);
        $this->assign("month", "'$month'");
        $this->assign("data", $data);
        $this->assign("real_data", $realData);
        $this->redirect();
    }

    //销售明细表
    public function sales_analysis()
    {
        header("Content-type: text/html; charset=utf-8");
        $cal = $this->calendar();
        $stime = $cal['start']; //开始时间
        $etime = $cal['end']; //结束时间
        $s_time = $cal['str'];
        $num = $cal['days'];//天数

        $monthData = array();
        $realData = array();
        $models = new Model("order_goods as og");
        // $rows = $models->join("left join goods as gd on og.goods_id = gd.id left join order as od on og.order_id = od.id")->fields("gd.name as gdname,gd.sell_price as gdprice,sum(og.goods_nums) as nums")->where("od.pay_time between '$stime' and '$etime' and od.pay_status=1")->findAll();

        if ($num <= 3) {
            $rows = $models->join("left join order as o on og.order_id = o.id left join goods as gd on og.goods_id = gd.id")->fields("gd.name as gdname,gd.sell_price as gdprice,sum(og.goods_nums) as nums,TIME_FORMAT(o.pay_time,'%H:00') as day ")->where("o.pay_time between '$stime' and '$etime' and o.pay_status=1")->group('hour(o.pay_time)')->findAll();
            for ($i = 0; $i < 24; $i++) {
                $monthData[($i < 10 ? '0' . $i : $i) . ':00'] = 0.00;
                $realData[($i < 10 ? '0' . $i : $i) . ':00'] = 0.00;
            }
        } else {
            $rows = $models->join("left join order as o on og.order_id = o.id left join goods as gd on og.goods_id = gd.id")->fields("gd.name as gdname,gd.sell_price as gdprice,sum(og.goods_nums) as nums,date_format(o.pay_time,'%m-%d') as day ")->where("o.pay_time between '$stime' and '$etime' and o.pay_status=1")->group('day')->findAll();
            $month_day = null;
            for ($i = 0; $i < $num; $i++) {
                $month_day = date("m-d", strtotime($stime . '+' . $i . 'day'));
                $monthData[$month_day] = 0.00;
                $realData[$month_day] = 0.00;
            }
        }
        if ($rows) {
            foreach ($rows as $row) {
                $monthData[$row['day']] = $row['nums'];
                $realData[$row['day']] = $row['nums']*$row['gdprice'];
            }
        }

        $month = implode("','", array_keys($monthData)); //将数组转换成字符串
        $data = implode(",", $monthData);
        $realData = implode(",", $realData);
        $this->assign('s_time', $s_time);
        $this->assign("month", "'$month'");
        $this->assign("data", $data);
        $this->assign("real_data", $realData);
        $this->redirect();
    }

    //供应商明细表
    public function supplier()
    {
        header("Content-type: text/html; charset=utf-8");
        $cal = $this->calendar();
        $stime = $cal['start']; //开始时间
        $etime = $cal['end']; //结束时间
        $s_time = $cal['str'];
        $num = $cal['days'];//天数

        $monthData = array();
        $realData = array();
        $models = new Model("order_goods as og");
        // $rows = $models->join("left join goods as gd on og.goods_id = gd.id left join order as od on og.order_id = od.id")->fields("gd.name as gdname,gd.sell_price as gdprice,sum(og.goods_nums) as nums")->where("od.pay_time between '$stime' and '$etime' and od.pay_status=1")->findAll();

        if ($num <= 3) {
            $rows = $models->join("left join order as o on og.order_id = o.id left join goods as gd on og.goods_id = gd.id")->fields("gd.name as gdname,gd.sell_price as gdprice,sum(og.goods_nums) as nums,TIME_FORMAT(o.pay_time,'%H:00') as day ")->where("o.pay_time between '$stime' and '$etime' and o.pay_status=1")->group('hour(o.pay_time)')->findAll();
            for ($i = 0; $i < 24; $i++) {
                $monthData[($i < 10 ? '0' . $i : $i) . ':00'] = 0.00;
                $realData[($i < 10 ? '0' . $i : $i) . ':00'] = 0.00;
            }
        } else {
            $rows = $models->join("left join order as o on og.order_id = o.id left join goods as gd on og.goods_id = gd.id")->fields("gd.name as gdname,gd.sell_price as gdprice,sum(og.goods_nums) as nums,date_format(o.pay_time,'%m-%d') as day ")->where("o.pay_time between '$stime' and '$etime' and o.pay_status=1")->group('day')->findAll();
            $month_day = null;
            for ($i = 0; $i < $num; $i++) {
                $month_day = date("m-d", strtotime($stime . '+' . $i . 'day'));
                $monthData[$month_day] = 0.00;
                $realData[$month_day] = 0.00;
            }
        }
        if ($rows) {
            foreach ($rows as $row) {
                $monthData[$row['day']] = $row['nums'];
                $realData[$row['day']] = $row['nums']*$row['gdprice'];
            }
        }

        $month = implode("','", array_keys($monthData)); //将数组转换成字符串
        $data = implode(",", $monthData);
        $realData = implode(",", $realData);
        $this->assign('s_time', $s_time);
        $this->assign("month", "'$month'");
        $this->assign("data", $data);
        $this->assign("real_data", $realData);
        $this->redirect();
    }

    //提成划分明细表
    public function division()
    {
        header("Content-type: text/html; charset=utf-8");
        $cal = $this->calendar();
        $stime = $cal['start']; //开始时间
        $etime = $cal['end']; //结束时间
        $s_time = $cal['str'];
        $num = $cal['days'];//天数

        $monthData = array();
        $realData = array();
        $models = new Model("order_goods as og");
        // $rows = $models->join("left join goods as gd on og.goods_id = gd.id left join order as od on og.order_id = od.id")->fields("gd.name as gdname,gd.sell_price as gdprice,sum(og.goods_nums) as nums")->where("od.pay_time between '$stime' and '$etime' and od.pay_status=1")->findAll();

        if ($num <= 3) {
            $rows = $models->join("left join order as o on og.order_id = o.id left join goods as gd on og.goods_id = gd.id")->fields("gd.name as gdname,gd.sell_price as gdprice,sum(og.goods_nums) as nums,TIME_FORMAT(o.pay_time,'%H:00') as day ")->where("o.pay_time between '$stime' and '$etime' and o.pay_status=1")->group('hour(o.pay_time)')->findAll();
            for ($i = 0; $i < 24; $i++) {
                $monthData[($i < 10 ? '0' . $i : $i) . ':00'] = 0.00;
                $realData[($i < 10 ? '0' . $i : $i) . ':00'] = 0.00;
            }
        } else {
            $rows = $models->join("left join order as o on og.order_id = o.id left join goods as gd on og.goods_id = gd.id")->fields("gd.name as gdname,gd.sell_price as gdprice,sum(og.goods_nums) as nums,date_format(o.pay_time,'%m-%d') as day ")->where("o.pay_time between '$stime' and '$etime' and o.pay_status=1")->group('day')->findAll();
            $month_day = null;
            for ($i = 0; $i < $num; $i++) {
                $month_day = date("m-d", strtotime($stime . '+' . $i . 'day'));
                $monthData[$month_day] = 0.00;
                $realData[$month_day] = 0.00;
            }
        }
        if ($rows) {
            foreach ($rows as $row) {
                $monthData[$row['day']] = $row['nums'];
                $realData[$row['day']] = $row['nums']*$row['gdprice'];
            }
        }

        $month = implode("','", array_keys($monthData)); //将数组转换成字符串
        $data = implode(",", $monthData);
        $realData = implode(",", $realData);
        $this->assign('s_time', $s_time);
        $this->assign("month", "'$month'");
        $this->assign("data", $data);
        $this->assign("real_data", $realData);
        $this->redirect();
    }

    //销售排行榜表
    public function sales_rank()
    {
        header("Content-type: text/html; charset=utf-8");
        $cal = $this->calendar();
        $stime = $cal['start']; //开始时间
        $etime = $cal['end']; //结束时间
        $s_time = $cal['str'];
        $num = $cal['days'];//天数

        $monthData = array();
        $realData = array();
        $models = new Model("order_goods as og");
       // $rows = $models->join("left join goods as gd on og.goods_id = gd.id left join order as od on og.order_id = od.id")->fields("gd.name as gdname,gd.sell_price as gdprice,sum(og.goods_nums) as nums")->where("od.pay_time between '$stime' and '$etime' and od.pay_status=1")->findAll();

        if ($num <= 3) {
            $rows = $models->join("left join order as o on og.order_id = o.id left join goods as gd on og.goods_id = gd.id")->fields("gd.name as gdname,gd.sell_price as gdprice,sum(og.goods_nums) as nums,TIME_FORMAT(o.pay_time,'%H:00') as day ")->where("o.pay_time between '$stime' and '$etime' and o.pay_status=1")->group('hour(o.pay_time)')->findAll();
            for ($i = 0; $i < 24; $i++) {
                $monthData[($i < 10 ? '0' . $i : $i) . ':00'] = 0.00;
                $realData[($i < 10 ? '0' . $i : $i) . ':00'] = 0.00;
            }
        } else {
            $rows = $models->join("left join order as o on og.order_id = o.id left join goods as gd on og.goods_id = gd.id")->fields("gd.name as gdname,gd.sell_price as gdprice,sum(og.goods_nums) as nums,date_format(o.pay_time,'%m-%d') as day ")->where("o.pay_time between '$stime' and '$etime' and o.pay_status=1")->group('day')->findAll();
            $month_day = null;
            for ($i = 0; $i < $num; $i++) {
                $month_day = date("m-d", strtotime($stime . '+' . $i . 'day'));
                $monthData[$month_day] = 0.00;
                $realData[$month_day] = 0.00;
            }
        }
        if ($rows) {
            foreach ($rows as $row) {
                $monthData[$row['day']] = $row['nums'];
                $realData[$row['day']] = $row['nums']*$row['gdprice'];
            }
        }

        $month = implode("','", array_keys($monthData)); //将数组转换成字符串
        $data = implode(",", $monthData);
        $realData = implode(",", $realData);
        $this->assign('s_time', $s_time);
        $this->assign("month", "'$month'");
        $this->assign("data", $data);
        $this->assign("real_data", $realData);
        $this->redirect();
    }
    //导出销售排行榜表
    public function sales_rank_excel()
    {
        header("Content-type: text/html; charset=utf-8");
        $time = Req::args("s_time");
        if (!$time) {
            $time = date("Y-m-d%20--%20Y-m-d");
        }
        $date = explode('%20--%20', $time);
        $stime = date('Y-m-d 00:00:00', strtotime($date[0]));
        $etime = date('Y-m-d 00:00:00', strtotime($date[1] . '+1day'));
        $title = "圆梦销售排行[$stime - $etime]";
        $where = "'$stime'< o.pay_time and o.pay_time<'$etime'";
        // Create new PHPExcel object
        $objPHPExcel = new PHPExcel();

        // Set document properties
        $objPHPExcel->getProperties()
            ->setCreator("ymlypt")
            ->setLastModifiedBy("ymlypt")
            ->setTitle("test")
            ->setSubject("Office 2007 XLSX Test Document")
            ->setDescription("goods of ymlypt")
            ->setKeywords("goods")
            ->setCategory("Test result file");
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('A')->setAutoSize(true);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('B')->setWidth(25);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('C')->setWidth(25);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('D')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('E')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('F')->setWidth(10);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('G')->setWidth(10);
        // Add some data
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('A1:G1')->setCellValue('A1', '产品销量排行榜');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("A2", '序号');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('B2','厂商');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue("C2", '货品名称');
        $objPHPExcel->setActiveSheetIndex(0) ->setCellValue('D2', '重量');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('E2', '单价');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('F2', '销量');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('G2', '销售额');

        $goods = new Model("goods as gd");
        $shop = new Model("shop as sh");
        $result = $goods->join("left join shop as sh on gd.shop_id = sh.id")
            ->fields("gd.id as gid,sh.name as shname,gd.name as gdname,gd.base_sales_volume,sell_price,gd.weight as gweight")
            ->findAll();
        //销量
        // print_r($result);die;
        foreach ($result as $k => $v) {
            $order_goods = new Model("order_goods as og");
            $sales_volume = $order_goods->join("left join order as o on og.order_id = o.id")->where("og.goods_id =".$v["gid"]." and o.status in (3,4) and ".$where)->fields("SUM(og.goods_nums) as sell_volume")->order("sell_volume desc")->findAll();
            if($sales_volume[0]['sell_volume']){
                $result[$k]['sales_volume'] = $sales_volume[0]['sell_volume'];
            }else{
                $result[$k]['sales_volume'] = 0;
            }
            
        }
        if (!empty($result)) {
            foreach ($result as $k => $v) {
                $index = $k + 3;
                $objPHPExcel->setActiveSheetIndex(0)
                    ->setCellValueExplicit('A' . $index, $k+1)
                    ->setCellValueExplicit('B' . $index, $v['shname'], PHPExcel_Cell_DataType::TYPE_STRING)
                    ->setCellValueExplicit('C' . $index, $v['gdname'], PHPExcel_Cell_DataType::TYPE_STRING)
                    ->setCellValue('D' . $index, $v['gweight'])
                    ->setCellValue('E' . $index, $v['sell_price'])
                    ->setCellValue('F' . $index, $v['sales_volume'])
                    ->setCellValue('G' . $index, $v['sales_volume']*$v['sell_price']);
            }
            $length = count($result) + 2;
            $objPHPExcel->setActiveSheetIndex(0);
            $objPHPExcel->getActiveSheet()->freezePane('A2');
            $objPHPExcel->setActiveSheetIndex(0)->getStyle('A1:G' . $length)->getAlignment()->setWrapText(true)->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);

        }

        ob_end_clean(); //清空（擦除）缓冲区并关闭输出缓冲
        ob_start();//打开输出控制缓冲
        // Redirect output to a client’s web browser (Excel5)
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $title . '".xls"');
        header('Cache-Control: max-age=0');
        // If you're serving to IE 9, then the following may be needed
        header('Cache-Control: max-age=1');

        // If you're serving to IE over SSL, then the following may be needed
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
        header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header('Pragma: public'); // HTTP/1.0

        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        exit;
    }

    //货品进销存明细表
    public function inventory_excel()
    {
        header("Content-type: text/html; charset=utf-8");
        $time = Req::args("s_time");
        if (!$time) {
            $time = date("Y-m-d%20--%20Y-m-d");
        }
        $date = explode('%20--%20', $time);
        $stime = date('Y-m-d 00:00:00', strtotime($date[0]));
        $etime = date('Y-m-d 00:00:00', strtotime($date[1] . '+1day'));
        $title = "圆梦销售排行[$stime - $etime]";
        $where = "'$stime'< o.pay_time and o.pay_time<'$etime'";
        // Create new PHPExcel object
        $objPHPExcel = new PHPExcel();
        $objPHPExcel->getProperties()
            ->setCreator("ymlypt")
            ->setLastModifiedBy("ymlypt")
            ->setTitle("test")
            ->setSubject("Office 2007 XLSX Test Document")
            ->setDescription("goods of ymlypt")
            ->setKeywords("goods")
            ->setCategory("Test result file");
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('A')->setWidth(10);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('B')->setWidth(25);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('C')->setWidth(25);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('D')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('E')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('F')->setWidth(10);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('G')->setWidth(10);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('H')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('I')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('J')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('K')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('L')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('M')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('N')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('O')->setWidth(15);
        // Add some data
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('A1:O1')->setCellValue('A1', '货品进销存明细表');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('A2:A4')->setCellValue("A2", '序号');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('B2:B4')->setCellValue('B2','厂商');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('C2:C4')->setCellValue("C2", '货品名称');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('D2:D4') ->setCellValue('D2', '重量');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('E2:E4')->setCellValue('E2', '单价');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('F2:G3')->setCellValue('F2', '期初库存');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('F4','数量');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('G4','供货价');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('H2:I3')->setCellValue('H2','进仓（厂家发出货品后，默认已入仓）');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('H4','数量');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('I4','供货价');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('G4','供货价');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('J2:M2')->setCellValue('J2','出仓');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('J3:K3')->setCellValue('J3','发出途中');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('L3:M3')->setCellValue('L3','已验收完成销售');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('J4','数量');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('K4','供货价');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('L4', '数量');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('M4','供货价');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('N4','供货价');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('O4', '数量');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('N2:O3')->setCellValue('N2','期末库存');

        $goods = new Model("goods as gd");
        $shop = new Model("shop as sh");
//        $result = $goods->join("left join shop as sh on gd.shop_id = sh.id")
//            ->fields("gd.id as gid,sh.id as shid,sh.name as shname,gd.name as gdname,sell_price,gd.weight as gweight,store_nums,cost_price")
//            ->group("shid")
//            ->findAll();
        $model = new Model();
        $result = $model->query("select *,sum(store_nums) from(select g.id as gid,s.name as sname,g.name as gname from tiny_goods as g left join tiny_shop as s on g.shop_id=s.id group by s.id) as total");
        echo "<pre>";
        print_r($result);
        die();

        foreach ($result as $k => $v) {
            $order_goods = new Model("order_goods as og");
            $delivery_status = $order_goods->join("left join order as o on og.order_id = o.id")->where("og.goods_id =".$v["gid"]." and o.delivery_status=1")->fields("SUM(o.delivery_status) as num")->findAll();
            $receive_status = $order_goods->join("left join order as o on og.order_id = o.id")->where("og.goods_id =".$v["gid"]." and o.status=4")->fields("SUM(o.status) as nums")->findAll();
            if ($receive_status[0]['nums']){
                $result[$k]['receive_status'] = ($receive_status[0]['nums'])/4;
            }else{
                $result[$k]['receive_status'] = 0;
            }
            if ($delivery_status[0]['num']){
                $result[$k]['delivery_status'] = ($delivery_status[0]['num'])/4;
            }else{
                $result[$k]['delivery_status'] = 0;
            }

        }
//        echo "<pre>";
//        print_r($result);
//        die();

        if (!empty($result)) {
            foreach ($result as $k => $v) {
                $index = $k + 5;
                $objPHPExcel->setActiveSheetIndex(0)
                    ->setCellValueExplicit('A' . $index, $k+1)
                    ->setCellValueExplicit('B' . $index, $v['shname'], PHPExcel_Cell_DataType::TYPE_STRING)
                    ->setCellValueExplicit('C' . $index, $v['gdname'], PHPExcel_Cell_DataType::TYPE_STRING)
                    ->setCellValue('D' . $index, $v['gweight'])
                    ->setCellValue('E' . $index, $v['sell_price'])
                    ->setCellValue('F' . $index, $v['store_nums'])
                    ->setCellValue('G' . $index, $v['cost_price'])
                    ->setCellValue('H' . $index, $v['store_nums'])
                    ->setCellValue('I' . $index, $v['cost_price'])
                    ->setCellValue('J' . $index, $v['delivery_status'])
                    ->setCellValue('K' . $index, $v['cost_price'])
                    ->setCellValue('L' . $index, $v['receive_status'])
                    ->setCellValue('M' . $index, $v['cost_price'])
                    ->setCellValue('N' . $index, $v['store_nums']-$v['receive_status'])
                    ->setCellValue('O' . $index, $v['cost_price'])
                    ;
            }
            $length = count($result) + 4;
            $objPHPExcel->setActiveSheetIndex(0);
            $objPHPExcel->getActiveSheet()->freezePane('A2');
            $objPHPExcel->setActiveSheetIndex(0)->getStyle('A1:O' . $length)->getAlignment()->setWrapText(true)->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);

        }

        ob_end_clean(); //清空（擦除）缓冲区并关闭输出缓冲
        ob_start();//打开输出控制缓冲
        // Redirect output to a client’s web browser (Excel5)
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $title . '".xls"');
        header('Cache-Control: max-age=0');
        // If you're serving to IE 9, then the following may be needed
        header('Cache-Control: max-age=1');

        // If you're serving to IE over SSL, then the following may be needed
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
        header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header('Pragma: public'); // HTTP/1.0

        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        exit;
    }
    public function sales_analysis_excel()
    {
        header("Content-type: text/html; charset=utf-8");
        $time = Req::args("s_time");
        if (!$time) {
            $time = date("Y-m-d%20--%20Y-m-d");
        }
        $date = explode('%20--%20', $time);
        $stime = date('Y-m-d 00:00:00', strtotime($date[0]));
        $etime = date('Y-m-d 00:00:00', strtotime($date[1] . '+1day'));
        $title = "圆梦销售分析[$stime - $etime]";
        $where = "'$stime'< o.pay_time and o.pay_time<'$etime'";
        // Create new PHPExcel object
        $objPHPExcel = new PHPExcel();
        $objPHPExcel->getProperties()
            ->setCreator("ymlypt")
            ->setLastModifiedBy("ymlypt")
            ->setTitle("test")
            ->setSubject("Office 2007 XLSX Test Document")
            ->setDescription("goods of ymlypt")
            ->setKeywords("goods")
            ->setCategory("Test result file");
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('A')->setWidth(10);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('B')->setWidth(25);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('C')->setWidth(25);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('D')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('E')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('F')->setWidth(10);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('G')->setWidth(10);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('H')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('I')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('J')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('K')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('L')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('M')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('N')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('O')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('P')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('Q')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('R')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('S')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('T')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('U')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('V')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('W')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('X')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('Y')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('Z')->setWidth(15);
        $objPHPExcel->setActiveSheetIndex(0)->getColumnDimension('AA')->setWidth(15);
        // Add some data
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('A1:AA1')->setCellValue('A1', '销售分析表');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('A2:A4')->setCellValue("A2", '序号');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('B2:B4')->setCellValue('B2','客户');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('C2:C4')->setCellValue("C2", '厂商');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('D2:D4') ->setCellValue('D2', '货品名称');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('E2:E4')->setCellValue('E2', '重量');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('F2:F4')->setCellValue('F2', '单价');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('G2:G4')->setCellValue('G2','销量');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('H2:I2')->setCellValue('H2','销售收入');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('H3:H4')->setCellValue('H3','会员价');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('I3:I4')->setCellValue('I3','积分兑换价');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('J2:J4')->setCellValue('J2','所需积分（积分不作为收入计算毛利）');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('K2:K4')->setCellValue('K2','货品供货价');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('L2:L4')->setCellValue('L2','折扣价');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('M2:M4')->setCellValue('M2','货品毛利');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('N2:V2')->setCellValue('N2','提成');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('N3:O3')->setCellValue('N3','一组');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('N4','名字');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('O4','金额');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('P3:Q3')->setCellValue('P3', '二组');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('P4','名字');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('Q4','金额');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('R3:S3')->setCellValue('R3', '三组');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('R4','名字');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('S4','金额');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('T3:U3')->setCellValue('T3','四组');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('T4','名字');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('U4','金额');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('V3:V4')->setCellValue('V3','小计');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('W2:W4')->setCellValue('W2','产品净利');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('X2:Z2')->setCellValue('X2','赠品');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('X3:X4')->setCellValue('X3','货品名称');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('Y3:Y4')->setCellValue('Y3','数量');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('Z3:Z4')->setCellValue('Z3','进货金额');
        $objPHPExcel->setActiveSheetIndex(0)->mergeCells('AA2:AA4')->setCellValue('AA2','合计');


        $order_goods = new Model("order_goods as og");
        $result = $order_goods->join("left join order as o on og.order_id = o.id left join shop as s on og.shop_id = s.id left join goods as gd on og.goods_id = gd.id")
            ->fields("gd.id as gdid,o.user_id as ouser_id ,s.name as sname,gd.name as gname,og.goods_weight as gweight,og.real_price as og_real_price,gd.cost_price as gd_cost_price")
            ->findAll();

        foreach ($result as $k => $v) {
            $model  = new Model();
            if ($v["ouser_id"]){
                $username = $model->table('user')->where("id =".$v["ouser_id"])->fields("nickname")->findAll();
                if ($username){
                    if ($username[0]['nickname']){
                        $result[$k]['nickname'] = $username[0]['nickname'];

                }else{
                        $result[$k]['nickname'] = '';
                    }
                }else{
                    $result[$k]['nickname'] = '';
                }

            }else{
                $result[$k]['nickname'] = '';
            }
            //积分
            if ($v['gdid']){
                $point = $model->table('point_sale')->where("goods_id=".$v["gdid"])->fields("price_set")->findAll();
                if ($point){
                    if ($point[0]['price_set']){
                        $price_set = array_merge(unserialize($point[0]['price_set']));
                        if ($price_set[0]['cash']){
                            $result[$k]['cash'] = $price_set[0]['cash'];
                        }else{
                            $result[$k]['cash'] = '';
                        }
                        if ($price_set[0]['point']){
                            $result[$k]['point'] = $price_set[0]['point'];
                        }else{
                            $result[$k]['point'] = $price_set[0]['point'];
                        }
                    }else{
                        $result[$k]['cash'] = '';
                        $result[$k]['point'] = '';
                    }
                }else{
                    $result[$k]['cash'] = '';
                    $result[$k]['point'] = '';
                }

            }
            //销量
            if ($v["gdid"]){
                $sales_volume = $order_goods->join("left join order as o on og.order_id = o.id")->where("og.goods_id = ".$v["gdid"]." and o.status in (3,4)")->fields("SUM(og.goods_nums) as sell_volume")->findAll();
                if($sales_volume[0]['sell_volume']){
                    $result[$k]['sales_volume'] = $sales_volume[0]['sell_volume'];
                }else{
                    $result[$k]['sales_volume'] = 0;
                }
            }else{
                $result[$k]['sales_volume'] = 0;
            }


        }
        echo "<pre>";
        print_r($result);
        die();

        if (!empty($result)) {
            foreach ($result as $k => $v) {
                $index = $k + 5;
                $objPHPExcel->setActiveSheetIndex(0)
                    ->setCellValueExplicit('A' . $index, $k+1)
                    ->setCellValueExplicit('B' . $index, $v['nickname'], PHPExcel_Cell_DataType::TYPE_STRING)
                    ->setCellValueExplicit('C' . $index, $v['sname'], PHPExcel_Cell_DataType::TYPE_STRING)
                    ->setCellValue('D' . $index, $v['gname'])
                    ->setCellValue('E' . $index, $v['gweight'])
                    ->setCellValue('F' . $index, $v['og_real_price'])
                    ->setCellValue('G' . $index, $v['sales_volume'])
                    ->setCellValue('H' . $index, '')
                    ->setCellValue('I' . $index, '')
                    ->setCellValue('J' . $index, $v['cash']+$v['point'])
                    ->setCellValue('K' . $index, $v['gd_cost_price'])
                    ->setCellValue('L' . $index, '')
                    ->setCellValue('M' . $index, $v['gd_cost_price'])
                ;
            }
            $length = count($result) + 4;
            $objPHPExcel->setActiveSheetIndex(0);
            $objPHPExcel->getActiveSheet()->freezePane('A2');
            $objPHPExcel->setActiveSheetIndex(0)->getStyle('A1:AA' . $length)->getAlignment()->setWrapText(true)->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER)->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);

        }

        ob_end_clean(); //清空（擦除）缓冲区并关闭输出缓冲
        ob_start();//打开输出控制缓冲
        // Redirect output to a client’s web browser (Excel5)
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $title . '".xls"');
        header('Cache-Control: max-age=0');
        // If you're serving to IE 9, then the following may be needed
        header('Cache-Control: max-age=1');

        // If you're serving to IE over SSL, then the following may be needed
        header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT'); // always modified
        header('Cache-Control: cache, must-revalidate'); // HTTP/1.1
        header('Pragma: public'); // HTTP/1.0

        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objWriter->save('php://output');
        exit;
    }

}
