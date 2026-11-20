<?php

namespace App\Exports\SurveyAuditoria;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/**
 * Evita la inyección de fórmulas: un texto que empiece con = + - @ (o tabulador / retorno)
 * se escribe siempre como texto, con el estilo de apóstrofo inicial, y nunca como fórmula.
 */
trait ValoresSeguros
{
    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);
            $cell->getStyle()->setQuotePrefix(true);

            return true;
        }

        return (new DefaultValueBinder)->bindValue($cell, $value);
    }

    /** Número si el texto es solo dígitos (sin ceros a la izquierda, hasta 15); si no, el texto tal cual. */
    protected function numeroOTexto(mixed $valor): int|float|string|null
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $texto = (string) $valor;

        if (preg_match('/^-?(0|[1-9]\d{0,14})(\.\d{1,6})?$/', $texto) === 1) {
            return str_contains($texto, '.') ? (float) $texto : (int) $texto;
        }

        return $texto;
    }
}
