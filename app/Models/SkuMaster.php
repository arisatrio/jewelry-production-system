<?php

namespace App\Models;

use Database\Factories\SkuMasterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $sku_code
 * @property string|null $item_original
 * @property int|null $name_prefix_id
 * @property int|null $category_prefix_id
 * @property int|null $gold_prefix_id
 * @property int|null $size_prefix_id
 * @property int|null $stone_shape_prefix_id
 * @property int|null $stone_type_prefix_id
 * @property int|null $diamond_type_prefix_id
 * @property string|null $crt
 * @property string|null $gold_weight
 * @property string|null $sell_price
 * @property int $is_complete
 * @property int $wildcard_count
 * @property int $completeness_score
 * @property string|null $catalog_image
 * @property string|null $design_image
 * @property string|null $file_jwlcad
 * @property string|null $image_url
 * @property string|null $image_filename
 * @property Carbon|null $image_uploaded_at
 * @property string $source
 * @property int $is_active
 * @property int|null $is_deleted
 * @property string|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $label
 * @property string|null $created_by
 * @property string|null $modified_by
 * @property-read Collection<int, SkuMasterDiamond> $diamonds
 */
#[Fillable([
    'sku_code',
    'item_original',
    'name_prefix_id',
    'category_prefix_id',
    'gold_prefix_id',
    'size_prefix_id',
    'stone_shape_prefix_id',
    'stone_type_prefix_id',
    'diamond_type_prefix_id',
    'crt',
    'gold_weight',
    'sell_price',
    'is_complete',
    'wildcard_count',
    'completeness_score',
    'catalog_image',
    'design_image',
    'file_jwlcad',
    'image_url',
    'image_filename',
    'image_uploaded_at',
    'source',
    'is_active',
    'is_deleted',
    'metadata',
    'label',
    'created_by',
    'modified_by',
])]
class SkuMaster extends Model
{
    /** @use HasFactory<SkuMasterFactory> */
    use HasFactory;

    /**
     * The connection name for the model.
     *
     * @var string|null
     */
    protected $connection = 'second';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sku_master';

    /**
     * @param  Builder<SkuMaster>  $query
     * @return Builder<SkuMaster>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('is_active', 1)
            ->where(function (Builder $builder): void {
                $builder->where('is_deleted', 0)->orWhereNull('is_deleted');
            });
    }

    /**
     * SKU identity without the gold-color prefix (first hyphen segment).
     */
    public static function identityCode(?string $skuCode): string
    {
        $code = strtoupper(trim((string) $skuCode));

        if ($code === '') {
            return '';
        }

        $separator = strpos($code, '-');

        if ($separator === false || $separator === 0) {
            return $code;
        }

        return trim(substr($code, $separator + 1));
    }

