<?php

namespace TortoiseIT\LaravelPeriscope\Http\Controllers;

use TortoiseIT\LaravelPeriscope\Support\EntryFilters;
use TortoiseIT\LaravelPeriscope\Support\TelescopeEntryRepository;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class EntryController extends Controller
{
    public function __invoke(Request $request, string $uuid, TelescopeEntryRepository $entries)
    {
        $entry = $entries->find($uuid);
        $filters = EntryFilters::fromRequest($request);

        abort_if(! $entry, 404);

        return view('periscope::entry', [
            'entry' => $entry,
            'relatedEntries' => $entries->batchEntries($entry->batch_id, $entry->uuid),
            'typeCounts' => $entries->typeCounts($filters),
            'filters' => $filters,
            'tags' => $entries->tags((string) $request->query('tag_search', '')),
        ]);
    }
}
