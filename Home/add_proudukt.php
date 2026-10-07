<?php
session_start();

/* Lokal administrationsside til XAMPP. */
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Denne side er kun tilgængelig lokalt.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

require_once __DIR__ . '/../Api/connect.php';
require_once __DIR__ . '/../Api/catalog.php';

mysqli_set_charset($conn, 'utf8mb4');

function esc($value): string
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

$_SESSION['create_product_csrf'] ??= bin2hex(random_bytes(32));

/* Genbrug eksisterende kombinationer af butik og kategori. */
$administration = flashfood_application($conn)->administration();
$sellers = $administration->sellers();
foreach ($sellers as &$sellerRow) $sellerRow['is_meal'] = is_restaurant_product($sellerRow);
unset($sellerRow);
$foods = $administration->climateFoods();

$error = '';
$successId = $_SESSION['created_product_id'] ?? null;
unset($_SESSION['created_product_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        $token = $_POST['csrf'] ?? '';

        if (!is_string($token) ||
            !hash_equals($_SESSION['create_product_csrf'], $token)) {
            throw new InvalidArgumentException(
                'Formularen er udløbet. Genindlæs siden og prøv igen.'
            );
        }

        $sellerIndex = filter_var(
            $_POST['seller'] ?? null,
            FILTER_VALIDATE_INT
        );

        if ($sellerIndex === false ||
            $sellerIndex === null ||
            !isset($sellers[$sellerIndex])) {
            throw new InvalidArgumentException('Vælg en butik.');
        }

        $seller = $sellers[$sellerIndex];
        $merchant = $seller['merchant'];
        $category = (int)$seller['category'];
        $isMeal = (bool)$seller['is_meal'];

        $title = trim((string)($_POST['title'] ?? ''));
        $image = trim((string)($_POST['image'] ?? ''));
        $expiresAt = (string)($_POST['expires_at'] ?? '');
        $priceText = str_replace(
            ',',
            '.',
            trim((string)($_POST['price'] ?? ''))
        );

        if ($title === '' || mb_strlen($title) > 255) {
            throw new InvalidArgumentException(
                'Skriv et produktnavn på højst 255 tegn.'
            );
        }

        if (!preg_match('/^\d{1,6}(?:\.\d{1,2})?$/', $priceText)) {
            throw new InvalidArgumentException(
                'Skriv en gyldig pris med højst to decimaler.'
            );
        }

        $price = (float)$priceText;

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $expiresAt);

        if (!$date || $date->format('Y-m-d') !== $expiresAt) {
            throw new InvalidArgumentException('Vælg en gyldig udløbsdato.');
        }

        if (strlen($image) > 255) {
            throw new InvalidArgumentException(
                'Billedstien må højst være 255 bytes.'
            );
        }

        $image = $image === '' ? null : $image;
        $agbCode = null;
        $recipe = [];
        $ingredients = '';

        if ($isMeal) {
            $codes = $_POST['ingredient_code'] ?? [];
            $weights = $_POST['ingredient_weight'] ?? [];

            if (!is_array($codes) || !is_array($weights) ||
                count($codes) !== count($weights) ||
                count($codes) > 50) {
                throw new InvalidArgumentException(
                    'Opskriften skal indeholde mellem 1 og 50 ingredienser.'
                );
            }

            $isAssumed = isset($_POST['is_assumed']) ? 1 : 0;

            foreach ($codes as $index => $code) {
                $code = trim((string)$code);
                $weightText = str_replace(
                    ',',
                    '.',
                    trim((string)($weights[$index] ?? ''))
                );

                /* Helt tomme rækker ignoreres. */
                if ($code === '' && $weightText === '') {
                    continue;
                }

                if (!isset($foods[$code])) {
                    throw new InvalidArgumentException(
                        'Vælg en fødevare fra listen for hver ingrediens.'
                    );
                }

                if (!preg_match(
                    '/^\d{1,6}(?:\.\d{1,2})?$/',
                    $weightText
                ) || (float)$weightText <= 0) {
                    throw new InvalidArgumentException(
                        'Alle ingredienser skal have en vægt over 0 gram.'
                    );
                }

                $recipe[] = [
                    'code' => $code,
                    'name' => $foods[$code]['product_name_en'],
                    'weight' => (float)$weightText
                ];
            }

            if (!$recipe) {
                throw new InvalidArgumentException(
                    'Tilføj mindst én ingrediens til retten.'
                );
            }

            $ingredients = implode(
                ', ',
                array_column($recipe, 'name')
            );
        } else {
            $agbCode = trim((string)($_POST['agb_code'] ?? ''));

            if (!isset($foods[$agbCode])) {
                throw new InvalidArgumentException(
                    'Vælg en matchende fødevare fra databasen.'
                );
            }

            $ingredients = trim(
                (string)($_POST['ingredients'] ?? '')
            );
        }

        $productId = $administration->create([
            'title' => $title, 'merchant' => $merchant, 'category' => $category,
            'ingredients' => $ingredients, 'image' => $image, 'price' => $price,
            'expires_at' => $expiresAt, 'agb_code' => $agbCode
        ], $recipe, (int)($isAssumed ?? 0));

        $_SESSION['created_product_id'] = $productId;

        header('Location: add_proudukt.php');
        exit;

    } catch (InvalidArgumentException $exception) {

        $error = $exception->getMessage();

    } catch (Throwable $exception) {

        (new SystemLogger())->error('products.creation_failed', ['type' => get_class($exception)]);

        $error = 'Produktet kunne ikke gemmes. Se PHP-fejlloggen.';
    }
}
?>
<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tilføj produkt – Flash Food</title>

    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px;
            background: #f4f7f4;
            color: #203529;
            font-family: Arial, sans-serif;
        }
        main {
            max-width: 850px;
            margin: auto;
            padding: 28px;
            background: white;
            border-radius: 16px;
        }
        h1 { margin-top: 0; }
        label {
            display: block;
            margin-top: 18px;
            font-weight: bold;
        }
        input, select, textarea, button {
            font: inherit;
        }
        input:not([type="checkbox"]), select, textarea {
            width: 100%;
            padding: 11px;
            margin-top: 7px;
            border: 1px solid #b9c7bd;
            border-radius: 8px;
        }
        fieldset {
            margin: 24px 0;
            padding: 18px;
            border: 1px solid #d1ddd4;
            border-radius: 10px;
        }
        legend { font-weight: bold; }
        button {
            padding: 11px 16px;
            border: 0;
            border-radius: 8px;
            cursor: pointer;
            background: #e8efe9;
        }
        .save {
            background: #246744;
            color: white;
            margin-top: 20px;
        }
        .ingredient {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 130px auto;
            align-items: end;
            gap: 10px;
            margin-bottom: 16px;
        }
        .ingredient label { margin-top: 0; }
        .message {
            padding: 14px;
            border-radius: 8px;
            margin: 18px 0;
        }
        .error { background: #ffe9e6; }
        .success { background: #e5f4e8; }
        .hint {
            color: #536459;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        [hidden] { display: none !important; }
        @media (max-width: 600px) {
            body { padding: 12px; }
            main { padding: 18px; }
            .ingredient { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<main>
    <h1>Tilføj produkt eller ret</h1>

    <p class="hint">
        Vælg den korrekte fødevare, herunder om den er rå eller tilberedt.
        Fødevarernes navne i databasen er på engelsk.
    </p>

    <?php if ($successId): ?>
        <div class="message success" role="status">
            Produktet og dets CO₂-kobling er gemt.
            <a href="product.php?id=<?= (int)$successId ?>">
                Se produktet
            </a>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="message error" role="alert">
            <?= esc($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" id="productForm">
        <input
            type="hidden"
            name="csrf"
            value="<?= esc($_SESSION['create_product_csrf']) ?>"
        >

        <label for="seller">Butik og kategori</label>
        <select name="seller" id="seller" required>
            <option value="">Vælg butik</option>

            <?php foreach ($sellers as $index => $seller): ?>
                <option
                    value="<?= $index ?>"
                    data-meal="<?= $seller['is_meal'] ? '1' : '0' ?>"
                >
                    <?= esc($seller['merchant']) ?>
                    — kategori <?= (int)$seller['category'] ?>
                    — <?= $seller['is_meal'] ? 'Restaurant' : 'Supermarked' ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="title">Produktnavn</label>
        <input
            id="title"
            name="title"
            maxlength="255"
            placeholder="Fx Organic Tomatoes 500 g"
            required
        >

        <p class="hint">
            For supermarkedsvarer: skriv pakkens vægt i navnet,
            fx 500 g eller 1 kg, så dit nuværende vægtfilter kan finde den.
        </p>

        <label for="price">Pris i kroner</label>
        <input
            id="price"
            name="price"
            type="number"
            min="0"
            max="999999.99"
            step="0.01"
            required
        >

        <label for="expires_at">Udløbsdato</label>
        <input id="expires_at" name="expires_at" type="date" required>

        <label for="image">Billedsti eller billedadresse – valgfri</label>
        <input
            id="image"
            name="image"
            maxlength="255"
            placeholder="Brug samme format som dine eksisterende produkter"
        >

        <fieldset id="groceryFields">
            <legend>Supermarkedsprodukt</legend>

            <label for="foodSearch">Søg i fødevarerne</label>
            <input
                id="foodSearch"
                type="search"
                placeholder="Skriv fx tomato, avocado eller yogurt"
            >

            <label for="agb_code">Matchende fødevare</label>
            <select id="agb_code" name="agb_code" required>
                <option value="">Vælg fødevare</option>

                <?php foreach ($foods as $food): ?>
                    <option value="<?= esc($food['agb_code']) ?>">
                        <?= esc($food['product_name_en']) ?>
                        — <?= esc($food['co2e_kg_per_kg']) ?> kg CO₂e/kg
                        — <?= esc($food['agb_code']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="ingredients">Ingrediensliste – hvis relevant</label>
            <textarea
                id="ingredients"
                name="ingredients"
                rows="3"
            ></textarea>
        </fieldset>

        <fieldset id="mealFields" hidden disabled>
            <legend>Restaurantrettens ingredienser</legend>

            <p class="hint">
                Indtast mængder for én portion.
                For en boks skal mængderne gælde hele boksen,
                og produktnavnet skal indeholde “Box”.
            </p>

            <p class="hint">
                Søg efter en ingrediens, og vælg den i listen.
                Feltet skal ende med fødevarens kode.
            </p>

            <div id="ingredientRows"></div>

            <button type="button" id="addIngredient">
                + Tilføj ingrediens
            </button>

            <label>
                <input type="checkbox" name="is_assumed" value="1" checked>
                Dette er en eksempelopskrift med antagne mængder
            </label>

            <p class="hint">
                Fjern kun markeringen, hvis ingredienser og vægte
                svarer til den faktiske opskrift.
            </p>
        </fieldset>

        <button type="submit" class="save">
            Gem produkt med CO₂-kobling
        </button>
    </form>

    <datalist id="foodOptions">
        <?php foreach ($foods as $food): ?>
            <option value="<?= esc($food['agb_code']) ?>">
                <?= esc($food['product_name_en']) ?>
                — <?= esc($food['co2e_kg_per_kg']) ?> kg CO₂e/kg
            </option>
        <?php endforeach; ?>
    </datalist>

    <template id="ingredientTemplate">
        <div class="ingredient">
            <label>
                Fødevare
                <input
                    name="ingredient_code[]"
                    list="foodOptions"
                    placeholder="Søg fx rice"
                    autocomplete="off"
                    required
                >
            </label>

            <label>
                Gram
                <input
                    name="ingredient_weight[]"
                    type="number"
                    min="0.01"
                    max="999999.99"
                    step="0.01"
                    required
                >
            </label>

            <button type="button" class="removeIngredient">
                Fjern
            </button>
        </div>
    </template>
</main>

<script>
const seller = document.getElementById('seller');
const groceryFields = document.getElementById('groceryFields');
const mealFields = document.getElementById('mealFields');
const rows = document.getElementById('ingredientRows');
const template = document.getElementById('ingredientTemplate');
const foodSelect = document.getElementById('agb_code');
const foodSearch = document.getElementById('foodSearch');

const foodChoices = Array.from(foodSelect.options)
    .filter(option => option.value !== '')
    .map(option => ({
        value: option.value,
        text: option.textContent.trim()
    }));

function updateProductType() {
    const selected = seller.options[seller.selectedIndex];
    const isMeal = selected?.dataset.meal === '1';

    groceryFields.hidden = isMeal;
    groceryFields.disabled = isMeal;

    mealFields.hidden = !isMeal;
    mealFields.disabled = !isMeal;
}

function addIngredient() {
    if (rows.children.length >= 50) {
        return;
    }

    const fragment = template.content.cloneNode(true);

    fragment.querySelector('.removeIngredient')
        .addEventListener('click', event => {
            event.currentTarget.closest('.ingredient').remove();
        });

    rows.appendChild(fragment);
}

foodSearch.addEventListener('input', () => {
    const query = foodSearch.value.trim().toLowerCase();
    const previousValue = foodSelect.value;

    const matches = foodChoices.filter(food =>
        food.text.toLowerCase().includes(query)
    );

    foodSelect.replaceChildren(new Option('Vælg fødevare', ''));

    for (const food of matches) {
        foodSelect.add(new Option(food.text, food.value));
    }

    if (matches.some(food => food.value === previousValue)) {
        foodSelect.value = previousValue;
    }
});

seller.addEventListener('change', updateProductType);

document.getElementById('addIngredient')
    .addEventListener('click', addIngredient);

addIngredient();
updateProductType();
</script>
</body>
</html>