<?php

use League\Csv\Reader;
use League\Csv\Statement;

require __DIR__ . '/bootstrap.php';

// Fetch all categories to see what we need to create
$categories = $woocommerce->get('products/categories');
$dataFromNetPS = [];
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
$page = 1;
$products = [];
while ($page !== false) {
    $products = array_merge($products, $woocommerce->get('products', array_merge([
        'category' => $uncategorized_id,
        'per_page' => 100,
    ], ['page' => $page,])));
    $headers = $woocommerce->http->getResponse()->getHeaders();

    if (isset($headers["x-wp-totalpages"])) {
        $totalPages = $headers["x-wp-totalpages"];
    } else {
        $totalPages = 1;
    }

    if ($page < $totalPages) {
        $page++;
    } else {
        $page = false;
    }
}


// Lookup product SKU in Google Sheet and find NetPS number
$response = $service->spreadsheets_values->get($_ENV['GOOGLE_SHEET'], $_ENV['GOOGLE_RANGE']);
$values = $response->getValues();

// Load Google Sheets
$googleSheetNetPs = [];
if (empty($values)) {
    throw new Exception('No data in your spreadsheet is found to do a data synchronization.');
}
array_walk($values, function ($value) use (&$googleSheetNetPs) {
    if (!empty($value[$_ENV['NSID_ROW']])) {
        $googleSheetNetPs[$value[$_ENV['SKU_ROW']]] = $value[$_ENV['NSID_ROW']];
    }
});

// Find data that matches Google Sheets and WooCommerced Products
$needDataFromNetPS = [];
foreach ($products as $product) {
    if (!empty($product->sku)) {
        if (isset($googleSheetNetPs[$product->sku])) {
            $needDataFromNetPS[$product->id] = $googleSheetNetPs[$product->sku];
        }
    }
}

// Get NetPS data
//load the CSV document from a stream
$categoriesToCheck = '';
$reader = Reader::createFromPath(__DIR__ . '/netps.csv', 'r');
$reader->setHeaderOffset(0);
$records = $reader->getRecords();

