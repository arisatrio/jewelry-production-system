<?php

namespace App\Support;

use App\Models\MsChain;
use App\Models\SkuMaster;
use Illuminate\Support\Facades\Schema;

class SkuMasterChainPrefill
{
    public function skuHasChainColumn(): bool
    {
        return Schema::connection('second')->hasColumn('sku_master', 'chain_id');
    }

    /**
     * @return list<array{
     *     id: string,
     *     chainId: string,
     *     category: string,
     *     item: string,
     *     color: string,
     *     size: string
     * }>
     */
    public function formRowsFromSku(?SkuMaster $sku): array
    {
        if ($sku === null || ! $this->skuHasChainColumn()) {
            return [];
        }

        $msChainId = (int) ($sku->chain_id ?? 0);

        if ($msChainId <= 0 || ! Schema::connection('third')->hasTable('mschain')) {
            return [];
        }

        $msChain = MsChain::query()->notDeleted()->find($msChainId);

        if ($msChain === null) {
            return [];
        }

        $item = filled($msChain->item_name)
            ? trim((string) $msChain->item_name)
            : trim((string) $msChain->item);

        if ($item === '' && filled($msChain->item_code)) {
            $item = trim((string) $msChain->item_code);
        }

        return [[
            'id' => 'sku-chain-0',
            'chainId' => (string) $msChain->row_id,
            'category' => trim((string) ($msChain->chain_category ?? '')),
            'item' => $item,
            'color' => $this->resolveColorLabel($sku),
            'size' => $this->formatChainLength($msChain->length),
        ]];
    }

    private function resolveColorLabel(SkuMaster $sku): string
    {
        $color = trim((string) ($sku->resolvedGoldColor() ?? ''));

        return $color !== '' ? $color : '';
    }

    private function formatChainLength(mixed $length): string
    {
        if ($length === null || $length === '') {
            return '';
        }

        $numeric = (float) $length;

        if ($numeric <= 0) {
            return '';
        }

        $formatted = number_format($numeric, 2, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}
