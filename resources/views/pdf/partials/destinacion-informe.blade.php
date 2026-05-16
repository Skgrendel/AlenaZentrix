@php
    $textos = json_decode(file_get_contents(resource_path('data/textos-legales.json')), true);
    $destinacion = $textos['destinacion_informe'];
    $nombreEmpresa = isset($orden)
        ? (\App\Models\Company::find($orden->company_id)?->nombre ?? '{empresa_contratante}')
        : '{empresa_contratante}';
@endphp

<div class="section">
    <div class="section-title">{{ $destinacion['titulo'] }}</div>
    <div class="info-box">
        @foreach ($destinacion['parrafos'] as $i => $parrafo)
            <p style="font-size:8pt; line-height:1.5; margin:{{ $i < count($destinacion['parrafos']) - 1 ? '0 0 7px 0' : '0' }}; color:#333;">{{ str_replace('{empresa_contratante}', $nombreEmpresa, $parrafo) }}</p>
        @endforeach
    </div>
</div>
