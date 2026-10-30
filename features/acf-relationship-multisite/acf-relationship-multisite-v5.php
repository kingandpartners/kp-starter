<?php

class acf_field_relationship_multisite extends acf_field {


  /*
  *  __construct
  *
  *  This function will setup the field type data
  *
  *  @type  function
  *  @date  5/03/2014
  *  @since 5.0.0
  *
  *  @param n/a
  *  @return  n/a
  */

  function __construct() {
    // vars
    $this->name     = 'relationship_multisite';
    $this->label    = __("Relationship Multisite",'acf');
    $this->category = 'relational';
    $this->defaults = array(
      'post_type'     => array(),
      'taxonomy'      => array(),
      'site'          => array(),
      'sites'         => array(),
      'max'           => 0,
      'filters'       => array('search', 'post_type', 'taxonomy'),
      'elements'      => array(),
      'return_format' => 'object'
    );
    $this->l10n = array(
      'max'     => __("Maximum values reached ( {max} values )",'acf'),
      'loading' => __('Loading','acf'),
      'empty'   => __('No matches found','acf'),
    );


    // extra
    add_action('wp_ajax_acf/fields/relationship_multisite/query', array($this, 'ajax_query'));
    add_action('wp_ajax_nopriv_acf/fields/relationship_multisite/query', array($this, 'ajax_query'));


    // do not delete!
    parent::__construct();
  }

  function input_admin_enqueue_scripts() {

    $dir = content_url( 'mu-plugins/kp-starter/features/acf-relationship-multisite/' );


    // register & include JS
    wp_register_script('acf-relationship_multisite', "{$dir}js/input.js");
    wp_enqueue_script('acf-relationship_multisite');



  }
  /*
  *  query_posts
  *
  *  description
  *
  *  @type  function
  *  @date  24/10/13
  *  @since 5.0.0
  *
  *  @param $post_id (int)
  *  @return  $post_id (int)
  */

  function ajax_query() {

      // options
      $options = acf_parse_args($_POST, array(
      'post_id'   => 0,
      's'         => '',
      'post_type' => '',
      'taxonomy'  => '',
      'sites'     => '',
      'lang'      => false,
      'field_key' => '',
      'nonce'     => '',
      'paged'     => 1
    ));


    // validate
    if( ! wp_verify_nonce($options['nonce'], 'acf_nonce') ) {

      die();

    }


    // vars
      $r    = array();
      $args = array();


      // paged
      $args['posts_per_page'] = 20;
      $args['paged'] = $options['paged'];


    // load field
    $field = acf_get_field( $options['field_key'] );

    if( !$field ) {

      die();

    }


    // WPML
    if( $options['lang'] ) {

      global $sitepress;

      if( !empty($sitepress) ) {

        $sitepress->switch_lang( $options['lang'] );

      }
    }


    // update $args
    if( !empty($options['post_type']) ) {

      $args['post_type'] = dhz_get_array( $options['post_type'] );

    } elseif( !empty($field['post_type']) ) {

      $args['post_type'] = dhz_get_array( $field['post_type'] );

    } else {
      switch_to_blog( $field['site'] );
      $args['post_type'] = acf_get_post_types();
      restore_current_blog();
    }


    // update taxonomy
    $taxonomies = array();
    $args['tax_query'] = array();

    if( !empty($options['taxonomy']) ) {

      $term = acf_decode_taxonomy_term($options['taxonomy']);

      // append to $args
      $args['tax_query'][] = array(
        'taxonomy' => $term['taxonomy'],
        'field'    => 'slug',
        'terms'    => $term['term'],
      );

    } elseif( !empty($field['taxonomy']) ) {

      $taxonomies = acf_decode_taxonomy_terms( $field['taxonomy'] );

      // now create the tax queries
      foreach( $taxonomies as $taxonomy => $terms ) {

        $args['tax_query'][] = array(
          'taxonomy' => $taxonomy,
          'field'    => 'slug',
          'terms'    => $terms,
        );

      }

    }


    // search
    if( $options['s'] ) {

      $args['s'] = $options['s'];

    }


    // filters
    $args = apply_filters('acf/fields/relationship/query', $args, $field, $options['post_id']);
    $args = apply_filters('acf/fields/relationship/query/name=' . $field['name'], $args, $field, $options['post_id'] );
    $args = apply_filters('acf/fields/relationship/query/key=' . $field['key'], $args, $field, $options['post_id'] );

    $sites = (!empty($options['sites'])) ? explode(',', $options['sites']) : $field['sites'];

    if (count($sites) > 0) {
      $groups = array_reduce($sites, function($carry, $site) use($args, $options, $field) {
        $field['site'] = $site;
        $carry[] = $this->get_group($args, $options, $field);
        return $carry;
      }, []);
    } else {
      $groups = $this->get_group($args, $options, $field);
    }

    // return JSON
    echo json_encode( $groups );
    die();

  }

