<?php

namespace TortoiseIT\LaravelPeriscope\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use TortoiseIT\LaravelPeriscope\Support\EntryFilters;
use TortoiseIT\LaravelPeriscope\Support\TelescopeEntryRepository;

class ScheduleRunsController extends Controller
{
    public function __invoke(Request $request, string $commandKey, TelescopeEntryRepository $entries)
    {
        $filters = EntryFilters::fromRequest($request);
        $results = $entries->scheduledCommandRuns($commandKey, $filters);
        $hasMore = $results->count() > $filters->perPage;
        $visibleResults = $results->take($filters->perPage);
        $nextBefore = $hasMore ? $visibleResults->last()?->sequence : null;

        return view('periscope::schedule-runs', [
            'commandKey' => $commandKey,
            'commandLabel' => (string) $request->query('label', 'Scheduled command'),
            'filters' => $filters,
            'entries' => $visibleResults,
            'hasMore' => $hasMore,
            'nextBefore' => $nextBefore,
            'typeCounts' => $entries->typeCounts($filters),
            'types' => $entries->types(),
            'tags' => $entries->tags((string) $request->query('tag_search', '')),
            'topbarAction' => route('periscope.schedule.show', ['commandKey' => $commandKey, 'label' => (string) $request->query('label', 'Scheduled command')]),
        ]);
    }
}