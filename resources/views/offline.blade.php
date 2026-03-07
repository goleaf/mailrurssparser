<!DOCTYPE html>
<html lang="ru">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Офлайн</title>
        <style>
            body {
                margin: 0;
                min-height: 100vh;
                display: grid;
                place-items: center;
                font-family: Instrument Sans, system-ui, sans-serif;
                background: linear-gradient(180deg, #eff6ff 0%, #dbeafe 100%);
                color: #0f172a;
            }

            .card {
                max-width: 32rem;
                margin: 1.5rem;
                padding: 2rem;
                border-radius: 1.5rem;
                background: rgba(255, 255, 255, 0.92);
                box-shadow: 0 25px 80px -40px rgba(15, 23, 42, 0.45);
                text-align: center;
            }

            .badge {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.5rem 0.875rem;
                border-radius: 9999px;
                background: #dbeafe;
                color: #1d4ed8;
                font-size: 0.75rem;
                font-weight: 700;
                letter-spacing: 0.14em;
                text-transform: uppercase;
            }

            h1 {
                margin: 1rem 0 0;
                font-size: 2rem;
            }

            p {
                margin: 1rem 0 0;
                line-height: 1.65;
                color: #475569;
            }
        </style>
    </head>
    <body>
        <main class="card">
            <div class="badge">Офлайн режим</div>
            <h1>Нет подключения к интернету</h1>
            <p>
                Ранее сохранённые страницы доступны в режиме офлайн.
                Как только соединение вернётся, приложение снова загрузит
                свежие данные.
            </p>
        </main>
    </body>
</html>
