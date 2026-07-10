-- Trigger: Update cash register when a fee is collected
DELIMITER //

CREATE TRIGGER IF NOT EXISTS AfterFeePaymentCollected
AFTER INSERT ON fee_collections
FOR EACH ROW
BEGIN
    UPDATE cash_register 
    SET total_collections = total_collections + NEW.amount_paid
    WHERE date = NEW.payment_date AND status = 'Open';
END //

DELIMITER ;
