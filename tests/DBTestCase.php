<?php

namespace Freeman\LaravelBatch\Test;

use Freeman\LaravelBatch\BatchServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase as Orchestra;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class DBTestCase extends Orchestra
{
    /**
     * {@inheritdoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->resetDatabase();

        // $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
        // $this->withFactories(__DIR__ . '/database/factories');

        // $this->artisan('migrate', ['--database' => 'sqlite']);

        $this->migrateTables();
    }

    /**
     * {@inheritdoc}
     */
    protected function getPackageProviders($app)
    {
        return [
            BatchServiceProvider::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver'   => 'sqlite',
            'database' => __DIR__ . '/database/database.sqlite',
            'prefix'   => '',
        ]);
        $app['config']->set('app.key', 'wslxrEFGWY6GfGhvN9L3wH3KSRJQQpBD');
    }

    /**
     * Reset the database.
     *
     * @return void
     */
    protected function resetDatabase()
    {
        file_put_contents(__DIR__ . '/database/database.sqlite', null);
    }

    private function migrateTables()
    {
        Schema::create('players', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->softDeletes();

            $table->string('name');
            $table->date('birthday');
            $table->double('salary_per_year');    // the unit is M
            $table->boolean('is_captain')->default(false);
            $table->integer('apps');    // played matches number
            $table->dateTime('last_goal_at')->nullable();
            $table->jsonb('attributes');
            $table->jsonb('positions');
            $table->string('complex_string')->nullable();
            $table->jsonb('complex_json')->nullable();
        });
    }

    protected function assertDatabaseCount(string $table, int $count)
    {
        $this->assertEquals(DB::table($table)->count(), $count);
    }
}
