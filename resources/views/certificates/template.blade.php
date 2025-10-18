<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate</title>

    <!-- Preload fonts for better compatibility -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pinyon+Script&family=Lato:wght@300;400;700&display=swap"
        rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Lato', 'DejaVu Sans', Arial, sans-serif;
            background: white;
        }

        .certificate-container {
            width: 100%;
            text-align: center;
            page-break-inside: avoid;
        }

        .certificate-inner {
            display: inline-block;
            width: 1000px;
            /* Adjusted for 2000x1414 ratio */
            text-align: center;
            margin: 0 auto;
            position: relative;
            page-break-inside: avoid;
        }

        .certificate-inner img {
            width: 1000px;
            height: 707px;
            /* Maintains 2000:1414 ratio (1000:707) */
            display: block;
        }

        h1.name {
            position: absolute;
            top: 52%;
            /* Adjusted for your certificate layout */
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 48px;
            /* Proportional to new size */
            font-weight: 400;
            letter-spacing: 1.5px;
            color: #1a365d;
            /* Darker blue to match your certificate */
            white-space: nowrap;
            text-transform: none;
            font-family: 'Pinyon Script', 'DejaVu Serif', cursive;
            margin: 0;
            padding: 0;
            line-height: 1;
            z-index: 10;
        }

        p.body-text {
            position: absolute;
            top: 62%;
            /* Adjusted for your certificate layout */
            left: 50%;
            transform: translate(-50%, -50%);
            color: #1a365d;
            font-weight: 400;
            width: 700px;
            /* Wider for the larger certificate */
            font-family: 'Lato', 'DejaVu Sans', Arial, sans-serif;
            font-size: 16px;
            /* Proportional to new size */
            line-height: 1.5;
            text-align: center;
            margin: 0;
            padding: 0;
            z-index: 10;
        }

        p.date-code {
            position: absolute;
            top: 75%;
            /* Position for date and certificate code */
            left: 50%;
            transform: translate(-50%, -50%);
            color: #1a365d;
            font-weight: 300;
            font-family: 'Lato', 'DejaVu Sans', Arial, sans-serif;
            font-size: 12px;
            text-align: center;
            margin: 0;
            padding: 0;
            z-index: 10;
        }

        /* PDF-specific styles */
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .certificate-container {
                page-break-inside: avoid;
            }
        }

        /* DomPDF specific styles */
        @page {
            margin: 0;
            size: A4 landscape;
        }

        /* Fallback fonts for PDF generation */
        .certificate-inner * {
            font-synthesis: none;
        }
    </style>
</head>

<body>
    <div class="certificate-container">
        <div class="certificate-inner">
            <!-- Background image embedded as base64 with correct ratio -->
            <img src="data:image/png;base64,{{ base64_encode(file_get_contents(resource_path('views/certificates/certificate-image.png'))) }}"
                alt="certificate background">

            <h1 class="name">{{ $userName ?? 'Student Name' }}</h1>

            <!-- Course completion text -->
            <p class="body-text">
                for successfully completing NetFlow Academy's
                <strong>"{{ $courseName ?? 'Course Name' }}"</strong> course
                and gaining essential skills for professional development.
            </p>

            <!-- Date and certificate code -->
            <p class="date-code">
                Issued on {{ $issueDate ?? date('F j, Y') }} | Certificate Code: {{ $certificateCode ?? 'CERT-000' }}
            </p>
        </div>
    </div>
</body>

</html>