foreach ($records as $offset => $value) {
    if (in_array($value['NetPS_Plant_ID'], $needDataFromNetPS)) {
        $wooKey = array_search($value['NetPS_Plant_ID'], $needDataFromNetPS);

        /**
         * Add the NetPS data you want in this data structure to be used by WooCommerce
         */
        $html = '';
        $html .= (!empty($value['Edible_Qualities_Para_1'])) ? '<h2>Edible Qualities</h2>' . $value["Edible_Qualities_Para_1"] : '';
        $html .= (!empty($value['Edible_Qualities_Para_2'])) ? '<br/><br/>' . $value["Edible_Qualities_Para_2"] : '';
        if (!empty($value['Edible_Qualities_Para_2A_SCSV'])) {
            $list = explode(';', $value['Edible_Qualities_Para_2A_SCSV']);
            $html .= '<ul>';
            foreach ($list as $item) {
                $html .= '<li>' . $item . '</li>';
            }
            $html .= '</ul>';
        }

        $html .= (!empty($value['Features_And_Attributes_Para1'])) ? '<h2>Features & Attributes</h2>' . $value['Features_And_Attributes_Para1'] : '';
        $html .= (!empty($value['Features_And_Attributes_Para2'])) ? '<br/><br/>' . $value['Features_And_Attributes_Para2'] : '';
        if (!empty($value['Features_And_Attributes_Para2A_SCSV'])) {
            $list = explode(';', $value['Features_And_Attributes_Para2A_SCSV']);
            $html .= '<ul>';
            foreach ($list as $item) {
                $html .= '<li>' . $item . '</li>';
            }
            $html .= '</ul>';
        }
        $html .= (!empty($value['Features_And_Attributes_Para3'])) ? '<br/><br/>' . $value['Features_And_Attributes_Para3'] : '';
        if (!empty($value['Features_And_Attributes_Para3A_SCSV'])) {
            $list = explode(';', $value['Features_And_Attributes_Para3A_SCSV']);
            $html .= '<ul>';
            foreach ($list as $item) {
                $html .= '<li>' . $item . '</li>';
            }
            $html .= '</ul>';
        }

        $html .= (!empty($value['Ornamental_Features_Para1'])) ? '<h2>Ornamental Features</h2>' . $value["Ornamental_Features_Para1"] : '';
        $html .= (!empty($value['Ornamental_Features_Para2'])) ? '<br/><br/>' . $value["Ornamental_Features_Para2"] : '';


        $html .= (!empty($value["Landscape_Attributes_Para1"])) ? '<h2>Landscape Attributes</h2>' . $value["Landscape_Attributes_Para1"] : '';
        $html .= (!empty($value['Landscape_Attributes_Para2'])) ? '<br/><br/>' . $value["Landscape_Attributes_Para2"] : '';
        $html .= (!empty($value['Landscape_Attributes_Para3'])) ? '<br/><br/>' . $value['Landscape_Attributes_Para3'] : '';
        if (!empty($value['Landscape_Attributes_Para3A_SCSV'])) {
            $list = explode(';', $value['Landscape_Attributes_Para3A_SCSV']);
            $html .= '<ul>';
            foreach ($list as $item) {
                $html .= '<li>' . $item . '</li>';
            }
            $html .= '</ul>';
        }

        $html .= (!empty($value["Planting_And_Growing_Para1"])) ? '<h2>Planting & Growing</h2>' . $value["Planting_And_Growing_Para1"] : '';
        $html .= (!empty($value['Planting_And_Growing_Para2'])) ? '<br>' . $value["Planting_And_Growing_Para2"] : '';
        $html .= (!empty($value['Planting_And_Growing_Para3'])) ? '<br>' . $value['Planting_And_Growing_Para3'] : '';
        $html .= (!empty($value['Planting_And_Growing_Para4'])) ? '<br>' . $value['Planting_And_Growing_Para4'] : '';

        if ((!empty($value["Photo_1_Full_Size_URL"]))) {
            $photos[] = ['src' => $value["Photo_1_Full_Size_URL"]];
        }

        if ((!empty($value["Photo_2_Full_Size_URL"]))) {
            $photos[] = ['src' => $value["Photo_2_Full_Size_URL"]];
        }

        if ((!empty($value["Photo_3_Full_Size_URL"]))) {
            $photos[] = ['src' => $value["Photo_3_Full_Size_URL"]];
        }

        // Attributes
        if (!empty($value['Growth_Rate'])) {
            $attributes[] = [
                'name' => 'Growth Rate',
                'visible' => true,
                'options' => [$value['Growth_Rate']],
            ];
        }

        if (!empty($value['Hardiness_Zone_AB'])) {
            $attributes[] = [
                'name' => 'Hardiness Zone',
                'visible' => true,
                'options' => [$value['Hardiness_Zone_AB']],
            ];
        }

        if (!empty($value['Height_Descriptor'])) {
            $attributes[] = [
                'name' => 'Height',
                'visible' => true,
                'options' => [$value['Height_Descriptor']],
            ];
        }

        if (!empty($value['Spread_Descriptor'])) {
            $attributes[] = [
                'name' => 'Spread',
                'visible' => true,
                'options' => [$value['Spread_Descriptor']],
            ];
        }

        if (!empty($value['Moisture_Descriptor'])) {
            $attributes[] = [
                'name' => 'Moisture',
                'visible' => true,
                'options' => [$value['Moisture_Descriptor']],
            ];
        }

        if (!empty($value['Soil_pH_Preference'])) {
            $attributes[] = [
                'name' => 'Soil pH',
                'visible' => true,
                'options' => [$value['Soil_pH_Preference']],
            ];
        }

        if (!empty($value['Soil_Type_Preference'])) {
            $attributes[] = [
                'name' => 'Soil Type',
                'visible' => true,
                'options' => [$value['Soil_Type_Preference']],
            ];
        }
        if (!empty($value['Sunlight_Descriptor'])) {
            $attributes[] = [
                'name' => 'Sunlight',
                'visible' => true,
                'options' => [$value['Sunlight_Descriptor']],
            ];
        }

        // Product Tags
        if (!empty($value['Landscape_Application_SCSV'])) {
            $names = explode(';', $value['Landscape_Application_SCSV']);
            foreach ($names as $name) {
                if (!empty($name)) {
                    $tags[] = ['name' => $name];
                }
            }
        }

        // Product wildlife tag
        if (!empty($value['Wildlife_Attraction_SCSV'])) {
            $names = explode(';', $value['Wildlife_Attraction_SCSV']);
            $tags[] = ['name' => 'Attracts wildlife'];
            foreach ($names as $name) {
                if (!empty($name)) {
                    $tags[] = ['name' => ucfirst($name)];
                }
            }
        }


        $dataFromNetPS[$wooKey] = [
            'short_description' => $value["Description"],
            'description' => $html,
            'attributes' => $attributes,
            'tags' => $tags,
            'images' => $photos,
            'categories' => $value['Category_List_SCSV']
        ];

        $categoriesToCheck .= ';' . $value['Category_List_SCSV'];
    }
    $netPS[intval($value['NetPS_Plant_ID'])] = $offset;
}


// Create hierarchy of categories in woocommerce for products that are pulled down
$categoriesToCheck = explode(';', $categoriesToCheck);
$uniqueCategoriesToCheck = array_unique($categoriesToCheck);

foreach ($uniqueCategoriesToCheck as $key => $value) {
    if (empty($value)) {
        unset($uniqueCategoriesToCheck[$key]);
        continue;
    }
    if (in_array(strtolower($value), $categoryNames)) {
        unset($uniqueCategoriesToCheck[$key]);
    }
};

// create in WooCommerce
foreach ($uniqueCategoriesToCheck as $value) {
    try {
        $categories[] = $woocommerce->post('products/categories', ['name' => ucwords(trim(strtolower($value)))]);
    } catch (Exception $e) {

    }
}

// Update WooCommerce Product
foreach ($dataFromNetPS as $wooKey => $data) {
    $beforeProductInsert = explode(';', $data['categories']);
    $data['categories'] = null;
    foreach ($beforeProductInsert as $category) {
        foreach ($categories as $checking) {
            if (str_replace(' ', '-', trim(strtolower($category))) == $checking->slug) {
                $cid = new stdClass();
                $cid->id = $checking->id;
                $cid->name = $checking->name;
                $cid->slug = $checking->slug;

                $data['categories'][] = $cid;
            }
        }
    }

    $response = $woocommerce->put('products/' . $wooKey, $data);

    echo 'Product updated [' . $response->id . '] ' . $response->name . PHP_EOL;
}
// Create category if needed