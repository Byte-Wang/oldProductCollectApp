<?php

namespace app\admin\controller;

use app\common\controller\Backend;

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

    public function initialize()
    {
        parent::initialize();
        $this->model = new \app\admin\model\Team;
    }

    public function select()
    {
        $this->request->filter(['strip_tags', 'trim']);

        list($where, $alias, $limit, $order) = $this->queryBuilder();

        //团队
        $admin = $this->auth->getAdmin();
        $whereRole = null;
        if (!in_array(1, $admin->group_arr) && !in_array(2, $admin->group_arr)) {
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