<?php

namespace app\admin\controller;

use app\admin\model\Admin;
use app\admin\model\ProductCheck;
use app\common\controller\Backend;
use app\common\library\Excel;
use think\facade\Db;
use Exception;
use think\db\exception\PDOException;
use think\exception\ValidateException;

/**
 * 产品管理
 *
 */
class Product extends Backend
{
    /**
     * Product模型对象
     * @var \app\admin\model\Product
     */
    protected $model = null;

    protected $quickSearchField = ['id'];

    protected $defaultSortField = 'weigh,desc';

    protected $preExcludeFields = ['createtime', 'updatetime'];

    protected $noNeedPermission = ['calculateRatio', 'select', 'allotSelect'];

    protected $noNeedLogin = ['test','checkAsin'];

    public function initialize()
    {
        parent::initialize();
        $this->model = new \app\admin\model\Product;
    }

    //校验产品唯一性 asin or (category_rank and brand)
    //排除作废
    private function check($asin, $sid, $category_rank = null, $brand = null, $id = 0)
    {
        $model = $this->model;
        if ($id) {
            $model = $model->where('id', '<>', $id);
        }
        //if ($model->where(['asin' => $asin])->where('state', '<>', '-1')->count() > 0) {
        if ($model->where(['asin' => $asin, 'station_id' => $sid])->count() > 0) {
            throw new ValidateException("ASIN已存在");
            //$this->error("");
        }

        // 查询校验表，是否能够校验通过
        if ((new ProductCheck)->checkOnly($asin, $sid)) {
            throw new ValidateException("校验ASIN已存在");
        }

        //if ($model->where(['category_rank' => $category_rank, 'brand' => $brand])->where('state', '<>', '-1')->count() > 0) {

        //by zhijie - 11/05 关闭排名与品牌的重复校验
        /*if (!empty($category_rank) && !empty($brand)) {
            if ($model->where(['category_rank' => $category_rank, 'brand' => $brand])->count() > 0) {
                throw new ValidateException("排名和品牌已存在");
            }
        }*/
    }

