<?php

namespace Tests\Unit;

use App\Domain\Patient\PatientSearchQuery;
use PHPUnit\Framework\TestCase;

class PatientSimilarityTest extends TestCase
{
    public function test_levenshtein_counts_characters_not_bytes(): void
    {
        $this->assertSame(0, PatientSearchQuery::levenshtein('রহিম', 'রহিম'));
        $this->assertSame(1, PatientSearchQuery::levenshtein('রহিম', 'রহিন'));
        $this->assertSame(1, PatientSearchQuery::levenshtein('rahim', 'rahiem'));
        $this->assertSame(5, PatientSearchQuery::levenshtein('', 'rahim'));
    }

    public function test_trigrams_of_words(): void
    {
        $this->assertSame(['rah', 'ahi', 'him'], PatientSearchQuery::trigrams('Rahim'));
        $this->assertSame(['ab', 'rah', 'ahi', 'him'], PatientSearchQuery::trigrams('Ab Rahim'));
        $this->assertSame([], PatientSearchQuery::trigrams('  '));
    }

    public function test_typos_and_prefixes_score_high_unrelated_names_low(): void
    {
        $this->assertSame(1.0, PatientSearchQuery::similarity('rahim', 'Rahim Uddin'));
        $this->assertGreaterThanOrEqual(0.7, PatientSearchQuery::similarity('Rahiem', 'Abdur Rahim'));
        $this->assertGreaterThanOrEqual(0.7, PatientSearchQuery::similarity('rah', 'Rahim'));
        $this->assertLessThan(0.7, PatientSearchQuery::similarity('Rahim', 'Rahman'));
        $this->assertLessThan(0.7, PatientSearchQuery::similarity('Karim', 'Rahim Uddin'));
        $this->assertSame(0.0, PatientSearchQuery::similarity('', 'Rahim'));
    }

    public function test_every_query_word_must_match(): void
    {
        $this->assertLessThan(0.7, PatientSearchQuery::similarity('Rahim Khan', 'Rahim Uddin'));
    }
}
