<?php

// ==================================================
// SMART SEARCH
// Ingen OpenAI / ingen API-key
// ==================================================

require_once __DIR__ . '/connect.php';


// ==================================================
// 1. HJÆLPEFUNKTIONER
// ==================================================

function lower_text(string $text): string
{
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($text, 'UTF-8');
    }

    return strtolower($text);
}


function normalize_text(string $text): string
{
    $text = lower_text(trim($text));

    // CO₂ -> co2
    $text = str_replace(
        ['co₂', 'co²'],
        'co2',
        $text
    );

    // Fjern mærkelige tegn
    $text = preg_replace(
        '/[^\p{L}\p{N}\s.,]/u',
        ' ',
        $text
    );

    // Flere mellemrum -> ét mellemrum
    $text = preg_replace(
        '/\s+/u',
        ' ',
        $text
    );

    return trim($text);
}


function normalize_for_compare(string $text): string
{
    $text = lower_text($text);

    // Gør danske bogstaver lettere for levenshtein
    $text = strtr(
        $text,
        [
            'æ' => 'ae',
            'ø' => 'o',
            'å' => 'a',
            'é' => 'e',
            'è' => 'e',
            'ü' => 'u'
        ]
    );

    return $text;
}


function split_words(string $text): array
{
    $words = preg_split(
        '/[^\p{L}\p{N}]+/u',
        $text,
        -1,
        PREG_SPLIT_NO_EMPTY
    );

    return $words ?: [];
}


// ==================================================
// FUZZY MATCH
// Fx:
// tommat -> tomat
// kyling -> kylling
// lokallt -> lokalt
// ==================================================

function fuzzy_equals(
    string $word,
    string $target,
    int $maxDistance = 2
): bool {

    $a = normalize_for_compare($word);
    $b = normalize_for_compare($target);

    if ($a === $b) {
        return true;
    }

    return levenshtein($a, $b) <= $maxDistance;
}


function contains_fuzzy(
    array $words,
    array $targets,
    int $distance = 1
): bool {

    foreach ($words as $word) {

        foreach ($targets as $target) {

            if (
                fuzzy_equals(
                    $word,
                    $target,
                    $distance
                )
            ) {
                return true;
            }
        }
    }

    return false;
}


// ==================================================
// 2. MODTAG SØGNING
// ==================================================

$originalQuery =
    isset($_GET['q'])
        ? trim((string)$_GET['q'])
        : '';


if ($originalQuery === '') {

    header(
        'Location: ../Home/home.php'
    );

    exit;
}


$text = normalize_text($originalQuery);

$words = split_words($text);


// ==================================================
// 3. PARAMETRE SOM SENDES TIL HOME
// ==================================================

$params = [];


// Behold valgt type:
// all / restaurant / market

$type =
    isset($_GET['type'])
        ? (string)$_GET['type']
        : 'all';


if (
    in_array(
        $type,
        ['all', 'restaurant', 'market'],
        true
    )
) {
    $params['type'] = $type;
}


// ==================================================
// 4. LOKAL
// ==================================================

if (
    contains_fuzzy(
        $words,
        [
            'lokal',
            'lokalt',
            'lokale',
            'local'
        ],
        1
    )
) {

    $params['local'] = '1';
}


// ==================================================
// 5. VEGETARISK / KØD
// ==================================================

// "uden kød"
if (
    preg_match(
        '/\b(uden\s+kød|without\s+meat)\b/u',
        $text
    )
) {

    $params['veg'] = '1';

} elseif (
    contains_fuzzy(
        $words,
        [
            'vegetarisk',
            'vegetar',
            'vegetarian',
            'veggie'
        ],
        2
    )
) {

    $params['veg'] = '1';
}


// Kød
if (
    !isset($params['veg'])
    &&
    contains_fuzzy(
        $words,
        [
            'kød',
            'koed',
            'meat'
        ],
        1
    )
) {

    $params['meat'] = '1';
}


// ==================================================
// 6. CO2
// ==================================================

// Lav CO2

if (
    preg_match(
        '/\b(lav|lavt|lavere|low)\b.{0,15}\b(co2|klima|aftryk)\b/u',
        $text
    )
    ||
    preg_match(
        '/\b(co2|klima|aftryk)\b.{0,15}\b(lav|lavt|lavere|low)\b/u',
        $text
    )
) {

    $params['lowco2'] = '1';
}


// Middel CO2

if (
    preg_match(
        '/\b(middel|medium|moderat)\b.{0,15}\b(co2|klima|aftryk)\b/u',
        $text
    )
    ||
    preg_match(
        '/\b(co2|klima|aftryk)\b.{0,15}\b(middel|medium|moderat)\b/u',
        $text
    )
) {

    $params['midco2'] = '1';
}


// Høj CO2

