<?php
declare(strict_types=1);

final class CatalogFilters
{
    public static function parse(array $src): array
    {
        $pmin = isset($src['pmin'])
            ? (float)$src['pmin']
            : null;

        $pmax = isset($src['pmax'])
            ? (float)$src['pmax']
            : null;

        $wmin = isset($src['wmin'])
            ? (int)$src['wmin']
            : null;

        $wmax = isset($src['wmax'])
            ? (int)$src['wmax']
            : null;

        if (
            $pmin !== null
            && $pmin <= PRICE_FILTER_MIN
        ) {
            $pmin = null;
        }

        if (
            $pmax !== null
            && $pmax >= PRICE_FILTER_MAX
        ) {
            $pmax = null;
        }

        if (
            $wmin !== null
            && $wmin <= WEIGHT_FILTER_MIN
        ) {
            $wmin = null;
        }

        if (
            $wmax !== null
            && $wmax >= WEIGHT_FILTER_MAX
        ) {
            $wmax = null;
        }

        return [
            'type' => $src['type'] ?? 'all',

            'q' => trim(
                (string)($src['q'] ?? '')
            ),

            'pmin' => $pmin,
            'pmax' => $pmax,
            'wmin' => $wmin,
            'wmax' => $wmax,

            'onlyVeg' =>
                (string)($src['veg'] ?? '') === '1',

            'onlyMeat' =>
                (string)($src['meat'] ?? '') === '1',

            'lowCO2' =>
                (string)($src['lowco2'] ?? '') === '1',

            'midCO2' =>
                (string)($src['midco2'] ?? '') === '1',

            'highCO2' =>
                (string)($src['highco2'] ?? '') === '1',

            'localOnly' =>
                (string)($src['local'] ?? '') === '1',

            'sort' => $src['sort'] ?? 'eco'
        ];
    }
}
