<?php
/**
 * Redirect to unified Student Profile Report (Tabbed dossier)
 */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
header("Location: profile_report.php" . ($id > 0 ? "?id=$id" : ""));
exit;
