<?php

$password = "Kwadwo2023kn";

$hash = password_hash($password, PASSWORD_DEFAULT);

echo "<h3>Password:</h3>";
echo $password;

echo "<h3>Generated Hash:</h3>";
echo $hash;