<?php


use PHPUnit\Framework\TestCase;
use Tcg\Common\Packing\Controllers\CommonPackingController;
use Tcg\Common\Models\Product;

class CommonPackingTest extends TestCase
{
    public function testDefaultDimensionsAreUsed()
    {
        $standardItems     = $this->getStandardEmptyItems();
        foreach ($standardItems as $item) {
            $this->assertNotEmpty($item->dimension);
            $this->assertArrayHasKey('length', $item->dimension);
            $this->assertArrayHasKey('width', $item->dimension);
            $this->assertArrayHasKey('height', $item->dimension);
            $this->assertArrayHasKey('mass', $item->dimension);
            $dimension = $item->dimension;
            $this->assertGreaterThan(0, $dimension['length']);
            $this->assertGreaterThan(0, $dimension['width']);
            $this->assertGreaterThan(0, $dimension['height']);
            $this->assertGreaterThan(0, $dimension['mass']);
        }
    }
    public function testCalculateMultiFittingItems()
    {
        $standardItems     = $this->getStandardItems();
        $boxes             = $this->getBoxes();
        $packingController = new CommonPackingController($boxes);
        $parcels           = $packingController->calculateMultiFittingItems($standardItems);
        $this->assertNotEmpty($parcels);
        $this->assertCount(2, $parcels);
        $this->assertEquals($this->getExpectedParcels(), $parcels);
    }

    private function getStandardItems(): array
    {
        $product300g = new Product(
            'product-300g',
            10.0,
            'Product 300g',
            'variant-300g',
            7,
            [
                'length' => 0,
                'width'  => 0,
                'height' => 0,
                'mass'   => 0.3, // 300g
            ],
        );
        $product3kg  = new Product(
            'product-3kg',
            10.0,
            'Product 3kg',
            'variant-3kg',
            3,
            [
                'length' => 0,
                'width'  => 0,
                'height' => 0,
                'mass'   => 3.0, // 3kg
            ],
        );

        return [
            $product300g,
            $product3kg,
        ];
    }
    private function getStandardEmptyItems(): array
    {
        $product300g = new Product(
            'product-300g',
            10.0,
            'Product 300g',
            'variant-300g',
            7,
            [
                'length' => 0,
                'width'  => 0,
                'height' => 0,
                'mass'   => 0.0, // 300g
            ],
        );
        $product3kg  = new Product(
            'product-3kg',
            10.0,
            'Product 3kg',
            'variant-3kg',
            3,
            [
                'length' => 0,
                'width'  => 0,
                'height' => 0,
                'mass'   => 0.0, // 3kg
            ],
        );

        return [
            $product300g,
            $product3kg,
        ];
    }

    private function getBoxes(): array
    {
        return [
            [
                'id'         => 1,
                'length'     => 50.0,
                'width'      => 40.0,
                'height'     => 30.0,
                'max_weight' => 2.0, // kg
                'volume'     => 50 * 40 * 30,
                'dimension'  => [
                    'length' => 50,
                    'width'  => 40,
                    'height' => 30,
                    'volume' => 50 * 40 * 30,
                ],
            ],
            [
                'id'         => 2,
                'length'     => 80.0,
                'width'      => 70.0,
                'height'     => 60.0,
                'max_weight' => 10.0, // kg
                'volume'     => 80 * 70 * 60,
                'dimension'  => [
                    'length' => 80,
                    'width'  => 70,
                    'height' => 60,
                    'volume' => 80 * 70 * 60,
                ],
            ],
        ];
    }

    private function getExpectedParcels(): array
    {
        return [
            [
                'item'        => 1,
                'description' => 'Product 3kg',
                'pieces'      => 1,
                'length'      => 80,
                'width'       => 70,
                'height'      => 60,
                'mass'        => 9.9, // kg
                'value'       => 60.0, // example value
                'maxWeight'   => 10.0, // kg
            ],
            [
                'item'        => 2,
                'description' => 'Product 300g',
                'pieces'      => 1,
                'length'      => 50,
                'width'       => 40,
                'height'      => 30,
                'mass'        => 1.2, // kg
                'value'       => 40.0, // example value
                'maxWeight'   => 2.0, // kg
            ],
        ];
    }
}