  function get_group($args, $options, $field) {
    $groups = $this->acf_get_posts_from_site( $args, $field['site'] );
    $group  = $this->get_site_children($groups, $options, $field);
    restore_current_blog();
    return (isset($group[0]['children'])) ? $group[0]['children'] : [];
  }

  function get_site_children($groups, $options, $field) {
    if ( empty($groups) ) return [];

    foreach( array_keys($groups) as $group_title ) {

      // vars
      $posts  = acf_extract_var( $groups, $group_title );
      $titles = array();


      // data
      $data = array(
        'text'     => $group_title,
        'children' => array()
      );


      foreach( array_keys($posts) as $post_id ) {

        // override data
        $posts[ $post_id ] = $this->get_post_title( $posts[ $post_id ], $field, $options['post_id'] );

      };


      // order by search
      if( !empty($args['s']) ) {

        $posts = acf_order_by_search( $posts, $args['s'] );

      }


      // append to $data
      foreach( array_keys($posts) as $post_id ) {

        $data['children'][] = array(
          'id'   => get_current_blog_id() . ':' . $post_id,
          'text' => $posts[ $post_id ]
        );

      }


      // append to $r
      $r[] = $data;

    }
    return $r;
  }


  /*
  *  get_post_title
  *
  *  This function returns the HTML for a result
  *
  *  @type  function
  *  @date  1/11/2013
  *  @since 5.0.0
  *
  *  @param $post (object)
  *  @param $field (array)
  *  @param $post_id (int) the post_id to which this value is saved to
  *  @return  (string)
  */

  function get_post_title( $post, $field, $post_id = 0 ) {

    // get post_id
    if( !$post_id ) {

      $form_data = acf_get_setting('form_data');

      if( !empty($form_data['post_id']) ) {

        $post_id = $form_data['post_id'];

      } else {

        $post_id = get_the_ID();

      }

    }


    // vars
    $title = acf_get_post_title( $post );


    // elements
    if( !empty($field['elements']) ) {

      if( in_array('featured_image', $field['elements']) ) {

        $image = '';

        if( $post->post_type == 'attachment' ) {

          $image = wp_get_attachment_image( $post->ID, array(22, 22) );

        } else {

          $image = get_the_post_thumbnail( $post->ID, array(22, 22) );

        }


        $title = '<div class="thumbnail">' . $image . '</div>' . $title;
      }

    }


    // filters
    $title = apply_filters('acf/fields/relationship/result', $title, $post, $field, $post_id);
    $title = apply_filters('acf/fields/relationship/result/name=' . $field['_name'], $title, $post, $field, $post_id);
    $title = apply_filters('acf/fields/relationship/result/key=' . $field['key'], $title, $post, $field, $post_id);


    // return
    return $title;
  }


  /*
  *  get_posts
  *
  *  This function will return an array of posts for a given field value
  *
  *  @type  function
  *  @date  13/06/2014
  *  @since 5.0.0
  *
  *  @param $value (array)
  *  @return  $value
  */

  function get_posts( $value ) {

    // force value to array
    $value = dhz_get_array( $value );


    // convert to int
    $value = array_map('intval', $value);


    // load posts in 1 query to save multiple DB calls from following code
    if( count($value) > 1 ) {

      get_posts(array(
        'posts_per_page' => -1,
        'post_type'      => acf_get_post_types(),
        'post_status'    => 'any',
        'post__in'       => $value,
        'meta_query'     => array(
          array(
            'key'   => '_locale',
            'value' => $this->current_locale()
          ),
        ),
      ));

    }


    // vars
    $posts = array();


    // update value to include $post
    foreach( $value as $post_id ) {

      if( $post = get_post( $post_id ) ) {

        $posts[] = $post;

      }

    }


    // return
    return $posts;
  }

