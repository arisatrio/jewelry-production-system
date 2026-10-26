import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { list as spkSelectList } from '@/routes/spk/select';

type SpkOption = {
    rowId: number;
    produksiNo: string;
    customer: string;
    item: string;
};

type ChainIssueSpkPickerProps = {
    error?: string;
};

export function ChainIssueSpkPicker({ error }: ChainIssueSpkPickerProps) {
    const [search, setSearch] = useState('');
    const [rows, setRows] = useState<SpkOption[]>([]);
    const [loading, setLoading] = useState(false);
    const [selected, setSelected] = useState<SpkOption | null>(null);
    const [open, setOpen] = useState(false);

    useEffect(() => {
        if (!open) {
            return;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(() => {
            setLoading(true);

            void fetch(
                spkSelectList.url({
                    query: {
                        search: search !== '' ? search : undefined,
                        limit: 15,
                    },
                }),
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    signal: controller.signal,
                },
            )
                .then(async (response) => {
                    if (!response.ok) {
                        throw new Error('Gagal memuat SPK');
                    }

                    const payload = (await response.json()) as {
                        data?: SpkOption[];
                    };

                    setRows(payload.data ?? []);
                })
                .catch((fetchError: unknown) => {
                    if (
                        fetchError instanceof DOMException &&
                        fetchError.name === 'AbortError'
                    ) {
                        return;
                    }

                    setRows([]);
                })
                .finally(() => {
                    setLoading(false);
                });
        }, 300);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [open, search]);

    const pick = (row: SpkOption) => {
        setSelected(row);
        setSearch(row.produksiNo);
        setOpen(false);
    };

    const clear = () => {
        setSelected(null);
        setSearch('');
        setOpen(true);
    };

    return (
        <div className="grid min-w-[14rem] gap-1">
            <Label htmlFor="chain-issue-spk-search" className="text-xs">
                SPK
            </Label>
            <input
                type="hidden"
                name="spk_id"
                value={selected?.rowId ?? ''}
                required
            />
            <div className="relative">
                <Input
                    id="chain-issue-spk-search"
                    type="search"
                    autoComplete="off"
                    placeholder="Cari nomor SPK…"
                    value={search}
                    onChange={(event) => {
                        setSearch(event.target.value);
                        setSelected(null);
                        setOpen(true);
                    }}
                    onFocus={() => setOpen(true)}
                />
                {selected !== null && (
                    <button
                        type="button"
                        className="absolute top-1/2 right-2 -translate-y-1/2 text-xs text-muted-foreground hover:text-foreground"
                        onClick={clear}
                    >
                        Ubah
                    </button>
                )}
                {open && selected === null && (
                    <ul
                        className="absolute z-10 mt-1 max-h-48 w-full overflow-auto rounded-md border bg-background text-sm shadow-md"
                        role="listbox"
                    >
                        {loading ? (
                            <li className="px-3 py-2 text-muted-foreground">
                                Memuat…
                            </li>
                        ) : rows.length === 0 ? (
                            <li className="px-3 py-2 text-muted-foreground">
                                Tidak ada SPK.
                            </li>
                        ) : (
                            rows.map((row) => (
                                <li key={row.rowId}>
                                    <button
                                        type="button"
                                        role="option"
                                        className="w-full px-3 py-2 text-left hover:bg-muted"
                                        onClick={() => pick(row)}
                                    >
                                        <span className="font-medium">
                                            {row.produksiNo}
                                        </span>
                                        <span className="mt-0.5 block text-xs text-muted-foreground">
                                            {row.customer} · {row.item}
                                        </span>
                                    </button>
                                </li>
                            ))
                        )}
                    </ul>
                )}
            </div>
            <InputError message={error} />
        </div>
    );
}
