<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Food;
use App\Entity\FoodPortion;
use App\Enum\FoodSource;
use App\Nutrition\Nutrients;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * A starter catalogue of staple foods.
 *
 * An empty database makes the app feel broken on first run - every search comes
 * back with nothing and every entry has to be typed in by hand. These are common
 * staples with values per 100 g, each with the named portions and the density it
 * actually needs, so the unit picker has something real to offer.
 *
 * Load with: php bin/console doctrine:fixtures:load
 */
final class FoodFixtures extends Fixture
{
    /**
     * name, kcal, protein, carbs, fat, fibre, density g/ml, portions
     */
    private const array FOODS = [
        ['Apple, raw', 52.0, 0.3, 13.8, 0.2, 2.4, 1.0, ['medium' => 180.0, 'large' => 240.0]],
        ['Banana, raw', 89.0, 1.1, 22.8, 0.3, 2.6, 1.0, ['medium' => 120.0]],
        ['Chicken breast, raw', 120.0, 22.5, 0.0, 2.6, null, 1.0, ['fillet' => 150.0]],
        ['Beef mince, 15% fat, raw', 215.0, 18.6, 0.0, 15.0, null, 1.0, []],
        ['Salmon fillet, raw', 208.0, 20.4, 0.0, 13.4, null, 1.0, ['fillet' => 140.0]],
        ['Hen egg, whole, raw', 143.0, 12.6, 0.7, 9.5, null, 1.0, ['medium' => 58.0, 'large' => 68.0]],
        ['Whole milk, 3.5%', 64.0, 3.4, 4.8, 3.6, null, 1.03, ['glass' => 200.0]],
        ['Quark, low fat', 67.0, 12.0, 4.0, 0.3, null, 1.04, ['tub' => 250.0]],
        ['Skyr, natural', 63.0, 11.0, 4.0, 0.2, null, 1.04, ['tub' => 150.0]],
        ['Gouda cheese, 45%', 356.0, 25.0, 0.0, 28.0, null, 1.0, ['slice' => 30.0]],
        ['Rolled oats', 372.0, 13.5, 58.7, 7.0, 10.0, 0.4, ['tbsp' => 12.0, 'scoop' => 40.0]],
        ['Wholegrain bread', 247.0, 9.4, 41.3, 3.9, 6.8, 0.45, ['slice' => 45.0]],
        ['White bread roll', 274.0, 8.7, 51.0, 3.1, 3.0, 0.35, ['roll' => 55.0]],
        ['Pasta, dry', 350.0, 12.5, 69.0, 1.5, 3.2, 0.6, ['portion' => 80.0]],
        ['White rice, dry', 349.0, 7.0, 77.0, 0.6, 1.4, 0.85, ['portion' => 75.0]],
        ['Potato, raw', 70.0, 2.0, 14.6, 0.1, 2.1, 1.0, ['medium' => 150.0]],
        ['Olive oil', 884.0, 0.0, 0.0, 100.0, null, 0.92, []],
        ['Butter', 741.0, 0.7, 0.6, 82.0, null, 0.91, ['pat' => 10.0]],
        ['Peanut butter', 588.0, 25.0, 20.0, 50.0, 6.0, 1.1, ['tbsp' => 16.0]],
        ['Almonds', 579.0, 21.2, 21.6, 49.9, 12.5, 0.6, ['handful' => 30.0]],
        ['Broccoli, raw', 34.0, 2.8, 6.6, 0.4, 2.6, 1.0, ['head' => 400.0]],
        ['Carrot, raw', 41.0, 0.9, 9.6, 0.2, 2.8, 1.0, ['medium' => 61.0]],
        ['Tomato, raw', 18.0, 0.9, 3.9, 0.2, 1.2, 1.0, ['medium' => 123.0]],
        ['Cucumber, raw', 15.0, 0.7, 3.6, 0.1, 0.5, 1.0, []],
        ['Lentils, dry', 353.0, 25.8, 60.1, 1.1, 30.5, 0.85, ['portion' => 80.0]],
        ['Chickpeas, cooked', 164.0, 8.9, 27.4, 2.6, 7.6, 0.9, []],
        ['Tofu, firm', 144.0, 15.8, 2.8, 8.7, 2.3, 1.0, ['block' => 200.0]],
        ['Honey', 304.0, 0.3, 82.4, 0.0, null, 1.42, ['tsp' => 7.0, 'tbsp' => 21.0]],
        ['Sugar, white', 387.0, 0.0, 100.0, 0.0, null, 0.85, ['tsp' => 4.0, 'tbsp' => 12.5]],
        ['Dark chocolate, 70%', 598.0, 7.8, 45.9, 42.6, 10.9, 1.0, ['square' => 10.0, 'bar' => 100.0]],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::FOODS as [$name, $kcal, $protein, $carbs, $fat, $fiber, $density, $portions]) {
            $food = new Food(
                $name,
                new Nutrients($kcal, $protein, $carbs, $fat, $fiber),
                FoodSource::Seed,
            );
            $food->setDensityGPerMl($density);

            foreach ($portions as $label => $grams) {
                $food->addPortion(new FoodPortion($food, (string) $label, $grams));
            }

            $manager->persist($food);
        }

        $manager->flush();
    }
}
