<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Certificate of Completion</title>
    <style>
        body {
            font-family: 'Helvetica', sans-serif;
            text-align: center;
            border: 20px solid #787878;
            padding: 50px;
            height: 550px;
        }

        h1 {
            font-size: 50px;
            color: #333;
        }

        h2 {
            font-size: 30px;
        }

        p {
            font-size: 20px;
        }

        .date,
        .code {
            font-size: 16px;
            color: #555;
            margin-top: 40px;
        }
    </style>
</head>

<body>
    <p>This is to certify that</p>
    <h1>{{ $userName }}</h1>
    <p>has successfully completed the course</p>
    <h2>{{ $courseName }}</h2>
    <p class="date">Issued on: {{ $issueDate }}</p>
    <p class="code">Certificate Code: {{ $certificateCode }}</p>
</body>

</html>
