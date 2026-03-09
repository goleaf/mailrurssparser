<?php

namespace Tests;

use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        EloquentFactory::expandRelationshipsByDefault();
        \App\Models\Article::disableSearchSyncing();
    }
}
