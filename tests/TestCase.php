<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Login sebagai admin (sesi + cookie token seperti middleware admin.token). */
    protected function asAdmin(): static
    {
        $user = User::query()->first() ?? User::forceCreate([
            'name' => 'Naro Capture Admin',
            'email' => 'admin@narocapture.com',
            'password' => bcrypt('rahasia123'),
        ]);
        $user->forceFill(['remember_token' => 'token-uji'])->save();

        return $this->actingAs($user)->withCookie('admin_auth_token', 'token-uji');
    }
}
