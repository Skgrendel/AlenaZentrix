<?php

namespace Database\Seeders;

use App\Models\TipoAnomalia;
use App\Models\TipoComercio;
use App\Models\TipoImposibilidad;
use App\Models\TipoRegulador;
use App\Models\MarcaMedidor;
use App\Models\MarcaRegulador;
use Illuminate\Database\Seeder;

class CatalogosSeeder extends Seeder
{
    public function run(): void
    {
        // Tipos de Anomalía
        $anomalias = [
            ['nombre' => 'Sin Anomalias',                          'nomenclatura' => 'NA'],
            ['nombre' => 'Bypass',                                 'nomenclatura' => 'BY'],
            ['nombre' => 'Medidor con sellos manipulados',         'nomenclatura' => 'MCSM'],
            ['nombre' => 'Medidor con digitos desalineados',       'nomenclatura' => 'MCDD'],
            ['nombre' => 'Medidor sin talco',                      'nomenclatura' => 'MST'],
            ['nombre' => 'Medidor enterrado',                      'nomenclatura' => 'ME'],
            ['nombre' => 'Conexión directa',                       'nomenclatura' => 'CD'],
            ['nombre' => 'Medidor frenado',                        'nomenclatura' => 'MF'],
            ['nombre' => 'Medidor gira hacia atrás',               'nomenclatura' => 'MGHA'],
            ['nombre' => 'Medidor No encontrado',                  'nomenclatura' => 'MNE'],
            ['nombre' => 'Medidor No Concuerda con el contrato',   'nomenclatura' => 'MNC'],
            ['nombre' => 'Medidor trocado',                        'nomenclatura' => 'MT'],
            ['nombre' => 'Inactivo y en Consumo',                  'nomenclatura' => 'IC'],
            ['nombre' => 'Inspeccion y revision',                   'nomenclatura' => 'IR'],
            ['nombre' => 'Posible fuga',                           'nomenclatura' => 'PF'],
            ['nombre' => 'Pendiente de Retiro / Revision',         'nomenclatura' => 'PR'],
            ['nombre' => 'Medidor con doble local Comercial',      'nomenclatura' => 'MDL'],
        ];
        foreach ($anomalias as $item) {
            TipoAnomalia::create($item);
        }

        // Tipos de Comercio
        $comercios = [
            ['nombre' => 'Restaurante',                            'nomenclatura' => 'RE'],
            ['nombre' => 'Venta de Comidas Rapidas',               'nomenclatura' => 'VCR'],
            ['nombre' => 'Panaderia',                              'nomenclatura' => 'PA'],
            ['nombre' => 'Cafeteria',                              'nomenclatura' => 'CA'],
            ['nombre' => 'Bar / Discoteca',                        'nomenclatura' => 'BA'],
            ['nombre' => 'Lavanderia',                             'nomenclatura' => 'LA'],
            ['nombre' => 'Venta de Fritos',                        'nomenclatura' => 'VF'],
            ['nombre' => 'Asadero de Pollos',                      'nomenclatura' => 'AP'],
            ['nombre' => 'Industria',                              'nomenclatura' => 'IN'],
            ['nombre' => 'Productor de Quesos / leche',            'nomenclatura' => 'PQL'],
            ['nombre' => 'Hotel/Motel',                            'nomenclatura' => 'HM'],
            ['nombre' => 'Centro recreacional',                    'nomenclatura' => 'CR'],
            ['nombre' => 'Institucion educativa',                  'nomenclatura' => 'IE'],
            ['nombre' => 'Centro Comercial',                       'nomenclatura' => 'CC'],
            ['nombre' => 'Heladeria',                              'nomenclatura' => 'HE'],
            ['nombre' => 'Bodegas',                                'nomenclatura' => 'BO'],
            ['nombre' => 'Inmueble Desocupado / En Arriendo',      'nomenclatura' => 'IM'],
            ['nombre' => 'Tienda de Abarrotes',                    'nomenclatura' => 'TA'],
            ['nombre' => 'Ferreteria',                             'nomenclatura' => 'TF'],
            ['nombre' => 'Papeleria y Micelaneas',                 'nomenclatura' => 'TP'],
            ['nombre' => 'Tienda de Juguetes',                     'nomenclatura' => 'TJ'],
            ['nombre' => 'Veterinaria',                            'nomenclatura' => 'VE'],
            ['nombre' => 'Taller de Motos',                        'nomenclatura' => 'TM'],
            ['nombre' => 'Estacion de Servicio',                   'nomenclatura' => 'ES'],
            ['nombre' => 'Hospital / Clinica / IPS / Consultorio', 'nomenclatura' => 'HC'],
            ['nombre' => 'Terreno Baldio',                         'nomenclatura' => 'TB'],
            ['nombre' => 'Locales Comerciales',                    'nomenclatura' => 'LC'],
            ['nombre' => 'Drogueria',                              'nomenclatura' => 'DR'],
            ['nombre' => 'Edificio / En construccion',             'nomenclatura' => 'EC'],
            ['nombre' => 'Base Naval / Base Militar',              'nomenclatura' => 'BM'],
            ['nombre' => 'Comercio no encontrado',                 'nomenclatura' => 'CNE'],
        ];
        foreach ($comercios as $item) {
            TipoComercio::create($item);
        }

        // Tipos de Imposibilidad
        $imposibilidades = [
            ['nombre' => 'Ninguna',                                    'nomenclatura' => 'NI'],
            ['nombre' => 'Obstaculos (Poca Visibilidad)',               'nomenclatura' => 'OB'],
            ['nombre' => 'Falta de Acceso (Rejas, Cerraduras, Etc)',   'nomenclatura' => 'FA'],
            ['nombre' => 'Falta de Medidor',                           'nomenclatura' => 'FM'],
            ['nombre' => 'Usuario No Permite Lectura',                 'nomenclatura' => 'UNP'],
            ['nombre' => 'Lugar Deshabitado',                          'nomenclatura' => 'LD'],
        ];
        foreach ($imposibilidades as $item) {
            TipoImposibilidad::create($item);
        }

        // Tipos de Regulador (presión)
        $reguladores = [
            ['nombre' => 'Baja presion',  'nomenclatura' => 'BP'],
            ['nombre' => 'Media presion', 'nomenclatura' => 'MP'],
            ['nombre' => 'Alta presion',  'nomenclatura' => 'AP'],
        ];
        foreach ($reguladores as $item) {
            TipoRegulador::create($item);
        }

        // Marcas de Medidor
        $marcasMedidor = [
            ['nombre' => 'DAESUNG',              'nomenclatura' => 'DA'],
            ['nombre' => 'YAO',                  'nomenclatura' => 'YA'],
            ['nombre' => 'YAZAKI',               'nomenclatura' => 'YZ'],
            ['nombre' => 'GOLDSTAR',             'nomenclatura' => 'GO'],
            ['nombre' => 'SINGER',               'nomenclatura' => 'SI'],
            ['nombre' => 'RICOH',                'nomenclatura' => 'RI'],
            ['nombre' => 'KROHNSCHODER',         'nomenclatura' => 'KR'],
            ['nombre' => 'AMERICAN METER',       'nomenclatura' => 'AM'],
            ['nombre' => 'ROCKWELL',             'nomenclatura' => 'RO'],
            ['nombre' => 'KEUK',                 'nomenclatura' => 'KE'],
            ['nombre' => 'INVENSYS - SENSUS',    'nomenclatura' => 'IS'],
            ['nombre' => 'ABB ELSTER',           'nomenclatura' => 'AB'],
            ['nombre' => 'ROMET',                'nomenclatura' => 'RM'],
            ['nombre' => 'INSTROMET',            'nomenclatura' => 'IM'],
            ['nombre' => 'IM METER',             'nomenclatura' => 'IMM'],
            ['nombre' => 'ELSTER',               'nomenclatura' => 'EL'],
            ['nombre' => 'GALLUS',               'nomenclatura' => 'GA'],
            ['nombre' => 'EQUIMETER',            'nomenclatura' => 'EQ'],
            ['nombre' => 'ITRON',                'nomenclatura' => 'IT'],
            ['nombre' => 'ACTARIS',              'nomenclatura' => 'AC'],
            ['nombre' => 'WILSON',               'nomenclatura' => 'WI'],
            ['nombre' => 'KEUKDONG',             'nomenclatura' => 'KD'],
            ['nombre' => 'METREX',               'nomenclatura' => 'ME'],
            ['nombre' => 'SHINHAN',              'nomenclatura' => 'SH'],
            ['nombre' => 'GENERICA',             'nomenclatura' => 'GE'],
            ['nombre' => 'SENSUS',               'nomenclatura' => 'SE'],
            ['nombre' => 'DRESSER',              'nomenclatura' => 'DR'],
            ['nombre' => 'DUMIN',                'nomenclatura' => 'DU'],
            ['nombre' => 'WITECH',               'nomenclatura' => 'WT'],
            ['nombre' => 'CHINT',                'nomenclatura' => 'CH'],
            ['nombre' => 'FMG',                  'nomenclatura' => 'FM'],
            ['nombre' => 'TANCY',                'nomenclatura' => 'TA'],
            ['nombre' => 'MEDIDOR FICTICIO (CAMBIO)', 'nomenclatura' => 'MF'],
        ];
        foreach ($marcasMedidor as $item) {
            MarcaMedidor::create($item);
        }

        // Marcas de Regulador
        $marcasRegulador = [
            ['nombre' => 'G-1.6',    'nomenclatura' => 'G16'],
            ['nombre' => 'G-2.5',    'nomenclatura' => 'G25'],
            ['nombre' => 'G-4',      'nomenclatura' => 'G4'],
            ['nombre' => 'G-6',      'nomenclatura' => 'G6'],
            ['nombre' => 'G10',      'nomenclatura' => 'G10'],
            ['nombre' => 'G16',      'nomenclatura' => 'G16B'],
            ['nombre' => 'AL-425',   'nomenclatura' => 'AL425'],
            ['nombre' => 'AL-800',   'nomenclatura' => 'AL800'],
            ['nombre' => 'AL-1000',  'nomenclatura' => 'AL1000'],
            ['nombre' => 'AC-250',   'nomenclatura' => 'AC250'],
            ['nombre' => 'AC-630',   'nomenclatura' => 'AC630'],
            ['nombre' => 'MR-8',     'nomenclatura' => 'MR8'],
            ['nombre' => 'MR-10',    'nomenclatura' => 'MR10'],
            ['nombre' => 'MR-12',    'nomenclatura' => 'MR12'],
        ];
        foreach ($marcasRegulador as $item) {
            MarcaRegulador::create($item);
        }
    }
}
