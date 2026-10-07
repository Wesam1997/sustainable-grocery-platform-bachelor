<?php
declare(strict_types=1);

/** Composition root: selects concrete adapters. Services depend on contracts. */
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
    public function __construct(mysqli $connection, ?LlmClientInterface $llm = null)
    {
        $references = getenv('FLASHFOOD_COMPARISON_SOURCE') === 'database'
            ? new MysqliComparisonReferences($connection)
            : null;
        $this->metrics = self::createMetrics($llm, $references);
        $this->products = new MysqliProductRepository($connection);
        $climate = new ClimateService(ClimateServiceFactory::create(RESTAURANTS));
        $this->catalog = new CatalogService($this->products, new ProductSignals($climate));
        $this->users = new UserService(new MysqliUserRepository($connection));
        $store = new SessionBasketStore();
        $this->cart = new CartService($this->products, $store);
        $this->favorites = new FavoriteService($this->products, $store);
        $this->search = new SmartSearchService($this->products);
        $this->rateLimiter = new MysqliRateLimiter($connection);
        $this->administration = new MysqliProductAdministration($connection);
    }
    public static function createMetrics(?LlmClientInterface $llm = null, ?ComparisonReferenceInterface $references = null): ClimateMetricsInterface
    {
        return new ClimateMetrics(
            new ComparisonEngine($references ?? new ArrayComparisonReferences(require __DIR__ . '/Config/comparisons.php')),
            new ExplanationService($llm, new SystemLogger())
        );
    }
    public function metrics(): ClimateMetricsInterface { return $this->metrics; }
    public function products(): ProductRepositoryInterface { return $this->products; }
    public function catalog(): CatalogServiceInterface { return $this->catalog; }
    public function users(): UserServiceInterface { return $this->users; }
    public function cart(): CartServiceInterface { return $this->cart; }
    public function favorites(): FavoriteServiceInterface { return $this->favorites; }
    public function search(): SearchServiceInterface { return $this->search; }
    public function rateLimiter(): RateLimiterInterface { return $this->rateLimiter; }
    public function administration(): ProductAdministrationInterface { return $this->administration; }
}


