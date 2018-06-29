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
    	$this->code = 0;
    	$this->content = null;
        $this->time = time();
        return;
    }
}    