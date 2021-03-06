<?php

declare( strict_types = 1 );
namespace Umlts\Config;

use Umlts\Config\Exceptions\FileNotReadableException;

/**
 * Loads configuration files
 *
 * The configuration files loaded can be changed via command line
 * arguments:
 *
 * @example php config-test.php --config:file=/tmp/test.config.json
 * @example php config-test.php --config:file=/tmp/test.config.json --config:file=/tmp/test2.config.ini
 * @example php config-test.php --config:file=http://localhost/test.config.json
 */
class Config {

    const OPT_NAMESPACE = 'config';

    /**
     * @var string $basedir
     */
    private $basedir = './';

    /**
     * Array holding all the configuration information.
     *
     * @var array $config
     */
    private $config = array();

    /**
     * The namespace in use
     *
     * @var array $ns
     */
    private $ns = array();

    /**
     * @var array $base_config_file
     */
    private $base_config_files = [
        'config/config.json',
        'config/config.yml',
        'config/config.ini',
    ];

    /**
     * @var bool $permit_root
     *   Is it allowed to get configuration data
     *   outside the namespace?
     */
    private $permit_root = TRUE;

    /**
     * Constructs the objects
     *
     * @param string $basedir
     *   Directory to look for the default configuration files
     * @param bool $ignore_default
     *   Do not look for the default configuration files
     */
    public function __construct( string $basedir = './', bool $ignore_default = FALSE ) {
        $this->basedir = substr( $basedir, -1 ) == '/' ? $basedir :  $basedir . '/';

        if ( $ignore_default === FALSE ) {
            $this->loadDefaultFiles();
        }

    }

    /**
     * Clones configuration
     *
     * @param string $key
     *   If namespace is given, the cloned Config object contains
     *   just the data underneath this namespace.
     * @return Config
     *   Cloned object, namespace set to root.
     */
    public function clone( string $key = '' ) : Config {
        $clone = new Config( $this->basedir, TRUE );
        $clone->setNamespace( '/' );
        $clone->merge( $this->get( $key ) );
        return $clone;
    }

    /**
     * Merges $data into the existing configuration. Overwrites settings
     * from $this->config with the values from $data if the settings
     * already exists.
     *
     * @return Config
     *   Returns this object
     */
    private function merge( array $data ) : Config {
        $this->config = array_replace_recursive( $this->config, $data );
        return $this;
    }

    /**
     * Removes comments from the config data.
     * This makes it possible to have comments in JSON files
     * which is usually not possible.
     *
     * Comment format:
     *   - If a "#" is the first character (except for white spaces
     *     and tabs) in a line, the complete line will be ignored.
     *
     * @param string $content
     *   String with comments
     * @return string
     *   Returns string without comments
     */
    public function removeComment( string $content ) : string {
        return preg_replace( '/^\s*#(.*?)\n/m', '', $content );
    }

    /**
     * Loads a configuration file.
     *
     * @param string $file
     *   Path to file. Maybe a stream, too.
     * @param string $format
     *   Sets the config file format. Mehtod does not try to guess
     *   the format on its own if given.
     *
     * @return Config
     *   Returns this object
     * @throws FileNotReadableException
     *   If file is not readable
     */
    public function load( string $file, string $format = '' ) : Config {

        $file_info = new \SplFileInfo( $file );
        if ( !$file_info->isReadable() ) {
            throw new FileNotReadableException( '"' . $file . '" is not readable.' );
        }

        try {
            $content = @file_get_contents( $file );
        } catch ( \Exception $e ) {
            throw new FileNotReadableException( 'Failed reading "' . $file . '".' );
        }

        if ( empty( $format ) ) {
            $format = $this->guessFormat( $file );
        }

        switch ( $format ) {
            case 'json':
                $this->parseJson( $content );
                break;
            case 'yml':
            case 'yaml':
                $this->parseYaml( $content );
                break;
            case 'ini':
                $this->parseIni( $content );
                break;
            default:
                throw new \InvalidArgumentException( 'Cannot guess the file format or the format does not exist.' );
                break;
        }

        return $this;
    }

    /**
     * Parses JSON configuration data and stores it
     *
     * @param string $input
     * @return Config
     */
    public function parseJson( string $content ) : Config {

        $data = json_decode( $this->removeComment( $content ), TRUE );
        if ( $data == NULL && json_last_error() != JSON_ERROR_NONE ) {
            throw new \RuntimeException( 'Error parsing JSON: ' . json_last_error_msg() );
        }
        $data = (array) $data;
        if ( !empty( $data ) ) { $this->merge( $data ); }

        return $this;
    }

    /**
     * Parses YAML configuration data and stores it
     *
     * @param string $input
     * @return Config
     */
    public function parseYaml( string $content ) : Config {

        $data = yaml_parse( $content );
        if ( $data === FALSE ) {
            throw new \RuntimeException( 'Error parsing YAML.' );
        }
        if ( !empty( $data ) ) { $this->merge( $data ); }

        return $this;
    }

