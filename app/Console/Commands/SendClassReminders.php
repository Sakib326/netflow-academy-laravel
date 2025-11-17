<?php

namespace App\Console\Commands;

use App\Models\ClassRoutine;
use App\Models\Enrollment;
use App\Mail\ClassReminderMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class SendClassReminders extends Command
{
    protected $signature = 'class:send-reminders';
    protected $description = 'Send class reminder emails 30 minutes before class starts';

    public function handle()
    {
        $now = Carbon::now();
        $targetTime = $now->copy()->addMinutes(30);
        $currentDay = $now->format('l'); // Monday, Tuesday, etc.

        $this->info("Checking for classes at: {$targetTime->format('H:i')} on {$currentDay}");

        // Get all class routines
        $classRoutines = ClassRoutine::with(['course', 'batch'])->get();

        $emailsSent = 0;

        foreach ($classRoutines as $routine) {
            // Check if today has a class and if it's 30 minutes before start time
            foreach ($routine->days as $daySchedule) {
                if ($daySchedule['day'] === $currentDay) {

                    $classStartTime = Carbon::createFromFormat('H:i:s', $daySchedule['start_time']);
                    $classStartTime->setDate($now->year, $now->month, $now->day);

                    // Check if current time is 30 minutes before class (within 1 minute tolerance)
                    $timeDiff = abs($targetTime->diffInMinutes($classStartTime));

                    if ($timeDiff <= 1) { // 1 minute tolerance
                        // Check if today is not an off date
                        if (!$this->isOffToday($routine, $now)) {
                            $sent = $this->sendRemindersForClass($routine, $daySchedule);
                            $emailsSent += $sent;
                        } else {
                            $this->info("Skipping class for {$routine->course->title} - Off date today");
                        }
                    }
                }
            }
        }

        $this->info("Class reminder emails sent: {$emailsSent}");
        return 0;
    }

    private function isOffToday(ClassRoutine $routine, Carbon $today): bool
    {
        $todayStr = $today->format('Y-m-d');

        if ($routine->off_dates) {
            foreach ($routine->off_dates as $offDate) {
                if ($offDate['date'] === $todayStr) {
                    return true;
                }
            }
        }

        return false;
    }

    private function sendRemindersForClass(ClassRoutine $routine, array $daySchedule): int
    {
        // Get all enrolled students for this batch
        $enrollments = Enrollment::where('batch_id', $routine->batch_id)
            ->where('status', 'active')
            ->with('user')
            ->get();

        $sent = 0;

        foreach ($enrollments as $enrollment) {
            try {
                Mail::to($enrollment->user->email)->send(
                    new ClassReminderMail($routine, $enrollment->user, $daySchedule)
                );

                $this->info("✓ Reminder sent to: {$enrollment->user->email}");
                $sent++;
            } catch (\Exception $e) {
                $this->error("✗ Failed to send reminder to {$enrollment->user->email}: " . $e->getMessage());
            }
        }

        return $sent;
    }
}
