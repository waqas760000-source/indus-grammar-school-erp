<?php
/**
 * Permissions helper logic binding
 */
function checkPagePermission(string $perm) {
    AuthMiddleware::requirePermission($perm);
}
