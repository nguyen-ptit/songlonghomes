<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Houzez Property Feed Import Functions
 */
class Houzez_Property_Feed_Import {

	public function __construct() {

        add_action( 'admin_init', array( $this, 'check_not_multiple_if_no_pro') );

        add_action( 'admin_init', array( $this, 'save_import_settings') );

        add_action( 'admin_init', array( $this, 'toggle_import_running_status') );

        add_action( 'admin_init', array( $this, 'delete_import') );

        add_action( "houzez_property_feed_property_imported", array( $this, 'perform_field_mapping' ), 1, 3 );
        add_action( 'houzez_property_feed_property_imported', array( $this, 'set_generic_houzez_property_data'), 1, 3 );

        add_filter( 'houzez_property_feed_xml_mapped_field_value', array( $this, 'get_xml_mapped_field_value' ), 1, 4 );
        add_filter( 'houzez_property_feed_csv_mapped_field_value', array( $this, 'get_csv_mapped_field_value' ), 1, 4 );

        add_action( 'add_meta_boxes', array( $this, 'import_data_meta_box') );

	}

    public function check_not_multiple_if_no_pro()
    {
        if ( isset($_GET['action']) && $_GET['action'] == 'addimport' )
        {
            if ( apply_filters( 'houzez_property_feed_pro_active', false ) !== true ) 
            {
                $options = get_option( 'houzez_property_feed', array() );
                $imports = ( isset($options['imports']) && is_array($options['imports']) && !empty($options['imports']) ) ? $options['imports'] : array();

                foreach ( $imports as $key => $import )
                {
                    if ( $imports[$key]['deleted'] && $imports[$key]['deleted'] === true )
                    {
                        unset( $imports[$key] );
                    }
                }

                if ( count($imports) >=1 )
                {
                    wp_redirect( admin_url( 'admin.php?page=houzez-property-feed-import&hpferrormessage=' . urlencode(__( 'Maximum number of imports reached. Upgrade to PRO if wanting to benfit from multiple imports and more', 'houzezpropertyfeed' ) ) ) );
                    die();
                }
            }
        }
    }

