<ul class="nav nav-tabs">
    <li role="presentation" class="{{ Request::routeIs('plugin.nmscustomfields.index') ? 'active' : '' }}">
        <a href="{{ route('plugin.nmscustomfields.index') }}">Plugin Index</a>
    </li>
    <li role="presentation" class="{{ Request::routeIs('plugin.nmscustomfields.customfield.index') ? 'active' : '' }}">
        <a href="{{ route('plugin.nmscustomfields.customfield.index') }}">Manage Custom Fields</a>
    </li>
    <li role="presentation" class="{{ Request::routeIs('plugin.nmscustomfields.customfield.devices') ? 'active' : '' }}">
        <a href="{{ route('plugin.nmscustomfields.customfield.devices') }}">Show Custom Fields used by Devices</a>
    </li>
</ul>