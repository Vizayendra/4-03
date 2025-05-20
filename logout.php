<?php
session_start();
// end the session
session_unset();
session_destroy();

// go back to login
header("Location: login.php");
exit();
