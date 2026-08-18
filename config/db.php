<?php
	// ============================================================================
	// PHARMACY POS - DATABASE & SYSTEM CONFIGURATION
	// Cameroon Edition
	// ============================================================================
	
	// Set Timezone to Cameroon (Africa/Douala)
	date_default_timezone_set('Africa/Douala');
	
	// Database Configuration
	$host = "localhost";
	$name = "root";
	$pass = "";
	$db = "pharmacy";
	
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
	define('SYSTEM_CURRENCY', 'XAF');
	define('SYSTEM_CURRENCY_SYMBOL', 'FCFA');
	
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
	$loc = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	
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