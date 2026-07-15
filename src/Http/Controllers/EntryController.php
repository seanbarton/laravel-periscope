<?php

namespace TortoiseIT\LaravelPeriscope\Http\Controllers;

use TortoiseIT\LaravelPeriscope\Support\EntryFilters;
use TortoiseIT\LaravelPeriscope\Support\TelescopeEntryRepository;
use TortoiseIT\LaravelPeriscope\Support\TraceFormatter;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class EntryController extends Controller
{
    public function __invoke(Request $request, string $uuid, TelescopeEntryRepository $entries, TraceFormatter $traceFormatter)
    {
        $entry = $entries->find($uuid);
        $filters = EntryFilters::fromRequest($request);

        abort_if(! $entry, 404);

        $relatedEntries = $entries->batchEntries($entry->batch_id, $entry->uuid);
        $batchEntries = collect($relatedEntries)
            ->push($entry)
            ->sortBy('sequence')
            ->values();

        return view('periscope::entry', [
            'entry' => $entry,
            'stackTrace' => $traceFormatter->forEntry($entry),
            'errorTrail' => $traceFormatter->errorTrail($batchEntries),
            'relatedEntries' => $relatedEntries,
            'typeCounts' => $entries->typeCounts($filters),
            'filters' => $filters,
            'tags' => $entries->tags((string) $request->query('tag_search', '')),
        ]);
    }
}
