<?php

require_once __DIR__ . '/../core/config/bootstrap.php';
require_once __DIR__ . '/../core/config/catalog.php';
require_once __DIR__ . '/../core/auth/csrf.php';
require_once __DIR__ . '/../core/auth/session-service.php';
require_once __DIR__ . '/../core/currency/currency-service.php';
require_once __DIR__ . '/../core/i18n/auth.php';
require_once dirname(__DIR__) . '/modules/module-registry.php';

therain_session_start_secure();

if (!empty($_SESSION['therain_user_id'])) {
    header('Location: home.php');
    exit();
}

$errors = isset($_SESSION['therain_register_errors']) ? $_SESSION['therain_register_errors'] : array();
$old = isset($_SESSION['therain_register_old']) ? $_SESSION['therain_register_old'] : array();
unset($_SESSION['therain_register_errors'], $_SESSION['therain_register_old']);

$modules = therain_module_registry();
$languages = therain_language_options();
$timezones = therain_timezone_options();
$currentLocale = therain_auth_locale();
$authLanguages = therain_language_options();
$locations = array(
  'CM' => array('name' => 'Cameroon', 'dial' => '+237', 'cities' => array('Bafoussam', 'Bamenda', 'Douala', 'Garoua', 'Maroua', 'Yaounde')),
  'NG' => array('name' => 'Nigeria', 'dial' => '+234', 'cities' => array('Abuja', 'Benin City', 'Ibadan', 'Kano', 'Lagos', 'Port Harcourt')),
  'GH' => array('name' => 'Ghana', 'dial' => '+233', 'cities' => array('Accra', 'Cape Coast', 'Kumasi', 'Tamale', 'Tema')),
  'KE' => array('name' => 'Kenya', 'dial' => '+254', 'cities' => array('Eldoret', 'Kisumu', 'Mombasa', 'Nairobi', 'Nakuru')),
  'ZA' => array('name' => 'South Africa', 'dial' => '+27', 'cities' => array('Bloemfontein', 'Cape Town', 'Durban', 'Johannesburg', 'Pretoria')),
  'EG' => array('name' => 'Egypt', 'dial' => '+20', 'cities' => array('Alexandria', 'Cairo', 'Giza', 'Luxor', 'Port Said')),
  'MA' => array('name' => 'Morocco', 'dial' => '+212', 'cities' => array('Agadir', 'Casablanca', 'Fez', 'Marrakesh', 'Rabat', 'Tangier')),
  'CI' => array('name' => 'Cote d\'Ivoire', 'dial' => '+225', 'cities' => array('Abidjan', 'Bouake', 'Korhogo', 'Yamoussoukro')),
  'SN' => array('name' => 'Senegal', 'dial' => '+221', 'cities' => array('Dakar', 'Kaolack', 'Saint-Louis', 'Thies')),
  'TZ' => array('name' => 'Tanzania', 'dial' => '+255', 'cities' => array('Arusha', 'Dar es Salaam', 'Dodoma', 'Mwanza', 'Zanzibar')),
  'UG' => array('name' => 'Uganda', 'dial' => '+256', 'cities' => array('Entebbe', 'Jinja', 'Kampala', 'Mbarara')),
  'ET' => array('name' => 'Ethiopia', 'dial' => '+251', 'cities' => array('Addis Ababa', 'Bahir Dar', 'Gondar', 'Mekelle')),
  'GB' => array('name' => 'United Kingdom', 'dial' => '+44', 'cities' => array('Belfast', 'Birmingham', 'Glasgow', 'Liverpool', 'London', 'Manchester')),
  'FR' => array('name' => 'France', 'dial' => '+33', 'cities' => array('Bordeaux', 'Lille', 'Lyon', 'Marseille', 'Nantes', 'Paris')),
  'DE' => array('name' => 'Germany', 'dial' => '+49', 'cities' => array('Berlin', 'Cologne', 'Frankfurt', 'Hamburg', 'Munich')),
  'US' => array('name' => 'United States', 'dial' => '+1', 'cities' => array('Boston', 'Chicago', 'Houston', 'Los Angeles', 'New York', 'San Francisco', 'Seattle', 'Washington')),
  'CA' => array('name' => 'Canada', 'dial' => '+1', 'cities' => array('Calgary', 'Montreal', 'Ottawa', 'Toronto', 'Vancouver')),
  'BR' => array('name' => 'Brazil', 'dial' => '+55', 'cities' => array('Brasilia', 'Curitiba', 'Rio de Janeiro', 'Salvador', 'Sao Paulo')),
  'IN' => array('name' => 'India', 'dial' => '+91', 'cities' => array('Ahmedabad', 'Bengaluru', 'Chennai', 'Delhi', 'Hyderabad', 'Kolkata', 'Mumbai')),
  'CN' => array('name' => 'China', 'dial' => '+86', 'cities' => array('Beijing', 'Chengdu', 'Guangzhou', 'Shanghai', 'Shenzhen')),
  'JP' => array('name' => 'Japan', 'dial' => '+81', 'cities' => array('Fukuoka', 'Hiroshima', 'Kyoto', 'Osaka', 'Tokyo')),
  'AU' => array('name' => 'Australia', 'dial' => '+61', 'cities' => array('Adelaide', 'Brisbane', 'Melbourne', 'Perth', 'Sydney')),
  'AE' => array('name' => 'United Arab Emirates', 'dial' => '+971', 'cities' => array('Abu Dhabi', 'Ajman', 'Dubai', 'Sharjah')),
  'SA' => array('name' => 'Saudi Arabia', 'dial' => '+966', 'cities' => array('Dammam', 'Jeddah', 'Mecca', 'Medina', 'Riyadh')),
  'TR' => array('name' => 'Turkiye', 'dial' => '+90', 'cities' => array('Ankara', 'Antalya', 'Bursa', 'Istanbul', 'Izmir')),
  'MX' => array('name' => 'Mexico', 'dial' => '+52', 'cities' => array('Cancun', 'Guadalajara', 'Mexico City', 'Monterrey', 'Puebla')),
  'AR' => array('name' => 'Argentina', 'dial' => '+54', 'cities' => array('Buenos Aires', 'Cordoba', 'Mendoza', 'Rosario')),
  'IT' => array('name' => 'Italy', 'dial' => '+39', 'cities' => array('Bologna', 'Florence', 'Milan', 'Naples', 'Rome', 'Turin')),
  'ES' => array('name' => 'Spain', 'dial' => '+34', 'cities' => array('Barcelona', 'Madrid', 'Malaga', 'Seville', 'Valencia')),
  'PT' => array('name' => 'Portugal', 'dial' => '+351', 'cities' => array('Braga', 'Coimbra', 'Lisbon', 'Porto')),
  'RU' => array('name' => 'Russia', 'dial' => '+7', 'cities' => array('Kazan', 'Moscow', 'Novosibirsk', 'Saint Petersburg')),
  'PK' => array('name' => 'Pakistan', 'dial' => '+92', 'cities' => array('Islamabad', 'Karachi', 'Lahore', 'Peshawar')),
  'ID' => array('name' => 'Indonesia', 'dial' => '+62', 'cities' => array('Bandung', 'Jakarta', 'Medan', 'Surabaya')),
  'MY' => array('name' => 'Malaysia', 'dial' => '+60', 'cities' => array('Johor Bahru', 'Kuala Lumpur', 'Kuching', 'Penang')),
  'PH' => array('name' => 'Philippines', 'dial' => '+63', 'cities' => array('Cebu City', 'Davao', 'Manila', 'Quezon City')),
  'TH' => array('name' => 'Thailand', 'dial' => '+66', 'cities' => array('Bangkok', 'Chiang Mai', 'Pattaya', 'Phuket')),
  'VN' => array('name' => 'Vietnam', 'dial' => '+84', 'cities' => array('Da Nang', 'Hanoi', 'Ho Chi Minh City', 'Hue')),
  'NZ' => array('name' => 'New Zealand', 'dial' => '+64', 'cities' => array('Auckland', 'Christchurch', 'Dunedin', 'Wellington')),
  'OTHER' => array('name' => 'Other country', 'dial' => '+', 'cities' => array('Other city')),
);

