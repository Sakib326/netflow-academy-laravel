<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Certificate</title>

    <style>
        /* Import fonts at the top - better for PDF */
        @import url("https://fonts.googleapis.com/css2?family=Pinyon+Script&family=Lato:wght@300;400;700&display=swap");

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            font-family: "Lato", Arial, sans-serif;
            background: white;
        }

        .certificate-container {
            width: 100%;
            height: 100vh;
            position: relative;
            text-align: center;
            page-break-inside: avoid;
        }

        .certificate-inner {
            width: 1000px;
            height: 707px;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            page-break-inside: avoid;
        }

        .certificate-inner img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            position: absolute;
            top: 0;
            left: 0;
            z-index: 1;
        }

        h1.name {
            position: absolute;
            top: 52%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 48px;
            font-weight: 400;
            letter-spacing: 1.5px;
            color: #1a365d !important;
            white-space: nowrap;
            text-transform: none;
            font-family: "Pinyon Script", serif;
            margin: 0;
            padding: 0;
            line-height: 1;
            z-index: 10;
            text-align: center;
        }

        p.body-text {
            position: absolute;
            top: 62%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #1a365d !important;
            font-weight: 400;
            width: 700px;
            max-width: 700px;
            font-family: "Lato", Arial, sans-serif;
            font-size: 16px;
            line-height: 1.5;
            text-align: center;
            margin: 0;
            padding: 0;
            z-index: 10;
        }

        p.date-code {
            position: absolute;
            top: 68%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #1a365d !important;
            font-weight: 300;
            font-family: "Lato", Arial, sans-serif;
            font-size: 12px;
            text-align: center;
            margin: 0;
            padding: 0;
            z-index: 10;
            white-space: nowrap;
        }

        /* PDF-specific optimizations */
        @page {
            margin: 0;
            padding: 0;
            size: A4 landscape;
        }

        @media print {

            html,
            body {
                width: 297mm;
                height: 210mm;
                margin: 0;
                padding: 0;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                color-adjust: exact;
            }

            .certificate-container {
                width: 100%;
                height: 100%;
                page-break-inside: avoid;
                page-break-after: avoid;
                page-break-before: avoid;
            }

            .certificate-inner {
                page-break-inside: avoid;
            }

            /* Force font rendering */
            h1.name {
                font-family: serif !important;
                color: #1a365d !important;
            }

            p.body-text,
            p.date-code {
                font-family: sans-serif !important;
                color: #1a365d !important;
            }
        }

        /* DomPDF specific fixes */
        .dompdf_force_color {
            color: #1a365d !important;
        }
    </style>
</head>

<body>
    <div class="certificate-container">
        <div class="certificate-inner">
            <!-- Background image using base64 for PDF compatibility -->
            <img src="data:image/png;base64,{{ base64_encode(file_get_contents(resource_path('views/certificates/certificate-image.png'))) }}"
                alt="certificate background" />

            <!-- Name in Pinyon Script font -->
            <h1 class="name dompdf_force_color">
                {{ $userName ?? 'Student Name' }}
            </h1>

            <!-- Course completion text -->
            <p class="body-text dompdf_force_color">
                for successfully completing NetFlow Academy's
                <strong>"{{ $courseName ?? 'Course Name' }}"</strong> course
                and gaining essential skills for professional development.
            </p>

            <!-- Date and certificate code -->
            <p class="date-code dompdf_force_color">
                Issued on {{ $issueDate ?? date('F j, Y') }} | Certificate
                Code: {{ $certificateCode ?? 'CERT-000' }}
            </p>
        </div>
    </div>
</body>

</html>