    public function save_import_settings()
    {
        if ( !isset($_POST['save_import_settings']) )
        {
            return;
        }

        if ( !isset($_POST['_wpnonce']) || ( isset($_POST['_wpnonce']) && !wp_verify_nonce( $_POST['_wpnonce'], 'save-import-settings' ) ) ) 
        {
            die( __( "Failed security check", 'houzezpropertyfeed' ) );
        }

        // ready to save
        $import_id = !empty($_POST['import_id']) ? (int)$_POST['import_id'] : time();

        $options = get_option( 'houzez_property_feed' , array() );
        if ( !is_array($options) ) { $options = array(); }
        if ( !is_array($options['imports']) ) { $options['imports'] = array(); }
        if ( !is_array($options['imports'][$import_id]) ) { $options['imports'][$import_id] = array(); }

        $format = sanitize_text_field($_POST['format']);

        $running = ( isset($_POST['running']) && sanitize_text_field($_POST['running']) == 'yes' ) ? true : false;

        $agent_display_option = ( isset($_POST['agent_display_option']) ) ? sanitize_text_field($_POST['agent_display_option']) : 'author_info';

        $import_options = array(
            'running' => $running,
            'format' => $format,
            'frequency' => sanitize_text_field($_POST['frequency']), // might want to validate this is not a pro frequency
            'create_location_taxonomy_terms' => ( isset($_POST['create_location_taxonomy_terms']) && sanitize_text_field($_POST['create_location_taxonomy_terms']) == 'yes' ) ? true : false,
            'property_city_address_field' => ( isset($_POST['property_city_address_field']) ) ? sanitize_text_field($_POST['property_city_address_field']) : true,
            'property_area_address_field' => ( isset($_POST['property_area_address_field']) ) ? sanitize_text_field($_POST['property_area_address_field']) : true,
            'property_state_address_field' => ( isset($_POST['property_state_address_field']) ) ? sanitize_text_field($_POST['property_state_address_field']) : true,
            'agent_display_option' => $agent_display_option,
        );

        $rules = array();
        switch ( $agent_display_option )
        {
            case "author_info":
            case "agent_info":
            case "agency_info":
            {
                if ( 
                    isset($_POST[$agent_display_option . '_rules_field']) && 
                    is_array($_POST[$agent_display_option . '_rules_field']) && 
                    count($_POST[$agent_display_option . '_rules_field']) > 1 // more than 1 to ignore template
                )
                {
                    $rule_i = 0;
                    foreach ( $_POST[$agent_display_option . '_rules_field'] as $j => $field )
                    {
                        if ( $rule_i > 0 )
                        {
                            if ( 
                                !empty($field) && 
                                !empty($_POST[$agent_display_option . '_rules_equal'][$j]) && 
                                !empty($_POST[$agent_display_option . '_rules_result'][$j]) 
                            )
                            {
                                $rules[] = array(
                                    'field' => $field,
                                    'equal' => sanitize_text_field($_POST[$agent_display_option . '_rules_equal'][$j]),
                                    'result' => sanitize_text_field($_POST[$agent_display_option . '_rules_result'][$j]),
                                );
                            }
                        }

                        ++$rule_i;
                    }
                }
                break;
            }
        }
        $import_options['agent_display_option_rules'] = $rules;

        $rules = array();
        if ( 
            isset($_POST['field_mapping_rules']) && 
            is_array($_POST['field_mapping_rules']) && 
            count($_POST['field_mapping_rules']) > 1 // more than 1 to ignore template
        )
        {
            $rule_i = 0;
            foreach ( $_POST['field_mapping_rules'] as $j => $field )
            {
                if ( $rule_i > 0 )
                {
                    $rules[$rule_i-1] = array(
                        'houzez_field' => sanitize_text_field($field['houzez_field']),
                        'result' => sanitize_text_field($field['result']),
                        'rules' => array(),
                    );

                    unset($field['houzez_field']);
                    unset($field['result']);

                    foreach ( $field as $i => $rule_fields )
                    {
                        foreach ( $rule_fields as $k => $rule_field )
                        {
                            $rules[$rule_i-1]['rules'][$k][$i] = sanitize_text_field($rule_field);
                        }
                    }
                }

                ++$rule_i;
            }
        }
        $import_options['field_mapping_rules'] = $rules;

        // Save core format fields (API Key, XML URL etc)
        $formats = get_houzez_property_feed_import_formats();
        if ( isset($formats[$format]) )
        {
            if ( isset($formats[$format]['fields']) && !empty($formats[$format]['fields']) )
            {
                foreach ( $formats[$format]['fields'] as $field )
                {   
                    if ( isset($field['type']) && $field['type'] != 'html' )
                    {
                        $field_value = '';
                        if ( isset($_POST[$format . '_' . $field['id']]) && !empty($_POST[$format . '_' . $field['id']]) )
                        {
                            $field_value = sanitize_text_field($_POST[$format . '_' . $field['id']]);
                        }
                        if ( $field['id'] == 'property_node_options' || $field['id'] == 'property_field_options' )
                        {
                            $field_value = stripslashes($field_value);
                        }
                        $import_options[$field['id']] = $field_value;
                    }
                }
            }
        }

        $import_mappings = array();

        if ( isset($_POST['taxonomy_mapping']) && is_array($_POST['taxonomy_mapping']) && !empty($_POST['taxonomy_mapping']) )
        {
            foreach ( $_POST['taxonomy_mapping'] as $taxonomy => $mappings )
            {
                $taxonomy = sanitize_text_field($taxonomy);

                $import_mappings[$taxonomy] = array();

                if ( is_array($mappings) && !empty($mappings) )
                {
                    foreach ( $mappings as $crm_value => $term_id )
                    {
                        if ( !empty((int)$term_id) )
                        {
                            $import_mappings[$taxonomy][$crm_value] = (int)$term_id;
                        }
                    }
                }

                if ( isset($_POST['custom_mapping'][$taxonomy]) )
                {
                    foreach ( $_POST['custom_mapping'][$taxonomy] as $key => $custom_mapping )
                    {
                        if ( trim($custom_mapping) != '' )
                        {
                            if ( isset($_POST['custom_mapping_value'][$taxonomy][$key]) && trim($_POST['custom_mapping_value'][$taxonomy][$key]) != '' )
                            {
                                $import_mappings[$taxonomy][$custom_mapping] = $_POST['custom_mapping_value'][$taxonomy][$key];
                            }
                        }
                    }
                }
            }
        }

        $import_options['mappings'] = $import_mappings;

        if ( isset($_POST['image_fields']) )
        {
            $import_options['image_fields'] = sanitize_textarea_field($_POST['image_fields']);
        }
        if ( isset($_POST['floorplan_fields']) )
        {
            $import_options['floorplan_fields'] = sanitize_textarea_field($_POST['floorplan_fields']);
        }
        if ( isset($_POST['document_fields']) )
        {
            $import_options['document_fields'] = sanitize_textarea_field($_POST['document_fields']);
        }
        $import_options['media_download_clause'] = ( isset($_POST['media_download_clause']) ? sanitize_text_field($_POST['media_download_clause']) : 'url_change' );

        $options['imports'][$import_id] = $import_options;

        update_option( 'houzez_property_feed', $options );

        wp_redirect( admin_url( 'admin.php?page=houzez-property-feed-import&hpfsuccessmessage=' . __( 'Import details saved', 'houzezpropertyfeed' ) ) );
        die();
    }

