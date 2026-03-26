<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Symfony\Component\HttpFoundation\Response;

class SsoLogoutResponse implements LogoutResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     */
    public function toResponse($request): Response
    {
        $ssoLogoutUrl = config('services.sso.logout_url');

        if ($ssoLogoutUrl) {
            return redirect()->away($ssoLogoutUrl);
        }

        return $request->wantsJson()
            ? new JsonResponse('', 204)
            : redirect('/');
    }
}
