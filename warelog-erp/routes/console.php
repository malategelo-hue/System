<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use App\Models\SystemSetting;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('database:backup', function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        throw new RuntimeException('Automated database backups currently support SQLite only.');
    }

    $databasePath = DB::connection()->getDatabaseName();
    if (! is_file($databasePath)) {
        throw new RuntimeException('The SQLite database file was not found.');
    }

    $backupDirectory = storage_path('app/private/backups');
    if (! is_dir($backupDirectory) && ! mkdir($backupDirectory, 0755, true) && ! is_dir($backupDirectory)) {
        throw new RuntimeException('The database backup directory could not be created.');
    }

    $backupPath = $backupDirectory.DIRECTORY_SEPARATOR.'warelog-'.now()->format('Ymd-His-u').'.sqlite';
    $quotedPath = DB::connection()->getPdo()->quote($backupPath);
    if ($quotedPath === false) {
        throw new RuntimeException('The database backup path could not be encoded.');
    }
    DB::statement("VACUUM INTO {$quotedPath}");

    $this->info("Database backup created: {$backupPath}");
})->purpose('Create a backup of the SQLite database');

Schedule::command('database:backup')
    ->dailyAt('02:00')
    ->when(fn () => SystemSetting::values()['backup_daily'] === '1');
