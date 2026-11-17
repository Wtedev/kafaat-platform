<?php

namespace App\Http\Middleware;

use App\Services\ExperienceHouses\ManagerAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureExperienceHouseManager
{
    public function __construct(private readonly ManagerAccess $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $manager = $this->access->current($request);
        if ($manager === null) {
            return redirect()->route('experience-houses.manage.login');
        }

        $request->attributes->set('eh_manager', $manager);
        view()->share('manager', $manager);

        return $next($request);
    }
}