    public function toggle_import_running_status()
    {
        if ( isset($_GET['action']) && in_array($_GET['action'], array("startimport", "pauseimport")) && isset($_GET['import_id']) )
        {
            $import_id = !empty($_GET['import_id']) ? (int)$_GET['import_id'] : '';

            if ( empty($import_id) )
            {
                wp_redirect( admin_url( 'admin.php?page=houzez-property-feed-import&hpferrormessage=' . __( 'No import passed', 'houzezpropertyfeed' ) ) );
                die();
            }

            $options = get_option( 'houzez_property_feed', array() );
            
            if ( !isset($options['imports'][$import_id]) )
            {
                wp_redirect( admin_url( 'admin.php?page=houzez-property-feed-import&hpferrormessage=' . __( 'Import not found', 'houzezpropertyfeed' ) ) );
                die();
            }

            switch ( sanitize_text_field($_GET['action']) )
            {
                case "startimport":
                {   
                    // Check one imports not already active if not using pro
                    if ( apply_filters( 'houzez_property_feed_pro_active', false ) !== true ) 
                    {
                        foreach ( $options['imports'] as $import )
                        {
                            if ( ( !isset($import['deleted']) || ( isset($import['deleted']) && $import['deleted'] !== true ) ) && isset($import['running']) && $import['running'] === true )
                            {
                                wp_redirect( admin_url( 'admin.php?page=houzez-property-feed-import&hpferrormessage=' . urlencode(__( 'Maximum number of running imports reached. Upgrade to PRO if wanting to benfit from multiple imports and more', 'houzezpropertyfeed' ) ) ) );
                                die();
                            }
                        }
                    }

                    $options['imports'][$import_id]['running'] = true;

                    update_option( 'houzez_property_feed', $options );

                    wp_redirect( admin_url( 'admin.php?page=houzez-property-feed-import&hpfsuccessmessage=' . __( 'Import started', 'houzezpropertyfeed' ) ) );
                    die();

                    break;

                }
                case "pauseimport":
                {
                    $options['imports'][$import_id]['running'] = false;

                    update_option( 'houzez_property_feed', $options );

                    wp_redirect( admin_url( 'admin.php?page=houzez-property-feed-import&hpfsuccessmessage=' . __( 'Import paused', 'houzezpropertyfeed' ) ) );
                    die();

                    break;

                }
            }
        }
    }

    public function delete_import()
    {
        if ( isset($_GET['action']) && $_GET['action'] == 'deleteimport' && isset($_GET['import_id']) )
        {
            $import_id = !empty($_GET['import_id']) ? (int)$_GET['import_id'] : '';

            if ( empty($import_id) )
            {
                wp_redirect( admin_url( 'admin.php?page=houzez-property-feed-import&hpferrormessage=' . __( 'No import passed', 'houzezpropertyfeed' ) ) );
                die();
            }

            $options = get_option( 'houzez_property_feed', array() );
            
            if ( !isset($options['imports'][$import_id]) )
            {
                wp_redirect( admin_url( 'admin.php?page=houzez-property-feed-import&hpferrormessage=' . __( 'Import not found', 'houzezpropertyfeed' ) ) );
                die();
            }

            $options['imports'][$import_id]['running'] = false;
            $options['imports'][$import_id]['deleted'] = true;

            update_option( 'houzez_property_feed', $options );

            wp_redirect( admin_url( 'admin.php?page=houzez-property-feed-import&hpfsuccessmessage=' . __( 'Import deleted successfully', 'houzezpropertyfeed' ) ) );
            die();
        }
    }

