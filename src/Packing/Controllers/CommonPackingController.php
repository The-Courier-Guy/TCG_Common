<?php

namespace Tcg\Common\Packing\Controllers;

use Tcg\Common\Models\Product;

class CommonPackingController
{
    private const DEFAULT_MASS = 0.1;
    private const DEFAULT_DIMENSION = 1;

    private $boxes;

    private int $j;

    public function __construct(array $boxes)
    {
        $this->boxes = $boxes;
        $this->j     = 0;
    }

    public function getBoxes()
    {
        return $this->boxes;
    }

    public function getFitsIndex(Product $item)
    {
        $fitsIndex = null;
        foreach ($this->boxes as $key => $box) {
            if (
                $item->dimension['volume'] <= $box['dimension']['volume'] &&
                $item->dimension['length'] <= $box['dimension']['length'] &&
                $item->dimension['width'] <= $box['dimension']['width'] &&
                $item->dimension['height'] <= $box['dimension']['height'] &&
                $item->dimension['mass'] <= ($box['max_weight'] ?? 0.0)
            ) {
                $fitsIndex = (int)$key;
                break;
            }
        }

        return $fitsIndex;
    }

    public function anyItemsTooHeavy(array $items): bool
    {
        $maxWeight = 0.0;
        foreach ($this->boxes as $box) {
            if ($box['max_weight'] > $maxWeight) {
                $maxWeight = $box['max_weight'];
            }
        }
        foreach ($items as $item) {
            if (($item->dimension['mass'] ?? 0.0) > $maxWeight) {
                return true;
            }
        }

        return false;
    }

    public function partitionItems(array $items, $containersEnabled = false): array
    {
        $tooBigOrHeavyItems    = [];
        $singleItems    = [];
        $containerItems = [];

        // First, set aside the configured single parcel items
        foreach ($items as $key => $item) {
            if (isset($item->options['single_parcel_item']) && $item->options['single_parcel_item']) {
                $singleItems[] = $item;
                unset($items[$key]);
            }
        }

        // Next, find any dimensioned items that are too big or heavy for any box and set them aside
        foreach ($items as $key => $item) {
            // Double check item dimensions are sorted
            $sizes = [$item->dimension['length'], $item->dimension['width'], $item->dimension['height']];
            rsort($sizes);
            $item->dimension['length'] = $sizes[0];
            $item->dimension['width']  = $sizes[1];
            $item->dimension['height'] = $sizes[2];
            if (isset($item->dimension) && $item->dimension['volume'] > 0) {
                $fitsInBox = false;
                foreach ($this->boxes as $box) {
                    // Double check sorted box dimensions
                    $boxSizes = [$box['length'], $box['width'], $box['height']];
                    rsort($boxSizes);
                    $box['length'] = $box['dimension']['length'] = $boxSizes[0];
                    $box['width']  = $box['dimension']['width'] = $boxSizes[1];
                    $box['height'] = $box['dimension']['height'] = $boxSizes[2];
                    if (
                        $item->dimension['length'] <= $box['length'] &&
                        $item->dimension['width'] <= $box['width'] &&
                        $item->dimension['height'] <= $box['height'] &&
                        $item->dimension['mass'] <= ($box['max_weight'] ?? 0.0)
                    ) {
                        $fitsInBox = true;
                        break;
                    }
                }
                if (!$fitsInBox) {
                    $tooBigOrHeavyItems[] = $item;
                    unset($items[$key]);
                }
            }
        }

        // Next, identify any container items and set them apart
        if ($containersEnabled) {
            foreach ($items as $key => $item) {
                $isContainer = $item->options['is_container'];
                if ($isContainer) {
                    $quantity = $item->quantity;
                    for ($i = 0; $i < $quantity; $i++) {
                        $item->quantity   = 1;
                        $containerItems[] = $item;
                    }
                    unset($items[$key]);
                }
            }
        }

        // Whatever is left are dimensioned items that fit into at least one box
        // or items with no dimensions or mass (we will treat them as dimensioned items with a default mass in packing)
        return [$tooBigOrHeavyItems, $singleItems, $containerItems, $items];
    }