    //计算比例和比例系数
    private function calculate($data)
    {
        $station_id = $data['station_id'];
        //查询配置
        $model = new \app\admin\model\Station;
        $station = $model->where(['status' => 1])->find($station_id);
        if (empty($station)) {
            throw new ValidateException("未查询到站点配置");
        }

        //计算公式
        //（（售价-FBA费用-（ value_a /   value_b    *重量/汇率）-售价*   value_c  -进价/汇率）/（进价+（    value_d   /     value_e*重量/汇率）*汇率）
        //$ratio = ($data['sale_price'] - $data['fba_price'] - ($station['value_a'] / $station['value_b'] * $data['weight'] / $data['rate']) - $data['sale_price'] * $station['value_c'] - $data['purchase_price'] / $data['rate']) / ($data['purchase_price'] + ($station['value_d'] / $station['value_e'] * $data['weight'] / $data['rate']) * $data['rate']);

        // (售价 - FBA费用 - (value_a / value_b * 重量 / 汇率) - (售价 * value_c) - 进价/汇率) * 汇率 / (进价 + (value_d / value_e * 重量 / 汇率) * 汇率)
        //  (
        //      售价 - FBA费用 -
        //          (
        //              value_a / value_b * 重量 / 汇率) -
        //                  (售价 * value_c) -
        //                      进价 / 汇率
        //          ) /
        //          (
        //              进价 +
        //                  (value_d / value_e * 重量 / 汇率) * 汇率
        //          )

        //加 + bcadd();
        //减 - bcsub();
        //乘 * bcmul();
        //除 / bcdiv();
        $step1 = bcdiv($station['value_a'], $station['value_b'], 5);
        $step2 = bcmul($step1, $data['weight'], 5);
        $step3 = bcdiv($step2, $data['rate'], 5);
        $step4 = bcsub($data['sale_price'], $data['fba_price'], 5);

        $step5 = bcmul($data['sale_price'], $station['value_c'], 5);
        $step6 = bcdiv($data['purchase_price'], $data['rate'], 5);
        $step7 = bcsub(bcsub(bcsub($step4, $step3, 5), $step5, 5), $step6, 5);
        // 漏掉一步 增加 * 汇率
        $step13 = bcmul($step7, $data['rate'], 5);

        $step8 = bcdiv($station['value_d'], $station['value_e'], 5);
        $step9 = bcmul($step8, $data['weight'], 5);
        $step10 = bcdiv($step9, $data['rate'], 5);
        $step11 = bcmul($step10, $data['rate'], 5);
        $step12 = bcadd($data['purchase_price'], $step11, 5);

        $ratio = round(bcdiv($step13, $step12, 5), 2);
        //$ratio = ($data['sale_price'] - $data['fba_price'] - ($station['value_a'] / $station['value_b'] * $data['weight'] / $data['rate']) - $data['sale_price'] * $station['value_c'] - $data['purchase_price'] / $data['rate']) / ($data['purchase_price'] + ($station['value_d'] / $station['value_e'] * $data['weight'] / $data['rate']) * $data['rate']);
        $limits = json_decode(json_encode($station['limit_array']), true);//json_decode($station['limit_array'], true);
        //limit 类型
        /*
         * 当 {$type='售价'} 小于等于 {$confine} 时,{$result=0}不可提交
         * 当 {$type='进价'} 小于等于 {$confine} 时,比例需大于等于{$result}可提交
         [
            [
                'type' => 'sale'  //sale-售价限制;purchase-进价限制
                'confine' => ''  // 进价/售价的金额
                'result' => ''     //比例的限制，需大于等于result可提交 / result=0是满足限制不可提交
            ]
         ]
         */
        $err = '';
        if (is_array($limits) && count($limits) > 0) {
            foreach ($limits as $key => $limit) {
                if ($limit['type'] == 'sale') {
                    //售价
                    $type = '售价';
                    $confine = $data['sale_price'];
                } else if ($limit['type'] == 'purchase') {
                    $type = '进价';
                    $confine = $data['purchase_price'];
                } else {
                    $err = "配置错误";
                    break;
                }

                if ($limit['result'] == 0) {
                    //if ($confine <= $limit['confine']) {
                    if (bccomp($confine, $limit['confine'], 2) != 1) {
                        $err = "当{$type}小于等于{$limit['confine']}时，不可提交";
                        break;
                    }
                } else {
                    $flag = true;
                    if ($key > 0) {
                        $last = $limits[$key - 1];
                        if ($limit['type'] == $last['type']) {
                            //增加判断条件
                            //当进价大于 {$last['confine']} 并且 小于$this 时候，执行判断
                            if (bccomp($confine, $last['confine'], 2) != 1) {
                                //满足了上衣
                                $flag = false; //执行下一步判断，否则直接成功
                            }
                        }
                    }

                    // if ($confine <= $limit['confine'] && $limit['result'] > $ratio) {
                    if ($flag && bccomp($confine, $limit['confine'], 2) != 1 && bccomp($limit['result'], $ratio, 2) == 1) {
                        $err = "当{$type}小于等于{$limit['confine']}时，比例需大于等于{$limit['result']}可提交，当前比例为{$ratio}";
                        break;
                    }
                }
            }
        }
        if ($err) {
            throw new ValidateException($err);
        }

        //加 + bcadd();
        //减 - bcsub();
        //乘 * bcmul();
        //除 / bcdiv();

        $temp1 = bcdiv($station['coefficient_a'], $station['coefficient_b'], 5);
        $temp2 = bcmul($temp1, $data['weight'], 5);
        $temp3 = bcdiv($temp2, $data['rate'], 5);
        $temp4 = bcmul($data['sale_price'], $station['coefficient_c'], 5);
        $temp5 = bcdiv($data['purchase_price'], $data['rate'], 5);
        $temp6 = bcsub($data['sale_price'], $data['fba_price'], 5);
        $temp7 = bcsub($temp6, $temp3, 5);
        $temp8 = bcsub($temp7, $temp4, 5);
        $temp9 = bcsub($temp8, $temp5, 5);
        // (售价 - FBA费用 - (coefficient_a / coefficient_b * 重量 / 汇率) - (售价 * coefficient_c) - 进价/汇率) * 汇率
        $rate = round(bcmul($temp9, $data['rate'], 5), 2);
        //$rate = ($data['sale_price'] - $data['fba_price'] - ($station['coefficient_a'] / $station['coefficient_b'] * $data['weight'] / $data['rate']) - $data['sale_price'] * $station['coefficient_c'] - $data['purchase_price'] / $data['rate']) * $data['rate'];

        //  校验比例限制
        /*
       * 当 {$type='比例系数'} 小于等于 {$confine} 时,{$result=0}不可提交
       [
          [
              'type' => 'sale'  //sale-售价限制;purchase-进价限制;coefficient-比例系数
              'confine' => ''  // 进价/售价的金额
              'result' => ''     //比例的限制，需大于等于result可提交 / result=0是满足限制不可提交
          ]
       ]
       */
        $climits = json_decode(json_encode($station['coefficient_limit_array']), true);// json_decode($station['coefficient_limit_array'], true);
        if (is_array($climits) && count($climits) > 0) {
            foreach ($climits as $limit) {
                if ($limit['type'] == 'coefficient') {
                    $type = '比例系数';
                    $confine = $rate;
                } else {
                    $err = "配置错误";
                    break;
                }

                if ($limit['result'] == 0) {
                    //if ($confine <= $limit['confine']) {
                    if (bccomp($confine, $limit['confine'], 2) != 1) {
                        $err = "当{$type}小于等于{$limit['confine']}时，不可提交，当前{$type}为{$confine}";
                        break;
                    }
                } else {
                    $err = "配置错误";
                    break;
                }
            }
        }
        if ($err) {
            throw new ValidateException($err);
        }

        $data = [
            'ratio' => $ratio,
            'coefficient' => $rate,
        ];

        return $data;
    }

