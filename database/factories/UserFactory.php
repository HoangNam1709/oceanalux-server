<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            // Mật khẩu mặc định cho tất cả user ngẫu nhiên sẽ là 'password'
            'password' => static::$password ??= Hash::make('password'),
            // Tạo số điện thoại giả định
            'phone' => fake()->phoneNumber(),
            // Mặc định tạo ra sẽ là khách hàng (viết thường theo chuẩn DB của bạn)
            'role' => 'customer',
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
