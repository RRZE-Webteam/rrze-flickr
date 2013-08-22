<?php
/**
 * Plugin Name: RRZE-Flickr
 * Description: Widget und Shortcode für Flickr.
 * Version: 1.0
 * Author: Rolf v. d. Forst
 * Author URI: http://blogs.fau.de/webworking/
 * License: GPLv2 or later
 */

/*
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

add_action( 'plugins_loaded', array( 'RRZE_Flickr', 'plugins_loaded' ) );

register_activation_hook( __FILE__, array( 'RRZE_Flickr', 'activation' ) );

class RRZE_Flickr {
    const version = '1.0'; // Plugin-Version

    const option_name = '_rrze_flickr';

    const version_option_name = '_rrze_flickr_version';

    const textdomain = 'rrze-flickr';
    
    const php_version = '5.3'; // Minimal erforderliche PHP-Version

    const wp_version = '3.6'; // Minimal erforderliche WordPress-Version
    
    public static function plugins_loaded() {
                
        load_plugin_textdomain( self::textdomain, false, sprintf( '%s/lang/', dirname( plugin_basename( __FILE__ ) ) ) );
        
        add_action( 'init', array( __CLASS__, 'update_version' ) );
        
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_plugin_styles' ) );
        
        add_action( 'admin_menu', array( __CLASS__, 'add_options_page' ) );

        add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );        
        
        add_shortcode( 'flickr', array( __CLASS__, 'flickr_shortcode') );
        
        add_action( 'widgets_init', array( __CLASS__, 'widgets_init' ) );
                
    }
    
    public static function activation() {
        self::version_compare();
        
        update_option( self::version_option_name , self::version );
    }
            
    private static function version_compare() {
        $error = '';
        
        if ( version_compare( PHP_VERSION, self::php_version, '<' ) ) {
            $error = sprintf( __( 'Ihre PHP-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die PHP-Version %s.', self::textdomain ), PHP_VERSION, self::php_version );
        }

        if ( version_compare( $GLOBALS['wp_version'], self::wp_version, '<' ) ) {
            $error = sprintf( __( 'Ihre Wordpress-Version %s ist veraltet. Bitte aktualisieren Sie mindestens auf die Wordpress-Version %s.', self::textdomain ), $GLOBALS['wp_version'], self::wp_version );
        }

        if( ! empty( $error ) ) {
            deactivate_plugins( plugin_basename( __FILE__ ), false, true );
            wp_die( $error );
        }
        
    }
    
    public static function update_version() {
		if( get_option( self::version_option_name, null ) != self::version )
			update_option( self::version_option_name , self::version );
    }    

    public static function get_options( $key = '' ) {
        $defaults = array(
            'api_key' => ''
        );

        $options = (array) get_option( self::option_name );
        $options = wp_parse_args( $options, $defaults );
        $options = array_intersect_key( $options, $defaults );

        if( ! empty( $key ) )
            return isset( $options[$key] ) ? $options[$key] : null;

        return $options;
    }
    
	public static function add_options_page() {

		add_options_page( __( 'Flickr', self::option_name ), __( 'Flickr', self::option_name ), 'manage_options', 'options-flickr', array( __CLASS__, 'options_flickr' ) );
        
	}
    
    public static function options_flickr() {
        ?>
        <div class="wrap">
            <?php screen_icon(); ?>
            <h2><?php echo esc_html( __( 'Einstellungen &rsaquo; Flickr', self::textdomain ) ); ?></h2>
            
            <form method="post" action="options.php">
                <?php 
                do_settings_sections( 'flickr_options' );
                settings_fields( 'flickr_options' );               
                submit_button();
                ?>
            </form>
            
        </div>
        <?php     
    }
    
    public static function admin_init() {        
        
        register_setting( 'flickr_options', self::option_name, array( __CLASS__, 'options_validate' ) );
                
        add_settings_section( 'options_flickr_section', false, '__return_false', 'flickr_options' );
        
        add_settings_field( 'options_flickr_api_key', __( 'API-Schlüssel', self::textdomain ), array( __CLASS__, 'field_flickr_api_key' ), 'flickr_options', 'options_flickr_section' );
    }
    
    public static function field_flickr_api_key() {
        $options = self::get_options();    
        ?>
        <input type="text" class="regular-text" value="<?php echo $options['api_key']; ?>" id="blogname" name="<?php printf( '%s[api_key]', self::option_name ); ?>">
        <?php
    }
    
    public static function options_validate( $input ) {
        $input['api_key'] = isset($input['api_key']) ? strip_tags($input['api_key']) : '';

        return $input;
    }
    
    public static function register_plugin_styles() {
        wp_register_style( 'rrze-flickr', sprintf( '%scss/rrze-flickr.css', plugin_dir_url( __FILE__ ) ) );
    }
    
    public static function flickr_shortcode($atts) {
        wp_enqueue_style( 'rrze-flickr' );
        do_action('enqueue_lightbox');
        
        extract( shortcode_atts( array(
                'screen_name' => '',
                'tags' => '',
                'number' => 6,
                'size' => 'm'
            ), $atts ) );  
        
        return self::get_photos('flickr-photos', $screen_name, $tags, $number, $size);
    }
    
    public static function widgets_init() {
        register_widget('RRZE_Flickr_Widget');
    }
    
    public static function get_photos($class, $screen_name = '', $tags = '', $number = 0, $size = 's') {
        $api_key = self::get_options('api_key');

        $size = in_array($size, array('s', 'm')) ? $size : 's';
        
        if($api_key) {
            
            $user_id_str = '';
            $tags_str = '';
            
            if($screen_name) {
                $user = wp_remote_get('http://api.flickr.com/services/rest/?method=flickr.people.findByUsername&api_key='.$api_key.'&username='.$screen_name.'&format=json');
                $user = trim($user['body'], 'jsonFlickrApi()');
                $user = json_decode($user);     
                if(!empty($user->user->id))
                    $user_id_str = sprintf('&user_id=%s', $user->user->id);
            }

            if($tags)
                $tags_str = sprintf('&tags=%s', $tags);

            $api_url = sprintf('http://api.flickr.com/services/rest/?method=flickr.photos.search&api_key=%1$s%2$s%3$s&per_page=%4$s&format=json&nojsoncallback=1', $api_key, $user_id_str, $tags_str, $number);
            $photos = wp_remote_get($api_url);
            $photos = trim($photos['body'], 'jsonFlickrApi()');
            $photos = json_decode($photos);

            if(!empty($photos->photos->total)) {
                $output = '';
                $output .= '<div class="' . $class . '">';
                $output .= '<ul>';
                    foreach($photos->photos->photo as $photo) { 
                        $photo = (array) $photo;
                        $url = "http://farm" . $photo['farm'] . ".static.flickr.com/" . $photo['server'] . "/" . $photo['id'] . "_" . $photo['secret'];
                        $output .= '<li>';
                            $output .= '<a href="' . $url . '_b.jpg" rel="lightbox">';
                                $output .= '<img src="' . $url . '_' . $size . '.jpg" alt="' . $photo['title'] . '" />';
                            $output .= '</a>';
                        $output .= '</li>';
                    }
                $output .= '</ul>';
                $output .= '</div>';
            } else {
                $output = '<p class="flickr-error">'._e('Es wurde keine Bilder gefunden.', RRZE_Flickr::textdomain).'</p>';
            }

        } else {
            $output = '<p class="flickr-error">'._e('API-Schlüssel fehlt.', RRZE_Flickr::textdomain).'</p>';
        }
        
        return $output;
        
    }
    
}

class RRZE_Flickr_Widget extends WP_Widget {
    
	public function __construct() {
        
		parent::__construct(
			'rrze-flickr',
			__('Bilder von Flickr', RRZE_Flickr::textdomain),
			array(
				'classname'		=>	'rrze-flickr-widget',
				'description'	=>	__('Bilder von Flickr anzeigen', RRZE_Flickr::textdomain)
			)
		);
	
		if ( is_active_widget( false, false, 'rrze-flickr', true ) )
            wp_register_style( 'rrze-flickr-widget', sprintf( '%scss/rrze-flickr-widget.css', plugin_dir_url( __FILE__ ) ) );
	}
    
	public function widget( $args, $instance ) {
	
		extract( $args, EXTR_SKIP );
		
		$title = apply_filters('widget_title', $instance['title']);       
		$screen_name = $instance['screen_name'];
        $tags = $instance['tags'];
		$number = $instance['number'];
		
		echo $before_widget;

		wp_enqueue_style( 'rrze-flickr-widget' );
		do_action('enqueue_lightbox');
        
		if($title)
			echo $before_title.$title.$after_title;
		
        echo RRZE_Flickr::get_photos('flickr-widget', $screen_name, $tags, $number);
        
		echo $after_widget;
		
	}
	
	public function update( $new_instance, $old_instance ) {	
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
        $instance['screen_name'] = strip_tags($new_instance['screen_name']);
        $instance['tags'] = strip_tags($new_instance['tags']);
		$number = (int) $new_instance['number'];
        $instance['number'] = !empty($number) ? $number : 6;
        
		return $instance;
		
	}
	
	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? esc_attr( $instance['title'] ) : '';
        $screen_name = isset( $instance['screen_name'] ) ? esc_attr( $instance['screen_name'] ) : '';
        $tags = isset( $instance['tags'] ) ? esc_attr( $instance['tags'] ) : '';
		$number = isset( $instance['number'] ) ? absint( $instance['number'] ) : 6;	
		?>
		<p>
            <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Titel:', RRZE_Flickr::textdomain ); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" />
        </p>
        
        <p>
            <label for="<?php echo $this->get_field_id('screen_name'); ?>"><?php _e( 'Flickr-Benutzername: ', RRZE_Flickr::textdomain ); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('screen_name'); ?>" name="<?php echo $this->get_field_name('screen_name'); ?>" type="text" value="<?php echo $screen_name; ?>" size="25" />
        </p>
     
        <p>
            <label for="<?php echo $this->get_field_id('tags'); ?>"><?php _e( 'Schlagworte: ', RRZE_Flickr::textdomain ); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('tags'); ?>" name="<?php echo $this->get_field_name('tags'); ?>" type="text" value="<?php echo $tags; ?>" size="25" />
            <small><?php _e( 'Trenne Schlagwörter durch Kommas.' ); ?></small>
        </p>
        
		<p>
            <label for="<?php echo $this->get_field_id( 'number' ); ?>"><?php _e( 'Anzahl der Bilder, die angezeigt werden: ', RRZE_Flickr::textdomain ); ?></label>
            <input id="<?php echo $this->get_field_id( 'number' ); ?>" name="<?php echo $this->get_field_name( 'number' ); ?>" type="text" value="<?php echo $number; ?>" size="3" />
        </p>
        
        <?php
		
	}
	
}
