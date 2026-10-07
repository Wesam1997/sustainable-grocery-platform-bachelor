# Gennemgang af First-Pro-1(7).zip

## Rettet i denne kopi

- Rettet 22 komponentfilnavne under src, hvor kopinumre som `(5)` eller mellemrum før `.php` forhindrede autoload. Desuden normaliseret 18 gamle interfacekopier i proudket.
- MysqliProductRepository kaldte escapedList med en mysqli-forbindelse som første argument, selv om metoden kun tager en array. Rettet begge kald for Market og Restaurant.
- Rettet Home.js-stiens store/lille bogstav. Samlet Home-controlleren i Js/Home.js, så inline-koden og den eksterne fil ikke initialiserer de samme sliders to gange.
- Produktets Add to cart-formular anvender nu add-to-cart-handleren. Før blev JSON-svaret vist som en ny side.
- Produktets favoritknap er nu forbundet til AddFav.php via den eksisterende formularhandler.
- Produktdetaljer accepterer nu per box, som RecipeClimateCalculator kan returnere.
- Klimafilterets forklaringer viser nu de samme grænser som beregneren: op til 2, over 2 og op til 5, samt over 5 kg CO₂e/kg. Teksten markerer dem som prototypekategorier. Klassifikation af måltider bruger per kg, selv om den viste værdi kan være per serving eller per box.
- Tilføjet Api/logout.php. Log ud kræver POST og et gyldigt CSRF-token og afslutter sessionen.
- Rettet linket produkt.php til product.php på administrationssiden og eksisterende navigation fra profilsider til Home, Cart og Wishlist.
- Rettet telefonfeltets ugyldige input-type til tel.

## Stadig ufærdigt

- profil/redigerPersonligOplysninger.html sender til # med GET og har ingen backend, som gemmer ændringer.
- profil/skift-adgangskode.html og profil/adressebog.html mangler.
- profil/minReturn.php er en statisk prototypeside. Links til kobt.php og nuvaerende.php mangler mål, og returhandlinger har ingen tilkoblet backend.
- LlmClientInterface findes, men ingen konkret modelklient implementerer det i denne ZIP. Application bruger som standard null, og ExplanationService returnerer derfor standardteksten. ComparisonEngine beregner en sammenligning, men dens forklaring vises ikke i den nuværende produktvisning. Adaptive hover-visning er heller ikke implementeret.
- proudket indeholder gamle kopier af interfaces. Autoload bruger src, så disse kopier er ikke aktive. Ved manuel indlæsning af begge kopier kan samme interface blive deklareret to gange.
- Konfigurationen indeholder aktuelt Sakura som restaurant og ØkoHaven som marked. Flere butikker kræver en opdatering af disse konfigurationslister.

## Kontroller

- 87 PHP-filer: syntaks OK.
- 7 JavaScript-filer: syntaks OK.
- Alle komponentklasser og interfaces indlæses; Application kan konstrueres uden databaseforespørgsler.
- Market-, Restaurant- og All-filterets SQL-generering testet med en testadapter uden en rigtig database.
- Beregningsgrænser, opskriftens per-kg-normalisering, per-box-værdi og manglende klimadata kontrolleret.
- Produktvisningens per-box-værdi og håndtering af forkert produkt-ID og manglende data kontrolleret med DOM-testobjekter.
- Logout: GET giver 405, forkert token giver 403, gyldigt token afslutter sessionen og giver 303.

## Database og lokal afprøvning

Forbindelsen bruger som standard localhost, root og databasen flashfoodcart. DatabaseConnection opretter en forbindelse; den opretter ikke en ny database.

Din lokale XAMPP-database er ikke tilgængelig her, og ZIP-filen indeholder ikke en eksport af hele databasens skema. Derfor er login, produktforespørgsler og den fulde købsoplevelse ikke testet mod dine rigtige data eller i en browser.

Koden forventer bl.a. proudkt, environmental_food_data, product_recipe_ingredients, users og auth_rate_limits. Produktforespørgslen forventer også environmental_food_data.data_quality_dqr. Det er ikke kontrolleret, om tabeller og kolonner findes i din database.

Udpak kopien i en ny mappe under XAMPP/htdocs. Start Apache og MySQL, og åbn Home/home.php gennem localhost. Afprøv Market, Restaurant, et produkt, favorit, kurv, login og logout.

Database/001_climate_equivalence_reference.sql er en valgfri migration til et sammenligningstabel i din eksisterende database. Den er kun nødvendig, hvis FLASHFOOD_COMPARISON_SOURCE er sat til database. Ellers læses sammenligninger fra src/Config/comparisons.php. Bilfaktoren 0.17 er en markeret prototypeværdi med source=null; den er ikke kildeverificeret.
