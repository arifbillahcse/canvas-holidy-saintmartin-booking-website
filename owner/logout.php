<?php
require __DIR__ . '/_boot.php';
unset($_SESSION['owner_id']);
header('Location: login.php');
