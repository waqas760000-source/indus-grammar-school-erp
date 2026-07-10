<?php
/**
 * Layout Session settings validator
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
