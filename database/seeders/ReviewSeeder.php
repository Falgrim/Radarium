<?php

namespace Database\Seeders;

use App\Models\Configuration;
use App\Models\Review;
use App\Models\Specialist;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        dump('Review seeder is running...');
        $time_start = microtime(true);

        $this->createReviews();

        $time_end = microtime(true);
        dump('Review created in ' . round($time_end - $time_start, 2) . ' seconds');
    }

    private function createReviews(): void
    {
        $specialists = Specialist::take(20)->get();

        if (!$specialists) {
            dump('Specialist is EMPTY');
            return;
        }

        $data = [];
        foreach ($specialists as $specialist) {
            $data[] = [
                'specialist_id' => $specialist->id,
                'user_id' => 1,
                'text' => str()->random(),
                'rating' => rand(1, 5),
                'can_edit' => rand(0, 1),
                'status' => rand(0, 2),
            ];
        }

        foreach ($data as $row) {
            Review::create($row);
        }
    }
}
