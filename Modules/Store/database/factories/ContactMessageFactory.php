<?php

namespace Modules\Store\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Store\Entities\ContactMessage;

/**
 * @extends Factory<ContactMessage>
 */
class ContactMessageFactory extends Factory
{
    protected $model = ContactMessage::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'phone' => '0599'.$this->faker->numerify('######'),
            'email' => $this->faker->safeEmail(),
            'subject' => null,
            'message' => $this->faker->sentence(12),
            'locale' => 'ar',
        ];
    }

    public function read(): static
    {
        return $this->state(['read_at' => now()]);
    }
}
