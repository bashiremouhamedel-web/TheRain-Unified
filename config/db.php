<?php
	$therainCoreBootstrap = dirname(__DIR__) . '/core/config/bootstrap.php';
	if (is_file($therainCoreBootstrap)) {
		require_once $therainCoreBootstrap;
	}
	// ============================================================================
	// PHARMACY POS - DATABASE & SYSTEM CONFIGURATION
	// Cameroon Edition
	// ============================================================================
	
	// Set Timezone to Cameroon (Africa/Douala)
	date_default_timezone_set('Africa/Douala');
	
	// Database Configuration
	// THERAIN_PHARMACY_DB_OVERRIDE lets test tooling and the Phase 8
	// compatibility bridge (management/pharmacy/compatibility/bridge-service.php)
	// point this connection at a disposable database instead of the real
	// one, WITHOUT changing behavior for any existing deployment: the
	// override is only ever set explicitly by test bootstrap code, never
	// present in a normal request, so $db below is unchanged from before
	// this phase for every real Pharmacy installation.
	$host = "localhost";
	$name = "root";
	$pass = "";
	$db = getenv('THERAIN_PHARMACY_DB_OVERRIDE');
	if ($db === false || $db === '') {
		$db = "pharmacy";
	}

	// Connect to Database
	$conn = new mysqli($host, $name, $pass, $db);
	
	// Check connection
	if ($conn->connect_error) {
		die("Connection failed: " . $conn->connect_error);
	}
	
	// Set character set
	$conn->set_charset("utf8mb4");
	
	// ============================================================================
	// APPLICATION CONFIGURATION
	// ============================================================================
	
	// System Settings
	define('SYSTEM_TIMEZONE', 'Africa/Douala');
	define('SYSTEM_COUNTRY', 'Cameroon');

	// Phase 9 currency compatibility bridge (see docs/CURRENCY-ARCHITECTURE.md).
	// SYSTEM_CURRENCY/SYSTEM_CURRENCY_SYMBOL default to the original,
	// unconditional Cameroon values below -- unchanged for every legacy
	// store that was never provisioned through Unified registration, and
	// for every Standalone Pharmacy deployment that has no core/ directory
	// at all (checked via is_file() before ever requiring a CORE file, so
	// a standalone package missing core/ entirely still works exactly as
	// before). Only a session with a bridged store_id, on a machine where
	// the CORE files are actually present and reachable, can change this --
	// and any failure at any step (no bridge row, CORE database
	// unreachable, tenant has no currency set) falls back to the same
	// unconditional default silently, never a fatal error.
	$therainSystemCurrency = 'XAF';
	$therainSystemCurrencySymbol = 'FCFA';

	if (isset($_SESSION['store_id'])) {
		$therainBridgeServicePath = dirname(__DIR__) . '/management/pharmacy/compatibility/bridge-service.php';
		$therainCurrencyServicePath = dirname(__DIR__) . '/core/currency/currency-service.php';

		if (is_file($therainBridgeServicePath) && is_file($therainCurrencyServicePath)) {
			try {
				require_once $therainBridgeServicePath;
				require_once $therainCurrencyServicePath;

				$therainBridgedTenant = therain_pharmacy_tenant_for_store((int) $_SESSION['store_id']);

				if ($therainBridgedTenant !== null) {
					$therainTenantCurrency = therain_tenant_default_currency($therainBridgedTenant['tenant_id']);
					$therainActingUserId = isset($_SESSION['therain_acting_user_id']) ? (int) $_SESSION['therain_acting_user_id'] : 0;
					if ($therainActingUserId > 0) {
						$therainTenantCurrency = therain_user_currency_preference($therainActingUserId, $therainBridgedTenant['tenant_id']);
					}

					if ($therainTenantCurrency !== null) {
						$therainSystemCurrency = $therainTenantCurrency['code'];
						$therainSystemCurrencySymbol = !empty($therainTenantCurrency['symbol'])
							? $therainTenantCurrency['symbol']
							: $therainTenantCurrency['code'];
					}
				}
			} catch (Throwable $therainCurrencyBridgeException) {
				// Fall back to the Cameroon defaults set above -- see the
				// doc comment on this block for why this must never be fatal.
			}
		}
	}

	define('SYSTEM_CURRENCY', $therainSystemCurrency);
	define('SYSTEM_CURRENCY_SYMBOL', $therainSystemCurrencySymbol);
	
	// Cameroon Payment Methods
	define('PAYMENT_METHODS', array(
		'mtn_mobile_money' => 'MTN Mobile Money',
		'orange_mobile_money' => 'Orange Mobile Money',
		'other_mobile_money' => 'Mobile Money - Other',
		'bank_transfer' => 'Bank Transfer',
		'card_payment' => 'Card Payment',
		'binance_pay' => 'Binance Pay',
		'hand_cash' => 'Hand Cash',
		'cheque' => 'Cheque'
	));
	
	// Default Expense Categories
	define('DEFAULT_EXPENSE_CATEGORIES', array(
		'Rent', 'Utilities', 'Salaries', 'Marketing', 'Maintenance', 'Transport', 'Other'
	));
	
	// Default Medicine Units
	define('DEFAULT_UNITS', array(
		'Piece', 'Dozen', 'Box', 'Bottle', 'Vial', 'Strip', 'Pack', 'Sachet'
	));
	
	// Default Medicine Categories
	define('DEFAULT_CATEGORIES', array(
		'Tablets', 'Capsules', 'Syrups', 'Drops', 'Injections', 'Ointments'
	));
	
	// Current Date
	$date = date("Y-m-d");
	
	// Current DateTime
	$datetime = date("Y-m-d H:i:s");
	
	// Location
	$loc = isset($_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'])
		? 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']
		: null; // unset outside a real HTTP request (CLI/tests)
	
	// ============================================================================
	// HELPER FUNCTIONS
	// ============================================================================
	
	/**
	 * Get User's IP Address
	 */
	function getUserIP() {
		$ipaddress = '';
		if (isset($_SERVER['HTTP_CLIENT_IP']))
			$ipaddress = $_SERVER['HTTP_CLIENT_IP'];
		else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
			$ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
		else if(isset($_SERVER['HTTP_X_FORWARDED']))
			$ipaddress = $_SERVER['HTTP_X_FORWARDED'];
		else if(isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']))
			$ipaddress = $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
		else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
			$ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
		else if(isset($_SERVER['HTTP_FORWARDED']))
			$ipaddress = $_SERVER['HTTP_FORWARDED'];
		else if(isset($_SERVER['REMOTE_ADDR']))
			$ipaddress = $_SERVER['REMOTE_ADDR'];
		else
			$ipaddress = 'UNKNOWN';
		return $ipaddress;
	}
	$ip = getUserIP();
	
	/**
	 * Run Database Query and Return Results
	 */
	function runQuery($query) {
		global $conn;
		$result = $conn->query($query);
		$resultset = array();
		if ($result && $result->num_rows > 0) {
			while($row = mysqli_fetch_assoc($result)) {
				$resultset[] = $row;
			}
		}
		if(!empty($resultset))
			return $resultset;
		return array();
	}
	
	/**
	 * Get Number of Rows from Query
	 */
	function numRows($query) {
		global $conn;
		$result = $conn->query($query);
		$rowcount = mysqli_num_rows($result);
		return $rowcount;	
	}
	
	/**
	 * Format Currency (XAF - Cameroon)
	 */
	function formatCurrency($amount) {
		return number_format($amount, 2, '.', ',') . ' ' . SYSTEM_CURRENCY;
	}
	
	/**
	 * Get Current User Store
	 */
	function getCurrentStore() {
		if(isset($_SESSION['store_id'])) {
			global $conn;
			$result = $conn->query("SELECT * FROM `store` WHERE `store_id` = '{$_SESSION['store_id']}'");
			if($result && $result->num_rows > 0) {
				return mysqli_fetch_assoc($result);
			}
		}
		return false;
	}
	
	/**
	 * Check if User is Logged In
	 */
	function isLoggedIn() {
		return isset($_SESSION['store_id']) && !empty($_SESSION['store_id']);
	}
	
	/**
	 * Redirect if Not Logged In
	 */
	function requireLogin() {
		if(!isLoggedIn()) {
			header("location:login.php");
			exit();
		}
	}
	
	// ============================================================================
	// DATABASE READY
	// ============================================================================
?>

?>