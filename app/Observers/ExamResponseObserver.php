<?php

namespace App\Observers;

use App\Models\ExamResponse;
use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;

class ExamResponseObserver
{
    /**
     * Handle the ExamResponse "updated" event.
     */
    public function updated(ExamResponse $examResponse): void
    {
        // Check if status changed to 'graded', score is >= 40%, and certificate doesn't exist
        if ($examResponse->status === 'graded' &&
            $examResponse->percentage >= 40 &&
            !$examResponse->certificate) {
            $this->generateCertificate($examResponse);
        }
    }

    private function generateCertificate(ExamResponse $examResponse): void
    {
        $user = $examResponse->user;
        $course = $examResponse->exam->course;
        $certificateCode = 'CERT-' . $course->id . '-' . $user->id . '-' . Str::random(8);
        $fileName = Str::slug($user->name . '-' . $course->title . '-' . $certificateCode) . '.pdf';

        // Create directory if it doesn't exist
        $publicPath = public_path('certificates');
        if (!file_exists($publicPath)) {
            mkdir($publicPath, 0755, true);
        }

        $filePath = 'certificates/' . $fileName;
        $fullPath = public_path($filePath);

        // Data to pass to the certificate view
        $data = [
            'userName' => $user->name,
            'courseName' => $course->title,
            'issueDate' => now()->format('F j, Y'),
            'certificateCode' => $certificateCode,
            'score' => $examResponse->percentage,
        ];

        // Generate PDF from a Blade view
        $pdf = Pdf::loadView('certificates.template', $data)->setPaper('a4', 'landscape');

        // Save directly to public folder
        file_put_contents($fullPath, $pdf->output());

        // Create the certificate record in the database
        Certificate::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'exam_response_id' => $examResponse->id,
            'certificate_code' => $certificateCode,
            'path' => $filePath,
            'issue_date' => now(),
        ]);
    }
}