    /**
     * Parses INI configuration data and stores it
     *
     * @param string $input
     * @return Config
     */
    public function parseIni( string $content ) : Config {

        $data = parse_ini_string( $content, TRUE );
        if ( $data === FALSE ) {
            throw new \RuntimeException( 'Error parsing Ini file.' );
        }
        if ( !empty( $data ) ) { $this->merge( $data ); }

        return $this;
    }


    /**
     * Loads the default configuration files.
     *
     * @return Config
     *   Returns this object
     */
    private function loadDefaultFiles() : Config {
        if ( empty( $this->base_config_files ) ) { return $this; }
        foreach( $this->base_config_files as $file ) {
            try {
                $this->load( $this->basedir . $file );
            } catch ( FileNotReadableException $e ) {
                // Ignore errors opening the default config files
                // Does not touch exceptions for parsing errors, though.
            }
        }
        return $this;
    }

    /**
     * Guesses the config file format.
     *
     * @return string
     *   Returns the file extension in lowercase. Returns an empty string
     *   if the extension could not be determined.
     */
    private function guessFormat( string $file ) : string {

        if ( preg_match( '/\.(json|yml|yaml|ini)($|\?|\#)/i', $file, $result ) ) {
            return strtolower( $result[1] );
        }

        return '';
    }

    /**
     * Sets the active namespace. All key in get calls will be prepended
     * by the namespace.
     *
     * @param $ns
     *   The namespace. Seperated by '/'.
     *
     * @return Config
     *   Returns this object.
     */
    public function setNamespace( string $ns ) : Config {
        if ( empty( $ns ) || $ns === '/' ) {
            $this->ns = [];
            return $this;
        }
        if ( !$this->exists( $ns ) ) {
            throw new \InvalidArgumentException( 'Namespace "' . $ns . '" not valid.' );
        }
        $this->ns = $this->prependKey( $ns );
        return $this;
    }

    /**
     * @return string
     */
    public function getNamespace() : string {
        return implode( '/', $this->ns );
    }

    /**
     * @return array
     */
    public function getNamespaceArray() : array {
        return $this->ns;
    }

    /**
     * Split the key into an array.
     *
     * @param string $key
     * @return string[]
     */
    private function getKeyArray( string $key ) : array {
        return explode( '/', $key );
    }

    /**
     * Prepends the key with the active namespace.
     *
     * @param string $key
     * @throws InvalidArgumentException
     *   If access to the root element is not permitted
     * @return array
     *   Returns the key array with namespace
     */
    private function prependKey( string $key ) : array {
        if ( empty( $key ) ) { return $this->getNamespaceArray(); }

        $key_array = $this->getKeyArray( $key );

        // Root?
        if ( strpos( $key, '/' ) === 0 ) {
            if ( $this->permit_root === FALSE
              && !empty( $this->getNamespaceArray() ) ) {
                throw new \InvalidArgumentException( 'Root access not permitted: "' . $key . '".' );
            }
            array_shift( $key_array );
            return $key_array;
        } else {
            return array_merge( $this->getNamespaceArray(), $key_array );
        }
    }

    /**
     * Gets an configuration setting.
     *
     * @param string $key
     *   The key for the setting. Seperated by '/'.
     * @param mixed $default
     *   The default value if key is not set.
     *
     * $return mixed
     *   Returns the value
     */
    public function get( string $key = '', $default = NULL ) {

        $key_array = $this->prependKey( $key );

        $value = $this->config;

        foreach ( $key_array as $k ) {

            if ( !isset( $value[ $k ] ) ) {
                if ( isset( $default ) ) {
                    return $default;
                } else {
                    throw new \InvalidArgumentException( 'Setting with the key ' . implode( '/', $key_array ) . ' does not exist.' );
                }
            }

            $value = $value[ $k ];
        }
        return $value;
    }

    /**
     * Checks if a setting for a key exists.
     *
     * @param string $key
     *   The key for the setting. Seperated by '/'.
     *
     * $return bool
     *   Returns if the setting exists
     */
    public function exists( string $key = '' ) : bool {

        $key_array = $this->prependKey( $key );

        $value = $this->config;

        foreach ( $key_array as $k ) {
            if ( !isset( $value[ $k ] ) ) { return FALSE; }
            $value = $value[ $k ];
        }

        return TRUE;
    }

    /**
     * Sets a configuration setting.
     *
     * @param string $key
     *   The key for the setting. Seperated by '/'.
     * @param mixed $value
     *   The value to set
     *
     * $return Config
     *   Returns this object
     */
    public function set( string $key, $value ) : Config {

        $new = $value;

        $key_array = array_reverse( $this->prependKey( $key ) );

        foreach ( $key_array as $k ) { $new = [ $k => $new ]; }

        $this->merge( $new );

        return $this;
    }

    /**
     * Magic __toString function.
     * @return string
     *   print_r output for the $this->config
     */
    public function __toString() {
        $config = $this->get();
        return print_r( $config, TRUE );
    }
}
