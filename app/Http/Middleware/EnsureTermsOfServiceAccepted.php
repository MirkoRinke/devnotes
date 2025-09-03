<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Carbon\Carbon;

use app\Traits\ApiResponses;

class EnsureTermsOfServiceAccepted {

    /**
     *  The traits used in the controller
     */
    use ApiResponses;

    public function handle(Request $request, Closure $next) {
        $user = $request->user();

        $defaultTermsOfServiceDate = Carbon::now()->subDays(2)->format('Y-m-d H:i:s');
        $termsOfServiceDate = env('CURRENT_TERMS_OF_SERVICE_DATE', $defaultTermsOfServiceDate);

        $tokenId = $user->currentAccessToken()->id;
        $token = $request->user()->tokens()->where('id', $tokenId)->first();

        if (!$user || !$user->terms_of_service_accepted_at ||  $user->terms_of_service_accepted_at < $termsOfServiceDate) {
            $token->delete();
            return $this->errorResponse('You must accept the terms of service to continue.', 'TERMS_OF_SERVICE_NOT_ACCEPTED', 403);
        }

        return $next($request);
    }
}
