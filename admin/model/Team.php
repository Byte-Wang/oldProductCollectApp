<?php

namespace app\admin\model;

use think\facade\Db;
use think\Model;

/**
 * Team
 * @controllerUrl 'team'
 */
class Team extends Model
{
    // 表名
    protected $name = 'team';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    /**
     * 追加属性
     */
    protected $append = [
        'principal_info', //负责人
    ];

    public function getNoteTextareaAttr($value, $row)
    {
        return !$value ? '' : $value;
    }

    //获取负责人信息
    public function getPrincipalInfoAttr($value, $row)
    {
        $info = Db::name('admin')
            ->where('id', $row['principal'])
            ->field("id,username,nickname,avatar,email,mobile,team_id")
            ->find();
        return $info;
    }
}