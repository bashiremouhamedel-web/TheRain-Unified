<?php

// The public root auth URL enters the Unified authentication experience.
// Legacy Pharmacy action files remain available for standalone installs.
header('Location: auth/login.php');
exit();
