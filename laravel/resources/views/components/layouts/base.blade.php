@props(['title' => 'Plataforma'])
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#102438">
<title>{{ $title }} · SIRMD</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-screen bg-paper font-sans text-ink antialiased">
<a href="#contenido" class="skip-link">Saltar al contenido</a>{{ $slot }}</body>
</html>
