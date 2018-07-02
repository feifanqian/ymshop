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
}    