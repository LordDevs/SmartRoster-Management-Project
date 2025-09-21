<?php
// populate_employees.php – script to seed the database with 30 sample employees
require_once 'config.php';
requireAdmin();

// Name arrays for random pairing
$firstNames = ['Aidan','Bridget','Ciaran','Deirdre','Eoin','Fiona','Grainne','Hugh','Ian','Jillian',
    'Kevin','Laura','Maeve','Niall','Orla','Padraig','Roisin','Seamus','Tara','Una',
    'Vaughan','Siobhan','Cathal','Darragh','Elaine','Fergal','Grainne','Isolde','Liam','Muireann'];
$lastNames  = ['O\'Connor','Murphy','Kelly','O\'Brien','Smith','Doyle','Byrne','Ryan','Walsh','O\'Sullivan',
    'Kennedy','Lynch','McCarthy','Maher','Nolan','Power','Quinn','Reid','Sweeney','Ward',
    'Gallagher','Doran','Keane','Flynn','Moran','Brady','Hayes','Kavanagh','Moore','Clarke'];

// Fetch existing stores or create a default one if necessary
$storeIds = $pdo->query('SELECT id FROM stores')->fetchAll(PDO::FETCH_COLUMN);
if (!$storeIds) {
    $pdo->exec("INSERT INTO stores (name, location) VALUES ('Default Store','Default Location')");
    $storeIds = [$pdo->lastInsertId()];
}

// Prepare insert statement
$insert = $pdo->prepare('INSERT INTO employees (name, phone, email, store_id, ppsn, irp, hourly_rate) VALUES (?, ?, ?, ?, ?, ?, ?)');
for ($i = 0; $i < 30; $i++) {
    $first = $firstNames[array_rand($firstNames)];
    $last  = $lastNames[array_rand($lastNames)];
    $name  = "$first $last";
    $phone = '08' . rand(1,9) . '-' . rand(100,999) . '-' . rand(1000,9999);
    $email = strtolower(str_replace("'", '', $first)) . '.' . strtolower(str_replace("'", '', $last)) . '@example.com';
    $store = $storeIds[array_rand($storeIds)];
    $ppsn  = rand(1000000,9999999) . chr(rand(ord('A'),ord('Z')));
    $irp   = 'IRP' . rand(1000000,9999999);
    $rate  = rand(1200,2000) / 100.0;
    $insert->execute([$name, $phone, $email, $store, $ppsn, $irp, $rate]);
}

echo '30 sample employees were added.';
