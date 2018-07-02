<?php
class TestAction extends Controller
{
    public $model = null;
    public $user = null;
    public $code = 1000;
    public $content = NULL;
    public $time = NULL;
    public $redis;

    public function __construct()
    {
        $this->model = new Model();
    }

    public function test() {
        $this->redis   = new \Redis();
        $this->redis->connect('127.0.0.1', 6379);
        $this->redis->set("say","hello world");  
        $content = $this->redis->get("say");   		
    	$this->code = 0;
    	$this->content = $content;
        return;
    }
    public function get_between($str, $start, $end) {
        $str1 = explode($start, $str);
        $str2 = explode($end, $str1[1]);
        return $str2[0];
    }
    public function cut($begin, $end, $str) {
        $t1 = mb_strpos($str, $begin);
        $t2 = mb_strpos($str, $end);
        $ret = mb_substr($str, $t1 + 3, $t2 - $t1);
        return $ret;
    }
    public function test2() {

//        $cache = CacheFactory::getInstance("redis");
        $key = '满58.00元减50元';
//        $cache->set($key, "aaaaa123", 100);
//        $this->content = $cache->get($key);

//        $this->content = $this->cut('减', '元', $key);
        $this->content = $this->get_between($key,'减', '元');

    }
}    