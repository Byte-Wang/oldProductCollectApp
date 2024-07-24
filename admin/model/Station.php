<?php

namespace app\admin\model;

use think\Model;

/**
 * Station
 * @controllerUrl 'station'
 */
class Station extends Model
{
    // 表名
    protected $name = 'station';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    // 设置json类型字段
    protected $json = ['limit_array', 'coefficient_limit_array'];
    
    // 设置JSON数据返回数组
    protected $jsonAssoc = true;


    public function getLimitArrayAttr($value, $row)
    {
        return !$value ? '' : $value;
    }

    public function getCoefficientLimitArrayAttr($value, $row)
    {
        return !$value ? '' : $value;
    }
}