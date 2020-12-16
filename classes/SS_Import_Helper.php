<?php

class SS_Import_Helper {

  static function ss_checks_file($file, $file_url){
    if (!empty($file) && $file['size']) {
      if ($file["error"] > 0) {
        echo "Return Code: " . $file["error"] . "<br />";
      } else {
        if (in_array($file["type"],array('text/csv','application/vnd.ms-excel'))) {
          return 'csv';
        } elseif ($file["type"] == 'application/json') {
          return 'json';
        } else {
          echo "Unknown file type".$file['type'];
          return FALSE;
        }
      }
    } elseif (!empty($file_url)) {
      // needs improvement
      return 'csv_url';
    } else {
      echo 'No file uploaded.';
      return false;
    }
  }

  static function ss_parse_csv($file, $im_is_wpml, $csv_max_length){
    $file = fopen($file, "r");
    $data = array();

    $first_line = fgetcsv($file, $csv_max_length, ",");
    if ($im_is_wpml) {
      $count = 1;
      while ( ($row = fgetcsv($file, $csv_max_length, ",") ) != FALSE ){
        if ($count % 2){
          $array_to_add = array_combine($first_line, $row);
          $data[]['bg'] = self::csv_transform_row($array_to_add);
        } else {
          $array_to_add = array_combine($first_line, $row);
          $data[count($data)-1]['en'] = self::csv_transform_row($array_to_add);
        }
        $count++;
      }
    } else {
      while ( ($row = fgetcsv($file, $csv_max_length, ",") ) != FALSE ){
        $array_to_add = array_combine($first_line, $row);
        $data[] = self::csv_transform_row($array_to_add);
      }
    }

    fclose($file);
    return $data;
  }

  static function csv_transform_row($array_to_add) {
    foreach ($array_to_add as $k=>$v) {
      if (strpos($k, 'meta:') !== false) { // meta:
        unset($array_to_add[$k]);
        if (!empty($v)) {
          $array_to_add['meta'][substr($k,5)] = $v;
        }
      } elseif (strpos($k, 'tax:') !== false ) {
        unset($array_to_add[$k]);
        if (!empty($v)) {
          $array_to_add['tax'][substr($k,4)] = $v;
        }
      } elseif (strpos($k, 'acf-field:') !== false) { // acf-field:
        unset($array_to_add[$k]);
        $array_to_add['acf-field'][substr($k,10)] = $v;
      } elseif (strpos($k, 'acf-row:') !== false) { // acf-row:product_files:product_file
        unset($array_to_add[$k]);
        $array_to_add['acf-row'][substr($k,8,strpos($k,':',8)-8)][substr($k,strpos($k,':',9)+1)] = $v;
      } elseif (strpos($k, 'acf-sub:') !== false) { // acf-sub:field_group:field_name
        unset($array_to_add[$k]);
        $array_to_add['acf-sub'][substr($k,8,strpos($k,':',8)-8)][substr($k,strpos($k,':',9)+1)] = $v;
      } elseif (strpos($k, 'acf-table:') !== false) { // acf-table:table_1 asd>>>asd>>>asd>>> dsadsad dsada>>>d
        unset($array_to_add[$k]);
        if ( !empty($v) ){
          $array_to_add['acf-table'][substr($k,10,strpos($k,':',10)-10)][] = explode('>>>',$v);
        }
      } elseif (strpos($k, 'prod_gallery_') !== false) { // prod_gallery_1, prod_gallery_2, prod_gallery_3
        unset($array_to_add[$k]);
        if (!empty($v)) {
          $array_to_add['gallery_ids'][] = $v;
        }
      }elseif (strpos($k, 'attribute:') !== false) { // attribute: 
        unset($array_to_add[$k]);
        if (!empty($v)) {
          $array_to_add['attribute'][substr($k,10)] = $v;
        }
      } elseif (strpos($k, 'var1:') !== false) { // var1: 
        unset($array_to_add[$k]);
        if (!empty($v)) {
          $array_to_add['var1']['name'] = substr($k,5);
          $array_to_add['var1']['value'] = $v;
        }
      } elseif (strpos($k, 'var2:') !== false) { // var2: 
        unset($array_to_add[$k]);
        if (!empty($v)) {
          $array_to_add['var2']['name'] = substr($k,5);
          $array_to_add['var2']['value'] = $v;
        }
      }
    }

    return $array_to_add;
  }
}