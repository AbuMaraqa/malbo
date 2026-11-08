<?php

namespace Modules\Branch\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Branch\Entities\Branch;
use Modules\Branch\Entities\BranchWorkingHour;
use Modules\Branch\Enums\BranchStatus;
use Modules\Branch\Enums\WeekDay;
use Modules\Branch\Http\Requests\Admin\BranchRequest;
use Modules\Branch\Repositories\Contracts\BranchRepositoryInterface;
use Modules\Branch\Services\BranchService;
use Modules\Core\Support\AdminFilter;
use Modules\Core\Support\AdminTable;
use Modules\GeoLocation\Repositories\Contracts\CityRepositoryInterface;
use Modules\GeoLocation\Repositories\Contracts\CountryRepositoryInterface;
use Modules\GeoLocation\Support\GeoFilters;

class BranchController extends Controller
{
    public function __construct(
        private readonly BranchRepositoryInterface $branches,
        private readonly BranchService $branchService,
        private readonly CountryRepositoryInterface $countries,
        private readonly CityRepositoryInterface $cities,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Branch/Branch/Index', [
            'table' => AdminTable::for($this->branches->tableQuery())
                ->search(['phone', 'email'])
                ->filters(
                    AdminFilter::translated('name'),
                    GeoFilters::integer('status'),
                    AdminFilter::relation('country_id', 'country'),
                    AdminFilter::relation('city_id', 'city'),
                    AdminFilter::text('phone'),
                    AdminFilter::text('email'),
                    GeoFilters::locale(),
                    ...AdminFilter::dateRange('created_at'),
                )
                ->sorts(GeoFilters::nameSort(), 'status', 'opening_time', 'closing_time', 'created_at', 'id')
                ->rows(fn (Branch $branch) => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'description' => $branch->description,
                    'address' => $branch->address,
                    'phone' => $branch->phone,
                    'email' => $branch->email,
                    'country' => $branch->country?->name,
                    'city' => $branch->city?->name,
                    'opening_time' => $this->time($branch->opening_time),
                    'closing_time' => $this->time($branch->closing_time),
                    'latitude' => $branch->latitude,
                    'longitude' => $branch->longitude,
                    'status' => $branch->status?->value,
                    'status_label' => $branch->status?->getLabel(),
                    'open_days' => $branch->workingHours
                        ->reject(fn (BranchWorkingHour $hour) => $hour->is_closed)
                        ->sortBy(fn (BranchWorkingHour $hour) => $hour->day->value)
                        ->map(fn (BranchWorkingHour $hour) => $hour->day->getLabel())
                        ->values(),
                    'locales' => $branch->translations->pluck('locale'),
                ]),
            'statuses' => $this->statusOptions(),
            'countries' => $this->countries->options(),
            'cities' => $this->cities->options(),
            'locales' => Locales::keys(),
        ]);
    }

    public function add(): Response
    {
        return Inertia::render('Branch/Branch/Add', [
            ...$this->formOptions(),
            'workingHours' => $this->weeklySchedule(null),
        ]);
    }

    public function store(BranchRequest $request): RedirectResponse
    {
        $this->branchService->create($request->validated(), app()->getLocale(), $request->user()->id);

        return to_route('branches.index')->with('success', __('Branch created successfully'));
    }

    public function edit(int $branch): Response
    {
        /** @var Branch $model */
        $model = $this->branches->findOrFail($branch);
        $translation = $model->translate(app()->getLocale(), false);

        return Inertia::render('Branch/Branch/Edit', [
            'branch' => [
                'id' => $model->id,
                'name' => $translation?->name ?? '',
                'description' => $translation?->description ?? '',
                'address' => $translation?->address ?? '',
                'email' => $model->email,
                'phone' => $model->phone,
                'latitude' => $model->latitude,
                'longitude' => $model->longitude,
                'opening_time' => $this->time($model->opening_time),
                'closing_time' => $this->time($model->closing_time),
                'country_id' => $model->country_id,
                'city_id' => $model->city_id,
                'status' => $model->status?->value,
                'working_hours' => $this->weeklySchedule($model),
            ],
            ...$this->formOptions(),
        ]);
    }

    public function update(BranchRequest $request, int $branch): RedirectResponse
    {
        /** @var Branch $model */
        $model = $this->branches->findOrFail($branch);

        $this->branchService->update($model, $request->validated(), app()->getLocale());

        return to_route('branches.index')->with('success', __('Branch updated successfully'));
    }

    public function destroy(int $branch): RedirectResponse
    {
        /** @var Branch $model */
        $model = $this->branches->findOrFail($branch);

        $this->branchService->delete($model);

        return back()->with('success', __('Branch deleted successfully'));
    }

    public function destroyMany(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];

        $this->branchService->deleteMany($ids);

        return back()->with('success', __('Branches deleted successfully'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'statuses' => $this->statusOptions(),
            'countries' => $this->countries->options(),
            'cities' => $this->cities->options(),
            'days' => array_map(
                fn (WeekDay $day) => ['value' => $day->value, 'label' => $day->getLabel()],
                WeekDay::cases(),
            ),
        ];
    }

    /**
     * Seven rows for the form. A day the branch has no row for starts from the branch's own hours.
     *
     * @return list<array{day: int, is_closed: bool, start_time: string|null, end_time: string|null}>
     */
    private function weeklySchedule(?Branch $branch): array
    {
        $saved = $branch ? $branch->workingHours->keyBy(fn (BranchWorkingHour $hour) => $hour->day->value) : collect();

        return array_map(function (WeekDay $day) use ($saved, $branch): array {
            /** @var BranchWorkingHour|null $hour */
            $hour = $saved->get($day->value);

            return [
                'day' => $day->value,
                'is_closed' => $hour?->is_closed ?? false,
                'start_time' => $hour ? $hour->start_time?->format('H:i') : ($this->time($branch?->opening_time) ?? '08:00'),
                'end_time' => $hour ? $hour->end_time?->format('H:i') : ($this->time($branch?->closing_time) ?? '17:00'),
            ];
        }, WeekDay::cases());
    }

    /**
     * @return list<array{value: int, label: string|null}>
     */
    private function statusOptions(): array
    {
        return array_map(
            fn (BranchStatus $status) => ['value' => $status->value, 'label' => $status->getLabel()],
            BranchStatus::cases(),
        );
    }

    /**
     * PostgreSQL gives a time as 08:00:00; the time input and the table use 08:00.
     */
    private function time(?string $time): ?string
    {
        return $time === null ? null : substr($time, 0, 5);
    }
}
