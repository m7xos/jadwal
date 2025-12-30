<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use App\Console\Commands\KirimPengingatTindakLanjut;
use App\Console\Commands\RemindTindakLanjutCommand;

return Application::configure(basePath: dirname(__DIR__))
    ->withCommands([
        KirimPengingatTindakLanjut::class,
        RemindTindakLanjutCommand::class,
    ])
    ->withSchedule(function (Schedule $schedule) {
        // Jalankan scheduler pengingat TL setiap menit agar window 5 jam dan pengingat akhir tidak terlewat.
        $schedule->command('surat:ingatkan-tl')->everyMinute();
        $schedule->command('kegiatan:remind-tindak-lanjut')->everyMinute();
    })
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        channels: __DIR__.'/../routes/channels.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
