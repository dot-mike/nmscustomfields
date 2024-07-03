<?php

namespace DotMike\NmsCustomFields\Http\Controllers;

use Illuminate\Routing\Controller;

use Gate;

class PluginAdminController extends Controller
{

    // show plugin main page
    // GET /plugins/nmscustomfields
    public function index()
    {
        Gate::authorize('admin');

        return view('nmscustomfields::main');
    }
}
