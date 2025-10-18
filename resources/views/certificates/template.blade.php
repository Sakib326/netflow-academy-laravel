<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pinyon+Script&display=swap" rel="stylesheet">

    <style>
        body {
            margin: 0;
            padding: 0;
        }

        .certificate-container {
            width: 100%;
            text-align: center;
        }

        .certificate-inner {
            display: inline-block;
            width: 800px;
            text-align: center;
            margin: auto;
            position: relative;
        }

        .certificate-inner img {
            width: 800px;
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
        }

        p.body-text {
            position: absolute;
            top: 58%;
            left: 50%;
            transform: translate(-50%, -70%);
            color: #252525;
            font-weight: 400;
            width: 600px;
            font-family: 'Lato', sans-serif;
            font-size: 18px;
            line-height: 1.4;
        }
    </style>
</head>

<body>
    <div class="certificate-container">
        <div class="certificate-inner">
            <img src="./certificate-image.png" alt="certificate background">

            <!-- Name -->
            <h1 class="name">{{ $userName }}</h1>

            <!-- Body / description text -->
            <!-- Body / description text -->
            <p class="body-text">
                This certificate is awarded to {{ $userName }} for successfully finishing NetFlow Academy's
                "{{ $courseName }}" course, gaining essential skills for professional development.
                Issued on {{ $issueDate }}. Certificate Code: {{ $certificateCode }}.
            </p>
        </div>
    </div>
</body>

</html>
