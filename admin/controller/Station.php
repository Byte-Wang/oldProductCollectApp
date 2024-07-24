<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use think\db\exception\PDOException;
use think\facade\Db;
use Exception;

/**
 * 配置比例管理
 *
 */
class Station extends Backend
{
    /**
     * Station模型对象
     * @var \app\admin\model\Station
     */
    protected $model = null;

    protected $quickSearchField = ['id'];

    protected $defaultSortField = 'id,desc';

    protected $preExcludeFields = ['createtime', 'updatetime'];

    protected $noNeedPermission = ['index', 'select'];

    public function initialize()
    {
        parent::initialize();
        $this->model = new \app\admin\model\Station;
    }

    public function del($ids = null)
    {
        if (!$this->request->isDelete() || !$ids) {
            $this->error(__('Parameter error'));
        }

        $pk = $this->model->getPk();
        $data = $this->model->where($pk, 'in', $ids)->select();
        //查询是否正在使用站点
        $check = Db::name('product')->where('station_id', 'in', $ids)->count();
        if ($check > 0) {
            $this->error("该站点数据正在使用中，不能删除");
        }
        $count = 0;
        Db::startTrans();
        try {
            foreach ($data as $v) {
                $count += $v->delete();
            }
            Db::commit();
        } catch (PDOException $e) {
            Db::rollback();
            $this->error($e->getMessage());
        } catch (Exception $e) {
            Db::rollback();
            $this->error($e->getMessage());
        }
        if ($count) {
            $this->success(__('Deleted successfully'));
        } else {
            $this->error(__('No rows were deleted'));
        }
    }

}