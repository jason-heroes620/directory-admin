<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <title>Heroes</title>
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
                    <p>Partner Registration information:</p>
                    <br>
                    <p class="text-xl font-semibold">
                        Contact Person: {{ $lastName }} {{ $firstName }}
                    </p>
                    <p>
                        Email: {{ $email }}
                    </p>
                    <p>
                        Contact No.: {{ $phone }}
                    </p>
                    <p>
                        Company: {{ $company}}
                    </p>
                    <p>
                        Industry: {{ $industry }}
                    </p>
                </div>
            </div>
        </div>
    </div>

</body>

</html>