import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { MessageStrip } from '@ui5/webcomponents-react/MessageStrip';
import { useEffect, useState } from 'react';
import { SpkItemThumbnail } from '@/components/spk/spk-item-thumbnail';
import {
    SpkTableDescriptionCell,
    SpkTableLastProcessCell,
    SpkTableStatusCell,
    targetDaysLeftHint,
    tipeProduksiBadgeClass,
} from '@/components/spk/spk-list-cells';
import type { SpkIndexRow } from '@/components/spk/types';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { assign as assignStoreStockSpk } from '@/routes/spk/store-stock-requests';
import { list as spkSelectList } from '@/routes/spk/select';

export type StoreStockInputRequest = {
    docNo: string;
};

type ExistingSpkRow = SpkIndexRow;

type SelectListMeta = {
    currentPage: number;
    lastPage: number;
    perPage: number;
    total: number;
};

type SelectListResult = {
    requestKey: string;
    rows: ExistingSpkRow[];
    meta: SelectListMeta | null;
    failed: boolean;
};

type SpkStoreStockInputDialogProps = {
    request: StoreStockInputRequest | null;
    onOpenChange: (open: boolean) => void;
    onAssigned: (message: string) => void;
};

const SEARCH_DEBOUNCE_MS = 300;

function readXsrfToken(): string {
    const row = document.cookie
        .split('; ')
        .find((part) => part.startsWith('XSRF-TOKEN='));

    if (!row) {
        return '';
    }

    return decodeURIComponent(row.slice('XSRF-TOKEN='.length));
}

function firstErrorMessage(payload: {
    message?: string;
    errors?: Record<string, string[]>;
}): string {
    const fieldError = Object.values(payload.errors ?? {})
        .flat()
        .find((message) => message.trim() !== '');

    return fieldError ?? payload.message ?? 'Gagal menghubungkan SPK.';
}

