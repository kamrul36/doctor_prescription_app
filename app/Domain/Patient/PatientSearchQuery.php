<?php

namespace App\Domain\Patient;

use App\Domain\Patient\Models\Patient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Finds patients by code, phone fragment or (typo-tolerant) name.
 *
 * Portable on purpose (MySQL now, SQLite in tests, maybe PostgreSQL later):
 * SQL only narrows candidates with LIKE on the query's trigrams, then a
 * trigram + edit-distance score ranks them in PHP. Only the candidate step
 * would change if a database-native engine replaces this class.
 */
class PatientSearchQuery
{
    /** Candidates fetched from the database before ranking. */
    private const CANDIDATE_LIMIT = 400;

    /** Minimum name similarity (0..1) for a fuzzy hit. */
    private const MIN_SCORE = 0.7;

    /** Most results a search can return. */
    private const MAX_RESULTS = 100;

    public function paginate(string $term, int $page = 1, int $perPage = 25): Paginator
    {
        $term = trim($term);

        if ($term === '') {
            return Patient::query()->latest('id')->paginate($perPage, page: $page);
        }

        $hits = $this->search($term);

        return new LengthAwarePaginator(
            $hits->forPage($page, $perPage)->values(),
            $hits->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
    }

    /** @return Collection<int, Patient> best match first */
    public function search(string $term): Collection
    {
        $term = trim($term);
        $digits = preg_replace('/\D+/', '', $term) ?? '';
        $phoneLike = $digits !== '' && preg_match('/^[\d\s+\-()]+$/', $term) === 1;

        $scored = [];

        // Code and phone: exact and fragment matches are certain hits.
        if ($phoneLike) {
            $needle = $this->like($digits);
            Patient::query()
                ->where(fn (Builder $q) => $q
                    ->where('code', $digits)
                    ->orWhere('code', 'like', $needle.'%')
                    ->orWhere('phone', 'like', '%'.$needle.'%')
                    ->orWhere('alt_phone', 'like', '%'.$needle.'%'))
                ->limit(self::CANDIDATE_LIMIT)
                ->get()
                ->each(function (Patient $p) use (&$scored, $digits) {
                    $scored[$p->id] = [$p, $p->code === $digits ? 2.0 : 1.5];
                });

            if ($scored !== []) {
                return $this->ordered($scored);
            }
        }

        $grams = self::trigrams($term);

        if ($grams !== []) {
            Patient::query()
                ->where(function (Builder $q) use ($grams) {
                    foreach ($grams as $gram) {
                        $like = '%'.$this->like($gram).'%';
                        $q->orWhere('name', 'like', $like)->orWhere('name_bn', 'like', $like);
                    }
                })
                ->limit(self::CANDIDATE_LIMIT)
                ->get()
                ->each(function (Patient $p) use (&$scored, $term) {
                    $score = max(self::similarity($term, $p->name), self::similarity($term, (string) $p->name_bn));

                    if ($score >= self::MIN_SCORE) {
                        $scored[$p->id] = [$p, $score];
                    }
                });
        }

        return $this->ordered($scored);
    }

    /**
     * How alike a query and a name are, 0..1. Every query word must find a
     * similar word in the name (prefix, or small edit distance); the result is
     * the mean of the best per-word similarities.
     */
    public static function similarity(string $query, string $name): float
    {
        $queryWords = self::words($query);
        $nameWords = self::words($name);

        if ($queryWords === [] || $nameWords === []) {
            return 0.0;
        }

        $total = 0.0;

        foreach ($queryWords as $q) {
            $best = 0.0;

            foreach ($nameWords as $n) {
                $best = max($best, self::wordSimilarity($q, $n));
            }

            $total += $best;
        }

        return $total / count($queryWords);
    }

    private static function wordSimilarity(string $q, string $n): float
    {
        if ($q === $n) {
            return 1.0;
        }

        // Typing the start of a name is a good match: "rahi" finds "rahim".
        if (mb_strlen($q) >= 2 && str_starts_with($n, $q)) {
            return 0.9;
        }

        $longest = max(mb_strlen($q), mb_strlen($n));

        return max(0.0, 1 - self::levenshtein($q, $n) / $longest);
    }

    /** Multibyte-safe edit distance (PHP's levenshtein() counts bytes, which breaks Bangla). */
    public static function levenshtein(string $a, string $b): int
    {
        $a = mb_str_split($a);
        $b = mb_str_split($b);

        if ($a === []) {
            return count($b);
        }

        $previous = range(0, count($b));

        foreach ($a as $i => $charA) {
            $current = [$i + 1];

            foreach ($b as $j => $charB) {
                $current[] = min(
                    $previous[$j + 1] + 1,
                    $current[$j] + 1,
                    $previous[$j] + ($charA === $charB ? 0 : 1),
                );
            }

            $previous = $current;
        }

        return $previous[count($b)];
    }

    /**
     * Distinct 3-character slices of each word (a short word is its own gram).
     *
     * @return list<string>
     */
    public static function trigrams(string $text): array
    {
        $grams = [];

        foreach (self::words($text) as $word) {
            $length = mb_strlen($word);

            if ($length <= 3) {
                $grams[] = $word;

                continue;
            }

            for ($i = 0; $i <= $length - 3; $i++) {
                $grams[] = mb_substr($word, $i, 3);
            }
        }

        return array_values(array_unique($grams));
    }

    /** @return list<string> */
    private static function words(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{M}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);

        return $words === false ? [] : $words;
    }

    private function like(string $value): string
    {
        return addcslashes($value, '\\%_');
    }

    /**
     * @param  array<int, array{0: Patient, 1: float}>  $scored
     * @return Collection<int, Patient>
     */
    private function ordered(array $scored): Collection
    {
        return collect($scored)
            ->sortBy([fn ($a, $b) => $b[1] <=> $a[1], fn ($a, $b) => strcmp($a[0]->name, $b[0]->name)])
            ->take(self::MAX_RESULTS)
            ->map(fn ($row) => $row[0])
            ->values();
    }
}
