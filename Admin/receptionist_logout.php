<?php
session_start();
unset($_SESSION['snackzone_receptionist']);
session_destroy();
header('Location: receptionist_login.php');
exit;
