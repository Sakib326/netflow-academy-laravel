<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate</title>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Pinyon+Script&family=Lato:wght@300;400;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Lato', Arial, sans-serif;
            background: white;
        }

        .certificate-container {
            width: 100%;
            text-align: center;
            page-break-inside: avoid;
        }

        .certificate-inner {
            display: inline-block;
            width: 800px;
            text-align: center;
            margin: 0 auto;
            position: relative;
            page-break-inside: avoid;
        }

        .certificate-inner img {
            width: 800px;
            height: auto;
            display: block;
        }

        h1.name {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -110%);
            font-size: 55px;
            font-weight: 500;
            letter-spacing: 2px;
            color: #252525;
            white-space: nowrap;
            text-transform: capitalize !important;
            font-family: 'Pinyon Script', cursive;
            margin: 0;
            padding: 0;
            line-height: 1;
        }

        p.body-text {
            position: absolute;
            top: 58%;
            left: 50%;
            transform: translate(-50%, -70%);
            color: #252525;
            font-weight: 400;
            width: 600px;
            font-family: 'Lato', Arial, sans-serif;
            font-size: 18px;
            line-height: 1.4;
            text-align: center;
            margin: 0;
            padding: 0;
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
    </style>
</head>

<body>
    <div class="certificate-container">
        <div class="certificate-inner">
            <!-- Background image embedded as base64 -->
            <img src="data:image/png;base64,{{ base64_encode(file_get_contents(resource_path('views/certificates/certificate-image.png'))) }}"
                alt="certificate background">

            <!-- Name in Pinyon Script font -->
            <h1 class="name">{{ $userName ?? 'Student Name' }}</h1>

            <!-- Body text in Lato font -->
            <p class="body-text">
                This certificate is awarded to {{ $userName ?? 'Student Name' }} for successfully finishing NetFlow
                Academy's "{{ $courseName ?? 'Course Name' }}" course, gaining essential skills for professional
                development.
                Issued on {{ $issueDate ?? date('F j, Y') }}. Certificate Code: {{ $certificateCode ?? 'CERT-000' }}.
            </p>
        </div>
    </div>
</body>

</html>
