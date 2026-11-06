import { useState } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type PengrajinEmployeeOption = {
    value: string;
    label: string;
    name: string;
};

export type PengrajinFormValues = {
    name: string | null;
    rate: string | null;
    isActive: string;
    employeeId: string | null;
    userId: string | null;
};

type PengrajinFieldsProps = {
    craftsman?: PengrajinFormValues;
    employees: PengrajinEmployeeOption[];
    errors: Partial<
        Record<'name' | 'rate' | 'is_active' | 'employee_id', string>
    >;
};

export function PengrajinFields({
    craftsman,
    employees,
    errors,
}: PengrajinFieldsProps) {
    const isCreate = craftsman === undefined;
    const [employeeId, setEmployeeId] = useState(craftsman?.employeeId ?? '');
    const [name, setName] = useState(craftsman?.name ?? '');

    function handleEmployeeChange(value: string): void {
        setEmployeeId(value);

        if (!isCreate) {
            return;
        }

        const employee = employees.find((option) => option.value === value);
        setName(employee?.name ?? '');
    }

    const employeeField = (
        <div className="grid gap-2">
            <Label htmlFor="employee_id">Pegawai</Label>
            <select
                id="employee_id"
                name="employee_id"
                className="masterDataFormSelect"
                value={employeeId}
                autoFocus={isCreate}
                onChange={(event) => handleEmployeeChange(event.target.value)}
            >
                <option value="">Tidak dihubungkan</option>
                {employees.map((employee) => (
                    <option key={employee.value} value={employee.value}>
                        {employee.label}
                    </option>
                ))}
            </select>
            <InputError message={errors.employee_id} />
        </div>
    );

    const nameField = (
        <div className="grid gap-2">
            <Label htmlFor="name">Nama</Label>
            <Input
                id="name"
                name="name"
                required
                maxLength={100}
                value={isCreate ? name : undefined}
                defaultValue={isCreate ? undefined : (craftsman?.name ?? '')}
                onChange={
                    isCreate
                        ? (event) => setName(event.target.value)
                        : undefined
                }
                placeholder="Nama pengrajin"
                autoFocus={!isCreate}
            />
            <InputError message={errors.name} />
        </div>
    );

    return (
        <>
            {isCreate && employeeField}
            {nameField}

            <div className="grid gap-2">
                <Label htmlFor="rate">Tarif</Label>
                <Input
                    id="rate"
                    name="rate"
                    type="number"
                    min="0"
                    step="0.01"
                    defaultValue={craftsman?.rate ?? ''}
                    placeholder="Kosongkan jika belum ada"
                />
                <InputError message={errors.rate} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="is_active">Status</Label>
                <select
                    id="is_active"
                    name="is_active"
                    className="masterDataFormSelect"
                    defaultValue={craftsman?.isActive ?? 'YES'}
                    required
                >
                    <option value="YES">Aktif</option>
                    <option value="NO">Nonaktif</option>
                </select>
                <InputError message={errors.is_active} />
            </div>

            {!isCreate && employeeField}
        </>
    );
}