if (
    preg_match(
        '/\b(høj|højt|hoj|hojt|high)\b.{0,15}\b(co2|klima|aftryk)\b/u',
        $text
    )
    ||
    preg_match(
        '/\b(co2|klima|aftryk)\b.{0,15}\b(høj|højt|hoj|hojt|high)\b/u',
        $text
    )
) {

    $params['highco2'] = '1';
}


// ==================================================
// 7. VÆGT
// ==================================================

$searchWithoutWeight = $text;


// ---------- MAX VÆGT ----------

if (
    preg_match(
        '/\b(?:under|max|maks|maksimum|højst|op til|below|up to)\s*([0-9]+(?:[.,][0-9]+)?)\s*(kg|kilo|kilogram|g|gram|grammer)\b/u',
        $text,
        $match
    )
) {

    $weight =
        (float)str_replace(
            ',',
            '.',
            $match[1]
        );

    $unit = $match[2];


    if (
        in_array(
            $unit,
            ['kg', 'kilo', 'kilogram'],
            true
        )
    ) {
        $weight *= 1000;
    }

    $params['wmax'] = (int)$weight;


    $searchWithoutWeight =
        str_replace(
            $match[0],
            ' ',
            $searchWithoutWeight
        );
}


// ---------- MIN VÆGT ----------

if (
    preg_match(
        '/\b(?:over|min|minimum|mindst|fra|above|at least)\s*([0-9]+(?:[.,][0-9]+)?)\s*(kg|kilo|kilogram|g|gram|grammer)\b/u',
        $text,
        $match
    )
) {

    $weight =
        (float)str_replace(
            ',',
            '.',
            $match[1]
        );

    $unit = $match[2];


    if (
        in_array(
            $unit,
            ['kg', 'kilo', 'kilogram'],
            true
        )
    ) {
        $weight *= 1000;
    }

    $params['wmin'] = (int)$weight;


    $searchWithoutWeight =
        str_replace(
            $match[0],
            ' ',
            $searchWithoutWeight
        );
}


// ==================================================
// 8. PRIS
// ==================================================

// Maximum pris:
// under 20
// max 20
// under 20 kr
// højst 20

if (
    preg_match(
        '/\b(?:under|max|maks|maksimum|højst|op til|below|less than|up to)\s*([0-9]+(?:[.,][0-9]+)?)\s*(?:kr|kroner|dkk)?\b/u',
        $searchWithoutWeight,
        $match
    )
) {

    $params['pmax'] =
        (float)str_replace(
            ',',
            '.',
            $match[1]
        );
}


// Minimum pris:
// over 20
// mindst 20
// min 20

if (
    preg_match(
        '/\b(?:over|min|minimum|mindst|fra|above|more than|at least)\s*([0-9]+(?:[.,][0-9]+)?)\s*(?:kr|kroner|dkk)?\b/u',
        $searchWithoutWeight,
        $match
    )
) {

    $params['pmin'] =
        (float)str_replace(
            ',',
            '.',
            $match[1]
        );
}


// ==================================================
// 9. SORTERING
// ==================================================

$params['sort'] = 'eco';


if (
    preg_match(
        '/\b(billigst|billigste|cheap|cheapest)\b/u',
        $text
    )
) {

    $params['sort'] = 'price_asc';

} elseif (
    preg_match(
        '/\b(dyrest|dyreste|expensive|most expensive)\b/u',
        $text
    )
) {

    $params['sort'] = 'price_desc';

} elseif (
    preg_match(
        '/\b(nyeste|nyest|newest|new)\b/u',
        $text
    )
) {

    $params['sort'] = 'newest';
}


// ==================================================
// 10. STOPORD
// Ord som IKKE er produktnavne
// ==================================================

$stopWords = [

    // Dansk
    'jeg',
    'vil',
    'gerne',
    'have',
    'find',
    'finde',
    'vis',
    'mig',
    'en',
    'et',
    'den',
    'det',
    'de',
    'der',
    'som',
    'med',
    'og',
    'eller',
    'til',
    'af',
    'for',
    'på',
    'i',

    // Pris
    'under',
    'over',
    'maks',
    'max',
    'maksimum',
    'minimum',
    'min',
    'mindst',
    'højst',
    'fra',
    'op',
    'kr',
    'kroner',
    'dkk',

    // Vægt
    'gram',
    'grammer',
    'g',
    'kg',
    'kilo',
    'kilogram',

    // Lokal
    'lokal',
    'lokalt',
    'lokale',
    'local',

    // CO2
    'co2',
    'klima',
    'klimaaftryk',
    'aftryk',
    'lav',
    'lavt',
    'lavere',
    'middel',
    'medium',
    'moderat',
    'høj',
    'højt',
    'hoj',
    'hojt',

    // Kost
    'vegetar',
    'vegetarisk',
    'vegetarian',
    'veggie',
    'kød',
    'koed',
    'meat',
    'uden',

    // Sortering
    'billig',
    'billigt',
    'billigst',
    'billigste',
    'dyrest',
    'dyreste',
    'nyeste',
    'nyest',

    // Engelsk
    'i',
    'want',
    'would',
    'like',
    'a',
    'an',
    'the',
    'find',
    'show',
    'me',
    'with',
    'and',
    'or',
    'for',
    'of',
    'below',
    'above',
    'less',
    'than',
    'more',
    'up',
    'to',
    'at',
    'least',
    'low',
    'high',
    'cheap',
    'cheapest',
    'new',
    'newest'
];


