<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Product\CreateProductInput;
use App\Dto\Product\PatchProductInput;
use App\Dto\Product\ReplaceProductInput;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;

/** Ecrit en BDD sans connaître Request, JsonResponse, statuts HTTP ou OpenAPI. */
final class ProductWriter
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function create(CreateProductInput $input): Product
    {
        if ($input->price === null || $input->stock === null) {
            throw new \LogicException('CreateProductInput doit être validé avant écriture.');
        }

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
        if ($input->price === null || $input->stock === null || $input->isActive === null) {
            throw new \LogicException('ReplaceProductInput doit être validé avant écriture.');
        }

        $product
            ->setName($input->name)
            ->setDescription($input->description)
            ->setPrice($input->price)
            ->setStock($input->stock)
            ->setIsActive($input->isActive);

        // L'entité a été obtenue par Doctrine ; elle est déjà suivie.
        $this->em->flush();

        return $product;
    }

    /** @param list<string> $presentFields Les clés réellement envoyées dans le JSON. */
    public function patch(Product $product, PatchProductInput $input, array $presentFields): Product
    {
        if (in_array('name', $presentFields, true)) {
            if ($input->name === null) {
                throw new \LogicException('PATCH : name validé ne peut pas être null.');
            }
            $product->setName($input->name);
        }
        if (in_array('description', $presentFields, true)) {
            $product->setDescription($input->description);
        }
        if (in_array('price', $presentFields, true)) {
            if ($input->price === null) {
                throw new \LogicException('PATCH : price validé ne peut pas être null.');
            }
            $product->setPrice($input->price);
        }
        if (in_array('stock', $presentFields, true)) {
            if ($input->stock === null) {
                throw new \LogicException('PATCH : stock validé ne peut pas être null.');
            }
            $product->setStock($input->stock);
        }
        if (in_array('isActive', $presentFields, true)) {
            if ($input->isActive === null) {
                throw new \LogicException('PATCH : isActive validé ne peut pas être null.');
            }
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
