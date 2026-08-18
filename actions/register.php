<?php
session_start();
include('../config/db.php');

if(!isset($_POST['register'])) {
    $_SESSION['error_msg'] = "Invalid request";
    header("location:../register.php");
    exit();
}

// Get form data
$store_name = isset($_POST['store_name']) ? $conn->real_escape_string(trim($_POST['store_name'])) : '';
$owner_name = isset($_POST['owner_name']) ? $conn->real_escape_string(trim($_POST['owner_name'])) : '';
$email = isset($_POST['email']) ? $conn->real_escape_string(trim($_POST['email'])) : '';
$phone = isset($_POST['phone']) ? $conn->real_escape_string(trim($_POST['phone'])) : '';
$address = isset($_POST['address']) ? $conn->real_escape_string(trim($_POST['address'])) : '';
$username = isset($_POST['username']) ? $conn->real_escape_string(trim($_POST['username'])) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';
$confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';

$error = false;
$error_msg = '';

// Validation checks
if(empty($store_name)) {
    $error = true;
    $error_msg = "Store name is required";
}

if(empty($owner_name)) {
    $error = true;
    $error_msg = "Owner name is required";
}

if(empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = true;
    $error_msg = "Valid email address is required";
}

if(empty($phone)) {
    $error = true;
    $error_msg = "Phone number is required";
}

if(strlen($username) < 5) {
    $error = true;
    $error_msg = "Username must be at least 5 characters long";
}

if(strpos($username, ' ') !== false) {
    $error = true;
    $error_msg = "Username cannot contain spaces";
}

if(strlen($password) < 8) {
    $error = true;
    $error_msg = "Password must be at least 8 characters long";
}

if($password !== $confirm_password) {
    $error = true;
    $error_msg = "Passwords do not match";
}

// Check if username already exists
if(!$error) {
    $check_username = numRows("SELECT * FROM `store` WHERE `user_name` = '$username'");
    if($check_username > 0) {
        $error = true;
        $error_msg = "Username already taken. Please choose another one";
    }
}

// Check if email already exists
if(!$error) {
    $check_email = numRows("SELECT * FROM `store` WHERE `email` = '$email'");
    if($check_email > 0) {
        $error = true;
        $error_msg = "Email already registered. Please use another email or login if you have an account";
    }
}

if($error) {
    $_SESSION['error_msg'] = $error_msg;
    header("location:../register.php");
    exit();
}

// Insert new store
$insert_store = $conn->query("INSERT INTO `store`(
    `name`, 
    `user_name`, 
    `pass`, 
    `email`, 
    `phone`, 
    `address`, 
    `city`, 
    `country`, 
    `currency`, 
    `store_status`
) VALUES (
    '$store_name',
    '$username',
    '$password',
    '$email',
    '$phone',
    '$address',
    'Douala',
    'Cameroon',
    'XAF',
    'active'
)");

if($insert_store) {
    $store_id = $conn->insert_id;
    
    // Add default payment methods for Cameroon
    $payment_methods = array(
        array('MTN Mobile Money', 'mtn_mobile_money', 'mobile_money'),
        array('Orange Mobile Money', 'orange_mobile_money', 'mobile_money'),
        array('Mobile Money - Other', 'other_mobile_money', 'mobile_money'),
        array('Bank Transfer', 'bank_transfer', 'bank'),
        array('Card Payment', 'card_payment', 'card'),
        array('Binance Pay', 'binance_pay', 'crypto'),
        array('Hand Cash', 'hand_cash', 'cash'),
        array('Cheque', 'cheque', 'bank')
    );
    
    foreach($payment_methods as $method) {
        $conn->query("INSERT INTO `payment_method`(
            `store`,
            `method_name`,
            `method_code`,
            `category`,
            `is_active`
        ) VALUES (
            '$store_id',
            '$method[0]',
            '$method[1]',
            '$method[2]',
            1
        )");
    }
    
    // Add default categories
    $categories = array('Tablets', 'Capsules', 'Syrups', 'Drops', 'Injections', 'Ointments');
    foreach($categories as $category) {
        $conn->query("INSERT INTO `p_medicine_category`(
            `store`,
            `name`
        ) VALUES (
            '$store_id',
            '$category'
        )");
    }
    
    // Add default units
    $units = array('Piece', 'Dozen', 'Box', 'Bottle', 'Vial', 'Strip', 'Pack', 'Sachet');
    foreach($units as $unit) {
        $check_unit = numRows("SELECT * FROM `medicine_unit` WHERE `unit` = '$unit'");
        if($check_unit == 0) {
            $conn->query("INSERT INTO `medicine_unit`(`unit`) VALUES ('$unit')");
        }
    }
    
    // Add default expense categories
    $expense_cats = array('Rent', 'Utilities', 'Salaries', 'Marketing', 'Maintenance', 'Transport', 'Other');
    foreach($expense_cats as $expense_cat) {
        $conn->query("INSERT INTO `p_expense_category`(
            `store`,
            `category`
        ) VALUES (
            '$store_id',
            '$expense_cat'
        )");
    }
    
    // Registration successful - redirect to login
    $_SESSION['success_msg'] = "Registration successful! Please login with your credentials.";
    header("location:../login.php");
    exit();
    
} else {
    $_SESSION['error_msg'] = "Registration failed. Please try again later: " . $conn->error;
    header("location:../register.php");
    exit();
}
?>
