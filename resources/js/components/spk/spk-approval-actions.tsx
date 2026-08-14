import { useState } from 'react';
import { router } from '@inertiajs/react';
import acceptIcon from '@ui5/webcomponents-icons/dist/accept.js';
import declineIcon from '@ui5/webcomponents-icons/dist/decline.js';
import paperPlaneIcon from '@ui5/webcomponents-icons/dist/paper-plane.js';
import { Button } from '@ui5/webcomponents-react/Button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import {
    approve as spkApprove,
    reject as spkReject,
    submit as spkSubmit,
} from '@/routes/spk';

export type SpkApprovalAbilities = {
    canEdit: boolean;
    canSubmit: boolean;
    canApprove: boolean;
    canReject: boolean;
    status: string;
    statusLabel: string;
    role: string;
    history: Array<{
        status: string;
        statusLabel: string;
        approve: string;
        notes: string | null;
        createdBy: string | null;
        createdAt: string | null;
    }>;
};

type SpkApprovalActionsProps = {
    productionId: number;
    approval: SpkApprovalAbilities;
};

export function SpkApprovalActions({
    productionId,
    approval,
}: SpkApprovalActionsProps) {
    const [rejectOpen, setRejectOpen] = useState(false);
    const [notes, setNotes] = useState('');
    const [processing, setProcessing] = useState(false);

    if (
        !approval.canApprove &&
        !approval.canReject &&
        !approval.canSubmit
    ) {
        return null;
    }

    const handleSubmit = (): void => {
        setProcessing(true);
        router.post(
            spkSubmit.url(productionId),
            {},
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            },
        );
    };

    const handleApprove = (): void => {
        setProcessing(true);
        router.post(
            spkApprove.url(productionId),
            { notes: null },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            },
        );
    };

    const handleReject = (): void => {
        if (notes.trim() === '') {
            window.alert('Catatan reject wajib diisi.');

            return;
        }

        setProcessing(true);
        router.post(
            spkReject.url(productionId),
            { notes: notes.trim() },
            {
                preserveScroll: true,
                onFinish: () => {
                    setProcessing(false);
                    setRejectOpen(false);
                    setNotes('');
                },
            },
        );
    };

    return (
        <>
            <div className="spkApprovalActions">
                {approval.canSubmit ? (
                    <Button
                        design="Emphasized"
                        icon={paperPlaneIcon}
                        disabled={processing}
                        onClick={handleSubmit}
                    >
                        Kirim ke Manager
                    </Button>
                ) : null}
                {approval.canApprove ? (
                    <Button
                        design="Positive"
                        className="spkApprovalApproveBtn"
                        icon={acceptIcon}
                        disabled={processing}
                        onClick={handleApprove}
                    >
                        Approve
                    </Button>
                ) : null}
                {approval.canReject ? (
                    <Button
                        design="Negative"
                        icon={declineIcon}
                        disabled={processing}
                        onClick={() => setRejectOpen(true)}
                    >
                        Reject
                    </Button>
                ) : null}
            </div>

            <Dialog open={rejectOpen} onOpenChange={setRejectOpen}>
                <DialogContent className="spkApprovalRejectDialog">
                    <DialogHeader>
                        <DialogTitle>Reject SPK</DialogTitle>
                        <DialogDescription>
                            SPK akan dikembalikan ke Draft. Isi alasan penolakan.
                        </DialogDescription>
                    </DialogHeader>
                    <Textarea
                        value={notes}
                        onChange={(event) => setNotes(event.target.value)}
                        placeholder="Catatan reject..."
                        rows={4}
                        autoFocus
                    />
                    <DialogFooter>
                        <Button
                            design="Transparent"
                            disabled={processing}
                            onClick={() => setRejectOpen(false)}
                        >
                            Batal
                        </Button>
                        <Button
                            design="Negative"
                            disabled={processing}
                            onClick={handleReject}
                        >
                            Reject ke Draft
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
