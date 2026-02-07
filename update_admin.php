<?php
$pdo = new PDO('mysql:host=localhost;dbname=jm;charset=utf8mb4', 'jm', 'ck123456@');
$hash = password_hash('admin', PASSWORD_DEFAULT);
$stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = 1');
$stmt->execute([$hash]);
echo '密码已更新，hash: ' . $hash . PHP_EOL;
?>
