<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pinyon+Script&family=Lato&display=swap" rel="stylesheet">

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
            height: auto;
            display: block;
        }


        h1.name {
            position: absolute;
            top: 330px;
            /* adjust according to your design */
            left: 0;
            width: 100%;
            text-align: center;
            font-size: 56px;
            font-family: 'Pinyon Script', cursive;
            color: #252525;
            font-weight: 500;
            letter-spacing: 2px;
            text-transform: capitalize;
        }

        p.body-text {
            position: absolute;
            top: 440px;
            /* adjust based on design */
            left: 10%;
            width: 80%;
            text-align: center;
            font-family: 'Lato', sans-serif;
            font-size: 18px;
            color: #252525;
            line-height: 1.5;
        }
    </style>
</head>

<body>
    <div class="certificate-container">
        <div class="certificate-inner">
            <img src="./certificate-image.png" alt="certificate background">

            <!-- Name -->
            <h1 class="name">Mahmudul Hasan</h1>

            <!-- Body / description text -->
            <p class="body-text">
                This certificate is given to Marceline Anderson for his achievement during the month of July 2024.
                Hopefully this certificate will be a great motivation.
            </p>
        </div>
    </div>
</body>

</html>
