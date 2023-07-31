<?php
global $post;

$sortby = '';

if( houzez_is_half_map_search_result() ) {
	$sortby = houzez_option('search_default_order');
}

if( isset( $_GET['sortby'] ) ) {
    $sortby = $_GET['sortby'];
}
$sort_id = 'sort_properties';
if(houzez_is_half_map()) {
	$sort_id = 'ajax_sort_properties';
}
?>
<div class="sort-by">
	<div class="d-flex align-items-center">
		<div class="sort-by-title">
			<?php esc_html_e( 'Sắp xếp theo:', 'houzez' ); ?>
		</div><!-- sort-by-title -->  
		<select id="<?php echo esc_attr($sort_id); ?>" class="selectpicker form-control bs-select-hidden" title="<?php esc_html_e( 'Mặc định', 'houzez' ); ?>" data-live-search="false" data-dropdown-align-right="auto">
			<option value=""><?php esc_html_e( 'Mặc định', 'houzez' ); ?></option>
			<option <?php selected($sortby, 'a_price'); ?> value="a_price"><?php esc_html_e('Giá - Thấp tới cao', 'houzez'); ?></option>
            <option <?php selected($sortby, 'd_price'); ?> value="d_price"><?php esc_html_e('Giá - Cao tới thấp', 'houzez'); ?></option>
            
            <option <?php selected($sortby, 'featured_first'); ?> value="featured_first"><?php esc_html_e('Danh sách nổi bật đầu tiên', 'houzez'); ?></option>
            
            <option <?php selected($sortby, 'a_date'); ?> value="a_date"><?php esc_html_e('Ngày - Cũ đến Mới', 'houzez' ); ?></option>
            <option <?php selected($sortby, 'd_date'); ?> value="d_date"><?php esc_html_e('Ngày - Mới đến Cũ', 'houzez' ); ?></option>
		</select><!-- selectpicker -->
	</div><!-- d-flex -->
</div><!-- sort-by -->