<?php

namespace app\admin\model;

use think\facade\Db;
use think\Model;

/**
 * Product
 * @controllerUrl 'product'
 */
class ProductCheck extends Model
{
    // 表名
    protected $name = 'product_check';

    public function checkOnly($asin, $sid)
    {
        $name = Db::name('station')->where('id', $sid)->value('title') ?? '';
        if (empty($name)) {
            //失败
            return true;
        }

        return $this->where(['asin' => $asin, 'station_name' => $name])->count() > 0;
    }
}