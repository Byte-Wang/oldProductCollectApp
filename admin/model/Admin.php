<?php

namespace app\admin\model;

use think\facade\Config;
use think\facade\Db;
use think\Model;
use ba\Random;
use app\admin\model\AdminGroup;

/**
 * Admin模型
 * @controllerUrl 'authAdmin'
 */
class Admin extends Model
{
    /**
     * @var string 自动写入时间戳
     */
    protected $autoWriteTimestamp = 'int';

    /**
     * @var string 自动写人创建时间
     */
    protected $createTime = 'createtime';
    /**
     * @var string 自动写人更新时间
     */
    protected $updateTime = 'updatetime';

    /**
     * 追加属性
     */
    protected $append = [
        'group_arr',
        'group_name_arr',
        'team',
        'team_area_info',
        'manage_team',
    ];

    public function getGroupArrAttr($value, $row)
    {
        $groupAccess = Db::name('admin_group_access')
            ->where('uid', $row['id'])
            ->column('group_id');
        return $groupAccess;
    }

    public function getTeamAttr($value, $row)
    {
        $team = Db::name('team')
            ->alias('t')
            ->leftJoin('ba_team_area ta', 't.team_area_id = ta.id')
            ->where('t.id', $row['team_id'])
            ->field("t.*,ta.name as team_area_name")
            ->find();
        return $team;
    }

    public function getManageTeamAttr($value, $row) {
        $team = Db::name('team')
            ->alias('t2')
            ->where('t2.principal', $row['id'])
            ->find();
        return $team;
    }

    public function getGroupNameArrAttr($value, $row)
    {
        $groupAccess = Db::name('admin_group_access')
            ->where('uid', $row['id'])
            ->column('group_id');
        $groupNames  = AdminGroup::whereIn('id', $groupAccess)->column('name');
        return $groupNames;
    }

    public function getAvatarAttr($value)
    {
        return full_url($value, true, Config::get('buildadmin.default_avatar'));
    }

    public function getTeamAreaInfoAttr($value, $row)
    {
        $info = Db::name('team_area')
            ->where('id', $row['team_area_id'])
            ->field("id,name")
            ->find();
        return $info;
    }

    public function getLastlogintimeAttr($value)
    {
        return $value ? date('Y-m-d H:i:s', $value) : 'none';
    }

    /**
     * 重置用户密码
     * @param int    $uid         管理员ID
     * @param string $newPassword 新密码
     */
    public function resetPassword($uid, $newPassword)
    {
        $salt   = Random::build('alnum', 16);
        $passwd = encrypt_password($newPassword, $salt);
        $ret    = $this->where(['id' => $uid])->update(['password' => $passwd, 'salt' => $salt]);
        return $ret;
    }
}