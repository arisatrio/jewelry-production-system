import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { update } from '@/routes/master-data/spk-process-sla';

type SlaTargetRow = {
    processKey: string;
    label: string;
    workingDays: number;
    updatedBy: string | null;
    updatedAt: string | null;
};

type SpkProcessSlaEditProps = {
    targets: SlaTargetRow[];
    totalWorkingDays: number;
};

type SlaFormTarget = {
    process_key: string;
    working_days: string;
};

export default function SpkProcessSlaEdit({
    targets,
    totalWorkingDays,
}: SpkProcessSlaEditProps) {
    const form = useForm<{ targets: SlaFormTarget[] }>({
        targets: targets.map((row) => ({
            process_key: row.processKey,
            working_days: String(row.workingDays),
        })),
    });

    const handleWorkingDaysChange = (index: number, value: string): void => {
        form.setData(
            'targets',
            form.data.targets.map((row, rowIndex) =>
                rowIndex === index
                    ? { ...row, working_days: value }
                    : row,
            ),
        );
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        form.put(update.url(), {
            preserveScroll: true,
        });
    };

    const liveTotal = form.data.targets.reduce((sum, row) => {
        const days = Number.parseInt(row.working_days, 10);

        return sum + (Number.isFinite(days) ? days : 0);
    }, 0);

    return (
        <>
            <Head title="SLA Proses SPK" />
            <div className="masterDataPage">
                <div className="masterDataHeader">
                    <div>
                        <h1 className="masterDataTitle">SLA Proses SPK</h1>
                        <p className="masterDataSubtitle">
                            Atur target hari kerja (Senin–Jumat) untuk setiap
                            proses produksi SPK.
                        </p>
                    </div>
                </div>

                <div className="spkTableCard">
                    <form onSubmit={handleSubmit}>
                        <div className="spkTableToolbar">
                            <div className="spkTableToolbarLeft">
                                <span className="masterDataSubtitle">
                                    Total target:{' '}
                                    <strong>{liveTotal}</strong> hari kerja
                                    {liveTotal !== totalWorkingDays
                                        ? ` (tersimpan: ${totalWorkingDays})`
                                        : ''}
                                </span>
                            </div>
                            <div className="spkTableToolbarRight">
                                <Button
                                    type="submit"
                                    disabled={form.processing || !form.isDirty}
                                >
                                    {form.processing
                                        ? 'Menyimpan...'
                                        : 'Simpan SLA'}
                                </Button>
                            </div>
                        </div>

                        <InputError message={form.errors.targets} />

                        <div className="spkTableScroll">
                            <table className="spkTable masterDataTable">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Proses</th>
                                        <th>Target (hari kerja)</th>
                                        <th>Terakhir diubah</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {targets.map((row, index) => {
                                        const fieldError =
                                            form.errors[
                                                `targets.${index}.working_days`
                                            ];

                                        return (
                                            <tr key={row.processKey}>
                                                <td>{index + 1}</td>
                                                <td>
                                                    {row.label}
                                                    <input
                                                        type="hidden"
                                                        name={`targets[${index}][process_key]`}
                                                        value={row.processKey}
                                                    />
                                                </td>
                                                <td>
                                                    <Input
                                                        type="number"
                                                        min={0}
                                                        max={365}
                                                        step={1}
                                                        required
                                                        className="masterDataSearch"
                                                        value={
                                                            form.data.targets[
                                                                index
                                                            ]?.working_days ??
                                                            ''
                                                        }
                                                        onChange={(event) =>
                                                            handleWorkingDaysChange(
                                                                index,
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        aria-label={`Target hari kerja ${row.label}`}
                                                    />
                                                    <InputError
                                                        message={fieldError}
                                                    />
                                                </td>
                                                <td>
                                                    {row.updatedAt ?? '—'}
                                                    {row.updatedBy
                                                        ? ` · ${row.updatedBy}`
                                                        : ''}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}

SpkProcessSlaEdit.layout = {
    activeMenu: 'SLA Proses SPK',
    pageTitle: 'SLA Proses SPK',
};
