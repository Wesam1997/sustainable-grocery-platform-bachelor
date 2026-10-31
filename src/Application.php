<?php
declare(strict_types=1);
// Existing order and cart dependencies
require_once __DIR__ . '/Infrastructure/Database/MysqliBasketStore.php';
require_once __DIR__ . '/Contracts/OrderRepositoryInterface.php';
require_once __DIR__ . '/Modules/Orders/OrderService.php';
require_once __DIR__ . '/Infrastructure/Database/MysqliOrderRepository.php';

// Existing cart climate summary
require_once __DIR__ . '/Modules/Cart/CartClimateSummaryService.php';

// Green Points reward service
require_once __DIR__ . '/Modules/Rewards/GreenPointsService.php';

// Order history dependencies
require_once __DIR__ . '/Application/Contracts/OrderHistoryReaderInterface.php';
require_once __DIR__ . '/Application/Services/OrderHistoryService.php';
require_once __DIR__ . '/Infrastructure/Persistence/MysqliOrderHistoryReader.php';

/**
 * Composition root: selects concrete adapters.
 * Services depend on contracts.
 */
final class Application
{
    private ProductRepositoryInterface $products;
    private ClimateMetricsInterface $metrics;
    private CatalogServiceInterface $catalog;
    private UserServiceInterface $users;
    private CartServiceInterface $cart;
    private FavoriteServiceInterface $favorites;
    private SearchServiceInterface $search;
    private RateLimiterInterface $rateLimiter;
    private ProductAdministrationInterface $administration;

    private OrderService $orders;
    private OrderHistoryService $orderHistory;
    private GreenPointsService $greenPoints;

    public function __construct(
        mysqli $connection,
        ?LlmClientInterface $llm = null
    ) {
        // Comparison references and climate metrics
        $references = new MysqliComparisonReferences($connection);

        $this->metrics = self::createMetrics(
            $llm,
            $references
        );

        // Product repository
        $this->products = new MysqliProductRepository($connection);

        // Climate and catalog services
        $climate = new ClimateService(
            ClimateServiceFactory::create(RESTAURANTS)
        );

        $this->catalog = new CatalogService(
            $this->products,
            new ProductSignals($climate)
        );

        // User service
        $this->users = new UserService(
            new MysqliUserRepository($connection)
        );

        // Shared basket storage
        // Resolves user_id on each operation.
        $store = new MysqliBasketStore($connection);

        // Cart service
        $this->cart = new CartService(
            $this->products,
            $store
        );

        // Favorite service
        $this->favorites = new FavoriteService(
            $this->products,
            $store
        );

        /*
         * Climate summary for checkout.
         *
         * Reuses the existing cart climate calculations.
         * No duplicate climate calculation logic.
         */
        $cartClimateSummary = new CartClimateSummaryService(
            $this->catalog,
            $this->metrics
        );

        /*
         * Green Points service.
         *
         * Rules:
         * - Low Climate Impact: 10 points per unit
         * - Medium Climate Impact: 0 points
         * - High Climate Impact: 0 points
         * - Unknown Climate Impact: 0 points
         *
         * Reward threshold:
         * 400 points = 20% discount.
         */
        $this->greenPoints = new GreenPointsService();

        /*
         * Order service.
         *
         * Checkout uses the existing climate summary
         * to calculate emissions for purchased quantities.
         *
         * GreenPointsService calculates rewards
         * for each purchased Low Impact product.
         *
         * MysqliOrderRepository persists climate data
         * and earned points together with the order.
         */
        $this->orders = new OrderService(
            $this->cart,
            new MysqliOrderRepository($connection),
            $cartClimateSummary,
            $this->greenPoints
        );

        // Order history - read-only access to previous purchases
        $this->orderHistory = new OrderHistoryService(
            new MysqliOrderHistoryReader($connection)
        );

        // Search service
        $this->search = new SmartSearchService(
            $this->products
        );

        // Rate limiter
        $this->rateLimiter = new MysqliRateLimiter(
            $connection
        );

        // Product administration
        $this->administration = new MysqliProductAdministration(
            $connection
        );
    }

    /**
     * Creates the climate metrics service.
     *
     * Climate calculations are performed by PHP.
     * ExplanationService uses the provided LLM client
     * to generate explanations when available.
     *
     * Invalid LLM responses fall back to
     * deterministic PHP explanations.
     */
    public static function createMetrics(
        ?LlmClientInterface $llm = null,
        ?ComparisonReferenceInterface $references = null
    ): ClimateMetricsInterface {
        return new ClimateMetrics(
            new ComparisonEngine(
                $references ?? new ArrayComparisonReferences(
                    require __DIR__ . '/Config/comparisons.php'
                )
            ),
            new ExplanationService(
                $llm,
                new SystemLogger()
            )
        );
    }

    public function metrics(): ClimateMetricsInterface
    {
        return $this->metrics;
    }

    public function products(): ProductRepositoryInterface
    {
        return $this->products;
    }

    public function catalog(): CatalogServiceInterface
    {
        return $this->catalog;
    }

    public function users(): UserServiceInterface
    {
        return $this->users;
    }

    public function cart(): CartServiceInterface
    {
        return $this->cart;
    }

    public function favorites(): FavoriteServiceInterface
    {
        return $this->favorites;
    }

    public function orders(): OrderService
    {
        return $this->orders;
    }

    public function orderHistory(): OrderHistoryService
    {
        return $this->orderHistory;
    }

    public function greenPoints(): GreenPointsService
    {
        return $this->greenPoints;
    }

    public function search(): SearchServiceInterface
    {
        return $this->search;
    }

    public function rateLimiter(): RateLimiterInterface
    {
        return $this->rateLimiter;
    }

    public function administration(): ProductAdministrationInterface
    {
        return $this->administration;
    }
}
