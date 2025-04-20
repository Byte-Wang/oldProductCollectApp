<?php

namespace app\admin\controller;

use app\admin\model\Admin;
use app\common\controller\Backend;
use think\db\Raw;
use think\facade\Db;

/**
 * 团队管理
 *
 */
class Team extends Backend
{
    /**
     * Team模型对象
     * @var \app\admin\model\Team
     */
    protected $model = null;

    protected $quickSearchField = ['id'];

    protected $defaultSortField = 'id,desc';

    protected $preExcludeFields = ['createtime', 'updatetime'];

    protected $noNeedPermission = ['select', 'index'];
    
    protected $noNeedLogin = ['index'];

    public function initialize()
    {
        parent::initialize();
        $this->model = new \app\admin\model\Team;
    }
    
    public function index() {
        $this->request->filter(['strip_tags', 'trim']);
        
        list($where, $alias, $limit, $order) = $this->queryBuilder();
        
        $admin = $this->auth->getAdmin();
        $teamAreaRole = '';
        $currentTeamArea = $admin->belong_team_area_id;
        if ($currentTeamArea && $currentTeamArea != 0) {
            $teamAreaRole = 'team.team_area_id = '.$currentTeamArea;
        }
         
        $res = $this->model
            ->alias($alias)
            ->where($where)
            ->where($teamAreaRole)
            ->order($order)
            ->paginate($limit);
            
            
        $sql = Db::getLastSql();

        $this->success('', [
            'list' => $res->items(),
            'total' => $res->total(),
            'remark' => get_route_remark(),
            'sql' => $sql
        ]);
    }

    public function select()
    {
        $this->request->filter(['strip_tags', 'trim']);

        list($where, $alias, $limit, $order) = $this->queryBuilder();

        //团队
        $admin = $this->auth->getAdmin();
        $whereRole = null;
        if (!in_array(1, $admin->group_arr) && !in_array(2, $admin->group_arr) && !in_array(5, $admin->group_arr)) {
            $whereRole = ['id' => $admin->team_id];
        }
        $res = $this->model
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->where($whereRole)
            ->order($order)
            ->paginate(9999);

        $this->success('', [
            'list' => $res->items(),
            'total' => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

}