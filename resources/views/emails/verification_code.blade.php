<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Код подтверждения</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&display=swap" rel="stylesheet">
</head>
<body style="font-family: 'Open Sans', sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; text-align: center;">
<div style="background: #181818; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); width: 100%; margin: auto; color: white;">
    <img src="{{ asset('images/logo.png') }}?v={{ time() }}" alt="Logo" style="margin-bottom: 10px; width: 350px;">
    <h1 style="font-size: 32px; margin-bottom: 10px;">Ваш код для сброса пароля</h1>

    <p style="margin: 10px 0; font-size: 20px;">Код: <span style="font-weight: bold; font-size: 24px; color: #ff4860;">{{ $code }}</span></p>
    <p style="margin: 10px 0; font-size: 20px;">Пожалуйста, используйте этот код для сброса пароля.</p>

    <!-- Сообщение для пользователей, которые не сбрасывали пароль -->
    <p style="margin: 20px 0; font-size: 20px;">
        Если вы не запрашивали сброс пароля, пожалуйста, проигнорируйте это сообщение.
    </p>
</div>
</body>
</html>
