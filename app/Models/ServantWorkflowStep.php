<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServantWorkflowStep extends Model
{
    protected $fillable = ['servant_id', 'workflow_step_id', 'responsable_id', 'statut', 'date', 'commentaire'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function servant(): BelongsTo
    {
        return $this->belongsTo(Servant::class);
    }

    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }
}