    /**
     * @param array $box
     * @param array $itemDims
     *
     * @return int
     */
    public function getMaxPackingConfiguration(array $box, array $itemDims): int
    {
        $boxMaxWeight    = $box['max_weight'] ?? 0.0;
        $boxPermutations = [
            [0, 1, 2],
            [0, 2, 1],
            [1, 0, 2],
            [1, 2, 0],
            [2, 1, 0],
            [2, 0, 1]
        ];
        if (isset($box['dimension'])) {
            $box = array_values($box['dimension']);
        } else {
            unset($box['mass']);
            unset($box['volume']);
            $box = array_values($box);
        }
        $maxItems = 0;
        foreach ($boxPermutations as $boxPermutation) {
            $boxItems        = (int)($box[0] / $itemDims[$boxPermutation[0]]);
            $boxItems        *= (int)($box[1] / $itemDims[$boxPermutation[1]]);
            $boxItems        *= (int)($box[2] / $itemDims[$boxPermutation[2]]);
            $boxMaxMassItems = $boxMaxWeight > 0.0 ? (int)floor($boxMaxWeight / $itemDims[3]) : $boxItems;
            $boxItems        = min($boxItems, $boxMaxMassItems);
            $maxItems        = max($maxItems, $boxItems);
        }

        return $maxItems;
    }

    public function calculateMultiFittingItems(array $fittingItems, $isContainer = false, $container = null): array
    {
        if (empty($fittingItems)) {
            return [];
        }
        $fits = [];

        usort($fittingItems, function ($b, $a) {
            // We want to sort in descending order of size, so we compare $b to $a
            $aLength = $a->dimension['length'] > 0.0 ? $a->dimension['length'] : self::DEFAULT_DIMENSION;
            $aWidth  = $a->dimension['width'] > 0.0 ? $a->dimension['width'] : self::DEFAULT_DIMENSION;
            $aHeight = $a->dimension['height'] > 0.0 ? $a->dimension['height'] : self::DEFAULT_DIMENSION;
            $aMass   = $a->dimension['mass'] > 0.0 ? $a->dimension['mass'] : self::DEFAULT_MASS;

            $bLength = $b->dimension['length'] > 0.0 ? $b->dimension['length'] : self::DEFAULT_DIMENSION;
            $bWidth  = $b->dimension['width'] > 0.0 ? $b->dimension['width'] : self::DEFAULT_DIMENSION;
            $bHeight = $b->dimension['height'] > 0.0 ? $b->dimension['height'] : self::DEFAULT_DIMENSION;
            $bMass   = $b->dimension['mass'] > 0.0 ? $b->dimension['mass'] : self::DEFAULT_MASS;
            if ($aLength !== $bLength) {
                return $aLength <=> $bLength;
            }
            if ($aWidth !== $bWidth) {
                return $aWidth <=> $bWidth;
            }
            if ($aHeight !== $bHeight) {
                return $aHeight <=> $bHeight;
            }

            return $aMass <=> $bMass;
        });

        foreach ($fittingItems as $key1 => $item) {
            $itemDims = [
                $item->dimension['length'] > 0.0 ? $item->dimension['length'] : self::DEFAULT_DIMENSION,
                $item->dimension['width'] > 0.0 ? $item->dimension['width'] : self::DEFAULT_DIMENSION,
                $item->dimension['height'] > 0.0 ? $item->dimension['height'] : self::DEFAULT_DIMENSION,
                $item->dimension['mass'] > 0.0 ? $item->dimension['mass'] : self::DEFAULT_MASS
            ];

            foreach ($this->boxes as $key => $box) {
                $maxByMassVolume   = self::getMaxPackingConfiguration($box, $itemDims);
                $fits[$key][$key1] = $maxByMassVolume;
            }
        }

        $tcgPackages = [];

        if ($isContainer && $container !== null) {
            $container = unserialize(serialize($container));
            foreach ($fits as $fitIndex => $fit) {
                $remainingItems = unserialize(serialize($fittingItems));
                $result         = [];
                list($r2, $anyItemsLeft, $remainingItems) = $this->fitItemsInRealBoxes(
                    $remainingItems,
                    $fits,
                    (int)$fitIndex
                );
                if ($r2 !== null) {
                    $result = $r2[0];
                }
                if (!empty($result)) {
                    $container->price             += $result['value'] ?? 0.0;
                    $container->dimension['mass'] = (float)($container->dimension['mass'] ?? 0.0) + ($result['mass'] ?? 0.0);
                }


                return [$container, $remainingItems];
            }
        } else {
            foreach ($fits as $fitIndex => $fit) {
                $remainingItems = unserialize(serialize($fittingItems));
                $results        = [];
                $anyItemsLeft   = true;
                while ($anyItemsLeft) {
                    list($r2, $anyItemsLeft, $remainingItems) = $this->fitItemsInRealBoxes(
                        $remainingItems,
                        $fits,
                        (int)$fitIndex
                    );
                    if ($r2 !== null) {
                        $results[] = $r2[0];
                    }
                }
                if (count($results) === 1) {
                    return $results;
                }
                $tcgPackages[$fitIndex] = $results;
            }

            usort($tcgPackages, self::sort1(...));

            return $tcgPackages[0];
        }

        return $tcgPackages;
    }

