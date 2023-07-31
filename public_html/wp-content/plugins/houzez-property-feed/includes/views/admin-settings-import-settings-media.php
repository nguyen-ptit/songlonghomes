<h3><?php echo __( 'Media', 'houzezpropertyfeed' ); ?></h3>

<p><?php echo __( 'Specify which fields should be used for media', 'houzezpropertyfeed' ); ?>:</p>

<h3><?php echo __( 'Images', 'houzezpropertyfeed' ); ?></h3>

<table class="form-table">
	<tbody>
		<tr>
			<th><label for="image_fields"><?php echo __( 'Fields Containing Images', 'houzezpropertyfeed' ); ?></label></th>
			<td>
				<textarea name="image_fields" id="image_fields" placeholder="{/images/image[1]}&#10;{/images/image[2]}&#10;{/images/image[3]/url}|{/images/image[3]/caption}&#10;{/image[0]}.jpg" style="width:100%; height:120px; max-width:500px;"><?php echo isset($import_settings['image_fields']) ? $import_settings['image_fields'] : ''; ?></textarea>
				<div style="color:#999; font-size:13px; margin-top:5px;">
					Enter one image URL per line.<br>
					Separate with a pipe (|) character to specify the image caption.<br>
					Note: Uses the <a href="https://www.w3schools.com/xml/xpath_syntax.asp" target="_blank">XPath syntax</a>.
				</div>
			</td>
		</tr>
	</tbody>
</table>

<hr>

<h3><?php echo __( 'Floorplans', 'houzezpropertyfeed' ); ?></h3>

<table class="form-table">
	<tbody>
		<tr>
			<th><label for="floorplan_fields"><?php echo __( 'Fields Containing Floorplans', 'houzezpropertyfeed' ); ?></label></th>
			<td>
				<textarea name="floorplan_fields" id="floorplan_fields" placeholder="{/floorplans/floorplan[1]/url}|{/floorplans/floorplan[1]/caption}&#10;{/floorplans/floorplan[2]}" style="width:100%; height:120px; max-width:500px;"><?php echo isset($import_settings['floorplan_fields']) ? $import_settings['floorplan_fields'] : ''; ?></textarea>
				<div style="color:#999; font-size:13px; margin-top:5px;">
					Enter one floorplan URL per line.<br>
					Separate with a pipe (|) character to specify the floorplan caption.<br>
					Note: Uses the <a href="https://www.w3schools.com/xml/xpath_syntax.asp" target="_blank">XPath syntax</a>.
				</div>
			</td>
		</tr>
	</tbody>
</table>

<hr>

<h3><?php echo __( 'Documents (Brochures, EPCs etc)', 'houzezpropertyfeed' ); ?></h3>

<table class="form-table">
	<tbody>
		<tr>
			<th><label for="document_fields"><?php echo __( 'Fields Containing Documents', 'houzezpropertyfeed' ); ?></label></th>
			<td>
				<textarea name="document_fields" id="document_fields" placeholder="{/brochureURL}|Brochure&#10;{/epcs/epc[1]}&#10;{/documents/document[1]/url}|{/documents/document[1]/caption}" style="width:100%; height:120px; max-width:500px;"><?php echo isset($import_settings['document_fields']) ? $import_settings['document_fields'] : ''; ?></textarea>
				<div style="color:#999; font-size:13px; margin-top:5px;">
					Enter one document URL per line.<br>
					Separate with a pipe (|) character to specify the document caption.<br>
					Note: Uses the <a href="https://www.w3schools.com/xml/xpath_syntax.asp" target="_blank">XPath syntax</a>.
				</div>
			</td>
		</tr>
	</tbody>
</table>

<hr>

<h3><?php echo __( 'Media Options', 'houzezpropertyfeed' ); ?></h3>

<table class="form-table">
	<tbody>
		<tr>
			<th><label for="media_download_clause_url_change"><?php echo __( 'Download Media', 'houzezpropertyfeed' ); ?></label></th>
			<td style="padding-top:20px;">
				<div style="padding:3px 0"><label><input type="radio" name="media_download_clause" id="media_download_clause_always" value="always"<?php echo ( isset($import_settings['media_download_clause']) && $import_settings['media_download_clause'] == 'always' ) ? ' checked' : ''; ?>> Every time an import runs</label></div>
				<div style="padding:3px 0"><label><input type="radio" name="media_download_clause" id="media_download_clause_url_change" value="url_change"<?php echo ( !isset($import_settings['media_download_clause']) || ( isset($import_settings['media_download_clause']) && $import_settings['media_download_clause'] == 'url_change' ) ) ? ' checked' : ''; ?>> Only if media URL changes (recommended)</label></div>
			</td>
		</tr>
	</tbody>
</table>