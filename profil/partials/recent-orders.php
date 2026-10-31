<?php
declare(strict_types=1);

/**
 * Flash Food - Recent Purchases
 *
 * Groups actual purchases by date.
 * Displays products, quantities, prices and CO2e.
 *
 * Data is provided by OrderHistoryService.
 * No database queries are performed here.
 */

$recentOrders = $recentOrders ?? [];
$ordersLoaded = $ordersLoaded ?? false;

$dateGroups = [];

if ($ordersLoaded && is_array($recentOrders)) {

    foreach ($recentOrders as $order) {

        if (!is_array($order)) {
            continue;
        }

        $orderId = filter_var(
            $order['id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $createdAt = $order['created_at'] ?? null;
        $total = $order['total'] ?? null;

        if (
            $orderId === false
            || !is_string($createdAt)
            || !is_numeric($total)
            || !is_finite((float) $total)
            || (float) $total < 0
        ) {
            continue;
        }

        try {
            $date = new DateTimeImmutable($createdAt);
        } catch (Exception $e) {
            continue;
        }

        $dateKey = $date->format('Y-m-d');

        if (!isset($dateGroups[$dateKey])) {
            $dateGroups[$dateKey] = [
                'date' => $date->format('d/m/Y'),
                'orders' => [],
            ];
        }

        $dateGroups[$dateKey]['orders'][] = [
            'id' => (int) $orderId,
            'time' => $date->format('H:i'),
            'total' => (float) $total,
            'total_co2e_kg' =>
                $order['total_co2e_kg'] ?? null,
            'status' => (string) ($order['status'] ?? ''),
            'items' => is_array($order['items'] ?? null)
                ? $order['items']
                : [],
        ];
    }
}

// Newest purchase dates first.
krsort($dateGroups);

?>

<section
    class="card orders-card"
    id="recent-orders"
    aria-labelledby="recent-orders-title"
>

    <div class="section-heading">
        <div class="section-title">

            <span class="section-icon" aria-hidden="true">
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                >
                    <path d="M4 8h16l-1.5 12h-13L4 8Z"/>
                    <path d="M8 9V6a4 4 0 0 1 8 0v3"/>
                </svg>
            </span>

            <h2 id="recent-orders-title">
                Recent purchases
            </h2>

        </div>

        <p class="section-description">
            Select a date to see what you purchased.
        </p>
    </div>

    <?php if (!$ordersLoaded): ?>

        <div class="orders-empty">
            <h3>Purchase history is unavailable</h3>
            <p>Please try again later.</p>
        </div>

    <?php elseif ($dateGroups === []): ?>

        <div class="orders-empty">
            <h3>No purchases yet</h3>

            <p>
                Your purchases will appear here
                after you complete your first order.
            </p>

            <a
                href="../Home/home.php"
                class="shop-button"
            >
                Explore products
            </a>
        </div>

    <?php else: ?>

        <div class="purchase-date-list">

            <?php foreach ($dateGroups as $dateKey => $group): ?>

                <details class="purchase-date-group">

                    <summary class="purchase-date-summary">

                        <span class="purchase-date-heading">

                         

                            <strong>
                                <?= profileEscape($group['date']) ?>
                            </strong>

                            <small>
                                <?= count($group['orders']) ?>
                                <?= count($group['orders']) === 1
                                    ? 'order'
                                    : 'orders' ?>
                            </small>

                        </span>

                    

                    </summary>

                    <div class="purchase-date-content">

                        <?php foreach ($group['orders'] as $order): ?>

                            <article class="purchase-order">

                                <div class="purchase-order-header">

                                    <div>
                                        <strong>
                                            Order #<?= profileEscape(
                                                $order['id']
                                            ) ?>
                                        </strong>

                                        <small>
                                            <?= profileEscape(
                                                $order['time']
                                            ) ?>
                                        </small>
                                    </div>

                                    <span class="purchase-order-status">
                                        <?= profileEscape(
                                            ucfirst($order['status'])
                                        ) ?>
                                    </span>

                                </div>

                                <?php if ($order['items'] === []): ?>

                                    <p class="purchase-no-items">
                                        Product details are unavailable
                                        for this order.
                                    </p>

                                <?php else: ?>

                                    <ul class="purchase-product-list">

                                        <?php foreach ($order['items'] as $item): ?>

                                            <li class="purchase-product">

                                                <div class="purchase-product-info">

                                                    <strong>
                                                        <?= profileEscape(
                                                            $item['title']
                                                            ?? 'Product'
                                                        ) ?>
                                                    </strong>

                                                    <small>
                                                        Quantity:
                                                        <?= profileEscape(
                                                            $item['quantity']
                                                            ?? 0
                                                        ) ?>
                                                    </small>

                                                    <?php
                                                    $itemCo2e = $item['co2e_kg']
                                                        ?? null;

                                                    $itemCo2eValid =
                                                        is_numeric($itemCo2e)
                                                        && is_finite((float) $itemCo2e)
                                                        && (float) $itemCo2e >= 0;
                                                    ?>

                                                    <small>
                                                        COâ‚‚e:
                                                        <?php if ($itemCo2eValid): ?>

                                                            <?= profileEscape(
                                                                number_format(
                                                                    (float) $itemCo2e,
                                                                    3,
                                                                    '.',
                                                                    ','
                                                                )
                                                            ) ?> kg

                                                        <?php else: ?>

                                                            Not available

                                                        <?php endif; ?>
                                                    </small>

                                                </div>

                                                <strong class="purchase-product-price">
                                                    <?= profileEscape(
                                                        number_format(
                                                            (float) (
                                                                $item['line_total']
                                                                ?? 0
                                                            ),
                                                            2,
                                                            ',',
                                                            '.'
                                                        )
                                                    ) ?> kr.
                                                </strong>

                                            </li>

                                        <?php endforeach; ?>

                                    </ul>

                                <?php endif; ?>

                                <div class="purchase-order-total">

                                    <span>Order total</span>

                                    <strong>
                                        <?= profileEscape(
                                            number_format(
                                                $order['total'],
                                                2,
                                                ',',
                                                '.'
                                            )
                                        ) ?> kr.
                                    </strong>

                                </div>

                                <?php
                                $orderCo2e = $order['total_co2e_kg'];

                                $orderCo2eValid =
                                    is_numeric($orderCo2e)
                                    && is_finite((float) $orderCo2e)
                                    && (float) $orderCo2e >= 0;
                                ?>

                                <div class="purchase-order-climate">

                                    <span>Order climate footprint</span>

                                    <strong>
                                        <?php if ($orderCo2eValid): ?>

                                            <?= profileEscape(
                                                number_format(
                                                    (float) $orderCo2e,
                                                    3,
                                                    '.',
                                                    ','
                                                )
                                            ) ?> kg COâ‚‚e

                                        <?php else: ?>

                                            Not available

                                        <?php endif; ?>
                                    </strong>

                                </div>

                            </article>

                        <?php endforeach; ?>

                    </div>

                </details>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</section>

