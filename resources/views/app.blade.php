<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="/images/ksnsk-logo.png" type="image/png">
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="/images/ksnsk-logo.png">
    @inertiaHead
    @vite(['resources/js/app.js'])
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
</head>
<body class="antialiased bg-slate-50">
    @inertia
</body>
</html>
