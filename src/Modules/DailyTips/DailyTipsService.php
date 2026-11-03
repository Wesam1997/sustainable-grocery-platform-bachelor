<?php
declare(strict_types=1);

final class DailyTipsService
{
    private string $cacheDirectory;
    private DateTimeZone $timezone;

    public function __construct(
        private ?DailyTipsGeneratorInterface $generator = null,
        ?string $cacheDirectory = null
    ) {
        $this->cacheDirectory =
            $cacheDirectory ??
            dirname(__DIR__, 3) .
            '/var/daily-tips';

        $this->timezone = new DateTimeZone(
            'Europe/Copenhagen'
        );
    }

    /**
     * Return today's three sustainability tips.
     *
     * @return array{
     *     date: string,
     *     tips: array<int, string>,
     *     source: string
     * }
     */
    public function getToday(): array
    {
        $today = new DateTimeImmutable(
            'now',
            $this->timezone
        );

        $dateKey = $today->format('Y-m-d');
        $cacheFile = $this->cacheFile($dateKey);

        /*
         * Use the saved result if today's
         * tips have already been generated.
         */
        $cached = $this->readCache(
            $cacheFile,
            $dateKey
        );

        if ($cached !== null) {
            return $cached;
        }

        /*
         * Try Ollama when a generator is enabled.
         */
        if ($this->generator !== null) {
            try {
                $previousTips =
                    $this->readPreviousTips();

                $generatedTips =
                    $this->generator
                        ->generateDailyTips(
                            $previousTips
                        );

                if ($this->tipsAreValid(
                    $generatedTips
                )) {
                    $result = [
                        'date' => $dateKey,
                        'tips' => array_values(
                            $generatedTips
                        ),
                        'source' => 'llm',
                    ];

                    $this->saveCache(
                        $cacheFile,
                        $result
                    );

                    return $result;
                }

                error_log(
                    'daily_tips.invalid_llm_output'
                );
            } catch (Throwable $error) {
                error_log(
                    'daily_tips.llm_failed: ' .
                    $error->getMessage()
                );
            }
        }

        /*
         * Safe fallback if Ollama is disabled,
         * unavailable or returns invalid content.
         */
        $result = [
            'date' => $dateKey,
            'tips' => $this->fallbackTips(
                $today
            ),
            'source' => 'fallback',
        ];

        $this->saveCache(
            $cacheFile,
            $result
        );

        return $result;
    }

    private function cacheFile(
        string $dateKey
    ): string {
        return $this->cacheDirectory .
            DIRECTORY_SEPARATOR .
            $dateKey .
            '.json';
    }

