<?php

namespace Freeman\LaravelBatch\Test;

use PHPUnit\Framework\TestCase as BaseTestCase;
use ReflectionClass;
use Freeman\LaravelBatch\BatchServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $this->createDummyprovider()->register();
    }

    protected function createDummyprovider(): BatchServiceProvider
    {
        $reflectionClass = new ReflectionClass(BatchServiceProvider::class);

        return $reflectionClass->newInstanceWithoutConstructor();
    }
}
