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
        $this->redirect();
    }

    //销售明细表
    public function sales_analysis()
    {
        $this->redirect();
    }

    //供应商明细表
    public function supplier()
    {
        $this->redirect();
    }

    //提成划分明细表
    public function division()
    {
        $this->redirect();
    }

    //销售排行榜表
    public function sales_rank()
    {
        $model = new Model("goods as gd");
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
        $objPHPExcel->setActiveSheetIndex(0) ->setCellValue('D2', '尺寸');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('E2', '单价');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('F2', '销量');
        $objPHPExcel->setActiveSheetIndex(0)->setCellValue('G2', '销售量');

        $goods = new Model("goods as gd");
        $shop = new Model("shop as sh");
        $result = $goods->join("left join shop as sh on gd.shop_id = sh.id")
            ->fields("gd.id as gid,sh.name as shname,gd.name as gdname,gd.base_sales_volume,sell_price")
            ->order("base_sales_volume desc")
            ->findAll();
        //销量

        $order_goods = new Model("order_goods as og");
        $sales_volume = $order_goods->join("left join order as o on og.order_id = o.id")->where("og.goods_id = $id o.status in (3,4)")->fields("SUM(og.goods_nums) as sell_volume")->findAll();
        $result['sales_volume'] = $sales_volume;
        echo "<pre>";
        print_r($result);
        die();
        if (!empty($result)) {
            foreach ($result as $k => $v) {
                $index = $k + 3;
                $objPHPExcel->setActiveSheetIndex(0)
                    ->setCellValueExplicit('A' . $index, $k+1)
                    ->setCellValueExplicit('B' . $index, $v['shname'], PHPExcel_Cell_DataType::TYPE_STRING)
                    ->setCellValueExplicit('C' . $index, $v['gdname'], PHPExcel_Cell_DataType::TYPE_STRING)
                    ->setCellValue('D' . $index, $v['base_sales_volume'])
                    ->setCellValue('E' . $index, $v['sell_price'])
                    ->setCellValue('F' . $index, $v['base_sales_volume'])
                    ->setCellValue('G' . $index, $v['base_sales_volume']*$v['sell_price']);
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

}
