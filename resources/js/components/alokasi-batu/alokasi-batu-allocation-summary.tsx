import deleteIcon from '@ui5/webcomponents-icons/dist/delete.js';
import { Button } from '@ui5/webcomponents-react/Button';
import { Text } from '@ui5/webcomponents-react/Text';
import type {
    DiamondLine,
    PasangBatuDiamondOption,
    PasangBatuStoneOption,
    SettingStoneLine,
} from '@/components/pasang-batu/pasang-batu-stone-editor';

function resolveLabel(
    options: PasangBatuStoneOption[],
    value: string,
): string {
    if (value.trim() === '') {
        return '—';
    }

    return options.find((option) => option.value === value)?.label ?? value;
}

type AlokasiBatuAllocationSummaryProps = {
    compact?: boolean;
    dossier: boolean;
    settingStones: SettingStoneLine[];
    diamonds: DiamondLine[];
    stoneOptions: PasangBatuStoneOption[];
    diamondOptions: PasangBatuDiamondOption[];
    errorPrefix: string;
    errors: Record<string, string>;
    onRemoveSetting: (key: string) => void;
    onRemoveDiamond: (key: string) => void;
};

export function AlokasiBatuAllocationSummary({
    compact = false,
    dossier,
    settingStones,
    diamonds,
    stoneOptions,
    diamondOptions,
    errorPrefix,
    errors,
    onRemoveSetting,
    onRemoveDiamond,
}: AlokasiBatuAllocationSummaryProps) {
    const diamondById = new Map(
        diamondOptions.map((option) => [option.value, option]),
    );

    if (!dossier && settingStones.length === 0) {
        return null;
    }

    if (dossier && diamonds.length === 0) {
        return null;
    }

    return (
        <div
            className={[
                'spkStoneAllocationSummary',
                compact ? 'is-compact' : 'spkStoneBatchPanel',
            ].join(' ')}
        >
            {!compact ? (
                <div className="spkCoranDetailLabel">
                    {dossier
                        ? 'Cadangan dossier'
                        : 'Cadangan batu mikro'}
                </div>
            ) : null}
            <table className="spkCoranMaterialEditorTable">
                <thead>
                    <tr>
                        {dossier ? (
                            <>
                                <th scope="col">Kode</th>
                                <th scope="col">Bentuk</th>
                                <th scope="col">Crt</th>
                            </>
                        ) : (
                            <>
                                <th scope="col">Batu</th>
                                <th scope="col">Pcs</th>
                                <th scope="col">Crt</th>
                            </>
                        )}
                        <th scope="col" className="spkTableActionCol">
                            Aksi
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {dossier
                        ? diamonds.map((line, index) => {
                              const selected = diamondById.get(line.diamond_id);
                              const diamondError =
                                  errors[
                                      `${errorPrefix}.diamonds.${index}.diamond_id`
                                  ];

                              return (
                                  <tr key={line.key} className="spkCoranLineRow">
                                      <td>
                                          {selected?.code ??
                                              selected?.label ??
                                              line.diamond_id}
                                          {diamondError ? (
                                              <Text className="spkFioriError">
                                                  {diamondError}
                                              </Text>
                                          ) : null}
                                      </td>
                                      <td>{selected?.shape ?? '—'}</td>
                                      <td>{selected?.crt ?? '—'}</td>
                                      <td className="spkTableActionCol">
                                          <Button
                                              design="Transparent"
                                              icon={deleteIcon}
                                              type="Button"
                                              accessibleName="Hapus alokasi dossier"
                                              onClick={() =>
                                                  onRemoveDiamond(line.key)
                                              }
                                          />
                                      </td>
                                  </tr>
                              );
                          })
                        : settingStones.map((line, index) => {
                              const stoneError =
                                  errors[
                                      `${errorPrefix}.setting_stones.${index}.stone_id`
                                  ];
                              const pcsError =
                                  errors[
                                      `${errorPrefix}.setting_stones.${index}.pcs`
                                  ];
                              const crtError =
                                  errors[
                                      `${errorPrefix}.setting_stones.${index}.crt`
                                  ];

                              return (
                                  <tr key={line.key} className="spkCoranLineRow">
                                      <td>
                                          {resolveLabel(
                                              stoneOptions,
                                              line.stone_id,
                                          )}
                                          {stoneError ? (
                                              <Text className="spkFioriError">
                                                  {stoneError}
                                              </Text>
                                          ) : null}
                                      </td>
                                      <td>
                                          {line.pcs}
                                          {pcsError ? (
                                              <Text className="spkFioriError">
                                                  {pcsError}
                                              </Text>
                                          ) : null}
                                      </td>
                                      <td>
                                          {line.crt}
                                          {crtError ? (
                                              <Text className="spkFioriError">
                                                  {crtError}
                                              </Text>
                                          ) : null}
                                      </td>
                                      <td className="spkTableActionCol">
                                          <Button
                                              design="Transparent"
                                              icon={deleteIcon}
                                              type="Button"
                                              accessibleName="Hapus alokasi mikro"
                                              onClick={() =>
                                                  onRemoveSetting(line.key)
                                              }
                                          />
                                      </td>
                                  </tr>
                              );
                          })}
                </tbody>
            </table>
        </div>
    );
}
