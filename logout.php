<?php
session_start();
include "config.php";

session_destroy();
header("Location: login.php");
exit();
?>