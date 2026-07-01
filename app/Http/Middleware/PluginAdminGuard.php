<?php

namespace App\Http\Middleware;

use App\Services\PluginNavRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PluginAdminGuard
{
    public function __construct(protected PluginNavRegistry $navRegistry) {}

    public function handle(Request $request, Closure $next)
    {
        $routeName = $request->route()?->getName();

        if (!$routeName) {
            return $next($request);
        }

        $adminPatterns = [];
        $publicPatterns = [];

        foreach ($this->navRegistry->all() as $item) {
            $pattern = $item['route_pattern'] ?? '';
            if (!$pattern) continue;
            if ($item['admin_only'] ?? false) {
                $adminPatterns[] = $pattern;
            } else {
                $publicPatterns[] = $pattern;
            }
        }

        if (empty($adminPatterns)) {
            return $next($request);
        }

        $matchesAdmin = false;
        foreach ($adminPatterns as $pattern) {
            if (Str::is($pattern, $routeName)) {
                $matchesAdmin = true;
                break;
            }
        }

        if (!$matchesAdmin) {
            return $next($request);
        }

        foreach ($publicPatterns as $pattern) {
            if (Str::is($pattern, $routeName)) {
                return $next($request);
            }
        }

        $user = $request->user();
        if ($user && $user->level !== 'admin') {
            abort(403);
        }

        return $next($request);
    }
}
