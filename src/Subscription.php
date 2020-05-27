<?php

namespace Laravel\Cashier;

use Exception;
use Carbon\Carbon;
use LogicException;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Model;
use Braintree\Subscription as BraintreeSubscription;

class Subscription extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'trial_ends_at', 'ends_at',
        'created_at', 'updated_at',
    ];

    /**
     * Indicates plan changes should be prorated.
     *
     * @var bool
     */
    protected $prorate = true;

    /**
     * Get the user that owns the subscription.
     */
    public function user()
    {
        return $this->owner();
    }

    /**
     * Get the model related to the subscription.
     */
    public function owner()
    {
        $model = getenv('BRAINTREE_MODEL') ?: config('services.braintree.model', 'App\\User');

        $model = new $model;

        return $this->belongsTo(get_class($model), $model->getForeignKey());
    }

    /**
     * Determine if the subscription is active, on trial, or within its grace period.
     *
     * @return bool
     */
    public function valid()
    {
        return $this->active() || $this->onTrial() || $this->onGracePeriod();
    }

    /**
     * Determine if the subscription is active.
     *
     * @return bool
     */
    public function active()
    {
        return is_null($this->ends_at) || $this->onGracePeriod();
    }

    /**
     * Determine if the subscription is no longer active.
     *
     * @return bool
     */
    public function cancelled()
    {
        return ! is_null($this->ends_at);
    }

    /**
     * Determine if the subscription is within its trial period.
     *
     * @return bool
     */
    public function onTrial()
    {
        if (! is_null($this->trial_ends_at)) {
            return Carbon::today()->lt($this->trial_ends_at);
        }

        return false;
    }

    /**
     * Determine if the subscription is within its grace period after cancellation.
     *
     * @return bool
     */
    public function onGracePeriod()
    {
        if (! is_null($endsAt = $this->ends_at)) {
            return Carbon::now()->lt(Carbon::instance($endsAt));
        }

        return false;
    }

    /**
     * Swap the subscription to a new Braintree plan.
     *
     * @param  string  $plan
     * @return $this|\Laravel\Cashier\Subscription
     * @throws \Exception
     */
    public function swap($plan)
    {
        if ($this->onGracePeriod() && $this->braintree_plan === $plan) {
            return $this->resume();
        }

        if (! $this->active()) {
            return $this->owner->newSubscription($this->name, $plan)
                                ->skipTrial()->create();
        }

        $plan = BraintreeService::findPlan($plan);

        if ($this->wouldChangeBillingFrequency($plan) && $this->prorate) {
            return $this->swapAcrossFrequencies($plan);
        }

        $subscription = $this->asBraintreeSubscription();

        $response = BraintreeSubscription::update($subscription->id, [
            'planId' => $plan->id,
            'price' => (string) round($plan->price * (1 + ($this->owner->taxPercentage() / 100)), 2),
            'neverExpires' => true,
            'numberOfBillingCycles' => null,
            'options' => [
                'prorateCharges' => $this->prorate,
            ],
        ]);

        if ($response->success) {
            $this->fill([
                'braintree_plan' => $plan->id,
                'ends_at' => null,
            ])->save();
        } else {
            throw new Exception('Braintree failed to swap plans: '.$response->message);
        }

        return $this;
    }

    /**
     * Determine if the given plan would alter the billing frequency.
     *
     * @param  string  $plan
     * @return bool
     */
    protected function wouldChangeBillingFrequency($plan)
    {
        return $plan->billingFrequency !==
           BraintreeService::findPlan($this->braintree_plan)->billingFrequency;
    }

    /**
     * Swap the subscription to a new Braintree plan with a different frequency.
     *
     * @param  string  $plan
     * @return $this
     */
    protected function swapAcrossFrequencies($plan)
    {
        $currentPlan = BraintreeService::findPlan($this->braintree_plan);

        $discount = $this->switchingToMonthlyPlan($currentPlan, $plan)
                                ? $this->getDiscountForSwitchToMonthly($currentPlan, $plan)
                                : $this->getDiscountForSwitchToYearly();

        $options = [];

        if ($discount->amount > 0 && $discount->numberOfBillingCycles > 0) {
            $options = ['discounts' => ['add' => [
                [
                    'inheritedFromId' => 'plan-credit',
                    'amount' => (float) $discount->amount,
                    'numberOfBillingCycles' => $discount->numberOfBillingCycles,
                ],
            ]]];
        }

        $this->cancelNow();

        return $this->owner->newSubscription($this->name, $plan->id)
                            ->skipTrial()->create(null, [], $options);
    }

    /**
     * Determine if the user is switching form yearly to monthly billing.
     *
     * @param  \Braintree\Plan  $currentPlan
     * @param  \Braintree\Plan  $plan
     * @return bool
     */
    protected function switchingToMonthlyPlan($currentPlan, $plan)
    {
        return $currentPlan->billingFrequency == 12 && $plan->billingFrequency == 1;
    }

    /**
     * Get the discount to apply when switching to a monthly plan.
     *
     * @param  \Braintree\Plan  $currentPlan
     * @param  \Braintree\Plan  $plan
     * @return object
     */
    protected function getDiscountForSwitchToMonthly($currentPlan, $plan)
    {
        return (object) [
            'amount' => $plan->price,
            'numberOfBillingCycles' => floor(
                $this->moneyRemainingOnYearlyPlan($currentPlan) / $plan->price
            ),
        ];
    }

    /**
     * Calculate the amount of discount to apply to a swap to monthly billing.
     *
     * @param  \Braintree\Plan  $plan
     * @return float
     */
    protected function moneyRemainingOnYearlyPlan($plan)
    {
        return ($plan->price / 365) * Carbon::today()->diffInDays(Carbon::instance(
            $this->asBraintreeSubscription()->billingPeriodEndDate
        ), false);
    }

    /**
     * Get the discount to apply when switching to a yearly plan.
     *
     * @return object
     */
    protected function getDiscountForSwitchToYearly()
    {
        $amount = 0;

        foreach ($this->asBraintreeSubscription()->discounts as $discount) {
            if ($discount->id == 'plan-credit') {
                $amount += (float) $discount->amount * $discount->numberOfBillingCycles;
            }
        }

        return (object) [
            'amount' => $amount,
            'numberOfBillingCycles' => 1,
        ];
    }

    /*
     * Apply a coupon to the subscription.
     *
     * @param  string  $coupon
     * @param  bool  $removeOthers
     * @return void
     */
    public function applyCoupon($coupon, $removeOthers = false)
    {
        if (! $this->active()) {
            throw new InvalidArgumentException("Unable to apply coupon. Subscription not active.");
        }

        BraintreeSubscription::update($this->braintree_id, [
            'discounts' => [
                'add' => [[
                        'inheritedFromId' => $coupon,
                ]],
                'remove' => $removeOthers ? $this->currentDiscounts() : [],
            ],
        ]);
    }
    
    public function addAddOn($id, $quantity = 1){
        $result = BraintreeSubscription::update($this->braintree_id, [
                'addOns' => [
                    'add' => [[
                        'inheritedFromId' => $id,
                        'quantity' => $quantity
                    ]]
                ]
            ]);
        
        if (!$result->success){
            throw new \Exception($result->message);
        }
        
        return true;
    }
    
    public function updateAddOnQuantity($id, $qty){
        $result = BraintreeSubscription::update($this->braintree_id, [
            'addOns' => [
                'update' => [[
                    'existingId' => $id,
                    'quantity' => $qty
                ]]
            ]
        ]);
        
        if (!$result->success){
            throw new \Exception($result->message);
        }
        
        return true;
    }
    
    public function removeAddOn($id){
        $result = BraintreeSubscription::update($this->braintree_id, [
            'addOns' => [
                'remove' => [$id]
            ]
        ]);
        
        if (!$result->success){
            throw new \Exception($result->message);
        }
        
        return true;
    }

    /**
     * Get the current discounts for the subscription.
     *
     * @return array
     */
    protected function currentDiscounts()
    {
        return collect($this->asBraintreeSubscription()->discounts)->map(function ($discount) {
            return $discount->id;
        })->all();
    }

    /**
     * Cancel the subscription.
     *
     * @return $this
     */
    public function cancel()
    {
        $subscription = $this->asBraintreeSubscription();

        if ($this->onTrial()) {
            BraintreeSubscription::cancel($subscription->id);

            $this->markAsCancelled();
        } else {
            BraintreeSubscription::update($subscription->id, [
                'numberOfBillingCycles' => $subscription->currentBillingCycle,
            ]);

            $this->ends_at = $subscription->billingPeriodEndDate;

            $this->save();
        }


        return $this;
    }

    /**
     * Cancel the subscription immediately.
     *
     * @return $this
     */
    public function cancelNow()
    {
        $subscription = $this->asBraintreeSubscription();

        BraintreeSubscription::cancel($subscription->id);

        $this->markAsCancelled();

        return $this;
    }

    /**
     * Mark the subscription as cancelled.
     *
     * @return void
     */
    public function markAsCancelled()
    {
        $this->fill(['ends_at' => Carbon::now()])->save();
    }

    /**
     * Resume the cancelled subscription.
     *
     * @return $this
     *
     * @throws \LogicException
     */
    public function resume()
    {
        if (! $this->onGracePeriod()) {
            throw new LogicException('Unable to resume subscription that is not within grace period.');
        }

        $subscription = $this->asBraintreeSubscription();

        BraintreeSubscription::update($subscription->id, [
            'neverExpires' => true,
            'numberOfBillingCycles' => null,
        ]);

        $this->fill(['ends_at' => null])->save();

        return $this;
    }

    /**
     * Indicate that plan changes should not be prorated.
     *
     * @return $this
     */
    public function noProrate()
    {
        $this->prorate = false;

        return $this;
    }

    /**
     * Get the subscription as a Braintree subscription object.
     *
     * @return \Braintree\Subscription
     */
    public function asBraintreeSubscription()
    {
        return BraintreeSubscription::find($this->braintree_id);
    }
}
