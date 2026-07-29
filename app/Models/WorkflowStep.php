<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowStep extends Model
{
    protected $fillable = ['cle', 'nom', 'ordre'];

    public function servantWorkflowSteps(): HasMany
    {
        return $this->hasMany(ServantWorkflowStep::class);
    }
}
