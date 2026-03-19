<?php

$passwords = [
    'admin123',
    'manager123', 
    'staff123'
];

foreach($passwords as $password) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    echo "Password: $password\n";
    echo "Hash: $hash\n";
    echo "------------------------\n";
}
?>