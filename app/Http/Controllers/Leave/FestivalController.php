<?php

namespace App\Http\Controllers\Leave;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\StoreFestivalRequest;
use App\Http\Requests\Leave\UpdateFestivalRequest;
use App\Models\Festival;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;

class FestivalController extends Controller
{
    public function index(): Response
    {
        $festivals = Festival::query()
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Festival $festival): array => $this->payload($festival));

        return Inertia::render('festivals/index', [
            'festivals' => $festivals,
        ]);
    }

    public function create(): Response
    {
        $countries = DB::connection('salescrm')
            ->table('countries')
            ->where('status', 1)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('festivals/create', [
            'countries' => $countries,
        ]);
    }

    public function store(StoreFestivalRequest $request): RedirectResponse
    {
        $payload = $request->payload();
        $countries = $payload['countries'] ?? [];
        unset($payload['countries']);

        $festival = Festival::query()->create($payload);

        if (!empty($countries)) {
            $insertData = array_map(fn($countryId) => [
                'festival_id' => $festival->id,
                'country_id' => (int) $countryId,
                'created_at' => now(),
                'updated_at' => now(),
            ], $countries);
            DB::table('festival_nationality')->insert($insertData);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Festival created.'),
        ]);

        return to_route('festivals.index');
    }

    public function edit(Festival $festival): Response
    {
        $countries = DB::connection('salescrm')
            ->table('countries')
            ->where('status', 1)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('festivals/edit', [
            'festival' => $this->payload($festival),
            'countries' => $countries,
        ]);
    }

    public function update(
        UpdateFestivalRequest $request,
        Festival $festival,
    ): RedirectResponse {
        $payload = $request->payload();
        $countries = $payload['countries'] ?? [];
        unset($payload['countries']);

        $festival->update($payload);

        DB::table('festival_nationality')->where('festival_id', $festival->id)->delete();
        if (!empty($countries)) {
            $insertData = array_map(fn($countryId) => [
                'festival_id' => $festival->id,
                'country_id' => (int) $countryId,
                'created_at' => now(),
                'updated_at' => now(),
            ], $countries);
            DB::table('festival_nationality')->insert($insertData);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Festival updated.'),
        ]);

        return to_route('festivals.index');
    }

    public function destroy(Festival $festival): RedirectResponse
    {
        $festival->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Festival deleted.'),
        ]);

        return to_route('festivals.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Festival $festival): array
    {
        $countries = DB::table('festival_nationality')
            ->where('festival_id', $festival->id)
            ->pluck('country_id')
            ->toArray();

        return [
            'id' => $festival->id,
            'name' => $festival->name,
            'type' => $festival->type,
            'shortcode' => $festival->shortcode,
            'is_active' => (int) $festival->is_active,
            'start_date' => $festival->start_date?->format('Y-m-d'),
            'end_date' => $festival->end_date?->format('Y-m-d'),
            'countries' => $countries,
        ];
    }
}