    /**
     * @param array $items
     * @param array $fits
     * @param int $boxndx
     *
     * @return array|null
     */
    public function fitItemsInRealBoxes(
        array $items,
        array $fits,
        int $boxndx = 0,
    ): ?array {
        $items1 = array_values($items);

        foreach ($fits as $fitKey => $fit) {
            if ((int)$fitKey < $boxndx) {
                unset($fits[$fitKey]);
            }
        }
        $j = $this->j;
        $j++;
        $entry  = [];
        $boxKey = null;

        for ($key = 0; $key < count($items1); $key++) {
            $item = $items1[$key];
            if ($item->quantity === 0) {
                continue;
            }
            $slug                 = $key;
            $boxKey               = !$boxKey ? $this->getBoxKey($fits, $slug, $item->quantity) : $boxKey;
            $box                  = $this->boxes[$boxKey];
            $entry['item']        = $j;
            $entry['description'] = $item->description;
            $entry['pieces']      = 1;
            $entry['length']      = $box['dimension']['length'] ?? $box['length'];
            $entry['width']       = $box['dimension']['width'] ?? $box['width'];
            $entry['height']      = $box['dimension']['height'] ?? $box['height'];
            $entry['mass']        = 0.0;
            $entry['value']       = 0;
            $entry['maxWeight']   = $box['max_weight'] ?? 0.0;

            // Calculate how many can be added
            $itemDims = [
                $item->dimension['length'] > 0.0 ? $item->dimension['length'] : self::DEFAULT_DIMENSION,
                $item->dimension['width'] > 0.0 ? $item->dimension['width'] : self::DEFAULT_DIMENSION,
                $item->dimension['height'] > 0.0 ? $item->dimension['height'] : self::DEFAULT_DIMENSION,
                $item->dimension['mass'] > 0.0 ? $item->dimension['mass'] : self::DEFAULT_MASS
            ];
            $maxItems = self::getMaxPackingConfiguration($box, $itemDims);
            if ($maxItems === 0) {
                return [null, count($items1) > 0, array_values($items1)];
            }
            $nItemsToAdd = min($maxItems, $item->quantity);
            // Put them into the box
            $entry['value']         += $nItemsToAdd * $item->price;
            $entry['mass']          += $nItemsToAdd * ($item->dimension['mass'] > 0.0 ?
                    $item->dimension['mass'] : self::DEFAULT_MASS);
            $items1[$key]->quantity -= $nItemsToAdd;

            // Calculate the remaining boxes content
            $vBoxes = self::getActualPackingConfigurationAdvanced($box, $itemDims, $nItemsToAdd);
            // There are up to three virtual boxes
            for ($vBoxi = 0; $vBoxi < count($vBoxes); $vBoxi++) {
                $this->fitItemsInVbox($vBoxes[$vBoxi], $items1, $entry);
            }
            break;
        }
        $r2[]           = $entry;
        $itemsRemaining = 0;
        foreach ($items1 as $item1) {
            $itemsRemaining += $item1->quantity;
        }
        $anyItemsLeft = $itemsRemaining > 0;
        $this->j      = $j;

        return [$r2, $anyItemsLeft, array_values($items1)];
    }

    /**
     * @param $fits
     * @param $slug
     * @param $itemCount
     *
     * @return int|string
     */
    private function getBoxKey($fits, $slug, $itemCount)
    {
        $fitsSlug = 0;
        foreach ($fits as $key => $fit) {
            $fitsSlug = $key;
            if ($fit[$slug] >= $itemCount) {
                break;
            }
        }

        return $fitsSlug;
    }

