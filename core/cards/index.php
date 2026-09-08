<?php

require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/config/connection.php';
require_once dirname(__DIR__) . '/auth/csrf.php';
require_once dirname(__DIR__) . '/auth/session-service.php';
require_once dirname(__DIR__) . '/auth/auth-service.php';
require_once dirname(__DIR__) . '/permissions/permission-service.php';
require_once dirname(__DIR__) . '/dashboard/dashboard-shell.php';
require_once dirname(__DIR__) . '/i18n/auth.php';

therain_session_start_secure();
$user = therain_require_login('../../auth/login.php');
$connection = therain_db();
$roles = therain_user_roles_for_tenant($user['id'], $user['tenant_id'], $connection);
$isSuperAdmin = false;
foreach ($roles as $role) {
    if (!empty($role['is_system_role']) && $role['slug'] === THERAIN_SUPER_ADMIN_ROLE_SLUG) {
        $isSuperAdmin = true;
        break;
    }
}
if (!$isSuperAdmin) {
    http_response_code(403);
    exit('Forbidden');
}

$colors = array('#17A2B8', '#6F42C1', '#FF7844', '#0F6BFF', '#059669', '#DC2626');
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!therain_csrf_verify(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : null)) {
        $error = 'Your session expired. Please try again.';
    } else {
        $holderName = trim(isset($_POST['holder_name']) ? $_POST['holder_name'] : '');
        $phone = trim(isset($_POST['phone']) ? $_POST['phone'] : '');
        $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
        $roleLabel = trim(isset($_POST['role_label']) ? $_POST['role_label'] : 'Staff');
        $cardType = in_array($_POST['card_type'] ?? 'STAFF', array('STAFF', 'CUSTOMER'), true) ? $_POST['card_type'] : 'STAFF';
        $themeColor = strtoupper(trim(isset($_POST['theme_color']) ? $_POST['theme_color'] : '#17A2B8'));
        $linkedUserId = !empty($_POST['user_id']) ? (int) $_POST['user_id'] : null;
        if ($holderName === '' || !in_array($themeColor, $colors, true)) {
            $error = 'Holder name and a valid card color are required.';
        } else {
            $cardNumber = 'YT-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
            $qrPayload = 'therain-card:' . bin2hex(random_bytes(16));
            $statement = $connection->prepare(
                'INSERT INTO identity_cards (tenant_id, user_id, card_type, card_number, holder_name, role_label, phone, email, theme_color, qr_payload, issued_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );
            $statement->bind_param('iissssssss', $user['tenant_id'], $linkedUserId, $cardType, $cardNumber, $holderName, $roleLabel, $phone, $email, $themeColor, $qrPayload);
            if ($statement->execute()) {
                $message = 'Card ' . $cardNumber . ' created successfully.';
            } else {
                $error = 'Unable to create the card.';
            }
            $statement->close();
        }
    }
}

$staff = array();
$staffStatement = $connection->prepare(
    'SELECT users.id, users.email, user_profiles.first_name, user_profiles.last_name, user_profiles.phone,
            GROUP_CONCAT(DISTINCT roles.name ORDER BY roles.name SEPARATOR ", ") AS role_name
     FROM users LEFT JOIN user_profiles ON user_profiles.user_id = users.id
     LEFT JOIN user_roles ON user_roles.user_id = users.id AND user_roles.tenant_id = users.tenant_id
     LEFT JOIN roles ON roles.id = user_roles.role_id
     WHERE users.tenant_id = ? AND users.status = "active" ORDER BY users.id'
);
$staffStatement->bind_param('i', $user['tenant_id']);
$staffStatement->execute();
$staff = $staffStatement->get_result()->fetch_all(MYSQLI_ASSOC);
$staffStatement->close();

