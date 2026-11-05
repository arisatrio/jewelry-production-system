import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ReactNode } from 'react';

import {
    formatMicroStoneBalanceCrt,
    formatMicroStonePcs,
} from '@/pages/inventory/batu-mikro/micro-stone-display';

type MicroStoneTransactionFormFieldsProps = {
    idPrefix: string;
    stoneLabel: string;
    balancePcs: string;
    availablePcs: string;
    balanceCrt: string;
    errors: Partial<Record<string, string>>;
    autoFocus?: boolean;
};

function RequiredLabel({
    htmlFor,
    children,
}: {
    htmlFor: string;
    children: ReactNode;
}) {
    return (
        <Label htmlFor={htmlFor}>
            {children}{' '}
            <span className="text-red-600" aria-hidden="true">
                *
            </span>
        </Label>
    );
}

export function MicroStoneTransactionFormFields({
    idPrefix,
    stoneLabel,
    balancePcs,
    availablePcs,
    balanceCrt,
    errors,
    autoFocus = false,
}: MicroStoneTransactionFormFieldsProps) {
    return (
        <>
            <div className="grid gap-1">
                <Label className="text-xs">Batu</Label>
                <p className="text-sm font-medium">{stoneLabel}</p>
            </div>

            <div
                className="microStoneStockSnapshot"
                aria-label="Saldo stok saat ini"
            >
                <p className="microStoneStockSnapshotTitle">
                    Saldo stok saat ini
                </p>
                <dl className="microStoneStockSnapshotGrid">
                    <dt>Saldo (pcs)</dt>
                    <dd className="tabular-nums">
                        {formatMicroStonePcs(balancePcs)}
                    </dd>
                    <dt>Tersedia (pcs)</dt>
                    <dd className="tabular-nums">
                        {formatMicroStonePcs(availablePcs)}
                    </dd>
                    <dt>Saldo (crt)</dt>
                    <dd className="tabular-nums">
                        {formatMicroStoneBalanceCrt(balanceCrt)}
                    </dd>
                </dl>
            </div>

            <div className="grid gap-2">
                <RequiredLabel htmlFor={`${idPrefix}-pcs`}>
                    Jumlah (pcs)
                </RequiredLabel>
                <Input
                    id={`${idPrefix}-pcs`}
                    name="pcs"
                    type="number"
                    required
                    min="0.01"
                    step="0.01"
                    autoFocus={autoFocus}
                />
                <InputError message={errors.pcs} />
            </div>

            <div className="grid gap-2">
                <RequiredLabel htmlFor={`${idPrefix}-crt`}>CRT</RequiredLabel>
                <Input
                    id={`${idPrefix}-crt`}
                    name="crt"
                    type="number"
                    required
                    min="0.0001"
                    step="0.0001"
                />
                <InputError message={errors.crt} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}-notes`}>Catatan</Label>
                <Input
                    id={`${idPrefix}-notes`}
                    name="notes"
                    maxLength={100}
                />
                <InputError message={errors.notes} />
            </div>
        </>
    );
}
