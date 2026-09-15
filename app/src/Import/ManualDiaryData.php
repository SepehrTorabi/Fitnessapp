<?php

declare(strict_types=1);

namespace App\Import;

/**
 * Days entered by hand, after the printed diary the PDF covers.
 *
 * Same shape and same rules as {@see FoodDiaryData} - both go through
 * {@see FoodDiaryImporter}, which checks each day's items against the stated
 * total and replaces rather than duplicates on a second run.
 *
 * Where a figure came from is recorded next to it. Three sources appear:
 *
 *  - the catalogue, for foods already in it. Those line values are derived from
 *    the stored per-100 g values, so re-importing cannot drift them.
 *  - Open Food Facts, for branded products looked up there.
 *  - published reference values for generic whole foods, which no database of
 *    branded products can answer for.
 */
final class ManualDiaryData
{
    /**
     * @var array<string, array{0: string, 1: float}>
     */
    public const array UNIT_WEIGHTS = [
        // --- Already in the catalogue; weights unchanged from there ---
        'Körnerbrötchen' => ['Stück', 85.0],
        'Vegane Streichcreme „Toskana" (Edeka)' => ['EL', 15.0],
        'Eier als Rührei (ohne Öl)' => ['Ei', 50.0],
        'Chicken Crunchies (Edeka)' => ['g', 1.0],
        'Dark chocolate, 70%' => ['g', 1.0],

        // --- New, from Open Food Facts ---
        //
        // Choceur/Wintertraum Winterriegel Apfel-Märchen: 572 kcal/100 g. The
        // stated 104 kcal a bar fixes the bar at 18.2 g, so the weight is
        // derived from the two figures rather than guessed.
        'Winterriegel Apfel-Märchen (Aldi)' => ['Riegel', 18.2],
        'BBQ Sauce (Heinz)' => ['EL', 15.0],

        // --- New, from reference values ---
        //
        // Pasta and rice are cooked weights. That follows the existing diary:
        // it logs 250 g of Eiernudeln as 360 kcal, which is only possible
        // cooked - dry that would be some 900 kcal.
        'Spaghetti, gekocht' => ['g', 1.0],
        'Basmatireis, gekocht' => ['g', 1.0],
        'Nektarine' => ['Stück', 140.0],
        'Feige, frisch' => ['Stück', 50.0],
        'Flacher Pfirsich' => ['Stück', 80.0],

        // Computed from the ingredients given: 800 g Hackfleisch gemischt,
        // 200 g Bacon, 1 Packung Tomatenmark (70 g), 1 Zwiebel (110 g),
        // 1 Paprika (150 g) - 1330 g in total, 2849 kcal, so 214 kcal/100 g.
        // That is the raw mixture; simmering drives off water, so the sauce as
        // served is somewhat denser than this and 300 g of it is, if anything,
        // slightly understated.
        'Hackfleischsoße (Hackfleisch gemischt, Bacon, Tomatenmark, Zwiebel, Paprika)' => ['g', 1.0],
    ];

    /**
     * @var array<string, array<string, mixed>>
     */
    public const array DAYS = [
        '2026-09-15' => [
            'cosima' => [
                'burned' => ['Training', 1172],
                'sum' => [2530.9, 128.9, 127.6],
                'items' => [
                    ['Körnerbrötchen', 1, 230, 5.1, 8.5],
                    ['Vegane Streichcreme „Toskana" (Edeka)', 2, 162, 9.4, 1.8],
                    ['Eier als Rührei (ohne Öl)', 3, 210, 14.4, 18.9],
                    ['Winterriegel Apfel-Märchen (Aldi)', 4, 416.4, 26.9, 5.75],
                    ['Chicken Crunchies (Edeka)', 250, 512.5, 20, 42.5],
                    ['BBQ Sauce (Heinz)', 2, 41.4, 0.06, 0.27],
                    ['Spaghetti, gekocht', 200, 316, 1.8, 11.6],
                    ['Hackfleischsoße (Hackfleisch gemischt, Bacon, Tomatenmark, Zwiebel, Paprika)', 300, 642.6, 51.2, 38.3],
                ],
            ],
            'sepehr' => [
                'burned' => ['Training', 1048],
                'sum' => [1937.9, 82.35, 97.29],
                'items' => [
                    ['Chicken Crunchies (Edeka)', 250, 512.5, 20, 42.5],
                    ['BBQ Sauce (Heinz)', 2, 41.4, 0.06, 0.27],
                    ['Basmatireis, gekocht', 200, 260, 0.6, 5.4],
                    ['Spaghetti, gekocht', 100, 158, 0.9, 5.8],
                    ['Hackfleischsoße (Hackfleisch gemischt, Bacon, Tomatenmark, Zwiebel, Paprika)', 300, 642.6, 51.2, 38.3],
                    ['Dark chocolate, 70%', 20, 119.6, 8.52, 1.56],
                    ['Nektarine', 1, 61.6, 0.42, 1.54],
                    ['Feige, frisch', 3, 111, 0.45, 1.2],
                    ['Flacher Pfirsich', 1, 31.2, 0.2, 0.72],
                ],
            ],
        ],
    ];
}
