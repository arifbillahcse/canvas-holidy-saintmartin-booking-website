<?php
require __DIR__ . '/_boot.php';
$_SESSION = [];
session_destroy();
header('Location: login.php');
