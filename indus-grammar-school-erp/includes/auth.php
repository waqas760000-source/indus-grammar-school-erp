<?php
/**
 * Authentication helper logic binding
 */
if (!isLoggedIn()) {
    AuthMiddleware::requireLogin();
}
