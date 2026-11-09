<?php

namespace App\Support;

use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class DiamondDossierStockReportWorkbookExport implements Export, WithMultipleSheets
{
    /**
     * @param  array{
     *     sections: list<array{
     *         shapeId: int,
     *         shapeLabel: string,
     *         rows: list<array{
     *             crtMin: string,
     *             crtMax: string,
     *             reserved: array{pcs: int, crt: string},
     *             available: array{pcs: int, crt: string},
     *             actualAvailable: array{pcs: int, crt: string},
     *         }>,
     *         totals: array{
     *             reserved: array{pcs: int, crt: string},
     *             available: array{pcs: int, crt: string},
     *             actualAvailable: array{pcs: int, crt: string},
     *         },
     *     }>,
     *     grandTotals: array{
     *         reserved: array{pcs: int, crt: string},
     *         available: array{pcs: int, crt: string},
     *         actualAvailable: array{pcs: int, crt: string},
     *     },
     * }  $report
     * @param  array<int, list<array<string, int|string|null>>>  $shapeDiamonds
     */
    public function __construct(
        private readonly array $report,
        private readonly array $shapeDiamonds,
    ) {}

    /**
     * @return list<object>
     */
    public function sheets(): array
    {
        $sheets = [
            new DiamondDossierStockReportSummaryExport(
                $this->report['sections'],
                $this->report['grandTotals'],
            ),
        ];

        $usedTitles = ['Summary'];

        foreach ($this->report['sections'] as $section) {
            $shapeId = (int) $section['shapeId'];
            $title = $this->uniqueSheetTitle((string) $section['shapeLabel'], $usedTitles);

            $sheets[] = new DiamondDossierStockReportShapeDetailExport(
                $title,
                $this->shapeDiamonds[$shapeId] ?? [],
            );
        }

        return $sheets;
    }

    /**
     * @param  list<string>  $usedTitles
     */
    private function uniqueSheetTitle(string $label, array &$usedTitles): string
    {
        $sanitized = self::sanitizeSheetTitle($label);
        $title = $sanitized;
        $suffix = 2;

        while (in_array($title, $usedTitles, true)) {
            $base = Str::limit($sanitized, max(1, 31 - (strlen((string) $suffix) + 3)), '');
            $title = $base.' ('.$suffix.')';
            $suffix++;
        }

        $usedTitles[] = $title;

        return $title;
    }

    public static function sanitizeSheetTitle(string $label): string
    {
        $title = preg_replace('/[\\\\\\/?\\*\\[\\]:]/', ' ', $label) ?? $label;
        $title = trim($title);

        if ($title === '') {
            return 'Shape';
        }

        return Str::limit($title, 31, '');
    }
}
