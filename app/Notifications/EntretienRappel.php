<?php

namespace App\Notifications;

use App\Models\Interview;
use Illuminate\Notifications\Notification;

class EntretienRappel extends Notification
{
    public function __construct(private readonly Interview $entretien) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'titre' => 'Entretien demain',
            'message' => "Entretien avec {$this->entretien->candidate->nomComplet()} demain"
                .($this->entretien->heure_entretien ? " à {$this->entretien->heure_entretien}" : '')
                .'.',
            'route' => 'interviews.index',
            'interview_id' => $this->entretien->id,
        ];
    }
}
