<?php
  session_start();
  
  // Redirect if already logged in
  if(isset($_SESSION['store_id'])) {
    header("location:index.php");
    exit();
  }
  
  include('config/db.php');
  
  // Check if there are any existing stores
  $existingStores = numRows("SELECT * FROM `store`");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register | Pharmacy POS</title>

  <!-- Google Font: Poppins -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
  <!-- icheck bootstrap -->
  <link rel="stylesheet" href="plugins/icheck-bootstrap/icheck-bootstrap.min.css">
  <!-- Theme style -->
  <link rel="stylesheet" href="dist/css/adminlte.min.css">
  
  <style>
    * {
      font-family: 'Poppins', sans-serif;
    }
    
    body {
      background: linear-gradient(135deg, #17A2B8 0%, #138496 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .register-box {
      width: 100%;
      max-width: 500px;
    }
    
    .register-logo {
      text-align: center;
      margin-bottom: 30px;
      color: white;
    }
    
    .register-logo h1 {
      font-weight: 700;
      font-size: 32px;
      margin: 0;
    }
    
    .register-logo p {
      font-size: 14px;
      opacity: 0.9;
    }
    
    .register-card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.2);
      padding: 0;
      border: none;
    }
    
    .register-card .card-header {
      background: linear-gradient(135deg, #17A2B8 0%, #138496 100%);
      color: white;
      border-radius: 12px 12px 0 0;
      padding: 20px;
      border: none;
    }
    
    .register-card .card-header h3 {
      margin: 0;
      font-weight: 600;
      font-size: 20px;
    }
    
    .register-card .card-body {
      padding: 30px;
    }
    
    .form-group label {
      font-weight: 500;
      color: #343A40;
      margin-bottom: 8px;
      font-size: 14px;
    }
    
    .form-control {
      border: 1px solid #DEE2E6;
      border-radius: 6px;
      padding: 10px 15px;
      font-size: 14px;
      transition: all 0.3s ease;
    }
    
    .form-control:focus {
      border-color: #17A2B8;
      box-shadow: 0 0 0 0.2rem rgba(23, 162, 184, 0.25);
    }
    
    .input-group-append .input-group-text {
      background: transparent;
      border: 1px solid #DEE2E6;
      border-left: none;
    }
    
    .form-control:focus ~ .input-group-append .input-group-text {
      border-color: #17A2B8;
    }
    
    .btn-register {
      background: linear-gradient(135deg, #17A2B8 0%, #138496 100%);
      border: none;
      color: white;
      font-weight: 600;
      padding: 10px 20px;
      border-radius: 6px;
      width: 100%;
      margin-top: 20px;
      transition: all 0.3s ease;
    }
    
    .btn-register:hover {
      background: linear-gradient(135deg, #138496 0%, #0f6276 100%);
      color: white;
      transform: translateY(-2px);
      box-shadow: 0 5px 20px rgba(23, 162, 184, 0.4);
    }
    
    .login-link {
      text-align: center;
      margin-top: 20px;
      padding-top: 20px;
      border-top: 1px solid #DEE2E6;
    }
    
    .login-link a {
      color: #17A2B8;
      text-decoration: none;
      font-weight: 500;
    }
    
    .login-link a:hover {
      text-decoration: underline;
    }
    
    .alert {
      border-radius: 6px;
      border: none;
      margin-bottom: 20px;
    }
  </style>
</head>
<body class="hold-transition register-page">
  <div class="register-box">
    <div class="register-logo">
      <h1><i class="fas fa-pills"></i> Pharmacy POS</h1>
      <p>Create Your Store Account</p>
    </div>

    <div class="card register-card">
      <div class="card-header">
        <h3>Register New Store</h3>
      </div>
      
      <div class="card-body">
        <?php 
        if(isset($_SESSION['error_msg'])) {
          echo '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' . $_SESSION['error_msg'] . '</div>';
          unset($_SESSION['error_msg']);
        }
        ?>
        
        <form action="actions/register.php" method="post">
          <!-- Store Name -->
          <div class="form-group">
            <label for="store_name">Store Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="store_name" name="store_name" placeholder="Enter your pharmacy name" required>
          </div>

          <!-- Owner Name -->
          <div class="form-group">
            <label for="owner_name">Owner Full Name <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="owner_name" name="owner_name" placeholder="Enter your full name" required>
          </div>

          <!-- Email -->
          <div class="form-group">
            <label for="email">Email Address <span class="text-danger">*</span></label>
            <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" required>
          </div>

          <!-- Phone -->
          <div class="form-group">
            <label for="phone">Phone Number <span class="text-danger">*</span></label>
            <input type="tel" class="form-control" id="phone" name="phone" placeholder="Enter your phone number" required>
          </div>

          <!-- Address -->
          <div class="form-group">
            <label for="address">Address</label>
            <textarea class="form-control" id="address" name="address" rows="2" placeholder="Enter store address"></textarea>
          </div>

          <!-- Username -->
          <div class="form-group">
            <label for="username">Username <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="username" name="username" placeholder="Choose a unique username" required>
            <small class="form-text text-muted">Minimum 5 characters, no spaces</small>
          </div>

          <!-- Password -->
          <div class="form-group">
            <label for="password">Password <span class="text-danger">*</span></label>
            <div class="input-group">
              <input type="password" class="form-control" id="password" name="password" placeholder="Create a strong password" required>
              <div class="input-group-append">
                <div class="input-group-text">
                  <span class="fas fa-lock"></span>
                </div>
              </div>
            </div>
            <small class="form-text text-muted">Minimum 8 characters with numbers and symbols</small>
          </div>

          <!-- Confirm Password -->
          <div class="form-group">
            <label for="confirm_password">Confirm Password <span class="text-danger">*</span></label>
            <div class="input-group">
              <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
              <div class="input-group-append">
                <div class="input-group-text">
                  <span class="fas fa-lock"></span>
                </div>
              </div>
            </div>
          </div>

          <!-- Terms Checkbox -->
          <div class="form-group">
            <div class="icheck-primary">
              <input type="checkbox" id="agree" name="agree" required>
              <label for="agree">
                I agree to the <a href="#" style="color: #17A2B8;">Terms and Conditions</a>
              </label>
            </div>
          </div>

          <!-- Register Button -->
          <button type="submit" class="btn btn-register" name="register">
            <i class="fas fa-user-plus"></i> Create Account
          </button>
        </form>

        <!-- Login Link -->
        <div class="login-link">
          Already have an account? <a href="login.php">Sign in here</a>
        </div>
      </div>
    </div>
  </div>

  <!-- jQuery -->
  <script src="plugins/jquery/jquery.min.js"></script>
  <!-- Bootstrap 4 -->
  <script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
  <!-- AdminLTE App -->
  <script src="dist/js/adminlte.min.js"></script>

  <script>
    // Form validation
    document.querySelector('form').addEventListener('submit', function(e) {
      const username = document.getElementById('username').value.trim();
      const password = document.getElementById('password').value;
      const confirmPassword = document.getElementById('confirm_password').value;

      // Validate username
      if(username.length < 5) {
        e.preventDefault();
        alert('Username must be at least 5 characters long');
        return false;
      }

      if(username.includes(' ')) {
        e.preventDefault();
        alert('Username cannot contain spaces');
        return false;
      }

      // Validate password
      if(password.length < 8) {
        e.preventDefault();
        alert('Password must be at least 8 characters long');
        return false;
      }

      // Check password match
      if(password !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match');
        return false;
      }
    });
  </script>
</body>
</html>