$cards = array();
$cardStatement = $connection->prepare(
    'SELECT identity_cards.*, user_profiles.first_name, user_profiles.last_name, user_profiles.phone AS profile_phone, user_profiles.profile_image_path,
            users.email AS profile_email, tenants.name AS tenant_name, tenants.business_name
     FROM identity_cards
     LEFT JOIN users ON users.id = identity_cards.user_id AND users.tenant_id = identity_cards.tenant_id
     LEFT JOIN user_profiles ON user_profiles.user_id = users.id
     LEFT JOIN tenants ON tenants.id = identity_cards.tenant_id
     WHERE identity_cards.tenant_id = ? ORDER BY identity_cards.created_at DESC'
);
$cardStatement->bind_param('i', $user['tenant_id']);
$cardStatement->execute();
$cards = $cardStatement->get_result()->fetch_all(MYSQLI_ASSOC);
$cardStatement->close();

$escape = function ($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); };
$cardMarkup = '';
foreach ($cards as $card) {
    $name = trim(($card['first_name'] ?? '') . ' ' . ($card['last_name'] ?? '')) ?: $card['holder_name'];
    $phone = $card['profile_phone'] ?: $card['phone'];
    $email = $card['profile_email'] ?: $card['email'];
    $businessName = $card['business_name'] ?: ($card['tenant_name'] ?: 'Young Tech Pharmacy');
    $barcode = preg_replace('/[^A-Z0-9-]/', '', strtoupper($card['card_number']));
    $qrCode = rawurlencode($card['qr_payload']);
    $cardMarkup .= '<article class="identity-card-pair" data-card-id="' . (int) $card['id'] . '" style="--identity-card-color:' . $escape($card['theme_color']) . '">'
        . '<div class="identity-card identity-card-front">'
        . '<header class="identity-card-brand"><span class="identity-brand-mark">TW</span><span><strong>TheRain <b>Unified</b></strong><small>Smart Management Solutions</small></span><i></i><span class="identity-tenant"><strong>' . $escape($businessName) . '</strong><small>Pharmacy</small></span></header>'
        . '<div class="identity-card-front-body"><div class="identity-card-photo">' . (!empty($card['profile_image_path']) ? '<img src="../../' . $escape(ltrim($card['profile_image_path'], '/')) . '" alt="">' : '<span class="fas fa-user"></span>') . '</div><div class="identity-card-details"><h3>' . $escape($name) . '</h3><p>' . $escape($card['role_label']) . '</p><dl><dt>Email</dt><dd>' . $escape($email) . '</dd><dt>Phone</dt><dd>' . $escape($phone) . '</dd><dt>Employee ID</dt><dd>' . $escape($card['card_number']) . '</dd><dt>Tenant</dt><dd>' . $escape($businessName) . '</dd></dl></div><div class="identity-card-qr"><span class="identity-qr-pattern" data-qr="' . $escape($qrCode) . '"></span><small>Scan to verify</small></div></div>'
        . '<footer><span class="identity-card-ribbon"><i class="fas fa-user"></i> ' . $escape($card['card_type']) . '</span><strong>Professional identity card</strong></footer></div>'
        . '<div class="identity-card identity-card-back">'
        . '<header class="identity-card-back-brand"><strong>TheRain Unified</strong><span>' . $escape($businessName) . '</span></header>'
        . '<div class="identity-card-back-body"><section><h4>Contact Information</h4><p><i class="fas fa-map-marker-alt"></i> Douala, Cameroon</p><p><i class="fas fa-phone"></i> ' . $escape($phone) . '</p><p><i class="fas fa-envelope"></i> ' . $escape($email) . '</p><hr><div class="identity-card-status"><b>Card Status</b><strong>' . $escape($card['status']) . '</strong><span>Issued ' . $escape($card['issued_at']) . '</span></div></section><section class="identity-card-verification"><div class="identity-barcode"><span>' . $escape($barcode) . '</span></div><small>' . $escape($card['card_number']) . '</small><span class="identity-qr-pattern" data-qr="' . $escape($qrCode) . '"></span><small>Scan to verify or get contact</small></section></div>'
        . '<footer><span><i class="fas fa-shield-alt"></i> This card is the property of ' . $escape($businessName) . '</span><strong>Smart card • QR verified</strong></footer></div>'
        . '<button class="identity-card-print" type="button" data-print-card="' . (int) $card['id'] . '"><i class="fas fa-print"></i> Print front &amp; back</button></article>';
}
if ($cardMarkup === '') {
    $cardMarkup = '<div class="overview-empty"><i class="fas fa-id-card"></i><strong>No cards created yet</strong><p>Create a card from an existing Young Tech staff account. The selected color is stored with the card.</p></div>';
}

