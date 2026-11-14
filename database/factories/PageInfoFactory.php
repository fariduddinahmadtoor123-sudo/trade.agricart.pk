<?php

namespace Database\Factories;

use App\Models\PageInfo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageInfo>
 */
class PageInfoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'page_slug' => fake()->unique()->slug(2),
            'title' => fake()->sentence(3),
            'user_guide_content' => fake()->paragraph(),
            'ai_prompt_content' => fake()->paragraph(),
        ];
    }
}
