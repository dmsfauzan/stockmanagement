<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_commands_are_registered(): void
    {
        $commands = array_keys(Artisan::all());

        $this->assertContains('backup:run', $commands);
        $this->assertContains('backup:clean', $commands);
        $this->assertContains('backup:monitor', $commands);
        $this->assertContains('backup:list', $commands);
    }

    public function test_backup_configuration_is_present(): void
    {
        $config = include config_path('backup.php');

        $this->assertNotNull($config['backup']['name'] ?? null);
        $this->assertNotEmpty($config['backup']['source']['files']['include'] ?? null);
    }

    public function test_backup_destination_disk_is_configured(): void
    {
        $config = include config_path('backup.php');

        $disks = $config['backup']['destination']['disks'] ?? null;

        $this->assertIsArray($disks);
        $this->assertNotEmpty($disks);
        $this->assertArrayHasKey($disks[0], config('filesystems.disks'));
    }
}
