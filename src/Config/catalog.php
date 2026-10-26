<?php
declare(strict_types=1);

const PRICE_FILTER_MIN = 0;
const PRICE_FILTER_MAX = 500;

const WEIGHT_FILTER_MIN = 0;
const WEIGHT_FILTER_MAX = 5000;

/*
 * Data-derived climate classification thresholds.
 *
 * Thresholds were calculated using tertiles from
 * 2451 environmental_food_data records.
 *
 * Classification is based on kg CO2e per kg of food,
 * including restaurant meals displayed per serving.
 *
 * Low:    <= 1.80
 * Medium: > 1.80 and <= 5.21
 * High:   > 5.21
 *
 * These are dataset-relative thresholds,
 * not universal scientific standards.
 */
const CO2_LOW_MAX = 1.80;
const CO2_MEDIUM_MAX = 5.21;

const RESTAURANTS = ['Sakura'];
const MARKETS = ['ØkoHaven'];
const LOCAL_MERCHANTS = ['ØkoHaven'];

const MEAT_RED = [
    'okse',
    'oksekød',
    'kalv',
    'svin',
    'svinekød',
    'lam',
    'lammekød',
    'bøf',
    'beef',
    'pork',
    'veal',
    'lamb'
];

const MEAT_WHITE = [
    'kylling',
    'høne',
    'kalkun',
    'chicken',
    'turkey'
];

const FISH = [
    'fisk',
    'laks',
    'tun',
    'torsk',
    'sild',
    'makrel',
    'ørred',
    'reje',
    'rejer',
    'fish',
    'salmon',
    'tuna',
    'cod',
    'trout',
    'shrimp',
    'shrimps',
    'prawn',
    'prawns',
    'seafood',
    'ebi',
    'scampi'
];