// ==================================================
// 11. FIND ORD DER KAN VÆRE PRODUKT
// ==================================================

$productCandidates = [];

foreach ($words as $word) {

    // Ignorer tal
    if (is_numeric($word)) {
        continue;
    }


    // Ignorer meget korte ord
    if (
        function_exists('mb_strlen')
            ? mb_strlen($word, 'UTF-8') < 2
            : strlen($word) < 2
    ) {
        continue;
    }


    $isStopWord = false;


    foreach ($stopWords as $stopWord) {

        // Også tolerant over for fx "lokallt"
        if (
            fuzzy_equals(
                $word,
                $stopWord,
                1
            )
        ) {

            $isStopWord = true;
            break;
        }
    }


    if (!$isStopWord) {

        $productCandidates[] = $word;
    }
}


// ==================================================
// 12. HENT RIGTIGE ORD FRA DATABASEN
// ==================================================
//
// Smart Search finder selv produktord i databasen.
// Vi hardcoder altså IKKE alle produkter.
//

$databaseWords = [];


$sql = "
    SELECT
        title,
        category,
        ingredients
    FROM proudkt
";


$result = mysqli_query(
    $conn,
    $sql
);


if ($result) {

    while (
        $row = mysqli_fetch_assoc($result)
    ) {

        $databaseText =
            ($row['title'] ?? '')
            . ' '
            . ($row['category'] ?? '')
            . ' '
            . ($row['ingredients'] ?? '');


        $dbWords =
            split_words(
                normalize_text(
                    $databaseText
                )
            );


        foreach ($dbWords as $dbWord) {

            $length =
                function_exists('mb_strlen')
                    ? mb_strlen(
                        $dbWord,
                        'UTF-8'
                    )
                    : strlen($dbWord);


            // Ignorer meget korte databaseord
            if ($length < 3) {
                continue;
            }


            $databaseWords[$dbWord] =
                $dbWord;
        }
    }
}


// Gør associative array til almindeligt array

$databaseWords =
    array_values(
        $databaseWords
    );


// ==================================================
// 13. FUZZY PRODUKTSØGNING
// ==================================================

$bestProduct = null;
$bestScore = PHP_INT_MAX;


foreach (
    $productCandidates
    as $candidate
) {

    $candidateCompare =
        normalize_for_compare(
            $candidate
        );


    foreach (
        $databaseWords
        as $dbWord
    ) {

        $dbCompare =
            normalize_for_compare(
                $dbWord
            );


        // Præcis match
        if (
            $candidateCompare
            ===
            $dbCompare
        ) {

            $bestProduct = $dbWord;
            $bestScore = 0;

            break 2;
        }


        $distance =
            levenshtein(
                $candidateCompare,
                $dbCompare
            );


        $candidateLength =
            strlen(
                $candidateCompare
            );


        // Tilladt stavefejl afhængigt af længden

        if ($candidateLength <= 4) {

            $allowedDistance = 1;

        } elseif ($candidateLength <= 7) {

            $allowedDistance = 2;

        } else {

            $allowedDistance = 3;
        }


        if (
            $distance <= $allowedDistance
            &&
            $distance < $bestScore
        ) {

            $bestProduct = $dbWord;
            $bestScore = $distance;
        }
    }
}


// ==================================================
// 14. PRODUKT QUERY
// ==================================================

if ($bestProduct !== null) {

    $params['q'] =
        $bestProduct;

}


// Hvis vi ikke fandt fuzzy match,
// men der stadig er et produktord,
// bruger vi første kandidat.

elseif (
    !empty($productCandidates)
) {

    $params['q'] =
        $productCandidates[0];
}


// ==================================================
// 15. MARKER SMART SEARCH
// ==================================================

$params['search_mode'] =
    'smart';


// ==================================================
// 16. GEM DEN OPRINDELIGE SØGNING
// ==================================================
//
// Så kan vi senere vise:
// "Du søgte: jeg vil have lokal tommat..."

$params['original_q'] =
    $originalQuery;


// ==================================================
// 17. SEND TIL HOME.PHP
// ==================================================

$queryString =
    http_build_query(
        $params
    );


header(
    'Location: ../Home/home.php?'
    . $queryString
);

exit;