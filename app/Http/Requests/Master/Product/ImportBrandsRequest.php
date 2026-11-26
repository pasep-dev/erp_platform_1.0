<?php

namespace App\Http\Requests\Master\Product;

use App\Http\Requests\Master\AuthorizesMasterData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class ImportBrandsRequest extends FormRequest
{
    use AuthorizesMasterData;

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', File::types(['xlsx'])->max(5120)],
        ];
    }
}
