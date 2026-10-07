<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>NurseExam247</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <div id="app"></div>
    <div id="toast-container"></div>
    <script>
        window.__BOOT__ = @json(['page' => $boot ?? 'dashboard', 'param' => $param ?? null]);
    </script>
    <script src="{{ asset('js/api.js') }}"></script>
    <script src="{{ asset('js/exam.js') }}"></script>
    <script src="{{ asset('js/views.js') }}"></script>
    <script src="{{ asset('js/app.js') }}"></script>
</body>
</html>
