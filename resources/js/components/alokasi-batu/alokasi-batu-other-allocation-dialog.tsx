import { Button } from '@ui5/webcomponents-react/Button';
import {
    type SpkStoneRowAllocation,
    isDossierSpkStone,
} from '@/components/alokasi-batu/alokasi-batu-allocation-state';
import {
    DiamondPanel,
    SettingStonePanel,
    type DiamondLine,
    type PasangBatuDiamondOption,
    type PasangBatuStoneOption,
    type SettingStoneLine,
} from '@/components/pasang-batu/pasang-batu-stone-editor';
import { spkStoneKindLabel } from '@/components/spk/spk-stone-list';
import type { SpkStoneItem } from '@/components/spk/types';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type AlokasiBatuOtherAllocationDialogProps = {
    stone: SpkStoneItem | null;
    bucket: SpkStoneRowAllocation;
    stoneOptions: PasangBatuStoneOption[];
    diamondOptions: PasangBatuDiamondOption[];
    errors: Record<string, string>;
    onOpenChange: (open: boolean) => void;
    onSettingLinesChange: (lines: SettingStoneLine[]) => void;
    onDiamondLinesChange: (lines: DiamondLine[]) => void;
};

export function AlokasiBatuOtherAllocationDialog({
    stone,
    bucket,
    stoneOptions,
    diamondOptions,
    errors,
    onOpenChange,
    onSettingLinesChange,
    onDiamondLinesChange,
}: AlokasiBatuOtherAllocationDialogProps) {
    const open = stone !== null;
    const dossier = stone !== null ? isDossierSpkStone(stone) : false;

    const titleLabel =
        stone === null
            ? ''
            : [
                  stone.positionName,
                  stone.shapeName || stone.shape,
                  spkStoneKindLabel(
                      stone.stoneKind ?? stone.stock?.source,
                  ),
              ]
                  .filter((part) => part && part !== '—')
                  .join(' · ');

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="spkAlertModal alokasiBatuOtherModal">
                <DialogHeader>
                    <DialogTitle>
                        Ambil alokasi batu lain
                        {titleLabel !== '' ? ` — ${titleLabel}` : ''}
                    </DialogTitle>
                </DialogHeader>

                {stone !== null ? (
                    dossier ? (
                        <DiamondPanel
                            showHeader
                            errorPrefix={`allocation.${stone.id}.diamonds`}
                            lines={bucket.diamonds}
                            diamondOptions={diamondOptions}
                            errors={errors}
                            onChange={onDiamondLinesChange}
                        />
                    ) : (
                        <SettingStonePanel
                            showHeader
                            title="Batu mikro"
                            errorPrefix={`allocation.${stone.id}.setting_stones`}
                            lines={bucket.settingStones}
                            stoneOptions={stoneOptions}
                            errors={errors}
                            integerPcs
                            onChange={onSettingLinesChange}
                        />
                    )
                ) : null}

                <DialogFooter className="alokasiBatuOtherModalFooter">
                    <Button
                        design="Emphasized"
                        type="Button"
                        onClick={() => onOpenChange(false)}
                    >
                        Selesai
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
