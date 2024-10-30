<?php
namespace llas;

defined( 'WPINC' ) || exit;

?>
<style>
	.login-security-settings .field-col {
		display: inline-block;
		margin-right: 20px;
	}

	.login-security-settings .field-col-desc{
		min-width: 540px;
		max-width: calc(100% - 640px);
		vertical-align: top;
	}
</style>
<div class="wrap login-security-settings">
	<h2><?php echo __( 'Login Security Settings', 'limit-login-attempts-security' ); ?></h2>

	<form method="post" action="<?php menu_page_url( 'llas' ); ?>" class="llas-relative">
	<?php wp_nonce_field( 'llas' ); ?>

	<table class="form-table">
		<tr>
			<th scope="row" valign="top"><?php echo __( 'Whitelist', 'limit-login-attempts-security' ); ?></th>
			<td>
				<div class="field-col">
					<textarea name="whitelist" rows="15" cols="80"><?php echo esc_textarea( implode( "\n", Core::conf( 'whitelist', array() ) ) ); ?></textarea>
				</div>
				<div class="field-col field-col-desc">
					<p class="description">
						<?php echo __( 'Format', 'limit-login-attempts-security' ); ?>: <code>prefix1:value1, prefix2:value2</code>.
						<?php echo __( 'Both prefix and value are case insensitive.', 'limit-login-attempts-security' ); ?>
						<?php echo __( 'Spaces around comma/colon are allowed.', 'limit-login-attempts-security' ); ?>
						<?php echo __( 'One rule set per line.', 'limit-login-attempts-security' ); ?>
					</p>
					<p class="description">
						<?php echo __( 'Prefix list', 'limit-login-attempts-security' ); ?>: <code>ip</code>, <code><?php echo implode( '</code>, <code>', Core::PREFIX_SET ); ?></code>.
					</p>
					<p class="description"><?php echo __( 'IP prefix with colon is optional. IP value support wildcard (*).', 'limit-login-attempts-security' ); ?></p>
					<p class="description"><?php echo __( 'Example', 'limit-login-attempts-security' ); ?> 1) <code>ip:1.2.3.*</code></p>
					<p class="description"><?php echo __( 'Example', 'limit-login-attempts-security' ); ?> 2) <code>42.20.*.*, continent_code: NA</code> (<?php echo __( 'Dropped optional prefix', 'limit-login-attempts-security' ); ?> <code>ip:</code>)</p>
					<p class="description"><?php echo __( 'Example', 'limit-login-attempts-security' ); ?> 3) <code>continent: North America, country_code: US, subdivisions_code: NY</code></p>
					<p class="description"><?php echo __( 'Example', 'limit-login-attempts-security' ); ?> 4) <code>subdivisions_code: NY, postal: 10001</code></p>
					<p class="description">
						<button type="button" class="button button-link" id="llas_get_ip"><?php echo __( 'Get my GeoLocation data from', 'limit-login-attempts-security' ); ?> doapi.us</button>
						<code id="llas_mygeolocation">-</code>
					</p>
				</div>
			</td>
		</tr>

		<tr>
			<th scope="row" valign="top"><?php echo __( 'Blacklist', 'limit-login-attempts-security' ); ?></th>
			<td>
				<div class="field-col">
					<textarea name="blacklist" rows="15" cols="80"><?php echo esc_textarea( implode( "\n", Core::conf( 'blacklist', array() ) ) ); ?></textarea>
				</div>
				<div class="field-col field-col-desc">
					<p class="description">
						<?php echo sprintf( __( 'Same format as %s', 'limit-login-attempts-security' ), '<strong>' . __( 'Whitelist', 'limit-login-attempts-security' ) . '</strong>' ); ?>
					</p>
				</div>
			</td>
		</tr>
	</table>

	<p class="submit">
		<?php submit_button(); ?>
	</p>
	</form>
</div>

<script>
	jQuery( function( $ ) {
		$( '#llas_get_ip' ).click( function( e ) {
			$.ajax( {
				url: '<?php echo get_rest_url( null, 'llas/v1/myip' ); ?>',
				dataType: 'json',
				success: function( data ) {
					var html = [];
					$.each( data, function( k, v ) {
						 html.push( k + ':' + v );
					});
					$( '#llas_mygeolocation' ).html( html.join( ', ' ) ) ;
				}
			} ) ;
		} );
	} );
</script>
