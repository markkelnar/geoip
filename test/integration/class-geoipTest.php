<?php
/**
 * Unit tests for WP Engine GeoTarget Plugin
 *
 * @package wpengine-geoip
 */

namespace WPEngine;

/**
 * Unit tests for GeoIP class
 */
class GeoIp_Test extends \WP_UnitTestCase {

	/**
	 * Name of class under test
	 *
	 * @var string
	 */
	private static $class_name = 'WPEngine\GeoIp';

	/**
	 * Test if class can be instantiated
	 */
	public function test_can_instantiate() {
		$this->assertTrue( class_exists( self::$class_name ) );
		$geo = GeoIp::instance();
		$this->assertEquals( self::$class_name, get_class( $geo ) );
	}

    /**
     * Verify shortcodes are added to WordPress
     *
     * @param string $slug Name of shortcode
     *
     * @dataProvider data_shortcode_slugs
     */
    public function test_action_init_register_shortcodes($slug) {
        // Test that the plugin adds shortcodes via constructor side effect
        $this->assertTrue( shortcode_exists( $slug ) );

        // Remove the shortcodes and readd them using action_init_register_shortcodes
        remove_shortcode( $slug );
        $this->assertFalse( shortcode_exists( $slug ) );

        $geoip_mock = $this->getMockBuilder( self::$class_name )
            ->disableOriginalConstructor()
            ->setMethods( null )
            ->getMock();

        $geoip_mock->action_init_register_shortcodes();
        $this->assertTrue( shortcode_exists( $slug ) );
    }

    public function data_shortcode_slugs() {
        return array(
            [GeoIp::SHORTCODE_CONTINENT],
            [GeoIp::SHORTCODE_COUNTRY],
            [GeoIp::SHORTCODE_REGION],
            [GeoIp::SHORTCODE_CITY],
            [GeoIp::SHORTCODE_POSTAL_CODE],
            [GeoIp::SHORTCODE_LATITUDE],
            [GeoIp::SHORTCODE_LONGITUDE],
            [GeoIp::SHORTCODE_LOCATION],
            [GeoIp::SHORTCODE_CONTENT],
        );
    }


    /**
     * Test init
     *
     * @param string $hook Name of WordPress hook
     * @param string $method_name Method being hooked
     *
     * @dataProvider data_test_init
     */
    public function test_init($hook, $method_name) {
        // Test that the plugin adds the action via constructor side effect
        $geoip = GeoIp::instance();
        $priority = has_action( $hook, array( $geoip, $method_name ) );
        $this->assertInternalType( 'int', $priority );

        // remove and readd action using init then test
        remove_action( $hook, array( $geoip, $method_name ), $priority );
        $geoip::init();
        $this->assertInternalType( 'int', has_action( $hook, array( $geoip, $method_name ) ) );
    }

    public function data_test_init()
    {
        return array(
            ['init', 'setup'],
            ['init', 'action_init_register_shortcodes'],
            ['admin_enqueue_scripts', 'enqueue_admin_js'],
            ['admin_init', 'action_admin_init_check_plugin_dependencies'],
            ['admin_notices', 'action_admin_notices'],
            ['wp_ajax_geoip_dismiss_notice', 'ajax_action_dismiss_notice'],
        );
    }

    /**
     * Test enqueue_admin_js
     */
    public function test_enqueue_admin_js() {
        global $wp_scripts;

        // The script shouldn't be enqueued yet but alas dequeue before test
        wp_dequeue_script( GeoIp::TEXT_DOMAIN . '-admin-js' );

        $geoip_mock = $this->getMockBuilder( self::$class_name )
            ->disableOriginalConstructor()
            ->setMethods( array( 'helper_should_notice_show' ) )
            ->getMock();

        // Test when null/false return from helper_should_notice_show
        $geoip_mock->enqueue_admin_js();
        $is_enqueued = isset($wp_scripts->registered[GeoIp::TEXT_DOMAIN . '-admin-js']);
        $this->assertFalse($is_enqueued);

        $geoip_mock->expects( $this->once() )
            ->method( 'helper_should_notice_show' )
            ->willReturn( true );

        // Test for enqueue
        $geoip_mock->enqueue_admin_js();
        $is_enqueued = isset($wp_scripts->registered[GeoIp::TEXT_DOMAIN . '-admin-js']);
        $this->assertTrue($is_enqueued);

        // Test for localized script with nonce
        $nonce_var_string = false;
        $wp_dependency = $wp_scripts->registered[GeoIp::TEXT_DOMAIN . '-admin-js'];
        if (isset($wp_dependency->extra['data'])) {
            $nonce_var_string = $wp_dependency->extra['data'];
        }
        $this->assertContains('nonce', $nonce_var_string);
    }

