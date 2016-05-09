<?php
/*
Plugin Name: Distance Shipping Cost Calculator
Plugin URI: https://github.com/kostasx/wordpress-distance-shipping-calculator
Description: A WooCommerce plugin to calculate the shipping costs based on zip code
Author: KostasX / PlethoraThemes
Version: 1.0.0
Author URI: http://github.com/kostasx/
*/

/*** Setup ***/

define('REGION', 'GR');                               // Use Country TLD, e.g 'US' for the United States (https://goo.gl/z2Yjen)
define('RETAILER', '32200');                          // Zip code of where the goods are shipped from, e.g 32200 = Thebes, Greece 
define('GOOGLE_API_KEY','<YOUR-API-KEY>');            // Google Distance Matrix API key*

// * To generate an API key: https://console.developers.google.com/flows/enableapi?apiid=distance_matrix_backend&keyType=SERVER_SIDE&reusekey=true

// ** To generate an API Key: https://console.developers.google.com/flows/enableapi?apiid=geocoding_backend&keyType=SERVER_SIDE&reusekey=true

/*** Helper Functions ***/

// Convert Zip Code to Place ID (https://developers.google.com/places/place-id)
function zip_code_to_place_id($zipCode,$region){

  // Geocoding API
  // https://developers.google.com/maps/documentation/geocoding/intro

  $response = wp_remote_get( "https://maps.googleapis.com/maps/api/geocode/json?address=".$zipCode."&region=".$region."&key=" . GOOGLE_API_KEY );

  if( is_array($response) ) {
    $json_res = json_decode($response['body']);
    if ( $json_res->status == "OK" ){
      return $json_res->results[0]->place_id; // Address: $json_res->results[0]->formatted_address;
    } else {
      die(var_dump($response));
    }
  }

}

function get_distance($origin_place_id, $destination_place_id){

  // Distance Matrix API
  // https://developers.google.com/maps/documentation/distance-matrix/

  $response = wp_remote_get( "https://maps.googleapis.com/maps/api/distancematrix/json?origins=place_id:".$origin_place_id."&destinations=place_id:".$destination_place_id."&key=" . GOOGLE_API_KEY );

  if( is_array($response) ) {
    $json_res = json_decode($response['body']);

    if ( $json_res->status == "OK" ){
      // Debugging:
      // update_option('zipcode_latest', $json_res->origin_addresses[0] . " -> " . $json_res->destination_addresses[0] );
      return $json_res->rows[0]->elements[0]->distance->value;  // Get distance in kilometers
    } else {
      die(var_dump($response));
    }

  }

}

function calc_distance_cost($meters){

  $km = round($meters/1000);

  $cost = NULL; 
  $cost_table = array(
    "2"       => 0,   // 0~2 km
    "10"      => 10,  // 2~10 km
    "20"      => 15,  // 10~20 km
    "50"      => 25   // 10~50 km
  );

  foreach ($cost_table as $key => $value) {
    if ( $km <= intval($key) ){
      $cost = $value;
      break; 
    } 
  }

  if ( is_null($cost) ){ $cost = end($cost_table); }

  return $cost; // Debug output $cost . " (distance: ".$km.")";

}

function shipping_calc_init(){

  /*** Configuration ***/

  if ( !get_option( 'zipcode_destination' ) ){

    $destination_place_id = zip_code_to_place_id( RETAILER, REGION );
    add_option('zipcode_destination', $destination_place_id);

  }

  /*** Add Zip Code Poke Section ***/

  function plethora_add_markup_section($content) {

    if ( is_product() || is_shop() || is_product_category() ) {
      $css = '<style>
      #zipCodeWrapper {
        display: none;
        position: fixed;
        width: 100vw;
        top: 0;
        left: 0;
        padding: 10px;
        border: 1px solid gray;
        background-color: #f0f0f0;
        z-index: 10001;
        text-align: center;
      }
      #zipCodeWrapper input {
        width: 150px;
      }
      #zipCodeDismiss{
        position: absolute;
        right: 16px;
        top: 5px;
      }
      </style>';
      $content = 

      '<div id="zipCodeWrapper">
        <a href="#" id="zipCodeDismiss">Close</a>
        <div class="plethora-zip-code-input">
          <span>Please enter your zip code to receive shipping costs:</span>
          <input id="zipCode" type="text" />
          <input id="zipCodeSubmitter" type="submit" value="Submit" />
        </div>
      </div>';

      echo $css . $content;

    } else {

      echo $content;

    }

  }

  add_action('wp_footer', 'plethora_add_markup_section', 2);

  /*** Zip Code Section JavaScript ***/

  function plethora_add_javascript(){
    $javascript = '<script src="https://cdnjs.cloudflare.com/ajax/libs/js-cookie/2.1.1/js.cookie.min.js"></script>';
    $javascript .= '<script>
  
      window.onload = function(){

        var zipCodeWrapper = document.getElementById("zipCodeWrapper");
        var zipCodeSubmitter = document.getElementById("zipCodeSubmitter");
        var zipCodeInput = document.getElementById("zipCode");
        var zipCodeDismissBtn = document.getElementById("zipCodeDismiss");

        if ( zipCodeWrapper ){

          if ( !Cookies.get("zipCode") && !Cookies.get("zipCodeDismissed") ){

            zipCodeWrapper.style.display = "block";
            zipCodeSubmitter.addEventListener("click", function(e){
              e.preventDefault();
              Cookies.set("zipCode", zipCodeInput.value.replace(/ /g,""));
              location.reload();
            });

            zipCodeDismissBtn.addEventListener("click", function(e){
              e.preventDefault();
              Cookies.set("zipCodeDismissed","true");
              zipCodeWrapper.style.display = "none";
            });

          }
        }

      }

    </script>';

    echo $javascript;

  }

  add_action('wp_footer', 'plethora_add_javascript', 3);

  /*** Display Shipping Costs ***/

  add_filter('woocommerce_get_price_html', 'add_custom_price_front', 10, 2);

  function add_custom_price_front($p, $obj) {

    // $post_id = $obj->post->ID;

    if ( isset($_COOKIE['zipCode']) ){

      $zipCode = $_COOKIE['zipCode'];

      // Do we already have this zip code stored?
      if ( get_option( 'zipcode_' . $zipCode ) ){

        $cost = get_option( 'zipcode_' . $zipCode );

      // Let's get the distance using Google API
      } else {

        $place_id = zip_code_to_place_id( $zipCode, REGION );
        $distance = get_distance($place_id, get_option('zipcode_destination'));
        $cost     = calc_distance_cost($distance);
        add_option( 'zipcode_' . $zipCode, $cost, '', 'yes' );

      }

      return $p . "<br /><span style='font-size:80%' class='pro_price_extra_info'> Shipping Costs: " . get_woocommerce_currency_symbol() . $cost . "</span>";

    } else {

      return $p;

    }

  }

}

shipping_calc_init();