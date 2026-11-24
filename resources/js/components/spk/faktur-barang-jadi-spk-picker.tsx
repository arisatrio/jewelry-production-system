import { router } from '@inertiajs/react';
import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { MessageStrip } from '@ui5/webcomponents-react/MessageStrip';
import { useEffect, useMemo, useState } from 'react';
import { FakturBarangJadiStatusBadge } from '@/components/spk/faktur-barang-jadi-status-badge';
import { SpkFotoBrjCell } from '@/components/spk/spk-foto-brj-cell';
import { SpkItemSkuColumn } from '@/components/spk/spk-item-sku-column';
import type { SpkIndexRow } from '@/components/spk/types';
import { Checkbox } from '@/components/ui/checkbox';
import { create as fakturBarangJadiCreate } from '@/routes/faktur-barang-jadi';

type ProductionsPaginator = {
    data: SpkIndexRow[];
    total: number;
    current_page: number;
    last_page: number;
    per_page: number;
};

type FakturBarangJadiSpkPickerProps = {
    productions: ProductionsPaginator;
    filters: {
        search: string;
        faktur_belum?: boolean;
        per_page: number;
    };
    statusCounts: {
        belumDibuatFaktur: number;
        doneRangka: number;
        doneBarangJadi: number;
    };
    fakturBarangJadiFotoBrjAvailable?: boolean | null;
    selectedIds: number[];
    onSelectedIdsChange: (ids: number[]) => void;
};

export const FAKTUR_BARANG_JADI_CREATE_INFO =
    'Hanya SPK produksi selesai — DONE (Rangka) atau DONE (Barang Jadi). Reparasi dan SPK yang belum selesai tidak ditampilkan.';

const SEARCH_DEBOUNCE_MS = 300;

function isRowSelectable(row: SpkIndexRow): boolean {
    return row.fakturBarangJadiIssued !== true;
}

