<?php

namespace DotMike\NmsCustomFields\Http\Middleware;

use App\Models\Device;
use Illuminate\Http\Request;
use Closure;

class ResolveDevice
{

    /**
     * Resolve the device from the route parameter.
     * 
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $device = $request->route('device');

        if ($device) {
            if ($device instanceof Device) {
                return $next($request);
            } else {
                if (is_numeric($device)) {
                    $deviceModel = Device::findOrFail($device);
                } else {
                    $deviceModel = Device::where('hostname', $device)->firstOrFail();
                }
            }

            $request->route()->setParameter('device', $deviceModel);
        }

        return $next($request);
    }
}
