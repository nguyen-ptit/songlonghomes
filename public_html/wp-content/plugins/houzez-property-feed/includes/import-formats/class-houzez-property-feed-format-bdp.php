<?php
/**
 * Class for managing the import process of a BDP JSON file
 *
 * @package WordPress
 */
if ( class_exists( 'Houzez_Property_Feed_Process' ) ) {

class Houzez_Property_Feed_Format_Bdp extends Houzez_Property_Feed_Process {

	public function __construct( $instance_id = '', $import_id = '' )
	{
		$this->instance_id = $instance_id;
		$this->import_id = $import_id;

		if ( $this->instance_id != '' && isset($_GET['custom_property_import_cron']) )
	    {
	    	$current_user = wp_get_current_user();

	    	$this->log("Executed manually by " . ( ( isset($current_user->display_name) ) ? $current_user->display_name : '' ) );
	    }
	}

	public function parse()
	{
		$this->properties = array(); // Reset properties in the event we're importing multiple files

		$this->log("Parsing properties");

		$import_settings = get_import_settings_from_id( $this->import_id );

		$date = date('r');

        $string_to_sign = "GET\n";
        $string_to_sign .= "\n" . $import_settings['account_id'];
        $string_to_sign .= "\n" . strtolower($date);

        $signature = base64_encode(
            hash_hmac('sha1', utf8_encode($string_to_sign), $import_settings['secret'], true)
        );

        $url = ( isset($import_settings['base_url']) && !empty($import_settings['base_url']) ) ? trim($import_settings['base_url'], '/') : 'https://api.bdphq.com';
        $url .= "/restapi/props";

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $headers = array(
           "AccountId: " . $import_settings['account_id'],
           "Date: " . $date,
           "Authorization: BDWS " . $import_settings['api_key'] . ":" . $signature
        );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        //for debug only!
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($curl);
        curl_close($curl);

        $json = json_decode( $response, TRUE );

        $limit = apply_filters( "houzez_property_feed_property_limit", 25 );

        $property_i = 1;

        $statuses_to_exclude = apply_filters( 'houzez_property_feed_bdp_exclude_statuses', array('Sold', 'Withdrawn') );

        if ( $json !== null && isset( $json['properties'] ) && is_array( $json['properties'] ) )
        {
            foreach ($json['properties'] as $property)
            {
                $property_id = $property['property_id'];

                $url = ( isset($import_settings['base_url']) && !empty($import_settings['base_url']) ) ? trim($import_settings['base_url'], '/') : 'https://api.bdphq.com';
                $url .= "/restapi/property/" . $property_id;

                $curl = curl_init($url);
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

                $headers = array(
                   "AccountId: " . $import_settings['account_id'],
                   "Date: " . $date,
                   "Authorization: BDWS " . $import_settings['api_key'] . ":" . $signature
                );
                curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
                //for debug only!
                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

                $property_response = curl_exec($curl);
                curl_close($curl);

                $property_json = json_decode( $property_response, TRUE );

                if ( $property_json !== null && is_array( $property_json ) )
                {
                	if ( 
                		( isset($property['sellingStatus']) && !empty($property['sellingStatus']) && in_array($property['sellingStatus'], $statuses_to_exclude) )
                		||
                		( isset($property['letting']['status']) && !empty($property['letting']['status']) && in_array($property['letting']['status'], $statuses_to_exclude) )
                	)
					{

					}
					else
					{
	                	if ( $property_i <= $limit )
	                	{
		                    $this->properties[] = $property_json;
		                }
		            }
                }
                else
                {
                    // Failed to parse JSON
                    $this->log_error( 'Failed to parse property ' . $property_id . ' JSON file: ' . $property_response );
                    return false;
                }

                ++$property_i;
            }
        }
        else
        {
            // Failed to parse JSON
            $this->log_error( 'Failed to parse JSON file: ' . $response );
            return false;
        }

		if ( empty($this->properties) )
		{
			$this->log_error( 'No properties found. We\'re not going to continue as this could likely be wrong and all properties will get removed if we continue.' );

			return false;
		}

		return true;
	}

	public function import()
	{
		global $wpdb;

		$imported_ref_key = ( ( $this->import_id != '' ) ? '_imported_ref_' . $this->import_id : '_imported_ref' );

		$import_settings = get_import_settings_from_id( $this->import_id );

		$this->log( 'Starting import' );

		$this->import_start();

		do_action( "houzez_property_feed_pre_import_properties", $this->properties, $this->import_id );
        do_action( "houzez_property_feed_pre_import_properties_bdp", $this->properties, $this->import_id );

        $this->properties = apply_filters( "houzez_property_feed_properties_due_import", $this->properties, $this->import_id );
        $this->properties = apply_filters( "houzez_property_feed_properties_due_import_bdp", $this->properties, $this->import_id );

        $limit = apply_filters( "houzez_property_feed_property_limit", 25 );
        $additional_message = '';
        if ( $limit !== false )
        {
        	$this->properties = array_slice( $this->properties, 0, $limit );
        	$additional_message = '. <a href="https://houzezpropertyfeed.com/#pricing" target="_blank">Upgrade to PRO</a> to import unlimited properties';
        }

		$this->log( 'Beginning to loop through ' . count($this->properties) . ' properties' . $additional_message );

		$property_row = 1;
		foreach ( $this->properties as $property )
		{
			$this->log( 'Importing property ' . $property_row . ' with reference ' . $property['property_id'], $property['property_id'] );

			$inserted_updated = false;

			$args = array(
	            'post_type' => 'property',
	            'posts_per_page' => 1,
	            'post_status' => 'any',
	            'meta_query' => array(
	            	array(
		            	'key' => $imported_ref_key,
		            	'value' => $property['property_id']
		            )
	            )
	        );
	        $property_query = new WP_Query($args);

	        $display_address = $property['dispAddress'];

	        $post_content = '';
	        /*if ( isset( $property['descText'] ) && !empty( $property['descText'] ) )
            {
            	$post_content = $property['descText'];
            }

            if ( isset( $property['rooms'] ) && is_array( $property['rooms'] ) )
            {
                foreach( $property['rooms'] as $room )
                {
                    if ( $room['active'] == 1 )
                    {
                    	$room_content = ( isset($room['roomName']) && !empty($room['roomName']) ) ? '<strong>' . $room['roomName'] . '</strong>' : '';
						$room_content .= ( isset($room['roomWidth']) && !empty($room['roomWidth']) ) ? ' (' . $room['roomWidth'] . ' x ' . $room['roomLength'] . ')' : '';
						if ( isset($room['roomDesc']) && !empty($room['roomDesc']) ) 
						{
							if ( !empty($room_content) ) { $room_content .= '<br>'; }
							$room_content .= $room['roomDesc'];
						}
						
						if ( !empty($room_content) )
						{
							$post_content .= '<p>' . $room_content . '</p>';
						}
                    }
                }
            }*/
	        
	        if ($property_query->have_posts())
	        {
	        	$this->log( 'This property has been imported before. Updating it', $property['property_id'] );

	        	// We've imported this property before
	            while ($property_query->have_posts())
	            {
	                $property_query->the_post();

	                $post_id = get_the_ID();

	                $my_post = array(
				    	'ID'          	 => $post_id,
				    	'post_title'     => wp_strip_all_tags( $display_address ),
				    	'post_excerpt'   => $property['summaryText'],
				    	'post_content' 	 => $post_content,
				    	'post_status'    => 'publish',
				  	);

				 	// Update the post into the database
				    $post_id = wp_update_post( $my_post, true );

				    if ( is_wp_error( $post_id ) ) 
					{
						$this->log_error( 'Failed to update post. The error was as follows: ' . $post_id->get_error_message(), $property['property_id'] );
					}
					else
					{
						$inserted_updated = 'updated';
					}
	            }
	        }
	        else
	        {
	        	$this->log( 'This property hasn\'t been imported before. Inserting it', $property['property_id'] );

	        	// We've not imported this property before
				$postdata = array(
					'post_excerpt'   => $property['summaryText'],
					'post_content' 	 => $post_content,
					'post_title'     => wp_strip_all_tags( $display_address ),
					'post_status'    => 'publish',
					'post_type'      => 'property',
					'comment_status' => 'closed',
				);

				$post_id = wp_insert_post( $postdata, true );

				if ( is_wp_error( $post_id ) ) 
				{
					$this->log_error( 'Failed to insert post. The error was as follows: ' . $post_id->get_error_message(), $property['property_id'] );
				}
				else
				{
					$inserted_updated = 'inserted';
				}
			}
			$property_query->reset_postdata();

			if ( $inserted_updated !== false )
			{
				// Inserted property ok. Continue

				if ( $inserted_updated == 'updated' )
				{
					// Get all meta data so we can compare before and after to see what's changed
					$metadata_before = get_metadata('post', $post_id, '', true);

					// Get all taxonomy/term data
					$taxonomy_terms_before = array();
					$taxonomy_names = get_post_taxonomies( $post_id );
					foreach ( $taxonomy_names as $taxonomy_name )
					{
						$taxonomy_terms_before[$taxonomy_name] = wp_get_post_terms( $post_id, $taxonomy_name, array('fields' => 'ids') );
					}
				}

				$this->log( 'Successfully ' . $inserted_updated . ' post', $property['property_id'], $post_id );

				update_post_meta( $post_id, $imported_ref_key, $property['property_id'] );

				update_post_meta( $post_id, '_property_import_data', json_encode($property, JSON_PRETTY_PRINT) );

				$department = !empty($property['letting']) ? 'residential-lettings' : 'residential-sales';

				$poa = false;
				if ( 
					strpos( strtolower($property['priceTypeLabel']), 'application' ) !== FALSE || 
					strpos( strtolower($property['priceTypeLabel']), 'poa' ) !== FALSE
				)
				{
					$poa = true;
				}

				if ( $poa === true ) 
                {
                    update_post_meta( $post_id, 'fave_property_price', 'POA');
                    update_post_meta( $post_id, 'fave_property_price_postfix', '' );
                }
                else
                {
                    update_post_meta( $post_id, 'fave_property_price_prefix', ( ( $department == 'residential-sales' && isset($property['priceTypeLabel']) ) ? $property['priceTypeLabel'] : '' ) );
                    update_post_meta( $post_id, 'fave_property_price', ( isset( $property['floatAskingPrice'] ) ? $property['floatAskingPrice'] : 0 ) );
                    update_post_meta( $post_id, 'fave_property_price_postfix', ( $department == 'residential-lettings' ? 'pcm' : '' ) );
                }

                update_post_meta( $post_id, 'fave_property_bedrooms', ( ( isset($property['bedRooms']) ) ? $property['bedRooms'] : '' ) );
	            update_post_meta( $post_id, 'fave_property_bathrooms', ( ( isset($property['bathRooms']) ) ? $property['bathRooms'] : '' ) );
	            update_post_meta( $post_id, 'fave_property_rooms', ( ( isset($property['livingRooms']) ) ? $property['livingRooms'] : '' ) );
	            update_post_meta( $post_id, 'fave_property_garage', ( ( isset($property['parkingType']) ) ? $property['parkingType'] : '' ) );
	            update_post_meta( $post_id, 'fave_property_id', $property['property_id'] );

	            $address_parts = array();
	            if ( isset($property['streetName']) && $property['streetName'] != '' )
	            {
	                $address_parts[] = $property['streetName'];
	            }
	            if ( isset($property['addrL1']) && $property['addrL1'] != '' )
	            {
	                $address_parts[] = $property['addrL1'];
	            }
	            if ( isset($property['addrL2']) && $property['addrL2'] != '' )
	            {
	                $address_parts[] = $property['addrL2'];
	            }
	            if ( isset($property['address_County']) && $property['address_County'] != '' )
	            {
	                $address_parts[] = $property['address_County'];
	            }
	            if ( isset($property['postcode']) && $property['postcode'] != '' )
	            {
	                $address_parts[] = $property['postcode'];
	            }

	            update_post_meta( $post_id, 'fave_property_map', '1' );
	            update_post_meta( $post_id, 'fave_property_map_address', implode(", ", $address_parts) );
	            $lat = '';
	            $lng = '';
	            if ( isset($property['lat']) && !empty($property['lat']) )
	            {
	                update_post_meta( $post_id, 'houzez_geolocation_lat', $property['lat'] );
	                $lat = $property['lat'];
	            }
	            if ( isset($property['lng']) && !empty($property['lng']) )
	            {
	                update_post_meta( $post_id, 'houzez_geolocation_long', $property['lng'] );
	                $lng = $property['lng'];
	            }
	            update_post_meta( $post_id, 'fave_property_location', $lat . "," . $lng . ",14" );
	            update_post_meta( $post_id, 'fave_property_country', 'GB' );
	            
	            $address_parts = array();
	            if ( isset($property['streetName']) && $property['streetName'] != '' )
	            {
	                $address_parts[] = $property['streetName'];
	            }
	            update_post_meta( $post_id, 'fave_property_address', implode(", ", $address_parts) );
	            update_post_meta( $post_id, 'fave_property_zip', ( ( isset($property['postcode']) ) ? $property['postcode'] : '' ) );

	            update_post_meta( $post_id, 'fave_featured', ( isset($property['featured']) && $property['featured'] == '1' ) ? '1' : '0' );
	            update_post_meta( $post_id, 'fave_agent_display_option', ( isset($import_settings['agent_display_option']) ? $import_settings['agent_display_option'] : 'none' ) );

	            if ( 
	            	isset($import_settings['agent_display_option']) && 
	            	isset($import_settings['agent_display_option_rules']) && 
	            	is_array($import_settings['agent_display_option_rules']) && 
	            	!empty($import_settings['agent_display_option_rules']) 
	            )
	            {
		            switch ( $import_settings['agent_display_option'] )
		            {
		            	case "author_info":
		            	{
		            		foreach ( $import_settings['agent_display_option_rules'] as $rule )
		            		{
		            			$value_in_feed_to_check = '';
		            			switch ( $rule['field'] )
		            			{
		            				default:
		            				{
		            					$value_in_feed_to_check = $property[$rule['field']];
		            				}
		            			}

		            			if ( $value_in_feed_to_check == $rule['equal'] || $rule['equal'] == '*' )
		            			{
		            				// set post author
		            				$my_post = array(
								    	'ID'          	 => $post_id,
								    	'post_author'    => $rule['reult'],
								  	);

								 	// Update the post into the database
								    wp_update_post( $my_post, true );

		            				break; // Rule matched. Lets not do anymore
		            			}
		            		}
		            		break;
		            	}
		            	case "agent_info":
		            	{
		            		foreach ( $import_settings['agent_display_option_rules'] as $rule )
		            		{
		            			$value_in_feed_to_check = '';
		            			switch ( $rule['field'] )
		            			{
		            				default:
		            				{
		            					$value_in_feed_to_check = $property[$rule['field']];
		            				}
		            			}

		            			if ( $value_in_feed_to_check == $rule['equal'] || $rule['equal'] == '*' )
		            			{
		            				update_post_meta( $post_id, 'fave_agents', $rule['result'] );
		            				break; // Rule matched. Lets not do anymore
		            			}
		            		}
		            		break;
		            	}
		            	case "agency_info":
		            	{
		            		foreach ( $import_settings['agent_display_option_rules'] as $rule )
		            		{
		            			$value_in_feed_to_check = '';
		            			switch ( $rule['field'] )
		            			{
		            				default:
		            				{
		            					$value_in_feed_to_check = $property[$rule['field']];
		            				}
		            			}

		            			if ( $value_in_feed_to_check == $rule['equal'] || $rule['equal'] == '*' )
		            			{
		            				update_post_meta( $post_id, 'fave_property_agency', $rule['result'] );
		            				break; // Rule matched. Lets not do anymore
		            			}
		            		}
		            		break;
		            	}
		            }
	        	}
	        	
	            //turn bullets into property features
	            /*$feature_term_ids = array();
	            if ( isset($property['features']) && is_array($property['features']) && !empty($property['features']) )
				{
					foreach ( $property['features'] as $feature )
					{
						$term = term_exists( trim($feature), 'property_feature');
						if ( $term !== 0 && $term !== null && isset($term['term_id']) )
						{
							$feature_term_ids[] = (int)$term['term_id'];
						}
						else
						{
							$term = wp_insert_term( trim($feature), 'property_feature' );
							if ( is_array($term) && isset($term['term_id']) )
							{
								$feature_term_ids[] = (int)$term['term_id'];
							}
						}
					}
					if ( !empty($feature_term_ids) )
					{
						wp_set_object_terms( $post_id, $feature_term_ids, "property_feature" );
					}
					else
					{
						wp_delete_object_term_relationships( $post_id, "property_feature" );
					}
				}*/

				$mappings = ( isset($import_settings['mappings']) && is_array($import_settings['mappings']) && !empty($import_settings['mappings']) ) ? $import_settings['mappings'] : array();

				// status taxonomies
				if ( $department == 'residential-sales' )
				{
					$taxonomy_mappings = ( isset($mappings['sales_status']) && is_array($mappings['sales_status']) && !empty($mappings['sales_status']) ) ? $mappings['sales_status'] : array();

					if ( isset($property['sellingStatus']) && !empty($property['sellingStatus']) )
					{
						if ( isset($taxonomy_mappings[$property['sellingStatus']]) && !empty($taxonomy_mappings[$property['sellingStatus']]) )
						{
							wp_set_object_terms( $post_id, $taxonomy_mappings[$property['sellingStatus']], "property_status" );
						}
						else
						{
							$this->log( 'Received status of ' . $property['sellingStatus'] . ' that isn\'t mapped in the import settings', $property['property_id'], $post_id );
						}
					}
				}
				else
				{
					$taxonomy_mappings = ( isset($mappings['lettings_status']) && is_array($mappings['lettings_status']) && !empty($mappings['lettings_status']) ) ? $mappings['lettings_status'] : array();

					if ( isset($property['letting']['status']) && !empty($property['letting']['status']) )
					{
						if ( isset($taxonomy_mappings[$property['letting']['status']]) && !empty($taxonomy_mappings[$property['letting']['status']]) )
						{
							wp_set_object_terms( $post_id, $taxonomy_mappings[$property['letting']['status']], "property_status" );
						}
						else
						{
							$this->log( 'Received status of ' . $property['letting']['status'] . ' that isn\'t mapped in the import settings', $property['property_id'], $post_id );
						}
					}
				}

				// property type taxonomies
				$taxonomy_mappings = ( isset($mappings['property_type']) && is_array($mappings['property_type']) && !empty($mappings['property_type']) ) ? $mappings['property_type'] : array();

				$property_type_ids = array();
                if ( isset($property['typeNames']) && is_array($property['typeNames']) && !empty($property['typeNames']) )
                {
                    foreach ( $property['typeNames'] as $bdp_type )
                    {
                        if ( !empty($taxonomy_mappings) && isset($taxonomy_mappings[$bdp_type]) )
                        {
                            $property_type_ids[] = $taxonomy_mappings[$bdp_type];
                        }
                        else
                        {
                            $this->log( 'Received property type of ' . $bdp_type . ' that isn\'t mapped in the import settings', $property['property_id'], $post_id );
                        }
                    }
                }
                if ( !empty($property_type_ids) )
                {
                    wp_set_post_terms( $post_id, $property_type_ids, 'property_type' );
                }

				// Location taxonomies
				$create_location_taxonomy_terms = isset( $import_settings['create_location_taxonomy_terms'] ) ? $import_settings['create_location_taxonomy_terms'] : false;

				$houzez_tax_settings = get_option('houzez_tax_settings', array() );
				
				$location_taxonomies = array();
				if ( !isset($houzez_tax_settings['property_city']) || ( isset($houzez_tax_settings['property_city']) && $houzez_tax_settings['property_city'] != 'disabled' ) )
				{
					$location_taxonomies[] = 'property_city';
				}
				if ( !isset($houzez_tax_settings['property_area']) || ( isset($houzez_tax_settings['property_area']) && $houzez_tax_settings['property_area'] != 'disabled' ) )
				{
					$location_taxonomies[] = 'property_area';
				}
				if ( !isset($houzez_tax_settings['property_state']) || ( isset($houzez_tax_settings['property_state']) && $houzez_tax_settings['property_state'] != 'disabled' ) )
				{
					$location_taxonomies[] = 'property_state';
				}

				foreach ( $location_taxonomies as $location_taxonomy )
				{
					$address_field_to_use = isset( $import_settings[$location_taxonomy . '_address_field'] ) ? $import_settings[$location_taxonomy . '_address_field'] : '';
					if ( !empty($address_field_to_use) )
					{
						$location_term_ids = array();
						if ( isset($property[$address_field_to_use]) && !empty($property[$address_field_to_use]) )
		            	{
		            		$term = term_exists( trim($property[$address_field_to_use]), $location_taxonomy);
							if ( $term !== 0 && $term !== null && isset($term['term_id']) )
							{
								$location_term_ids[] = (int)$term['term_id'];
							}
							else
							{
								if ( $create_location_taxonomy_terms === true )
								{
									$term = wp_insert_term( trim($property[$address_field_to_use]), $location_taxonomy );
									if ( is_array($term) && isset($term['term_id']) )
									{
										$location_term_ids[] = (int)$term['term_id'];
									}
								}
							}
		            	}
		            	if ( !empty($location_term_ids) )
						{
							wp_set_object_terms( $post_id, $location_term_ids, $location_taxonomy );
						}
						else
						{
							wp_delete_object_term_relationships( $post_id, $location_taxonomy );
						}
					}
				}

				// Images
				$media_ids = array();
				$new = 0;
				$existing = 0;
				$deleted = 0;
				$image_i = 0;
				$previous_media_ids = get_post_meta( $post_id, 'fave_property_images' );

				if (isset($property['images']) && !empty($property['images']))
                {
                    foreach ($property['images'] as $image)
                    {
                        if (
                            substr( strtolower($image['url']), 0, 2 ) == '//' ||
                            substr( strtolower($image['url']), 0, 4 ) == 'http'
                        )
                        {
							// This is a URL
							$url = $image['url'];
							$description = '';
						    
							$filename = basename( $url );

							// Check, based on the URL, whether we have previously imported this media
							$imported_previously = false;
							$imported_previously_id = '';
							if ( is_array($previous_media_ids) && !empty($previous_media_ids) )
							{
								foreach ( $previous_media_ids as $previous_media_id )
								{
									if ( 
										get_post_meta( $previous_media_id, '_imported_url', TRUE ) == $url
									)
									{
										$imported_previously = true;
										$imported_previously_id = $previous_media_id;
										break;
									}
								}
							}

							if ($imported_previously)
							{
								$media_ids[] = $imported_previously_id;

								if ( $description != '' )
								{
									$my_post = array(
								    	'ID'          	 => $imported_previously_id,
								    	'post_title'     => $description,
								    );

								 	// Update the post into the database
								    wp_update_post( $my_post );
								}

								if ( $image_i == 0 ) set_post_thumbnail( $post_id, $imported_previously_id );

								++$existing;

								++$image_i;
							}
							else
							{
								$tmp = download_url( $url );

							    $file_array = array(
							        'name' => $filename,
							        'tmp_name' => $tmp
							    );

							    // Check for download errors
							    if ( is_wp_error( $tmp ) ) 
							    {
							        $this->log_error( 'An error occurred whilst importing ' . $url . '-big.jpg. The error was as follows: ' . $tmp->get_error_message(), $property['property_id'], $post_id );
							    }
							    else
							    {
								    $id = media_handle_sideload( $file_array, $post_id, $description );

								    // Check for handle sideload errors.
								    if ( is_wp_error( $id ) ) 
								    {
								        @unlink( $file_array['tmp_name'] );
								        
								        $this->log_error( 'ERROR: An error occurred whilst importing ' . $url . '-big.jpg. The error was as follows: ' . $id->get_error_message(), $property['property_id'], $post_id );
								    }
								    else
								    {
								    	$media_ids[] = $id;

								    	update_post_meta( $id, '_imported_url', $url);

								    	if ( $image_i == 0 ) set_post_thumbnail( $post_id, $id );

								    	++$new;

								    	++$image_i;
								    }
								}
							}
						}
					}
				}
				if ( $media_ids != $previous_media_ids )
				{
					delete_post_meta( $post_id, 'fave_property_images' );
					foreach ( $media_ids as $media_id )
					{
						add_post_meta( $post_id, 'fave_property_images', $media_id );
					}
				}

				// Loop through $previous_media_ids, check each one exists in $media_ids, and if it doesn't then delete
				if ( is_array($previous_media_ids) && !empty($previous_media_ids) )
				{
					foreach ( $previous_media_ids as $previous_media_id )
					{
						if ( !in_array($previous_media_id, $media_ids) )
						{
							if ( wp_delete_attachment( $previous_media_id, TRUE ) !== FALSE )
							{
								++$deleted;
							}
						}
					}
				}

				$this->log( 'Imported ' . count($media_ids) . ' photos (' . $new . ' new, ' . $existing . ' existing, ' . $deleted . ' deleted)', $property['property_id'], $post_id );

				// Floorplans
				$floorplans = array();

				if (isset($property['floorPlanPdfPathPublic']) && !empty($property['floorPlanPdfPathPublic']))
                {
					$floorplans[] = array( 
						"fave_plan_title" => __( 'Floorplan', 'houzezpropertyfeed' ), 
						"fave_plan_image" => $property['floorPlanPdfPathPublic']
					);
				}

				if ( !empty($floorplans) )
				{
	                update_post_meta( $post_id, 'floor_plans', $floorplans );
	                update_post_meta( $post_id, 'fave_floor_plans_enable', 'enable' );
	            }
	            else
	            {
	            	update_post_meta( $post_id, 'fave_floor_plans_enable', 'disable' );
	            }

				$this->log( 'Imported ' . count($floorplans) . ' floorplans', $property['property_id'], $post_id );

				// Brochures and EPCs
				$media_ids = array();
				$new = 0;
				$existing = 0;
				$deleted = 0;
				$previous_media_ids = get_post_meta( $post_id, 'fave_attachments' );

				if (isset($property['brochurePathPublic']) && !empty($property['brochurePathPublic']))
                {
					// This is a URL
					$url = $property['brochurePathPublic'];
					$description = '';
				    
					$filename = basename( $url );

					// Check, based on the URL, whether we have previously imported this media
					$imported_previously = false;
					$imported_previously_id = '';
					if ( is_array($previous_media_ids) && !empty($previous_media_ids) )
					{
						foreach ( $previous_media_ids as $previous_media_id )
						{
							if ( 
								get_post_meta( $previous_media_id, '_imported_url', TRUE ) == $url
							)
							{
								$imported_previously = true;
								$imported_previously_id = $previous_media_id;
								break;
							}
						}
					}

					if ($imported_previously)
					{
						$media_ids[] = $imported_previously_id;

						if ( $description != '' )
						{
							$my_post = array(
						    	'ID'          	 => $imported_previously_id,
						    	'post_title'     => $description,
						    );

						 	// Update the post into the database
						    wp_update_post( $my_post );
						}

						++$existing;
					}
					else
					{
						$tmp = download_url( $url );

					    $file_array = array(
					        'name' => $filename,
					        'tmp_name' => $tmp
					    );

					    // Check for download errors
					    if ( is_wp_error( $tmp ) ) 
					    {
					        $this->log_error( 'An error occurred whilst importing ' . $url . '. The error was as follows: ' . $tmp->get_error_message(), $property['property_id'], $post_id );
					    }
					    else
					    {
						    $id = media_handle_sideload( $file_array, $post_id, $description, array(
                                'post_title' => __( 'Brochure', 'houzezpropertyfeed' ),
                                'post_excerpt' => $description
                            ) );

						    // Check for handle sideload errors.
						    if ( is_wp_error( $id ) ) 
						    {
						        @unlink( $file_array['tmp_name'] );
						        
						        $this->log_error( 'ERROR: An error occurred whilst importing ' . $url . '. The error was as follows: ' . $id->get_error_message(), $property['property_id'], $post_id );
						    }
						    else
						    {
						    	$media_ids[] = $id;

						    	update_post_meta( $id, '_imported_url', $url);

						    	++$new;
						    }
						}
					}
				}

				if (isset($property['epcDocPathPublic']) && !empty($property['epcDocPathPublic']))
                {
					// This is a URL
					$url = $property['epcDocPathPublic'];
					$description = '';
				    
					$filename = basename( $url );

					// Check, based on the URL, whether we have previously imported this media
					$imported_previously = false;
					$imported_previously_id = '';
					if ( is_array($previous_media_ids) && !empty($previous_media_ids) )
					{
						foreach ( $previous_media_ids as $previous_media_id )
						{
							if ( 
								get_post_meta( $previous_media_id, '_imported_url', TRUE ) == $url
							)
							{
								$imported_previously = true;
								$imported_previously_id = $previous_media_id;
								break;
							}
						}
					}

					if ($imported_previously)
					{
						$media_ids[] = $imported_previously_id;

						if ( $description != '' )
						{
							$my_post = array(
						    	'ID'          	 => $imported_previously_id,
						    	'post_title'     => $description,
						    );

						 	// Update the post into the database
						    wp_update_post( $my_post );
						}

						++$existing;
					}
					else
					{
						$tmp = download_url( $url );

					    $file_array = array(
					        'name' => $filename,
					        'tmp_name' => $tmp
					    );

					    // Check for download errors
					    if ( is_wp_error( $tmp ) ) 
					    {
					        $this->log_error( 'An error occurred whilst importing ' . $url . '. The error was as follows: ' . $tmp->get_error_message(), $property['property_id'], $post_id );
					    }
					    else
					    {
						    $id = media_handle_sideload( $file_array, $post_id, $description, array(
                                'post_title' => __( 'EPC', 'houzezpropertyfeed' ),
                                'post_excerpt' => $description
                            ) );

						    // Check for handle sideload errors.
						    if ( is_wp_error( $id ) ) 
						    {
						        @unlink( $file_array['tmp_name'] );
						        
						        $this->log_error( 'ERROR: An error occurred whilst importing ' . $url . '. The error was as follows: ' . $id->get_error_message(), $property['property_id'], $post_id );
						    }
						    else
						    {
						    	$media_ids[] = $id;

						    	update_post_meta( $id, '_imported_url', $url);

						    	++$new;
						    }
						}
					}
				}

				if ( $media_ids != $previous_media_ids )
				{
					delete_post_meta( $post_id, 'fave_attachments' );
					foreach ( $media_ids as $media_id )
					{
						add_post_meta( $post_id, 'fave_attachments', $media_id );
					}
				}

				// Loop through $previous_media_ids, check each one exists in $media_ids, and if it doesn't then delete
				if ( is_array($previous_media_ids) && !empty($previous_media_ids) )
				{
					foreach ( $previous_media_ids as $previous_media_id )
					{
						if ( !in_array($previous_media_id, $media_ids) )
						{
							if ( wp_delete_attachment( $previous_media_id, TRUE ) !== FALSE )
							{
								++$deleted;
							}
						}
					}
				}

				$this->log( 'Imported ' . count($media_ids) . ' brochures and EPCs (' . $new . ' new, ' . $existing . ' existing, ' . $deleted . ' deleted)', $property['property_id'], $post_id );
				
				update_post_meta( $post_id, 'fave_video_url', '' );
				update_post_meta( $post_id, 'fave_virtual_tour', '' );

				if ( isset($property['doVimeo']) && !empty($property['doVimeo']) )
				{
					// This is a URL
					$url = $property['doVimeo'];

					if ( strpos(strtolower($url), 'youtu') !== false || strpos(strtolower($url), 'vimeo') !== false )
					{
						update_post_meta( $post_id, 'fave_video_url', $url );
					}
					else
					{
						$iframe = '<iframe src="' . $url . '" style="border:0; height:360px; width:640px; max-width:100%" allowFullScreen="true"></iframe>';
						update_post_meta( $post_id, 'fave_virtual_tour', $iframe );
					}
				}

				if ( isset($property['shMovieLink']) && !empty($property['shMovieLink']) )
				{
					// This is a URL
					$url = $property['shMovieLink'];

					if ( strpos(strtolower($url), 'youtu') !== false || strpos(strtolower($url), 'vimeo') !== false )
					{
						update_post_meta( $post_id, 'fave_video_url', $url );
					}
					else
					{
						$iframe = '<iframe src="' . $url . '" style="border:0; height:360px; width:640px; max-width:100%" allowFullScreen="true"></iframe>';
						update_post_meta( $post_id, 'fave_virtual_tour', $iframe );
					}
				}

				do_action( "houzez_property_feed_property_imported", $post_id, $property, $this->import_id );
				do_action( "houzez_property_feed_property_imported_bdp", $post_id, $propert, $this->import_id );

				$post = get_post( $post_id );
				do_action( "save_post_property", $post_id, $post, false );
				do_action( "save_post", $post_id, $post, false );

				if ( $inserted_updated == 'updated' )
				{
					$this->compare_meta_and_taxonomy_data( $post_id, $property['property_id'], $metadata_before, $taxonomy_terms_before );
				}
			}

			++$property_row;

		} // end foreach property

		do_action( "houzez_property_feed_post_import_properties_bdp", $this->import_id );

		$this->import_end();

		$this->log( 'Finished import' );
	}

	public function remove_old_properties()
	{
		global $wpdb, $post;

		if ( !empty($this->properties) )
		{
			$import_refs = array();
			foreach ($this->properties as $property)
			{
				$import_refs[] = $property['property_id'];
			}

			$this->do_remove_old_properties( $import_refs );

			unset($import_refs);
		}
	}
}

}