    public function perform_field_mapping( $post_id, $property, $import_id )
    {
        $import_settings = get_import_settings_from_id( $import_id );

        if ( $import_settings === false )
        {
            return false;
        }

        if ( !isset($import_settings['field_mapping_rules']) )
        {
            return false;
        }

        if ( empty($import_settings['field_mapping_rules']) )
        {
            return false;
        }

        $original_property = $property;

        if ( is_object($property) )
        {
            $property = SimpleXML2ArrayWithCDATASupport($property);
        }

        $import_settings['field_mapping_rules'] = convert_old_field_mapping_to_new( $import_settings['field_mapping_rules'] );

        $post_fields_to_update = array();

        $property_node = '';
        if ( isset($import_settings['property_node']) )
        {
            $property_node = $import_settings['property_node'];
            $explode_property_node = explode("/", $property_node);
            $property_node = $explode_property_node[count($explode_property_node)-1];
        }

        $taxonomies_with_multiple_values = array();

        foreach ( $import_settings['field_mapping_rules'] as $and_rules )
        {
            // field
            // equal
            // houzez_field
            // result
            $rules_met = 0;
            foreach ( $and_rules['rules'] as $i => $rule )
            {
                if ( is_object($original_property) && substr($rule['field'], 0, 1) == '/' )
                {
                    // Using XPATH syntax
                    $values_to_check = $original_property->xpath('/' . $property_node . $rule['field']);
                    if ( $values_to_check === FALSE || empty($values_to_check) )
                    {
                        continue;
                    }

                    $found = false;
                    foreach ( $values_to_check as $value_to_check )
                    {
                        if ( $rule['equal'] == '*' )
                        {
                            $found = true;
                        }
                        elseif ( $value_to_check == $rule['equal'] )
                        {
                            $found = true;
                        }
                        
                    }
                    if ( $found )
                    {
                        ++$rules_met;
                    }
                }
                else
                {
                    // loop through all fields in data and see if $rule['field'] is found
                    if ( is_array($property) )
                    {
                        $value_to_check = check_array_for_matching_key( $property, $rule['field'] );

                        if ( $value_to_check === false )
                        {
                            continue;
                        }

                        // we found a field with this key
                        if ( $rule['equal'] != '*' && $value_to_check != $rule['equal'] )
                        {
                            continue;
                        }

                        ++$rules_met;
                    }
                }
            }

            if ( $rules_met == count($and_rules['rules']) )
            {
                $result = $and_rules['result'];

                preg_match_all('/{[^}]*}/', $and_rules['result'], $matches);
                if ( $matches !== FALSE && isset($matches[0]) && is_array($matches[0]) && !empty($matches[0]) )
                {
                    foreach ( $matches[0] as $match )
                    {
                        $field_name = str_replace(array("{", "}"), "", $match);
                        $value_to_check = '';

                        if ( is_object($original_property) && substr($field_name, 0, 1) == '/' )
                        {
                            // Using XPATH syntax
                            $values_to_check = $original_property->xpath('/' . $property_node . $field_name);
                            if ( $values_to_check !== false && is_array($values_to_check) && !empty($values_to_check) )
                            {
                                $value_to_check = (string)$values_to_check[0];
                            }
                        }
                        else
                        {
                            $value_to_check = check_array_for_matching_key( $property, $field_name );

                            if ( $value_to_check === false )
                            {
                                $value_to_check = '';
                            }
                        }

                        $result = str_replace($match, $value_to_check, $result);
                    }
                }

                $houzez_fields = get_houzez_fields_for_field_mapping();

                // we found a matching field with the required value
                if ( isset($houzez_fields[$and_rules['houzez_field']]) && $houzez_fields[$and_rules['houzez_field']]['type'] == 'post_field' )
                {
                    $post_fields_to_update[$and_rules['houzez_field']] = $result;
                }
                elseif ( isset($houzez_fields[$and_rules['houzez_field']]) && $houzez_fields[$and_rules['houzez_field']]['type'] == 'taxonomy' )
                {
                    // only do for taxonomies that have a single value, else we'll do multiple ones later
                    preg_match_all('/\[[^\]]*\]/', $and_rules['houzez_field'], $matches);
                    if ( $matches !== FALSE && isset($matches[0]) && is_array($matches[0]) && !empty($matches[0]) )
                    {
                        foreach ( $matches[0] as $match )
                        {
                            $taxonomy = str_replace($match, '', $and_rules['houzez_field']);

                            if ( !isset($taxonomies_with_multiple_values[$taxonomy]) ) { $taxonomies_with_multiple_values[$taxonomy] = array(); }

                            // check term exists and get termID as wp_set_object_terms() requires the ID
                            $term_id = '';
                            if ( $taxonomy == 'property_feature' )
                            {
                                // create if not exists
                                $term = term_exists( $result, $taxonomy );
                                if ( $term !== 0 && $term !== null && isset($term['term_id']) )
                                {
                                    $term_id = (int)$term['term_id'];
                                }
                                else
                                {
                                    $term = wp_insert_term( $result, $taxonomy );
                                    if ( is_array($term) && isset($term['term_id']) )
                                    {
                                        $term_id = (int)$term['term_id'];
                                    }
                                }
                            }
                            else
                            {
                                $term = term_exists( $result, $taxonomy );
                                if ( $term !== 0 && $term !== null && isset($term['term_id']) )
                                {
                                    $term_id = (int)$term['term_id'];
                                }
                            }

                            if ( !empty($term_id) )
                            {
                                $taxonomies_with_multiple_values[$taxonomy][] = $term_id;
                            }
                        }
                    }
                    else
                    {
                        wp_set_object_terms( $post_id, $result, $and_rules['houzez_field'] );
                    }
                }
                else
                {
                    update_post_meta( $post_id, $and_rules['houzez_field'], $result );
                }
            }
        }

        if ( !empty($taxonomies_with_multiple_values) )
        {
            foreach ( $taxonomies_with_multiple_values as $taxonomy => $taxonomy_values )
            {
                if ( !empty($taxonomy_values) )
                {
                    wp_set_object_terms( $post_id, $taxonomy_values, $taxonomy );
                }
            }
        }

        // not doing for XML format as should be done inside XML import class
        if ( $import_settings['format'] != 'xml' )
        {
            if ( !empty($post_fields_to_update) )
            {
                wp_update_post($post_fields_to_update, TRUE);
            }
        }
    }