// Prefer the live, richer database currency catalog (Phase 5); fall back
// to the small static list only if the database is unreachable, so this
// form still renders without a database connection (Phase 3 behaviour).
try {
    $currencies = array();
    foreach (therain_currency_catalog(true) as $currencyRow) {
        $currencies[$currencyRow['code']] = $currencyRow['name'] . ' (' . $currencyRow['symbol'] . ')';
    }
    if (empty($currencies)) {
        $currencies = therain_currency_options();
    }
} catch (Exception $exception) {
    $currencies = therain_currency_options();
}

function therain_old($old, $key, $default = '')
{
    return isset($old[$key]) ? htmlspecialchars($old[$key], ENT_QUOTES, 'UTF-8') : $default;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>TheRain Unified | Create Account</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body class="auth-page">
<div class="auth-layout">
  <section class="auth-visual" aria-label="TheRain Unified">
    <div class="auth-visual-content">
      <img class="auth-visual-logo auth-visual-logo-light" src="../dist/image/logo-light.png" alt="TheRain Unified logo">
      <h1><?php echo therain_auth_t('tagline'); ?></h1>
      <p><?php echo therain_auth_t('platform'); ?> <?php echo therain_auth_t('secure'); ?></p>
      <div class="auth-features" aria-label="Platform highlights">
        <div><i class="fas fa-th-large" aria-hidden="true"></i><span>Unified<br>Platform</span></div>
        <div><i class="fas fa-shield-alt" aria-hidden="true"></i><span>Enterprise<br>Security</span></div>
        <div><i class="fas fa-chart-bar" aria-hidden="true"></i><span>Real-time<br>Analytics</span></div>
        <div><i class="fas fa-users" aria-hidden="true"></i><span>Multi-tenant<br>Ready</span></div>
      </div>
    </div>
  </section>
  <main class="auth-panel">
    <section class="auth-card" aria-labelledby="auth-title">
      <div class="auth-card-header">
        <div class="auth-language">
          <label class="sr-only" for="auth-language">Language</label>
          <select id="auth-language" onchange="window.location.href='register.php?lang=' + encodeURIComponent(this.value)" aria-label="Language">
            <?php foreach ($authLanguages as $code => $language) : ?><option value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $currentLocale === $code ? 'selected' : ''; ?>><?php echo htmlspecialchars($language['name'], ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?>
          </select>
        </div>
        <img class="auth-logo" src="../dist/image/logo.png" alt="TheRain Unified">
        <h2 id="auth-title"><?php echo therain_auth_t('create_account'); ?></h2>
        <p class="auth-description"><?php echo therain_auth_t('register_description'); ?></p>
      </div>
      <?php if (!empty($errors)) : ?>
        <div class="alert alert-danger">
          <ul class="mb-0 pl-3">
            <?php foreach ($errors as $error) : ?>
              <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form action="actions/register.php" method="post" enctype="multipart/form-data">
        <?php echo therain_csrf_field(); ?>

        <h6 class="auth-section"><?php echo therain_auth_t('owner'); ?></h6>
        <div class="form-group">
          <label><?php echo therain_auth_t('owner_name'); ?></label>
            <input type="text" class="form-control" name="full_name" value="<?php echo therain_old($old, 'full_name'); ?>" placeholder="Enter your full name" required>
        </div>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label><?php echo therain_auth_t('email'); ?></label>
            <input type="email" class="form-control" name="email" value="<?php echo therain_old($old, 'email'); ?>" placeholder="Enter your email address" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label><?php echo therain_auth_t('password'); ?></label>
            <input type="password" class="form-control" name="password" placeholder="Create a strong password" required>
            <small class="form-text text-muted"><?php echo therain_auth_t('password_hint'); ?></small>
          </div>
          <div class="form-group col-md-6">
            <label><?php echo therain_auth_t('confirm_password'); ?></label>
            <input type="password" class="form-control" name="confirm_password" placeholder="Confirm your password" required>
          </div>
        </div>

        <h6 class="auth-section"><?php echo therain_auth_t('business'); ?></h6>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label><?php echo therain_auth_t('business_name'); ?></label>
            <input type="text" class="form-control" name="business_name" value="<?php echo therain_old($old, 'business_name'); ?>" placeholder="Enter your business or organization name" required>
          </div>
          <div class="form-group col-md-6">
            <label><?php echo therain_auth_t('business_type'); ?></label>
            <select class="form-control" name="business_type">
              <option value="">Select a business type</option>
              <option value="retail" <?php echo isset($old['business_type']) && $old['business_type'] === 'retail' ? 'selected' : ''; ?>>Retail</option>
              <option value="services" <?php echo isset($old['business_type']) && $old['business_type'] === 'services' ? 'selected' : ''; ?>>Services</option>
              <option value="healthcare" <?php echo isset($old['business_type']) && $old['business_type'] === 'healthcare' ? 'selected' : ''; ?>>Healthcare</option>
              <option value="hospitality" <?php echo isset($old['business_type']) && $old['business_type'] === 'hospitality' ? 'selected' : ''; ?>>Hospitality</option>
              <option value="education" <?php echo isset($old['business_type']) && $old['business_type'] === 'education' ? 'selected' : ''; ?>>Education</option>
              <option value="other" <?php echo isset($old['business_type']) && $old['business_type'] === 'other' ? 'selected' : ''; ?>>Other</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label><?php echo therain_auth_t('business_description'); ?></label>
          <textarea class="form-control" name="business_description" rows="2" placeholder="Describe your organization"><?php echo therain_old($old, 'business_description'); ?></textarea>
        </div>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label><?php echo therain_auth_t('business_email'); ?></label>
            <input type="email" class="form-control" name="business_email" value="<?php echo therain_old($old, 'business_email'); ?>" placeholder="Enter your business email">
          </div>
          <div class="form-group col-md-6">
            <label><?php echo therain_auth_t('business_phone'); ?></label>
            <input type="text" class="form-control" name="business_phone" value="<?php echo therain_old($old, 'business_phone'); ?>" placeholder="Enter your business phone number" required>
          </div>
        </div>
        <div class="form-group">
          <label><?php echo therain_auth_t('address'); ?></label>
          <input type="text" class="form-control" name="address" value="<?php echo therain_old($old, 'address'); ?>" placeholder="Enter your business address">
        </div>
        <div class="form-row auth-location-row">
          <div class="form-group col-md-4">
            <label><?php echo therain_auth_t('country'); ?></label>
            <select id="country" class="form-control" name="country" required></select>
          </div>
          <div class="form-group col-md-4">
            <label><?php echo therain_auth_t('city'); ?></label>
            <select id="city" class="form-control" name="city" required disabled><option value="">Select a country first</option></select>
          </div>
          <div class="form-group col-md-4">
            <label><?php echo therain_auth_t('phone'); ?></label>
            <div class="phone-control"><select id="phone-country-code" aria-label="Country calling code"></select><input type="tel" name="phone" value="<?php echo therain_old($old, 'phone'); ?>" placeholder="Enter your phone number" required></div>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label><?php echo therain_auth_t('currency'); ?></label>
            <select class="form-control" name="currency">
              <?php foreach ($currencies as $code => $label) : ?>
                <option value="<?php echo $code; ?>" <?php echo (isset($old['currency']) && $old['currency'] === $code) ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group col-md-6"></div>
        </div>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label><?php echo therain_auth_t('timezone'); ?></label>
            <select class="form-control" name="timezone">
              <?php foreach ($timezones as $timezone) : ?>
                <option value="<?php echo $timezone; ?>" <?php echo (isset($old['timezone']) && $old['timezone'] === $timezone) ? 'selected' : ''; ?>><?php echo $timezone; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group col-md-6"></div>
        </div>

        <h6 class="auth-section"><?php echo therain_auth_t('branding'); ?></h6>
        <div class="form-row">
          <div class="form-group col-md-6">
            <label><?php echo therain_auth_t('profile_picture'); ?></label>
            <input type="file" class="form-control-file" name="profile_picture" accept="image/png,image/jpeg,image/webp">
          </div>
          <div class="form-group col-md-6">
            <label><?php echo therain_auth_t('business_logo'); ?></label>
            <input type="file" class="form-control-file" name="business_logo" accept="image/png,image/jpeg,image/webp">
          </div>
        </div>
        <small class="form-text brand-hint mb-3 d-block"><?php echo therain_auth_t('upload_hint'); ?></small>

        <h6 class="auth-section"><?php echo therain_auth_t('management_system'); ?></h6>
        <div class="form-group">
          <label><?php echo therain_auth_t('management_system'); ?></label>
          <select class="form-control" name="management_system" required>
            <option value=""><?php echo therain_auth_t('select_system'); ?>&hellip;</option>
            <?php foreach ($modules as $slug => $module) : ?>
              <option value="<?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (isset($old['management_system']) && $old['management_system'] === $slug) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($module['name'], ENT_QUOTES, 'UTF-8'); ?><?php echo empty($module['enabled']) ? ' (coming soon)' : ''; ?>
              </option>
            <?php endforeach; ?>
          </select>
          <small class="form-text text-muted"><?php echo therain_auth_t('module_hint'); ?></small>
        </div>

        <label class="auth-check" for="terms_consent">
          <input type="checkbox" id="terms_consent" name="terms_consent" value="1" required>
          <span><?php echo therain_auth_t('terms'); ?></span>
        </label>

        <button type="submit" class="auth-button mt-3"><?php echo therain_auth_t('create_workspace'); ?> <i class="fas fa-arrow-right" aria-hidden="true"></i></button>
      </form>

      <p class="mt-3 mb-0 text-center">
        <?php echo therain_auth_t('already_account'); ?> <a href="login.php"><?php echo therain_auth_t('sign_in_here'); ?></a>
      </p>
    </div>
  </div>
</div>
<footer class="auth-footer">
  <div class="auth-footer-security"><i class="fas fa-shield-alt" aria-hidden="true"></i> Your data is protected with enterprise-grade security</div>
  <div class="auth-footer-copy">&copy; <?php echo date('Y'); ?> TheRain Unified. <?php echo therain_auth_t('copyright'); ?></div>
  <nav class="auth-footer-links" aria-label="Footer links"><a href="#">Privacy Policy</a><a href="#">Terms &amp; Conditions</a><a href="#">Help Center</a></nav>
</footer>

<script src="../plugins/jquery/jquery.min.js"></script>
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../dist/js/adminlte.min.js"></script>
<script>
var authLocations = <?php echo json_encode($locations); ?>;
var countrySelect = document.getElementById('country');
var citySelect = document.getElementById('city');
var phoneCode = document.getElementById('phone-country-code');
Object.keys(authLocations).forEach(function (code) {
  countrySelect.add(new Option(authLocations[code].name, code));
  phoneCode.add(new Option(authLocations[code].dial + ' ' + code, authLocations[code].dial));
});
function updateLocationFields() {
  var location = authLocations[countrySelect.value];
  citySelect.innerHTML = '';
  location.cities.forEach(function (city) { citySelect.add(new Option(city, city)); });
  citySelect.disabled = false;
  phoneCode.value = location.dial;
}
countrySelect.addEventListener('change', updateLocationFields);
var oldCountry = <?php echo json_encode(isset($old['country']) ? $old['country'] : 'CM'); ?>;
var selectedCountry = Object.keys(authLocations).find(function (code) { return authLocations[code].name === oldCountry || code === oldCountry; }) || 'CM';
countrySelect.value = selectedCountry;
updateLocationFields();
var oldCity = <?php echo json_encode(isset($old['city']) ? $old['city'] : ''); ?>;
if (oldCity) { citySelect.value = oldCity; }
</script>
</body>
</html>
