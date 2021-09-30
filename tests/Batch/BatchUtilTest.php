<?php

namespace Freeman\LaravelBatch\Test\Batch;

use Carbon\Carbon;
use Freeman\LaravelBatch\BatchUtil;
use Freeman\LaravelBatch\Test\DBTestCase;
use Freeman\LaravelBatch\Test\Models\Player;
use Illuminate\Support\Arr;

class BatchUtilTest extends DBTestCase
{
    const DATA_INSERT = [
        [
            'name' => 'kaka',
            'birthday' => '1982-04-22',
            'salary_per_year' => 8.35,
            'is_captain' => false,
            'apps' => 300,
            // 'last_goal_at' => '2013-09-16 04:25',
            'attributes' => [
                'Acceleration' => 20,
                'Agility' => 18,
                'Balance' => 18,
                'Pace' => 20,
                'Stamina' => 15,
                'Strength' => 13,
            ],
            'positions' => ['AMC', 'AMCL'],
            'complex_string' => '',
            'complex_json' => '',
        ],
        [
            'name' => 'nesta',
            'birthday' => '1976-03-19',
            'salary_per_year' => 8.53,
            'is_captain' => true,
            'apps' => 350,
            // 'last_goal_at' => null,
            'attributes' => [
                'Acceleration' => 16,
                'Agility' => 15,
                'Balance' => 15,
                'Pace' => 16,
                'Stamina' => 17,
                'Strength' => 16,
            ],
            'positions' => ['DC', 'DCR'],
            'complex_string' => "Alessandro'Nesta\r187`cm\nAC\"Milan, DC/DCR\Italian",
            'complex_json' => [
                "'full_name" => "Alessandro'Nesta\r",
                '\Height' => "187`cm\n",
                '/team' => "AC\"Milan",
                '`position' => "DC/DCR",
                '"country' => "\Italian",
            ],
        ],
        [
            'name' => 'pirlo',
            'birthday' => '1979-05-19',
            'salary_per_year' => 8.5,
            'is_captain' => false,
            'apps' => 320,
            // 'last_goal_at' => '2014-08-21 03:35',
            'attributes' => [
                'Acceleration' => 12,
                'Agility' => 15,
                'Balance' => 15,
                'Pace' => 12,
                'Stamina' => 14,
                'Strength' => 10,
            ],
            'positions' => ['DM', 'MC'],
            'complex_string' => null,
            'complex_json' => null,
        ],
    ];

    const DATA_UPDATE = [
            [
                'id' => 1,
                'name' => 'kaka-22',
                'birthday' => '1982-04-22', // birthday do not change
                'salary_per_year' => 9,  // particular field
                'attributes' => [
                    'Acceleration' => 20,
                    'Agility' => 20,
                    'Balance' => 20,
                    'Pace' => 20,
                    'Stamina' => 20,
                    'Strength' => 20,
                ],
                'positions' => ['AMC', 'AMCL', 'FC'],
            ],
            [
                'id' => 2,
                'name' => 'nesta-13',
                'birthday' => '1976-03-19', // birthday do not change
                'is_captain' => false,
                'complex_string' => "Alessandro'Nesta\r187`cm\nAC\"Milan, DC/DCR\Italian.//\\\(*?-=)",
            ],
            [
                'id' => 3,
                'name' => 'pirlo-21',
                'birthday' => '1979-05-19', // birthday do not change
                'apps' => 300,
                'complex_json' => [
                    "'full_name" => "Andrea'Pirlo\r",
                    '\Height' => "177`cm\n",
                    '/team' => "AC\"Milan",
                    '`position' => "DM/DMC",
                    '"country' => "\Italian",
                ],
            ],
        ];

    private function assertNonJsonFields()
    {
        collect(self::DATA_INSERT)->each(function ($item) {
            $this->assertDatabaseHas('players', Arr::except($item, ['attributes', 'positions', 'complex_json', 'complex_string']));
        });
    }

