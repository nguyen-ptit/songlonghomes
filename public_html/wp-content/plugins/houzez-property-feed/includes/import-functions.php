<?php

function get_import_settings_from_id( $import_id )
{
    $options = get_option( 'houzez_property_feed' , array() );
    $imports = ( isset($options['imports']) && is_array($options['imports']) && !empty($options['imports']) ) ? $options['imports'] : array();

    if ( isset($imports[$import_id]) )
    {
        return $imports[$import_id];
    }

    return false;
}

function convert_old_field_mapping_to_new( $field_mapping_rules )
{
    $old_style = false;

    foreach ( $field_mapping_rules as $rule )
    {
        if ( isset($rule['field']) )
        {
            $old_style = true;
            break;
        }
    }

    if ( $old_style === true )
    {
        // need to convert
        $new_field_mapping_rules = array();
        foreach ( $field_mapping_rules as $rule )
        {
            if ( $rule['result'] == '{field_value}' )
            {
                $rule['result'] = '{' . $rule['field'] . '}';
            }

            $new_field_mapping_rules[] = array(
                'houzez_field' => $rule['houzez_field'],
                'result' => $rule['result'],
                'rules' => array(
                    array(
                        'field' => $rule['field'],
                        'equal' => $rule['equal']
                    )
                )
            );
        }
        $field_mapping_rules = $new_field_mapping_rules;
    }

    return $field_mapping_rules;
}