    /**
     * @return array{
     *     date: string,
     *     tips: array<int, string>,
     *     source: string
     * }|null
     */
    private function readCache(
        string $file,
        string $expectedDate
    ): ?array {
        if (!is_file($file)) {
            return null;
        }

        try {
            $content = file_get_contents($file);

            if (
                !is_string($content) ||
                trim($content) === ''
            ) {
                return null;
            }

            $data = json_decode(
                $content,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (!is_array($data)) {
                return null;
            }

            if (
                ($data['date'] ?? null) !==
                $expectedDate
            ) {
                return null;
            }

            $tips = $data['tips'] ?? null;
            $source = $data['source'] ?? null;

            if (!$this->tipsAreValid($tips)) {
                return null;
            }

            if (
                !is_string($source) ||
                !in_array(
                    $source,
                    ['llm', 'fallback'],
                    true
                )
            ) {
                return null;
            }

            return [
                'date' => $expectedDate,
                'tips' => array_values($tips),
                'source' => $source,
            ];
        } catch (Throwable $error) {
            error_log(
                'daily_tips.invalid_cache: ' .
                $error->getMessage()
            );

            return null;
        }
    }

    /**
     * Read up to 30 older tips so Ollama
     * can avoid repeating them.
     *
     * @return array<int, string>
     */
    private function readPreviousTips(): array
    {
        if (!is_dir($this->cacheDirectory)) {
            return [];
        }

        $files = glob(
            $this->cacheDirectory .
            DIRECTORY_SEPARATOR .
            '*.json'
        );

        if (!is_array($files)) {
            return [];
        }

        rsort($files, SORT_STRING);

        $previousTips = [];

        foreach ($files as $file) {
            try {
                $content =
                    file_get_contents($file);

                if (!is_string($content)) {
                    continue;
                }

                $data = json_decode(
                    $content,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

                $tips = is_array($data)
                    ? ($data['tips'] ?? null)
                    : null;

                if (!$this->tipsAreValid($tips)) {
                    continue;
                }

                foreach ($tips as $tip) {
                    $previousTips[] = $tip;
                }

                if (count($previousTips) >= 30) {
                    break;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return array_slice(
            $previousTips,
            0,
            30
        );
    }

    private function tipsAreValid(
        mixed $tips
    ): bool {
        if (
            !is_array($tips) ||
            count($tips) !== 3
        ) {
            return false;
        }

        $normalizedTips = [];

        foreach ($tips as $tip) {
            if (!is_string($tip)) {
                return false;
            }

            $tip = trim($tip);

            if (!$this->tipIsValid($tip)) {
                return false;
            }

            $normalized = strtolower(
                preg_replace(
                    '/\s+/u',
                    ' ',
                    $tip
                ) ?? $tip
            );

            if (
                in_array(
                    $normalized,
                    $normalizedTips,
                    true
                )
            ) {
                return false;
            }

            $normalizedTips[] = $normalized;
        }

        return true;
    }

    private function tipIsValid(
        string $tip
    ): bool {
        if ($tip === '') {
            return false;
        }

        /*
         * Reject HTML generated by the model.
         */
        if (strip_tags($tip) !== $tip) {
            return false;
        }

        if (strlen($tip) > 300) {
            return false;
        }

        $words = preg_split(
            '/\s+/u',
            trim($tip),
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        if (!is_array($words)) {
            return false;
        }

        $wordCount = count($words);

        if (
            $wordCount < 8 ||
            $wordCount > 32
        ) {
            return false;
        }

        /*
         * It must be one finished sentence.
         */
        if (
            preg_match('/[.!?]$/u', $tip) !== 1
        ) {
            return false;
        }

        /*
         * Reject links, Markdown and code.
         */
        if (
            preg_match(
                '/https?:\/\/|www\.|```|<script/iu',
                $tip
            ) === 1
        ) {
            return false;
        }

        /*
         * Reject clearly inaccurate claims.
         */
        $forbiddenClaims = [
            '/organic\s+always\s+(has|have|means|produces)/iu',
            '/organic\s+is\s+always\s+lower/iu',
            '/lower[\s-]?co₂?e?\s+always\s+means\s+healthier/iu',
            '/zero\s+emissions/iu',
            '/carbon[\s-]?free/iu',
        ];

        foreach ($forbiddenClaims as $pattern) {
            if (
                preg_match($pattern, $tip) === 1
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function fallbackTips(
        DateTimeImmutable $date
    ): array {
        $groups = [
            [
                'Compare similar products and choose the lower-CO₂e option to reduce the estimated climate impact of your basket.',

                'Organic farming can support soil, biodiversity and production methods that limit the use of synthetic pesticides.',

                'Lower-impact choices can earn Green Points and help you unlock discounts while shopping.',
            ],

            [
                'Products within the same category can have different estimated climate impacts, making comparison a useful shopping tool.',

                'Choosing organic food can support environmentally considerate farming practices and stronger animal-welfare standards.',

                'Small lower-CO₂e choices can add up when they become part of your everyday shopping habits.',
            ],

            [
                'Compare two similar products to find the option with the lower estimated CO₂e value.',

                'Organic and lower-CO₂e describe different environmental qualities, and a product may offer both benefits.',

                'Flash Food rewards qualifying lower-impact choices with Green Points that can contribute towards discounts.',
            ],

            [
                'Climate information can help you make a more informed choice without giving up the food you enjoy.',

                'The organic label identifies products made according to regulated organic farming and production requirements.',

                'Checking similar products before choosing is a simple way to make your basket more climate-conscious.',
            ],

            [
                'CO₂e provides one common unit for comparing the estimated climate impact of different greenhouse gases.',

                'Organic farming practices can help protect soil quality, biodiversity and animal welfare.',

                'Choosing the lower-CO₂e product within the same category can make a meaningful difference over time.',
            ],
        ];

        $dayOfYear = (int) $date->format('z');

        return $groups[
            $dayOfYear % count($groups)
        ];
    }

    /**
     * @param array{
     *     date: string,
     *     tips: array<int, string>,
     *     source: string
     * } $data
     */
    private function saveCache(
        string $file,
        array $data
    ): void {
        if (!is_dir($this->cacheDirectory)) {
            if (
                !mkdir(
                    $this->cacheDirectory,
                    0775,
                    true
                ) &&
                !is_dir($this->cacheDirectory)
            ) {
                throw new RuntimeException(
                    'Cannot create daily tips cache directory.'
                );
            }
        }

        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE |
            JSON_THROW_ON_ERROR
        );

        $written = file_put_contents(
            $file,
            $json,
            LOCK_EX
        );

        if ($written === false) {
            throw new RuntimeException(
                'Cannot save daily tips cache.'
            );
        }
    }
}