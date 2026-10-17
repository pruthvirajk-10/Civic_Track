<?php
/**
 * CivicTrack — Safe Logout Handler
 */

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

logoutUser();

// Start clean session for flash message
session_start();
setFlash('info', 'You have been safely signed out. Thank you for using CivicTrack.');

header('Location: ' . getBaseUrl() . 'login.php');
exit;
