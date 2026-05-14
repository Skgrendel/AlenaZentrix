@php
    $textos = json_decode(file_get_contents(resource_path('data/objetivos-pdf.json')), true);
    $destinacion = $textos['objetivo_informe'];
@endphp

<div class="section">
    <div class="section-title">{{ $destinacion['titulo'] }}</div>
    <div class="info-box">
        @foreach ($destinacion['parrafos'] as $i => $parrafo)
            <p style="font-size:8pt; line-height:1.5; margin:{{ $i < count($destinacion['parrafos']) - 1 ? '0 0 7px 0' : '0' }}; color:#333;">{{ $parrafo }}</p>
        @endforeach
    </div>
</div>
