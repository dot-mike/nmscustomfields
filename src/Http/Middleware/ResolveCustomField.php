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
        $customdevicefield = $request->route('customdevicefield');
        $customdevicefield = strtolower($customdevicefield);

        if ($customdevicefield) {
            if ($customdevicefield instanceof CustomFieldDevice) {
                return $next($request);
            } else {
                $customdevicefieldModel = null;

                if (is_numeric($customdevicefield)) {
                    $customdevicefieldModel = CustomFieldDevice::findOrFail($customdevicefield);
                } else {
                    $device = $request->route('device');
                    $customdevicefieldModel = CustomFieldDevice::whereHas('customField', function ($query) use ($customdevicefield) {
                        $query->whereRaw('LOWER(name) = ?', [$customdevicefield]);
                    })->where('device_id', $device->device_id)->firstOrFail();
                }

                $request->route()->setParameter('customdevicefield', $customdevicefieldModel);
            }

            $request->route()->setParameter('customdevicefield', $customdevicefieldModel);
        }

        return $next($request);
    }
}
