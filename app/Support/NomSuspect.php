<?php

namespace App\Support;

/**
 * Repère les noms/prénoms qui ne ressemblent pas à un nom de personne
 * (smileys, chiffres, symboles, espaces en trop...), pour contrôle manuel.
 */
class NomSuspect
{
    /** @return string|null raison du doute, ou null si le nom semble correct */
    public static function raison(?string $valeur): ?string
    {
        $valeur ??= '';

        if (trim($valeur) === '') {
            return 'vide';
        }

        if (preg_match('/\d/u', $valeur)) {
            return 'contient un chiffre';
        }

        if (preg_match('/[^\p{L}\p{M}\s\'’.\-]/u', $valeur)) {
            return 'contient un symbole ou un smiley';
        }

        if ($valeur !== trim($valeur) || preg_match('/\s{2,}/u', $valeur)) {
            return 'espaces en trop';
        }

        if (mb_strlen(preg_replace('/[^\p{L}]/u', '', $valeur)) < 2) {
            return 'moins de 2 lettres';
        }

        return null;
    }
}
