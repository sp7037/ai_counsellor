<?php
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Widget Demo — PHP Page</title>
</head>
<body>
    <h1>PHP page widget demo</h1>
    <p>Hosted at <?php echo htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'localhost', ENT_QUOTES, 'UTF-8'); ?></p>
    <script async src="/build/widget.js" data-widget-key="YOUR_WIDGET_KEY" data-gateway="http://127.0.0.1:8000/widget/v1"></script>
</body>
</html>
