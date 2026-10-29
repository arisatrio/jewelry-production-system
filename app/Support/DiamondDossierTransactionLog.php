<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DiamondDossierTransactionLog
{
    public const EVENT_ENTRY = 'entry';

    public const EVENT_OUT = 'out';

    public function tableAvailable(): bool
    {
        return Schema::connection('third')->hasTable('trdiamond');
    }

    public static function eventLabel(string $eventType): string
    {
        return match ($eventType) {
            self::EVENT_ENTRY => 'Penambahan',
            self::EVENT_OUT => 'Pemakaian',
            default => '—',
        };
    }

    /**
     * @return LengthAwarePaginator<int, array<string, int|string|null>>
     */
    public function paginate(
        string $search,
        ?string $dateFrom,
        ?string $dateTo,
        ?string $eventType,
        int $perPage,
    ): LengthAwarePaginator {
        $eventsQuery = $this->buildEventsQuery($search, $eventType);

        $query = DB::connection('third')
            ->query()
            ->fromSub($eventsQuery, 'events');

        if ($dateFrom !== null) {
            $query->whereDate('events.event_date', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $query->whereDate('events.event_date', '<=', $dateTo);
        }

        return $query
            ->orderByDesc('events.event_date')
            ->orderByDesc('events.diamond_id')
            ->orderByDesc('events.event_type')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (object $row): array => $this->toRow($row));
    }

    private function buildEventsQuery(string $search, ?string $eventType): Builder
    {
        $entryQuery = $this->entryEventsQuery($search);
        $outQuery = $this->outEventsQuery($search);

        return match ($eventType) {
            self::EVENT_ENTRY => $entryQuery,
            self::EVENT_OUT => $outQuery,
            default => $entryQuery->unionAll($outQuery),
        };
    }

    private function entryEventsQuery(string $search): Builder
    {
        return $this->diamondBaseQuery($search)
            ->where(function (Builder $query): void {
                $query->whereNotNull('diamond.entry_date')
                    ->orWhereNotNull('diamond.created_date');
            })
            ->select([
                DB::raw("'".self::EVENT_ENTRY."' as event_type"),
                'diamond.row_id as diamond_id',
                DB::raw('COALESCE(diamond.entry_date, DATE(diamond.created_date)) as event_date'),
                'diamond.doc_no',
                'diamond.diamond_type',
                'shape.name as shape_name',
                'diamond.color',
                'diamond.crt',
                'diamond.certificate',
                'diamond.supplier',
                'mounting_document.doc_no as mounting_doc_no',
                'mounting_document.row_id as mounting_doc_id',
                'production.spk_no as spk_no',
                'production.row_id as spk_id',
                'diamond.created_by as actor',
                'diamond.created_date as actor_at',
            ]);
    }

    private function outEventsQuery(string $search): Builder
    {
        return $this->diamondBaseQuery($search)
            ->whereNotNull('diamond.out_date')
            ->select([
                DB::raw("'".self::EVENT_OUT."' as event_type"),
                'diamond.row_id as diamond_id',
                'diamond.out_date as event_date',
                'diamond.doc_no',
                'diamond.diamond_type',
                'shape.name as shape_name',
                'diamond.color',
                'diamond.crt',
                'diamond.certificate',
                'diamond.supplier',
                'mounting_document.doc_no as mounting_doc_no',
                'mounting_document.row_id as mounting_doc_id',
                'production.spk_no as spk_no',
                'production.row_id as spk_id',
                'diamond.modified_by as actor',
                'diamond.modified_date as actor_at',
            ]);
    }

    private function diamondBaseQuery(string $search): Builder
    {
        return DB::connection('third')
            ->table('trdiamond as diamond')
            ->leftJoin('msshape as shape', 'shape.row_id', '=', 'diamond.shape_id')
            ->leftJoin('diamondmounting as mounting_document', function ($join): void {
                $join->on('mounting_document.row_id', '=', 'diamond.diamondmounting_id')
                    ->where('mounting_document.is_deleted', 0);
            })
            ->leftJoin('spk as production', 'production.row_id', '=', 'mounting_document.spk_id')
            ->where('diamond.is_deleted', 0)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';

                $query->where(function (Builder $innerQuery) use ($like): void {
                    $innerQuery->where('diamond.doc_no', 'like', $like)
                        ->orWhere('diamond.diamond_type', 'like', $like)
                        ->orWhere('diamond.certificate', 'like', $like)
                        ->orWhere('diamond.color', 'like', $like)
                        ->orWhere('diamond.supplier', 'like', $like)
                        ->orWhere('shape.name', 'like', $like)
                        ->orWhere('mounting_document.doc_no', 'like', $like)
                        ->orWhere('production.spk_no', 'like', $like);
                });
            });
    }

    /**
     * @return array{
     *     id: string,
     *     eventType: string,
     *     eventLabel: string,
     *     eventDate: string|null,
     *     diamondId: int,
     *     code: string|null,
     *     diamondType: string|null,
     *     shape: string|null,
     *     color: string|null,
     *     crt: string|null,
     *     certificate: string|null,
     *     supplier: string|null,
     *     mountingDocumentNo: string|null,
     *     mountingDocumentId: int|null,
     *     spkNo: string|null,
     *     spkId: int|null,
     *     actor: string|null,
     *     actorAt: string|null
     * }
     */
    private function toRow(object $row): array
    {
        $eventType = (string) $row->event_type;
        $diamondId = (int) $row->diamond_id;

        return [
            'id' => $eventType.'-'.$diamondId,
            'eventType' => $eventType,
            'eventLabel' => self::eventLabel($eventType),
            'eventDate' => $this->nullableString($row->event_date),
            'diamondId' => $diamondId,
            'code' => $this->nullableString($row->doc_no),
            'diamondType' => $this->nullableString($row->diamond_type),
            'shape' => $this->nullableString($row->shape_name),
            'color' => $this->nullableString($row->color),
            'crt' => filled($row->crt ?? null)
                ? number_format((float) $row->crt, 3, '.', '')
                : null,
            'certificate' => $this->nullableString($row->certificate),
            'supplier' => $this->nullableString($row->supplier),
            'mountingDocumentNo' => $this->nullableString($row->mounting_doc_no),
            'mountingDocumentId' => filled($row->mounting_doc_id ?? null)
                ? (int) $row->mounting_doc_id
                : null,
            'spkNo' => $this->nullableString($row->spk_no),
            'spkId' => filled($row->spk_id ?? null) ? (int) $row->spk_id : null,
            'actor' => $this->nullableString($row->actor),
            'actorAt' => $this->formatActorAt($row->actor_at ?? null),
        ];
    }

    private function formatActorAt(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->format('d-M-Y');
        } catch (\Throwable) {
            return null;
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }
}
