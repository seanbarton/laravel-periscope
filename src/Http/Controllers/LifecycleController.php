<?php

namespace TortoiseIT\LaravelPeriscope\Http\Controllers;

use TortoiseIT\LaravelPeriscope\Support\EntryFilters;
use TortoiseIT\LaravelPeriscope\Support\EntryLifecycle;
use TortoiseIT\LaravelPeriscope\Support\TelescopeEntryRepository;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LifecycleController extends Controller
{
    public function __invoke(Request $request, string $uuid, TelescopeEntryRepository $entries, EntryLifecycle $lifecycle)
    {
        $entry = $entries->find($uuid);
        $filters = EntryFilters::fromRequest($request);

        abort_if(! $entry, 404);

        $batchEntries = $entries->batchEntries($entry->batch_id, limit: 300);

        return view('periscope::lifecycle', [
            'entry' => $entry,
            'lifecycle' => $lifecycle->build($entry, $batchEntries),
            'typeCounts' => $entries->typeCounts($filters),
            'filters' => $filters,
            'tags' => $entries->tags((string) $request->query('tag_search', '')),
        ]);
    }
}
