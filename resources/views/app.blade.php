<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @inertiaHead
    @vite(['resources/js/app.js'])
    <script src="https://checkout.razorpay.com/v1/checkout.js" defer></script>
</head>
<body class="antialiased bg-slate-50">
    @inertia
</body>
</html>
