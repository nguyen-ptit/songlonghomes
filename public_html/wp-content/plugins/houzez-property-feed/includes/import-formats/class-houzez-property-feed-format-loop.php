<?php
/**
 * Class for managing the import process of a Loop JSON file
 *
 * @package WordPress
 */
if ( class_exists( 'Houzez_Property_Feed_Process' ) ) {

class Houzez_Property_Feed_Format_Loop extends Houzez_Property_Feed_Process {

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

		// List endpoints for getting both sales and lettings properties
		$loop_endpoints = array(
			'property/residential/sales/listed/100',
			'property/residential/lettings/listed/100',
		);

		foreach ( $loop_endpoints as $loop_endpoint )
		{
			$response = wp_remote_get( 'https://api.loop.software/' . $loop_endpoint, array( 'timeout' => 120, 'headers' => array(
				'Content-Type' => 'application/json',
				'x-api-key' => $import_settings['api_key'],
			) ) );

			if ( !is_wp_error($response) && is_array( $response ) )
			{
				$contents = $response['body'];

				$json = json_decode( $contents, TRUE );

				if ( $json !== FALSE && is_array($json) && isset($json['data']) )
				{
					$this->log("Found " . count($json['data']) . " properties in JSON from " . $loop_endpoint . " ready for parsing");

					foreach ($json['data'] as $property)
					{
						$this->properties[] = $property;
					}
				}
				else
				{
					// Failed to parse JSON
					$this->log_error( 'Failed to parse JSON file for ' . $loop_endpoint . ': ' . $contents );
					return false;
				}
			}
			else
			{
				$this->log_error( 'Failed to obtain JSON from ' . $loop_endpoint . ': ' . print_r($response, TRUE) );
				return false;
			}
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
        do_action( "houzez_property_feed_pre_import_properties_loop", $this->properties, $this->import_id );

        $this->properties = apply_filters( "houzez_property_feed_properties_due_import", $this->properties, $this->import_id );
        $this->properties = apply_filters( "houzez_property_feed_properties_due_import_loop", $this->properties, $this->import_id );

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
			$this->log( 'Importing property ' . $property_row . ' with reference ' . $property['listingId'], $property['listingId'] );

			$inserted_updated = false;

			$args = array(
	            'post_type' => 'property',
	            'posts_per_page' => 1,
	            'post_status' => 'any',
	            'meta_query' => array(
	            	array(
		            	'key' => $imported_ref_key,
		            	'value' => $property['listingId']
		            )
	            )
	        );
	        $property_query = new WP_Query($args);

	        if ( isset($property['address_DisplayAddress']) && trim($property['address_DisplayAddress']) != '' )
	        {
	        	$display_address = trim($property['address_DisplayAddress']);
	        }
	        else
	        {
		        $display_address = array();
		        if ( isset($property['address_Street']) && trim($property['address_Street']) != '' )
		        {
		        	$display_address[] = trim($property['address_Street']);
		        }
		        if ( isset($property['address_Locality']) && trim($property['address_Locality']) != '' )
		        {
		        	$display_address[] = trim($property['address_Locality']);
		        }
		        elseif ( isset($property['address_Town']) && trim($property['address_Town']) != '' )
		        {
		        	$display_address[] = trim($property['address_Town']);
		        }
		        elseif ( isset($property['address_District']) && trim($property['address_District']) != '' )
		        {
		        	$display_address[] = trim($property['address_District']);
		        }
		        $display_address = implode(", ", $display_address);
	       	}
	        
	        if ($property_query->have_posts())
	        {
	        	$this->log( 'This property has been imported before. Updating it', $property['listingId'] );

	        	// We've imported this property before
	            while ($property_query->have_posts())
	            {
	                $property_query->the_post();

	                $post_id = get_the_ID();

	                $my_post = array(
				    	'ID'          	 => $post_id,
				    	'post_title'     => wp_strip_all_tags( $display_address ),
				    	'post_excerpt'   => $property['shortDescription'],
				    	'post_content' 	 => $property['fullDescription'],
				    	'post_status'    => 'publish',
				  	);

				 	// Update the post into the database
				    $post_id = wp_update_post( $my_post, true );

				    if ( is_wp_error( $post_id ) ) 
					{
						$this->log_error( 'Failed to update post. The error was as follows: ' . $post_id->get_error_message(), $property['listingId'] );
					}
					else
					{
						$inserted_updated = 'updated';
					}
	            }
	        }
	        else
	        {
	        	$this->log( 'This property hasn\'t been imported before. Inserting it', $property['listingId'] );

	        	// We've not imported this property before
				$postdata = array(
					'post_excerpt'   => $property['shortDescription'],
					'post_content' 	 => $property['fullDescription'],
					'post_title'     => wp_strip_all_tags( $display_address ),
					'post_status'    => 'publish',
					'post_type'      => 'property',
					'comment_status' => 'closed',
				);

				$post_id = wp_insert_post( $postdata, true );

				if ( is_wp_error( $post_id ) ) 
				{
					$this->log_error( 'Failed to insert post. The error was as follows: ' . $post_id->get_error_message(), $property['listingId'] );
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

				$this->log( 'Successfully ' . $inserted_updated . ' post', $property['listingId'], $post_id );

				update_post_meta( $post_id, $imported_ref_key, $property['listingId'] );

				update_post_meta( $post_id, '_property_import_data', json_encode($property, JSON_PRETTY_PRINT) );

				$department = isset( $property['price'] ) ? 'residential-sales' : 'residential-lettings';

				$poa = false;
				if ( 
					isset($property['priceQualifier']) && 
					( 
						strpos(strtolower($property['priceQualifier']), 'application') !== FALSE || 
						strpos(strtolower($property['priceQualifier']), 'priceOnRequest') !== FALSE || 
						strpos(strtolower($property['priceQualifier']), 'poa') !== FALSE 
					)
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
                    update_post_meta( $post_id, 'fave_property_price_prefix', ( ( $department == 'residential-sales' && isset($property['priceQualifier']) && !in_array(strtolower($property['priceQualifier']), array('none')) ) ? $property['priceQualifier'] : '' ) );
                    update_post_meta( $post_id, 'fave_property_price', ( $department == 'residential-sales' ? $property['price'] : $property['rent'] ) );
                    update_post_meta( $post_id, 'fave_property_price_postfix', ( $department == 'residential-lettings' ? 'pcm' : '' ) );
                }

                update_post_meta( $post_id, 'fave_property_bedrooms', ( ( isset($property['bedrooms']) ) ? $property['bedrooms'] : '' ) );
	            update_post_meta( $post_id, 'fave_property_bathrooms', ( ( isset($property['bathrooms']) ) ? $property['bathrooms'] : '' ) );
	            update_post_meta( $post_id, 'fave_property_rooms', ( ( isset($property['receptionRooms']) ) ? $property['receptionRooms'] : '' ) );
	            update_post_meta( $post_id, 'fave_property_garage', '' ); // need to look at parking
	            update_post_meta( $post_id, 'fave_property_id', $property['propertyRefId'] );

	            $address_parts = array();
	            if ( isset($property['address_Street']) && $property['address_Street'] != '' )
	            {
	                $address_parts[] = $property['address_Street'];
	            }
	            if ( isset($property['address_Locality']) && $property['address_Locality'] != '' )
	            {
	                $address_parts[] = $property['address_Locality'];
	            }
	            if ( isset($property['address_Town']) && $property['address_Town'] != '' )
	            {
	                $address_parts[] = $property['address_Town'];
	            }
	            if ( isset($property['address_County']) && $property['address_County'] != '' )
	            {
	                $address_parts[] = $property['address_County'];
	            }
	            if ( isset($property['address_Postcode']) && $property['address_Postcode'] != '' )
	            {
	                $address_parts[] = $property['address_Postcode'];
	            }

	            update_post_meta( $post_id, 'fave_property_map', '1' );
	            update_post_meta( $post_id, 'fave_property_map_address', implode(", ", $address_parts) );
	            $lat = '';
	            $lng = '';
	            if ( isset($property['latitude']) && !empty($property['latitude']) )
	            {
	                update_post_meta( $post_id, 'houzez_geolocation_lat', $property['latitude'] );
	                $lat = $property['latitude'];
	            }
	            if ( isset($property['longitude']) && !empty($property['longitude']) )
	            {
	                update_post_meta( $post_id, 'houzez_geolocation_long', $property['longitude'] );
	                $lng = $property['longitude'];
	            }
	            update_post_meta( $post_id, 'fave_property_location', $lat . "," . $lng . ",14" );
	            update_post_meta( $post_id, 'fave_property_country', 'GB' );
	            
	            $address_parts = array();
	            if ( isset($property['address_Street']) && $property['address_Street'] != '' )
	            {
	                $address_parts[] = $property['address_Street'];
	            }
	            update_post_meta( $post_id, 'fave_property_address', implode(", ", $address_parts) );
	            update_post_meta( $post_id, 'fave_property_zip', ( ( isset($property['address_Postcode']) ) ? $property['address_Postcode'] : '' ) );

	            add_post_meta( $post_id, 'fave_featured', '0', TRUE );
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
		            				case "api_key":
		            				{
		            					$value_in_feed_to_check = $import_settings['api_key'];
		            					break;
		            				}
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
		            				case "api_key":
		            				{
		            					$value_in_feed_to_check = $import_settings['api_key'];
		            					break;
		            				}
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
		            				case "api_key":
		            				{
		            					$value_in_feed_to_check = $import_settings['api_key'];
		            					break;
		            				}
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
	            $feature_term_ids = array();
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
				}

				$mappings = ( isset($import_settings['mappings']) && is_array($import_settings['mappings']) && !empty($import_settings['mappings']) ) ? $import_settings['mappings'] : array();

				// status taxonomies
				if ( $department == 'residential-sales' )
				{
					$taxonomy_mappings = ( isset($mappings['sales_status']) && is_array($mappings['sales_status']) && !empty($mappings['sales_status']) ) ? $mappings['sales_status'] : array();
				}
				else
				{
					$taxonomy_mappings = ( isset($mappings['lettings_status']) && is_array($mappings['lettings_status']) && !empty($mappings['lettings_status']) ) ? $mappings['lettings_status'] : array();
				}

				if ( isset($property['status']) && !empty($property['status']) )
				{
					if ( isset($taxonomy_mappings[$property['status']]) && !empty($taxonomy_mappings[$property['status']]) )
					{
						wp_set_object_terms( $post_id, $taxonomy_mappings[$property['status']], "property_status" );
					}
					else
					{
						$this->log( 'Received status of ' . $property['status'] . ' that isn\'t mapped in the import settings', $property['listingId'], $post_id );
					}
				}

				// property type taxonomies
				$taxonomy_mappings = ( isset($mappings['property_type']) && is_array($mappings['property_type']) && !empty($mappings['property_type']) ) ? $mappings['property_type'] : array();

				if ( isset($property['propertyType']) && !empty($property['propertyType']) )
				{
					if ( isset($taxonomy_mappings[$property['propertyType']]) && !empty($taxonomy_mappings[$property['propertyType']]) )
					{
						wp_set_object_terms( $post_id, $taxonomy_mappings[$property['propertyType']], "property_type" );
					}
					else
					{
						$this->log( 'Received property type of ' . $property['propertyType'] . ' that isn\'t mapped in the import settings', $property['listingId'], $post_id );
					}
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
						if ( isset($property['address_' . $address_field_to_use]) && !empty($property['address_' . $address_field_to_use]) )
		            	{
		            		$term = term_exists( trim($property['address_' . $address_field_to_use]), $location_taxonomy);
							if ( $term !== 0 && $term !== null && isset($term['term_id']) )
							{
								$location_term_ids[] = (int)$term['term_id'];
							}
							else
							{
								if ( $create_location_taxonomy_terms === true )
								{
									$term = wp_insert_term( trim($property['address_' . $address_field_to_use]), $location_taxonomy );
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

				if ( isset($property['images']) && is_array($property['images']) && !empty($property['images']) )
				{
					foreach ( $property['images'] as $image )
					{
						if ( 
							isset($image['url']) && $image['url'] != ''
							&&
							(
								substr( strtolower($image['url']), 0, 2 ) == '//' || 
								substr( strtolower($image['url']), 0, 4 ) == 'http'
							)
						)
						{
							// This is a URL
							$url = $image['url'];
							$description = '';
							$modified = $image['dateUpdated'];
						    
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
										&&
										(
											get_post_meta( $previous_media_id, '_modified', TRUE ) == '' 
											||
											(
												get_post_meta( $previous_media_id, '_modified', TRUE ) != '' &&
												get_post_meta( $previous_media_id, '_modified', TRUE ) == $modified
											)
										)
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
								$tmp = download_url( $url . '-big.jpg' );

							    $file_array = array(
							        'name' => $filename,
							        'tmp_name' => $tmp
							    );

							    // Check for download errors
							    if ( is_wp_error( $tmp ) ) 
							    {
							        $this->log_error( 'An error occurred whilst importing ' . $url . '-big.jpg. The error was as follows: ' . $tmp->get_error_message(), $property['listingId'], $post_id );
							    }
							    else
							    {
								    $id = media_handle_sideload( $file_array, $post_id, $description );

								    // Check for handle sideload errors.
								    if ( is_wp_error( $id ) ) 
								    {
								        @unlink( $file_array['tmp_name'] );
								        
								        $this->log_error( 'ERROR: An error occurred whilst importing ' . $url . '-big.jpg. The error was as follows: ' . $id->get_error_message(), $property['listingId'], $post_id );
								    }
								    else
								    {
								    	$media_ids[] = $id;

								    	update_post_meta( $id, '_imported_url', $url);
								    	update_post_meta( $id, '_modified', $modified);

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

				$this->log( 'Imported ' . count($media_ids) . ' photos (' . $new . ' new, ' . $existing . ' existing, ' . $deleted . ' deleted)', $property['listingId'], $post_id );

				// Floorplans
				$floorplans = array();

				if ( isset($property['floorPlans']) && is_array($property['floorPlans']) && !empty($property['floorPlans']) )
				{
					foreach ( $property['floorPlans'] as $floorplan )
					{
						if ( 
							isset($floorplan['url']) && $floorplan['url'] != ''
							&&
							(
								substr( strtolower($floorplan['url']), 0, 2 ) == '//' || 
								substr( strtolower($floorplan['url']), 0, 4 ) == 'http'
							)
						)
						{
							$floorplans[] = array( 
								"fave_plan_title" => __( 'Floorplan', 'houzezpropertyfeed' ), 
								"fave_plan_image" => $floorplan['url']
							);
						}
					}
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

				$this->log( 'Imported ' . count($floorplans) . ' floorplans', $property['listingId'], $post_id );

				// Brochures and EPCs
				$media_ids = array();
				$new = 0;
				$existing = 0;
				$deleted = 0;
				$previous_media_ids = get_post_meta( $post_id, 'fave_attachments' );

				if ( isset($property['brochure']) && is_array($property['brochure']) && !empty($property['brochure']) )
				{
					foreach ( $property['brochure'] as $brochure )
					{
						if ( 
							isset($brochure['url']) && $brochure['url'] != ''
							&&
							(
								substr( strtolower($brochure['url']), 0, 2 ) == '//' || 
								substr( strtolower($brochure['url']), 0, 4 ) == 'http'
							)
						)
						{
							// This is a URL
							$url = $brochure['url'];
							$description = '';
							$modified = $brochure['dateUpdated'];
						    
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
										&&
										(
											get_post_meta( $previous_media_id, '_modified', TRUE ) == '' 
											||
											(
												get_post_meta( $previous_media_id, '_modified', TRUE ) != '' &&
												get_post_meta( $previous_media_id, '_modified', TRUE ) == $modified
											)
										)
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
							        $this->log_error( 'An error occurred whilst importing ' . $url . '. The error was as follows: ' . $tmp->get_error_message(), $property['listingId'], $post_id );
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
								        
								        $this->log_error( 'ERROR: An error occurred whilst importing ' . $url . '. The error was as follows: ' . $id->get_error_message(), $property['listingId'], $post_id );
								    }
								    else
								    {
								    	$media_ids[] = $id;

								    	update_post_meta( $id, '_imported_url', $url);
								    	update_post_meta( $id, '_modified', $modified);

								    	++$new;
								    }
								}
							}
						}
					}
				}

				if ( isset($property['epc']) && is_array($property['epc']) && !empty($property['epc']) )
				{
					foreach ( $property['epc'] as $epc )
					{
						if ( 
							isset($epc['url']) && $epc['url'] != ''
							&&
							(
								substr( strtolower($epc['url']), 0, 2 ) == '//' || 
								substr( strtolower($epc['url']), 0, 4 ) == 'http'
							)
						)
						{
							// This is a URL
							$url = $epc['url'];
							$description = '';
							$modified = $epc['dateUpdated'];
						    
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
										&&
										(
											get_post_meta( $previous_media_id, '_modified', TRUE ) == '' 
											||
											(
												get_post_meta( $previous_media_id, '_modified', TRUE ) != '' &&
												get_post_meta( $previous_media_id, '_modified', TRUE ) == $modified
											)
										)
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
							        $this->log_error( 'An error occurred whilst importing ' . $url . '. The error was as follows: ' . $tmp->get_error_message(), $property['listingId'], $post_id );
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
								        
								        $this->log_error( 'ERROR: An error occurred whilst importing ' . $url . '. The error was as follows: ' . $id->get_error_message(), $property['listingId'], $post_id );
								    }
								    else
								    {
								    	$media_ids[] = $id;

								    	update_post_meta( $id, '_imported_url', $url);
								    	update_post_meta( $id, '_modified', $modified);

								    	++$new;
								    }
								}
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

				$this->log( 'Imported ' . count($media_ids) . ' brochures and EPCs (' . $new . ' new, ' . $existing . ' existing, ' . $deleted . ' deleted)', $property['listingId'], $post_id );
				
				update_post_meta( $post_id, 'fave_video_url', '' );
				update_post_meta( $post_id, 'fave_virtual_tour', '' );

				if ( isset($property['virtualTourUrls']) && is_array($property['virtualTourUrls']) && !empty($property['virtualTourUrls']) )
				{
					foreach ( $property['virtualTourUrls'] as $virtual_tour )
					{
						if ( 
							$virtual_tour != ''
							&&
							(
								substr( strtolower($virtual_tour), 0, 2 ) == '//' || 
								substr( strtolower($virtual_tour), 0, 4 ) == 'http'
							)
						)
						{
							// This is a URL
							$url = $virtual_tour;

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
					}
				}

				do_action( "houzez_property_feed_property_imported", $post_id, $property, $this->import_id );
				do_action( "houzez_property_feed_property_imported_loop", $post_id, $propert, $this->import_id );

				$post = get_post( $post_id );
				do_action( "save_post_property", $post_id, $post, false );
				do_action( "save_post", $post_id, $post, false );

				if ( $inserted_updated == 'updated' )
				{
					$this->compare_meta_and_taxonomy_data( $post_id, $property['listingId'], $metadata_before, $taxonomy_terms_before );
				}
			}

			++$property_row;

		} // end foreach property

		do_action( "houzez_property_feed_post_import_properties_loop", $this->import_id );

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
				$import_refs[] = $property['listingId'];
			}

			$this->do_remove_old_properties( $import_refs );

			unset($import_refs);
		}
	}
}

}