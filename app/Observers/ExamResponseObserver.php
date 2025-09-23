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
        // Check if score was updated, is >= 60, and a certificate doesn't already exist
        if ($examResponse->isDirty('score') && $examResponse->score >= 60 && !$examResponse->certificate) {
            $this->generateCertificate($examResponse);
        }
    }

    private function generateCertificate(ExamResponse $examResponse): void
    {
        $user = $examResponse->user;
        $course = $examResponse->exam->course;
        $certificateCode = 'CERT-' . $course->id . '-' . $user->id . '-' . Str::random(8);
        $fileName = Str::slug($user->name . '-' . $course->title . '-' . $certificateCode) . '.pdf';
        $filePath = 'public/certificates/' . $fileName;

        // Data to pass to the certificate view
        $data = [
            'userName' => $user->name,
            'courseName' => $course->title,
            'issueDate' => now()->format('F j, Y'),
            'certificateCode' => $certificateCode,
        ];

        // Generate PDF from a Blade view
        $pdf = Pdf::loadView('certificates.template', $data)->setPaper('a4', 'landscape');

        // Save the PDF to storage
        Storage::put($filePath, $pdf->output());

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
