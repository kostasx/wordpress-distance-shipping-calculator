(Further development of this plugin has been moved [here](https://github.com/PlethoraLabs/wordpress-shipping-distance-calculator))

# wordpress-distance-shipping-calculator
Calculate shipping costs based on Zip codes of origin and destination

# Prerequisites
First, you'll need to get an API key from Google for the Geolocation and Distance Matrix APIs.

# Installation
Create a directory named `wordpress-distance-shipping-calculator` inside your `wp-content/plugins/` dir and upload the contents of this repository there.

# Configuration
* Paste your API key into the line `define('GOOGLE_API_KEY','<YOUR-API-KEY>');`
* Update the `$cost_table` array according to the kilometer distances, e.g.
```
  $cost_table = array(
    "2"       => 0,   // 0~2 km
    "10"      => 10,  // 2~10 km
    "20"      => 15,  // 10~20 km
    "50"      => 25   // 10~50 km
  );

```
For distances 0 to 2 kilometers no cost is added. From 2 to 10 kilometers, 10 (whatever the current currency is) is added,
 for distances from 10 to 20, 15 is added, and so forth.
 
 # Note: The plugin does not add the shipping cost to the price. It's just displays it next to the produt's price.
