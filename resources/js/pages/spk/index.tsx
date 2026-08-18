import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import SpkTable from '@/components/spk/spk-table';
import type { SpkRow } from '@/components/spk/types';
import {
    create as spkCreate,
    index as spkIndex,
    show as spkShow,
} from '@/routes/spk';

type ProductionsPaginator = {
    data: SpkRow[];
    total: number;
    current_page: number;
    per_page: number;
};

type SpkIndexProps = {
    productions: ProductionsPaginator;
    types: string[];
    typeCounts: {
        all: number;
        byType: Record<string, number>;
    };
    statuses: string[];
    filters: {
        search: string;
        type: string;
        status: string;
        per_page: number;
    };
};

export default function SpkIndex({
    productions,
    types,
    typeCounts,
    statuses,
    filters,
}: SpkIndexProps) {
    const [searchQuery, setSearchQuery] = useState(filters.search);

    useEffect(() => {
        setSearchQuery(filters.search);
    }, [filters.search]);

    useEffect(() => {
        const timeout = window.setTimeout(() => {
            if (searchQuery === filters.search) {
                return;
            }

            router.get(
                spkIndex.url({
                    query: {
                        search: searchQuery || undefined,
                        type: filters.type || undefined,
                        status: filters.status || undefined,
                        per_page: filters.per_page,
                        page: 1,
                    },
                }),
                {},
                {
                    preserveState: true,
                    replace: true,
                },
            );
        }, 300);

        return () => window.clearTimeout(timeout);
    }, [searchQuery, filters.search, filters.type, filters.per_page]);

    const visit = (params: {
        page?: number;
        per_page?: number;
        search?: string;
        type?: string;
        status?: string;
    }) => {
        const type = params.type ?? filters.type;
        const status = params.status ?? filters.status;

        router.get(
            spkIndex.url({
                query: {
                    search: params.search || undefined,
                    type: type || undefined,
                    status: status || undefined,
                    per_page: params.per_page ?? filters.per_page,
                    page: params.page ?? 1,
                },
            }),
            {},
            {
                preserveState: true,
                replace: true,
            },
        );
    };

    return (
        <>
            <Head title="SPK" />
            <SpkTable
                rows={productions.data}
                total={productions.total}
                pageSize={productions.per_page}
                currentPage={productions.current_page}
                searchQuery={searchQuery}
                onSearchChange={setSearchQuery}
                onPageSizeChange={(size) =>
                    visit({
                        page: 1,
                        per_page: size,
                        search: searchQuery,
                    })
                }
                onPageChange={(page) =>
                    visit({
                        page,
                        per_page: productions.per_page,
                        search: searchQuery,
                    })
                }
                onCreate={() => router.visit(spkCreate.url())}
                types={types}
                typeCounts={typeCounts}
                selectedType={filters.type}
                onTypeChange={(type) =>
                    visit({
                        page: 1,
                        per_page: productions.per_page,
                        search: searchQuery,
                        type,
                    })
                }
                statuses={statuses}
                selectedStatus={filters.status}
                onStatusChange={(status) =>
                    visit({
                        page: 1,
                        per_page: productions.per_page,
                        search: searchQuery,
                        status,
                    })
                }
                onOpenRow={(row) => router.visit(spkShow.url(row.produksiNo))}
                onProduksiNoClick={(row) =>
                    router.visit(spkShow.url(row.produksiNo))
                }
            />
        </>
    );
}

SpkIndex.layout = {
    activeMenu: 'SPK',
};
