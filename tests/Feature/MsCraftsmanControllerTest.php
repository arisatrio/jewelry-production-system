<?php

use App\Models\Employee;
use App\Models\MsCraftsman;
use App\Models\Position;
use App\Models\User;
use Illuminate\Support\Str;

test('pengrajin index page is accessible', function () {
    $this->get(route('master-data.pengrajin.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('master-data/pengrajin/index')
            ->has('craftsmen.data')
            ->has('craftsmen.total')
            ->has('employees')
            ->has('labelTukangOptions')
            ->where('filters.search', '')
            ->where('filters.status', 'active')
            ->where('filters.sort', 'name')
            ->where('filters.direction', 'asc')
            ->has('filters.per_page')
            ->has('filterOptions.sort')
            ->has('filterOptions.direction')
        );
});

test('pengrajin index page can filter by search query', function () {
    $position = Position::factory()->create([
        'name' => 'Pengrajin Perak',
    ]);
    $employee = Employee::factory()->create([
        'nama_lengkap' => 'Pegawai Filter Pengrajin',
        'position_id' => $position->id,
    ]);
    $craftsman = MsCraftsman::factory()->create([
        'name' => 'FilterPengrajin '.Str::upper(Str::random(8)),
        'employee_id' => $employee->id,
    ]);

    $this->get(route('master-data.pengrajin.index', ['search' => $craftsman->name]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('master-data/pengrajin/index')
            ->where('filters.search', $craftsman->name)
            ->has('craftsmen.data', 1)
            ->where('craftsmen.data.0.name', $craftsman->name)
            ->where('craftsmen.data.0.employeeName', 'Pegawai Filter Pengrajin')
            ->has('craftsmen.data.0.labelTukang')
            ->where('craftsmen.data.0.positionName', 'Pengrajin Perak')
            ->missing('craftsmen.data.0.userName')
        );

    $craftsman->delete();
});

test('pengrajin employee options include the position name', function () {
    $position = Position::factory()->create([
        'name' => 'Pengrajin Perak',
    ]);
    $employee = Employee::factory()->create([
        'nama_lengkap' => 'Edi Jabatan',
        'nama_panggilan' => 'Edi Jabatan',
        'position_id' => $position->id,
    ]);

    $this->get(route('master-data.pengrajin.index', ['form' => 'create']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('employees', function (mixed $employees) use ($employee): bool {
                $option = collect($employees)->firstWhere('value', (string) $employee->id);

                return is_array($option)
                    && $option['label'] === 'Edi Jabatan · Pengrajin Perak'
                    && $option['name'] === 'Edi Jabatan';
            })
        );
});

test('pengrajin form only lists active employees', function () {
    $active = Employee::factory()->create([
        'nama_lengkap' => 'Pegawai Aktif '.Str::upper(Str::random(6)),
        'status' => Employee::STATUS_ACTIVE,
    ]);
    $inactive = Employee::factory()->create([
        'nama_lengkap' => 'Pegawai Nonaktif '.Str::upper(Str::random(6)),
        'status' => 'resigned',
    ]);
    $otherDepartment = Employee::factory()->create([
        'nama_lengkap' => 'Pegawai Store '.Str::upper(Str::random(6)),
        'status' => Employee::STATUS_ACTIVE,
        'department_id' => 5,
    ]);

    $this->get(route('master-data.pengrajin.index', ['form' => 'create']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('employees', function (mixed $employees) use ($active, $inactive, $otherDepartment): bool {
                $values = collect($employees)->pluck('value');

                return $values->contains((string) $active->id)
                    && $values->doesntContain((string) $inactive->id)
                    && $values->doesntContain((string) $otherDepartment->id);
            })
        );

    $this->from(route('master-data.pengrajin.create'))
        ->post(route('master-data.pengrajin.store'), [
            'name' => 'InactiveLink '.Str::upper(Str::random(6)),
            'is_active' => 'YES',
            'employee_id' => $inactive->id,
        ])
        ->assertRedirect(route('master-data.pengrajin.create'))
        ->assertSessionHasErrors('employee_id');

    $this->from(route('master-data.pengrajin.create'))
        ->post(route('master-data.pengrajin.store'), [
            'name' => 'OtherDeptLink '.Str::upper(Str::random(6)),
            'is_active' => 'YES',
            'employee_id' => $otherDepartment->id,
        ])
        ->assertRedirect(route('master-data.pengrajin.create'))
        ->assertSessionHasErrors('employee_id');
});

test('pengrajin edit keeps the linked employee outside production', function () {
    $employee = Employee::factory()->create([
        'nama_lengkap' => 'Pegawai JB '.Str::upper(Str::random(6)),
        'status' => Employee::STATUS_ACTIVE,
        'department_id' => 5,
    ]);
    $craftsman = MsCraftsman::factory()->create([
        'employee_id' => $employee->id,
    ]);

    $this->get(route('master-data.pengrajin.index', ['edit' => $craftsman->row_id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('employees', function (mixed $employees) use ($employee): bool {
                return collect($employees)->contains(
                    fn (mixed $option): bool => is_array($option) && $option['value'] === (string) $employee->id,
                );
            })
        );

    $craftsman->delete();
});

test('pengrajin create opens the list form', function () {
    $this->get(route('master-data.pengrajin.create'))
        ->assertRedirect(route('master-data.pengrajin.index', ['form' => 'create']));

    $this->get(route('master-data.pengrajin.index', ['form' => 'create']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('master-data/pengrajin/index')
            ->where('formMode', 'create')
            ->where('editing', null)
            ->has('employees')
        );
});

test('pengrajin can be stored with employee and user', function () {
    $employee = Employee::factory()->create();
    $user = User::factory()->create();
    $user->forceFill(['employee_id' => $employee->id])->save();
    $name = 'CreatePengrajin '.Str::upper(Str::random(8));

    $this->post(route('master-data.pengrajin.store'), [
        'name' => $name,
        'rate' => '1500.50',
        'is_active' => 'YES',
        'employee_id' => $employee->id,
        'label_tukang' => 'Tukang Poles',
        'user_id' => 999999,
    ])->assertRedirect(route('master-data.pengrajin.index'));

    $craftsman = MsCraftsman::query()->notDeleted()->where('name', $name)->first();

    expect($craftsman)->not->toBeNull()
        ->and($craftsman->rate)->toBe('1500.50')
        ->and($craftsman->is_active)->toBe('YES')
        ->and($craftsman->employee_id)->toBe($employee->id)
        ->and($craftsman->label_tukang)->toBe('Tukang Poles')
        ->and($craftsman->user_id)->toBe($user->id)
        ->and($craftsman->created_by)->toBe('system');

    $craftsman->delete();
});

test('pengrajin label tukang options include saved labels', function () {
    $label = 'LabelTukang '.Str::upper(Str::random(6));
    $craftsman = MsCraftsman::factory()->create([
        'label_tukang' => $label,
    ]);

    $this->get(route('master-data.pengrajin.index', ['form' => 'create']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('labelTukangOptions', function (mixed $options) use ($label): bool {
                return collect($options)->contains(
                    fn (mixed $option): bool => is_array($option)
                        && ($option['value'] ?? null) === $label
                        && ($option['label'] ?? null) === $label,
                );
            })
        );

    $craftsman->delete();
});

test('pengrajin store validates unique name and existing links', function () {
    $craftsman = MsCraftsman::factory()->create([
        'name' => 'UniquePengrajin '.Str::upper(Str::random(8)),
    ]);

    $this->from(route('master-data.pengrajin.create'))
        ->post(route('master-data.pengrajin.store'), [
            'name' => $craftsman->name,
            'is_active' => 'YES',
            'employee_id' => 999999,
        ])
        ->assertRedirect(route('master-data.pengrajin.create'))
        ->assertSessionHasErrors(['name', 'employee_id']);

    $craftsman->delete();
});

test('pengrajin edit opens the list form', function () {
    $craftsman = MsCraftsman::factory()->create([
        'name' => 'EditPengrajin '.Str::upper(Str::random(8)),
        'is_active' => 'NO',
    ]);

    $this->get(route('master-data.pengrajin.edit', $craftsman))
        ->assertRedirect(route('master-data.pengrajin.index', [
            'edit' => $craftsman->row_id,
        ]));

    $this->get(route('master-data.pengrajin.index', ['edit' => $craftsman->row_id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('master-data/pengrajin/index')
            ->where('editing.id', $craftsman->row_id)
            ->where('editing.name', $craftsman->name)
            ->where('editing.isActive', 'NO')
            ->has('employees')
        );

    $craftsman->delete();
});

test('pengrajin index can filter by status', function () {
    $token = 'StatusPengrajin'.Str::upper(Str::random(6));
    $active = MsCraftsman::factory()->create([
        'name' => $token.' Aktif',
        'is_active' => 'YES',
    ]);
    $inactive = MsCraftsman::factory()->create([
        'name' => $token.' Nonaktif',
        'is_active' => 'NO',
    ]);

    $this->get(route('master-data.pengrajin.index', [
        'search' => $token,
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.status', 'active')
            ->has('craftsmen.data', 1)
            ->where('craftsmen.data.0.name', $active->name)
        );

    $this->get(route('master-data.pengrajin.index', [
        'search' => $token,
        'status' => 'all',
    ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.status', 'all')
            ->has('craftsmen.data', 2)
        );

    $active->delete();
    $inactive->delete();
});

test('pengrajin can be updated and links can be cleared', function () {
    $employee = Employee::factory()->create();
    $user = User::factory()->create();
    $craftsman = MsCraftsman::factory()->create([
        'name' => 'BeforePengrajin '.Str::upper(Str::random(8)),
        'employee_id' => $employee->id,
        'user_id' => $user->id,
    ]);
    $newName = 'AfterPengrajin '.Str::upper(Str::random(8));

    $this->put(route('master-data.pengrajin.update', $craftsman), [
        'name' => $newName,
        'rate' => '',
        'is_active' => 'NO',
        'employee_id' => '',
    ])->assertRedirect(route('master-data.pengrajin.index'));

    $craftsman->refresh();

    expect($craftsman->name)->toBe($newName)
        ->and($craftsman->rate)->toBeNull()
        ->and($craftsman->is_active)->toBe('NO')
        ->and($craftsman->employee_id)->toBeNull()
        ->and($craftsman->user_id)->toBeNull()
        ->and($craftsman->modified_by)->toBe('system');

    $craftsman->delete();
});

test('pengrajin can be soft deleted', function () {
    $craftsman = MsCraftsman::factory()->create([
        'name' => 'DeletePengrajin '.Str::upper(Str::random(8)),
    ]);

    $this->delete(route('master-data.pengrajin.destroy', $craftsman))
        ->assertRedirect(route('master-data.pengrajin.index'));

    $craftsman->refresh();

    expect($craftsman->is_deleted)->toBe(1)
        ->and($craftsman->deleted_by)->toBe('system')
        ->and($craftsman->deleted_date)->not->toBeNull();

    $this->get(route('master-data.pengrajin.edit', $craftsman))
        ->assertNotFound();

    $craftsman->delete();
});

test('deleted pengrajin is hidden from index', function () {
    $craftsman = MsCraftsman::factory()->deleted()->create([
        'name' => 'HiddenPengrajin '.Str::upper(Str::random(8)),
    ]);

    $this->get(route('master-data.pengrajin.index', ['search' => $craftsman->name]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('craftsmen.total', 0)
            ->has('craftsmen.data', 0)
        );

    $craftsman->delete();
});
