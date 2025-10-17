<!DOCTYPE html>
<html>

<head>
    <title>Class Reminder</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: #007bff;
            color: white;
            padding: 20px;
            text-align: center;
        }

        .content {
            padding: 20px;
            background: #f9f9f9;
        }

        .details {
            background: white;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }

        .footer {
            text-align: center;
            padding: 20px;
            color: #666;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h2>🔔 Class Reminder</h2>
        </div>

        <div class="content">
            <p>Dear <strong>{{ $studentName }}</strong>,</p>

            <p>This is a reminder that your class is starting in <strong>30 minutes</strong>:</p>

            <div class="details">
                <ul style="list-style: none; padding: 0;">
                    <li><strong>📚 Course:</strong> {{ $courseName }}</li>
                    <li><strong>👥 Batch:</strong> {{ $batchName }}</li>
                    <li><strong>📅 Day:</strong> {{ $day }}</li>
                    <li><strong>⏰ Time:</strong> {{ $classTime }} - {{ $endTime }}</li>
                </ul>
            </div>

            <p>Please join the class on time. Don't forget to bring your materials!</p>
        </div>

        <div class="footer">
            <p>Best regards,<br><strong>{{ config('app.name') }} Team</strong></p>
        </div>
    </div>
</body>

</html>
