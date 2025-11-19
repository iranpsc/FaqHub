<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Jobs\FetchUserLevel;
use App\Notifications\LoginNotification;
use App\Services\UsernameGenerator;

class AuthController extends Controller
{
    /**
     * Redirect to the OAuth server for authentication.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function redirect(Request $request)
    {
        cache()->put('state', $state = Str::random(40));

        // Cache the intended URL if provided
        if ($request->has('intended_url')) {
            cache()->put('intended_url', $request->input('intended_url'));
        }

        $query = http_build_query([
            'client_id' => config('services.oauth.client_id'),
            'redirect_uri' => config('services.oauth.redirect'),
            'response_type' => 'code',
            'scope' => '',
            'state' => $state,
            // 'prompt' => '', // "none", "consent", or "login"
        ]);

        return response()->json([
            'redirect_url' => config('services.oauth.url') . '/oauth/authorize?' . $query
        ]);
    }

    /**
     * Handle the OAuth callback.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function callback(Request $request)
    {
        $state = cache()->pull('state');

        throw_unless(
            strlen($state) > 0 && $state === $request->state,
            \InvalidArgumentException::class
        );

        $response = Http::asForm()->post(config('services.oauth.url') . '/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => config('services.oauth.client_id'),
            'client_secret' => config('services.oauth.client_secret'),
            'redirect_uri' => config('services.oauth.redirect'),
            'code' => $request->code,
        ]);

        $accessToken = $response->json('access_token');

        $userResponse = Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken,
        ])
        ->acceptJson()
        ->get(config('services.oauth.url') . '/api/user');

        $userArray = $userResponse->json();

        $user = User::updateOrCreate(
            [
                'email' => $userArray['email'],
            ],
            [
                'name' => $userArray['name'],
                'mobile' => $userArray['mobile'],
                'code' => $userArray['code'],
                'access_token' => $accessToken,
                'refresh_token' => $response->json('refresh_token'),
                'expires_in' => $response->json('expires_in'),
                'token_type' => $response->json('token_type'),
            ]
        );

        if (empty($user->username)) {
            $user->update([
                'username' => UsernameGenerator::generate($user->name, $user->id),
            ]);
        }

        $user->update(['email_verified_at' => $userArray['email_verified_at']]);

        if($user->wasChanged('email_verified_at')) {
            $user->increment('score', 10); // Increment score for new user
        }

        $this->guard()->login($user);

        // Send login notification if enabled
        if ($user->login_notification_enabled) {
            $loginData = [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ];
            $user->notify(new LoginNotification($user, $loginData));
        }

        // Dispatch job to fetch user level
        FetchUserLevel::dispatch($user);

        $token = $user->createToken('auth-token')->plainTextToken;

                // Get cached intended URL and clean up session
        $intendedUrl = cache()->pull('intended_url');

        // Validate and sanitize the intended URL
        // Falls back to frontend app URL from FRONTEND_APP_URL env variable
        $baseUrl = $this->validateAndSanitizeUrl($intendedUrl) ?: config('services.oauth.app_url');

        // Use URL fragment to avoid leaking tokens via Referer headers
        return redirect($baseUrl . '#token=' . $token);
    }

    /**
     * Get the authenticated user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request)
    {
        return response()->json($request->user());
    }


    /**
     * Log out the user and revoke all tokens.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        $user->tokens()->delete();
        $this->guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * Validate and sanitize the intended URL to prevent open redirects.
     *
     * @param string|null $url
     * @return string|null
     */
    protected function validateAndSanitizeUrl($url)
    {
        if (!$url) {
            return null;
        }

        // Parse the URL
        $parsedUrl = parse_url($url);

        // Reject malformed URLs
        if ($parsedUrl === false) {
            return null;
        }

        // Get allowed domains from frontend app URL
        $appDomain = parse_url(config('services.oauth.app_url'), PHP_URL_HOST);
        $urlDomain = $parsedUrl['host'] ?? null;

        // Only allow redirects to the same domain as the app
        if ($urlDomain !== $appDomain) {
            return null;
        }

        // Ensure the URL uses HTTPS in production
        if (app()->environment('production') && ($parsedUrl['scheme'] ?? '') !== 'https') {
            return null;
        }

        // Reconstruct a clean URL
        $scheme = $parsedUrl['scheme'] ?? 'https';
        $host = $parsedUrl['host'];
        $port = isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '';
        $path = $parsedUrl['path'] ?? '/';
        $query = isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '';

        return $scheme . '://' . $host . $port . $path . $query;
    }

    /**
     * Get the guard for the controller.
     *
     * @return \Illuminate\Contracts\Auth\StatefulGuard
     */
    protected function guard()
    {
        return Auth::guard('web');
    }
}