	/**
	 * Test display of admin notice
	 */
	public function test_action_admin_notices() {
		ob_start();
		$geo = new self::$class_name();
		$geo->setup();
		$geo->action_admin_init_check_plugin_dependencies();
		$geo->action_admin_notices();
		$expected_output = array(
			'<div class="notice notice-warning wpengine-geoip is-dismissible" data-key="dependency">',
			'<p>',
			'WP Engine GeoTarget requires a <a href="http://wpengine.com/plans/?utm_source=wpengine-geoip">WP Engine account</a> with GeoTarget enabled for full functionality. Only testing queries will work on this site.',
			'</p>',
			'</div>',
		);
		$actual_output = $this->getActualOutput();
		ob_end_clean();
		foreach ( $expected_output as $line ) {
			$this->assertContains( $line, $actual_output );
		}
	}

	public function test_geos_array_callers() {
        $geoip_mock = $this->getMockBuilder( self::$class_name )
            ->disableOriginalConstructor()
            ->setMethods( null )
            ->getMock();

        // Test continent with null value
        $this->assertEmpty($geoip_mock->continent());

        // Test continent with value but while countries is not yet set
        $this->assertEmpty($geoip_mock->continent('EC'));

        $geoip_mock->countries = array(
            'EC' => array(
                'country'   => 'Ecuador',
                'continent' => 'SA',
            ),
        );

        // Test continent
        $this->assertEquals('SA', $geoip_mock->continent('EC'));

        $geoip_mock->geos = array(
            'countrycode' => 'abc',
            'region' => 'easy as',
            'city' => '123',
            'postalcode' => 'simple as',
            'latitude' => 'do-re-me',
            'longitude' => 'you and me',
        );

        // Test geos getters
        $this->assertEquals('abc', $geoip_mock->country());
        $this->assertEquals('easy as', $geoip_mock->region());
        $this->assertEquals('123', $geoip_mock->city());
        $this->assertEquals('simple as', $geoip_mock->postal_code());
        $this->assertEquals('do-re-me', $geoip_mock->latitude());
        $this->assertEquals('you and me', $geoip_mock->longitude());
    }

    public function test_do_shortcode_except_content() {
        $geoip_mock = $this->getMockBuilder( self::$class_name )
            ->disableOriginalConstructor()
            ->setMethods( null )
            ->getMock();

        $geoip_mock->geos = array();

        // Test do_shortcode_region without GeoIP->geos['region'] set
        $this->assertEquals('[' . GeoIp::SHORTCODE_REGION . ']', $geoip_mock->do_shortcode_region( null ));

        // Test do_shortcode_city without GeoIP->geos['city'] set
        $this->assertEquals('[' . GeoIp::SHORTCODE_CITY . ']', $geoip_mock->do_shortcode_city( null ));

        // Test do_shortcode_postal_code without GeoIP->geos['postalcode'] set
        $this->assertEquals('[' . GeoIp::SHORTCODE_POSTAL_CODE . ']', $geoip_mock->do_shortcode_postal_code( null ));

        // Test do_shortcode_latitude without GeoIP->geos['latitude'] set
        $this->assertEquals('[' . GeoIp::SHORTCODE_LATITUDE . ']', $geoip_mock->do_shortcode_latitude( null ));

        // Test do_shortcode_longitude without GeoIP->geos['longitude'] set
        $this->assertEquals('[' . GeoIp::SHORTCODE_LONGITUDE . ']', $geoip_mock->do_shortcode_longitude( null ));

        // Test do_shortcode_country without GeoIP->geos['countrycode'] set
        $this->assertEquals('[' . GeoIp::SHORTCODE_COUNTRY . ']', $geoip_mock->do_shortcode_country( null ));

        $geoip_mock->geos = array(
            'countrycode' => 'EC',
            'region' => 'easy as',
            'city' => '123',
            'postalcode' => 'simple as',
            'latitude' => 'do-re-me',
            'longitude' => 'you and me',
        );

        // Test do_shortcode_continent without GeoIP->countries set
        $this->assertEquals('[' . GeoIp::SHORTCODE_CONTINENT . ']', $geoip_mock->do_shortcode_continent( null ));

        $geoip_mock->countries = array(
            'EC' => array(
                'country'   => 'Ecuador',
                'continent' => 'SA',
            ),
        );

        // Test do_shortcode_continent
        $this->assertEquals('SA', $geoip_mock->do_shortcode_continent( null ));

        // do_shortcode_...
        $this->assertEquals('EC', $geoip_mock->do_shortcode_country( null ));
        $this->assertEquals('easy as', $geoip_mock->do_shortcode_region( null ));
        $this->assertEquals('123', $geoip_mock->do_shortcode_city( null ));
        $this->assertEquals('simple as', $geoip_mock->do_shortcode_postal_code( null ));
        $this->assertEquals('do-re-me', $geoip_mock->do_shortcode_latitude( null ));
        $this->assertEquals('you and me', $geoip_mock->do_shortcode_longitude( null ));
    }
}
