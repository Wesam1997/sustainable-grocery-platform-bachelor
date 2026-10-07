<?php
declare(strict_types=1);

final class MysqliComparisonReferences implements ComparisonReferenceInterface
{
        public function __construct(private mysqli $connection) {}
        public function find(string $key): ?array
        {
            $stmt = $this->connection->prepare(
                'SELECT comparison_key, label, kg_co2e_per_unit, unit, source_title, source_url, assumptions, is_example
                 FROM climate_equivalence_reference WHERE comparison_key = ? AND enabled = 1 LIMIT 1'
            );
            try {
                $stmt->bind_param('s', $key);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                if (!$row) return null;
                $row['source'] = ['title'=>$row['source_title'], 'url'=>$row['source_url'], 'assumptions'=>$row['assumptions']];
                return $row;
            } finally { $stmt->close(); }
        }
}
