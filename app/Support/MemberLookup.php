<?php

namespace App\Support;

use App\Models\User;

/**
 * Shared member lookup for the reserve and transfer flows: an exact email
 * wins, then an exact contact, then a digit-normalised contact match so
 * "0300 1234567" still finds "03001234567".
 */
class MemberLookup
{
    public static function find(?string $term): ?User
    {
        $term = trim((string) $term);
        if ($term === '') {
            return null;
        }

        if (filter_var($term, FILTER_VALIDATE_EMAIL)) {
            return User::where('email', $term)->first();
        }

        $user = User::where('contact', $term)->first();
        if ($user) {
            return $user;
        }

        $digits = preg_replace('/\D+/', '', $term);
        if ($digits !== '') {
            return User::whereRaw('REGEXP_REPLACE(contact, "[^0-9]", "") = ?', [$digits])->first();
        }

        return null;
    }
}
