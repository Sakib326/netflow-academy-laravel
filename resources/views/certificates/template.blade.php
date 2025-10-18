<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Certificate</title>

    <style>
        /* Import fonts at the top - better for PDF */
        @import url("https://fonts.googleapis.com/css2?family=Pinyon+Script&family=Lato:wght@300;400;700&display=swap");

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: "Lato", "DejaVu Sans", Arial, sans-serif;
            background: white;
        }

        .certificate-inner {
            width: 1000px;
            height: 707px;
            position: relative;
            margin: 0 auto;
            /* Center the certificate on the page */
            page-break-inside: avoid;
        }

        .certificate-inner .background-image {
            width: 100%;
            height: 100%;
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
            color: #1a365d;
            white-space: nowrap;
            font-family: "Pinyon Script", "DejaVu Serif", serif;
            margin: 0;
            z-index: 10;
            text-align: center;
        }

        p.body-text {
            position: absolute;
            top: 62%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #1a365d;
            font-weight: 400;
            width: 700px;
            font-family: "Lato", "DejaVu Sans", Arial, sans-serif;
            font-size: 16px;
            line-height: 1.5;
            text-align: center;
            margin: 0;
            z-index: 10;
        }

        p.date-code {
            position: absolute;
            top: 68%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #1a365d;
            font-weight: 300;
            font-family: "Lato", "DejaVu Sans", Arial, sans-serif;
            font-size: 12px;
            text-align: center;
            margin: 0;
            z-index: 10;
            white-space: nowrap;
        }

        /* PDF-specific optimizations */
        @page {
            margin: 0;
            size: 1000px 707px;
            /* Set page size to match image */
        }
    </style>
</head>

<body>
    <div class="certificate-inner">
        <!-- Background image using base64 for PDF compatibility -->
        <img class="background-image"
            src="data:image/png;base64,{{ base64_encode(file_get_contents(resource_path('views/certificates/certificate-image.png'))) }}"
            alt="certificate background" />

        <!-- Name -->
        <h1 class="name">
            {{ $userName ?? 'Student Name' }}
        </h1>

        <!-- Course completion text -->
        <p class="body-text">
            for successfully completing NetFlow Academy's
            <strong>"{{ $courseName ?? 'Course Name' }}"</strong> course
            and gaining essential skills for professional development.
        </p>

        <!-- Date and certificate code -->
        <p class="date-code">
            Issued on {{ $issueDate ?? date('F j, Y') }} | Certificate
            Code: {{ $certificateCode ?? 'CERT-000' }}
        </p>
    </div>
</body>

</html>
