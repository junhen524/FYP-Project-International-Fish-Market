<?php
// Login and fetch stock page to check image URLs
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_COOKIEJAR => 'C:\xampp\htdocs\FisherySystem\cookie.txt',
    CURLOPT_COOKIEFILE => 'C:\xampp\htdocs\FisherySystem\cookie.txt',
    CURLOPT_FOLLOWLOCATION => true,
]);

// 1. Login page - get CSRF
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/login/');
$res = curl_exec($ch);
preg_match('/name="csrf_token"[^>]*value="([^"]+)"/', $res, $m);
$token = $m[1] ?? '';

// 2. Login
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'username' => 'superadmin',
    'password' => 'sadmin1234',
    'csrf_token' => $token,
]));
$res = curl_exec($ch);

// 3. Stock list
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/dashboard/stock/');
$res = curl_exec($ch);
preg_match_all('/<img[^>]*src="([^"]+)"/', $res, $matches);
foreach ($matches[1] as $i => $url) {
    echo ($i+1) . ": $url\n";
}
curl_close($ch);
