<?php

// Sample PHP code for web programming practicum
function connectToDatabase() {
    $pdo = new PDO('mysql:host=localhost;dbname=prakchek', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}

function getStudents($classId) {
    $pdo = connectToDatabase();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE role = "student"');
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
