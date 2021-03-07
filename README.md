#WooCommerce and NetPS Plant Finder Sync
This will synchronize WooCommerce data with NetPS Plant Finder data. The script
requires a few things to operate and is intended to run on initial item setup.
It can run multiple times though because it only looks for uncategorized
products in WooCommerce.

###Prerequisites

* PHP 7.4 or higher
* Composer
* WooCommerce API
* Google Sheets API for accessing your lookup table
  * Follow [Google's Quickstart for credentials.json](https://developers.google.com/sheets/api/quickstart/php)
* Net PS CSV downloaded and placed in the root folder

I'm not going to explain installs of PHP, Composer, or Net PS download. This 
should be easily found online if you are this far.

###Setup
1. You will need to copy the `.env.example` file to `.env` and fill out your 
information as required.
1. Run `composer install` to get all required vendor scripts
1. You should have Google's `credentials.json` placed in the root folder
1. Place `netps.csv` data in the root folder
1. Run `php index.php` and follow any prompts for Google or keys

###What happens?
This script performs the following in order:

1. Find all categories in WooCommerce
1. Find the category that matches `uncategorized` in WooCommerce
1. Find all products that are published in the `uncategorized` category
1. Loop through products and lookup SKU in Google Sheet to find NetPS #
    1. Stores NetPS ID and Product ID
1. Get NetPS data
1. Fetch NetPS category, description, photo that matches WooCommerce Product
1. Create WooCommerce category if needed.
1. Update product and categories
1. Generate report of what changed


###Credits
Chris Smith - @cgsmith105