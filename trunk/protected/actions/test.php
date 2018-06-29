<?php
class TestAction extends Controller
{
    public $model = null;
    public $user = null;
    public $code = 1000;
    public $content = NULL;
    public $time = NULL;

    public function __construct()
    {
        $this->model = new Model();
    }

    public function test() {   		
    	$taobao_pid = $this->model->table('taoke_pid')->where('user_id is NULL')->order('id desc')->find();
    	$this->code = 0;
    	$this->content = $taobao_pid;
        // $this->time = time();
        return;
    }
}    