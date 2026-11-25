<?php

namespace App\Http\Requests\Master;

trait AuthorizesMasterData
{
    protected function prepareForValidation(): void
    {
        $this->normalizeMasterDataCode();
    }

    protected function normalizeMasterDataCode(): void
    {
        $code = $this->input('code');

        if (is_string($code)) {
            $this->merge([
                'code' => mb_strtoupper($code, 'UTF-8'),
            ]);
        }
    }

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