  function field_instructions($field) {
    $current_blog = get_bloginfo('name' );
    switch_to_blog( $field['site'] );
    $post_types = array_map(function($label) {
      return get_post_type_object($label)->label;
    }, $field['post_type']);
    $post_types = implode(', ', $post_types);
    $name = (count($field['sites']) > 1) ? 'multiple sites' : get_bloginfo('name' );
    restore_current_blog();
    return "<p>You are looking at <strong>$post_types</strong> from <strong>$name</strong></p>";
  }

  function field_post_types($field) {
    $post_types = array();
    if (!empty($field['post_type'])) {
      $post_types = $field['post_type'];
    } else {
      switch_to_blog( $field['site'] );
      $post_types = acf_get_post_types();
      restore_current_blog();
    }
    return acf_get_pretty_post_types($post_types);
  }

  function field_attributes($field) {
    $atts = array(
      'id'             => $field['id'],
      'class'          => "acf-relationship {$field['class']}",
      'data-max'       => $field['max'],
      'data-s'         => '',
      'data-post_type' => '',
      'data-taxonomy'  => '',
      'data-paged'     => 1,
      'data-sites'     => '',
    );
    if (defined('ICL_LANGUAGE_CODE')) $atts['data-lang'] = ICL_LANGUAGE_CODE;
    return $atts;
  }

  function field_taxonomies($field) {
    $taxonomies = array();
    if (!empty($field['taxonomy'])) {
      // get the field's terms
      $term_groups = dhz_get_array( $field['taxonomy'] );
      $term_groups = acf_decode_taxonomy_terms( $term_groups );

      // update taxonomies
      $taxonomies = array_keys($term_groups);
    } elseif (!empty($field['post_type'])) {
      // loop over post types and find connected taxonomies
      foreach ($field['post_type'] as $post_type) {
        $post_taxonomies = get_object_taxonomies($post_type);
        // bail early if no taxonomies
        if (empty($post_taxonomies)) continue;
        foreach ($post_taxonomies as $post_taxonomy) {
          if(!in_array($post_taxonomy, $taxonomies)) {
            $taxonomies[] = $post_taxonomy;
          }
        }
      }
    } else {
      $taxonomies = acf_get_taxonomies();
    }
    return $taxonomies;
  }

  function field_term_groups($field, $taxonomies) {
    switch_to_blog($field['site']);
    $term_groups = acf_get_taxonomy_terms($taxonomies);
    restore_current_blog();
    unset($term_groups['Category']);
    // update $term_groups with specific terms
    if (!empty($field['taxonomy'])) {
      foreach (array_keys($term_groups) as $taxonomy) {
        foreach (array_keys($term_groups[$taxonomy]) as $term) {
          if (!in_array($term, $field['taxonomy'])) {
            unset($term_groups[$taxonomy][$term]);
          }
        }
      }
    }
    return $term_groups;
  }

  function field_widths($field) {
    $width = array(
      'search'    => 0,
      'taxonomy'  => 0
    );
    if (!empty($field['filters'])) {
      $width = array(
        'search'    => 50,
        'taxonomy'  => 50
      );
      foreach (array_keys($width) as $k) {
        if(!in_array($k, $field['filters'])) {
          $width[ $k ] = 0;
        }
      }
      // search
      if ($width['search'] == 0) {
        $width['post_type'] = ($width['post_type'] == 0) ? 0 : 50;
        $width['taxonomy']  = ($width['taxonomy'] == 0) ? 0 : 50;
      }
      // search
      if ($width['taxonomy'] == 0) {
        $width['search'] = ($width['search'] == 0) ? 0 : 100;
      }
    }
    return $width;
  }

  /*
  *  render_field()
  *
  *  Create the HTML interface for your field
  *
  *  @param $field - an array holding all the field's data
  *
  *  @type  action
  *  @since 3.6
  *  @date  23/01/13
  */

