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
    filters: {
        search: string;
        per_page: number;
    };
};

export default function SpkIndex({ productions, filters }: SpkIndexProps) {
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
    }, [searchQuery, filters.search, filters.per_page]);

    const visit = (params: {
        page?: number;
        per_page?: number;
        search?: string;
    }) => {
        router.get(
            spkIndex.url({
                query: {
                    search: params.search || undefined,
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
