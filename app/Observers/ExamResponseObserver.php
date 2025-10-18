<?php

namespace App\Observers;

use App\Models\ExamResponse;
use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mpdf\Mpdf;

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
        try {
            $user = $examResponse->user;
            $course = $examResponse->exam->course;
            $certificateCode = 'CERT-' . $course->id . '-' . $user->id . '-' . Str::random(8);
            $fileName = Str::slug($user->name . '-' . $course->title . '-' . $certificateCode) . '.pdf';

            // Create directory if it doesn't exist
            $publicPath = public_path('certificates');
            if (!file_exists($publicPath)) {
                mkdir($publicPath, 0755, true);
            }

            // Create temp directory if it doesn't exist
            $tempPath = storage_path('app/temp');
            if (!file_exists($tempPath)) {
                mkdir($tempPath, 0755, true);
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

            // Render the Blade view to HTML
            $html = view('certificates.template', $data)->render();

            // Create mPDF instance with Pinyon Script font
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => [264.583, 187.145], // 1000x707 px converted to mm
                'orientation' => 'L', // Landscape
                'margin_top' => 0,
                'margin_right' => 0,
                'margin_bottom' => 0,
                'margin_left' => 0,
                'margin_header' => 0,
                'margin_footer' => 0,
                'default_font_size' => 12,
                'default_font' => 'dejavusans',
                'tempDir' => storage_path('app/temp'),
                // Add custom font directory
                'fontDir' => [
                    storage_path('fonts'),
                ],
                // Define custom fonts
                'fontdata' => [
                    'pinyonscript' => [
                        'R' => 'PinyonScript-Regular.ttf',
                    ],
                ],
            ]);

            // Write HTML to PDF
            $mpdf->WriteHTML($html);

            // Save PDF to public folder
            $mpdf->Output($fullPath, 'F');

            // Create the certificate record in the database
            Certificate::create([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'exam_response_id' => $examResponse->id,
                'certificate_code' => $certificateCode,
                'path' => $filePath,
                'issue_date' => now(),
            ]);

            \Log::info('Certificate generated successfully', [
                'certificate_code' => $certificateCode,
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to generate certificate', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $examResponse->user_id,
                'exam_response_id' => $examResponse->id,
            ]);
        }
    }
}
