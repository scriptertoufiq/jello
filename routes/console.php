<?php

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

Schedule::command('boards:send-due-date-reminders')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->description('Notify assignees about cards due in the next day');