$content = '<section class="dashboard-hero"><div><p class="dashboard-kicker">Identity Management</p><h1>Staff ID Cards</h1><p>Create professional tenant-scoped cards for existing staff members.</p></div><div class="dashboard-date"><i class="fas fa-id-card"></i><span>' . count($cards) . ' cards</span></div></section>';
$content .= '<section class="dashboard-card-layout"><article class="dashboard-panel"><div class="panel-heading"><div><i class="fas fa-plus-circle"></i><div><h2>Create a card</h2><small>Link it to an existing staff account</small></div></div></div>';
if ($message) $content .= '<div class="card-feedback card-feedback-success">' . $escape($message) . '</div>';
if ($error) $content .= '<div class="card-feedback card-feedback-error">' . $escape($error) . '</div>';
$content .= '<form method="post" class="identity-card-form">' . therain_csrf_field() . '<label>Existing staff member<select name="user_id" data-staff-select><option value="">Choose staff member</option>';
foreach ($staff as $member) { $name = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')) ?: $member['email']; $roleName = $member['role_name'] ?: 'Staff member'; $content .= '<option value="' . (int) $member['id'] . '" data-name="' . $escape($name) . '" data-email="' . $escape($member['email']) . '" data-phone="' . $escape($member['phone']) . '" data-role="' . $escape($roleName) . '">' . $escape($name . ' - ' . $member['email']) . '</option>'; }
$content .= '</select></label><label>Card type<select name="card_type"><option value="STAFF">Staff card</option><option value="CUSTOMER">Customer card</option></select></label><label>Holder name<input name="holder_name" required data-holder-name></label><label>Role / job title<input name="role_label" value="Staff member" data-role-label></label><div class="identity-form-grid"><label>Phone<input name="phone" data-phone></label><label>Email<input name="email" type="email" data-email></label></div><fieldset><legend>Choose card color</legend><div class="identity-color-options">';
foreach ($colors as $color) { $content .= '<label class="identity-color-swatch" style="--swatch:' . $color . '"><input type="radio" name="theme_color" value="' . $color . '" ' . ($color === '#17A2B8' ? 'checked' : '') . '><span title="' . $color . '"></span></label>'; }
$content .= '</div></fieldset><button class="auth-button" type="submit"><i class="fas fa-id-card"></i> Create card</button></form></article><section><div class="identity-card-grid">' . $cardMarkup . '</div></section></section><script>(function(){var staff=document.querySelector("[data-staff-select]");if(staff){staff.addEventListener("change",function(){var o=this.options[this.selectedIndex];document.querySelector("input[name=holder_name]").value=o.dataset.name||"";document.querySelector("input[name=email]").value=o.dataset.email||"";document.querySelector("input[name=phone]").value=o.dataset.phone||"";document.querySelector("input[name=role_label]").value=o.dataset.role||"Staff member";});}Array.prototype.forEach.call(document.querySelectorAll("[data-print-card]"),function(button){button.addEventListener("click",function(){Array.prototype.forEach.call(document.querySelectorAll("[data-card-id]"),function(card){card.classList.toggle("identity-card-print-target",card.getAttribute("data-card-id")===button.getAttribute("data-print-card"));});window.print();});});}());</script>';

global $therain_dashboard_user, $therain_dashboard_base;
$therain_dashboard_user = $user;
$therain_dashboard_base = '../../';
$identity = therain_dashboard_identity($user, $connection);
therain_dashboard_render($identity, array(array('label' => 'Dashboard', 'icon' => 'fas fa-home', 'route' => 'auth/home.php'), array('label' => 'ID Cards', 'icon' => 'fas fa-id-card', 'route' => 'core/cards/index.php')), therain_notification_unread_count($user['id'], $user['tenant_id'], $connection), $content, 'Pharmacy Management');
