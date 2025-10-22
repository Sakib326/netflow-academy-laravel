<!DOCTYPE html>
<html lang="en">

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
            width: 2000px;
            height: 1414px;
            margin: 0;
            padding: 0;
            position: relative;
            font-family: "DejaVu Sans", sans-serif;
            overflow: hidden;
        }

        /* Background Image (exact fit) */
        .background {
            position: absolute;
            top: 0;
            left: 0;
            width: 2000px;
            height: 1414px;
            object-fit: cover;
            z-index: 0;
        }

        /* Overlay text container */
        .content {
            position: absolute;
            top: 0;
            left: 0;
            width: 2000px;
            height: 1414px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            z-index: 1;
        }

        /* Student name (centered prominently) */
        .name {
            font-size: 140px;
            color: #252525;
            font-family: "Pinyon Script", cursive;
            letter-spacing: 6px;
            margin-top: -150px;
            /* Adjust vertically as per design */
        }

        /* Certificate description text */
        .body-text {
            font-size: 45px;
            color: #252525;
            line-height: 68px;
            width: 1600px;
            margin-top: 40px;
            font-family: "DejaVu Sans", sans-serif;
        }
    </style>
</head>

<body>
    <img class="background"
        src="data:image/png;base64,{{ base64_encode(file_get_contents(resource_path('views/certificates/certificate-image.png'))) }}"
        alt="Certificate Background">

    <div class="content">
        <div class="name">{{ $userName ?? 'Student Name' }}</div>
        <div class="body-text">
            This certificate is proudly presented to <strong>{{ $userName ?? 'Student Name' }}</strong> for successfully
            completing<br>
            the course <strong>"{{ $courseName ?? 'Course Name' }}"</strong>.<br><br>
            Certificate Code: <strong>{{ $certificateCode ?? 'CERT-000' }}</strong>
        </div>
    </div>
</body>

</html>
