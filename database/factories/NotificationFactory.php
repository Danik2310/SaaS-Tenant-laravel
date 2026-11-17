<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use App\Shared\Constants\NotificationTypes;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Notification> */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => NotificationTypes::PASSWORD_NOT_SET,
            'level' => NotificationTypes::LEVEL_WARNING,
            'title' => 'Set a password for your account',
            'body' => null,
            'data' => [],
            'dedupe_key' => null,
            'read_at' => null,
        ];
    }

    public function read(): static
    {
        return $this->state(fn () => ['read_at' => now()]);
    }
}
