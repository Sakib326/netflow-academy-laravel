<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Enrollment;
use App\Models\Batch;
use App\Models\User; // <-- ADD THIS
use App\Models\Course;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;

class OrderObserver
{
    /**
     * Handle the Order "updated" event.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    public function updated(Order $order)
    {
        // Check if the 'status' was changed to 'paid'
        if ($order->isDirty('status') && $order->status === 'paid') {

            DB::beginTransaction();
            try {
                // 1. Create/update payment record
                Payment::updateOrCreate(
                    ['order_id' => $order->id],
                    [
                        'user_id' => $order->user_id,
                        'course_id' => $order->course_id,
                        'amount' => $order->amount,
                        'status' => 'completed',
                        'payment_method' => 'manual_approval'
                    ]
                );

                // 2. Auto-enroll user
                $this->autoEnrollUser($order);

                DB::commit();

                // Send a success notification to the admin panel
                if (app()->runningInConsole() || request()->is('livewire/*')) {
                    Notification::make()
                        ->title('User Enrolled Successfully')
                        ->body("Order #{$order->order_number} was paid. The user has been enrolled.")
                        ->success()
                        ->send();
                }

            } catch (\Exception $e) {
                DB::rollBack();

                // Send an error notification
                if (app()->runningInConsole() || request()->is('livewire/*')) {
                    Notification::make()
                        ->title('Enrollment Failed')
                        ->body('An error occurred: ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            }
        }
    }

    /**
     * Enroll the user into the course or bundle.
     */
    private function autoEnrollUser(Order $order): void
    {
        $course = $order->course;
        $user = $order->user;

        if ($course->isBundle()) {
            foreach ($course->getBundledCourses() as $bundleCourse) {
                $this->createEnrollment($order, $user, $bundleCourse);
            }
        } else {
            $this->createEnrollment($order, $user, $course);
        }
    }

    /**
     * Create an enrollment record if it doesn't exist.
     */
    private function createEnrollment(Order $order, User $user, Course $course): void
    {
        $batch = $this->getOrCreateBatch($course);

        // Create enrollment only if one doesn't already exist for this batch
        Enrollment::firstOrCreate(
            ['user_id' => $user->id, 'batch_id' => $batch->id],
            ['order_id' => $order->id, 'status' => 'active']
        );
    }

    /**
     * Find an active batch or create a new one.
     */
    private function getOrCreateBatch(Course $course): Batch
    {
        return Batch::firstOrCreate(
            ['course_id' => $course->id, 'is_active' => true],
            [
                'name' => $course->title . ' - Auto Batch',
                'start_date' => now(),
            ]
        );
    }
}
