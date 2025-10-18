<!DOCTYPE html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Certificate</title>
    <style>
        /* Using @import is fine, but ensure network access from your server */
        @import url("https://fonts.googleapis.com/css2?family=Pinyon+Script&family=Lato:wght@300;400;700&display=swap");

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Lato", "DejaVu Sans", Arial, sans-serif;
            background-color: #fff;
        }

        .certificate-inner {
            width: 1000px;
            height: 707px;
            position: relative;
            margin: 0;
            page-break-inside: avoid;
        }

        .background-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
        }

        .content {
            position: absolute;
            width: 100%;
            height: 100%;
            z-index: 10;
            text-align: center;
        }

        h1.name {
            /* Removed transform, using margin for centering */
            width: 100%;
            position: absolute;
            top: 340px;
            /* Approx 52% - (font-size/2) */
            left: 0;
            font-size: 48px;
            font-weight: 400;
            letter-spacing: 1.5px;
            color: #1a365d;
            white-space: nowrap;
            font-family: "Pinyon Script", "DejaVu Serif", serif;
        }

        p.body-text {
            /* Removed transform, using margin for centering */
            width: 700px;
            position: absolute;
            top: 410px;
            /* Approx 62% */
            left: 150px;
            /* (1000px - 700px) / 2 */
            color: #1a365d;
            font-weight: 400;
            font-size: 16px;
            line-height: 1.5;
            text-align: center;
        }

        p.date-code {
            /* Removed transform, using margin for centering */
            width: 100%;
            position: absolute;
            top: 470px;
            /* Approx 68% */
            left: 0;
            color: #1a365d;
            font-weight: 300;
            font-size: 12px;
            white-space: nowrap;
            text-align: center;
        }

        @page {
            margin: 0;
            size: 1000px 707px;
        }
    </style>
</head>

<body>
    <div class="certificate-inner">
        <img class="background-image"
            src="data:image/png;base64,{{ base64_encode(file_get_contents(resource_path('views/certificates/certificate-image.png'))) }}"
            alt="certificate background" />

        <div class="content">
            <h1 class="name">
                {{ $userName ?? 'Student Name' }}
            </h1>

            <p class="body-text">
                for successfully completing NetFlow Academy's
                <strong>"{{ $courseName ?? 'Course Name' }}"</strong> course
                and gaining essential skills for professional development.
            </p>

            <p class="date-code">
                Issued on {{ $issueDate ?? date('F j, Y') }} | Certificate
                Code: {{ $certificateCode ?? 'CERT-000' }}
            </p>
        </div>
    </div>
</body>

</html>
