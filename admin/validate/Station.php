<?php

namespace app\admin\validate;

use think\Validate;

class Station extends Validate
{
    protected $failException = true;

    /**
     * 验证规则
     */
    protected $rule = [
        'value_a' => 'require|gt:0',
        'value_b' => 'require|gt:0',
        'value_c' => 'require|gt:0',
        'value_d' => 'require|gt:0',
        'value_e' => 'require|gt:0',
        'limit_array' => 'require|array',
        'coefficient_a' => 'require|gt:0',
        'coefficient_b' => 'require|gt:0',
        'coefficient_c' => 'require|gt:0',
        'coefficient_limit_array' => 'require|array',
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
        'add' => ['value_a', 'value_b', 'value_c', 'value_d', 'value_e', 'limit_array', 'coefficient_a', 'coefficient_b', 'coefficient_c', 'coefficient_limit_array'],
        'edit' => ['value_a', 'value_b', 'value_c', 'value_d', 'value_e', 'limit_array', 'coefficient_a', 'coefficient_b', 'coefficient_c', 'coefficient_limit_array'],
    ];

}