  function render_field($field) {
    // output field instructions above field
    echo $this->field_instructions($field);

    // vars
    $atts = $this->field_attributes($field);

    // data types
    $field['post_type'] = dhz_get_array($field['post_type']);
    $field['taxonomy']  = dhz_get_array($field['taxonomy']);

    // post_types
    $post_types = $this->field_post_types($field);

    // taxonomies
    $taxonomies = $this->field_taxonomies($field);

    // terms
    $term_groups = $this->field_term_groups($field, $taxonomies);

    // width for select filters
    $width = $this->field_widths($field);

    ?>
<div <?php acf_esc_attr_e($atts); ?>>

  <div class="acf-hidden">
    <input type="hidden" name="<?php echo $field['name']; ?>" value="" />
  </div>

  <?php if( $width['search'] > 0 || $width['post_type'] > 0 || $width['taxonomy'] > 0 ): ?>
  <div class="filters">

    <ul class="acf-hl">

      <?php if( $width['search'] > 0 ): ?>
      <li style="width:<?php echo $width['search']; ?>%;">
        <div class="inner">
        <input class="filter" data-filter="s" placeholder="<?php _e("Search...",'acf'); ?>" type="text" style="height:28px;padding: 0 10px;border:0" />
        </div>
      </li>
      <?php endif; ?>

      <?php if( $width['taxonomy'] > 0 ): ?>
      <li style="width:<?php echo $width['taxonomy']; ?>%;">
        <div class="inner">
        <select class="filter" data-filter="taxonomy" style="border:0">
          <option value=""><?php _e('Select taxonomy','acf'); ?></option>
          <?php foreach( $term_groups as $k_opt => $v_opt ):?>
            <optgroup label="<?php echo $k_opt; ?>">
              <?php foreach( $v_opt as $k => $v ): ?>
                <option value="<?php echo $k; ?>"><?php echo $v; ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
        </div>
      </li>
      <?php endif; ?>
    </ul>

  </div>
  <?php endif; ?>

  <div class="selection acf-cf">

    <div class="choices">

      <ul class="acf-bl list"></ul>

    </div>

    <div class="values">

      <ul class="acf-bl list">

        <?php if( !empty($field['value']) ):
          $posts = $this->get_sorted_multisite_posts($field['value'], $field);
          switch_to_blog( $field['site'] );
          if (!empty($posts)):

            foreach( array_keys($posts) as $i ):

              // vars
              $post = acf_extract_var( $posts, $i );

              ?><li>
                <input type="hidden" name="<?php echo $field['name']; ?>[]" value="<?php echo $post->full_id; ?>" />
                <span data-id="<?php echo $post->full_id; ?>" class="acf-rel-item">
                  <?php echo $post->full_title; ?>
                  <a href="#" class="acf-icon small dark" data-name="remove_item"><i class="acf-sprite-remove"></i></a>
                </span>
              </li><?php

            endforeach;

          endif;

          restore_current_blog();

        endif; ?>

      </ul>

    </div>

  </div>

</div>
    <?php
  }



  /*
  *  render_field_settings()
  *
  *  Create extra options for your field. This is rendered when editing a field.
  *  The value of $field['name'] can be used (like bellow) to save extra data to the $field
  *
  *  @type  action
  *  @since 3.6
  *  @date  23/01/13
  *
  *  @param $field  - an array holding all the field's data
  */

  function render_field_settings( $field ) {

    // default_value
    acf_render_field_setting( $field, array(
      'label'        => __('Multisite','acf-relationship-multisite'),
      'instructions' => __('Select the site to get posts from','acf-relationship-multisite'),
      'type'         => 'select',
      'name'         => 'site',
      'choices'      => $this->acf_get_sites(),
      'multiple'     => 0,
      'ui'           => 1,
      'allow_null'   => 1,
      'placeholder'  => __("Select site",'acf-relationship-multisite'),
    ));


    // filters
    acf_render_field_setting( $field, array(
      'label'        => __('Filters','acf'),
      'instructions' => '',
      'type'         => 'checkbox',
      'name'         => 'filters',
      'choices'      => array(
        'search'     => __("Search",'acf'),
        'post_type'  => __("Post Type",'acf'),
        'taxonomy'   => __("Taxonomy",'acf'),
        'site'       => __("Site",'acf'),
      ),
    ));


    // filters
    acf_render_field_setting( $field, array(
      'label'        => __('Elements','acf'),
      'instructions' => __('Selected elements will be displayed in each result','acf'),
      'type'         => 'checkbox',
      'name'         => 'elements',
      'choices'      => array(
        'featured_image'  => __("Featured Image",'acf'),
      ),
    ));


    // max
    if( $field['max'] < 1 ) {

      $field['max'] = '';

    }


    acf_render_field_setting( $field, array(
      'label'        => __('Maximum posts','acf'),
      'instructions' => '',
      'type'         => 'number',
      'name'         => 'max',
    ));


    // return_format
    acf_render_field_setting( $field, array(
      'label'        => __('Return Format','acf'),
      'instructions' => '',
      'type'         => 'radio',
      'name'         => 'return_format',
      'choices'      => array(
        'object'     => __("Post Object",'acf'),
        'id'         => __("Post ID",'acf'),
      ),
      'layout'        =>  'horizontal',
    ));


  }


