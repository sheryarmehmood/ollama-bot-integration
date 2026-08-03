<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AI ChatBot - Ollama</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #1a1a2e;
        }
    </style>
</head>
<body>
    <div id="app">
        <chat-bot></chat-bot>
    </div>
</body>
</html>
