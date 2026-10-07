<?php
declare(strict_types=1);

final class ExplanationService implements ClimateExplanationInterface
{
        public function __construct(private ?LlmClientInterface $llm, private LoggerInterface $logger) {}

        public function explain(array $climate, ?array $comparison): string
        {
            $fallback = (string)($climate['note'] ?? 'Climate information is not available.');
            if ($this->llm === null || empty($climate['available'])) return $fallback;

            // The model may select an approved explanation, but cannot introduce new facts.
            $approved = [$fallback];
            if ($comparison !== null && $comparison['key'] === 'car') {
                $prefix = !empty($comparison['is_example']) ? 'Illustrative comparison: ' : 'Approximate comparison: ';
                $approved[] = $prefix . number_format((float)$comparison['value'], 1, '.', '')
                    . ' km of car driving for the same emissions ' . $comparison['basis'] . '. ' . $fallback;
            }
            try {
                $text = trim($this->llm->generateExplanation([
                    'climate' => $climate, 'comparison' => $comparison,
                    'approved_explanations' => $approved,
                    'instruction' => 'Return exactly one approved explanation. Do not change its numbers or wording.'
                ]));
                if (!in_array($text, $approved, true)) throw new UnexpectedValueException('Unapproved explanation.');
                return $text;
            } catch (Throwable $error) {
                $this->logger->error('llm.explanation_failed', ['type' => get_class($error)]);
                return $fallback;
            }
        }
}
