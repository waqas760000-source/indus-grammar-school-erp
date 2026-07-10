-- View: Detailed Student Ledgers joining challans and collections
CREATE OR REPLACE VIEW view_student_ledgers AS
SELECT 
    s.id as student_id,
    s.first_name,
    s.last_name,
    s.admission_no,
    fc.challan_no,
    fc.net_amount as total_due,
    COALESCE(SUM(fcol.amount_paid), 0) as total_paid,
    (fc.net_amount - COALESCE(SUM(fcol.amount_paid), 0)) as balance_due
FROM students s
JOIN fee_challans fc ON s.id = fc.student_id
LEFT JOIN fee_collections fcol ON fc.id = fcol.challan_id
GROUP BY fc.id;
