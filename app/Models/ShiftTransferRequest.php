<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ShiftTransferRequest extends Model
{
    use LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'organisation_id', 'type', 'shift_id', 'shift_destination_id', 'servant_id',
        'demandeur_id', 'motif', 'date_demande', 'discussion_servant', 'approuve_deux_shifts',
        'validation_chef_origine', 'validation_chef_origine_par_id', 'validation_chef_origine_le',
        'validation_chef_destination', 'validation_chef_destination_par_id', 'validation_chef_destination_le',
        'entretien_date', 'entretien_heure',
        'statut', 'resultat', 'resultat_date', 'favorable', 'shift_position_destination_id', 'notes', 'decideur_id',
        'reintegre_le', 'reintegre_par_id', 'reintegration_commentaire',
    ];

    protected function casts(): array
    {
        return [
            'date_demande' => 'date',
            'resultat_date' => 'date',
            'approuve_deux_shifts' => 'boolean',
            'validation_chef_origine' => 'boolean',
            'validation_chef_origine_le' => 'datetime',
            'validation_chef_destination' => 'boolean',
            'validation_chef_destination_le' => 'datetime',
            'entretien_date' => 'date',
            'favorable' => 'boolean',
            'reintegre_le' => 'datetime',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function shiftDestination(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_destination_id');
    }

    public function servant(): BelongsTo
    {
        return $this->belongsTo(Servant::class);
    }

    public function demandeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'demandeur_id');
    }

    public function decideur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decideur_id');
    }

    public function reintegrePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reintegre_par_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function validateurOrigine(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validation_chef_origine_par_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function validateurDestination(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validation_chef_destination_par_id');
    }

    public function shiftPositionDestination(): BelongsTo
    {
        return $this->belongsTo(ShiftPosition::class, 'shift_position_destination_id');
    }

    /**
     * Les deux chefs (origine + destination) ont validé la permutation : condition
     * requise avant que l'administrateur puisse planifier l'entretien puis trancher.
     */
    public function validationsChefsCompletes(): bool
    {
        return $this->validation_chef_origine === true && $this->validation_chef_destination === true;
    }

    /**
     * Suivi lisible d'une permutation : état global + étapes successives
     * (initiation, validation origine, validation destination, double
     * validation, décision du Conseil). Relations attendues (eager-load) :
     * demandeur.role, shift, shiftDestination, validateurOrigine,
     * validateurDestination, decideur.
     *
     * @return array{etat: array{libelle: string, ton: string}, etapes: list<array{cle: string, libelle: string, statut: string, detail: ?string}>}
     */
    public function suiviPermutation(): array
    {
        $format = fn ($date) => $date?->format('d/m/Y');
        $shiftOrigine = $this->shift?->nom;
        $shiftDestination = $this->shiftDestination?->nom;
        $role = $this->demandeur?->role?->nom;

        $etapeValidation = function (string $cle, string $shiftNom, ?bool $validation, ?User $validateur, $le) use ($format): array {
            return [
                'cle' => $cle,
                'libelle' => "Validation du coordonnateur du shift {$shiftNom}",
                'statut' => match ($validation) {
                    true => 'fait',
                    false => 'refuse',
                    default => 'en_attente',
                },
                'detail' => match ($validation) {
                    null => 'En attente',
                    default => ($validation ? 'Validée' : 'Refusée')
                        .($validateur ? " par {$validateur->name}" : '')
                        .($le ? ' le '.$format($le) : ''),
                },
            ];
        };

        $etapes = [
            [
                'cle' => 'initiation',
                'libelle' => 'Initiée par '.($this->demandeur?->name ?? '—').($role ? " ({$role})" : ''),
                'statut' => 'fait',
                'detail' => 'Le '.$format($this->date_demande),
            ],
            $etapeValidation('origine', (string) $shiftOrigine, $this->validation_chef_origine, $this->validateurOrigine, $this->validation_chef_origine_le),
            $etapeValidation('destination', (string) $shiftDestination, $this->validation_chef_destination, $this->validateurDestination, $this->validation_chef_destination_le),
        ];

        if ($this->validationsChefsCompletes()) {
            $derniere = collect([$this->validation_chef_origine_le, $this->validation_chef_destination_le])->filter()->max();
            $etapes[] = [
                'cle' => 'double_validation',
                'libelle' => 'Validée par les deux coordonnateurs',
                'statut' => 'fait',
                'detail' => $derniere ? 'Le '.$format($derniere) : null,
            ];
        }

        $refusCoordonnateur = $this->validation_chef_origine === false || $this->validation_chef_destination === false;
        $tranchee = $this->statut === 'traitee';

        $etapes[] = [
            'cle' => 'decision',
            'libelle' => 'Décision du Conseil',
            'statut' => match (true) {
                $refusCoordonnateur => 'refuse',
                $tranchee => $this->favorable === false ? 'refuse' : 'fait',
                default => 'en_attente',
            },
            'detail' => match (true) {
                $refusCoordonnateur => 'Sans objet : demande clôturée suite au refus d\'un coordonnateur',
                $tranchee => trim(($this->favorable === null ? '' : ($this->favorable ? 'Favorable' : 'Défavorable').' — ').($this->resultat ?? ''))
                    .($this->decideur ? " par {$this->decideur->name}" : '')
                    .($this->resultat_date ? ' le '.$format($this->resultat_date) : ''),
                default => 'En attente',
            },
        ];

        $etat = match (true) {
            $this->validation_chef_origine === false => ['libelle' => "Refusée par le coordonnateur de {$shiftOrigine}", 'ton' => 'danger'],
            $this->validation_chef_destination === false => ['libelle' => "Refusée par le coordonnateur de {$shiftDestination}", 'ton' => 'danger'],
            $tranchee => ['libelle' => 'Tranchée'.($this->favorable === null ? '' : ($this->favorable ? ' (favorable)' : ' (défavorable)')), 'ton' => $this->favorable === false ? 'danger' : 'success'],
            $this->validationsChefsCompletes() => ['libelle' => 'Prête pour décision du Conseil', 'ton' => 'info'],
            $this->validation_chef_origine === null && $this->validation_chef_destination === null => ['libelle' => "En attente des coordonnateurs de {$shiftOrigine} et de {$shiftDestination}", 'ton' => 'warning'],
            $this->validation_chef_origine === null => ['libelle' => "En attente du coordonnateur de {$shiftOrigine}", 'ton' => 'warning'],
            default => ['libelle' => "En attente du coordonnateur de {$shiftDestination}", 'ton' => 'warning'],
        };

        return ['etat' => $etat, 'etapes' => $etapes];
    }

    /**
     * Relèves traitées dont le servant n'a pas encore été réintégré : tant
     * qu'il en existe une, le servant est dans l'état « relevé ».
     */
    public function scopeReleveeNonReintegree($query)
    {
        return $query->where('type', 'releve')
            ->where('statut', 'traitee')
            ->whereNull('reintegre_le');
    }

    public function scopeEnAttente($query)
    {
        return $query->where('statut', 'en_attente');
    }
}
