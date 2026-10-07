<?php
declare(strict_types=1);

interface LlmClientInterface
{
    /** Receives calculated facts; never performs the climate calculation. */
    public function generateExplanation(array $facts): string;
}