    private function assertQueryResult()
    {
        $fields = array_keys(self::DATA_INSERT[0]);
        $this->assertEquals(self::DATA_INSERT, Player::query()->get($fields)->toArray());
    }

    /** @test */
    public function test_batch_insert()
    {
        $util = BatchUtil::ofModel(new Player);

        $ids = $util->batchInsert(self::DATA_INSERT);

        $this->assertDatabaseCount('players', 3);
        $this->assertNonJsonFields();
        $this->assertQueryResult();
        $this->assertEquals([1, 2, 3], $ids);

        $ids = $util->batchInsert(self::DATA_INSERT);
        $this->assertDatabaseCount('players', 6);
        $this->assertEquals([4, 5, 6], $ids);
    }

    public function test_batch_insert_failed()
    {
        $util = BatchUtil::ofModel(new Player);
        $data = self::DATA_INSERT;
        // batchInsert method fetch keys of first assoc item as insert fields default
        // unset the key 'complex_string' of the second assoc item to produce different fields
        unset($data[1]['complex_string']);

        $result = $util->batchInsert($data);
        $this->assertDatabaseCount('players', 0);
        $this->assertFalse($result);

        $data = self::DATA_INSERT;
        unset($data[0]['complex_string']);

        $result = $util->batchInsert($data);
        $this->assertDatabaseCount('players', 0);
        $this->assertFalse($result);
    }

    private function assertBatchUpdate()
    {
        collect(self::DATA_UPDATE)->each(function ($item) {
            $fields = array_keys($item);
            $id = $item['id'];

            $this->assertEquals($item, Player::query()->find($id, $fields)->toArray());
        });
    }

    /** @test */
    public function test_batch_update()
    {
        $util = BatchUtil::ofModel(new Player);

        $util->batchInsert(self::DATA_INSERT);

        $util->batchUpdate(self::DATA_UPDATE);
        $this->assertBatchUpdate();
    }

    /** @test */
    public function test_batch_save()
    {
        $players = collect(self::DATA_INSERT)->map(function($item) {
            return new Player($item);
        })->batchSave();

        $this->assertDatabaseCount('players', 3);
        $this->assertNonJsonFields();
        $this->assertQueryResult();
        $this->assertEquals([1, 2, 3], $players->pluck('id')->toArray());

        $this->assertEquals(['kaka', 'nesta', 'pirlo'], $players->pluck('name')->toArray());
        $players->map(function($player) {
            return $player->fill([
                'name' => $player->name . '-' . $player->id,
            ]);
        })->batchSave();
        $this->assertDatabaseCount('players', 3);
        $this->assertEquals(['kaka-1', 'nesta-2', 'pirlo-3'], $players->pluck('name')->toArray());

        // fill updating data to players
        $players = $players->zip(self::DATA_UPDATE)->map(function($item) {
            $player = $item[0];
            return $player->fill(array_merge($item[1], ['updated_at' => '2021-01-01 00:00:00']));   // set updated_at here for each item
        });
        // push item to save inserts and updates at the same time
        $players->push(new Player([
            'name' => 'shevchenko',
            'birthday' => '1976-09-29',
            'salary_per_year' => 10,
            'is_captain' => false,
            'apps' => 380,
            'attributes' => [],
            'positions' => [],
        ]));
        // set false not to update the updated_at field
        $players->batchSave(false);
        $this->assertDatabaseCount('players', 4);
        $this->assertDatabaseHas('players', ['name' => 'shevchenko']);
        $this->assertBatchUpdate();

        $this->assertEquals(['kaka-22', 'nesta-13', 'pirlo-21', 'shevchenko'], $players->pluck('name')->toArray());

        // assert the updated_at
        $this->assertEquals('2021-01-01 00:00:00', $players->first()->updated_at->toDateTimeString());
        $this->assertEquals(Carbon::now()->toDateTimeString(), $players->last()->created_at->toDateTimeString());
    }
}