    /**
     * @param $box
     * @param $itemDims
     * @param $count
     *
     * @return array
     */
    private static function getActualPackingConfigurationAdvanced($box, $itemDims, $count): array
    {
        $boxPermutations = [
            [0, 1, 2],
            [0, 2, 1],
            [1, 0, 2],
            [1, 2, 0],
            [2, 1, 0],
            [2, 0, 1]
        ];

        if (isset($box['dimension'])) {
            $boxLength = $box['dimension']['length'];
            $boxWidth  = $box['dimension']['width'];
            $boxHeight = $box['dimension']['height'];
        } else {
            $boxLength = $box[0] ?? $box['length'];
            $boxWidth  = $box[1] ?? $box['width'];
            $boxHeight = $box[2] ?? $box['height'];
        }

        $usedHeight = $boxHeight;
        $useds      = [];
        foreach ($boxPermutations as $permutation) {
            $nl = min($count, (int)($boxLength / $itemDims[$permutation[0]]));
            $nw = min($count, (int)($boxWidth / $itemDims[$permutation[1]]));
            $na = $nl * $nw;
            $h  = 0;
            if ($na !== 0) {
                $h = ceil($count / $na) * $itemDims[$permutation[2]];
                if ($h <= $usedHeight) {
                    $usedHeight = $h;
                }
            }
            $useds[] = [$nl * $itemDims[$permutation[0]], $nw * $itemDims[$permutation[1]], $h];
        }

        $used = [];
        foreach ($useds as $u) {
            if (self::floatsAreEqual($u[2], $usedHeight)) {
                $used = $u;
                break;
            }
        }

        $remainingBoxes = [];
        if (!empty($used)) {
            $vb1 = [$used[0], $used[1], $boxHeight - $used[2]];
            rsort($vb1);
            $vb1['volume'] = $vb1[0] * $vb1[1] * $vb1[2];
            if ($vb1['volume'] > 0) {
                $remainingBoxes[] = $vb1;
            }

            $vb2 = [$boxLength - $used[0], $boxWidth, $boxHeight];
            rsort($vb2);
            $vb2['volume'] = $vb2[0] * $vb2[1] * $vb2[2];
            if ($vb2['volume'] > 0) {
                $remainingBoxes[] = $vb2;
            }

            $vb3 = [
                $boxLength,
                $boxWidth - $used[1],
                $boxHeight
            ];
            rsort($vb3);
            $vb3['volume'] = $vb3[0] * $vb3[1] * $vb3[2];
            if ($vb3['volume'] > 0) {
                $remainingBoxes[] = $vb3;
            }
        }

        return $remainingBoxes;
    }

    /**
     * Calculate fit of items into virtual boxes
     * Called recursively
     *
     * @param $vbox
     * @param $items1
     * @param $entry
     *
     * @return void
     */
    private function fitItemsInVbox($vbox, &$items1, &$entry)
    {
        for ($itemi = 0; $itemi < count($items1); $itemi++) {
            $itemvb = $items1[$itemi];
            if ($itemvb->quantity === 0) {
                continue;
            }

            // Calculate how many can be added
            $itemDims        = [
                $itemvb->dimension['length'] > 0.0 ? $itemvb->dimension['length'] : self::DEFAULT_DIMENSION,
                $itemvb->dimension['width'] > 0.0 ? $itemvb->dimension['width'] : self::DEFAULT_DIMENSION,
                $itemvb->dimension['height'] > 0.0 ? $itemvb->dimension['height'] : self::DEFAULT_DIMENSION,
                $itemvb->dimension['mass'] > 0.0 ? $itemvb->dimension['mass'] : self::DEFAULT_MASS
            ];
            $maxItems        = self::getMaxPackingConfiguration($vbox, $itemDims);
            $availableWeight = $entry['maxWeight'] > 0.0 ? $entry['maxWeight'] - $entry['mass'] : 0.0;
            $maxItemsByMass  = $availableWeight > 0.0 ? (int)floor($availableWeight / $itemDims[3]) : $maxItems;
            $maxItems        = min($maxItems, $maxItemsByMass);
            if ($maxItems === 0) {
                continue;
            }

            // Else put items into this virtual box
            $nitems = min(
                $maxItems,
                $itemvb->quantity
            );

            $items1[$itemi]->quantity -= $nitems;
            $entry['mass']            += $nitems * ($itemvb->dimension['mass'] > 0 ?
                    $itemvb->dimension['mass'] : self::DEFAULT_MASS);
            $entry['value']           += $nitems * $itemvb->price;

            // Calculate the remaining vboxes content
            $vboxes = self::getActualPackingConfigurationAdvanced($vbox, $itemDims, $nitems);
            for ($vbi = 0; $vbi < count($vboxes); $vbi++) {
                $this->fitItemsInVbox($vboxes[$vbi], $items1, $entry);
            }
            break;
        }
    }

    private static function floatsAreEqual($a, $b): bool
    {
        return abs($a - $b) < 0.0001;
    }

    private function sort1($a, $b)
    {
        if (count($a) === count($b)) {
            $aVol = 0.0;
            foreach ($a as $value) {
                $aVol += $this->packVol($value);
            }
            $bVol = 0.0;
            foreach ($b as $value) {
                $bVol += $this->packVol($value);
            }

            return $aVol <=> $bVol;
        }

        return count($a) <=> count($b);
    }

