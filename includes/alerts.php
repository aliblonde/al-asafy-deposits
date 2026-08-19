<?php
// includes/alerts.php — Flash message display
if (!empty($_SESSION['flash'])) {
    foreach ($_SESSION['flash'] as $flash) {
        $type = htmlspecialchars($flash['type']);
        $msg = htmlspecialchars($flash['msg']);
        echo "<div class=\"alert flash-{$type} border d-flex align-items-center gap-2 mb-3\" role=\"alert\" style=\"border-radius:8px\">
              <i class=\"bi bi-" . ($type === 'success' ? 'check-circle' : ($type === 'danger' ? 'exclamation-triangle' : 'info-circle')) . "\"></i>
              <span>{$msg}</span>
              <button type=\"button\" class=\"btn-close btn-close-white ms-auto\" data-bs-dismiss=\"alert\"></button>
              </div>\n";
    }
    unset($_SESSION['flash']);
}
