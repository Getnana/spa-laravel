<?php

namespace App\Http\Controllers;

use App\Support\SpaValidation;
use Illuminate\Contracts\Validation\Validator;

abstract class Controller
{
    protected Validator $validator;

    protected function getJsonInput(): array
    {
        return request()->input();
    }

    protected function validate(array $rules, array $messages = []): bool
    {
        $this->validator = SpaValidation::make($this->getJsonInput(), $rules, $messages);
        return $this->validator->passes();
    }
}
