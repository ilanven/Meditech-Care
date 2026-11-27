<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Product;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/** Jeu fixe et lisible pour reproduire facilement les essais de l'atelier. */
final class ProductFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $products = [
            ['Clavier mécanique', 'AZERTY compact', '49.90', 12, true],
            ['Souris sans fil', 'Rechargeable USB-C', '24.50', 30, true],
            ['Écran 27 pouces', 'Écran IPS 144 Hz', '229.00', 5, true],
            ['Hub USB-C', 'Six ports', '39.99', 0, true],
            ['Casque audio', null, '79.00', 8, false],
            ['Webcam HD', 'Résolution 1080p', '44.90', 19, true],
            ['Tapis de souris', 'Format XL', '14.90', 52, true],
            ['Support ordinateur', 'Aluminium réglable', '34.90', 7, true],
            ['Microphone USB', 'Micro sur pied', '89.95', 3, false],
            ['Câble Ethernet', 'Câble de deux mètres', '6.50', 100, true],
        ];

        foreach ($products as [$name, $description, $price, $stock, $isActive]) {
            $product = (new Product())
                ->setName($name)
                ->setDescription($description)
                ->setPrice($price)
                ->setStock($stock)
                ->setIsActive($isActive);
            $manager->persist($product);
        }

        $manager->flush();
    }
}
