<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulerTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_builds_without_errors(): void
    {
        $this->artisan('schedule:list')->assertSuccessful();
    }
}
