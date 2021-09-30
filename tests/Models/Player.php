<?php

namespace Freeman\LaravelBatch\Test\Models;

class Player extends Model
{
    protected $table = 'players';

    /**
     * @inheritdoc
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',

        'name' => 'string',
        'birthday' => 'datetime:Y-m-d',
        'salary_per_year' => 'float',
        'is_captain' => 'bool',
        'apps' => 'integer',
        'last_goal_at' => 'datetime:Y-m-d H:i',

        'attributes' => 'array',
        'positions' => 'array',
        'complex_json' => 'array',
    ];
}
