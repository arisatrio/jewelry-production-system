import { Text } from '@ui5/webcomponents-react/Text';
import { Title } from '@ui5/webcomponents-react/Title';
import type { SpkCraftsmanReportCard } from '@/components/spk/types';
import { formatGram } from '@/lib/utils';

type SpkCraftsmanReportSectionProps = {
    cards: SpkCraftsmanReportCard[];
};

function formatShrink(value: string | null): string {
    return formatGram(value);
}

export function SpkCraftsmanReportSection({
    cards,
}: SpkCraftsmanReportSectionProps) {
    return (
        <div className="spkCraftsmanReport">
            <div className="spkShrinkReportHeader">
                <div className="spkShrinkReportHeading">
                    <Title level="H5">Laporan Pengrajin</Title>
                    <Text className="spkShrinkReportMeta">
                        {cards.length} pengerjaan tercatat
                    </Text>
                </div>
            </div>

            {cards.length === 0 ? (
                <div className="spkCraftsmanReportEmpty">
                    Belum ada data pengrajin untuk SPK ini.
                </div>
            ) : (
                <div className="spkCraftsmanCardGrid">
                    {cards.map((card) => (
                        <article
                            key={`${card.no}-${card.craftsmanId}-${card.process}`}
                            className="spkCraftsmanCard"
                            aria-label={`${card.craftsmanName} — ${card.process}`}
                        >
                            <header className="spkCraftsmanCardHeader">
                                <span className="spkCraftsmanCardIndex">
                                    #{card.no}
                                </span>
                                <div className="spkCraftsmanCardHeading">
                                    <h3 className="spkCraftsmanCardTitle">
                                        {card.craftsmanName}
                                    </h3>
                                    <p className="spkCraftsmanCardProcess">
                                        {card.process}
                                    </p>
                                </div>
                            </header>

                            <div className="spkCraftsmanCardMetrics">
                                <div className="spkCraftsmanCardMetric">
                                    <span className="spkCraftsmanCardMetricLabel">
                                        Waktu Kerja
                                    </span>
                                    <strong className="spkCraftsmanCardMetricValue">
                                        {card.workDuration ?? '—'}
                                    </strong>
                                    {card.sentAt || card.receivedAt ? (
                                        <span className="spkCraftsmanCardMetricHint">
                                            {card.sentAt ?? '—'} →{' '}
                                            {card.receivedAt ?? '—'}
                                        </span>
                                    ) : null}
                                </div>
                                <div className="spkCraftsmanCardMetric is-shrink">
                                    <span className="spkCraftsmanCardMetricLabel">
                                        Susut
                                    </span>
                                    <strong className="spkCraftsmanCardMetricValue">
                                        {formatShrink(card.shrink)}
                                    </strong>
                                </div>
                            </div>
                        </article>
                    ))}
                </div>
            )}
        </div>
    );
}
