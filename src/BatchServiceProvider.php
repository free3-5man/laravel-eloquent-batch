<?php

namespace Freeman\LaravelBatch;

use Carbon\Carbon;
use Illuminate\Support\ServiceProvider;
use \Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BatchServiceProvider extends ServiceProvider
{
    public function register()
    {
        Collection::macro('batchSave', function ($touchUpdatedAt = true) {
            /** @var Collection $this */
            if ($this->count() == 0)
                return $this;

            list($toUpdateColl, $toInsertColl) = $this->partition(function (Model $model) {
                return $model->exists;
            });

            $toUpdateData = $toUpdateColl->filter(function(Model $model) {
                return $model->isDirty();
            })->values()->map(function(Model $model) use($touchUpdatedAt) {
                $attributes = array_merge(
                    $model->only([
                        $model->getKeyName()
                    ]),
                    $model->getDirty(),
                    $model->usesTimestamps() && $touchUpdatedAt ? [
                        $model->getUpdatedAtColumn() => Carbon::now()->format($model->getDateFormat()),
                    ] : []
                );
                $model->fill($attributes);

                return $attributes;
            })->toArray();

            $toInsertColl = $toInsertColl->reject(function (Model $model) {
                return empty($model->getAttributes());
            })->values();
            $toInsertData = $toInsertColl->map(function (Model $model) {
                $attributes = array_merge(
                    $model->getAttributes(),
                    $model->usesTimestamps() ? [
                        $model->getCreatedAtColumn() => Carbon::now()->format($model->getDateFormat()),
                        $model->getUpdatedAtColumn() => Carbon::now()->format($model->getDateFormat()),
                    ] : []
                );
                $model->fill($attributes);

                return $attributes;
            })->toArray();

            try {
                DB::beginTransaction();

                $batchUtil = new BatchUtil($this->first());

                $returnIds = $batchUtil->batchInsert($toInsertData);
                
                $batchUtil->batchUpdate($toUpdateData);

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

            $toInsertColl->each(function(Model $model) {
                $model->exists = true;
            });
            // add returnIds to models in this collection
            if(is_array($returnIds) && !empty($returnIds))
                $toInsertColl->zip($returnIds)->map(function($item) {
                    /** @var Model $model */
                    $model = $item[0];
                    $id = $item[1];
                    return $model->fill([
                        $model->getKeyName() => $id,
                    ]);
                });

            return $toInsertColl->merge($toUpdateColl);
        });
    }
}
