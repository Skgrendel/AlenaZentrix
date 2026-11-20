<?php

namespace App\Exports\SurveyAuditoria;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Colores, bordes e impresión compartidos por las hojas del libro, con la paleta del logo de la aplicación.
 */
final class EstiloDeInforme
{
    public const OSCURO = '17222E';

    public const VERDE = '95C11F';

    public const ROJO = 'E30613';

    public const AMARILLO = 'FBBE12';

    public const GRIS_CLARO = 'F4F5F7';

    public const GRIS_BORDE = 'D9DCE1';

    public const GRIS_TEXTO = '5B6573';

    public const TITULO_DEL_INFORME = 'Informe de Auditoría de Suspensiones';

    /** Imagen del logo de la aplicación (la marca «Al»), o null si el archivo no está. */
    public static function rutaDelLogo(): ?string
    {
        $ruta = public_path('icons/icon-512.png');

        return is_file($ruta) ? $ruta : null;
    }

    /**
     * Aplicar estilos a un rango reescribe el estilo de cada celda y pierde el apóstrofo inicial que protege
     * los textos que empiezan con = + - @ (tabulador o retorno) contra la inyección de fórmulas.
     * Se llama al final de cada hoja, después de todos los estilos, para volver a ponerlo.
     */
    public static function reforzarTextoSeguro(Worksheet $hoja): void
    {
        foreach ($hoja->getCellCollection()->getCoordinates() as $coordenada) {
            $celda = $hoja->getCell($coordenada);
            $valor = $celda->getValue();

            if (is_string($valor) && preg_match('/^[=+\-@\t\r]/', $valor) === 1 && ! $celda->getStyle()->getQuotePrefix()) {
                $celda->setValueExplicit($valor, DataType::TYPE_STRING);
                $celda->getStyle()->setQuotePrefix(true);
            }
        }
    }

    /** Bordes finos y grises alrededor de cada celda del rango. */
    public static function bordes(Worksheet $hoja, string $rango): void
    {
        $hoja->getStyle($rango)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::GRIS_BORDE);
    }

    /**
     * Hoja lista para imprimir o guardar como PDF: horizontal, tamaño carta, márgenes angostos,
     * pie con el título y el número de página y, si se pide, el encabezado repetido en cada página.
     */
    public static function prepararImpresion(Worksheet $hoja, bool $ajustarAlAncho, ?int $filaDeEncabezado = null): void
    {
        $pagina = $hoja->getPageSetup();
        $pagina->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $pagina->setPaperSize(PageSetup::PAPERSIZE_LETTER);

        if ($ajustarAlAncho) {
            $pagina->setFitToPage(true);
            $pagina->setFitToWidth(1);
            $pagina->setFitToHeight(0);
        }

        if ($filaDeEncabezado !== null) {
            $pagina->setRowsToRepeatAtTopByStartAndEnd($filaDeEncabezado, $filaDeEncabezado);
        }

        $margenes = $hoja->getPageMargins();
        $margenes->setTop(0.5)->setBottom(0.6)->setLeft(0.4)->setRight(0.4)->setHeader(0.25)->setFooter(0.3);

        $hoja->getPageSetup()->setHorizontalCentered(true);
        $hoja->getHeaderFooter()->setOddFooter('&L&8'.self::TITULO_DEL_INFORME.'&R&8Página &P de &N');
    }
}
