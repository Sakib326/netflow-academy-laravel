<?php

namespace App\Observers;

use App\Models\ExamResponse;
use App\Models\Certificate;
use Illuminate\Support\Str;
use Mpdf\Mpdf;

class ExamResponseObserver
{
    public function updated(ExamResponse $examResponse): void
    {
        if (
            $examResponse->status === 'graded'
            && $examResponse->percentage >= 40
            && !$examResponse->certificate
        ) {
            $this->generateCertificate($examResponse);
        }
    }

    private function generateCertificate(ExamResponse $examResponse): void
    {
        $user = $examResponse->user;
        $course = $examResponse->exam->course;

        $certificateCode = 'CERT-' . strtoupper(Str::random(8));
        $fileName = Str::slug($user->name . '-' . $course->title) . '-' . time() . '.pdf';

        $directory = public_path('certificates');
        if (! file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $filePath = 'certificates/' . $fileName;
        $fullPath = public_path($filePath);

        $backgroundImagePath = resource_path('views/certificates/certificate-image.png');

        $data = [
            'userName'        => $user->name,
            'courseName'      => $course->title,
            'issueDate'       => now()->format('F j, Y'),
            'certificateCode' => $certificateCode,
            'backgroundImage' => $backgroundImagePath,
        ];

        $html = view('certificates.template', $data)->render();

        // Configure mPDF
        $configVars = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs   = $configVars['fontDir'];

        $fontVars  = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData  = $fontVars['fontdata'];

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => [529.17, 374.02], // in mm or adjust to your size
            'orientation'   => 'L',
            'margin_top'    => 0,
            'margin_bottom' => 0,
            'margin_left'   => 0,
            'margin_right'  => 0,
            'dpi'           => 72,
            'fontDir'       => array_merge($fontDirs, [
                storage_path('fonts'),
            ]),
            'fontdata'      => $fontData + [
                'pinyonscript' => [
                    'R' => 'PinyonScript-Regular.ttf',
                ],
                 'dmserif' => [
                    'R' => 'DMSerifDisplay-Regular.ttf',
                    'I' => 'DMSerifDisplay-Italic.ttf', // Optional if you have italic
                ],
            ],
            'default_font'  => 'dejavusans',
        ]);

        $mpdf->SetAutoPageBreak(false);

        $mpdf->WriteHTML($html);
        $mpdf->Output($fullPath, \Mpdf\Output\Destination::FILE);

        Certificate::create([
            'user_id'          => $user->id,
            'course_id'        => $course->id,
            'exam_response_id' => $examResponse->id,
            'certificate_code' => $certificateCode,
            'path'             => $filePath,
            'issue_date'       => now(),
        ]);
    }
}
