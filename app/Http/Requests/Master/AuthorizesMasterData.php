<?php

namespace App\Http\Requests\Master;

trait AuthorizesMasterData
{
    public function authorize(): bool
    {
        $user = $this->user();
        $routeName = $this->route()?->getName();

        if ($user === null || ! is_string($routeName)) {
            return false;
        }

        $segments = explode('.', $routeName);
        $permissionPrefix = implode('.', array_slice($segments, 0, 2));
        $action = $this->isMethod('POST') ? 'create' : 'update';

        return $user->can("{$permissionPrefix}.{$action}");
    }
}
