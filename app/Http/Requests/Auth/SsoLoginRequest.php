<?php

namespace App\Http\Requests\Auth;

use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;
use Laravel\Fortify\Fortify;

class SsoLoginRequest extends FortifyLoginRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            Fortify::username() => 'required|string',
        ];
    }
}
