import type { FormEvent } from 'react';
import { useMemo, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import declineIcon from '@ui5/webcomponents-icons/dist/decline.js';
import saveIcon from '@ui5/webcomponents-icons/dist/save.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { Text } from '@ui5/webcomponents-react/Text';
import { AlokasiBatuDaftarBatuForm } from '@/components/alokasi-batu/alokasi-batu-daftar-batu-form';
import {
    distributeInitialAllocations,
    flattenAllocationsForSubmit,
    remapAllocationFormErrors,
    type AllocationsBySpkStoneId,
} from '@/components/alokasi-batu/alokasi-batu-allocation-state';
import type {
    PasangBatuDiamondOption,
    PasangBatuStoneOption,
} from '@/components/pasang-batu/pasang-batu-stone-editor';
import { SpkItemSkuColumn } from '@/components/spk/spk-item-sku-column';
import { SpkItemThumbnail } from '@/components/spk/spk-item-thumbnail';
import type { SpkStoneItem } from '@/components/spk/types';
import { index as alokasiIndex, update } from '@/routes/alokasi-batu';

type ProductionHeader = {
    spkId: number;
    spkNo: string;
    customerName: string | null;
    itemName: string | null;
    orderReference: string | null;
    orderTypeLabel: string;
    skuCode: string | null;
    typeCode: string | null;
    productItemName: string | null;
    spkImageUrl: string | null;
    qty: number;
    satuan: string;
};

type AlokasiBatuShowProps = {
    production: ProductionHeader;
    skuStones: SpkStoneItem[];
    stoneOptions: PasangBatuStoneOption[];
    diamondOptions: PasangBatuDiamondOption[];
    form: {
        settingStones: Array<{
            stone_id: string;
            pcs: string;
            crt: string;
            notes: string;
        }>;
        diamonds: Array<{ diamond_id: string }>;
    };
};

export default function AlokasiBatuShow({
    production,
    skuStones,
    stoneOptions,
    diamondOptions,
    form,
}: AlokasiBatuShowProps) {
    const [allocationsByStoneId, setAllocationsByStoneId] =
        useState<AllocationsBySpkStoneId>(() =>
            distributeInitialAllocations(
                skuStones,
                form.settingStones,
                form.diamonds,
            ),
        );

    const { put, processing, transform, errors } = useForm({});

    const displayErrors = useMemo(
        () =>
            remapAllocationFormErrors(
                errors as Record<string, string>,
                skuStones,
                allocationsByStoneId,
            ),
        [errors, skuStones, allocationsByStoneId],
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const { setting_stones, diamonds } = flattenAllocationsForSubmit(
            skuStones,
            allocationsByStoneId,
        );

        transform(() => ({
            setting_stones: setting_stones
                .filter((line) => line.stone_id.trim() !== '')
                .map((line) => ({
                    stone_id: Number(line.stone_id),
                    pcs:
                        line.pcs.trim() !== ''
                            ? String(
                                  Math.round(
                                      Number(
                                          line.pcs.trim().replace(',', '.'),
                                      ),
                                  ),
                              )
                            : null,
                    crt:
                        line.crt.trim() !== ''
                            ? line.crt.replace(',', '.')
                            : null,
                    notes:
                        line.notes.trim() !== ''
                            ? line.notes.trim()
                            : null,
                })),
            diamonds: diamonds
                .filter((line) => line.diamond_id.trim() !== '')
                .map((line) => ({ diamond_id: Number(line.diamond_id) })),
        }));

        put(update.url(production.spkNo), { preserveScroll: true });
    };

    return (
        <>
            <Head title={`Alokasi Batu — ${production.spkNo}`} />
            <form onSubmit={submit} className="spkFioriForm">
                <section className="spkFioriFormCard">
                    <div className="spkFioriFormCardHeader">
                        <h2 className="spkFioriFormCardTitle">
                            Alokasi — {production.spkNo}
                        </h2>
                        <div className="spkFioriFormCardActions">
                            <Button
                                design="Default"
                                icon={declineIcon}
                                onClick={() =>
                                    router.visit(alokasiIndex.url())
                                }
                            >
                                Kembali
                            </Button>
                            <Button
                                design="Emphasized"
                                icon={saveIcon}
                                type="Submit"
                                disabled={processing}
                            >
                                Simpan
                            </Button>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-start gap-4 p-4">
                        <SpkItemThumbnail
                            imageUrl={production.spkImageUrl}
                            alt={production.spkNo}
                        />
                        <div className="min-w-0 flex-1 space-y-1">
                            <Text>
                                {production.orderTypeLabel} ·{' '}
                                {production.orderReference ?? '—'}
                            </Text>
                            <SpkItemSkuColumn
                                typeCode={production.typeCode}
                                productItemName={production.productItemName}
                                skuCode={production.skuCode}
                                itemDescription={production.itemName}
                            />
                            <Text>
                                {production.customerName ?? '—'} · Qty{' '}
                                {production.qty} {production.satuan}
                            </Text>
                        </div>
                    </div>
                </section>

                <AlokasiBatuDaftarBatuForm
                    skuStones={skuStones}
                    stoneOptions={stoneOptions}
                    diamondOptions={diamondOptions}
                    allocations={allocationsByStoneId}
                    errors={displayErrors}
                    onAllocationsChange={setAllocationsByStoneId}
                />
            </form>
        </>
    );
}

AlokasiBatuShow.layout = {
    activeMenu: 'Alokasi Batu',
};
