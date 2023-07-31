<div class="notice notice-error no-format-notice inline"><p>Please select an import format in order to configure the following page.</p></div>

<h3><?php echo __( 'Additional Field Mapping', 'houzezpropertyfeed' ); ?></h3>

<p><?php echo __( 'Here you can do any additional field mapping to cater for non-standard mapping or to import into any custom fields you\'ve set up in the <a href="' . admin_url('admin.php?page=houzez_fbuilder') . '" target="_blank">Houzez Field Builder</a>', 'houzezpropertyfeed' ); ?>.</p>

<?php
	$houzez_fields = get_houzez_fields_for_field_mapping();

	// convert old-style rules to new style
	if ( isset($import_settings['field_mapping_rules']) && !empty($import_settings['field_mapping_rules']) )
	{
		$import_settings['field_mapping_rules'] = convert_old_field_mapping_to_new( $import_settings['field_mapping_rules'] );
	}
?>

<div class="rules-table-available-fields">
	<div class="rules-table">
		<div class="notice notice-info inline" id="missing_mandatory_xml_field_mapping" style="display:none"><p><?php echo __( 'No title, excerpt or content fields mapped. At least one of these is mandatory for a property to import.', 'houzezpropertyfeed' ); ?></p></div>
		<table class="form-table">
			<tbody>
				<tr>
					<th>Rules</th>
					<td>
		 				<div id="field_mapping_rule_template" style="display:none">
							<div class="field-mapping-rule">
								<div class="and-rules">
									<div class="or-rule">
										<div>
											If 
											<input type="text" name="field_mapping_rules[{rule_count}][field][]" value="">
											field in <span class="hpf-import-format-name"></span> feed
										</div>
										<div>
											Is equal to 
											<input type="text" name="field_mapping_rules[{rule_count}][equal][]" placeholder="Value in feed, or use * wildcard">
										</div>
										<div class="rule-actions">
											<a href="" class="add-and-rule-action"><span class="dashicons dashicons-plus2"></span> Add AND Rule</a> | <a href="" class="delete-action"><span class="dashicons dashicons-trash"></span> Delete Rule</a>
										</div>
									</div>
								</div>
								<div class="then">
									<div style="padding:20px 0; font-weight:600">THEN</div>
									<div>
										Set Houzez field
										<select name="field_mapping_rules[{rule_count}][houzez_field]" style="width:250px;">
											<option value=""></option>
											<?php
												if ( !empty($houzez_fields) )
												{
													foreach ( $houzez_fields as $key => $value )
													{
														echo '<option value="' . esc_attr($key) . '">' . esc_html($value['label']) . '</option>';
													}
												}
											?>
										</select>
										<div class="notice notice-info inline already-mapped-warning" style="margin-top:15px; display:none"><p>The <span class="already-mapped-field"></span> field is already mapped by default in the <span class="hpf-import-format-name"></span> feed. Creating a mapping here will overwrite this.</p></div>
									</div>
									<div>
										To
										<input type="text" name="field_mapping_rules[{rule_count}][result]" style="width:100%; max-width:340px;" value="" placeholder="Enter value or {field_name_here} to use value sent">
									</div>
								</div>
							</div>
						</div>

						<div id="field_mapping_rules">
							<?php
								if ( isset($import_settings['field_mapping_rules']) && !empty($import_settings['field_mapping_rules']) )
								{
									foreach ( $import_settings['field_mapping_rules'] as $i => $and_rules )
									{
							?>
							<div class="field-mapping-rule">
								<div class="and-rules">
									<?php $rule_i = 0; foreach ( $and_rules['rules'] as $or_rule ) { ?>
									<div class="or-rule">
										<div style="padding:20px 0; font-weight:600" class="and-label">AND</div>
										<div>
											If 
											<input type="text" name="field_mapping_rules[<?php echo $i; ?>][field][]" value="<?php echo esc_attr($or_rule['field']); ?>">
											field in <span class="hpf-import-format-name"></span> feed
										</div>
										<div>
											Is equal to 
											<input type="text" name="field_mapping_rules[<?php echo $i; ?>][equal][]" value="<?php echo esc_attr($or_rule['equal']); ?>" placeholder="Value in feed, or use * wildcard">
										</div>
										<div class="rule-actions">
											<a href="" class="add-and-rule-action"><span class="dashicons dashicons-plus-alt2"></span> Add AND Rule</a> | <a href="" class="delete-action"><span class="dashicons dashicons-trash"></span> Delete Rule</a>
										</div>
									</div>
									<?php ++$rule_i; } // end foreach AND rules ?>
								</div>
								<div class="then">
									<div style="padding:20px 0; font-weight:600">THEN</div>
									<div>
										Set Houzez field
										<select name="field_mapping_rules[<?php echo $i; ?>][houzez_field]" style="width:250px;">
											<option value=""></option>
											<?php
												if ( !empty($houzez_fields) )
												{
													foreach ( $houzez_fields as $key => $value )
													{
														echo '<option value="' . esc_attr($key) . '"';
														if ( $key == $and_rules['houzez_field'] ) { echo ' selected'; }
														echo '>' . esc_html($value['label']) . '</option>';
													}
												}
											?>
										</select> 
										<div class="notice notice-info inline already-mapped-warning" style="margin-top:15px; display:none"><p>The <span class="already-mapped-field"></span> field is already mapped by default in the <span class="hpf-import-format-name"></span> feed. Creating a mapping here will overwrite this.</p></div>
									</div>
									<div>
										To
										<input type="text" name="field_mapping_rules[<?php echo $i; ?>][result]" style="width:100%; max-width:340px;" value="<?php echo esc_attr($and_rules['result']); ?>" placeholder="Enter value or {field_name_here} to use value sent">
									</div>
								</div>
							</div>
							<?php
									}
								}
							?>
						</div>

						<a href="" class="button field-mapping-add-or-rule-button">Add Additional Field Mapping Rule</a>
					
					</td>
				</tr>
			</tbody>
		</table>
	</div>

	<div class="xml-rules-available-fields" style="display:none">
		<h3 style="margin-top:0">Fields found in the XML</h3>
		<p>Below is a list of the fields we found in the XML provided using the <a href="https://www.w3schools.com/xml/xpath_syntax.asp" target="_blank">XPath syntax</a>.</p>
		<p>You can <strong>click and drag</strong> the fields below into the rule.</p>
		<hr>
		<?php echo '<p id="no_nodes_found"' . ( ( !isset($import_settings['property_node_options']) || ( isset($import_settings['property_node_options']) && empty($import_settings['property_node_options']) ) ) ? '' : ' style="display:none"' ) . '><em>' . __( 'No XML fields found. Please go to the \'Import Format\' tab and click \'Fetch XML\' to obtain a list of these.', 'houzezpropertyfeed' ) . '</em></p>'; ?>
		<div id="xml-nodes-found">
			<?php
			if ( isset($import_settings['property_node_options']) && !empty($import_settings['property_node_options']) )
			{
				$options = json_decode($import_settings['property_node_options']);

				if ( !empty($options) )
				{
					foreach ( $options as $option )
					{
						$node_name = $option;
						if ( isset($import_settings['property_node']) && !empty($import_settings['property_node']) )
						{
							if ( strpos($node_name, $import_settings['property_node']) === false )
							{
								continue;
							}

							$node_name = str_replace($import_settings['property_node'], '', $node_name);
						}

						if ( !empty($node_name) )
						{
							echo '<a href="#">' . $node_name . '</a>';
						}
					}	
				}
			}
		?></div>
	</div>

	<div class="csv-rules-available-fields" style="display:none">
		<h3 style="margin-top:0">Fields found in the CSV</h3>
		<p>Below is a list of the fields we found in the CSV provided.</p>
		<p>You can <strong>click and drag</strong> the fields below into the rule.</p>
		<hr>
		<?php echo '<p id="no_fields_found"' . ( ( !isset($import_settings['property_field_options']) || ( isset($import_settings['property_field_options']) && empty($import_settings['property_node_options']) ) ) ? '' : ' style="display:none"' ) . '><em>' . __( 'No CSV fields found. Please go to the \'Import Format\' tab and click \'Fetch CSV\' to obtain a list of these.', 'houzezpropertyfeed' ) . '</em></p>'; ?>
		<div id="csv-fields-found">
			<?php
			if ( isset($import_settings['property_field_options']) && !empty($import_settings['property_field_options']) )
			{
				$options = json_decode($import_settings['property_field_options']);

				if ( !empty($options) )
				{
					foreach ( $options as $option )
					{
						$field_name = $option;

						if ( !empty($field_name) )
						{
							echo '<a href="#">' . $field_name . '</a>';
						}
					}	
				}
			}
		?></div>
	</div>
</div>

<script>
	var hpf_rule_count = <?php echo ( isset($import_settings['field_mapping_rules']) && !empty($import_settings['field_mapping_rules']) ) ? count($import_settings['field_mapping_rules']) : 0; ?>;
</script>