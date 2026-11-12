# TCG_Common

## TCG common class for modules

This is the TCG common class for modules.

## Installation

You can install this module using composer:

```console
composer require tcg/tcg-common
```

## Usage - mostly for Developers
### Standard  Items
The consuming framework must first transform the checkout/cart items into a standard format. 

The standard format is an array of Product items, using new `TCG_Common\Item\Product` class.
The constructor is:
```php
public function __construct(
        string $productId,
        float $price,
        ?string $description,
        ?string $variantId,
        ?int $quantity = 1,
        ?array $dimension = null,
        ?array $options = []
    )
```
where $dimension is an array with keys `width`, `height`, `length` and `weight`. 
The $options is an array of key-value pairs, such as 'single_parcel_item' => true.

Example of creating a Product item:
```phpphp
use TCG_Common\Models\Product;

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
```

Using an array of boxes, each of the form
```php
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
```

create a CommonPackingController instance:
```phpphp
use TCG_Common\Packing\Controllers\CommonPackingController;
$packingController = new CommonPackingController($boxes);
```

Partition the items according to dimensions and weight:
```php
list(
            $tooBigOrHeavyItems, $singles, $containerItems, $dimensionBased
            ) =
            $packingController->partitionItems($standardItems);
```

`$tooBigOrHeavyItems` will contain items that exceed the max weight or dimension of any box, 
`$singles` will contain items that are marked as single parcel items, 
`$containerItems` will contain items that are containers (boxes) themselves,
 and `$dimensionBased` will contain the remaining items that can be packed based on their dimensions.

`$singles` are filtered first and so may contain items that are too heavy or too big, 
but they are still treated as single parcel items.
```php
$singleParcels = $packingController->packSingleItems($singles);
```
They will be packed into boxes if they fit, else they will be packed into a single parcel using their own dimensions.

Too-heavy and too-big items will be handled separately, using their own dimensions. For example:
```php
$parcels = array_merge($parcels, $packingController->packTooBigOrHeavyItems($tooBigOrHeavyItems));
```

If there are container items, they should be packed next:
```php
[$packedContainers, $fittingItems] = $packingController->packContainerItems($containerItems, $dimensionBased);
```
`$packedContainers` will contain the packed containers, which are themselves fitting items, and 
`$fittingItems` will contain the residual items that did not fit into the containers.
These should be merged:
```php
$dimensionBased = array_merge($fittingItems, $dimensionBased);
```

Finally, the remaining dimension-based items can be packed:
```php
$parcels = array_merge($parcels, $packingController->packDimensionedItems($dimensionBased));
```
This is an array of parcels which can be used to get rates from TCG.