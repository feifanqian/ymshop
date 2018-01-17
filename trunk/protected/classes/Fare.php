<?php

//运费计算类
class Fare {

    private $weight = 0;

    public function __construct($weight = 0) {
        $this->weight = $weight;
    }

    /**
     * 计算运费
     * @param int $address_id
     * @param array $productarr
     * @return float
     */
    public function calculate($address_id, $productarr = NULL) {
        if ($productarr) {
            $product_ids = array_filter(array_keys($productarr));
            $model = new Model("products as pr");
            $list = $model->fields("pr.id,pr.goods_id,pr.weight,go.shop_id,go.freeshipping")
                    ->join("goods as go on pr.goods_id=go.id")
                    ->where("pr.id IN (" . implode(',', $product_ids) . ")")
                    ->findAll();
            $shop_ids = array();
            $tmplist = array();
            foreach ($list as $k => $v) {
                $shop_ids[] = $v['shop_id'];
                $v['nums'] = $productarr[$v['id']];
                $tmplist[$v['id']] = $v;
            }
            $productlist = array();
            foreach ($productarr as $k => $v) {
                if (!isset($tmplist[$k]) || $tmplist[$k]['freeshipping'])
                    continue;
                $productlist[$k] = $tmplist[$k];
            }
            $shopdict = array();
            $shoplist = $model->table("shop")->fields("id,freeshipping")->where("id IN (" . implode(',', $shop_ids) . ")")->findAll();
            foreach ($shoplist as $k => $v) {
                $shopdict[$v['id']] = $v['freeshipping'];
            }

            $shopweight = array();
            foreach ($productlist as $k => $v) {
                if (isset($shopdict[$v['shop_id']]) && $shopdict[$v['shop_id']]) {
                    unset($productlist[$k]);
                    continue;
                }
                $shopweight[$v['shop_id']] = isset($shopweight[$v['shop_id']]) ? $shopweight[$v['shop_id']] : 0;
                $shopweight[$v['shop_id']]+=$v['weight'] * $v['nums'];
            }

            $totalfare = 0;
            //按商家来计算运费
            foreach ($shopweight as $k => $v) {
                $totalfare += $this->calculatenow($address_id, $v);
            }
        } else {
            $totalfare = $this->calculatenow($address_id);
        }
        return sprintf("%01.2f", $totalfare);
    }
    
    /**
     * 计算运费
     * @param int $address_id
     * @param int $weight
     * @return float
     */
    public function calculatenow($address_id, $weight = NULL) {
        // $weight = is_null($weight) ? $this->weight : $weight;
        $weight = $this->weight;
        $total = 0;
        $model = new Model("fare");
        $fare = $model->where("is_default=1")->find();
        if ($fare && $weight) {
            $addr = $model->table('address')->where("id=$address_id")->find();
            if ($addr) {
                $city = $addr['city'];
                $first_price = $fare['first_price'];
                $second_price = $fare['second_price'];
                $first_weight = $fare['first_weight'];
                $second_weight = $fare['second_weight'];

                $zoning = unserialize($fare['zoning']);
                foreach ($zoning as $zon) {
                    if (preg_match('/,' . $city . ',/', ',' . $zon['area'] . ',') > 0) {
                        $first_price = $zon['f_price'];
                        $second_price = $zon['s_price'];
                        $first_weight = $zon['f_weight'];
                        $second_weight = $zon['s_weight'];
                        break;
                    }
                }

                if ($weight <= $first_weight)
                    $total = $first_price;
                else {
                    $lastweight = $weight - $first_weight;
                    $total = $first_price + ceil($lastweight / $second_weight) * $second_price;
                }
            }
        }
        return sprintf("%01.2f", $total);
    }

    public function calculates($address_id, $productarr = NULL) {
        if ($productarr) {
            $product_ids = array_filter(array_keys($productarr));
            $model = new Model("products as pr");
            $list = $model->fields("pr.id,pr.goods_id,pr.weight,go.shop_id,go.freeshipping")
                    ->join("goods as go on pr.goods_id=go.id")
                    ->where("pr.id IN (" . implode(',', $product_ids) . ")")
                    ->findAll();
            $shop_ids = array();
            $tmplist = array();
            foreach ($list as $k => $v) {
                $shop_ids[] = $v['shop_id'];
                $v['nums'] = $productarr[$v['id']];
                $tmplist[$v['id']] = $v;
            }
            $productlist = array();
            foreach ($productarr as $k => $v) {
                if (!isset($tmplist[$k]) || $tmplist[$k]['freeshipping'])
                    continue;
                $productlist[$k] = $tmplist[$k];
            }
            $shopdict = array();
            $shoplist = $model->table("shop")->fields("id,freeshipping")->where("id IN (" . implode(',', $shop_ids) . ")")->findAll();
            foreach ($shoplist as $k => $v) {
                $shopdict[$v['id']] = $v['freeshipping'];
            }

            $shopweight = array();
            foreach ($productlist as $k => $v) {
                if (isset($shopdict[$v['shop_id']]) && $shopdict[$v['shop_id']]) {
                    unset($productlist[$k]);
                    continue;
                }
                $shopweight[$v['shop_id']] = isset($shopweight[$v['shop_id']]) ? $shopweight[$v['shop_id']] : 0;
                $shopweight[$v['shop_id']]+=$v['weight'] * $v['nums'];
            }

            $totalfare = 0;
            //按商家来计算运费
            foreach ($shopweight as $k => $v) {
                $totalfare += $this->calculatenows($address_id, $v);
            }
        } else {
            $totalfare = $this->calculatenows($address_id);
        }
        return sprintf("%01.2f", $totalfare);
    }
    
    /**
     * 计算运费
     * @param int $address_id
     * @param int $weight
     * @return float
     */
    public function calculatenows($address_id, $weight = NULL) {
        // $weight = is_null($weight) ? $this->weight : $weight;
        $weight = $this->weight;
        var_dump($weight);die;
        $total = 0;
        $model = new Model("fare");
        $fare = $model->where("is_default=1")->find();
        if ($fare && $weight) {
            $addr = $model->table('address')->where("id=$address_id")->find();
            if ($addr) {
                $city = $addr['city'];
                $first_price = $fare['first_price'];
                $second_price = $fare['second_price'];
                $first_weight = $fare['first_weight'];
                $second_weight = $fare['second_weight'];

                $zoning = unserialize($fare['zoning']);
                foreach ($zoning as $zon) {
                    if (preg_match('/,' . $city . ',/', ',' . $zon['area'] . ',') > 0) {
                        $first_price = $zon['f_price'];
                        $second_price = $zon['s_price'];
                        $first_weight = $zon['f_weight'];
                        $second_weight = $zon['s_weight'];
                        break;
                    }
                }

                if ($weight <= $first_weight)
                    $total = $first_price;
                else {
                    $lastweight = $weight - $first_weight;
                    $total = $first_price + ceil($lastweight / $second_weight) * $second_price;
                }
            }
        }
        return sprintf("%01.2f", $total);
    }

}