    public function test()
    {
        $data = [
            'station_id' => 7,
            'weight' => 50,
            'rate' => 5,
            'sale_price' => 12.99,
            'purchase_price' => 8,
            'fba_price' => 4.04,
        ];
        try {

            $a = $this->calculate($data);
//            var_dump('结束');
            var_dump($a);
        } catch (ValidateException $e) {
            var_dump($e->getMessage());
        }
        die();

    }

    public function index()
    {
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->param('select')) {
            $this->select();
        }

        list($where, $alias, $limit, $order) = $this->queryBuilder();

        //type = 0 是个人 =1是团队
        $admin = $this->auth->getAdmin();
        $type = $this->request->get("type", 0);
        $whereRole = [];
        if ($type == 1) {
            //团队
            if (in_array(1, $admin->group_arr)) {
                //查看全部
                //审核员 的 待审核 为 1
            } elseif (in_array(2, $admin->group_arr)) {
                //审核员 只能查看分配的数据
                $whereRole = ['allot_id' => $admin->id];
            } elseif (in_array(3, $admin->group_arr)) {
                $whereRole = ['submit_team' => $admin->team_id];
            } else {
                $this->error('权限不足');
            }
        } else {
            $whereRole = ['submit_user' => $admin->id];
        }

        $res = $this->model
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->where($whereRole)
            ->where(['status' => 1]) // 【by wangzhijie】增加删除数据的判断
            // ->orderRaw("field(state,'0','1','4','2','5','3','-1'),createtime DESC")
            ->order($order) // by zhijie - 11/05 开启列表页排序功能
            ->paginate($limit);

        $this->success('', [
            'list' => $res->items(),
            'total' => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    public function add()
    {

        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            $this->preExcludeFields = ['createtime', 'updatetime', 'id'];
            $data = $this->excludeFields($data);
            $result = false;
            Db::startTrans();
            try {
                //数据校验
                $validate = new \app\admin\validate\Product;
                $validate->scene('add');
                $validate->check($data);

                //校验产品唯一性
                $this->check($data['asin'], $data['station_id'], $data['category_rank'], $data['brand']);

                //计算比例和比例系数
                $calculate = $this->calculate($data);
                $data['ratio'] = $calculate['ratio'];
                $data['coefficient'] = $calculate['coefficient'];

                //用户和用户team
                $admin = $this->auth->getAdmin();
                $data['submit_user'] = $admin['id'];
                $data['submit_team'] = $admin['team_id'];

                $result = $this->model->save($data);
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
            if ($row['state'] == '-1') {
                $this->error('作废的数据不能修改');
            }
            //if ($row['state'] == '1') {
               // $this->error('通过的数据不能修改');
           // }

            $data = $this->excludeFields($data);
            $result = false;
            Db::startTrans();
            try {
                //数据校验
                $validate = new \app\admin\validate\Product;
                $validate->scene('edit');
                $validate->check($data);

                //校验产品唯一性
                $this->check($data['asin'], $data['station_id'], $data['category_rank'], $data['brand'], $id);

                //计算比例和比例系数
                $calculate = $this->calculate($data);
                $data['ratio'] = $calculate['ratio'];
                $data['coefficient'] = $calculate['coefficient'];

                //用户和用户team
//                $admin = $this->auth->getAdmin();
//                $data['submit_user'] = $admin['id'];
//                $data['submit_team'] = $admin['team_id'];

                $result = $row->save($data);

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
                $this->success(__('Update successful'));
            } else {
                $this->error(__('No rows updated'));
            }

        }

        $this->success('', [
            'row' => $row
        ]);
    }

    //返回给前端比例
    public function calculateRatio()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }
            try {
                //数据校验
                $validate = new \app\admin\validate\Product;
                $validate->scene('calculate')->check($data);

                $data = $this->calculate($data);
                $this->success('', $data);

            } catch (ValidateException $e) {
                $this->error($e->getMessage());
            }
        }

