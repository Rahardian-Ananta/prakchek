<?php
// config_mail.php
// ⚠️ JANGAN di-commit ke GitHub! Tambahkan ke .gitignore

return [
    // Brevo SMTP Settings
    'smtp_host' => 'smtp-relay.brevo.com',
    'smtp_port' => 587,
    'smtp_secure' => 'tls',  // Gunakan 'tls' untuk port 587
    'smtp_auth' => true,
    
    // Credentials dari Brevo
    'mail_from' => 'velzathor@gmail.com',  // Gunakan email terverifikasi di Brevo Anda
    'mail_from_name' => 'Prakchek LMS (Velzhator)',
    
    // ⬇️ Copy PERSIS dari dashboard Brevo ⬇️
    'smtp_username' => 'abc561001@smtp-brevo.com',           // ← Login
    'smtp_password' => 'YOUR_SMTP_PASSWORD',  // ← SMTP Key asli
    
    // Fallback ke log file jika SMTP gagal
    'use_fallback_log' => true,
];
