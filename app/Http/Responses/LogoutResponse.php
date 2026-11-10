<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LogoutResponse implements LogoutResponseContract
{
    /**
     * Create the response returned after logout.
     *
     * @param  Request  $request
     * @return Response
     */
    public function toResponse($request)
    {
        return redirect()->route('login');
    }
}
