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
        'belong_team_area_id',
    ];
    
    public function getBelongTeamAreaIdAttr($value, $row)
    {
         // 1. 判断 $row['team_area_id'] 是否有值
        if (!empty($row['team_area_id'])) {
            return $row['team_area_id'];
        }
    
        // 2. 如果 $row['team_area_id'] 为空，判断 $row['team_id'] 是否有值
        if (!empty($row['team_id'])) {
            // 使用 ThinkPHP 查询语句查询 team_area_id
            $teamAreaId = Db::name('team')
                ->where('id', $row['team_id']) // 根据 team_id 查询
                ->value('team_area_id');      // 只取 team_area_id 字段的值
    
            // 3. 检查查询结果是否有值
            if (!empty($teamAreaId)) {
                return $teamAreaId; // 如果有值，直接返回
            }
        }
    
        // 4. 如果以上条件都不满足，返回 0
        return 0;
    }

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