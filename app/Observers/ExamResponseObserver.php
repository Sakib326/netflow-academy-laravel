<?php

namespace App\Observers;

use App\Models\ExamResponse;
use App\Models\Certificate;
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
            $certificateCode = 'CERT-' . strtoupper(Str::random(8));
            $fileName = Str::slug($user->name . '-' . $course->title) . '-' . time() . '.pdf';

            // Create certificates directory if it doesn't exist
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

            // Render the Blade view to HTML
            $html = view('certificates.template', $data)->render();

            // Initialize mPDF with custom font support
            $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
            $fontDirs = $defaultConfig['fontDir'];

            $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
            $fontData = $defaultFontConfig['fontdata'];

            // ✅ FIXED: Set custom page size to match 2000x1414 px
            // Convert pixels to mm: 2000px ÷ 3.7795 = 529.17mm, 1414px ÷ 3.7795 = 374.02mm
            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => [529.17, 374.02], // Custom size in mm (2000x1414 px)
                'orientation' => 'L', // Landscape
                'margin_top' => 0,
                'margin_right' => 0,
                'margin_bottom' => 0,
                'margin_left' => 0,
                'dpi' => 72, // ✅ Set DPI for accurate rendering
                'fontDir' => array_merge($fontDirs, [
                    storage_path('fonts'),
                ]),
                'fontdata' => $fontData + [
                    'pinyonscript' => [
                        'R' => 'PinyonScript-Regular.ttf',
                    ]
                ],
                'default_font' => 'dejavusans',
            ]);

            // Disable automatic page breaks
            $mpdf->SetAutoPageBreak(false);

            // ✅ Set page format explicitly
            $mpdf->AddPage('L', [529.17, 374.02]);

            // Write HTML to PDF
            $mpdf->WriteHTML($html);

            // Output to file
            $mpdf->Output($fullPath, \Mpdf\Output\Destination::FILE);

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
                'file_path' => $filePath,
                'user' => $user->name,
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to generate certificate', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'user_id' => $examResponse->user_id ?? null,
            ]);

            throw $e;
        }
    }
}
