<?php

function ss_import_view() { ?>
  <div class="wrap">
    <h1>SS Importer</h1>
    <hr />
    <h3>Current fields:</h3>
    <ul>
      <li>"post_id" - ID, for updating posts</li>
      <li>"post_title" - Title</li>
      <li>"post_content" - Post Content</li>
      <li>"post_excerpt" - Post Excerpt</li>
      <li>"post_parent" - Post Parent (for variations)</li>
      <li>"menu_order" - Menu Order</li>
      <li>"meta:{key}" - {value}</li>
      <li>"acf-field:{key}" - {value}</li>
      <li>"acf-row:{row-name}:{field-name}" - {value},{value},{value}...</li>
      <li>"acf-sub:{group-name}:{field-name}" - {value}</li>
      <li>"acf-table:{table-name}:{field-name}" - {value},{value},{value}...</li>
      <li><h4>Woocommerce fields</h4></li>
      <li>"product_type" - product_type</li>
      <li>"attribute:{attr_name}" - {attr-name1},{attr-name2},{attr-name3}...</li>
      <!-- <li>"variation_atts" - {attr-name1},{attr-name2},{attr-name3} (Attributes used for variation. Must have post_parent, var1, var2</li> -->
      <li>"var1:{attr_name}" - {value} (First attribute used for variation)</li>
      <li>"var2:{attr_name}" - {value} (Second attribute used for variation)</li>
      <li>"price" - price</li>
      <li>"product_cat" - {id1},{id2},{id3}...</li>
      <li>"product_tag" - {id1},{id2},{id3}...</li>
    </ul>
    <form enctype="multipart/form-data" method="post" action="<?php menu_page_url('ss-import-submit.php'); ?>" class="media-upload-form type-form validate">
      <table class="form-table">
        <tbody>
          <tr>
            <th scope="row"><label for="im-file">Upload a file</label></th>
            <td><input type="file" name="im-file" id="im-file" class="regular-text"></td>
          </tr>
          <tr>
            <th scope="row"><label for="im-file-url">File URL</label></th>
            <td><input name="im-file-url" id="im-file-url" class="regular-text" value="<?php echo SS_Import_Main::$sheets_url; ?>"></td>
          </tr>
          <tr>
            <th scope="row"><label for="im-post-type">Choose a post type</label></th>
            <td>
              <select name="im-post-type" id="im-post-type">
                <?php
                foreach (SS_Import_Main::$site_post_types as $ptype) {
                  echo '<option value="'.$ptype->name.'">'.$ptype->label.'</option>';
                  if ($ptype->name === 'product') {
                    echo '<option value="product_variation">'.__('Вариации на продукт','ss').'</option>';
                  }
                } ?>
              </select>
            </td>
          </tr>
          <tr>
            <th scope="row"><label for="im-csv-length">CSV row max length</label></th>
            <td><input type="number" name="im-csv-length" id="im-csv-length" class="all-options" value="<?php echo SS_Import_Main::$csv_max_length; ?>" min="0" step="1000"></td>
          </tr>
          <tr>
            <th scope="row"><label for="im-is-wpml">Is WPML?</label></th>
            <td><input type="checkbox" name="im-is-wpml" id="im-is-wpml" class="regular-text"></td>
          </tr>
        </tbody>
      </table>
      <!-- <input type="submit" name="im-upload" id="im-upload" class="button button-primary" value="Upload"/> -->
      <input type="submit" name="im-preview" id="im-preview" formaction="<?php menu_page_url('ss-import-preview.php'); ?>" class="button button-secondary" value="Preview"/>
      <input type="submit" name="im-custom" id="im-custom" formaction="<?php menu_page_url('ss-import-custom.php'); ?>" class="button button-secondary" value="Custom Action"/>
      <input type="submit" name="im-submit" id="im-submit" class="button button-primary" value="Submit"/>
    </form>
  </div>
  <?php
}
