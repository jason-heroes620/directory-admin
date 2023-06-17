<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <title>Heroes School Directory</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @vite('resources/js/app.js')
</head>

<body>
    <div class="flex w-full justify-center">
        <div class="flex flex-col w-full place-content-center">
            <div>
                <p>Hi Admin, </p>
            </div>
            <div>
                <div>
                    <p>Below is the inquiry sent from Heroes School Directory Form:</p>
                    <br>
                    <p class="text-xl font-semibold">
                        Contact Person: {{ $lastName }} {{ $firstName }}
                    </p>
                    <p>
                        Email: {{ $email }}
                    </p>
                    <p>
                        Message: {{ $messages }}
                    </p>
                </div>
            </div>
        </div>
    </div>

</body>

</html>