    /**
     * @param array $package
     *
     * @return float
     */
    private function packVol(array $package): float
    {
        return (float)$package['length'] * (float)$package['width'] * (float)$package['height'];
    }

    // Packing of different types of items (dimensioned, too heavy, single parcel, container).
    /**
     * Parcel up single items
     * They either fit into a box, or have their own dimensions
     *
     * @param array $items
     *
     * @return array
     */
    public function packSingleItems(array $items): array
    {
        $parcels = [];
        if (empty($items)) {
            return $parcels;
        }
        foreach ($items as $item) {
            $fitsIndex = $this->getFitsIndex($item);

            // If it fits in a box pack into the box
            if ($fitsIndex !== null) {
                for ($i = 0; $i < $item->quantity; $i++) {
                    $parcels[] = [
                        'mass'   => $item->dimension['mass'] ?? 0.1, // default to 0.1 kg
                        'value'  => $item->price,
                        'length' => $this->boxes[$fitsIndex]['dimension']['length'],
                        'width'  => $this->boxes[$fitsIndex]['dimension']['width'],
                        'height' => $this->boxes[$fitsIndex]['dimension']['height'],
                    ];
                }
            } else {
                // pack as an individual parcel
                for ($i = 0; $i < $item->quantity; $i++) {
                    $parcels[] = [
                        'mass'        => $item->dimension['mass'] ?? 0.1,
                        'value'       => $item->price,
                        'length'      => (float)($item->dimension['length'] > 0.0 ? $item->dimension['length'] : 1.0),
                        'width'       => (float)($item->dimension['width'] > 0.0 ? $item->dimension['width'] : 1.0),
                        'height'      => (float)($item->dimension['height'] > 0.0 ? $item->dimension['height'] : 1.0),
                        'description' => $item->description,
                    ];
                }
            }
        }

        return $parcels;
    }

    /**
     * Parcel up too-big or too-heavy items
     * They don't fit in boxes so have their own dimensions
     *
     * @param array $items
     *
     * @return array
     */
    public function packTooBigOrHeavyItems(array $items): array
    {
        $parcels = [];
        if (empty($items)) {
            return $parcels;
        }
        foreach ($items as $item) {
            // pack as an individual parcel
            for ($i = 0; $i < $item->quantity; $i++) {
                $parcels[] = [
                    'mass'        => $item->dimension['mass'] ?? 0.1,
                    'value'       => $item->price,
                    'length'      => (float)($item->dimension['length'] > 0.0 ? $item->dimension['length'] : 1.0),
                    'width'       => (float)($item->dimension['width'] > 0.0 ? $item->dimension['width'] : 1.0),
                    'height'      => (float)($item->dimension['height'] > 0.0 ? $item->dimension['height'] : 1.0),
                    'description' => $item->description,
                ];
            }
        }

        return $parcels;
    }

    /**
     * Calculate optimum packing into boxes based on product dimensions and weight
     *
     * @param array $fittingItems
     *
     * @return array
     */
    public function packDimensionedItems(array $fittingItems): array
    {
        $parcels = [];

        if (empty($fittingItems)) {
            return $parcels;
        }

        $this->fittingItems = $fittingItems;

        // Now the fitting items - use advanced algorithm
        return $this->calculateMultiFittingItems($fittingItems);
    }

    /**
     * @param array $containers
     * @param array $fittingItems
     *
     * @return array
     */
    public function packContainers(array $containers, array $fittingItems): array
    {
        $packedContainers = [];

        foreach ($containers as $container) {
            if (empty($fittingItems)) {
                $packedContainers[] = unserialize(serialize($container));
                $fittingItems       = unserialize(serialize($fittingItems));
            } else {
                $containerDimension = $container->dimension;
                unset($containerDimension['mass']);
                unset($containerDimension['volume']);
                $containerDimension = array_values($containerDimension);
                rsort($containerDimension);
                $containerDimension['volume'] = $containerDimension[0] * $containerDimension[1] * $containerDimension[2];
                // Now we need to try and fit the other items into the container
                $containerPackingController = new CommonPackingController([$container->dimension]);
                [$packedContainer, $fittingItems] = $containerPackingController->calculateMultiFittingItems(
                    $fittingItems,
                    true,
                    $container
                );
                $packedContainers[] = unserialize(serialize($packedContainer));
                $fittingItems       = unserialize(serialize($fittingItems));
            }
        }


        return [$packedContainers, $fittingItems];
    }

}
