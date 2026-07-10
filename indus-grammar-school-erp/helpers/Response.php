<?php
/**
 * AJAX and HTTP Response Helper logic
 */
class Response {
    public static function json(array $data, int $code = 200): void {
        jsonResponse($data, $code);
    }
}
