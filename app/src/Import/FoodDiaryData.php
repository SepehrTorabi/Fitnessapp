<?php

declare(strict_types=1);

namespace App\Import;

/**
 * The contents of "Essenstagebuch_Sepehr_Cosima.pdf" (17.08.2026 - 14.09.2026).
 *
 * Transcribed literally: every kcal, fat and protein figure below is exactly
 * what the PDF prints for that line. Nothing is recomputed on the way in, which
 * is what lets {@see FoodDiaryImporter} check each imported day against the
 * "Summe" line the PDF states for it.
 *
 * Two things the PDF does not contain and the schema needs:
 *
 *  - Carbohydrate. The tables have kcal, Fett and Eiweiß only. The importer
 *    derives carbs with the Atwater factors, which is the one value consistent
 *    with the three that are given.
 *
 *  - Gram weights for anything counted rather than weighed ("1 Scheibe", "1 EL").
 *    Those live in UNIT_WEIGHTS below.
 */
final class FoodDiaryData
{
    /**
     * What one unit of a counted food weighs, and what that unit is called.
     *
     * A food listed here in grams ('g') is weighed in the PDF and needs no
     * estimate at all - the quantity in DAYS is already grams.
     *
     * For the counted ones the weight matters only for the per-100 g display and
     * the gram total; the calories and macros of a logged portion come out
     * exactly as printed either way, because the per-100 g values are derived
     * from the same weight.
     *
     * Several are exact rather than estimated, because the PDF happens to state
     * the same food both ways - those are marked "derived".
     *
     * @var array<string, array{0: string, 1: float}>
     */
    public const array UNIT_WEIGHTS = [
        // --- Bakery ---
        'Körnerbrötchen' => ['Stück', 85.0],
        'Brötchen mit Kürbiskernen' => ['Stück', 85.0],
        'Vollkornbrötchen' => ['Stück', 80.0],
        'Brötchen' => ['Stück', 80.0],
        'Rosenbrötchen (Aldi Nord)' => ['Stück', 50.0],
        'Laugenecke mit Kernen' => ['Stück', 75.0],
        'Laugenecke' => ['Stück', 75.0],
        'Walnussbrot' => ['Scheibe', 45.0],
        'Burger Bun (Gut & Günstig)' => ['Stück', 76.0],
        'Burger Bun (Edeka)' => ['Stück', 76.0],
        'Barbari (persisches Brot)' => ['g', 1.0],

        // --- Cheese and spreads ---
        'Milram Butterkäse' => ['Scheibe', 20.0],
        'Amsterdam Käse' => ['Scheibe', 21.0],
        'Amsterdam Gouda' => ['Scheibe', 21.0],
        'Gouda' => ['Scheibe', 20.0],
        'Holländischer Trüffelgouda' => ['g', 1.0],
        // derived: 1 EL = 81 kcal and 1 TL = 27 kcal give the same 540 kcal/100 g
        // at 15 g and 5 g respectively.
        'Vegane Streichcreme „Toskana" (Edeka)' => ['EL', 15.0],
        'Vegane Streichcreme „Toskana" (Penny)' => ['EL', 15.0],
        'Vegane Streichcreme „Toskana" (dm)' => ['EL', 15.0],
        // derived: "ca. 30 g" = 84 kcal, 1 EL = 42 kcal, 1 TL = 14 kcal.
        'Vegane Curry-Streichcreme (Penny)' => ['g', 1.0],
        'Parmesan (Edeka)' => ['EL', 15.0],
        '3-Zwiebel-Quark (Milram)' => ['EL', 15.0],

        // --- Sausage ---
        // derived: 100 g = 282 kcal and one slice = 9 kcal give 3.2 g a slice.
        'Vegane Salami (Billie Green)' => ['Scheibe', 3.2],
        'Vegane Salami' => ['Scheibe', 6.2],
        'Salami' => ['Scheibe', 10.0],
        'Putenwürstchen' => ['Stück', 30.0],

        // --- Meat ---
        'Hähnchen-Filetspieße Paprika (Aldi)' => ['Packung', 400.0],
        'Lammsteak (Aldi)' => ['Packung', 200.0],
        'Hähnchen-Hacksteak (Aldi Nord)' => ['Stück', 105.0],
        // derived: "1/2 Packung" and "250 g" are both 512.5 kcal.
        'Chicken Crunchies (Edeka)' => ['g', 1.0],
        'Chicken Burger Patty (Edeka)' => ['Stück', 100.0],
        'Köttbullar (Edeka)' => ['Packung', 440.0],
        'Hähnchenspieß mit Tomaten-Joghurt-Marinade (Penny)' => ['g', 1.0],
        'Hähnchenspieß mariniert in Tomaten-Joghurt' => ['g', 1.0],
        'Hähnchenbrust in Paprikamarinade (Penny)' => ['g', 1.0],
        'Hähnchenbrust mit Kebabmarinade (Penny)' => ['g', 1.0],
        'Hähnchengeschnetzeltes, gebraten (ohne Öl)' => ['g', 1.0],
        'Hähnchen mit Marinade aus getrockneten Tomaten & Parmesan' => ['g', 1.0],
        'Hähnchenbrust gefüllt (Amsterdam Gouda, getrocknete Tomaten & Parmesan)' => ['g', 1.0],
        'Hackfleischsoße mit Tomatenmark & Erbsen (ohne Öl)' => ['g', 1.0],
        'Lauch-Frischkäse-Sauce mit Hackfleisch' => ['g', 1.0],
        'Geflügelrolle (Backwerk)' => ['Stück', 140.0],

        // --- Pasta, potatoes, dough ---
        'Eiernudeln (Spaichinger Nudelmacher Hörnchen)' => ['g', 1.0],
        'Eiernudeln mit Pesto Pomodori e Aglio (Edeka Herzstücke)' => ['g', 1.0],
        'Spaghetti mit Pesto Genovese (Edeka Herzstücke)' => ['g', 1.0],
        'Spaghetti mit 1 EL Pesto Genovese (Edeka)' => ['g', 1.0],
        'Kartoffelecke' => ['Stück', 40.0],
        'Käse-Tortellini (Frosta)' => ['g', 1.0],
        'Hähnchenpfanne mit Penne & Gemüse (Frosta)' => ['g', 1.0],
        'Lubia Polo (persisches Gericht)' => ['g', 1.0],
        'Börekstange mit Kartoffel' => ['Stück', 36.0],
        'Veganer Falafel-Wrap' => ['Stück', 200.0],
        'Proteinwrap (Aldi)' => ['Stück', 46.0],
        'Lahmacun (Imbiss) mit Extra Fleisch' => ['Stück', 350.0],

        // --- Vegetables ---
        'Bratgemüse (Aubergine, Zucchini, Gemüsezwiebel, Paprika, Öl)' => ['g', 1.0],
        'Gebackenes Gemüse (Zucchini, Paprika, Zwiebel, Möhren, Aubergine, Öl)' => ['g', 1.0],
        'Gebratenes Gemüse, halbe Portion (2 Zucchini, 2 Auberginen, 2 Paprika, 1 Zwiebel, Olivenöl)' => ['Portion', 700.0],
        'Gebratenes Gemüse, halbe Portion (Zucchini, Aubergine, Paprika, Zwiebel, Möhren, Öl)' => ['Portion', 650.0],
        'Grillgemüse, halbe Portion (Paprika, Aubergine, Zucchini, Gemüsezwiebel, Olivenöl)' => ['Portion', 600.0],
        'Gurke' => ['Stück', 380.0],
        'Salat' => ['g', 1.0],
        'Kleiner Fertigsalat mit Käse (Penny)' => ['g', 1.0],
        'Gebratener Brokkoli' => ['g', 1.0],

        // --- Dips ---
        'Tzatziki (Edeka)' => ['g', 1.0],
        'Tzatziki (Penny)' => ['g', 1.0],
        'Tzatziki' => ['g', 1.0],
        'Milram Tzatziki' => ['g', 1.0],

        // --- Quiche ---
        'Käse-Lauch-Speck-Quiche (Picnic Rezept)' => ['g', 1.0],
        'Speck-Lauch-Quiche (Picnic Rezept)' => ['g', 1.0],

        // --- Sweet and snacks ---
        'Selbstgemachter Zupfkuchen' => ['Stück', 113.0],
        // derived: "1 Stück" and "150 g" are both 268 kcal.
        'Protein-Käsekuchen (Eigenrezept)' => ['g', 1.0],
        'Kleines Stück Brownie' => ['Stück', 40.0],
        'Dattel' => ['Stück', 8.0],
        'Walnuss' => ['Stück', 4.0],
        'Nabat Stäbchen (persischer Kandiszucker)' => ['Stück', 12.0],
        'Cheetos' => ['g', 1.0],
        'Erdbeermarmelade (Bonne Maman)' => ['EL', 15.0],
        // derived: 1 TL = 8 kcal and 50 g = 80 kcal give 5 g a teaspoon.
        'Teriyaki BBQ (Hello Taste)' => ['g', 1.0],

        // --- Fruit ---
        'Rote Trauben' => ['g', 1.0],
        'Kleine Birne' => ['Stück', 120.0],
        'Birne' => ['Stück', 180.0],
        'Banane' => ['Stück', 118.0],

        // --- Drinks, shakes, bars ---
        'Mandelmilch (Penny)' => ['g', 1.0],
        'Kleine Apfelschorle' => ['Stück', 230.0],
        // derived: the bottle is 0,75 l.
        'Truefruits Pink (0,75 l)' => ['Flasche', 750.0],
        'Eiweißshake' => ['Portion', 300.0],
        'Protein Shake (Fitnessstudio)' => ['Portion', 300.0],
        'Proteinriegel' => ['Stück', 55.0],
        'Schoko-Protein-Pulver' => ['g', 1.0],
        'Milchshake (Honig, Kakaopulver, Haselnussmus, Banane, Mandelmilch)' => ['Portion', 765.0],
        'Milchshake (Mandelmilch, Banane, Whey Protein Salted Caramel)' => ['Portion', 670.0],
        'Quarkspeise selbstgemacht (Magerquark, griech. Joghurt, Haselnussmus, Honig, Kakaopulver)' => ['Portion', 524.0],

        // --- Eggs ---
        'Eier als Rührei (ohne Öl)' => ['Ei', 50.0],
        'Eier als Rührei' => ['Ei', 60.0],

        // --- Pizza (each night's recipe differs, so each is its own record) ---
        'Pizza (350 g Pizzamehl, 2 Pack. Geflügelsalami, 2 Pack. Butterkäse, Tomatensoße Basilikum, 2 Paprika, 2 Zwiebeln)' => ['Portion', 1800.0],
        'Pizza (100 g Pizzamehl Tipo 00, 175 g Tomatensauce Basilikum, 150 g Maasdamer light, Hähnchen Kebab, 1/2 Paprika, 1/2 Zwiebel)' => ['Portion', 800.0],
        'Pizza (100 g Pizzamehl Tipo 00, 175 g Tomatensauce Basilikum, 150 g Maasdamer light, Hähnchen Kebab, 1/2 Paprika, 1/2 Zwiebel, Champignons)' => ['Portion', 950.0],
        'Pizza (150 g Pizzamehl, 200 g Gouda gerieben, 1/2 Paprika, 1/2 Gemüsezwiebel, Geflügelsalami Gut&Günstig, 175 g Pastasoße Basilikum)' => ['Portion', 900.0],
    ];