    public function set_generic_houzez_property_data( $post_id, $property, $import_id )
    {
        add_post_meta( $post_id, 'fave_loggedintoview', '0', TRUE );
        add_post_meta( $post_id, 'fave_single_content_area', 'global', TRUE );
        add_post_meta( $post_id, 'fave_single_top_area', 'global', TRUE );
        add_post_meta( $post_id, 'fave_prop_homeslider', 'no', TRUE );
    }

    public function get_xml_mapped_field_value( $value, $property, $field_name, $import_id )
    {
        $import_settings = get_import_settings_from_id( $import_id );

        if ( $import_settings === false )
        {
            return $value;
        }

        if ( !isset($import_settings['field_mapping_rules']) )
        {
            return $value;
        }

        if ( empty($import_settings['field_mapping_rules']) )
        {
            return $value;
        }

        $property_node = '';
        if ( isset($import_settings['property_node']) )
        {
            $property_node = $import_settings['property_node'];
            $explode_property_node = explode("/", $property_node);
            $property_node = $explode_property_node[count($explode_property_node)-1];
        }

        foreach ( $import_settings['field_mapping_rules'] as $and_rules )
        {
            if ( $and_rules['houzez_field'] == $field_name )
            {
                // This is the field we're after. Check rules are met
                $rules_met = 0;
                foreach ( $and_rules['rules'] as $i => $rule )
                {
                    if ( is_object($property) && substr($rule['field'], 0, 1) == '/' )
                    {
                        // Using XPATH syntax
                        $values_to_check = $property->xpath('/' . $property_node . $rule['field']);
                        if ( $values_to_check === FALSE || empty($values_to_check) )
                        {
                            continue;
                        }

                        $found = false;
                        foreach ( $values_to_check as $value_to_check )
                        {
                            if ( $rule['equal'] == '*' )
                            {
                                $found = true;
                            }
                            elseif ( $value_to_check == $rule['equal'] )
                            {
                                $found = true;
                            }
                            
                        }
                        if ( $found )
                        {
                            ++$rules_met;
                        }
                    }
                }

                if ( $rules_met == count($and_rules['rules']) )
                {
                    $result = $and_rules['result'];

                    preg_match_all('/{[^}]*}/', $and_rules['result'], $matches);
                    if ( $matches !== FALSE && isset($matches[0]) && is_array($matches[0]) && !empty($matches[0]) )
                    {
                        foreach ( $matches[0] as $match )
                        {
                            $field_name = str_replace(array("{", "}"), "", $match);
                            $value_to_check = '';

                            if ( substr($field_name, 0, 1) == '/' )
                            {
                                // Using XPATH syntax
                                $values_to_check = $property->xpath('/' . $property_node . $field_name);
                                if ( $values_to_check !== false && is_array($values_to_check) && !empty($values_to_check) )
                                {
                                    $value_to_check = (string)$values_to_check[0];
                                }
                            }

                            $result = str_replace($match, $value_to_check, $result);
                        }
                    }

                    return $result;
                }
            }
        }

        return $value;
    }

