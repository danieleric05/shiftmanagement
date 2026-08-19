<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user()?->load('role', 'organisation');

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'role' => $user?->role?->slug,
            ],
            'licence' => $user?->organisation ? [
                'expired' => $user->organisation->isLicenseExpired(),
                'expiresAt' => $user->organisation->license_expires_at,
            ] : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'warning' => fn () => $request->session()->get('warning'),
                'credentials' => fn () => $request->session()->get('credentials'),
            ],
            'notifications' => $user ? fn () => [
                'non_lues' => $user->unreadNotifications()->count(),
                'recentes' => $user->unreadNotifications()->take(5)->get()->map(fn ($n) => [
                    'id' => $n->id,
                    'titre' => $n->data['titre'] ?? '',
                    'message' => $n->data['message'] ?? '',
                    'route' => $n->data['route'] ?? null,
                    'date' => $n->created_at->diffForHumans(),
                ]),
            ] : null,
        ];
    }
}
