<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class PropertyPaymentPlanInstallmentTemplate extends Pivot
{
    protected $table = 'property_payment_plan_installment_templates';

    protected $guarded = [];

    public function paymentPlan()
    {
        return $this->belongsTo(PropertyPaymentPlan::class, 'property_payment_plan_id');
    }

    public function installmentTemplate()
    {
        return $this->belongsTo(InstallmentTemplate::class, 'installment_template_id');
    }
}
