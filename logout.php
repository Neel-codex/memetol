<?php
require __DIR__ . '/includes/bootstrap.php';
Auth::logout();
redirect('login.php');
