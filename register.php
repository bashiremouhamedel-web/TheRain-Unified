<?php

// Keep the historical root URL as a compatibility wrapper for Unified auth.
header('Location: auth/register.php');
exit();
