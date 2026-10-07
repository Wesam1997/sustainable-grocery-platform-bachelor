<?php
declare(strict_types=1);

const PRICE_FILTER_MIN = 0;
const PRICE_FILTER_MAX = 500;

const WEIGHT_FILTER_MIN = 0;
const WEIGHT_FILTER_MAX = 5000;


/*
 * Prototype thresholds.
 * Classification is always based on CO₂e per kg,
 * even when a restaurant meal is displayed per serving.
 */
const CO2_LOW_MAX = 2.0;
const CO2_MEDIUM_MAX = 5.0;


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



