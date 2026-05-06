<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido a {{ config('app.name') }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f7f7f7;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background-color: #1a1a1a;
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        .content {
            padding: 30px;
        }
        .greeting {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #1a1a1a;
        }
        .welcome-message {
            margin-bottom: 20px;
            color: #555;
        }
        .credentials {
            background-color: #f9f9f9;
            border-left: 4px solid #0066cc;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .credentials-label {
            font-weight: 600;
            color: #1a1a1a;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .credential-item {
            margin: 8px 0;
            padding: 8px 0;
            border-bottom: 1px solid #e0e0e0;
            font-size: 14px;
        }
        .credential-item:last-child {
            border-bottom: none;
        }
        .credential-label {
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .credential-value {
            color: #0066cc;
            font-weight: 600;
            font-family: 'Courier New', monospace;
            word-break: break-all;
        }
        .login-button {
            display: inline-block;
            background-color: #0066cc;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 4px;
            margin: 20px 0;
            font-weight: 600;
            text-align: center;
        }
        .footer {
            background-color: #f9f9f9;
            padding: 20px;
            text-align: center;
            color: #999;
            font-size: 12px;
            border-top: 1px solid #e0e0e0;
        }
        .footer a {
            color: #0066cc;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ config('app.name') }}</h1>
        </div>

        <div class="content">
            <div class="greeting">¡Hola {{ $firstName }} {{ $lastName }}!</div>

            <div class="welcome-message">
                <p>Te damos la más cordial bienvenida a {{ config('app.name') }}. Hemos creado una cuenta para ti en nuestro sistema.</p>

                <p>A continuación encontrarás tus credenciales de acceso. Por favor, guárdalas en un lugar seguro.</p>
            </div>

            <div class="credentials">
                <div class="credentials-label">📧 Credenciales de Acceso</div>
                <div class="credential-item">
                    <div class="credential-label">Correo Electrónico</div>
                    <div class="credential-value">{{ $email }}</div>
                </div>
                <div class="credential-item">
                    <div class="credential-label">Contraseña</div>
                    <div class="credential-value">{{ $password }}</div>
                </div>
            </div>

            <p style="text-align: center;">
                <a href="{{ $loginUrl }}" class="login-button">Iniciar Sesión</a>
            </p>

            <div style="background-color: #fef3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 15px; margin: 20px 0; color: #856404; font-size: 13px;">
                <strong>🔒 Por tu seguridad:</strong> Te recomendamos cambiar tu contraseña en tu primer acceso. Nunca compartas tus credenciales con nadie.
            </div>

            <p style="color: #999; font-size: 13px;">Si tienes problemas para acceder o necesitas ayuda, no dudes en contactar al administrador del sistema.</p>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
