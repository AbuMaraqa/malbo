<?php

use Illuminate\Support\Facades\Schedule;

// The database cache keeps expired rows until something deletes them.
Schedule::command('cache:prune-expired')->daily();

// Queued jobs (back-in-stock alerts...) on the database queue, without a worker to keep alive: each
// minute a worker drains what is waiting and stops. One at a time.
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping(5);
