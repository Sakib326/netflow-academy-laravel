<?php

namespace App\Mail;

use App\Models\ClassRoutine;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ClassReminderMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public ClassRoutine $classRoutine,
        public User $student,
        public array $classDetails
    ) {
    }

    public function build()
    {
        return $this->subject('Class Reminder - ' . $this->classRoutine->course->title)
                    ->view('emails.class-reminder')
                    ->with([
                        'studentName' => $this->student->name,
                        'courseName' => $this->classRoutine->course->title,
                        'batchName' => $this->classRoutine->batch->name,
                        'classTime' => $this->classDetails['start_time'],
                        'endTime' => $this->classDetails['end_time'],
                        'day' => $this->classDetails['day'],
                    ]);
    }
}
