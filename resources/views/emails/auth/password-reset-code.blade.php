<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Your Password Reset Code</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #333;
        }

        .container {
            padding: 20px;
            max-width: 600px;
            margin: auto;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .code {
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 5px;
            background-color: #f2f2f2;
            padding: 10px 20px;
            border-radius: 5px;
            display: inline-block;
            margin: 20px 0;
        }

        .footer {
            margin-top: 20px;
            font-size: 12px;
            color: #777;
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>Hello, {{ $user->name }}!</h2>
        <p>We received a request to reset your password. Use the code below to complete the process.</p>

        <div class="code">{{ $resetCode }}</div>

        <p>This code will expire in 10 minutes.</p>
        <p class="footer">If you did not request a password reset, please ignore this email.</p>
    </div>
</body>

</html>
