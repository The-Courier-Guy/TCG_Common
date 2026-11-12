<?php

namespace Tcg\Common\Packing\Controllers;

class ShipLogicParcelController
{
    private array $boxes;
    private array $fittingItems;
    private int $j;
    public CommonPackingController $commonPackingController;

    /**
     * @param array $boxes
     */
    public function __construct(array $boxes)
    {
        $this->boxes = $boxes;
        // Unset the unused boxes, sort dimensions
        foreach ($this->boxes as $key => $box) {
            $length    = $box['length'] ?? 0.0;
            $width     = $box['width'] ?? 0.0;
            $height    = $box['height'] ?? 0.0;
            $boxValues = [$length, $width, $height];
            rsort($boxValues);
            foreach (['length', 'width', 'height'] as $index => $boxKey) {
                $this->boxes[$key][$boxKey] = $boxValues[$index];
            }
            if ((int)$length === 0 || (int)$width === 0 || (int)$height === 0) {
                unset($this->boxes[$key]);
            }
        }
        // Sort the boxes in ascending order by dimension
        usort($this->boxes, function ($a, $b) {
            return $a['length'] <=> $b['length']
                ?: $a['width'] <=> $b['width']
                    ?: $a['height'] <=> $b['height']
                        ?: $a['max_weight'] <=> $b['max_weight'];
        });
        $this->j = 0;

        $this->commonPackingController = new CommonPackingController($this->boxes);
    }
}
