@extends('emails.layout')

@section('title', 'Bienvenido a ' . config('app.name'))

@section('content')
    <div class="greeting">¡Hola {{ $firstName }} {{ $lastName }}!</div>
    <div class="intro">
        Te damos la más cordial bienvenida a {{ config('app.name') }}. Hemos creado una cuenta para ti en nuestro sistema.
        A continuación encontrarás tus credenciales de acceso. Por favor, guárdalas en un lugar seguro.
    </div>

    <div class="section">
        <div class="section-title">Credenciales de Acceso</div>
        <div class="detail-row">
            <span class="detail-label">Correo Electrónico</span>
            <span class="detail-value" style="font-family: 'Courier New', monospace; color: #17222e;">{{ $email }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Contraseña</span>
            <span class="detail-value" style="font-family: 'Courier New', monospace; color: #17222e;">{{ $password }}</span>
        </div>
    </div>

    <p style="text-align: center; margin: 28px 0;">
        <a href="{{ $loginUrl }}"
           style="display: inline-block; background-color: #17222e; color: #ffffff; padding: 12px 32px; text-decoration: none; border-radius: 4px; font-size: 14px; font-weight: 600; letter-spacing: 0.5px;">
            Iniciar Sesión
        </a>
    </p>

    <div style="background-color: #fffbf0; border: 1px solid #f0d080; border-radius: 4px; padding: 14px 16px; font-size: 13px; color: #7a5800;">
        <strong>Por tu seguridad:</strong> Te recomendamos cambiar tu contraseña en tu primer acceso.
        Nunca compartas tus credenciales con nadie.
    </div>

    <p class="note">Si tienes problemas para acceder o necesitas ayuda, no dudes en contactar al administrador del sistema.</p>
@endsection