  /*
  *  format_value()
  *
  *  This filter is appied to the $value after it is loaded from the db and before it is returned to the template
  *
  *  @type  filter
  *  @since 3.6
  *  @date  23/01/13
  *
  *  @param $value (mixed) the value which was loaded from the database
  *  @param $post_id (mixed) the $post_id from which the value was loaded
  *  @param $field (array) the field array holding all the field options
  *
  *  @return  $value (mixed) the modified value
  */

  function format_value( $value, $post_id, $field ) {
    // bail early if no value
    if (empty($value)) return $value;

    // force value to array
    $selected_posts = dhz_get_array( $value );

    // load posts if needed
    if( $field['return_format'] == 'object' ) {
      $selected_posts = $this->get_sorted_multisite_posts($selected_posts, $field);
    }

    // return
    return $selected_posts;
  }


  /*
  *  load_value()
  *
  *  This filter is applied to the $value after it is loaded from the db
  *
  *  @type  filter
  *  @since 3.6
  *  @date  23/01/13
  *
  *  @param $value (mixed) the value found in the database
  *  @param $post_id (mixed) the $post_id from which the value was loaded
  *  @param $field (array) the field array holding all the field options
  *  @return  $value
  */


  function load_value( $value, $post_id, $field ) {

    if( empty($value) ) {

      return $value;

    }

    if (array_key_exists('selected_posts', $value)) {
      $value = $value['selected_posts'];
    }

    return $value;

  }

  /*
  *  update_value()
  *
  *  This filter is appied to the $value before it is updated in the db
  *
  *  @type  filter
  *  @since 3.6
  *  @date  23/01/13
  *
  *  @param $value - the value which will be saved in the database
  *  @param $post_id - the $post_id of which the value will be saved
  *  @param $field - the field array holding all the field options
  *
  *  @return  $value - the modified value
  */

  function update_value( $value, $post_id, $field ) {

    $r = array();
    $selected_posts = array();
    // validate
    if (empty($value)) return $value;

    // force value to array
    $selected_posts = dhz_get_array( $value );

    // array
    foreach( $selected_posts as $k => $v ){
      // object?
      if( is_object($v) && isset($v->ID) ) {
        $selected_posts[ $k ] = $v->ID;
      }
    }

    // save value as strings, so we can clearly search for them in SQL LIKE statements
    $r['site_id'] = $field['site'];
    $r['selected_posts'] = array_map('strval', $selected_posts);

    // return
    return $r;

  }

  function acf_get_sites() {

    $ref = array();
    $sites = get_sites();

    foreach ($sites as $site) {
      $site = (array) $site;
      $current_blog_details = get_blog_details( array( 'blog_id' => $site['blog_id'] ) );
      $site_id = $site['blog_id'];
      $label = $current_blog_details->blogname;
      $ref[$site_id] = $label;
    }
    return $ref;
  }

