import { router } from '@inertiajs/react';
import { show as spkShow } from '@/routes/spk';

type SpkItemNoLinkProps = {
    spkNo: string | null;
    orderReference?: string | null;
};

export function SpkItemNoLink({ spkNo, orderReference }: SpkItemNoLinkProps) {
    if (!spkNo) {
        return null;
    }

    const reference = orderReference?.trim() ?? '';

    return (
        <div className="flex flex-wrap items-baseline gap-x-1.5">
            <button
                type="button"
                className="spkProduksiLink"
                onClick={() => router.visit(spkShow.url(spkNo))}
            >
                {spkNo}
            </button>
            {reference !== '' ? (
                <span className="text-slate-600">| {reference}</span>
            ) : null}
        </div>
    );
}