export function SpkStoreStockInputDialog({
    request,
    onOpenChange,
    onAssigned,
}: SpkStoreStockInputDialogProps) {
    return (
        <Dialog
            open={request !== null}
            onOpenChange={onOpenChange}
        >
            <DialogContent className="spkAlertModal spkStatusListModal">
                <DialogHeader>
                    <DialogTitle>SPK Draft</DialogTitle>
                    <DialogDescription>
                        Cari dan pilih SPK yang sudah ada untuk dihubungkan dengan
                        permintaan {request?.docNo}.
                    </DialogDescription>
                </DialogHeader>
                {request !== null ? (
                    <SpkStoreStockInputBody
                        request={request}
                        onCancel={() => onOpenChange(false)}
                        onAssigned={onAssigned}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function SpkStoreStockInputBody({
    request,
    onCancel,
    onAssigned,
}: {
    request: StoreStockInputRequest;
    onCancel: () => void;
    onAssigned: (message: string) => void;
}) {
    const [page, setPage] = useState(1);
    const [searchInput, setSearchInput] = useState('');
    const [search, setSearch] = useState('');
    const [result, setResult] = useState<SelectListResult | null>(null);
    const [selectedRow, setSelectedRow] = useState<ExistingSpkRow | null>(null);
    const [saving, setSaving] = useState(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const requestKey = `${page}|${search}`;
    const loading = result?.requestKey !== requestKey;
    const rows = result?.rows ?? [];
    const meta = result?.meta ?? null;

    useEffect(() => {
        const timeoutId = window.setTimeout(() => {
            if (searchInput.trim() !== search) {
                setSearch(searchInput.trim());
                setPage(1);
            }
        }, SEARCH_DEBOUNCE_MS);

        return () => window.clearTimeout(timeoutId);
    }, [searchInput, search]);

    useEffect(() => {
        const controller = new AbortController();

        fetch(
            spkSelectList.url({
                query: {
                    page: page > 1 ? page : undefined,
                    search: search !== '' ? search : undefined,
                },
            }),
            {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: controller.signal,
            },
        )
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const payload = (await response.json()) as {
                    data?: ExistingSpkRow[];
                    meta?: SelectListMeta;
                };

                setResult({
                    requestKey,
                    rows: Array.isArray(payload.data) ? payload.data : [],
                    meta: payload.meta ?? null,
                    failed: false,
                });
            })
            .catch(() => {
                if (controller.signal.aborted) {
                    return;
                }

                setResult({
                    requestKey,
                    rows: [],
                    meta: null,
                    failed: true,
                });
            });

        return () => controller.abort();
    }, [page, search, requestKey]);

    const assignSelected = async (row: ExistingSpkRow): Promise<void> => {
        if (saving) {
            return;
        }

        setSaving(true);
        setErrorMessage(null);

        try {
            const response = await fetch(assignStoreStockSpk.url(), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': readXsrfToken(),
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    doc_no: request.docNo,
                    spk_no: row.produksiNo,
                }),
            });

            const payload = (await response.json().catch(() => ({}))) as {
                message?: string;
                errors?: Record<string, string[]>;
            };

            if (!response.ok) {
                setErrorMessage(firstErrorMessage(payload));

                return;
            }

            onAssigned(
                payload.message ??
                    `SPK ${row.produksiNo} berhasil dihubungkan ke ${request.docNo}.`,
            );
        } catch {
            setErrorMessage('Gagal menghubungkan SPK. Silakan coba lagi.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <>
            <div className="spkStatusListToolbar">
                <div
                    className="spkTableHeaderSearch--finishing spkStatusListSearch"
                    role="search"
                >
                    <input
                        type="search"
                        className="spkTableHeaderSearchInput--finishing"
                        placeholder="Cari No SPK, pasaran, item..."
                        value={searchInput}
                        onChange={(event) => setSearchInput(event.target.value)}
                        aria-label="Cari nomor SPK"
                        autoFocus
                    />
                    <Icon
                        name={searchIcon}
                        className="spkTableHeaderSearchIcon--finishing"
                    />
                </div>
            </div>

            {errorMessage !== null ? (
                <MessageStrip design="Negative" hideCloseButton>
                    {errorMessage}
                </MessageStrip>
            ) : null}

            <div
                className={[
                    'spkAlertModalBody spkStatusListBody',
                    loading && result !== null ? 'is-loading' : '',
                ]
                    .filter(Boolean)
                    .join(' ')}
            >
                {loading && result === null ? (
                    <p className="spkAlertModalEmpty">Memuat data...</p>
                ) : result?.failed ? (
                    <p className="spkAlertModalEmpty">
                        Gagal memuat data. Silakan coba lagi.
                    </p>
                ) : rows.length === 0 ? (
                    <p className="spkAlertModalEmpty">
                        Tidak ada SPK ditemukan.
                    </p>
                ) : (
                    <table className="spkAlertModalTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Item</th>
                                <th>Dibuat Oleh</th>
                                <th>Tanggal</th>
                                <th className="spkTableColCenter">Target Selesai</th>
                                <th>Proses Terakhir</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => {
                                const isSelected =
                                    selectedRow?.rowId === row.rowId;

                                return (
                                    <tr
                                        key={row.rowId}
                                        tabIndex={0}
                                        aria-selected={isSelected}
                                        className={`spkAlertModalRow${isSelected ? ' is-selected' : ''}`}
                                        onClick={() => setSelectedRow(row)}
                                        onDoubleClick={() => {
                                            void assignSelected(row);
                                        }}
                                        onKeyDown={(event) => {
                                            if (event.key === 'Enter') {
                                                event.preventDefault();
                                                void assignSelected(row);
                                            } else if (event.key === ' ') {
                                                event.preventDefault();
                                                setSelectedRow(row);
                                            }
                                        }}
                                    >
                                        <td className="whitespace-nowrap">
                                            <div className="flex flex-col items-start gap-1.5">
                                                <span className="spkAlertModalTableIdentifier">
                                                    {row.produksiNo}
                                                </span>
                                                <span
                                                    className={`spkTableBadge ${tipeProduksiBadgeClass(row.tipeProduksi)}`}
                                                >
                                                    {row.tipeProduksi}
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div className="flex items-start gap-3">
                                                <SpkItemThumbnail
                                                    imageUrl={row.spkImageUrl}
                                                    spkNo={row.produksiNo}
                                                />
                                                <div className="flex flex-col">
                                                    <span>{row.item}</span>
                                                    <SpkTableDescriptionCell
                                                        row={row}
                                                    />
                                                </div>
                                            </div>
                                        </td>
                                        <td>{row.createdBy ?? '—'}</td>
                                        <td className="whitespace-nowrap">
                                            {row.createdDate}
                                        </td>
                                        <td className="spkTableColCenter whitespace-nowrap">
                                            <div className="flex flex-col">
                                                <span>{row.estimatedDelivery}</span>
                                                {row.targetDaysLeft !== null ? (
                                                    <TargetDaysLeft
                                                        daysLeft={row.targetDaysLeft}
                                                    />
                                                ) : null}
                                            </div>
                                        </td>
                                        <td>
                                            <SpkTableLastProcessCell row={row} />
                                        </td>
                                        <td>
                                            <SpkTableStatusCell row={row} />
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                )}
            </div>

            {meta !== null && meta.total > 0 ? (
                <div className="spkTableFooter spkStatusListFooter">
                    <div className="spkTableTotal">
                        Total {meta.total.toLocaleString('id-ID')} SPK
                    </div>
                    <div className="spkPagination">
                        <button
                            type="button"
                            className="spkPageBtn"
                            disabled={saving || loading || meta.currentPage <= 1}
                            onClick={() => setPage(meta.currentPage - 1)}
                        >
                            Sebelumnya
                        </button>
                        <button type="button" className="spkPageBtn is-active">
                            {meta.currentPage}
                        </button>
                        <button
                            type="button"
                            className="spkPageBtn"
                            disabled={
                                saving ||
                                loading ||
                                meta.currentPage >= meta.lastPage
                            }
                            onClick={() => setPage(meta.currentPage + 1)}
                        >
                            Berikutnya
                        </button>
                    </div>
                </div>
            ) : null}

            <DialogFooter>
                <Button
                    design="Default"
                    type="Button"
                    disabled={saving}
                    onClick={onCancel}
                >
                    Batal
                </Button>
                <Button
                    design="Emphasized"
                    type="Button"
                    disabled={selectedRow === null || saving}
                    onClick={() => {
                        if (selectedRow !== null) {
                            void assignSelected(selectedRow);
                        }
                    }}
                >
                    {saving ? 'Menghubungkan...' : 'Hubungkan'}
                </Button>
            </DialogFooter>
        </>
    );
}

function TargetDaysLeft({ daysLeft }: { daysLeft: number }) {
    const hint = targetDaysLeftHint(daysLeft);

    return <span className={`text-xs ${hint.className}`}>{hint.label}</span>;
}
