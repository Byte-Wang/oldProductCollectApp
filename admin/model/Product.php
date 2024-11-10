<?php

namespace app\admin\model;

use think\db\Raw;
use think\facade\Db;
use think\Model;

/**
 * Product
 * @controllerUrl 'product'
 */
class Product extends Model
{
    // 表名
    protected $name = 'product';

    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';

    public $exportExcel = [
        'asin' => 'ASIN',
        'category_rank' => '类目排名',
        'category' => '类目',
        'product_name' => '产品名称',
        'station_name' => '站点名称',
        'brand' => '品牌名',
        'weight' => '重量(g)',
        'rate' => '汇率',
        'purchase_price' => '进价',
        'sale_price' => '售价',
        'fba_price' => 'FBA费用',
        'ratio' => '比例',
        'coefficient' => '比例系数',
        'purchase_url' => '采购链接',
        'image_url' => '图片链接',
        'remark' => '样品采购备注',
        'submit_user_name' => '提交人',
        'submit_team_name' => '提交团队',
        'allot_name' => '审核员',
        'state_text' => '状态',
        'reason' => '审核反馈',
    ];

    public $importExcel = [
        0 => 'asin',                            // 'ASIN',
        1 => 'category_rank',                   // '类目排名',
        2 => 'category',                        // '类目',
        3 => 'product_name',                    // '产品名称',
        4 => 'station_name',                    // '站点名称',
        5 => 'brand',                           // '品牌名',
        6 => 'weight',                          // '重量(g)',
        7 => 'rate',                            // '汇率',
        8 => 'purchase_price',                  // '进价',
        9 => 'sale_price',                      // '售价',
        10 => 'fba_price',                      // 'FBA费用',
        //'ratio',                              // '比例',
        //'coefficient',                        // '比例系数',
        11 => 'purchase_url',                   // '采购链接',
        12 => 'image_url',                      // '图片链接',
        13 => 'remark',                         // '样品采购备注',
    ];

    /**
     * 追加属性
     */
    protected $append = [
        'submit_user_info', //提交人
        'submit_team_info', //提交团队
        'station_name', //站点名称
        'submit_user_name', //提交人名称
        'submit_team_name', //提交团队名称
        'state_text', //提交团队名称
        'first_user_info', //一审团队负责人
        'second_user_info', //二审审核员
        'allot_name',
        'allot_info', //审核员信息
    ];

    public function orderRaw(string $field, array $bind = [])
    {
        $this->options['order'][] = new Raw($field, $bind);

        return $this;
    }


    protected static function onAfterInsert($model)
    {
        if ($model->weigh == 0) {
            $pk = $model->getPk();
            $model->where($pk, $model[$pk])->update(['weigh' => $model[$pk]]);
        }
    }

    public function getSubmitUserInfoAttr($value, $row)
    {
        $info = Db::name('admin')
            ->where('id', $row['submit_user'])
            ->field("id,username,nickname,avatar,email,mobile,team_id")
            ->find();
        return $info;
    }

    public function getSubmitTeamInfoAttr($value, $row)
    {
        $info = Db::name('team')
            ->where('id', $row['submit_team'])
            ->find();
        return $info;
    }


    public function getStationNameAttr($value, $row)
    {
        $info = Db::name('station')
            ->where('id', $row['station_id'])
            ->value('title', '');
        return $info;
    }

    public function getStationIdAttr($value, $row)
    {
        return !$value ? '' : $value;
    }

    public function getRemarkAttr($value, $row)
    {
        return !$value ? '' : $value;
    }

    public function getReasonAttr($value, $row)
    {
        return !$value ? '' : $value;
    }

    public function getBrandAttr($value, $row)
    {
        return !$value ? '' : htmlspecialchars_decode($value);
    }
    
    public function getCategoryAttr($value, $row)
    {
        return !$value ? '' : htmlspecialchars_decode($value);
    }

    public function getSubmitUserNameAttr($value, $row)
    {
        $info = Db::name('admin')
            ->where('id', $row['submit_user'])
            ->value('nickname'); // by zhijie - 11/05  导出产品列表的excel中，提交人一列由账号名改为真实名字（昵称）
        return $info ?? '';
    }

    public function getFirstUserInfoAttr($value, $row)
    {
        $info = Db::name('admin')
            ->where('id', $row['first_user'])
            ->find();
        return $info;
    }

    public function getSecondUserInfoAttr($value, $row)
    {
        $info = Db::name('admin')
            ->where('id', $row['second_user'])
            ->find();
        return $info;
    }

    public function getAllotInfoAttr($value, $row)
    {
        $info = Db::name('admin')
            ->where('id', $row['allot_id'])
            ->find();
        return $info;
    }

    public function getSubmitTeamNameAttr($value, $row)
    {
        $info = Db::name('team')
            ->where('id', $row['submit_team'])
            ->value('name');
        return $info ?? '';
    }

    public function getAllotNameAttr($value, $row)
    {
        if (!empty($row['allot_id'])) {

            $info = Db::name('admin')
                ->where('id', $row['allot_id'])
                ->value('username');
            return $info ?? '';
        }
        return '';
    }

    public function getStateTextAttr($value, $row)
    {
        //状态:0=待审核,1=初审通过,2=驳回,3=审核通过,-1=作废
        //状态:-1=无效数据,0=待审核,1=初审通过,2=驳回,3=合格,4=待二审,5=异议二审
        $text = '';
        switch ($row['state']) {
            case '-1';
                $text = '无效数据';
                break;
            case '0';
                $text = '待审核';
                break;
            case '1';
                $text = '初审通过';
                break;
            case '2';
                $text = '驳回';
                break;
            case '3';
                $text = '合格';
                break;
            case '4';
                $text = '待二审';
                break;
            case '5';
                $text = '异议二审';
                break;

        }
        return $text;
    }
}