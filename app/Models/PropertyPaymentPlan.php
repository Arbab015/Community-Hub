<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PropertyPaymentPlan extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::deleting(function (PropertyPaymentPlan $plan) {
            $plan->installmentAttachments()->delete();
        });
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    // Polymorphic relationship
    public function planable()
    {
        return $this->morphTo();
    }

    public function installmentTemplates()
    {
        return $this->belongsToMany(
            InstallmentTemplate::class,
            'property_payment_plan_installment_templates'
        )
            ->withPivot([
                'name',
                'number_of_installments',
                'installment_frequency',
                'additional_installment_amount',
                'additional_installment_amount_in_per',
                'balloon_payment',
                'balloon_payment_in_per',
                'balloon_payment_frequency',
            ])
            ->withTimestamps();
    }

    // Every attached installment row, including customized ones whose
    // installment_template_id has been nulled after editing.
    public function installmentAttachments()
    {
        return $this->hasMany(PropertyPaymentPlanInstallmentTemplate::class, 'property_payment_plan_id');
    }

    // Payment template relationship
    public function paymentTemplate()
    {
        return $this->belongsTo(PaymentTemplate::class, 'payment_template_id');
    }
}
