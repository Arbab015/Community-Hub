<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class PropertyPaymentPlanInstallmentTemplate extends Pivot
{
    use HasUuids;

    protected $table = 'property_installment_plans';

    protected $guarded = [];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public function paymentPlan()
    {
        return $this->belongsTo(PropertyPaymentPlan::class, 'property_payment_plan_id');
    }

    public function installmentTemplate()
    {
        return $this->belongsTo(InstallmentTemplate::class, 'installment_template_id');
    }
}