function get_houzez_fields_for_field_mapping()
{
    $houzez_fields = array(
        // Post Fields
        'post_title' => array( 'type' => 'post_field', 'label' => __( 'Post Title', 'houzez' ) ),
        'post_excerpt' => array( 'type' => 'post_field', 'label' => __( 'Post Excerpt', 'houzez' ) ),
        'post_content' => array( 'type' => 'post_field', 'label' => __( 'Post Content', 'houzez' ) ),
        // Houzez Fields
        'fave_property_sec_price' => array( 'type' => 'meta', 'label' => __( 'Second Price (Optional)', 'houzez' ) ),
        'fave_property_price_prefix' => array( 'type' => 'meta', 'label' => __( 'Price Prefix', 'houzez' ) ),
        'fave_property_price_postfix' => array( 'type' => 'meta', 'label' => __( 'After The Price', 'houzez' ) ),
        'fave_property_size' => array( 'type' => 'meta', 'label' => __( 'Area Size', 'houzez' ) ),
        'fave_property_size_prefix' => array( 'type' => 'meta', 'label' => __( 'Size Postfix', 'houzez' ) ),
        'fave_property_bedrooms' => array( 'type' => 'meta', 'label' => __( 'Bedrooms', 'houzez' ) ),
        'fave_property_rooms' => array( 'type' => 'meta', 'label' => __( 'Rooms', 'houzez' ) ),
        'fave_property_bathrooms' => array( 'type' => 'meta', 'label' => __( 'Bathrooms', 'houzez' ) ),
        'fave_property_garage' => array( 'type' => 'meta','label' =>  __( 'Garages', 'houzez' ) ),
        'fave_property_garage_size' => array( 'type' => 'meta', 'label' => __( 'Garage Size', 'houzez' ) ),
        'fave_property_year' => array( 'type' => 'meta', 'label' => __( 'Year Built', 'houzez' ) ),
        'fave_property_id' => array( 'type' => 'meta', 'label' => __( 'Property ID', 'houzez' ) ),
        'fave_property_address' => array( 'type' => 'meta', 'label' => __( 'Street Address', 'houzez' ) ),
        'fave_property_zip' => array( 'type' => 'meta', 'label' => __( 'Zip/Postal Code', 'houzez' ) ),
        'fave_property_disclaimer' => array( 'type' => 'meta', 'label' => __( 'Disclaimer', 'houzez' ) ),
        'fave_video_url' => array( 'type' => 'meta', 'label' => __( 'Video URL', 'houzez' ) ),
        'fave_virtual_tour' => array( 'type' => 'meta', 'label' => __( '360° Virtual Tour', 'houzez' ) ),
        'fave_energy_class' => array( 'type' => 'meta', 'label' => __( 'Energy Class', 'houzez' ) ),
        'fave_energy_global_index' => array( 'type' => 'meta', 'label' => __( 'Global Energy Performance Index', 'houzez' ) ),
        'fave_renewable_energy_global_index' => array( 'type' => 'meta', 'label' => __( 'Renewable energy performance index', 'houzez' ) ),
        'fave_energy_performance' => array( 'type' => 'meta', 'label' => __( 'Energy performance of the building', 'houzez' ) ),
        'fave_epc_current_rating' => array( 'type' => 'meta', 'label' => __( 'EPC Current Rating', 'houzez' ) ),
        'fave_epc_potential_rating' => array( 'type' => 'meta', 'label' => __( 'EPC Potential Rating', 'houzez' ) ),
        'fave_property_land' => array( 'type' => 'meta', 'label' => __( 'Land Area', 'houzez' ) ),
        'fave_property_land_postfix' => array( 'type' => 'meta', 'label' => __( 'Land Area Size Postfix', 'houzez' ) ),
        'fave_property_price' => array( 'type' => 'meta', 'label' => __( 'Sale or Rent Price', 'houzez' ) ),
    );

    // add any fields from field builder
    $houzez_fields_builder = new Houzez_Fields_Builder();
    $houzez_fields_built = $houzez_fields_builder::get_form_fields();

    if ( $houzez_fields_built !== FALSE && is_array($houzez_fields_built) && !empty($houzez_fields_built) )
    {
        foreach ( $houzez_fields_built as $field_build )
        {
            $houzez_fields['fave_' . $field_build->field_id] = array( 'type' => 'meta', 'label' => __( $field_build->label, 'houzez' ) );
        }
    }

    $taxonomies = array(
        'property_type' => array( 'type' => 'taxonomy', 'label' => __( 'Property Type', 'houzez' ) ),
        'property_status' => array( 'type' => 'taxonomy', 'label' => __( 'Status', 'houzez' ) ),
    );

    for ( $i = 0; $i < apply_filters( 'houzez_property_feed_field_mapping_feature_count', 10 ); ++$i )
    {
        $taxonomies['property_feature[' . $i . ']'] = array( 'type' => 'taxonomy', 'label' => __( 'Property Feature', 'houzez' ) . ' ' . ( $i + 1 ) );
    }

    $houzez_tax_settings = get_option('houzez_tax_settings', array() );
    if ( !isset($houzez_tax_settings['property_city']) || ( isset($houzez_tax_settings['property_city']) && $houzez_tax_settings['property_city'] != 'disabled' ) )
    {
        $taxonomies['property_city'] = array( 'type' => 'taxonomy', 'label' => __( 'City', 'houzez' ) );
    }
    if ( !isset($houzez_tax_settings['property_area']) || ( isset($houzez_tax_settings['property_area']) && $houzez_tax_settings['property_area'] != 'disabled' ) )
    {
        $taxonomies['property_area'] = array( 'type' => 'taxonomy', 'label' => __( 'Area', 'houzez' ) );
    }
    if ( !isset($houzez_tax_settings['property_state']) || ( isset($houzez_tax_settings['property_state']) && $houzez_tax_settings['property_state'] != 'disabled' ) )
    {
        $taxonomies['property_state'] = array( 'type' => 'taxonomy', 'label' => __( 'County / State', 'houzez' ) );
    }
    if ( !isset($houzez_tax_settings['property_country']) || ( isset($houzez_tax_settings['property_country']) && $houzez_tax_settings['property_country'] != 'disabled' ) )
    {
        $taxonomies['property_country'] = array( 'type' => 'taxonomy', 'label' => __( 'Country', 'houzez' ) );
    }

    $houzez_fields = array_merge($houzez_fields, $taxonomies);

    $houzez_fields = apply_filters( 'houzez_property_feed_field_mapping_houzez_fields', $houzez_fields );

    $houzez_fields = houzez_property_feed_array_msort( $houzez_fields, array( 'label' => SORT_ASC ) );

    return $houzez_fields;
}