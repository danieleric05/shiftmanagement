<?php

namespace App\Http\Middleware;

use App\Models\User;
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
                'lectureSeule' => (bool) $user?->estEnLectureSeule(),
            ],
            'licence' => $user?->organisation ? $this->licence($user) : null,
            // Ordre des colonnes de la liste des servants : seul le Conseil du
            // Temple (administrateur/super_admin) peut le personnaliser.
            'preferences' => [
                'colonnesServants' => $user?->estAdministrateur() ? $user->ordreColonnesServants() : null,
            ],
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

    /**
     * Licence de l'organisation de l'utilisateur. Le compte à rebours
     * (`compteARebours`) n'est fourni qu'au Conseil du Temple
     * (administrateur/super_admin), pour une licence datée et non expirée :
     * une licence expirée relève du bandeau d'expiration existant.
     *
     * @return array<string, mixed>
     */
    private function licence(User $user): array
    {
        $organisation = $user->organisation;
        $expiresAt = $organisation->license_expires_at;
        $expired = $organisation->isLicenseExpired();

        $compteARebours = null;
        if ($expiresAt !== null && ! $expired && $user->estAdministrateur()) {
            $secondesRestantes = now()->diffInSeconds($expiresAt, false);
            $joursRestants = (int) floor($secondesRestantes / 86400);
            $compteARebours = [
                'expiresAtIso' => $expiresAt->toIso8601String(),
                'joursRestants' => $joursRestants,
                'niveau' => match (true) {
                    $secondesRestantes <= 7 * 86400 => 'urgent',
                    $secondesRestantes <= 60 * 86400 => 'attention',
                    default => 'info',
                },
            ];
        }

        return [
            'expired' => $expired,
            'expiresAt' => $expiresAt,
            'compteARebours' => $compteARebours,
        ];
    }
}
