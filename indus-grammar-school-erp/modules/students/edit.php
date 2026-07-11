<?php
/**
 * Redirect to unified Registration page for editing
 */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
header("Location: registration.php" . ($id > 0 ? "?id=$id" : ""));
exit;
