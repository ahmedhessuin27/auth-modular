<?php

use App\Modules\Shared\Infrastructure\Messaging\Inbox\ProcessedMessage;
use App\Modules\Shared\Infrastructure\Messaging\Outbox\OutboxMessage;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('model:prune', [
    '--model' => [OutboxMessage::class, ProcessedMessage::class],
])->daily();
