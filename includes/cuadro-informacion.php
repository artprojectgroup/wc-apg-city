<?php
/**
 * Plantilla: cuadro de información del plugin en la página de ajustes.
 *
 * Muestra enlaces de donación, redes sociales, más plugins, contacto
 * y documentación/soporte dentro del panel de administración.
 *
 * Variables esperadas en el *scope* del include:
 * - $apg_city['donacion']    URL para donaciones.
 * - $apg_city['plugin']      Nombre legible del plugin.
 * - $apg_city['plugin_url']  URL de la documentación/sitio del plugin.
 * - $apg_city['soporte']     URL de la página de soporte.
 * - $apg_city['plugin_uri']  URL del listado en WordPress.org.
 *
 * @package   WC_APG_City
 * @global    array<string,string> $apg_city
 */

// Igual no deberías poder abrirme.
defined( 'ABSPATH' ) || exit;
?>
<div class="informacion">
  <!-- Fila: Donación y autor -->
  <div class="fila">
    <div class="columna">
      <p>
        <?php esc_html_e( 'If you enjoyed and find helpful this plugin, please make a donation:', 'wc-apg-city' ); ?>
      </p>
      <p><a href="<?php echo esc_url( $apg_city['donacion'] ); ?>" target="_blank" title="<?php esc_attr_e( 'Make a donation by ', 'wc-apg-city' ); ?>APG"><span class="genericon genericon-cart"></span></a></p>
    </div>
    <div class="columna">
      <p>Art Project Group:</p>
      <p><a href="http://www.artprojectgroup.es" title="Art Project Group" target="_blank"><strong class="artprojectgroup">APG</strong></a></p>
    </div>
  </div>
  <!-- Fila: Redes sociales y más plugins -->
  <div class="fila">
    <div class="columna">
      <p>
        <?php esc_html_e( 'Follow us:', 'wc-apg-city' ); ?>
      </p>
      <p><a href="https://www.facebook.com/artprojectgroup" title="<?php esc_attr_e( 'Follow us on ', 'wc-apg-city' ); ?>Facebook" target="_blank"><span class="genericon genericon-facebook-alt"></span></a> <a href="https://x.com/artprojectgroup" title="<?php esc_attr_e( 'Follow us on ', 'wc-apg-city' ); ?>X" target="_blank"><span class="genericon genericon-x-alt"></span></a> <a href="http://es.linkedin.com/in/artprojectgroup" title="<?php esc_attr_e( 'Follow us on ', 'wc-apg-city' ); ?>LinkedIn" target="_blank"><span class="genericon genericon-linkedin"></span></a></p>
    </div>
    <div class="columna">
      <p>
        <?php esc_html_e( 'More plugins:', 'wc-apg-city' ); ?>
      </p>
      <p><a href="http://profiles.wordpress.org/artprojectgroup/" title="<?php esc_attr_e( 'More plugins on ', 'wc-apg-city' ); ?>WordPress" target="_blank"><span class="genericon genericon-wordpress"></span></a></p>
    </div>
  </div>
  <!-- Fila: Contacto y Documentación/Soporte -->
  <div class="fila">
    <div class="columna">
      <p>
        <?php esc_html_e( 'Contact with us:', 'wc-apg-city' ); ?>
      </p>
      <p><a href="mailto:info@artprojectgroup.es" title="<?php esc_attr_e( 'Contact with us by ', 'wc-apg-city' ); ?>e-mail"><span class="genericon genericon-mail"></span></a> <a href="skype:artprojectgroup" title="<?php esc_attr_e( 'Contact with us by ', 'wc-apg-city' ); ?>Skype"><span class="genericon genericon-skype"></span></a></p>
    </div>
    <div class="columna">
      <p>
        <?php esc_html_e( 'Documentation and Support:', 'wc-apg-city' ); ?>
      </p>
      <p><a href="<?php echo esc_url( $apg_city['plugin_url'] ); ?>" title="<?php echo esc_attr( $apg_city['plugin'] ); ?>"><span class="genericon genericon-book"></span></a> <a href="<?php echo esc_url( $apg_city['soporte'] ); ?>" title="<?php esc_attr_e( 'Support', 'wc-apg-city' ); ?>"><span class="genericon genericon-cog"></span></a></p>
    </div>
  </div>
  <!-- Fila final: Valoración -->
  <div class="fila final">
    <div class="columna">
      <p>
        <?php
        // translators: %s is the plugin name (e.g., WC – APG City).
        echo esc_html( sprintf( __( 'Please, rate %s:', 'wc-apg-city' ), $apg_city['plugin'] ) );
        ?>
      </p>
      <?php echo wp_kses_post( apg_city_plugin( $apg_city['plugin_uri'] ) ); ?>
    </div>
    <div class="columna final"></div>
  </div>
</div>