  function acf_get_posts_from_site( $args, $site ) {

    // vars
    $r = array();
    switch_to_blog( $site );

    // defaults
    $args = acf_parse_args( $args, array(
      'posts_per_page'          => -1,
      'paged'                   => 0,
      'post_type'               => 'post',
      'orderby'                 => 'menu_order title',
      'order'                   => 'ASC',
      'post_status'             => 'publish',
      'suppress_filters'        => false,
      'update_post_meta_cache'  => false,
      'meta_query'              => array(
        array(
          'key'   => '_locale',
          'value' => $this->current_locale()
        ),
      ),
    ));


    // find array of post_type
    $post_types = dhz_get_array( $args['post_type'] );
    $post_types_labels = acf_get_pretty_post_types($post_types);


    // attachment doesn't work if it is the only item in an array
    if( count($post_types) == 1 ) {

      $args['post_type'] = current($post_types);

    }


    // add filter to orderby post type
    add_filter('posts_orderby', '_acf_orderby_post_type', 10, 2);


    // get posts
    $posts = get_posts( $args );


    // loop
    foreach( $post_types as $post_type ) {

      // vars
      $this_posts = array();
      $this_group = array();


      // populate $this_posts
      foreach( array_keys($posts) as $key ) {

        if( $posts[ $key ]->post_type == $post_type ) {

          $this_posts[] = acf_extract_var( $posts, $key );

        }

      }


      // bail early if no posts for this post type
      if( empty($this_posts) ) {

        continue;

      }


      // sort into hierachial order!
      // this will fail if a search has taken place because parents wont exist
      if( is_post_type_hierarchical($post_type) && empty($args['s'])) {

        // vars
        $match_id = $this_posts[ 0 ]->ID;
        $offset = 0;
        $length = count($this_posts);


        // reset $this_posts
        $this_posts = array();


        // get all posts
        $all_args = array_merge($args, array(
          'posts_per_page' => -1,
          'paged'          => 0,
          'post_type'      => $post_type
        ));

        $all_posts = get_posts( $all_args );


        // loop over posts and find $i
        foreach( $all_posts as $offset => $p ) {

          if( $p->ID == $match_id ) {

            break;

          }

        }


        // order posts
        $all_posts = get_page_children( 0, $all_posts );


        for( $i = $offset; $i < ($offset + $length); $i++ ) {

          $this_posts[] = acf_extract_var( $all_posts, $i);

        }

      }


      // populate $this_posts
      foreach( array_keys($this_posts) as $key ) {

        // extract post
        $post = acf_extract_var( $this_posts, $key );


        // add to group
        $this_group[ $post->ID ] = $post;

      }


      // group by post type
      $post_type_name = $post_types_labels[ $post_type ];

      $r[ $post_type_name ] = $this_group;

    }


    // return
    return $r;

  }

  function get_sorted_multisite_posts($selected_posts, $field) {
    // sort selected posts by site id
    $sorted = array_reduce($selected_posts, function($carry, $item) {
      list($site, $id) = explode(':', $item);
      $carry[$site][] = $id;
      return $carry;
    }, []);
    $sites = array_keys($sorted);
    $post_map = array_reduce($sites, function($carry, $site) use($sorted, $field) {
      $ids = array_values($sorted[$site]);
      switch_to_blog($site);
      $site_name = get_bloginfo('name');
      $posts = get_posts([
        'post_per_page' => -1,
        'post_type'     => $field['post_type'],
        'post_status'   => 'publish',
        'post__in'      => $ids,
        'meta_query'    => array(
          array(
            'key'   => '_locale',
            'value' => $this->current_locale()
          ),
        ),
      ]);
      foreach ($posts as $post) {
        $full_id = $site . ':' . $post->ID;
        $post->full_title = '<strong>' . $site_name . '</strong> ' . $post->post_title;
        $post->full_id = $full_id;
        $post->site_name = $site_name;
        $post = apply_filters('relationship_multisite_post', $post);
        $carry[$full_id] = $post;
      }
      restore_current_blog();
      return $carry;
    }, []);
    return array_reduce($selected_posts, function($carry, $full_id) use($post_map) {
      if (isset($post_map[$full_id])) $carry[] = $post_map[$full_id];
      return $carry;
    }, []);
  }

  function current_locale() {
    return current_language() ?? 'en';
  }
}

function dhz_get_array( $var = false, $delimiter = ',' ) {

  // is array?
  if( is_array($var) ) {

    return $var;

  }


  // bail early if empty
  if( empty($var) && !is_numeric($var) ) {

    return array();

  }


  // string
  if( is_string($var) && $delimiter ) {

    return explode($delimiter, $var);

  }


  // place in array
  return array( $var );

}

new acf_field_relationship_multisite();

?>