export function FakturBarangJadiSpkPicker({
    productions,
    filters,
    statusCounts,
    fakturBarangJadiFotoBrjAvailable,
    selectedIds,
    onSelectedIdsChange,
}: FakturBarangJadiSpkPickerProps) {
    const [searchQuery, setSearchQuery] = useState(filters.search);

    const selectablePageIds = useMemo(
        () =>
            productions.data
                .filter(isRowSelectable)
                .map((row) => row.rowId),
        [productions.data],
    );

    const allPageSelected =
        selectablePageIds.length > 0 &&
        selectablePageIds.every((id) => selectedIds.includes(id));
    const somePageSelected = selectablePageIds.some((id) =>
        selectedIds.includes(id),
    );

    const totalPages = Math.max(1, productions.last_page);
    const fotoBrjColumn =
        fakturBarangJadiFotoBrjAvailable !== false;

    useEffect(() => {
        setSearchQuery(filters.search);
    }, [filters.search]);

    const visitPicker = (params: {
        search?: string;
        faktur_belum?: boolean;
        page?: number;
    }) => {
        const nextSearch = params.search ?? filters.search;
        const fakturBelum =
            params.faktur_belum !== undefined
                ? params.faktur_belum
                : (filters.faktur_belum ?? false);

        router.get(
            fakturBarangJadiCreate.url({
                query: {
                    search: nextSearch || undefined,
                    faktur_belum: fakturBelum ? 1 : undefined,
                    page:
                        (params.page ?? productions.current_page) > 1
                            ? params.page ?? productions.current_page
                            : undefined,
                    per_page:
                        filters.per_page !== 50 ? filters.per_page : undefined,
                },
            }),
            {},
            { preserveState: true, replace: true },
        );
    };

    useEffect(() => {
        const timeoutId = window.setTimeout(() => {
            if (searchQuery === filters.search) {
                return;
            }

            visitPicker({ search: searchQuery, page: 1 });
        }, SEARCH_DEBOUNCE_MS);

        return () => window.clearTimeout(timeoutId);
        // eslint-disable-next-line react-hooks/exhaustive-deps -- debounce search
    }, [searchQuery, filters.search]);

    const toggleSelectAll = (checked: boolean | 'indeterminate') => {
        if (checked === true) {
            onSelectedIdsChange([
                ...new Set([...selectedIds, ...selectablePageIds]),
            ]);

            return;
        }

        onSelectedIdsChange(
            selectedIds.filter((id) => !selectablePageIds.includes(id)),
        );
    };

    const toggleSelectRow = (
        id: number,
        checked: boolean | 'indeterminate',
    ) => {
        onSelectedIdsChange(
            checked === true
                ? selectedIds.includes(id)
                    ? selectedIds
                    : [...selectedIds, id]
                : selectedIds.filter((itemId) => itemId !== id),
        );
    };

    return (
        <div className="grid gap-3">
            <MessageStrip design="Information" hideCloseButton>
                {FAKTUR_BARANG_JADI_CREATE_INFO}
            </MessageStrip>

            <div className="flex flex-wrap items-center gap-2">
                <div
                    className="spkTableHeaderSearch--finishing flex-1 min-w-[12rem]"
                    role="search"
                >
                    <input
                        type="search"
                        className="spkTableHeaderSearchInput--finishing w-full"
                        placeholder="Cari SPK..."
                        value={searchQuery}
                        autoComplete="off"
                        aria-label="Cari SPK"
                        onChange={(event) =>
                            setSearchQuery(event.target.value)
                        }
                    />
                    <Icon
                        name={searchIcon}
                        className="spkTableHeaderSearchIcon--finishing"
                    />
                </div>
                <button
                    type="button"
                    className={[
                        'spkTableStatusAlertBtn--finishing',
                        filters.faktur_belum ? 'is-active' : '',
                    ]
                        .filter(Boolean)
                        .join(' ')}
                    onClick={() =>
                        visitPicker({
                            faktur_belum: !filters.faktur_belum,
                            page: 1,
                        })
                    }
                >
                    <MessageStrip
                        design="Information"
                        hideCloseButton
                        className="spkTableStatusAlertStrip--finishing"
                    >
                        <span className="spkTableStatusAlertLabel--finishing">
                            Belum Dibuat Faktur
                        </span>
                        <strong className="spkTableStatusAlertCount--finishing">
                            {statusCounts.belumDibuatFaktur.toLocaleString(
                                'id-ID',
                            )}
                        </strong>
                    </MessageStrip>
                </button>
            </div>

            <div className="spkTableScroll spkTableScroll--finishing">
                <table className="spkTable">
                    <thead>
                        <tr>
                            <th className="spkTableColCheck--finishing">
                                <Checkbox
                                    checked={
                                        allPageSelected
                                            ? true
                                            : somePageSelected
                                              ? 'indeterminate'
                                              : false
                                    }
                                    onCheckedChange={toggleSelectAll}
                                    aria-label="Pilih semua SPK di halaman"
                                    disabled={selectablePageIds.length === 0}
                                />
                            </th>
                            <th className="spkTableColDoc--spk">ID</th>
                            <th>Item</th>
                            <th className="spkTableColCenter">
                                Proses Terakhir
                            </th>
                            <th className="spkTableColCenter">Status</th>
                            {fotoBrjColumn ? (
                                <th className="spkTableColCenter spkTableColFotoBrj">
                                    Foto Barang Jadi
                                </th>
                            ) : null}
                            <th className="spkTableColCenter">Lokasi</th>
                            <th className="spkTableColCenter">
                                Status Faktur
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {productions.data.length === 0 ? (
                            <tr>
                                <td colSpan={fotoBrjColumn ? 8 : 7}>
                                    Tidak ada SPK yang memenuhi syarat.
                                </td>
                            </tr>
                        ) : (
                            productions.data.map((row) => {
                                const selectable = isRowSelectable(row);
                                const fakturStatus =
                                    row.fakturBarangJadiStatus ?? null;

                                return (
                                    <tr
                                        key={row.rowId}
                                        className={[
                                            selectedIds.includes(row.rowId)
                                                ? 'is-selected'
                                                : '',
                                            fakturStatus === 'received'
                                                ? 'is-faktur-barang-jadi-received'
                                                : fakturStatus === 'sent'
                                                  ? 'is-faktur-barang-jadi-sent'
                                                  : fakturStatus === 'created'
                                                    ? 'is-faktur-barang-jadi-created'
                                                    : 'is-faktur-barang-jadi-pending',
                                        ]
                                            .filter(Boolean)
                                            .join(' ') || undefined}
                                    >
                                        <td className="spkTableColCheck--finishing">
                                            <Checkbox
                                                checked={selectedIds.includes(
                                                    row.rowId,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    toggleSelectRow(
                                                        row.rowId,
                                                        checked,
                                                    )
                                                }
                                                aria-label={`Pilih ${row.produksiNo}`}
                                                disabled={!selectable}
                                            />
                                        </td>
                                        <td className="font-medium">
                                            {row.produksiNo}
                                        </td>
                                        <td>
                                            <SpkItemSkuColumn
                                                typeCode={row.typeCode}
                                                productItemName={
                                                    row.productItemName
                                                }
                                                skuCode={row.skuCode}
                                                itemDescription={
                                                    row.itemDescription
                                                }
                                            />
                                        </td>
                                        <td className="spkTableColCenter text-sm">
                                            {row.prosesTerakhirDisplay ??
                                                row.prosesTerakhir}
                                        </td>
                                        <td className="spkTableColCenter">
                                            <span className="spkTableBadge spkTableBadge--done">
                                                {row.status}
                                            </span>
                                        </td>
                                        {fotoBrjColumn ? (
                                            <td className="spkTableColCenter">
                                                <SpkFotoBrjCell
                                                    rowId={row.rowId}
                                                    spkNo={row.produksiNo}
                                                    imageUrl={
                                                        row.fotoBrjImageUrl ??
                                                        null
                                                    }
                                                    uploadEnabled={
                                                        fakturBarangJadiFotoBrjAvailable !==
                                                        false
                                                    }
                                                />
                                            </td>
                                        ) : null}
                                        <td className="spkTableColCenter">
                                            {row.location ?? '—'}
                                        </td>
                                        <td className="spkTableColCenter">
                                            {fakturStatus ? (
                                                <FakturBarangJadiStatusBadge
                                                    status={fakturStatus}
                                                />
                                            ) : (
                                                <span className="text-muted-foreground text-xs">
                                                    Belum dibuat
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })
                        )}
                    </tbody>
                </table>
            </div>

            <div className="spkTableFooter">
                <div className="spkTableTotal">
                    {selectedIds.length > 0
                        ? `${selectedIds.length} SPK terpilih · `
                        : ''}
                    Total {productions.total.toLocaleString('id-ID')} SPK
                </div>
                <div className="spkPagination">
                    <button
                        type="button"
                        className="spkPageBtn"
                        disabled={productions.current_page <= 1}
                        onClick={() =>
                            visitPicker({
                                page: productions.current_page - 1,
                            })
                        }
                    >
                        Sebelumnya
                    </button>
                    <button
                        type="button"
                        className="spkPageBtn is-active"
                    >
                        {productions.current_page}
                    </button>
                    <button
                        type="button"
                        className="spkPageBtn"
                        disabled={productions.current_page >= totalPages}
                        onClick={() =>
                            visitPicker({
                                page: productions.current_page + 1,
                            })
                        }
                    >
                        Berikutnya
                    </button>
                </div>
            </div>
        </div>
    );
}