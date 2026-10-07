<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Servant extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * Libellés affichés des statuts (les valeurs techniques en base ne
     * changent pas) : « en_formation » s'affiche « Nouveau », « actif »
     * s'affiche « Ancien ».
     */
    public const LIBELLES_STATUT = [
        'recommande' => 'Recommandé',
        'en_formation' => 'Nouveau',
        'actif' => 'Ancien',
        'suspendu' => 'Relevé',
        'retire' => 'Permutant',
    ];

    /**
     * Statuts que le Conseil du Temple peut choisir librement, dans les deux
     * sens (ServantPolicy::changeStatut()). « Relevé » et « Permutant » restent
     * gérés par les relèves/permutations et la réintégration.
     */
    public const STATUTS_MODIFIABLES = ['recommande', 'en_formation', 'actif'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'organisation_id', 'user_id', 'nom', 'prenom', 'genre', 'photo',
        'telephone', 'telephone_appel', 'pieu_id', 'date_appel', 'date_debut', 'adresse', 'statut', 'titre_leadership',
        'appele', 'whatsapp_1', 'whatsapp_2', 'formation_1', 'formation_2', 'formation_3',
        'niveau_technique', 'niveau_anglais', 'jour_alternatif', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_appel' => 'date',
            'date_debut' => 'date',
            'appele' => 'boolean',
            'whatsapp_1' => 'boolean',
            'whatsapp_2' => 'boolean',
            'formation_1' => 'boolean',
            'formation_2' => 'boolean',
            'formation_3' => 'boolean',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pieu(): BelongsTo
    {
        return $this->belongsTo(Pieu::class);
    }

    public function workflowSteps(): HasMany
    {
        return $this->hasMany(ServantWorkflowStep::class);
    }

    /**
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * @return HasMany<Assignment, $this>
     */
    public function assignationsActives(): HasMany
    {
        return $this->assignments()->where('statut', 'actif');
    }

    /**
     * @return HasMany<ShiftTransferRequest, $this>
     */
    public function demandesChangement(): HasMany
    {
        return $this->hasMany(ShiftTransferRequest::class);
    }

    /**
     * État « relevé » : au moins une relève traitée non suivie d'une
     * réintégration, ou statut « Relevé » (valeur technique `suspendu`)
     * saisi sur la fiche (cf. ServantReintegrationController).
     */
    public function estReleve(): bool
    {
        return $this->statut === 'suspendu'
            || $this->demandesChangement()->releveeNonReintegree()->exists();
    }

    /**
     * Servant mis à l'écart (relevé ou « Permutant ») : il revient par la
     * réintégration, jamais par le changement manuel de statut.
     */
    public function estMisALEcart(): bool
    {
        return $this->statut === 'retire' || $this->estReleve();
    }

    public function nomComplet(): string
    {
        return "{$this->prenom} {$this->nom}";
    }

    /**
     * Démarre le parcours d'intégration standard (une entrée par étape du
     * catalogue WorkflowStep) — appelé de façon identique partout où un
     * Servant est créé (formulaire Servants, affectation rapide depuis un
     * Shift), pour que le parcours ne dépende jamais du chemin de création
     * emprunté. Idempotent : ne fait rien si le servant a déjà un parcours
     * (jamais de doublons).
     */
    public function demarrerParcours(): void
    {
        if ($this->workflowSteps()->exists()) {
            return;
        }

        foreach (WorkflowStep::orderBy('ordre')->get() as $index => $step) {
            $this->workflowSteps()->create([
                'workflow_step_id' => $step->id,
                'statut' => $index === 0 ? 'en_cours' : 'en_attente',
            ]);
        }
    }
}
