<?php

namespace App\Tests\Unit\Service;

use App\Service\FraudChecker;
use PHPUnit\Framework\TestCase;

class FraudCheckerTest extends TestCase
{
    private FraudChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new FraudChecker();
    }

    public function test_amount_below_threshold_is_not_high_risk(): void
    {
        $assessment = $this->checker->check(500.0, 'credit_card');

        $this->assertFalse($assessment->isHighRisk);
        $this->assertSame(0.0, $assessment->riskScore);
        $this->assertLessThan(0.6, $assessment->riskScore);
        $this->assertSame('Amount within normal range', $assessment->reason);
    }

    public function test_amount_above_threshold_is_high_risk(): void
    {
        $assessment = $this->checker->check(1500.0, 'credit_card');

        $this->assertTrue($assessment->isHighRisk);
        $this->assertGreaterThan(0.6, $assessment->riskScore);
    }

    public function test_amount_at_threshold_boundary(): void
    {
        $assessment = $this->checker->check(1000.0, 'credit_card');

        $this->assertFalse($assessment->isHighRisk);
    }
}

