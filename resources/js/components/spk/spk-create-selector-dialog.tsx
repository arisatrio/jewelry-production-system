import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

type SelectorColumn = {
    key: string;
    label: string;
};

type SelectorRow = {
    id: string;
    [key: string]: string;
};

type SpkCreateSelectorDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    search: string;
    onSearchChange: (value: string) => void;
    loading: boolean;
    emptyText: string;
    columns: SelectorColumn[];
    rows: SelectorRow[];
    onSelect: (id: string) => void;
};

export function SpkCreateSelectorDialog({
    open,
    onOpenChange,
    title,
    description,
    search,
    onSearchChange,
    loading,
    emptyText,
    columns,
    rows,
    onSelect,
}: SpkCreateSelectorDialogProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="spkCreateDialog">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>

                <Input
                    value={search}
                    onChange={(event) => onSearchChange(event.target.value)}
                    placeholder="Cari..."
                    autoFocus
                />

                <div className="spkCreateDialogTableWrap">
                    <table className="spkCreateDialogTable">
                        <thead>
                            <tr>
                                {columns.map((column) => (
                                    <th key={column.key}>{column.label}</th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {loading ? (
                                <tr>
                                    <td colSpan={columns.length}>
                                        Memuat data...
                                    </td>
                                </tr>
                            ) : rows.length === 0 ? (
                                <tr>
                                    <td colSpan={columns.length}>{emptyText}</td>
                                </tr>
                            ) : (
                                rows.map((row) => (
                                    <tr
                                        key={row.id}
                                        tabIndex={0}
                                        onClick={() => onSelect(row.id)}
                                        onKeyDown={(event) => {
                                            if (
                                                event.key === 'Enter' ||
                                                event.key === ' '
                                            ) {
                                                event.preventDefault();
                                                onSelect(row.id);
                                            }
                                        }}
                                    >
                                        {columns.map((column) => (
                                            <td key={`${row.id}-${column.key}`}>
                                                {row[column.key] ?? '—'}
                                            </td>
                                        ))}
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </DialogContent>
        </Dialog>
    );
}