        $this->error(__('Parameter error'));
    }

    //审核
    public function audit()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();

            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            //排除字段
            $this->preExcludeFields = [
                'createtime',
                'updatetime',
                'purchase_url',
                'image_url',
                'asin',
                'category_rank',
                'category',
                'product_name',
                'station_id',
                'weight',
                'rate',
                'ratio',
                'purchase_price',
                'sale_price',
                'fba_price'
            ];
            $data = $this->excludeFields($data);

            $row = $this->model->find($data['id']);

            if (!$row) {
                $this->error(__('Record not found'));
            }

            if ($row['state'] == '-1') {
                $this->error('作废的数据不能修改');
            }

            $admin = $this->auth->getAdmin();
            if (in_array(1, $admin->group_arr)) {
                //查看全部
                //超级管理员所有状态都能修改
                if ($row['state'] == '4') {
                    //复审
                    $data['second_user'] = $admin['id'];
                    $data['second_time'] = time();
                } elseif ($row['state'] == '0') {
                    //初审
                    $data['first_user'] = $admin['id'];
                    $data['first_time'] = time();

                    //审核通过，并且已分配过审核员。则直接改为待二审状态 4
                    if ($data['state'] == '1' && $row['allot_id'] != 0) {
                        $data['state'] = '4';
                    }
                }
            } elseif (in_array(2, $admin->group_arr)) {
                //审核员 的 二审待审核状态 为 4
                if ($row['state'] == '0' || $row['state'] == '1') {
                    $this->error('当前状态未分配');
                }

                if ($row['allot_id'] != $admin['id']) {
                    $this->error('没有权限，该数据未分配给此用户');
                }
                      //复审
                if ($row['state'] == '3' || $row['state'] == '4') {
                    $data['second_user'] = $admin['id'];
                    $data['second_time'] = time();
                } 
            } elseif (in_array(3, $admin->group_arr)) {
                //负责人 初审
                if ($row['state'] != '0') {
                    $this->error('当前不是待修改状态');
                }
                $data['first_user'] = $admin['id'];
                $data['first_time'] = time();

                //审核通过，并且已分配过审核员。则直接改为待二审状态 4
                if ($data['state'] == '1' && $row['allot_id'] != 0) {
                    $data['state'] = '4';
                }
            } else {
                $this->error('权限不足');
            }

            $result = false;
            Db::startTrans();
            try {
                //数据校验
                $validate = new \app\admin\validate\Product;
                $validate->scene('audit');
                $validate->check($data);

                $result = $row->save($data);

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
                $this->success(__('Update successful'));
            } else {
                $this->error(__('No rows updated'));
            }

        }
    }

    //导出数据
    public function export()
    {
        $this->request->filter(['strip_tags', 'trim']);

        list($where, $alias, $limit, $order) = $this->queryBuilder();

        //type = 0 是个人 =1是团队
        $admin = $this->auth->getAdmin();
//        $admin = Db::name('admin')->where('id=1')->find();
        $type = $this->request->get("type", 0);
        $whereRole = [];
        if ($type == 1) {
            //团队
            if (in_array(1, $admin->group_arr)) {
                //查看全部
                //审核员 的 待审核 为 1
            } elseif (in_array(2, $admin->group_arr)) {
                //审核员 只能查看分配的数据
                $whereRole = ['allot_id' => $admin->id];
            } elseif (in_array(3, $admin->group_arr)) {
                $whereRole = ['submit_team' => $admin->team_id];
            } else {
                $this->error('权限不足');
            }
        } else {
            $whereRole = ['submit_user' => $admin->id];
        }

        $res = $this->model
            ->withJoin($this->withJoinTable, $this->withJoinType)
            ->alias($alias)
            ->where($where)
            ->where($whereRole)
            ->order($order)
            ->select()->toArray();
        Excel::export($this->model->exportExcel, true, $res, '产品列表');
    }
    

    //导入数据
    public function import()
    {
        $file = $this->request->file('file');
        $list = Excel::import($file);
        //处理数据
        Db::startTrans();
        try {
            $all = [];
            foreach ($list as $shell) {
                $data = [];
                foreach ($this->model->importExcel as $k => $key) {
                    if (isset($shell[$k])) $data[$key] = $shell[$k];
                }
                //根据站点名称查询站点id
                if (!empty($data['station_name'])) {
                    $sid = Db::name('station')->where(['title' => $data['station_name']])->value('id');
                    if (!$sid) throw new ValidateException("未查询到站点信息");
                    $data['station_id'] = $sid;
                    unset($data['station_name']);
                } else {
                    throw new ValidateException("没有站点信息");
                }
                $data = $this->excludeFields($data);
                //数据校验
                (new \app\admin\validate\Product)->scene('import')->check($data);

                //校验产品唯一性
                $this->check($data['asin'], $data['station_id'], $data['category_rank'], $data['brand']);

                //计算比例和比例系数
                $calculate = $this->calculate($data);
                $data['ratio'] = $calculate['ratio'];
                $data['coefficient'] = $calculate['coefficient'];

                //用户和用户team
                $admin = $this->auth->getAdmin();
                $data['submit_user'] = $admin['id'];
                $data['submit_team'] = $admin['team_id'];

                $all[] = $data;
            }

            $result = $this->model->insertAll($all);
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

    public function checkAsin()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }

            $data = $this->excludeFields($data);
            try {
                //数据校验
                $validate = new \app\admin\validate\Product;
                $validate->scene('check');
                $validate->check($data);

                //校验产品唯一性
                $this->check($data['asin'], $data['station_id'], $data['category_rank'] ?? null, $data['brand'] ?? null, $data['id'] ?? 0);

            } catch (ValidateException $e) {
                $this->error($e->getMessage());
            }

            // 从外部数据库查询是否已存在 // by wangzhijie
            //  $isFind = $this->checkAsinFromApi($data['asin'], $data['station_id']);
            // if ($isFind==true){
            //     $this->error('ASIN已存在');
            // } else {
                $this->success('成功');
            // }
        }

        $this->error(__('Parameter error'));
    }
    
    private function checkAsinFromApi($asin,$station_id) {
        try {
            $data = array(
                'appid' => 'ds89W3232d3',
                'sec' => 'sidowx32dx',
                'searchVal' => $asin,
                'searchType' => '2'
            );
            $query = http_build_query($data);
            $options['http'] = array(
                'timeout'=>60,
                'method' => 'POST',
                'header' => 'Content-type:application/x-www-form-urlencoded',
                'content' => $query
            );
            $context = stream_context_create($options);
            $result=file_get_contents("http://api.banxi.cc/api/open/queryAsin/",false,$context);
            
            $resultObj = json_decode($result,true);
            $dataList=$resultObj['data']['data'];

            if (count($dataList) == 0) {
                return false;
            }

            $station_id_map = array(
                '11' => 'AUS',  //澳大利亚
                '9' => 'JP',    //日本
                '8' => 'UK',    // 英国
                '7' => 'CAN'    // 加拿大
            );
            if(!array_key_exists($station_id,$station_id_map)){
				return false;
			} 
            $station_code = $station_id_map[$station_id];
            $isFind=false;
            foreach ($dataList as $key => $value) {
                if (strtoupper($value['contry']) == $station_code) {
                    $isFind=true;
                }
            }

            return $isFind;
            
        } catch (ValidateException $e) {
            return false;
        }
        
         return false;
     }


    //分配
    public function allot()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();

            if (!$data) {
                $this->error(__('Parameter %s can not be empty', ['']));
            }
            $ids = $data['ids']; //分配的数据
            $allotId = $data['allot_id'];    //分配的用户id

            // 查询所有数据信息
            $list = Db::name('product')->whereIn('id', $ids)->select()->toArray();
            if (!$list) {
                $this->error("未选择有效数据");
            }
            $result = false;
            Db::startTrans();
            try {
                // 只能分配审核通过的数据
                foreach ($list as &$item) {
                    //判断用户状态，只有初审通过 state=1的能分配
                    if (($item['state'] == '1' || $item['first_user'] != 0) && $item['second_user'] == 0) {
                        //初审通过，或者是已初审，并且未二审的。都可以分配。
                        $item['allot_id'] = $allotId;
                        $item['state'] = '4';  //改为已分配/未二审状态
                    }
                }
                //保存全部数据
                $result = $this->model->saveAll($list);
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
                $this->success("分配成功");
            } else {
                $this->error("分配失败");
            }
        }
    }

    public function allotSelect()
    {
        $uids = Db::name('admin_group_access')->where(['group_id' => 2])->column('uid');
        $res = Db::name('admin')
            ->whereIn('id', $uids)
            ->paginate(9999);

        $this->success('', [
            'list' => $res->items(),
            'total' => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

}