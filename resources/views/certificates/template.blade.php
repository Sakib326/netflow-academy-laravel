<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'dejavusans', sans-serif;
        }

        .certificate-table {
            width: 100%;
            border-collapse: collapse;
        }

        .background-row {
            position: relative;
        }

        .background-image {
            width: 2000px;
            /* ✅ FIXED: Set exact dimensions */
            height: 1414px;
            /* ✅ FIXED: Set exact dimensions */
            display: block;
        }

        .content-table {
            width: 100%;
            text-align: center;
            margin-top: -710px;
        }

        .spacer {
            height: 300px;
        }

        .user-name {
            font-size: 60px;
            color: #0a2a43;
            font-family: 'dmserif', sans-serif;
            /* padding: 20px 0; */
        }

        .course-name {
            font-size: 18px;
            letter-spacing: 1px;
            color: #000000;

            padding: 0 100px;
        }

        .issue-date {
            font-size: 18px;
            color: #000000;
            /* padding: 15px 0; */
        }

        .certificate-code {
            font-size: 18px;
            color: #000000;
            /* padding: 10px 0; */
        }
    </style>
</head>

<body>
    <table class="certificate-table" cellpadding="0" cellspacing="0">
        <tr class="background-row">
            <td>
                <img class="background-image"
                    src="data:image/png;base64,{{ base64_encode(file_get_contents(resource_path('views/certificates/certificate-image.png'))) }}"
                    alt="Certificate Background">
            </td>
        </tr>
    </table>

    <table class="content-table" cellpadding="0" cellspacing="0">
        <tr>
            <td class="spacer"></td>
        </tr>
        <tr>
            <td class="user-name">{{ $userName }}</td>
        </tr>
        <tr>
            <td class="course-name">
                Successfully finished NetFlow Academy’s
                <strong>{{ $courseName }}</strong> course,
                gaining essential skills for professional development.
            </td>
        </tr>
        <tr>
            <td class="issue-date">Date: {{ $issueDate }}</td>
        </tr>
        <tr>
            <td class="certificate-code">ID: {{ $certificateCode }}</td>
        </tr>
    </table>
</body>

</html>
