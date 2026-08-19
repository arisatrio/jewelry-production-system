<?php

namespace App\Support;

use App\Models\SkuMaster;
use Illuminate\Support\Str;

class SkuMasterDescriptionExtractor
{
    /**
     * Build SPK item description from SKU component names.
     *
     * Format: {gold} {category} {name} {size?} {shape?} {stoneType?} {diamondType?} {crt?}
     * Size "REGULAR" is omitted to keep descriptions concise.
     */
    public function extract(SkuMaster $sku): string
    {
        $parts = [];

        $goldColor = $this->displayText($sku->goldColorPrefix?->gold_color);

        if ($goldColor !== '') {
            $parts[] = $goldColor;
        }

        $category = $this->displayText($sku->categoryPrefix?->category);

        if ($category !== '') {
            $parts[] = $category;
        }

        $name = $this->displayText($sku->namePrefix?->name);

        if ($name !== '') {
            $parts[] = $name;
        }

        $size = $this->displayText($sku->sizePrefix?->size);

        if ($size !== '' && strtoupper($size) !== 'REGULAR') {
            $parts[] = $size;
        }

        $shape = $this->displayText($sku->stoneShapePrefix?->stone_shape);

        if ($shape !== '') {
            $parts[] = $shape;
        }

        $stoneType = $this->displayText($sku->stoneTypePrefix?->stone_type);

        if ($stoneType !== '') {
            $parts[] = $stoneType;
        }

        $diamondType = $this->displayText($sku->diamondTypePrefix?->diamond_type);

        if ($diamondType !== '') {
            $parts[] = $diamondType;
        }

        $crt = $this->formatCrt($sku->crt);

        if ($crt !== '') {
            $parts[] = $crt;
        }

        if ($parts === []) {
            return trim((string) ($sku->item_original ?? ''));
        }

        return implode(' ', $parts);
    }

    private function formatCrt(mixed $crt): string
    {
        if ($crt === null || $crt === '') {
            return '';
        }

        $numeric = (float) $crt;

        if ($numeric <= 0) {
            return '';
        }

        return rtrim(rtrim(number_format($numeric, 2, '.', ''), '0'), '.');
    }

    private function displayText(mixed $value): string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return '';
        }

        return Str::of($text)->lower()->title()->toString();
    }
}
