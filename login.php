<?php
  session_start();
  
  // Redirect if already logged in
  if(isset($_SESSION['store_id'])) {
    header("location:index.php");
    exit();
  }
  
  include('config/db.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Log In | Pharmacy</title>

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
    }
    
    .login-box {
      width: 100%;
      max-width: 400px;
    }
    
    .login-logo {
      text-align: center;
      color: white;
      margin-bottom: 20px;
    }
    
    .login-logo a {
      color: white;
      text-decoration: none;
      font-weight: 700;
      font-size: 28px;
    }
    
    .card {
      border: none;
      border-radius: 12px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    }
    
    .login-card-body {
      padding: 30px;
    }
    
    .form-control {
      border-radius: 6px;
      border: 1px solid #DEE2E6;
      padding: 10px 15px;
    }
    
    .form-control:focus {
      border-color: #17A2B8;
      box-shadow: 0 0 0 0.2rem rgba(23, 162, 184, 0.25);
    }
    
    .input-group-text {
      background: transparent;
      border: 1px solid #DEE2E6;
      border-left: none;
      color: #6C757D;
    }
    
    .btn-primary {
      background: linear-gradient(135deg, #17A2B8 0%, #138496 100%);
      border: none;
      border-radius: 6px;
      font-weight: 600;
    }
    
    .btn-primary:hover {
      background: linear-gradient(135deg, #138496 0%, #0f6276 100%);
    }
    
    .login-box-msg {
      color: #6C757D;
      font-weight: 500;
    }
  </style>
</head>
<body class="hold-transition login-page">
<div class="login-box">
  <div class="login-logo">
    <a href="#"><b>Pharmacy POS</b></a>
  </div>
  <!-- /.login-logo -->
  <div class="card">
    <div class="card-body login-card-body">
      <?php 
      if(isset($_SESSION['success_msg'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
          <i class="fas fa-check-circle"></i> ' . $_SESSION['success_msg'] . '
          <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>';
        unset($_SESSION['success_msg']);
      }
      if(isset($_SESSION['e-msg'])) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
          <i class="fas fa-exclamation-circle"></i> ' . $_SESSION['e-msg'] . '
          <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>';
        unset($_SESSION['e-msg']);
      }
      ?>
      <p class="login-box-msg">Sign in to start your session</p>

      <form action="actions/login.php" method="post">
        <div class="input-group mb-3">
          <input type="text" class="form-control" placeholder="User ID" name="userid" value="<?php if(isset($_GET['adminlog'])){ echo base64_decode($_GET['user']); }?>" required>
          <div class="input-group-append">
            <div class="input-group-text">
              <span class="fas fa-envelope"></span>
            </div>
          </div>
        </div>
        <div class="input-group mb-3">
          <input type="password" class="form-control" placeholder="Password" name="pwd" value="<?php if(isset($_GET['adminlog'])){ echo base64_decode($_GET['pass']); }?>" required>
          <div class="input-group-append">
            <div class="input-group-text">
              <span class="fas fa-lock"></span>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-8">
            <div class="icheck-primary">
              <input type="checkbox" id="remember">
              <label for="remember">
                Remember Me
              </label>
            </div>
          </div>
          <!-- /.col -->
          <div class="col-4">
            <button type="submit" class="btn btn-primary btn-block" name="btnLogin">Sign In</button>
          </div>
          <!-- /.col -->
        </div>
      </form>

      <div class="social-auth-links text-center mb-3">
        <p>- OR -</p>
        <a href="#" class="btn btn-block btn-primary">
          <i class="fab fa-facebook mr-2"></i> Sign in using Facebook
        </a>
        <a href="#" class="btn btn-block btn-danger">
          <i class="fab fa-google-plus mr-2"></i> Sign in using Google+
        </a>
      </div>
      <!-- /.social-auth-links -->

      <p class="mb-1">
        <a href="#">I forgot my password</a>
      </p>
      <p class="mb-0">
        <a href="register.php" class="text-center">Create a new account</a>
      </p>
    </div>
    <!-- /.login-card-body -->
  </div>
</div>

<?php include("part/alert.php");?>
<!-- jQuery -->
<script src="plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="dist/js/adminlte.min.js"></script>
</body>
</html>
