<?php
// redirect_alt.php
// Alternatif redirect menggunakan HTML meta refresh

$target_url = "https://www.dnsleaktest.com/";
?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="refresh" content="0;url=<?php echo $target_url; ?>">
    <title>Redirecting...</title>
</head>
<body>
    <p>Jika tidak otomatis redirect, <a href="<?php echo $target_url; ?>">klik di sini</a>.</p>

</body>
</html>