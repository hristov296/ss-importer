<?php 

class SS_Import_Submit {

  function ss_insert_csv_no_wpml($data, $im_post_type, $is_woocommerce) {
    $output = '';

    foreach ($data as $i => $impost) {
      $postarr = array(
        'post_status' => 'publish',
        'post_type' => $im_post_type,
      );

      if (!empty($impost['post_id'])) {
        $postarr['ID'] = $impost['post_id'];
        $postarr['post_date'] = date("Y-m-d H:i:s", get_post_time('U', false, $impost['post_id']));
        $postarr['post_date_gmt'] = date("Y-m-d H:i:s", get_post_time('U', true, $impost['post_id']));
        $curr_post_title = get_the_title( $impost['post_id'] );
        $curr_post_excerpt = get_the_excerpt( $impost['post_id'] );
        $curr_post_content = get_the_content( $impost['post_id'] );
        $postarr['post_title'] = $curr_post_title ? $curr_post_title : '' ;
        $postarr['post_excerpt'] = $curr_post_excerpt ? $curr_post_excerpt : '' ;
        $postarr['post_content'] = $curr_post_content ? $curr_post_content : '' ;
      }
      if (!empty($impost['post_title'])) {
        $postarr['post_title'] = $impost['post_title'];
      }
      if (!empty($impost['post_content'])) {
        $postarr['post_content'] = $impost['post_content'];
      }
      if (!empty($impost['post_excerpt'])) {
        $postarr['post_excerpt'] = $impost['post_excerpt'];
      }
      if (!empty($impost['menu_order'])) {
        $postarr['menu_order'] = $impost['menu_order'];
      }
      if (!empty($impost['post_parent'])) {
        $postarr['post_parent'] = $impost['post_parent'];
      }
      if (!empty($impost['post_date'])) {
        $postarr['post_date'] = $impost['post_date'];
      }

      if (!empty($impost['meta'])) {
        foreach ($impost['meta'] as $key => $val) {
          if ($val !== '') {
            $postarr['meta_input'][$key] = $val;
          }
        }
      }

      if (!empty($impost['tax'])) {
        foreach ($impost['tax'] as $key => $val) {
          if ($val !== '') {
            $postarr['tax_input'][$key] = $val;
          }
        }
      }

      if ($im_post_type === 'product_variation') {
        if (empty($impost['post_parent'])) {
          $output .= 'Missing post_parent for product_variation on row:'.($i+1);
          break;
        }
        if (empty($impost['var1'])) {
          $output .= 'Missing var1 for product_variation on row:'.($i+1);
          break;
        }
        if (empty($impost['var2'])) {
          $output .= 'Missing var2 for product_variation on row:'.($i+1);
          break;
        }
      }

      $currid = wp_insert_post($postarr); // Sucction

      if ($currid) {
          $output .= $currid.' - '.$postarr['post_title'].' - success!</br>';

          if ($is_woocommerce && $im_post_type === 'product') {

            $product = wc_get_product($currid);
            $product_type = $product->get_type();
            if (!empty($impost['product_type']) && $impost['product_type'] === 'variable') {
              $product_type = 'variable';
            }
            $classname = WC_Product_Factory::get_product_classname( $currid, $product_type );
            $product = new $classname($currid);

            if (!empty($impost['product_cat'])) {
              $temp_arr = explode(',', $impost['product_cat']);
              $product->set_category_ids(array_map(function($val){return (int)$val;}, $temp_arr));
            }
            if (!empty($impost['product_tag'])) {
              $temp_arr = explode(',', $impost['product_tag']);
              $product->set_tag_ids(array_map(function($val){return (int)$val;}, $temp_arr));
            }
            if (!empty($impost['sale_price'])) {
              $product->set_sale_price( $impost['sale_price'] );
            }
            if (!empty($impost['price'])) {
              $product->set_regular_price( $impost['price'] );
            }
            if (!empty($impost['sku'])) {
              $product->set_sku( $impost['sku'] );
            }

            if (!empty($impost['attribute'])) {
              $current_atts = $product->get_attributes();

              $product->set_attributes( $this->ss_wc_attributes_sync($currid, $current_atts, $impost['attribute']), false );
            }

            if (!empty($impost['gallery_ids'])){
              $gallery_ids = array();
              foreach ($impost['gallery_ids'] as $key => $image_url) {
                $curr_gal_id = preg_match('/(http|https)/',$image_url) ? $this->crb_insert_attachment_from_url($image_url, $currid, $postarr['post_title'].'-00'.$key) : $image_url;
                array_push($gallery_ids, $curr_gal_id);
              }
              
              if (!empty($gallery_ids)){
                $product->set_gallery_image_ids($gallery_ids);
              }
            }
            
            $product->save();

          }

          if ($im_post_type === 'product_variation') {

            // set the parent's attributes, if not set
            $pr = new WC_Product_Variable($impost['post_parent']);
            $current_atts = $pr->get_attributes();
  
            
            $pr->set_attributes($this->ss_wc_attributes_sync($impost['post_parent'], $current_atts, $impost['attribute'], true));
            $cl = new WC_Product_Data_Store_CPT();
            $cl->update($pr);

            $variation = new WC_Product_Variation($currid);
            $variation->set_attributes(array(
              $impost['var1']['name'] => $impost['var1']['value'],
              $impost['var2']['name'] => $impost['var2']['value'],
            ));
            if (!empty($impost['price'])) {
              $variation->set_regular_price( $impost['price'] );
            }
            if (!empty($impost['sku'])) {
              $variation->set_sku( $impost['sku'] );
            }
            $variation->save();
          }

          if (!empty($impost['post_image'])) {
            $featured_img_id = preg_match('/(http|https)/',$impost['post_image']) ? $this->crb_insert_attachment_from_url($impost['post_image'], $currid, $postarr['post_title']) : $impost['post_image'];
            set_post_thumbnail($currid, $featured_img_id);
          }
          if (!empty($impost['acf-field'])){
            foreach ($impost['acf-field'] as $key => $val) {
              if ($val !== '') {
                if ($j = update_field($key, $val, $currid)){
                  $output .= 'Updated field <i>'.$key.'</i> with value: <i>'.$val.'</i><br/>';
                } else {
                  $output .= 'Failed updating <i>'.$key.'</i> => <i>'.$val.'</i><br/>';
                }
              }
            }
          }
          if (!empty($impost['acf-row'])) {
            foreach ($impost['acf-row'] as $rep_name => $rep_field) {
              foreach ($rep_field as $field_key => $field_name) {
                if ($field_name !== '') {
                  $value = explode(',', $field_name);
                  foreach ($value as $val) {
                    if ($i = add_row($rep_name,array($field_key=>$val), $currid)){
                      $output .= 'Added acf row: <i>'.$rep_name.'</i>, value: <i>'.$val.'</i> to repeater: <i>'.$rep_name.'</i><br/>';
                    } else {
                      $output .= 'Failed adding acf row: <i>'.$rep_name.'</i>, value: <i>'.$val.'</i> to repeater: <i>'.$rep_name.'</i><br/>';
                    }
                  }
                }
              }
            }
          }

          if (!empty($impost['acf-table'])) {
            foreach ($impost['acf-table'] as $table_name => $table_rows) {
              $field = acf_get_field($table_name);
              foreach ($table_rows as $row_key => $values) {
                $array_to_update = array();
                foreach( $field['sub_fields'] as $key => $sub_field) {
                  $array_to_update[$sub_field['name']] = $values[$key];
                }
                add_row($table_name, $array_to_update, $currid);
              }
            }
          }
          
          if (!empty($impost['acf-sub'])) {
              foreach ($impost['acf-sub'] as $group_name => $group_fields) {
                  foreach ($group_fields as $field_key => $field_name) {
                      if ($field_name !== '') {
                        if ($j = update_field($group_name.'_'.$field_key, $field_name, $currid)) {
                              $output .= 'Group '.$group_name.' sub field updated: '.$field_key.' - '.$field_name;
                          } else {
                              error_log(print_r($j, true));
                              $output .= 'Group '.$group_name.' sub field failed: '.$field_key.' - '.$field_name;
                          }
                      }
                  }
              }
          }
          
      }

      $output .= ' <br/>';
    }

    echo $output;

  }
  