    public function get_csv_mapped_field_value( $value, $property, $field_name, $import_id )
    {
        $import_settings = get_import_settings_from_id( $import_id );

        if ( $import_settings === false )
        {
            return $value;
        }

        if ( !isset($import_settings['field_mapping_rules']) )
        {
            return $value;
        }

        if ( empty($import_settings['field_mapping_rules']) )
        {
            return $value;
        }

        foreach ( $import_settings['field_mapping_rules'] as $and_rules )
        {
            if ( $and_rules['houzez_field'] == $field_name )
            {
                // This is the field we're after. Check rules are met
                $rules_met = 0;
                foreach ( $and_rules['rules'] as $i => $rule )
                {
                    $value_to_check = '';
                    if ( isset($property[$rule['field']]) )
                    {
                        $value_to_check = $property[$rule['field']];
                    }

                    $found = false;
                    
                    if ( $rule['equal'] == '*' )
                    {
                        $found = true;
                    }
                    elseif ( $value_to_check == $rule['equal'] )
                    {
                        $found = true;
                    }

                    if ( $found )
                    {
                        ++$rules_met;
                    }
                }

                if ( $rules_met == count($and_rules['rules']) )
                {
                    $result = $and_rules['result'];

                    preg_match_all('/{[^}]*}/', $and_rules['result'], $matches);
                    if ( $matches !== FALSE && isset($matches[0]) && is_array($matches[0]) && !empty($matches[0]) )
                    {
                        foreach ( $matches[0] as $match )
                        {
                            $field_name = str_replace(array("{", "}"), "", $match);
                            $value_to_check = '';

                            if ( isset($property[$field_name]) )
                            {
                                $value_to_check = $property[$field_name];
                            }

                            $result = str_replace($match, $value_to_check, $result);
                        }
                    }

                    return $result;
                }
            }
        }

        return $value;
    }

    public function import_data_meta_box()
    {
        $screen = get_current_screen();
        if ( isset($screen->post_type) && $screen->post_type == 'property' )
        {
            if ( isset($screen->action) && $screen->action == 'add' )
            {

            }
            else
            {
                add_meta_box( 'houzezpropertyfeed-import-data', __( 'Import Data', 'houzezpropertyfeed' ), array( $this, 'output_import_data_meta_box'), 'property', 'advanced', 'low' );
            }
        }
    }

    public function output_import_data_meta_box( $post )
    {
        if ( isset($post->ID) )
        {
            if ( get_post_meta( $post->ID, '_property_import_data', TRUE ) != '' )
            {
                echo '<textarea readonly rows="20" style="width:100%;">' . get_post_meta( $post->ID, '_property_import_data', TRUE )  . '</textarea>';
            }
            else
            {
                echo __( 'No import data to display', 'houzezpropertyfeed' );
            }
        }
    }
}

new Houzez_Property_Feed_Import();