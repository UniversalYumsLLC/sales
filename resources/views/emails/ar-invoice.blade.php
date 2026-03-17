<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Universal Yums</title>
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .email-header {
            background-color: #333;
            color: #fff;
            padding: 20px;
            text-align: center;
        }
        .email-header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: normal;
        }
        .email-body {
            padding: 30px;
        }
        .email-body p {
            margin: 0 0 15px 0;
        }
        .email-body a {
            color: #0066cc;
        }
        .email-footer {
            background-color: #f9f9f9;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #eee;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1>Universal Yums</h1>
        </div>
        <div class="email-body">
            {!! $body !!}
        </div>
        <div class="email-footer">
            <p>Universal Yums | 9 Woodland Rd Unit B, Roseland NJ 07068</p>
            <p>Questions? Contact us at accountsreceivable@universalyums.com</p>
        </div>
    </div>
</body>
</html>
