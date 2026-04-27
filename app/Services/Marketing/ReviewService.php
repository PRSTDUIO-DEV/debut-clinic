<?php

namespace App\Services\Marketing;

use App\Models\Review;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReviewService
{
    /**
     * Generate a review request (pending row + signed-ish public token).
     */
    public function requestReview(Visit $visit, string $source = 'line'): Review
    {
        $patient = $visit->patient;
        if (! $patient) {
            throw ValidationException::withMessages(['visit' => 'Visit has no patient']);
        }

        return Review::create([
            'branch_id' => $visit->branch_id,
            'patient_id' => $patient->id,
            'visit_id' => $visit->id,
            'doctor_id' => $visit->doctor_id,
            'rating' => 0,
            'source' => $source,
            'status' => 'pending',
            'public_token' => $this->makeToken(),
            'requested_at' => now(),
        ]);
    }

    /**
     * Public submit a rating using a token. Token expires after 7 days.
     */
    public function submit(string $token, int $rating, ?string $title, ?string $body): Review
    {
        return DB::transaction(function () use ($token, $rating, $title, $body) {
            $review = Review::where('public_token', $token)->lockForUpdate()->first();
            if (! $review) {
                throw ValidationException::withMessages(['token' => 'Invalid or expired token']);
            }
            if ($review->submitted_at) {
                throw ValidationException::withMessages(['token' => 'Review already submitted']);
            }
            if ($review->requested_at && Carbon::parse($review->requested_at)->lt(now()->subDays(7))) {
                throw ValidationException::withMessages(['token' => 'Token expired']);
            }
            if ($rating < 1 || $rating > 5) {
                throw ValidationException::withMessages(['rating' => 'Rating must be 1-5']);
            }

            $review->rating = $rating;
            $review->title = $title;
            $review->body = $body;
            $review->submitted_at = now();
            // Auto-publish high ratings (4-5), keep low ratings pending for moderation
            $review->status = $rating >= 4 ? 'published' : 'pending';
            $review->save();

            return $review;
        });
    }

    public function moderate(Review $review, string $status, User $moderator): Review
    {
        if (! in_array($status, ['published', 'hidden'])) {
            throw ValidationException::withMessages(['status' => 'Invalid status']);
        }
        $review->status = $status;
        $review->moderated_by = $moderator->id;
        $review->moderated_at = now();
        $review->save();

        return $review;
    }

    /**
     * Aggregate ratings (avg + count) for a branch, optionally per doctor.
     *
     * @return array{branch:array{avg:float,count:int,distribution:array<int,int>}, doctors:array<int,array>}
     */
    public function aggregate(int $branchId): array
    {
        $base = Review::where('branch_id', $branchId)
            ->where('status', 'published')
            ->whereNotNull('submitted_at');

        $countTotal = (clone $base)->count();
        $avg = $countTotal > 0 ? round((float) (clone $base)->avg('rating'), 2) : 0;

        $dist = (clone $base)
            ->selectRaw('rating, COUNT(*) as c')
            ->groupBy('rating')
            ->pluck('c', 'rating')
            ->all();
        $distribution = [];
        for ($i = 1; $i <= 5; $i++) {
            $distribution[$i] = (int) ($dist[$i] ?? 0);
        }

        $doctors = (clone $base)
            ->whereNotNull('doctor_id')
            ->selectRaw('doctor_id, AVG(rating) as avg, COUNT(*) as c')
            ->groupBy('doctor_id')
            ->orderByDesc('avg')
            ->get()
            ->map(fn ($r) => [
                'doctor_id' => (int) $r->doctor_id,
                'avg' => round((float) $r->avg, 2),
                'count' => (int) $r->c,
            ])
            ->all();

        return [
            'branch' => ['avg' => $avg, 'count' => $countTotal, 'distribution' => $distribution],
            'doctors' => $doctors,
        ];
    }

    private function makeToken(): string
    {
        do {
            $token = Str::random(48);
        } while (Review::where('public_token', $token)->exists());

        return $token;
    }
}
