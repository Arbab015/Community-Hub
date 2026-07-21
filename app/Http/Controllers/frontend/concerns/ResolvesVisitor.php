<?php

namespace App\Http\Controllers\frontend\concerns;

use App\Models\Visitor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

trait ResolvesVisitor
{
    /**
     * Get the visitor for the current request, creating one (and queuing
     * the cookie) if none exists yet. Returns the model directly so it can
     * be reused within the same request — Cookie::queue() only affects the
     * outgoing response, not the current $request's cookie bag.
     */
    protected function getOrCreateVisitor()
    {
        $uuid = request()->cookie('visitor_id');

        $visitor = $uuid ? Visitor::where('visitor_token', $uuid)->first() : null;

        if (! $visitor) {
            $uuid = (string) Str::uuid();
            $visitor = Visitor::create([
                'visitor_token' => $uuid,
                'last_active_at' => now(),
            ]);

            Cookie::queue('visitor_id', $uuid, 60 * 24 * 365 * 2); // 2 years
        } else {

            if (! $visitor->last_active_at || Carbon::parse($visitor->last_active_at)->lt(now()->subDay())) {
                $visitor->update(['last_active_at' => now()]);
            }

            // refresh cookie expiry too
            Cookie::queue('visitor_id', $uuid, 60 * 24 * 365 * 2);
        }

        return $visitor;
    }
}
