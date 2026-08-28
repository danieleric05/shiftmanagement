<?php

namespace App\Notifications;

use App\Models\ShiftTransferRequest;
use Illuminate\Notifications\Notification;

class NouvelleDemandeTransfert extends Notification
{
    public function __construct(private readonly ShiftTransferRequest $demande) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $typeLabel = match ($this->demande->type) {
            'releve' => 'relève',
            'appel' => 'appel',
            default => 'permutation',
        };

        return [
            'titre' => "Nouvelle demande de {$typeLabel}",
            'message' => "{$this->demande->demandeur->name} a soumis une demande de {$typeLabel} pour {$this->demande->servant->nomComplet()} sur le shift {$this->demande->shift->nom}.",
            'route' => 'shift-transfers.index',
            'shift_transfer_request_id' => $this->demande->id,
        ];
    }
}
