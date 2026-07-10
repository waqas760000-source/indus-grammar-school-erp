<?php
/**
 * String and Currency Formatter Helper logic
 */
class Formatter {
    public static function currency(float $val): string {
        return 'Rs. ' . number_format($val, 2);
    }
}
