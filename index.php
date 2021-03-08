<?php

use League\Csv\Reader;
use League\Csv\Statement;

require __DIR__ . '/bootstrap.php';

// Fetch all categories to see what we need to create
$categories = $woocommerce->get('products/categories');

// Find uncategorized category ID
$uncategorized_id = 0;
$categoryNames = [];
array_walk($categories, function ($value) use (&$uncategorized_id, &$categoryNames) {
    if ($value->slug === 'uncategorized') {
        $uncategorized_id = $value->id;
    }
    $categoryNames[] = strtolower($value->name);
});

// Find all products that are uncategorized
$products = $woocommerce->get('products', ['categories' => [$uncategorized_id]]);

// Lookup product SKU in Google Sheet and find NetPS number
$response = $service->spreadsheets_values->get($_ENV['GOOGLE_SHEET'], $_ENV['GOOGLE_RANGE']);
$values = $response->getValues();

$googleSheetNetPs = [];
if (empty($values)) {
    throw new Exception('No data in your spreadsheet is found to do a data synchronization.');
}
array_walk($values, function ($value) use (&$googleSheetNetPs) {
    if (!empty($value[$_ENV['NSID_ROW']])) {
        $googleSheetNetPs[$value[$_ENV['SKU_ROW']]] = $value[$_ENV['NSID_ROW']];
    }
});

// Get NetPS data

//load the CSV document from a stream
$reader = Reader::createFromPath(__DIR__ . '/netps.csv', 'r');
$reader->setHeaderOffset(0);
$records = $reader->getRecords();
foreach ($records as $offset => $value) {
    $netPS[intval($value['NetPS_Plant_ID'])] = $offset;
}

$categoriesToCheck = '';
foreach ($googleSheetNetPs as $sku => $netPSid) {
    if (isset($netPS[$netPSid])) {
        $googleSheetNetPs[$sku] = $reader->fetchOne($netPS[$netPSid]);
        $categoriesToCheck .= ';' . $googleSheetNetPs[$sku]['Category_List_SCSV'];
    }else {
        echo 'Cannot find (dropping): ' . $sku . PHP_EOL;
    }
}

// Create hierarchy of categories in woocommerce for products that are pulled down
$categoriesToCheck = explode(';', $categoriesToCheck);
$uniqueCategoriesToCheck = array_unique($categoriesToCheck);

foreach ($uniqueCategoriesToCheck as $key => $value) {
    if (in_array(strtolower($value), $categoryNames)) {
        unset($uniqueCategoriesToCheck[$key]);
    }
};

// create in WooCommerce
foreach ($uniqueCategoriesToCheck as $value) {
    $categories[] = $woocommerce->post('products/categories', ['name' => ucwords(trim(strtolower($value)))]);
}

// Update WooCommerce Product

// Create category if needed