  function ss_insert_csv_wpml($data, $im_post_type, $is_woocommerce) {
    $output = '';
    $inserted = array();

    foreach ($data as $data) {
      foreach ($data as $lang => $impost) {
        $postarr = array(
          'post_status' => 'publish',
          'post_type' => $im_post_type,
          // 'tax_input' => array(
          //   'product_type' => 'simple',
          //   'product_cat' => $lang == 'bg' ? 'Без категория' : '',
          //   'translation_priority' => $lang == 'bg' ? 'optional' : '',
          // ),
        );

        if (!empty($impost['post_id'])) {
          $postarr['ID'] = $impost['post_id'];
          $postarr['post_date'] = date("Y-m-d H:i:s", get_post_time('U', false, $impost['post_id']));
          $postarr['post_date_gmt'] = date("Y-m-d H:i:s", get_post_time('U', true, $impost['post_id']));
          $curr_post_title = get_the_title( $impost['post_id'] );
          $curr_post_excerpt = get_the_excerpt( $impost['post_id'] );
          $curr_post_content = get_the_content( $impost['post_id'] );
          $postarr['post_title'] = $curr_post_title ? $curr_post_title : '' ;
          $postarr['post_excerpt'] = $curr_post_excerpt ? $curr_post_excerpt : '' ;
          $postarr['post_content'] = $curr_post_content ? $curr_post_content : '' ;
        }
        if (!empty($impost['post_title'])) {
          $postarr['post_title'] = $impost['post_title'];
        }
        if (!empty($impost['post_content'])) {
          $postarr['post_content'] = $impost['post_content'];
        }
        if (!empty($impost['post_excerpt'])) {
          $postarr['post_excerpt'] = $impost['post_excerpt'];
        }
        if (!empty($impost['menu_order'])) {
          $postarr['menu_order'] = $impost['menu_order'];
        }
        if (!empty($impost['post_parent'])) {
          $postarr['post_parent'] = $impost['post_parent'];
        }
        if (!empty($impost['post_date'])) {
          $postarr['post_date'] = $impost['post_date'];
        }
  
        if (!empty($impost['meta'])) {
          foreach ($impost['meta'] as $key => $val) {
            if ($val !== '') {
              $postarr['meta_input'][$key] = $val;
            }
          }
        }

        if (!empty($impost['tax'])) {
          foreach ($impost['tax'] as $key => $val) {
            if ($val !== '') {
              $postarr['tax_input'][$key] = $val;
            }
          }
        }
  
        if ($im_post_type === 'product_variation') {
          if (empty($impost['post_parent'])) {
            $output .= 'Missing post_parent for product_variation on row:'.($i+1);
            break;
          }
          if (empty($impost['var1'])) {
            $output .= 'Missing var1 for product_variation on row:'.($i+1);
            break;
          }
          if (empty($impost['var2'])) {
            $output .= 'Missing var2 for product_variation on row:'.($i+1);
            break;
          }
        }
  
        $currid = wp_insert_post($postarr); // Sucction

        if ($currid) {
            $output .= $currid.' - '.$impost['post_title'].' - success!</br>';

            if ($is_woocommerce && $im_post_type === 'product') {

              $product = wc_get_product($currid);
              $product_type = $product->get_type();
              if (!empty($impost['product_type']) && $impost['product_type'] === 'variable') {
                $product_type = 'variable';
              }
              $classname = WC_Product_Factory::get_product_classname( $currid, $product_type );
              $product = new $classname($currid);
  
              if (!empty($impost['product_cat'])) {
                $temp_arr = explode(',', $impost['product_cat']);
                $product->set_category_ids(array_map(function($val){return (int)$val;}, $temp_arr));
              }
              if (!empty($impost['product_tag'])) {
                $temp_arr = explode(',', $impost['product_tag']);
                $product->set_tag_ids(array_map(function($val){return (int)$val;}, $temp_arr));
              }
              if (!empty($impost['sale_price'])) {
                $product->set_sale_price( $impost['sale_price'] );
              }
              if (!empty($impost['price'])) {
                $product->set_regular_price( $impost['price'] );
              }
              if (!empty($impost['sku'])) {
                $product->set_sku( $impost['sku'] );
              }
  
              if (!empty($impost['attribute'])) {
                $current_atts = $product->get_attributes();
  
                $product->set_attributes( $this->ss_wc_attributes_sync($currid, $current_atts, $impost['attribute']), false );
              }
  
              if (!empty($impost['gallery_ids'])){
                $gallery_ids = array();
                foreach ($impost['gallery_ids'] as $key => $image_url) {
                  $curr_gal_id = preg_match('/(http|https)/',$image_url) ? $this->crb_insert_attachment_from_url($image_url, $currid, $postarr['post_title'].'-00'.$key) : $image_url;
                  array_push($gallery_ids, $curr_gal_id);
                }
                
                if (!empty($gallery_ids)){
                  $product->set_gallery_image_ids($gallery_ids);
                }
              }
              
              $product->save();
  
            }
  
            if ($im_post_type === 'product_variation') {

              // set the parent's attributes, if not set
              $pr = new WC_Product_Variable($impost['post_parent']);
              $current_atts = $pr->get_attributes();
    
              
              $pr->set_attributes($this->ss_wc_attributes_sync($impost['post_parent'], $current_atts, $impost['attribute'], true));
              $cl = new WC_Product_Data_Store_CPT();
              $cl->update($pr);
  
              $variation = new WC_Product_Variation($currid);
              $variation->set_attributes(array(
                $impost['var1']['name'] => $impost['var1']['value'],
                $impost['var2']['name'] => $impost['var2']['value'],
              ));
              if (!empty($impost['price'])) {
                $variation->set_regular_price( $impost['price'] );
              }
              if (!empty($impost['sku'])) {
                $variation->set_sku( $impost['sku'] );
              }
              $variation->save();
            }
  
            if (!empty($impost['post_image'])) {
              $featured_img_id = preg_match('/(http|https)/',$impost['post_image']) ? $this->crb_insert_attachment_from_url($impost['post_image'], $currid, $postarr['post_title']) : $impost['post_image'];
              set_post_thumbnail($currid, $featured_img_id);
            }
            if (!empty($impost['acf-field'])){
              foreach ($impost['acf-field'] as $key => $val) {
                if ($val !== '') {
                  if ($j = update_field($key, $val, $currid)){
                    $output .= 'Updated field <i>'.$key.'</i> with value: <i>'.$val.'</i><br/>';
                  } else {
                    $output .= 'Failed updating <i>'.$key.'</i> => <i>'.$val.'</i><br/>';
                  }
                }
              }
            }
            if (!empty($impost['acf-row'])) {
              foreach ($impost['acf-row'] as $rep_name => $rep_field) {
                foreach ($rep_field as $field_key => $field_name) {
                  if ($field_name !== '') {
                    $value = explode(',', $field_name);
                    foreach ($value as $val) {
                      if ($i = add_row($rep_name,array($field_key=>$val), $currid)){
                        $output .= 'Added acf row: <i>'.$rep_name.'</i>, value: <i>'.$val.'</i> to repeater: <i>'.$rep_name.'</i><br/>';
                      } else {
                        $output .= 'Failed adding acf row: <i>'.$rep_name.'</i>, value: <i>'.$val.'</i> to repeater: <i>'.$rep_name.'</i><br/>';
                      }
                    }
                  }
                }
              }
            }

            if (!empty($impost['acf-table'])) {
              foreach ($impost['acf-table'] as $table_name => $table_rows) {
                $field = acf_get_field($table_name);
                foreach ($table_rows as $row_key => $values) {
                  $array_to_update = array();
                  foreach( $field['sub_fields'] as $key => $sub_field) {
                    $array_to_update[$sub_field['name']] = $values[$key];
                  }
                  add_row($table_name, $array_to_update, $currid);
                }
              }
            }
            
            if (!empty($impost['acf-sub'])) {
                foreach ($impost['acf-sub'] as $group_name => $group_fields) {
                    foreach ($group_fields as $field_key => $field_name) {
                        if ($field_name !== '') {
                          if ($j = update_field($group_name.'_'.$field_key, $field_name, $currid)) {
                                $output .= 'Group '.$group_name.' sub field updated: '.$field_key.' - '.$field_name;
                            } else {
                                error_log(print_r($j, true));
                                $output .= 'Group '.$group_name.' sub field failed: '.$field_key.' - '.$field_name;
                            }
                        }
                    }
                }
            }
            
            $inserted[$lang] = $currid;
        }
      }

      if (!empty($inserted)){
        $wpml_element_type = apply_filters( 'wpml_element_type', $im_post_type );
        $get_language_args = array('element_id' => $inserted['bg'], 'element_type' => $im_post_type );
        $original_post_language_info = apply_filters( 'wpml_element_language_details', null, $get_language_args );

        $set_language_args = array(
            'element_id'    => $inserted['en'],
            'element_type'  => $wpml_element_type,
            'trid'   => $original_post_language_info->trid,
            'language_code'   => 'en',
            'source_language_code' => $original_post_language_info->language_code
        );

        do_action( 'wpml_set_element_language_details', $set_language_args );
        $output .= $inserted['en'].' - set as EN translation to - '.$inserted['bg'];

      }
      $inserted = array();
      $output .= ' <br/>';
    }

    echo $output;
  }

