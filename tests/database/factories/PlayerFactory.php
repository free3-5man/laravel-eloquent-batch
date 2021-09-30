<?php

use Faker\Generator as Faker;
use Freeman\LaravelBatch\Test\Models\Player;

$factory->define(Player::class, function (Faker $faker) {
    return [
        'name' => $faker->name,
        'birthday' => $faker->date(),
        'salary_per_year' => $faker->randomFloat(null, 1, 10),
        'is_captain' => $faker->boolean(),
        'apps' => $faker->numberBetween(0, 100),
        'last_goal_at' => $faker->dateTime(),
        'attributes' => [
            'Acceleration' => $faker->numberBetween(1, 20),
            'Agility' => $faker->numberBetween(1, 20),
            'Balance' => $faker->numberBetween(1, 20),
            'Pace' => $faker->numberBetween(1, 20),
            'Stamina' => $faker->numberBetween(1, 20),
            'Strength' => $faker->numberBetween(1, 20),
        ],
        'positions' => $faker->randomElements([
            'STC','STCL','STCR',
            'AMC','AMCL','AMCR',
            'AML','AMR','STL','STR',
            'MC','MCL','MCR',
            'ML','MR',
            'DM','DMCL','DMCR',
            'WBL','WBR',
            'DC','DCL','DCR',
            'DL','DR',
            'SW',
            'GK',
        ]),
    ];
});
