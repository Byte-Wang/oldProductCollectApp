<?php

namespace app\admin\validate;

use think\Validate;

class Product extends Validate
{
    protected $failException = true;

    /**
     * 验证规则
     */
    protected $rule = [
        'asin' => 'require',
        'category_rank' => 'require',
        'category' => 'require',
        'product_name' => 'require',
        'station_id' => 'require',
        'brand' => 'require',
        'weight' => 'require',
        'rate' => 'require',
        'ratio' => 'require',
        'purchase_price' => 'require',
        'sale_price' => 'require',
        'fba_price' => 'require',
        'submit_user' => 'require',
        'state' => 'require',
        'reason' => 'require',
    ];

    /**
     * 提示消息
     */
    protected $message = [
    ];

    /**
     * 验证场景
     */
    protected $scene = [
        'add' => [
            'asin',
            'category_rank',
            'category',
            'product_name',
            'station_id',
            'brand',
            'weight',
            'rate',
            'ratio',
            'purchase_price',
            'sale_price',
            'fba_price',
        ],
        'import' => [
            'asin',
            'category_rank',
            'category',
            'product_name',
            'station_id',
            'weight',
            'rate',
            'purchase_price',
            'sale_price',
            'fba_price',
        ],
        'edit' => [
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
            'fba_price',
        ],
        'calculate' => [
            'station_id',
            'weight',
            'rate',
            'purchase_price',
            'sale_price',
            'fba_price',
        ],
        'audit' => [
            'id',
            'state',
        ],
        'check' => [
            'asin',
            'station_id',
        ],
    ];

}
