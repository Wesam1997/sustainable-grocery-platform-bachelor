<?php
declare(strict_types=1);

final class CatalogService implements CatalogServiceInterface
{
    public function __construct(private ProductRepositoryInterface $products, private ProductSignalsInterface $signals) {}
    public function find(int $id): ?array
    {
        $row = $this->products->find($id);
        if ($row !== null) $row['_sig'] = $this->signals->compute($row);
        return $row;
    }
    public function items(
        
        array $params
    ): array {
        $filters = CatalogFilters::parse($params);

        $selectedCO2 = [];

        if ($filters['lowCO2']) {
            $selectedCO2[] = 1;
        }

        if ($filters['midCO2']) {
            $selectedCO2[] = 2;
        }

        if ($filters['highCO2']) {
            $selectedCO2[] = 3;
        }

        $items = [];

        foreach ($this->products->listFiltered($filters) as $row) {
            $sig = $this->signals->compute($row);

            if (
                $filters['onlyVeg']
                && !$filters['onlyMeat']
                && $sig['diet'] !== 'veg'
            ) {
                continue;
            }

            if (
                $filters['onlyMeat']
                && !$filters['onlyVeg']
                && $sig['diet'] === 'veg'
            ) {
                continue;
            }

            if (
                $selectedCO2
                && !in_array(
                    $sig['co2']['rank'],
                    $selectedCO2,
                    true
                )
            ) {
                continue;
            }

            if (
                $filters['localOnly']
                && !$sig['local']
            ) {
                continue;
            }

            if (
                $filters['wmin'] !== null
                && (
                    $sig['weight_g'] === null
                    || $sig['weight_g']
                        < $filters['wmin']
                )
            ) {
                continue;
            }

            if (
                $filters['wmax'] !== null
                && (
                    $sig['weight_g'] === null
                    || $sig['weight_g']
                        > $filters['wmax']
                )
            ) {
                continue;
            }

            $row['_sig'] = $sig;
            $items[] = $row;
        }



        usort(
            $items,
            function (
                array $a,
                array $b
            ) use ($filters): int {
                switch ($filters['sort']) {
                    case 'price_asc':
                        return
                            (float)$a['price']
                            <=>
                            (float)$b['price'];

                    case 'price_desc':
                        return
                            (float)$b['price']
                            <=>
                            (float)$a['price'];

                    case 'newest':
                        return
                            (int)$b['id']
                            <=>
                            (int)$a['id'];

                    default:
                        $comparison =
                            $b['_sig']['eco_score']
                            <=>
                            $a['_sig']['eco_score'];

                        return $comparison !== 0
                            ? $comparison
                            : (
                                (float)$a['price']
                                <=>
                                (float)$b['price']
                            );
                }
            }
        );

        return $items;
    }
}
