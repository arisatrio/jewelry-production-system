<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMsCraftsmanRequest;
use App\Http\Requests\UpdateMsCraftsmanRequest;
use App\Models\Employee;
use App\Models\MsCraftsman;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MsCraftsmanController extends Controller
{
    /**
     * Display a listing of craftsmen.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive', 'unlinked', 'all'])],
            'per_page' => ['nullable', 'integer'],
            'sort' => ['nullable', Rule::in(['name', 'rate', 'status'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'form' => ['nullable', Rule::in(['create'])],
            'edit' => ['nullable', 'integer'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $status = (string) ($validated['status'] ?? 'active');
        $perPage = (int) ($validated['per_page'] ?? 50);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 50;
        $sort = (string) ($validated['sort'] ?? 'name');
        $direction = (string) ($validated['direction'] ?? 'asc');
        $formMode = ($validated['form'] ?? null) === 'create' ? 'create' : null;
        $editId = isset($validated['edit']) ? (int) $validated['edit'] : null;

        $request->query->remove('form');
        $request->query->remove('edit');

        $craftsmen = MsCraftsman::query()
            ->notDeleted()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when($status === 'active', function ($query): void {
                $query->where('is_active', 'YES');
            })
            ->when($status === 'inactive', function ($query): void {
                $query->where(function ($query): void {
                    $query->where('is_active', '!=', 'YES')->orWhereNull('is_active');
                });
            })
            ->when($status === 'unlinked', function ($query): void {
                $query->where(function ($query): void {
                    $query->whereNull('employee_id')->orWhereNull('user_id');
                });
            })
            ->when($sort === 'rate', function ($query) use ($direction): void {
                $query->orderBy('rate', $direction)->orderBy('name');
            })
            ->when($sort === 'status', function ($query) use ($direction): void {
                $query->orderBy('is_active', $direction)->orderBy('name');
            })
            ->when($sort === 'name', function ($query) use ($direction): void {
                $query->orderBy('name', $direction);
            })
            ->paginate($perPage)
            ->withQueryString();

        $employeeMeta = $this->employeeListMeta(
            $craftsmen->getCollection()->pluck('employee_id'),
        );

        $craftsmen->through(
            fn (MsCraftsman $craftsman): array => $this->toListItem(
                $craftsman,
                $employeeMeta['names'],
                $employeeMeta['positions'],
            ),
        );

        $editing = null;

        if ($editId !== null) {
            $craftsman = MsCraftsman::query()->notDeleted()->find($editId);

            if ($craftsman !== null) {
                $editing = $this->toFormItem($craftsman);
            }
        }

        return Inertia::render('master-data/pengrajin/index', [
            'craftsmen' => $craftsmen,
            'formMode' => $formMode,
            'editing' => $editing,
            'employees' => $this->employeeOptions(
                isset($editing['employeeId']) ? (int) $editing['employeeId'] : null,
            ),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'per_page' => $perPage,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'filterOptions' => [
                'sort' => [
                    ['value' => 'name', 'label' => 'Nama'],
                    ['value' => 'rate', 'label' => 'Tarif'],
                    ['value' => 'status', 'label' => 'Status'],
                ],
                'direction' => [
                    ['value' => 'asc', 'label' => 'A–Z / terkecil'],
                    ['value' => 'desc', 'label' => 'Z–A / terbesar'],
                ],
            ],
        ]);
    }

    /**
     * Open the create form on the craftsman list.
     */
    public function create(): RedirectResponse
    {
        return to_route('master-data.pengrajin.index', ['form' => 'create']);
    }

    /**
     * Store a newly created craftsman.
     */
    public function store(StoreMsCraftsmanRequest $request): RedirectResponse
    {
        $actor = $this->actorName($request);

        MsCraftsman::query()->create([
            'name' => $request->validated('name'),
            'rate' => $request->validated('rate'),
            'is_active' => $request->validated('is_active'),
            'employee_id' => $employeeId = $request->validated('employee_id'),
            'user_id' => $this->userIdForEmployee($employeeId),
            'is_deleted' => 0,
            'created_date' => now(),
            'created_by' => $actor,
            'modified_date' => now(),
            'modified_by' => $actor,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pengrajin berhasil ditambahkan.',
        ]);

        return to_route('master-data.pengrajin.index');
    }

    /**
     * Open the edit form on the craftsman list.
     */
    public function edit(MsCraftsman $msCraftsman): RedirectResponse
    {
        abort_if($msCraftsman->is_deleted === 1, 404);

        return to_route('master-data.pengrajin.index', [
            'edit' => $msCraftsman->row_id,
        ]);
    }

    /**
     * Update the specified craftsman.
     */
    public function update(UpdateMsCraftsmanRequest $request, MsCraftsman $msCraftsman): RedirectResponse
    {
        abort_if($msCraftsman->is_deleted === 1, 404);

        $msCraftsman->update([
            'name' => $request->validated('name'),
            'rate' => $request->validated('rate'),
            'is_active' => $request->validated('is_active'),
            'employee_id' => $employeeId = $request->validated('employee_id'),
            'user_id' => $this->userIdForEmployee($employeeId),
            'modified_date' => now(),
            'modified_by' => $this->actorName($request),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pengrajin berhasil diperbarui.',
        ]);

        return to_route('master-data.pengrajin.index');
    }

    /**
     * Soft-delete the specified craftsman.
     */
    public function destroy(Request $request, MsCraftsman $msCraftsman): RedirectResponse
    {
        abort_if($msCraftsman->is_deleted === 1, 404);

        $actor = $this->actorName($request);

        $msCraftsman->update([
            'is_deleted' => 1,
            'deleted_date' => now(),
            'deleted_by' => $actor,
            'modified_date' => now(),
            'modified_by' => $actor,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pengrajin berhasil dihapus.',
        ]);

        return to_route('master-data.pengrajin.index');
    }

    /**
     * @param  Collection<int, mixed>  $ids
     * @return array{names: Collection<int, string>, positions: Collection<int, string>}
     */
    private function employeeListMeta(Collection $ids): array
    {
        $ids = $ids->filter(fn (mixed $id): bool => (int) $id > 0)->unique()->values();

        if ($ids->isEmpty()) {
            return [
                'names' => collect(),
                'positions' => collect(),
            ];
        }

        $employees = Employee::query()
            ->with('position:id,name')
            ->whereIn('id', $ids)
            ->get(['id', 'nama_lengkap', 'position_id']);

        return [
            'names' => $employees->pluck('nama_lengkap', 'id'),
            'positions' => $employees
                ->mapWithKeys(fn (Employee $employee): array => [
                    $employee->id => trim((string) $employee->position?->name),
                ])
                ->filter(fn (string $name): bool => $name !== ''),
        ];
    }

    /**
     * @return list<array{value: string, label: string, name: string}>
     */
    private function employeeOptions(?int $selectedId = null): array
    {
        $employees = Employee::query()
            ->productionActive()
            ->whereNotNull('nama_lengkap')
            ->where('nama_lengkap', '!=', '')
            ->orderBy('nama_lengkap')
            ->with('position:id,name')
            ->get(['id', 'nama_lengkap', 'nama_panggilan', 'status', 'position_id']);

        if ($selectedId !== null && $employees->doesntContain('id', $selectedId)) {
            $selected = Employee::query()
                ->with('position:id,name')
                ->find($selectedId, ['id', 'nama_lengkap', 'nama_panggilan', 'status', 'position_id']);

            if ($selected !== null) {
                $employees->push($selected);
            }
        }

        return $employees
            ->map(function (Employee $employee): array {
                $label = (string) $employee->nama_lengkap;
                $nickname = trim((string) $employee->nama_panggilan);

                if ($nickname !== '' && strcasecmp($nickname, $label) !== 0) {
                    $label .= " · {$nickname}";
                }

                $position = trim((string) $employee->position?->name);

                if ($position !== '') {
                    $label .= " · {$position}";
                }

                if ($employee->status !== null && $employee->status !== Employee::STATUS_ACTIVE) {
                    $label .= " ({$employee->status})";
                }

                return [
                    'value' => (string) $employee->id,
                    'label' => $label,
                    'name' => (string) $employee->nama_lengkap,
                ];
            })
            ->values()
            ->all();
    }

    private function userIdForEmployee(mixed $employeeId): ?int
    {
        if ($employeeId === null) {
            return null;
        }

        $userId = User::query()
            ->where('employee_id', (int) $employeeId)
            ->where('is_deleted', 0)
            ->orderBy('id')
            ->value('id');

        return $userId !== null ? (int) $userId : null;
    }

    /**
     * @param  Collection<int, string>  $employeeNames
     * @param  Collection<int, string>  $positionNames
     * @return array{
     *     id: int,
     *     name: string|null,
     *     rate: string|null,
     *     isActive: string,
     *     employeeId: string|null,
     *     employeeName: string|null,
     *     positionName: string|null,
     *     userId: string|null
     * }
     */
    private function toListItem(MsCraftsman $craftsman, Collection $employeeNames, Collection $positionNames): array
    {
        $employeeId = $craftsman->employee_id;

        return [
            'id' => (int) $craftsman->row_id,
            'name' => $craftsman->name,
            'rate' => $craftsman->rate,
            'isActive' => $craftsman->is_active === 'YES' ? 'YES' : 'NO',
            'employeeId' => $employeeId !== null ? (string) $employeeId : null,
            'employeeName' => $employeeId !== null ? ($employeeNames[$employeeId] ?? null) : null,
            'positionName' => $employeeId !== null ? ($positionNames[$employeeId] ?? null) : null,
            'userId' => $craftsman->user_id !== null ? (string) $craftsman->user_id : null,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     name: string|null,
     *     rate: string|null,
     *     isActive: string,
     *     employeeId: string|null,
     *     userId: string|null
     * }
     */
    private function toFormItem(MsCraftsman $craftsman): array
    {
        return [
            'id' => (int) $craftsman->row_id,
            'name' => $craftsman->name,
            'rate' => $craftsman->rate,
            'isActive' => $craftsman->is_active === 'YES' ? 'YES' : 'NO',
            'employeeId' => $craftsman->employee_id !== null ? (string) $craftsman->employee_id : null,
            'userId' => $craftsman->user_id !== null ? (string) $craftsman->user_id : null,
        ];
    }

    private function actorName(Request $request): string
    {
        return $request->user()?->name ?? 'system';
    }
}