  function ss_wc_attributes_sync($post_id, $curr_atts, $new_atts, $is_var = false) {
    $atts = array();

    foreach ($new_atts as $attr_tax => $values) {
      $current_options = array();
      $values = array_map(
        function($val) use ($attr_tax) {
          return get_term_by('slug', $val, $attr_tax)->term_id;
        }, explode(',',$values)
      );

      if (array_key_exists($attr_tax, $curr_atts)) {
        $current_options = wc_get_object_terms( $post_id, $attr_tax, 'term_id' );
      }
      $options = array_merge($current_options, $values);

      $attr = new WC_Product_attribute();
      $attr->set_id(wc_attribute_taxonomy_id_by_name( $attr_tax ));
      $attr->set_name( $attr_tax );
      $attr->set_options( $options );
      $attr->set_position( 0 );
      $attr->set_visible( 1 );
      $attr->set_variation( (int)$is_var );
      $atts[] = $attr;

    }

    return array_merge($curr_atts, $atts);
  }

  /**
   * Insert an attachment from an URL address.
   *
   * @param  String $url
   * @param  Int    $parent_post_id
   * @return Int    Attachment ID
   */
  function crb_insert_attachment_from_url($url, $parent_post_id = null, $name = null) {

    if( !class_exists( 'WP_Http' ) )
      include_once( ABSPATH . WPINC . '/class-http.php' );

    $http = new WP_Http();
    $response = $http->request( $url );

    if (is_wp_error( $response )) {
      error_log(print_r($response->get_error_message(),true));
      return false;
    }

    if( $response['response']['code'] != 200 ) {
      error_log(print_r($response,true));
      return false;
    }

    $ext = pathinfo($url)['extension'];
    $upload_name = isset($name) ? $this->prepare_filename($name).'.'.$ext : basename($url);

    $upload = wp_upload_bits( $upload_name, null, $response['body'] );
    if( !empty( $upload['error'] ) ) {
      error_log(print_r($upload_name,true));
      error_log(print_r($upload,true));
      return false;
    }

    $file_path = $upload['file'];
    $file_name = basename( $file_path );
    $file_type = wp_check_filetype( $file_name, null );
    $attachment_title = sanitize_file_name( pathinfo( $file_name, PATHINFO_FILENAME ) );
    $wp_upload_dir = wp_upload_dir();

    $post_info = array(
      'guid'           => $wp_upload_dir['url'] . '/' . $file_name,
      'post_mime_type' => $file_type['type'],
      'post_title'     => $attachment_title,
      'post_content'   => '',
      'post_status'    => 'inherit',
    );

    // Create the attachment
    $attach_id = wp_insert_attachment( $post_info, $file_path, $parent_post_id );
    // error_log(print_r($attach_id,true));

    // Include image.php
    require_once( ABSPATH . 'wp-admin/includes/image.php' );

    // Define attachment metadata
    $attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );

    // Assign metadata to attachment
    wp_update_attachment_metadata( $attach_id,  $attach_data );

    return $attach_id;

  }

  function prepare_filename($fileName) {
    // Remove multiple spaces
    $fileName = preg_replace('/\s+/', ' ', $fileName);

    // Replace spaces with hyphens
    $fileName = preg_replace('/\s/', '-', $fileName);

    return $fileName;
  }

  function ss_submit_json() {

  }

  function ss_custom_action($data, $im_post_type, $is_woocommerce) {
    $output = 'No active custom action right now';

    // $this->crb_insert_attachment_from_url('https://i.jessops.com/ce-images/PRODUCT/PRODUCT_ENLARGED/ANIKOCM205330698.jpg', 139, 'Canon EOS-1D X Mark III Digital SLR Body');
    // foreach ($data as $i => $impost) {
    //   $variation = new WC_Product_Variation($impost['post_id']);

    //   $variation->set_regular_price( $impost['price'] );
    //   $variation->save();

    //   $output .= $impost['post_id'].' updated with price - '.$impost['price'].'</br>';
    // }

    echo $output;
  }
  
}

