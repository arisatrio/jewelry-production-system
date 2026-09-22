import { NotebookText } from 'lucide-react';
import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type NotesCellProps = {
    notes: string | null | undefined;
    title?: string;
    docNo?: string | null;
    spkNo?: string | null;
};

function buildNotesTitle(
    baseTitle: string,
    docNo?: string | null,
    spkNo?: string | null,
): string {
    const parts = [baseTitle];
    const finishingNo = docNo?.trim() ?? '';
    const prdNo = spkNo?.trim() ?? '';

    if (finishingNo !== '') {
        parts.push(finishingNo);
    }

    if (prdNo !== '') {
        parts.push(prdNo);
    }

    return parts.join(' · ');
}

export function NotesCell({
    notes,
    title = 'Catatan',
    docNo,
    spkNo,
}: NotesCellProps) {
    const [open, setOpen] = useState(false);
    const trimmed = notes?.trim() ?? '';
    const modalTitle = buildNotesTitle(title, docNo, spkNo);

    if (trimmed === '') {
        return <span className="spkNotesEmpty">—</span>;
    }

    return (
        <>
            <button
                type="button"
                className="spkNotesBtn"
                aria-label={`Lihat ${modalTitle.toLowerCase()}`}
                title={modalTitle}
                onClick={() => setOpen(true)}
            >
                <NotebookText aria-hidden className="spkNotesBtnIcon" />
            </button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="spkAlertModal spkNotesModal">
                    <DialogHeader>
                        <DialogTitle>{modalTitle}</DialogTitle>
                    </DialogHeader>
                    <div className="spkAlertModalBody">
                        <p className="spkNotesModalText">{trimmed}</p>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
