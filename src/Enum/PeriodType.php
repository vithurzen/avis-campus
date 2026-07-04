<?php

namespace App\Enum;

/**
 * Rythme des périodes académiques d'une formation.
 * Un cursus est soit semestriel, soit trimestriel ; toutes ses périodes
 * (entité Semester) héritent de ce type.
 */
enum PeriodType: string
{
    case Semester = 'semester';
    case Trimester = 'trimester';

    /**
     * Libellé français affiché dans l'UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Semester  => 'Semestre',
            self::Trimester => 'Trimestre',
        };
    }

    /**
     * Abréviation utilisée pour nommer les périodes (S1, T1, ...).
     */
    public function abbreviation(): string
    {
        return match ($this) {
            self::Semester  => 'S',
            self::Trimester => 'T',
        };
    }
}
