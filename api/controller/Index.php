<?php

namespace app\api\controller;

use app\common\controller\Frontend;

class Index extends Frontend
{
    protected $noNeedLogin = ['index'];

    public function initialize()
    {
        parent::initialize();
    }

    public function index()
    {
        $this->success('', [
            'site' => [
                'site_name'     => get_sys_config('site_name'),
                'record_number' => get_sys_config('record_number'),
                'version'       => get_sys_config('version'),
            ],
        ]);
    }
}