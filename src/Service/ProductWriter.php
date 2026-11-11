<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Product\CreateProductInput;
use App\Dto\Product\PatchProductInput;
use App\Dto\Product\ReplaceProductInput;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;

/** Ecriture des produits : le contrôleur final orchestre HTTP, ce service modifie l'entité. */
final class ProductWriter
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function create(CreateProductInput $input): Product
    {
        $product = (new Product())
            ->setName($input->name)
            ->setDescription($input->description)
            ->setPrice($input->price)
            ->setStock($input->stock)
            ->setIsActive($input->isActive);

        $this->em->persist($product);
        $this->em->flush();

        return $product;
    }

    public function replace(Product $product, ReplaceProductInput $input): Product
    {
        $product
            ->setName($input->name)
            ->setDescription($input->description)
            ->setPrice($input->price)
            ->setStock($input->stock)
            ->setIsActive($input->isActive);

        $this->em->flush();

        return $product;
    }

    /** @param list<string> $presentFields Champs réellement envoyés dans le JSON. */
    public function patch(Product $product, PatchProductInput $input, array $presentFields): Product
    {
        if (in_array('name', $presentFields, true)) {
            $product->setName($input->name);
        }
        if (in_array('description', $presentFields, true)) {
            $product->setDescription($input->description);
        }
        if (in_array('price', $presentFields, true)) {
            $product->setPrice($input->price);
        }
        if (in_array('stock', $presentFields, true)) {
            $product->setStock($input->stock);
        }
        if (in_array('isActive', $presentFields, true)) {
            $product->setIsActive($input->isActive);
        }

        $this->em->flush();

        return $product;
    }

    public function delete(Product $product): void
    {
        $this->em->remove($product);
        $this->em->flush();
    }
}
