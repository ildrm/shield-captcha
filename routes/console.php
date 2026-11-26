<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('shield:heartbeat')->everyMinute();
Schedule::command('shield:prune --batch=250')->everyFiveMinutes()->withoutOverlapping(10);
Schedule::command('shield:purge-key-displays')->everyFiveMinutes()->withoutOverlapping(10);
Schedule::command('shield:sms:prune')->hourly()->withoutOverlapping(10);
Schedule::command('shield:prune-team-invitations')->hourly()->withoutOverlapping(10);
Schedule::command('shield:prune-protection --batch=250')->everyFiveMinutes()->withoutOverlapping(10);
