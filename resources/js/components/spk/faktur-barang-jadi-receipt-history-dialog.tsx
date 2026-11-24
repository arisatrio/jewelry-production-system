import { router } from '@inertiajs/react';
import searchIcon from '@ui5/webcomponents-icons/dist/search.js';
import { Icon } from '@ui5/webcomponents-react/Icon';
import { useEffect, useState } from 'react';
import {
    FakturBarangJadiReceiptList,
    type FakturBarangJadiReceiptMeta,
    type FakturBarangJadiReceiptRow,
} from '@/components/spk/faktur-barang-jadi-receipt-list';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { index as fakturBarangJadiReceiptHistory } from '@/routes/faktur-barang-jadi/print/receipt';

type ReceiptHistoryResult = {
    requestKey: string;
    rows: FakturBarangJadiReceiptRow[];
    meta: FakturBarangJadiReceiptMeta | null;
    failed: boolean;
};

type FakturBarangJadiReceiptHistoryDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    employeeOptions: string[];
    onWorkflowChanged?: () => void;
};

const SEARCH_DEBOUNCE_MS = 300;

export function FakturBarangJadiReceiptHistoryDialog({
    open,
    onOpenChange,
    employeeOptions,
    onWorkflowChanged,
}: FakturBarangJadiReceiptHistoryDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="spkAlertModal spkStatusListModal">
                <DialogHeader>
                    <DialogTitle>Riwayat Faktur Barang Jadi</DialogTitle>
                    <DialogDescription>
                        Kelola alur faktur: dibuat → dikirim → diterima. Klik
                        baris atau tombol cetak untuk preview.
                    </DialogDescription>
                </DialogHeader>
                {open ? (
                    <FakturBarangJadiReceiptHistoryBody
                        employeeOptions={employeeOptions}
                        onWorkflowChanged={onWorkflowChanged}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function FakturBarangJadiReceiptHistoryBody({
    employeeOptions,
    onWorkflowChanged,
}: {
    employeeOptions: string[];
    onWorkflowChanged?: () => void;
}) {
    const [page, setPage] = useState(1);
    const [searchInput, setSearchInput] = useState('');
    const [search, setSearch] = useState('');
    const [result, setResult] = useState<ReceiptHistoryResult | null>(null);
    const [reloadToken, setReloadToken] = useState(0);
    const requestKey = `${page}|${search}|${reloadToken}`;
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
        const query: Record<string, string | number> = {};

        if (page > 1) {
            query.page = page;
        }

        if (search !== '') {
            query.search = search;
        }

        fetch(fakturBarangJadiReceiptHistory.url({ query }), {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                return response.json() as Promise<{
                    data: FakturBarangJadiReceiptRow[];
                    meta: FakturBarangJadiReceiptMeta;
                }>;
            })
            .then((payload) => {
                setResult({
                    requestKey,
                    rows: payload.data,
                    meta: payload.meta,
                    failed: false,
                });
            })
            .catch((error: unknown) => {
                if (error instanceof DOMException && error.name === 'AbortError') {
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
    }, [page, search, requestKey, reloadToken]);

    const notifyWorkflowChanged = (): void => {
        setReloadToken((current) => current + 1);
        onWorkflowChanged?.();
        router.reload({ only: ['productions', 'statusCounts'] });
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
                        placeholder="Cari no. faktur, SPK, nama..."
                        value={searchInput}
                        onChange={(event) => setSearchInput(event.target.value)}
                        aria-label="Cari riwayat faktur"
                    />
                    <Icon
                        name={searchIcon}
                        className="spkTableHeaderSearchIcon--finishing"
                    />
                </div>
            </div>

            <FakturBarangJadiReceiptList
                employeeOptions={employeeOptions}
                rows={rows}
                meta={meta}
                page={page}
                onPageChange={setPage}
                loading={loading}
                failed={result?.failed ?? false}
                onWorkflowChanged={notifyWorkflowChanged}
                layout="modal"
            />
        </>
    );
}