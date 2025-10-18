<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Certificate</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        .certificate {
            width: 1000px;
            height: 707px;
            position: relative;
            margin: 0;
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
            margin: 0;
            padding: 0;
        }

        .body-text {
            position: absolute;
            top: 410px;
            left: 150px;
            width: 700px;
            color: #1a365d;
            text-align: center;
            font-size: 16px;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }

        .date-code {
            position: absolute;
            top: 470px;
            left: 0;
            width: 1000px;
            color: #1a365d;
            text-align: center;
            font-size: 12px;
            margin: 0;
            padding: 0;
        }

        @page {
            margin: 0;
            size: 1000px 707px;
        }
    </style>
</head>

<body>
    <div class="certificate">
        <img src="data:image/png;base64,{{ base64_encode(file_get_contents(resource_path('views/certificates/certificate-image.png'))) }}"
            alt="Certificate">
        <h1 class="name">{{ $userName ?? 'Student Name' }}</h1>
        <p class="body-text">for successfully completing NetFlow Academy's
            <strong>"{{ $courseName ?? 'Course Name' }}"</strong> course and gaining essential skills for professional
            development.</p>
        <p class="date-code">Issued on {{ $issueDate ?? date('F j, Y') }} | Certificate Code:
            {{ $certificateCode ?? 'CERT-000' }}</p>
    </div>
</body>

</html>
