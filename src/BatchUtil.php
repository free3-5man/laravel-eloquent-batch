<?php

namespace Freeman\LaravelBatch;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class BatchUtil
{
    private $model;

    /** @var \PDO $pdo */
    private $pdo;
    private $connection;
    private $driver;

    private $table;
    private $primaryKey;

    private $chunkSize;

    private $updateIndex;

    public function __construct(Model $model) {
        if(is_null($model->getConnectionName()))
            $model->setConnection(config('database.default'));

        $this->model = $model;

        $this->pdo = $model->getConnection()->getPdo();
        $this->connection = $model->getConnectionName();
        $this->driver = config('database.connections.' . $this->connection . '.driver');

        $this->table = $model->getTable();
        $this->primaryKey = $model->getKeyName();

        $this->chunkSize = 1000;

        $this->updateIndex = $this->primaryKey;
    }

    public static function ofModel(Model $model)
    {
        return new static($model);
    }

    public function setChunkSize(int $size)
    {
        $this->chunkSize = $size;

        return $this;
    }

    public function setUpdateIndex(string $updateIndex)
    {
        $this->updateIndex = $updateIndex;

        return $this;
    }

    private function buildBatchInsertSql(array $values)
    {
        $fields = array_keys($values[0]);
        $valuesString = collect($values)->map(function($value) use($fields) {
            ksort($value);
            $valueStr = collect($value)
                ->filter(function ($item, $key) use ($fields) {
                    return in_array($key, $fields);
                })
                ->map(function ($item) {
                    return is_null($item) ? 'NULL' : $this->escape($item);
                })->implode(', ');

            return "($valueStr)";
        })->implode(', ');

        sort($fields);
        $fieldsString = collect($fields)->map(function($field) {
            return $this->getWrappedField($field);
        })->implode(', ');

        return "INSERT INTO {$this->table} ({$fieldsString}) VALUES {$valuesString};";
    }

    /**
     * batch insert values to table
     *
     * @author Michael Freeman <free3_5man@163.com>
     * @DateTime 2021-09-22 14:00:43
     * @param array $values
     * @return bool|array return false if failed or no values, return the inserted ids array while model has self-incrementing primary key
     */
    public function batchInsert(array $values)
    {
        $count = count($values);
        if ($count == 0)
            return false;

        // fetch keys of first assoc item as insert fields default
        $fields = array_keys($values[0]);
        // validate fields of each item
        $bEachItemHasFields = collect($values)->reduce(function ($carry, $item) use ($fields) {
            return $carry && Arr::isAssoc($item) && empty(array_diff($fields, array_keys($item))) && empty(array_diff(array_keys($item), $fields));
        }, true);
        if ($bEachItemHasFields == false)
            return false;

        try {
            DB::beginTransaction();

            $lastId = 0;
            if ($this->model->getIncrementing())
            {
                // $lastId = $this->model->withTrashed()->latest($this->primaryKey)->value($this->primaryKey) ?? 0;

                $lastId = $this->pdo->lastInsertId($this->primaryKey);
                $lastId = $lastId == 1 ? $lastId - 1 : $lastId;
                // dump($lastId);
            }

            $returnIds = collect($values)->chunk($this->chunkSize)
            ->reduce(function ($returnIds, $chunk) use ($count, $lastId) {
                $sql = $this->buildBatchInsertSql($chunk->values()->toArray());
                // dump($sql);
                DB::connection($this->connection)->select($sql);

                // or call DB insert method instead
                /* $insertValues = array_map(function ($item) {
                    return array_map(function ($value) {
                        return is_array($value) ? json_encode($value) : $value;
                    }, $item);
                }, $chunk->toArray());
                DB::connection($this->connection)->table($this->table)->insert($insertValues); */

                $ids = [];
                // 只针对自增长ID有效
                if ($this->model->getIncrementing())
                    $ids = range($lastId + 1, $lastId + $count);

                return array_merge($returnIds, $ids);
            }, []);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $returnIds;
    }

    public function escape($value)
    {
        if (is_bool($value))
            return $value ? 'true' : 'false';

        if (is_array($value))
            $value = json_encode($value);

        if (is_string($value)) {
            // 把单个反斜杠替换为两个反斜杠，这种不可取，因为会有App\Models\xxx这样的情况
            // $result = str_replace('\\', '\\\\', $value);
            // 所以改为把尾部连续的反斜杠替换为空字符串
            $result = preg_replace('/\\\+$/', '', $value);

            return $this->pdo->quote($result);
        }

        return $value;
    }

    public function buildBatchUpdateSql(array $values)
    {
        // except items do not have id
        $filtered = collect($values)->filter(function($value) {
            return !empty($value[$this->updateIndex]);
        });

        $stringids = $filtered->map(function ($value) {
            $id = $value[$this->updateIndex];
            return "'{$id}'";
        })->implode(', ');

        $fieldsExceptId = collect($values)
            // collect all fields/keys of each item
            ->reduce(function(Collection $carry, $value) {
                return $carry->merge(array_keys($value));
            }, collect([]))
            // unique
            ->unique()->values()
            // reject the index field
            ->reject(function($value) {
                return $value === $this->updateIndex;
            });

        $stringCase = $fieldsExceptId->map(function($field) use($filtered) {
            $stringWhenThen = $filtered
                // filter field exists items
                ->filter(function($item) use($field) {
                    return array_key_exists($field, $item);
                })
                ->pluck($field, $this->updateIndex)->map(function($fieldValue, $idValue) {
                    $escapedValue = is_null($fieldValue) ? 'NULL' : $this->escape($fieldValue);
                    return "WHEN \"{$this->updateIndex}\" = '{$idValue}' THEN {$escapedValue}";
                })->implode("\n");

            return "\"{$field}\" = (CASE {$stringWhenThen} \nELSE {$field} END)";
        })->implode(', ');

        return "UPDATE \"{$this->table}\" SET {$stringCase} WHERE \"{$this->updateIndex}\" IN ({$stringids});";
    }

    public function batchUpdate($values)
    {
        if (empty($values))
            return false;
        if (empty($this->updateIndex))
            return false;

        try {
            DB::beginTransaction();

            collect($values)->chunk($this->chunkSize)->each(function($chunk) {
                $query = $this->buildBatchUpdateSql($chunk->values()->toArray());

                DB::connection($this->connection)->statement($query);
            });

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function getWrappedField(string $field)
    {
        // mysql 用``包裹字段，pgsql用""包裹
        return $this->driver == 'mysql' ? "`{$field}`" : "\"{$field}\"";
    }
}
