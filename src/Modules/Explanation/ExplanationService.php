<?php
declare(strict_types=1);

final class ExplanationService implements ClimateExplanationInterface
{
    private string $lastSource = 'unavailable';

    public function __construct(
        private ?LlmClientInterface $llm,
        private LoggerInterface $logger
    ) {}

    public function lastSource(): string { return $this->lastSource; }

    public function explain(array $climate, ?array $comparison): string
    {
        $this->lastSource = 'unavailable';
        $fallback = (string)($climate['note'] ?? 'A comparison is currently unavailable.');
        if (empty($climate['available']) || $comparison === null) return $fallback;
        $basis = (string)($comparison['basis'] ?? '');
        $subject = match ($basis) {
            'per package' => 'this pack',
            'per serving' => 'one serving',
            'per box' => 'this box',
            'per kg' => '1 kg of this product',
            default => null
        };
        $raw = $comparison['value'] ?? null;
        if ($subject === null || !is_numeric($raw) || !is_finite((float)$raw) || (float)$raw < 0) return $fallback;
        $amount = rtrim(rtrim(number_format((float)$raw, 1, '.', ''), '0'), '.');
        if ((float)$raw > 0 && (float)$amount === 0.0) $amount = 'less than 0.1';
        $key = ($comparison['key'] ?? '') . ':' . ($comparison['unit'] ?? '');
        $activity = match ($key) {
            'car:km' => 'driving ' . $amount . ' km in a petrol car',
            'phone:charge' => $amount . ' full phone charges',
            'shower:min' => $amount . ' minutes of a warm shower',
            default => null
        };
        if ($activity === null) return $fallback;
        // Facts and conversion units remain under application control.
        $approved = [
            'The estimated emissions for ' . $subject . ' are comparable to ' . $activity . '.',
            'For ' . $subject . ', the estimated emissions correspond to ' . $activity . '.'
        ];
        $this->lastSource = 'fallback';
        if ($this->llm === null) return $approved[0];
        try {
            $text = trim($this->llm->generateExplanation([
                'approved_explanations' => $approved,
                'instruction' => 'Select one approved explanation. Return it exactly, without quotation marks or additional text.'
            ]));
            if (!in_array($text, $approved, true)) throw new UnexpectedValueException('Unapproved explanation.');
            $this->lastSource = 'llm';
            return $text;
        } catch (Throwable $error) {
            $this->logger->error('llm.explanation_failed', ['type' => get_class($error)]);
            return $approved[0];
        }
    }
}
