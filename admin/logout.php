<?php
require __DIR__ . '/../includes/bootstrap.php';
Auth::adminLogout();
redirect('admin/index.php');
