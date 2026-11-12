<?php

namespace Tcg\Common\Lockers\Service;

class PudoShippingService
{
    /**
     * @param $options
     * @param $rateLabel
     * @param $ratePrice
     * @param $serviceLevelCode
     *
     * @return void
     */
    public function applyRateOverrides($options, &$rateLabel, &$ratePrice, $serviceLevelCode): void
    {
        $labelOverrides = $options['label_overrides'] ?? [];
        $priceOverrides = $options['price_overrides'] ?? [];

        if (!empty($labelOverrides[$serviceLevelCode])) {
            $rateLabel = $labelOverrides[$serviceLevelCode];
        }

        if (!empty($priceOverrides[$serviceLevelCode])) {
            $ratePrice = (float)$priceOverrides[$serviceLevelCode];
        }
    }

    /**
     * @return string[]
     */
    public static function getRateOptions(): array
    {
        return [
            "ECO"       => "Economy",
            "LOX"       => "Local Overnight Parcel",
            "OVN"       => "Overnight",
            "L2LXS-ECO" => "TCG Locker L2LXS - ECO",
            "L2LS-ECO"  => "TCG Locker L2LS - ECO",
            "L2LM-ECO"  => "TCG Locker L2LM - ECO",
            "L2LL-ECO"  => "TCG Locker L2LL - ECO",
            "L2LXL-ECO" => "TCG Locker L2LXL - ECO",
            "K2LXS-ECO" => "TCG Locker K2LXS - ECO",
            "K2LS-ECO"  => "TCG Locker K2LS - ECO",
            "K2LM-ECO"  => "TCG Locker K2LM - ECO",
            "K2LL-ECO"  => "TCG Locker K2LL - ECO",
            "K2LXL-ECO" => "TCG Locker K2LXL - ECO",
            "K2KXS-ECO" => "TCG Locker K2KXS - ECO",
            "K2KS-ECO"  => "TCG Locker K2KS - ECO",
            "K2KM-ECO"  => "TCG Locker K2KM - ECO",
            "K2KL-ECO"  => "TCG Locker K2KL - ECO",
            "K2KXL-ECO" => "TCG Locker K2KXL - ECO",
            "K2DXS-ECO" => "TCG Locker K2DXS - ECO",
            "K2DS-ECO"  => "TCG Locker K2DS - ECO",
            "K2DM-ECO"  => "TCG Locker K2DM - ECO",
            "K2DL-ECO"  => "TCG Locker K2DL - ECO",
            "K2DXL-ECO" => "TCG Locker K2DXL - ECO",
            "L2DXS-ECO" => "TCG Locker L2DXS - ECO",
            "L2DS-ECO"  => "TCG Locker L2DS - ECO",
            "L2DM-ECO"  => "TCG Locker L2DM - ECO",
            "L2DL-ECO"  => "TCG Locker L2DL - ECO",
            "L2DXL-ECO" => "TCG Locker L2DXL - ECO",
            "L2KXS-ECO" => "TCG Locker L2KXS - ECO",
            "L2KS-ECO"  => "TCG Locker L2KS - ECO",
            "L2KM-ECO"  => "TCG Locker L2KM - ECO",
            "L2KL-ECO"  => "TCG Locker L2KL - ECO",
            "L2KXL-ECO" => "TCG Locker L2KXL - ECO",
            "D2LXS-ECO" => "TCG Locker D2LXS - ECO",
            "D2LS-ECO"  => "TCG Locker D2LS - ECO",
            "D2LM-ECO"  => "TCG Locker D2LM - ECO",
            "D2LL-ECO"  => "TCG Locker D2LL - ECO",
            "D2LXL-ECO" => "TCG Locker D2LXL - ECO",
            "D2KXS-ECO" => "TCG Locker D2KXS - ECO",
            "D2KS-ECO"  => "TCG Locker D2KS - ECO",
            "D2KM-ECO"  => "TCG Locker D2KM - ECO",
            "D2KL-ECO"  => "TCG Locker D2KL - ECO",
            "D2PXS-ECO" => "TCG Locker D2PXS - ECO",
            "D2PS-ECO"  => "TCG Locker D2PS - ECO",
            "D2PM-ECO"  => "TCG Locker D2PM - ECO",
            "L2PXS-ECO" => "TCG Locker L2PXS - ECO",
            "L2PS-ECO"  => "TCG Locker L2PS - ECO",
            "L2PM-ECO"  => "TCG Locker L2PM - ECO",
        ];
    }
}
