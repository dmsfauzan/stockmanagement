<?php

namespace App\Services\Barcode;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Picqer\Barcode\BarcodeGeneratorSVG;

class LabelService
{
    public function qrSvg(string $data, int $size = 160): string
    {
        $builder = new Builder(writer: new SvgWriter, size: $size, margin: 0, data: $data);

        return $builder->build()->getString();
    }

    public function barcodeSvg(string $data, float $widthFactor = 1.6, int $height = 45): string
    {
        $generator = new BarcodeGeneratorSVG;

        return $generator->getBarcode($data, $generator::TYPE_CODE_128, $widthFactor, $height);
    }

    public function barcodeValue(?string $barcode, ?string $sku): string
    {
        $value = trim((string) ($barcode ?? ''));

        return $value !== '' ? $value : (string) $sku;
    }
}
