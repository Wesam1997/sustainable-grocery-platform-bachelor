USE flashfoodcart;

-- Synthetic prototype scenarios: these are NOT verified Danish emission factors.
-- Phone: assumed 0.015 kWh per full charge * assumed 0.200 kg CO2e/kWh = 0.003.
-- Warm shower: assumed 0.280 kWh per minute * same assumed electricity factor = 0.056.
-- Shower scenario covers electric water heating only; excludes water supply and treatment.
-- Existing rows, including the car reference, are left unchanged.
START TRANSACTION;

INSERT INTO climate_equivalence_reference
(comparison_key, label, kg_co2e_per_unit, unit, source_title, source_url, assumptions, is_example, enabled)
SELECT
'phone', 'Full phone charge (prototype scenario)', 0.00300000, 'charge',
'Assumed prototype scenario - not a measured factor', '',
'Synthetic test assumptions: 0.015 kWh electricity per full charge and 0.200 kg CO2e per kWh. Charging electricity only; excludes phone manufacture. Not a verified Danish average.',
1, 1
WHERE NOT EXISTS (SELECT 1 FROM climate_equivalence_reference WHERE comparison_key = 'phone');

INSERT INTO climate_equivalence_reference
(comparison_key, label, kg_co2e_per_unit, unit, source_title, source_url, assumptions, is_example, enabled)
SELECT
'shower', 'Warm shower minute (prototype scenario)', 0.05600000, 'min',
'Assumed prototype scenario - not a measured factor', '',
'Synthetic test assumptions: 0.280 kWh electric water heating per shower minute and 0.200 kg CO2e per kWh. Excludes water supply, treatment and equipment. Not a verified Danish average.',
1, 1
WHERE NOT EXISTS (SELECT 1 FROM climate_equivalence_reference WHERE comparison_key = 'shower');

COMMIT;

SELECT comparison_key, kg_co2e_per_unit, unit, is_example, enabled
FROM climate_equivalence_reference
WHERE comparison_key IN ('car', 'phone', 'shower')
ORDER BY comparison_key;
