<?php

namespace app\admin\controller\auth;

use ba\Random;
use Exception;
use app\common\controller\Backend;
use app\admin\model\Admin as AdminModel;
use think\db\exception\PDOException;
use think\exception\ValidateException;
use think\facade\Db;
use app\admin\model\AdminGroup;

class Admin extends Backend
{
    /**
     * @var AdminModel
     */
    protected $model = null;

    protected $preExcludeFields = ['createtime', 'updatetime', 'password', 'salt', 'loginfailure', 'lastlogintime', 'lastloginip'];

    protected $quickSearchField = ['username', 'nickname','team_id'];

    protected $noNeedPermission = ['index','select'];

    public function initialize()
    {
        parent::initialize();
        $this->model = new AdminModel();
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            /**
             * 由于有密码字段-对方法进行重写
             * 数据验证
             */
            if ($this->modelValidate) {
                try {
                    $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    $validate = new $validate;
                    $validate->scene('add')->check($data);
                } catch (ValidateException $e) {
                    Db::rollback();
                    $this->error($e->getMessage());
                }
            }

            $salt   = Random::build('alnum', 16);
            $passwd = encrypt_password($data['password'], $salt);

            $data   = $this->excludeFields($data);
            $result = false;
            Db::startTrans();
            try {
                $data['salt']     = $salt;
                $data['password'] = $passwd;
                $result           = $this->model->save($data);
                if ($data['group_arr']) {
                    $groupAccess = [];
                    foreach ($data['group_arr'] as $datum) {
                        $groupAccess[] = [
                            'uid'      => $this->model->id,
                            'group_id' => $datum,
                        ];
                    }
                    Db::name('admin_group_access')->insertAll($groupAccess);
                }
                Db::commit();
            } catch (ValidateException $e) {
                Db::rollback();
                $this->error($e->getMessage());
            } catch (PDOException $e) {
                Db::rollback();
                $this->error($e->getMessage());
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if ($result !== false) {
                $this->success(__('Added successfully'));
            } else {
                $this->error(__('No rows were added'));
            }
        }

        $this->error(__('Parameter error'));
    }

    public function edit($id = null)
    {
        $row = $this->model->find($id);
        if (!$row) {
            $this->error(__('Record not found'));
        }

        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            /**
             * 由于有密码字段-对方法进行重写
             * 数据验证
             */
            if ($this->modelValidate) {
                try {
                    $validate = str_replace("\\model\\", "\\validate\\", get_class($this->model));
                    $validate = new $validate;
                    $validate->scene('edit')->check($data);
                } catch (ValidateException $e) {
                    $this->error($e->getMessage());
                }
            }

            if ($this->auth->id == $data['id'] && $data['status'] == '0') {
                $this->error(__('Please use another administrator account to disable the current account!'));
            }

            if (isset($data['password']) && $data['password']) {
                $this->model->resetPassword($data['id'], $data['password']);
            }

            Db::name('admin_group_access')
                ->where('uid', $id)
                ->delete();
            if ($data['group_arr']) {
                $groupAccess = [];
                foreach ($data['group_arr'] as $datum) {
                    $groupAccess[] = [
                        'uid'      => $id,
                        'group_id' => $datum,
                    ];
                }
                Db::name('admin_group_access')->insertAll($groupAccess);
            }

            $data   = $this->excludeFields($data);
            $result = false;
            Db::startTrans();
            try {
                $result = $row->save($data);
                Db::commit();
            } catch (PDOException $e) {
                Db::rollback();
                $this->error($e->getMessage());
            } catch (Exception $e) {
                Db::rollback();
                $this->error($e->getMessage());
            }
            if ($result !== false) {
                $this->success(__('Update successful'));
            } else {
                $this->error(__('No rows updated'));
            }
        }

        $row['password'] = '';
        $this->success('', [
            'row' => $row
        ]);
    }

    public function select()
    {
        $this->request->filter(['strip_tags', 'trim']);

        list($where, $alias, $limit, $order) = $this->queryBuilder();

        //团队
        $admin = $this->auth->getAdmin();
        $whereRole = null;
        if (!in_array(1, $admin->group_arr) && !in_array(2, $admin->group_arr)) {
            $whereRole = ['team_id' => $admin->team_id];
        }

        $res = $this->model
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->where($whereRole)
            ->order($order)
            ->paginate(9999);

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }
}