<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Certificate</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            width: 1000px;
            height: 707px;
            font-family: DejaVuSans, sans-serif;
        }

        .certificate {
            width: 1000px;
            height: 707px;
            position: relative;
            background-size: cover;
        }

        .certificate img {
            width: 1000px;
            height: 707px;
            display: block;
        }

        .name {
            position: absolute;
            top: 340px;
            left: 0;
            width: 1000px;
            font-size: 48px;
            color: #1a365d;
            text-align: center;
            font-family: pinyonscript;
            letter-spacing: 2px;
            line-height: 1;
        }

        .body-text {
            position: absolute;
            top: 410px;
            left: 150px;
            width: 700px;
            color: #1a365d;
            text-align: center;
            font-size: 16px;
            line-height: 24px;
            font-family: DejaVuSans, sans-serif;
        }

        .date-code {
            position: absolute;
            top: 470px;
            left: 0;
            width: 1000px;
            color: #1a365d;
            text-align: center;
            font-size: 12px;
            font-family: DejaVuSans, sans-serif;
        }
    </style>
</head>

<body>
    <div class="certificate">
        <img src="data:image/png;base64,{{ base64_encode(file_get_contents(resource_path('views/certificates/certificate-image.png'))) }}"
            alt="Certificate" />

        <div class="name">{{ $userName ?? 'Student Name' }}</div>

        <div class="body-text">
            for successfully completing NetFlow Academy's
            <strong>"{{ $courseName ?? 'Course Name' }}"</strong> course
            and gaining essential skills for professional development.
        </div>

        <div class="date-code">
            Issued on {{ $issueDate ?? date('F j, Y') }} | Certificate Code: {{ $certificateCode ?? 'CERT-000' }}
        </div>
    </div>
</body>

</html>
