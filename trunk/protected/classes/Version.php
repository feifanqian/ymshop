<?php

/**
 * 版本号模块
 * @version $Id: Version.php 272 2015-07-24 09:52:23Z karson $
 */
class Version {

    private $model = null;
    private $platform = 'android';
    public function __construct($platform) {
        $this->platform = strtolower($platform);
        $this->model = new Model();
    }

    /**
     * 读取所有版本号信息
     */
    public function all() {
        $cache = CacheFactory::getInstance();
        if($this->platform=='android'){
            $items = $cache->get("android_Version");
        }else if($this->platform=='ios'){
            $items = $cache->get("ios_Version");
        }else{
            return array();
        }
        if (is_null($items)) {
            $items = $this->model->table("version")->where("platform ='{$this->platform}'")->order("id desc")->findAll();
            $cache->set($this->platform."_Version", $items, 315360000);
        }
        return $items;
    }

    /**
     * 检测版本号
     * 
     * @param string $version 客户端版本号
     * @return mixed
     */
    public function check($version) {
        foreach ($this->all() as $k => $v) {
            if ($v['status'] == 'normal' && $this->inversion($version, $v['oldversion'])) {
                $updateversion = $v;
                break;
            }
        }
        if (isset($updateversion)) {
            $search = array('{version}', '{newversion}', '{downloadurl}', '{url}', '{packagesize}');
            $replace = array($version, $updateversion['newversion'], $updateversion['downloadurl'], $updateversion['downloadurl'], $updateversion['packagesize']);
            $upgradetext = str_replace($search, $replace, $updateversion['content']);
            $new = explode('.', $updateversion['newversion']);
            $old = explode('.', $version);
            $new_value = $new[0]*100+$new[1]*10+$new[2];
            $old_value = $old[0]*100+$old[1]*10+$old[2];
            if($new_value<=$old_value){
                return NULL;
            }
            return array(
                "enforce" => $updateversion['enforce'],
                "version" => $version,
                "newversion" => $updateversion['newversion'],
                "downloadurl" => $updateversion['downloadurl'],
                "packagesize" => $updateversion['packagesize'],
                "upgradetext" => $upgradetext
            );
        }
        return NULL;
    }

    /**
     * 检测版本是否的版本要求的数据中
     * 
     * @param string $version
     * @param array $data
     */
    function inversion($version, $data = array()) {
        //版本号以.分隔
        $data = is_array($data) ? $data : array($data);
        if ($data) {
            if (in_array("*", $data) || in_array($version, $data)) {
                return true;
            }
            $ver = explode('.', $version);
            if ($ver) {
                $versize = count($ver);
                //验证允许的版本
                foreach ($data as $m) {
                    $c = explode('.', $m);
                    if (!$c || $versize != count($c))
                        continue;
                    $i = 0;
                    foreach ($c as $a => $k) {
                        if (!$this->compare($ver[$a], $k)) {
                            continue 2;
                        } else {
                            $i++;
                        }
                    }
                    if ($i == $versize)
                        return true;
                }
                return false;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * 比较两个版本号
     * 
     * @param string $v1
     * @param string $v2
     * @return boolean
     */
    function compare($v1, $v2) {
        if ($v2 == "*" || $v1 == $v2) {
            return true;
        } else {
            $values = array();
            $k = explode(',', $v2);
            foreach ($k as $v) {
                if (strpos($v, '-') !== false) {
                    list($start, $stop) = explode('-', $v);
                    for ($i = $start; $i <= $stop; $i++) {
                        $values[] = $i;
                    }
                } else {
                    $values[] = $v;
                }
            }
            if (in_array($v1, $values)) {
                return true;
            } else {
                return false;
            }
        }
    }

}
