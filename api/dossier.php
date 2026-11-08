<?php

/**
 * Leest een lokaal website-dossier in.
 *
 * Het dossier wordt alleen gelezen; dit bestand wijzigt geen gegevens.
 */
function koenraad_get_website_dossier(): string
{
    $dossierPath = dirname(__DIR__) . '/data/website-dossier-jan.md';

    if (!is_file($dossierPath) || !is_readable($dossierPath)) {
        return '';
    }

    $dossier = file_get_contents($dossierPath);

    if ($dossier === false) {
        return '';
    }

    return trim($dossier);
}