    /**
     * SKU ids that share the same identity after the gold-color prefix.
     *
     * @return list<int>
     */
    public static function idsSharingIdentity(?string $skuCode): array
    {
        $identity = self::identityCode($skuCode);

        if ($identity === '') {
            return [];
        }

        return self::query()
            ->where(function (Builder $query) use ($identity): void {
                $query
                    ->whereRaw(
                        "UPPER(TRIM(SUBSTRING(sku_code, LOCATE('-', sku_code) + 1))) = ?",
                        [$identity],
                    )
                    ->orWhereRaw('UPPER(TRIM(sku_code)) = ?', [$identity]);
            })
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Display label for selectors and print.
     */
    public function displayName(): string
    {
        $skuCode = trim((string) $this->sku_code);
        $itemOriginal = trim((string) ($this->item_original ?? ''));

        if ($skuCode !== '' && $itemOriginal !== '') {
            return "{$skuCode} — {$itemOriginal}";
        }

        if ($skuCode !== '') {
            return $skuCode;
        }

        return $itemOriginal !== '' ? $itemOriginal : '-';
    }

    /**
     * JewelCAD filename/code from sku_master.file_jwlcad.
     */
    public function resolvedJwcadFile(): ?string
    {
        $value = trim((string) ($this->file_jwlcad ?? ''));

        return $value !== '' && $value !== '-' ? $value : null;
    }

    /**
     * Raw image reference from sku_master.design_image.
     */
    public function resolvedImageReference(): ?string
    {
        $value = trim((string) ($this->design_image ?? ''));

        return $value !== '' && $value !== '-' ? $value : null;
    }

    /**
     * Display URL for SKU design image (design_image preferred).
     */
    public function resolvedImageUrl(?string $baseUrl = null): ?string
    {
        $reference = $this->resolvedImageReference();

        if ($reference === null) {
            return null;
        }

        if (
            str_starts_with($reference, 'http://')
            || str_starts_with($reference, 'https://')
            || str_starts_with($reference, '//')
        ) {
            return $reference;
        }

        if (str_starts_with($reference, '/')) {
            return $reference;
        }

        $base = rtrim($baseUrl ?? (string) config('spk.production_image_base_url'), '/').'/';
        $path = ltrim(str_replace('\\', '/', $reference), '/');

        return $path !== '' ? $base.$path : null;
    }

    /**
     * Filename copied to SPK file_name when no upload is provided.
     */
    public function resolvedImageFileName(): ?string
    {
        $fileName = trim(str_replace('\\', '/', (string) ($this->image_filename ?? '')));

        if ($fileName !== '') {
            return $fileName;
        }

        $reference = trim(str_replace('\\', '/', (string) ($this->design_image ?? '')));

        if ($reference === '') {
            return null;
        }

        if (str_contains($reference, '://')) {
            $path = parse_url($reference, PHP_URL_PATH);
            $basename = is_string($path) ? basename($path) : '';

            return $basename !== '' && $basename !== '.' ? $basename : null;
        }

        return ltrim($reference, '/');
    }

    /**
     * Map prefix gold color to SPK gold color options.
     */
    public function resolvedGoldColor(): ?string
    {
        $raw = strtoupper(trim((string) ($this->goldColorPrefix?->gold_color ?? '')));

        return match ($raw) {
            'WHITE GOLD' => 'White Gold',
            'YELLOW GOLD' => 'Yellow Gold',
            'ROSE GOLD' => 'Rose Gold',
            'TWO TONES', 'TWO TONE' => 'Two Tones',
            default => null,
        };
    }

    /**
     * @return BelongsTo<SkuPrefixGoldColor, $this>
     */
    public function goldColorPrefix(): BelongsTo
    {
        return $this->belongsTo(SkuPrefixGoldColor::class, 'gold_prefix_id', 'id');
    }

    /**
     * @return BelongsTo<SkuPrefixCategory, $this>
     */
    public function categoryPrefix(): BelongsTo
    {
        return $this->belongsTo(SkuPrefixCategory::class, 'category_prefix_id', 'id');
    }

    /**
     * @return BelongsTo<SkuPrefixName, $this>
     */
    public function namePrefix(): BelongsTo
    {
        return $this->belongsTo(SkuPrefixName::class, 'name_prefix_id', 'id');
    }

    /**
     * @return BelongsTo<SkuPrefixSize, $this>
     */
    public function sizePrefix(): BelongsTo
    {
        return $this->belongsTo(SkuPrefixSize::class, 'size_prefix_id', 'id');
    }

    /**
     * @return BelongsTo<SkuPrefixStoneShape, $this>
     */
    public function stoneShapePrefix(): BelongsTo
    {
        return $this->belongsTo(SkuPrefixStoneShape::class, 'stone_shape_prefix_id', 'id');
    }

    /**
     * @return BelongsTo<SkuPrefixStoneType, $this>
     */
    public function stoneTypePrefix(): BelongsTo
    {
        return $this->belongsTo(SkuPrefixStoneType::class, 'stone_type_prefix_id', 'id');
    }

    /**
     * @return BelongsTo<SkuPrefixDiamondType, $this>
     */
    public function diamondTypePrefix(): BelongsTo
    {
        return $this->belongsTo(SkuPrefixDiamondType::class, 'diamond_type_prefix_id', 'id');
    }

    /**
     * @return HasMany<SkuMasterDiamond, $this>
     */
    public function diamonds(): HasMany
    {
        return $this->hasMany(SkuMasterDiamond::class, 'row_id', 'id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'name_prefix_id' => 'integer',
            'category_prefix_id' => 'integer',
            'gold_prefix_id' => 'integer',
            'size_prefix_id' => 'integer',
            'stone_shape_prefix_id' => 'integer',
            'stone_type_prefix_id' => 'integer',
            'diamond_type_prefix_id' => 'integer',
            'gold_weight' => 'decimal:3',
            'sell_price' => 'decimal:2',
            'is_complete' => 'integer',
            'wildcard_count' => 'integer',
            'completeness_score' => 'integer',
            'image_uploaded_at' => 'datetime',
            'is_active' => 'integer',
            'is_deleted' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
