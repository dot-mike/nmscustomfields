<?php

namespace DotMike\NmsCustomFields\Http\Middleware;

use DotMike\NmsCustomFields\Models\CustomFieldDevice;

use Illuminate\Http\Request;
use Closure;

class ResolveCustomField
{

    /**
     * Resolve the custom field from the route parameter.
     * 
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $param = $request->route('customdevicefield');

        // No route parameter, or already resolved by Laravel's implicit binding.
        if ($param === null || $param instanceof CustomFieldDevice) {
            return $next($request);
        }

        if (is_numeric($param)) {
            $model = CustomFieldDevice::findOrFail($param);
        } else {
            $device = $request->route('device');
            $model = CustomFieldDevice::whereHas('customField', function ($query) use ($param) {
                $query->whereRaw('LOWER(name) = ?', [strtolower((string) $param)]);
            })->where('device_id', $device->device_id)->firstOrFail();
        }

        $request->route()->setParameter('customdevicefield', $model);

        return $next($request);
    }
}
