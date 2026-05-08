<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', config('app.name'))</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f2f2f2;
            color: #333333;
            line-height: 1.6;
            padding: 32px 16px;
        }
        .wrapper {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #e0e0e0;
        }
        .top-bar {
            height: 4px;
            background-color: #17222e;
        }
        /* Contenido */
        .content {
            padding: 32px 40px;
        }
        .greeting {
            font-size: 17px;
            font-weight: 600;
            color: #17222e;
            margin-bottom: 10px;
        }
        .intro {
            font-size: 14px;
            color: #555555;
            margin-bottom: 24px;
        }
        /* Alertas integradas al contenido */
        .alert {
            padding: 14px 16px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        .alert.success { background-color: #f0faf4; border-color: #2d9e5f; color: #1a5e38; }
        .alert.danger  { background-color: #fdf2f2; border-color: #c0392b; color: #7f1d1d; }
        .alert.info    { background-color: #f4f6f9; border-color: #17222e; color: #17222e; }
        .alert.warning { background-color: #fffbf0; border-color: #d4a017; color: #7a5800; }
        /* Secciones */
        .section {
            border: 1px solid #e8e8e8;
            border-radius: 4px;
            margin: 20px 0;
            overflow: hidden;
        }
        .section-title {
            background-color: #f7f7f7;
            border-bottom: 1px solid #e8e8e8;
            padding: 8px 16px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #17222e;
        }
        .detail-row {
            display: flex;
            padding: 9px 16px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label {
            color: #888888;
            min-width: 140px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .detail-value {
            color: #1a1a1a;
            font-weight: 500;
            flex: 1;
        }
        /* Caja de motivo / comentario */
        .comment-box {
            background-color: #fdf2f2;
            border: 1px solid #f5c6c6;
            border-radius: 4px;
            padding: 16px;
            margin: 20px 0;
            font-size: 14px;
            color: #5c2323;
            white-space: pre-line;
            line-height: 1.7;
        }
        .comment-box .comment-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #c0392b;
            margin-bottom: 8px;
        }
        /* Nota */
        .note {
            font-size: 13px;
            color: #777777;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #eeeeee;
        }
        /* Footer */
        .footer {
            padding: 20px 40px;
            text-align: center;
            background-color: #f7f7f7;
            border-top: 1px solid #e0e0e0;
            font-size: 12px;
            color: #aaaaaa;
        }
        .footer a { color: #17222e; text-decoration: none; }
    </style>
</head>
<body>
    <div class="wrapper">

        <div class="top-bar"></div>

        <div class="content">
            @yield('content')
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.
        </div>

    </div>
</body>
</html>
