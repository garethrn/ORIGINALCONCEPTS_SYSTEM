<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('quotes:expire')->dailyAt('00:30')->timezone('Africa/Johannesburg');
Schedule::command('billing:recurring')->dailyAt('06:00')->timezone('Africa/Johannesburg');
Schedule::command('support:overdue-reminders')->dailyAt('07:30')->timezone('Africa/Johannesburg');
