<?php

namespace App\Service;

class FraudChecker
{
    private const AMOUNT_HIGH_RISK_THRESHOLD = 1000.0;

    public function check(float $amount, string $paymentMethod): RiskAssessment
    {
        $riskScore = 0.0;
        $reasons = [];

        if ($amount > self::AMOUNT_HIGH_RISK_THRESHOLD) {
            $riskScore = 0.8;
            $reasons[] = sprintf('Amount %.2f exceeds high-risk threshold (%.2f)', $amount, self::AMOUNT_HIGH_RISK_THRESHOLD);
        } elseif ($amount > 500) {
            $riskScore = 0.4;
            $reasons[] = 'Amount in elevated range';
        }

        $isHighRisk = $riskScore >= 0.6;

        if (empty($reasons)) {
            $reason = 'Amount within normal range';
        } else {
            $reason = implode('; ', $reasons);
        }

        return new RiskAssessment(
            isHighRisk: $isHighRisk,
            reason: $reason,
            riskScore: $riskScore,
        );
    }
}

final class RiskAssessment
{
    public function __construct(
        public readonly bool   $isHighRisk,
        public readonly string $reason,
        public readonly float  $riskScore,
    ) {}
}

