<?php
if (session_id() == '') {
    session_start();
}


if (isset($_SESSION['voter_id'])) {
    $_SESSION = array();

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();

    header('Location: login.php?success=logout');
    exit();
}

$error_message = '';
$success_message = '';

if (isset($_GET['error'])) {
    if ($_GET['error'] == 'empty') {
        $error_message = 'Please enter your Voter ID and password.';
    } elseif ($_GET['error'] == 'invalid') {
        $error_message = 'Invalid Voter ID or password.';
    } elseif ($_GET['error'] == 'inactive') {
        $error_message = 'Your account is inactive. Please contact the administrator.';
    } elseif ($_GET['error'] == 'not_registered') {
        $error_message = 'Your voter profile is not yet complete or registered.';
    } elseif ($_GET['error'] == 'login_required') {
        $error_message = 'Please log in first to access your account.';
    } elseif ($_GET['error'] == 'session_expired') {
        $error_message = 'Your session expired. Please log in again.';
    } elseif ($_GET['error'] == 'server_error') {
        $error_message = 'A server error occurred. Please try again.';
    } else {
        $error_message = 'Login failed. Please try again.';
    }
}

if (isset($_GET['success'])) {
    if ($_GET['success'] == 'logout') {
        $success_message = 'You have been logged out successfully.';
    } elseif ($_GET['success'] == 'registered') {
        $success_message = 'Registration successful. You may now log in.';
    }
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login - iVotePH</title>
  <link rel="icon" type="image/png" href="logo.png">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="style.css">

  <style>
    img.brandLogo {
      width: 126px !important;
      max-width: 126px !important;
      height: auto !important;
      max-height: 46px !important;
      object-fit: contain !important;
      display: block !important;
    }

    .userNavList,
    .sidebarMenuNav {
      list-style: none !important;
      margin: 0 !important;
      padding: 0 !important;
    }

    .userNavList {
      display: flex !important;
      gap: 8px !important;
      overflow-x: auto !important;
    }

    .userNavList li,
    .sidebarMenuNav li {
      list-style: none !important;
    }

    .userTopbarInner {
      display: flex !important;
      align-items: center !important;
      gap: 14px !important;
    }

    .userSidebar,
    #sidebar {
      transform: translateX(-110%);
    }

    .userSidebar.open,
    #sidebar.open {
      transform: translateX(0);
    }

    .loginAlert {
      border-radius: 16px;
      font-size: 13px;
      font-weight: 800;
      margin-bottom: 16px;
      padding: 13px 14px;
    }

    .passwordWrapper {
      position: relative;
    }

    .passwordToggleBtn {
      position: absolute;
      top: 50%;
      right: 12px;
      transform: translateY(-50%);
      border: none;
      background: transparent;
      color: #667085;
      cursor: pointer;
      padding: 4px;
    }

    .passwordWrapper .formInput {
      padding-right: 44px;
    }
  </style>
</head>

<body class="authPage">
  <div class="loginContainer">
    <div class="loginCard card">
      <div class="authLogo">
        <img src="FINALS 2.png" alt="iVotePH" class="authLogoImg">
      </div>

      <h1>Login</h1>
      <p class="loginSubtitle">Access your voting account securely</p>

      <?php if ($error_message != '') { ?>
        <div class="alert alert-danger loginAlert">
          <i class="fa-solid fa-circle-exclamation me-2"></i>
          <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
        </div>
      <?php } ?>

      <?php if ($success_message != '') { ?>
        <div class="alert alert-success loginAlert">
          <i class="fa-solid fa-circle-check me-2"></i>
          <?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?>
        </div>
      <?php } ?>

      <form id="loginForm" action="login_process.php" method="POST">
        <div class="formGroup">
          <label for="voter_id" class="formLabel">Voter ID</label>
          <input
            id="voter_id"
            name="voter_id"
            type="text"
            class="formInput"
            placeholder="Enter your Voter ID"
            autocomplete="username"
            required>
        </div>

        <div class="formGroup">
          <label for="password" class="formLabel">Password</label>

          <div class="passwordWrapper">
            <input
              id="password"
              name="password"
              type="password"
              class="formInput"
              placeholder="Enter your password"
              autocomplete="current-password"
              required>

            <button type="button" class="passwordToggleBtn" id="togglePassword" aria-label="Show password">
              <i class="fa-solid fa-eye"></i>
            </button>
          </div>
        </div>

        <div class="forgotPasswordLink text-end mb-3">
          <a href="#" onclick="return false;">Forgot Password?</a>
        </div>

        <button type="submit" class="loginBtn btn w-100 py-3">
          <i class="fa-solid fa-right-to-bracket me-2"></i>
          Log In
        </button>
      </form>

      <div class="registerLink text-center mt-3">
        Don't have an account?
        <a href="register.php">Register here</a>
      </div>
    </div>
  </div>

  <script>
    document.getElementById('togglePassword').addEventListener('click', function () {
      var passwordInput = document.getElementById('password');
      var icon = this.querySelector('i');

      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
      } else {
        passwordInput.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
      }
    });
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>