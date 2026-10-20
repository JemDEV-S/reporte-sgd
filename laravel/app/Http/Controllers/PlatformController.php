<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PlatformController extends Controller
{
    public function __invoke(?string $area = null): View
    {
        $areas = collect(config('platform.areas'))->map(fn (array $item) => [
            ...$item, 'allowed' => Gate::allows($item['permission']),
        ]);

        if ($area !== null) {
            abort_unless($areas->has($area), 404);
            abort_unless($areas[$area]['allowed'], 403);
        }

        return view('platform', compact('areas', 'area'));
    }
}
