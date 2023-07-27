<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Laravel</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />

        <!-- Styles -->
    </head>
    <body>
        <div class="my-class">
            This is my class.
        </div>

        <br>

        <div class="bg-blue-500">This is a blue container</div>

        @vite(['resources/less/app.less', 'resources/css/app.css'])
    </body>
</html>
