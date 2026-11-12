<?php

namespace Tcg\Common\Models;


/**
 * Represents a product with various attributes and behaviors
 * related to an item in an inventory or catalog.
 */
class Product
{
    public string $productId;
    public ?string $variantId;
    public ?int $quantity;
    public ?array $dimension;
    public ?array $options;
    public float $price;

    public const DEFAULT_DIMENSION = [
        'length' => 1, // cm
        'width'  => 1,
        'height' => 1,
        'mass'   => 0.1, // kg
    ];
    public ?string $description;

    /**
     * Product constructor.
     *
     * @param string $productId The unique identifier for the product.
     * @param float $price
     * @param string|null $description
     * @param string|null $variantId The variant identifier for the product, if applicable.
     * @param int|null $quantity The quantity of the product.
     * @param array|null $dimension
     * @param array|null $options
     */
    public function __construct(
        string $productId,
        float $price,
        ?string $description,
        ?string $variantId,
        ?int $quantity = 1,
        ?array $dimension = null,
        ?array $options = []
    ) {
        $this->productId = $productId;
        $this->price     = $price;
        $this->variantId = $variantId;
        $this->quantity  = $quantity;
        $this->dimension = $dimension ?? self::DEFAULT_DIMENSION;
        if ($this->dimension['length'] <= 0) {
            $this->dimension['length'] = self::DEFAULT_DIMENSION['length'];
        }
        if ($this->dimension['width'] <= 0) {
            $this->dimension['width'] = self::DEFAULT_DIMENSION['width'];
        }
        if ($this->dimension['height'] <= 0) {
            $this->dimension['height'] = self::DEFAULT_DIMENSION['height'];
        }
        if ($this->dimension['mass'] <= 0) {
            $this->dimension['mass'] = self::DEFAULT_DIMENSION['mass'];
        }
        $this->description = $description ?? "Product $productId";
        $this->options     = $options;
    }

    public function getTotalMass(): float
    {
        return $this->dimension['mass'] * $this->quantity;
    }

    public function getTotalValue(): float
    {
        return $this->price * $this->quantity;
    }
}
