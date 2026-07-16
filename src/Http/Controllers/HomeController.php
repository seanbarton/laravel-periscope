<?php

namespace TortoiseIT\LaravelPeriscope\Http\Controllers;

use TortoiseIT\LaravelPeriscope\Support\EntryFilters;
use TortoiseIT\LaravelPeriscope\Support\TelescopeEntryRepository;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class HomeController extends Controller
{
    public function __invoke(Request $request, TelescopeEntryRepository $entries)
    {
        $filters = EntryFilters::fromRequest($request);

        return view('periscope::overview', [
            'filters' => $filters,
            'requestOverview' => $entries->requestOverview($filters),
            'typeCounts' => $entries->typeCounts($filters),
            'types' => $entries->types(),
            'tags' => $entries->tags((string) $request->query('tag_search', '')),
            'topbarAction' => route('periscope.index'),
        ]);
    }
}