    /**
     * Every day in the PDF, in its order.
     *
     * Each item is [food name, quantity, kcal, fat g, protein g] and an optional
     * sixth element 'g' forcing the quantity to be read as grams - needed where
     * the PDF gives a food by weight on one day and by the piece on another.
     *
     * 'sum' is the "Summe" line the PDF prints for that person that day. The
     * importer recomputes it from the items and refuses to write anything if the
     * two disagree, which turns a transcription slip into a failed import rather
     * than into wrong data.
     *
     * 'burned' is the "Verbrauch" line, or null on the days that have none.
     *
     * @var array<string, array<string, mixed>>
     */
    public const array DAYS = [
        '2026-09-14' => [
            'cosima' => [
                'burned' => null,
                'sum' => [2445.3, 104.8, 124.6],
                'items' => [
                    ['Eier als Rührei (ohne Öl)', 2.5, 175, 12, 15.8],
                    ['Körnerbrötchen', 1, 230, 5.1, 8.5],
                    ['Vegane Salami (Billie Green)', 6, 54, 2.4, 6],
                    ['Milram Butterkäse', 1, 70, 6.3, 4],
                    ['Vegane Streichcreme „Toskana" (Edeka)', 1, 81, 4.7, 0.9],
                    ['Pizza (350 g Pizzamehl, 2 Pack. Geflügelsalami, 2 Pack. Butterkäse, Tomatensoße Basilikum, 2 Paprika, 2 Zwiebeln)', 1, 1640.3, 73.7, 87.6],
                    ['Rote Trauben', 300, 195, 0.6, 1.8],
                ],
            ],
            'sepehr' => [
                'burned' => null,
                'sum' => [2445.3, 104.8, 124.6],
                'items' => [
                    ['Eier als Rührei (ohne Öl)', 2.5, 175, 12, 15.8],
                    ['Körnerbrötchen', 1, 230, 5.1, 8.5],
                    ['Vegane Salami (Billie Green)', 6, 54, 2.4, 6],
                    ['Milram Butterkäse', 1, 70, 6.3, 4],
                    ['Vegane Streichcreme „Toskana" (Edeka)', 1, 81, 4.7, 0.9],
                    ['Pizza (350 g Pizzamehl, 2 Pack. Geflügelsalami, 2 Pack. Butterkäse, Tomatensoße Basilikum, 2 Paprika, 2 Zwiebeln)', 1, 1640.3, 73.7, 87.6],
                    ['Rote Trauben', 300, 195, 0.6, 1.8],
                ],
            ],
        ],

        '2026-09-10' => [
            'cosima' => [
                'burned' => ['3.000 Schritte', 135],
                'sum' => [1515, 62, 163.8],
                'items' => [
                    ['Protein-Käsekuchen (Eigenrezept)', 150, 268, 8.7, 34.1],
                    ['Hähnchen-Filetspieße Paprika (Aldi)', 1, 552, 22.8, 82.4],
                    ['Chicken Crunchies (Edeka)', 250, 512.5, 20, 42.5],
                    ['Tzatziki (Penny)', 150, 127.5, 10.5, 4.5],
                    ['Kleine Apfelschorle', 1, 55, 0, 0.3],
                ],
            ],
            'sepehr' => [
                'burned' => null,
                'sum' => [1850, 78.5, 222],
                'items' => [
                    ['Protein-Käsekuchen (Eigenrezept)', 150, 268, 8.7, 34.1],
                    ['Hähnchen-Filetspieße Paprika (Aldi)', 1, 552, 22.8, 82.4],
                    ['Chicken Crunchies (Edeka)', 250, 512.5, 20, 42.5],
                    ['Tzatziki (Penny)', 150, 127.5, 10.5, 4.5],
                    ['Lammsteak (Aldi)', 1, 390, 16.5, 58.5],
                ],
            ],
        ],

        '2026-09-08' => [
            'cosima' => [
                'burned' => ['4.620 Schritte', 208],
                'sum' => [1427.7, 50.9, 108.5],
                'items' => [
                    ['Rosenbrötchen (Aldi Nord)', 1, 135, 1, 4],
                    ['Vegane Salami (Billie Green)', 5, 45, 2, 5],
                    ['Vegane Streichcreme „Toskana" (Edeka)', 1, 81, 4.7, 0.9],
                    ['Milram Butterkäse', 1, 70, 6.3, 4],
                    ['Lauch-Frischkäse-Sauce mit Hackfleisch', 250, 253.3, 9.8, 32.4],
                    ['Eiernudeln (Spaichinger Nudelmacher Hörnchen)', 250, 360, 1.5, 11],
                    ['Hähnchen-Hacksteak (Aldi Nord)', 2.5, 450, 25, 50],
                    ['Bratgemüse (Aubergine, Zucchini, Gemüsezwiebel, Paprika, Öl)', 100, 33.4, 0.6, 1.2],
                ],
            ],
            'sepehr' => [
                'burned' => ['4.620 Schritte', 208],
                'sum' => [1331.7, 51.4, 104.6],
                'items' => [
                    ['Rosenbrötchen (Aldi Nord)', 1, 135, 1, 4],
                    ['Vegane Salami (Billie Green)', 5, 45, 2, 5],
                    ['Vegane Streichcreme „Toskana" (Edeka)', 1, 81, 4.7, 0.9],
                    ['Milram Butterkäse', 1, 70, 6.3, 4],
                    ['Lauch-Frischkäse-Sauce mit Hackfleisch', 100, 101.3, 3.9, 12.9],
                    ['Eiernudeln (Spaichinger Nudelmacher Hörnchen)', 150, 216, 0.9, 6.6],
                    ['Hähnchen-Hacksteak (Aldi Nord)', 2.5, 450, 25, 50],
                    ['Bratgemüse (Aubergine, Zucchini, Gemüsezwiebel, Paprika, Öl)', 100, 33.4, 0.6, 1.2],
                    ['Proteinriegel', 1, 200, 7, 20],
                ],
            ],
        ],

        '2026-09-06' => [
            'cosima' => [
                'burned' => ['15.000 Schritte', 675],
                'sum' => [2727.5, 97.7, 201.6],
                'items' => [
                    ['Pizza (100 g Pizzamehl Tipo 00, 175 g Tomatensauce Basilikum, 150 g Maasdamer light, Hähnchen Kebab, 1/2 Paprika, 1/2 Zwiebel)', 1, 1572, 56.3, 138.4],
                    ['Brötchen', 1, 217, 4.5, 8],
                    ['Proteinwrap (Aldi)', 1, 138, 4.2, 15],
                    ['Vegane Salami (Billie Green)', 100, 282, 12, 32, 'g'],
                    ['Vegane Streichcreme „Toskana" (Edeka)', 3, 243, 14.1, 2.7],
                    ['Milram Butterkäse', 1, 70, 6.3, 4],
                    ['Kleine Birne', 3, 205.5, 0.3, 1.5],
                ],
            ],
            'sepehr' => [
                'burned' => ['15.000 Schritte', 675],
                'sum' => [2993.5, 116.6, 222],
                'items' => [
                    ['Pizza (100 g Pizzamehl Tipo 00, 175 g Tomatensauce Basilikum, 150 g Maasdamer light, Hähnchen Kebab, 1/2 Paprika, 1/2 Zwiebel, Champignons)', 1, 1616, 56.9, 144.4],
                    ['Brötchen', 1, 217, 4.5, 8],
                    ['Proteinwrap (Aldi)', 1, 138, 4.2, 15],
                    ['Vegane Salami (Billie Green)', 100, 282, 12, 32, 'g'],
                    ['Vegane Streichcreme „Toskana" (Edeka)', 3, 243, 14.1, 2.7],
                    ['Amsterdam Käse', 2, 152, 12, 10.4],
                    ['Milram Butterkäse', 2, 140, 12.6, 8],
                    ['Kleine Birne', 3, 205.5, 0.3, 1.5],
                ],
            ],
        ],

        '2026-09-05' => [
            'cosima' => [
                'burned' => ['10.000 Schritte', 450],
                'sum' => [2076.2, 84.7, 115.9],
                'items' => [
                    ['Barbari (persisches Brot)', 80, 212, 1.2, 6.4],
                    ['Salami', 5, 200, 17.5, 10],
                    ['Vegane Streichcreme „Toskana" (Edeka)', 1, 81, 4.7, 0.9],
                    ['Milram Butterkäse', 1, 70, 6.3, 4],
                    ['Nabat Stäbchen (persischer Kandiszucker)', 1, 47, 0, 0],
                    ['Lahmacun (Imbiss) mit Extra Fleisch', 1, 600, 28, 32],
                    ['Quarkspeise selbstgemacht (Magerquark, griech. Joghurt, Haselnussmus, Honig, Kakaopulver)', 1, 548.5, 16.3, 54.7],
                    ['Gurke', 1, 46, 0.4, 2],
                    ['Eiernudeln mit Pesto Pomodori e Aglio (Edeka Herzstücke)', 150, 271.7, 10.3, 5.9],
                ],
            ],
            'sepehr' => [
                'burned' => ['10.000 Schritte', 450],
                'sum' => [1892.1, 80.5, 115.8],
                'items' => [
                    ['Barbari (persisches Brot)', 80, 212, 1.2, 6.4],
                    ['Salami', 5, 200, 17.5, 10],
                    ['Vegane Streichcreme „Toskana" (Edeka)', 1, 81, 4.7, 0.9],
                    ['Amsterdam Gouda', 1.5, 114, 9, 7.8],
                    ['Lahmacun (Imbiss) mit Extra Fleisch', 1, 600, 28, 32],
                    ['Quarkspeise selbstgemacht (Magerquark, griech. Joghurt, Haselnussmus, Honig, Kakaopulver)', 1, 548.5, 16.3, 54.7],
                    ['Gurke', 1, 46, 0.4, 2],
                    ['Eiernudeln mit Pesto Pomodori e Aglio (Edeka Herzstücke)', 50, 90.6, 3.4, 2],
                ],
            ],
        ],

        '2026-09-04' => [
            'cosima' => [
                'burned' => ['13.774 Schritte', 620],
                'sum' => [2167.5, 92, 85.7],
                'items' => [
                    ['Laugenecke mit Kernen', 1, 224, 5.6, 8],
                    ['Laugenecke', 1, 216, 3.2, 6.4],
                    ['Börekstange mit Kartoffel', 3, 300, 14.4, 6],
                    ['Veganer Falafel-Wrap', 1, 460, 20, 16],
                    ['Kleines Stück Brownie', 1, 180, 10, 2.4],
                    ['Chicken Burger Patty (Edeka)', 2, 406, 20.8, 31],
                    ['Burger Bun (Gut & Günstig)', 1, 214, 4.1, 6.4],
                    ['Milram Butterkäse', 2, 140, 12.6, 8],
                    ['3-Zwiebel-Quark (Milram)', 1, 19.5, 1.2, 1.4],
                    ['Teriyaki BBQ (Hello Taste)', 5, 8, 0.1, 0.1],
                ],
            ],
            'sepehr' => [
                'burned' => null,
                'sum' => [1724, 70.6, 150.9],
                'items' => [
                    ['Vollkornbrötchen', 1, 217, 4.5, 8],
                    ['Amsterdam Käse', 1, 76, 6, 5.2],
                    ['Vegane Salami (Billie Green)', 7, 63, 2.8, 7],
                    ['Vegane Streichcreme „Toskana" (Edeka)', 1, 81, 4.7, 0.9],
                    ['Hähnchen mit Marinade aus getrockneten Tomaten & Parmesan', 250, 487.5, 14.4, 80.5],
                    ['Chicken Burger Patty (Edeka)', 2, 406, 20.8, 31],
                    ['Burger Bun (Gut & Günstig)', 1, 214, 4.1, 6.4],
                    ['Amsterdam Gouda', 2, 152, 12, 10.4],
                    ['3-Zwiebel-Quark (Milram)', 1, 19.5, 1.2, 1.4],
                    ['Teriyaki BBQ (Hello Taste)', 5, 8, 0.1, 0.1],
                ],
            ],
        ],

        '2026-09-02' => [
            'cosima' => [
                'burned' => ['8.676 Schritte', 390],
                'sum' => [1818.4, 62.6, 85.7],
                'items' => [
                    ['Geflügelrolle (Backwerk)', 1, 420, 18, 22.5],
                    ['Milchshake (Honig, Kakaopulver, Haselnussmus, Banane, Mandelmilch)', 1, 659.9, 20.4, 12.8],
                    ['Chicken Crunchies (Edeka)', 250, 512.5, 20, 42.5],
                    ['Gebratenes Gemüse, halbe Portion (2 Zucchini, 2 Auberginen, 2 Paprika, 1 Zwiebel, Olivenöl)', 1, 226, 4.2, 7.9],
                ],
            ],
            'sepehr' => [
                'burned' => null,
                'sum' => [2439.5, 98.4, 220.1],
                'items' => [
                    ['Brötchen mit Kürbiskernen', 1, 230, 5.1, 8.5],
                    ['Vegane Salami (Billie Green)', 8, 72, 3.2, 8],
                    ['Milram Butterkäse', 1, 70, 6.3, 4],
                    ['Vegane Streichcreme „Toskana" (Penny)', 1, 81, 4.7, 0.9],
                    ['Milchshake (Mandelmilch, Banane, Whey Protein Salted Caramel)', 1, 380.5, 10.5, 41.8],
                    ['Hähnchenbrust gefüllt (Amsterdam Gouda, getrocknete Tomaten & Parmesan)', 250, 867.5, 44.4, 106.5],
                    ['Chicken Crunchies (Edeka)', 250, 512.5, 20, 42.5],
                    ['Gebratenes Gemüse, halbe Portion (2 Zucchini, 2 Auberginen, 2 Paprika, 1 Zwiebel, Olivenöl)', 1, 226, 4.2, 7.9],
                ],
            ],
        ],
        '2026-08-31' => [
            'cosima' => [
                'burned' => ['Training', 1057],
                'sum' => [1801, 53.1, 105.6],
                'items' => [
                    ['Vollkornbrötchen', 1, 217, 4.5, 8],
                    ['Vegane Salami (Billie Green)', 3, 27, 1.2, 3],
                    ['Milram Butterkäse', 0.5, 35, 3.2, 2],
                    ['Vegane Streichcreme „Toskana" (Penny)', 5, 27, 1.6, 0.3, 'g'],
                    ['Chicken Crunchies (Edeka)', 250, 512.5, 20, 42.5],
                    ['Gebratenes Gemüse, halbe Portion (Zucchini, Aubergine, Paprika, Zwiebel, Möhren, Öl)', 1, 190.5, 2.8, 6.6],
                    ['Eiernudeln (Spaichinger Nudelmacher Hörnchen)', 300, 432, 1.8, 13.2],
                    ['Hackfleischsoße mit Tomatenmark & Erbsen (ohne Öl)', 300, 360, 18, 30],
                ],
            ],
            'sepehr' => [
                'burned' => ['Training', 1020],
                'sum' => [1967, 66.2, 142.6],
                'items' => [
                    ['Vollkornbrötchen', 1, 217, 4.5, 8],
                    ['Vegane Salami (Billie Green)', 3, 27, 1.2, 3],
                    ['Milram Butterkäse', 0.5, 35, 3.2, 2],
                    ['Vegane Streichcreme „Toskana" (Penny)', 5, 27, 1.6, 0.3, 'g'],
                    ['Chicken Crunchies (Edeka)', 250, 512.5, 20, 42.5],
                    ['Gebratenes Gemüse, halbe Portion (Zucchini, Aubergine, Paprika, Zwiebel, Möhren, Öl)', 1, 190.5, 2.8, 6.6],
                    ['Eiernudeln (Spaichinger Nudelmacher Hörnchen)', 200, 288, 1.2, 8.8],
                    ['Hackfleischsoße mit Tomatenmark & Erbsen (ohne Öl)', 350, 420, 21, 35],
                    ['Parmesan (Edeka)', 3, 120, 8.7, 11.4],
                    ['Eiweißshake', 1, 130, 2, 25],
                ],
            ],
        ],

        '2026-08-29' => [
            'cosima' => [
                'burned' => ['Training', 1150],
                'sum' => [2131.5, 90.4, 120.6],
                'items' => [
                    ['Selbstgemachter Zupfkuchen', 1.5, 594, 32.4, 10.8],
                    ['Pizza (150 g Pizzamehl, 200 g Gouda gerieben, 1/2 Paprika, 1/2 Gemüsezwiebel, Geflügelsalami Gut&Günstig, 175 g Pastasoße Basilikum)', 1, 1514.5, 57.8, 108.8],
                    ['Gurke', 0.5, 23, 0.2, 1],
                ],
            ],
            'sepehr' => [
                'burned' => ['Training', 960],
                'sum' => [2261.5, 92.4, 145.6],
                'items' => [
                    ['Selbstgemachter Zupfkuchen', 1.5, 594, 32.4, 10.8],
                    ['Pizza (150 g Pizzamehl, 200 g Gouda gerieben, 1/2 Paprika, 1/2 Gemüsezwiebel, Geflügelsalami Gut&Günstig, 175 g Pastasoße Basilikum)', 1, 1514.5, 57.8, 108.8],
                    ['Gurke', 0.5, 23, 0.2, 1],
                    ['Eiweißshake', 1, 130, 2, 25],
                ],
            ],
        ],

        '2026-08-28' => [
            'cosima' => [
                'burned' => ['11.000 Schritte', 500],
                'sum' => [2331.8, 106.3, 116.1],
                'items' => [
                    ['Protein-Käsekuchen (Eigenrezept)', 150, 268, 8.7, 34.1],
                    ['Vollkornbrötchen', 1.5, 325, 6.8, 12],
                    ['Vegane Salami (Billie Green)', 6, 54, 2.4, 6],
                    ['Vegane Streichcreme „Toskana" (Penny)', 10, 54, 3.1, 0.6, 'g'],
                    ['Milram Butterkäse', 0.5, 35, 3.2, 2],
                    ['Gurke', 0.5, 23, 0.2, 1],
                    ['Selbstgemachter Zupfkuchen', 2, 792, 43.2, 14.4],
                    ['Burger Bun (Edeka)', 1, 214, 4.1, 6.4],
                    ['Chicken Burger Patty (Edeka)', 2, 406, 20.8, 31],
                    ['Milram Butterkäse', 2, 140, 12.6, 8],
                    ['Tzatziki (Edeka)', 15, 12.8, 1.1, 0.5],
                    ['Teriyaki BBQ (Hello Taste)', 5, 8, 0.1, 0.1],
                ],
            ],
            'sepehr' => [
                'burned' => ['11.000 Schritte', 500],
                'sum' => [2331.8, 106.3, 116.1],
                'items' => [
                    ['Protein-Käsekuchen (Eigenrezept)', 150, 268, 8.7, 34.1],
                    ['Vollkornbrötchen', 1.5, 325, 6.8, 12],
                    ['Vegane Salami (Billie Green)', 6, 54, 2.4, 6],
                    ['Vegane Streichcreme „Toskana" (Penny)', 10, 54, 3.1, 0.6, 'g'],
                    ['Milram Butterkäse', 0.5, 35, 3.2, 2],
                    ['Gurke', 0.5, 23, 0.2, 1],
                    ['Selbstgemachter Zupfkuchen', 2, 792, 43.2, 14.4],
                    ['Burger Bun (Edeka)', 1, 214, 4.1, 6.4],
                    ['Chicken Burger Patty (Edeka)', 2, 406, 20.8, 31],
                    ['Milram Butterkäse', 2, 140, 12.6, 8],
                    ['Tzatziki (Edeka)', 15, 12.8, 1.1, 0.5],
                    ['Teriyaki BBQ (Hello Taste)', 5, 8, 0.1, 0.1],
                ],
            ],
        ],

        '2026-08-26' => [
            'cosima' => [
                'burned' => ['Training', 930],
                'sum' => [1955.5, 50.9, 136.8],
                'items' => [
                    ['Truefruits Pink (0,75 l)', 1, 443, 0.8, 2.3],
                    ['Gebackenes Gemüse (Zucchini, Paprika, Zwiebel, Möhren, Aubergine, Öl)', 300, 97, 1.3, 3.4],
                    ['Hähnchengeschnetzeltes, gebraten (ohne Öl)', 150, 248, 5.4, 46.5],
                    ['Tzatziki (Edeka)', 200, 170, 14, 6],
                    ['Kleine Birne', 2, 137, 0.2, 1],
                    ['Chicken Crunchies (Edeka)', 250, 512.5, 20, 42.5],
                    ['Protein-Käsekuchen (Eigenrezept)', 150, 268, 8.7, 34.1],
                    ['Teriyaki BBQ (Hello Taste)', 50, 80, 0.5, 1],
                ],
            ],
            'sepehr' => [
                'burned' => ['Training', 1088],
                'sum' => [2095.5, 55.7, 160],
                'items' => [
                    ['Truefruits Pink (0,75 l)', 1, 443, 0.8, 2.3],
                    ['Protein-Käsekuchen (Eigenrezept)', 150, 268, 8.7, 34.1],
                    ['Gebackenes Gemüse (Zucchini, Paprika, Zwiebel, Möhren, Aubergine, Öl)', 300, 97, 1.3, 3.4],
                    ['Hähnchengeschnetzeltes, gebraten (ohne Öl)', 150, 248, 5.4, 46.5],
                    ['Tzatziki (Edeka)', 200, 170, 14, 6],
                    ['Kleine Birne', 2, 137, 0.2, 1],
                    ['Chicken Crunchies (Edeka)', 250, 512.5, 20, 42.5],
                    ['Hähnchenbrust in Paprikamarinade (Penny)', 80, 140, 4.8, 23.2],
                    ['Teriyaki BBQ (Hello Taste)', 50, 80, 0.5, 1],
                ],
            ],
        ],

        '2026-08-24' => [
            'cosima' => [
                'burned' => ['Training', 1003],
                'sum' => [2176.5, 106.3, 164.3],
                'items' => [
                    ['Eier als Rührei', 3, 250, 19, 19],
                    ['Hähnchenspieß mit Tomaten-Joghurt-Marinade (Penny)', 150, 186, 6.6, 30],
                    ['Hähnchenbrust in Paprikamarinade (Penny)', 150, 263, 9, 43.5],
                    ['Grillgemüse, halbe Portion (Paprika, Aubergine, Zucchini, Gemüsezwiebel, Olivenöl)', 1, 163, 2.5, 5.3],
                    ['Käse-Lauch-Speck-Quiche (Picnic Rezept)', 200, 580, 42, 20],
                    ['Kleine Birne', 2, 137, 0.2, 1],
                    ['Chicken Crunchies (Edeka)', 250, 512.5, 20, 42.5],
                    ['Tzatziki (Edeka)', 100, 85, 7, 3],
                ],
            ],
            'sepehr' => [
                'burned' => ['Training', 1027],
                'sum' => [2506.5, 115.3, 209.3],
                'items' => [
                    ['Eier als Rührei', 3, 250, 19, 19],
                    ['Hähnchenspieß mit Tomaten-Joghurt-Marinade (Penny)', 150, 186, 6.6, 30],
                    ['Hähnchenbrust in Paprikamarinade (Penny)', 150, 263, 9, 43.5],
                    ['Grillgemüse, halbe Portion (Paprika, Aubergine, Zucchini, Gemüsezwiebel, Olivenöl)', 1, 163, 2.5, 5.3],
                    ['Käse-Lauch-Speck-Quiche (Picnic Rezept)', 200, 580, 42, 20],
                    ['Kleine Birne', 2, 137, 0.2, 1],
                    ['Chicken Crunchies (Edeka)', 250, 512.5, 20, 42.5],
                    ['Tzatziki (Edeka)', 100, 85, 7, 3],
                    ['Proteinriegel', 1, 200, 7, 20],
                    ['Protein Shake (Fitnessstudio)', 1, 130, 2, 25],
                ],
            ],
        ],

        '2026-08-22' => [
            'cosima' => [
                'burned' => null,
                'sum' => [2360, 122.7, 112.7],
                'items' => [
                    ['Barbari (persisches Brot)', 80, 212, 1.2, 6.4],
                    ['Vegane Curry-Streichcreme (Penny)', 15, 42, 3.6, 0.4],
                    ['Vegane Salami', 5, 87, 6.3, 5.3],
                    ['Milram Butterkäse', 1, 70, 6.3, 4],
                    ['Speck-Lauch-Quiche (Picnic Rezept)', 200, 540, 38, 16],
                    ['Spaghetti mit 1 EL Pesto Genovese (Edeka)', 100, 226, 7.7, 6.3],
                    ['Hähnchenspieß mariniert in Tomaten-Joghurt', 300, 372, 13.2, 60],
                    ['Milram Tzatziki', 80, 82, 6.8, 2.9],
                    ['Dattel', 15, 336, 0.6, 2.4],
                    ['Walnuss', 15, 393, 39, 9],
                ],
            ],
            'sepehr' => [
                'burned' => null,
                'sum' => [2404, 134, 114.4],
                'items' => [
                    ['Barbari (persisches Brot)', 80, 212, 1.2, 6.4],
                    ['Vegane Curry-Streichcreme (Penny)', 15, 42, 3.6, 0.4],
                    ['Vegane Salami', 5, 87, 6.3, 5.3],
                    ['Milram Butterkäse', 1, 70, 6.3, 4],
                    ['Speck-Lauch-Quiche (Picnic Rezept)', 300, 810, 57, 24],
                    ['Hähnchenspieß mariniert in Tomaten-Joghurt', 300, 372, 13.2, 60],
                    ['Milram Tzatziki', 80, 82, 6.8, 2.9],
                    ['Dattel', 15, 336, 0.6, 2.4],
                    ['Walnuss', 15, 393, 39, 9],
                ],
            ],
        ],

        '2026-08-21' => [
            'cosima' => [
                'burned' => ['Training', 1071],
                'sum' => [2094.5, 95.7, 77.2],
                'items' => [
                    ['Eier als Rührei (ohne Öl)', 3, 210, 14.4, 18.9],
                    ['Gurke', 0.5, 23, 0.2, 1],
                    ['Barbari (persisches Brot)', 80, 212, 1.2, 6.4],
                    ['Erdbeermarmelade (Bonne Maman)', 1, 37, 0, 0.1],
                    ['Vegane Curry-Streichcreme (Penny)', 5, 14, 1.2, 0.1],
                    ['Vegane Salami', 3, 52, 3.8, 3.2],
                    ['Spaghetti mit Pesto Genovese (Edeka Herzstücke)', 300, 570, 27, 18],
                    ['Kartoffelecke', 6, 360, 12, 6],
                    ['Putenwürstchen', 0.5, 27.5, 2, 2.5],
                    ['Milram Tzatziki', 100, 103, 8.5, 3.6],
                    ['Milram Butterkäse', 4, 280, 25, 16],
                    ['Birne', 2, 206, 0.4, 1.4],
                ],
            ],
            'sepehr' => [
                'burned' => ['Training', 1006],
                'sum' => [3331.5, 168.5, 184.6],
                'items' => [
                    ['Eier als Rührei (ohne Öl)', 3, 210, 14.4, 18.9],
                    ['Gurke', 0.5, 23, 0.2, 1],
                    ['Barbari (persisches Brot)', 80, 212, 1.2, 6.4],
                    ['Vegane Curry-Streichcreme (Penny)', 5, 14, 1.2, 0.1],
                    ['Vegane Salami', 3, 52, 3.8, 3.2],
                    ['Milram Butterkäse', 1, 70, 6.3, 4],
                    ['Lubia Polo (persisches Gericht)', 700, 1155, 42, 49],
                    ['Kartoffelecke', 2, 120, 4, 2],
                    ['Putenwürstchen', 11.5, 632.5, 46, 57.5],
                    ['Milram Tzatziki', 100, 103, 8.5, 3.6],
                    ['Holländischer Trüffelgouda', 150, 534, 40.5, 37.5],
                    ['Birne', 2, 206, 0.4, 1.4],
                ],
            ],
        ],

        '2026-08-19' => [
            'cosima' => [
                'burned' => ['Training', 589],
                'sum' => [2370, 80.9, 144.5],
                'items' => [
                    ['Barbari (persisches Brot)', 80, 212, 1.2, 6.4],
                    ['Erdbeermarmelade (Bonne Maman)', 1, 37, 0, 0.1],
                    ['Lubia Polo (persisches Gericht)', 500, 825, 30, 35],
                    ['Banane', 3, 315, 1.2, 4],
                    ['Mandelmilch (Penny)', 500, 80, 6.5, 2.5],
                    ['Cheetos', 25, 121, 7.8, 1.5],
                    ['Birne', 0.5, 51, 0.1, 0.4],
                    ['Hähnchenspieß mit Tomaten-Joghurt-Marinade (Penny)', 150, 186, 6.6, 30],
                    ['Hähnchenbrust mit Kebabmarinade (Penny)', 200, 350, 12, 58],
                    ['Milram Tzatziki', 100, 103, 8.5, 3.6],
                    ['Gebratener Brokkoli', 100, 90, 7, 3],
                ],
            ],
            'sepehr' => [
                'burned' => ['Training', 720],
                'sum' => [2805, 112.4, 197],
                'items' => [
                    ['Barbari (persisches Brot)', 80, 212, 1.2, 6.4],
                    ['Vegane Salami', 3, 52, 3.8, 3.2],
                    ['Gouda', 1, 71, 5.4, 5],
                    ['Vegane Curry-Streichcreme (Penny)', 30, 84, 7.2, 0.8],
                    ['Lubia Polo (persisches Gericht)', 500, 825, 30, 35],
                    ['Banane', 2, 210, 0.8, 2.6],
                    ['Mandelmilch (Penny)', 500, 80, 6.5, 2.5],
                    ['Schoko-Protein-Pulver', 50, 190, 3.5, 37.5],
                    ['Cheetos', 25, 121, 7.8, 1.5],
                    ['Birne', 0.5, 51, 0.1, 0.4],
                    ['Kleiner Fertigsalat mit Käse (Penny)', 150, 180, 12, 7.5],
                    ['Hähnchenspieß mit Tomaten-Joghurt-Marinade (Penny)', 150, 186, 6.6, 30],
                    ['Hähnchenbrust mit Kebabmarinade (Penny)', 200, 350, 12, 58],
                    ['Milram Tzatziki', 100, 103, 8.5, 3.6],
                    ['Gebratener Brokkoli', 100, 90, 7, 3],
                ],
            ],
        ],

        '2026-08-17' => [
            'cosima' => [
                'burned' => ['Training', 757],
                'sum' => [2729, 142.6, 173.2],
                'items' => [
                    ['Walnussbrot', 2, 208, 5.6, 7.2],
                    ['Vegane Salami', 7, 122, 8.8, 7.4],
                    ['Gouda', 1, 71, 5.4, 5],
                    ['Vegane Streichcreme „Toskana" (dm)', 2, 162, 9.3, 1.8],
                    ['Köttbullar (Edeka)', 0.5, 548, 44, 26],
                    ['Hähnchenpfanne mit Penne & Gemüse (Frosta)', 500, 600, 17, 52.5],
                    ['Dattel', 8, 179, 0.3, 1.3],
                    ['Walnuss', 10, 262, 26, 6],
                    ['Hähnchenspieß mariniert in Tomaten-Joghurt', 300, 372, 13.2, 60],
                    ['Tzatziki', 100, 85, 7, 3],
                    ['Salat', 300, 120, 6, 3],
                ],
            ],
            'sepehr' => [
                'burned' => ['Training', 602],
                'sum' => [1963, 120.7, 125.5],
                'items' => [
                    ['Käse-Tortellini (Frosta)', 500, 660, 37, 21],
                    ['Köttbullar (Edeka)', 0.5, 548, 44, 26],
                    ['Hähnchenspieß mariniert in Tomaten-Joghurt', 300, 372, 13.2, 60],
                    ['Tzatziki', 100, 85, 7, 3],
                    ['Salat', 300, 120, 6, 3],
                    ['Holländischer Trüffelgouda', 50, 178, 13.5, 12.5],
                ],
            ],
        ],
    ];
}

