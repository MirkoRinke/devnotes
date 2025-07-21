<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Carbon\Carbon;

use app\Traits\ApiResponses;

class EnsurePrivacyPolicyAccepted {

    /**
     *  The traits used in the controller
     */
    use ApiResponses;

    public function handle(Request $request, Closure $next) {
        $user = $request->user();

        $defaultPolicyDate = Carbon::now()->subDays(2)->format('Y-m-d H:i:s');
        $policyDate = env('CURRENT_PRIVACY_POLICY_DATE', $defaultPolicyDate);

        $tokenId = $user->currentAccessToken()->id;
        $token = $request->user()->tokens()->where('id', $tokenId)->first();

        if (!$user || !$user->privacy_policy_accepted_at ||  $user->privacy_policy_accepted_at < $policyDate) {
            $token->delete();
            return $this->errorResponse('You must accept the privacy policy to continue.', 'PRIVACY_POLICY_NOT_ACCEPTED', 403);
        }

        return $next($request);
    }
}
