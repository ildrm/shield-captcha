<?php

namespace App\Tenancy;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\RateLimiter;
use SensitiveParameter;

class ConfirmPassword implements ValidationRule
{
    public function validate(string $attribute, #[SensitiveParameter] mixed $value, Closure $fail): void
    {
        $user = Filament::auth()->user();
        if ($user === null || RateLimiter::increment('shield-confirm-password:'.$user->id, 60) > 12) {
            $fail('Wait one minute before trying again.');

            return;
        }

        if (! is_string($value) || strlen($value) > 512 || ! password_verify($value, $user->getAuthPassword())) {
            $fail('Enter your current password.');
        }
    }
}
